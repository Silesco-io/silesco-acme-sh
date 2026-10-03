// SPDX-License-Identifier: Apache-2.0
// Closed offline export for Composer path repositories / immutable image assembly.
import {mkdirSync, readdirSync, readFileSync, writeFileSync, lstatSync} from 'node:fs';
import {join, resolve, relative} from 'node:path';
import {createHash} from 'node:crypto';
const root=resolve(import.meta.dirname,'..'), target=resolve(process.argv[2] || '');
const within=relative(root,target).replaceAll('\\','/');
if (!process.argv[2] || within==='' || (!within.startsWith('../') && !within.includes(':'))) throw new Error('export.target_invalid');
mkdirSync(target,{recursive:true});
if(readdirSync(target).length) throw new Error('export.target_not_empty');
const files=[];
function collect(path){ const stat=lstatSync(path); if(stat.isSymbolicLink()) throw new Error('export.link'); if(stat.isDirectory()) for(const f of readdirSync(path).sort())collect(join(path,f)); else files.push(path); }
for(const item of ['composer.json','VERSION','UPSTREAM.json','LICENSE','THIRD_PARTY_NOTICES.md','src','resources','locales'])collect(join(root,item));
const sums=[];
for(const file of files){const name=relative(root,file).replaceAll('\\','/'); const bytes=readFileSync(file); const out=join(target,name);mkdirSync(resolve(out,'..'),{recursive:true});writeFileSync(out,bytes);sums.push(createHash('sha256').update(bytes).digest('hex')+'  '+name);}
writeFileSync(join(target,'SHA256SUMS'),sums.sort().join('\n')+'\n');
console.log(`Exported ${files.length} immutable package files`);
