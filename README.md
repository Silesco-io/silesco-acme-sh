# silesco-acme-sh

Framework-independent PHP 8.3 library over **acme.sh 3.1.6**, package
`silesco-io/acme-sh` **0.2.0-alpha.1**. Not a new ACME client or DNS API implementation.

- All 198 pinned DNS drivers: 463 fields, 218 authorization variants, separate EN/RU help.
- Immutable requests, executor interface and sanitized operation results.
- Optional standalone Linux executor with explicit policy; no process/network execution by default.
- Exact upstream provenance in [UPSTREAM.json](UPSTREAM.json), reproducible catalog and read-only update check.

[Integration](docs/integration.md) · [Русский](docs/ru/integration.md) ·
[Standalone executor](docs/local-executor.md) · [Maintenance](docs/maintenance.md) ·
[Provider review](docs/provider-review.md) · [Generated API](docs/reference/api.md).

## Quick start / Быстрый старт

Requirements: PHP 8.3+, Composer 2 and `ext-json`. No Yii3 or Silesco installation
is required. The package is **not yet published to Packagist**. For the current
development version, run these commands in your application's directory:

Требования: PHP 8.3+, Composer 2 и `ext-json`. Yii3 и Silesco не нужны.
Пока пакет не опубликован в Packagist, подключите его из GitHub в каталоге своего приложения:

```sh
composer config repositories.silesco-acme-sh vcs https://github.com/Silesco-io/silesco-acme-sh.git
composer require "silesco-io/acme-sh:dev-codex/php-acme-stage3"
```

This selects a development branch, not a stable release. Commit `composer.lock`
to retain the selected revision; `composer update` may select a newer revision.
Do not lower the whole project's `minimum-stability` just for this package.
Это тестовая ветка, не стабильный релиз. Сохраните `composer.lock`, чтобы закрепить
ревизию; последующее `composer update` может её изменить.

Create `catalog.php` next to your application's `composer.json`:
Создайте `catalog.php` рядом с `composer.json` своего приложения:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use Silesco\AcmeSh\Catalog;

$catalog = Catalog::bundled();
echo 'DNS providers: ', count($catalog->all()), PHP_EOL;
echo json_encode(
    $catalog->form('dns_regru', 'ru'),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
), PHP_EOL;
```

Run `php catalog.php`. It prints REG.RU field descriptions, help and setup links;
it does not request credentials, call the provider or issue a certificate.
Your UI renders these metadata as escaped text; the library does not generate HTML.

Запустите `php catalog.php`: получите поля REG.RU, подсказки и ссылки настройки.
Пример не запрашивает секреты, не обращается к провайдеру и не выпускает сертификат.
Интерфейс создаёт ваше приложение с экранированием текста — библиотека не генерирует HTML.

For certificate issuance, continue with the [integration guide](docs/integration.md)
and [standalone Linux worker](docs/local-executor.md).
Для выпуска сертификатов: [руководство интеграции](docs/ru/integration.md) и
[локальный Linux-исполнитель](docs/ru/local-executor.md).
acme.sh must be provisioned separately; execution is opt-in with a fixed policy.
No browser-controlled shell commands, executable paths or CA endpoints are accepted.

Run `php tests/run.php`; export with `node scripts/export.mjs EMPTY_DIRECTORY`.
See [status](STATUS.md) for tested scope. All forms are covered; **zero live provider
accounts tested**. Issuance does not mean web-server activation or browser trust.

Открытая PHP-библиотека без зависимости от Yii3/Silesco. Каталог охватывает все DNS-драйверы
закреплённой версии; формы не являются доказательством работоспособности каждого аккаунта.
acme.sh поставляется отдельно под своей лицензией. В Silesco используется отдельный
адаптер существующего пути разрешённых мутаций, а не локальный запуск из веб-процесса.
