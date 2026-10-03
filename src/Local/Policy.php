<?php
declare(strict_types=1);
namespace Silesco\AcmeSh\Local;

use Silesco\AcmeSh\Catalog;

/** Explicit standalone Linux execution policy. Request payload cannot change paths, CA or command. */
final readonly class Policy
{
    public string $sourceRoot;
    public string $stateRoot;
    public ?string $webroot;
    public string $certificateTrustBundle;
    public string $certificateTrustBundleSha256;

    /** Terms acceptance is an explicit developer decision for the selected CA, never a default. */
    public function __construct(
        string $sourceRoot,
        string $stateRoot,
        public string $caDirectory,
        public string $accountEmail,
        public bool $acceptTerms,
        string $certificateTrustBundle,
        ?string $webroot = null,
        public int $timeoutSeconds = 300,
        public array $allowedProviders = [],
    ) {
        if (PHP_OS_FAMILY !== 'Linux' || !extension_loaded('openssl') || !extension_loaded('posix')
            || !function_exists('proc_open')) {
            throw new \RuntimeException('acme.executor_unavailable');
        }
        if (!$acceptTerms || $timeoutSeconds < 1 || $timeoutSeconds > 3600
            || !filter_var($accountEmail, FILTER_VALIDATE_EMAIL) || strlen($accountEmail) > 254
            || preg_match('/[\x00-\x20\x7f]/', $accountEmail)
            || !preg_match('#\Ahttps://[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?(?::[1-9][0-9]{0,4})?/[a-zA-Z0-9/._~-]+\z#D', $caDirectory)
            || !array_is_list($allowedProviders) || count(array_unique($allowedProviders)) !== count($allowedProviders)) {
            throw new \InvalidArgumentException('acme.local_policy_invalid');
        }
        $this->sourceRoot = self::directory($sourceRoot, false);
        $this->stateRoot = self::directory($stateRoot, true);
        $this->webroot = $webroot === null ? null : self::directory($webroot, false);
        foreach ($allowedProviders as $provider) {
            if (!is_string($provider)) throw new \InvalidArgumentException('acme.local_policy_invalid');
            Catalog::bundled()->provider($provider);
        }
        if (realpath($certificateTrustBundle) !== $certificateTrustBundle || !str_starts_with($certificateTrustBundle, '/')) {
            throw new \InvalidArgumentException('acme.local_path_invalid');
        }
        self::directory(dirname($certificateTrustBundle), false);
        self::file($certificateTrustBundle, hash_file('sha256', $certificateTrustBundle));
        $this->certificateTrustBundle = $certificateTrustBundle;
        $this->certificateTrustBundleSha256 = hash_file('sha256', $certificateTrustBundle);
        if ($this->sourceRoot === $this->stateRoot || str_starts_with($this->stateRoot . '/', $this->sourceRoot . '/')
            || str_starts_with($this->sourceRoot . '/', $this->stateRoot . '/')) {
            throw new \InvalidArgumentException('acme.local_policy_invalid');
        }
        $this->verifySource();
    }

    /** Verify exact reviewed acme.sh and every pinned DNS driver before process execution. */
    public function verifySource(): void
    {
        self::directory($this->stateRoot, true);
        self::directory(dirname($this->certificateTrustBundle), false);
        self::file($this->certificateTrustBundle, $this->certificateTrustBundleSha256);
        $catalog = Catalog::bundled();
        $upstream = $catalog->upstream();
        $expected = $upstream['acmeScriptSha256'] ?? null;
        if (!is_string($expected) || !preg_match('/\A[0-9a-f]{64}\z/D', $expected)) {
            throw new \RuntimeException('acme.upstream_pin_invalid');
        }
        self::file($this->sourceRoot . '/acme.sh', $expected);
        $script = file_get_contents($this->sourceRoot . '/acme.sh');
        if (!is_string($script) || !preg_match('/^VER=' . preg_quote($upstream['release'], '/') . '$/m', $script)) {
            throw new \RuntimeException('acme.upstream_pin_invalid');
        }
        $directory = self::directory($this->sourceRoot . '/dnsapi', false);
        $expectedNames = [];
        foreach ($catalog->all() as $id => $provider) {
            $expectedNames[] = $id . '.sh';
            self::file($directory . '/' . $id . '.sh', $provider['scriptSha256']);
        }
        $actualNames = [];
        foreach (scandir($directory) ?: [] as $name) {
            if (str_starts_with($name, 'dns_') && str_ends_with($name, '.sh')) $actualNames[] = $name;
        }
        sort($actualNames); sort($expectedNames);
        if ($actualNames !== $expectedNames) throw new \RuntimeException('acme.upstream_pin_invalid');
    }

    /** Validate existing canonical directory without symlink components; private state must be owner-only. */
    public static function directory(string $path, bool $private): string
    {
        clearstatcache(true);
        if (!str_starts_with($path, '/') || $path === '/' || preg_match('/[\x00-\x20\x7f]/', $path)
            || realpath($path) !== rtrim($path, '/') || !is_dir($path)) {
            throw new \InvalidArgumentException('acme.local_path_invalid');
        }
        $current = '';
        foreach (explode('/', trim($path, '/')) as $part) {
            $current .= '/' . $part;
            if (is_link($current)) throw new \InvalidArgumentException('acme.local_path_invalid');
            $ancestor = stat($current);
            if ($ancestor === false || !in_array($ancestor['uid'], [0, posix_geteuid()], true)
                || (($ancestor['mode'] & 0022) !== 0 && !(($ancestor['mode'] & 01000) !== 0 && $ancestor['uid'] === 0))) {
                throw new \InvalidArgumentException('acme.local_path_permissions');
            }
        }
        $stat = stat($path);
        if ($stat === false || !in_array($stat['uid'], [0, posix_geteuid()], true)
            || ($stat['mode'] & 0022) !== 0
            || ($private && (($stat['mode'] & 0077) !== 0 || $stat['uid'] !== posix_geteuid()))) {
            throw new \InvalidArgumentException('acme.local_path_permissions');
        }
        return rtrim($path, '/');
    }

    /** Validate immutable regular source file and exact digest; no links or other-writable files. */
    private static function file(string $path, string $digest): void
    {
        clearstatcache(true, $path);
        $stat = lstat($path);
        if ($stat === false || !is_file($path) || is_link($path) || $stat['nlink'] !== 1
            || !in_array($stat['uid'], [0, posix_geteuid()], true) || ($stat['mode'] & 0022) !== 0
            || !hash_equals($digest, hash_file('sha256', $path))) {
            throw new \RuntimeException('acme.upstream_pin_invalid');
        }
    }
}
