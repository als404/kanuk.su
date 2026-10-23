<?php
// PHP 7.4 compatible. Replace paths and confirm price_group_id before writes.
return [
    'document_root' => dirname(__DIR__, 3), // if installed at DOCUMENT_ROOT/local/modules/cost_update
    'site_id' => 's1',
    'iblock_id' => 16,
    'article_property_id' => 51,
    'brand_facet_id' => 104,
    'brands_iblock_id' => 14,
    'price_group_id' => 1, // REQUIRED for writing: determine CATALOG_GROUP_ID from b_catalog_price
    'currency' => 'RUB',
    'html_cache_domain' => 'kanuk.brexit.by',
    'catalog_path' => '/catalog/',
    'spreadsheet_id' => '1zgP0Lytv4ju6g2kSORVGQkixcqWQV6NqOIyK0xTox_I',
    'brands' => [
        'BREXIT' => ['code'=>'brexit', 'gid'=>'1270836307', 'range'=>'C:AW', 'article_index'=>0, 'price_index'=>45, 'stock_index'=>46],
        'ESSON'  => ['code'=>'esson',  'gid'=>'613326184', 'range'=>'C:M',  'article_index'=>0, 'price_index'=>9,  'stock_index'=>10],
    ],
    'google_timeout' => 35,
    'google_cache_ttl' => 0, // 0 means fetch fresh on each automatic run
    'max_rows' => 100000,
];
