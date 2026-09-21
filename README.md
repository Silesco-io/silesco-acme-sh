# silesco-acme-sh

Independent PHP 8.3 SDK, `silesco-io/acme-sh` 0.1.0-alpha.1. Catalog of 191 pinned DNS
drivers, reviewed REG.RU form, immutable requests and executor interface. Not a
new ACME client. [Integration/coverage](docs/integration.md),
[Русский](docs/ru/integration.md), [API](docs/reference/api.md).

Run `php tests/run.php`; export offline with `node scripts/export.mjs EMPTY_DIR`.

Независимая PHP-библиотека над acme.sh: каталог DNS-провайдеров, настройка и операции через сменяемого исполнителя. Не новая реализация ACME или DNS API.

Начать с AGENTS.md, PROJECT.md, DECISIONS.md и STATUS.md. Архитектура — context/ и spec/, документация компонента — docs/. Реальная выдача сертификата проверяется отдельно вместе с исполнителем; каталог не означает готовность всех провайдеров.
