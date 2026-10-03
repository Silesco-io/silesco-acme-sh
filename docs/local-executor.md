# Standalone Linux local executor

Document ID: `acme-sh.local-executor`. Language: `en`. License: CC BY 4.0.
Code examples retain Apache-2.0. See [Russian version](ru/local-executor.md).

This is an **optional synchronous worker** for independent applications. The
framework-independent catalog and request API do not execute processes by default.
Silesco Wizard/UI must use their authorized bootstrap/Guard adapter, **not** this
worker. No listener, daemon, Docker integration, sudo or installation binary is
added by this library.

## Trusted setup

The administrator supplies a reviewed, immutable acme.sh source directory exactly
matching `Catalog::upstream()` / `UPSTREAM.json`, not an executable selected by a
request. Release 3.1.6, commit `807da6498377ee5e0cf43a78091f46f12dc59a89` is pinned.
`Policy` verifies the main script SHA-256 and the complete 198-driver inventory
before execution. Use canonical LF bytes: changing line endings changes hashes.
The GPL-3.0 upstream runtime is separately provided; this Apache-2.0 library does
not silently install or update it.

This opt-in profile requires Linux PHP 8.3+, OpenSSL and POSIX extensions,
`proc_open`, fixed `/bin/sh`, `/usr/bin/setsid` and `/usr/bin/timeout`, plus the
dependencies required by acme.sh and explicitly enabled DNS drivers. Default
`PATH` is `/usr/bin:/bin`; an application must not introduce writable commands
there. The dedicated worker user needs write access to its private state and,
for HTTP-01, the explicitly configured challenge webroot; it does not require
root. It must have network access to its selected CA/provider and DNS as needed.

Create the state root as mode 0700, owned by that worker. Source files and trust
bundle must be regular, non-linked, reviewed files, owned by root or the worker,
not writable by group/others. Canonical paths cannot contain whitespace or
symlink components. Ancestors cannot be controlled by another user; root-owned
sticky temporary directories are allowed. Dedicated durable storage is needed
for account keys and crash journals; a disposable `/tmp` root is only suitable
for isolated offline tests.

```php
use Silesco\AcmeSh\CertificateRequest;
use Silesco\AcmeSh\Local\{Policy, LocalExecutor};

$policy = new Policy(
    sourceRoot: '/opt/reviewed/acme.sh-3.1.6',
    stateRoot: '/var/lib/example-acme',
    caDirectory: 'https://acme-staging-v02.api.letsencrypt.org/directory',
    accountEmail: 'operator@example.com',
    acceptTerms: true, // Explicit acceptance for this CA, obtained by your application.
    certificateTrustBundle: '/opt/reviewed/le-staging-ca.pem',
    webroot: '/var/www/acme-challenge',
    timeoutSeconds: 300,
);
$worker = new LocalExecutor($policy);
$request = CertificateRequest::http01(
    ['panel.example.com'],
    '019f6a21-0000-7000-8000-000000000001',
);
$operation = $worker->submit($request);
$progress = $worker->poll($operation->operationId);
$metadata = $worker->certificateMetadata($operation->operationId);
```

The CA URL, email, terms decision, executable, trust bundle, paths, deadline and
allowed providers belong to trusted construction; they are absent from browser
requests. No shell command string, hook, executable path or CA override is
accepted by `CertificateRequest`. A staging bundle verifies issuance material
only: it is **not** inserted into system/provider trust roots, and does not prove
browser/PWA trust. Provider/CA HTTPS retains the OS's normal trust configuration.

## DNS credentials and provider authority

DNS is disabled by default. The administrator must explicitly configure, for
example, `allowedProviders: ['dns_regru']`. Full catalog coverage does not mean
that every upstream driver's privileges are suitable for a web application.
Some drivers invoke external CLIs, modify local DNS zones or evaluate trusted
operator configuration. Review their pinned source and prerequisites before
allowing them. A privileged worker must never take such command/configuration
fields directly from untrusted web forms. An allowlist is a developer's security
decision, not an option a browser may set.

Provide `CredentialResolver::resolve($providerId, $reference): array`. The request
contains only an opaque reference; the resolver retrieves approved credentials
from application-owned custody and returns the exact upstream field names.
`Catalog::validateCredentials()` validates the catalog's field/authentication
shape. It is not a network credential test or a substitute for a driver's
security review. Use least-privilege API credentials wherever the provider allows.

Secrets are passed only through the child environment, not argv, journal, result
or logs. Resolver exceptions are replaced by `acme.credentials_invalid`; raw
upstream output is bounded and discarded, never interpreted as an error code.
The child does not inherit the PHP process's environment, debug flags, proxy,
home, hooks or logging configuration. acme.sh runs with debug/log/syslog disabled
and a fresh per-operation accountconf. No notify/deploy/pre/post/reload hooks,
automatic upgrades, cron installation or `--insecure` are requested.

Environment transfer is **not encryption**: the dedicated Unix account/root can
observe its processes. acme.sh/provider drivers can persist API secrets in their
own accountconf. The executor's entire state is mode 0700, files 0600; it is
secret-bearing storage, not an exportable diagnostics folder. This profile does
not promise encrypted credential cache, secure erasure, or wiping PHP memory.
Do not log/dump credentials, resolver objects, process arguments/environment or
this directory. The caller owns retention, protected backups and worker isolation.

## Execution, certificate readback and deployment

`submit()` runs synchronously under one nonblocking state-root lock. Dispatch it
from your trusted job worker, not a web request that can be aborted/retried
implicitly. All arguments are an argv array. Fixed reviewed `/bin/sh` executes
the pinned script; no application-built shell command is evaluated. An independent
GNU timeout bounds an orphaned child even if PHP exits. Output beyond 1 MiB is
discarded and terminates the process group; TERM is followed by bounded KILL.

Exit 0 is insufficient: the executor checks bounded private PEM files, exact DNS
SANs (including wildcard intent), validity, certificate/private-key match and
chain against the fixed trust bundle. Only then is `issued` committed after
material/journal fsync. Results contain public fingerprint, serial, names and
validity, never PEM/private keys. `issued` is not Nginx activation or PWA readiness.

Issued material is stored at
`<stateRoot>/local-<idempotencyKey>/certs/<first-domain>_ecc/`:
`<first-domain>.cer`, `<first-domain>.key`, `ca.cer`, upstream `fullchain.cer`.
Your explicitly authorized deployment code must securely read/check/activate
the needed files and verify live TLS. This executor does not copy to arbitrary
web-selected paths or restart services. Private key reads belong to that caller,
not the public progress API.

## Replays, loss and limitations

The journal stores minimal request/policy hashes, deadline and safe result, not
credential values or raw output. Exact same idempotency key+request+policy returns
the prior result without reissuing. Changed intent or policy raises
`acme.request_conflict`. Terminal failure/timeout is also sticky: no automatic
CA retry hidden inside submit/poll.

Before any possible network request the journal durably becomes `running`.
If PHP dies, a restart's `submit()` sees this ambiguous state and refuses to
issue again (`acme.executor_unavailable`; after the original deadline,
`acme.request_expired`). `poll()` only reads; it does not execute or extend the
deadline. A still-running operation becomes an expired readout at that deadline.
An operator must reconcile the existing CA/account/TXT/material before explicitly
authorizing another request ID. Deleting a journal or blindly generating a new
UUID is not safe recovery. The library does not automatically assume that stale
TXT were cleaned up after timeout; provider-side reconciliation may be necessary.

This version supports issuance; automatic certificate renewal/revocation/scheduling,
ambiguous-result reconciliation, service deployment and Silesco authority are
separate contracts. `--issue` with a new UUID must not be sold as seamless renewal.
Offline tests cover local process/result safety, not 198 live provider accounts.
Run real LE **staging** acceptance with separately authorized credentials before
claiming successful provider integration; never repeatedly test production LE.

## Offline test

Run `tests/local-executor.php` on Linux with `ACME_SH_TEST_SOURCE` pointing to the
canonical pinned source. Its fixture CA, credentials and processes are synthetic;
no network is needed. It tests issuance readback, SAN/key failures, exit-0 without
material, timeouts, secret-output discard, HTTP/DNS policy, restart/idempotency,
protected paths and tampered source. It does not enroll provider accounts.
