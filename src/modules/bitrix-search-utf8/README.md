# Bitrix Search: UTF-8 numeric HTML entities fix

## Назначение

Локальный патч для устаревшего модуля поиска 1С-Битрикс (наблюдалось на версии 17.5.8) после миграции сайта на UTF-8 и MySQL 8.4. Предотвращает ошибку MySQL 1366 (`Incorrect string value`) при сохранении товаров с числовыми HTML-сущностями, например `&#216;` (символ `Ø`).

**Статус на `discount-tools.ru`:** исправление применено на production и проверено сохранением карточки товара через административную панель 02.11.2026. Временная диагностика в `iblock/classes/mysql/iblockelement.php` удалена, файл восстановлен по оригиналу.

## Причина

Файл ядра: `bitrix/modules/search/classes/general/search.php`.

При индексации `CSearch::Index()` вызывает `preg_replace_callback('/&#(\d+);/', array('CSearch', 'chr'), $content)` в ветках добавления и обновления записи. Исходная реализация `CSearch::chr()`:

```php
function chr($a)
{
    return chr($a[1]);
}
```

`chr(216)` создаёт единственный байт `D8`, недопустимый как самостоятельный байт UTF-8. При записи `SEARCHABLE_CONTENT` MySQL возвращает ошибку 1366.

Патч заменяет эту операцию на UTF-8-декодирование полной сущности:

```php
function chr($a)
{
    return html_entity_decode($a[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
```

`&#216;` → `Ø` (`C3 98` в UTF-8). Существующие описания товаров менять не нужно. Исправление применяется к обоим сценариям индексации через общий метод.

## Файлы в репозитории

```text
src/
└── modules/
    └── bitrix-search-utf8/
        ├── README.md
        └── search-utf8-entities.patch
```

**Важно:** в Git хранится **патч, а не копия файла ядра**. Не подменяйте весь `search.php` версией с другого сервера: ядро может отличаться.

## Установка при переносе / развёртывании нового сайта

1. Убедиться, что сайт использует UTF-8, и проверить версию файла ядра; при существенном обновлении Битрикс предварительно проверить, сохранилась ли проблема.
2. Сделать резервную копию целевого файла `bitrix/modules/search/classes/general/search.php` и зафиксировать состояние файлов перед изменением.
3. На новом сервере перейти в **корень сайта**, содержащий папку `bitrix` (пример production: `/var/www/discount-tools/public`).
4. Выполнить проверку применимости патча (`--dry-run`):

```bash
cd /var/www/discount-tools/public
sudo patch --dry-run -p1 < /path/to/repository/src/modules/bitrix-search-utf8/search-utf8-entities.patch
```

5. Только если сухой прогон успешен, применить:

```bash
cd /var/www/discount-tools/public
sudo patch -p1 < /path/to/repository/src/modules/bitrix-search-utf8/search-utf8-entities.patch
sudo /opt/remi/php74/root/usr/bin/php -l bitrix/modules/search/classes/general/search.php
```

Путь `/opt/remi/php74/root/usr/bin/php` соответствует текущему production. На другом хостинге использовать фактический PHP CLI.

**Если патч уже применён:** повторно не запускать `patch`; проверить функцию `CSearch::chr()` вручную. При несовпадении исходников не использовать принудительное применение (`-f`) без анализа. PHP OPcache/процессы могут потребовать планового обновления кеша или перезапуска PHP-FPM после изменения кода.

## Функциональная проверка

Проверка после включения Битрикс (только чтение; данные сайта не меняет):

```bash
sudo -u discount /opt/remi/php74/root/usr/bin/php <<'PHP'
<?php
$_SERVER['DOCUMENT_ROOT'] = '/var/www/discount-tools/public';
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';
CModule::IncludeModule('search');
$s = preg_replace_callback('/&#(\d+);/', array('CSearch', 'chr'), 'Трубы &#216; 2" – 12"');
echo 'UTF8: '.(mb_check_encoding($s, 'UTF-8') ? 'OK' : 'INVALID')."\n";
echo 'HEX: '.bin2hex($s)."\n";
echo 'TEXT: '.$s."\n";
PHP
```

Ожидается `UTF8: OK`, в HEX — `c398` вместо `d8`, в тексте — `Трубы Ø 2" – 12"`. Затем проверить сохранение через административную панель тестовой карточки с `&#216;` в HTML-описании.

## Откат

Восстановить резервную копию `search.php` от **этого же сервера и этой же версии ядра**, проверить синтаксис PHP и повторить функциональные тесты. Откат возвращает исходный дефект, поэтому выполнять его только при необходимости.

## Сопутствующие замечания

- Патч изменяет лишь преобразование числовых HTML-сущностей внутри поискового модуля, не структуру БД и не кодировку таблиц.
- Обновление ядра Битрикс может перезаписать исправление; после обновления проверить `CSearch::chr()` и при необходимости адаптировать патч.
- Это отдельное исправление от ранее выполненной совместимости SQL экспорта заказов (`bitrix/modules/sale/general/export.php`) с MySQL 8.4.
