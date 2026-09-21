# Silesco.io — Модуль 13. Лицензирование, contributions и бренд

**Статус:** нормативный baseline; юридическая проверка требуется до публичного релиза
**Дата:** 2026-07-11

---

## 1. Цель лицензирования

Silesco публикует исходный код для аудита, самостоятельной установки и модификации пользователем, но защищает всю будущую продуктовую линейку Silesco.io от конкурирующих hosted/managed продуктов, перепродажи штатной автоматической установки и неофициальных сборок, выдаваемых за официальные.

Основные runtime/UI/installer-компоненты используют неизменённый текст PolyForm Shield 1.0.0. Проект описывается как `source-available`, а не OSI Open Source. До публикации каждый репозиторий обязан содержать полный `LICENSE`, Required Notice и SPDX-compatible machine metadata, где она применима.

Лицензия не заменяет техническую provenance-защиту: официальный installer, TUF/Cosign, digest, identity Master и статус модифицированной сборки должны проверяться машинно. Честная независимая консультация по инфраструктуре пользователя не должна смешиваться с выдачей неофициальной сборки или партнёрства за Silesco.io.

## 2. Карта лицензий

| Область | Базовое решение |
|---|---|
| Runtime, UI, installer, first-party agents/helpers | PolyForm Shield 1.0.0 |
| `silesco-home` и `store.silesco.io` application code | Закрытый proprietary hosted source |
| KMS.Silesco.io application code | Закрытый proprietary source |
| license.silesco.io application code | Закрытый proprietary hosted source |
| OpenBao как engine KMS | MPL-2.0 с соблюдением notices/source obligations для изменённых OpenBao files |
| SAM schema, protocol schemas, public SDK и примеры интеграции | Apache-2.0 |
| Официальный Store catalog/metadata, опубликованные SAM и verified badge | Отдельные условия Silesco; application code закрыт, badge не передаётся вместе со schema |
| Архитектурная и пользовательская документация | CC BY 4.0, кроме явно исключённых brand assets |
| Yii3 application/import tooling `silesco-docs` | Apache-2.0; собираемые docs/reference сохраняют свои notices и лицензии |
| Название, кибер-сова, logo/wordmark, verified marks | Отдельная brand policy; не передаются программными лицензиями |
| Third-party code/assets | Исходная лицензия; полный `THIRD_PARTY_NOTICES` и SBOM |

Точная раскладка по репозиториям фиксируется до создания публичных репозиториев. Нельзя помещать Apache-схемы или brand assets под Shield только из-за общего monorepo без явных `LICENSES/` и file notices.

`silesco-home`, Store, KMS и License размещаются в отдельных private repositories. Обычное участие в публичной/source-available части организации не предоставляет к ним доступ; hosted-доступ выдаётся отдельной минимальной team. Contributors не получают organization Owner или all-repository роли только ради работы над отдельным runtime-репозиторием.

`silesco-docs` является отдельным публичным репозиторием. Его публичность не даёт доступ к private hosted source: для закрытых сервисов в него передаётся только reviewed public documentation.

Переводы сохраняют лицензию соответствующего исходного текста/catalog. Импорт из внешнего translation service не отменяет CLA/атрибуцию contributor-а и не считается передачей прав сервису; машинные suggestions должны проходить human review до включения в Git.

## 3. KMS.Silesco.io

HashiCorp Vault под BSL не используется как engine коммерческого KMS.Silesco.io: платный hosted Transit/encryption service для третьих лиц несёт риск квалификации как competitive offering HashiCorp. Запрос отдельной лицензии возможен, но не является зависимостью roadmap.

Базовый engine KMS.Silesco.io — OpenBao под MPL-2.0. Код Silesco взаимодействует с ним как отдельный сервис через API. Изменения исходных файлов OpenBao публикуются на условиях MPL-2.0; отдельные файлы закрытого KMS приложения не перелицензируются автоматически. Перед реализацией выполняются compatibility/security review, pinning версии, SBOM и проверка Transit/HA/backup/upgrade.

Локальный HashiCorp Vault на пользовательской Master-ноде является отдельным internal-use сценарием и не изменяется этим решением. Возможность будущей замены локального Vault на OpenBao рассматривается отдельно и не должна происходить неявно.

## 3.1. license.silesco.io и Ultimate

Ultimate монетизирует коммерческое сопровождение независимых registrable domains на разных Agent-нодах, а не домашнее использование основной доменной зоны. Почтовый сервер, сайты, Nextcloud и иные приложения основной зоны не требуют Ultimate. Дополнительный порт существующего домена также не является отдельной платной capability.

Hosted `license.silesco.io` получает только `installation_id`, capability class, nonce и непрозрачный `operation_hash` канонического Deployment Plan. Домен, IP, команды и данные клиента не передаются. Ответом является короткоживущий одноразовый asymmetrically signed Authorization Token с `jti`/expiry; Guard сверяет подпись и точный payload hash вместе с обычным Execution Permit.

`installation_id` является локально созданным Installer UUIDv7 и восстанавливается из зашифрованного Recovery Bundle. Он служит locator установки, но не секретом и не самостоятельным доказательством владения. Beta channel allowlist намеренно использует его только как защиту от случайного выбора; alpha downloads ограничиваются owner-managed IP allowlist, а отдельный ключ остаётся опциональным усилением. Платная entitlement может автоматически дать право выбрать beta или Ultimate capability, но не может молча переключить channel либо выполнить локальную мутацию.

Hosted telemetry не является частью License, KMS, Store или основного сайта и не может использовать subscription/account data для advertising profiling. Полная boundary зафиксирована в `23_release_channels_and_telemetry.md`.

Separate Go/Rust debug symbols сохраняют лицензию/доступ соответствующего source artifact, но не публикуются автоматически только из-за доступности executable. Release/beta symbol store является закрытой operational infrastructure; optional alpha debug package распространяется тем же signed channel metadata и не считается механизмом обфускации либо дополнительной лицензией. Strip не заменяет правовую лицензию и security boundary.

Сервис не применяет dark patterns: окончание подписки не останавливает существующие сайты/почту, certificate renewal, security fixes, удаление или recovery. После grace period запрещаются лишь новые коммерчески лицензируемые мутации. Удаление доменной зоны/ноды не требует hosted approval.

CA-issued code-signing certificate и host-bound DRM не входят в baseline; release integrity обеспечивается собственными TUF/Cosign keys. Отдельный локальный закрытый Ultimate binary сейчас не создаётся. Контракт `CommercialCapabilityProof` резервируется, чтобы при доказанной потребности позднее добавить непривилегированный proprietary-компонент под отдельной лицензией. Такая лицензия и допустимые ограничения modification/reverse engineering проходят отдельную юридическую проверку в выбранной юрисдикции.

## 4. Contributions

До ввода CLA внешние code contributions не сливаются. Разрешены issues, предложения, capability reports и обсуждения без передачи кода.

CLA должен позволять contributor сохранить copyright, но предоставить владельцу Silesco бессрочную всемирную лицензию с правом:

- использовать, изменять, распространять и коммерциализировать contribution;
- сублицензировать и перелицензировать его, включая Shield, Apache и коммерческие условия;
- использовать необходимые patent claims;
- защищать проект от нарушения прав третьих лиц.

Contributor подтверждает авторство/полномочия и раскрывает third-party material. DCO может дополнять CLA, но не заменяет право перелицензирования. CLA принимается и журналируется до merge; bot/check блокирует merge без принятия.

## 5. Бренд и маскот

Название Silesco, домены, кибер-сова и будущие marks не лицензируются PolyForm/Apache/CC автоматически. Исходная raster-концепция совы создана генеративным ИИ; наёмный художник выполнил ручную векторизацию и экспорт форматов.

Договор FL.ru № БС#1824061 от 04.02.2026, раздел 9, отчуждает заказчику всё исключительное право на созданный объект в полном объёме с момента принятия работы и содержит гарантии исполнителя о правах/third-party material. В evidence archive сохраняются оба PDF, утверждённое ТЗ, proof of acceptance/completion/payment, переписка и все исходники. Для дополнительной устойчивости можно получить короткое отдельное подтверждение художника о том, что переданный SVG является результатом этой сделки и может изменяться, коммерчески использоваться, регистрироваться как обозначение и позднее передаваться юридическому лицу; это полезно, но основной договор уже содержит широкое отчуждение.

Отсутствие юридического лица не блокирует предварительную защиту: заявителем товарного знака во многих юрисдикциях может быть физическое лицо. Конкретная страна, классы, clearance search и процедура передачи будущей компании проверяются профильным специалистом. До регистрации сохраняются доказательства первого использования, официальные домены/accounts и строгая provenance релизов.

## 6. Release gate

До первого публичного source release обязательны:

- полный неизменённый `LICENSE` PolyForm Shield и Required Notice;
- `LICENSES/`, file headers и repository license map;
- `CONTRIBUTING.md` и CLA workflow;
- `THIRD_PARTY_NOTICES`, SBOM и automated license scan;
- brand/trademark policy;
- подтверждение прав на vector mascot;
- юридическая проверка формулировок о paid installation/support и юрисдикции владельца.
