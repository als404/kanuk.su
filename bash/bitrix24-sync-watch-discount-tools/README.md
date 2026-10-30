# Bitrix24 Sync Watch — discount-tools.ru

Версия **1.1-postfix**. Скрипт мониторинга обращений Битрикс24 к сайту, адаптированный для AlmaLinux production с Postfix.

## Что именно проверяет

Скрипт читает nginx access-log и ищет последнюю строку запроса:

- `/bitrix/admin/1c_exchange.php?type=crm&mode=checkauth` (параметры могут идти в другом порядке);
- HTTP `200`;
- при `REQUIRE_LOG_USER=1` пользователь HTTP Basic Auth `bx_eshop` в журнале в виде ` - bx_eshop `.

**Ограничение:** HTTP `200` и `checkauth` *не подтверждают* содержимое ответа `success`, успешность выгрузки товаров/заказов или полный цикл синхронизации. Например, HTML-страница с HTTP 200 может выглядеть для монитора успешной. Скрипт обнаруживает отсутствие запросов обмена, а не все логические ошибки протокола. Не хранит пароль и не вызывает API Bitrix24.

Правила:

- Последний подходящий запрос не старше `ALERT_AFTER=1500` секунд (25 минут) — `OK`.
- Старше порога или не найден — `ALERT`.
- При переходе `OK -> ALERT` отправляется одно письмо.
- При переходе `ALERT -> OK` отправляется письмо о восстановлении.
- При первом запуске в статусе `OK` письмо не отправляется.
- Если отправка письма не удалась, состояние не меняется; очередной запуск повторит попытку.
- Из-за `flock` одновременные запуски мониторинга не конфликтуют.

При периоде обмена 10 минут порог 25 минут допускает два интервала и небольшую задержку.

## Файлы

```text
bash/bitrix24-sync-watch-discount-tools/
├── bitrix24_sync_watch.sh
├── examples/
│   ├── bitrix24_sync_watch.conf.example
│   └── root.cron.example
├── logrotate/
│   └── bitrix24_sync_watch
├── README.md
├── MIGRATION.md
├── SHA256SUMS
└── .gitignore
```

## Установка на production

Подробные команды для macOS и production: [MIGRATION.md](MIGRATION.md).

Пути:

| Назначение | Значение |
|---|---|
| Скрипт | `/usr/local/sbin/bitrix24_sync_watch.sh` |
| Конфигурация | `/etc/bitrix24_sync_watch.conf` (root:root, 600) |
| Nginx access-log | `/var/log/nginx/access.log` (проверить фактический путь!) |
| Журнал мониторинга | `/var/log/bitrix24_sync_watch.log` |
| State | `/var/lib/bitrix24-sync-watch/state` |
| Cron | `/etc/cron.d/discount-tools-bitrix24-sync-watch` |
| Postfix sendmail | `/usr/sbin/sendmail` |
| Отправитель | `info@discount-tools.ru` |
| Получатель | `adm+site@kanuk.su` |

## Команды

```bash
sudo /usr/local/sbin/bitrix24_sync_watch.sh --check
sudo /usr/local/sbin/bitrix24_sync_watch.sh --dry-run
sudo /usr/local/sbin/bitrix24_sync_watch.sh --test-email
sudo /usr/local/sbin/bitrix24_sync_watch.sh --run
```

- `--check`: проверяет конфиг, журнал и свежесть, возвращает ошибку при отсутствии свежего совпадения; не отправляет письма, не меняет state.
- `--dry-run`: показывает статус, не меняет state, лог и почту, возвращает 0.
- `--test-email`: отправляет тест через Postfix, не меняет state (успешная команда означает принятие sendmail, не гарантирует доставку адресату).
- `--run`: запускает монитор и при изменениях отправляет уведомления.

**Не включайте cron до успешного `--check`.** Если журнал не содержит ` - bx_eshop `, проверьте реальный формат журнала, не отключайте проверку пользователя без понимания последствий.

## Логирование и ротация

Читает текущий access-log. Если там нет свежего совпадения, проверяет не более `MAX_ROTATED` новых ротированных файлов вида `access.log.1`, `access.log.1.gz` или `access.log-YYYYMMDD.gz`. Журнал собственных событий можно ротировать по файлу из `logrotate/`.

Скрипт не изменяет настройки синхронизации, не удаляет файлы, не очищает кеш и не перезапускает службы.
