<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * DT Discontinued Products
 *
 * Серверная проверка возможности покупки
 * товаров, снятых с производства.
 *
 * Bitrix 17.5.8 / PHP 7.4
 */

class DTDiscontinuedProduct
{
    const PRODUCT_IBLOCK_ID = 16;
    const PROPERTY_CODE = "DISCONTINUED";
    const PROPERTY_XML_ID = "Y";

    /**
     * Проверка статуса товара.
     * Для SKU проверяется родительский товар.
     */
    public static function isDiscontinued($productId)
    {
        $productId = (int)$productId;

        if ($productId <= 0) {
            return false;
        }

        if (
            !CModule::IncludeModule("iblock")
            || !CModule::IncludeModule("catalog")
        ) {
            return false;
        }

        $iblockId = (int)CIBlockElement::GetIBlockByID(
            $productId
        );

        if ($iblockId !== self::PRODUCT_IBLOCK_ID) {

            $parent = CCatalogSKU::GetProductInfo(
                $productId,
                $iblockId
            );

            if (
                empty($parent)
                || (int)$parent["IBLOCK_ID"] !== self::PRODUCT_IBLOCK_ID
            ) {
                return false;
            }

            $productId = (int)$parent["ID"];
        }

        $dbProperty = CIBlockElement::GetProperty(
            self::PRODUCT_IBLOCK_ID,
            $productId,
            array(),
            array("CODE" => self::PROPERTY_CODE)
        );

        while ($property = $dbProperty->Fetch()) {

            if (
                !empty($property["VALUE"])
                && $property["VALUE_XML_ID"] === self::PROPERTY_XML_ID
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Получение текущего остатка.
     */
    public static function getQuantity($productId)
    {
        if (!CModule::IncludeModule("catalog")) {
            return 0;
        }

        $product = CCatalogProduct::GetByID(
            (int)$productId
        );

        return isset($product["QUANTITY"])
            ? max(0, (float)$product["QUANTITY"])
            : 0;
    }

    /**
     * Проверка запрашиваемого количества.
     */
    public static function canPurchase($productId, $quantity)
    {
        if (!self::isDiscontinued($productId)) {
            return true;
        }

        return (float)$quantity <= self::getQuantity($productId);
    }

    /**
     * Формирование результата запрета покупки.
     */
    private static function purchaseError($productId, $stock)
    {
        $message = $stock <= 0
            ? "Товар снят с производства и отсутствует на складе."
            : "Товар снят с производства. Доступное количество: " . $stock;

        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::ERROR,
            new \Bitrix\Sale\ResultError(
                $message,
                "DT_DISCONTINUED_NO_STOCK"
            ),
            "sale"
        );
    }

    /**
     * Проверка корзины перед сохранением.
     *
     * Запрещаем увеличение количества сверх остатка.
     * Разрешаем удаление и уменьшение количества.
     */
    public static function onBasketBeforeSaved(
        \Bitrix\Main\Event $event
    ) {
        $basket = $event->getParameter("ENTITY");

        if (!$basket instanceof \Bitrix\Sale\Basket) {
            return;
        }

        $currentQuantities = array();
        $originalQuantities = array();

        foreach ($basket as $item) {

            $productId = (int)$item->getProductId();
            $quantity = (float)$item->getQuantity();
            $delay = $item->getField("DELAY");

            if (
                $productId > 0
                && $quantity > 0
                && $delay !== "Y"
            ) {
                if (!isset($currentQuantities[$productId])) {
                    $currentQuantities[$productId] = 0;
                }

                $currentQuantities[$productId] += $quantity;
            }

            // Новая позиция ранее отсутствовала в корзине.
            if ((int)$item->getId() <= 0) {
                continue;
            }

            $original = $item->getFields()->getOriginalValues();

            $oldProductId = (int)(
                $original["PRODUCT_ID"] ?? $productId
            );

            $oldQuantity = (float)(
                $original["QUANTITY"] ?? $quantity
            );

            $oldDelay = $original["DELAY"] ?? $delay;

            if (
                $oldProductId > 0
                && $oldQuantity > 0
                && $oldDelay !== "Y"
            ) {
                if (!isset($originalQuantities[$oldProductId])) {
                    $originalQuantities[$oldProductId] = 0;
                }

                $originalQuantities[$oldProductId] += $oldQuantity;
            }
        }

        foreach ($currentQuantities as $productId => $quantity) {

            $originalQuantity =
                $originalQuantities[$productId] ?? 0;

            // Разрешаем сохранение неизменённых позиций
            // и уменьшение количества.
            if ($quantity <= $originalQuantity) {
                continue;
            }

            if (!self::isDiscontinued($productId)) {
                continue;
            }

            $stock = self::getQuantity($productId);

            if ($quantity > $stock) {
                return self::purchaseError(
                    $productId,
                    $stock
                );
            }
        }

        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            null,
            "sale"
        );
    }

    /**
     * Повторная проверка при оформлении нового заказа.
     *
     * Существующие заказы не затрагиваем.
     */
    public static function onOrderBeforeSaved(
        \Bitrix\Main\Event $event
    ) {
        $order = $event->getParameter("ENTITY");

        if (!$order instanceof \Bitrix\Sale\Order) {
            return;
        }

        // Не вмешиваемся в ранее оформленные заказы.
        if ((int)$order->getId() > 0) {
            return;
        }

        $basket = $order->getBasket();

        if (!$basket) {
            return;
        }

        $quantities = array();

        foreach ($basket as $item) {

            if ($item->getField("DELAY") === "Y") {
                continue;
            }

            $productId = (int)$item->getProductId();
            $quantity = (float)$item->getQuantity();

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            if (!isset($quantities[$productId])) {
                $quantities[$productId] = 0;
            }

            $quantities[$productId] += $quantity;
        }

        foreach ($quantities as $productId => $quantity) {

            if (!self::isDiscontinued($productId)) {
                continue;
            }

            $stock = self::getQuantity($productId);

            if ($quantity > $stock) {
                return self::purchaseError(
                    $productId,
                    $stock
                );
            }
        }

        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            null,
            "sale"
        );
    }
}