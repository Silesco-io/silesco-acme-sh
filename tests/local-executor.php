<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Silesco\AcmeSh\{Catalog, CertificateRequest, OperationState};
use Silesco\AcmeSh\Local\{Policy, CredentialResolver, LocalExecutor, NativeProcessRunner, ProcessRunner};
// Isolated Linux fixture only. No network, provider account, CA registration or trusted-root modification.
$count = 0;
function localCheck(bool $value): void { global $count; if (!$value) throw new RuntimeException('local.assertion.' . ($count + 1)); $count++; }
function localReject(callable $operation, string $code): void {
    try { $operation(); } catch (Throwable $error) { localCheck($error->getMessage() === $code); return; }
    throw new RuntimeException('local.expected_rejection');
}
if (PHP_OS_FAMILY !== 'Linux') throw new RuntimeException('local.tests_require_linux');
umask(0077);
putenv('INHERITED_DEBUG_SECRET=fixture-secret'); putenv('DEBUG=3'); putenv('SYS_LOG=7');
$root = '/tmp/acme-local-' . bin2hex(random_bytes(8)); mkdir($root, 0700);
$source = getenv('ACME_SH_TEST_SOURCE');
if (!$source || !is_dir($source)) throw new RuntimeException('local.pinned_source_required');
mkdir($root . '/source', 0755); mkdir($root . '/source/dnsapi', 0755);
copy($source . '/acme.sh', $root . '/source/acme.sh'); chmod($root . '/source/acme.sh', 0644);
foreach (Catalog::bundled()->all() as $id => $_provider) {
    copy($source . '/dnsapi/' . $id . '.sh', $root . '/source/dnsapi/' . $id . '.sh');
    chmod($root . '/source/dnsapi/' . $id . '.sh', 0644);
}
$configuration = $root . '/openssl.conf';
file_put_contents($configuration, "[req]\ndistinguished_name=dn\n[dn]\n[ca]\nbasicConstraints=critical,CA:true\nkeyUsage=critical,keyCertSign,cRLSign\n");
$caKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$caCsr = openssl_csr_new(['commonName' => 'Offline fixture CA'], $caKey, ['config' => $configuration, 'digest_alg' => 'sha256']);
$ca = openssl_csr_sign($caCsr, null, $caKey, 2, ['config' => $configuration, 'x509_extensions' => 'ca', 'digest_alg' => 'sha256']);
openssl_x509_export($ca, $caPem); file_put_contents($root . '/ca.pem', $caPem);
mkdir($root . '/webroot', 0700);
function localPolicy(string $name, array $providers = []): Policy {
    global $root;
    $state = $root . '/' . $name; if (!is_dir($state)) mkdir($state, 0700);
    return new Policy($root . '/source', $state, 'https://ca.example.test/directory', 'operator@example.test', true,
        $root . '/ca.pem', $root . '/webroot', 2, $providers);
}
final class FixtureRunner implements ProcessRunner {
    public int $calls = 0;
    public bool $safeArguments = false;
    public function __construct(private string $mode = 'valid') {}
    public function run(array $arguments, #[SensitiveParameter] array $environment, string $workingDirectory, int $timeoutSeconds): int {
        global $root, $ca, $caKey;
        $this->calls++;
        $this->safeArguments = $arguments[0] === '/bin/sh' && !in_array('fixture-secret', $arguments, true)
            && ($environment['DEBUG'] ?? '') === '0' && ($environment['SYS_LOG'] ?? '') === '0'
            && !isset($environment['INHERITED_DEBUG_SECRET']);
        if ($this->mode === 'failed') return 1;
        if ($this->mode === 'timeout') throw new RuntimeException('acme.timeout');
        if ($this->mode === 'raw_error') throw new RuntimeException('fixture-secret');
        if ($this->mode === 'empty') return 0;
        $domains = []; $certRoot = '';
        foreach ($arguments as $i => $arg) {
            if ($arg === '--domain') $domains[] = $arguments[$i + 1];
            if ($arg === '--cert-home') $certRoot = $arguments[$i + 1];
        }
        $directory = $certRoot . '/' . $domains[0] . '_ecc'; mkdir($directory, 0700);
        $actual = $this->mode === 'wrong_san' ? ['other.example.test'] : $domains;
        $configuration = $root . '/leaf.conf';
        file_put_contents($configuration, "[req]\ndistinguished_name=dn\n[dn]\n[leaf]\nbasicConstraints=critical,CA:false\nkeyUsage=critical,digitalSignature\nextendedKeyUsage=serverAuth\nsubjectAltName=" . implode(',', array_map(fn($d) => 'DNS:' . $d, $actual)) . "\n");
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $csr = openssl_csr_new(['commonName' => $domains[0]], $key, ['config' => $configuration, 'digest_alg' => 'sha256']);
        $leaf = openssl_csr_sign($csr, $this->mode === 'untrusted_chain' ? null : $ca,
            $this->mode === 'untrusted_chain' ? $key : $caKey, $this->mode === 'expired' ? 0 : 1,
            ['config' => $configuration, 'x509_extensions' => 'leaf', 'digest_alg' => 'sha256']);
        openssl_x509_export($leaf, $pem); openssl_pkey_export($key, $keyPem);
        if ($this->mode === 'wrong_key') { $wrong = openssl_pkey_new(['private_key_bits' => 2048]); openssl_pkey_export($wrong, $keyPem); }
        file_put_contents($directory . '/' . $domains[0] . '.cer', $pem);
        file_put_contents($directory . '/' . $domains[0] . '.key', $keyPem);
        file_put_contents($directory . '/ca.cer', file_get_contents($root . '/ca.pem'));
        file_put_contents($directory . '/fullchain.cer', $pem . file_get_contents($root . '/ca.pem'));
        if ($this->mode === 'wrong_fullchain') file_put_contents($directory . '/fullchain.cer', file_get_contents($root . '/ca.pem'));
        if ($this->mode === 'symlink') {
            unlink($directory . '/' . $domains[0] . '.key'); symlink($root . '/ca.pem', $directory . '/' . $domains[0] . '.key');
        }
        return 0;
    }
}
final class FixtureCredentials implements CredentialResolver {
    public function resolve(string $providerId, string $reference): array {
        if ($reference === 'bad') throw new RuntimeException('fixture-secret');
        return ['REGRU_API_Username' => 'fixture-user', 'REGRU_API_Password' => 'fixture-secret'];
    }
}
$key = '019f6a21-0000-7000-8000-000000000001';
$request = CertificateRequest::http01(['panel.example.test', 'install.example.test'], $key);
$runner = new FixtureRunner(); $policy = localPolicy('good'); $executor = new LocalExecutor($policy, null, $runner);
$result = $executor->submit($request); localCheck($result->state === OperationState::Issued); localCheck($runner->safeArguments);
localCheck($executor->poll($result->operationId)->state === OperationState::Issued);
localCheck($executor->submit($request)->state === OperationState::Issued && $runner->calls === 1);
$restarted = new LocalExecutor($policy, null, new FixtureRunner('failed'));
localCheck($restarted->submit($request)->state === OperationState::Issued);
localCheck($executor->certificateMetadata($result->operationId)['domains'] === ['install.example.test', 'panel.example.test']);
localCheck(str_contains(json_encode($result), 'fixture-secret') === false);
localReject(fn() => serialize($executor), 'acme.executor_not_serializable');
localReject(fn() => $executor->submit(CertificateRequest::http01(['different.example.test'], $key)), 'acme.request_conflict');
$changedPolicy = new Policy($root . '/source', $policy->stateRoot, $policy->caDirectory, $policy->accountEmail, true,
    $policy->certificateTrustBundle, $policy->webroot, 3);
localReject(fn() => (new LocalExecutor($changedPolicy, null, new FixtureRunner()))->submit($request), 'acme.request_conflict');
localReject(fn() => $executor->poll('../../etc/passwd'), 'acme.operation_id_invalid');
localReject(fn() => $executor->poll('local-019f6a21-0000-7000-8000-000000000009'), 'acme.operation_unknown');
foreach (['failed' => 'acme.challenge_failed', 'timeout' => 'acme.timeout', 'empty' => 'acme.result_invalid',
    'wrong_san' => 'acme.result_invalid', 'wrong_key' => 'acme.result_invalid', 'untrusted_chain' => 'acme.result_invalid',
    'expired' => 'acme.result_invalid', 'wrong_fullchain' => 'acme.result_invalid', 'raw_error' => 'acme.executor_unavailable'] as $mode => $error) {
    $fake = new FixtureRunner($mode); $test = new LocalExecutor(localPolicy($mode), null, $fake);
    $reply = $test->submit($request); localCheck($reply->state === OperationState::Failed && $reply->errorCode === $error);
    localCheck($test->submit($request)->errorCode === $error && $fake->calls === 1);
}
$dns = CertificateRequest::dns01(['*.example.test'], 'dns_regru', 'fixture-reference', $key);
$dnsRunner = new FixtureRunner(); $dnsExecutor = new LocalExecutor(localPolicy('dns', ['dns_regru']), new FixtureCredentials(), $dnsRunner);
localCheck($dnsExecutor->submit($dns)->state === OperationState::Issued); localCheck($dnsRunner->safeArguments);
$disabled = new LocalExecutor(localPolicy('dns-disabled'), new FixtureCredentials(), new FixtureRunner());
localCheck($disabled->submit($dns)->errorCode === 'acme.executor_unavailable');
localCheck($dnsExecutor->certificateMetadata('local-' . $key)['domains'] === ['*.example.test']);
$invalidCreds = new LocalExecutor(localPolicy('bad-creds', ['dns_regru']), new FixtureCredentials(), new FixtureRunner());
localCheck($invalidCreds->submit(CertificateRequest::dns01(['*.example.test'], 'dns_regru', 'bad', $key))->errorCode === 'acme.credentials_invalid');
$journal = $policy->stateRoot . '/' . $result->operationId . '/operation.json';
$record = json_decode(file_get_contents($journal), true); $record['state'] = 'running'; $record['certificate'] = null; $record['deadlineUnix'] = time() + 30;
file_put_contents($journal, json_encode($record));
$recoveryRunner = new FixtureRunner(); $recovery = new LocalExecutor($policy, null, $recoveryRunner);
localCheck($recovery->submit($request)->errorCode === 'acme.executor_unavailable' && $recoveryRunner->calls === 0);
$record['deadlineUnix'] = time() - 1; file_put_contents($journal, json_encode($record));
localCheck($recovery->poll($result->operationId)->state === OperationState::Expired && $recoveryRunner->calls === 0);
localCheck(!str_contains(file_get_contents($journal), 'fixture-secret'));
$native = new NativeProcessRunner();
file_put_contents($root . '/output.sh', "#!/bin/sh\nprintf fixture-secret\nprintf fixture-secret >&2\nexit 7\n");
ob_start(); $exit = $native->run(['/bin/sh', $root . '/output.sh'], ['PATH' => '/usr/bin:/bin'], $root, 2); $printed = ob_get_clean();
localCheck($exit === 7 && $printed === '');
file_put_contents($root . '/environment.sh', <<<'SH'
#!/bin/sh
test "${INHERITED_DEBUG_SECRET+present}" != present || exit 99
test "$DEBUG" = 0 || exit 98
exit 0
SH);
localCheck($native->run(['/bin/sh', $root . '/environment.sh'], ['PATH' => '/usr/bin:/bin', 'DEBUG' => '0'], $root, 2) === 0);
file_put_contents($root . '/overflow.sh', "#!/bin/sh\ndd if=/dev/zero bs=65536 count=40\n");
localReject(fn() => $native->run(['/bin/sh', $root . '/overflow.sh'], ['PATH' => '/usr/bin:/bin'], $root, 2), 'acme.executor_unavailable');
file_put_contents($root . '/timeout.sh', "#!/bin/sh\nsleep 30 &\nwait\n");
$start = microtime(true); localReject(fn() => $native->run(['/bin/sh', $root . '/timeout.sh'], ['PATH' => '/usr/bin:/bin'], $root, 1), 'acme.timeout');
localCheck(microtime(true) - $start < 3);
$symlinkExecutor = new LocalExecutor(localPolicy('symlink'), null, new FixtureRunner('symlink'));
localReject(fn() => $symlinkExecutor->submit($request), 'acme.result_invalid');
localCheck($symlinkExecutor->submit($request)->errorCode === 'acme.executor_unavailable');
mkdir($root . '/bad-permissions', 0700); chmod($root . '/bad-permissions', 0755);
localReject(fn() => new Policy($root . '/source', $root . '/bad-permissions', 'https://ca.example.test/directory', 'operator@example.test', true, $root . '/ca.pem'), 'acme.local_path_permissions');
symlink($root . '/source', $root . '/source-link');
localReject(fn() => new Policy($root . '/source-link', $policy->stateRoot, 'https://ca.example.test/directory', 'operator@example.test', true, $root . '/ca.pem'), 'acme.local_path_invalid');
$mutablePolicy = localPolicy('permissions-mutated'); chmod($mutablePolicy->stateRoot, 0755); clearstatcache();
localReject(fn() => (new LocalExecutor($mutablePolicy, null, new FixtureRunner()))->submit($request), 'acme.local_path_permissions');
localReject(fn() => new Policy($root . '/source', $policy->stateRoot, 'http://unsafe.test/directory', 'operator@example.test', true, $root . '/ca.pem'), 'acme.local_policy_invalid');
localReject(fn() => new Policy($root . '/source', $policy->stateRoot, 'https://ca.example.test/directory', 'operator@example.test', false, $root . '/ca.pem'), 'acme.local_policy_invalid');
file_put_contents($root . '/source/acme.sh', "# changed\n", FILE_APPEND);
localReject(fn() => $policy->verifySource(), 'acme.upstream_pin_invalid');
echo "Local executor OK: {$count} assertions; offline fixtures only\n";
