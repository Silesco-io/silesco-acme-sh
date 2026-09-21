# PHP ACME integration (0.1.0-alpha.1)

PHP 8.3+, no framework, database, Vault or network dependency. This is a public SDK
(Apache-2.0), not an ACME protocol implementation. Install as a Composer path package
or export the closed package using `node scripts/export.mjs EMPTY_OUTPUT_DIRECTORY`.
Composer PSR-4 namespace is `Silesco\AcmeSh\` mapped to `src/`. Release assembly pins
the exact commit and exported SHA256SUMS; no runtime download or floating version.

```php
use Silesco\AcmeSh\{Catalog, CertificateRequest, Executor};
$form = Catalog::bundled()->form('dns_regru', 'ru');
$request = CertificateRequest::http01(['panel.example.test', 'install.example.test'], $uuid);
// $executor is an application-owned implementation of Executor.
$progress = $executor->submit($request)->assertFor($request);
$progress = $executor->poll($progress->operationId)->assertFor($request);
```

The supplied UUID identifies one immutable operation. A retry of the same request
must recover the same operation, while reuse for different content fails. Polling
never submits another issuance. Application policy chooses CA, account, validated
CSR/private-key owner and runtime paths; the browser cannot select a shell command,
CA endpoint, hook or file path. `binding()` is deterministic library-local JSON
SHA-256, not RFC 8785 and not a Protocol/Execution Permit signature payload.

Executor states: queued, running, waiting_dns, issued, failed, expired. Public error
codes are the closed `Operation::ERRORS` list. No raw stdout/stderr/provider bodies
may populate a result. `issued` says nothing about Nginx activation, live TLS proof,
browser trust or PWA readiness. The root owner separately verifies and activates TLS.

In Silesco the Wizard owns the adapter to the existing typed bootstrap intent and
root result mechanism. This package cannot mutate host state or open Docker/sudo.
Outside Silesco an application can implement Executor locally around its own trusted
acme.sh runner. **No local process runner is shipped in this initial release**;
command escaping, custody, timeout and state durability are not falsely delegated
to an unsafe default. The interface is independently reusable now.

## DNS catalog coverage

All 191 `dns_*.sh` drivers from the pinned tree are inventoried with exact source URL
and SHA-256. Only REG.RU's form is reviewed in this release. Other records explicitly
have `formCoverage=unreviewed` and `form()` fails closed; no inferred credential forms
or claim of full provider enablement. No real provider account was tested here.

REG.RU requires `REGRU_API_Username` and `REGRU_API_Password`. Both are handled as
sensitive. Configure an API alternative password and the **executing server's public
outbound IP** at https://www.reg.ru/user/account/#/settings/api/ . The library checks
field shape only, not account validity. Do not log the submitted form. DNS request
objects carry only an opaque credential reference; an executor resolves that handle
through its separate protected credential transport. Reference binding alone gives
no authorization. Unknown fields, control characters and overlong values fail closed.

Upstream acme.sh can persist credentials to account.conf and print sensitive provider
responses, especially in debug mode. Executors must isolate protected account state,
disable debug output and sanitize logs. This library does not claim to solve credential
custody. All HTML consumers must escape catalog text and never execute metadata.

## Reproducible checks

`php tests/run.php`; `php scripts/reference.php --check`;
`node scripts/catalog.mjs PINNED_SOURCE_DIRECTORY --check`.
Acquire upstream only from the exact commit, verify source archive SHA-256
`9af3ad3d775a5782246df4cdd4b4e7b9b3179deb63c509b10e3ba0433093a884`.
Source is parsed as text and never executed. Generated reference is produced from
PHPDoc with reflection, compatible with phpDocumentor comment input; no hosted docs
generator is introduced. Catalog ru/en parity and hostile inputs are tested offline.

Architecture: 1.23.0 / ADR-091. PHP API 0.1.0-alpha.1, acme.sh 3.1.4, catalog schema1.
No earlier implemented API exists; this is the initial alpha contract. The live
LE-staging and DNS credential tests belong to the installed cross-project flow and
are not established by this package's offline tests.
