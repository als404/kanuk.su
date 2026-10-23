# hdd_size.sh - контроль заполнения диска discount-tools.ru

Git-пакет для скрипта, который контролирует заполнение диска сервера сайта `discount-tools.ru` и при достижении порога очищает рабочий кэш Bitrix.

## Подтверждённая исходная конфигурация

Исторически скрипт работал так:

```text
скрипт: /home/script/hdd_size.sh
диск: /dev/vda1
порог: 90%
очистка: /home/bitrix/ext_www/public_html/bitrix/cache/*
лог: /home/script/logs/hdd_size.log
cron: каждую минуту
```

Проверка заполнения в текущей версии выполняется через:

```bash
df -P
```

а не поиском строк `90%`-`100%`.

## Что делает Git-версия

При заполнении ниже `90%`:

```text
ничего не удаляет
state = OK
```

При заполнении `>=90%`:

```text
очищает только содержимое bitrix/cache
сам каталог bitrix/cache не удаляет
повторно измеряет заполнение
пишет результат в лог
state = ALERT
отправляет уведомление через msmtp
```

Пока состояние остаётся `ALERT`, одинаковое письмо каждую минуту не отправляется.

Когда заполнение снова становится ниже порога:

```text
state = OK
```

и отправляется одно уведомление о восстановлении.

## Что скрипт НЕ удаляет

Скрипт не очищает:

```text
bitrix/managed_cache
bitrix/stack_cache
bitrix/html_pages
upload
backup
базу данных
логи
```

В текущей конфигурации очищается только:

```text
/home/bitrix/ext_www/public_html/bitrix/cache/*
```

## Структура репозитория

```text
hdd-size-watch-discount-tools/
├── hdd_size.sh
├── README.md
├── MIGRATION.md
├── SHA256SUMS
├── .gitignore
├── examples/
│   ├── hdd_size.conf.example
│   └── root.cron.example
└── logrotate/
    └── hdd_size
```

## Установка

Команды выполняются от `root`.

### 1. Создать каталоги

```bash
mkdir -p /home/script/logs
```

### 2. Установить скрипт

```bash
cp hdd_size.sh /home/script/hdd_size.sh
chmod 755 /home/script/hdd_size.sh
```

Проверить синтаксис:

```bash
bash -n /home/script/hdd_size.sh
```

### 3. Создать внешний конфиг

```bash
cp examples/hdd_size.conf.example /etc/hdd_size.conf
chmod 600 /etc/hdd_size.conf
vi /etc/hdd_size.conf
```

Внешний конфиг нужен для миграции: сам Git-файл можно обновлять, не меняя серверные пути.

### 4. Проверить текущие параметры без очистки

```bash
/home/script/hdd_size.sh --check
```

Пример:

```text
DISK_TARGET=/dev/vda1
USAGE=75%
THRESHOLD=90%
CACHE_DIR=/home/bitrix/ext_www/public_html/bitrix/cache
```

### 5. Безопасно проверить порог без удаления

```bash
/home/script/hdd_size.sh --dry-run
```

Если диск ниже порога, удаления не будет.

Если диск уже `>=90%`, скрипт только напишет, что **сделал бы** очистку.

### 6. Настроить cron

Открыть root crontab:

```bash
crontab -e
```

Добавить:

```cron
* * * * * /bin/bash /home/script/hdd_size.sh >> /home/script/logs/hdd_size.log 2>&1
```

Проверить:

```bash
crontab -l
```

## Лог

Путь:

```text
/home/script/logs/hdd_size.log
```

Скрипт не пишет строку каждую минуту при штатном состоянии.

В журнал попадают значимые события:

```text
CLEANUP
RECOVERED
ERROR
DRY-RUN
```

Проверка:

```bash
tail -50 /home/script/logs/hdd_size.log
```

## State

Файл:

```text
/var/tmp/hdd_size.state
```

Возможные значения:

```text
OK
ALERT
```

Проверить:

```bash
cat /var/tmp/hdd_size.state
```

State нужен, чтобы не отправлять одинаковое ALERT-письмо каждую минуту.

## Lock

Файл:

```text
/var/lock/hdd_size.lock
```

Используется `flock -n`, чтобы два cron-запуска не выполняли очистку одновременно.

## Почта

Для уведомлений используется существующая инфраструктура `msmtp`:

```text
/usr/bin/msmtp
```

Конфигурация:

```text
/home/bitrix/.msmtprc
```

Аккаунт:

```text
discount-tools.ru
```

Пароль SMTP **не хранится в Git**.

Перед включением почты проверить:

```bash
which msmtp
ls -la /home/bitrix/.msmtprc
```

Если уведомления временно не нужны:

```text
MAIL_ENABLED=0
```

в `/etc/hdd_size.conf`.

## Logrotate

Установить:

```bash
cp logrotate/hdd_size /etc/logrotate.d/hdd_size
chmod 644 /etc/logrotate.d/hdd_size
```

Проверить без изменений:

```bash
logrotate -d /etc/logrotate.d/hdd_size
```

## Ручная диагностика

Посмотреть заполнение:

```bash
df -P /dev/vda1
```

Размер кэша:

```bash
du -sh /home/bitrix/ext_www/public_html/bitrix/cache
```

Проверить скрипт:

```bash
/home/script/hdd_size.sh --check
```

Проверить журнал:

```bash
tail -50 /home/script/logs/hdd_size.log
```

## Безопасность Git

Не добавлять в репозиторий:

```text
/home/bitrix/.msmtprc
SMTP-пароли
локальный /etc/hdd_size.conf с секретами
```

Перед push:

```bash
git status
git diff --cached
```

## Миграция

Пошаговый перенос на другой сервер находится в `MIGRATION.md`.
