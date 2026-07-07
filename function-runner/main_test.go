package main

import (
	"reflect"
	"testing"
)

func TestSplitHandler(t *testing.T) {
	cases := map[string][2]string{
		"index.handler": {"index", "handler"},
		"handler":       {"index", "handler"},
		"app.main.run":  {"app.main", "run"},
		"":              {"index", "handler"},
	}
	for in, want := range cases {
		f, h := splitHandler(in)
		if f != want[0] || h != want[1] {
			t.Errorf("splitHandler(%q) = (%q,%q), want (%q,%q)", in, f, h, want[0], want[1])
		}
	}
}

func TestParseSandbox(t *testing.T) {
	if got := parseSandbox(""); got != nil {
		t.Errorf("empty => %v, want nil", got)
	}
	if got := parseSandbox(`["unshare","-rn","--"]`); !reflect.DeepEqual(got, []string{"unshare", "-rn", "--"}) {
		t.Errorf("json array => %v", got)
	}
	if got := parseSandbox("docker run"); !reflect.DeepEqual(got, []string{"docker", "run"}) {
		t.Errorf("space form => %v", got)
	}
}

func TestBuildCommandWrapsTimeoutAndSandbox(t *testing.T) {
	got := buildCommand([]string{"unshare", "-rn", "--"}, "node", "nodejs20", 30, 128, "/w", []string{"a", "b"})
	want := []string{"timeout", "--kill-after=5", "30", "unshare", "-rn", "--", "node", "a", "b"}
	if !reflect.DeepEqual(got, want) {
		t.Errorf("buildCommand = %v, want %v", got, want)
	}
}

func TestBuildCommandSubstitutesPlaceholders(t *testing.T) {
	got := buildCommand([]string{"--memory={memory_mb}m", "-v", "{workdir}:{workdir}", "img-{runtime}"},
		"node", "nodejs20", 5, 256, "/scratch/x", nil)
	joined := got[3:7] // after timeout --kill-after=5 5
	want := []string{"--memory=256m", "-v", "/scratch/x:/scratch/x", "img-nodejs20"}
	if !reflect.DeepEqual(joined, want) {
		t.Errorf("placeholders = %v, want %v", joined, want)
	}
}

func TestPoolKeyStableAndDistinct(t *testing.T) {
	base := poolKey("nodejs20", "index.handler", "CODE", map[string]any{"A": "1"})
	if base != poolKey("nodejs20", "index.handler", "CODE", map[string]any{"A": "1"}) {
		t.Error("poolKey not stable for identical inputs")
	}
	if base == poolKey("nodejs20", "index.handler", "CODE2", map[string]any{"A": "1"}) {
		t.Error("poolKey should differ when code differs")
	}
	if base == poolKey("nodejs20", "index.handler", "CODE", map[string]any{"A": "2"}) {
		t.Error("poolKey should differ when env differs")
	}
	if base == poolKey("python312", "index.handler", "CODE", map[string]any{"A": "1"}) {
		t.Error("poolKey should differ when runtime differs")
	}
}
