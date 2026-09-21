<?php
declare(strict_types=1);
namespace Silesco\AcmeSh;

/** Immutable intent, not a command or authorization. Contains no credential values. */
final readonly class CertificateRequest implements \JsonSerializable
{
    private function __construct(
        public array $domains,
        public string $challenge,
        public string $idempotencyKey,
        public ?string $providerId,
        public ?string $credentialReference,
    ) {}

    /** Create an HTTP-01 intent. Canonical lowercase ASCII names only; wildcard forbidden. */
    public static function http01(array $domains, string $idempotencyKey): self
    {
        return self::create($domains, 'http-01', $idempotencyKey, null, null);
    }

    /** Create DNS-01 intent using an opaque credential handle, never a value or file path. */
    public static function dns01(array $domains, string $providerId, string $credentialReference, string $idempotencyKey): self
    {
        Catalog::bundled()->provider($providerId);
        if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}\z/D', $credentialReference)) {
            throw new \InvalidArgumentException('acme.credential_reference_invalid');
        }
        return self::create($domains, 'dns-01', $idempotencyKey, $providerId, $credentialReference);
    }

    private static function create(array $domains, string $challenge, string $key, ?string $provider, ?string $reference): self
    {
        if (!preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $key)) {
            throw new \InvalidArgumentException('acme.idempotency_key_invalid');
        }
        if (!array_is_list($domains) || count($domains) < 1 || count($domains) > 100) {
            throw new \InvalidArgumentException('acme.domains_invalid');
        }
        foreach ($domains as $domain) {
            if (!is_string($domain) || strlen($domain) > 253
                || !preg_match('/\A(?:\*\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D', $domain)
                || ($challenge === 'http-01' && str_starts_with($domain, '*.'))) {
                throw new \InvalidArgumentException('acme.domain_invalid');
            }
        }
        if (count(array_unique($domains)) !== count($domains)) {
            throw new \InvalidArgumentException('acme.domain_duplicate');
        }
        return new self($domains, $challenge, $key, $provider, $reference);
    }

    /** Closed public intent representation. CA and runtime paths belong to executor policy. */
    public function jsonSerialize(): array
    {
        return ['schemaVersion' => 1, 'domains' => $this->domains, 'challenge' => $this->challenge,
            'idempotencyKey' => $this->idempotencyKey, 'providerId' => $this->providerId,
            'credentialReference' => $this->credentialReference];
    }

    /** Library-local deterministic binding; not RFC 8785 or a Silesco Execution Permit hash. */
    public function binding(): string
    {
        return hash('sha256', json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
