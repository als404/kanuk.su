# Протокол испытаний V4.4 на 247 — 13.11.2026

Источники: выводы SSH и скриншоты, присланные в переписке. Числа относятся **только** к тестовому серверу `discount-tools-test`.

- `cost_update` V4.3, ручной CSV: `2100089` / `ID=5238`, цена `500→600`, `sort_queued=1`, DRY-RUN не изменял БД (`sort_queued=0`).
- После импорта очередь: `queued=1`, `--preview`: `WOULD_UPDATE GROUP=10 PRICE=600.00 ZERO=0 CURRENT_PRICE=500.00`.
- Сортировщик обработал задачу: `queued=0`, `UNCHANGED GROUP=10 PRICE=600.00 ZERO=0 CURRENT_PRICE=600.00`.
- V4.4, контрольный тест: `--enqueue --ids=5238` + `--run --queue=1 --sweep=0` → `selected=1,changed=0,failed=0,cache_deleted=1`; затем `queued=0`, `cached_files=0`.
- Выявлены каталоги HTML-кеша с `755`; cron от `als` не имел права удаления, ошибку диагностика фиксировала, очередь оставалась `queued=1`.
- Cron перенесён на владельца кеша `discount`. По последнему отчёту: `QUEUE {"selected":1,"changed":1,"failed":0,"cache_deleted":1}`, `STATUS {"queued":0,"sweep_cursor":2140}`, `ID=5238 STATUS=UNCHANGED GROUP=10 PRICE=600.00 ZERO=0 CURRENT_GROUP='10' CURRENT_PRICE='600.00' CURRENT_ZERO='0'`.
- Скриншот инкогнито: товар `2100089` за 600 ₽ расположен перед товарами BREXIT 1785/2231 ₽, порядок соответствует выбранному режиму.

Итог: **проверена** цепочка CSV → очередь → cron (пользователь `discount`) → индекс → удаление HTML-кеша → отображение. **Не проверено:** production, расписание скидок с изменением только по времени, новое имя домена/хостинга, нестандартные пути/разделы.
