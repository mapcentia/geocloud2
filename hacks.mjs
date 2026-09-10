#!/usr/bin/env node
/*
 * hacks.mjs — apply the phpfastcache vendor patches (former Gruntfile shell:hacks).
 *
 * Copies the patched sources from docker/_hacks/ over the phpfastcache library
 * installed by composer. Must run after `composer install` (i.e. after
 * `node build.mjs production`). Run: node hacks.mjs
 */
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import fs from 'node:fs';

const ROOT = path.dirname(fileURLToPath(import.meta.url));
const R = (p) => path.resolve(ROOT, p);

const PFC = 'app/vendor/phpfastcache/phpfastcache/lib/Phpfastcache';

// [source dir, destination, destinationIsDir]
const COPIES = [
  ['docker/_hacks/pool', `${PFC}/Core/Pool`, true],
  ['docker/_hacks/proxy', `${PFC}/Proxy`, true],
  ['docker/_hacks/drivers/redis', `${PFC}/Drivers/Redis/Driver.php`, false],
  ['docker/_hacks/drivers/rediscluster', `${PFC}/Drivers/Rediscluster/Driver.php`, false],
];

let count = 0;
for (const [srcDir, dest, destIsDir] of COPIES) {
  const absSrcDir = R(srcDir);
  if (!fs.existsSync(absSrcDir)) {
    console.error(`hacks.mjs: source missing: ${srcDir}`);
    process.exit(1);
  }
  const files = fs.readdirSync(absSrcDir).filter((f) => fs.statSync(path.join(absSrcDir, f)).isFile());
  if (destIsDir) {
    fs.mkdirSync(R(dest), { recursive: true });
    for (const f of files) {
      fs.copyFileSync(path.join(absSrcDir, f), path.join(R(dest), f));
      count++;
    }
  } else {
    // Single-file destination (e.g. .../Driver.php).
    fs.mkdirSync(path.dirname(R(dest)), { recursive: true });
    for (const f of files) {
      fs.copyFileSync(path.join(absSrcDir, f), R(dest));
      count++;
    }
  }
}

console.log(`hacks.mjs: applied ${count} file(s)`);
