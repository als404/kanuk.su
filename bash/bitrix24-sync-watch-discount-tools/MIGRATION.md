# MIGRATION - перенос Bitrix24 Sync Watch на другой сервер

Монитор зависит от инфраструктуры сервера:

- nginx access-log и его `log_format`;
- ротации nginx-логов;
- `msmtp`;
- SMTP-конфигурации;
- root cron;
- путей к state/log-файлам.

При переносе сайта недостаточно просто скопировать `bitrix24_sync_watch.sh`.

## 1. Зафиксировать старый сервер

```bash
hostname -f
date
timedatectl
```

```bash
ls -lh /var/log/nginx/discount-tools.ru_access.log*
```

```bash
grep ' - bx_eshop ' /var/log/nginx/discount-tools.ru_access.log \
  | grep '1c_exchange.php' \
  | grep 'type=crm' \
  | grep 'mode=checkauth' \
  | tail -20
```

```bash
crontab -l
which msmtp
ls -la /home/bitrix/.msmtprc
```

```bash
/usr/local/sbin/bitrix24_sync_watch.sh
cat /var/tmp/bitrix24_sync_watch.state
tail -30 /var/log/bitrix24_sync_watch.log
```

SMTP-пароль не копировать в Git.

## 2. Что переносить

Из репозитория:

```text
bitrix24_sync_watch.sh
examples/bitrix24_sync_watch.conf.example
examples/root.cron.example
logrotate/bitrix24_sync_watch
```

Отдельно, защищённым способом:

```text
/home/bitrix/.msmtprc
```

State-файл переносить не обязательно.

## 3. Найти access-log на новом сервере

```bash
grep -RniE 'access_log.*discount-tools|server_name.*discount-tools' \
  /etc/nginx /usr/local/nginx/conf 2>/dev/null
```

После ручной синхронизации должна находиться строка:

```bash
grep ' - bx_eshop ' /ПУТЬ/К/access.log \
  | grep '1c_exchange.php' \
  | grep 'type=crm' \
  | grep 'mode=checkauth' \
  | tail
```

Важно: nginx должен писать пользователя `bx_eshop` в access-log. Если этого поля нет, нужно вернуть совместимый `log_format` либо осознанно изменить критерий мониторинга.

## 4. Проверить ротацию nginx

Текущий код ожидает:

```text
/var/log/nginx/discount-tools.ru_access.log
/var/log/nginx/discount-tools.ru_access.log-YYYYMMDD.gz
```

Проверить:

```bash
ls -lh /var/log/nginx/discount-tools.ru_access.log*
```

Если новый сервер использует `access.log.1`, `access.log.2.gz` или другую схему, изменить поиск архивированного файла до включения cron.

## 5. Проверить зависимости

```bash
command -v bash
command -v awk
command -v sed
command -v date
command -v base64
command -v gzip
command -v msmtp
```

## 6. Установить скрипт

```bash
cp bitrix24_sync_watch.sh /usr/local/sbin/bitrix24_sync_watch.sh
chmod 755 /usr/local/sbin/bitrix24_sync_watch.sh
bash -n /usr/local/sbin/bitrix24_sync_watch.sh
```

## 7. Настроить конфиг

```bash
cp examples/bitrix24_sync_watch.conf.example /etc/bitrix24_sync_watch.conf
chmod 600 /etc/bitrix24_sync_watch.conf
vi /etc/bitrix24_sync_watch.conf
```

Перепроверить:

```text
SITE_NAME
ACCESS_LOG
B24_USER
EXCHANGE_PATH
SYNC_PERIOD_MIN
ALERT_AFTER
STATE_FILE
MONITOR_LOG
MSMTP
MSMTP_CONFIG
MSMTP_ACCOUNT
EMAIL_FROM
EMAIL_TO
```

Если период Bitrix24 изменился с 10 минут, пересчитать `ALERT_AFTER`.

## 8. Восстановить SMTP

Текущая схема:

```text
msmtp: /usr/bin/msmtp
config: /home/bitrix/.msmtprc
account: discount-tools.ru
from: info@discount-tools.ru
to: adm+site@kanuk.su
```

Права:

```bash
chmod 600 /home/bitrix/.msmtprc
```

Проверить аккаунт без вывода пароля:

```bash
grep -nE '^account[[:space:]]+discount-tools\.ru|^account[[:space:]]+default' \
  /home/bitrix/.msmtprc
```

## 9. Проверить вручную до cron

Сначала убедиться, что nginx уже содержит свежий успешный `checkauth`.

```bash
/usr/local/sbin/bitrix24_sync_watch.sh
```

Ожидается:

```text
OK: последний checkauth ... прошло N мин.
```

```bash
cat /var/tmp/bitrix24_sync_watch.state
tail -30 /var/log/bitrix24_sync_watch.log
```

На первом успешном запуске журнал должен получить `STARTED`.

## 10. Включить cron

Только после успешной ручной проверки:

```bash
crontab -e
```

```cron
*/5 * * * * /usr/local/sbin/bitrix24_sync_watch.sh >/dev/null 2>&1
```

## 11. Настроить logrotate

```bash
cp logrotate/bitrix24_sync_watch /etc/logrotate.d/bitrix24_sync_watch
chmod 644 /etc/logrotate.d/bitrix24_sync_watch
logrotate -d /etc/logrotate.d/bitrix24_sync_watch
```

## 12. Проверить часовой пояс

```bash
timedatectl
date
```

Парсинг nginx timestamp учитывает смещение из access-log, но NTP и системное время должны быть корректны.

## 13. Переключение со старого сервера

```text
1. Подготовить новый сервер.
2. Проверить nginx access-log.
3. Запустить ручной checkauth Bitrix24.
4. Запустить монитор вручную - должен быть OK.
5. Включить cron на новом сервере.
6. Отключить cron монитора на старом сервере.
7. Убедиться, что работает один production-монитор.
```

На старом сервере удалить/закомментировать:

```cron
*/5 * * * * /usr/local/sbin/bitrix24_sync_watch.sh >/dev/null 2>&1
```

## 14. Откат

Если после миграции появляются ложные ALERT:

1. временно отключить cron монитора;
2. проверить `ACCESS_LOG`;
3. проверить nginx `log_format`;
4. проверить наличие `bx_eshop`;
5. проверить схему `.gz` ротации;
6. проверить `MSMTP_CONFIG` и `MSMTP_ACCOUNT`;
7. проверить время сервера.

Не менять настройки Bitrix24 только ради исправления ошибки самого монитора.

## 15. Финальный чек-лист

- [ ] скрипт установлен в `/usr/local/sbin/bitrix24_sync_watch.sh`
- [ ] `bash -n` проходит
- [ ] `/etc/bitrix24_sync_watch.conf` создан, права `600`
- [ ] `ACCESS_LOG` правильный
- [ ] в access-log есть `bx_eshop`
- [ ] есть `type=crm`
- [ ] есть `mode=checkauth`
- [ ] успешный запрос имеет HTTP `200`
- [ ] схема ротации nginx совместима
- [ ] `msmtp` установлен
- [ ] `.msmtprc` перенесён безопасно
- [ ] аккаунт `discount-tools.ru` существует
- [ ] SMTP-пароль отсутствует в Git
- [ ] ручной запуск возвращает `OK`
- [ ] state содержит `OK`
- [ ] root cron включён
- [ ] logrotate проверен
- [ ] старый cron отключён
