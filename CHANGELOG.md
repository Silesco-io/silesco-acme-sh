# Package changes

## 0.2.0-alpha.1 — 2026-10-03

- Pin upstream acme.sh3.1.6 by signed tag, commit and canonical source hashes.
- Catalog schema2: all198 DNS drivers,463 fields,218 variants,50 explicit metadata corrections.
- Complete ru/en forms, prerequisites and conditional requirements; no live account claim.
- Optional standalone Linux issuance executor, durable replay state, bounded process isolation,
  protected credentials/state and certificate readback. Not a Silesco web executor.
- Read-only upstream check and maintenance workflow; recursive generated PHPDoc reference.
- Export exact provenance and optional worker source. Separate upstream GPL runtime is not bundled.

Raw schema1 consumers must migrate. Request/Executor/Operation signatures remain compatible.
Automatic renewal/revocation/TLS activation are not implemented by this release.

## 0.1.0-alpha.1 — 2026-09-21

Initial immutable SDK/interface;191 acme.sh3.1.4 drivers, reviewed REG.RU form only.
