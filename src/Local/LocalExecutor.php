<?php
declare(strict_types=1);
namespace Silesco\AcmeSh\Local;

use Silesco\AcmeSh\{Catalog, CertificateRequest, Executor, Operation, OperationState};

/** Opt-in synchronous Linux worker for independent applications, not a Silesco web/host executor. */
final class LocalExecutor implements Executor
{
    private ProcessRunner $runner;

    /** Construction is a trusted configuration boundary; never construct policy/runner from web input. */
    public function __construct(private readonly Policy $policy, private readonly ?CredentialResolver $credentials = null,
        ?ProcessRunner $runner = null)
    {
        $this->runner = $runner ?? new NativeProcessRunner();
    }

    /** Issue once under a durable lock. Replays read the same terminal result, never another CA request. */
    public function submit(CertificateRequest $request): Operation
    {
        $this->policy->verifySource();
        $operationId = 'local-' . $request->idempotencyKey;
        $lock = $this->lock();
        try {
            $existing = $this->read($operationId);
            if ($existing !== null) {
                if (!hash_equals($existing['requestBinding'], $request->binding())
                    || !hash_equals($existing['policyBinding'], $this->policyBinding())) {
                    throw new \LogicException('acme.request_conflict');
                }
                return $this->operation($existing, true);
            }
            $root = $this->policy->stateRoot . '/' . $operationId;
            $this->mkdir($root); $this->mkdir($root . '/certs'); $this->mkdir($this->policy->stateRoot . '/accounts');
            $record = ['schemaVersion' => 1, 'operationId' => $operationId, 'requestBinding' => $request->binding(),
                'policyBinding' => $this->policyBinding(), 'deadlineUnix' => time() + $this->policy->timeoutSeconds,
                'state' => 'running', 'errorCode' => null, 'certificate' => null];
            // Before the first possible network request: a crash leaves ambiguous running, never automatic retry.
            $this->write($root . '/operation.json', $record);
            try {
                $environment = ['PATH' => '/usr/bin:/bin', 'HOME' => $root, 'LANG' => 'C', 'LC_ALL' => 'C',
                    'DEBUG' => '0', 'LOG_LEVEL' => '0', 'SYS_LOG' => '0', 'NO_COLOR' => '1',
                    'AUTO_UPGRADE' => '0', 'UPGRADE_HASH' => '', 'LOG_FILE' => '', 'LE_LOG_FILE' => ''];
                $arguments = ['/bin/sh', $this->policy->sourceRoot . '/acme.sh', '--issue',
                    '--home', $this->policy->sourceRoot, '--config-home', $this->policy->stateRoot . '/accounts',
                    '--cert-home', $root . '/certs', '--accountconf', $root . '/account.conf',
                    '--server', $this->policy->caDirectory, '--accountemail', $this->policy->accountEmail,
                    '--keylength', 'ec-256', '--log-level', '0', '--syslog', '0', '--no-color'];
                // Accountconf is new for this operation: no persisted hooks, syslog or unsafe debug configuration.
                $this->writeBytes($root . '/account.conf', '');
                if ($request->challenge === 'http-01') {
                    if ($this->policy->webroot === null) throw new \RuntimeException('acme.executor_unavailable');
                    $arguments = [...$arguments, '--webroot', $this->policy->webroot];
                } else {
                    if (!in_array($request->providerId, $this->policy->allowedProviders, true) || $this->credentials === null) {
                        throw new \RuntimeException('acme.executor_unavailable');
                    }
                    try { $values = $this->credentials->resolve($request->providerId, $request->credentialReference); }
                    catch (\Throwable) { throw new \RuntimeException('acme.credentials_invalid'); }
                    Catalog::bundled()->validateCredentials($request->providerId, $values);
                    foreach ($values as $name => $value) {
                        if (!is_string($name) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $name)
                            || array_key_exists($name, $environment)) throw new \RuntimeException('acme.credentials_invalid');
                        $environment[$name] = $value;
                    }
                    unset($values);
                    $arguments = [...$arguments, '--dns', $request->providerId];
                }
                foreach ($request->domains as $domain) $arguments = [...$arguments, '--domain', $domain];
                $remaining = $record['deadlineUnix'] - time();
                if ($remaining < 1) throw new \RuntimeException('acme.request_expired');
                try { $exit = $this->runner->run($arguments, $environment, $root, $remaining); }
                finally { unset($environment); }
                if ($exit !== 0) throw new \RuntimeException('acme.challenge_failed');
                $record['certificate'] = CertificateVerifier::verify($root . '/certs', $request->domains, $this->policy->certificateTrustBundle);
                $record['state'] = 'issued';
            } catch (\Throwable $error) {
                $record['state'] = 'failed';
                $code = $error->getMessage();
                $record['errorCode'] = in_array($code, Operation::ERRORS, true) ? $code : 'acme.executor_unavailable';
            }
            $this->secureTree($root);
            $this->secureTree($this->policy->stateRoot . '/accounts');
            $this->write($root . '/operation.json', $record);
            return $this->operation($record, false);
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    /** Read-only polling. It never starts issuance or guesses success after worker loss. */
    public function poll(string $operationId): Operation
    {
        Policy::directory($this->policy->stateRoot, true);
        $record = $this->read($operationId);
        if ($record === null) throw new \InvalidArgumentException('acme.operation_unknown');
        return $this->operation($record, false);
    }

    /** Public metadata only; private key values, provider responses and credentials never enter results. */
    public function certificateMetadata(string $operationId): ?array
    {
        Policy::directory($this->policy->stateRoot, true);
        $record = $this->read($operationId);
        if ($record === null) throw new \InvalidArgumentException('acme.operation_unknown');
        return $record['state'] === 'issued' ? $record['certificate'] : null;
    }

    /** Hide collaborators and credential storage implementations from accidental object dumps. */
    public function __debugInfo(): array { return ['profile' => 'standalone-linux-local']; }

    /** Do not serialize a credential resolver, runner or trusted execution authority. */
    public function __serialize(): array { throw new \LogicException('acme.executor_not_serializable'); }

    /** Trusted runtime policy cannot be restored from serialized input. */
    public function __unserialize(array $data): void { throw new \LogicException('acme.executor_not_serializable'); }

    private function policyBinding(): string
    {
        return hash('sha256', json_encode([$this->policy->sourceRoot, $this->policy->stateRoot, $this->policy->webroot,
            $this->policy->caDirectory, $this->policy->accountEmail, $this->policy->allowedProviders,
            $this->policy->certificateTrustBundleSha256, $this->policy->timeoutSeconds,
            $this->policy->acceptTerms, Catalog::bundled()->upstream()], JSON_THROW_ON_ERROR));
    }

    private function operation(array $record, bool $recovered): Operation
    {
        $state = OperationState::from($record['state']);
        if ($state === OperationState::Running && time() >= $record['deadlineUnix']) {
            return new Operation($record['operationId'], $record['requestBinding'], OperationState::Expired, 'acme.request_expired');
        }
        if ($recovered && $state === OperationState::Running) {
            return new Operation($record['operationId'], $record['requestBinding'], OperationState::Failed, 'acme.executor_unavailable');
        }
        return new Operation($record['operationId'], $record['requestBinding'], $state, $record['errorCode']);
    }

    private function lock()
    {
        $path = $this->policy->stateRoot . '/executor.lock';
        $lock = @fopen($path, 'x+');
        if ($lock !== false) chmod($path, 0600);
        else { $this->privateFile($path); $lock = @fopen($path, 'r+'); }
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            throw new \RuntimeException('acme.executor_unavailable');
        }
        return $lock;
    }

    private function read(string $id): ?array
    {
        if (!preg_match('/\Alocal-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $id)) {
            throw new \InvalidArgumentException('acme.operation_id_invalid');
        }
        $root = $this->policy->stateRoot . '/' . $id;
        if (!file_exists($root)) return null;
        Policy::directory($root, true);
        $path = $root . '/operation.json'; $this->privateFile($path);
        $json = file_get_contents($path);
        if ($json === false || strlen($json) > 65536) throw new \RuntimeException('acme.result_invalid');
        try { $record = json_decode($json, true, 16, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new \RuntimeException('acme.result_invalid'); }
        if (!is_array($record) || array_keys($record) !== ['schemaVersion', 'operationId', 'requestBinding', 'policyBinding', 'deadlineUnix', 'state', 'errorCode', 'certificate']
            || $record['schemaVersion'] !== 1 || $record['operationId'] !== $id
            || !is_int($record['deadlineUnix']) || $record['deadlineUnix'] < 1
            || !is_string($record['policyBinding']) || !preg_match('/\A[0-9a-f]{64}\z/D', $record['policyBinding'])) {
            throw new \RuntimeException('acme.result_invalid');
        }
        try {
            $this->operation($record, false);
            if ($record['state'] === 'issued' && (!is_array($record['certificate'])
                || array_keys($record['certificate']) !== ['domains', 'sha256', 'serial', 'notBefore', 'notAfter'])) {
                throw new \RuntimeException('acme.result_invalid');
            }
        } catch (\Throwable) { throw new \RuntimeException('acme.result_invalid'); }
        return $record;
    }

    private function mkdir(string $path): void
    {
        $created = !file_exists($path);
        if ($created && !@mkdir($path, 0700)) throw new \RuntimeException('acme.executor_unavailable');
        Policy::directory($path, true);
        if ($created) $this->syncDirectory(dirname($path));
    }

    private function privateFile(string $path): void
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || !is_file($path) || is_link($path) || $stat['nlink'] !== 1 || $stat['uid'] !== posix_geteuid()
            || ($stat['mode'] & 0077) !== 0) throw new \RuntimeException('acme.result_invalid');
    }

    private function write(string $path, array $record): void
    {
        $this->writeBytes($path, json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");
    }

    private function writeBytes(string $path, #[\SensitiveParameter] string $bytes): void
    {
        $temporary = dirname($path) . '/.pending-' . bin2hex(random_bytes(16));
        $file = @fopen($temporary, 'x');
        if ($file === false) throw new \RuntimeException('acme.executor_unavailable');
        try {
            chmod($temporary, 0600);
            if (fwrite($file, $bytes) !== strlen($bytes) || !fflush($file) || !fsync($file)) {
                throw new \RuntimeException('acme.executor_unavailable');
            }
        } finally { fclose($file); }
        if (!rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('acme.executor_unavailable'); }
        $this->syncDirectory(dirname($path));
    }

    private function syncDirectory(string $path): void
    {
        $directory = @fopen($path, 'r');
        if ($directory === false || !fsync($directory)) {
            if (is_resource($directory)) fclose($directory);
            throw new \RuntimeException('acme.executor_unavailable');
        }
        fclose($directory);
    }

    private function secureTree(string $root): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST) as $entry) {
            if ($entry->isLink() || (!$entry->isDir() && !$entry->isFile())) throw new \RuntimeException('acme.result_invalid');
            $stat = lstat($entry->getPathname());
            if ($stat === false || $stat['uid'] !== posix_geteuid() || (!$entry->isDir() && $stat['nlink'] !== 1)
                || !chmod($entry->getPathname(), $entry->isDir() ? 0700 : 0600)) {
                throw new \RuntimeException('acme.result_invalid');
            }
            $handle = @fopen($entry->getPathname(), 'r');
            if ($handle === false || !fsync($handle)) {
                if (is_resource($handle)) fclose($handle);
                throw new \RuntimeException('acme.executor_unavailable');
            }
            fclose($handle);
        }
        $this->syncDirectory($root);
    }
}
