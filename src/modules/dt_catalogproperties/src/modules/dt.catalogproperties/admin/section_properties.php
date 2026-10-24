<?php
/** Category characteristic assignment editor (iblock 16). */
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Требуются права администратора.');
}

require_once $_SERVER['DOCUMENT_ROOT'].'/local/modules/dt.catalogproperties/lib/SectionRules.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/local/modules/dt.catalogproperties/lib/CompositeCache.php';

use DT\CatalogProperties\SectionRules;

function dtcp_h($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, (defined('LANG_CHARSET') ? LANG_CHARSET : 'UTF-8'));
}

$APPLICATION->SetTitle('Характеристики категорий — инфоблок 16');
$sectionId = isset($_REQUEST['section_id']) ? (int)$_REQUEST['section_id'] : 0;
$message = '';
$error = '';

try {
    $sections = SectionRules::sections();
    if ($sectionId === 0 && !empty($sections)) {
        $sectionId = (int)$sections[0]['ID'];
    }
    if ($sectionId > 0) {
        SectionRules::assertSection($sectionId);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!check_bitrix_sessid()) {
            throw new \RuntimeException('Неверный токен сессии.');
        }
        if ($sectionId <= 0) {
            throw new \RuntimeException('Не выбран раздел.');
        }
        $action = isset($_POST['dtcp_action']) ? (string)$_POST['dtcp_action'] : '';
        switch ($action) {
            case 'add':
                SectionRules::add($sectionId, (int)$_POST['property_id']);
                $message = 'Свойство добавлено в набор.';
                break;
            case 'remove':
                SectionRules::remove($sectionId, (int)$_POST['property_id']);
                $message = 'Свойство исключено из набора. Значения товаров не изменены.';
                break;
            case 'copy_parent':
                $n = SectionRules::copyMissingFromParent($sectionId);
                $message = 'Скопировано недостающих свойств: '.$n.'. Собственные свойства сохранены.';
                break;
            case 'reorder':
                $order = isset($_POST['property_ids']) && is_array($_POST['property_ids']) ? $_POST['property_ids'] : array();
                SectionRules::setOrder($sectionId, $order);
                $message = 'Порядок сохранён.';
                break;
            default:
                throw new \RuntimeException('Неизвестная операция.');
        }

       // DT COMPOSITE CACHE
        // Очистка выполняется после успешного сохранения настроек.
        // Ограничения удаления и поддерживаемый хост проверяются CompositeCache.
        try {
            $dtDryRun = false;

            $dtHost = \DT\CatalogProperties\CompositeCache::currentHost();

            $dtCompositeReport =
                \DT\CatalogProperties\CompositeCache::invalidateSection(
                    $sectionId,
                    $dtHost,
                    $dtDryRun
                );

            $dtChecked = (int)$dtCompositeReport['checked'];
            $dtFound = (int)$dtCompositeReport['found'];
            $dtDeleted = (int)$dtCompositeReport['deleted'];
            $dtSkipped = (int)$dtCompositeReport['skipped'];

            if ($dtDryRun) {
                $message .= sprintf(
                    ' Диагностика кеша: проверено %d URL, найдено %d. Удаление отключено.',
                    $dtChecked,
                    $dtFound
                );
            } else {
                $message .= sprintf(
                    ' HTML-кеш: проверено %d URL, удалено %d страниц.',
                    $dtChecked,
                    $dtDeleted
                );
            }

            if (!empty($dtCompositeReport['errors']) || $dtSkipped > 0
                || (!$dtDryRun && $dtDeleted !== $dtFound)) {
                $message .=
                    ' Внимание: часть HTML-кеша могла остаться без обновления.';

                error_log(
                    'DT CompositeCache incomplete: section='.$sectionId.
                    ', host='.$dtHost.
                    ', found='.$dtFound.
                    ', deleted='.$dtDeleted.
                    ', skipped='.$dtSkipped.
                    ', errors='.json_encode($dtCompositeReport['errors'])
                );
            }
        } catch (\Throwable $e) {
            $message .=
                ' Внимание: настройки сохранены, но очистка HTML-кеша не выполнена. '.
                'Посетители могут временно видеть прежние характеристики.';

            error_log(
                'DT CompositeCache failure: section='.$sectionId.
                ', '.$e->getMessage()
            );
        }
    }



    // Category navigation metadata; only the new module tables are inspected.
    $configuredRows = \Bitrix\Main\Application::getConnection()
        ->query('SELECT SECTION_ID FROM dt_cp_section_config');
    $configuredSectionIds = array();
    while ($configuredRow = $configuredRows->fetch()) {
        $configuredSectionIds[(int)$configuredRow['SECTION_ID']] = true;
    }
    $sectionNames = array();
    foreach ($sections as $sectionRow) {
        $sectionNames[(int)$sectionRow['ID']] = array(
            'name' => (string)$sectionRow['NAME'],
            'parent' => (int)$sectionRow['IBLOCK_SECTION_ID']
        );
    }
    $sectionChoices = array();
    foreach ($sections as $sectionRow) {
        $path = array();
        $visited = array();
        $currentId = (int)$sectionRow['ID'];
        while ($currentId > 0 && isset($sectionNames[$currentId]) && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            array_unshift($path, $sectionNames[$currentId]['name']);
            $currentId = $sectionNames[$currentId]['parent'];
        }
        $id = (int)$sectionRow['ID'];
        $sectionChoices[] = array(
            'id' => $id,
            'name' => (string)$sectionRow['NAME'],
            'path' => implode(' → ', $path),
            'configured' => isset($configuredSectionIds[$id]),
            'parent' => (int)$sectionRow['IBLOCK_SECTION_ID']
        );
    }
    $assigned = $sectionId ? SectionRules::assigned($sectionId) : array();
    $configured = $sectionId ? SectionRules::isConfigured($sectionId) : false;
    $properties = array_values(array_filter(SectionRules::properties(), function ($p) {
        return $p['ACTIVE'] === 'Y' && preg_match('/^PROP[0-9]+$/i', (string)$p['CODE']);
    }));
} catch (\Exception $e) {
    $error = $e->getMessage();
    $assigned = array();
    $configured = false;
    $properties = array();
}

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
if ($error !== '') {
    CAdminMessage::ShowMessage(array('MESSAGE' => $error, 'TYPE' => 'ERROR'));
}
if ($message !== '') {
    CAdminMessage::ShowMessage(array('MESSAGE' => $message, 'TYPE' => 'OK'));
}
?>
<style>
.dtcp-table{border-collapse:collapse;width:100%;max-width:1100px;background:#fff}
.dtcp-table th,.dtcp-table td{padding:8px 12px;border:1px solid #ddd;text-align:left}
.dtcp-table th{background:#eee}
.dtcp-panel{padding:14px;margin:12px 0;background:#f5f7f9;max-width:1070px}
.dtcp-muted{color:#777}
.dtcp-actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
.dtcp-picker{position:relative;display:inline-block;width:390px;max-width:95vw;vertical-align:middle}
.dtcp-picker input[type=search]{width:100%;box-sizing:border-box;padding:6px 8px}
.dtcp-suggestions{display:none;position:absolute;left:0;right:0;top:100%;z-index:100;background:#fff;border:1px solid #bbb;max-height:270px;overflow:auto;box-shadow:0 4px 12px #0002}
.dtcp-suggestions button{display:block;width:100%;padding:8px 10px;text-align:left;background:#fff;border:0;cursor:pointer;white-space:normal}
.dtcp-suggestions button:hover,.dtcp-suggestions button:focus{background:#e9f4fc;outline:1px solid #8dc3e8}
.dtcp-suggestions p{padding:8px 10px;margin:0;color:#666}
.dtcp-suggestions .dtcp-show-more {
    background: #f2f6fa;
    color: #2169a3;
    text-align: center;
    font-weight: 600;
    border-top: 1px solid #ddd;
}

.dtcp-suggestions .dtcp-show-more:hover {
    background: #e3eef8;
}
/* Category tree and content panel. */
.dtcp-layout{display:grid;grid-template-columns:310px minmax(0,1fr);gap:16px;align-items:start;max-width:1500px}
.dtcp-sidebar{background:#fff;border:1px solid #d9e0e5;padding:12px;position:sticky;top:12px;min-width:0}
.dtcp-sidebar input[type=search]{box-sizing:border-box;width:100%;padding:8px}
.dtcp-sidebar-help{margin:8px 0;font-size:12px;color:#666}
.dtcp-tree{max-height:75vh;overflow:auto;overflow-x:hidden;padding:3px 0}
.dtcp-tree-row{display:flex;align-items:center;min-height:30px;gap:2px;border-radius:3px}
.dtcp-tree-row.is-current{background:#e5f3ff}
.dtcp-tree-toggle{flex:0 0 24px;border:0;background:transparent;cursor:pointer;padding:5px 2px;font-size:14px}
.dtcp-tree-link{flex:1;min-width:0;display:block;padding:5px 3px;text-decoration:none;color:#283b48;overflow-wrap:anywhere}
.dtcp-tree-link:hover{text-decoration:underline}
.dtcp-tree-mark{color:#19804b;font-weight:bold;margin-right:4px;white-space:nowrap}
.dtcp-tree-empty{padding:12px;color:#777}
.dtcp-main{min-width:0}
.dtcp-current-path{font-size:13px;color:#576670;margin:0 0 7px}
.dtcp-panel{max-width:none}
@media(max-width:920px){.dtcp-layout{grid-template-columns:1fr}.dtcp-sidebar{position:static}.dtcp-tree{max-height:300px}}
</style>
<div class="dtcp-layout">
<aside class="dtcp-sidebar" aria-label="Дерево категорий">
    <strong>Категории каталога</strong>
    <p class="dtcp-sidebar-help">Раскрывайте разделы стрелкой или ищите по названию и ID. ✓ — набор настроен.</p>
    <input type="search" id="dtcp-tree-search" placeholder="Поиск категории или ID" autocomplete="off" aria-label="Поиск по дереву категорий">
    <div class="dtcp-tree" id="dtcp-tree" role="tree"></div>
</aside>
<main class="dtcp-main">
<div class="dtcp-panel">
    <strong>Выбранная категория</strong>
    <p class="dtcp-current-path"><?php foreach ($sectionChoices as $choice) { if ($choice['id'] === $sectionId) { echo dtcp_h($choice['path'].' (#'.$choice['id'].')'); break; } } ?></p>
    <p class="dtcp-muted">Состояние: <?= $configured ? 'набор настроен ('.count($assigned).' свойств)' : 'набор ещё не настроен' ?>. Изменения касаются только назначений категории, не товаров.</p>
</div>
<?php if ($sectionId > 0): ?>
<div class="dtcp-panel dtcp-actions">
    <form method="post" action="?section_id=<?= $sectionId ?>">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="dtcp_action" value="copy_parent">
        <button type="submit">Скопировать недостающие из родителя</button>
    </form>
    <form method="post" action="?section_id=<?= $sectionId ?>">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="dtcp_action" value="add">
        <label for="dtcp-property">Добавить свойство:</label>
        <div class="dtcp-picker" id="dtcp-picker">
            <input id="dtcp-filter" type="search" placeholder="Введите часть названия, PROP-код или ID" autocomplete="off" aria-label="Поиск характеристики" aria-controls="dtcp-suggestions" aria-expanded="false" required>
            <input type="hidden" id="dtcp-property" name="property_id" value="">
            <div id="dtcp-suggestions" class="dtcp-suggestions" role="listbox"></div>
        </div>
        <button type="submit">Добавить</button>
    </form>
</div>
<h3>Характеристики текущей категории</h3>
<?php if (empty($assigned)): ?>
    <p>Набор пуст. Добавьте свойства или скопируйте их из родителя.</p>
<?php else: ?>
    <p class="dtcp-muted">Для изменения порядка впишите последовательные номера (например, 100, 200, 300) либо используйте кнопки вверх/вниз на следующем этапе. Здесь доступно сохранение порядка через номера строк.</p>
    <form id="dtcp-order" method="post" action="?section_id=<?= $sectionId ?>">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="dtcp_action" value="reorder">
        <table class="dtcp-table">
            <thead><tr><th>№</th><th>Название</th><th>Код</th><th>ID</th><th>Статус</th><th>Операция</th></tr></thead>
            <tbody>
            <?php foreach ($assigned as $index => $p): ?>
                <tr>
                    <td><input type="number" class="dtcp-position" data-id="<?= (int)$p['PROPERTY_ID'] ?>" value="<?= $index + 1 ?>" min="1" style="width:60px"></td>
                    <td><?= dtcp_h($p['NAME']) ?></td><td><?= dtcp_h($p['CODE']) ?></td><td><?= (int)$p['PROPERTY_ID'] ?></td>
                    <td><?= $p['ACTIVE'] === 'Y' ? 'Активно' : 'Неактивно' ?></td>
                    <td><button type="submit" form="dtcp-remove-<?= (int)$p['PROPERTY_ID'] ?>" onclick="return confirm('Исключить из набора категории? Значения товаров останутся без изменений.')">Исключить</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div id="dtcp-order-hidden"></div>
        <p><button type="submit">Сохранить порядок</button></p>
    </form>
    <?php foreach ($assigned as $p): ?>
        <form id="dtcp-remove-<?= (int)$p['PROPERTY_ID'] ?>" method="post" action="?section_id=<?= $sectionId ?>">
            <?= bitrix_sessid_post() ?><input type="hidden" name="dtcp_action" value="remove"><input type="hidden" name="property_id" value="<?= (int)$p['PROPERTY_ID'] ?>">
        </form>
    <?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<script>
(() => {
    const tree = document.getElementById('dtcp-tree');
    const input = document.getElementById('dtcp-tree-search');

    if (!tree || !input) return;

    const sections = <?= json_encode(
        $sectionChoices,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE |
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;

    const selectedId = <?= (int)$sectionId ?>;
    const byId = {};
    const children = {};
    const expanded = {};
    const storageKey = 'dtcp_tree_expanded_16';

    const normalize = value =>
        String(value).toLocaleLowerCase().replace(/ё/g, 'е').trim();

    sections.forEach(section => {
        byId[section.id] = section;
        children[section.id] = [];
    });

    sections.forEach(section => {
        const parent = byId[section.parent] ? section.parent : 0;
        (children[parent] || (children[parent] = [])).push(section);
    });

    // Раскрываем цепочку родителей выбранной категории.
    let ancestor = byId[selectedId];
    let guard = 0;

    while (ancestor && guard++ < 10) {
        if (ancestor.parent) {
            expanded[ancestor.parent] = true;
        }

        ancestor = byId[ancestor.parent];
    }

    // Восстанавливаем раскрытые ветки.
    try {
        const saved = JSON.parse(
            sessionStorage.getItem(storageKey) || 'null'
        );

        if (Array.isArray(saved)) {
            saved.forEach(id => {
                if (byId[id]) expanded[id] = true;
            });
        }
    } catch (error) {
        // Хранилище браузера может быть недоступно.
    }

    const remember = () => {
        try {
            const ids = Object.keys(expanded)
                .filter(id => expanded[id])
                .map(Number);

            sessionStorage.setItem(storageKey, JSON.stringify(ids));
        } catch (error) {
            // Сохранение состояния необязательно.
        }
    };

    const draw = () => {
        const query = normalize(input.value);
        const terms = query.split(/\s+/).filter(Boolean);
        const visible = {};

        if (terms.length) {
            sections.forEach(section => {
                const haystack = normalize(
                    `${section.name} ${section.path} ${section.id}`
                );

                const matches = terms.every(word =>
                    haystack.includes(word)
                );

                if (!matches) return;

                let current = section;
                let depth = 0;

                while (current && depth++ < 10) {
                    visible[current.id] = true;
                    current = byId[current.parent];
                }
            });
        }

        tree.textContent = '';
        let count = 0;

        const walk = (list, depth) => {
            list.forEach(section => {
                if (terms.length && !visible[section.id]) return;

                count++;

                const row = document.createElement('div');
                row.className = `dtcp-tree-row${
                    section.id === selectedId ? ' is-current' : ''
                }`;

                row.style.paddingLeft = `${Math.min(depth, 5) * 15}px`;
                row.setAttribute('role', 'treeitem');
                row.setAttribute(
                    'aria-selected',
                    String(section.id === selectedId)
                );

                const kids = children[section.id] || [];
                const isOpen = terms.length > 0 || !!expanded[section.id];

                if (kids.length) {
                    const toggle = document.createElement('button');

                    toggle.type = 'button';
                    toggle.className = 'dtcp-tree-toggle';
                    toggle.textContent = isOpen ? '▾' : '▸';
                    toggle.title = isOpen ? 'Свернуть' : 'Раскрыть';

                    toggle.setAttribute(
                        'aria-label',
                        `${toggle.title} ${section.name}`
                    );

                    toggle.setAttribute('aria-expanded', String(isOpen));

                    toggle.addEventListener('click', () => {
                        expanded[section.id] = !expanded[section.id];
                        remember();
                        draw();
                    });

                    row.appendChild(toggle);
                } else {
                    const spacer = document.createElement('span');
                    spacer.style.flex = '0 0 24px';
                    row.appendChild(spacer);
                }

                const link = document.createElement('a');

                link.className = 'dtcp-tree-link';
                link.href = `?section_id=${encodeURIComponent(section.id)}`;
                link.textContent = `${section.name} (#${section.id})`;
                link.title = section.path;

                if (section.configured) {
                    const mark = document.createElement('span');

                    mark.className = 'dtcp-tree-mark';
                    mark.textContent = '✓ ';
                    mark.title = 'Набор настроен';

                    link.insertBefore(mark, link.firstChild);
                }

                row.appendChild(link);
                tree.appendChild(row);

                if (kids.length && isOpen) {
                    walk(kids, depth + 1);
                }
            });
        };

        walk(children[0] || [], 0);

        if (!count) {
            const message = document.createElement('p');

            message.className = 'dtcp-tree-empty';
            message.textContent = 'Категории не найдены';

            tree.appendChild(message);
        }
    };

    input.addEventListener('input', draw);
    draw();
})();
</script>

<script>
(() => {
    const picker = document.getElementById('dtcp-picker');

    if (!picker) return;

    const input = document.getElementById('dtcp-filter');
    const selected = document.getElementById('dtcp-property');
    const results = document.getElementById('dtcp-suggestions');

    const all = <?= json_encode(
        array_map(static function ($property) {
            $name = html_entity_decode(
                (string)$property['NAME'],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );

            return [
                'id' => (int)$property['ID'],
                'name' => $name,
                'label' => $name.' ['.$property['CODE'].'] #'.$property['ID']
            ];
        }, $properties),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE |
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;

    const normalize = value =>
        String(value).toLocaleLowerCase().replace(/ё/g, 'е').trim();

    const close = () => {
        results.style.display = 'none';
        input.setAttribute('aria-expanded', 'false');
    };

    // Ранжирование найденных характеристик.
    const getPriority = (property, query) => {
        const name = normalize(property.name);

        if (name === query) return 0;
        if (name.startsWith(query)) return 1;
        if (name.includes(query)) return 2;

        return 3;
    };

    const PAGE_SIZE = 15;
    let visibleCount = PAGE_SIZE;
    let previousQuery = '';

    const render = () => {
        const query = normalize(input.value);
        if (query !== previousQuery) {
            visibleCount = PAGE_SIZE;
            previousQuery = query;
        }
        const words = query.split(/\s+/).filter(Boolean);

        results.textContent = '';
        selected.value = '';

        if (!words.length) {
            close();
            return;
        }

        const matches = all
            .filter(property => {
                const haystack = normalize(property.label);

                return words.every(word => haystack.includes(word));
            })
            .sort((a, b) => {
                const difference =
                    getPriority(a, query) - getPriority(b, query);

                return difference ||
                    a.name.localeCompare(b.name, 'ru');
            });

        matches.slice(0, visibleCount).forEach(property => {
            const button = document.createElement('button');

            button.type = 'button';
            button.textContent = property.label;
            button.setAttribute('role', 'option');

            button.addEventListener('click', () => {
                input.value = property.label;
                selected.value = property.id;
                close();
            });

            results.appendChild(button);
        });

        if (!matches.length) {
            const message = document.createElement('p');
            message.textContent = 'Совпадений нет';
            results.appendChild(message);
        } else if (matches.length > visibleCount) {
            const remaining = matches.length - visibleCount;

            const moreButton = document.createElement('button');
            moreButton.type = 'button';
            moreButton.className = 'dtcp-show-more';
            moreButton.textContent =
                `Показать ещё (${remaining} осталось)`;

            moreButton.addEventListener('click', () => {
                visibleCount += PAGE_SIZE;
                render();
            });

            results.appendChild(moreButton);
        }

        results.style.display = 'block';
        input.setAttribute('aria-expanded', 'true');
    };

    input.addEventListener('input', () => {
        input.setCustomValidity('');
        render();
    });

    input.addEventListener('focus', () => {
        if (input.value && !selected.value) render();
    });

    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            close();
            return;
        }

        if (
            event.key === 'ArrowDown' &&
            results.style.display === 'block'
        ) {
            const first = results.querySelector('button');

            if (first) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    results.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            close();
            input.focus();
        }
    });

    document.addEventListener('click', event => {
        if (!picker.contains(event.target)) close();
    });

    picker.closest('form').addEventListener('submit', event => {
        if (!selected.value) {
            event.preventDefault();

            input.setCustomValidity(
                'Выберите характеристику из результатов поиска.'
            );

            input.reportValidity();
        }
    });
})();

// Сохранение порядка характеристик.
(() => {
    const form = document.getElementById('dtcp-order');

    if (!form) return;

    form.addEventListener('submit', () => {
        const container = document.getElementById('dtcp-order-hidden');

        container.textContent = '';

        const positions = [
            ...document.querySelectorAll('.dtcp-position')
        ];

        positions.sort((a, b) =>
            (parseInt(a.value, 10) || 0) -
            (parseInt(b.value, 10) || 0)
        );

        positions.forEach(item => {
            const hidden = document.createElement('input');

            hidden.type = 'hidden';
            hidden.name = 'property_ids[]';
            hidden.value = item.dataset.id;

            container.appendChild(hidden);
        });
    });
})();
</script>

</main>
</div>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php'; ?>
