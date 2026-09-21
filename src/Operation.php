<?php
declare(strict_types=1);
namespace Silesco\AcmeSh;

/** Immutable safe executor result; raw provider output and certificates are deliberately absent. */
final readonly class Operation implements \JsonSerializable
{
    public const ERRORS = ['acme.executor_unavailable', 'acme.dns_invalid', 'acme.credentials_invalid',
        'acme.challenge_failed', 'acme.provider_unavailable', 'acme.ca_unavailable', 'acme.rate_limited',
        'acme.timeout', 'acme.request_conflict', 'acme.request_expired', 'acme.result_invalid'];

    /** Construct a result bound to exactly one request; retries are bounded by executor policy. */
    public function __construct(
        public string $operationId,
        public string $requestBinding,
        public OperationState $state,
        public ?string $errorCode = null,
        public ?int $retryAfterSeconds = null,
    ) {
        if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}\z/D', $operationId)
            || !preg_match('/\A[0-9a-f]{64}\z/D', $requestBinding)
            || ($retryAfterSeconds !== null && ($retryAfterSeconds < 1 || $retryAfterSeconds > 3600))
            || ($errorCode !== null && !in_array($errorCode, self::ERRORS, true))
            || (in_array($state, [OperationState::Failed, OperationState::Expired], true) !== ($errorCode !== null))) {
            throw new \InvalidArgumentException('acme.result_invalid');
        }
    }

    /** Reject cross-request replies before exposing executor progress. */
    public function assertFor(CertificateRequest $request): self
    {
        if (!hash_equals($request->binding(), $this->requestBinding)) {
            throw new \UnexpectedValueException('acme.result_binding_mismatch');
        }
        return $this;
    }

    /** Serialize only the bounded public progress contract. */
    public function jsonSerialize(): array
    {
        return ['operationId' => $this->operationId, 'requestBinding' => $this->requestBinding,
            'state' => $this->state->value, 'errorCode' => $this->errorCode, 'retryAfterSeconds' => $this->retryAfterSeconds];
    }
}
