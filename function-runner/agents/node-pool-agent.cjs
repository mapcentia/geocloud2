'use strict';
/*
 * GC2 pooled runtime agent (Node.js). Long-lived: loads the handler once, then
 * serves invocations in a loop. Protocol is newline-delimited JSON: one request
 * object per line on stdin ({event, context}), one response per line on stdout
 * ({status, output, logs, error, duration_ms}). Handler console output is
 * captured in-band into `logs` so stdout stays a clean protocol channel.
 *
 * argv: <codeFile> <handlerName>
 */
const fs = require('fs');
const readline = require('readline');
const { pathToFileURL } = require('url');

(async () => {
    const [, , codeFile, handlerName] = process.argv;
    const mod = await import(pathToFileURL(codeFile).href);
    const fn = mod[handlerName] || (mod.default && (mod.default[handlerName] || mod.default));
    if (typeof fn !== 'function') {
        process.stderr.write('Handler "' + handlerName + '" is not a function\n');
        process.exit(2);
    }

    const protocol = process.stdout;
    const rl = readline.createInterface({ input: process.stdin });

    for await (const line of rl) {
        if (!line) continue;
        let req;
        try { req = JSON.parse(line); } catch (_) { continue; }

        const start = Date.now();
        const logs = [];
        const original = { log: console.log, error: console.error, warn: console.warn, info: console.info, debug: console.debug };
        const capture = (...a) => logs.push(a.map((x) => (typeof x === 'string' ? x : JSON.stringify(x))).join(' '));
        console.log = console.error = console.warn = console.info = console.debug = capture;

        let resp;
        try {
            const result = await fn(req.event, req.context || {});
            resp = { status: 'succeeded', output: result === undefined ? null : result };
        } catch (e) {
            resp = { status: 'failed', error: e && e.stack ? e.stack : String(e) };
        } finally {
            Object.assign(console, original);
        }
        resp.logs = logs.join('\n');
        resp.duration_ms = Date.now() - start;
        protocol.write(JSON.stringify(resp) + '\n');
    }
})().catch((e) => {
    process.stderr.write((e && e.stack ? e.stack : String(e)) + '\n');
    process.exit(1);
});
