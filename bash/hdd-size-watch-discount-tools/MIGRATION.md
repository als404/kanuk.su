# MIGRATION - перенос hdd_size.sh на другой сервер

Монитор диска жёстко зависит от структуры файловой системы конкретного сервера. После переноса сайта нельзя автоматически считать, что новый диск снова называется `/dev/vda1`.

## 1. Что сохранить на старом сервере

До переноса выполнить:

```bash
hostname -f
date
timedatectl
```

```bash
df -P
df -P /dev/vda1
```

```bash
ls -ld /home/bitrix/ext_www/public_html/bitrix/cache
du -sh /home/bitrix/ext_www/public_html/bitrix/cache
```

```bash
crontab -l
```

```bash
/home/script/hdd_size.sh --check
```

```bash
tail -50 /home/script/logs/hdd_size.log
```

## 2. Не переносить `/dev/vda1` вслепую

На новом сервере сначала определить файловую систему сайта:

```bash
df -P /ПУТЬ/К/САЙТУ
```

Например:

```bash
df -P /home/bitrix/ext_www/public_html
```

Результат может быть:

```text
/dev/vda1
/dev/sda2
/dev/mapper/...
overlay
другой mount point
```

В `/etc/hdd_size.conf` указать реальный `DISK_TARGET`.

## 3. Проверить новый путь сайта

Найти каталог кэша Bitrix:

```bash
find /home /var/www -type d -path '*/bitrix/cache' 2>/dev/null
```

Проверить:

```bash
ls -ld /НОВЫЙ/ПУТЬ/bitrix/cache
```

В конфиге изменить:

```text
CACHE_DIR="/НОВЫЙ/ПУТЬ/bitrix/cache"
```

Нельзя оставлять старый путь, если сайт переехал.

## 4. Установить скрипт

```bash
mkdir -p /home/script/logs
cp hdd_size.sh /home/script/hdd_size.sh
chmod 755 /home/script/hdd_size.sh
```

Проверить:

```bash
bash -n /home/script/hdd_size.sh
```

## 5. Создать конфиг

```bash
cp examples/hdd_size.conf.example /etc/hdd_size.conf
chmod 600 /etc/hdd_size.conf
vi /etc/hdd_size.conf
```

Перепроверить:

```text
DISK_TARGET
THRESHOLD
CACHE_DIR
STATE_FILE
LOCK_FILE
MAIL_ENABLED
MSMTP
MSMTP_CONFIG
MSMTP_ACCOUNT
EMAIL_FROM
EMAIL_TO
```

## 6. Проверить зависимости

```bash
command -v bash
command -v df
command -v awk
command -v find
command -v flock
command -v date
command -v base64
command -v msmtp
```

## 7. Проверить без удаления

Сначала:

```bash
/home/script/hdd_size.sh --check
```

Убедиться, что:

- определяется правильный диск;
- процент совпадает с `df -P`;
- `CACHE_DIR` существует;
- порог равен ожидаемому значению.

Затем:

```bash
/home/script/hdd_size.sh --dry-run
```

До прохождения этих двух проверок cron не включать.

## 8. SMTP

Если используется почтовое уведомление, безопасно перенести или заново создать:

```text
/home/bitrix/.msmtprc
```

Права:

```bash
chmod 600 /home/bitrix/.msmtprc
```

Пароль не хранить в Git.

Если SMTP на новом сервере ещё не готов, временно:

```text
MAIL_ENABLED=0
```

## 9. Cron

После успешной ручной проверки:

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

## 10. Logrotate

```bash
cp logrotate/hdd_size /etc/logrotate.d/hdd_size
chmod 644 /etc/logrotate.d/hdd_size
logrotate -d /etc/logrotate.d/hdd_size
```

## 11. State после миграции

Старый state переносить не обязательно.

На новом сервере можно начать с чистого состояния:

```bash
rm -f /var/tmp/hdd_size.state
```

Первый штатный запуск создаст актуальное состояние.

## 12. Проверить права

Скрипт должен запускаться от `root`, потому что он удаляет содержимое Bitrix cache и пишет lock/state.

Проверить:

```bash
ls -l /home/script/hdd_size.sh
ls -ld /home/script/logs
ls -ld /НОВЫЙ/ПУТЬ/bitrix/cache
```

## 13. Переключение серверов

Рекомендуемый порядок:

```text
1. Установить скрипт на новом сервере.
2. Настроить /etc/hdd_size.conf.
3. Выполнить --check.
4. Выполнить --dry-run.
5. Проверить msmtp или временно MAIL_ENABLED=0.
6. Включить cron на новом сервере.
7. Отключить cron hdd_size.sh на старом сервере.
```

Не оставлять два активных production-сервера, если старый больше не должен обслуживать сайт.

## 14. Откат

Если после миграции обнаружен неверный путь или диск:

```bash
crontab -e
```

сразу закомментировать строку `hdd_size.sh`.

После этого исправить `/etc/hdd_size.conf` и повторить:

```bash
/home/script/hdd_size.sh --check
/home/script/hdd_size.sh --dry-run
```

## 15. Финальный чек-лист

- [ ] определён реальный диск нового сервера
- [ ] `DISK_TARGET` не скопирован вслепую со старого сервера
- [ ] найден реальный `bitrix/cache`
- [ ] `CACHE_DIR` проверен вручную
- [ ] `THRESHOLD=90`
- [ ] `bash -n` проходит
- [ ] `--check` показывает правильный диск и процент
- [ ] `--dry-run` проходит безопасно
- [ ] SMTP настроен либо `MAIL_ENABLED=0`
- [ ] cron добавлен только после тестов
- [ ] logrotate проверен
- [ ] SMTP-пароль отсутствует в Git
