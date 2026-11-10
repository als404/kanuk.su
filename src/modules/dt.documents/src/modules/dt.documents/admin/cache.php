<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
if (!$USER->IsAdmin()) $APPLICATION->AuthForm('Только для администраторов.');
if (!\Bitrix\Main\Loader::includeModule('dt.documents')) die('Модуль dt.documents не установлен.');
use DT\Documents\CacheQueue;
use DT\Documents\CacheWorker;
use DT\Documents\CachePurger;
use DT\Documents\Repository;
$APPLICATION->SetTitle('Очередь обновления кеша документов');
$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!check_bitrix_sessid()) throw new \RuntimeException('Сессия истекла.');
        $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
        if ($action === 'step') {
            $result = CacheWorker::runOnce();
            $notice = 'Результат: '.$result['status'].'; задание #'.$result['task'].'; товаров: '.$result['processed'].'; HTML-файлов: '.$result['deleted_files'].'.';
        } elseif ($action === 'retry') {
            CacheQueue::retry(isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0);
            $notice = 'Повторная обработка задания разрешена.';
        } else {
            throw new \RuntimeException('Неизвестная операция.');
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}
try {
    $stats = CacheQueue::stats();
    $recent = CacheQueue::recent(50);
} catch (\Throwable $e) {
    $stats = null;
    $recent = array();
    $error = $e->getMessage().'. Для обновления с 0.1.0 сначала создать только новую таблицу по миграции.';
}
$rootInfo = '';
try {
    $rootInfo = CachePurger::htmlCacheRoot();
} catch (\Throwable $e) {
    $rootInfo = 'Не настроено: '.$e->getMessage();
}
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
if ($error) CAdminMessage::ShowMessage(array('MESSAGE'=>$error,'TYPE'=>'ERROR'));
if ($notice) CAdminMessage::ShowMessage(array('MESSAGE'=>$notice,'TYPE'=>'OK'));
?>
<p><a href="dt_documents_list.php">Материалы</a> · <a href="dt_documents_settings.php">Настройки</a></p>
<p>Сохранение материала не блокируется на тысячи карточек: затронутые товары обрабатываются пакетами не более <?=CacheWorker::MAX_ITEMS?> за запуск. Задания со статусом «Ошибка» сохраняются до повторной обработки.</p>
<p><b>Композитный кеш:</b> <?=Repository::escapeHtml($rootInfo)?></p>
<?php if ($stats): ?>
<p>В очереди: <b><?=(int)$stats['N']?></b> · Завершено: <?=(int)$stats['D']?> · Ошибок: <b><?=(int)$stats['E']?></b> · Обработано товарных привязок: <?=(int)$stats['processed']?></p>
<form method="post" style="margin:16px 0">
    <?=bitrix_sessid_post()?>
    <input type="hidden" name="action" value="step">
    <button class="adm-btn adm-btn-save" type="submit" <?=$stats['N'] < 1 ? 'disabled' : ''?>>Обработать один пакет (до <?=CacheWorker::MAX_ITEMS?> товаров)</button>
</form>
<table class="adm-list-table" style="max-width:1200px;width:100%">
<thead><tr><th>ID</th><th>Материал</th><th>Вид</th><th>Условие</th><th>Статус</th><th>Товаров</th><th>Последний ID</th><th>Ошибка / действие</th></tr></thead>
<tbody>
<?php foreach ($recent as $task): ?>
<tr>
<td><?=(int)$task['ID']?></td><td><?=(int)$task['DOCUMENT_ID']?></td>
<td><?=Repository::escapeHtml($task['KIND'])?></td>
<td><?=Repository::escapeHtml($task['KIND']==='S' ? '#'.$task['SECTION_ID'] : ($task['KIND']==='A' ? $task['BRAND'] : 'тег каталога'))?></td>
<td><?=Repository::escapeHtml(array('N'=>'Ожидает','D'=>'Готово','E'=>'Ошибка')[$task['STATUS']] ?? $task['STATUS'])?></td>
<td><?=(int)$task['PROCESSED']?></td><td><?=(int)$task['LAST_ELEMENT_ID']?></td>
<td><?=Repository::escapeHtml($task['ERROR_TEXT'])?>
<?php if ($task['STATUS']==='E'): ?>
<form method="post"><?=bitrix_sessid_post()?>
<input type="hidden" name="action" value="retry"><input type="hidden" name="task_id" value="<?=(int)$task['ID']?>">
<button type="submit">Повторить</button></form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$recent): ?><tr><td colspan="8">Заданий нет</td></tr><?php endif; ?>
</tbody>
</table>
<?php endif;
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
