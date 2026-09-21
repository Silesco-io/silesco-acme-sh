<?php
declare(strict_types=1);
namespace Silesco\AcmeSh;

/** Pinned upstream inventory. A listed provider does not imply a reviewed form or tested account. */
final readonly class Catalog
{
    private function __construct(private array $data) {}

    /** Load shipped immutable data, without network access or code execution. */
    public static function bundled(): self
    {
        return new self(json_decode(file_get_contents(__DIR__ . '/../resources/providers.json'), true, 32, JSON_THROW_ON_ERROR));
    }

    /** Complete pinned driver inventory keyed by exact upstream provider ID. */
    public function all(): array { return $this->data['providers']; }

    /** Exact upstream provenance for the inventory. */
    public function upstream(): array { return $this->data['upstream']; }

    /** Return metadata or reject unsupported/unpinned driver names. */
    public function provider(string $id): array
    {
        return $this->data['providers'][$id] ?? throw new \InvalidArgumentException('acme.provider_unknown');
    }

    /** Return reviewed localized field descriptors. Never returns credential values. */
    public function form(string $id, string $locale = 'en'): array
    {
        $provider = $this->provider($id);
        if ($provider['formCoverage'] !== 'reviewed') {
            throw new \LogicException('acme.provider_form_unreviewed');
        }
        $locale = in_array($locale, ['ru', 'en'], true) ? $locale : 'en';
        $catalog = json_decode(file_get_contents(__DIR__ . '/../locales/' . $locale . '.json'), true, 16, JSON_THROW_ON_ERROR);
        $fields = [];
        foreach ($provider['fields'] as $field) {
            $field['label'] = $catalog['messages'][$field['labelKey']]['message'];
            $field['help'] = $catalog['messages'][$field['helpKey']]['message'];
            $fields[] = $field;
        }
        return ['providerId' => $id, 'displayName' => $provider['displayName'], 'fields' => $fields,
            'documentationUrl' => $provider['documentationUrl'], 'credentialsUrl' => $provider['credentialsUrl'],
            'help' => $catalog['messages'][$provider['helpKey']]['message'], 'accountTested' => false];
    }

    /** Validate only reviewed form shape; never logs, persists, returns or probes supplied credentials. */
    public function validateCredentials(string $id, #[\SensitiveParameter] array $values): void
    {
        $form = $this->form($id);
        if (array_diff(array_keys($values), array_column($form['fields'], 'name')) !== []) {
            throw new \InvalidArgumentException('acme.credentials_fields_invalid');
        }
        foreach ($form['fields'] as $field) {
            $value = $values[$field['name']] ?? null;
            if (!is_string($value) || $value === '' || strlen($value) > $field['maxLength']
                || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new \InvalidArgumentException('acme.credentials_invalid');
            }
        }
    }
}
