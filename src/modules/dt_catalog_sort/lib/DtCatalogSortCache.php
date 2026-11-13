<?
/**
 * Test-only, targeted Bitrix composite HTML cache invalidation for sort index.
 * No full-site cache clears, no recursive directory removal, no writes on include.
 * PHP 7.4 / Bitrix 17.5.8; see README for constraints.
 */
final class DtCatalogSortCache
{
    const PRODUCT_IBLOCK = 16;
    const TEST_MACHINE = 'discount-tools-test';
    const TEST_DOMAIN = 'vmtest.discount-tools.ru';
    const DOCUMENT_ROOT = '/var/www/discount-tools/public';
    const MAX_PATHS = 256;
    const MAX_FILES = 5000;

    private static function root()
    {
        if (gethostname() !== self::TEST_MACHINE) {
            throw new \RuntimeException('Composite invalidation is restricted to the test machine');
        }
        $root = realpath(self::DOCUMENT_ROOT);
        if ($root === false || $root !== self::DOCUMENT_ROOT) {
            throw new \RuntimeException('Unexpected test document root');
        }
        $base = realpath($root . '/bitrix/html_pages/' . self::TEST_DOMAIN . '/catalog');
        if ($base === false || !is_dir($base)) {
            throw new \RuntimeException('Test composite catalog directory not found');
        }
        return $base;
    }

    private static function safeCode($code)
    {
        // Slugs observed on test are transliterated ASCII. Fail closed for other routing schemes.
        return is_string($code) && preg_match('/^[a-zA-Z0-9_-]+$/D', $code) === 1;
    }

    private static function sectionChain($sectionId)
    {
        $parts = array();
        $seen = array();
        for ($depth = 0; $sectionId > 0 && $depth < 40; ++$depth) {
            if (isset($seen[$sectionId])) {
                throw new \RuntimeException('Circular section hierarchy at ' . $sectionId);
            }
            $seen[$sectionId] = true;
            $row = \CIBlockSection::GetList(
                array(), array('IBLOCK_ID' => self::PRODUCT_IBLOCK, 'ID' => $sectionId),
                false, array('ID', 'CODE', 'IBLOCK_SECTION_ID')
            )->Fetch();
            if (!$row) {
                throw new \RuntimeException('Section not found: ' . $sectionId);
            }
            $code = (string)$row['CODE'];
            if (!self::safeCode($code)) {
                throw new \RuntimeException('Unsupported section slug, ID=' . $sectionId);
            }
            array_unshift($parts, $code);
            $sectionId = (int)$row['IBLOCK_SECTION_ID'];
        }
        if ($sectionId > 0) {
            throw new \RuntimeException('Section hierarchy depth exceeded');
        }
        return $parts;
    }

    /**
     * Relative directories only. A section list must clear ALL its cached variants
     * (sort, view, pagination), not just the current product's detail page.
     */
    private static function pathsForProduct($productId)
    {
        $productId = (int)$productId;
        $element = \CIBlockElement::GetByID($productId)->Fetch();
        if (!$element || (int)$element['IBLOCK_ID'] !== self::PRODUCT_IBLOCK) {
            throw new \RuntimeException('Product cannot be resolved for cache clear: ' . $productId);
        }
        $code = (string)$element['CODE'];
        // Even a product with no section can appear on the catalog landing page.
        $paths = array('' => true);
        $sectionIds = array();
        if (!empty($element['IBLOCK_SECTION_ID'])) {
            $sectionIds[(int)$element['IBLOCK_SECTION_ID']] = true;
        }
        $groups = \CIBlockElement::GetElementGroups(
            $productId, true, array('ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID')
        );
        while ($group = $groups->Fetch()) {
            if (isset($group['IBLOCK_ID']) && (int)$group['IBLOCK_ID'] !== self::PRODUCT_IBLOCK) {
                continue;
            }
            $id = (int)$group['ID'];
            if ($id > 0) {
                $sectionIds[$id] = true;
            }
        }
        foreach (array_keys($sectionIds) as $sectionId) {
            $parts = self::sectionChain($sectionId);
            for ($n = 1; $n <= count($parts); ++$n) {
                $paths[implode('/', array_slice($parts, 0, $n))] = true;
                // Compatibility with currently observed flat /catalog/<section>/ paths.
                $paths[$parts[$n - 1]] = true;
            }
            if ($code !== '') {
                if (!self::safeCode($code)) {
                    throw new \RuntimeException('Unsupported product slug, ID=' . $productId);
                }
                // Detail cache is relevant for price changes made through Bitrix admin.
                $paths[implode('/', $parts) . '/' . $code] = true;
                $paths[end($parts) . '/' . $code] = true;
            }
        }
        if (count($paths) > self::MAX_PATHS) {
            throw new \RuntimeException('Too many target cache directories: ' . count($paths));
        }
        return array_keys($paths);
    }

    /** Enumerate and validate every candidate before deletion. */
    private static function candidates($base, array $paths)
    {
        $files = array();
        $foundDirs = array();
        $unwritableDirs = array();
        foreach ($paths as $relative) {
            $target = $base . ($relative === '' ? '' : '/' . $relative);
            $dir = realpath($target);
            if ($dir === false) {
                continue; // The page has never been cached.
            }
            if ($dir !== $base && strpos($dir, $base . '/') !== 0) {
                throw new \RuntimeException('Cache path escapes root: ' . $relative);
            }
            if (!is_dir($dir) || !is_readable($dir)) {
                throw new \RuntimeException('Cache directory is not readable: ' . $relative);
            }
            $foundDirs[] = $relative;
            if (!is_writable($dir)) {
                $unwritableDirs[] = $relative;
            }
            $matched = glob($dir . '/index@*.html');
            if ($matched === false) {
                throw new \RuntimeException('Cannot enumerate cache in ' . $relative);
            }
            foreach ($matched as $file) {
                if (is_link($file)) {
                    throw new \RuntimeException('Unexpected cache symlink: ' . $file);
                }
                if (!is_file($file)) {
                    continue;
                }
                $real = realpath($file);
                if ($real === false || dirname($real) !== $dir ||
                    strpos($real, $base . '/') !== 0) {
                    throw new \RuntimeException('Unsafe cache file: ' . $file);
                }
                $files[$real] = true;
                if (count($files) > self::MAX_FILES) {
                    throw new \RuntimeException('Too many composite files in one invalidation');
                }
            }
        }
        return array('files' => array_keys($files), 'existing_dirs' => $foundDirs,
            'unwritable_dirs' => $unwritableDirs);
    }

    /** Read-only inventory for predeployment testing. */
    public static function preview($productId)
    {
        $base = self::root();
        $paths = self::pathsForProduct((int)$productId);
        $target = self::candidates($base, $paths);
        return array(
            'product_id' => (int)$productId,
            'cache_root' => $base,
            'paths' => $paths,
            'cached_files' => count($target['files']),
            'unwritable_dirs' => $target['unwritable_dirs']
        );
    }

    /**
     * Execute after recalc, BEFORE queue acknowledgement / sweep checkpoint.
     * If a purge fails, the queue entry is retained and retried even if the index
     * has already been updated and the next recalc returns UNCHANGED.
     */
    public static function invalidate($productId)
    {
        $base = self::root();
        $paths = self::pathsForProduct((int)$productId);
        $target = self::candidates($base, $paths);
        if ($target['unwritable_dirs']) {
            throw new \RuntimeException('Cron user cannot write cache directories: ' .
                implode(',', $target['unwritable_dirs']));
        }
        $tagged = \Bitrix\Main\Application::getInstance()->getTaggedCache();
        if (!is_object($tagged) || !method_exists($tagged, 'clearByTag')) {
            throw new \RuntimeException('Bitrix tagged cache unavailable');
        }
        // Clear managed component cache BEFORE HTML pages to avoid old-data rebuilds.
        $tagged->clearByTag('iblock_id_' . self::PRODUCT_IBLOCK);
        $tagged->clearByTag('element_' . (int)$productId);

        $deleted = 0;
        foreach ($target['files'] as $file) {
            if (!@unlink($file) && file_exists($file)) {
                throw new \RuntimeException('Composite file deletion failed: ' . $file);
            }
            ++$deleted;
        }
        return array('deleted' => $deleted, 'paths' => count($paths));
    }
}
