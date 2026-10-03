# PHP integration — 0.2.0-alpha.1

PHP 8.3+, PSR-4 `Silesco\AcmeSh\` → `src/`, no framework or database. Install this
Composer library as a path/VCS package; it is not yet published to Packagist.
The closed offline export includes VERSION, UPSTREAM.json and SHA256SUMS.
No floating runtime download, Yii dependency or bundled acme.sh source.

```php
use Silesco\AcmeSh\{Catalog, CertificateRequest};
$catalog = Catalog::bundled();
$form = $catalog->form('dns_regru', 'ru');
// Render escaped labels/help, never evaluate metadata. Do not log submitted values.
$catalog->validateCredentials('dns_regru', $submittedValues, $form['variantId']);
$request = CertificateRequest::dns01(['panel.example.test'], 'dns_regru', $protectedHandle, $uuid);
$progress = $executor->submit($request)->assertFor($request);
$progress = $executor->poll($progress->operationId)->assertFor($request);
```

`$executor` is application-owned. The optional [standalone Linux implementation](local-executor.md)
can be selected explicitly. **Silesco does not run it in its web process**: Wizard uses
the existing typed bootstrap/Guard/helper adapter. This library grants no mutation authority.

## Forms and credential validation

198 providers, 218 variants, optional/default/conditional fields. `form(id, locale, variantId)`
returns the selected fields plus all variants and prerequisites. Locale falls back to English.
Known credential-creation URLs are included; otherwise use the official documentation link.
All 50 source corrections and external tool requirements are in [provider review](provider-review.md).

`validateCredentials` checks only shape and the declared variant: exact case-sensitive names,
required fields, control characters, value lengths and mutually exclusive credentials.
With no variant argument any declared variant may match; explicitly select one in forms.
Defaults are suggestions, not silently injected values. Infrastructure context such as
account 2FA or IAM roles is **not guessed** from credentials; the application/executor must
validate those requirements and prerequisites. This is not an account-access test.

REG.RU requires `REGRU_API_Username` and `REGRU_API_Password`. Configure the alternative
API password and **executing server's outbound IP** in the linked REG.RU settings.
acme.sh creates/removes TXT records itself. No PHP reimplementation of provider API.

## Operations and security

An immutable request holds domains, provider and opaque credential reference, never secrets,
CA endpoints, shell commands, paths or hooks. UUID identifies one operation. Reusing it with
different content must fail; polling must not issue again. `binding()` is deterministic
library-local JSON SHA-256, **not RFC8785 or a Protocol/Execution Permit signature payload**.

States: queued, running, waiting_dns, issued, failed, expired. Errors use the closed
Operation::ERRORS list, never raw stdout/stderr or provider responses. `issued` does not
prove Nginx activation, browser trust or PWA readiness. The owning application proves those.
Current request model is issuance; automatic renewal is not implemented by this release.

acme.sh may persist credentials in account.conf and some providers print secrets even without
debug. Executors must protect state and suppress all output sinks, not merely disable debug.
The local executor documents this explicitly; see [its guide](local-executor.md).

## Compatibility and checks

Architecture 1.24.0 / ADR-091—092. PHP API 0.2.0-alpha.1; catalog schema2 replaces schema1
before stable1.0. CertificateRequest/Executor/Operation signatures remain compatible with0.1.
Consumers reading raw catalog JSON must migrate to schema2; source-image pins must be updated.

`php tests/run.php`; `php scripts/reference.php --check`;
`node scripts/catalog-test.mjs PINNED_SOURCE_DIRECTORY`.
Exact source acquisition and update policy: [maintenance](maintenance.md).
Offline tests do not establish real LE staging or 198 live provider accounts.
