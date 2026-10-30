# MIGRATION / INSTALL — Bitrix24 Sync Watch 1.1-postfix

Инструкция для production `discount-tools.ru` (AlmaLinux 9, Postfix). В отличие от исходной версии 1.0.0 отправляет письма через `/usr/sbin/sendmail`, без msmtp.

## 1. Скопировать ZIP с Mac

```bash
scp ~/Downloads/bitrix24-sync-watch-discount-tools-v1.1-postfix.zip als@45.128.206.42:/tmp/
ssh -t als@45.128.206.42
```

## 2. Найти реальный Nginx access-log

```bash
sudo nginx -T 2>/dev/null | grep -n 'access_log'
sudo ls -lh /var/log/nginx/*access* /var/log/nginx/access.log* 2>/dev/null | head -30
```

Путь по умолчанию в конфиге — `/var/log/nginx/access.log`. Если сайт пишет в другой файл, исправьте `ACCESS_LOG`.

Для диагностики последних запросов (проверьте, нет ли токенов/секретов перед публикацией):

```bash
sudo grep -F '1c_exchange.php' /var/log/nginx/access.log | tail -n 10
```

## 3. Установить скрипт и конфигурацию

```bash
sudo install -d -m 700 -o root -g root /var/lib/bitrix24-sync-watch
sudo unzip -p /tmp/bitrix24-sync-watch-discount-tools-v1.1-postfix.zip \
  bash/bitrix24-sync-watch-discount-tools/bitrix24_sync_watch.sh \
  | sudo tee /usr/local/sbin/bitrix24_sync_watch.sh >/dev/null
sudo chown root:root /usr/local/sbin/bitrix24_sync_watch.sh
sudo chmod 755 /usr/local/sbin/bitrix24_sync_watch.sh

sudo unzip -p /tmp/bitrix24-sync-watch-discount-tools-v1.1-postfix.zip \
  bash/bitrix24-sync-watch-discount-tools/examples/bitrix24_sync_watch.conf.example \
  | sudo tee /etc/bitrix24_sync_watch.conf >/dev/null
sudo chown root:root /etc/bitrix24_sync_watch.conf
sudo chmod 600 /etc/bitrix24_sync_watch.conf
sudo vi /etc/bitrix24_sync_watch.conf
```

Перед изменениями существующей версии скопируйте старые скрипт и конфиг. В данном переносе скрипта на production ранее не было.

## 4. Проверить вручную (без отправки писем и записи state)

```bash
sudo bash -n /usr/local/sbin/bitrix24_sync_watch.sh && echo 'SYNTAX OK'
sudo /usr/local/sbin/bitrix24_sync_watch.sh --check
sudo /usr/local/sbin/bitrix24_sync_watch.sh --dry-run
```

`--check` должен показать `RESULT=OK`, дату `LAST_CHECKAUTH` и актуальный `LAST_SOURCE`. Если `RESULT=ALERT`, проверьте в первую очередь `ACCESS_LOG`, `B24_USER`, `REQUIRE_LOG_USER` и реальный журнал обращения.

ВНИМАНИЕ: если Nginx не записывает username в access-log, `REQUIRE_LOG_USER=1` не найдёт совпадений. Параметр `REQUIRE_LOG_USER=0` разрешает засчитывать любые HTTP 200 `checkauth` по этому URL, что повышает риск ложных OK. Используйте только после изучения формата логов.

## 5. Проверить отправку Postfix

```bash
systemctl is-active postfix
sudo /usr/local/sbin/bitrix24_sync_watch.sh --test-email
```

Проверьте получение письма на `adm+site@kanuk.su`.

## 6. Первый штатный запуск и cron

При `RESULT=OK`:

```bash
sudo /usr/local/sbin/bitrix24_sync_watch.sh --run
sudo cat /var/lib/bitrix24-sync-watch/state
```

Только затем включите cron:

```bash
sudo unzip -p /tmp/bitrix24-sync-watch-discount-tools-v1.1-postfix.zip \
  bash/bitrix24-sync-watch-discount-tools/examples/root.cron.example \
  | sudo tee /etc/cron.d/discount-tools-bitrix24-sync-watch >/dev/null
sudo chown root:root /etc/cron.d/discount-tools-bitrix24-sync-watch
sudo chmod 644 /etc/cron.d/discount-tools-bitrix24-sync-watch
systemctl is-active crond
sudo cat /etc/cron.d/discount-tools-bitrix24-sync-watch
```

## 7. Logrotate (дополнительно)

```bash
sudo unzip -p /tmp/bitrix24-sync-watch-discount-tools-v1.1-postfix.zip \
  bash/bitrix24-sync-watch-discount-tools/logrotate/bitrix24_sync_watch \
  | sudo tee /etc/logrotate.d/bitrix24_sync_watch >/dev/null
sudo chown root:root /etc/logrotate.d/bitrix24_sync_watch
sudo chmod 644 /etc/logrotate.d/bitrix24_sync_watch
```

## 8. Диагностика

```bash
sudo /usr/local/sbin/bitrix24_sync_watch.sh --check
sudo tail -n 30 /var/log/bitrix24_sync_watch.log
sudo tail -n 30 /var/log/bitrix24_sync_watch-cron.log
```

Весь мониторинг основан на HTTP 200 `checkauth`, а не на ответе `success` и не на успешном завершении загрузки заказов. Системные часы сервера должны быть синхронизированы (NTP), а в nginx логах должна присутствовать дата с часовым поясом.

## Откат

```bash
sudo rm -f /etc/cron.d/discount-tools-bitrix24-sync-watch
sudo rm -f /usr/local/sbin/bitrix24_sync_watch.sh
sudo rm -f /etc/bitrix24_sync_watch.conf
```

Данные сайта, Битрикс24 и журнал Nginx эти команды не трогают. State и журнал мониторинга сохраняются для диагностики.
