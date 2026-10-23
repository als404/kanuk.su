<?php
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
require_once __DIR__.'/lib/runner.php';
$dryRun=!in_array('--apply',$argv,true);
try {
    if (!$dryRun && cfg()['price_group_id']===null) throw new RuntimeException('Configure price_group_id before --apply');
    $inputs=[];
    foreach (cfg()['brands'] as $brand=>$settings) {
        $inputs[$brand]=extractRows(fetchGoogle($brand),$settings,$brand);
    }
    runBrands($inputs,$dryRun);
} catch (Throwable $e) { logLine('ERROR '.$e->getMessage()); fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
