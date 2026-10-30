<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

global $APPLICATION, $USER;
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Доступ разрешён только администраторам.');
}

$APPLICATION->SetTitle('XML для дилеров — BREXIT / ESSON');

$siteRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$stateDir = '/var/lib/discount-tools/dealer-xml';
$requestPath = $stateDir . '/manual.request';
$processingPath = $stateDir . '/manual.processing';
$statusPath = $stateDir . '/manual.status';
$feedback = '';
$feedbackIsError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dealer_xml_generate'])) {
    if (!check_bitrix_sessid()) {
        $feedback = 'Ошибка проверки сессии. Повторите операцию.';
        $feedbackIsError = true;
    } elseif (!is_dir($stateDir) || !is_writable($stateDir)) {
        $feedback = 'Нет доступа к очереди ручной генерации. Проверьте владельца /var/lib/discount-tools/dealer-xml.';
        $feedbackIsError = true;
    } elseif (is_file($processingPath)) {
        $feedback = 'Генерация уже выполняется. Обновите страницу через минуту.';
    } else {
        // Режим x позволяет поставить только один запрос одновременно.
        $fp = @fopen($requestPath, 'x');
        if ($fp === false) {
            $feedback = 'Запрос уже находится в очереди либо невозможно создать файл очереди.';
            $feedbackIsError = !is_file($requestPath);
        } else {
            fwrite($fp, date('c') . ' admin_id=' . (int)$USER->GetID() . "\n");
            fclose($fp);
            @chmod($requestPath, 0640);
            $feedback = 'Запрос поставлен в очередь. Запуск — ближайшей минутой через cron.';
        }
    }
}

function discountDealerXmlEscape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function discountDealerXmlFileDetails($path)
{
    clearstatcache(true, $path);
    if (!is_file($path)) {
        return 'Файл отсутствует';
    }
    $mtime = @filemtime($path);
    $size = @filesize($path);
    $parts = array();
    if ($mtime !== false) {
        $parts[] = 'Последнее изменение: ' . date('d.m.Y H:i:s', $mtime) . ' (время сервера)';
    }
    if ($size !== false) {
        $parts[] = 'Размер: ' . number_format($size / 1024 / 1024, 2, ',', ' ') . ' МБ';
    }
    return implode('; ', $parts);
}

$files = array(
    array('name' => 'BREXIT', 'file' => 'brexit.xml'),
    array('name' => 'ESSON', 'file' => 'esson.xml'),
);
$status = is_file($statusPath) ? trim((string)@file_get_contents($statusPath)) : 'Ручная генерация пока не запускалась';
$queued = is_file($requestPath);
$running = is_file($processingPath);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
?>
<div style="max-width:1050px; padding: 12px 0;">
    <h2>Файлы выгрузки для дилеров</h2>
    <table class="adm-list-table" style="width:100%;margin:12px 0 22px;">
        <thead><tr class="adm-list-table-header"><td class="adm-list-table-cell"><div class="adm-list-table-cell-inner">Дилер</div></td><td class="adm-list-table-cell"><div class="adm-list-table-cell-inner">XML-файл</div></td><td class="adm-list-table-cell"><div class="adm-list-table-cell-inner">Состояние файла</div></td></tr></thead>
        <tbody>
        <?php foreach ($files as $file): ?>
            <tr class="adm-list-table-row">
                <td class="adm-list-table-cell"><?=discountDealerXmlEscape($file['name'])?></td>
                <td class="adm-list-table-cell"><a target="_blank" rel="noopener noreferrer" href="/dealer-export/<?=discountDealerXmlEscape($file['file'])?>">https://discount-tools.ru/dealer-export/<?=discountDealerXmlEscape($file['file'])?></a></td>
                <td class="adm-list-table-cell"><?=discountDealerXmlEscape(discountDealerXmlFileDetails($siteRoot . '/dealer-export/' . $file['file']))?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Ручное обновление XML</h2>
    <?php if ($feedback !== ''): ?>
        <p style="padding:12px;background:<?= $feedbackIsError ? '#fff0f0' : '#eff8ed' ?>;border:1px solid #ccc;">
            <?=discountDealerXmlEscape($feedback)?>
        </p>
    <?php endif; ?>
    <p>Автоматическое обновление XML работает в рамках существующего почасового задания.</p>
    <p>Состояние ручного запуска: <strong><?=discountDealerXmlEscape($running ? 'выполняется' : ($queued ? 'в очереди' : $status))?></strong></p>
    <form method="post" action="">
        <?=bitrix_sessid_post()?>
        <input type="hidden" name="dealer_xml_generate" value="1">
        <input type="submit" class="adm-btn-save" value="Сформировать XML сейчас" <?= ($queued || $running) ? 'disabled="disabled"' : '' ?>>
    </form>
    <p style="margin-top:14px;color:#666;">Запрос будет выполнен отдельным процессом от пользователя сайта; страницу можно закрыть. Для обновления статуса перезагрузите её.</p>
</div>
<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
