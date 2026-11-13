# Диагностика production — только чтение

Не изменяйте конфигурацию и не устанавливайте сортировку, пока не известны реальные параметры сервера. Фиксируем результат проверки:

| Проверка | Команда/способ | Что записать |
|---|---|---|
| Имя хоста | `hostname -f; hostname` | точное имя для allowlist |
| Корень сайта | `readlink -f /var/www/discount-tools/public` | канонический docroot |
| PHP CLI | `/opt/remi/php74/root/usr/bin/php -v` | версия PHP и модули |
| Владелец кеша | `stat -c '%U:%G %a %n' .../html_pages/discount-tools.ru/catalog` | пользователь cron |
| Домены HTML-кеша | `find .../bitrix/html_pages -mindepth 1 -maxdepth 1 -type d` | исключить `vmtest` |
| Существующие cron | `crontab -l`, `sudo crontab -u <owner> -l` | не дублировать |
| Подключение hooks | `grep -n 'dt_catalog_sort' .../bitrix/php_interface/init.php` | не дублировать |
| Шаблон сортировки | `grep -nE 'DT_SORT_GROUP|DT_SORT_PRICE|DT_SORT_ZERO|ELEMENT_SORT_FIELD|HIDE_NOT_AVAILABLE' .../section.php` | сравнить с V3 |
| Содержимое очереди | CLI `--status` только после адаптации `read-only` под production | `queued`, `sweep_cursor` |

### SQL только на чтение (в соответствующей базе)

```sql
SHOW TABLES LIKE 'dt_catalog_sort_queue';
SHOW TABLES LIKE 'dt_catalog_sort_state';
SELECT CODE, PROPERTY_TYPE, MULTIPLE FROM b_iblock_property
 WHERE IBLOCK_ID=16 AND CODE IN ('CATALOG_SORT_PRIORITY','DT_SORT_GROUP','DT_SORT_PRICE','DT_SORT_ZERO');
SELECT COUNT(*) AS TOTAL_ACTIVE FROM b_iblock_element
 WHERE IBLOCK_ID=16 AND ACTIVE='Y';
```

Осторожно: число активных товаров на production может отличаться от 6074 на тесте. Прямые SQL-запросы не подтверждают корректность значения всех свойств; проверяйте выборочно через Bitrix API, без записи.

### Критерии перехода к внедрению

- Установлены реальные пути, hostname, домен кеша и пользователь worker.
- Подтверждены свойства/таблицы или составлен отдельный план их создания.
- Сверены изменения `section.php`, `init.php` и живой `cost_update/lib/common.php`.
- Созданы резервные копии и документирован откат.
- Подготовлена **отдельная** production-сборка CLI и кеш-класса; тестовая ограничительная логика не просто удалена, а заменена корректной allowlist.
