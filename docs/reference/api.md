# PHP API reference

Generated from PHPDoc and reflection; do not edit. Version 0.2.0-alpha.1.

## Silesco\AcmeSh\Catalog

/** Complete version-pinned driver metadata; form coverage is not a live account test. */

### bundled

/** Load immutable package resources; no network access or metadata execution. */

```php
static bundled(): Silesco\AcmeSh\Catalog
```

### all

/** All DNS drivers at the pinned release, keyed by their exact upstream IDs. */

```php
all(): array
```

### upstream

/** Exact version, commit, source hashes and release provenance. */

```php
upstream(): array
```

### provider

/** Return metadata, or reject unsupported/unpinned driver names. */

```php
provider(string $id): array
```

### form

/** Localized form for an explicit authorization variant, or its declared default. */

```php
form(string $id, string $locale = 'en', ?string $variantId = NULL): array
```

### validateCredentials

/** Validate field shape/declared requirements, not provider account access or authorization. */

```php
validateCredentials(string $id, array $values, ?string $variantId = NULL): void
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

## Silesco\AcmeSh\Local\CertificateVerifier

/** Read back actual issuance material; command exit status never proves a certificate was issued. */

### verify

/** Verify bounded PEM, exact SANs, time, chain against fixed roots and matching private key; returns public metadata only. */

```php
static verify(string $certRoot, array $domains, string $trustBundle): array
```

## Silesco\AcmeSh\Local\CredentialResolver

/** Developer-supplied secret custody boundary; no default store, file lookup or logging. */

### resolve

/** Resolve an opaque handle into exact provider environment fields; never log values or exceptions. */

```php
resolve(string $providerId, string $reference): array
```

## Silesco\AcmeSh\Local\LocalExecutor

/** Opt-in synchronous Linux worker for independent applications, not a Silesco web/host executor. */

### __construct

/** Construction is a trusted configuration boundary; never construct policy/runner from web input. */

```php
__construct(Silesco\AcmeSh\Local\Policy $policy, ?Silesco\AcmeSh\Local\CredentialResolver $credentials = NULL, ?Silesco\AcmeSh\Local\ProcessRunner $runner = NULL)
```

### submit

/** Issue once under a durable lock. Replays read the same terminal result, never another CA request. */

```php
submit(Silesco\AcmeSh\CertificateRequest $request): Silesco\AcmeSh\Operation
```

### poll

/** Read-only polling. It never starts issuance or guesses success after worker loss. */

```php
poll(string $operationId): Silesco\AcmeSh\Operation
```

### certificateMetadata

/** Public metadata only; private key values, provider responses and credentials never enter results. */

```php
certificateMetadata(string $operationId): ?array
```

### __debugInfo

/** Hide collaborators and credential storage implementations from accidental object dumps. */

```php
__debugInfo(): array
```

### __serialize

/** Do not serialize a credential resolver, runner or trusted execution authority. */

```php
__serialize(): array
```

### __unserialize

/** Trusted runtime policy cannot be restored from serialized input. */

```php
__unserialize(array $data): void
```

## Silesco\AcmeSh\Local\NativeProcessRunner

/** Shell-free argv launch of a fixed reviewed shell script; own process group and bounded discarded output. */

### run

/** No inherited environment, stdout buffer, logging or exception containing credentials. */

```php
run(array $arguments, array $environment, string $workingDirectory, int $timeoutSeconds): int
```

## Silesco\AcmeSh\Local\Policy

/** Explicit standalone Linux execution policy. Request payload cannot change paths, CA or command. */

### __construct

/** Terms acceptance is an explicit developer decision for the selected CA, never a default. */

```php
__construct(string $sourceRoot, string $stateRoot, string $caDirectory, string $accountEmail, bool $acceptTerms, string $certificateTrustBundle, ?string $webroot = NULL, int $timeoutSeconds = 300, array $allowedProviders = array (
))
```

### verifySource

/** Verify exact reviewed acme.sh and every pinned DNS driver before process execution. */

```php
verifySource(): void
```

### directory

/** Validate existing canonical directory without symlink components; private state must be owner-only. */

```php
static directory(string $path, bool $private): string
```

## Silesco\AcmeSh\Local\ProcessRunner

/** Trusted process boundary, injectable only by application construction, not request input. */

### run

/** Return exit code, or stable acme.timeout/acme.executor_unavailable; discard all raw output. */

```php
run(array $arguments, array $environment, string $workingDirectory, int $timeoutSeconds): int
```

## Silesco\AcmeSh\Operation

/** Immutable safe executor result; raw provider output and certificates are deliberately absent. */

### __construct

/** Construct a result bound to exactly one request; retries are bounded by executor policy. */

```php
__construct(string $operationId, string $requestBinding, Silesco\AcmeSh\OperationState $state, ?string $errorCode = NULL, ?int $retryAfterSeconds = NULL)
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

