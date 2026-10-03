<?php
declare(strict_types=1);
namespace Silesco\AcmeSh\Local;

/** Developer-supplied secret custody boundary; no default store, file lookup or logging. */
interface CredentialResolver
{
    /** Resolve an opaque handle into exact provider environment fields; never log values or exceptions. */
    public function resolve(string $providerId, string $reference): array;
}
