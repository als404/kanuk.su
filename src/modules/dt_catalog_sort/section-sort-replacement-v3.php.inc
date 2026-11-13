<?php
//SORT//
// Default: fixed ASC within manufacturer/availability groups.
// Price: all products by effective price, no brand or availability priority.
$arAvailableSort = array(
    'default' => array('order' => 'asc'),
    'price'   => array('order' => 'asc'),
    'rating'  => array('order' => 'desc'),
);

$sortMode = $APPLICATION->get_cookie('dt_catalog_sort_mode');
if (!is_string($sortMode) || !isset($arAvailableSort[$sortMode])) {
    $sortMode = 'default';
}

$sortOrder = $APPLICATION->get_cookie('dt_catalog_sort_order');
if ($sortOrder !== 'asc' && $sortOrder !== 'desc') {
    $sortOrder = $arAvailableSort[$sortMode]['order'];
}

$requestedSort = isset($_GET['sort']) && is_string($_GET['sort'])
    ? $_GET['sort'] : null;
$requestedOrder = isset($_GET['order']) && is_string($_GET['order'])
    ? $_GET['order'] : null;

if ($requestedSort !== null && isset($arAvailableSort[$requestedSort])) {
    $sortMode = $requestedSort;
    $sortOrder = $arAvailableSort[$sortMode]['order'];
    if ($sortMode !== 'default' && ($requestedOrder === 'asc' || $requestedOrder === 'desc')) {
        $sortOrder = $requestedOrder;
    }
    $APPLICATION->set_cookie('dt_catalog_sort_mode', $sortMode, false, '/', SITE_SERVER_NAME);
    $APPLICATION->set_cookie('dt_catalog_sort_order', $sortOrder, false, '/', SITE_SERVER_NAME);
} elseif ($sortMode === 'default') {
    $sortOrder = 'asc';
}

if ($sortMode === 'default') {
    $sortOrder = 'asc';
}
?>
<div class="catalog-item-sorting">
    <label><span class="full"><?=Loc::getMessage('SECT_SORT_LABEL_FULL')?></span><span class="short"><?=Loc::getMessage('SECT_SORT_LABEL_SHORT')?></span>:</label>
    <?php foreach ($arAvailableSort as $key => $settings):
        $selected = $sortMode === $key;
        $className = $selected ? 'selected ' . $sortOrder : '';
        $newOrder = $key === 'default' ? 'asc' : (
            $selected ? ($sortOrder === 'asc' ? 'desc' : 'asc') : $settings['order']
        );
        $link = $APPLICATION->GetCurPageParam(
            'sort=' . $key . '&order=' . $newOrder,
            array('sort', 'order', 'PAGEN_1')
        );
    ?>
        <a href="<?=htmlspecialcharsbx($link)?>" class="<?=$className?>" rel="nofollow"><?=Loc::getMessage('SECT_SORT_' . $key)?></a>
    <?php endforeach; ?>
</div>
