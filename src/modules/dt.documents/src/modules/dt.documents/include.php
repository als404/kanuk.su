<?php
\CModule::AddAutoloadClasses('dt.documents', array(
    'DT\\Documents\\Repository' => 'lib/Repository.php',
    'DT\\Documents\\Resolver' => 'lib/Resolver.php',
    'DT\\Documents\\ProductSearch' => 'lib/ProductSearch.php',
    'DT\\Documents\\Renderer' => 'lib/Renderer.php',
    'DT\\Documents\\CatalogSections' => 'lib/CatalogSections.php',
    'DT\\Documents\\AdminMenu' => 'lib/AdminMenu.php',
    'DT\\Documents\\LegacyImporter' => 'lib/LegacyImporter.php',
    'DT\\Documents\\Upload' => 'lib/Upload.php',
    'DT\\Documents\\Encoding' => 'lib/Encoding.php',
    'DT\\Documents\\CacheQueue' => 'lib/CacheQueue.php',
    'DT\\Documents\\CachePurger' => 'lib/CachePurger.php',
    'DT\\Documents\\CacheWorker' => 'lib/CacheWorker.php',
    'DT\\Documents\\CacheAgent' => 'lib/CacheAgent.php',
));
