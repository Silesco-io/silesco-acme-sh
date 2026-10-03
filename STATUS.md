# Текущее состояние

## Received architecture 1.24.0 — 2026-10-03

Source commit: `8cbde7d6`. See [receipt](docs/architecture-1.24.0-receipt.md).
Public PHP SDK is independent of Yii/runtime execution. Provider catalog exists; DNS credential form and root integration require their own acceptance.
This is receipt, not complete runtime conformance or hosted publication.

## PHP SDK 0.1.0-alpha.1 — 2026-09-21

Applied architecture: 1.23.0 / ADR-091. Implemented framework-independent catalog,
immutable CertificateRequest, Executor interface and bounded Operation results.
Complete inventory: 191 pinned acme.sh3.1.4 drivers; reviewed form: REG.RU only;
live provider accounts tested: zero. This is not full DNS-provider enablement.
Offline tests, generated PHPDoc reference, ru/en parity and closed package export
are provided. Wizard owns Silesco execution adapter; no shell or local runner here.
Next gate: integrate real HTTP-01 executor and live LE-staging acceptance; protected
DNS credentials transport and remaining form reviews stay explicitly incomplete.
See docs/integration.md. Previous scaffold status below is historical.

Verified:618 assertions on Linux PHP8.3 pinned FrankenPHP container with network
disabled/read-only mount; same tests PHP8.5.3 host. Catalog offline reproduction:
PASS191drivers. PHPDoc reference regeneration/check: PASS. Closed offline export:
13package files plusSHA256SUMS. No LE request, realcredential or account mutation.

## Received architecture 1.23.0 — 2026-09-21

ADR-091 / spec/24_wizard_yii_bootstrap.md is the current migration target.
See [snapshot receipt](docs/architecture-1.23.0-receipt.md). This is not an
implementation-complete claim; older applied-version entries remain historical.
Next shared gate: Installer/Nginx/Yii3 startup without PostgreSQL/Vault.

Дата: 2026-09-21. Этап: scaffold, реализации нет.
Architecture baseline: 1.22.1 (snapshot 2026-09-19).
Documentation profile: PHP integration guides + generated API reference (план).
Localization profile: отдельные ru/en каталоги, stable machine codes.

## Готово

- Штатный шаблон с context/spec и manifest.
- Назначение, границы и последние решения владельца в PROJECT.md.
- Origin: Silesco-io/silesco-acme-sh. Push не выполнялся.

## Следующий шаг после разрешения разработки

Синхронизировать глобальную архитектуру с Yii3 Wizard и общей библиотекой acme.sh; затем определить PHP API и каталог провайдеров. Не переписывать Wizard в этом репозитории.

## Проверки и ограничения

При создании проверить SHA-256 snapshot. Runtime/Composer/CI тестов нет: код не реализован. Старый snapshot не включает последний сценарий установки — это известный разрыв, не готовность к интеграции.
