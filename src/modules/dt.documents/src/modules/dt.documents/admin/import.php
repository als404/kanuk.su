<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
if (!$USER->IsAdmin()) $APPLICATION->AuthForm('Только для администраторов.');
if (!\Bitrix\Main\Loader::includeModule('dt.documents')) die('Модуль dt.documents не установлен.');
use DT\Documents\Repository;
use DT\Documents\LegacyImporter;
$APPLICATION->SetTitle('Импорт материалов из db.js');
$error = '';
$message = '';
try {
    $preview = LegacyImporter::preview();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!check_bitrix_sessid()) throw new \RuntimeException('Ошибка проверки сессии.');
        if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'IMPORT') {
            throw new \RuntimeException('Для импорта введите IMPORT в поле подтверждения.');
        }
        if ($preview['missing_pdf'] || $preview['missing_image']) {
            if (empty($_POST['accept_missing'])) {
                throw new \RuntimeException('Найдены отсутствующие файлы. Подтвердите сохранение старых ссылок.');
            }
        }
        $result = LegacyImporter::import();
        $message = 'Импортировано: '.$result['inserted'].' из '.$result['total'].'. Уже импортированные записи не изменены.';
        $preview = LegacyImporter::preview();
    }
} catch (\Throwable $e) {
    $preview = null;
    $error = $e->getMessage();
}
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
if ($error) CAdminMessage::ShowMessage(array('MESSAGE'=>$error,'TYPE'=>'ERROR'));
if ($message) CAdminMessage::ShowMessage(array('MESSAGE'=>$message,'TYPE'=>'OK'));
?>
<p><a href="dt_documents_list.php">К списку материалов</a></p>
<p>Источник: <code>/upload/doc/db.js</code>. Импорт выполняется из проверенного JSON-снимка, включённого в модуль. JavaScript не выполняется на сервере.</p>
<p>Сначала проверяется SHA-256 рабочего файла: <code><?=Repository::escapeHtml(LegacyImporter::EXPECTED_SHA256)?></code>.</p>
<p>Служебный шаблон автоматических инструкций не переносится в таблицу: новый модуль воспроизводит это правило отдельно.</p>
<?php if ($preview): ?>
<table class="adm-list-table" style="max-width:780px">
    <tr><td>Всего документов/статей</td><td><?=(int)$preview['total']?></td></tr>
    <tr><td>Готовы к импорту</td><td><?=(int)$preview['ready']?></td></tr>
    <tr><td>Уже перенесены</td><td><?=(int)$preview['already']?></td></tr>
    <tr><td>Отсутствуют PDF по старым путям</td><td><?=count($preview['missing_pdf'])?></td></tr>
    <tr><td>Отсутствуют превью по старым путям</td><td><?=count($preview['missing_image'])?></td></tr>
    <tr><td>Ссылки на статьи или внешние страницы</td><td><?=(int)$preview['external_or_pages']?></td></tr>
</table>
<?php if ($preview['missing_pdf'] || $preview['missing_image']): ?>
    <p><b>Предупреждение:</b> импорт не удаляет материалы с отсутствующими файлами. Проверьте пути, чтобы исключить битые ссылки.</p>
    <?php foreach (array('missing_pdf'=>'PDF', 'missing_image'=>'Превью') as $key=>$title): ?>
        <?php if ($preview[$key]): ?>
            <details><summary>Нет файла: <?=$title?> (<?=count($preview[$key])?>)</summary>
                <ul><?php foreach (array_slice($preview[$key], 0, 100) as $entry): ?>
                    <li>#<?=(int)$entry['id']?>: <?=Repository::escapeHtml($entry['path'])?></li>
                <?php endforeach; ?></ul>
                <?php if (count($preview[$key]) > 100): ?>Показаны первые 100.<?php endif; ?>
            </details>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
<form method="post" style="margin-top:24px;padding:18px;border:1px solid #dce0e5;max-width:780px;background:#fff">
    <?=bitrix_sessid_post()?>
    <p>Импортируются только отсутствующие записи с LEGACY_ID 1–48. <b>Файлы PDF/JPG не перемещаются, каталог товаров не изменяется.</b></p>
    <p><label>Подтверждение (введите IMPORT): <input type="text" name="confirm" autocomplete="off"></label></p>
    <?php if ($preview['missing_pdf'] || $preview['missing_image']): ?>
        <p><label><input type="checkbox" name="accept_missing" value="1"> Сохранить старые URL даже для отсутствующих файлов</label></p>
    <?php endif; ?>
    <p><button type="submit" class="adm-btn adm-btn-save" <?=$preview['ready']===0?'disabled':''?>>Импортировать отсутствующие записи</button></p>
</form>
<?php endif; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
