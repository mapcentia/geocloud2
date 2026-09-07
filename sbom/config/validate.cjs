#!/usr/bin/env node
// Usage: node validate.cjs --modules <node_modules> --out <validation.json> <cdx> [<cdx> ...]
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

function parseArgs(argv) {
  const opts = { modules: null, out: null, files: [] };
  for (let i = 0; i < argv.length; i++) {
    if (argv[i] === '--modules') opts.modules = argv[++i];
    else if (argv[i] === '--out') opts.out = argv[++i];
    else opts.files.push(argv[i]);
  }
  if (!opts.modules || !opts.out || opts.files.length === 0) {
    throw new Error('Usage: validate.cjs --modules <node_modules> --out <validation.json> <cdx>...');
  }
  return opts;
}

const opts = parseArgs(process.argv.slice(2));
const modules = path.resolve(opts.modules);
const Ajv = require(path.join(modules, 'ajv'));
const addFormats = require(path.join(modules, 'ajv-formats'));
const ajv = new Ajv({ strict: false, allErrors: true });
addFormats(ajv);
ajv.addFormat('iri-reference', true);
ajv.addFormat('idn-email', true);

const schemaDir = path.join(__dirname, 'validation-schemas');
const read = p => JSON.parse(fs.readFileSync(p, 'utf8'));
const sha = p => crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');
for (const name of ['spdx.schema.json', 'jsf-0.82.schema.json', 'cryptography-defs.schema.json']) {
  ajv.addSchema(read(path.join(schemaDir, name)), 'http://cyclonedx.org/schema/' + name);
}
const validate = ajv.compile(read(path.join(schemaDir, 'bom-1.7.schema.json')));

const report = {
  schema: 'CycloneDX 1.7',
  validator: 'Ajv',
  validatorVersion: require(path.join(modules, 'ajv/package.json')).version,
  uncheckedFormats: ['iri-reference', 'idn-email'],
  files: {},
};
let allPassed = true;

for (const file of opts.files) {
  const cdx = read(file);
  const name = path.basename(file);
  const errors = [];
  const schemaValid = validate(cdx);
  if (!schemaValid) errors.push({ schema: validate.errors });

  const comps = cdx.components || [];
  const allRefs = comps.map(c => c['bom-ref']).filter(Boolean);
  if (cdx.metadata && cdx.metadata.component && cdx.metadata.component['bom-ref']) {
    allRefs.push(cdx.metadata.component['bom-ref']);
  }
  const refs = new Set(allRefs);
  let referencesValid = refs.size === allRefs.length;
  if (!referencesValid) errors.push({ duplicateRefs: true });
  for (const d of cdx.dependencies || []) {
    for (const ref of [d.ref, ...(d.dependsOn || [])]) {
      if (!refs.has(ref)) { referencesValid = false; errors.push({ missingRef: ref }); }
    }
  }

  const libs = comps.filter(c => c.type === 'library');
  const fileReport = {
    schemaValid,
    referencesValid,
    libraryComponents: libs.length,
    fileComponents: comps.length - libs.length,
    librariesMissingVersion: libs.filter(c => !c.version).length,
    librariesMissingPurl: libs.filter(c => !c.purl).length,
    librariesWithoutLicenseMetadata: libs.filter(c => !(c.licenses && c.licenses.length)).length,
    dependencyGraphNodes: (cdx.dependencies || []).length,
    sha256: sha(file),
  };
  if (errors.length) fileReport.errors = errors;
  report.files[name] = fileReport;
  if (!schemaValid || !referencesValid) allPassed = false;
}

report.all_passed = allPassed;
fs.writeFileSync(opts.out, JSON.stringify(report, null, 2) + '\n');
console.log(JSON.stringify(report, null, 2));
process.exit(allPassed ? 0 : 1);
