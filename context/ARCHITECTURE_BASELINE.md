# Актуальная архитектурная база Silesco.io

**Architecture baseline:** 1.22.1
**Snapshot date:** 2026-09-19

Этот файл фиксирует глобальные решения архитектуры. Это не версия продукта или компонента. При расхождении он имеет приоритет над старыми схемами и примерами; точная дельта версий находится в `context/ARCHITECTURE_CHANGELOG.md`.

## BASE-072: Wizard переносит browser session на canonical origin

Владелец согласовал переход кнопкой на выбранный домен 2026-09-19 (ADR-090).
Wizard использует отдельную двухфазную операцию prepared→claimed→committed:
исходная cookie действует до доказанного получения provisional cookies на
canonical target и same-origin CSRF confirmation. Commit атомарно заменяет
session hashes; TTL установки не продлевается, первоначальный token повторно
не используется. Credential передаётся только form POST, не URL/storage/logs;
durable state содержит только verifier и exact identity/origin/TLS bindings.
Target определяется проверенным root proof, не произвольным browser URL.

Уточнение владельца 2026-09-19: только source HTML Wizard использует
Referrer-Policy: strict-origin, чтобы междоменный form POST сохранял точный
Origin. Передаётся лишь scheme/host, без path/query/credential. Prepare/claim/
confirm API и target confirmation HTML сохраняют no-referrer; no-store везде.
Origin: null не принимается, wildcard CORS/Domain cookies не разрешаются.

Installer/Nginx сохраняют ограниченную source ingress projection с прежним
bootstrap certificate до commit/expiry; она не даёт PWA/KMS/Vault доступа и
не становится trusted canonical origin. Неизвестные hosts вне этой projection
отклоняются. Переход без смены host не нужен. Exact Protocol wire mapping,
browser replay/lost-response и Linux integration tests обязательны. Новых
служб/портов нет; подробные условия и предел 120s находятся в модуле06.

## BASE-071: PWA handoff v2 сохраняет явный HTTPS-контекст

Владелец разрешил handoff v2 2026-09-09 (ADR-089). Protocol вводит отдельно
версионированный PWA handoff с обязательным типизированным HTTPS-контекстом
в binding. Legacy v1 schemas/fixtures остаются неизменными; test proof никогда
не преобразуется в legacy production proof. Обычный HTTPS и alpha/le-staging-test
различаются явно; test context связан с exact CA bundle. Это контекст, не
разрешение: Installer, Wizard и Controller независимо сверяют root-owned policy
по ADR-088. Browser не выбирает профиль и не является issuer.

Offer/delivery, durable completion/readback и PWA persistence сохраняют одну
привязку; смена policy/CA инвалидирует pending операции. Origin/RP/CSRF/TTL,
одноразовость P, Controller commit authority и native K delivery не меняются.
Protocol определяет exact wire mapping и совместимость; consumers обновляются
согласованно до release assembly. KMS/recovery admission также сохраняют test
status. Сертификат/CA не добавляется продуктом в системное хранилище; provider
TLS не использует staging roots. Решение не является публикацией generation.

## BASE-001: Разделение Observer и Mutation Handler

`silesco-agent-core` работает от `silesco-ops` и преимущественно читает состояние, собирает телеметрию и сообщает о необходимости действия. Он не создаёт и не удаляет контейнеры, не записывает compose/.env и не меняет UFW или WireGuard.

`silesco-agent-guard` работает от отдельного непривилегированного системного пользователя с `nologin`. Только он выполняет заранее типизированные мутации через минимально необходимый механизм привилегий и после проверки уровня подтверждения.

Произвольные шаблоны вида `sudo <utility> *` не считаются безопасным окончательным whitelist. Guard профилей Agent и Master запускает только явно перечисленные root-owned пути `/opt/silesco.io/helpers/<helper>/current/bin/<helper>`. Каждый helper независимо проверяет Permit и имеет отдельную SemVer-версию/TUF target.

## BASE-002: Права на конфигурационные файлы

Guard создаёт и изменяет `.env`, compose и другие производные конфигурации. Ops может получить только необходимое чтение. Конкретные права выбираются по принципу минимальности; секреты нельзя предоставлять Core только ради удобства мониторинга.

## BASE-003: WireGuard — вход только на Master

Agent-ноды и Source-пиры всегда являются инициаторами host-native WireGuard-соединения. Публичный входящий UDP endpoint открывается только на Master; Wizard предлагает `31946/udp`, проверяет конфликт и позволяет выбрать другой port. Endpoint не является identity. На Agent UFW не разрешает публичный входящий WireGuard-порт.

## BASE-004: Непрерывное Docker-наблюдение без Portainer

Portainer не входит в runtime Silesco. Rust-демон `silesco-agent-observer` под отдельным пользователем стандартной группы `docker` каждые 60 секунд пишет санитизированный append-only spool `/var/opt/silesco.io/spool/silesco-agent-observer/`. Core не имеет Docker socket, читает spool через read-only ACL и хранит собственные delivery cursor/retry/WAL в `/var/opt/silesco.io/state/silesco-agent-core/`. Silesco не меняет штатную схему Docker; доступ группы `docker` признаётся root-equivalent риском.

## BASE-005: Деплой выполняет Guard

Желаемое состояние формируется UI и сохраняется в PostgreSQL. Guard материализует compose/.env, выполняет Docker-операции и необходимые изменения сети. Core только наблюдает результат и сообщает фактическое состояние.

Подтверждение требуется по риску операции, а не автоматически на каждую техническую команду. Безопасный повтор уже одобренной составной операции не должен запрашивать новый TOTP на каждом внутреннем шаге.

## BASE-006: Уровни подтверждения

- Level 1: стандартные контролируемые действия; основной путь — PWA/WebAuthn, резервный — локальная root-консоль.
- Level 2: критические изменения UFW, WireGuard, удаления нод и аналогичные операции; основной путь — PWA с TOTP, резервный — локальная root-консоль.

Challenge привязан к `node_id`, типизированной операции, хэшу нормализованного payload, уровню, сроку действия, инициатору и idempotency key. Одобрение другого payload недопустимо. TOTP и секреты никогда не пересылаются Agent-ноде для самостоятельной проверки, если это можно проверить в доверенном контуре Master.

## BASE-007: PostgreSQL Command Outbox

Redis исключён из первой версии. Desired state, команда и outbox-запись создаются одной транзакцией PostgreSQL. Agent сам подключается к Master Agent API и получает команду с lease.

Команда содержит уникальный `command_id` и idempotency key. Повторная доставка не должна повторно выполнять необратимую мутацию. Истёкшая lease, backoff, максимальное число попыток и poison-command status задаются явно.

## BASE-008: PostgreSQL и производное состояние

PostgreSQL — источник желаемой конфигурации. Локальные файлы и фактическое состояние Docker сверяются reconciliation-процессом. Секреты не должны храниться открытым текстом в `compose_yaml`; в БД сохраняются ссылки или зашифрованные значения по явно принятой схеме.

Challenge-response, команды, результаты и состояние развёртываний являются самостоятельными доменными сущностями PostgreSQL.

## BASE-009: Disaster Recovery без циклической зависимости

Vault snapshot восстанавливается как отдельный артефакт и требует исходного recovery/unseal-материала. Нельзя делать единственным способом расшифровки бэкапа Transit-ключ, доступный только из того же Vault, который восстанавливается.

Для данных приложений должен существовать проверенный recovery key или другой независимый путь. Процедура DR считается готовой только после тестового восстановления на чистом окружении.

## BASE-010: Версионирование и совместимость

Каждый межкомпонентный запрос сообщает имя компонента, его версию, версию протокола и `node_id`. Master поддерживает объявленное окно совместимости. При несовместимости нода получает статус `degraded`, а мутации блокируются до безопасного обновления.

## BASE-011: Наблюдаемость и безопасные логи

Сквозной `correlation_id` использует UUID v7. Логи не содержат паролей, токенов, TOTP, unseal-долей, полного содержимого `.env` и иных секретов. Для аудита мутации сохраняются тип операции, хэш payload, субъект, метод подтверждения и результат.

## BASE-012: Чистый Mermaid

Ссылки на источники и пояснения располагаются в обычном Markdown-тексте. Маркеры вроде `[1, 4]` не вставляются в строки Mermaid, если они не являются частью валидной подписи узла.

## BASE-013: KMS без привязки к поставщику

Архитектура поддерживает стандартные внешние Cloud KMS. Фирменный доступный вариант называется `KMS.Silesco.io`, но не должен блокировать выбор другого провайдера и не считается обязательным элементом базовой установки.

## BASE-014: CrowdSec ingest централизован на Master

Единственный Collector на Master локально читает central CrowdSec LAPI, обогащает события GeoIP и пишет их через узкую локальную `SECURITY DEFINER` ingest-функцию. На Agent остаются штатные Log Processor и host bouncer; Collector credential и PostgreSQL ingest listener им не выдаются.

## BASE-015: Identity не равна IP

Master и Agent узнаются по ключам, сертификатам и стабильным ID. Основной endpoint задаётся DNS. Аварийный root-only rebind меняет адрес Master, но не доверенную identity. Восстановление Agent на новом IP выполняется через одноразовый recovery token и отзыв старой identity.

## BASE-016: Bootstrap и PWA PRF

Wizard доступен до Vault через одноразовый self-signed HTTPS URL и fingerprint. Первый браузер атомарно погашает token и получает единственную cookie+IP сессию с TTL 2 часа и idle timeout 30 минут. PWA artifact может быть staged заранее, но PWA route, QR, восьмизначный pairing code и enrollment API активируются только после выпуска, атомарной установки и проверки доверенного сертификата canonical origin. Одних DNS/Host/forwarded-proto недостаточно: pairing требует root-owned atomic readiness record, связанный с exact origin, certificate fingerprint/validity и active Nginx TLS generation, плюс overwrite-нутый Nginx generation marker; mismatch/absence/rollback fail closed. Незавершённая повторно запущенная установка откатывается по bootstrap journal. PWA-unseal использует WebAuthn PRF только после фактического enrollment/crypto round-trip; предварительный platform-authenticator probe advisory и на iOS может дать false negative. Обычная assertion не считается ключом расшифрования. Публичного proxy Vault API и route `/vault/unseal` нет: `POST /bootstrap/v1/unseal` идёт через Nginx Unix socket в отдельный постоянный pre-Vault `silesco-unseal-controller`, который проверяет WebAuthn/challenge/replay и выполняет только локальный `sys/unseal` без сохранения share.

Nginx bootstrap lifecycle имеет exact modes `bootstrap` (self-signed Wizard only), `trusted-enrollment` (canonical LE Wizard + PWA + Controller, no UI) и `configured` (canonical LE PWA + Controller + UI, no Wizard). Wizard socket override существует только в первых двух modes; каждый переход использует staged generation, проверку и rollback.

## BASE-017: Semantic Versioning

Все first-party компоненты и каждый root helper используют независимый Semantic Versioning 2.0.0. Версии бинарника, Agent protocol, SAM `apiVersion` и архитектурного baseline разделены. До стабильного контракта используется `0.y.z`; несовместимое изменение повышает major соответствующего контракта.

## BASE-018: SAM не является исполняемым шаблоном

SAM — ограниченная декларативная YAML-схема. Значения подставляются типизированными ссылками в разобранный AST; свободные template/hooks/shell запрещены. TUF закрепляет digest SAM, SAM закрепляет image digest, Cosign проверяет image, а пользовательские overrides входят в подписанный Execution Permit.

Параметры делятся на сразу видимые `basic` и скрытые под раскрываемым UI `advanced`. Раздел называется «PRO-настройки»: это предупреждение о требуемой квалификации и возможной деградации/несовместимости, а не Premium entitlement или платная функция. Official SAM описывает документированный безопасный configuration surface закреплённого image digest; Docker Hub/upstream docs используются при авторинге и ревью, но не парсятся Master во время установки. Optional setting различает upstream default (`unsetBehavior: omit`), materialized `default` и `suggested`. Свободный environment/command/Compose editor запрещён, а security-sensitive возможности остаются в `permissions`, не в advanced-настройках. PRO-overrides входят в Deployment Plan.

## BASE-019: Recovery Bundle разделяет факторы

Пользователь получает один зашифрованный `.srb`, отдельную recovery phrase и отдельно хранимые Shamir shares. Bundle использует age/scrypt, содержит только read-only S3 recovery credential и не содержит постоянный KMS Transit token. KMS recovery credential перевыпускается после восстановления аккаунта.

## BASE-020: Аномалии используют локальный и исторический baseline

Core применяет rolling robust baseline и абсолютные пороги. Master строит по TimescaleDB профиль для часа недели и доставляет подписанную модель Core. Аномалия уведомляет пользователя, но не запускает мутацию без Guard и Execution Permit.

## BASE-021: Умная линковка по целевой матрице

Rust является предпочтительным языком системных бинарников, Go допускается при конкретном преимуществе. Линковка выбирается для каждой native-библиотеки: гарантированные baseline/installer dependencies динамические, редкие и неодинаковые — по возможности статические. Docker и CrowdSec обязательны на Master/Agent, поэтому закреплённые package/SONAME из их dependency tree входят в baseline. Source-пиры без ops/guard/observer имеют отдельный профиль. Целевые ОС: Ubuntu Server 24.04/26.04 LTS и Debian 12/13, архитектуры amd64/arm64; dependency matrix, ELF audit и SBOM обязательны.

## BASE-022: Маскот задаёт brand palette

Официальный безымянный маскот — кибер-сова. Канонические SVG-цвета: ink `#232D37`, slate `#384B5D`, steel `#687E94`, cyan accent `#00FCFA`. Cyan применяется дозированно; семантические статусы обязаны проходить contrast/accessibility review и не кодируются только цветом.

## BASE-023: Лицензии разделены по областям

Runtime/UI/installer — PolyForm Shield 1.0.0; SAM schema/protocol/public SDK — Apache-2.0; KMS.Silesco.io code закрыт и использует OpenBao/MPL-2.0, не HashiCorp Vault BSL. Brand assets имеют отдельные права. Внешний код не сливается до принятия CLA с правом перелицензирования; DCO недостаточно.

## BASE-024: Язык определяется типом компонента

Observer, Guard, Unseal Controller и root helpers используют Rust по профилю риска. Core, Collector и scheduler над restic используют Go; одновременно наступившие backup jobs могут выполняться параллельно с bounded resource policy и учётом restic locks. UI, Notifier worker, baseline publication worker, hosted Store и оболочка KMS используют PHP 8.3/Yii3. PWA использует TypeScript. Wizard — временный непривилегированный Go web-бинарник со встроенным TypeScript frontend. Shell-only installer передаёт workflow временным systemd wizard/path/apply/timer units, возвращает SSH prompt и сохраняет root journal/мутации за apply oneshot. После commit root cleanup удаляет bootstrap-контур. SAM parser/compiler — отдельная Rust CLI/library. Protocol — schema-first с bindings для нужных языков. Универсального правила `Rust preferred` для всех каталогов нет.

## BASE-025: Готовые инфраструктурные продукты не переписываются

Nginx, acmesh-official/acme.sh, upstream TimescaleDB/PostgreSQL image, HashiCorp Vault, CrowdSec и restic используются как готовые закреплённые артефакты. Их субпроекты содержат конфигурации, policies, integrations и upgrade/rollback tests. CrowdSec и restic устанавливаются на host Master/Agent, не в production-контейнеры; Source-пиры исключены без отдельной managed-backup роли. Restic читает только разрешённые host paths, а containerized restore-test означает disposable test target, не backup engine. Store, KMS и license.silesco.io являются hosted-модулями основного сайта Silesco.io, а не частью пользовательской установки.

## BASE-026: Filesystem layout является глобальным контрактом

Static artifacts: `/opt/silesco.io`; host configuration: `/etc/opt/silesco.io`; persistent state/deployments: `/var/opt/silesco.io`; runtime: `/run/silesco.io`; необходимые file logs: `/var/log/silesco.io`. `/bin`, `/sbin` и `/usr/bin` не изменяются, hardlinks запрещены, operator CLI использует root-owned symlinks в `/usr/local/bin` или `/usr/local/sbin`. Systemd запускает абсолютные `current` paths. Каждый субпроект перечисляет свои read/write paths по `16_filesystem_layout.md`; новый path требует глобального ADR.

Только общие service-path parents `/var/opt/silesco.io`, `/var/opt/silesco.io/state`, `/run/silesco.io` и `/run/silesco.io/bootstrap` имеют `0711 root:root`: это даёт traversal к известному exact leaf без directory listing. Component state/socket/credential leaves остаются scoped `0700/0750/0710`; распространять traversal mode на leaf или private material запрещено.

Root helpers устанавливаются в `/opt/silesco.io/helpers/<helper>/versions/<semver>/bin/` и вызываются по root-owned `current/bin/<helper>` paths. Краткоживущие Permits передаются через root-controlled `/run/silesco.io/credentials/permits/`. Docker telemetry spool находится в `/var/opt/silesco.io/spool/silesco-agent-observer/`; durable component-owned WAL/cursors — только в `/var/opt/silesco.io/state/<component>/`.

User applications: `/var/opt/silesco.io/apps/<slug>--<short-id>/` либо opaque UUID privacy mode. `deployment/generations` отделён от persistent `data/<volume-name>`; default official SAM использует managed bind mounts для ручного recovery. Directory name не является identity: полный immutable ID и container/volume map находятся в несекретном `identity.json`.

Отдельного `/resources` нет. Любой атомарный PostgreSQL/Redis является обычным provider app в `/var/opt/silesco.io/apps/` и публикует capability; consumer связывается через `app_dependencies`. Монолитный `bundled` dependency остаётся внутри app directory/data. Private control-plane services Silesco переиспользовать запрещено. Свободный per-app recovery README не хранится; recovery tooling строит инструкцию из resolved structured state.

## BASE-027: Agent ingress имеет два контура

Agent Nginx по умолчанию слушает `http://<agent-tunnel-ip>:26783` только внутри WireGuard и только от точного Master tunnel IP. Это ограниченное исключение без второго mTLS-слоя; Agent API, Vault и CrowdSec сохраняют обязательный mTLS. По явному Deployment Plan Agent может дополнительно публиковать назначенные приложения напрямую в Internet; control-plane endpoints на Agent не публикуются. Shared HTTPS использует SNI/Host, дополнительные TCP/UDP/Nginx listeners не ограничены `80/443`, но проходят conflict/UFW policy. Основная registrable domain и её поддомены не требуют Ultimate; независимые коммерческие доменные зоны относятся к будущей Ultimate capability.

## BASE-028: ACME централизован, private keys изолированы по нодам

Центральный Master `silesco-acme` оркестрирует HTTP-01/DNS-01 и renewal. Public Agent штатно генерирует private key/CSR локально и получает обратно certificate chain; private key ноду не покидает. Shared wildcard/SAN key с Master разрешён только как предупреждаемая PRO-опция. Local Agent acme.sh разворачивается лишь для выбранного автономного issuance.

## BASE-029: Ultimate и CrowdSec не используют dark confirmation flows

`license.silesco.io` выдаёт короткоживущий signed token только для новой/изменённой независимой коммерческой доменной зоны, получая `installation_id`, capability, nonce и opaque `operation_hash`, но не payload/domain/IP. Guard сверяет token с Execution Permit. Expiry не останавливает существующие routes/mail/renewal/recovery; закрытый local Ultimate module отложен.

CrowdSec Log Processors отправляют alerts в central Master LAPI, а host bouncers получают decisions. Временная автоматическая блокировка до 24 часов является заранее включённой policy и не требует PWA на каждый IP. Silesco CrowdSec content поставляется отдельными signed/digest-pinned Store artifacts по SAM `artifactRef`.

## BASE-030: Vault unseal имеет четыре независимые Shamir-доли

Vault использует ordinary Shamir seal `2 из 4`: local root-only `L`, PWA/WebAuthn PRF `P`, KMS-assisted `K`, offline recovery `R`. Standard Transit auto-seal не используется. KMS и PWA/SSH доступны одновременно; прекращение KMS-подписки не ограничивает PWA, SSH, recovery или Agent enrollment.

## BASE-031: Rootless Docker не является baseline первой версии

Стандартная установка использует rootful Docker и отдельного постоянного Observer в группе `docker`; его компрометация считается root-equivalent. Rootless сохраняется как будущий Ultimate deployment profile после отдельной OS/SAM compatibility certification. Observer не имеет входящего API и доставляет telemetry через Core.

## BASE-032: CrowdSec enrichment централизован на Master

На Agent работают только штатные CrowdSec Log Processor и host bouncer. Единственный Collector на Master читает central LAPI, обогащает события GeoIP и использует локальный ограниченный TimescaleDB ingest contract.

## BASE-033: Enrollment и WireGuard rotation разделяют network и root роли

Base64 JSON является однострочной transport encoding и может содержать публичный WG-ключ Master и одноразовую enrollment capability, но не private keys. Временная и постоянная WireGuard keypair создаются локально; private key ноду не покидает. До временного WireGuard-туннеля Core выполняет только одноразовую server-authenticated HTTPS-регистрацию capability и временного public key: Master не может создать WireGuard peer для ещё неизвестного ключа. После регистрации весь bootstrap продолжается через временный туннель. Core ведёт enrollment/rotation handshake, Guard проверяет подписанный plan, apply-wireguard helper применяет root mutation. Первичная нода подтверждается пользователем; штатная key rotation автоматическая.

## BASE-034: CrowdSec transport использует обязательный mTLS, но upstream queue не является durable

Central LAPI доступен по WireGuard и `RequireAndVerifyClientCert`; Agent/bouncer имеют разные certificate OU, CRL и replacement flow. Password/API-key credential не смешивается с TLS credential. Rootful Docker bouncer применяет `INPUT` и `DOCKER-USER`, IPv4 и IPv6. Transport IP/WireGuard prefixes управляемых нод исключаются из обычного global auto-ban и используют typed quarantine.

При LAPI outage уже установленные decisions продолжают действовать, но недоставленные alerts находятся в памяти Log Processor и теряются при его рестарте. До отдельного bounded durable cursor/spool/replay-контракта система показывает `degraded_security` и возможный telemetry gap, а lossless delivery не обещается.

## BASE-035: CrowdSec startup проверяет фактическую готовность role-specific endpoint

Один `network-online.target` не считается доказательством готовности route/DNS: на Debian 13 с ifupdown и `allow-hotplug` target наступает до DHCP lease. Host configuration использует bounded readiness gate для требуемого endpoint: CAPI на Master при включённой online-интеграции либо WireGuard/central LAPI на Agent. После timeout запуск завершается ошибкой и остаётся под bounded systemd retry/backoff; бесконечное ожидание запрещено.

## BASE-036: Store и platform release supply chain разделены

Store содержит только пользовательские SAM, документацию и связанные application artifacts. First-party binaries, внутренние control-plane configuration bundles, SBOM и platform update targets поставляются отдельным TUF release repository соответствующих компонентов. `silesco-home`, Store, KMS и License являются отдельными закрытыми hosted-проектами и не блокируют локальный MVP.

## BASE-037: WireGuard transport и mTLS identity имеют независимый lifecycle

WireGuard использует локальные Curve25519 private/public keys и стабильный туннельный адрес; X.509, CSR, Vault PKI и CRL к WireGuard неприменимы. Поверх туннеля Core и Guard используют отдельные X.509 private keys/certificates, подписанные локальным Vault Master PKI через узкий Yii3 Agent API. Agent передаёт только CSR и не получает произвольный Vault PKI token.

Enrollment, mTLS renewal и WireGuard key rotation являются тремя разными canonical state machines по ADR-048. Private keys никогда не хранятся в PostgreSQL, protocol payload, logs или audit. Initial mTLS policy v1 использует certificate TTL 30 дней и renewal window за 5 дней с deterministic jitter `0..12h`; policy доставляется Master и не hardcode-ится в компонентах. mTLS renewal является автоматической component-owned identity-операцией без PWA и без root helper. Плановая WireGuard rotation также не требует PWA, но её root mutation получает автоматически выпущенный краткоживущий Execution Permit, привязанный к точному plan hash; permanent tunnel IP, AllowedIPs приложений и Nginx upstream при этом не меняются.

## BASE-038: WireGuard является host-native

Контейнерный WireGuard gateway не входит в baseline. Root helper применяет generation в native `/etc/wireguard`/live interface; Silesco-контейнерам не выдаются `NET_ADMIN`, `/dev/net/tun`, host network или ownership tunnel lifecycle.

Новая installation предлагает conflict-checked IPv4 pool `10.73.0.0/16`, Master `10.73.0.1`, stable Agent `/32` и random installation-local RFC 4193 `/64`. Конфликт требует явного выбора другого private pool до mutation; address не является identity.

## BASE-039: Порты Silesco принадлежат единому реестру

Разнесённые default-назначения находятся в `18_port_registry.md`: Agent ingress `26783/tcp`, remote Master Agent API `27931/tcp`, Master-local Agent API `29371/tcp`, PostgreSQL Collector `28643/tcp`, CrowdSec LAPI `29873/tcp`, Vault bootstrap `30741/tcp`, Vault Agent listener `31627/tcp`, Master WireGuard endpoint `31946/udp`. Это не IANA reservation и не security boundary. Перед apply проверяется полный `{namespace,transport,address,port}`, случайный fallback запрещён, exact bind сохраняется в Deployment Plan/PostgreSQL. Container-only standard upstream ports host port не занимают.

`silesco_backend` является private user-defined bridge без неявных host publications, но не Docker `--internal`: этот флаг несовместим с нормативным PostgreSQL loopback mapping Collector. Любой фактический host ingress по-прежнему появляется только из явного `ports:` в Deployment Plan.

## BASE-040: Architecture handoff является version-aware

Версия этого snapshot фиксируется отдельно от component/protocol/SAM versions. При известной предыдущей версии и том же major читаются global boundaries, changelog delta и перечисленные изменённые specs. При major bump, неизвестной версии или противоречии выполняется полное перечитывание. После sync `PROJECT.md` и `STATUS.md` фиксируют applied architecture version.

## BASE-041: Документация входит в Definition of Done

Каждый субпроект владеет своими component-local guides и generated API/contract reference. `silesco-docs` собирает их вместе с ручными user journeys, runbooks и cross-component flows в версионируемый `docs.silesco.io`. Код/schema/comments не заменяют ручные guides. Изменение API/CLI/config/schema/operations без обновления docs не считается законченным. Публичная документация не раскрывает private hosted source, secrets или security-sensitive operational details.

## BASE-042: docs.silesco.io использует Yii3

Центральная документация публикуется отдельным PHP 8.3/Yii3-приложением. Сторонний documentation site generator не вводится. Yii3 импортирует закреплённые Markdown/generated reference artifacts, строит navigation/version/search и безопасно отображает их; импортированный content не исполняется как PHP/template code. Component-local language-native generators остаются источниками reference artifacts.

## BASE-043: i18n/l10n обязательны с первого пользовательского релиза

Обязательные стартовые локали — `ru` и `en`, идентификаторы локалей соответствуют BCP 47. User-facing strings находятся в отдельных UTF-8 JSON catalogs, используют stable semantic keys и ICU-compatible patterns; XLIFF 2.1 является vendor-neutral interchange. Protocol/audit/log canonical state содержит codes и typed parameters, а не translated prose. CI проверяет completeness, placeholder/plural parity, safe markup и round-trip. Любой новый user-facing компонент обязан выбрать localization profile до реализации UI/CLI/notification/docs text.

## BASE-044: PostgreSQL имеет явную direct-access matrix

`silesco-postgresql` является SQL/config/test package закреплённого upstream TimescaleDB/PostgreSQL image; Yii3 отдельно владеет business migrations. Прямые scoped credentials имеют только Yii3 workloads, disposable migration/backup/restore jobs и единственный host-native Collector на Master. Core, Guard, Observer и Agent-ноды не подключаются к PostgreSQL; observations/results проходят через Agent API.

Collector использует exact loopback `127.0.0.1:28643 → container:5432`, TLS 1.3, client certificate и роль только с `EXECUTE` locked `SECURITY DEFINER` ingest function. Public/WireGuard/wildcard listener запрещён. Source node выводится из проверенного CrowdSec machine identity mapping, а не из входного `node_id`.

CrowdSec machine registry связывает exact upstream `<certificate-CN>@<stable-tunnel-source>` с `node_id`, DER fingerprint, serial и identity generation из PKI-issued public binding. Root apply проверяет certificate/address и LAPI `auth_type=tls` до PostgreSQL activation; Collector не доверяет payload `node_id` и не хранит JWT/private keys в DB.

Infrastructure migrations применяет disposable pinned `psql` job по signed bundle, краткоживущей Vault migration role и advisory lock. Guard проверяет task/Permit и вызывает helper, но database credential не получает. Expand infrastructure precedes Yii3 business migrations; destructive contract waits for compatibility window. Точный контракт находится в `22_postgresql_contract.md`.

Backup credential краткоживущий и read-only. Полный TimescaleDB logical restore является отдельной явной DR capability: disposable recovery login временно получает superuser, exact target route и bounded TTL, затем уничтожается; постоянный marker role не имеет LOGIN/SUPERUSER, а runtime-компоненты recovery credential не получают. Audit runtime пишет только через versioned `SECURITY DEFINER` append function с сериализованной SHA-256 chain; прямой table INSERT/UPDATE/DELETE запрещён.

## BASE-045: Vault bootstrap имеет exact loopback+mTLS projection

Host-native `silesco-unseal-controller` вызывает только `https://127.0.0.1:30741/v1/sys/unseal`. Docker публикует exact `127.0.0.1:30741/tcp` на containerized Vault bootstrap listener `8202/tcp`; wildcard/public/WireGuard bind запрещён. TLS 1.3, server CA verification и Controller-only client mTLS обязательны. Controller client key доставляется как scoped systemd credential; caller не может выбрать Vault URL/path.

## BASE-046: Bootstrap Unix socket использует dedicated shared group

`silesco-unseal-controller` создаёт canonical socket в `/run/silesco.io/bootstrap/unseal/controller.sock` от `silesco-unseal:silesco-bootstrap`: directory разрешает группе traversal без listing, socket имеет mode `0660`. Official Nginx container получает только supplemental numeric GID этой host-группы через Compose `group_add` и exact directory mount. World permissions, общий runtime user, container-UID ACL и TCP replacement запрещены.

## BASE-047: Telemetry batch атомарен, rates вычисляет Master

Observer отправляет raw cumulative counters с `observer_instance_id`, `spool_epoch`, `sequence` и `counter_epoch`; Core не подменяет wire payload локально вычисленными rates. Master хранит atomic/idempotent batch ledger с content digest, raw samples, explicit gaps/anomalies/baselines и строит rates единым алгоритмом. Retention baseline: raw 14 дней, 5-minute 90 дней, hourly 365 дней; compression raw chunks через 24 часа.

## BASE-048: Master-local Agent API сохраняет mTLS identity

Core/Guard на Master используют `127.0.0.1:29371/tcp`, удалённые Agent — `<master-tunnel-ip>:27931/tcp`. Оба exact binds ведут в один Yii3 Agent API и требуют TLS 1.3, отдельные component certificates, одинаковую authorization/revocation/replay policy. Plaintext loopback, Nginx termination и caller identity headers запрещены.

## BASE-049: PWA enrollment двухфазен и привязан к canonical origin

WebAuthn PRF production path запускается только на canonical ACME HTTPS origin; self-signed IP принадлежит Wizard, а IP-only recovery использует SSH/offline shares. Browser durable фиксирует IndexedDB ciphertext/evidence, но не `status=committed`; единственная Controller transaction активирует credential, создаёт Protocol completion и durable root outbox. После uncertain commit/reload browser выполняет только terminal readback, не commit/abort replay. Initial `L+P` ждёт root-confirmed acknowledgement. PRF output, AES key, plaintext share и continuation capability не сохраняются. Первая установка создаёт одну primary PWA identity.

## BASE-050: Initial Vault material не выводится

Shamir shares `L/P/K/R` и initial Root token не попадают в terminal, argv, environment, journald, progress API или общий bootstrap journal. Bootstrap становится configured только после обязательного handoff, initial unseal, выпуска scoped identities и revoke Root token.

## BASE-051: Bootstrap runtime использует exact mounts

Wizard слушает `/run/silesco.io/bootstrap/wizard/wizard.sock`, Controller — отдельный `/run/silesco.io/bootstrap/unseal/controller.sock`; Nginx получает только два exact leaf mounts через dedicated GID, не весь bootstrap parent. Immutable PWA artifact `/opt/silesco.io/components/silesco-pwa/versions/<semver>/dist/` монтируется read-only.

## BASE-052: Public TLS material публикуется одной PKI generation

ACME root consumer создаёт immutable paired generation `/var/opt/silesco.io/pki/nginx/master-public/generations/<generation>/` с closed manifest и проверенными `fullchain.pem`/`privkey.pem`. Только один root-owned atomic `current` pointer делает всю пару активной. Nginx config binding, `nginx -t`, reload, canonical TLS proof и trusted-origin readiness связываются с exact PKI generation ID и manifest digest. Независимая замена ключа/цепочки и повторное floating-разрешение `current` внутри одного apply запрещены; rollback возвращает согласованную пару PKI+Nginx generations.

## BASE-053: Docker observability labels являются частью подписанного deployment plan

SAM compiler добавляет во все service-контейнеры exact labels `io.silesco.managed=true`, `io.silesco.app-id=<instance_id>` и `io.silesco.generation=<desired_generation>` до сериализации resolved Compose, вычисления digest и выпуска Execution Permit. Guard и root deployment helper применяют уже одобренные bytes без post-signature добавления или исправления labels. Новая generation становится active только после verification/commit; rollback восстанавливает предыдущую committed generation вместе с её Compose bytes и labels.

## BASE-054: Execution Permit trust не выбирается caller-ом

Permit подписывает отдельный непубличный UI worker через installation-local non-exportable Vault Transit Ed25519 key. UI HTTP, Core и Guard signer capability не получают. Helpers читают только fixed `/etc/opt/silesco.io/trust/execution-permit/current/trust.json`, проверяют root ownership, closed schema, monotonic generation, issuer/kid/key version и revoked state до signature/payload/replay validation. Initial Agent trust входит в approved enrollment bootstrap; normal rotation имеет bounded active/retiring overlap, а emergency compromise требует local root/re-enrollment.

## BASE-055: Installation identity создаётся локально и переживает recovery

Installer при первом развёртывании локально создаёт UUIDv7 `installation_id`. Основной сайт не является issuer; UUID не является credential и не смешивается с `master_id`/`node_id`. Recovery Bundle хранит его внутри зашифрованного payload, а restore восстанавливает exact UUID без генерации нового. Release публичен; beta allowlist по UUID защищает от случайного перехода; alpha дополнительно использует owner-managed IP allowlist, а отдельный download key остаётся опциональным. Eligibility не переключает channel автоматически и не отключает уже установленную версию.

## BASE-056: Внешняя телеметрия является отдельным добровольным модулем

Release не устанавливает `silesco-telemetry-exporter` до сознательного Settings opt-in и PWA-confirmed component install. Beta/alpha включают exporter с явным disclosure и немедленным opt-out. Exporter читает только уже имеющиеся агрегаты, не получает Docker socket/root/raw logs/general DB access. Hosted observations изолированы в ClickHouse; default retention 90 дней меняется versioned GUI policy. Unknown apps передают только count, пока пользователь отдельно не разрешит image/metadata для каждого exact instance.

## BASE-057: Linux symbols отделяются от exact production ELF

Go/Rust runtime всех channels собирается с production optimization. Pipeline один раз создаёт ELF с DWARF и sanitized source paths, отделяет exact `.debug`/`.dwp`, затем strip-ит тот же executable; отдельная повторная compilation symbols запрещена. Для Go canonical `-ldflags="-s -w"` не используется, потому что удаляет DWARF до отделения. Release/beta symbols хранятся server-side, alpha package опционален. Telemetry отправляет build identity и normalized frames, но не core/heap dump, locals, argv/environment или memory.

## BASE-058: Web interfaces используют единый local-first frontend stack

User-facing web-поверхности используют Shoelace 2 для generic controls, Apache ECharts 6 для operational charts, Feather Icons 4 для SVG icons, D3 Geo 7/Equal Earth (`geoEqualEarth`) для overview choropleth по умолчанию и OpenLayers 10/Web Mercator для дополнительного interactive wrapped режима. Assets входят в pinned first-party artifact; CDN/runtime third-party fetch запрещён. Theme/locale — app-wide preferences. Critical forms и exact chart/map values сохраняют server-rendered/accessibility fallback. Owl colors не ограничивают полную semantic UI palette; cyan применяется дозированно.

## BASE-059: Telemetry upload имеет отдельную short-lived mTLS identity

После effective consent exporter локально создаёт UUIDv7 upload identity, P-256 keypair и PKCS #10 CSR. Отдельный hosted Telemetry CA выдаёт clientAuth-only certificate после one-time challenge/proof-of-possession; private key ноду не покидает. `installation_id` остаётся locator и не создаёт first-registration-wins: разные identities одного UUID хранятся отдельно и ограничиваются policy. Batches используют TLS 1.3 mTLS и exact certificate↔registry↔body binding. Renewal создаёт новую key generation до отзыва старой. Consent disable локально прекращает serialization/upload и удаляет очередь до сетевого revoke; outage hosted service не задерживает privacy commit. Telemetry identity не переиспользуется для main site, KMS, Vault, Store, License, entitlement или customer control plane.

## BASE-060: Offline share R выдаётся проверяемой PDF-карточкой

Wizard только на доказанном canonical HTTPS-origin создаёт recovery-карточку PDF локально в браузере. Карточка содержит одну Shamir-долю `R` в versioned checksummed text encoding и QR, но не `.srb`, recovery phrase, `L/P/K`, KMS credential или initial Root token. Скачать и распечатать — отдельные действия; перед печатью показывается предупреждение о plaintext в OS/printer spool. Handoff завершается только после readback выбранных групп; download/print event и checkbox недостаточны. Сервер и journal сохраняют только acknowledgement/fingerprint, а не share/PDF. JavaScript освобождает transient references и отзывает object URL, но гарантированное стирание browser memory не заявляется.

## BASE-061: Vault init изолирован и PGP-wrapped до передачи владельцам

ADR-086: native Shamir-доля закреплённого Vault 2.0.3 имеет 33 raw bytes
(включая coordinate/index), 66 lowercase hex или 44 Base64 characters.
Recovery-card 2.0.0 / `bech32m-silescor-v2` сохраняет все 33 bytes (HRP
`silescor`, 68 characters); Protocol версионирует также fingerprint domain и
prepare/package/ack binding. Legacy 32-byte v1 не мигрируется padding/truncation
и не принимается как native share. Порог `2 из 4`, ownership и `.srb` не меняются.

Первичный `/v1/sys/init` вызывает только bounded root-owned oneshot через digest-pinned disposable init-client, временную Docker network `silesco_vault_init` и container-only mTLS listener `32417/tcp` без host publication. Vault native PGP wrapping шифрует `L/P/K/R` в exact recipient order и initial Root token отдельному root worker ещё до HTTP response. Controller не имеет init path, Docker socket или token. Все recipient private keys/plaintext передаются только sealed FD; journal хранит digests/acks. Init предваряется полным preflight; после `initialized=true` автоматический rollback/reset запрещён, а незавершённая доставка переходит в `vault_init_recovery_required`.

## BASE-062: Vault post-init custody и setup являются host-bound и manifest-driven

Пять private recipients `L/P/K/R/root` переживают crash только как отдельные `systemd-creds --with-key=host` encrypted credentials после executable transient `LoadCredentialEncrypted=` round-trip probe; plaintext/null/TPM fallback запрещены. Exact PGP-wrapped init response после `initialized=true` является recovery material без time-based deletion. `L` публикуется отдельной root-only generation; потеря host key требует внешнего quorum и полного Vault rekey.

Один Raft volume последовательно проходит `PREINIT`, `POSTINIT_SEALED_SETUP` с distinct CA/identity и `PERMANENT`. Initial unseal — `L+K` при доказанном KMS decrypt либо `L+P` через реальный PWA/Controller; `R` только recovery fallback. Vault-owned versioned manifest задаёт closed ordered API, exact request/template digests, idempotency/readback и pinned PostgreSQL-owned role-statement digests. Initial Root token отзывается через revoke-self; тем же token обязаны отказать и lookup-self, и privileged sys/mounts. Permanent phase разрешён только после cleanup proof init/setup identities/listeners/network; unlink+fsync/readback не называется secure erase.

## BASE-063: Vault setup разрывает TLS cycle и восстанавливает authority bounded ceremony

До Database Engine PostgreSQL получает transaction bootstrap server TLS на private setup contour. Setup infrastructure PKI подписывает locally generated permanent CSR и durable ждёт Installer apply/restart/peer proof; затем Database plugin использует `verify-full` и inline public `tls_ca`. Bootstrap TLS удаляется только после verified Vault connection.

Plaintext `K` движется только exact KMS adapter→Controller по parent-spawned `SOCK_SEQPACKET|SOCK_CLOEXEC`: exact child-FD mapping, sealed memfd/SCM_RIGHTS и kernel `SCM_CREDENTIALS` при `SO_PASSCRED` в каждом packet связываются с coordinator-held pidfd/expected PID/UID/GID. `SO_PEERCRED` inherited socketpair identity не доказывает; adapter не имеет Vault, Controller — KMS, coordinator — plaintext. При потере host key после partial setup distinct recovery profile на том же Raft volume сначала unseal-ится external `2 из 3 P/K/R`, затем тот же threshold повторно вносится one-at-a-time в PGP-wrapped `sys/generate-root`. Recovery worker продолжает только original manifest generation/hash и отзывает token; reset и common quorum storage запрещены.

## BASE-064: PWA authority фиксирует Controller, browser только перечитывает terminal

Browser после IndexedDB persistence единожды отправляет evidence в enrollment commit. Controller атомарно активирует credential, пишет Protocol completion и durable root outbox; root coordinator потребляет outbox идемпотентно. Существующий handoff completion path является plaintext-free terminal readback: `202 root_pending`, `204 root_acked`, typed durable `409 commit_not_observed|commit_rejected`, `410 expired`. После uncertain response/reload commit и abort не повторяются; continuation capability memory-only, bounded retry завершается stable recovery instruction.

## BASE-065: Architecture snapshot имеет exact manifest, а PostgreSQL job leases разделены

`context/GENERATED_SNAPSHOT_MANIFEST.json` перечисляет exact SHA-256 всех architecture-owned files в generated `context/` и `spec/`. Sync удаляет каждый tracked managed file, отсутствующий в новом manifest, поэтому historical ADR/spec не переживает baseline как copy-only overlay. Component-owned `docs/`, `PROJECT.md`, `STATUS.md` и `DECISIONS.md` pruning не затрагивает.

Manifest использует closed `schemaVersion=1`, exact keys, non-empty identity files, case-insensitive unique paths и обязан полностью совпасть с source inventory до inspection target. Reparse/symlink/junction запрещены в source/target chains и managed roots. Полные managed roots сначала проверяются в same-volume staging и заменяются rename с backup; отказ возвращает byte-identical pre-state, а dirty target остаётся fail-closed.

Vault Database roles для disposable PostgreSQL jobs используют отдельные default/max TTL: migration `900/3600s`, backup `3600/14400s`, restore `900/900s`. Generic runtime 1 час их не заменяет; renewal определяется component/job policy и ограничен `max_ttl`. Первый старт использует bootstrap и permanent server-TLS generations. Permanent CSR/leaf имеет ровно DNS SAN `silesco-postgresql`, installation-bound URI SAN и не имеет IP SAN; CSR↔leaf/SAN проверяются до apply/restart barrier и `verify-full` подключения.

## BASE-066: Vault key owner остаётся установленным, но не работает постоянно

`silesco-vault-key-owner` — host-native root-owned компонент без listener и фонового daemon. Он запускается только fixed systemd operations для создания и host-encryption recipients, доставки ровно одного секрета через sealed memory, перепаковки доступной доли при смене KMS/телефона и полного Vault rekey при потере либо компрометации прежней защиты. TOTP и generic secret storage ему не принадлежат.

Доступная смена protector сохраняет ту же Shamir-долю и удаляет старую generation только после decrypt/readback proof новой. Vault rekey заменяет весь набор `L/P/K/R`; commit нового набора необратим, поэтому до него выполняется полный preflight recipients, а после него сбой переходит в recovery с повторной доставкой durable ciphertext, не в rollback к старым shares.

## BASE-067: Каждый hosted-сервис проходит mandatory preview exact artifact

`silesco-release` собирает подписанный candidate, SBOM/notices и channel metadata, разворачивает exact candidate digest в изолированном `<random>.preview.silesco.io`, ждёт явного решения владельца и только затем продвигает те же bytes в production. Это обязательно для Home, Docs, Store, KMS, License, Telemetry и любого нового first-party hosted-сервиса.

Preview не считается скрытым из-за случайного имени: обязательны authentication/allowlist, `noindex`, отдельные credentials/data и отсутствие production secrets. Approval не разрешает rebuild. Production verification failure возвращает предыдущий known-good digest при заранее доказанной совместимости миграций.

## BASE-068: Vault setup использует verified netns FD и process-bounded PostgreSQL barrier

Setup listener `32417` не публикуется на host. Installer сверяет exact Vault setup container ID/PID/starttime, pinned image, Compose project `silesco-vault-setup`/service `vault-setup`, network ID/IP и netns inode, открывает `/proc/<pid>/ns/net` `O_RDONLY|O_CLOEXEC` и передаёт only FD. Worker делает `setns(CLONE_NEWNET)`, использует pinned IP:`32417` для routing и TLS hostname/SNI `silesco-vault` для identity. Controller loopback `30741 -> 8202` остаётся отдельным `/sys/unseal` path.

Key owner и worker используют fixed FD ABI ADR-085 и закрывают все остальные descriptors до exec. Worker выполняет operations `1..28`, выдаёт canonical PostgreSQL certificate package через `SOCK_SEQPACKET` и завершается typed pause; Root token в памяти не ждёт host mutation. Installer durable сохраняет package, PostgreSQL owner выдаёт byte-bound ack, а continuation запускает новый process с fresh delivery UUID/nonce, но теми же transaction/ciphertext/setup generation+hash. Другой package/ack — conflict. Secret-bearing setup operations не сохраняют runtime request hash/HMAC или иную password/share/token-derived value.

## BASE-069: Локальный KMS adapter отделён от hosted KMS и Controller

ADR-087: `silesco-kms-adapter` — отдельный версионируемый Linux/Rust host artifact
без постоянного listener/daemon. Первый provider — Yandex Cloud KMS для тестов;
это не обязательный провайдер продукта и не hosted `silesco-kms` (Yii3/OpenBao).
Wizard предлагает явный выбор без preselection: «Без KMS» / «Yandex Cloud KMS»,
ID симметричного ключа и загрузку JSON авторизованного ключа service account.
Credential принимается только после canonical HTTPS, передаётся через bounded
private ingress и сохраняется host-encrypted вне Vault для последующего reboot.
Intent/result/logs не содержат credential, JWT, IAM token или plaintext K.

KeyOwner выдаёт только native33-byte K через отдельный закрытый owner-local FD
контракт. Adapter выполняет encrypt/decrypt roundtrip с versioned exact AAD и
durable ciphertext, затем передаёт lossless66-lowercase-hex K в существующий
Controller receiver по ADR-081. K ACK требует доказанного roundtrip; результат
подачи в Vault — отдельное свидетельство. Controller не получает provider API,
adapter — Vault route/certificate, coordinator не читает K. Существующие
Protocol init/K ACK и Controller delivery contracts не дублируются.

## BASE-070: PWA на LE staging разрешена только явно тестовому профилю

Исключение1.20.2, разрешено владельцем2026-09-08: только alpha
`le-staging-test` может до внедрения подписания использовать внешний заранее
заданный SHA-256 publication index и проверяемую им цепочку closed package
вместо подписанного deployment plan. Самостоятельная HTTPS policy с channel,
profile и exact CA bundle digest входит в пакет; Installer после проверки
публикует root-owned policy. Не выводить разрешение из ACME-флага, наличия CA
или самого proof. Это не подпись; release/beta/обычный профиль исключены.
Production signing остаётся обязательным. Полные условия — модуль06.

Уточнение1.20.1 (владелец подтвердил2026-09-08): production
`io.silesco.bootstrap.trusted-origin/v1alpha1` не меняется. Отдельная закрытая
`io.silesco.bootstrap.test-trusted-origin/v1alpha1` содержит прежние8 полей плюс
`releaseChannel=alpha`, `deploymentProfile=le-staging-test`, `caBundleSha256`
(SHA-256 exact fixed CA bundle bytes,64 lowercase hex). Consumers выбирают reader
по независимо полученной root-owned policy из подписанного deployment plan,
не по самому proof. Schema/profile/channel/CA digest должны совпасть; ordinary,
release/beta и legacy consumers test proof не принимают. Publisher проверяет
live canonical TLS peer отдельным CA bundle; системные/provider roots не меняются.
Никакой промежуточный результат не превращает test proof в production proof.
Exact fields/rollback/consumer coordination закреплены в модуле06.

ADR-088: подписанный alpha deployment plan может явно выбрать `le-staging-test`
для одноразового окружения. Он использует canonical DNS HTTPS, проверку exact
LE staging chain/hostname/validity и обычные PWA origin/RP/CSRF/TTL проверки.
Никакого `--insecure`, HTTP PWA или переименования staging в production.
Installer/PWA результаты сохраняют тестовый статус; stable/beta и обычный
профиль его не принимают. Trust staging CA на тестовом телефоне добавляет сам
пользователь; продукт не меняет системные trusted roots и не считает факт
скачивания сертификата доказательством browser trust/PRF/storage.
