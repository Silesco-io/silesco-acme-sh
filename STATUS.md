# Текущее состояние

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
