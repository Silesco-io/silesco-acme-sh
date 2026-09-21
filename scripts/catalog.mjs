// SPDX-License-Identifier: Apache-2.0
// Offline deterministic inventory generator; never sources upstream shell.
import {readFileSync, readdirSync, writeFileSync} from 'node:fs';
import {join, resolve} from 'node:path';
import {createHash} from 'node:crypto';
const root = resolve(import.meta.dirname, '..');
const source = process.argv[2];
if (!source) throw new Error('usage: node scripts/catalog.mjs PINNED_SOURCE_DIRECTORY [--check]');
const commit = '3661fd86b6304115e42f43910e6dd452ab9866d6';
const upstream = {release:'3.1.4', commit, image:'ghcr.io/acmesh-official/acme.sh@sha256:08bad323dd6537ea2caba64260ef6e70e96057c4d9214afb9c07861db26653d9'};
if (!readFileSync(join(source,'acme.sh'),'utf8').includes('VER=3.1.4')) throw new Error('upstream.version_mismatch');
const providers = {};
for (const file of readdirSync(join(source,'dnsapi')).filter(f=>/^dns_[a-zA-Z0-9_]+\.sh$/.test(f)).sort()) {
  const bytes = readFileSync(join(source,'dnsapi',file));
  const text = bytes.toString('utf8');
  const id = file.slice(0,-3);
  const info = text.match(/^[a-zA-Z0-9_]+_info='([^']*)'/m)?.[1] || '';
  const doc = info.match(/^Docs: (\S+)/m)?.[1];
  providers[id] = {displayName:info.split('\n')[0].trim() || id,
    scriptSha256:createHash('sha256').update(bytes).digest('hex'),
    sourceUrl:`https://github.com/acmesh-official/acme.sh/blob/${commit}/dnsapi/${file}`,
    documentationUrl:doc ? (doc.startsWith('https://') ? doc : 'https://'+doc) : 'https://github.com/acmesh-official/acme.sh/wiki/dnsapi',
    formCoverage:'unreviewed', accountTested:false, fields:[]};
}
Object.assign(providers.dns_regru, {formCoverage:'reviewed', credentialsUrl:'https://www.reg.ru/user/account/#/settings/api/',
  helpKey:'provider.regru.help', fields:[
    {name:'REGRU_API_Username', required:true, secret:true, type:'password', maxLength:1024, labelKey:'provider.regru.username.label', helpKey:'provider.regru.username.help'},
    {name:'REGRU_API_Password', required:true, secret:true, type:'password', maxLength:1024, labelKey:'provider.regru.password.label', helpKey:'provider.regru.password.help'}]});
const output = JSON.stringify({schemaVersion:1, upstream, providers},null,2)+'\n';
const target = join(root,'resources/providers.json');
if (process.argv.includes('--check')) {
  if (readFileSync(target,'utf8') !== output) throw new Error('catalog.not_reproducible');
} else writeFileSync(target,output);
console.log(`${Object.keys(providers).length} pinned DNS drivers; 1 reviewed form; 0 live account tests`);
