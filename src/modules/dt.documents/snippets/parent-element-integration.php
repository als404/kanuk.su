<?
// In /local/templates/elektro_flat/components/bitrix/catalog/.default/element.php
// Immediately after the completed IncludeComponent('bitrix:catalog.element', ...)
// and before //RELATED_ITEMS//. Parent template uses $ElementID returned by component.
$dtdPublicProductId = (int)$ElementID;
require $_SERVER['DOCUMENT_ROOT'].'/local/modules/dt.documents/integration/catalog_detail.php';
?>
