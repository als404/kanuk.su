# Обновление существующего production (AlmaLinux 9.3)

Перед заменой скрипта, если cron уже включён, временно отключите **только** задание `/etc/cron.d/discount-tools-hdd-watch` на время изменения (например, комментированием его строки). Иначе оно может сработать между заменами.

```bash
sudo cp -a /home/script/hdd_size.sh /home/script/hdd_size.sh.before-2.1
sudo cp -a /etc/hdd_size.conf /etc/hdd_size.conf.before-2.1
```

Из распакованного архива (в командах `<dir>` — каталог с файлами архива):

```bash
sudo install -o root -g root -m 755 <dir>/hdd_size.sh /home/script/hdd_size.sh
sudo install -o root -g root -m 600 <dir>/examples/hdd_size.conf.example /etc/hdd_size.conf
sudo bash -n /home/script/hdd_size.sh
sudo /home/script/hdd_size.sh --check
sudo /home/script/hdd_size.sh --dry-run
```

Проверьте значения EMAIL_FROM и EMAIL_TO в `/etc/hdd_size.conf`. После проверки восстановите задание cron; **не добавляйте второе такое же задание**. Cron-файл по образцу — `/etc/cron.d/discount-tools-hdd-watch`, права `644 root:root`. Если он уже настроен, достаточно сохранить текущий.

```bash
sudo chown root:root /etc/cron.d/discount-tools-hdd-watch
sudo chmod 644 /etc/cron.d/discount-tools-hdd-watch
systemctl is-active crond postfix
```

Лог `/home/script/logs/hdd_size-cron.log`. Настройку logrotate при необходимости установите из `logrotate/hdd_size` в `/etc/logrotate.d/hdd_size`.

**Откат:** верните оба файла из `.before-2.1` и проверьте синтаксис. Никакие файлы базы данных и каталога `upload` не изменяются.
