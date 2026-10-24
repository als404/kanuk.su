<?php
// Bitrix module bootstrap. No database queries during inclusion.
\Bitrix\Main\Loader::registerAutoLoadClasses(
    'dt.catalogproperties',
    array(
        'DT\\CatalogProperties\\AdminMenu' => 'lib/AdminMenu.php',
        'DT\\CatalogProperties\\AdminElementProperties' => 'lib/AdminElementProperties.php',
    )
);
