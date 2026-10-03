// SPDX-License-Identifier: Apache-2.0
// Offline catalog provenance, form coverage, and localization invariants.
import assert from 'node:assert/strict';
import {readFileSync,readdirSync,mkdtempSync,mkdirSync,writeFileSync,rmSync} from 'node:fs';
import {resolve,join,sep} from 'node:path';
import {tmpdir} from 'node:os';
import {createHash} from 'node:crypto';
import {spawnSync} from 'node:child_process';
const root = resolve(import.meta.dirname,'..');
const source = process.argv[2];
if (!source) throw new Error('usage: node scripts/catalog-test.mjs PINNED_SOURCE_DIRECTORY');
const json = path=>JSON.parse(readFileSync(path,'utf8'));
const pin = json(join(root,'UPSTREAM.json'));
const catalog = json(join(root,'resources/providers.json'));
const overrides = json(join(root,'resources/provider-overrides.json')).providers;
const locales = Object.fromEntries(['en','ru'].map(locale=>[locale,json(join(root,`locales/${locale}.json`)).messages]));
const drivers = readdirSync(join(source,'dnsapi')).filter(file=>/^dns_[a-zA-Z0-9_]+\.sh$/.test(file)).sort();
const digest = data=>createHash('sha256').update(data).digest('hex');
let assertions = 0;
const check = condition=>{assert.ok(condition); assertions++;};
check(catalog.schemaVersion===2);
check(catalog.upstream.release===pin.release && catalog.upstream.commit===pin.commit);
check(!Object.hasOwn(catalog.upstream,'image'));
check(drivers.length===pin.dnsDriverCount);
assert.deepEqual(Object.keys(catalog.providers),drivers.map(file=>file.slice(0,-3))); assertions++;
assert.deepEqual(Object.keys(locales.en),Object.keys(locales.ru)); assertions++;
const keyExists = key=>check(typeof locales.en[key]?.message==='string' && locales.en[key].message.length>0 && typeof locales.ru[key]?.message==='string' && locales.ru[key].message.length>0);
for (const [id,provider] of Object.entries(catalog.providers)) {
  check(['upstream_metadata','reviewed_override'].includes(provider.formCoverage));
  check(provider.accountTested===false);
  check(provider.scriptSha256===digest(readFileSync(join(source,'dnsapi',`${id}.sh`))));
  check(provider.sourceUrl.includes(pin.commit));
  check(/^https:\/\//.test(provider.documentationUrl) && /^https:\/\//.test(provider.credentialsUrl));
  keyExists(provider.helpKey);
  for (const entry of provider.prerequisites) keyExists(entry.helpKey);
  const names = new Set(provider.fields.map(field=>field.name));
  check(names.size===provider.fields.length);
  if (!overrides[id]?.replaceFields) {
    const text = readFileSync(join(source,'dnsapi',`${id}.sh`),'utf8');
    const info = text.match(new RegExp(`^${id}_info='([^']*)'`,'m'))?.[1] ?? '';
    const tail = info.slice(info.indexOf('Options:'));
    for (const line of tail.split('\n')) {
      const name = line.match(/^\s*([A-Za-z_][A-Za-z0-9_]*)(?:\s|$)/)?.[1];
      if (name && (name.includes('_') || /^[A-Z]+$/.test(name) || /[a-z][A-Z]/.test(name))) {
        check(names.has(name)); // Independent scan also catches supplemental Optional: sections.
      }
    }
  }
  check(provider.authVariants.length>0);
  const variantIds = new Set(provider.authVariants.map(variant=>variant.id));
  check(variantIds.size===provider.authVariants.length);
  for (const field of provider.fields) {
    check(/^[A-Za-z_][A-Za-z0-9_]*$/.test(field.name));
    check(typeof field.required==='boolean' && typeof field.secret==='boolean');
    check(['text','password','textarea'].includes(field.type));
    check(Number.isInteger(field.maxLength) && field.maxLength>0 && field.maxLength<=65536);
    keyExists(field.labelKey); keyExists(field.helpKey);
  }
  for (const variant of provider.authVariants) {
    keyExists(variant.labelKey);
    check(new Set(variant.fields.map(field=>field.name)).size===variant.fields.length);
    for (const field of variant.fields) {
      check(names.has(field.name) && typeof field.required==='boolean');
      check(!field.metadataConditional);
      if (field.condition) check(['present','equals'].includes(field.condition.operator) && (!!field.condition.field !== !!field.condition.context));
    }
  }
}
const reproduction = spawnSync(process.execPath,[join(root,'scripts/catalog.mjs'),source,'--check'],{encoding:'utf8'});
assert.equal(reproduction.status,0,reproduction.stderr); assertions++;
// Wrong source bytes must be rejected before reading or executing driver data.
const badSource = mkdtempSync(join(tmpdir(),'silesco-catalog-negative-'));
try {
  mkdirSync(join(badSource,'dnsapi'));
  writeFileSync(join(badSource,'acme.sh'),`VER=${pin.release}\n# deliberately changed\n`);
  const failure = spawnSync(process.execPath,[join(root,'scripts/catalog.mjs'),badSource,'--check'],{encoding:'utf8'});
  check(failure.status!==0 && failure.stderr.includes('upstream.script_mismatch'));
  writeFileSync(join(badSource,'acme.sh'),readFileSync(join(source,'acme.sh')));
  writeFileSync(join(badSource,'dnsapi',drivers[0]),readFileSync(join(source,'dnsapi',drivers[0])));
  const inventoryFailure = spawnSync(process.execPath,[join(root,'scripts/catalog.mjs'),badSource,'--check'],{encoding:'utf8'});
  check(inventoryFailure.status!==0 && inventoryFailure.stderr.includes('upstream.driver_inventory_mismatch'));
} finally {
  if (!resolve(badSource).startsWith(resolve(tmpdir())+sep+'silesco-catalog-negative-')) throw new Error('test.cleanup_path_invalid');
  rmSync(badSource,{recursive:true,force:true});
}
console.log(`PASS ${assertions} catalog assertions; ${drivers.length} driver forms; zero live provider accounts tested`);
