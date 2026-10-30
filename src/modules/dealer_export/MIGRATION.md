# Перенос Dealer Export на другой сервер / домен

## 1. Резервные копии и аудит

Перед заменой действующих файлов скопировать (не публиковать в Git):

- `/var/www/discount-tools/public/local/tools/dealer-export/dealer_export.py`
- `/var/www/discount-tools/public/local/tools/dealer-export/dealer_export.json`
- `/etc/cron.d/discount-tools-cost-update`
- `/usr/local/bin/discount-tools-sync-and-export.sh`
- `/var/www/discount-tools/public/bitrix/php_interface/init.php`
- `/etc/nginx/conf.d/discount-tools.conf`

Версия `bin/dealer_export.py` сверена с production 30.10.2026 (см. `SOURCE_VERIFICATION.md`). **Перед будущим деплоем** повторно сравните хеши, так как серверный код может измениться:

```bash
sha256sum /var/www/discount-tools/public/local/tools/dealer-export/dealer_export.py
```

На Mac в корне Git:

```bash
shasum -a 256 src/modules/dealer_export/bin/dealer_export.py
```

Если хеши различаются — скачать актуальный рабочий файл с сервера, проверить diff и обновить Git до изменения production.

## 2. Системные зависимости

- Nginx, PHP, MySQL/MariaDB client, `python3`, `flock`, `timeout`, Bitrix.
- На текущем сервере: сайт `/var/www/discount-tools/public`, пользователь `discount`, PHP-FPM 7.4.
- Python-генератор использует стандартную библиотеку Python и внешние CLI `php`, `mysql`/`mariadb`.
- Для cron проверить `crond` и наличие `python3`, `php`, `mysql` в `PATH` пользователя `discount`.

## 3. Дерево развёртывания

```text
SITE_ROOT/
├── bitrix/admin/discount_dealer_xml.php             # роутер
├── bitrix/php_interface/init.php                    # существующий, подключить меню
├── dealer-export/                                  # публичные XML, не PHP
└── local/
    ├── admin/discount_dealer_xml.php                # интерфейс
    ├── php_interface/dealer_xml_admin_menu.php      # меню
    ├── tools/dealer-export/
    │   ├── dealer_export.py
    │   ├── dealer_export.json                         # создать из примера, исключить из Git
    │   ├── check_dealer_export_data.py               # необязательная диагностика
    │   └── dealer_xml_manual_worker.sh
    └── var/log/dealer_export.log                    # создаётся генератором

/etc/cron.d/discount-tools-dealer-xml-manual           # cron ручной очереди
/var/lib/discount-tools/dealer-xml/                  # очередь, статус, лог
```

## 4. Установка на чистый сервер

```bash
SITE=/var/www/discount-tools/public
REPO=/path/to/kanuk.su/src/modules/dealer_export

sudo install -d -m 755 "$SITE/local/tools/dealer-export" "$SITE/local/admin" "$SITE/local/php_interface" "$SITE/bitrix/admin" "$SITE/dealer-export" "$SITE/local/var/log"
sudo install -o root -g root -m 755 "$REPO/bin/dealer_export.py" "$SITE/local/tools/dealer-export/dealer_export.py"
sudo install -o root -g root -m 755 "$REPO/bin/dealer_xml_manual_worker.sh" "$SITE/local/tools/dealer-export/dealer_xml_manual_worker.sh"
sudo install -o root -g root -m 644 "$REPO/admin/discount_dealer_xml.php" "$SITE/local/admin/discount_dealer_xml.php"
sudo install -o root -g root -m 644 "$REPO/admin/discount_dealer_xml_router.php" "$SITE/bitrix/admin/discount_dealer_xml.php"
sudo install -o root -g root -m 644 "$REPO/admin/dealer_xml_admin_menu.php" "$SITE/local/php_interface/dealer_xml_admin_menu.php"
sudo install -o discount -g discount -m 644 "$REPO/examples/dealer_export.json.example" "$SITE/local/tools/dealer-export/dealer_export.json"
sudo install -d -o discount -g discount -m 750 /var/lib/discount-tools/dealer-xml
sudo install -o root -g root -m 644 "$REPO/cron/discount-tools-dealer-xml-manual" /etc/cron.d/discount-tools-dealer-xml-manual
```

На **работающем production** не выполнять эти команды слепо: они перезапишут конфигурацию экспортёра и его код. Для текущего сервера PHP и меню уже установлены, нужен только контроль версий в Git.

## 5. Настройки при смене хостинга/домена

Создать `dealer_export.json` из `examples/dealer_export.json.example`, проверить:

| Параметр | Что изменить |
|---|---|
| `site_root` | Абсолютный путь DOCUMENT_ROOT |
| `base_url` | Публичный HTTPS-домен |
| `output_dir` | Папка, куда писать `brexit.xml` и `esson.xml` |
| `log_file` | Путь к доступному для записи журналу |
| `lock_file` | Путь к файлу блокировки экспортёра |
| `expected_database` | Реальное имя базы |
| `product_iblock_id` | ID инфоблока товаров (сейчас 16) |
| `manufacturer_property_id` | ID свойства бренда (52) |
| `article_property_id` | ID артикула (51) |
| `more_photo_property_id` | ID дополнительных фото (50) |
| `price_group_id` | Тип цены (1) |
| `brands.*.manufacturer_id` | Элементы брендов BREXIT 267, ESSON 3688 |

Смену кодировки MySQL/таблиц проверять отдельно (legacy CP1251 vs UTF-8); не выполнять массовую перекодировку без анализа текущей базы. Пароли БД считываются генератором из конфигурации Битрикс.

Пути в `bin/dealer_xml_manual_worker.sh` фиксированы под текущий production — при смене корня сайта исправить `SITE`, `STATE_DIR`, `SCRIPT`, `CONFIG`. В `admin/discount_dealer_xml.php` при необходимости изменить `$stateDir`; в `cron/discount-tools-dealer-xml-manual` изменить путь к worker и пользователя сайта. Ссылки в PHP сейчас содержат домен `discount-tools.ru`: при миграции также заменить его.

## 6. Меню админки

Подключение **в действующий** `/bitrix/php_interface/init.php`:

```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/dealer_xml_admin_menu.php';
```

Не создавать второй `init.php` и не удалять уже существующие обработчики, особенно `dt.catalogproperties`. Обработчик `OnBuildGlobalMenu` не создаёт отдельную группу «Продукция», использует `items_id` уже существующих пунктов.

## 7. Публичный доступ к XML (Nginx)

На production ранее присутствовало правило `location ^~ /dealer-export/ { return 404; }`. Поэтому файлы на диске могли быть видны в админке, но давать 404 по URL. В HTTPS server для магазина нужно разместить **перед общим запретом** точные разрешения:

```nginx
location = /dealer-export/brexit.xml {
    default_type application/xml;
    try_files $uri =404;
}
location = /dealer-export/esson.xml {
    default_type application/xml;
    try_files $uri =404;
}
location ^~ /dealer-export/ { return 404; }
```

Не открывайте весь `/dealer-export/` и не разрешайте исполняемые скрипты из публичного каталога. Проверка:

```bash
sudo nginx -t && sudo systemctl reload nginx
curl -sS -o /dev/null -w '%{http_code} %{content_type}\n' https://discount-tools.ru/dealer-export/brexit.xml
curl -sS -o /dev/null -w '%{http_code} %{content_type}\n' https://discount-tools.ru/dealer-export/esson.xml
```

## 8. Проверки после миграции

```bash
SITE=/var/www/discount-tools/public
sudo /opt/remi/php74/root/usr/bin/php -l "$SITE/local/admin/discount_dealer_xml.php"
sudo /opt/remi/php74/root/usr/bin/php -l "$SITE/local/php_interface/dealer_xml_admin_menu.php"
sudo /opt/remi/php74/root/usr/bin/php -l "$SITE/bitrix/admin/discount_dealer_xml.php"
sudo bash -n "$SITE/local/tools/dealer-export/dealer_xml_manual_worker.sh"
sudo -u discount /usr/bin/python3 "$SITE/local/tools/dealer-export/dealer_export.py" --config "$SITE/local/tools/dealer-export/dealer_export.json" --no-publish
```

Проверить существующее почасовое расписание `discount-tools-cost-update`, которое запускает обновление остатков/цен и затем экспортёр. Не запускать cron вручную для теста страницы. Проверить, что новая кнопка ставит запрос в очередь, и что `discount-tools-dealer-xml-manual` обрабатывает его через одну минуту. При отказе проверять `/var/lib/discount-tools/dealer-xml/manual.log` и `manual.status`. Не удалять `manual.processing` до завершения процесса.

## 9. Откат

- Снять новое задание `/etc/cron.d/discount-tools-dealer-xml-manual`.
- Убрать только одну строку подключения меню из `init.php`.
- Восстановить резервные копии обновлённых PHP/Bash/Python, если они были заменены.
- Не удалять XML и существующий cron почасового обновления.
- Вернуть конфигурацию Nginx из резервной копии, если ошибка связана с доступом к XML.
