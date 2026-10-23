# Миграция Dealer Export на новый хостинг / домен

Документ используется при переносе сайта вместе с XML-выгрузкой BREXIT / ESSON на другой сервер.

Предполагается, что сайт переносится уже в рабочей конфигурации **UTF-8 + PHP 7.4**, поэтому отдельная адаптация старой базы Windows-1251 / PHP 5.6 не требуется.

## 1. Перенести файлы

На новый сервер необходимо перенести:

```text
local/tools/dealer-export/dealer_export.py
local/tools/dealer-export/dealer_export.json
local/modules/dealer_export/index.php
```

Также должны существовать:

```text
dealer-export/
local/var/log/
```

Если в Git файлы хранятся в `src/modules/dealer_export/`, при деплое их необходимо разместить в соответствующих рабочих каталогах сайта.

## 2. Проверить Python

```bash
python3 --version
which python3
```

Если путь отличается от `/usr/bin/python3`, изменить его в:

- cron;
- PHP-странице ручного запуска.

## 3. Изменить домен

В production-конфигурации заменить тестовый адрес:

```text
https://kanuk.brexit.by
```

на production-домен, например:

```text
https://discount-tools.ru
```

После изменения проверить ссылки внутри XML:

```xml
<url>https://discount-tools.ru/catalog/...</url>
```

Также проверить абсолютные URL изображений.

## 4. Изменить серверные пути

На новом сервере проверить и изменить:

```text
site_root
output_dir
log_file
путь к dealer_export.py
путь к dealer_export.json
путь в PHP-обработчике
путь в cron
```

Пример старого пути:

```text
/var/www/kanuk/data/www/kanuk.brexit.by/
```

Его необходимо заменить на фактический `DOCUMENT_ROOT` production-сайта.

## 5. Проверить параметры БД

Проверить:

- имя базы;
- host;
- доступ пользователя БД;
- кодировку соединения;
- наличие таблиц каталога.

Если экспортёр читает подключение из `.settings.php` Битрикс, убедиться, что файл находится по ожидаемому пути и доступен пользователю запуска.

## 6. Проверить структуру каталога

Если переносится копия текущей базы целиком, ID должны сохраниться.

Контрольные значения:

```text
Каталог:                  iblock 16
SKU:                      iblock 17
ARTNUMBER:                property 51
MANUFACTURER:             property 52
MORE_PHOTO:               property 50
BREXIT manufacturer ID:   267
ESSON manufacturer ID:    3688
BASE price group:         1
```

Если каталог пересоздавался или свойства менялись, значения необходимо проверить.

## 7. Настроить владельца и права

Определить пользователя PHP-FPM:

```bash
ps -eo user,group,cmd | grep '[p]hp-fpm'
```

Проверить владельцев:

```bash
ls -la /path/to/local/tools/dealer-export
ls -la /path/to/dealer-export
ls -la /path/to/local/var/log
```

Cron рекомендуется запускать от пользователя сайта.

Не использовать:

```text
chmod 777
sudo из PHP
```

## 8. Проверить lock-файл

Перед первым запуском:

```bash
pgrep -af dealer_export.py
```

Если экспортёр не работает, старый lock-файл можно удалить:

```bash
rm -f /tmp/kanuk-dealer-export.lock
```

Если в production меняется имя lock-файла, новое имя необходимо зафиксировать в конфигурации/коде.

## 9. Первый запуск — без публикации

```bash
/usr/bin/python3 \
  /path/to/dealer_export.py \
  --config /path/to/dealer_export.json \
  --no-publish
```

Проверить лог:

```bash
tail -50 /path/to/local/var/log/dealer_export.log
```

Контрольные ориентиры текущей базы:

```text
BREXIT: 1428 товаров
ESSON:   97 товаров
```

Количество может измениться, если каталог обновлялся после подготовки документации.

Не должно быть неожиданных:

```text
ERROR
Traceback
validation failed
```

## 10. Проверить preview XML

Проверить:

- XML корректно открывается;
- бренд соответствует файлу;
- товары имеют артикулы;
- цены корректны;
- наличие корректно;
- единицы измерения корректны;
- изображения открываются;
- ссылки используют новый домен;
- описание передаётся корректно;
- количество товаров соответствует каталогу.

Рекомендуется вручную проверить несколько товаров каждого бренда.

## 11. Выполнить production-публикацию

После успешного `--no-publish`:

```bash
/usr/bin/python3 \
  /path/to/dealer_export.py \
  --config /path/to/dealer_export.json
```

Проверить:

```bash
grep -m1 generated_at /path/to/dealer-export/brexit.xml
grep -m1 generated_at /path/to/dealer-export/esson.xml
ls -lh /path/to/dealer-export/
```

## 12. Проверить публичные URL

После переключения домена должны открываться:

```text
https://NEW-DOMAIN/dealer-export/brexit.xml
https://NEW-DOMAIN/dealer-export/esson.xml
```

Сообщение браузера:

```text
This XML file does not appear to have any style information associated with it.
```

не является ошибкой.

## 13. Проверить страницу ручного запуска

В PHP-обработчике изменить фиксированные пути:

```text
python
dealer_export.py
dealer_export.json
output directory
```

Проверить:

- доступ только администратору;
- POST-запуск;
- `check_bitrix_sessid()`;
- доступность `exec()`;
- возможность записи XML;
- возможность записи логов.

После нажатия кнопки в журнале должен появиться новый блок:

```text
INFO Dealer export start; publish=yes
...
INFO Dealer export finished successfully
```

Время на странице рекомендуется читать из `generated_at` XML.

## 14. Настроить cron

```bash
crontab -u SITE_USER -e
```

Пример:

```cron
17 3 * * * /usr/bin/python3 /path/to/dealer_export.py --config /path/to/dealer_export.json >> /path/to/local/var/log/dealer_export_cron.log 2>&1
```

Учесть часовой пояс сервера:

```bash
date
timedatectl
```

Проверить:

```bash
crontab -u SITE_USER -l
```

## 15. Финальная проверка

После миграции должны успешно работать:

1. запуск из терминала;
2. запуск кнопкой из закрытого раздела Битрикс;
3. автоматический запуск cron.

После каждого сценария проверить:

```text
dealer_export.log
generated_at в XML
BREXIT XML
ESSON XML
владельца созданных файлов
```

## Краткий чек-лист

```text
[ ] Файлы экспортёра перенесены
[ ] Python 3 найден
[ ] Новый домен указан
[ ] Серверные пути изменены
[ ] Доступ к БД работает
[ ] ID каталога и свойств проверены
[ ] Права каталогов настроены
[ ] Старый lock отсутствует
[ ] --no-publish выполнен успешно
[ ] Preview XML проверены
[ ] Production XML опубликованы
[ ] Публичный URL BREXIT работает
[ ] Публичный URL ESSON работает
[ ] Кнопка администратора работает
[ ] Cron работает
[ ] Логи записываются
```
