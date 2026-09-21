# Silesco.io — Модуль 21. Международизация и локализация

**Статус:** нормативный baseline
**Дата:** 2026-08-03
**Версия архитектурного baseline:** 1.3.0 (не версия продукта)

---

## 1. Термины и цель

- **i18n (internationalization)** — архитектурная готовность компонентов работать с разными языками, форматами чисел, дат и plural rules.
- **l10n (localization)** — конкретные переводы и locale-specific представление.

Silesco проектируется не только для русскоязычных пользователей. Первый пользовательский релиз обязан полностью поддерживать:

- `ru` — русский; формулировки подтверждаются владельцем проекта как носителем языка;
- `en` — английский; первоначальный перевод проходит отдельную языковую проверку и может уточняться без изменения machine contracts.

Ни один новый user-facing компонент не может откладывать вынос строк «на потом».

## 2. Locale identifiers и выбор языка

Все locale identifiers соответствуют BCP 47. Начальные каталоги называются `ru` и `en`; будущие региональные варианты используют форму `pt-BR`, `zh-Hans` и аналогичные зарегистрированные subtags.

Приоритет разрешения locale:

1. явный выбор пользователя, сохранённый в account/device settings;
2. locale текущей bootstrap/session настройки;
3. браузерный `Accept-Language` либо locale ОС для локального CLI;
4. fallback `en`.

Русская среда выбирает `ru`, если пользователь не сделал иной явный выбор. Переключение языка UI/PWA/Docs выполняется без переустановки. Timezone является отдельной настройкой и не выводится из языка.

## 3. Каталоги

User-facing компонент хранит отдельные UTF-8 files:

```text
locales/
├── en.json
└── ru.json
```

Catalog использует language-neutral schema и содержит как минимум:

- BCP 47 locale;
- catalog/component identifier и schema version;
- стабильные semantic message keys;
- message pattern;
- translator description/context;
- typed placeholder declarations;
- optional security/UX note.

Пример логической записи:

```json
{
  "key": "apps.running.count",
  "message": "{count, plural, one {# application is running} other {# applications are running}}",
  "description": "Container overview counter",
  "placeholders": {
    "count": "integer"
  }
}
```

Текст не используется как key. Переименование формулировки не меняет machine identity сообщения. Keys не переиспользуются для нового смысла после удаления.

## 4. Message formatting

Catalog patterns используют согласованный межъязыковой ICU MessageFormat-compatible subset: named placeholders, `plural`, `select` и `selectordinal` только при наличии одинаковых typed inputs во всех обязательных локалях.

Запрещены:

- конкатенация фрагментов перевода для построения предложения;
- ручное добавление окончания в зависимости от числа;
- positional placeholders без смыслового имени;
- raw HTML, JavaScript, PHP/template expressions или shell fragments в переводе;
- использование перевода для выбора permissions, command type, route, file path или другого control flow.

Числа, даты, время, проценты и размеры данных форматируются locale-aware библиотекой presentation-компонента. Machine/API values сохраняют канонический тип и формат независимо от locale.

## 5. Machine contracts и security boundary

Agent protocol, JSON Schema, signed Deployment Plan, Execution Permit, audit records, telemetry и structured logs используют:

- стабильный `code`/`event_type`/`operation_type`;
- типизированные parameters;
- correlation/idempotency identifiers;
- при необходимости developer-oriented non-localized diagnostic, который не считается UI text.

Локализованная строка не входит в canonical hash/signature и не является основанием для policy decision. Presentation boundary сопоставляет code и catalog key. Неизвестный key безопасно отображается через английский fallback и создаёт bounded diagnostic event без secret parameters.

Security-sensitive confirmation показывает локализованное объяснение, но точный typed plan/command/artifact identity остаётся неизменным и доступным пользователю. Перевод не может скрыть либо изменить действие.

## 6. Переводческий interchange

Runtime source of truth — versioned JSON catalogs. Для группового перевода используется детерминированный XLIFF 2.1 export/import:

```text
JSON catalogs
→ schema/placeholder validation
→ XLIFF 2.1 export
→ внешний или собственный translation service
→ XLIFF 2.1 import
→ validation and reviewed Git change
→ versioned JSON catalogs
```

Translation service не является authoritative source и не нужен для работы установленной Silesco. Импорт не может:

- добавлять/удалять semantic keys без отдельного source change;
- менять placeholder names/types;
- изменять catalog/schema/component version;
- добавлять executable markup;
- публиковать перевод напрямую без review и CI.

Возможный будущий Yii3-сервис группового перевода является отдельным продуктом, не субпроектом и не runtime dependency Silesco.io.

## 7. Ответственность компонентов

| Компонент/область | Обязанность |
|---|---|
| `silesco-ui` | account locale, web catalogs, error/event code rendering, localized settings |
| `silesco-pwa` | device/session locale и локализованные security confirmations |
| `silesco-wizard` | выбор языка до основной настройки; localized bootstrap validation |
| `silesco-installer` | locale-aware human CLI output; stable exit/error codes |
| `silesco-docs` | `/ru/` и `/en/`, language switch, canonical/hreflang links и localized search indexes |
| `silesco-agent-notifier` | locale получателя, templates по channel и безопасные typed parameters |
| `silesco-sam-toolchain` / Store | localized app metadata, parameter help, permission rationale и validation обоих обязательных языков |
| Protocol/Core/Guard/helpers | stable codes/parameters; не локализуют wire payload, audit и structured logs |

Общая language-neutral catalog JSON Schema и XLIFF round-trip fixtures принадлежат `silesco-protocol` как shared build contract. Это не делает переводы частью Agent wire protocol и не разрешает runtime-компонентам зависеть от translation service.

## 8. SAM и пользовательские приложения

SAM не дублирует свободные поля отдельно для каждого языка. Он использует типизированный localization bundle/keys для:

- display name и summary;
- descriptions/help пользовательских parameters;
- permission rationale;
- backup/recovery warnings;
- PRO-setting explanations.

Store publication official SAM требует `ru` и `en`. Пользовательские/private SAM могут иметь только один язык, но UI явно показывает неполное покрытие и применяет английский fallback только если он существует; machine values, identifiers и policy не переводятся.

Точная SAM localization schema принимается отдельным schema change в `silesco-sam-toolchain` и не реализуется свободной YAML-структурой.

## 9. Документация

Ключевые user journeys `docs.silesco.io` публикуются на `ru` и `en`. Developer reference может первоначально иметь один технический язык, если machine contract и examples остаются доступны, но installation, security confirmation, backup, recovery и troubleshooting guides обязаны иметь оба языка до первого public release.

Переводы документации хранятся раздельно и связаны stable document IDs, а не заголовками или URL-текстом. Изменение исходной статьи помечает связанные переводы stale до review.

## 10. CI и release gates

Для применимого компонента CI проверяет:

- JSON Schema всех catalogs;
- одинаковый обязательный key set для `ru` и `en`;
- placeholder names/types и plural/select compatibility;
- отсутствие duplicate/dead keys и запрещённого markup;
- корректность BCP 47 tags;
- JSON → XLIFF 2.1 → JSON round-trip без потери identity/parameters;
- отсутствие inline user-facing strings в согласованном lint scope;
- UI snapshot/functional tests хотя бы для `ru`, `en`, длинных строк и missing-key fallback;
- отсутствие локализованного текста в canonical/signature fixtures.

Machine-only helper без human output документирует профиль `codes-only` и не создаёт пустые catalogs.

## 11. Версионирование и готовность

- Catalog schema версионируется отдельно от текста.
- Catalog release связан с SemVer владеющего компонента.
- Исправление перевода без изменения key/placeholders может входить в patch release.
- Изменение key semantics или placeholder contract требует совместимого migration/major decision владельца contract.

Критерии первой итерации:

- [ ] Принята общая JSON Schema catalog и XLIFF mapping.
- [ ] `ru` и `en` catalogs проходят parity/round-trip tests.
- [ ] UI/Wizard/PWA/Installer/Docs/Notifier имеют точную locale-resolution policy.
- [ ] Protocol/error/audit/log examples используют codes и parameters без translated canonical data.
- [ ] SAM localization bundle типизирован и проверяется Store/toolchain.
- [ ] Security, backup и recovery flows проверены на обоих языках.
