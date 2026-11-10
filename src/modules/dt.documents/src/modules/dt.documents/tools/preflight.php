<?php
/** CLI-only read-only deployment preflight. No DB writes and no cache invalidation. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
$root = dirname(__DIR__, 4);
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['HTTP_HOST'] = 'discount-tools.ru';
if (!defined('NO_KEEP_STATISTIC')) define('NO_KEEP_STATISTIC', true);
if (!defined('NOT_CHECK_PERMISSIONS')) define('NOT_CHECK_PERMISSIONS', true);
if (!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
if (!defined('BX_CAT_CRON')) define('BX_CAT_CRON', true);
if (!is_file($root.'/bitrix/modules/main/include/prolog_before.php')) {
    fwrite(STDERR, "Bitrix prolog not found in ".$root."\n");
    exit(2);
}
require_once $root.'/bitrix/modules/main/include/prolog_before.php';
echo "php=".PHP_VERSION."\n";
echo "site_charset=".(defined('LANG_CHARSET') ? LANG_CHARSET : 'unknown')."\n";
echo "module_installed=".(\Bitrix\Main\ModuleManager::isModuleInstalled('dt.documents') ? 'Y' : 'N')."\n";
$db = \Bitrix\Main\Application::getConnection();
foreach (array('dt_doc_document','dt_doc_brand','dt_doc_article','dt_doc_section','dt_doc_cache_task') as $table) {
    echo 'table_'.$table.'='.($db->isTableExists($table) ? 'Y' : 'N')."\n";
}
$host = \Bitrix\Main\Config\Option::get('dt.documents', 'cache_host', '');
echo 'cache_host_configured='.($host !== '' ? 'Y' : 'N')."\n";
$htmlRoot = $root.'/bitrix/html_pages';
if (is_dir($htmlRoot)) {
    $hosts = array();
    foreach (scandir($htmlRoot) as $name) {
        if ($name[0] === '.' || !is_dir($htmlRoot.'/'.$name)) continue;
        $hosts[] = $name;
    }
    echo 'html_cache_directories='.implode(',', $hosts)."\n";
} else {
    echo "html_cache_directories=(directory missing)\n";
}
