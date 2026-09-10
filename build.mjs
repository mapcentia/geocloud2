#!/usr/bin/env node
/*
 * build.mjs — front-end asset build for GC2, replacing the old Gruntfile.js.
 *
 * Usage:
 *   node build.mjs production   (default) — full production build
 *   node build.mjs dev                    — debug build with jshint
 *
 * It reproduces, 1:1, what `grunt production` / `grunt` (default) did, using the
 * same underlying libraries the grunt-* plugins wrapped, so the generated files
 * are byte-identical:
 *   less            -> less
 *   cssmin          -> clean-css        (concat + minify)
 *   jshint          -> jshint           (dev only)
 *   hogan templates -> hogan.js
 *   uglify          -> uglify-js        (mangle/compress off = reparse+concat)
 *   processhtml     -> htmlprocessor
 *   preprocess      -> preprocess
 *   cacheBust       -> ported from grunt-cache-bust
 *
 * The former shell steps live here too, except the phpfastcache patch which is
 * now hacks.mjs:
 *   shell:chown, shell:composer   -> runShell() (best-effort)
 *   shell:hacks                   -> hacks.mjs
 */
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import fs from 'node:fs';
import { createHash } from 'node:crypto';
import { execSync } from 'node:child_process';
import urlLib from 'node:url';

const require = createRequire(import.meta.url);
const less = require('less');
const CleanCSS = require('clean-css');
const Hogan = require('hogan.js');
const HTMLProcessor = require('htmlprocessor');
const preprocess = require('preprocess');

const ROOT = path.dirname(fileURLToPath(import.meta.url));
const mode = (process.argv[2] || 'production').toLowerCase();
const isProduction = mode === 'production';

// Resolve a repo-relative path to an absolute one.
const R = (p) => path.resolve(ROOT, p);
const read = (p) => fs.readFileSync(R(p), 'utf8');
const write = (p, data) => {
  fs.mkdirSync(path.dirname(R(p)), { recursive: true });
  fs.writeFileSync(R(p), data);
};
const log = (msg) => console.log(msg);

/* ------------------------------------------------------------------ config */
// Transcribed verbatim from Gruntfile.js.

const LESS_FILES = {
  'public/apps/widgets/gc2map/css/styles.css': 'public/apps/widgets/gc2map/less/styles.less',
};

const CSSMIN_FILES = {
  'public/apps/viewer/css/build/all.min.css': [
    'public/js/bootstrap3/css/bootstrap.min.css',
    'public/js/MultiLevelPushMenu/jquery.multilevelpushmenu.css',
    'public/apps/viewer/css/styles.css',
  ],
  'public/css/build/styles.min.css': [
    'public/js/bootstrap/css/bootstrap.icons.min.css',
    'public/css/jquery.plupload.queue.css',
    'public/css/styles.css',
  ],
  'public/apps/widgets/gc2map/css/build/all.min.css': [
    'public/apps/widgets/gc2map/css/styles.css',
    'public/js/leaflet/plugins/markercluster/MarkerCluster.css',
    'public/js/leaflet/plugins/markercluster/MarkerCluster.Default.css',
  ],
};

const JSHINT_OPTIONS = {
  funcscope: true, shadow: true, evil: true, validthis: true, asi: true,
  newcap: false, notypeof: false, eqeqeq: false, loopfunc: true, devel: false,
  eqnull: true,
};
const JSHINT_FILES = ['public/js/*.js'];

const UGLIFY_PUBLISH = {
  'public/js/leaflet/leaflet-plugins-all.js': [
    'public/js/leaflet/plugins/markercluster/leaflet.markercluster-src.js',
    'public/js/leaflet/plugins/Leaflet.heat/leaflet-heat.js',
    'public/js/leaflet/plugins/Leaflet.draw/leaflet.draw.js',
    'public/js/leaflet/plugins/Leaflet.label/leaflet.label.js',
    'public/js/leaflet/plugins/Leaflet.print/leaflet.print-src.js',
    'public/js/leaflet/plugins/Leaflet.Editable/Leaflet.Editable.js',
    'public/js/leaflet/plugins/Leaflet.GraphicScale/Leaflet.GraphicScale.min.js',
    'public/js/leaflet/plugins/Leaflet.Locate/Leaflet.Locate.js',
    'public/js/leaflet/plugins/Leaflet.Toolbar/leaflet.toolbar-src.js',
  ],
  'public/js/leaflet/cartodb-all.js': [
    'public/js/cartodbjs/cartodb.uncompressed.js',
    'public/js/leaflet/leaflet-plugins-all.js',
  ],
  'public/js/leaflet/leaflet-all.js': [
    'public/js/leaflet/leaflet-0.7.7-src.js',
    'public/js/leaflet/leaflet-plugins-all.js',
  ],
  'public/js/leaflet1/leaflet-1.2.0-all.js': [
    'public/js/leaflet1/leaflet.js',
    'public/js/leaflet1/plugins/Leaflet.Draw/leaflet.draw.js',
    'public/js/leaflet1/plugins/Leaflet.Editable/src/Leaflet.Editable.js',
    'public/js/leaflet1/plugins/Leaflet.GraphicScale/Leaflet.GraphicScale.min.js',
    'public/js/leaflet1/plugins/Leaflet.Locate/dist/L.Control.Locate.min.js',
    'public/js/leaflet1/plugins/Leaflet.Toolbar/dist/leaflet.toolbar.min.js',
  ],
  'public/api/v3/js/geocloud.min.js': ['public/api/v3/js/geocloud.js'],
  'public/apps/viewer/js/build/all.min.js': [
    'public/js/jquery/1.10.0/jquery.min.js',
    'public/js/bootstrap3/js/bootstrap.min.js',
    'public/js/hogan/hogan-2.0.0.js',
    'public/js/div/jRespond.js',
    'public/js/admin/common.js',
    'public/js/MultiLevelPushMenu/jquery.multilevelpushmenu.js',
    'public/apps/viewer/js/templates.js',
    'public/apps/viewer/js/viewer.js',
    'public/js/leaflet/leaflet-all.js',
  ],
  'public/js/admin/build/all.min.js': [
    'public/js/canvasResize/binaryajax.js',
    'public/js/canvasResize/exif.js',
    'public/js/canvasResize/canvasResize.js',
    'public/js/ext/adapter/ext/ext-base-debug.js',
    'public/js/ext/ext-all-debug.js',
    'public/js/ext/examples/ux/fileuploadfield/FileUploadField.js',
    'public/js/ext/examples/ux/Spinner.js',
    'public/js/ext/examples/ux/SpinnerField.js',
    'public/js/ext/examples/ux/CheckColumn.js',
    'public/js/ext/examples/ux/gridfilters/menu/RangeMenu.js',
    'public/js/ext/examples/ux/gridfilters/menu/ListMenu.js',
    'public/js/ext/examples/ux/superboxselect/SuperBoxSelect.js',
    'public/js/ext/examples/ux/gridfilters/GridFilters.js',
    'public/js/ext/examples/ux/gridfilters/filter/Filter.js',
    'public/js/ext/examples/ux/gridfilters/filter/StringFilter.js',
    'public/js/jquery/1.10.0/jquery.min.js',
    'public/js/openlayers/proj4js-combined.js',
    'public/js/GeoExt/script/GeoExt.js',
    'public/js/plupload/js/moxie.min.js',
    'public/js/plupload/js/plupload.min.js',
    'public/js/plupload/js/jquery.plupload.queue/jquery.plupload.queue.min.js',
    'public/js/admin/msg.js',
    'public/js/admin/admin.js',
    'public/js/admin/edittablestructure.js',
    'public/js/admin/elasticsearchmapping.js',
    'public/js/admin/editwmsclass.js',
    'public/js/admin/editwmslayer.js',
    'public/js/admin/edittilelayer.js',
    'public/js/admin/classwizards.js',
    'public/js/admin/addshapeform.js',
    'public/js/admin/addbitmapform.js',
    'public/js/admin/addrasterform.js',
    'public/js/admin/addfromscratch.js',
    'public/js/admin/addviewform.js',
    'public/js/admin/addosmform.js',
    'public/js/admin/addqgisform.js',
    'public/js/admin/colorfield.js',
    'public/js/admin/httpauthform.js',
    'public/js/admin/apikeyform.js',
    'public/js/admin/attributeform.js',
    'public/js/admin/filterfield.js',
    'public/js/admin/filterbuilder.js',
    'public/js/admin/comparisoncomboBox.js',
    'public/js/openlayers/defs/EPSG3857.js',
  ],
  'public/apps/widgets/gc2map/js/build/all.min.js': [
    'public/js/leaflet/leaflet-all.js',
    'public/js/openlayers/proj4js-combined.js',
    'public/js/bootstrap3/js/bootstrap.min.js',
    'public/js/hogan/hogan-2.0.0.js',
    'public/apps/widgets/gc2map/js/bootstrap-alert.js',
    'public/api/v3/js/geocloud.js',
    'public/apps/widgets/gc2map/js/main.js',
    'public/apps/widgets/gc2map/js/templates.js',
    'public/apps/widgets/gc2map/config/config.js',
  ],
};

const UGLIFY_DEVEL = {
  'public/apps/widgets/gc2map/js/build/all.min.js': [
    'public/js/leaflet/leaflet-all.js',
    'public/js/openlayers/proj4js-combined.js',
    'public/js/bootstrap3/js/bootstrap.min.js',
    'public/js/hogan/hogan-2.0.0.js',
    'public/apps/widgets/gc2map/js/bootstrap-alert.js',
    'public/api/v3/js/geocloud.js',
    'public/apps/widgets/gc2map/js/main.js',
    'public/apps/widgets/gc2map/js/templates.js',
    'public/apps/widgets/gc2map/config/config.js',
  ],
};

const HOGAN_FILES = {
  'public/apps/viewer/js/templates.js': ['public/apps/viewer/templates/body.tmpl'],
  'public/apps/widgets/gc2map/js/templates.js': [
    'public/apps/widgets/gc2map/templates/body.tmpl',
    'public/apps/widgets/gc2map/templates/body2.tmpl',
  ],
};

const PROCESSHTML_FILES = {
  'public/admin.php': 'public/admin.php',
  'public/apps/viewer/index.html': 'public/apps/viewer/index.html',
};

// dest -> src
const PREPROCESS_FILES = {
  'public/apps/widgets/gc2map/js/gc2map.js': 'public/apps/widgets/gc2map/js/gc2map.preprocessed.js',
  'public/api/v3/js/async_loader.js': 'public/api/v3/js/async_loader.preprocessed.js',
};

const CACHEBUST = {
  assets: ['js/admin/build/*', 'api/v1/js/*', 'api/v3/js/*', 'css/build/*', '/js/OpenLayers-2.12/OpenLayers.gc2.js'],
  encoding: 'utf8', algorithm: 'md5', length: 16, separator: '.', baseDir: 'public/',
  src: [
    'public/admin.php',
    'public/apps/viewer/index.html',
    'public/apps/widgets/gc2map/index.html',
    'public/api/v3/js/async_loader.js',
    'public/api/v3/js/geocloud.js',
    'public/apps/widgets/gc2map/js/gc2map.js',
  ],
};

/* ------------------------------------------------------------------- tasks */

async function taskLess() {
  for (const [dest, src] of Object.entries(LESS_FILES)) {
    const input = read(src);
    const output = await less.render(input, {
      filename: R(src),
      paths: [path.dirname(R(src))],
      compress: false,
    });
    write(dest, output.css);
  }
  log(`less: ${Object.keys(LESS_FILES).length} file(s)`);
}

function taskCssmin() {
  for (const [dest, srcs] of Object.entries(CSSMIN_FILES)) {
    const available = srcs.filter((f) => fs.existsSync(R(f))).map((f) => R(f));
    const out = new CleanCSS({}).minify(available);
    if (out.errors && out.errors.length) throw new Error(out.errors.join('\n'));
    write(dest, out.styles);
  }
  log(`cssmin: ${Object.keys(CSSMIN_FILES).length} bundle(s)`);
}

function taskJshint() {
  const { JSHINT } = require('jshint');
  const files = expandGlobs(JSHINT_FILES, ROOT).filter((f) => fs.statSync(f).isFile());
  let errors = 0;
  for (const abs of files) {
    JSHINT(fs.readFileSync(abs, 'utf8'), JSHINT_OPTIONS);
    const errs = (JSHINT.errors || []).filter(Boolean);
    if (errs.length) {
      errors += errs.length;
      for (const e of errs) {
        log(`${path.relative(ROOT, abs)}:${e.line}:${e.character} ${e.reason}`);
      }
    }
  }
  log(`jshint: ${files.length} file(s), ${errors} warning(s)`);
}

function taskHogan() {
  // Mirrors grunt-templates-hogan: namespace "Templates", key = basename
  // (Gruntfile's defaultName), templateOptions {asString:true}, tabs/newlines
  // stripped from the compiled string.
  const namespace = 'this["Templates"]';
  const declaration = 'this["Templates"] = this["Templates"] || {};';
  for (const [dest, srcs] of Object.entries(HOGAN_FILES)) {
    const output = [declaration];
    for (const src of srcs) {
      const filename = src.split('/').pop();
      let compiled = Hogan.compile(read(src), { asString: true });
      compiled = String(compiled).replace(/\t+|\n+/g, '');
      output.push(`${namespace}[${JSON.stringify(filename)}] = new Hogan.Template(${compiled});`);
    }
    write(dest, output.join('\n'));
  }
  log(`hogan: ${Object.keys(HOGAN_FILES).length} template file(s)`);
}

function uglifyBundle(files) {
  const UglifyJS = require('uglify-js');
  const available = files.filter((f) => fs.existsSync(R(f)));
  // grunt-contrib-uglify warns and skips a destination with no source files.
  if (available.length === 0) return null;
  const input = {};
  for (const f of available) input[f] = fs.readFileSync(R(f), 'utf8');
  const result = UglifyJS.minify(input, {
    compress: false,
    mangle: false,
    output: {},
    parse: {},
  });
  if (result.error) throw result.error;
  return result.code;
}

function taskUglify(targets) {
  let n = 0;
  for (const target of targets) {
    for (const [dest, srcs] of Object.entries(target)) {
      let code;
      try {
        code = uglifyBundle(srcs);
      } catch (e) {
        throw new Error(`uglify bundle failed: ${dest}\n  ${e.message}`);
      }
      if (code === null) {
        log(`uglify: skip ${dest} (no source files)`);
        continue;
      }
      write(dest, code);
      n++;
    }
  }
  log(`uglify: ${n} bundle(s)`);
}

function taskProcesshtml() {
  const html = new HTMLProcessor({ commentMarker: 'build', environment: 'dist', data: {} });
  for (const [dest, src] of Object.entries(PROCESSHTML_FILES)) {
    write(dest, html.process(R(src)));
  }
  log(`processhtml: ${Object.keys(PROCESSHTML_FILES).length} file(s)`);
}

function taskPreprocess(debug) {
  const context = { ...process.env };
  context.DEBUG = debug;
  context.NODE_ENV = context.NODE_ENV || 'development';
  for (const [dest, src] of Object.entries(PREPROCESS_FILES)) {
    const ctx = { ...context, src };
    const processed = preprocess.preprocess(read(src), ctx, {
      srcDir: path.dirname(R(src)),
      type: path.extname(src).slice(1),
    });
    write(dest, processed);
  }
  log(`preprocess: ${Object.keys(PREPROCESS_FILES).length} file(s) (DEBUG=${debug})`);
}

/* ----------------------------------------------------- cacheBust (ported) */
// Faithful port of grunt-cache-bust@1.7.0 with the options from Gruntfile.js
// (algorithm md5, length 16, separator '.', baseDir public/, createCopies true,
// queryString false).

function repeat(n, str) {
  return n > 0 ? str.repeat(n) : '';
}

function addFileHash(str, hash, separator) {
  const parsed = urlLib.parse(str);
  const pathToFile = parsed.pathname;
  const ext = path.extname(parsed.pathname);
  return (parsed.hostname ? parsed.protocol + parsed.hostname : '') +
    pathToFile.replace(ext, '') + separator + hash + ext;
}

function taskCacheBust() {
  const opts = CACHEBUST;
  let baseDir = opts.baseDir;
  if (!baseDir.endsWith('/')) baseDir += '/';
  const cwd = R(baseDir); // absolute public/

  // Build the asset map: expand globs relative to public/, hash each file.
  const assetFiles = expandGlobs(opts.assets, cwd)
    .map((abs) => path.relative(cwd, abs))
    .filter((rel) => { try { return fs.statSync(path.resolve(cwd, rel)).isFile(); } catch { return false; } })
    .sort()
    .reverse();

  const assetMap = {};
  for (const file of assetFiles) {
    const absPath = path.resolve(cwd, file);
    const hash = createHash(opts.algorithm).update(fs.readFileSync(absPath)).digest('hex').substring(0, opts.length);
    const newFilename = addFileHash(file, hash, opts.separator);
    fs.copyFileSync(absPath, path.resolve(cwd, newFilename)); // createCopies:true
    assetMap[file] = newFilename;
  }

  // Enclosing pairs (queryString:false adds the '?' variants so already-busted
  // references are matched and updated rather than doubled).
  let replaceEnclosedBy = [
    ['"', '"'], ["'", "'"], ['(', ')'], ['=', '>'], ['=', ' '], ['"', ' '], [' ', ' '],
  ];
  replaceEnclosedBy = replaceEnclosedBy.concat(replaceEnclosedBy.map((reb) => [reb[0], '?']));

  for (const srcRel of opts.src) {
    const filepath = R(srcRel);
    let markup = fs.readFileSync(filepath, 'utf8');
    const baseAbs = cwd + '/';
    const relativeFileDir = path.dirname(filepath).substr(baseAbs.length);
    const fileDepth = relativeFileDir !== '' ? relativeFileDir.split('/').length : 0;
    const baseDirs = filepath.substr(baseAbs.length).split('/');

    for (const [original, hashed] of Object.entries(assetMap)) {
      const replace = [
        ['/' + original, '/' + hashed],
        [repeat(fileDepth, '../') + original, repeat(fileDepth, '../') + hashed],
      ];
      const originalDirParts = path.dirname(original).split('/');
      for (let i = 1; i <= fileDepth; i++) {
        const fileDir = originalDirParts.slice(0, i).join('/');
        const bDir = baseDirs.slice(0, i).join('/');
        if (fileDir === bDir) {
          const originalFilename = path.basename(original);
          const hashedFilename = path.basename(hashed);
          let dir = repeat(fileDepth - 1, '../') + originalDirParts.slice(i).join('/');
          if (!dir.endsWith('/')) dir += '/';
          replace.push([dir + originalFilename, dir + hashedFilename]);
        }
      }
      for (const [orig, hsh] of replace) {
        for (const reb of replaceEnclosedBy) {
          markup = markup.split(reb[0] + orig + reb[1]).join(reb[0] + hsh + reb[1]);
        }
      }
    }
    fs.writeFileSync(filepath, markup);
  }
  log(`cacheBust: ${opts.src.length} file(s) busted`);
}

/* ------------------------------------------------------------ shell steps */

function runShell(label, command, { cwd = ROOT, optional = false } = {}) {
  // Allow asset-only builds (e.g. on a dev host) to skip the PHP/OS steps.
  if (process.env.BUILD_SKIP_SHELL) {
    log(`shell:${label}: skipped (BUILD_SKIP_SHELL)`);
    return;
  }
  try {
    execSync(command, { cwd, stdio: 'inherit' });
    log(`shell:${label}: ok`);
  } catch (e) {
    if (optional) {
      log(`shell:${label}: skipped (${e.message.split('\n')[0]})`);
    } else {
      throw e;
    }
  }
}

/* --------------------------------------------------------------- globbing */

function expandGlobs(patterns, cwd) {
  // grunt.file.expand semantics are close enough for these simple '*' patterns.
  const results = new Set();
  for (const pattern of patterns) {
    // grunt.file.expand matches patterns against cwd-relative paths, so an
    // absolute-looking pattern (leading '/') never matches. Skip it to match.
    if (pattern.startsWith('/')) continue;
    for (const m of fs.globSync(pattern, { cwd })) {
      results.add(path.resolve(cwd, m));
    }
  }
  return [...results];
}

/* ----------------------------------------------------------------- runner */

async function main() {
  log(`build.mjs: mode=${mode}`);
  if (isProduction) {
    await taskLess();
    taskCssmin();
    taskHogan();
    taskUglify([UGLIFY_PUBLISH, UGLIFY_DEVEL]);
    taskProcesshtml();
    taskPreprocess(false);
    taskCacheBust();
    runShell('chown', 'chown www-data:www-data -R /var/www/geocloud2/app/wms/files', { optional: true });
    runShell('composer', 'php composer.phar install', { cwd: R('app'), optional: true });
  } else {
    await taskLess();
    taskCssmin();
    taskJshint();
    taskHogan();
    taskPreprocess(true);
    taskCacheBust();
    runShell('composer', 'php composer.phar install', { cwd: R('app'), optional: true });
  }
  log('build.mjs: done');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
