# Maintaining the upstream catalog

The SDK0.2.0-alpha.1 pins acme.sh3.1.6, commit
`807da6498377ee5e0cf43a78091f46f12dc59a89`. UPSTREAM.json records the release tag object,
SSH signature fingerprint, allowed-signers digest, main-script SHA-256, canonical archive
digest and complete 198-driver inventory digest. The same provenance is exposed by Catalog
and included in every offline package export. acme.sh is never silently upgraded at runtime.

## Detecting releases

`node scripts/check-upstream.mjs` reads the official GitHub latest stable release API.
It does not change files, execute upstream source, create issues or use credentials.
`--fail-on-update` returns2 for a newer stable release; network/response failures return1,
not "up to date". `--offline` reports the pin only and explicitly says networkChecked=false.
The read-only GitHub workflow runs weekly and can be invoked manually after publication.

## Updating the package

1. Review official release notes and choose a stable tag; record its commit/tag object.
2. Verify the signed tag against an independently reviewed signer. Do not trust a changed
   allowed_signers file merely because it is downloaded beside the new tag.
3. Use LF canonical Git blobs, **not a Windows autocrlf checkout**. For current3.1.6:
   `git -c core.autocrlf=false archive --format=tar --output=source.tar 3.1.6`.
   Expected archive SHA-256: `9aacfd809a6c55b75d26a7239b417ae9cdd3cb002eebfab890fb0fcc0117fa36`.
4. Review all added/changed/removed dnsapi drivers, conditional credentials, local commands,
   external dependencies, output sinks and sensitive state. Update explicit overrides.
5. Update UPSTREAM.json, regenerate with `node scripts/catalog.mjs SOURCE`, then run
   `node scripts/catalog-test.mjs SOURCE`, PHP tests, Linux local-executor tests and API reference.
6. Compare generated forms and ru/en guidance. Upstream metadata is not infallible; no unexplained
   declared-field omissions or fabricated credential requirements are accepted.
7. Bump VERSION, document catalog/API compatibility, export and verify SHA256SUMS.
   Update downstream pinned package and separately supplied acme.sh runtime together.
8. Test selected real provider accounts with the test CA. Offline review cannot certify all accounts.

Never execute upstream scripts to discover form fields. Do not commit test credentials or runtime state.
No scheduled job updates dependencies or promotes a release automatically.
