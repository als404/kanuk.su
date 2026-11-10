<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Только для администраторов.');
}
if (!\Bitrix\Main\Loader::includeModule('dt.documents')) {
    die('Модуль dt.documents не установлен.');
}
use DT\Documents\Repository;
$APPLICATION->SetTitle('Документы товаров');
$type = isset($_GET['type']) && is_string($_GET['type']) ? (string)$_GET['type'] : '';
$query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$pageSize = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($pageSize, array(10, 20, 50), true)) {
    $pageSize = 20;
}
if (!in_array($type, array('', 'manual', 'document', 'article'), true)) {
    $type = '';
}
// Prevent excessively large GET parameters in URL and SQL search.
$query = function_exists('mb_substr') ? mb_substr($query, 0, 255, 'UTF-8') : substr($query, 0, 255);
$baseParams = array('lang' => LANGUAGE_ID, 'type' => $type, 'q' => $query, 'per_page' => $pageSize);
$pageUrl = function ($number, $extra = array()) use ($baseParams) {
    return 'dt_documents_list.php?'.http_build_query(array_merge($baseParams, array('page' => (int)$number), $extra));
};
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!check_bitrix_sessid()) {
            throw new \RuntimeException('Недействительная сессия.');
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
        if ($action === 'delete') {
            Repository::remove($id);
        } elseif ($action === 'enable' || $action === 'disable') {
            Repository::setActive($id, $action === 'enable');
        } else {
            throw new \RuntimeException('Неизвестная операция.');
        }
        LocalRedirect($pageUrl($page, array('changed' => 1)));
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}
try {
    $list = Repository::search($type, $query, $page, $pageSize);
    $totalPages = max(1, (int)ceil($list['total'] / $pageSize));
    if ($page > $totalPages) {
        $page = $totalPages;
        $list = Repository::search($type, $query, $page, $pageSize);
    }
} catch (\Throwable $e) {
    $list = array('items' => array(), 'total' => 0);
    $page = 1;
    $totalPages = 1;
    $error = $e->getMessage();
}
$firstItem = $list['total'] > 0 ? ($page - 1) * $pageSize + 1 : 0;
$lastItem = min($list['total'], $page * $pageSize);
$renderPager = function () use ($page, $totalPages, $pageUrl) {
    if ($totalPages <= 1) {
        return;
    }
    echo '<nav class="dtd-pagination" aria-label="Навигация по материалам">';
    if ($page > 1) {
        echo '<a href="'.Repository::escapeHtml($pageUrl(1)).'">Первая</a>';
        echo '<a href="'.Repository::escapeHtml($pageUrl($page - 1)).'">Назад</a>';
    }
    for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++) {
        if ($i === $page) {
            echo '<strong class="dtd-page-current" aria-current="page">'.$i.'</strong>';
        } else {
            echo '<a href="'.Repository::escapeHtml($pageUrl($i)).'">'.$i.'</a>';
        }
    }
    if ($page < $totalPages) {
        echo '<a href="'.Repository::escapeHtml($pageUrl($page + 1)).'">Вперёд</a>';
        echo '<a href="'.Repository::escapeHtml($pageUrl($totalPages)).'">Последняя</a>';
    }
    echo '<span>Страница '.$page.' из '.$totalPages.'</span>';
    echo '</nav>';
};
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
if ($error !== '') CAdminMessage::ShowMessage(array('MESSAGE' => $error, 'TYPE' => 'ERROR'));
if (isset($_GET['changed'])) CAdminMessage::ShowMessage(array('MESSAGE' => 'Изменения сохранены. Физические файлы не удалялись. При включённом PHP-выводе задачи очистки поставлены в очередь.', 'TYPE' => 'OK'));
?>
<style>
.dtd-filter{padding:14px;background:#f5f7f9;margin:12px 0;display:flex;gap:9px;align-items:center;flex-wrap:wrap}
.dtd-table{border-collapse:collapse;width:100%;background:#fff}
.dtd-table th,.dtd-table td{padding:9px;border:1px solid #dce0e5;text-align:left;vertical-align:top}
.dtd-table th{background:#eef2f6}
.dtd-muted{color:#6c7781;font-size:12px}
.dtd-actions{display:flex;gap:6px;flex-wrap:wrap}
.dtd-tabs{display:flex;gap:12px;flex-wrap:wrap;margin:14px 0}
.dtd-pagination{display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin:14px 0}
.dtd-pagination a,.dtd-page-current{padding:6px 10px;border:1px solid #cfd6de;background:#fff;text-decoration:none;border-radius:3px}
.dtd-pagination .dtd-page-current{background:#eef2f6;border-color:#7b8d9f}
.dtd-count{margin:12px 0;color:#4b5661}
</style>
<div class="dtd-tabs">
    <a href="dt_documents_list.php?lang=<?=LANGUAGE_ID?>">Все</a>
    <a href="dt_documents_list.php?lang=<?=LANGUAGE_ID?>&amp;type=manual">Инструкции</a>
    <a href="dt_documents_list.php?lang=<?=LANGUAGE_ID?>&amp;type=document">Сертификаты и документы</a>
    <a href="dt_documents_list.php?lang=<?=LANGUAGE_ID?>&amp;type=article">Статьи</a>
    <a href="dt_documents_import.php?lang=<?=LANGUAGE_ID?>">Импорт из db.js</a>
    <a href="dt_documents_preview.php?lang=<?=LANGUAGE_ID?>">Проверить товар</a>
    <a href="dt_documents_cache.php?lang=<?=LANGUAGE_ID?>">Очередь кеша</a>
    <a href="dt_documents_settings.php?lang=<?=LANGUAGE_ID?>">Настройки</a>
</div>
<p><a class="adm-btn adm-btn-save" href="dt_documents_edit.php?lang=<?=LANGUAGE_ID?>">Добавить материал</a></p>
<form class="dtd-filter" method="get" action="dt_documents_list.php">
    <input type="hidden" name="lang" value="<?=Repository::escapeHtml(LANGUAGE_ID)?>">
    <label>Категория:
        <select name="type">
            <?php foreach (array('' => 'Все', 'manual' => 'Инструкции', 'document' => 'Сертификаты и документы', 'article' => 'Статьи') as $value => $label): ?>
            <option value="<?=Repository::escapeHtml($value)?>" <?=$type===$value?'selected':''?>><?=Repository::escapeHtml($label)?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Название: <input type="search" name="q" value="<?=Repository::escapeHtml($query)?>" size="32"></label>
    <label>На странице:
        <select name="per_page">
            <?php foreach (array(10, 20, 50) as $size): ?>
            <option value="<?=$size?>" <?=$pageSize===$size?'selected':''?>><?=$size?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="adm-btn">Применить</button>
</form>
<p class="dtd-count">Найдено материалов: <strong><?=(int)$list['total']?></strong>.
Показано <?=(int)$firstItem?>–<?=(int)$lastItem?>. У служебных автоматических инструкций нет отдельных записей в этой таблице.</p>
<?php $renderPager(); ?>
<table class="dtd-table">
    <thead><tr><th>ID</th><th>Тип</th><th>Название / ссылка</th><th>Сорт.</th><th>Активен</th><th>Действия</th></tr></thead>
    <tbody>
    <?php foreach ($list['items'] as $item): ?>
        <tr>
            <td><?=(int)$item['ID']?></td>
            <td><?=Repository::escapeHtml($item['TYPE'])?></td>
            <td><a href="dt_documents_edit.php?lang=<?=LANGUAGE_ID?>&amp;id=<?=(int)$item['ID']?>"><?=Repository::escapeHtml($item['NAME'])?></a>
                <div class="dtd-muted"><?=Repository::escapeHtml($item['TARGET_URL'])?></div></td>
            <td><?=(int)$item['SORT_ORDER']?></td>
            <td><?=$item['ACTIVE']==='Y'?'Да':'Нет'?></td>
            <td><div class="dtd-actions">
                <a href="dt_documents_edit.php?lang=<?=LANGUAGE_ID?>&amp;id=<?=(int)$item['ID']?>">Редактировать</a>
                <form method="post">
                    <?=bitrix_sessid_post()?>
                    <input type="hidden" name="id" value="<?=(int)$item['ID']?>">
                    <input type="hidden" name="action" value="<?=$item['ACTIVE']==='Y'?'disable':'enable'?>">
                    <button type="submit"><?=$item['ACTIVE']==='Y'?'Отключить':'Включить'?></button>
                </form>
                <form method="post" onsubmit="return confirm('Удалить только запись и привязки? PDF/JPG останутся на сервере.')">
                    <?=bitrix_sessid_post()?>
                    <input type="hidden" name="id" value="<?=(int)$item['ID']?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit">Удалить</button>
                </form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$list['items']): ?><tr><td colspan="6">Записей нет</td></tr><?php endif; ?>
    </tbody>
</table>
<?php $renderPager(); ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
