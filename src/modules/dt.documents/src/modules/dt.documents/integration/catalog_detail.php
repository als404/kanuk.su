<?
/**
 * dt.documents public card integration — all products in catalog iblock #16.
 * Include after IncludeComponent("bitrix:catalog.element") in the parent
 * catalog/.default/element.php; pass $dtdPublicProductId explicitly.
 *
 * Uses the catalog property values as read by the verified admin search,
 * rather than assuming that MANUFACTURER.NAME in the template is a brand name.
 * IBLOCK_ID=16 is enforced in the element lookup. No database writes.
 */
if (!isset($dtdPublicProductId) || (int)$dtdPublicProductId <= 0) {
    return;
}

try {
    if (!\Bitrix\Main\Loader::includeModule('dt.documents') ||
        !\Bitrix\Main\Loader::includeModule('iblock')) {
        return;
    }

    $dtdLookup = \CIBlockElement::GetList(
        array(),
        array('IBLOCK_ID' => 16, 'ID' => (int)$dtdPublicProductId),
        false,
        false,
        array('ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'PROPERTY_ARTNUMBER', 'PROPERTY_MANUFACTURER')
    );
    $dtdRow = $dtdLookup->Fetch();
    if (!$dtdRow) {
        return;
    }

    $dtdBrands = \DT\Documents\ProductSearch::brands();
    $dtdBrandId = (int)$dtdRow['PROPERTY_MANUFACTURER_VALUE'];
    if (!isset($dtdBrands[$dtdBrandId])) {
        return;
    }

    $dtdProduct = array(
        'ID' => (int)$dtdRow['ID'],
        'IBLOCK_ID' => (int)$dtdRow['IBLOCK_ID'],
        'IBLOCK_SECTION_ID' => (int)$dtdRow['IBLOCK_SECTION_ID'],
        'PROPERTIES' => array(
            'MANUFACTURER' => array('NAME' => $dtdBrands[$dtdBrandId]),
            'ARTNUMBER' => array('VALUE' => (string)$dtdRow['PROPERTY_ARTNUMBER_VALUE']),
        ),
    );

    \DT\Documents\Renderer::render($dtdProduct);
} catch (\Throwable $dtdError) {
    // Fail open: the unchanged legacy JavaScript remains the fallback.
    error_log('[dt.documents] public-card product #'.(int)$dtdPublicProductId.': '.$dtdError->getMessage());
}
