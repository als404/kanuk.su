# Фиксация dt.documents в отдельном Git-репозитории

Папка Git-репозитория **не совпадает** с `/var/www/discount-tools/public`. Все команды `git` выполнять из **локальной папки репозитория в VSCode**, а не в SSH на production.

## До копирования

```bash
git rev-parse --show-toplevel
git status --short
git remote -v
git branch --show-current
```

Если есть несохранённые изменения, сначала разберите их; не перезаписывайте существующие исходники вслепую. Создайте отдельную ветку:

```bash
git switch -c feature/dt-documents-0.1.2-public
```

## Перенос пакета

1. Откройте **именно локальную папку репозитория** в VSCode, распакуйте туда Git-пакет с сохранением структуры.
2. Сверьте `src/modules/dt.documents/` с production. Пакет составлен из релизных архивов и исправлений, а не загружен непосредственно с production.
3. Сверьте **по файлу** `src/addition_include/addition_include.js` с уже существующим в репозитории `src/addition_include/`. Путь на production: `/upload/doc/addition_include.js`. Не удаляйте другие файлы папки.
4. Через VSCode добавьте полные версии трёх изменённых шаблонов сайта в принятую структуру исходников вашего проекта: родительский `catalog/.default/element.php`, дочерний `catalog.element/.default/template.php`, `catalog/index.php`. Фрагменты в `snippets/` — только справочный материал.
5. Не добавляйте в Git `.settings.php`, секреты, SQL dump, `/bitrix/cache`, `/bitrix/html_pages`, пользовательские PDF/превью, резервные копии и журналы.

## Проверка и фиксация

```bash
git diff --check
git status --short
# При наличии совместимого PHP:
# find src/modules/dt.documents -type f -name '*.php' -print0 | xargs -0 -n 1 php -d short_open_tag=1 -l

git add src/modules/dt.documents src/addition_include/addition_include.js docs tests snippets README.md
git diff --cached --stat
git diff --cached --check
```

**Три изменённых файла шаблонов сайта добавьте отдельно** по их фактическим путям в вашем локальном репозитории. Просмотрите `git diff --cached` и удостоверьтесь, что нет служебных файлов.

```bash
git commit -m "feat(dt.documents): snapshot 0.1.2 public integration and roadmap"
git push -u origin feature/dt-documents-0.1.2-public
```

Выполнение `git commit` и `git push` здесь не производилось: SSH к серверу и ваш локальный VSCode доступны только вам; подключение GitHub к чату пока не подтверждено.

**План следующей разработки**: `docs/ROADMAP.md` — в том числе `Активен: Все/Да/Нет`; изменений production в этом пакете нет.
