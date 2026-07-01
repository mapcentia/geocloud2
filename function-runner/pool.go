package main

import (
	"bufio"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"io"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"sync"
	"time"
)

// pool keeps long-lived, per-(function-identity) runtime processes alive so
// repeat invocations skip cold start. Each instance handles one invocation at a
// time; concurrency comes from running several instances. A semaphore caps
// simultaneous invocations; an idle reaper trims unused instances.
type pool struct {
	sandbox       []string
	baseDir       string
	maxConcurrent int
	maxIdlePerKey int
	idleTTL       time.Duration

	mu   sync.Mutex
	idle map[string][]*instance
	sem  chan struct{}
}

type instance struct {
	key      string
	cmd      *exec.Cmd
	stdin    io.WriteCloser
	stdout   *bufio.Reader
	workDir  string
	lastUsed time.Time
}

var errCallTimeout = errors.New("call timed out")

func newPool(sandbox []string, baseDir string, maxConcurrent, maxIdlePerKey int, idleTTL time.Duration) *pool {
	if maxConcurrent < 1 {
		maxConcurrent = 1
	}
	p := &pool{
		sandbox:       sandbox,
		baseDir:       baseDir,
		maxConcurrent: maxConcurrent,
		maxIdlePerKey: maxIdlePerKey,
		idleTTL:       idleTTL,
		idle:          map[string][]*instance{},
		sem:           make(chan struct{}, maxConcurrent),
	}
	go p.reapLoop()
	return p
}

func (p *pool) invoke(req invokeRequest) invokeResponse {
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

	// Cap simultaneous invocations.
	p.sem <- struct{}{}
	defer func() { <-p.sem }()

	key := poolKey(runtimeKey, getString(fn, "handler"), getString(fn, "code"), fn["env"])

	inst := p.take(key)
	if inst == nil {
		var err error
		inst, err = p.spawn(key, runtimeKey, rt, fn)
		if err != nil {
			return invokeResponse{Status: "failed", Error: "spawn failed: " + err.Error()}
		}
	}

	reqLine, _ := json.Marshal(map[string]any{"event": req.Event, "context": req.Context})
	start := time.Now()
	resp, err := inst.call(reqLine, time.Duration(timeoutS)*time.Second)
	durationMs := time.Since(start).Milliseconds()

	if err != nil {
		inst.kill()
		if errors.Is(err, errCallTimeout) {
			return invokeResponse{Status: "failed", Error: "Timed out after " + strconv.Itoa(timeoutS) + "s", DurationMs: durationMs}
		}
		return invokeResponse{Status: "failed", Error: "runtime instance died: " + err.Error(), DurationMs: durationMs}
	}
	if resp.DurationMs == 0 {
		resp.DurationMs = durationMs
	}
	p.put(inst)
	return resp
}

// take pops a warm idle instance for key, or nil.
func (p *pool) take(key string) *instance {
	p.mu.Lock()
	defer p.mu.Unlock()
	list := p.idle[key]
	if len(list) == 0 {
		return nil
	}
	inst := list[len(list)-1]
	p.idle[key] = list[:len(list)-1]
	return inst
}

// put returns an instance to the idle set, or kills it if the key is full.
func (p *pool) put(inst *instance) {
	inst.lastUsed = time.Now()
	p.mu.Lock()
	if len(p.idle[inst.key]) >= p.maxIdlePerKey {
		p.mu.Unlock()
		inst.kill()
		return
	}
	p.idle[inst.key] = append(p.idle[inst.key], inst)
	p.mu.Unlock()
}

func (p *pool) spawn(key, runtimeKey string, rt runtimeCfg, fn map[string]any) (*instance, error) {
	file, handler := splitHandler(getString(fn, "handler"))
	workDir := filepath.Join(p.baseDir, "gc2pool_"+randHex())
	if err := os.MkdirAll(workDir, 0o700); err != nil {
		return nil, err
	}
	codePath, err := materialize(fn, file, rt.Ext, workDir)
	if err != nil {
		os.RemoveAll(workDir)
		return nil, err
	}
	agentPath := filepath.Join(workDir, "_agent_"+rt.PoolAgent)
	agentBytes, err := agentFS.ReadFile("agents/" + rt.PoolAgent)
	if err != nil {
		os.RemoveAll(workDir)
		return nil, err
	}
	if err := writeAll(map[string][]byte{agentPath: agentBytes}); err != nil {
		os.RemoveAll(workDir)
		return nil, err
	}

	// No `timeout` wrapper: the process is long-lived; Go enforces per-call
	// deadlines and kills on timeout.
	args := buildPoolCommand(p.sandbox, rt.Bin, runtimeKey, fn, workDir, []string{agentPath, codePath, handler})
	cmd := exec.Command(args[0], args[1:]...)
	cmd.Dir = workDir
	cmd.Env = buildEnv(fn, nil)
	cmd.Stderr = io.Discard
	stdin, err := cmd.StdinPipe()
	if err != nil {
		os.RemoveAll(workDir)
		return nil, err
	}
	stdout, err := cmd.StdoutPipe()
	if err != nil {
		os.RemoveAll(workDir)
		return nil, err
	}
	if err := cmd.Start(); err != nil {
		os.RemoveAll(workDir)
		return nil, err
	}
	return &instance{
		key:     key,
		cmd:     cmd,
		stdin:   stdin,
		stdout:  bufio.NewReader(stdout),
		workDir: workDir,
	}, nil
}

// call sends one request line and reads one response line within timeout.
func (in *instance) call(reqLine []byte, timeout time.Duration) (invokeResponse, error) {
	if _, err := in.stdin.Write(append(reqLine, '\n')); err != nil {
		return invokeResponse{}, err
	}
	type result struct {
		line string
		err  error
	}
	ch := make(chan result, 1)
	go func() {
		line, err := in.stdout.ReadString('\n')
		ch <- result{line, err}
	}()
	select {
	case r := <-ch:
		if r.err != nil {
			return invokeResponse{}, r.err
		}
		var resp invokeResponse
		if err := json.Unmarshal([]byte(r.line), &resp); err != nil {
			return invokeResponse{}, err
		}
		return resp, nil
	case <-time.After(timeout):
		return invokeResponse{}, errCallTimeout
	}
}

func (in *instance) kill() {
	_ = in.stdin.Close()
	if in.cmd.Process != nil {
		_ = in.cmd.Process.Kill()
	}
	_, _ = in.cmd.Process.Wait()
	os.RemoveAll(in.workDir)
}

func (p *pool) reapLoop() {
	ticker := time.NewTicker(p.idleTTL)
	defer ticker.Stop()
	for range ticker.C {
		cutoff := time.Now().Add(-p.idleTTL)
		var dead []*instance
		p.mu.Lock()
		for key, list := range p.idle {
			kept := list[:0]
			for _, inst := range list {
				if inst.lastUsed.Before(cutoff) {
					dead = append(dead, inst)
				} else {
					kept = append(kept, inst)
				}
			}
			if len(kept) == 0 {
				delete(p.idle, key)
			} else {
				p.idle[key] = kept
			}
		}
		p.mu.Unlock()
		for _, inst := range dead {
			inst.kill()
		}
	}
}

// poolKey identifies reusable instances: same runtime + handler + code + env.
func poolKey(runtime, handler, code string, env any) string {
	h := sha256.New()
	h.Write([]byte(runtime + "\x00" + handler + "\x00" + code + "\x00"))
	if env != nil {
		envJSON, _ := json.Marshal(env) // Go sorts map keys → stable
		h.Write(envJSON)
	}
	return hex.EncodeToString(h.Sum(nil)[:16])
}

// buildPoolCommand: <sandbox...> <bin> <agent> <code> <handler>  (no timeout wrapper)
func buildPoolCommand(sandbox []string, bin, runtimeKey string, fn map[string]any, workDir string, args []string) []string {
	repl := replacer(workDir, getInt(fn, "memory_mb", 128), getInt(fn, "timeout_s", 30), runtimeKey)
	var out []string
	for _, t := range sandbox {
		out = append(out, repl.Replace(t))
	}
	out = append(out, bin)
	return append(out, args...)
}
