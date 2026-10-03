# Public PHP module stage — 2026-10-03

Package0.2.0-alpha.1; architecture snapshot1.24.0. Upstream acme.sh3.1.6, exact
commit807da6498377ee5e0cf43a78091f46f12dc59a89. The release SSH tag signature and
canonical source hashes were checked; Windows autocrlf output was rejected and
catalogue generation uses LF Git blobs. UPSTREAM.json preserves this evidence.

## Delivered and checked

| Check | Result |
|---|---|
| Every pinned DNS driver, field inventory, variants, ru/en parity, reproduction and negative hash tests | 6487 assertions;198 drivers,463 fields,218 variants |
| PHP catalogue/forms, request/result models, hostile inputs, complete locales | 7450 assertions |
| Linux worker process safety, timeout, discarded output, certificate chain/SAN/key readback, replay, protected paths | 51 assertions |
| Generated reference | identical on Windows/PHP8.5.3 and Linux/PHP8.3 |
| Composer metadata | strict validation PASS |
| Independent Composer consumer | path-package autoload/catalog/request/local-worker available without Yii or Silesco |
| Closed export | 20 files;allSHA256SUMS PASS |
| Read-only upstream query | stable3.1.6, updateAvailable=false;checked2026-10-03 |

The combined Linux run uses UID20000:20000, networknone, read-only source/export
and tmpfs(noexec,nosuid,nodev), cap-dropALL/no-new-privileges. Pinned image:
`dunglas/frankenphp:php8.3-bookworm@sha256:4c0ae6933ee2c08d82a2ce1593c37c754d3794a706c26eae7b3a6c21d2353918`.
Export manifest SHA-256:
`247101f8adf3de3be0a9bb9b0ae4453dc1ed10a8018427faef1ec79e508d784d`.

Commands: `php tests/run.php`; `node scripts/catalog-test.mjs CANONICAL_SOURCE`;
Linux `ACME_SH_TEST_SOURCE=CANONICAL_SOURCE php tests/local-executor.php`;
`php scripts/reference.php --check`; `composer validate --strict --no-check-publish`.
GitHub test and weekly read-only upstream workflows are authored; this report
does not claim a successful hosted workflow run before push.

## Decisions and limits

Use all pinned driver metadata with explicit source-reviewed corrections rather than
inventing provider APIs or limiting catalogue to REG.RU. Keep acme.sh separately
licensed and provisioned. Supply an opt-in worker for independent applications,
but retain Silesco's existing typed mutation boundary. No web shell authority.

These are source/offline tests. **Zero live provider accounts or LE issuances.**
Automatic renewal/revoke, TLS activation, DNS secret transport in Wizard and full
installation/PWA/Master acceptance remain separate stages. No VPS, get.silesco.io,
production promotion, host trust store or unrelated Docker project was changed.
