# Dealer Export — BREXIT / ESSON (полный Git-пакет)

**Проект:** discount-tools.ru · **Версия пакета:** 1.3-production-verified · **Инфоблок товаров:** 16.

Пакет объединяет **Python-генератор дилерского XML** из исходного архива `dealer_export.zip` и **административную страницу Битрикс** из `dealer-xml-admin-v1.1.zip`. Меню «Магазин → Продукция → XML для дилеров» уже настроено на production.

> **Проверено 30.10.2026:** `bin/dealer_export.py` заменён точной копией действующего production-файла, полученного с `/var/www/discount-tools/public/local/tools/dealer-export/dealer_export.py`. С исходной Git-версией отличается только одной конечной пустой строкой. Подробности в [SOURCE_VERIFICATION.md](SOURCE_VERIFICATION.md). Весь Python-код генератора сохранён без функциональных изменений.

## Дерево Git

```text
kanuk.su/
└── src/
    └── modules/
        └── dealer_export/
            ├── bin/
            │   ├── dealer_export.py                 # генератор BREXIT / ESSON
            │   ├── check_dealer_export_data.py      # диагностика структуры БД
            │   └── dealer_xml_manual_worker.sh      # обработчик запросов из админки
            ├── admin/
            │   ├── discount_dealer_xml.php          # интерфейс администрирования
            │   ├── discount_dealer_xml_router.php   # входной роутер /bitrix/admin/
            │   └── dealer_xml_admin_menu.php        # вставка в штатную «Продукцию»
            ├── cron/
            │   └── discount-tools-dealer-xml-manual # cron ручной очереди
            ├── examples/
            │   └── dealer_export.json.example       # настройки без паролей
            ├── legacy/
            │   └── index.php                       # историческая страница, НЕ устанавливать
            ├── .gitignore
            ├── README.md
            ├── MIGRATION.md
            └── SOURCE_VERIFICATION.md
```

## Расположение на production

| Файл в Git | Путь на сервере |
|---|---|
| `bin/dealer_export.py` | `/var/www/discount-tools/public/local/tools/dealer-export/dealer_export.py` |
| `bin/check_dealer_export_data.py` | `/var/www/discount-tools/public/local/tools/dealer-export/check_dealer_export_data.py` (только для диагностики) |
| `examples/dealer_export.json.example` | `/var/www/discount-tools/public/local/tools/dealer-export/dealer_export.json` (**после настройки**, не перезаписывать существующий) |
| `bin/dealer_xml_manual_worker.sh` | `/var/www/discount-tools/public/local/tools/dealer-export/dealer_xml_manual_worker.sh` |
| `admin/discount_dealer_xml.php` | `/var/www/discount-tools/public/local/admin/discount_dealer_xml.php` |
| `admin/discount_dealer_xml_router.php` | `/var/www/discount-tools/public/bitrix/admin/discount_dealer_xml.php` |
| `admin/dealer_xml_admin_menu.php` | `/var/www/discount-tools/public/local/php_interface/dealer_xml_admin_menu.php` |
| `cron/discount-tools-dealer-xml-manual` | `/etc/cron.d/discount-tools-dealer-xml-manual` |

Рабочие XML-файлы создаются в `/var/www/discount-tools/public/dealer-export/`, публичные ссылки:

- `https://discount-tools.ru/dealer-export/brexit.xml`
- `https://discount-tools.ru/dealer-export/esson.xml`

## Как работает

1. Основной `dealer_export.py` читает параметры БД из файлов конфигурации Битрикс, без хранения паролей в JSON. Генерирует два XML для производителей BREXIT (ID 267) и ESSON (ID 3688).
2. Существующее **почасовое задание** `/usr/local/bin/discount-tools-sync-and-export.sh` сначала обновляет цены/остатки, затем вызывает генератор. **Скрипт почасовой обвязки в этот пакет не входит**, так как его рабочая версия на сервере не была предоставлена. Сохранять отдельно в Git после выгрузки с production.
3. PHP-страница доступна администраторам Битрикс, показывает URL, размер, дату файлов, и ставит запрос на ручную генерацию в очередь.
4. Ручную очередь обрабатывает `dealer_xml_manual_worker.sh` под пользователем `discount` каждую минуту. Очередь/статус/лог хранятся вне DOCUMENT_ROOT: `/var/lib/discount-tools/dealer-xml`.
5. Обработчик меню `OnBuildGlobalMenu` вставляет пункт в существующую «Продукцию» после «Характеристик категорий» по `items_id`, **без создания дубля**.

## Привязка меню Битрикс

В действующем `/var/www/discount-tools/public/bitrix/php_interface/init.php` должна находиться одна строка внутри PHP-кода:

```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/dealer_xml_admin_menu.php';
```

**Не перезаписывайте** `init.php` из Git: там уже есть другие рабочие обработчики (включая меню характеристик категорий).

## Первоначальная настройка и проверка

Подробная последовательность создания каталога, переноса файлов, настройки параметров, синтаксических проверок, cron, Nginx и отката дана в [MIGRATION.md](MIGRATION.md).

Для проверки самого генератора, **без публикации**, после настройки JSON на сервере:

```bash
sudo -u discount /usr/bin/python3 \
  /var/www/discount-tools/public/local/tools/dealer-export/dealer_export.py \
  --config /var/www/discount-tools/public/local/tools/dealer-export/dealer_export.json \
  --no-publish
```

Не запускайте повторно почасовую синхронизацию цен/остатков ради проверки интерфейса. Для теста ручной очереди используйте кнопку в админке только после установки cron.

## Безопасность и Git

- Рабочий `dealer_export.json` с серверными путями в Git не добавлять; хранить только `*.example`.
- Не добавлять `/bitrix/.settings.php`, `/bitrix/php_interface/dbconn.php`, FTP/SMTP-пароли, дампы БД, XML-файлы и логи.
- Роутер `/bitrix/admin/discount_dealer_xml.php` — один из немногих новых файлов в дереве `/bitrix`, он не изменяет ядро.
- Старый `legacy/index.php` использовал непосредственный запуск PHP → shell и жёсткие пути тестового сервера; **не устанавливать** его при новом развёртывании.
- Файл `bin/dealer_export.py` теперь побайтово совпадает с полученной копией production на 30.10.2026. Если впоследствии он изменится на сервере, снова сверить хеши перед развёртыванием.
