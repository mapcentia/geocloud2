'use strict';
/*
 * GC2 function runtime agent (Node.js).
 *
 * Loads the user handler, runs it with the event, writes the JSON result to a
 * file. Logs go to stdout/stderr; a non-zero exit signals failure. Mirrors the
 * AWS Lambda runtime-bootstrap model in miniature.
 *
 * argv: <codeFile> <handlerName> <eventFile> <resultFile>
 * env:  GC2_CONTEXT = JSON execution context (caller identity, token, base url)
 */
const fs = require('fs');
const { pathToFileURL } = require('url');

(async () => {
    const [, , codeFile, handlerName, eventFile, resultFile] = process.argv;

    let event = {};
    try {
        event = JSON.parse(fs.readFileSync(eventFile, 'utf8'));
    } catch (_) {
        event = {};
    }

    let context = {};
    try {
        context = JSON.parse(process.env.GC2_CONTEXT || '{}');
    } catch (_) {
        context = {};
    }

    const mod = await import(pathToFileURL(codeFile).href);
    const fn = mod[handlerName]
        || (mod.default && (mod.default[handlerName] || mod.default));

    if (typeof fn !== 'function') {
        console.error('Handler "' + handlerName + '" is not a function');
        process.exit(2);
    }

    const result = await fn(event, context);
    fs.writeFileSync(resultFile, JSON.stringify(result === undefined ? null : result));
})().catch((err) => {
    console.error(err && err.stack ? err.stack : String(err));
    process.exit(1);
});
