<?php
/**
 * DT catalog sorting index prototype - test server ONLY.
 * PHP 7.4 / 1C-Bitrix 17.5.8; CLI-only.
 * All writes require --apply and test hostname.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = '/var/www/discount-tools/public';
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['SERVER_NAME'] = 'discount-tools.ru';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $root . '/bitrix/modules/main/include/prolog_before.php';

if (!CModule::IncludeModule('iblock') ||
    !CModule::IncludeModule('catalog') ||
    !CModule::IncludeModule('currency')) {
    fwrite(STDERR, "Required Bitrix module not available\n");
    exit(2);
}

final class DtCatalogSortTool
{
    const PRODUCT_IBLOCK = 16;
    const SKU_IBLOCK = 17;
    const SKU_LINK_PROPERTY = 72;
    const SITE = 's1';
    const CURRENCY = 'RUB';

    /** Use public anonymous group, exactly as tested. */
    const GROUPS = array(2);

    public static function propertiesExist()
    {
        $found = array();
        $result = CIBlockProperty::GetList(
            array('SORT' => 'ASC'),
            array('IBLOCK_ID' => self::PRODUCT_IBLOCK)
        );
        while ($property = $result->Fetch()) {
            if (in_array($property['CODE'], array('DT_SORT_GROUP', 'DT_SORT_PRICE', 'DT_SORT_ZERO'), true)) {
                $found[$property['CODE']] = $property;
            }
        }
        return $found;
    }

    public static function installProperties($apply)
    {
        $found = self::propertiesExist();
        foreach (array(
            'DT_SORT_GROUP' => 'DT sorting group',
            'DT_SORT_PRICE' => 'DT effective price RUB',
            'DT_SORT_ZERO' => 'DT no price flag'
        ) as $code => $name) {
            if (isset($found[$code])) {
                if ($found[$code]['PROPERTY_TYPE'] !== 'N' || $found[$code]['MULTIPLE'] !== 'N') {
                    throw new RuntimeException('Property has incompatible type: ' . $code);
                }
                echo "EXISTS {$code}: ID=" . $found[$code]['ID'] . "\n";
                continue;
            }
            if (!$apply) {
                echo "WOULD_CREATE {$code} (type=N, single)\n";
                continue;
            }
            $api = new CIBlockProperty();
            $id = $api->Add(array(
                'IBLOCK_ID' => self::PRODUCT_IBLOCK,
                'NAME' => $name,
                'CODE' => $code,
                'PROPERTY_TYPE' => 'N',
                'MULTIPLE' => 'N',
                'ACTIVE' => 'Y',
                'SORT' => $code === 'DT_SORT_GROUP' ? 990 : ($code === 'DT_SORT_PRICE' ? 991 : 992),
                'IS_REQUIRED' => 'N'
            ));
            if (!$id) {
                throw new RuntimeException('Failed to create ' . $code . ': ' . $api->LAST_ERROR);
            }
            echo "CREATED {$code}: ID={$id}\n";
        }
    }

    /**
     * Get the final price for a product or SKU (no coupons, anon group 2).
     * Return null if no valid price exists; never invent a price.
     */
    private static function effectivePrice($productId)
    {
        $result = CCatalogProduct::GetOptimalPrice(
            (int)$productId, 1, self::GROUPS, 'N', array(), self::SITE, array()
        );
        if (!is_array($result) || !isset($result['RESULT_PRICE']['DISCOUNT_PRICE'])) {
            return null;
        }
        $priceData = $result['RESULT_PRICE'];
        $amount = (float)$priceData['DISCOUNT_PRICE'];
        if (!is_finite($amount) || $amount < 0) {
            throw new RuntimeException('Invalid price for item ' . $productId);
        }
        $currency = isset($priceData['CURRENCY']) ? $priceData['CURRENCY'] : '';
        if ($currency !== self::CURRENCY) {
            if (!$currency) {
                throw new RuntimeException('Missing currency for item ' . $productId);
            }
            $amount = (float)CCurrencyRates::ConvertCurrency($amount, $currency, self::CURRENCY);
            if (!is_finite($amount) || $amount < 0) {
                throw new RuntimeException('Currency conversion failed for item ' . $productId);
            }
        }
        return round($amount, 2);
    }

    private static function normalizePriority($raw)
    {
        $priority = (int)$raw;
        return in_array($priority, array(10, 20, 30), true) ? $priority : 30;
    }

    /** Given a product and its active offers, compute one sortable key. */
    private static function calculate($product, $offers)
    {
        $id = (int)$product['ID'];
        $priority = self::normalizePriority(
            isset($product['PROPERTY_CATALOG_SORT_PRIORITY_VALUE'])
                ? $product['PROPERTY_CATALOG_SORT_PRIORITY_VALUE'] : null
        );
        $price = null;
        $source = 'product';
        $offerCount = 0;

        if (!empty($offers)) {
            $source = 'sku';
            foreach ($offers as $offer) {
                ++$offerCount;
                $offerId = (int)$offer['ID'];
                $candidate = self::effectivePrice($offerId);
                if ($candidate === null || $candidate <= 0) {
                    continue;
                }
                if ($price === null || $candidate < $price) {
                    $price = $candidate;
                }
            }
        } else {
            $price = self::effectivePrice($id);
        }

        // Keep all positive-price products before zero/missing-price products
        // within each availability block (HIDE_NOT_AVAILABLE = L).
        // Positive: BREXIT=10, ESSON=20, others=30.
        // Zero/missing: BREXIT=40, ESSON=50, others=60.
        if ($price === null) {
            $price = 0.0;
        }
        $zero = $price > 0 ? 0 : 1;
        $group = $priority + ($zero ? 30 : 0);
        return array(
            'id' => $id,
            'priority' => $priority,
            'group' => $group,
            'price' => $price,
            'zero' => $zero,
            'source' => $source,
            'offers' => $offerCount
        );
    }

    private static function products($after, $limit, $ids)
    {
        $filter = array('IBLOCK_ID' => self::PRODUCT_IBLOCK, 'ACTIVE' => 'Y');
        if ($ids !== null) {
            $filter['ID'] = $ids;
        } else {
            $filter['>ID'] = $after;
        }
        $navigation = $ids !== null ? false : array('nTopCount' => $limit);
        $rs = CIBlockElement::GetList(
            array('ID' => 'ASC'), $filter, false, $navigation,
            array('ID', 'NAME', 'PROPERTY_CATALOG_SORT_PRIORITY')
        );
        $products = array();
        while ($row = $rs->Fetch()) {
            $products[(int)$row['ID']] = $row;
        }
        return $products;
    }

    public static function rebuild($ids, $after, $limit, $apply)
    {
        if ($apply) {
            $props = self::propertiesExist();
            if (!isset($props['DT_SORT_GROUP'], $props['DT_SORT_PRICE'], $props['DT_SORT_ZERO'])) {
                throw new RuntimeException('Create all three properties before applying values');
            }
        }
        $products = self::products($after, $limit, $ids);
        if (!$products) {
            echo "NO_PRODUCTS\n";
            return;
        }
        $productIds = array_keys($products);
        $lastId = max($productIds);
        $offerMap = CCatalogSKU::GetOffersList(
            $productIds,
            self::PRODUCT_IBLOCK,
            array('ACTIVE' => 'Y'),
            array('ID', 'IBLOCK_ID', 'ACTIVE'),
            array()
        );
        if ($offerMap === false) {
            throw new RuntimeException('CCatalogSKU::GetOffersList returned false');
        }
        $changed = 0;
        $zeros = 0;
        foreach ($products as $id => $product) {
            $row = self::calculate(
                $product,
                isset($offerMap[$id]) ? $offerMap[$id] : array()
            );
            if ($row['price'] <= 0) {
                ++$zeros;
            }
            echo sprintf(
                "%s ID=%d PRIORITY=%d GROUP=%d PRICE=%.2f ZERO=%d SOURCE=%s OFFERS=%d\n",
                $apply ? 'WRITE' : 'PREVIEW',
                $row['id'], $row['priority'], $row['group'], $row['price'],
                $row['zero'], $row['source'], $row['offers']
            );
            if ($apply) {
                CIBlockElement::SetPropertyValuesEx(
                    $id, self::PRODUCT_IBLOCK,
                    array(
                        'DT_SORT_GROUP' => $row['group'],
                        'DT_SORT_PRICE' => number_format($row['price'], 2, '.', ''),
                        'DT_SORT_ZERO' => $row['zero']
                    )
                );
                ++$changed;
            }
        }
        echo "COUNT=" . count($products)
            . " WRITES={$changed} ZERO_OR_MISSING={$zeros} LAST_ID={$lastId}\n";
        echo "NOTE: Preview is not a guarantee of match with every storefront audience, coupon, or SKU selection.\n";
    }
}

try {
    $args = array_slice($argv, 1);
    $command = '--preview';
    $apply = false;
    $confirm = '';
    $ids = null;
    $after = 0;
    $limit = 100;
    foreach ($args as $arg) {
        if (in_array($arg, array('--preview', '--create-properties', '--rebuild', '--check-properties'), true)) {
            $command = $arg;
        } elseif ($arg === '--apply') {
            $apply = true;
        } elseif (strpos($arg, '--confirm=') === 0) {
            $confirm = substr($arg, 10);
        } elseif (strpos($arg, '--ids=') === 0) {
            $ids = array_values(array_unique(array_filter(
                array_map('intval', explode(',', substr($arg, 6))),
                static function ($id) { return $id > 0; }
            )));
            if (!$ids || count($ids) > 100) {
                throw new RuntimeException('--ids must have 1..100 positive IDs');
            }
        } elseif (strpos($arg, '--after=') === 0) {
            $after = max(0, (int)substr($arg, 8));
        } elseif (strpos($arg, '--limit=') === 0) {
            $limit = (int)substr($arg, 8);
            if ($limit < 1 || $limit > 500) {
                throw new RuntimeException('--limit must be 1..500');
            }
        } else {
            throw new RuntimeException('Unknown argument: ' . $arg);
        }
    }
    if ($apply) {
        $hostname = gethostname();
        if ($hostname !== 'discount-tools-test' || $confirm !== 'test') {
            throw new RuntimeException('Writes allowed only on discount-tools-test with --confirm=test');
        }
        if (!in_array($command, array('--create-properties', '--rebuild'), true)) {
            throw new RuntimeException('--apply requires --create-properties or --rebuild');
        }
    }
    $skuConfig = CCatalogSKU::GetInfoByProductIBlock(DtCatalogSortTool::PRODUCT_IBLOCK);
    if (!is_array($skuConfig) ||
        (int)$skuConfig['IBLOCK_ID'] !== DtCatalogSortTool::SKU_IBLOCK ||
        (int)$skuConfig['SKU_PROPERTY_ID'] !== DtCatalogSortTool::SKU_LINK_PROPERTY) {
        throw new RuntimeException('Unexpected SKU iblock/link configuration; refusing to proceed');
    }
    echo 'HOST=' . gethostname() . ' COMMAND=' . $command
        . ' MODE=' . ($apply ? 'APPLY' : 'READ_ONLY') . "\n";
    if ($command === '--check-properties') {
        foreach (DtCatalogSortTool::propertiesExist() as $code => $p) {
            echo "$code ID={$p['ID']} TYPE={$p['PROPERTY_TYPE']}\n";
        }
    } elseif ($command === '--create-properties') {
        DtCatalogSortTool::installProperties($apply);
    } elseif ($command === '--preview' || $command === '--rebuild') {
        DtCatalogSortTool::rebuild($ids, $after, $limit, $apply);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
