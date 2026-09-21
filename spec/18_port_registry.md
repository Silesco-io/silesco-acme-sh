# Silesco.io — Реестр listener-ов и политика портов

**Лицензия документа:** CC BY 4.0; runtime лицензируется отдельно
**Архитектурный baseline:** 1.19.1
**Дата проверки IANA:** 2026-08-23

## 1. Назначение

Silesco сохраняет привычные порты для пользовательских приложений. Нестандартные default-номера control plane выбираются не как защита и не скрывают сервис: это способ не занимать `8080`, `8443`, `51820` и другие часто используемые пользователем значения.

Silesco не имеет выделенного IANA диапазона. Каждый default ниже является только локальным значением продукта, проверенным по [IANA Service Name and Transport Protocol Port Number Registry](https://www.iana.org/assignments/service-names-port-numbers/service-names-port-numbers.xhtml). Installer/Wizard всё равно обязан проверить фактический конфликт на целевом хосте. При конфликте пользователь выбирает другое значение; автоматический fallback на случайный порт запрещён.

## 2. Классы listener-ов

| Класс | Где занимает socket | Кто может обращаться | Политика |
|---|---|---|---|
| public host | публичный адрес хоста | Internet либо явно ограниченные источники | Только точный Deployment Plan и UFW/CrowdSec policy |
| WireGuard-only host | только стабильный tunnel IP | точные разрешённые Silesco peers | Не открывается на public interface; source/route allowlist обязателен |
| loopback-only host | только `127.0.0.1` или отдельно утверждённый `::1` | host-native компонент той же ноды | Exact bind; wildcard/public/WireGuard exposure запрещены; mTLS/role остаются обязательны по contract |
| container-only | только private user-defined Docker network/Unix socket, без `ports:` | контейнеры разрешённой сети | Не занимает host port; upstream standard port можно сохранять |
| user application | разрешённый public/WireGuard/local bind | определяется SAM и выбором пользователя | Не входит в control-plane pool; конфликт проверяется по полному tuple |

Конфликт определяется по `{network namespace, transport, address, port}`, а не по одному номеру. Например, bind `10.73.0.2:26783/tcp` на WireGuard IP не запрещает приложению использовать `203.0.113.10:26783/tcp`, если ОС и policy допускают раздельные address-specific binds. Wildcard bind конфликтует со всеми адресами соответствующего namespace.

## 3. Назначенные defaults baseline 1.16.0

| Идентификатор | Default | Класс/bind | Transport security | Настраиваемость |
|---|---:|---|---|---|
| `master_wireguard_endpoint` | `31946/udp` | public host Master | WireGuard cryptographic peer identity | Wizard предлагает default; пользователь может изменить |
| `agent_application_ingress` | `26783/tcp` | Agent WireGuard IP only | HTTP внутри WireGuard; source только точный Master tunnel IP | фиксирован контрактом `v1alpha1`; изменение требует versioned plan |
| `master_agent_api` | `27931/tcp` | Master WireGuard IP only | обязательный mTLS Core/Guard | installation setting; endpoint не является identity |
| `master_local_agent_api` | `29371/tcp` | Master loopback `127.0.0.1` only | тот же обязательный mTLS Core/Guard; TLS завершается в Yii3 Agent API | fixed default после collision check; plaintext/Nginx identity proxy запрещены |
| `master_vault_agent_listener` | `31627/tcp` | Master WireGuard IP only | server TLS + обязательный mTLS/AppRole policy | installation setting; container upstream может оставаться `8200` |
| `master_vault_bootstrap_listener` | `30741/tcp` | Master loopback `127.0.0.1` only | TLS 1.3 + обязательный mTLS, client identity только Unseal Controller; exact `/sys/unseal` projection | fixed default после collision check; Docker target `8202`; доступен в sealed setup/recovery/permanent profiles, не является Vault API proxy |
| `master_vault_init_listener` | `32417/tcp` | container-only temporary profile network | PREINIT: exact bootstrap-init URI SAN и `/sys/init`; POSTINIT_SEALED_SETUP: distinct setup CA/URI и closed manifest API; RECOVERY_SETUP: distinct recovery CA/URI и только generate-root attempt/update/cancel | fixed contract; без `ports:`/host bind; host-native setup worker входит по проверенному netns FD и соединяется с pinned container IP, а не через host projection; init/setup/recovery profiles взаимоисключающие, ordinary listeners отсутствуют, temporary listener/network удаляются до permanent phase |
| `master_crowdsec_lapi` | `29873/tcp` | Master WireGuard IP only | TLS + обязательная machine mTLS identity | installation setting; upstream container/native port может отличаться |
| `master_postgresql_collector_ingest` | `28643/tcp` | Master loopback `127.0.0.1` only | PostgreSQL TLS 1.3 + client certificate + restricted DB role | installation setting после collision check; container target остаётся `5432` |

Эти разнесённые номера образуют единый управляемый пул назначений Silesco, но намеренно не являются непрерывным диапазоном и не резервируют соседние значения. Все девять значений на дату проверки не имели назначения в IANA registry; `32417/tcp` находился внутри unassigned диапазона `32401–32482`. Новое назначение добавляется только архитектурным ADR и обновлением этого файла.

Public web ingress сохраняет стандартные `80/tcp` и `443/tcp`, поскольку это пользовательская точка входа HTTP(S), а не скрытый control-plane listener. SSH остаётся системным listener пользователя и не управляется реестром Silesco.

## 4. WireGuard endpoint

WireGuard работает на host Master/Agent, не в контейнере. Входящий public UDP listener требуется только Master. Agent инициирует соединение и не получает публичного входящего WireGuard-правила.

Wizard выполняет цепочку:

```text
предложить 31946/udp
→ проверить bind/socket/firewall/Docker conflict
→ позволить пользователю выбрать другой UDP port
→ записать точный endpoint в PostgreSQL и canonical bootstrap payload
→ применить host WireGuard generation через Guard/helper
```

Смена UDP endpoint не меняет `master_id`, WireGuard public key, stable tunnel addresses или component mTLS identities. Установщик передаёт endpoint и закреплённый public key раздельными полями.

## 5. Agent application ingress

Baseline первой версии:

```text
Internet → Master Nginx → host WireGuard → http://<agent-tunnel-ip>:26783 → Agent Nginx → app
```

Это исключение из mTLS control-plane policy. Listener:

- bind-ится только на точный Agent WireGuard address;
- доступен по WireGuard routes только Master;
- дополнительно фильтруется по точному Master tunnel source address;
- не публикует UI, Wizard, Vault, Agent API или другие control-plane routes;
- принимает исходный `Host` и forwarding headers только от этого доверенного Master source;
- не имеет fallback на public Agent address;
- закрывается fail-closed при неизвестном host/application route.

WireGuard уже обеспечивает шифрование и peer authentication. Отказ от второго TLS-слоя упрощает домашний baseline, но уменьшает независимую component identity и защиту при ошибке маршрутизации. Поэтому Agent-to-Agent forwarding запрещён, AllowedIPs остаются узкими, а optional Nginx mTLS profile резервируется для будущей Ultimate-редакции и не входит в MVP contract.

## 6. Docker и SAM

Container-only upstream может продолжать использовать стандартный порт образа (`5432`, `8200`, `8080` и т.п.), пока `ports:` не публикует его на host. Изменять внутренний upstream номер только ради освобождения host port не требуется.

Термин «внутренняя сеть» описывает membership/exposure boundary и не означает обязательный Docker-флаг `--internal`. Общая `silesco_backend` создаётся как user-defined bridge без неявных публикаций: `--internal` несовместим с зарегистрированным host-loopback mapping PostgreSQL Collector. Каждая host publication всё равно остаётся отдельной привилегированной записью Deployment Plan.

Любой `ports:`, host networking, `-P`, wildcard bind или UFW opening является явной частью Deployment Plan. SAM описывает разрешённую exposure-модель, но не открывает порт сам. После разрешения basic/PRO параметров UI проверяет полный tuple, Guard/Helper повторяет проверку перед apply, а итоговое значение сохраняется в desired state и audit без случайной перенумерации.

## 7. Release gate

Перед каждым релизом компонента, создающего listener:

1. сверить назначение с актуальным IANA registry;
2. проверить collisions на всех поддерживаемых ОС и Docker profiles;
3. подтвердить exact bind address и отсутствие wildcard expansion;
4. проверить UFW/nftables и Docker publication как единый effective ingress;
5. обновить этот реестр, Deployment Plan schema/fixtures и changelog при изменении default;
6. доказать, что изменение endpoint не меняет cryptographic identity.
