<?php
function cfg() { static $x; if ($x === null) $x = require dirname(__DIR__).'/config.php'; return $x; }
function runtimeDir() { return dirname(__DIR__).'/runtime'; }
function logLine($s) { $line=date('c').' '.$s."\n"; file_put_contents(runtimeDir().'/sync.log', $line, FILE_APPEND|LOCK_EX); echo $line; }
function getLock() { $f=fopen(runtimeDir().'/sync.lock','c'); if (!$f || !flock($f,LOCK_EX|LOCK_NB)) throw new RuntimeException('Another synchronization is running'); return $f; }
function connectDb() {
    $c=cfg(); $p=$c['document_root'].'/bitrix/.settings.php';
    if (!is_file($p)) throw new RuntimeException('Bitrix database settings not found: '.$p);
    $settings=include $p; $d=$settings['connections']['value']['default'] ?? null;
    if (!$d) throw new RuntimeException('Database connection config missing');
    $host=$d['host'] ?? 'localhost'; $dbname=$d['database'] ?? '';
    $dsn='mysql:host='.$host.';dbname='.$dbname.';charset=utf8mb4';
    $pdo=new PDO($dsn, $d['login'], $d['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
function normalizedNumber($raw, $integer=false) {
    $s=trim((string)$raw); if ($s==='') return null;
    // Удаляем обозначение рублей и пробельные символы
    $s = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $s);
    $s = preg_replace('/\s*р\.?\s*$/ui', '', $s);

    // Заменяем десятичную запятую на точку
    $s = str_replace(',', '.', $s);
    $s=preg_replace('/[\s\x{00A0}\x{202F}₽ррубRUB]+/ui','',$s);
    $s=str_replace(',', '.', $s);
    if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $s)) throw new InvalidArgumentException('Invalid number: '.(string)$raw);
    if ($integer && (float)$s < 0) return 0;
    if ($integer && !preg_match('/^\d+$/D',$s)) throw new InvalidArgumentException('Stock must be integer: '.(string)$raw);
    return $integer ? (int)$s : round((float)$s, 0, PHP_ROUND_HALF_UP);
}
function rowsFromCsvString($csv, $separator=',') {
    $handle=fopen('php://temp','w+'); fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $csv)); rewind($handle);
    $rows=[]; while (($r=fgetcsv($handle, 0, $separator))!==false) { $rows[]=$r; if (count($rows)>cfg()['max_rows']) throw new RuntimeException('Too many rows'); }
    fclose($handle); return $rows;
}
function fetchGoogle($brand) {
    $c=cfg(); $b=$c['brands'][$brand]; $cache=runtimeDir().'/google_'.strtolower($brand).'.json';
    if ($c['google_cache_ttl']>0 && is_file($cache) && filemtime($cache)+$c['google_cache_ttl']>time()) {
        $parsed=json_decode(file_get_contents($cache),true); if (is_array($parsed)) return $parsed;
    }
    $url='https://docs.google.com/spreadsheets/d/'.rawurlencode($c['spreadsheet_id']).'/export?format=csv&gid='.rawurlencode($b['gid']).'&range='.rawurlencode($b['range']);
    $context=stream_context_create(['http'=>['timeout'=>$c['google_timeout'],'follow_location'=>1,'max_redirects'=>4,'user_agent'=>'CostUpdate/1.0']]);
    $body=@file_get_contents($url,false,$context);
    if ($body===false || $body==='') throw new RuntimeException('Google export failed for '.$brand);
    $rows=rowsFromCsvString($body); if (count($rows)<2) throw new RuntimeException('Google sheet appears empty: '.$brand);
    file_put_contents($cache.'.tmp',json_encode($rows,JSON_UNESCAPED_UNICODE),LOCK_EX); rename($cache.'.tmp',$cache);
    return $rows;
}
function extractRows($rawRows, $indexes, $brand) {
    $result=[]; $bad=0; $dupes=[];
    foreach ($rawRows as $r) {
        $sku=trim((string)($r[$indexes['article_index']] ?? ''));
        if (
            $sku === '' ||
            mb_stripos($sku, 'артикул') !== false
        ) {
            continue;
        }
        try {
            $price=normalizedNumber($r[$indexes['price_index']] ?? '');
            $qty=normalizedNumber($r[$indexes['stock_index']] ?? '',true);
        } catch (InvalidArgumentException $e) { $bad++; logLine('SKIP '.$brand.' '.$sku.': '.$e->getMessage()); continue; }
        if ($price===null && $qty===null) continue;
        if (isset($result[$sku])) {
            $dupes[$sku] = true;
        }

        $result[$sku] = ['price' => $price, 'qty' => $qty];
    }
    if ($dupes) {
        logLine('WARNING Duplicate articles in '.$brand.
            ' (last row used): '.
            implode(', ', array_keys($dupes)));
    }
    logLine('Parsed '.$brand.': '.count($result).' usable rows; '.$bad.' invalid rows');
    return $result;
}

function getBrandId(PDO $db, $brand) {
    // Ручной CSV: бренд выбран по ID из инфоблока 14
    if (preg_match('/^MANUAL:([1-9][0-9]*)$/D', (string)$brand, $m)) {
        $brandId = (int)$m[1];

        $stmt = $db->prepare(
            'SELECT ID FROM b_iblock_element
             WHERE ID = ? AND IBLOCK_ID = ? AND ACTIVE = ?
             LIMIT 1'
        );

        $stmt->execute([
            $brandId,
            cfg()['brands_iblock_id'],
            'Y'
        ]);

        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new RuntimeException('Бренд не найден или отключён');
        }

        return (int)$id;
    }

    // Google Sheets: прежняя логика BREXIT и ESSON
    $stmt = $db->prepare(
        'SELECT ID FROM b_iblock_element
         WHERE IBLOCK_ID = ? AND ACTIVE = ? AND CODE = ?
         LIMIT 2'
    );

    $stmt->execute([
        cfg()['brands'][$brand]['brand_iblock_id']
            ?? cfg()['brands_iblock_id'],
        'Y',
        cfg()['brands'][$brand]['code']
    ]);

    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($ids) !== 1) {
        throw new RuntimeException(
            'Brand code not uniquely found: ' . $brand
        );
    }

    return (int)$ids[0];
}

function findProducts(PDO $db, $articleList, $brandId) {
    $c=cfg(); $matched=[];
    foreach (array_chunk($articleList,350) as $chunk) {
        $pl=implode(',',array_fill(0,count($chunk),'?'));
        $sql='SELECT DISTINCT p.VALUE AS sku, e.ID AS id, cp.QUANTITY AS qty, cp.AVAILABLE AS available, pr.PRICE AS price, pr.CURRENCY AS currency, pr.ID AS price_id '
           .'FROM b_iblock_element_property p JOIN b_iblock_element e ON e.ID=p.IBLOCK_ELEMENT_ID '
           .'JOIN b_iblock_16_index fi ON fi.ELEMENT_ID=e.ID AND fi.FACET_ID=? AND fi.VALUE=? '
           .'JOIN b_catalog_product cp ON cp.ID=e.ID '
           .'LEFT JOIN b_catalog_price pr ON pr.PRODUCT_ID=e.ID AND pr.CATALOG_GROUP_ID=? '
           .'WHERE e.IBLOCK_ID=? AND p.IBLOCK_PROPERTY_ID=? AND p.VALUE IN ('.$pl.')';
        $s=$db->prepare($sql); $s->execute(array_merge([$c['brand_facet_id'],(string)$brandId,$c['price_group_id'],$c['iblock_id'],$c['article_property_id']],$chunk));
        while ($row=$s->fetch()) { $matched[$row['sku']][]=$row; }
    }
    return $matched;
}
function runImport(PDO $db, $brand, $rows, $dryRun=true) {
    $c=cfg(); if ($c['price_group_id']===null) throw new RuntimeException('Set price_group_id in config.php first');
    $brandId=getBrandId($db,$brand); $matched=findProducts($db,array_keys($rows),$brandId);
    $stat=['rows'=>count($rows),'missing'=>0,'ambiguous'=>0,'price'=>0,'stock'=>0,'unchanged'=>0,'cache_ids'=>[]];
    $setPrice=$db->prepare('UPDATE b_catalog_price SET PRICE=?, TIMESTAMP_X=NOW() WHERE ID=?');
    $setStock=$db->prepare('UPDATE b_catalog_product SET QUANTITY=?, AVAILABLE=? WHERE ID=?');
    foreach ($rows as $sku=>$values) {
        $found=$matched[$sku] ?? [];
        if (!$found) { $stat['missing']++; continue; }
        if (count($found)!==1) { $stat['ambiguous']++; logLine('AMBIGUOUS '.$brand.' '.$sku); continue; }
        $p=$found[0]; $id=(int)$p['id'];
        $priceChange=$values['price']!==null && ($p['price']===null || abs((float)$p['price']-$values['price'])>0.005);
        $qtyChange=$values['qty']!==null && ((int)$p['qty']!==$values['qty'] || $p['available']!==($values['qty']>0?'Y':'N'));
        if ($priceChange && $p['price_id']===null) { logLine('NO_PRICE_ROW '.$brand.' '.$sku.' ID='.$id); $priceChange=false; $stat['ambiguous']++; }
        if ($priceChange && $p['currency']!==$c['currency']) { logLine('CURRENCY_MISMATCH '.$brand.' '.$sku); $priceChange=false; $stat['ambiguous']++; }
        if (!$priceChange && !$qtyChange) { $stat['unchanged']++; continue; }
        // Подробный вывод изменений только в режиме DRY-RUN
        if ($dryRun) {
            if ($priceChange) {
                logLine(
                    'PRICE ' . $brand .
                    ' | SKU=' . $sku .
                    ' | ID=' . $id .
                    ' | OLD=' . ($p['price'] ?? 'NULL') .
                    ' | NEW=' . $values['price']
                );
            }

            if ($qtyChange) {
                logLine(
                    'STOCK ' . $brand .
                    ' | SKU=' . $sku .
                    ' | ID=' . $id .
                    ' | OLD=' . $p['qty'] .
                    ' | NEW=' . $values['qty']
                );
            }
        }
        if (!$dryRun) {
            $db->beginTransaction();
            try {
                if ($priceChange) $setPrice->execute([$values['price'],$p['price_id']]);
                if ($qtyChange) $setStock->execute([$values['qty'],$values['qty']>0?'Y':'N',$id]);
                $db->commit();
            } catch (Throwable $e) { $db->rollBack(); throw $e; }
        }
        if ($priceChange) $stat['price']++;
        if ($qtyChange) $stat['stock']++;
        $stat['cache_ids'][$id]=$id;
    }
    return $stat;
}
function saveQueue($ids) {
    $path=runtimeDir().'/pending_cache.json'; $current=[];
    if (is_file($path)) $current=json_decode(file_get_contents($path),true) ?: [];
    $combined=array_values(array_unique(array_merge(array_map('intval',$current),array_map('intval',$ids))));
    $tmp=$path.'.tmp'; file_put_contents($tmp,json_encode($combined),LOCK_EX); rename($tmp,$path);
}
