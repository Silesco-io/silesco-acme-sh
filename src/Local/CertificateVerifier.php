<?php
declare(strict_types=1);
namespace Silesco\AcmeSh\Local;

/** Read back actual issuance material; command exit status never proves a certificate was issued. */
final class CertificateVerifier
{
    /** Verify bounded PEM, exact SANs, time, chain against fixed roots and matching private key; returns public metadata only. */
    public static function verify(string $certRoot, array $domains, string $trustBundle): array
    {
        $directory = $certRoot . '/' . $domains[0] . '_ecc';
        try { Policy::directory($directory, true); }
        catch (\Throwable) { throw new \RuntimeException('acme.result_invalid'); }
        $leafPath = $directory . '/' . $domains[0] . '.cer';
        $keyPath = $directory . '/' . $domains[0] . '.key';
        $chainPath = $directory . '/ca.cer';
        $leaf = self::read($leafPath, 262144);
        $privateKey = self::read($keyPath, 32768);
        $certificate = @openssl_x509_read($leaf);
        $key = @openssl_pkey_get_private($privateKey);
        unset($privateKey);
        if ($certificate === false || $key === false || !openssl_x509_check_private_key($certificate, $key)) {
            throw new \RuntimeException('acme.result_invalid');
        }
        unset($key);
        $parsed = @openssl_x509_parse($certificate);
        if ($parsed === false || ($parsed['validFrom_time_t'] ?? PHP_INT_MAX) > time()
            || ($parsed['validTo_time_t'] ?? 0) < time() + 60) throw new \RuntimeException('acme.result_invalid');
        $sans = array_map('trim', explode(',', $parsed['extensions']['subjectAltName'] ?? ''));
        $names = [];
        foreach ($sans as $san) {
            if (!str_starts_with($san, 'DNS:')) throw new \RuntimeException('acme.result_invalid');
            $names[] = substr($san, 4);
        }
        $expected = $domains; sort($expected); sort($names);
        if ($names !== $expected) throw new \RuntimeException('acme.result_invalid');
        $chain = self::read($chainPath, 524288);
        $fullchain = self::read($directory . '/fullchain.cer', 786432);
        $fingerprints = static function (string $pem): array {
            if (!preg_match_all('/-----BEGIN CERTIFICATE-----[A-Za-z0-9+\/=\r\n]+-----END CERTIFICATE-----/', $pem, $matches)) {
                throw new \RuntimeException('acme.result_invalid');
            }
            $result = [];
            foreach ($matches[0] as $item) {
                $cert = @openssl_x509_read($item);
                if ($cert === false) throw new \RuntimeException('acme.result_invalid');
                $result[] = openssl_x509_fingerprint($cert, 'sha256');
            }
            return $result;
        };
        if ($fingerprints($fullchain) !== [...$fingerprints($leaf), ...$fingerprints($chain)]) {
            throw new \RuntimeException('acme.result_invalid');
        }
        if (@openssl_x509_checkpurpose($certificate, X509_PURPOSE_SSL_SERVER, [$trustBundle], $chainPath) !== true) {
            throw new \RuntimeException('acme.result_invalid');
        }
        return ['domains' => $names, 'sha256' => openssl_x509_fingerprint($certificate, 'sha256'),
            'serial' => $parsed['serialNumberHex'] ?? '', 'notBefore' => $parsed['validFrom_time_t'],
            'notAfter' => $parsed['validTo_time_t']];
    }

    /** Read protected non-linked material, never follow a link to external credentials. */
    private static function read(string $path, int $maximum): string
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || !is_file($path) || is_link($path) || $stat['nlink'] !== 1
            || $stat['uid'] !== posix_geteuid() || ($stat['mode'] & 0077) !== 0
            || $stat['size'] < 1 || $stat['size'] > $maximum) throw new \RuntimeException('acme.result_invalid');
        $data = file_get_contents($path);
        if ($data === false) throw new \RuntimeException('acme.result_invalid');
        return $data;
    }
}
