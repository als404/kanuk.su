<?php
namespace DT\CatalogProperties;

use Bitrix\Main\Application;
use Bitrix\Main\Composite\Page;
use RuntimeException;

/**
 * Targeted composite HTML cache invalidation for iblock 16.
 * Supports the current /catalog/#SECTION_CODE#/#ELEMENT_CODE#/ scheme.
 * IMPORTANT: deletion is explicitly restricted to vmtest.discount-tools.ru.
 */
final class CompositeCache
{
    const IBLOCK_ID = 16;
    const TEST_HOST = 'vmtest.discount-tools.ru';
    const MAX_PRODUCTS = 500;
    const MAX_URLS = 1000;

    /** Determine the current HTTP host; never trust forwarded-host headers. */
    public static function currentHost()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower(trim((string)$_SERVER['HTTP_HOST'])) : '';
        // Current test deployment uses the default HTTPS port and DNS hostname.
        if (!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/D', $host)) {
            throw new RuntimeException('Invalid HTTP_HOST; explicit host is required.');
        }
        return preg_replace('/:[0-9]{1,5}$/', '', $host);
    }

    /**
     * @param int $sectionId Main section whose configuration changed.
     * @param string|null $host Host, or null to derive from current HTTP_HOST.
     * @param bool $dryRun True means inspect only.
     * @return array Summary and per-page diagnostics.
     */
    public static function invalidateSection($sectionId, $host = null, $dryRun = true)
    {
        $sectionId = (int)$sectionId;
        $host = $host === null ? self::currentHost() : strtolower(trim((string)$host));
        $dryRun = (bool)$dryRun;
        if ($sectionId <= 0) {
            throw new RuntimeException('Invalid section ID.');
        }
        // A separate review is mandatory before production activation.
        if ($host !== self::TEST_HOST) {
            throw new RuntimeException('Composite cache deletion is permitted only on the test host.');
        }
        if (!class_exists(Page::class)) {
            throw new RuntimeException('Bitrix composite Page class is unavailable.');
        }
        $db = Application::getConnection();
        $section = $db->query('SELECT ID FROM b_iblock_section WHERE IBLOCK_ID='.self::IBLOCK_ID.' AND ID='.$sectionId.' LIMIT 1')->fetch();
        if (!$section) {
            throw new RuntimeException('Section not found in iblock 16.');
        }

        $countRow = $db->query('SELECT COUNT(*) AS CNT FROM b_iblock_element WHERE IBLOCK_ID='.self::IBLOCK_ID.' AND IBLOCK_SECTION_ID='.$sectionId)->fetch();
        $count = (int)$countRow['CNT'];
        if ($count > self::MAX_PRODUCTS) {
            throw new RuntimeException('Section has '.$count.' products (limit '.self::MAX_PRODUCTS.'); no cache deleted.');
        }

        // Collect only proven section memberships. Additional links can give valid alternate URLs.
        $elements = array();
        $rs = $db->query('SELECT e.ID AS ELEMENT_ID, e.CODE AS ELEMENT_CODE, e.IBLOCK_SECTION_ID AS MAIN_SECTION_ID, s.CODE AS SECTION_CODE'.
            ' FROM b_iblock_element e LEFT JOIN b_iblock_section s ON s.ID=e.IBLOCK_SECTION_ID'.
            ' WHERE e.IBLOCK_ID='.self::IBLOCK_ID.' AND e.IBLOCK_SECTION_ID='.$sectionId.' ORDER BY e.ID');
        while ($row = $rs->fetch()) {
            $id = (int)$row['ELEMENT_ID'];
            $elements[$id] = array('code' => (string)$row['ELEMENT_CODE'], 'sections' => array((string)$row['SECTION_CODE']));
        }

        if ($elements) {
            $ids = implode(',', array_map('intval', array_keys($elements)));
            $links = $db->query('SELECT se.IBLOCK_ELEMENT_ID AS ELEMENT_ID, s.CODE AS SECTION_CODE'.
                ' FROM b_iblock_section_element se INNER JOIN b_iblock_section s ON s.ID=se.IBLOCK_SECTION_ID AND s.IBLOCK_ID='.self::IBLOCK_ID.
                ' WHERE se.IBLOCK_ELEMENT_ID IN ('.$ids.') ORDER BY se.IBLOCK_ELEMENT_ID, s.ID');
            while ($row = $links->fetch()) {
                $id = (int)$row['ELEMENT_ID'];
                $code = (string)$row['SECTION_CODE'];
                if (isset($elements[$id]) && !in_array($code, $elements[$id]['sections'], true)) {
                    $elements[$id]['sections'][] = $code;
                }
            }
        }

        $report = array(
            'section_id' => $sectionId, 'host' => $host, 'dry_run' => $dryRun,
            'count' => $count, 'checked' => 0, 'found' => 0, 'deleted' => 0,
            'missing' => 0, 'skipped' => 0, 'errors' => array(), 'items' => array(),
            'candidate_urls' => 0, 'alternate_urls' => 0,
        );
        $candidates = array();
        foreach ($elements as $id => $element) {
            $code = $element['code'];
            foreach ($element['sections'] as $index => $sectionCode) {
                // No speculative encoding: unusual codes must be verified with actual routes.
                if (!self::isSafeSegment($code) || !self::isSafeSegment($sectionCode)) {
                    $report['skipped']++;
                    $report['items'][] = array('id' => $id, 'status' => 'skipped_slug', 'section_code' => $sectionCode);
                    continue;
                }
                $uri = '/catalog/'.$sectionCode.'/'.$code.'/';
                if (isset($candidates[$uri])) {
                    continue;
                }
                $candidates[$uri] = array('id' => $id, 'uri' => $uri, 'alternate' => $index > 0);
                if ($index > 0) {
                    $report['alternate_urls']++;
                }
            }
        }
        $report['candidate_urls'] = count($candidates);
        // Preflight every candidate before the first deletion; no partial run on oversized sets.
        if (count($candidates) > self::MAX_URLS) {
            throw new RuntimeException('Section has '.count($candidates).' possible URLs (limit '.self::MAX_URLS.'); no cache deleted.');
        }

        foreach ($candidates as $candidate) {
            $id = $candidate['id'];
            $uri = $candidate['uri'];
            try {
                $page = new Page($uri, $host);
                $expectedKey = '/'.$host.$uri.'index@.html';
                if ($page->getCacheKey() !== $expectedKey) {
                    $report['skipped']++;
                    $report['errors'][] = 'Unexpected composite cache key for element '.$id.' / '.$uri;
                    $report['items'][] = array('id' => $id, 'uri' => $uri, 'status' => 'key_mismatch');
                    continue;
                }
                $storage = $page->getStorage();
                if (!$storage) {
                    $report['skipped']++;
                    $report['errors'][] = 'Storage unavailable for '.$uri;
                    continue;
                }
                $report['checked']++;
                if (!$storage->exists()) {
                    $report['missing']++;
                    $report['items'][] = array('id' => $id, 'uri' => $uri, 'status' => 'not_cached');
                    continue;
                }
                $report['found']++;
                if ($dryRun) {
                    $report['items'][] = array('id' => $id, 'uri' => $uri, 'status' => 'would_delete');
                    continue;
                }
                $bytes = $page->delete();
                if ($bytes !== false && !$storage->exists()) {
                    $report['deleted']++;
                    $report['items'][] = array('id' => $id, 'uri' => $uri, 'status' => 'deleted');
                } else {
                    $report['errors'][] = 'Deletion not confirmed for '.$uri;
                    $report['items'][] = array('id' => $id, 'uri' => $uri, 'status' => 'delete_failed');
                }
            } catch (\Exception $e) {
                $report['errors'][] = 'Element '.$id.': '.$e->getMessage();
                $report['items'][] = array('id' => $id, 'uri' => $uri, 'status' => 'exception');
            }
        }
        return $report;
    }

    private static function isSafeSegment($code)
    {
        return $code !== '' && $code !== '.' && $code !== '..'
            && strpos($code, '/') === false && strpos($code, '\\') === false
            && strpos($code, "\0") === false
            && !preg_match('/[\x00-\x1F\x7F]/', $code)
            && strpos($code, '?') === false && strpos($code, '#') === false
            && strpos($code, '%') === false;
    }
}
