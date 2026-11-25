<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$APPLICATION->RestartBuffer();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

function dtBasketResponse($success, $message = "", $stock = null)
{
    echo json_encode(
        array(
            "SUCCESS" => (bool)$success,
            "MESSAGE" => $message,
            "STOCK" => $stock
        ),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    die();
}

if (
    !CModule::IncludeModule("sale")
    || !CModule::IncludeModule("catalog")
    || !CModule::IncludeModule("iblock")
) {
    dtBasketResponse(
        false,
        "Не удалось загрузить компоненты корзины."
    );
}

$productId = (int)($_REQUEST["ID"] ?? 0);
$qnt = (float)($_REQUEST["quantity"] ?? 0);

if ($productId <= 0 || $qnt <= 0 || !is_finite($qnt)) {

    dtBasketResponse(
        false,
        "Указано некорректное количество товара."
    );
}

// DT DISCONTINUED PRODUCT
if (class_exists("DTDiscontinuedProduct")
    && DTDiscontinuedProduct::isDiscontinued($productId)
) {
    $stock = DTDiscontinuedProduct::getQuantity($productId);

    if ($stock <= 0) {

        dtBasketResponse(
            false,
            "Товар снят с производства и отсутствует на складе. Покупка невозможна.",
            0
        );
    }

    if ($qnt > $stock) {

        dtBasketResponse(
            false,
            "Товар снят с производства. Доступное количество: "
                . $stock . " шт.",
            $stock
        );
    }
}
// END DT DISCONTINUED PRODUCT

$arItemParams = array();

if (!empty($_REQUEST["PROPS"])) {

    $arItemParamsBefore = unserialize(
        base64_decode(
            strtr($_REQUEST["PROPS"], "-_,", "+/=")
        ),
        array("allowed_classes" => false)
    );

    if (is_array($arItemParamsBefore)) {

        foreach ($arItemParamsBefore as $arProp) {
            if (is_array($arProp)) {
                $arItemParams[] = $arProp;
            }
        }
    }
}

if (!empty($_REQUEST["SELECT_PROPS"])) {

    $select_props = explode(
        "||",
        $_REQUEST["SELECT_PROPS"]
    );

    foreach ($select_props as $arSelProp) {

        $arProp = unserialize(
            base64_decode(
                strtr($arSelProp, "-_,", "+/=")
            ),
            array("allowed_classes" => false)
        );

        if (is_array($arProp)) {
            $arItemParams[] = $arProp;
        }
    }
}

$arFields = array(
    "QUANTITY" => $qnt,
    "DELAY" => "N"
);

$resBasket = CSaleBasket::GetList(
    array(),
    array(
        "PRODUCT_ID" => $productId,
        "FUSER_ID" => CSaleBasket::GetBasketUserID(),
        "LID" => SITE_ID,
        "ORDER_ID" => "NULL",
        "DELAY" => "Y"
    ),
    false,
    false,
    array("ID")
);

$APPLICATION->ResetException();

if ($ar = $resBasket->Fetch()) {

    $result = CSaleBasket::Update(
        $ar["ID"],
        $arFields
    );

} else {

    $result = Add2BasketByProductID(
        $productId,
        $qnt,
        $arItemParams
    );
}

// DT BASKET RESULT
if (!$result) {

    if (
        class_exists("DTDiscontinuedProduct")
        && DTDiscontinuedProduct::isDiscontinued($productId)
    ) {

        $stock = DTDiscontinuedProduct::getQuantity($productId);

        $message = $stock <= 0
            ? "Товар снят с производства и отсутствует на складе."
            : "Не удалось добавить товар. Товар снят с производства. Остаток на складе: "
                . $stock . " шт. Проверьте количество товара в корзине.";

        dtBasketResponse(
            false,
            $message,
            $stock
        );
    }

    dtBasketResponse(
        false,
        "Не удалось добавить товар в корзину. Проверьте количество и наличие товара."
    );
}

dtBasketResponse(true);
// END DT BASKET RESULT