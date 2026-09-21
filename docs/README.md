# Component documentation

Этот каталог принадлежит конкретному субпроекту и является source для `silesco-docs`.

При начале реализации замени эту инструкцию содержательными pages, которые описывают реальную surface компонента:

- `overview.md` — роль, границы и связи;
- `configuration.md` — public configuration, defaults, validation и examples;
- `operations.md` — install/update/backup/recovery и degraded modes;
- `security.md` — identity, privileges, secrets, threat assumptions и audit;
- `troubleshooting.md` — symptoms, safe diagnostics, repair и escalation;
- `reference/` — только generated API/CLI/schema reference.

Не создавай пустые pages для неприменимых разделов. Изменение API, CLI, schema, configuration, paths, privileges или operational behavior обновляет docs в том же change set. Полные правила находятся в `spec/19_documentation.md`.
