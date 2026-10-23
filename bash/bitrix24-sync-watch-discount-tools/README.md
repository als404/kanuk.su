# Bitrix24 Sync Watch - discount-tools.ru

Монитор фактической синхронизации **Bitrix24 <-> discount-tools.ru**.

Скрипт не обращается к API Bitrix24 и не изменяет настройки интеграции. Он читает nginx access-log и проверяет, что Bitrix24 регулярно выполняет успешную авторизацию через:

```text
/bitrix/admin/1c_exchange.php?type=crm&mode=checkauth
```

Контролируются:

```text
пользователь: bx_eshop
URL: /bitrix/admin/1c_exchange.php
type=crm
mode=checkauth
HTTP status: 200
```

IP Bitrix24 намеренно **не используется как условие**, так как он может измениться.

## Текущая рабочая конфигурация

| Параметр | Значение |
|---|---|
| Сайт | `discount-tools.ru` |
| Пользователь интеграции | `bx_eshop` |
| Нормальный период Bitrix24 | 10 минут |
| Порог ALERT | 25 минут / `1500` секунд |
| Проверка cron | каждые 5 минут |
| Access-log | `/var/log/nginx/discount-tools.ru_access.log` |
| State | `/var/tmp/bitrix24_sync_watch.state` |
| Лог монитора | `/var/log/bitrix24_sync_watch.log` |
| Скрипт на сервере | `/usr/local/sbin/bitrix24_sync_watch.sh` |
| msmtp | `/usr/bin/msmtp` |
| msmtp config | `/home/bitrix/.msmtprc` |
| msmtp account | `discount-tools.ru` |
| Отправитель | `info@discount-tools.ru` |
| Получатель | `adm+site@kanuk.su` |

## Логика

```text
последний успешный checkauth <= 25 минут
-> OK

последний успешный checkauth > 25 минут
-> ALERT

после ALERT появился новый успешный checkauth
-> RECOVERED
```

Письма отправляются только при переходе состояния:

```text
OK -> ALERT
ALERT -> OK
```

Повторное ALERT-письмо каждые 5 минут не отправляется.

## Структура репозитория

```text
bitrix24-sync-watch-discount-tools/
├── bitrix24_sync_watch.sh
├── README.md
├── MIGRATION.md
├── SHA256SUMS
├── .gitignore
├── examples/
│   ├── bitrix24_sync_watch.conf.example
│   └── root.cron.example
└── logrotate/
    └── bitrix24_sync_watch
```

## Установка

Команды выполняются от `root`.

### 1. Установить скрипт

```bash
cp bitrix24_sync_watch.sh /usr/local/sbin/bitrix24_sync_watch.sh
chmod 755 /usr/local/sbin/bitrix24_sync_watch.sh
bash -n /usr/local/sbin/bitrix24_sync_watch.sh
```

### 2. Создать конфигурацию

```bash
cp examples/bitrix24_sync_watch.conf.example /etc/bitrix24_sync_watch.conf
chmod 600 /etc/bitrix24_sync_watch.conf
vi /etc/bitrix24_sync_watch.conf
```

Скрипт содержит рабочие значения `discount-tools.ru` по умолчанию, но отдельный конфиг удобнее для миграции и Git.

### 3. Проверить msmtp

```bash
which msmtp
ls -la /home/bitrix/.msmtprc
```

Ожидается:

```text
/usr/bin/msmtp
```

В `.msmtprc` должен существовать аккаунт:

```text
account discount-tools.ru
```

SMTP-пароль **не хранится в этом репозитории**.

### 4. Проверить nginx access-log

```bash
ls -lh /var/log/nginx/discount-tools.ru_access.log
```

Последние успешные обращения:

```bash
grep ' - bx_eshop ' /var/log/nginx/discount-tools.ru_access.log \
  | grep '1c_exchange.php' \
  | grep 'type=crm' \
  | grep 'mode=checkauth' \
  | grep '" 200 ' \
  | tail -20
```

### 5. Ручной запуск

```bash
/usr/local/sbin/bitrix24_sync_watch.sh
```

При штатной синхронизации:

```text
OK: последний checkauth 2026-09-21 13:41:32 +0300, прошло 2 мин.
```

Проверить:

```bash
cat /var/tmp/bitrix24_sync_watch.state
tail -30 /var/log/bitrix24_sync_watch.log
```

События монитора:

```text
STARTED
ALERT
RECOVERED
ERROR
```

### 6. Добавить cron

```bash
crontab -e
```

```cron
*/5 * * * * /usr/local/sbin/bitrix24_sync_watch.sh >/dev/null 2>&1
```

### 7. Установить logrotate

```bash
cp logrotate/bitrix24_sync_watch /etc/logrotate.d/bitrix24_sync_watch
chmod 644 /etc/logrotate.d/bitrix24_sync_watch
logrotate -d /etc/logrotate.d/bitrix24_sync_watch
```

## Почему проверяется фактический `checkauth`

Монитор создавался из-за ситуации, когда период синхронизации Bitrix24 мог сброситься в `0` - ручной режим. Но проверять только значение периода недостаточно: даже при `10` обмен может не работать из-за авторизации, HTTP 401/403/500/502/503, SSL, таймаута или недоступности сайта.

Поэтому контролируется реальный успешный запрос `checkauth`.

## Работа с ротацией nginx-логов

Скрипт читает:

1. текущий `ACCESS_LOG`;
2. самый свежий архив `${ACCESS_LOG}-*.gz`.

Текущая схема:

```text
/var/log/nginx/discount-tools.ru_access.log
/var/log/nginx/discount-tools.ru_access.log-YYYYMMDD.gz
```

Если на новом сервере формат ротации другой, см. `MIGRATION.md`.

## Быстрая диагностика после ALERT

```bash
cat /var/tmp/bitrix24_sync_watch.state
tail -30 /var/log/bitrix24_sync_watch.log
tail -30 /home/bitrix/msmtp_discount-tools.ru.log
```

Последние подключения:

```bash
grep ' - bx_eshop ' /var/log/nginx/discount-tools.ru_access.log \
  | grep '1c_exchange.php' \
  | grep 'type=crm' \
  | grep 'mode=checkauth' \
  | tail -20
```

В Bitrix24 проверить `Период синхронизации`:

```text
нормально: 10
проблемно: 0
```

После восстановления:

```bash
/usr/local/sbin/bitrix24_sync_watch.sh
```

При переходе `ALERT -> OK` должно прийти письмо о восстановлении.

## Что монитор не делает

- не исправляет период синхронизации автоматически;
- не запускает обмен принудительно;
- не меняет Bitrix24;
- не меняет сайт;
- не перезапускает nginx/PHP/MySQL;
- не привязывается к IP Bitrix24.

## Безопасность Git

Не добавлять:

```text
/home/bitrix/.msmtprc
SMTP-пароли
реальные secret/env-файлы
```

Перед push:

```bash
git status
git diff --cached
```

## Миграция

См. `MIGRATION.md`.
