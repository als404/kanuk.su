# MIGRATION — перенос dt.catalogproperties на другой сервер и домен

## Состояние проверенной тестовой базы (24.10.2026)

На VM 247 после массового заполнения настроены **496 категорий** и **7 312 назначений** в `dt_cp_section_property`. Сохранить отдельный дамп **обеих** таблиц конфигурации перед переносом: без него одного Git-кода недостаточно. Применение конфигураций к production без сохранения ID категорий и свойств недопустимо.

Подключение фильтрации в существующем `init.php` должно учитывать **оба** редактора: `iblock_element_edit.php` и `cat_product_edit.php`; сортировка события `OnBuildGlobalMenu` — **10000**. Пример текущей ручной регистрации находится в `reference/current_init_registration.txt` и не предназначен для автоматического перезаписывания `init.php`.

## Перед переносом

1. Зафиксировать `DOCUMENT_ROOT`, версию PHP (проект: 7.4), Битрикс (17.5.8), БД, `SITE_ID`, активный шаблон, права файлов и домен. Использовать реальную конфигурацию целевого сайта.
2. Сделать **проверенный** backup базы и файлов, включая `dt_cp_section_config`, `dt_cp_section_property`, текущие `init.php` и шаблоны каталога.
3. Проверить кодировку **целевой** БД, подключения и дампа, особенно при переходе на production/CP1251. `001_create_tables.sql` создаёт новые таблицы в `utf8mb3`; нельзя слепо импортировать текст в неправильной кодировке или принудительно менять charset всей production-БД.
4. Проверить, что ID каталога **16**, ID разделов и ID свойств сохранены. Если нет — создать таблицы сопоставления идентификаторов до импорта назначений; выполнить контроль целостности по `b_iblock_section`, `b_iblock_property`.

## Перенос файлов

| Из Git | На сервере |
| --- | --- |
| `src/modules/dt.catalogproperties/` | `<DOCUMENT_ROOT>/local/modules/dt.catalogproperties/` |
| `src/site/bitrix/admin/dt_catalog_properties.php` | `<DOCUMENT_ROOT>/bitrix/admin/dt_catalog_properties.php` (установщик также умеет создавать) |
| `src/site/local/templates/elektro_flat/components/bitrix/catalog/.default/element.php` | соответствующий файл активного шаблона |
| `src/site/local/templates/elektro_flat/components/bitrix/catalog/.default/bitrix/catalog.element/.default/result_modifier.php` | соответствующий файл активного шаблона |
| `src/site/local/templates/elektro_flat/components/bitrix/catalog/.default/bitrix/catalog.element/.default/template.php` | соответствующий файл активного шаблона |

**Не перезаписывать** шаблоны новой площадки целиком без `diff -u`/трёхстороннего merge; эти файлы включают помимо интеграции существующую логику каталога. Важные точки: `DT_PROPERTIES_VERSION` в `element.php`, `ElementProperties::resolve()` в `result_modifier.php`, вывод нужных полей в `template.php`.

## База и установка

- Для **новой пустой конфигурации**: выполнить `001_create_tables.sql`, затем `002_add_config_version.sql` ровно один раз (только после backup).
- При переносе полной БД с существующими `dt_cp_*` **не запускать** эти миграции повторно. Перенести строки обеих таблиц без потерь. `001_rollback_tables.sql` **не выполнять** при обычном откате — он удаляет настройки.
- Установить локальный модуль через штатную страницу управления модулями Битрикс. `install/index.php` регистрирует события `OnAdminTabControlBegin` (100) и `OnBuildGlobalMenu` (10000); добавляет wrapper, если он отсутствует.
- Если сайт раньше включал модуль **вручную**, удалить только два ранее добавленных фрагмента `DT Catalog Properties` из действующего `/bitrix/php_interface/init.php` **после** проверки установки и автозагрузки. Не удалять сторонние обработчики и интеграции.
- Убедиться, что `include.php` зарегистрировал классы и события не продублированы.

## Домен и кеш

- Проверить HTTP_HOST, правила `/catalog/#SECTION_CODE#/#ELEMENT_CODE#/`, 301/302 редиректы, SSL, хосты composite кеша и `SITE_ID`.
- **Критично:** `CompositeCache.php` в текущем релизе **разрешает удаление только на `vmtest.discount-tools.ru`**. На другом домене `invalidateSection()` выбросит исключение: изменения правил сохранятся, но посетители могут получать устаревший composite HTML до обычного истечения кеша. Нельзя считать релиз production-ready по этой части.
- Перед разрешением production: проверить соответствие URL-генерации, реального домена и путей кеша, лимиты `MAX_PRODUCTS=500`, `MAX_URLS=1000`, dry-run и адресное удаление на тестовой копии нового домена. Не делать глобальную очистку без согласования.
- Проверить очистку component кеша по `CONFIG_VERSION`; в БД должен существовать столбец `CONFIG_VERSION`.

## SQL-проверки (подставить фактическое имя базы)

```sql
SHOW TABLES LIKE 'dt_cp_%';
SHOW COLUMNS FROM dt_cp_section_config LIKE 'CONFIG_VERSION';
SELECT COUNT(*) AS configured_sections FROM dt_cp_section_config;
SELECT COUNT(*) AS assigned_properties FROM dt_cp_section_property;
SELECT COUNT(*) AS invalid_sections FROM dt_cp_section_property p
  LEFT JOIN b_iblock_section s ON s.ID=p.SECTION_ID AND s.IBLOCK_ID=16
  WHERE s.ID IS NULL;
SELECT COUNT(*) AS invalid_properties FROM dt_cp_section_property p
  LEFT JOIN b_iblock_property b ON b.ID=p.PROPERTY_ID AND b.IBLOCK_ID=16
  WHERE b.ID IS NULL;
```

## Тестирование и откат

1. Проверить пункт «Магазин → Продукция → Характеристики категорий» и переход по нему.
2. Сравнить несколько настроенных/ненастроенных категорий; сохранить порядок и проверить версию конфигурации.
3. Проверить существующий и новый товар, смену категории с повторным открытием, сохранение значений (включая пустые, множественные и файловые при наличии).
4. Проверить публичную карточку, кеш и поведение при превышении лимитов.
5. При откате сначала отключить модуль через штатную страницу, восстановить шаблоны и старую регистрацию `init.php` **только если она действительно нужна**, сохранить `dt_cp_*` для возможного повторного запуска. При деинсталляции таблицы и wrapper автоматически не удаляются.

Не переносить из теста фиктивные товары (`test1234`) и тестовые audit-таблицы (`dt_cp_audit_element_2292_before`) в production.

## Финальная проверенная точка перед переносом (24.10.2026)

- VM 247: 496 категорий, 7 312 назначений. Перед копированием на production выполнить **новый** дамп обеих `dt_cp_*` таблиц; файл `/home/als/dt_cp_before_autofill_all.sql` создан **до** массового заполнения и не содержит итоговые 417 новых конфигураций.
- Успешно проверены два административных маршрута: `iblock_element_edit.php` и `cat_product_edit.php`. При ручной регистрации в `bitrix/php_interface/init.php` оба имени должны быть в условии `in_array(..., true)`.
- Для пункта «Магазин → Продукция → Характеристики категорий» необходим `OnBuildGlobalMenu` с сортировкой `10000`: меньший приоритет в тестовой конфигурации не давал обнаружить пункт `menu_catalog_attributes_16` в массиве меню.
- Массовое заполнение через SQL не вызывает обработчик `SectionRules` и адресную инвалидацию composite-кеша. После переноса проверить реальную публичную страницу и версии кеша, не выполнять глобальную очистку без оценки воздействия.
- В Git хранятся только исходники и SQL-схема, **не** финальный дамп данных, а также не сведения для доступа к БД.

### Снять новый финальный дамп с VM 247

```bash
sudo mysqldump --single-transaction kanuk_utf8 \
  dt_cp_section_config dt_cp_section_property \
  > /home/als/dt_cp_final_config_20261024.sql
chmod 600 /home/als/dt_cp_final_config_20261024.sql
sudo mysql kanuk_utf8 -e "SELECT COUNT(*) FROM dt_cp_section_config; SELECT COUNT(*) FROM dt_cp_section_property;"
```

Ожидаемые контрольные значения для снимка от 24.10.2026: `496` и `7312`. При последующих ручных изменениях фактические значения могут отличаться. Дамп хранить вне docroot и вне Git.
