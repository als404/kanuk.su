<?php
/** CLI only. Dry-run by default. Applies one additive schema change only on explicitly named host. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
$root = realpath(__DIR__.'/../../../../');
if (!$root || !is_file($root.'/bitrix/modules/main/include/prolog_before.php')) {
    fwrite(STDERR, "Invalid site root.\n");
    exit(1);
}
$host = (string)gethostname();
$apply = in_array('--apply', $argv, true);
$allowedHost = '--host='.$host;
if ($apply && !in_array($allowedHost, $argv, true)) {
    fwrite(STDERR, "Refused: use --apply --host=".$host." (and verify DB backup first).\n");
    exit(2);
}
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['HTTP_HOST'] = $host === 'discount-tools-test' ? 'vmtest.discount-tools.ru' : 'discount-tools.ru';
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('NOT_CHECK_PERMISSIONS', true);
require $root.'/bitrix/modules/main/include/prolog_before.php';
if (!\Bitrix\Main\ModuleManager::isModuleInstalled('dt.documents')) {
    fwrite(STDERR, "Module is not registered.\n");
    exit(3);
}
$db = \Bitrix\Main\Application::getConnection();
if (!$db->isTableExists('dt_doc_document')) {
    fwrite(STDERR, "dt_doc_document is missing.\n");
    exit(4);
}
$dbName = $db->query('SELECT DATABASE() AS DB')->fetch();
$column = $db->query("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT FROM information_schema.COLUMNS ".
    "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dt_doc_document' AND COLUMN_NAME='MATCH_MODE'")->fetch();
echo 'SERVER: '.$host."\n";
echo 'DATABASE: '.($dbName ? $dbName['DB'] : 'unknown')."\n";
echo 'ROWS: '.$db->queryScalar('SELECT COUNT(*) FROM dt_doc_document')."\n";
echo 'MATCH_MODE: '.($column ? 'PRESENT' : 'ABSENT')."\n";
if ($column) {
    if ($column['DATA_TYPE'] !== 'varchar' || $column['COLUMN_DEFAULT'] !== 'legacy') {
        fwrite(STDERR, "Existing MATCH_MODE column has unexpected definition. Stop.\n");
        exit(5);
    }
    echo "Already migrated.\n";
    exit(0);
}
if (!$apply) {
    echo "DRY RUN: no changes. For migration use --apply --host=".$host."\n";
    exit(0);
}
$db->queryExecute("ALTER TABLE dt_doc_document ADD COLUMN MATCH_MODE VARCHAR(24) NOT NULL DEFAULT 'legacy' AFTER TYPE");
echo "MIGRATION: APPLIED\n";
$check = $db->query("SELECT MATCH_MODE, COUNT(*) AS CNT FROM dt_doc_document GROUP BY MATCH_MODE");
while ($row = $check->fetch()) {
    echo $row['MATCH_MODE'].': '.$row['CNT']."\n";
}
