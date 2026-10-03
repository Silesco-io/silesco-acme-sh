// SPDX-License-Identifier: Apache-2.0
// Read-only maintainer check. Does not update pins, execute source or create issues.
import {readFileSync} from 'node:fs';
const pin = JSON.parse(readFileSync(new URL('../UPSTREAM.json', import.meta.url), 'utf8'));
const api = 'https://api.github.com/repos/acmesh-official/acme.sh/releases/latest';
const args = process.argv.slice(2);
if (args.some(arg => !['--fail-on-update', '--offline'].includes(arg))) throw new Error('upstream.arguments_invalid');
if (args.includes('--offline')) {
  console.log(JSON.stringify({pinnedRelease:pin.release,pinnedCommit:pin.commit,networkChecked:false}));
  process.exit(0);
}
try {
  const response = await fetch(api, {headers:{Accept:'application/vnd.github+json','User-Agent':'silesco-acme-sh-upstream-check'},
    redirect:'error',signal:AbortSignal.timeout(20000)});
  if (!response.ok) throw new Error(`http_${response.status}`);
  const body = await response.text();
  if (Buffer.byteLength(body)>262144) throw new Error('response_too_large');
  const release = JSON.parse(body);
  if (release.draft !== false || release.prerelease !== false || !/^\d+\.\d+\.\d+$/.test(release.tag_name)
      || release.html_url !== `https://github.com/acmesh-official/acme.sh/releases/tag/${release.tag_name}`) throw new Error('response_invalid');
  const version = value => value.split('.').map(Number);
  const [latest,old] = [version(release.tag_name),version(pin.release)];
  let comparison=0;
  for(let i=0;i<3;i++){if(latest[i]!==old[i]){comparison=Math.sign(latest[i]-old[i]);break;}}
  if(comparison<0)throw new Error('latest_older_than_pin');
  const updateAvailable=comparison>0;
  console.log(JSON.stringify({pinnedRelease:pin.release,pinnedCommit:pin.commit,latestRelease:release.tag_name,
    updateAvailable,releaseUrl:release.html_url,networkChecked:true}));
  if(updateAvailable && args.includes('--fail-on-update'))process.exitCode=2;
} catch {
  console.error('upstream.check_unavailable');
  process.exitCode=1;
}
