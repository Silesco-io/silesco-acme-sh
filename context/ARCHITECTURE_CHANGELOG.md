# Журнал изменений архитектуры Silesco.io

Версия архитектуры не является версией продукта, Agent protocol, SAM API или отдельного компонента. Она версионирует только согласованный документальный baseline и использует Semantic Versioning 2.0.0.

## 1.23.0 — 2026-09-21

- ADR-091 / BASE-073: целевой Wizard перенесён с Go ELF на непривилегированный PHP/Yii3 container bootstrap profile, использующий общие с панелью runtime/модули и Nginx ingress, без зависимости от готовых PostgreSQL/Vault.
- install.sh спрашивает базовый домен, проверяет пригодность wildcard/install address, иначе оставляет IP-вход; сертификаты LE выпускаются только из Wizard, не shell installer.
- Новый модуль 24 задаёт границы runtime, silesco-acme-sh, этапы перехода и проверяемые acceptance gates. silesco-acme остаётся контейнером upstream acme.sh; DNS API не переписываются.
- Потребители: Installer, Wizard, UI, Nginx, ACME, ACME-sh, Protocol и Release. Новый snapshot не означает готовый runtime или разрешение production promotion. ADR-088/089 и действующая защита перехода origin не отменены.

## 1.22.1 — 2026-09-19

- Уточнение ADR-090/BASE-072, явно принятое владельцем: только source HTML
  Wizard использует Referrer-Policy: strict-origin для сохранения точного
  Origin междоменного form POST. Path/query/token не передаются в Referer.
- API prepare/claim/confirm и target confirmation HTML сохраняют no-referrer;
  no-store остаётся обязательным везде. Origin: null не принимается.
- Основание: проверенный Chromium-сценарий с no-referrer присылает null,
  strict-origin сохраняет exact source. Это не ослабление проверки Origin,
  не новая trust policy и не разрешение публикации либо изменения VPS.
- Потребители уточнения: Protocol, Wizard, Nginx и Installer. Получение snapshot
  не означает завершение установленного сценария перехода.

## 1.22.0 — 2026-09-19

- ADR-090/BASE-072: согласован перенос действующей сессии Wizard с bootstrap
  адреса на выбранный HTTPS-домен. Одноразовый начальный токен не используется
  повторно; настройка и исходный срок сессии сохраняются.
- Зафиксированы prepare/claim/confirm, временные cookies, привязка к проверенной
  TLS generation, запрет секретов в URL и восстановление после потери ответа.
- Installer/Nginx сохраняют ограниченный исходный вход до commit или expiry;
  это не расширяет доступ PWA, KMS или recovery на старом адресе.
- Порядок: Protocol → Wizard и Installer/Nginx → установленный сквозной тест
  → Release. Получение snapshot не означает реализацию или разрешение публикации.
- В baseline включено ранее принятое решение: D3 Equal Earth по умолчанию,
  OpenLayers/Web Mercator как дополнительный режим; готовность UI проверяется отдельно.

## 1.21.0 — 2026-09-09

- Владелец принял handoff v2: ADR-089/BASE-071 требует явный HTTPS-контекст
  в binding и сохраняет legacy v1 без изменений. Это additive architecture
  minor; версия самого wire-контракта меняется отдельно.
- Test status/CA binding сохраняются через offer/delivery/completion/readback
  и PWA persistence; независимая root policy остаётся источником разрешения.
- Protocol → Wizard/Controller/PWA → Installer → установленный тест → Release.
  KMS/recovery admission сохраняют профиль; native K/init ACK не дублируются.
- Обновлены модуль06, реестр ADR, baseline/template и план D2. Согласование
  не означает реализованный контракт, новую trust root или публикацию билда.

## 1.20.2 — 2026-09-08

- Владелец разрешил только для тестовой alpha существующую цепочку внешнего
  publication-index SHA-256 вместо ещё не реализованной подписи deployment plan.
- Самостоятельная HTTPS policy и exact CA bundle должны входить в проверяемый
  пакет; Installer публикует независимую root-owned policy лишь после проверки.
  Proof, HTTP/browser input и один ACME staging-флаг не разрешают test mode.
- Production signing сохранён; beta/release/обычный профиль исключены.
  Затронуты ADR-088/BASE-070, модуль06, Release, Installer и TLS consumers.
  Snapshot не означает реализацию или публикацию новой generation.

## 1.20.1 — 2026-09-08

- Подтверждённое уточнение ADR-088/BASE-070: отдельный закрытый test HTTPS proof
  `io.silesco.bootstrap.test-trusted-origin/v1alpha1`; legacy production не меняется.
- Test proof связывает alpha/le-staging-test и exact fixed CA bundle digest;
  reader требует независимо полученную root-owned policy, не autodetection.
- Затронуты модуль06, Installer/Wizard/Nginx/Controller/PWA и root KMS consumers.
  Их реализация и applied version обновляются совместно после тестов; копирование
  snapshot не включает staging и не подтверждает готовность пакета.
- Это уточнение уже принятого тестового режима, не новый production trust anchor.

## Принято 2026-09-06 — включено в baseline 1.22.0

- Уточнён ADR-075 и модуль 12: default обзорной карты — равновеликая D3 Equal Earth (`geoEqualEarth`) вместо Natural Earth 1. Относительные площади сохраняются для всех материков; OpenLayers/Web Mercator остаётся дополнительным режимом.
- Baseline frontend stack обновлён в 1.22.0; получение snapshots user-facing consumers отмечается отдельно. В `silesco-ui` требуется проверить default projection, полное размещение мира и сохранение пользовательского выбора. Это запись принятого решения, а не подтверждение изменения опубликованного UI.

## 1.20.0 — 2026-09-01

### Добавлено

- ADR-087/BASE-069: отдельный local Linux/Rust `silesco-kms-adapter`, первый
  Yandex provider; явный Wizard choice/key ID/authorized-key JSON, bounded
  HTTPS-only credential ingress, encrypted custody вне Vault и33-byte K.
- Существующие Protocol init/K ACK и Controller FD delivery повторно
  используются; owner-local K producer, provider AAD/ciphertext/credential ABI
  фиксируются отдельно до реализации. Это не hosted `silesco-kms`.
- ADR-088/BASE-070: явно выбранный alpha `le-staging-test` для canonical HTTPS
  Wizard/PWA с проверкой staging CA, без ослабления origin/RP/CSRF/TTL и без
  изменения системного trust store. Production proof из него не выводится.
- Новые paths/identity adapter в16, очередь05B в17 и generator inventory.

### Consumer sync

Сначала Protocol/KeyOwner/Installer/Controller/Wizard/PWA/Vault/Nginx/ACME/Release
и новый adapter. Managed snapshots обновляются manifest-aware; applied
version/commit в component status записывается после review. Запись baseline
не означает реализованный KMS, staging ceremony или опубликованный билд.

## 1.19.2 — 2026-08-31

### Исправлено

- ADR-086 уточняет native Shamir representation: для закреплённого Vault 2.0.3
  доля содержит 33 bytes, включая coordinate/index, либо 66 lowercase hex
  characters. Это не 32-byte AES key. Порог и владельцы `L/P/K/R` не меняются.
- Recovery-card v2 сохраняет все 33 bytes; 32-byte legacy v1 не считается
  рабочей native Vault share и не мигрируется padding/truncation. Protocol
  версионирует schema/encoding/fingerprint binding; consumers меняются вместе.
- Проверка включает настоящий Vault init → card roundtrip → unseal другой
  независимой долей, а не только согласие синтетических fixtures между собой.

### Затронутые субпроекты и порядок

- Сначала `silesco-protocol`, затем `silesco-unseal-controller`,
  `silesco-wizard`, `silesco-installer`, `silesco-vault-key-owner` и recovery
  tooling. Vault/PWA/KMS consumers обязаны сохранять полное native material.
- Изменены `01_security_vault.md`, `10_recovery_bundle.md`, ADR-086,
  template baseline и `17_development_order.md`. Публичные порты, authority,
  PWA/printing UX, `.srb` format и production generation не меняются.

## 1.19.1 — 2026-08-26

### Уточнено

- ADR-085 закрывает setup topology без нового host port: Installer проверяет exact Vault setup container/network identity и передаёт worker-у already-open netns FD; worker использует pinned IP для routing и `silesco-vault` для TLS identity.
- Закреплены exact caller/key-owner/worker FD maps для plan, ciphertext, binding, setup mTLS, PostgreSQL CSR/password, netns и `SOCK_SEQPACKET` control; все лишние FDs закрываются до exec.
- PostgreSQL TLS barrier стал process-bounded: operations `1..28` выдают canonical package и typed pause, затем Installer/PostgreSQL owner durable фиксируют byte-bound acknowledgement, а operations `29..33` продолжаются новой Root-token delivery с fresh UUID/nonce в той же transaction/ciphertext/setup generation.
- Для secret-bearing setup operations запрещено хранить runtime request hash/HMAC и любую password/share/token-derived value; journal хранит public template digest, FD role, non-secret binding и semantic result.

### Затронутые субпроекты

- `silesco-installer`, `silesco-vault-key-owner`, `silesco-vault`, `silesco-postgresql`; Controller получает changelog clarification без изменения своей `30741 -> 8202` projection.

### Миграция

- Pre-stable consumers заменяют нереализованный direct setup transport на ADR-085 netns/FD ABI и двухпроцессную barrier continuation. Постоянный host listener для `32417` не создаётся.

## 1.19.0 — 2026-08-25

### Добавлено

- ADR-083 выделяет постоянный host-native `silesco-vault-key-owner`: без daemon, только bounded init, rewrap, rekey и recovery операции с `L/P/K/R` и initial Root token.
- Доступная смена KMS/телефона перепаковывает ту же долю с проверкой новой защиты; потеря или компрометация требует полного Vault rekey всех `L/P/K/R`.
- ADR-084 вводит `silesco-release`: каждый hosted-сервис проходит изолированный `<random>.preview.silesco.io`, ручное approval и promotion exact того же digest без пересборки.
- Preview использует отдельные данные/credentials, запрещает indexing и production secrets; случайное имя не считается защитой.
- Generator дополнен `silesco-vault-key-owner` и `silesco-release` и формирует 34 дерева.

### Затронутые субпроекты

- Vault/Installer/Controller/PWA/KMS adapters/recovery и новый `silesco-vault-key-owner`.
- Новый `silesco-release` и все hosted Home/Docs/Store/KMS/License/Telemetry.

### Миграция с 1.18.0

- Дальнейшие rewrap/rekey/recovery принадлежат установленному key owner, а не временному bootstrap worker.
- Hosted CI сначала создаёт candidate digest и preview; production получает exact approved candidate.

## 1.18.0 — 2026-08-25

### Добавлено

- ADR-082 вводит authoritative `context/GENERATED_SNAPSHOT_MANIFEST.json`: sync удаляет tracked stale files в architecture-owned `spec/` и generated `context/`, проверяет exact paths/SHA-256 и не затрагивает component-owned docs/PROJECT/STATUS/DECISIONS.
- Safety follow-up закрепляет closed `schemaVersion=1`, полное равенство manifest source inventory, запрет reparse/junction/symlink во всех source/target chains и transactional same-volume staging+rename rollback до byte-identical pre-state.
- Воспроизводимый generator/test доказывает 32 byte-identical дерева и regression, где старый PostgreSQL `spec/07_architecture_decisions.md` baseline 1.7 удаляется при sync 1.18.0.
- Vault Database roles получили нормативные default/max TTL: migration `900/3600s`, backup `3600/14400s`, restore `900/900s`; generic 1 час эти роли больше не переопределяет.
- PostgreSQL permanent server certificate после two-generation first-start barrier имеет exact singleton DNS SAN `silesco-postgresql`, installation-bound URI SAN и не имеет IP SAN.

### Затронутые субпроекты

- Все 32 generated trees и все реальные consumer repositories: manifest-aware sync/pruning generated context/spec.
- `silesco-postgresql`, `silesco-vault`, `silesco-installer`, migration/backup/restore jobs: role TTLs и exact permanent TLS identity/barrier.

### Миграция с 1.17.0

Copy-only overlay snapshots запрещён. Перед продолжением реализации применить `_scaffolding/sync_generated_snapshot.ps1`, проверить closed manifest/source inventory и удаление tracked files, отсутствующих в manifest, затем отдельно зафиксировать applied architecture commit в component-owned status/decisions. Reparse-containing либо dirty managed roots требуют явного исправления и не синхронизируются. PostgreSQL/Vault contracts не считать синхронизированными, если сохранился historical `spec/07_architecture_decisions.md` или permanent leaf допускает missing/additional DNS либо IP SAN.

## 1.17.0 — 2026-08-24

### Добавлено

- ADR-081 исправляет KMS adapter→Controller identity proof: inherited `SOCK_SEQPACKET|SOCK_CLOEXEC` использует exact child-FD mapping, `SO_PASSCRED` и kernel `SCM_CREDENTIALS` в каждом сообщении с sealed memfd; PID/UID/GID связываются с coordinator-held pidfd. `SO_PEERCRED` для inherited socketpair больше не считается доказательством sender identity.
- PWA enrollment получает единственную атомарную Controller boundary: IndexedDB evidence, activation credential, Controller-produced Protocol completion и durable root outbox фиксируются одной транзакцией. Browser не создаёт committed status и после uncertain commit/reload выполняет только terminal readback по существующему `/handoffs/{id}/completion`.
- Terminal readback формализует `202 root_pending`, `204 root_acked`, typed durable `409 commit_not_observed|commit_rejected` и `410 expired`. Initial `L+P` unseal ждёт root-confirmed `P` authority; continuation capability остаётся memory-only.
- PostgreSQL first-start contract теперь явно разделяет transaction bootstrap server TLS и Vault-issued permanent generation с durable apply/restart/peer barrier до Database Engine `verify-full`.

### Затронутые субпроекты

- `silesco-unseal-controller`, KMS adapters и `silesco-installer`: exact spawn/FD/ancillary credential verification и pidfd lifecycle.
- `silesco-pwa`, `silesco-unseal-controller`, `silesco-protocol`, `silesco-wizard` и `silesco-installer`: terminal-readback schema, Controller-owned completion/outbox, stable terminal recovery и root acknowledgement.
- `silesco-postgresql` и `silesco-vault`: bootstrap/permanent server TLS generation barrier и verified Database Engine transition.

### Миграция с 1.16.0

ADR-080 K delivery нельзя реализовывать через `SO_PEERCRED` inherited socketpair. Browser-authored committed completion и retry enrollment commit после uncertain response запрещены. Production `/sys/init` остаётся fail-closed до реализации ADR-081 и exact PostgreSQL two-generation TLS barrier.

## 1.16.0 — 2026-08-24

### Добавлено

- ADR-080 устраняет три recovery/setup cycle из ADR-079: PostgreSQL получает transaction bootstrap server TLS до Vault Database Engine, затем атомарно переходит на Vault-issued permanent server TLS; KMS plaintext `K` передаётся только adapter→Controller по inherited `SOCK_SEQPACKET` sealed-memfd; потерянная host-bound Root-token recipient восстанавливается bounded `sys/generate-root` ceremony без reset Vault.
- Setup manifest получает durable external apply barrier: инфраструктурный Vault PKI подписывает локально созданный PostgreSQL CSR, Installer применяет generation и возвращает exact TLS/restart proof; только после bound ack Database Engine использует `verify-full` и inline public `tls_ca`.
- Recovery profile использует тот же Raft volume, отдельные network/CA/worker identity и не поднимает обычные permanent listeners. После внешнего `2 из 3 P/K/R` unseal тот же threshold заново вносится по одному share в generate-root attempt; quorum нигде не удерживается.

### Затронутые субпроекты

- `silesco-vault`: infrastructure PKI/PostgreSQL TLS barrier, recovery-only profile и generate-root manifest/resume verifier.
- `silesco-installer`, `silesco-postgresql`: bootstrap server TLS generation, permanent Vault-issued CSR generation/apply/restart/readback и cleanup.
- `silesco-unseal-controller`, KMS adapters, `silesco-pwa`, `silesco-recovery-tooling`: typed `K` sealed-memfd delivery и повторная owner contribution для recovery ceremony.

### Миграция с 1.15.0

ADR-079 setup manifest нельзя считать production-ready без всех трёх ADR-080 gates. Уже инициализированный Raft volume не reset-ится при потере host key; setup authority восстанавливается только через exact recovery profile/generate-root и продолжает ту же manifest generation/hash.

## 1.15.0 — 2026-08-24

### Добавлено

- ADR-079 закрывает оставшиеся post-init пробелы ADR-078: crash-safe custody пяти OpenPGP recipients, host-bound encrypted credential `L`, отдельный sealed setup profile на том же Raft volume и Vault-owned versioned setup manifest.
- Host-bound credential разрешён только после executable `systemd-creds --with-key=host` + `LoadCredentialEncrypted=` round-trip probe на целевой ОС. Plaintext, null-key и TPM fallback запрещены; потеря host key требует внешнего Shamir quorum и полного Vault rekey.
- Первичный unseal использует `L+K`, если KMS включён и adapter доказал decrypt roundtrip, либо `L+P` через реальный PWA/Controller path при выключенном KMS. `R` остаётся явным recovery fallback.
- Setup worker имеет отдельные CA/identity и закрытый ordered API allowlist. Initial Root token отзывается через `revoke-self`; после этого тем же token доказываются оба отказа: `lookup-self` и привилегированный `sys/mounts`.
- Зашифрованный init response после `initialized=true` является recovery material и не удаляется по TTL. Удаление возможно только после общего commit либо явного destructive reset с новым пустым volume; evidence фиксирует unlink/fsync/readback, но не обещает secure erase.

### Затронутые субпроекты

- `silesco-vault`: setup listener/profile, canonical manifest/API envelopes, exact readback, revoke/denial и transition inventory.
- `silesco-installer`: systemd credential probe/custody, transaction ciphertext, owner workers, aggregate acknowledgements и profile orchestration — отдельная последующая реализация.
- `silesco-pwa`, `silesco-wizard`, `silesco-unseal-controller`: authoritative `P` IndexedDB/Controller activation, `R` PDF/readback и initial `L+P` unseal без root shortcut.
- `silesco-postgresql`: владеет exact database role statements; Vault manifest только закрепляет их digests и открывает bounded temporary setup connectivity.

### Миграция с 1.14.0

Не выполнять production `/sys/init`, пока target host не прошёл executable encrypted-credential probe, а Vault package не поставляет exact setup profile/manifest/revoke verifier. Реализация только init listener из ADR-078 недостаточна: после `initialized=true` продолжение обязано использовать тот же Raft volume, сохранённый exact ciphertext и три формально разделённые runtime phases.

## 1.14.0 — 2026-08-23

### Добавлено

- ADR-078 определяет необратимую первичную инициализацию Vault как bounded root-owned oneshot `silesco-vault-bootstrap.service`, использующий одноразовый digest-pinned init-client container, отдельную временную Docker-сеть и container-only mTLS listener `32417/tcp`.
- Vault native PGP wrapping применяется прямо в `POST /v1/sys/init`: четыре recipient public keys передаются в строгом порядке `L/P/K/R`, отдельный recipient защищает initial Root token. Plaintext shares и token не пересекают HTTP response, disk, argv, environment, terminal или journal.
- Зафиксированы владельцы и commit boundaries для `L/P/K/R`, ветка `K=disabled-destroyed`, обязательный revoke initial Root token и fail-closed recovery после необратимого init.

### Затронутые субпроекты

- `silesco-installer`, `silesco-vault`: root oneshot, временная сеть/listener/PKI, preflight, journal, setup и cleanup.
- `silesco-wizard`, `silesco-pwa`, `silesco-unseal-controller`: transient FD/memfd handoff и подтверждения `P/R` без durable plaintext.
- `silesco-protocol`: closed init-plan/result/ack contracts, digests, replay/expiry и recovery-required state.
- KMS adapters и `silesco-recovery-tooling`: exact `K` ciphertext roundtrip либо явное уничтожение `K`; восстановление после partial init.

### Миграция с 1.13.0

Не вызывать `/v1/sys/init` через Controller, обычный backend listener или host-published port. Сначала должны существовать и пройти preflight все пять PGP recipients, trusted temporary TLS generation, выбранная KMS branch и новый пустой Vault volume. После `initialized=true` автоматический rollback/reset запрещён.

## 1.13.0 — 2026-08-23

### Добавлено

- ADR-077 закрывает первичный offline handoff Shamir-доли `R`: Wizard на доверенном canonical HTTPS-origin формирует локально в браузере скачиваемую и печатную recovery-карточку PDF.
- Карточка содержит только одну долю `R` в versioned checksummed text encoding и QR, минимальную несекретную идентификацию формата и предупреждения о раздельном хранении. `.srb`, recovery phrase, доли `L/P/K`, KMS credential и initial Root token в неё не входят.
- Завершение handoff требует успешного создания PDF и контрольного считывания выбранных групп текста; browser download event или checkbox сами по себе не доказывают сохранение. Plaintext `R` не попадает в серверное durable state, cache, URL, terminal, logs или audit.
- Зафиксированы отдельные действия «Скачать PDF» и «Распечатать», локализованные RU/EN; печать явно предупреждает о возможной plaintext-копии в системном spool принтера.

### Затронутые субпроекты

- `silesco-protocol`: closed versioned recovery-card/readback contracts, state transitions и stable error codes без plaintext share в acknowledgement.
- `silesco-wizard`: one-time trusted-origin handoff, browser-local PDF/QR generation, download/print UX, readback challenge и fail-closed completion.
- `silesco-installer`, `silesco-vault`, `silesco-unseal-controller`: bounded root-owned transfer, cancellation/expiry, initial Root token revocation только после подтверждённого handoff.
- `silesco-pwa`, `silesco-recovery-tooling`, `silesco-docs`: offline recovery/import UX, проверка encoding/QR и пользовательская документация по хранению и печати.

### Миграция с 1.12.0

Не выполнять `vault init`, пока exact реализация one-time `R` handoff не умеет создать PDF на trusted origin, завершить readback и удалить plaintext из transient state. Старые placeholders, вывод share в консоль и подтверждение одним checkbox запрещены.

## 1.12.0 — 2026-08-23

### Добавлено

- ADR-076 отделяет telemetry upload identity от публичного `installation_id`, release-channel credentials, локального Vault/Agent PKI и hosted account/KMS boundaries.
- Exporter создаёт локальную P-256 keypair/PKCS #10 CSR; отдельный hosted Telemetry CA выдаёт short-lived `clientAuth` certificate после one-time challenge и proof-of-possession. Batches требуют TLS 1.3 mTLS и exact certificate/registry/body binding.
- Формализованы enrollment, renewal и disable/revoke state machines. Privacy commit выполняется локально до сети: serialization/upload останавливаются и unsent queue удаляется даже при outage hosted service.
- First-registration-wins по публичному UUID запрещён; разные upload identities учитываются отдельно, ограничиваются policy и не объединяются автоматически.

### Затронутые субпроекты

- `silesco-protocol`: closed enrollment/challenge/proof/package, activation, renewal и revoke contracts плюс mTLS identity binding существующих batch/ack.
- `silesco-telemetry-exporter`: локальные credential generations, atomic activation, bounded queue/revoke retry и restore re-enrollment.
- `silesco-telemetry`: отдельный CA/identity registry, mTLS ingress, certificate lifecycle, rate/duplicate policy и ClickHouse admission boundary.
- `silesco-installer`, `silesco-ui`, `silesco-pwa`, `silesco-recovery-tooling`, `silesco-docs`: install/consent/disable disclosure, PWA-confirmed component install, исключение upload private key из recovery и публичное privacy explanation.

### Миграция с 1.11.0

Существующие telemetry batch schemas не разрешают production upload до добавления upload-identity lifecycle contracts. Не использовать bearer token или bare UUID как временную аутентификацию. Реализацию exporter начинать только вместе с минимальным hosted CA/ingress fixture; ClickHouse analytics/GUI могут следовать позже.

## 1.11.0 — 2026-08-22

### Добавлено

- Принят ADR-075 и единый frontend stack для user-facing web-поверхностей: Shoelace 2, Apache ECharts 6, Feather Icons 4, D3 Geo 7/Natural Earth 1 и OpenLayers 10.
- Библиотеки поставляются локально и закрепляются lockfile/digest/provenance; runtime CDN, remote module import и скрытые browser requests запрещены.
- Theme и locale являются app-wide preference. Critical forms, exact chart values и map rankings сохраняют server-rendered/accessibility fallback при отказе JavaScript, canvas, SVG или Web Components.

### Затронутые субпроекты

- `silesco-ui`: первый полный consumer стека; сохраняет Yii3/server-rendered security fallbacks и RU/EN catalogs.
- `silesco-wizard`, `silesco-pwa`: используют тот же component/icon/token layer, но не включают chart/map engines без реальной user story.
- `silesco-home`, `silesco-store`, `silesco-docs`, `silesco-kms`: повторно используют единые tokens/components/icons при начале frontend implementation.

### Миграция с 1.10.0

Обновить snapshot `12_brand_identity.md` и dependency/license notices только в user-facing web-субпроектах. Wire/API/schema, privilege boundary и runtime topology не меняются. Наличие D3 для карт не разрешает заменять ECharts самописными D3-графиками.

## 1.10.0 — 2026-08-17

### Добавлено

- Все Linux Go/Rust runtime artifacts собираются как оптимизированные production ELF независимо от channel. Debug assertions и отключение оптимизации не используются как способ получить диагностику.
- Canonical pipeline сохраняет отдельный exact-matched DWARF artifact (`.debug`/`.dwp`) в закрытом symbol store и только затем strip-ит тот же ELF. Для Go запрещён прямой canonical `-ldflags="-s -w"`, потому что он удаляет DWARF до отделения symbols.
- Telemetry crash report содержит build identity и санитизированные frames, но не core/heap dump, locals, environment, argv или memory. Release symbols остаются server-side; alpha может получить отдельный подписанный debug-symbol package по запросу.

### Затронутые субпроекты

- Все Go/Rust runtime repositories и artifact builders: reproducible ELF/symbol pair, path remapping, build identity и provenance.
- `silesco-telemetry-exporter`, `silesco-telemetry`, Protocol и Docs: crash envelope, symbolication, privacy/retention и diagnostic consent.

## 1.9.0 — 2026-08-17

### Добавлено

- Локально созданный при первой установке UUIDv7 становится постоянным `installation_id`: он не выдаётся основным сайтом, не меняется при восстановлении и входит в зашифрованный Recovery Bundle.
- Каналы продукта разделены на `release`, `beta` и `alpha`. Release публичен; beta использует allowlist installation UUIDv7 как защиту от случайного перехода; alpha ограничивается owner-managed IP allowlist, а отдельный download key остаётся опциональным усилением.
- Телеметрия выделена в отдельные локальный exporter и hosted-сервис. Release не устанавливает exporter без сознательного opt-in и PWA-подтверждения; beta/alpha включают его с правом немедленного отключения.
- Hosted observations хранятся отдельно в ClickHouse. Retention по умолчанию равен 90 дням и меняется через hosted GUI/policy без изменения программного кода.
- Для приложений вне Store по умолчанию передаётся только общее количество. Передача image и иных сведений разрешается отдельно для каждого приложения и может быть отозвана.

### Затронутые субпроекты

- `silesco-installer`, `silesco-wizard`, `silesco-ui`, `silesco-recovery-tooling`, `silesco-license`, `silesco-home`: installation identity, выбор канала, eligibility и recovery.
- `silesco-telemetry-exporter`, `silesco-telemetry`, `silesco-protocol`, `silesco-postgresql`, `silesco-pwa`, `silesco-docs`: consent, typed batches, privacy, retention и документация.

## 1.8.1 — 2026-08-15

### Добавлено

- ADR-069 назначает отдельный non-exportable Vault Transit Ed25519 key `silesco-crowdsec-binding`; PKI issuance worker получает только sign capability, а root post-init publisher — только public-key lifecycle.
- Ключ CrowdSec binding запрещено переиспользовать как PKI CA либо Execution Permit signer. Его outage блокирует новые binding, но не отзывает уже активированные.
- Wizard показывает обнаруженные публичные IPv4/IPv6 и требует явного подтверждения exact allowlist до первой host mutation; неподтверждённый либо позднее появившийся адрес автоматически не публикуется.

### Затронутые субпроекты

- `silesco-vault`, `silesco-installer`, `silesco-ui`, `silesco-crowdsec`: signer/public trust ownership и initial post-init projection.
- `silesco-wizard`, `silesco-installer`, `silesco-helper-apply-firewall`: подтверждённый public-address allowlist и fail-closed address drift.

## 1.8.0 — 2026-08-15

### Добавлено

- ADR-071: Execution Permit подписывает отдельный непубличный UI worker через installation-local non-exportable Vault Transit Ed25519 key; UI HTTP и Guard signer capability не получают.
- Root helpers доверяют только fixed-path monotonic trust bundle с exact installation/issuer/key-version binding. Caller-supplied trust key запрещён.
- Normal rotation использует bounded active/retiring overlap и plan, подписанный прежним active key; emergency revocation требует локального UID 0 либо re-enrollment и не доверяет скомпрометированному key.

### Затронутые субпроекты

- `silesco-protocol`: closed trust-bundle/rotation contracts, semantic fixtures и exact JWS key-version binding.
- `silesco-ui`, `silesco-vault`, `silesco-postgresql`: isolated signer workload, Transit policy/public lifecycle и durable authorization evidence.
- `silesco-installer`, `silesco-agent-guard`, все root helpers: initial/root trust projection, fixed-path verification, monotonic generation, rotation/revocation и replay.

## 1.7.1 — 2026-08-14

### Добавлено

- ADR-070: три exact Docker observability label материализуются SAM compiler во все service-контейнеры до вычисления approved Compose bytes/digest и выпуска Execution Permit.
- `io.silesco.app-id` равен Protocol `instance_id`, а `io.silesco.generation` — `desired_generation`; Guard/helper не изменяют labels после подписи.
- Rollback восстанавливает предыдущую committed generation вместе с её Compose bytes и labels; requested generation становится active только после verification/commit.

### Затронутые субпроекты

- `silesco-sam-toolchain`, `silesco-protocol`: generated-only enrichment и exact digest/Permit fixtures.
- `silesco-agent-guard`, `silesco-helper-apply-deployment`: применение без post-signature mutation и generation rollback.
- `silesco-agent-observer`: strict read-only validation exact labels.

## 1.7.0 — 2026-08-14

### Добавлено

- ADR-068: новая установка предлагает конфликт-проверяемый IPv4 WireGuard pool `10.73.0.0/16`, Master `10.73.0.1` и stable Agent `/32`; Wizard позволяет заменить пул до любой host mutation.
- ADR-068 также создаёт installation-local random RFC 4193 `/64`: Master получает `::1`, Agent — stable `/128`; public IPv4/IPv6 endpoint не является identity.
- ADR-069: CrowdSec `machine_id → node_id` связывается с exact certificate CN, stable tunnel source address, DER fingerprint, serial и identity generation. Binding выпускает PKI authority и атомарно активирует root apply после доказанной LAPI mTLS authentication; Collector не доверяет payload `node_id`.

### Затронутые субпроекты

- `silesco-wizard`, `silesco-installer`, `silesco-helper-apply-wireguard`, `silesco-agent-guard`, `silesco-ui`: выбор, collision check, desired state и generation apply WireGuard address plan.
- `silesco-crowdsec`, `silesco-agent-collector`, `silesco-postgresql`, `silesco-vault`, `silesco-ui`, `silesco-installer`: certificate-bound machine registry, activation, rotation и ingest authorization.
- `silesco-protocol`: typed public binding/package для CrowdSec machine identity; private keys и JWT в wire payload не входят.

### Миграция с 1.6.2

Действующие установки сохраняют свой адресный план. Default применяется только к новым установкам и никогда выбирается автоматически после конфликта. Существующие CrowdSec TLS machine bindings не считаются доказанными, пока issuance authority не выпустит exact public binding и root apply не сверит его с LAPI.

## 1.6.2 — 2026-08-13

### Добавлено

- ADR-067: Wizard предлагает панель на `ui.<основной-домен>`, не занимая apex сайта пользователя.
- Wizard обязан предупреждать, что стандартный или случайный hostname не скрывает ingress и не заменяет аутентификацию, TLS, rate limiting и audit.

### Затронутые субпроекты

- `silesco-wizard`: локализованный выбор panel hostname и предупреждение без security-by-obscurity.
- `silesco-installer`, `silesco-acme`, `silesco-nginx`: один exact hostname проходит через intent, certificate generation, Nginx generation и trusted-origin proof.

### Миграция с 1.6.1

Обновить snapshots затронутых проектов. Wire schemas не меняются; существующий явно выбранный hostname сохраняется, новый default применяется только к новым установкам.

## 1.6.1 — 2026-08-11

### Добавлено

- ADR-066: certificate chain и private key Nginx публикуются одной immutable PKI generation через единственный atomic `current` pointer.
- Nginx config generation, TLS proof и trusted-origin readiness связываются с exact PKI generation ID и manifest digest; rollback возвращает согласованную пару PKI+Nginx generations.

### Затронутые субпроекты

- `silesco-acme`: validated staging/handoff обязан описывать closed paired generation, но не активирует Nginx самостоятельно.
- `silesco-installer`: pre-Vault root consumer владеет atomic pointer, journal, activation и rollback.
- `silesco-nginx`: config/apply contract закрепляет exact PKI generation и manifest digest.
- `silesco-wizard`: progress/readiness принимает только root-проверенный exact generation result; browser не выбирает paths/CA policy.

### Миграция с 1.6.0

Обновить snapshots четырёх затронутых проектов. Существующие active certificate files нельзя считать двумя независимыми mutable targets; следующая issuance сначала импортирует их в generation layout и только затем использует atomic pointer.

## 1.6.0 — 2026-08-09

### Добавлено

- ADR-061: atomic/idempotent telemetry batch ledger, explicit gaps/anomalies/baselines и Master-side rates из raw counters.
- ADR-062: Master-local Core/Guard используют loopback-only mTLS Agent API `127.0.0.1:29371`; remote Agent сохраняют WireGuard listener `:27931`.
- ADR-063: canonical-origin WebAuthn PRF и двухфазный PWA enrollment `pending → local durable commit → active`.
- ADR-064: initial Vault shares/root token никогда не выводятся в terminal/log/common journal; bootstrap завершён только после handoff и revoke.
- ADR-065: exact Wizard/Controller Unix mounts и read-only immutable PWA static artifact для official Nginx container.

### Изменено

- Telemetry retention: raw 14 дней, 5-minute 90 дней, hourly 365 дней; compression raw chunks после 24 часов.
- PostgreSQL является authoritative owner batch ledger/gaps/anomaly/baseline state; exact DDL остаётся в `silesco-postgresql`.
- Обычный PWA setup требует канонический ACME HTTPS origin. До успешной установки и проверки LE-сертификата PWA route, QR, восьмизначный pairing code и enrollment API не активируются; IP-only setup сохраняет ручной SSH/recovery unseal.
- Bootstrap ingress уточнён трёхфазным lifecycle: `bootstrap` (self-signed Wizard), `trusted-enrollment` (LE Wizard/PWA/Controller без UI), `configured` (LE PWA/Controller/UI без Wizard).

### Затронутые субпроекты

- `silesco-postgresql`, `silesco-agent-observer`, `silesco-agent-core`, `silesco-agent-collector`, `silesco-ui`;
- `silesco-pwa`, `silesco-unseal-controller`, `silesco-wizard`, `silesco-installer`, `silesco-nginx`, `silesco-acme`, `silesco-vault`;
- `silesco-protocol` wire schemas не меняются, но implementation обязан использовать raw cumulative counters и действующие batch identities без локального расширения wire contract.

### Миграция с 1.5.2

Обновить snapshots перечисленных проектов. До интеграции требуется новая PostgreSQL infrastructure migration, exact Nginx mounts, двухфазный Controller/PWA adapter и отдельный Master-local mTLS bind. Старые telemetry tables/functions не считать совместимым production storage.

## 1.5.2 — 2026-08-06

### Изменено

- По результатам первого live Master deployment принят ADR-060: только shared parents `/var/opt/silesco.io`, `/var/opt/silesco.io/state`, `/run/silesco.io` и `/run/silesco.io/bootstrap` используют traversal-only `0711 root:root`.
- Component state, socket, credentials и private-material leaves сохраняют scoped `0700/0750/0710`; directory listing и world-readable secrets не разрешены.
- Зафиксирован успешный Ubuntu 24.04 deployment, Vault Shamir `2 из 4`, reboot/re-unseal, acceptance и cleanup.

### Затронутые субпроекты

- `silesco-installer`: exact parent modes и проверка runtime/state traversal.
- `silesco-unseal-controller`: подтверждённые state/socket leaf ownership и modes.
- остальные host-native компоненты должны использовать тот же общий parent contract, не создавая собственные несовместимые modes.

## 1.5.1 — 2026-08-06

### Изменено

- Зафиксирован узкий Unix-socket boundary между host-native `silesco-unseal-controller` и official containerized Nginx: отдельная host-группа `silesco-bootstrap`, group-traverse без directory listing и socket `0660`.
- Nginx получает только supplemental numeric GID этой группы через `group_add` и bind mount exact bootstrap socket directory; общий пользователь, world permissions, ACL по container UID и TCP-proxy запрещены.
- Installer обязан создать группу до запуска Controller/Nginx, передать её фактический numeric GID в Compose и проверить connect из контейнера без расширения иных host permissions.

### Затронутые субпроекты

- `silesco-unseal-controller`: primary service group и socket ownership/mode.
- `silesco-nginx`: exact bind mount socket directory и supplemental GID.
- `silesco-installer`: создание группы, GID handoff и readiness test фактического Unix connect.

### Миграция с 1.5.0

Обновить ownership/mode runtime directory и systemd unit Controller, затем пересоздать Nginx container с `group_add`. Не ослаблять каталог до world-traverse и не переносить unseal route на TCP.

## 1.5.0 — 2026-08-06

### Добавлено

- Закреплена host-native проекция Unseal Controller к containerized Vault bootstrap listener: `127.0.0.1:30741/tcp → Vault:8202/tcp`.
- Loopback listener сохраняет TLS 1.3 и обязательный mTLS; отдельный client certificate/private key доступен только `silesco-unseal-controller` через scoped systemd credentials.
- Принят ADR-058; `30741/tcp` добавлен в единый реестр Silesco после проверки актуального IANA registry.

### Затрагиваемые субпроекты

- `silesco-vault`: опубликовать bootstrap target `8202` только на exact loopback host port `30741`, не ослабляя mTLS.
- `silesco-unseal-controller`: использовать exact `https://127.0.0.1:30741/v1/sys/unseal`, CA/server verification и scoped client credential; arbitrary Vault URL/path от caller запрещены.
- `silesco-installer` и `silesco-helper-apply-deployment`: проверять collision exact tuple, материализовать loopback-only Docker publication и credentials с нормативными ownership/modes.

### Миграция с 1.4.2

Обновить architecture snapshot и affected deployment contracts. Wire protocol, SAM API, public ingress и Vault Agent listener не меняются. Запрещены wildcard/public bind, прямой PWA/Nginx → Vault proxy и отключение client-certificate verification.

## 1.4.2 — 2026-08-06

### Исправлено

- Уточнена граница PostgreSQL backup/restore: backup credential остаётся краткоживущим read-only, а полный TimescaleDB logical restore выполняет отдельный bounded recovery login с superuser только внутри явного DR flow.
- Постоянный `silesco_restore` является NOLOGIN/NOSUPERUSER marker role; recovery login получает точный target database route, короткий TTL, не выдаётся runtime-компонентам и уничтожается сразу после `pre_restore → pg_restore → post_restore`.
- Audit contract требует versioned function-only append, сериализованный SHA-256 hash chain и отсутствие прямого runtime INSERT/UPDATE/DELETE.
- Migration checksum manifest обязан покрывать каждый исполняемый SQL-файл bundle, включая dispatcher и migration-history recorder.

### Затрагиваемые субпроекты

- `silesco-postgresql`, `silesco-vault`, `silesco-agent-backup`, `silesco-recovery-tooling` и `silesco-helper-apply-deployment` принимают уточнённые credential/restore contracts.
- Остальные субпроекты получают changelog/baseline snapshot без изменения wire/API contract.

### Миграция с 1.4.1

Обновить architecture snapshot. Protocol schema, SAM API, порты и обычные runtime credentials не меняются. Реализация restore обязана доказать TTL, private target route, уничтожение credential и сохранение исходных database object owners.

## 1.4.1 — 2026-08-06

### Исправлено

- Уточнена семантика `silesco_backend`: это закрытая по membership user-defined bridge, но не Docker network с флагом `--internal`.
- Лабораторно подтверждено, что Docker `--internal` блокирует требуемую публикацию Collector `127.0.0.1:28643 → PostgreSQL:5432`, тогда как обычная bridge сохраняет loopback-only ingress.
- Installer/deployment обязаны создавать сеть без публичных host publications по умолчанию; inbound isolation определяется membership и explicit `ports:`, а не несовместимым `--internal`.

### Затрагиваемые субпроекты

- `silesco-postgresql`, `silesco-installer`, `silesco-helper-apply-deployment`, `silesco-ui`, `silesco-vault`, `silesco-baseline-builder` и `silesco-unseal-controller` принимают уточнённый network contract.

### Миграция с 1.4.0

Обновить architecture snapshot. Wire protocol, SAM API, port number и database schema не меняются; запрещено создавать `silesco_backend` командой `docker network create --internal`.

## 1.4.0 — 2026-08-06

### Добавлено

- Добавлен нормативный `22_postgresql_contract.md`: ownership upstream configuration/SQL package, authoritative-state scope, direct-access matrix, migration ownership и failure/restore boundaries.
- Принят ADR-055: единственный host-native Collector работает только на Master и подключается к containerized PostgreSQL через exact loopback `127.0.0.1:28643/tcp` с mTLS и узкой `SECURITY DEFINER` ingest-функцией.
- Закреплён disposable infrastructure migration job: Guard не получает database credential, signed SQL bundle применяет pinned `psql` artifact под краткоживущей Vault role и PostgreSQL advisory lock.

### Исправлено

- Docker telemetry flow исправлен на `Observer → local spool → Core → Agent API → PostgreSQL/TimescaleDB`; Core/Guard/Observer не имеют PostgreSQL credentials.
- Recovery Bundle приведён к действующей схеме Shamir `2 из 4` с долями `L/P/K/R`; устаревшая рекомендация `2-of-3` удалена.
- Collector окончательно закреплён как один host-native Master component; Agent Collector, PostgreSQL public/WireGuard listener и неучтённый database port запрещены.

### Затрагиваемые субпроекты

- `silesco-postgresql`: принять полный contract, pin upstream pair/digest и реализовать SQL/config/test package.
- `silesco-agent-collector`: принять Master-only placement, loopback+mTLS transport, runtime role и codes-only result contract.
- `silesco-agent-core`, `silesco-agent-guard`, `silesco-agent-observer`: удалить любую модель прямого DB access; Core/Guard возвращают данные только через Agent API.
- `silesco-ui`: разделить infrastructure и Yii3 business migrations, объявить compatibility range и владеть Agent API database writes.
- `silesco-agent-backup`, `silesco-recovery-tooling`: принять раздельные краткоживущие backup/restore roles и schema compatibility metadata.
- `silesco-vault`, `silesco-helper-apply-deployment`: реализовать job-specific credential handoff и disposable migration job без раскрытия credential Guard.

### Миграция с 1.3.0

Добавить `spec/22_postgresql_contract.md` затронутым субпроектам, обновить перечисленные specs и applied architecture version. Wire protocol и SAM API не меняются; component releases требуются только после реализации новых локальных contracts.

## 1.3.0 — 2026-08-03

### Добавлено

- Добавлен `21_localization.md`: обязательные i18n/l10n boundaries, стартовые локали `ru`/`en`, BCP 47, JSON catalogs, ICU-compatible patterns и XLIFF 2.1 interchange.
- Принят ADR-054: пользовательские строки не встраиваются в код, protocol/audit/logs используют стабильные codes и parameters, а отображаемый текст локализуется на UI boundary.
- Русский и английский обязательны для первого пользовательского релиза; отсутствие key, несовпадение placeholders/plural branches и небезопасный markup блокируют CI/release.

### Затрагиваемые субпроекты

- Все субпроекты: принять `21_localization.md`; non-UI components сохраняют machine codes вместо локализованного protocol/log text.
- `silesco-ui`, `silesco-pwa`, `silesco-wizard`, `silesco-installer`, `silesco-docs`, `silesco-agent-notifier`: реализовать user locale selection/resolution и обязательные `ru`/`en` catalogs.
- `silesco-sam-toolchain`, `silesco-store`: типизировать localized application metadata и проверить оба обязательных языка.
- `silesco-protocol`: определить language-neutral JSON catalog schema/XLIFF validation artifacts как build contract, не смешивая переводы с Agent wire protocol.

### Миграция с 1.2.0

Добавить `spec/21_localization.md`, обновить `AGENTS.md`, `PROJECT.md`, `STATUS.md` и architecture context. Существующие machine contracts не получают translated strings; первая реализация user-facing компонента обязана создать отдельные `locales/en.json` и `locales/ru.json` до release.

## 1.2.0 — 2026-08-03

### Добавлено

- Добавлен `20_ultimate_backlog.md` — единый ненормативный реестр отложенных возможностей Ultimate с условиями возврата к проектированию.
- В backlog закреплены rootless Docker profile, независимые registrable domains на Agent-нодах и optional mTLS для прикладного Master Nginx → Agent Nginx ingress.
- Принят ADR-053: `docs.silesco.io` реализуется как отдельное Yii3-приложение, а не через MkDocs, Docusaurus или другой сторонний site generator.

### Затрагиваемые субпроекты

- `silesco-docs`: заменить неопределённый static-site toolchain на Yii3 application, сохранив импорт версионированных Markdown и generated reference artifacts.
- Остальные субпроекты: обязанности по component-local docs из baseline 1.1.0 не меняются; обновление runtime или component release не требуется.

### Миграция с 1.1.0

Обновить snapshot `19_documentation.md`, implementation policy и первый шаг `silesco-docs`. `20_ultimate_backlog.md` не копируется в субпроекты и не является разрешением реализовывать отложенные возможности без отдельного ADR.

## 1.1.0 — 2026-08-03

### Добавлено

- Принят отдельный публичный `silesco-docs` как source/build repository для `docs.silesco.io`.
- Добавлен `19_documentation.md`: ownership, четыре слоя docs, language-native generators, CI/release gates, versioning и границы публикации закрытых hosted-сервисов.
- Каждый субпроект обязан вести component-local docs и обновлять их в том же change set, что API/CLI/config/schema/operations.
- Generated code/contract reference отделён от ручных user guides, runbooks и cross-component flows.

### Затрагиваемые субпроекты

- Все субпроекты: принять `19_documentation.md`, docs-as-code и documentation acceptance gate.
- `silesco-docs`: создать public source registry, user/architecture sections и воспроизводимый static-site build.
- `silesco-home`: ссылаться на `docs.silesco.io`, не дублируя documentation ownership.
- `silesco-protocol`, `silesco-sam-toolchain`: публиковать versioned generated contract reference из authoritative schemas/examples.

### Миграция с 1.0.0

Обновить `AGENTS.md`, `PROJECT.md`, `STATUS.md`, architecture context/changelog и добавить `spec/19_documentation.md`. Пустые user-guide pages не создаются; конкретный docs profile заполняется при начале реализации компонента.

## 1.0.0 — 2026-08-03

Первая формально версионированная редакция baseline. Более ранние snapshots идентифицировались только датой.

### Изменено

- WireGuard закреплён как host-native системный контур: конфигурация применяется root helper в `/etc/wireguard`, а контейнерный WireGuard gateway отклонён.
- Публичный UDP endpoint WireGuard Master больше не закреплён за `51820/udp`: Wizard предлагает `31946/udp`, проверяет конфликт и позволяет выбрать другой порт.
- Добавлен единый реестр listener-ов и default-портов `18_port_registry.md`. Номер порта не является identity, случайный fallback запрещён, точный bind входит в Deployment Plan и PostgreSQL desired state.
- Agent application ingress первой версии использует `26783/tcp` и обычный HTTP только внутри WireGuard. Доступ разрешён исключительно от точного tunnel IP Master; прикладной Nginx mTLS оставлен будущим Ultimate-профилем.
- Обязательный mTLS сохранён для Agent API/Core/Guard, Vault Agent listener и CrowdSec LAPI. Глобальная формулировка «mTLS для любого межнодового трафика» отменена.
- Docker `ports:` и любое host-публикация listener считаются явной сетевой мутацией; неявные публикации, `-P` и случайный выбор свободного порта запрещены.
- Введён version-aware workflow синхронизации субпроектов: baseline + changelog delta + изменённые тематические спецификации. Полное перечитывание требуется при major-версии, неизвестной исходной версии или противоречии.

### Затрагиваемые субпроекты

- `silesco-nginx`: заменить `8443/mTLS` на WireGuard-only `26783/http` в baseline-контракте; сохранить mTLS как будущий профиль.
- `silesco-protocol`: обновить архитектурный snapshot и примеры Master endpoint; wire schemas остаются совместимыми, поскольку порт уже типизирован как значение endpoint.
- `silesco-installer`, `silesco-wizard`: выбирать и проверять Master WireGuard UDP endpoint, не hardcode-ить `51820`.
- `silesco-agent-core`, `silesco-agent-guard`, `silesco-helper-apply-wireguard`: различать endpoint и identity; применять native host WireGuard generation.
- `silesco-helper-apply-deployment`, `silesco-sam-toolchain`, `silesco-helper-apply-firewall`: валидировать точный `{transport,address,port}` и явную публикацию.
- `silesco-vault`, `silesco-crowdsec`, `silesco-ui`: использовать назначенные WireGuard-only host mappings из реестра, сохраняя mTLS.

### Миграция

Это первая формальная версия, поэтому миграции с предыдущей SemVer-версии нет. Субпроекты с snapshot `2026-07-28` применяют всю запись `1.0.0`, обновляют релевантные `spec/`, записывают `Architecture baseline: 1.0.0` в `PROJECT.md` и `STATUS.md`.
