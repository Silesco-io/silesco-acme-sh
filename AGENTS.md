# Правила работы над субпроектом Silesco.io

## Обязательный контекст

Сначала определи записанную в `PROJECT.md`/`STATUS.md` architecture version и сравни её с `context/ARCHITECTURE_VERSION`.

Для нового, неизвестного или major-несовместимого snapshot прочитай полностью и в этом порядке:

1. `context/SILESCO_CONTEXT.md` — зачем существует вся система.
2. `context/ARCHITECTURE_BASELINE.md` — какие глобальные решения считаются актуальными.
3. `context/SUBPROJECT_WORKFLOW.md` — как синхронизировать локальные и глобальные решения.
4. Все файлы в `spec/` — нормативные тематические спецификации snapshot.
5. `PROJECT.md` — какую цель преследует этот субпроект и где проходят его границы.
6. `DECISIONS.md` — какие локальные решения уже приняты.
7. `STATUS.md` — на чём остановилась предыдущая работа.

Если `PROJECT.md` ссылается на отдельную спецификацию, прочитай и её полностью.

Если предыдущая version известна и major не изменился, можно читать только неизменяемые global boundaries этого файла, `context/ARCHITECTURE_CHANGELOG.md` от applied version до текущей и названные в changelog изменённые `spec/`. При пропущенной версии, противоречии или изменении security boundary текущего компонента вернись к полному чтению.

## Главный принцип

Локально правильное решение не должно ухудшать Silesco.io как целое. Перед существенным изменением проверь:

- какую пользовательскую проблему оно решает;
- как оно влияет на простоту для человека без знаний Docker и Linux;
- не нарушает ли оно Zero Trust, минимальные привилегии и закрытость внутренних сервисов;
- кто является владельцем состояния и кто имеет право его изменять;
- какие контракты с другими субпроектами меняются;
- как система ведёт себя при потере Master, Vault, PostgreSQL или сети.

## Нерушимые границы

- До начала кода определить область лицензии: runtime/UI/installer/agents — PolyForm Shield 1.0.0; SAM/protocol/public SDK — Apache-2.0; документация — CC BY 4.0; brand assets исключены.
- Не принимать внешний код без CLA с правом перелицензирования; DCO сам по себе недостаточен.
- KMS.Silesco.io использует отдельный OpenBao Transit/MPL-2.0, а не HashiCorp Vault BSL. Локальный Vault Master является отдельным internal-use компонентом.
- PostgreSQL — авторитетный источник конфигурации. Файлы на нодах являются производным состоянием.
- `silesco-agent-core` и пользователь `silesco-ops` преимущественно наблюдают, читают и сообщают.
- Мутации ОС, Docker, конфигурации, UFW и WireGuard выполняет `silesco-agent-guard` в пределах строго заданных прав.
- Agent-ноды и Source-пиры инициируют host-native WireGuard-соединение к Master. Входящий публичный UDP endpoint нужен только Master и выбирается Wizard из реестра с collision check.
- Agent API/Core/Guard, Vault Agent listener и CrowdSec LAPI проходят через WireGuard+mTLS. Agent application ingress первой версии использует HTTP внутри WireGuard с exact Master source allowlist; future Nginx mTLS не включается молча.
- WireGuard Curve25519 transport keys и отдельные Core/Guard X.509 mTLS identities имеют независимые state machines. WireGuard не использует CSR/CA/CRL; штатная ротация любого вида не меняет stable tunnel IP, service AllowedIPs или Nginx upstream.
- Portainer не входит в runtime. Docker-метрики внутренним периодическим циклом собирает постоянный Rust `silesco-agent-observer` под пользователем rootful-группы `docker`; Core читает только санитизированный spool и не имеет Docker socket. Rootless является будущим Ultimate-профилем.
- Секреты, TOTP, unseal-ключи и чувствительные payload не попадают в логи.
- Любая команда должна быть идемпотентной либо иметь явную защиту от повторного выполнения.
- Команды первой версии хранятся в PostgreSQL outbox и безопасны при повторной доставке благодаря idempotency key.
- Единственный Collector работает на Master, локально читает central CrowdSec LAPI и пишет события через узкую локальную `SECURITY DEFINER` ingest-функцию; на Agent Collector не устанавливается.
- Путь Disaster Recovery не может зависеть только от ключа, находящегося внутри восстанавливаемого Vault.
- Все first-party компоненты и root helpers используют независимый Semantic Versioning 2.0.0. Несовместимый контракт требует major-версии или явной миграционной совместимости.
- Guard остаётся непривилегированным и запускает только независимо версионируемые root-owned helpers с проверкой Execution Permit.
- User-facing компоненты с первого релиза поддерживают `ru` и `en`; строки находятся в отдельных catalogs по `spec/21_localization.md`. Protocol/audit/logs хранят stable codes и parameters, а не localized prose.

## Порядок работы

1. Сначала сформулируй роль текущего субпроекта в одном предложении.
2. Перед реализацией перечисли затрагиваемые входы, выходы, данные и доверительные границы.
3. Если задача выходит за границы `PROJECT.md`, не расширяй ответственность молча: зафиксируй межпроектный контракт или запроси решение.
4. При изменении public API/CLI, события, schema, config, path, privilege boundary или operational behavior обнови `PROJECT.md`, при необходимости `DECISIONS.md` и затронутые component-local files в `docs/` в том же change set.
5. При изменении контракта явно классифицируй релиз как patch/minor/major и обнови compatibility matrix.
6. После законченного этапа обнови `STATUS.md`: что готово, что проверено и что делать дальше.
7. После архитектурной синхронизации запиши exact `Architecture baseline` и commit; не объявляй delta применённой до обновления релевантных specs/tests.
   `context/GENERATED_SNAPSHOT_MANIFEST.json` является авторитетным closed списком architecture-owned файлов: sync сначала проверяет полный source inventory/digests и отсутствие reparse points, затем транзакционно заменяет managed roots и удаляет tracked-файлы в `spec/` и generated `context/`, которых больше нет в manifest. Component-owned документация остаётся в `docs/`, `PROJECT.md`, `DECISIONS.md` и `STATUS.md` и этой очисткой не затрагивается.
8. Документируй public/exported code idiomatic comments для языка и проверяй штатный generator; generated reference не редактируй вручную.
9. `silesco-docs` собирает versioned component docs, но cross-component user journeys/runbooks пишутся отдельно: комментарии в коде их не заменяют.
10. До добавления user-facing строки определи localization key, typed placeholders и оба обязательных перевода. Не встраивай отображаемый текст в код и не локализуй machine contracts.

## Критерий готовности

Работа считается законченной, когда реализация проверена, обязательные `ru`/`en` catalogs или documented `codes-only` profile проходят localization CI, component docs и generated reference обновлены и собираются без ошибок, документация не противоречит коду, границы привилегий сохранены, отказовые сценарии учтены, а `STATUS.md` позволяет продолжить работу без восстановления контекста из чата.
