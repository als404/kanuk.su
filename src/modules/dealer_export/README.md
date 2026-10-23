# Dealer Export — XML-выгрузка BREXIT / ESSON

Модуль формирует отдельные XML-файлы товаров брендов **BREXIT** и **ESSON** для дилеров.

Экспорт выполняется напрямую из MySQL без загрузки полного ядра 1С-Битрикс. Это уменьшает нагрузку на сайт при регулярном запуске.

## Структура в Git

Модуль хранится в репозитории по пути:

```text
src/
└── modules/
    └── dealer_export/
        ├── dealer_export.py
        ├── dealer_export.json
        ├── check_dealer_export_data.py
        ├── index.php
        ├── README.md
        ├── MIGRATION.md
        └── .gitignore
```

Назначение файлов:

| Файл | Назначение |
|---|---|
| `dealer_export.py` | основной Python-экспортёр |
| `dealer_export.json` | конфигурация конкретного сайта/сервера |
| `check_dealer_export_data.py` | диагностический скрипт для проверки структуры каталога |
| `index.php` | закрытая страница Битрикс для ручного запуска |
| `README.md` | установка и эксплуатация |
| `MIGRATION.md` | перенос на другой хостинг/домен |

## Что формируется

Публичные файлы:

```text
/dealer-export/brexit.xml
/dealer-export/esson.xml
```

В XML передаются артикул, название, бренд, категории, описание, цена, валюта, наличие, количество, единица измерения, изображения, характеристики и URL товара.

Товары без артикула в выгрузку не попадают.

## Текущая структура каталога

| Параметр | Значение |
|---|---:|
| Основной инфоблок | `16` |
| Инфоблок SKU | `17` |
| `ARTNUMBER` | ID `51` |
| `MANUFACTURER` | ID `52` |
| `MORE_PHOTO` | ID `50` |
| BREXIT | manufacturer ID `267` |
| ESSON | manufacturer ID `3688` |
| Цена BASE | ID `1` |
| Валюта | `RUB` |

Если структура каталога меняется, эти значения необходимо перепроверить.

# Установка с нуля на сайт

Ниже приведена рабочая раскладка файлов на сервере. Путь `/var/www/kanuk/data/www/kanuk.brexit.by` используется как пример текущего тестового сайта. На другом сервере его нужно заменить на фактический `DOCUMENT_ROOT`.

## 1. Создать каталоги

На сервере должны существовать:

```text
SITE_ROOT/
├── dealer-export/                 # публичные XML
└── local/
    ├── modules/
    │   └── dealer_export/         # PHP-страница ручного запуска
    ├── tools/
    │   └── dealer-export/         # Python и конфиг
    └── var/
        └── log/                   # журналы
```

Пример команд:

```bash
SITE=/var/www/kanuk/data/www/kanuk.brexit.by

mkdir -p "$SITE/local/tools/dealer-export"
mkdir -p "$SITE/local/modules/dealer_export"
mkdir -p "$SITE/local/var/log"
mkdir -p "$SITE/dealer-export"
```

## 2. Разложить файлы из Git по рабочим каталогам

Файлы **не копируются на сайт в той же структуре, что и в Git**. Они раскладываются так:

```text
Git:
src/modules/dealer_export/dealer_export.py
    ↓
Сайт:
local/tools/dealer-export/dealer_export.py

Git:
src/modules/dealer_export/dealer_export.json
    ↓
Сайт:
local/tools/dealer-export/dealer_export.json

Git:
src/modules/dealer_export/index.php
    ↓
Сайт:
local/modules/dealer_export/index.php
```

Диагностический файл `check_dealer_export_data.py` можно хранить только в Git или временно размещать рядом с `dealer_export.py` при диагностике.

`README.md`, `MIGRATION.md` и `.gitignore` на production-сайт копировать не требуется.

Пример ручного копирования:

```bash
SITE=/var/www/kanuk/data/www/kanuk.brexit.by
REPO=/path/to/repository

cp "$REPO/src/modules/dealer_export/dealer_export.py" \
   "$SITE/local/tools/dealer-export/dealer_export.py"

cp "$REPO/src/modules/dealer_export/dealer_export.json" \
   "$SITE/local/tools/dealer-export/dealer_export.json"

cp "$REPO/src/modules/dealer_export/index.php" \
   "$SITE/local/modules/dealer_export/index.php"
```

## 3. Настроить `dealer_export.json`

Для конкретного сервера проверить минимум:

```json
{
  "site_root": "/actual/site/root",
  "expected_database": "database_name",
  "base_url": "https://example.ru",
  "output_dir": "/actual/site/root/dealer-export",
  "log_file": "/actual/site/root/local/var/log/dealer_export.log",
  "lock_file": "/tmp/dealer-export.lock"
}
```

В конфиге из репозитория сохранены параметры текущего тестового окружения. Перед использованием на другом сервере их обязательно изменить.

Пароли БД в конфиг не добавляются. Экспортёр получает параметры подключения из конфигурации Битрикс на сервере.

## 4. Проверить пути в `index.php`

В начале `index.php` заданы фиксированные серверные пути:

```php
$python    = "/usr/bin/python3";
$script    = "/actual/site/root/local/tools/dealer-export/dealer_export.py";
$config    = "/actual/site/root/local/tools/dealer-export/dealer_export.json";
$outputDir = "/actual/site/root/dealer-export";
```

При установке на другой сервер их необходимо заменить.

Страница специально не принимает произвольные команды или пути из HTTP-запроса.

## 5. Настроить права

Определить пользователя PHP-FPM:

```bash
ps -eo user,group,cmd | grep '[p]hp-fpm'
```

Рекомендуемая схема:

```text
dealer_export.py      750
dealer_export.json    640
index.php             644
dealer-export/        755
```

Cron желательно запускать от того же пользователя сайта, который имеет права создавать XML и писать журнал.

Не использовать `chmod 777` и не запускать `sudo` из PHP.

## 6. Проверить Python и системные команды

```bash
python3 --version
which python3
which php
which mysql || which mariadb
```

Экспортёр использует стандартную библиотеку Python. Дополнительные Python-пакеты не требуются.

Для чтения параметров Битрикс и выполнения SQL необходимы доступные CLI-команды PHP и MySQL/MariaDB.

## 7. Первый проверочный запуск

Сначала запуск без публикации:

```bash
/usr/bin/python3 \
  /var/www/kanuk/data/www/kanuk.brexit.by/local/tools/dealer-export/dealer_export.py \
  --config /var/www/kanuk/data/www/kanuk.brexit.by/local/tools/dealer-export/dealer_export.json \
  --no-publish
```

Проверить лог:

```bash
tail -50 /var/www/kanuk/data/www/kanuk.brexit.by/local/var/log/dealer_export.log
```

После успешной проверки выполнить публикацию:

```bash
/usr/bin/python3 \
  /var/www/kanuk/data/www/kanuk.brexit.by/local/tools/dealer-export/dealer_export.py \
  --config /var/www/kanuk/data/www/kanuk.brexit.by/local/tools/dealer-export/dealer_export.json
```

## 8. Добавить ссылку в закрытый раздел Битрикс

На закрытой странице для сотрудников/администратора добавить переход:

```html
<a href="/local/modules/dealer_export/">Обновить XML BREXIT / ESSON</a>
```

Сам `index.php` дополнительно проверяет авторизацию, права администратора и Bitrix session ID.

## 9. Настроить cron

Пример ежедневного запуска:

```cron
17 3 * * * /usr/bin/python3 /var/www/kanuk/data/www/kanuk.brexit.by/local/tools/dealer-export/dealer_export.py --config /var/www/kanuk/data/www/kanuk.brexit.by/local/tools/dealer-export/dealer_export.json >> /var/www/kanuk/data/www/kanuk.brexit.by/local/var/log/dealer_export_cron.log 2>&1
```

Перед добавлением cron проверить часовой пояс сервера:

```bash
date
timedatectl
```

В экспортёре уже есть собственный lock, поэтому отдельный `flock` в cron не требуется.

## 10. Финальная проверка установки

Проверить три сценария:

```text
1. запуск из терминала;
2. запуск кнопкой из закрытого раздела Битрикс;
3. запуск cron.
```

После каждого сценария проверить:

```bash
tail -30 SITE_ROOT/local/var/log/dealer_export.log

grep -m1 generated_at SITE_ROOT/dealer-export/brexit.xml
grep -m1 generated_at SITE_ROOT/dealer-export/esson.xml
```

Публичные URL:

```text
https://DOMAIN/dealer-export/brexit.xml
https://DOMAIN/dealer-export/esson.xml
```

# Безопасная публикация XML

Экспорт выполняется по схеме:

```text
получение данных
    ↓
создание временного XML
    ↓
валидация
    ↓
проверка обязательных данных
    ↓
fsync
    ↓
atomic replace
```

Если генерация завершается ошибкой, предыдущий публичный XML остаётся на месте.

Одновременный запуск нескольких экземпляров блокируется lock-файлом.

# Логи

Основной лог:

```text
local/var/log/dealer_export.log
```

Вывод cron:

```text
local/var/log/dealer_export_cron.log
```

Успешный запуск заканчивается строкой:

```text
INFO Dealer export finished successfully
```

# Время обновления

В атрибуте XML:

```xml
generated_at="2026-10-22T21:52:52+00:00"
```

время хранится в UTC.

На административной странице время формирования читается из `generated_at`, а не через `filemtime()`. Это исключает проблему устаревшего времени при использовании NFS/stat-cache.

# Что не хранить в Git

Не следует коммитить:

- пароли и секреты;
- XML, сформированные на production;
- production-логи;
- lock-файлы;
- временные файлы.

Для переноса на другой хостинг и смены домена см. [MIGRATION.md](./MIGRATION.md).
