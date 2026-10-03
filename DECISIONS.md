# Локальные архитектурные решения

## ADR-LOCAL-002: Full version-pinned catalog and opt-in standalone worker

Accepted 2026-10-03 under ADR-091—092 and the owner's explicit public-library scope.
SDK0.2/catalog schema2 replaces the initial191-driver/REG.RU-only coverage with all198
drivers at reviewed acme.sh3.1.6. All forms are source/metadata reviewed, not live tested.
Corrections remain explicit in provider-overrides.json; no runtime scraping or shell evaluation.
UPSTREAM.json is package provenance; read-only maintenance checks never update it automatically.

The public standalone LocalExecutor is explicit opt-in, Linux only, worker-policy owned.
It issues and verifies certificates without changing application services. DNS providers
are denied unless explicitly admitted. Silesco's web process must not use this worker:
its adapter remains subject to the established mutation path. Issuance is not TLS activation,
renewal or PWA readiness. No new universal Go/Rust agent or host privilege is introduced.

## ADR-LOCAL-001: Independent typed SDK and honest catalog coverage

Accepted2026-09-21 under global ADR-091. PHP8.3 public SDK is Apache-2.0; acme.sh
remains a separate GPL runtime. Stable catalog1 inventories every pinned driver,
but only reviewed forms may be rendered. No regex-inferred credential requirements.
Initial request/operation API was0.1.0-alpha.1; its limited catalog is superseded by ADR-LOCAL-002.
Executor owns authority/custody/timeout/idempotency. Library-local request binding
is not Protocol canonicalization or mutation authority. No process runner default.

Здесь фиксируются решения, специфичные для этого субпроекта. Глобальные решения не дублируются без необходимости: на них ссылаются из `context/ARCHITECTURE_BASELINE.md`.

## Согласованный scope (2026-09-21)

Отдельная независимая PHP-библиотека над acme.sh, полный каталог провайдеров закреплённой версии, общий для Yii3 Wizard и панели. Не переписывать DNS API. В Silesco использовать существующий путь мутаций; для сторонних проектов предусмотреть локального исполнителя. Подробности — PROJECT.md.

Переход Wizard на Yii3 и новый сценарий install.sh закреплены глобальными ADR-091—092;
этот SDK не является локальным разрешением менять соседние компоненты вне их контрактов.

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
