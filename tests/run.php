<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Silesco\AcmeSh\{Catalog, CertificateRequest, Operation, OperationState};
$count = 0;
function check(bool $test): void { global $count; $count++; if (!$test) throw new RuntimeException('assertion.' . $count); }
function rejects(callable $call, string $code): void {
    try { $call(); } catch (Throwable $e) { check($e->getMessage() === $code); return; }
    throw new RuntimeException('expected rejection ' . $code);
}
$catalog = Catalog::bundled();
check(count($catalog->all()) === 198);
check($catalog->upstream()['release'] === '3.1.6');
check($catalog->upstream() === json_decode(file_get_contents(__DIR__.'/../UPSTREAM.json'),true,32,JSON_THROW_ON_ERROR));
foreach ($catalog->all() as $id => $provider) {
    check((bool)preg_match('/\Adns_[A-Za-z0-9_]+\z/D', $id));
    check((bool)preg_match('/\A[0-9a-f]{64}\z/D', $provider['scriptSha256']));
    check($provider['accountTested'] === false);
    foreach (['en','ru'] as $locale) {
        $form = $catalog->form($id,$locale);
        check($form['providerId'] === $id);
        check($form['help'] !== '');
        check(str_starts_with($form['documentationUrl'],'https://'));
        foreach ($form['authVariants'] as $variant) {
            $selected = $catalog->form($id,$locale,$variant['id']);
            check($selected['fields'] === $variant['fields']);
            $fixture = [];
            foreach ($variant['fields'] as $field) {
                check($field['label'] !== '' && $field['help'] !== '');
                check(is_bool($field['secret']) && is_bool($field['required']));
                $fixture[$field['name']] = 'fixture';
            }
            $catalog->validateCredentials($id,$fixture,$variant['id']);
            check(true);
        }
    }
}
$en = $catalog->form('dns_regru'); $ru = $catalog->form('dns_regru', 'ru');
check(count($en['fields']) === 2);
check($en['fields'][0]['name'] === 'REGRU_API_Username');
check($en['fields'][1]['name'] === 'REGRU_API_Password');
check($en['fields'][1]['secret']);
check($en['fields'][1]['label'] !== $ru['fields'][1]['label']);
check($catalog->form('dns_regru', '../../etc/passwd') === $en);
check(count($catalog->form('dns_cf')['authVariants']) === 2);
rejects(fn() => $catalog->form('dns_cf','en','missing'), 'acme.auth_variant_unknown');
$catalog->validateCredentials('dns_cf',['CF_Token'=>'fixture']);
$catalog->validateCredentials('dns_cf',['CF_Key'=>'fixture','CF_Email'=>'fixture']);
rejects(fn() => $catalog->validateCredentials('dns_cf',['CF_Token'=>'fixture','CF_Key'=>'fixture','CF_Email'=>'fixture']), 'acme.credentials_invalid');
check($catalog->form('dns_mydevil')['fields'] === []);
check($catalog->form('dns_mydevil')['prerequisites'] !== []);
$catalog->validateCredentials('dns_beget',['Beget_Username'=>'fixture','Beget_Password'=>'fixture']);
rejects(fn() => $catalog->validateCredentials('dns_beget',['BEGET_Username'=>'fixture','BEGET_Password'=>'fixture']), 'acme.credentials_fields_invalid');
rejects(fn() => $catalog->provider('dns_missing'), 'acme.provider_unknown');
$catalog->validateCredentials('dns_regru', ['REGRU_API_Username' => 'fixture', 'REGRU_API_Password' => 'fixture']);
rejects(fn() => $catalog->validateCredentials('dns_regru', []), 'acme.credentials_invalid');
rejects(fn() => $catalog->validateCredentials('dns_regru', ['unknown'=>'fixture']), 'acme.credentials_fields_invalid');
rejects(fn() => $catalog->validateCredentials('dns_regru', ['REGRU_API_Username' => "a\n", 'REGRU_API_Password' => 'fixture']), 'acme.credentials_invalid');
rejects(fn() => $catalog->validateCredentials('dns_regru', ['REGRU_API_Username' => 'fixture', 'REGRU_API_Password' => str_repeat('x',4097)]), 'acme.credentials_invalid');
rejects(fn() => $catalog->validateCredentials('dns_regru', ['REGRU_API_Username' => 'fixture', 'REGRU_API_Password' => "fixture\0"]), 'acme.credentials_invalid');
rejects(fn() => $catalog->validateCredentials('dns_regru', ['REGRU_API_Username' => 'fixture', 'REGRU_API_Password' => ['fixture']]), 'acme.credentials_invalid');
$key = '019f6a21-0000-7000-8000-000000000001';
$request = CertificateRequest::http01(['panel.example.test', 'install.example.test'], $key);
check($request->challenge === 'http-01');
check(strlen($request->binding()) === 64);
check($request->binding() === CertificateRequest::http01($request->domains, $key)->binding());
foreach (['*.example.test','UPPER.example.test','example.test;id','https://example.test','127.0.0.1','example.test.','a..test','-a.test'] as $domain) {
    rejects(fn() => CertificateRequest::http01([$domain], $key), 'acme.domain_invalid');
}
rejects(fn() => CertificateRequest::http01([], $key), 'acme.domains_invalid');
rejects(fn() => CertificateRequest::http01(['a.test','a.test'], $key), 'acme.domain_duplicate');
rejects(fn() => CertificateRequest::http01(['a.test'], 'invalid'), 'acme.idempotency_key_invalid');
$dns = CertificateRequest::dns01(['*.example.test'], 'dns_regru', 'credential-1', $key);
check($dns->challenge === 'dns-01');
rejects(fn() => CertificateRequest::dns01(['a.test'],'dns_regru','/tmp/secret',$key),'acme.credential_reference_invalid');
$op = new Operation('op-1', $request->binding(), OperationState::Queued);
check($op->assertFor($request) === $op);
rejects(fn() => $op->assertFor($dns), 'acme.result_binding_mismatch');
rejects(fn() => new Operation('op-1',$request->binding(),OperationState::Failed,'raw provider secret'), 'acme.result_invalid');
rejects(fn() => new Operation('op-1',$request->binding(),OperationState::Issued,'acme.timeout'), 'acme.result_invalid');
rejects(fn() => new Operation('op-1',$request->binding(),OperationState::Failed), 'acme.result_invalid');
foreach (['ru','en'] as $locale) $locales[$locale] = json_decode(file_get_contents(__DIR__.'/../locales/'.$locale.'.json'),true,16,JSON_THROW_ON_ERROR);
check(array_keys($locales['ru']['messages']) === array_keys($locales['en']['messages']));
foreach ($locales['en']['messages'] as $key=>$message) {
    check($message['placeholders'] === $locales['ru']['messages'][$key]['placeholders']);
    check(!str_contains($message['message'],'<'));
}
echo "PASS $count assertions; no network, credentials or external issuance\n";
