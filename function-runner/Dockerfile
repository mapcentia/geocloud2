# ---- build ----
FROM golang:1.23-bookworm AS build
WORKDIR /src
COPY go.mod ./
COPY main.go ./
COPY agents ./agents
RUN CGO_ENABLED=0 go build -buildvcs=false -ldflags "-s -w" -o /out/gc2-function-runner .

# ---- runtime ----
# Carries the runner + the language runtimes it execs (node, python). gVisor
# (runsc) is provided by the host container runtime: either run THIS container
# itself under runsc, or set GC2_RUNNER_SANDBOX to a `docker run --runtime=runsc`
# launcher and mount the docker socket.
FROM node:20-bookworm-slim
RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 coreutils util-linux \
    && rm -rf /var/lib/apt/lists/*
COPY --from=build /out/gc2-function-runner /usr/local/bin/gc2-function-runner
ENV GC2_RUNNER_ADDR=:8090
EXPOSE 8090
ENTRYPOINT ["/usr/local/bin/gc2-function-runner"]
