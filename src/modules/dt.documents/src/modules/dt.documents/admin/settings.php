<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
if (!$USER->IsAdmin()) $APPLICATION->AuthForm('Только для администраторов.');
if (!\Bitrix\Main\Loader::includeModule('dt.documents')) die('Модуль dt.documents не установлен.');
use Bitrix\Main\Config\Option;
use DT\Documents\CacheAgent;
use DT\Documents\Repository;
$APPLICATION->SetTitle('Настройки dt.documents');
$error = '';
$saved = false;
$warning = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!check_bitrix_sessid()) throw new \RuntimeException('Ошибка проверки сессии.');
        $host = trim((string)(isset($_POST['cache_host']) ? $_POST['cache_host'] : ''));
        if ($host !== '' && (strlen($host) > 253 || !preg_match('/^[a-z0-9.-]+$/i', $host) || strpos($host, '..') !== false)) {
            throw new \RuntimeException('Неверный домен композитного кеша.');
        }
        $agent = isset($_POST['cache_agent']) ? 'Y' : 'N';
        if ($agent === 'Y' && $host === '') {
            throw new \RuntimeException('Для автоочистки сначала укажите проверенный домен HTML-кеша.');
        }
        $previousEnabled = Option::get('dt.documents', 'public_enabled', 'N');
        $previousManual = Option::get('dt.documents', 'auto_manual', 'Y');
        $enabled = isset($_POST['public_enabled']) ? 'Y' : 'N';
        $manual = isset($_POST['auto_manual']) ? 'Y' : 'N';
        Option::set('dt.documents', 'cache_host', $host);
        Option::set('dt.documents', 'cache_agent', $agent);
        Option::set('dt.documents', 'public_enabled', $enabled);
        Option::set('dt.documents', 'auto_manual', $manual);
        if ($agent === 'Y') {
            \CAgent::RemoveAgent(CacheAgent::EXPRESSION, 'dt.documents');
            \CAgent::AddAgent(CacheAgent::EXPRESSION, 'dt.documents', 'N', 60, '', 'Y');
        } else {
            \CAgent::RemoveAgent(CacheAgent::EXPRESSION, 'dt.documents');
        }
        if ($previousEnabled !== $enabled || $previousManual !== $manual) {
            $warning = 'Изменены глобальные правила вывода. Очередь документов не покрывает массовое переключение старого JS на PHP и смену автоматических инструкций. Необходим отдельный согласованный сброс кеша каталога.';
        }
        $saved = true;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}
$enabled = Option::get('dt.documents', 'public_enabled', 'N') === 'Y';
$manual = Option::get('dt.documents', 'auto_manual', 'Y') === 'Y';
$host = Option::get('dt.documents', 'cache_host', '');
$agent = Option::get('dt.documents', 'cache_agent', 'N') === 'Y';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
if ($error) CAdminMessage::ShowMessage(array('MESSAGE'=>$error,'TYPE'=>'ERROR'));
if ($saved) CAdminMessage::ShowMessage(array('MESSAGE'=>'Настройки сохранены.','TYPE'=>'OK'));
if ($warning) CAdminMessage::ShowMessage(array('MESSAGE'=>$warning,'TYPE'=>'ERROR'));
?>
<p><a href="dt_documents_list.php">К материалам</a> · <a href="dt_documents_cache.php">Очередь кеша</a></p>
<form method="post" style="padding:20px;background:#fff;border:1px solid #dce0e5;max-width:850px">
<?=bitrix_sessid_post()?>
<p><label><input type="checkbox" name="public_enabled" value="Y" <?=$enabled?'checked':''?>> Включить PHP-вывод документов в карточках</label></p>
<p style="color:#586672">Не включать до интеграции шаблона и отключения старого JavaScript. Установка сама ничего не переключает.</p>
<p><label><input type="checkbox" name="auto_manual" value="Y" <?=$manual?'checked':''?>> Автоматическая инструкция по производителю и артикулу</label></p>
<p><label>Домен папки композитного кеша (без https:// и путей):<br>
<input type="text" name="cache_host" value="<?=Repository::escapeHtml($host)?>" placeholder="discount-tools.ru" size="45"></label></p>
<p style="color:#586672">Перед настройкой проверьте фактическую папку в /bitrix/html_pages/. Модуль не использует серверные секреты и не очищает папки другого домена.</p>
<p><label><input type="checkbox" name="cache_agent" value="Y" <?=$agent?'checked':''?>> Автоматически обрабатывать очередь агентом Битрикс каждые 60 секунд (до 25 товаров за запуск)</label></p>
<p style="color:#586672">По умолчанию выключено. Для крупных категорий может потребоваться отдельный cron и контроль очереди. При неуспехе задания переходят в «Ошибка».</p>
<p><button class="adm-btn adm-btn-save" type="submit">Сохранить</button></p>
</form>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
