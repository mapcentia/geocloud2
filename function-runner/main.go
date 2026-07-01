// Command gc2-function-runner is the data-plane service that executes GC2
// functions in a sandbox. It is the production counterpart to the in-process
// PHP LocalFunctionRunner: it runs as its own (privileged) service so it can
// exec the runtime + a gVisor sandbox, which the web-server user cannot.
//
// The PHP control plane (HttpFunctionRunner) calls POST /invoke. Execution is
// identical in spirit to LocalFunctionRunner: materialise code + a per-runtime
// agent, run it under `timeout` wrapped by a configurable sandbox command, read
// the JSON result. For production set GC2_RUNNER_SANDBOX to a gVisor launcher
// (runsc / docker --runtime=runsc); for dev leave it empty or use unshare.
//
// Config (env):
//
//	GC2_RUNNER_ADDR     listen address (default ":8090")
//	GC2_RUNNER_TOKEN    shared secret; if set, requires "Authorization: Bearer <token>"
//	GC2_RUNNER_SANDBOX  JSON array command prefix, e.g.
//	                    ["docker","run","--runtime=runsc","--rm","--network=none",
//	                     "--memory={memory_mb}m","-v","{workdir}:{workdir}",
//	                     "-w","{workdir}","gc2/fn-{runtime}"]
//	                    placeholders: {workdir} {memory_mb} {timeout_s} {runtime}
//	GC2_RUNNER_WORKDIR  base scratch dir (default: os temp dir)
package main

import (
	"archive/zip"
	"bytes"
	"crypto/rand"
	"embed"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"time"
)

//go:embed agents/*
var agentFS embed.FS

const maxLogBytes = 64 * 1024

type runtimeCfg struct {
	Bin       string
	Ext       string
	Agent     string // one-shot agent (cold path)
	PoolAgent string // long-lived agent (warm pool)
}

var runtimes = map[string]runtimeCfg{
	"nodejs20":  {Bin: "node", Ext: "mjs", Agent: "node-bootstrap.cjs", PoolAgent: "node-pool-agent.cjs"},
	"python312": {Bin: "python3", Ext: "py", Agent: "python-bootstrap.py", PoolAgent: "python-pool-agent.py"},
}

type invokeRequest struct {
	Function map[string]any `json:"function"`
	Event    any            `json:"event"`
	// any (not map) so an empty PHP array json-encoded as [] is still accepted.
	Context any `json:"context"`
}

type invokeResponse struct {
	Status     string `json:"status"` // succeeded | failed
	Output     any    `json:"output"`
	Logs       string `json:"logs"`
	Error      string `json:"error,omitempty"`
	DurationMs int64  `json:"duration_ms"`
}

func main() {
	addr := env("GC2_RUNNER_ADDR", ":8090")
	token := os.Getenv("GC2_RUNNER_TOKEN")
	sandbox := parseSandbox(os.Getenv("GC2_RUNNER_SANDBOX"))
	baseDir := env("GC2_RUNNER_WORKDIR", os.TempDir())

	// Warm pool keeps runtime processes alive between invocations to skip cold
	// start; opt in with GC2_RUNNER_POOL=1.
	var warm *pool
	if os.Getenv("GC2_RUNNER_POOL") != "" {
		warm = newPool(sandbox, baseDir,
			envInt("GC2_RUNNER_POOL_MAX", 8),
			envInt("GC2_RUNNER_POOL_IDLE_PER_KEY", 4),
			time.Duration(envInt("GC2_RUNNER_POOL_IDLE_TTL_SEC", 60))*time.Second)
		log.Printf("warm pool enabled (maxConcurrent=%d)", warm.maxConcurrent)
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", func(w http.ResponseWriter, _ *http.Request) {
		_, _ = w.Write([]byte("ok"))
	})
	mux.HandleFunc("/invoke", func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
			return
		}
		if token != "" && r.Header.Get("Authorization") != "Bearer "+token {
			http.Error(w, "unauthorized", http.StatusUnauthorized)
			return
		}
		var req invokeRequest
		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			http.Error(w, "bad request", http.StatusBadRequest)
			return
		}
		var resp invokeResponse
		if warm != nil {
			resp = warm.invoke(req)
		} else {
			resp = invoke(req, sandbox, baseDir)
		}
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(resp)
	})

	log.Printf("gc2-function-runner listening on %s (sandbox=%v)", addr, sandbox)
	if err := http.ListenAndServe(addr, mux); err != nil {
		log.Fatal(err)
	}
}

func invoke(req invokeRequest, sandbox []string, baseDir string) invokeResponse {
	fn := req.Function
	runtimeKey := getString(fn, "runtime")
	rt, ok := runtimes[runtimeKey]
	if !ok {
		return invokeResponse{Status: "failed", Error: "Unsupported runtime: " + runtimeKey}
	}
	timeoutS := getInt(fn, "timeout_s", 30)
	if timeoutS < 1 {
		timeoutS = 1
	}
	memoryMb := getInt(fn, "memory_mb", 128)
	file, handler := splitHandler(getString(fn, "handler"))

	workDir := filepath.Join(baseDir, "gc2fn_"+randHex())
	if err := os.MkdirAll(workDir, 0o700); err != nil {
		return invokeResponse{Status: "failed", Error: "could not create work dir: " + err.Error()}
	}
	defer os.RemoveAll(workDir)

	start := time.Now()

	codePath, err := materialize(fn, file, rt.Ext, workDir)
	if err != nil {
		return invokeResponse{Status: "failed", Error: err.Error()}
	}
	agentPath := filepath.Join(workDir, "_agent_"+rt.Agent)
	eventPath := filepath.Join(workDir, "event.json")
	resultPath := filepath.Join(workDir, "result.json")

	agentBytes, err := agentFS.ReadFile("agents/" + rt.Agent)
	if err != nil {
		return invokeResponse{Status: "failed", Error: "agent missing: " + err.Error()}
	}
	eventBytes, _ := json.Marshal(req.Event)
	if err := writeAll(map[string][]byte{
		agentPath: agentBytes,
		eventPath: eventBytes,
	}); err != nil {
		return invokeResponse{Status: "failed", Error: err.Error()}
	}

	args := buildCommand(sandbox, rt.Bin, runtimeKey, timeoutS, memoryMb, workDir,
		[]string{agentPath, codePath, handler, eventPath, resultPath})

	cmd := exec.Command(args[0], args[1:]...)
	cmd.Dir = workDir
	cmd.Env = buildEnv(fn, req.Context)
	var stdout, stderr bytes.Buffer
	cmd.Stdout, cmd.Stderr = &stdout, &stderr
	runErr := cmd.Run()
	durationMs := time.Since(start).Milliseconds()

	logs := truncate(strings.TrimSpace(stdout.String() + ifNonEmpty("\n", stderr.String()) + stderr.String()))
	exitCode := 0
	if cmd.ProcessState != nil {
		exitCode = cmd.ProcessState.ExitCode()
	} else if runErr != nil {
		exitCode = 1
	}

	if exitCode == 124 || exitCode == 137 {
		return invokeResponse{Status: "failed", Logs: logs, Error: "Timed out after " + strconv.Itoa(timeoutS) + "s", DurationMs: durationMs}
	}
	if exitCode == 0 {
		if data, err := os.ReadFile(resultPath); err == nil {
			var out any
			_ = json.Unmarshal(data, &out)
			return invokeResponse{Status: "succeeded", Output: out, Logs: logs, DurationMs: durationMs}
		}
	}
	errMsg := strings.TrimSpace(stderr.String())
	if errMsg == "" {
		errMsg = "Handler exited with code " + strconv.Itoa(exitCode)
	}
	return invokeResponse{Status: "failed", Logs: logs, Error: truncate(errMsg), DurationMs: durationMs}
}

// replacer substitutes sandbox-command placeholders.
func replacer(workDir string, memoryMb, timeoutS int, runtimeKey string) *strings.Replacer {
	return strings.NewReplacer(
		"{workdir}", workDir,
		"{memory_mb}", strconv.Itoa(memoryMb),
		"{timeout_s}", strconv.Itoa(timeoutS),
		"{runtime}", runtimeKey,
	)
}

// buildCommand assembles: timeout --kill-after=5 <timeout> <sandbox...> <bin> <args...>
func buildCommand(sandbox []string, bin, runtimeKey string, timeoutS, memoryMb int, workDir string, args []string) []string {
	repl := replacer(workDir, memoryMb, timeoutS, runtimeKey)
	out := []string{"timeout", "--kill-after=5", strconv.Itoa(timeoutS)}
	for _, t := range sandbox {
		out = append(out, repl.Replace(t))
	}
	out = append(out, bin)
	return append(out, args...)
}

func buildEnv(fn map[string]any, ctx any) []string {
	env := []string{"PATH=" + envOr("PATH", "/usr/local/bin:/usr/bin:/bin")}
	if raw, ok := fn["env"].(map[string]any); ok {
		for k, v := range raw {
			env = append(env, k+"="+toString(v))
		}
	}
	ctxJSON, _ := json.Marshal(ctx)
	env = append(env, "GC2_CONTEXT="+string(ctxJSON))
	return env
}

// materialize writes the function code into workDir and returns the entry file.
// Inline: a single source file "<file>.<ext>". Zip: extract the base64 bundle
// and resolve the handler's entry file within it.
func materialize(fn map[string]any, file, ext, workDir string) (string, error) {
	if getString(fn, "package") == "zip" {
		if err := extractZipBase64(getString(fn, "code"), workDir); err != nil {
			return "", err
		}
		entry := resolveEntry(workDir, file, ext)
		if entry == "" {
			return "", fmt.Errorf("handler entry file for %q not found in bundle", file)
		}
		return entry, nil
	}
	codePath := filepath.Join(workDir, file+"."+ext)
	if err := os.MkdirAll(filepath.Dir(codePath), 0o700); err != nil {
		return "", err
	}
	if err := os.WriteFile(codePath, []byte(getString(fn, "code")), 0o600); err != nil {
		return "", err
	}
	return codePath, nil
}

func extractZipBase64(b64, workDir string) error {
	data, err := base64.StdEncoding.DecodeString(strings.TrimSpace(b64))
	if err != nil {
		return fmt.Errorf("code is not valid base64 for a zip package")
	}
	zr, err := zip.NewReader(bytes.NewReader(data), int64(len(data)))
	if err != nil {
		return fmt.Errorf("could not open zip package: %w", err)
	}
	for _, f := range zr.File {
		if strings.HasPrefix(f.Name, "/") || strings.Contains(f.Name, "..") {
			return fmt.Errorf("unsafe path in zip: %s", f.Name)
		}
		target := filepath.Join(workDir, f.Name)
		if f.FileInfo().IsDir() {
			if err := os.MkdirAll(target, 0o700); err != nil {
				return err
			}
			continue
		}
		if err := os.MkdirAll(filepath.Dir(target), 0o700); err != nil {
			return err
		}
		rc, err := f.Open()
		if err != nil {
			return err
		}
		out, err := os.OpenFile(target, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, 0o600)
		if err != nil {
			rc.Close()
			return err
		}
		_, cErr := io.Copy(out, rc)
		out.Close()
		rc.Close()
		if cErr != nil {
			return cErr
		}
	}
	return nil
}

// resolveEntry finds the handler entry file in an extracted bundle. Node resolves
// .mjs (respecting package.json type), .js or .cjs; Python resolves .py.
func resolveEntry(workDir, file, ext string) string {
	cands := []string{ext}
	if ext == "mjs" {
		cands = []string{"mjs", "js", "cjs"}
	}
	for _, e := range cands {
		p := filepath.Join(workDir, file+"."+e)
		if fi, err := os.Stat(p); err == nil && !fi.IsDir() {
			return p
		}
	}
	return ""
}

func splitHandler(handler string) (string, string) {
	if handler == "" {
		return "index", "handler"
	}
	if i := strings.LastIndex(handler, "."); i >= 0 {
		return handler[:i], handler[i+1:]
	}
	return "index", handler
}

func parseSandbox(s string) []string {
	s = strings.TrimSpace(s)
	if s == "" {
		return nil
	}
	var arr []string
	if err := json.Unmarshal([]byte(s), &arr); err == nil {
		return arr
	}
	return strings.Fields(s) // fallback: space-separated
}

func writeAll(files map[string][]byte) error {
	for path, data := range files {
		if err := os.WriteFile(path, data, 0o600); err != nil {
			return err
		}
	}
	return nil
}

func truncate(s string) string {
	if len(s) <= maxLogBytes {
		return s
	}
	return s[:maxLogBytes] + "\n...[truncated]"
}

func getString(m map[string]any, key string) string {
	if v, ok := m[key]; ok {
		return toString(v)
	}
	return ""
}

func getInt(m map[string]any, key string, def int) int {
	switch v := m[key].(type) {
	case float64:
		return int(v)
	case int:
		return v
	case string:
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}

func toString(v any) string {
	switch t := v.(type) {
	case string:
		return t
	case float64:
		return strconv.FormatFloat(t, 'f', -1, 64)
	case bool:
		return strconv.FormatBool(t)
	case nil:
		return ""
	default:
		b, _ := json.Marshal(t)
		return string(b)
	}
}

func ifNonEmpty(sep, s string) string {
	if s == "" {
		return ""
	}
	return sep
}

func env(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}

func envOr(key, def string) string { return env(key, def) }

func envInt(key string, def int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}

func randHex() string {
	b := make([]byte, 8)
	_, _ = rand.Read(b)
	return hex.EncodeToString(b)
}
