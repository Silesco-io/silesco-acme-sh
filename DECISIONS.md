# Локальные архитектурные решения

## ADR-LOCAL-001: Independent typed SDK and honest catalog coverage

Accepted2026-09-21 under global ADR-091. PHP8.3 public SDK is Apache-2.0; acme.sh
remains a separate GPL runtime. Stable catalog1 inventories every pinned driver,
but only reviewed forms may be rendered. No regex-inferred credential requirements.
Initial request/operation API is0.1.0-alpha.1, no prior runtime compatibility.
Executor owns authority/custody/timeout/idempotency. Library-local request binding
is not Protocol canonicalization or mutation authority. No process runner default.

Здесь фиксируются решения, специфичные для этого субпроекта. Глобальные решения не дублируются без необходимости: на них ссылаются из `context/ARCHITECTURE_BASELINE.md`.

## Согласованный scope (2026-09-21)

Отдельная независимая PHP-библиотека над acme.sh, полный каталог провайдеров закреплённой версии, общий для Yii3 Wizard и панели. Не переписывать DNS API. В Silesco использовать существующий путь мутаций; для сторонних проектов предусмотреть локального исполнителя. Подробности — PROJECT.md.

Переход Wizard на Yii3 и новый сценарий install.sh являются глобальными решениями владельца, ожидающими архитектурной синхронизации, а не локальным разрешением менять соседние компоненты.

## Шаблон ADR

### ADR-LOCAL-001: `<краткое название>`

- **Статус:** proposed / accepted / superseded
- **Дата:** `<YYYY-MM-DD>`
- **Контекст:** `<какую проблему или противоречие решаем>`
- **Решение:** `<что именно принято>`
- **Последствия:** `<выгоды, ограничения, новые обязанности>`
- **Затронутые контракты:** `<API, данные, события, соседние компоненты>`
- **Заменяет:** `<ADR или старое правило, если есть>`

---

Новые решения добавляются выше шаблона или отдельными секциями с последовательными номерами.
