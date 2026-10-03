<?php
declare(strict_types=1);
namespace Silesco\AcmeSh\Local;

/** Trusted process boundary, injectable only by application construction, not request input. */
interface ProcessRunner
{
    /** Return exit code, or stable acme.timeout/acme.executor_unavailable; discard all raw output. */
    public function run(array $arguments, #[\SensitiveParameter] array $environment, string $workingDirectory, int $timeoutSeconds): int;
}
