# Silesco.io — Нормативная структура файловой системы управляемой ноды

**Лицензия документа:** CC BY 4.0
**Статус:** нормативный baseline
**Дата:** 2026-07-16
**Версия архитектурного baseline:** 1.20.0 (не версия продукта)

---

## 1. Назначение и стандарт

ADR-087 дополнительно резервирует:

- `/opt/silesco.io/components/silesco-kms-adapter/versions/<version>/` — root-owned
  immutable artifact, без provider credentials; `current` переключает Installer;
- `/etc/opt/silesco.io/silesco-kms-adapter/` — root-owned public provider policy;
- `/var/opt/silesco.io/state/silesco-kms-adapter/` — root0700, encrypted provider
  credentials и K ciphertext generations, public bindings/replay/acks;
- `/run/silesco.io/bootstrap/kms/` — root-owned bounded private ingress/FD
  coordination. Plaintext credential не переносится в durable Wizard state;
  точные leaf ownership/modes/TTL закрепляются private ABI до реализации.

Adapter запускается под отдельной ограниченной identity `silesco-kms-adapter`;
только нужный credential/FD передаётся конкретному процессу. Это не доступ ко
всему state root, systemd host key, Docker socket или Vault credentials.

Этот модуль задаёт единый filesystem contract для Master- и Agent-нод. Субпроект не вправе самостоятельно выбирать новый постоянный каталог. Отклонение требует глобального ADR и миграции/rollback.

Silesco является add-on package и следует FHS 3.0:

- `/opt/silesco.io/` — статические first-party артефакты, изменяемые только installer/update;
- `/etc/opt/silesco.io/` — host-specific configuration;
- `/var/opt/silesco.io/` — постоянное изменяемое состояние add-on package;
- `/run/silesco.io/` — runtime state, автоматически исчезающий после reboot;
- `/var/log/silesco.io/` — только журналы, которым действительно нужен файл;
- `/srv` и `/home` Silesco не использует как внутреннее хранилище по умолчанию.

Source-пиры имеют отдельный профиль и получают только каталоги реально установленного peer-компонента.

## 2. Нормативное дерево

```text
/opt/silesco.io/
├── components/<component>/
│   ├── versions/<semver>/{bin,share}/
│   └── current -> versions/<semver>
├── helpers/<helper>/
│   ├── versions/<semver>/bin/<helper>
│   └── current -> versions/<semver>
├── web/<ui-or-pwa>/versions/<semver>/
└── third-party/<artifact>/<version>/

/etc/opt/silesco.io/
├── node.yaml
├── agents/
├── backup/
├── nginx/
├── acme/
├── vault/
├── postgres/
└── trust/

/var/opt/silesco.io/
├── state/<component>/
├── state/installer/vault-init/
│   ├── transactions/<transaction-id>/
│   │   ├── recipients/{l,p,k,r,root}.cred
│   │   ├── encrypted-response.json
│   │   ├── manifest.json
│   │   ├── setup-runtime-binding.json
│   │   ├── postgresql-certificate-package.json
│   │   ├── kms-deliveries/<delivery-id>.json
│   │   ├── postgresql-tls-barrier.json
│   │   └── recovery/<recovery-id>/attempt.json
│   └── current -> transactions/<transaction-id>
├── state/installer/vault-unseal-l/
│   ├── generations/<generation>/silesco-vault-local-share-l.cred
│   └── current -> generations/<generation>
├── system/<service>/
│   ├── generations/<generation>/
│   └── current -> generations/<generation>
├── apps/<instance-directory>/
│   ├── identity.json
│   ├── compose.yaml -> deployment/current/compose.yaml
│   ├── deployment/
│   │   ├── generations/<generation>/{compose.yaml,env,generated,metadata.json}
│   │   └── current -> generations/<generation>
│   ├── data/<volume-name>/
│   └── exports/
├── pki/agent/
│   ├── core/
│   │   ├── generations/<generation>/{key.pem,cert.pem,chain.pem,metadata.json}
│   │   └── current -> generations/<generation>
│   └── guard/
│       ├── generations/<generation>/{key.pem,cert.pem,chain.pem,metadata.json}
│       └── current -> generations/<generation>
├── acme/
├── backup/{jobs,cache,staging}/
├── spool/silesco-agent-observer/
├── cache/
├── geoip/
└── recovery/

/run/silesco.io/
├── sockets/
├── locks/
├── credentials/permits/
├── bootstrap/
│   ├── agent-enrollment/<enrollment-session-id>/
│   ├── wizard/wizard.sock
│   └── unseal/controller.sock
└── tmp/

/etc/opt/silesco.io/trust/execution-permit/
├── generations/<monotonic-generation>/trust.json
└── current -> generations/<monotonic-generation>

/etc/opt/silesco.io/trust/crowdsec-binding/
├── generations/<monotonic-generation>/trust.json
└── current -> generations/<monotonic-generation>

/var/log/silesco.io/nginx/{access.log,error.log}
```

`<component>` и `<helper>` берутся из утверждённого реестра, `<semver>` — каноническая SemVer без пользовательского ввода. `<instance-directory>` по умолчанию имеет вид `<readable-slug>--<short-id>`, например `nextcloud--a1b2c3d4`; режим приватности использует полный opaque UUID. Directory name является удобным locator, но не authoritative identity.

## 3. Бинарники, helpers и обновления

Каждая версия устанавливается в новый root-owned каталог, например:

```text
/opt/silesco.io/components/silesco-agent-core/versions/0.1.0/bin/silesco-agent-core
```

`current` — root-owned относительная symlink, атомарно переключаемая только installer/update helper. Порядок обновления:

1. создать новый version directory вне `current`;
2. проверить TUF/Cosign/hash, ELF dependencies, ownership и mode;
3. выполнить `fsync` файлов и родительского каталога;
4. атомарно заменить `current` symlink;
5. перезапустить unit и проверить health/protocol compatibility;
6. при ошибке вернуть предыдущую symlink;
7. удалять старые версии только по bounded retention после успешного health.

Execution Permit trust является отдельной monotonic projection, а не обычным component `current`: `trust.json` имеет `root:silesco-guard` и `0440`, generation directories — `root:root` и `0750`, все файлы regular/nlink=1/no-follow. Root helper читает ту же projection как root. Caller не может выбрать trust file. Normal rotation может удалить предыдущий key из accepted set только после bounded overlap; emergency revocation не откатывается к старой generation.

CrowdSec binding trust — отдельная purpose-specific monotonic projection с теми же regular/nlink/no-follow и rollback monotonicity требованиями, но доступная только root activation path. Она содержит installation binding, generation, exact `kid` и SPKI public key Transit `silesco-crowdsec-binding`; private key, Vault token и signer capability запрещены. Она не может заменять Execution Permit trust и не выбирается caller-ом first-slice plan.

Systemd запускает демоны по абсолютному пути `.../current/bin/...` и не полагается на `PATH`.

Helpers находятся только в `/opt/silesco.io/helpers/`. Все parents принадлежат `root:root` и недоступны для записи group/other. Sudoers перечисляет только утверждённые `current/bin/<helper>` paths. Helper повторно проверяет Execution Permit и protocol compatibility.

В `/bin`, `/sbin`, `/usr/bin` Silesco файлы не создаёт. Только интерактивные команды получают root-owned symlinks:

```text
/usr/local/sbin/silesco -> /opt/silesco.io/components/silesco-installer/current/bin/silesco
/usr/local/bin/silesco-recovery -> /opt/silesco.io/components/silesco-recovery-tooling/current/bin/silesco-recovery
```

Хардлинки запрещены: они мешают version switching/rollback, могут пересекать filesystems и скрывают принадлежность inode версии.

## 4. Конфигурация

`/etc/opt/silesco.io` содержит только декларативную host configuration и trust anchors, а не runtime state, базы, caches или бинарники.

- root-owned configuration: `root:silesco-<service>`, обычно files `0640`, directories `0750`;
- публичные CA/certificate chain могут быть `0644`, private keys — нет;
- секреты не помещаются в обычный YAML: используются Vault references или systemd credentials;
- generated configuration имеет schema/version/source digest;
- ручные overrides отделены от generated files и не перезаписываются молча;
- native host-продукты используют стандартные locations (`/etc/crowdsec`, `/etc/docker`, `/etc/systemd`), но Silesco-managed fragment помечается header/digest;
- Silesco не заменяет целиком чужой конфигурационный каталог, если достаточно drop-in/acquisition file.

## 5. Постоянное состояние и deployments

`/var/opt/silesco.io` входит в backup/recovery inventory и не содержит versioned executable.

Общие parents `/var/opt/silesco.io` и `/var/opt/silesco.io/state` имеют
`0711 root:root`: это разрешает scoped service пройти только к заранее
известному leaf path, но запрещает listing соседей. Каждый
`state/<component>` остаётся `0700/0750` у своей service identity; installer
state и private material не становятся world-readable.

`state/<component>` содержит replay journal, cursor, durable queue/WAL и другую принадлежащую компоненту state. Один компонент не пишет в state другого. Межкомпонентный обмен использует протокол либо специально описанный spool. Для Docker-телеметрии таким spool является `/var/opt/silesco.io/spool/silesco-agent-observer/`: Observer — единственный writer, Core читает через read-only ACL и не может изменять, переименовывать или удалять записи.

`system/<service>` содержит поколения производных Compose/config artifacts самой платформы: Nginx, UI/PWA, PostgreSQL/TimescaleDB, Vault и ACME container profile при его использовании. Эти private control-plane services никогда не предлагаются пользовательским приложениям и не смешиваются с `apps/`.

Абсолютный root пользовательских приложений — `/var/opt/silesco.io/apps/`. Каждый instance получает отдельный `<instance-directory>`.

`deployment/` содержит только производные файлы Deployment Plan. Поколение сначала создаётся в `deployment/generations/<generation>`, валидируется, `fsync`-ится и затем активируется атомарной `deployment/current` symlink. Верхний `compose.yaml` является только удобной read-only symlink на `deployment/current/compose.yaml`; копии с расходящимся содержимым запрещены.

`data/<volume-name>/` находится вне generations и никогда не удаляется при update/rollback deployment. В монолитном режиме каталог приложения содержит данные всего принадлежащего ему стека, например `apps/nextcloud--.../data/{nextcloud,postgresql,redis}/`. В атомарном режиме PostgreSQL/Redis являются самостоятельными приложениями с собственными каталогами `apps/postgresql-main--.../` и `apps/redis-main--.../`; потребитель хранит только dependency binding. Official SAM по умолчанию создаёт managed bind mounts из этих каталогов. Docker named volumes разрешены только как явное исключение с предупреждением.

App `identity.json` (без секретов) хранит полный immutable `app_id`, SAM identity/version, display name, directory slug, container/volume mappings, предоставляемые capabilities и app-to-app dependency bindings. Отдельный `README-recovery.txt` не создаётся: recovery tooling строит инструкцию из `identity.json`, Deployment Plan и backup metadata. SAM содержит только структурированные consistency/restore requirements.

SAM metadata и пользовательское имя предлагают readable slug, но не задают raw filesystem path. Silesco нормализует slug по строгому allowlist, добавляет короткий immutable ID для уникальности и сохраняет mapping в `identity.json`/PostgreSQL/Docker labels. Пользователь может выбрать opaque UUID до deployment; после создания directory name стабилен. Rename требует отдельной остановки, проверяемой миграции и Execution Permit. Traversal, symlink/hardlink attacks и mount escapes проверяются до записи; root helper использует safe relative operations (`openat2`/эквивалент там, где доступно).

Docker image layers и явно выбранные named volumes остаются в стандартном Docker data root. Silesco не перемещает `/var/lib/docker`. Нормативный default для official SAM — managed bind data в `/var/opt/silesco.io/apps/<instance-directory>/data/<volume-name>/`. Внешний absolute path допускается только после повышенного подтверждения и backup-policy review.

## 6. PKI, credentials и runtime

- Core mTLS generations: `/var/opt/silesco.io/pki/agent/core/`, owned by `silesco-ops`; Guard read запрещён;
- Guard mTLS generations: `/var/opt/silesco.io/pki/agent/guard/`, owned by `silesco-guard`; Core read запрещён;
- каждая generation содержит отдельную private key, leaf certificate, CA chain и public metadata; `current` переключается только после доказанного нового mTLS connection;
- WireGuard generations: `/var/opt/silesco.io/system/wireguard/generations/<generation>/`, root-only; active projection в native `/etc/wireguard/` либо live interface применяет только `silesco-helper-apply-wireguard`;
- installation address plan: root-owned closed metadata в active WireGuard generation хранит IPv4 CIDR, random ULA `/64`, Master addresses и allocation cursor; private keys в metadata/DB не входят;
- WireGuard является host-native службой; контейнерный gateway, `NET_ADMIN`/`/dev/net/tun` для Silesco-контейнера и ownership tunnel lifecycle из Docker не входят в baseline;
- временная enrollment WireGuard keypair и session material: `/run/silesco.io/bootstrap/agent-enrollment/<enrollment-session-id>/`, root-only; reboot/expiry требует нового bootstrap claim, а не восстановления raw capability;
- acme.sh home/account/challenge state: `/var/opt/silesco.io/acme/`;
- long-lived private keys: scoped service groups, dirs `0750/0700`, files `0640/0600`;
- short-lived credentials: `/run/silesco.io/credentials/`, предпочтительно systemd credentials/tmpfs;
- Unseal Controller Vault mTLS material: scoped systemd credentials под `/run/credentials/<unit>/`; persistent client private key не читается Nginx/UI и не попадает в command line/environment;
- `L` хранится только в `/var/opt/silesco.io/state/installer/vault-unseal-l/generations/<generation>/silesco-vault-local-share-l.cred` как host-bound encrypted systemd credential: все каталоги `root:root 0700`, файл `root:root 0600`, regular, `nlink=1`, без symlink traversal; `current` переключается атомарно и проверяется no-follow;
- до первого Vault init target host обязан пройти executable `systemd-creds --with-key=host` + transient `LoadCredentialEncrypted=` round-trip. Plaintext/null/TPM fallback и перенос `/var/lib/systemd/credential.secret` между OS installations запрещены;
- transaction custody располагается только в `/var/opt/silesco.io/state/installer/vault-init/transactions/<transaction-id>/`: parent `root:root 0700`, `recipients/*.cred`, `encrypted-response.json`, exact manifest/digests `root:root 0600`, regular, `nlink=1`. Каждый recipient — отдельный credential; объединённый quorum file запрещён;
- `encrypted-response.json` содержит exact Vault PGP ciphertext и после `initialized=true` считается recovery material без time-based deletion. Он исключён из обычного backup/`.srb`, сохраняется при `vault_init_recovery_required` и удаляется только после aggregate commit либо explicit destructive reset нового пустого volume;
- `kms-deliveries/<delivery-id>.json` содержит только closed plan/nonce/ciphertext digests/TTL/peer identities и sanitized ack; plaintext/hash `K`, KMS credential и memfd content отсутствуют. Socketpair не имеет filesystem path и не восстанавливается после crash;
- `postgresql-tls-barrier.json` связывает bootstrap/permanent generations, CSR/package/manifest digests и apply/restart/TLS peer acknowledgement. Transaction bootstrap server TLS является отдельной root-owned PostgreSQL generation; permanent private key остаётся у PostgreSQL owner, Vault получает только CSR;
- `setup-runtime-binding.json` — closed non-secret record exact Vault setup container/network identity: container ID, PID/starttime, pinned image identity, Compose project/service, network ID, container IP и netns device/inode. `/proc/<pid>/ns/net` и control socket не сохраняются как path/FD; после crash binding строится и проверяется заново;
- `postgresql-certificate-package.json` хранит exact canonical public certificate package от operations `1..28`, regular/nlink=1/root-only, через unique staging + `fsync` + atomic rename. `postgresql-tls-barrier.json` хранит acknowledgement и exact package digest. Другие bytes при том же transaction/setup generation запрещены;
- `recovery/<recovery-id>/attempt.json` хранит Raft cluster/original transaction/ciphertext/setup generation+hash, attempt nonce/TTL/progress/revoke proof без shares, Root token либо fresh recipient private packet. Private recipient существует только как отдельный host-encrypted credential;
- owner-specific instantiated oneshots используют fixed `LoadCredentialEncrypted=` path; runtime plaintext существует лишь в unit-specific `$CREDENTIALS_DIRECTORY` и sealed memfd/`SCM_RIGHTS`. Root coordinator не получает private `P/R/K` recipients или plaintext shares;
- setup invocation использует только fixed caller/child FD tables ADR-085; netns и `SOCK_SEQPACKET` control не имеют persistent filesystem path. После barrier первый worker завершается, а continuation получает новые process/FDs/delivery UUID/nonce при том же ciphertext/setup generation;
- `/run/silesco.io/bootstrap/unseal/` принадлежит `silesco-unseal:silesco-bootstrap`, имеет target mode `0710`, а `controller.sock` — `0660`; только Nginx получает supplemental numeric GID этой группы и exact bind mount каталога;
- `/run/silesco.io/bootstrap/wizard/` принадлежит `silesco-wizard:silesco-bootstrap`, имеет target mode `0710`, а `wizard.sock` — `0660`; Nginx получает отдельный exact bind mount только этого leaf, не всего bootstrap parent;
- `/run/silesco.io/bootstrap/trusted-origin/` принадлежит `root:silesco-bootstrap`, имеет `0750`; root атомарно публикует несекретный `current.json` как `root:silesco-bootstrap`, `0440`, а Wizard получает только read/traverse и никогда не пишет readiness proof; directory не монтируется в Nginx container;
- versioned PWA static artifact расположен в `/opt/silesco.io/components/silesco-pwa/versions/<semver>/dist/` и монтируется Nginx только read-only после проверки digest;
- plaintext secrets запрещены в `/opt`, logs, caches, command line и process environment дольше contract lifetime.

`/run/silesco.io` создаётся systemd `RuntimeDirectory=`/tmpfiles и очищается после reboot:

Общие parents `/run/silesco.io` и `/run/silesco.io/bootstrap` имеют
`0711 root:root`; закрытые leaf directories (`tmp`, credentials, отдельные
bootstrap sessions) сохраняют `0700/0750`. Это traversal-only исключение не
разрешает directory listing и не заменяет scoped owner/group socket boundary.

- sockets: `/run/silesco.io/sockets/<service>.sock`;
- краткоживущие подписанные Execution Permits для root helpers: `/run/silesco.io/credentials/permits/`, root-controlled и без попадания payload/secrets в command line;
- Wizard token/session/handoff/progress: `/run/silesco.io/bootstrap/` с разделёнными unprivileged/root-only subdirectories;
- temporary files используют `O_EXCL`, ограниченный mode и atomic rename;
- durable journal не хранится только в `/run`, а располагается в `/var/opt/silesco.io/state/...`.

## 7. Логи и аудит

Первичный Vault init не создаёт persistent plaintext path. Временный control socket, если он необходим реализации, расположен только в `/run/silesco.io/bootstrap/vault-init/control.sock`: parent `root:root 0700`, socket `root:root 0600`. P/R/token plaintext проходит через unit credential + sealed memfd/inherited FD/`SCM_RIGHTS`; host-encrypted recipients и exact response ciphertext являются единственными допустимыми persistent transaction artifacts. Temporary TLS/private identities удаляются после commit. Их plaintext нельзя размещать в `/var/opt`, journal, Wizard state или общем Controller socket.

Удаление чувствительного transaction artifact доказывается проверкой точного owner/type/mode/link count, `unlink`, `fsync` родительского каталога и повторным no-follow readback отсутствия. Архитектура не заявляет secure erase блоков SSD/CoW filesystem. После `initialized=true` automatic expiry не является основанием удалить `encrypted-response.json`.

First-party services по умолчанию пишут structured logs в stdout/stderr → journald. Собственные log files не создаются без контракта.

Исключение первой версии — Nginx access/error logs в `/var/log/silesco.io/nginx/`, которые читает host CrowdSec. Используются logrotate, ограниченные modes и формат без bootstrap-token, credentials или query secrets.

Security audit и container telemetry являются данными PostgreSQL/TimescaleDB и bounded spool, а не обычными текстовыми логами.

Setup journal не хранит plaintext secret и не хранит hash/HMAC полного runtime request, если запрос содержит password, share или token. Допустимы только digest public template/artifact, role входного FD, non-secret bindings и semantic result/readback.

## 8. Backup, cache и recovery

- host restic читает только paths resolved backup contract;
- cache/staging располагаются в `/var/opt/silesco.io/backup/` и имеют квоты/очистку;
- cache удаляем без потери authoritative state; staging не считается успешным backup;
- Recovery Bundle по умолчанию не хранится на восстанавливаемой ноде: пользователь выбирает внешний destination;
- local temporary bundle создаётся атомарно, имеет `0600` и удаляется после подтверждённого экспорта;
- `/opt` не является единственным backup source: first-party artifacts восстанавливаются из TUF repository, а backup сохраняет versions/digests, configuration/state/user data.

## 9. Разрешённые system integration paths

| Path | Назначение |
|---|---|
| `/usr/local/bin`, `/usr/local/sbin` | Только root-owned CLI symlinks |
| `/etc/systemd/system/silesco-*` | Local service/timer/path units; templates лежат в `/opt` |
| `/etc/systemd/system/silesco-*.d/` | Host overrides/drop-ins |
| `/etc/sudoers.d/silesco` | Allowlist helpers, `0440`, обязательный `visudo -c` |
| `/etc/tmpfiles.d/silesco.conf` | Runtime directories, если не покрыты unit directives |
| `/etc/logrotate.d/silesco` | Ротация необходимых файловых логов |
| `/etc/crowdsec/acquis.d/silesco.yaml` | Nginx acquisition fragment |
| `/etc/crowdsec/parsers|scenarios/...` | Только versioned Silesco CrowdSec content |

Новые исключения нельзя добавлять локальным решением субпроекта.

## 10. Ownership и базовые modes

| Область | Owner/group | Mode по умолчанию |
|---|---|---|
| `/opt/silesco.io` static tree | `root:root` | dirs `0755`, executable `0755`, static data `0644` |
| helper tree | `root:root` | ни один parent не writable group/other |
| `/etc/opt/silesco.io` | `root:root` или scoped service group | dirs `0750`, files `0640` |
| private keys/credentials | `root:<service>` | dirs `0750/0700`, files `0640/0600` |
| component state | `<service>:<service>` | dirs `0750/0700`, files `0640/0600` |
| shared append-only spool | writer group + read-only consumer ACL | consumer не rename/delete |
| Nginx logs | Nginx writer + CrowdSec reader | dirs `0750`, files `0640` |
| common service path parents | `root:root` | exact shared parents `0711` без listing; leaf не наследует этот mode |
| runtime socket | producer + explicit consumer group | socket `0660`, leaf parent `0710/0750` |

Installer проверяет owner, group, mode, symlinks и mount boundary. Несовпадение даёт явную ошибку; рекурсивный `chmod -R` запрещён.

## 11. Uninstall и сохранение данных

Обычный uninstall:

- останавливает units и удаляет `/opt/silesco.io`;
- удаляет созданные CLI symlinks/integration fragments;
- не удаляет `/etc/opt/silesco.io`, `/var/opt/silesco.io`, backups или application data без отдельного purge confirmation;
- перечисляет сохранённые данные и процедуру purge;
- purge показывает точные resolved paths и не следует symlink за Silesco roots.

## 12. Реестр путей компонентов

| Компонент/область | Static/config | Persistent/runtime writes |
|---|---|---|
| Installer/repair CLI | `/opt/silesco.io/components/silesco-installer/...`; CLI symlink `/usr/local/sbin/silesco` | `/var/opt/silesco.io/state/installer/`; bootstrap runtime `/run/silesco.io/bootstrap/` |
| Installation identity | `/etc/opt/silesco.io/identity/installation.json`, `root:root`, immutable public record, UUIDv7 only | authoritative copy в PostgreSQL и encrypted Recovery Bundle; private credential рядом не хранится |
| Wizard | временный `/opt/silesco.io/components/silesco-wizard/...` | `/run/silesco.io/bootstrap/wizard/wizard.sock` и scoped handoff/progress leaves; durable journal принадлежит installer |
| Unseal Controller | `/opt/silesco.io/components/silesco-unseal-controller/...`; `/etc/opt/silesco.io/unseal-controller/` | `/var/opt/silesco.io/state/silesco-unseal-controller/` содержит public credential/replay metadata, terminal PWA state и plaintext-free durable root outbox/ack; continuation capability memory-only; socket/request staging — `/run/silesco.io/bootstrap/unseal/` |
| Vault init custody | Vault-owned setup manifest/config — `/etc/opt/silesco.io/vault/`; fixed systemd unit definitions | `/var/opt/silesco.io/state/installer/vault-init/transactions/<transaction-id>/` содержит только host-encrypted recipients, exact response ciphertext и digests; `L` отдельно в `vault-unseal-l/generations/`; plaintext persistent files запрещены |
| Vault key owner | `/opt/silesco.io/components/silesco-vault-key-owner/<version>/`; `/etc/opt/silesco.io/vault-key-owner/`; fixed init/rewrap/rekey/recovery units | `/var/opt/silesco.io/state/silesco-vault-key-owner/generations/` и `transactions/` содержат encrypted recipients/ciphertext, fingerprints и acknowledgements; plaintext только sealed memory/runtime credential; daemon/socket отсутствует |
| Vault setup/recovery profiles | versioned Vault HCL/Compose/contracts; separate init/setup/recovery CA generations | тот же Docker Raft volume; transaction journals/barriers только в installer state; temporary network/PKI/runtime leaves удаляются до permanent profile |
| PostgreSQL bootstrap/permanent TLS barrier | Vault infrastructure PKI role + PostgreSQL-owned CSR/apply contract | `/var/opt/silesco.io/system/postgresql/generations/<generation>/`; bootstrap и permanent server private keys root/PostgreSQL-owner only, не Vault/setup worker |
| Core | `/opt/.../silesco-agent-core`; `/etc/opt/silesco.io/agents/core.yaml` | `/var/opt/silesco.io/state/silesco-agent-core/`; собственный runtime socket |
| Core mTLS identity | trust/policy в `/etc/opt/silesco.io/trust/` | `/var/opt/silesco.io/pki/agent/core/`; только `silesco-ops`, private key `0600` |
| Guard | `/opt/.../silesco-agent-guard`; `/etc/opt/silesco.io/agents/guard.yaml` | `/var/opt/silesco.io/state/silesco-agent-guard/`; permit/replay journal |
| Guard mTLS identity | trust/policy в `/etc/opt/silesco.io/trust/` | `/var/opt/silesco.io/pki/agent/guard/`; только `silesco-guard`, private key `0600` |
| WireGuard Agent transport | managed native projection `/etc/wireguard/`; trust metadata `/etc/opt/silesco.io/trust/` | root-only `/var/opt/silesco.io/system/wireguard/`; temporary enrollment material только `/run/silesco.io/bootstrap/agent-enrollment/` |
| Observer | `/opt/.../silesco-agent-observer`; scoped config | `/var/opt/silesco.io/state/silesco-agent-observer/` и append-only `/var/opt/silesco.io/spool/silesco-agent-observer/` |
| Collector | `/opt/silesco.io/components/silesco-agent-collector/...`; scoped config | `/var/opt/silesco.io/state/silesco-agent-collector/`; `/var/opt/silesco.io/geoip/`; PostgreSQL client material только в tmpfs `/run/silesco.io/credentials/silesco-agent-collector/postgresql/` |
| Telemetry exporter | `/opt/silesco.io/components/silesco-telemetry-exporter/...`; public policy/config в `/etc/opt/silesco.io/telemetry-exporter/` | queue/replay в `/var/opt/silesco.io/state/silesco-telemetry-exporter/queue/`; private P-256 key и certificate generations в закрытом `credentials/generations/<generation>/`, atomic `credentials/current`; revoke intent отдельно без secret; весь credentials subtree исключён из Recovery Bundle/обычного backup |
| Optional alpha debug symbols | `/opt/silesco.io/debug-symbols/<component>/<version>/<build-id>/`, отдельный signed package, root-owned read-only | штатные services не читают; package не содержит dumps и удаляется независимо от executable |
| CrowdSec machine binding | versioned public binding в root apply package; active mapping в PostgreSQL | CrowdSec private key только в root-owned credential generation; projected runtime key в `/run/silesco.io/credentials/`, LAPI JWT только в memory |
| Backup scheduler | `/opt/.../silesco-agent-backup`; `/etc/opt/silesco.io/backup/` | `/var/opt/silesco.io/{state/silesco-agent-backup,backup}/` |
| Root helpers | `/opt/silesco.io/helpers/<helper>/...`; `/etc/sudoers.d/silesco` | только contract-specific target/staging; собственного произвольного root state нет |
| Nginx | `/etc/opt/silesco.io/nginx/`; platform generation `/var/opt/silesco.io/system/nginx/` | `/var/log/silesco.io/nginx/`; exact certificate generation read from `/var/opt/silesco.io/pki/nginx/master-public/current` |
| ACME | `/etc/opt/silesco.io/acme/` | `/var/opt/silesco.io/acme/`; immutable paired generations `/var/opt/silesco.io/pki/nginx/master-public/generations/<generation>/` и один atomic `current` pointer |
| UI/PWA + Yii3 workers | UI artifacts и immutable PWA `/opt/silesco.io/components/silesco-pwa/versions/<semver>/dist/`; platform generation `/var/opt/silesco.io/system/ui/` | authoritative business state в PostgreSQL; краткоживущие runtime files только в `/run/silesco.io/` |
| PostgreSQL/TimescaleDB | `/etc/opt/silesco.io/postgres/`; platform generation и staged server TLS material `/var/opt/silesco.io/system/postgresql/` | Docker named volume в стандартном Docker data root; dumps/staging только по backup contract; private keys не входят в backup |
| Vault | `/etc/opt/silesco.io/vault/`; platform generation `/var/opt/silesco.io/system/vault/` | Docker named volume; seal/recovery artifacts только в утверждённых `pki/recovery` paths |
| Пользовательские приложения и атомарные службы | нет executable в Silesco static tree; SAM предлагает slug, но не raw path | все находятся в `/var/opt/silesco.io/apps/<slug>--<short-id>/`; монолитные dependencies принадлежат app, атомарные являются отдельными apps |
| Protocol/SAM toolchain | `/opt/silesco.io/components/<component>/...` | только caller-provided staging/runtime; persistent business state не создают |
| Recovery tooling | versioned static + `/usr/local/bin/silesco-recovery` symlink | explicit external destination; temporary `0600` staging по recovery contract |
| CrowdSec | package-managed native binaries/config/state; только Silesco fragments из §9 | стандартные host CrowdSec paths, которые Silesco не перемещает |
| Restic | package-manager path либо `/opt/silesco.io/third-party/restic/<version>/`; scheduler хранит проверенный absolute path | repositories являются внешними destinations; cache/staging — только `/var/opt/silesco.io/backup/` |
| Docker Engine | package-managed native paths и `/var/lib/docker` | Silesco не меняет Docker data root/layout |
| Hosted services, release/preview и Symbol store | не входят в filesystem contract пользовательской ноды | отдельные bounded hosted infrastructures; `silesco-release` разворачивает изолированный preview и продвигает exact digest; production secrets, ClickHouse и symbol objects не доступны preview либо пользовательской Панели напрямую |

Если upstream host package установлен package manager, его executable остаётся в нативном `/usr/bin` или `/usr/sbin`; Silesco проверяет package/version/path, но не пишет туда файл вручную. Если Silesco поставляет закреплённый upstream executable самостоятельно, он располагается в `/opt/silesco.io/third-party/` и вызывается по абсолютному пути.

## 13. Критерии готовности

- [ ] Installer создаёт дерево идемпотентно и проверяет ownership/modes.
- [ ] Все units используют нормативные абсолютные paths.
- [ ] Update/rollback атомарно переключает независимые component versions.
- [ ] Каждый субпроект перечисляет только свои read/write paths.
- [ ] Tests покрывают traversal, symlink/hardlink, mount escape и подмену `current`.
- [ ] Backup/recovery отделяет reproducible artifacts от state/user data.
- [ ] Uninstall/purge проверены на сохранение пользовательских данных.
