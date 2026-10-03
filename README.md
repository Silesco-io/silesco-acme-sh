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

Run `php tests/run.php`; export with `node scripts/export.mjs EMPTY_DIRECTORY`.
See [status](STATUS.md) for tested scope. All forms are covered; **zero live provider
accounts tested**. Issuance does not mean web-server activation or browser trust.

Открытая PHP-библиотека без зависимости от Yii3/Silesco. Каталог охватывает все DNS-драйверы
закреплённой версии; формы не являются доказательством работоспособности каждого аккаунта.
acme.sh поставляется отдельно под своей лицензией. В Silesco используется отдельный
адаптер существующего пути разрешённых мутаций, а не локальный запуск из веб-процесса.
