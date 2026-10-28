# WebP-оптимизация изображений discount-tools.ru

## Назначение

Конвертер на **Python 3 + Pillow + SQLite** создаёт рядом с JPG/JPEG/PNG файлы `photo.jpg.webp`, не меняя оригиналы и не загружая ядро **1С-Битрикс**. Nginx выдаёт WebP браузерам с `Accept: image/webp` по прежнему URL; остальные получают оригинал. PDF не конвертируются.

**Два режима:** однократная полная обработка изображений после переноса (`full_convert.sh`) и последующее ограниченное задание cron (50 попыток конвертации каждые 5 минут). Оба режима используют **одну** SQLite-базу и **один** lock-файл.

## Состав репозитория

```text
discount-webp-git/
├── webp_convert.py                  # основной конвертер, SQLite, flock, dry-run
├── full_convert.sh                  # ручная полная конвертация после миграции
├── nginx/
│   ├── 00-webp-map.conf             # map для Accept (контекст http)
│   └── server-webp-location.conf    # вставить в HTTPS server {}
├── cron/
│   └── discount-webp                # образец /etc/cron.d/discount-webp
├── logrotate/
│   └── discount-webp                # образец /etc/logrotate.d/discount-webp
├── README.md
└── MIGRATION.md
```

В репозиторий **не добавлять** виртуальное окружение, SQLite (`state.sqlite3`), журналы, lock-файл, исходные фотографии и созданные WebP.

## Размещение на VM 247 (фактически проверенное окружение)

```text
/opt/discount-tools/image-webp/webp_convert.py
/opt/discount-tools/image-webp/full_convert.sh
/opt/discount-tools/webp-venv/             # Python + Pillow
/var/lib/discount-tools/image-webp/        # state.sqlite3, converter.lock
/var/log/discount-tools/                   # webp-cron.log, webp-full.log
/etc/nginx/conf.d/00-webp-map.conf
/etc/nginx/conf.d/vmtest-https.conf         # две location-секции WebP
/etc/cron.d/discount-webp
/etc/logrotate.d/discount-webp
/var/www/discount-tools/public/upload/{iblock,resize_cache}/
```

Права: код `root:root`, директории состояния и логов `discount:discount` с `750`, файлы WebP `discount:discount`, режим `644`. Пользователь `discount` должен иметь право создавать файлы в исходных каталогах; не используйте `chmod -R 777`. На SELinux Enforcing проверьте контексты (`ls -Z`). Вне web-root размещаются код, SQLite и журналы.

## Пример первоначальной установки (проверить пути и имена пользователей)

```bash
sudo install -d -o root -g root -m 755 /opt/discount-tools/image-webp
sudo install -d -o discount -g discount -m 750 /var/lib/discount-tools/image-webp /var/log/discount-tools
sudo install -o root -g root -m 644 webp_convert.py /opt/discount-tools/image-webp/webp_convert.py
sudo install -o root -g root -m 755 full_convert.sh /opt/discount-tools/image-webp/full_convert.sh
# Создать Python venv в /opt/discount-tools/webp-venv и установить Pillow.
# На тесте использовалась Pillow 11.3.0; проверьте совместимость с Python целевого хостинга.
sudo /opt/discount-tools/webp-venv/bin/python -c 'from PIL import features; print(features.check("webp"))'
```

Нужно получить `True` при проверке WebP. Инструкция развёртывания Nginx и cron — в `MIGRATION.md`.

## Ручной полный запуск после переноса

**Сначала отключите плановое задание, если оно существует**, чтобы полная обработка не конкурировала с cron:

```bash
sudo mv /etc/cron.d/discount-webp /etc/cron.d/discount-webp.disabled
```

Убедитесь, что в каталоге состояния есть место для SQLite, а у `discount` есть доступ к `upload`. Для предварительного просмотра без конвертации:

```bash
sudo -u discount /opt/discount-tools/webp-venv/bin/python \
  /opt/discount-tools/image-webp/webp_convert.py \
  --root /var/www/discount-tools/public/upload/resize_cache \
  --root /var/www/discount-tools/public/upload/iblock \
  --limit 1000000 \
  --lock-file /var/lib/discount-tools/image-webp/converter.lock \
  --state-db /var/lib/discount-tools/image-webp/state.sqlite3
```

Запуск с созданием WebP и перенаправлением лога **внутри оболочки `discount`**:

```bash
sudo -u discount bash -c '
  /opt/discount-tools/image-webp/full_convert.sh \
    > /var/log/discount-tools/webp-full.log 2>&1
'
```

Признак завершения — команда вернулась с кодом `0`, а последняя строка лога содержит итоговый JSON:

```bash
sudo tail -n 1 /var/log/discount-tools/webp-full.log
```

При прерывании повторите запуск: актуальные результаты пропускаются по состоянию SQLite и проверке файлов. `--limit 1000000` — верхняя граница **попыток**, не ограничение времени или памяти. На большой базе полную конвертацию предпочтительно проводить вне пиковых часов. Скрипт пишет **lossy WebP**, включая PNG: проверьте качество, прозрачность и логотипы.

Если cron был отключён, **включайте его обратно только после проверки результата**:

```bash
sudo mv /etc/cron.d/discount-webp.disabled /etc/cron.d/discount-webp
sudo ls -l /etc/cron.d/discount-webp
sudo systemctl is-active crond
```

Не создавайте вторую активную копию задания. `flock` также предотвращает одновременное выполнение двух конвертеров.

## Плановое выполнение

Файл `cron/discount-webp` устанавливается в `/etc/cron.d/discount-webp` с правами `root:root`, `0644`. Он запускается от `discount` каждые 5 минут с `nice -n 10` и `ionice -c 3` и общим лимитом 50 попыток на `/upload/resize_cache` (первым) и `/upload/iblock`. Текущее поведение: **каждый запуск сканирует каталоги**, но актуальные изображения не перекодирует. Новые или изменённые исходники обрабатываются при следующих обходах. Если изображения не дают минимум 5% экономии, это фиксируется в SQLite, чтобы исключить постоянные повторные попытки.

```bash
sudo tail -n 5 /var/log/discount-tools/webp-cron.log
sudo grep -Ei 'Traceback|PermissionError|error:' /var/log/discount-tools/webp-cron.log | tail
```

Строки итогов содержат `scanned`, `attempted`, `applied`, `counts`, `saved_bytes`. `saved_bytes` — суммарная разница размеров исходников и созданных WebP, **не** фактическое освобождение диска: оригиналы сохранены, WebP добавляют занятое место. Это также не измерение полного объёма трафика сайта.

## Ограничения и безопасность

- Проход в `--apply` всегда создаёт sidecar-файлы рядом с оригиналами, без изменения JPEG/PNG/PDF.
- `--limit` ограничивает **попытки** конвертации, а не количество проверенных элементов. Скрипт начинает обход с первого каталога при каждом запуске; SQLite ускоряет решение, что конвертировать повторно не нужно.
- SQLite хранит **абсолютные пути** и параметры кодирования; после смены корня сайта создайте новую БД или подготовьте корректную миграцию путей. Не удаляйте базу без понимания последствий для пропущенных файлов.
- Если ранее созданный WebP имеет `mtime` не меньше исходника, конвертер может считать его актуальным независимо от изменений параметров кодирования. Для изменения `quality` нужен отдельный план регенерации.
- Старые `*.webp`, у которых больше нет оригинала, автоматически не удаляются.
- В Nginx `location` с `add_header` отменяет наследование `add_header` из `server`; для тестовой VM отдельно сохраните `X-Robots-Tag`, если он нужен для WebP/оригиналов.
- Для CDN/proxy проверьте различение `Accept` и корректность кеширования. При изменении Nginx сначала `nginx -t`, затем `reload`.

## Проверка HTTP

Проверяйте на реальном изображении, для которого есть `.webp`:

```bash
curl -sk -u developer -H 'Accept: image/webp,image/*' -D - \
  -o /tmp/dt-image.bin https://vmtest.discount-tools.ru/upload/iblock/FILE.jpeg
file /tmp/dt-image.bin
```

Ожидается `image/webp` и WebP по сигнатуре. С `Accept: image/jpeg` должен возвращаться оригинальный JPEG. Отдельно проверьте фото без WebP-копии, PDF, Basic Auth, а также картинку первого экрана и миниатюру в браузере. **Не копируйте тестовый Basic Auth на production автоматически.**
