<?
/**
 * Discount Tools: incremental sorting index for Bitrix 17.5.8 / PHP 7.4.
 * Test server only. This file does not run any work merely by being included.
 */
require_once __DIR__ . '/DtCatalogSortCache.php';

final class DtCatalogSortAuto
{
    const PRODUCT_IBLOCK = 16;
    const OFFER_IBLOCK = 17;
    const SITE_ID = 's1';
    const CURRENCY = 'RUB';
    const CUSTOMER_GROUPS = array(2);
    const QUEUE_TABLE = 'dt_catalog_sort_queue';
    const STATE_TABLE = 'dt_catalog_sort_state';

    private static $writing = false;

    public static function isWriting()
    {
        return self::$writing;
    }

    public static function modules()
    {
        if (!\CModule::IncludeModule('iblock') ||
            !\CModule::IncludeModule('catalog') ||
            !\CModule::IncludeModule('currency')) {
            throw new \RuntimeException('Missing iblock, catalog, or currency module');
        }
    }

    private static function db()
    {
        return \Bitrix\Main\Application::getConnection();
    }

    public static function installSchema()
    {
        self::db()->queryExecute(
            "CREATE TABLE IF NOT EXISTS dt_catalog_sort_queue (" .
            "PRODUCT_ID INT UNSIGNED NOT NULL PRIMARY KEY," .
            "VERSION INT UNSIGNED NOT NULL DEFAULT 1," .
            "REASON VARCHAR(32) NOT NULL DEFAULT 'update'," .
            "QUEUED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        self::db()->queryExecute(
            "CREATE TABLE IF NOT EXISTS dt_catalog_sort_state (" .
            "`NAME` VARCHAR(32) NOT NULL PRIMARY KEY," .
            "`VALUE` VARCHAR(255) NOT NULL" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        self::setStateIfMissing('sweep_cursor', '0');
    }

    private static function setStateIfMissing($key, $value)
    {
        self::db()->queryExecute(
            "INSERT IGNORE INTO dt_catalog_sort_state (`NAME`, `VALUE`) VALUES ('" .
            self::db()->getSqlHelper()->forSql($key) . "','" .
            self::db()->getSqlHelper()->forSql($value) . "')"
        );
    }

    private static function setState($key, $value)
    {
        self::db()->queryExecute(
            "INSERT INTO dt_catalog_sort_state (`NAME`, `VALUE`) VALUES ('" .
            self::db()->getSqlHelper()->forSql($key) . "','" .
            self::db()->getSqlHelper()->forSql($value) . "') " .
            "ON DUPLICATE KEY UPDATE `VALUE`=VALUES(`VALUE`)"
        );
    }

    private static function state($key, $default)
    {
        $sql = "SELECT `VALUE` FROM dt_catalog_sort_state WHERE `NAME`='" .
            self::db()->getSqlHelper()->forSql($key) . "'";
        $row = self::db()->query($sql)->fetch();
        return $row ? (string)$row['VALUE'] : $default;
    }

    public static function restartSweep()
    {
        self::setState('sweep_cursor', '0');
    }

    /**
     * Map SKU prices back to parent iblock 16; ignore unrelated catalog IDs.
     * Called only after a successful price/element event.
     */
    public static function parentId($id)
    {
        self::modules();
        $id = (int)$id;
        if ($id <= 0) {
            return 0;
        }
        $record = \CIBlockElement::GetList(
            array(), array('ID' => $id), false, false, array('ID', 'IBLOCK_ID')
        )->Fetch();
        if (!$record) {
            return 0;
        }
        if ((int)$record['IBLOCK_ID'] === self::PRODUCT_IBLOCK) {
            return $id;
        }
        if ((int)$record['IBLOCK_ID'] === self::OFFER_IBLOCK) {
            $info = \CCatalogSKU::GetProductInfo($id, self::OFFER_IBLOCK);
            return is_array($info) && !empty($info['ID']) ? (int)$info['ID'] : 0;
        }
        return 0;
    }

    public static function enqueue($id, $reason)
    {
        $id = self::parentId($id);
        if (!$id) {
            return;
        }
        $reason = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$reason), 0, 32);
        $reason = $reason !== '' ? $reason : 'update';
        self::db()->queryExecute(
            "INSERT INTO dt_catalog_sort_queue (PRODUCT_ID,VERSION,REASON,QUEUED_AT) " .
            "VALUES ({$id},1,'" . self::db()->getSqlHelper()->forSql($reason) . "',NOW()) " .
            "ON DUPLICATE KEY UPDATE VERSION=VERSION+1, " .
            "REASON=VALUES(REASON),QUEUED_AT=NOW()"
        );
    }

    private static function optimalPrice($id)
    {
        $price = \CCatalogProduct::GetOptimalPrice(
            (int)$id, 1, self::CUSTOMER_GROUPS, 'N', array(), self::SITE_ID, array()
        );
        if (!is_array($price) || !isset($price['RESULT_PRICE']['DISCOUNT_PRICE'])) {
            return null;
        }
        $data = $price['RESULT_PRICE'];
        $amount = (float)$data['DISCOUNT_PRICE'];
        if (!is_finite($amount) || $amount < 0) {
            throw new \RuntimeException('Invalid effective price for ' . $id);
        }
        $currency = isset($data['CURRENCY']) ? (string)$data['CURRENCY'] : '';
        if ($currency === '') {
            throw new \RuntimeException('Missing price currency for ' . $id);
        }
        if ($currency !== self::CURRENCY) {
            $amount = (float)\CCurrencyRates::ConvertCurrency($amount, $currency, self::CURRENCY);
        }
        if (!is_finite($amount) || $amount < 0) {
            throw new \RuntimeException('Bad converted price for ' . $id);
        }
        return round($amount, 2);
    }

    private static function row($id)
    {
        $rs = \CIBlockElement::GetList(
            array(),
            array('IBLOCK_ID' => self::PRODUCT_IBLOCK, 'ID' => (int)$id, 'ACTIVE' => 'Y'),
            false, false,
            array('ID', 'NAME', 'PROPERTY_CATALOG_SORT_PRIORITY',
                'PROPERTY_DT_SORT_GROUP', 'PROPERTY_DT_SORT_PRICE', 'PROPERTY_DT_SORT_ZERO')
        );
        return $rs->Fetch();
    }

    public static function calculate($id)
    {
        self::modules();
        $item = self::row($id);
        if (!$item) {
            return null;
        }
        $priority = (int)$item['PROPERTY_CATALOG_SORT_PRIORITY_VALUE'];
        if (!in_array($priority, array(10, 20, 30), true)) {
            $priority = 30;
        }
        $offerMap = \CCatalogSKU::GetOffersList(
            array((int)$id), self::PRODUCT_IBLOCK, array('ACTIVE' => 'Y'),
            array('ID', 'IBLOCK_ID', 'ACTIVE'), array()
        );
        if ($offerMap === false) {
            throw new \RuntimeException('SKU lookup failed for ' . $id);
        }
        $offers = isset($offerMap[$id]) ? $offerMap[$id] : array();
        $price = null;
        if ($offers) {
            foreach ($offers as $offer) {
                $candidate = self::optimalPrice($offer['ID']);
                if ($candidate !== null && $candidate > 0 &&
                    ($price === null || $candidate < $price)) {
                    $price = $candidate;
                }
            }
        } else {
            $price = self::optimalPrice($id);
        }
        if ($price === null) {
            $price = 0.0;
        }
        $zero = $price > 0 ? 0 : 1;
        $group = $priority + ($zero ? 30 : 0);
        return array(
            'id' => (int)$id, 'name' => (string)$item['NAME'],
            'group' => $group, 'price' => $price, 'zero' => $zero,
            'current_group' => $item['PROPERTY_DT_SORT_GROUP_VALUE'],
            'current_price' => $item['PROPERTY_DT_SORT_PRICE_VALUE'],
            'current_zero' => $item['PROPERTY_DT_SORT_ZERO_VALUE']
        );
    }

    public static function recalc($id, $write)
    {
        $data = self::calculate((int)$id);
        if (!$data) {
            return array('id' => (int)$id, 'status' => 'SKIP_INACTIVE');
        }
        $match = $data['current_group'] !== null &&
            $data['current_group'] !== '' &&
            (int)$data['current_group'] === $data['group'] &&
            $data['current_price'] !== null &&
            $data['current_price'] !== '' &&
            abs((float)$data['current_price'] - $data['price']) < 0.005 &&
            $data['current_zero'] !== null &&
            $data['current_zero'] !== '' &&
            (int)$data['current_zero'] === $data['zero'];
        $data['status'] = $match ? 'UNCHANGED' : ($write ? 'UPDATED' : 'WOULD_UPDATE');
        if (!$match && $write) {
            self::$writing = true;
            try {
                \CIBlockElement::SetPropertyValuesEx(
                    (int)$id, self::PRODUCT_IBLOCK,
                    array('DT_SORT_GROUP' => $data['group'],
                          'DT_SORT_PRICE' => number_format($data['price'], 2, '.', ''),
                          'DT_SORT_ZERO' => $data['zero'])
                );
            } finally {
                self::$writing = false;
            }
        }
        return $data;
    }

    /** Manual write command also performs cache invalidation, not only index writes. */
    public static function recalcAndInvalidate($id)
    {
        $row = self::recalc((int)$id, true);
        if ($row['status'] !== 'SKIP_INACTIVE') {
            try {
                DtCatalogSortCache::invalidate((int)$id);
            } catch (\Throwable $cacheError) {
                // An explicit manual write must not leave an updated index
                // without a durable retry if composite invalidation fails.
                self::enqueue((int)$id, 'cache-retry');
                throw $cacheError;
            }
        }
        return $row;
    }

    private static function nextIds($cursor, $limit)
    {
        $rows = array();
        $rs = \CIBlockElement::GetList(
            array('ID' => 'ASC'),
            array('IBLOCK_ID' => self::PRODUCT_IBLOCK, 'ACTIVE' => 'Y', '>ID' => (int)$cursor),
            false, array('nTopCount' => (int)$limit), array('ID')
        );
        while ($row = $rs->Fetch()) {
            $rows[] = (int)$row['ID'];
        }
        return $rows;
    }

    private static function processQueue($limit)
    {
        $stats = array('selected' => 0, 'changed' => 0, 'failed' => 0, 'cache_deleted' => 0);
        $rs = self::db()->query(
            'SELECT PRODUCT_ID, VERSION FROM dt_catalog_sort_queue ORDER BY QUEUED_AT,PRODUCT_ID LIMIT ' . (int)$limit
        );
        $entries = array();
        while ($entry = $rs->fetch()) {
            $entries[] = $entry;
        }
        foreach ($entries as $entry) {
            ++$stats['selected'];
            $id = (int)$entry['PRODUCT_ID'];
            $version = (int)$entry['VERSION'];
            try {
                $row = self::recalc($id, true);
                if ($row['status'] === 'UPDATED') {
                    ++$stats['changed'];
                }
                // Intentionally purge even for UNCHANGED: a previous run may have
                // written DT_SORT_* and failed cache invalidation. Keep the queue
                // entry until both the index and caches are reconciled.
                $cache = DtCatalogSortCache::invalidate($id);
                $stats['cache_deleted'] += $cache['deleted'];
                // If another web request enqueued while processing, VERSION changes;
                // the new queue entry is intentionally retained.
                self::db()->queryExecute(
                    'DELETE FROM dt_catalog_sort_queue WHERE PRODUCT_ID=' . $id .
                    ' AND VERSION=' . $version
                );
            } catch (\Throwable $e) {
                ++$stats['failed'];
                error_log('[DT_SORT] queue ID=' . $id . ' ' . $e->getMessage());
            }
        }
        return $stats;
    }

    private static function processSweep($limit)
    {
        $cursor = max(0, (int)self::state('sweep_cursor', '0'));
        // In older Bitrix versions nTopCount=0 does not reliably mean "zero rows".
        // A disabled sweep must not inspect products or advance the cursor.
        if ((int)$limit === 0) {
            return array('selected' => 0, 'changed' => 0, 'failed' => 0,
                'cursor' => $cursor, 'cycle_end' => false);
        }
        $ids = self::nextIds($cursor, $limit);
        if (!$ids) {
            self::setState('sweep_cursor', '0');
            return array('selected' => 0, 'changed' => 0, 'failed' => 0, 'cursor' => 0, 'cycle_end' => true);
        }
        $stats = array('selected' => 0, 'changed' => 0, 'failed' => 0, 'cursor' => $cursor, 'cycle_end' => false);
        foreach ($ids as $id) {
            try {
                $row = self::recalc($id, true);
                ++$stats['selected'];
                if ($row['status'] === 'UPDATED') {
                    ++$stats['changed'];
                    try {
                        $cache = DtCatalogSortCache::invalidate($id);
                    } catch (\Throwable $cacheError) {
                        // The index may already be correct on the next sweep.
                        // Persist a retry independent of the sweep checkpoint.
                        self::enqueue($id, 'cache-retry');
                        throw $cacheError;
                    }
                }
                $cursor = $id;
                self::setState('sweep_cursor', (string)$cursor);
            } catch (\Throwable $e) {
                ++$stats['failed'];
                error_log('[DT_SORT] sweep ID=' . $id . ' ' . $e->getMessage());
                // Do not skip the failed ID in the checkpoint.
                break;
            }
        }
        $stats['cursor'] = $cursor;
        return $stats;
    }

    public static function runWorker($queueLimit, $sweepLimit)
    {
        self::modules();
        $queue = self::processQueue($queueLimit);
        $sweep = self::processSweep($sweepLimit);
        return array('queue' => $queue, 'sweep' => $sweep);
    }

    public static function status()
    {
        $count = self::db()->query('SELECT COUNT(*) AS CNT FROM dt_catalog_sort_queue')->fetch();
        return array('queued' => (int)$count['CNT'],
                     'sweep_cursor' => (int)self::state('sweep_cursor', '0'));
    }
}
