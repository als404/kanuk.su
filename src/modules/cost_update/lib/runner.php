<?php
require_once __DIR__.'/common.php';
function runBrands(array $inputs, $dryRun) {
    $lock=getLock();
    try {
        $db=connectDb(); $all=[]; $changed=[];
        // Queue each brand after commit so a later failure does not lose its cache work.
        foreach ($inputs as $brand=>$rows) {
            $result=runImport($db,$brand,$rows,$dryRun);
            $changed=array_merge($changed,array_values($result['cache_ids']));
            if (!$dryRun && $result['cache_ids']) saveQueue(array_values($result['cache_ids']));
            unset($result['cache_ids']);
            $all[$brand]=$result;
            logLine(($dryRun?'DRY-RUN ':'UPDATED ').$brand.' '.json_encode($result,JSON_UNESCAPED_UNICODE));
        }
        if (!$dryRun) drainCacheQueue();
        return $all;
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
function drainCacheQueue() {
    $path=runtimeDir().'/pending_cache.json';
    if (!is_file($path)) return;
    $ids=json_decode(file_get_contents($path),true);
    if (!is_array($ids) || !$ids) return;
    $c=cfg(); $root=rtrim($c['document_root'],'/');
    $_SERVER['DOCUMENT_ROOT']=$root;
    if (!defined('NO_KEEP_STATISTIC')) define('NO_KEEP_STATISTIC',true);
    if (!defined('NOT_CHECK_PERMISSIONS')) define('NOT_CHECK_PERMISSIONS',true);
    if (!defined('BX_CAT_CRON')) define('BX_CAT_CRON',true);
    if (!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK',true);
    if (!defined('SITE_ID')) define('SITE_ID',$c['site_id']);
    require_once $root.'/bitrix/modules/main/include/prolog_before.php';
    if (!CModule::IncludeModule('iblock')) throw new RuntimeException('Bitrix iblock unavailable');
    require_once __DIR__.'/cache.php';
    clearChangedCache($ids);
    // Remove the queue ONLY when all cache operations were successful.
    unlink($path);
}
