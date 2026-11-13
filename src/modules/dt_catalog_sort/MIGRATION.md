# MIGRATION — dt_catalog_sort V4.4: Git → новый сервер / production

## Статус и ограничения

**НЕ запускать V4.4 из этого ZIP с `--apply` на `discount-tools.ru`.** В `bin/dt_catalog_sort.php`, `bin/dt_catalog_sort_auto.php`, `lib/DtCatalogSortCache.php` и `deploy/init.snippet.php` зашиты тестовые защитные условия `discount-tools-test` / `vmtest.discount-tools.ru`; простой поиск и замена строк **не является проверенной миграцией**. В первую очередь нужны диагностика реальной production-среды и отдельный production-профиль с ограничениями по хосту, docroot, домену кеша и доступным действиям.

Цель этого релиза: положить в Git **все проверенные на тесте исходники** и определить конкретный, обратимый порядок переноса. Прямого изменения production в данном релизе нет.

## Соответствие путей репозитория и сайта

| Файл в Git | Расположение на сервере |
|---|---|
| `bin/dt_catalog_sort_auto.php` | `<DOCROOT>/../tools/dt_catalog_sort_auto.php` (на 247: `/var/www/discount-tools/tools/`) |
| `bin/dt_catalog_sort.php` | `<DOCROOT>/../tools/dt_catalog_sort.php` |
| `lib/DtCatalogSortAuto.php` | `<DOCROOT>/local/php_interface/dt_catalog_sort/DtCatalogSortAuto.php` |
| `lib/DtCatalogSortCache.php` | `<DOCROOT>/local/php_interface/dt_catalog_sort/DtCatalogSortCache.php` |
| `lib/hooks.php` | `<DOCROOT>/local/php_interface/dt_catalog_sort/hooks.php` |
| `deploy/init.snippet.php` | только **фрагмент**, вставляется в существующий `<DOCROOT>/bitrix/php_interface/init.php` после проверки регистрации |
| `section-*.php.inc` | эталонные фрагменты; точечная интеграция в `<DOCROOT>/local/templates/elektro_flat/components/bitrix/catalog/.default/section.php` |

Внимание: пути CLI на production, `DOCROOT`, расположение `init.php` и расположение композитного кеша **проверить, не предполагать**. На тесте `init.php` подключается через `/bitrix/php_interface/init.php`, а не `/local/php_interface/init.php`.

## Этап A. Только диагностика production, без изменений

Выполняет администратор по SSH. Никаких редактирований, очистки кеша и записи в БД:

```bash
hostname -f
id
/opt/remi/php74/root/usr/bin/php -v | head -n 2
ls -ld /var/www/discount-tools/public /var/www/discount-tools/tools
find /var/www/discount-tools/public/bitrix/html_pages -mindepth 1 -maxdepth 1 -type d -printf '%f\n'
find /var/www/discount-tools/public/bitrix/html_pages/discount-tools.ru/catalog -type f -name 'index@*.html' | head -n 15
stat -c '%U:%G %a %n' /var/www/discount-tools/public/bitrix/html_pages/discount-tools.ru/catalog
ls -l /var/www/discount-tools/public/bitrix/php_interface/init.php
ls -l /var/www/discount-tools/public/local/templates/elektro_flat/components/bitrix/catalog/.default/section.php
sudo crontab -l -u discount 2>/dev/null || true
crontab -l 2>/dev/null || true
```

Если владельцем кеша является не `discount`, определить реального владельца и cron-пользователя. Не менять массово права (`chmod -R`, `chown -R` запрещены). Сведения о БД не публиковать с паролями. Также необходимо **read-only** подтвердить на production инфоблок 16, состав свойств `CATALOG_SORT_PRIORITY`, `DT_SORT_GROUP`, `DT_SORT_PRICE`, `DT_SORT_ZERO`, наполнение индекса и наличие таблиц `dt_catalog_sort_queue`/`dt_catalog_sort_state`. SQL-примеры в [docs/PRODUCTION-PREFLIGHT.md](docs/PRODUCTION-PREFLIGHT.md).

## Этап B. Подготовка безопасного production-релиза (отдельно от V4.4-test)

1. Сохранить V4.4-test побайтно и подготовить **отдельную production-сборку**, которую можно сравнить diff-ом. Нужна явная allowlist: точные hostname, документный корень и разрешённый каталог композитного кеша; проверка `realpath()` перед удалением и тест запрета удаления за пределами каталога.
2. Заменить тестовые ограничения в CLI на сознательно выбранные production-флаги/подтверждение, в том числе для команды создания свойств и массового заполнения. **Не включать опасный глобальный `--sweep` по умолчанию**.
3. Проверить, что существующий сортировочный `section.php` соответствует проверенной V3-логике. На production могут быть другие изменения шаблона: переносить diff, **не целый файл**.
4. Определить владельца и права каталогов кеша; запустить worker от владельца кеша/подтверждённой учётной записи. Проверить доступ к БД, PHP CLI, папке логов, lock-файлу и созданию новых подкаталогов.
5. При необходимости адаптировать подключение обработчиков событий в `bitrix/php_interface/init.php`, избегать повторной регистрации.
6. Не считать модуль `cost_update` уже интегрированным только по наличию в Git. У него прямые SQL-записи, поэтому отдельно сверить и применить **только точечный** патч V4.3 после проверки таблицы очереди. Инструкция в разделе D.

## Этап C. Резервная копия и контролируемое внедрение (после этапов A/B)

1. Сделать резервную копию production БД штатным механизмом; отдельно сохранить DDL и содержимое существующих сортировочных таблиц, если они есть; не очищать таблицы без понимания их статуса.
2. Сохранить с владельцами и правами `section.php`, `bitrix/php_interface/init.php`, существующие `dt_catalog_sort*.php`, `DtCatalogSort*.php`, `hooks.php` и при изменении интеграции `cost_update/lib/common.php`, `config.php` в папку **вне webroot**. Зафиксировать хеши и получить `diff`.
3. Если на production уже работает сортировка, остановить **только её** cron (через `EDITOR=vi crontab -e` или `sudo env EDITOR=vi crontab -u <worker> -e`), оставив прочие задачи без изменений. Сохранить обе таблицы cron до правки.
4. Новые/изменяемые исходники сайта переносить и править через **VSCode**; конфигурацию сервера в SSH — только `vi`/`vim`. Не подменять целиком чужой `init.php` или модуль `cost_update`.
5. PHP lint каждого изменяемого файла с `-d short_open_tag=On`; затем **read-only** проверка конфигурации, статуса, выборочного товара и `--cache-preview`, прежде чем выполнять запись.
6. После подтверждения тестировать сначала 1 товар и 1-2 раздела: порядок, сортировка по цене ASC/DESC, без цены, не в наличии, пагинация 24/48, кешированные и инкогнито страницы.
7. Включать cron только после проверки целевого пользователя, логов, блокировки и записи в каталог кеша. При ошибке `cache_deleted`/`failed` — остановить cron и диагностировать, не стирать очередь.
8. Мониторить ошибки, рост `queued`, возраст старейшей задачи, число изменённых товаров и реальные cache-hit страницы. После этого передать production на эксплуатацию.

## Этап D. Связь с существующим cost_update (НЕ заменять весь модуль)

`cost_update` использует PDO SQL для изменения `b_catalog_price` / `b_catalog_product` и **не порождает** события Битрикс. Поэтому, без дополнительной интеграции, сортировка после такого изменения не обновится.

В Git-архиве `kanuk.su-master.zip` состояние `src/modules/cost_update/lib/common.php` **старее**, чем проверенная на 247 интеграция V4.3. В этом комплекте есть **отдельный**, ограниченный patch: `integration/cost_update_v4_3_git.patch`. Он добавляет постановку ID в `dt_catalog_sort_queue` в ту же транзакцию, что и изменение цены/остатка, и счётчик `sort_queued`. Модуль в этой сборке **не изменён**, патч надо применять осознанно.

В локальной копии Git-репозитория из его корня сначала:

```bash
git apply --check src/modules/dt_catalog_sort/integration/cost_update_v4_3_git.patch
```

Если проверка успешна, сделать Git commit патча в **отдельном изменении**, после ревью. Не применять Git-патч напрямую на production без сравнения фактического `common.php` с Git-версией. В `cost_update/config.php` для реального подключения **после установки очереди** требуется параметр `'sort_queue_enabled' => true,` (в безопасном контексте конфигурации); не перезаписывать весь конфиг из Git. На 247 такая связка успешно проверена. Прямую синхронизацию Google Sheets на тесте не включать.

**Риск:** в архивной Git-конфигурации `cost_update` указан старый `html_cache_domain=kanuk.brexit.by`; считать актуальным только живой production-конфиг после read-only проверки.

## Этап E. Откат

1. Остановить только cron сортировки, сохранить диагностический лог и текущий размер очереди.
2. Восстановить исходные PHP-файлы из резервных копий, владельцев, группы и права, перепроверить lint. Если `section.php` менялся — вернуть его старую версию.
3. Если была применена интеграция `cost_update`, вернуть **только** исходный `common.php` и конфигурационный параметр, не выключая остальные функции модуля.
4. Не удалять автоматически `DT_SORT_*`, `dt_catalog_sort_queue` или `dt_catalog_sort_state`: данные и очередь могут потребоваться после отката.
5. Композитный HTML-кеш восстанавливать из бэкапа не требуется; уже удалённые HTML-страницы сформируются вновь при запросах. Проверить правильность представления страниц.
6. Cron возвращать только после проверки согласованности версий; если откатились на старую сортировку без обработчика — не включать новый worker.

## Отдельные ограничения

- **Скидки с датами начала/окончания:** при `--sweep=0` автоматического пересчёта без события нет; требуется отдельное решение перед полной эксплуатацией, если важны такие скидки.
- Права `755` новых кеш-каталогов были причиной неуспеха cron от `als` на тесте; проверенный запуск — от владельца кеша `discount`.
- При изменении маршрутов или slug, симлинках/нестандартных `CODE`, многораздельной привязке, удалении карточки и ротации кеша требуется отдельная проверка.
- Нет обещания атомарности между записью в БД и генерацией кеша при конкурентных веб-запросах; контроль браузером обязателен.
