# Silesco.io — Каналы продукта и добровольная телеметрия

**Статус:** принято; wire/storage contracts требуют реализации
**Версия архитектурного baseline:** 1.12.0
**Связанные решения:** ADR-072, ADR-073, ADR-074, ADR-076

---

## 1. Постоянная identity установки

`installation_id` — UUIDv7, который Installer генерирует локально на пользовательском сервере при первом развёртывании Панели. Основной сайт Silesco.io его не выдаёт. UUID создаётся до первой durable installation transaction, сохраняется в PostgreSQL и в root-owned public identity record `/etc/opt/silesco.io/identity/installation.json`.

`installation_id` не является секретом, паролем или доказательством владения. Он является стабильным идентификатором установки для backup/recovery, channel eligibility, платных возможностей и будущей привязки разных доменов к Agent-нодам. `master_id` и `node_id` идентифицируют узлы и не заменяют `installation_id`.

Recovery Bundle содержит исходный `installation_id` внутри зашифрованного payload. Restore восстанавливает его без изменений и не генерирует новый UUID. Одновременная эксплуатация двух восстановленных копий с одним `installation_id` должна выявляться hosted entitlement/identity lifecycle; это не разрешает передавать private keys через backup или hosted API.

## 2. Каналы продукта

Каналы не являются Git branches и не меняют trust model артефактов. Любой устанавливаемый target проходит одинаковую проверку подписанных release metadata, digest, совместимости и rollback protection.

| Канал | Доступ | Назначение |
|---|---|---|
| `release` | Публичный | Проверенные стабильные сборки для всех пользователей |
| `beta` | Allowlist `installation_id` | Непроверенные до уровня release сборки; ограничение защищает от случайного перехода, а не от целевого копирования |
| `alpha` | Owner-managed IP allowlist; при необходимости отдельный download key | Закрытая внутренняя проверка владельцем проекта и вручную добавленными установками |

Beta eligibility первоначально назначается вручную. Позднее её может автоматически назначать отдельное entitlement rule, например действующая подписка на KMS. Такое правило лишь разрешает выбрать beta: оно не переключает работающую установку без явного выбора пользователя и подтверждённого update plan.

### 2.1. Release assembly и hosted preview gate

`silesco-release` создаёт immutable candidate artifact, signed channel metadata, SBOM и notices. Любой first-party hosted-сервис сначала разворачивает candidate в изолированном `<random>.preview.silesco.io`. Preview использует authentication/IP allowlist, `noindex`, отдельные data/credentials и не имеет production secrets; случайное имя не считается защитой.

Owner approval связывается с service/version, artifact digest, config/migration plan digest и preview evidence. В production продвигаются exact те же bytes без rebuild. Rejection/expiry удаляет preview; production verification failure использует previous known-good artifact только при доказанной migration compatibility. Правило обязательно для всех нынешних и будущих hosted-сервисов.

Запрос eligibility содержит локальный `installation_id`. Для beta bare UUID допустим как намеренно слабая защита от ошибки пользователя. Для alpha обязателен owner-managed IP allowlist; отдельный отзываемый download key может быть добавлен позднее без изменения channel model. UUID сам по себе не открывает alpha targets. Удаление installation из allowlist запрещает только последующие закрытые обновления: уже установленная версия не блокируется и принудительно не откатывается.

`get.silesco.io` публикует общий bootstrap `install.sh`, signed channel metadata и проверяемые component bundles. Выбранный канал хранится как desired state. Смена канала и установка обновления являются явной мутацией; автоматический silent switch запрещён.

## 3. Граница телеметрии

Телеметрия состоит из двух самостоятельных субпроектов:

- `silesco-telemetry-exporter` — опциональный локальный сервис установки;
- `silesco-telemetry` — отдельный hosted ingress/processing/query service, не являющийся модулем основного сайта.

Exporter не создаёт новые источники наблюдения. Он читает только уже собранные Панелью агрегаты через узкий typed read contract. Ему запрещены Docker socket, root, generic SQL access, Vault secrets, raw logs и произвольные filesystem paths. Передача выполняется только outbound HTTPS batches с bounded durable queue.

Hosted transactional metadata — consent, installation public identity, policy version и per-app permissions — отделяется от observations. Временные ряды и статистические расчёты хранятся в отдельном ClickHouse. Основной сайт, Store, KMS и advertising systems не получают generic query access к telemetry storage.

## 4. Consent по каналам

| Канал | Наличие exporter | Начальное состояние |
|---|---|---|
| `release` | Не установлен | Передача невозможна; пользователь сам открывает Settings → Telemetry, читает состав/цели, включает и PWA-подтверждает установку модуля |
| `beta` | Входит в сборку | Включён с ясным disclosure; пользователь может немедленно отключить |
| `alpha` | Входит в сборку | Включён для внутренней проверки; может быть отключён |

Release UI не показывает настойчивые баннеры и не использует pre-checked checkbox. Consent хранит policy version, категории, время, канал и per-app exceptions. Отзыв consent немедленно останавливает новые batches и удаляет неотправленную очередь. Повторное включение требует нового явного действия; прошлое согласие нельзя расширять на новые категории молча.

## 5. Передаваемые данные

Основной интервал — пять минут, то есть до 288 окон в сутки. Для каждого управляемого приложения/контейнера передаются агрегаты, если исходные данные действительно доступны:

- CPU и RAM: среднее, максимум и p95 при достаточной плотности samples;
- network RX/TX;
- block I/O read/write; это не называется занятым объёмом диска;
- файловая ёмкость/usage только при наличии отдельного достоверного storage source;
- restart/health counters и длительность missing-data;
- stable Store application identifier и версия, но не пользовательские имена/домены;
- counts стабильных error codes компонентов Silesco;
- агрегированная статистика атак по типу/стране/результату без raw IP.

Запрещено передавать raw IP, домены, URL/path, имена пользователей, environment, command line, содержимое логов, secrets, payload пользовательских данных и private registry credentials.

## 6. Приложения вне Store

По умолчанию передаётся только общий count приложений вне Store, например `custom_app_count=3`. Для каждого приложения отдельно UI может запросить дополнительное согласие на передачу нормализованных metadata, включая exact image reference, чтобы проект мог оценить возможность добавления приложения в Store.

Разрешение имеет ключ конкретного `instance_id` и не распространяется на другие приложения. Глобальная кнопка «разрешить сведения обо всех неизвестных приложениях» запрещена. UI показывает отправляемые поля до подтверждения; отзыв permission прекращает сведения об этом приложении, не меняя общий consent и count.

## 7. Retention и расчёты

Default retention raw/5-minute telemetry равен 90 дням. Hosted operator может изменить его в GUI policy без изменения или пересборки кода. Effective retention version хранится durable, отображается в privacy description и применяется к ClickHouse TTL/partition policy через проверенный administrative workflow.

Увеличение retention не восстанавливает уже удалённые данные. Уменьшение запускает контролируемое удаление. Hourly/daily rollups могут иметь отдельный срок только если он явно указан в policy и не позволяет восстановить запрещённые identifiers.

ClickHouse выбран как column-oriented OLAP/time-series storage для сжатых временных рядов, materialized rollups, aggregate states и расчётов вида «у 90% установок приложение X использует не более Y RAM». PostgreSQL пользовательской Панели остаётся локальным desired-state authority и не становится hosted telemetry warehouse.

## 8. Безопасность upload

`installation_id` в upload является locator, а не credential. После consent локально создаётся отдельная telemetry upload identity/credential с узкими правами и rotation/revocation lifecycle. Ни beta eligibility по bare UUID, ни alpha download key не переиспользуются для загрузки telemetry.

Telemetry identity не использует локальный Vault Master CA, WireGuard keys, KMS credential, account session либо entitlement token. Отдельный hosted Telemetry CA выпускает только X.509 client certificates с EKU `clientAuth` для telemetry ingress. Exporter локально создаёт ECDSA P-256 private key и PKCS #10 CSR; private key никогда не передаётся hosted service и не попадает в PostgreSQL business rows, logs, Recovery Bundle, основной сайт или ClickHouse.

### 8.1. Первичная выдача upload credential

Canonical state machine:

```text
absent
→ key_created
→ challenge_requested
→ challenge_issued
→ proof_submitted
→ credential_issued
→ staged
→ active
```

Отказовые состояния:

```text
challenge_requested/challenge_issued/proof_submitted → rejected | expired
credential_issued/staged → activation_failed → retrying | abandoned
```

1. Enrollment выполняется только после effective consent. Release сначала проходит PWA-confirmed установку exporter; beta/alpha используют явно раскрытую channel policy. Дополнительное PWA-подтверждение для технической выдачи credential не требуется.
2. Exporter генерирует `upload_identity_id` UUIDv7, monotonic `key_generation`, P-256 keypair и подписанный PKCS #10 CSR. CSR содержит public key, но не private key.
3. Prepare request связывает `installation_id`, `upload_identity_id`, `key_generation`, exact CSR digest, consent revision, policy version, request ID, nonce и время.
4. Hosted ingress через server-authenticated TLS возвращает криптографически случайный одноразовый challenge, exact prepare-request digest и срок действия не более пяти минут. Session consumption является compare-and-set; повтор после consumption отклоняется.
5. Exporter подписывает canonical challenge private key из CSR. Hosted service проверяет CSR signature, challenge signature, request digest, nonce, expiry, rate policy и отсутствие conflicting reuse того же request/session ID.
6. Telemetry CA выпускает отдельный short-lived certificate с empty/controlled subject, SAN identity `urn:silesco:telemetry-upload:<upload_identity_id>`, exact key generation и только `clientAuth`. Certificate не даёт доступа к main site, KMS, Store, License либо customer control plane.
7. Credential package содержит certificate, CA chain, serial, validity, request/challenge binding и policy version. Exporter проверяет chain, EKU, SAN, public-key equality, validity и binding до atomic activation generation.
8. После activation все batch requests используют TLS 1.3 mTLS. `upload_identity_id` и `installation_id` внутри batch обязаны совпасть с hosted registry для authenticated certificate; HTTP header или source IP не заменяет эту проверку.

`installation_id` не доказывает уникальность реального пользователя. Поэтому hosted registry не использует first-registration-wins и не объединяет автоматически разные `upload_identity_id` под одним UUID. Несколько identities учитываются отдельно, ограничиваются versioned rate/active-identity policy и помечаются для duplicate/sybil analysis; это не позволяет посторонней предварительной регистрацией заблокировать настоящую установку. Telemetry aggregates считаются статистическими observations, а не entitlement, billing или security authority.

### 8.2. Ротация и отзыв

Certificate renewal является автоматической обслуживающей identity-операцией без PWA:

```text
active
→ renewal_due
→ key_created
→ csr_submitted
→ credential_issued
→ staged
→ verifying
→ activated
→ old_credential_revoking
→ completed
```

До `activated` старый credential остаётся рабочим. Renewal request проходит по действующему mTLS channel, использует новую локальную key generation/CSR и связывается с прежним serial. Новый certificate становится active только после доказанного mTLS request с новым key; затем старый serial отзывается. Default certificate TTL — 24 часа, абсолютный maximum — 7 суток; renewal window и jitter задаются versioned hosted policy и не зашиваются независимо в каждый consumer.

Consent disable использует отдельную state machine:

```text
active/renewal_due/staged
→ local_disabled
→ revocation_pending
→ revoked | expired
```

`local_disabled` является privacy commit boundary: exporter атомарно прекращает serialization/upload и удаляет unsent queue до сетевого revoke. Недоступность hosted service не отменяет этот результат. Scoped private key может сохраняться только в закрытой inactive generation для bounded revoke retries до подтверждения или certificate expiry; он не разрешает batch upload и затем уничтожается. Revocation intent содержит только identity/serial/request metadata и не является upload credential. Повторное включение после disable создаёт новую enrollment session и новую key generation; старый revoked/expired credential не реактивируется.

Hosted service принимает только versioned typed batches с sequence/idempotency, bounded size, timestamp window и schema validation. Повтор не удваивает данные; gaps отмечаются явно. Ingress имеет отдельные limits для enrollment, renewal, revoke и batches и не принимает bearer token как замену mTLS.

### 8.3. Protocol contracts

Protocol владеет как минимум следующими closed contracts:

- `telemetry.upload.policy.v1`;
- `telemetry.upload.enrollment-prepare.v1`;
- `telemetry.upload.enrollment-challenge.v1`;
- `telemetry.upload.enrollment-proof.v1`;
- `telemetry.upload.credential-package.v1`;
- `telemetry.upload.activation-result.v1`;
- `telemetry.upload.renew-request.v1`;
- `telemetry.upload.revoke-request.v1`;
- `telemetry.upload.revoke-result.v1`;
- существующие `telemetry.export.batch.v1` и `telemetry.export.ack.v1` с exact authenticated identity echo/binding.

CSR/package содержат public material, serial, validity, EKU/SAN и request/challenge digests, но никогда private key. Enrollment proof подписывает canonical challenge; activation result принимается только по новому mTLS connection. Ack обязан echo exact `batch_id`, `installation_id`, `upload_identity_id` и sequence authenticated request, чтобы exporter не удалил очередь по ответу другой identity или доставки.

## 9. Цели и запрет вторичного использования

Допустимые цели: sizing defaults и limits приложений, совместимость, надёжность компонентов Silesco, оценка распространённости приложений и качества защиты. Продажа данных, advertising profiling, построение пользовательских профилей и объединение с внешними рекламными наборами запрещены архитектурой и privacy policy.

До первого production upload должны существовать protocol schemas, sample privacy report, deletion/disable test, retention test и документация, позволяющая пользователю увидеть те же категории, которые реально сериализует exporter.

## 10. Технические основания выбора ClickHouse

- [ClickHouse: time-series database guide](https://clickhouse.com/resources/engineering/what-is-time-series-database) — columnar storage, time partitioning, high-cardinality analytics, compression и быстрые rollups.
- [ClickHouse: observability](https://clickhouse.com/resources/engineering/observability) — применение real-time OLAP column store для больших объёмов telemetry.
- [ClickHouse: Time to live](https://clickhouse.com/videos/time-to-live) — row/column TTL, controlled deletion и historical rollups.

## 11. Crash diagnostics и symbolication

Crash diagnostics является отдельной явно описанной telemetry category. Debug symbols не добавляют новых наблюдений: они только преобразуют уже полученный stack/program-counter в component function и относительную source location exact build.

Минимальный crash envelope может содержать:

- component, SemVer, source commit, target architecture и immutable build ID;
- executable digest и stable error/panic/signal code;
- normalized module-relative program counters/frames;
- bounded runtime/version metadata и crash fingerprint;
- occurrence count и timestamp window.

Автоматически запрещены core dump, heap dump, stack memory, locals/parameters, environment, argv, raw logs, process memory, secrets и абсолютные host/build paths. Raw panic/error text передаётся только после type-specific sanitizer; предпочтительны stable codes и normalized frames. Fatal signal без безопасного stack capture сообщает signal/build identity, а не включает coredump молча.

`silesco-telemetry` выполняет server-side symbolication через isolated read-only service. Exact symbol lookup возможен только по полной build identity; fallback к «ближайшей версии» запрещён. Исходный unsymbolicated envelope и результат имеют один fingerprint/audit binding. Symbols сохраняются как минимум пока может храниться соответствующий crash report и пока build находится в поддерживаемом update/recovery окне.

Release/beta installation не получает debug files. Alpha может отдельно скачать подписанный debug-symbol package для локального GDB/Delve расследования; наличие package не расширяет telemetry consent. Создание и передача core/heap dump является отдельной пользовательски инициированной diagnostic bundle operation с preview/redaction и не входит в обычную telemetry policy.

## 12. Технические основания symbol pipeline

- [Go linker](https://pkg.go.dev/cmd/link) — `-s`/`-w` удаляют symbol/debug information, поэтому canonical pipeline не может применять их до выделения separate symbols.
- [Rust codegen options](https://doc.rust-lang.org/rustc/codegen-options/index.html) — `debuginfo`, `split-debuginfo` и `strip`, включая packed `.dwp` на поддерживаемых Linux targets.
- [GDB: Separate Debug Files](https://www.sourceware.org/gdb/current/onlinedocs/gdb.html/Separate-Debug-Files.html) — стандартная ELF procedure `only-keep-debug` → strip → GNU debug link.

## 13. Технические основания upload identity

- [RFC 2986: PKCS #10](https://www.rfc-editor.org/rfc/rfc2986.html) — CSR связывает public key с подписью владельца соответствующего private key.
- [RFC 8446: TLS 1.3](https://www.rfc-editor.org/rfc/rfc8446.html) — server authentication и certificate-based client authentication для защищённого upload channel.
- [RFC 5480](https://www.rfc-editor.org/rfc/rfc5480.html) — профиль EC public keys, включая P-256, для X.509/PKIX interoperability.
