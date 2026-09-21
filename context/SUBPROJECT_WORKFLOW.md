# Silesco.io — Как начинать отдельный субпроект и чат

**Статус:** рабочая нормативная инструкция
**Дата:** 2026-08-06
**Architecture baseline:** 1.20.1

---

## 1. Что копировать всегда

Для каждого нового компонента скопируй **весь каталог** `_subproject-template`, а не отдельные файлы из него. Копию назови именем компонента, например `silesco-agent-observer/`.

В копии всегда остаются:

```text
AGENTS.md
PROJECT.md
STATUS.md
DECISIONS.md
docs/README.md
context/SILESCO_CONTEXT.md
context/ARCHITECTURE_BASELINE.md
context/ARCHITECTURE_VERSION
context/ARCHITECTURE_CHANGELOG.md
context/GENERATED_SNAPSHOT_MANIFEST.json
spec/
```

Этого набора достаточно, чтобы новый чат понимал цель всей системы, глобальные запреты, состояние конкретной работы и локальные решения.

## 2. Что заполнить перед первым сообщением

1. Заполни `PROJECT.md`: роль, результат для пользователя, ответственность, границы, зависимости, данные, права, сценарии отказа и acceptance criteria.
2. В `spec/` скопируй актуальные тематические документы из таблицы ниже.
3. В `PROJECT.md` добавь раздел `Нормативные источники` с именами файлов и датой snapshot.
4. В `STATUS.md` укажи этап `проектирование`, текущую дату и один следующий проверяемый шаг.
5. `DECISIONS.md` оставь с шаблоном, пока локальных решений нет.
6. Не копируй старую историю Gemini/Codex: принятые решения уже находятся в baseline, ADR и тематических спецификациях.

Если каталог уже существует, сначала сравни его applied architecture version с `ARCHITECTURE_VERSION` основного репозитория. При том же major прочитай changelog delta и изменённые specs. При неизвестной версии, пропущенной записи, major bump или противоречии перечитай полный baseline/spec snapshot.

Синхронизация выполняется только из соответствующего каталога `_subprojects-ready` через `_scaffolding/sync_generated_snapshot.ps1`. Closed `context/GENERATED_SNAPSHOT_MANIFEST.json` задаёт полный authoritative set для architecture-owned roots `spec/` и `context`: source inventory/digests полностью проверяются до target, reparse/symlink/junction запрещены, а full roots применяются через same-volume staging и rollback-safe rename. Tracked-файл в этих roots, отсутствующий в новом manifest, удаляется. Поэтому прежний `spec/07_architecture_decisions.md` или иной historical snapshot не может молча пережить новую генерацию. Component-owned `PROJECT.md`, `STATUS.md`, `DECISIONS.md` и `docs/` скрипт не перезаписывает и не удаляет; applied version/commit в них обновляется отдельным осмысленным change set после review delta.

## 3. Какие спецификации класть в `spec/`

| Субпроект | Обязательные тематические файлы |
|---|---|
| `silesco-agent-core` | `03_go_agents_standards.md`, `03_01_agent_core.md`, `04_network_peers.md`, `05_data_schema.md`, `11_docker_observability.md`, `22_postgresql_contract.md` |
| `silesco-agent-observer` | `03_go_agents_standards.md`, `03_01_agent_core.md`, `05_data_schema.md`, `11_docker_observability.md` |
| `silesco-agent-guard` | `01_security_vault.md`, `03_go_agents_standards.md`, `03_02_agent_guard.md`, `04_network_peers.md`, `06_flows_and_updates.md`, `22_postgresql_contract.md` |
| отдельный root helper | `03_go_agents_standards.md`, `03_02_agent_guard.md`, затронутый flow из `06_flows_and_updates.md`; SAM/Recovery при применимости |
| `silesco-agent-collector` | `03_go_agents_standards.md`, `03_03_auxiliary_agents.md`, `04_network_peers.md`, `05_data_schema.md`, `22_postgresql_contract.md` |
| `silesco-agent-backup` | `01_security_vault.md`, `03_go_agents_standards.md`, `03_03_auxiliary_agents.md`, `06_flows_and_updates.md`, `10_recovery_bundle.md`, `22_postgresql_contract.md` |
| `silesco-agent-notifier` | `03_go_agents_standards.md`, `03_03_auxiliary_agents.md`, `04_network_peers.md`, `05_data_schema.md` |
| `silesco-ui` / Agent API | `00_general_overview.md`, `01_security_vault.md`, `02_master_node.md`, `04_network_peers.md`, `05_data_schema.md`, `06_flows_and_updates.md`, `09_sam_manifest.md`, `11_docker_observability.md`, `22_postgresql_contract.md`, `23_release_channels_and_telemetry.md` |
| `silesco-pwa` | `01_security_vault.md`, `06_flows_and_updates.md`, `08_pwa_prf_capability_lab_context.md`, `10_recovery_bundle.md`, `12_brand_identity.md` |
| `silesco-wizard` | `00_general_overview.md`, `01_security_vault.md`, `02_master_node.md`, `06_flows_and_updates.md`, `10_recovery_bundle.md`, `12_brand_identity.md`, `23_release_channels_and_telemetry.md` |
| installer/bootstrap | `00_general_overview.md`, `01_security_vault.md`, `02_master_node.md`, `03_go_agents_standards.md`, `04_network_peers.md`, `06_flows_and_updates.md`, `10_recovery_bundle.md`, `23_release_channels_and_telemetry.md` |
| `silesco-telemetry-exporter` | `03_go_agents_standards.md`, `05_data_schema.md`, `06_flows_and_updates.md`, `11_docker_observability.md`, `22_postgresql_contract.md`, `23_release_channels_and_telemetry.md` |
| `silesco-telemetry` | `00_general_overview.md`, `05_data_schema.md`, `06_flows_and_updates.md`, `13_licensing_and_contributions.md`, `19_documentation.md`, `21_localization.md`, `23_release_channels_and_telemetry.md` |
| Nginx/acme.sh configuration | `00_general_overview.md`, `01_security_vault.md`, `02_master_node.md`, `04_network_peers.md`, `06_flows_and_updates.md` |
| PostgreSQL/TimescaleDB configuration | `01_security_vault.md`, `02_master_node.md`, `03_03_auxiliary_agents.md`, `04_network_peers.md`, `05_data_schema.md`, `06_flows_and_updates.md`, `10_recovery_bundle.md`, `11_docker_observability.md`, `22_postgresql_contract.md` |
| Vault configuration | `00_general_overview.md`, `01_security_vault.md`, `02_master_node.md`, `04_network_peers.md`, `06_flows_and_updates.md`, `10_recovery_bundle.md` |
| host CrowdSec configuration | `00_general_overview.md`, `02_master_node.md`, `03_03_auxiliary_agents.md`, `04_network_peers.md`, `05_data_schema.md`, `06_flows_and_updates.md` |
| SAM schema/parser/compiler | `05_data_schema.md`, `06_flows_and_updates.md`, `09_sam_manifest.md`, `13_licensing_and_contributions.md` |
| `store.silesco.io` | `06_flows_and_updates.md`, `09_sam_manifest.md`, `13_licensing_and_contributions.md` |
| `KMS.Silesco.io` | `01_security_vault.md`, `04_network_peers.md`, `06_flows_and_updates.md`, `10_recovery_bundle.md`, `13_licensing_and_contributions.md` |
| `silesco-kms-adapter` (local, not hosted) | `00_general_overview.md`, `01_security_vault.md`, `03_go_agents_standards.md`, `06_flows_and_updates.md`, `07_architecture_decisions.md`, `10_recovery_bundle.md`, `13_licensing_and_contributions.md` |
| Recovery Bundle/DR tooling | `01_security_vault.md`, `04_network_peers.md`, `05_data_schema.md`, `06_flows_and_updates.md`, `10_recovery_bundle.md`, `13_licensing_and_contributions.md`, `22_postgresql_contract.md`, `23_release_channels_and_telemetry.md` |
| frontend design system | `00_general_overview.md`, `12_brand_identity.md`, `13_licensing_and_contributions.md` |
| `silesco-docs` | `00_general_overview.md`, `07_architecture_decisions.md`, `12_brand_identity.md`, `13_licensing_and_contributions.md`, `17_development_order.md`, `19_documentation.md`, `21_localization.md` |

`07_architecture_decisions.md` обычно не копируется целиком: актуальные глобальные решения уже сведены в `ARCHITECTURE_BASELINE.md`. Копируй конкретный ADR только если его историческое обоснование необходимо компоненту.

`16_filesystem_layout.md`, `18_port_registry.md`, `19_documentation.md`, `21_localization.md` и `23_release_channels_and_telemetry.md` копируются во все субпроекты как обязательные глобальные contracts. Компонент обязан перечислить собственные read/write paths и listeners, выбрать содержательные docs/localization/channel/privacy profiles и не может вводить новый постоянный root/host port локальным ADR.

## 4. Что не класть в рабочий каталог субпроекта

- договоры, чеки, персональные данные и закрытый mascot evidence archive;
- весь экспорт старого чата;
- лабораторию Portainer, если компонент не анализирует историческое решение;
- PRF raw responses, credential IDs и секреты;
- production tokens, `.env`, Shamir shares, Recovery Bundle пользователя;
- копии всех архитектурных файлов «на всякий случай».

## 5. Первое сообщение в новом чате

> Это субпроект Silesco.io `<имя>`. Полностью прочитай `AGENTS.md`, затем перечисленные там context/spec/PROJECT/DECISIONS/STATUS. После чтения кратко сформулируй цель компонента, его границы и следующий шаг. Пока не меняй архитектурные решения молча: противоречия вынеси отдельным списком. Затем приступай к задаче: `<первая конкретная задача>`.

Не пересказывай в prompt всю архитектуру: файлы являются источником контекста.

## 6. Как возвращать решения в общую архитектуру

Локальное решение остаётся в `DECISIONS.md`, если оно не влияет на другие компоненты. Если меняются межкомпонентный API, security boundary, protocol, data ownership, лицензирование или пользовательский flow:

1. остановить реализацию несовместимой части;
2. сформулировать предлагаемое глобальное решение;
3. обсудить его в архитектурном чате;
4. обновить тематический модуль, `07_architecture_decisions.md` и template baseline;
5. повысить `ARCHITECTURE_VERSION` по SemVer и добавить точную запись в `CHANGELOG.md`;
6. обновить snapshot в затронутых субпроектах;
7. записать applied version/commit в `STATUS.md` субпроекта и `SUBPROJECT_SYNC_STATUS.md`.

## 7. Когда шаблон считается готовым к копированию

- `PROJECT.md` не содержит `<placeholder>`;
- в `spec/` лежат только релевантные актуальные документы;
- `context/GENERATED_SNAPSHOT_MANIFEST.json` совпадает с exact generated `context/`/`spec/`, а tracked stale files отсутствуют;
- лицензия компонента выбрана по `13_licensing_and_contributions.md`;
- системный пользователь и privilege boundary названы явно;
- определены SemVer компонента и версия protocol/schema;
- следующий шаг в `STATUS.md` можно проверить командой или конкретным артефактом.
- в `docs/README.md` зафиксирован профиль будущих component docs; пустые user-guide pages не созданы.
