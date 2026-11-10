<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Только для администраторов.');
}
if (!\Bitrix\Main\Loader::includeModule('dt.documents')) {
    die('Модуль dt.documents не установлен.');
}
use DT\Documents\Repository;
use DT\Documents\CatalogSections;
use DT\Documents\Upload;
use DT\Documents\ProductSearch;
$id = isset($_GET['id']) ? max(0, (int)$_GET['id']) : 0;
$APPLICATION->SetTitle($id ? 'Редактирование материала #'.$id : 'Новый материал');
$error = '';
$existing = $id ? Repository::get($id) : null;
if ($id && !$existing) {
    $error = 'Материал не найден.';
}
$defaults = array('TYPE'=>'document','MATCH_MODE'=>'brand_article','NAME'=>'','LINK_TEXT'=>'','LINK_TITLE'=>'','LINK_ALT'=>'',
    'TARGET_URL'=>'','IMAGE_URL'=>'','ACTIVE'=>'Y','SORT_ORDER'=>500,
    'BRANDS'=>array(), 'ARTICLES'=>array(), 'SECTIONS'=>array());
$record = $existing ? array_merge($defaults, $existing) : $defaults;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $record = array_merge($record, array(
        'TYPE'=>isset($_POST['type']) ? $_POST['type'] : '',
        'MATCH_MODE'=>isset($_POST['match_mode']) ? $_POST['match_mode'] : '',
        'NAME'=>isset($_POST['name']) ? $_POST['name'] : '',
        'LINK_TEXT'=>isset($_POST['link_text']) ? $_POST['link_text'] : '',
        'LINK_TITLE'=>isset($_POST['link_title']) ? $_POST['link_title'] : '',
        'LINK_ALT'=>isset($_POST['link_alt']) ? $_POST['link_alt'] : '',
        'TARGET_URL'=>isset($_POST['target_url']) ? $_POST['target_url'] : '',
        'IMAGE_URL'=>isset($_POST['image_url']) ? $_POST['image_url'] : '',
        'ACTIVE'=>isset($_POST['active']) ? 'Y' : 'N',
        'SORT_ORDER'=>isset($_POST['sort']) ? $_POST['sort'] : 500,
        'BRANDS'=>isset($_POST['brands']) ? $_POST['brands'] : '',
        'ARTICLES'=>isset($_POST['articles']) ? $_POST['articles'] : '',
    ));
    try {
        if (!check_bitrix_sessid()) {
            throw new \RuntimeException('Сессия истекла.');
        }
        $sectionsJson = isset($_POST['sections_json']) ? $_POST['sections_json'] : '[]';
        $sections = json_decode((string)$sectionsJson, true);
        if (!is_array($sections) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Некорректный список выбранных разделов.');
        }
        $record['SECTIONS'] = $sections;
        $pdf = Upload::accept('pdf_file', 'document');
        $image = Upload::accept('image_file', 'image');
        if ($pdf !== null) {
            $record['TARGET_URL'] = $pdf;
        }
        if ($image !== null) {
            $record['IMAGE_URL'] = $image;
        }
        $savedId = Repository::save($record, $id);
        LocalRedirect('dt_documents_edit.php?id='.$savedId.'&saved=1');
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}
$knownBrands = array();
try {
    $knownBrands = ProductSearch::brands();
} catch (\Throwable $e) {
    $error = $e->getMessage();
}
try {
    $catalogSections = CatalogSections::all();
} catch (\Throwable $e) {
    $catalogSections = array();
    $error = $e->getMessage();
}
$sectionsJson = \DT\Documents\Encoding::json($record['SECTIONS']);
$sectionData = \DT\Documents\Encoding::json($catalogSections);
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
if ($error !== '') CAdminMessage::ShowMessage(array('MESSAGE'=>$error,'TYPE'=>'ERROR'));
if (isset($_GET['saved'])) CAdminMessage::ShowMessage(array('MESSAGE'=>'Материал сохранён. При включённом PHP-выводе очередь кеша обновлена.','TYPE'=>'OK'));
?>
<style>
.dtd-form{max-width:1050px;background:#fff;border:1px solid #d9e0e5;padding:20px}
.dtd-field{margin-bottom:14px}
.dtd-field label{display:block;font-weight:bold;margin-bottom:5px}
/* Input padding must not be applied to native select: Bitrix sets a compact height. */
.dtd-field input[type=text],.dtd-field input[type=url],.dtd-field textarea{width:100%;max-width:800px;box-sizing:border-box;padding:7px}
#dtd-edit-form .dtd-field select{display:block;width:100%;max-width:800px;box-sizing:border-box;height:36px!important;min-height:36px;padding:0 10px!important;line-height:normal!important;font-size:14px;vertical-align:middle}
.dtd-field textarea{min-height:90px}
.dtd-help{font-size:12px;color:#65727d;margin:4px 0}
.dtd-choice{border:1px solid #d4dce3;background:#fbfcfd;padding:12px;max-width:800px}
.dtd-choice input[type=search]{width:100%;box-sizing:border-box;padding:8px}
.dtd-results{max-height:290px;overflow-y:auto;background:#fff;border:1px solid #dce0e5;margin-top:6px}
.dtd-results button{display:block;width:100%;text-align:left;border:none;background:white;cursor:pointer;padding:8px;border-bottom:1px solid #eef1f3}
.dtd-results button:hover,.dtd-results button:focus{background:#eaf5fd}
.dtd-selected{padding:0;list-style:none}
.dtd-selected li{margin:7px 0;padding:7px;background:#f2f7fc}
.dtd-selected label{display:inline;font-weight:normal}
.dtd-selected button{float:right}
</style>
<p><a href="dt_documents_list.php">К списку материалов</a></p>
<form method="post" enctype="multipart/form-data" class="dtd-form" id="dtd-edit-form">
    <?=bitrix_sessid_post()?>
    <div class="dtd-field">
        <label for="dtd-type">Категория материала</label>
        <select name="type" id="dtd-type">
            <?php foreach (array('manual'=>'Инструкция','document'=>'Сертификат / документ','article'=>'Статья') as $v=>$label): ?>
                <option value="<?=$v?>" <?=$record['TYPE']===$v?'selected':''?>><?=$label?></option>
            <?php endforeach; ?>
        </select>
        <p class="dtd-help">Группы всегда отображаются в порядке: инструкции / документы / статьи.</p>
    </div>
    <div class="dtd-field"><label for="dtd-name">Название *</label>
        <input type="text" name="name" id="dtd-name" maxlength="255" required value="<?=Repository::escapeHtml($record['NAME'])?>"></div>
    <div class="dtd-field"><label for="dtd-text">Текст ссылки</label>
        <input type="text" name="link_text" id="dtd-text" value="<?=Repository::escapeHtml($record['LINK_TEXT'])?>"></div>
    <div class="dtd-field"><label for="dtd-title">Title</label>
        <input type="text" name="link_title" id="dtd-title" value="<?=Repository::escapeHtml($record['LINK_TITLE'])?>"></div>
    <div class="dtd-field"><label for="dtd-alt">Alt превью</label>
        <input type="text" name="link_alt" id="dtd-alt" value="<?=Repository::escapeHtml($record['LINK_ALT'])?>"></div>
    <div class="dtd-field"><label for="dtd-target">URL PDF или страницы *</label>
        <input type="text" name="target_url" id="dtd-target" value="<?=Repository::escapeHtml($record['TARGET_URL'])?>">
        <p class="dtd-help">Пример: /upload/doc/other/file.pdf или /reviews/name/. Можно оставить пустым, если загружается новый PDF.</p>
        <input type="file" name="pdf_file" accept=".pdf,application/pdf"><p class="dtd-help">Новый файл сохраняется в /upload/doc/dt_documents/. До 20 МБ.</p>
    </div>
    <div class="dtd-field"><label for="dtd-image">URL превью (JPG/PNG/WebP)</label>
        <input type="text" name="image_url" id="dtd-image" value="<?=Repository::escapeHtml($record['IMAGE_URL'])?>">
        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
    </div>
    <div class="dtd-field"><label for="dtd-sort">Сортировка внутри категории</label>
        <input id="dtd-sort" type="number" name="sort" value="<?=(int)$record['SORT_ORDER']?>"></div>
    <div class="dtd-field"><label><input type="checkbox" name="active" value="Y" <?=$record['ACTIVE']==='Y'?'checked':''?>> Активен</label></div>
    <h3>Привязка к товарам</h3>
    <div class="dtd-field">
        <label for="dtd-mode">Правило применения материала *</label>
        <select name="match_mode" id="dtd-mode">
            <?php foreach (array(
                'brand_article'=>'Только выбранные артикулы указанного производителя',
                'brand'=>'Все товары выбранного производителя',
                'section'=>'Все товары выбранного раздела (с подразделами по выбору)',
                'brand_section'=>'Только товары производителя в выбранном разделе',
                'legacy'=>'Старое правило импортированной базы (производитель+артикул ИЛИ раздел)'
            ) as $mode=>$label): ?>
            <option value="<?=Repository::escapeHtml($mode)?>" <?=$record['MATCH_MODE']===$mode?'selected':''?>><?=Repository::escapeHtml($label)?></option>
            <?php endforeach; ?>
        </select>
        <p class="dtd-help">Пример: статья для всех BREXIT — «Все товары производителя». Инструкция для ремонтных муфт BREXIT — «Производитель в разделе» + раздел «Муфты ремонтные» с подразделами. Эти условия объединяются через И.</p>
        <?php if (!empty($record['LEGACY_ID'])): ?><p class="dtd-help"><b>Импортированная запись:</b> старый режим сохраняется, пока вы явно не выберете другой. При смене режима не относящиеся к нему привязки будут удалены из этой записи.</p><?php endif; ?>
    </div>
    <div class="dtd-field" id="dtd-brand-block">
        <label for="dtd-brands">Производители (по одному в строке)</label>
        <p class="dtd-help">Регистр не влияет на привязку: BREXIT, Brexit и brexit считаются одним производителем.</p>
        <textarea id="dtd-brands" name="brands" rows="4"><?=Repository::escapeHtml(is_array($record['BRANDS'])?implode("\n",$record['BRANDS']):$record['BRANDS'])?></textarea>
        <p class="dtd-help">Для точного совпадения используйте название из связанного инфоблока производителей.</p>
        <label for="dtd-brand-picker">Добавить производителя из каталога</label>
        <select id="dtd-brand-picker"><option value="">— выбрать производителя —</option>
        <?php foreach ($knownBrands as $name): ?><option value="<?=Repository::escapeHtml($name)?>"><?=Repository::escapeHtml($name)?></option><?php endforeach; ?>
        </select>
        <button type="button" id="dtd-add-brand">Добавить</button>
    </div>
    <div class="dtd-field" id="dtd-article-block"><label for="dtd-articles">Точные артикулы (по одному в строке)</label>
        <textarea id="dtd-articles" name="articles" rows="9"><?=Repository::escapeHtml(is_array($record['ARTICLES'])?implode("\n",$record['ARTICLES']):$record['ARTICLES'])?></textarea>
        <p class="dtd-help">В этой привязке артикулы точные, с сохранением ведущих нулей. Маски 4000_25 используются только в поиске товаров, не как правило для будущих товаров.</p></div>
    <div class="dtd-field dtd-choice" id="dtd-section-block">
        <label for="dtd-search">Поиск разделов каталога (инфоблок 16)</label>
        <input type="search" id="dtd-search" autocomplete="off" placeholder="Поиск по названию, полному пути или ID">
        <div id="dtd-results" class="dtd-results" aria-label="Результаты поиска"></div>
        <h4>Выбранные разделы</h4>
        <ul id="dtd-selected" class="dtd-selected"></ul>
        <input type="hidden" name="sections_json" id="dtd-sections-json" value="<?=Repository::escapeHtml($sectionsJson)?>">
        <p class="dtd-help">Для каждого раздела отдельно можно включить его подразделы. Выбор не сбрасывается при новом поиске.</p>
    </div>
    <p><button class="adm-btn adm-btn-save" type="submit">Сохранить материал</button></p>
</form>
<script>
(function () {
    'use strict';
    var sections = <?=$sectionData ?: '[]'?>;
    var initial = <?=$sectionsJson ?: '[]'?>;
    var search = document.getElementById('dtd-search');
    var results = document.getElementById('dtd-results');
    var selectedBox = document.getElementById('dtd-selected');
    var hidden = document.getElementById('dtd-sections-json');
    var form = document.getElementById('dtd-edit-form');
    var byId = {};
    var selected = {};
    sections.forEach(function (s) { byId[s.id] = s; });
    initial.forEach(function (s) {
        if (byId[s.id]) selected[s.id] = {id:s.id,children:s.children !== false};
    });
    function normalize(v) {
        return String(v).toLocaleLowerCase().replace(/ё/g, 'е').trim();
    }
    function sync() {
        hidden.value = JSON.stringify(Object.keys(selected).map(function (k) { return selected[k]; }));
    }
    function redrawSelected() {
        selectedBox.textContent = '';
        Object.keys(selected).forEach(function (k) {
            var s = byId[k];
            var li = document.createElement('li');
            var label = document.createElement('span');
            label.textContent = s.path + ' (#' + s.id + ') ';
            li.appendChild(label);
            var scope = document.createElement('label');
            var check = document.createElement('input');
            check.type = 'checkbox';
            check.checked = selected[k].children;
            check.addEventListener('change', function () { selected[k].children = check.checked; sync(); });
            scope.appendChild(check);
            scope.appendChild(document.createTextNode(' включая подразделы'));
            li.appendChild(scope);
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'Исключить';
            remove.addEventListener('click', function () { delete selected[k]; sync(); redrawSelected(); redrawResults(); });
            li.appendChild(remove);
            selectedBox.appendChild(li);
        });
        if (!Object.keys(selected).length) {
            var li = document.createElement('li');
            li.textContent = 'Разделы не выбраны';
            selectedBox.appendChild(li);
        }
        sync();
    }
    function redrawResults() {
        var value = normalize(search.value);
        var terms = value.split(/\s+/).filter(Boolean);
        results.textContent = '';
        if (!terms.length) {
            var empty = document.createElement('p');
            empty.className = 'dtd-help';
            empty.textContent = 'Введите название, ID или часть пути раздела.';
            results.appendChild(empty);
            return;
        }
        var matches = sections.filter(function (s) {
            var haystack = normalize(s.name+' '+s.path+' '+s.id);
            return terms.every(function (w) { return haystack.indexOf(w) !== -1; });
        });
        matches.sort(function (a,b) {
            var an = normalize(a.name), bn = normalize(b.name);
            var rank = function (n) { return n === value ? 0 : n.indexOf(value) === 0 ? 1 : 2; };
            return rank(an)-rank(bn) || an.localeCompare(bn,'ru');
        });
        matches.slice(0,40).forEach(function (s) {
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = (selected[s.id] ? '[x] ' : '+ ') + s.path + ' (#' + s.id + ')';
            button.addEventListener('click', function () {
                if (selected[s.id]) delete selected[s.id];
                else selected[s.id] = {id:s.id,children:true};
                redrawSelected(); redrawResults();
            });
            results.appendChild(button);
        });
        var summary = document.createElement('p');
        summary.className = 'dtd-help';
        summary.textContent = 'Найдено: '+matches.length+'. Показаны первые 40. Уточните запрос для остальных.';
        results.appendChild(summary);
    }
    var mode = document.getElementById('dtd-mode');
    var brandBlock = document.getElementById('dtd-brand-block');
    var articleBlock = document.getElementById('dtd-article-block');
    var sectionBlock = document.getElementById('dtd-section-block');
    function refreshMode() {
        var choice = mode.value;
        brandBlock.style.display = choice === 'section' ? 'none' : '';
        articleBlock.style.display = (choice === 'legacy' || choice === 'brand_article') ? '' : 'none';
        sectionBlock.style.display = (choice === 'legacy' || choice === 'section' || choice === 'brand_section') ? '' : 'none';
    }
    mode.addEventListener('change', refreshMode);
    document.getElementById('dtd-add-brand').addEventListener('click', function () {
        var picker = document.getElementById('dtd-brand-picker');
        var textarea = document.getElementById('dtd-brands');
        var name = picker.value;
        if (!name) return;
        var names = textarea.value.split(/\r?\n/).map(function (v) { return v.trim(); }).filter(Boolean);
        if (!names.some(function (v) { return normalize(v) === normalize(name); })) {
            names.push(name);
        }
        textarea.value = names.join('\n');
    });
    search.addEventListener('input', redrawResults);
    form.addEventListener('submit', sync);
    redrawSelected();
    redrawResults();
    refreshMode();
}());
</script>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
