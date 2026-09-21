# Silesco.io — Модуль 19. Документация и docs.silesco.io

**Статус:** нормативный baseline
**Дата:** 2026-08-03
**Версия архитектурного baseline:** 1.2.0 (не версия продукта)

---

## 1. Цель

`docs.silesco.io` должен быть полноценной версионируемой документацией, а не страницей-заглушкой. Она объясняет:

- установку, настройку, эксплуатацию, диагностику и восстановление Silesco;
- роль, границы и security model каждого компонента;
- сквозные flow между UI, Core, Guard, helpers, Vault, Nginx, CrowdSec и другими службами;
- API, CLI, schemas, configuration, states, limits и failure modes;
- совместимость docs с component SemVer, Agent protocol и SAM API.

## 2. Репозитории и владение

`silesco-docs` — отдельный публичный репозиторий и Yii3-приложение `docs.silesco.io`. `silesco-home` ссылается на него, но не владеет его содержимым.

Владение разделено:

- каждый субпроект владеет описанием своей роли, configuration, operations, security, troubleshooting и public developer reference;
- владелец machine-readable schema отвечает за её contract reference и examples;
- `silesco-docs` владеет user journeys, cross-component flows, glossary, navigation, version selector, search, импортом reference artifacts и публикацией сайта;
- нормативная архитектура объясняет global decisions, но не публикуется механически как user guide.

## 3. Четыре слоя

1. **User guides** — installation, Wizard, enrollment, deployment, backup, recovery и troubleshooting. Пишутся вручную.
2. **Architecture and operations** — responsibilities, trust boundaries, state machines, sequence diagrams, degraded modes и runbooks. Пишутся вручную и проходят review.
3. **Contract reference** — OpenAPI, JSON Schema, SAM/config schemas, CLI machine output и fixtures. Генерируется из machine-readable source of truth.
4. **Code API reference** — public packages, types, functions, classes и modules. Генерируется из idiomatic documentation comments.

Комментарии в коде не заменяют user guide, runbook или cross-component flow. Generated output не редактируется вручную: исправляется code comment, schema или generator.

## 4. Обязанности субпроекта

При начале реализации субпроект создаёт под `docs/` только содержательные разделы:

```text
docs/
├── overview.md
├── configuration.md
├── operations.md
├── security.md
├── troubleshooting.md
└── reference/        # generated output; не ручной source
```

Не каждому helper нужны все файлы. Обязательны только разделы его реальной public/operational surface; пустые boilerplate pages запрещены.

В том же change set обновляются docs, если меняются:

- public API/CLI, schema, event, config, environment variable или file format;
- port, route, privilege boundary, filesystem path, secret lifecycle или failure behavior;
- installation, update, backup, recovery или troubleshooting procedure;
- visible user flow или compatibility matrix.

Изменение не считается законченным, пока не обновлены затронутые docs и не прошла их сборка/валидация.

## 5. Генерация из кода и контрактов

| Стек | Source | Baseline generator |
|---|---|---|
| Rust | `///` / `//!`, public items, examples and doctests | `cargo doc` / rustdoc |
| Go | package comments and comments for exported identifiers | `go doc` / pkgsite-compatible output |
| TypeScript | TSDoc/JSDoc on exported API | TypeDoc |
| PHP/Yii3 | PHPDoc on public classes, methods and properties | phpDocumentor-compatible reference |
| HTTP API | OpenAPI 3.1 | закреплённый renderer/importer внутри Yii3 pipeline |
| JSON/SAM/config contracts | JSON Schema plus examples/fixtures | закреплённый renderer/importer внутри Yii3 pipeline |

Не требуется комментировать каждую private implementation detail. Обязательны public API, неочевидные invariants, security-sensitive behavior, errors, side effects, idempotency, limits и examples.

## 6. Центральное Yii3-приложение

`silesco-docs` содержит versioned source registry. Каждая запись ссылается на immutable release tag/commit и указывает repository/component ID, visibility, component/protocol/SAM version, публикуемые sources/artifacts и canonical URL.

`docs.silesco.io` реализуется на PHP 8.3/Yii3. Отдельный сторонний documentation site generator — MkDocs, Docusaurus или аналогичный framework — не используется. Yii3-приложение:

- импортирует только закреплённые версии Markdown и generated reference artifacts;
- строит navigation, canonical URLs, version routing и search index;
- валидирует links, anchors, metadata и compatibility labels до публикации;
- может кэшировать заранее отрендеренные страницы, но authoritative source остаётся в Git/release artifacts;
- не исполняет импортированный Markdown, HTML или examples как PHP/template code и применяет sanitization к разрешённому markup.

Language-native generators и OpenAPI/JSON Schema renderers остаются частью pipeline. Они создают reference artifacts, но не заменяют Yii3 как web application и publication boundary.

Сборщик не импортирует private source закрытых hosted-сервисов. Для них публикуется только reviewed user/API documentation без internal class reference, signing topology и operational secrets.

## 7. CI и release gates

В каждом субпроекте CI проверяет применимый набор:

- documentation generator завершается без ошибок;
- public API не имеет недокументированных exported items в согласованном scope;
- OpenAPI/JSON Schema/examples/fixtures взаимно согласованы;
- Markdown links, anchors и обязательные pages валидны;
- generated output воспроизводим и не подменён ручным файлом;
- docs не содержат credentials, private keys, production identifiers, raw security payloads и ненужные internal details.

`silesco-docs` дополнительно проверяет source registry, versioned links, navigation, cross-component references, Yii3 import/render pipeline и отсутствие ссылок на unpublished/private artifacts.

Публичные installation, security, backup, recovery и troubleshooting journeys первой версии имеют отдельные `ru` и `en` representations по `21_localization.md`. Stable document ID не зависит от переведённого title/slug; изменение одной локали помечает связанную другую локаль stale до review.

## 8. Версионирование

- Component reference публикуется вместе с component SemVer.
- Agent API reference и examples привязаны к Agent protocol version.
- Application-author documentation привязана к SAM `apiVersion`.
- User guides указывают поддерживаемую compatibility range.
- Исправление ошибки в docs может быть отдельным docs release; изменение контракта требует релиза его владельца.

## 9. Лицензии

Hand-written архитектурная и пользовательская документация публикуется под CC BY 4.0, кроме brand assets. Generated API reference сохраняет notices и условия исходного repository/artifact. Build tooling `silesco-docs` может иметь Apache-2.0, но не меняет лицензию собираемого текста.

## 10. Критерии готовности

- [ ] Каждый реализуемый субпроект имеет осмысленный `docs/` profile и generator/lint policy.
- [ ] `silesco-docs` имеет versioned source registry и воспроизводимую Yii3 import/render validation.
- [ ] Все public API/CLI/config/schema surfaces имею generated reference и reviewed examples.
- [ ] Ключевые user journeys и cross-component flows имею ручную версионируемую документацию.
- [ ] CI блокирует release при несогласованных schemas/examples, broken links или ошибках generator.
