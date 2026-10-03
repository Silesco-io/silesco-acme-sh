# Текущее состояние

## PHP SDK 0.2.0-alpha.1 — 2026-10-03

Architecture snapshot 1.24.0, source commit `8cbde7d6`;
[receipt](docs/architecture-1.24.0-receipt.md). Это не заявление о готовности всего runtime Silesco.

Upstream acme.sh **3.1.6**, commit `807da6498377ee5e0cf43a78091f46f12dc59a89`;
точная подпись/tag/source hashes — UPSTREAM.json. Каталог schema2: **198 драйверов,
463 поля, 218 вариантов**, 50 явных исправлений metadata. Все формы доступны в ru/en.

Реализованы immutable CertificateRequest, Executor, Operation; опциональный Linux
LocalExecutor с policy, CredentialResolver, изолированным запуском, durable replay journal
и проверкой SAN/key/validity/chain. По умолчанию пакет ничего не запускает.

Проверки и воспроизводимые команды: [stage report](docs/stage-0.2-report.md).
Ни одной реальной учётной записи провайдера или выдачи LE в этом этапе не проверялось.
Никаких изменений на VPS/get.silesco.io, production promotion или новых runtime-полномочий.

## Следующий этап

1. Включить SDK 0.2 в Yii3 Wizard и панель через существующий типизированный путь.
2. Реализовать защищённый ввод DNS credentials и формы выбранного провайдера в Wizard.
3. Проверить настоящий HTTP-01/DNS-01 на чистом VPS через LE staging.
4. Отдельно реализовать автоматическое продление и TLS activation; не выдавать staged за ready.

## История

0.1.0-alpha.1 (2026-09-21): 191 драйвер acme.sh 3.1.4, форма только REG.RU,
618 offline assertions; локального исполнителя не было. Этот scope заменён этапом 0.2,
а не является текущим ограничением каталога. Архитектурные receipts 1.23/1.24 сохранены.
