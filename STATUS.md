# Текущее состояние

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
