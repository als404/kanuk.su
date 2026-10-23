<?php
// This is deliberately a Bitrix-only phase, called only if changes have been committed.
function clearChangedCache(array $ids) {
    $c=cfg();
    $root=rtrim($c['document_root'],'/');
    $base=$root.'/bitrix/html_pages/'.$c['html_cache_domain'].rtrim($c['catalog_path'],'/').'/';
    $baseReal=realpath($base);
    if ($baseReal === false) {
        logLine('CACHE: HTML cache directory absent, skipping: ' . $base);
    }
    $paths=[];
    // Главная страница каталога
    $paths['']=true;
    foreach ($ids as $id) {
        $id=(int)$id;
        $item=CIBlockElement::GetByID($id)->Fetch();
        if (!$item || (int)$item['IBLOCK_ID']!==(int)$c['iblock_id']) { logLine('CACHE: not found ID '.$id); continue; }
        $code=$item['CODE'];
        $groups=CIBlockElement::GetElementGroups($id,true,['ID','CODE','IBLOCK_SECTION_ID']);
        while ($group=$groups->Fetch()) {
            $sectionId=(int)$group['ID'];
            $sectionCodes=[]; $cursor=$sectionId; $guard=0;
            while ($cursor && $guard++<40) {
                $rs=CIBlockSection::GetList([],['ID'=>$cursor],false,['ID','CODE','IBLOCK_SECTION_ID']);
                $section=$rs->Fetch(); if (!$section) break;
                array_unshift($sectionCodes,$section['CODE']); $cursor=(int)$section['IBLOCK_SECTION_ID'];
            }
            if (!$sectionCodes) continue;
            // Nested catalog path + section and ancestors; also compatibility with former flat section cache paths.
            for ($i=1;$i<=count($sectionCodes);$i++) {
                $paths[implode('/',array_slice($sectionCodes,0,$i)).'/']=true;
                $paths[$sectionCodes[$i-1].'/']=true;
            }
            $paths[implode('/',$sectionCodes).'/'.$code.'/']=true;
            $paths[end($sectionCodes).'/'.$code.'/']=true;
        }
    }
    $deleted=0;
    foreach (array_keys($paths) as $relative) {
        $relative=trim($relative,'/');
        if (strpos($relative,'..')!==false || strpos($relative,'\\')!==false) continue;
        $dir = ($baseReal !== false) ? realpath($baseReal.'/'.$relative) : false;
        if (
            $dir === false ||
            ($dir !== $baseReal && strpos($dir, $baseReal . '/') !== 0)
        ) {
            continue;
        }
        foreach (glob($dir.'/index@*.html') ?: [] as $file) { if (is_file($file) && @unlink($file)) $deleted++; }
    }
    // Preserve original tagged-cache behavior, once per entire batch instead of per product.
    $tagged=\Bitrix\Main\Application::getInstance()->getTaggedCache();
    $tagged->clearByTag('iblock_id_'.$c['iblock_id']);
    foreach ($ids as $id) $tagged->clearByTag('element_'.(int)$id);
    logLine('CACHE cleared pages='.$deleted.' items='.count($ids));
}
