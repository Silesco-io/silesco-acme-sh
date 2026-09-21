# PHP API reference

Generated from PHPDoc and reflection; do not edit. Version 0.1.0-alpha.1.

## Silesco\AcmeSh\Catalog

/** Pinned upstream inventory. A listed provider does not imply a reviewed form or tested account. */

### bundled

/** Load shipped immutable data, without network access or code execution. */

```php
static bundled(): Silesco\AcmeSh\Catalog
```

### all

/** Complete pinned driver inventory keyed by exact upstream provider ID. */

```php
all(): array
```

### upstream

/** Exact upstream provenance for the inventory. */

```php
upstream(): array
```

### provider

/** Return metadata or reject unsupported/unpinned driver names. */

```php
provider(string $id): array
```

### form

/** Return reviewed localized field descriptors. Never returns credential values. */

```php
form(string $id, string $locale): array
```

### validateCredentials

/** Validate only reviewed form shape; never logs, persists, returns or probes supplied credentials. */

```php
validateCredentials(string $id, array $values): void
```

## Silesco\AcmeSh\CertificateRequest

/** Immutable intent, not a command or authorization. Contains no credential values. */

### http01

/** Create an HTTP-01 intent. Canonical lowercase ASCII names only; wildcard forbidden. */

```php
static http01(array $domains, string $idempotencyKey): Silesco\AcmeSh\CertificateRequest
```

### dns01

/** Create DNS-01 intent using an opaque credential handle, never a value or file path. */

```php
static dns01(array $domains, string $providerId, string $credentialReference, string $idempotencyKey): Silesco\AcmeSh\CertificateRequest
```

### jsonSerialize

/** Closed public intent representation. CA and runtime paths belong to executor policy. */

```php
jsonSerialize(): array
```

### binding

/** Library-local deterministic binding; not RFC 8785 or a Silesco Execution Permit hash. */

```php
binding(): string
```

## Silesco\AcmeSh\Executor

/** Adapter boundary. Implementations own authorization, custody, persistence, deadlines and retry policy. */

### submit

/** Submit once or recover the exact same idempotent operation; conflicting reuse must fail. */

```php
submit(Silesco\AcmeSh\CertificateRequest $request): Silesco\AcmeSh\Operation
```

### poll

/** Read sanitized progress; polling must not execute another issuance. */

```php
poll(string $operationId): Silesco\AcmeSh\Operation
```

## Silesco\AcmeSh\Operation

/** Immutable safe executor result; raw provider output and certificates are deliberately absent. */

### __construct

/** Construct a result bound to exactly one request; retries are bounded by executor policy. */

```php
__construct(string $operationId, string $requestBinding, Silesco\AcmeSh\OperationState $state, ?string $errorCode, ?int $retryAfterSeconds)
```

### assertFor

/** Reject cross-request replies before exposing executor progress. */

```php
assertFor(Silesco\AcmeSh\CertificateRequest $request): Silesco\AcmeSh\Operation
```

### jsonSerialize

/** Serialize only the bounded public progress contract. */

```php
jsonSerialize(): array
```

## Silesco\AcmeSh\OperationState

/** Sanitized lifecycle. Issued is not TLS activation or PWA readiness. */

