<?
/**
 * Register from /local/php_interface/init.php AFTER the custom queue tables exist.
 * No recalculation or full catalog scans are performed during web requests.
 */
require_once __DIR__ . '/DtCatalogSortAuto.php';

final class DtCatalogSortHooks
{
    private static $priceDeleteProducts = array();

    private static function safe($callable)
    {
        try {
            $callable();
        } catch (\Throwable $e) {
            // Never break catalog editing because of an auxiliary sort index.
            error_log('[DT_SORT_EVENT] ' . $e->getMessage());
        }
    }

    private static function eventParams($event)
    {
        if (!is_object($event) || !method_exists($event, 'getParameter')) {
            return array(null, array());
        }
        if ($event->getParameter('success') === false) {
            return array(null, array());
        }
        $fields = $event->getParameter('fields');
        $fields = is_array($fields) ? $fields : array();
        $id = $event->getParameter('id');
        if (!$id) {
            $primary = $event->getParameter('primary');
            $id = is_array($primary) && isset($primary['ID']) ? $primary['ID'] : null;
        }
        return array($id, $fields);
    }

    /** Works with D7 Model/Price ORM event objects. */
    public static function afterPrice($event)
    {
        self::safe(static function () use ($event) {
            list($priceId, $fields) = DtCatalogSortHooks::eventParams($event);
            $productId = isset($fields['PRODUCT_ID']) ? (int)$fields['PRODUCT_ID'] : 0;
            if (!$productId && $priceId) {
                $price = \CPrice::GetByID((int)$priceId);
                if (is_array($price)) {
                    $productId = (int)$price['PRODUCT_ID'];
                }
            }
            if (!$productId && $priceId && isset(DtCatalogSortHooks::$priceDeleteProducts[(int)$priceId])) {
                $productId = DtCatalogSortHooks::$priceDeleteProducts[(int)$priceId];
            }
            if ($productId) {
                DtCatalogSortAuto::enqueue($productId, 'price');
            }
        });
    }

    public static function beforePriceDelete($event)
    {
        self::safe(static function () use ($event) {
            list($priceId, $fields) = DtCatalogSortHooks::eventParams($event);
            if (!empty($fields['PRODUCT_ID'])) {
                DtCatalogSortHooks::$priceDeleteProducts[(int)$priceId] = (int)$fields['PRODUCT_ID'];
            } elseif ($priceId) {
                $row = \CPrice::GetByID((int)$priceId);
                if ($row) {
                    DtCatalogSortHooks::$priceDeleteProducts[(int)$priceId] = (int)$row['PRODUCT_ID'];
                }
            }
        });
    }

    /** Fallback for legacy OnPriceAdd / OnPriceUpdate if enabled on this installation. */
    public static function legacyPrice($priceId, $fields = array())
    {
        self::safe(static function () use ($priceId, $fields) {
            $productId = is_array($fields) && !empty($fields['PRODUCT_ID'])
                ? (int)$fields['PRODUCT_ID'] : 0;
            if (!$productId) {
                $row = \CPrice::GetByID((int)$priceId);
                $productId = $row ? (int)$row['PRODUCT_ID'] : 0;
            }
            if ($productId) {
                DtCatalogSortAuto::enqueue($productId, 'legacy-price');
            }
        });
    }

    public static function legacyBeforePriceDelete($priceId)
    {
        self::safe(static function () use ($priceId) {
            $row = \CPrice::GetByID((int)$priceId);
            if ($row) {
                DtCatalogSortHooks::$priceDeleteProducts[(int)$priceId] = (int)$row['PRODUCT_ID'];
            }
        });
    }

    public static function legacyAfterPriceDelete($priceId)
    {
        self::safe(static function () use ($priceId) {
            if (isset(DtCatalogSortHooks::$priceDeleteProducts[(int)$priceId])) {
                DtCatalogSortAuto::enqueue(DtCatalogSortHooks::$priceDeleteProducts[(int)$priceId], 'price-delete');
            }
        });
    }

    public static function legacyProductPriceDelete($productId)
    {
        self::safe(static function () use ($productId) {
            DtCatalogSortAuto::enqueue((int)$productId, 'price-delete');
        });
    }

    public static function afterElement($fields)
    {
        self::safe(static function () use ($fields) {
            if (!is_array($fields) || empty($fields['ID']) ||
                (isset($fields['RESULT']) && $fields['RESULT'] === false)) {
                return;
            }
            if (isset($fields['IBLOCK_ID']) &&
                !in_array((int)$fields['IBLOCK_ID'], array(16, 17), true)) {
                return;
            }
            DtCatalogSortAuto::enqueue((int)$fields['ID'], 'element');
        });
    }

    public static function afterPropertiesEx($elementId, $iblockId, $propertyValues)
    {
        self::propertiesChanged($elementId, $iblockId, $propertyValues);
    }

    public static function afterProperties($elementId, $iblockId, $propertyValues)
    {
        self::propertiesChanged($elementId, $iblockId, $propertyValues);
    }

    private static function propertiesChanged($elementId, $iblockId, $propertyValues)
    {
        if (DtCatalogSortAuto::isWriting() ||
            !in_array((int)$iblockId, array(16, 17), true)) {
            return;
        }
        if (is_array($propertyValues) && $propertyValues) {
            $codes = array_keys($propertyValues);
            $sortOnly = true;
            foreach ($codes as $code) {
                if (!in_array((string)$code,
                    array('DT_SORT_GROUP', 'DT_SORT_PRICE', 'DT_SORT_ZERO', '692', '693'), true)) {
                    $sortOnly = false;
                    break;
                }
            }
            if ($sortOnly) {
                return;
            }
        }
        self::safe(static function () use ($elementId) {
            DtCatalogSortAuto::enqueue((int)$elementId, 'properties');
        });
    }

    public static function afterDiscount()
    {
        self::safe(static function () {
            // Discount rules may affect many products; a new full sweep is required.
            DtCatalogSortAuto::restartSweep();
        });
    }
}

$dtEvents = \Bitrix\Main\EventManager::getInstance();

// D7 price events differ between catalog versions. Queue deduplicates multiple hits.
foreach (array('Bitrix\\Catalog\\Model\\Price', 'Bitrix\\Catalog\\Price') as $dtPriceClass) {
    foreach (array('OnAfterAdd', 'OnAfterUpdate', 'OnAfterDelete') as $dtEventSuffix) {
        $dtEvents->addEventHandler('catalog', $dtPriceClass . '::' . $dtEventSuffix,
            array('DtCatalogSortHooks', 'afterPrice'));
    }
    $dtEvents->addEventHandler('catalog', $dtPriceClass . '::OnBeforeDelete',
        array('DtCatalogSortHooks', 'beforePriceDelete'));
}
foreach (array('PriceOnAfterAdd', 'PriceOnAfterUpdate', 'PriceOnAfterDelete') as $dtPriceEvent) {
    $dtEvents->addEventHandler('catalog', $dtPriceEvent,
        array('DtCatalogSortHooks', 'afterPrice'));
}
$dtEvents->addEventHandler('catalog', 'PriceOnBeforeDelete',
    array('DtCatalogSortHooks', 'beforePriceDelete'));

// Legacy handlers can be disabled in current Bitrix settings; they are only fallback.
$dtEvents->addEventHandlerCompatible('catalog', 'OnPriceAdd', array('DtCatalogSortHooks', 'legacyPrice'));
$dtEvents->addEventHandlerCompatible('catalog', 'OnPriceUpdate', array('DtCatalogSortHooks', 'legacyPrice'));
$dtEvents->addEventHandlerCompatible('catalog', 'OnBeforePriceDelete', array('DtCatalogSortHooks', 'legacyBeforePriceDelete'));
$dtEvents->addEventHandlerCompatible('catalog', 'OnPriceDelete', array('DtCatalogSortHooks', 'legacyAfterPriceDelete'));
$dtEvents->addEventHandlerCompatible('catalog', 'OnProductPriceDelete', array('DtCatalogSortHooks', 'legacyProductPriceDelete'));

$dtEvents->addEventHandlerCompatible('iblock', 'OnAfterIBlockElementUpdate', array('DtCatalogSortHooks', 'afterElement'));
$dtEvents->addEventHandlerCompatible('iblock', 'OnAfterIBlockElementAdd', array('DtCatalogSortHooks', 'afterElement'));
$dtEvents->addEventHandlerCompatible('iblock', 'OnAfterIBlockElementSetPropertyValuesEx', array('DtCatalogSortHooks', 'afterPropertiesEx'));
$dtEvents->addEventHandlerCompatible('iblock', 'OnAfterIBlockElementSetPropertyValues', array('DtCatalogSortHooks', 'afterProperties'));

foreach (array('OnDiscountAdd', 'OnDiscountUpdate', 'OnDiscountDelete') as $dtEventName) {
    $dtEvents->addEventHandlerCompatible('catalog', $dtEventName, array('DtCatalogSortHooks', 'afterDiscount'));
}
foreach (array('DiscountonAfterAdd', 'DiscountonAfterUpdate', 'DiscountonAfterDelete',
               'DiscountOnAfterAdd', 'DiscountOnAfterUpdate', 'DiscountOnAfterDelete') as $dtEventName) {
    $dtEvents->addEventHandler('sale', $dtEventName, array('DtCatalogSortHooks', 'afterDiscount'));
}
