<?php
declare(strict_types=1);
namespace Silesco\AcmeSh;

/** Complete version-pinned driver metadata; form coverage is not a live account test. */
final readonly class Catalog
{
    private function __construct(private array $data, private array $translations)
    {
        if (($data['schemaVersion'] ?? null) !== 2 || !is_array($data['providers'] ?? null)
            || !preg_match('/\A[0-9a-f]{40}\z/D', $data['upstream']['commit'] ?? '')
            || !preg_match('/\A[0-9a-f]{64}\z/D', $data['upstream']['acmeScriptSha256'] ?? '')
            || count($data['providers']) !== ($data['upstream']['dnsDriverCount'] ?? null)) {
            throw new \UnexpectedValueException('acme.catalog_invalid');
        }
    }

    /** Load immutable package resources; no network access or metadata execution. */
    public static function bundled(): self
    {
        $data = json_decode(file_get_contents(__DIR__ . '/../resources/providers.json'), true, 64, JSON_THROW_ON_ERROR);
        $locales = [];
        foreach (['en', 'ru'] as $locale) {
            $locales[$locale] = json_decode(file_get_contents(__DIR__ . '/../locales/' . $locale . '.json'), true, 32, JSON_THROW_ON_ERROR)['messages'];
        }
        return new self($data, $locales);
    }

    /** All DNS drivers at the pinned release, keyed by their exact upstream IDs. */
    public function all(): array { return $this->data['providers']; }

    /** Exact version, commit, source hashes and release provenance. */
    public function upstream(): array { return $this->data['upstream']; }

    /** Return metadata, or reject unsupported/unpinned driver names. */
    public function provider(string $id): array
    {
        return $this->data['providers'][$id] ?? throw new \InvalidArgumentException('acme.provider_unknown');
    }

    /** Localized form for an explicit authorization variant, or its declared default. */
    public function form(string $id, string $locale = 'en', ?string $variantId = null): array
    {
        $provider = $this->provider($id);
        $locale = isset($this->translations[$locale]) ? $locale : 'en';
        $variants = $provider['authVariants'];
        if ($variants === []) { throw new \UnexpectedValueException('acme.catalog_invalid'); }
        $variantId ??= $provider['defaultAuthVariant'] ?? $variants[0]['id'];
        $selected = null;
        $localizedVariants = [];
        $descriptors = array_column($provider['fields'], null, 'name');
        foreach ($variants as $variant) {
            $fields = [];
            foreach ($variant['fields'] as $requirement) {
                if (!isset($descriptors[$requirement['name']])) { throw new \UnexpectedValueException('acme.catalog_invalid'); }
                $field = array_replace($descriptors[$requirement['name']], $requirement);
                $field['label'] = $this->translate($locale, $field['labelKey']);
                $field['help'] = $this->translate($locale, $field['helpKey']);
                $fields[] = $field;
            }
            $item = ['id' => $variant['id'], 'label' => $this->translate($locale, $variant['labelKey']), 'fields' => $fields];
            $localizedVariants[] = $item;
            if ($variant['id'] === $variantId) { $selected = $item; }
        }
        if ($selected === null) { throw new \InvalidArgumentException('acme.auth_variant_unknown'); }
        return ['providerId' => $id, 'displayName' => $provider['displayName'],
            'variantId' => $variantId, 'fields' => $selected['fields'], 'authVariants' => $localizedVariants,
            'documentationUrl' => $provider['documentationUrl'],
            'credentialsUrl' => $provider['credentialsUrl'] ?? $provider['documentationUrl'],
            'help' => $this->translate($locale, $provider['helpKey']),
            'prerequisites' => array_map(fn(array $p): string => $this->translate($locale, $p['helpKey']), $provider['prerequisites'] ?? []),
            'formCoverage' => $provider['formCoverage'], 'accountTested' => $provider['accountTested']];
    }

    /** Validate field shape/declared requirements, not provider account access or authorization. */
    public function validateCredentials(string $id, #[\SensitiveParameter] array $values, ?string $variantId = null): void
    {
        $provider = $this->provider($id);
        $names = array_column($provider['fields'], 'name');
        foreach (array_keys($values) as $name) {
            if (!is_string($name) || !in_array($name, $names, true)) { throw new \InvalidArgumentException('acme.credentials_fields_invalid'); }
        }
        $variants = $variantId === null ? $provider['authVariants'] : array_values(array_filter(
            $provider['authVariants'], static fn(array $v): bool => $v['id'] === $variantId));
        if ($variants === []) { throw new \InvalidArgumentException('acme.auth_variant_unknown'); }
        foreach ($variants as $variant) { if ($this->matchesVariant($provider, $variant, $values)) { return; } }
        throw new \InvalidArgumentException('acme.credentials_invalid');
    }

    private function matchesVariant(array $provider, array $variant, #[\SensitiveParameter] array $values): bool
    {
        $requirements = array_column($variant['fields'], null, 'name');
        $descriptors = array_column($provider['fields'], null, 'name');
        // Do not silently mix two mutually exclusive credential sets.
        foreach ($values as $name => $value) { if (!isset($requirements[$name]) && $value !== '') { return false; } }
        foreach ($variant['fields'] as $requirement) {
            $field = $descriptors[$requirement['name']] ?? throw new \UnexpectedValueException('acme.catalog_invalid');
            $value = $values[$field['name']] ?? null;
            $required = $requirement['required'];
            if (isset($requirement['condition'])) {
                $condition = $requirement['condition'];
                if (isset($condition['context'])) {
                    // Infrastructure conditions are checked by executor policy, not guessed from secrets.
                    $required = false;
                } else {
                    $other = $values[$condition['field']] ?? null;
                    $matches = match ($condition['operator']) {
                        'present' => is_string($other) && $other !== '',
                        'equals' => $other === ($condition['value'] ?? null),
                        default => throw new \UnexpectedValueException('acme.catalog_invalid'),
                    };
                    $required = $required && $matches;
                }
            }
            if ($value === null || $value === '') { if ($required) { return false; } continue; }
            if (!is_string($value) || strlen($value) > $field['maxLength'] || str_contains($value, "\0")) { return false; }
            $multiline = ($field['type'] ?? '') === 'textarea' || ($field['multiline'] ?? false);
            if (preg_match($multiline ? '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/' : '/[\x00-\x1f\x7f]/', $value)) { return false; }
        }
        return true;
    }

    private function translate(string $locale, string $key): string
    {
        $entry = $this->translations[$locale][$key] ?? $this->translations['en'][$key] ?? null;
        if (!is_array($entry) || !is_string($entry['message'] ?? null)) { throw new \UnexpectedValueException('acme.catalog_translation_missing'); }
        return $entry['message'];
    }
}
