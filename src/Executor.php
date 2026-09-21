<?php
declare(strict_types=1);
namespace Silesco\AcmeSh;

/** Adapter boundary. Implementations own authorization, custody, persistence, deadlines and retry policy. */
interface Executor
{
    /** Submit once or recover the exact same idempotent operation; conflicting reuse must fail. */
    public function submit(CertificateRequest $request): Operation;
    /** Read sanitized progress; polling must not execute another issuance. */
    public function poll(string $operationId): Operation;
}
