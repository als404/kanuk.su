<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
if (!$USER->IsAdmin()) $APPLICATION->AuthForm('Только для администраторов.');
if (!\Bitrix\Main\Loader::includeModule('dt.documents') || !\Bitrix\Main\Loader::includeModule('iblock')) {
    die('Требуются dt.documents и iblock.');
}
use DT\Documents\Repository;
use DT\Documents\Resolver;
use DT\Documents\CatalogSections;
use DT\Documents\ProductSearch;

$APPLICATION->SetTitle('Проверка привязок по товару');
$article = isset($_GET['article']) && is_string($_GET['article']) ? trim($_GET['article']) : '';
$brandId = isset($_GET['brand_id']) ? max(0, (int)$_GET['brand_id']) : 0;
$productId = isset($_GET['product_id']) ? max(0, (int)$_GET['product_id']) : 0;
// Use a module-specific variable: Bitrix admin prologs may overwrite $page.
$dtdPreviewPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$searchActive = $article !== '' || $brandId > 0;
$brands = array();
$results = array('total'=>0,'items'=>array());
$product = null;
$items = array();
$sectionIds = array();
$error = '';
try {
    $brands = ProductSearch::brands();
    if ($brandId && !isset($brands[$brandId])) {
        throw new \RuntimeException('Производитель не найден в связанном инфоблоке.');
    }
    if ($searchActive) {
        $results = ProductSearch::search($article, $brandId, $dtdPreviewPage, 50);
        $totalPages = max(1, (int)ceil($results['total']/50));
        if ($dtdPreviewPage > $totalPages) {
            $dtdPreviewPage = $totalPages;
            $results = ProductSearch::search($article, $brandId, $dtdPreviewPage, 50);
        }
        if ($productId === 0 && $results['total'] === 1 && count($results['items']) === 1) {
            $productId = (int)$results['items'][0]['ID'];
        }
        if ($productId > 0) {
            $belongs = false;
            foreach ($results['items'] as $row) {
                if ((int)$row['ID'] === $productId) {
                    $belongs = true;
                    break;
                }
            }
            if (!$belongs) {
                throw new \RuntimeException('Выбранный товар не входит в текущую страницу результатов.');
            }
        }
    }
    if ($productId > 0) {
        // Legacy links with ?product_id=N are still accepted without any search fields.
        $rs = \CIBlockElement::GetList(array(),
            array('IBLOCK_ID'=>CatalogSections::IBLOCK_ID, 'ID'=>$productId), false, false,
            array('ID','IBLOCK_ID','IBLOCK_SECTION_ID','NAME','PROPERTY_ARTNUMBER','PROPERTY_MANUFACTURER'));
        $row = $rs->Fetch();
        if (!$row) throw new \RuntimeException('Товар не найден в инфоблоке 16.');
        $manufacturerId = (int)$row['PROPERTY_MANUFACTURER_VALUE'];
        $manufacturerName = isset($brands[$manufacturerId]) ? $brands[$manufacturerId] : '';
        $product = array(
            'ID'=>(int)$row['ID'], 'IBLOCK_ID'=>(int)$row['IBLOCK_ID'],
            'NAME'=>(string)$row['NAME'], 'IBLOCK_SECTION_ID'=>(int)$row['IBLOCK_SECTION_ID'],
            'PROPERTIES'=>array(
                'ARTNUMBER'=>array('VALUE'=>(string)$row['PROPERTY_ARTNUMBER_VALUE']),
                'MANUFACTURER'=>array('NAME'=>$manufacturerName)
            ),
        );
        $sectionIds = CatalogSections::productSectionIds($product['ID'], $product['IBLOCK_SECTION_ID']);
        $items = Resolver::matched($product);
    }
} catch (\Throwable $e) {
    $error = $e->getMessage();
}
$baseParams = array('article'=>$article, 'brand_id'=>$brandId);
$pageUrl = function ($pageNum, $id = 0) use ($baseParams) {
    return 'dt_documents_preview.php?'.http_build_query(array_merge($baseParams,
        array('page'=>(int)$pageNum), $id ? array('product_id'=>(int)$id) : array()));
};
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
if ($error !== '') CAdminMessage::ShowMessage(array('MESSAGE'=>$error,'TYPE'=>'ERROR'));
?>
<style>
.dtd-preview-table{border-collapse:collapse;width:100%;background:#fff;margin:12px 0}
.dtd-preview-table th,.dtd-preview-table td{padding:9px;border:1px solid #dce0e5;text-align:left;vertical-align:top}
.dtd-preview-table th{background:#eef2f6}
.dtd-preview-muted{color:#6c7781;font-size:12px}
.dtd-preview-filter{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:15px 0;padding:14px;background:#f5f7f9}
.dtd-preview-pagination{margin:12px 0;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
</style>
<p><a href="dt_documents_list.php">К списку материалов</a></p>
<p>Поиск только читает каталог (инфоблок 16). В артикуле <code>_</code> означает ровно один любой символ, <code>%</code> — любое количество символов. Без них поиск точный. Чтобы найти буквальный знак подчёркивания, введите <code>!_</code>.</p>
<form class="dtd-preview-filter" method="get" action="dt_documents_preview.php">
    <label for="dtd-article">Артикул / шаблон:</label>
    <input id="dtd-article" name="article" type="search" maxlength="191" size="25" value="<?=Repository::escapeHtml($article)?>" placeholder="4000_25 или 40001%">
    <label for="dtd-brand">Производитель:</label>
    <select name="brand_id" id="dtd-brand">
        <option value="0">Все производители</option>
        <?php foreach ($brands as $id=>$name): ?>
        <option value="<?=(int)$id?>" <?=$brandId===$id?'selected':''?>><?=Repository::escapeHtml($name)?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="adm-btn adm-btn-save">Найти товары</button>
</form>
<p class="dtd-preview-muted">Можно искать только по артикулу, только по производителю или по обоим условиям. Для поиска всех BREXIT выберите производителя и оставьте артикул пустым.</p>
<?php if ($searchActive && $error === ''): ?>
<h3>Найдено товаров: <?=(int)$results['total']?></h3>
<?php if ($results['total'] > 0): ?>
<?php $pages = max(1, (int)ceil($results['total']/50)); ?>
<div class="dtd-preview-pagination">
    <?php if ($dtdPreviewPage > 1): ?><a href="<?=Repository::escapeHtml($pageUrl($dtdPreviewPage-1))?>">← Назад</a><?php endif; ?>
    <span>Страница <?=(int)$dtdPreviewPage?> из <?=$pages?> (по 50 товаров)</span>
    <?php if ($dtdPreviewPage < $pages): ?><a href="<?=Repository::escapeHtml($pageUrl($dtdPreviewPage+1))?>">Вперёд →</a><?php endif; ?>
</div>
<table class="dtd-preview-table"><thead><tr>
    <th>ID</th><th>Артикул</th><th>Производитель</th><th>Название товара</th><th>Активен</th><th>Действие</th>
</tr></thead><tbody>
<?php foreach ($results['items'] as $row): ?>
<tr>
    <td><?=(int)$row['ID']?></td>
    <td><?=Repository::escapeHtml($row['ARTICLE'])?></td>
    <td><?=Repository::escapeHtml($row['BRAND'])?></td>
    <td><?=Repository::escapeHtml($row['NAME'])?></td>
    <td><?=$row['ACTIVE']==='Y'?'Да':'Нет'?></td>
    <td><a href="<?=Repository::escapeHtml($pageUrl($dtdPreviewPage, $row['ID']))?>">Проверить материалы</a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<div class="dtd-preview-pagination">
    <?php if ($dtdPreviewPage > 1): ?><a href="<?=Repository::escapeHtml($pageUrl($dtdPreviewPage-1))?>">← Назад</a><?php endif; ?>
    <span>Страница <?=(int)$dtdPreviewPage?> из <?=$pages?></span>
    <?php if ($dtdPreviewPage < $pages): ?><a href="<?=Repository::escapeHtml($pageUrl($dtdPreviewPage+1))?>">Вперёд →</a><?php endif; ?>
</div>
<?php else: ?><p>Товары по заданным условиям не найдены.</p><?php endif; ?>
<?php endif; ?>
<?php if ($product): ?>
<h3>Товар #<?=(int)$product['ID']?>: <?=Repository::escapeHtml($product['NAME'])?></h3>
<p>Производитель: <b><?=Repository::escapeHtml($product['PROPERTIES']['MANUFACTURER']['NAME'])?></b>,
   артикул: <b><?=Repository::escapeHtml($product['PROPERTIES']['ARTNUMBER']['VALUE'])?></b></p>
<p>ID разделов товара: <?=Repository::escapeHtml(implode(', ', $sectionIds))?></p>
<p>Подходящих материалов: <b><?=count($items)?></b></p>
<table class="dtd-preview-table"><thead><tr><th>Приоритет</th><th>Тип</th><th>Материал</th><th>URL</th></tr></thead><tbody>
<?php foreach ($items as $index=>$item): ?>
<tr><td><?=$index+1?></td><td><?=Repository::escapeHtml($item['TYPE'])?></td>
    <td><?=Repository::escapeHtml($item['LINK_TEXT'])?></td>
    <td><a href="<?=Repository::escapeHtml($item['TARGET_URL'])?>" target="_blank" rel="noopener noreferrer"><?=Repository::escapeHtml($item['TARGET_URL'])?></a></td></tr>
<?php endforeach; ?>
<?php if (!$items): ?><tr><td colspan="4">Связанные материалы не найдены.</td></tr><?php endif; ?>
</tbody></table>
<?php endif; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
