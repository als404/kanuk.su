<?
/**
 * DT Catalog Compare Popup
 *
 * Защищённая точка входа для окна
 * дополнительных параметров сравнения товаров.
 *
 * Bitrix 17.5.8 / PHP 7.4
 */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit;
}

define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_STATISTIC", true);

require_once(
    $_SERVER["DOCUMENT_ROOT"] .
    "/bitrix/modules/main/include/prolog_before.php"
);

header("Content-Type: text/html; charset=" . SITE_CHARSET);
header("Cache-Control: no-store, private");

function dtComparePopupError($message)
{
    echo '<div class="cont" style="padding:20px;">';
    echo htmlspecialcharsbx($message);
    echo '</div>';
    exit;
}

if (!check_bitrix_sessid()) {
    dtComparePopupError(
        "Сессия устарела. Обновите страницу сравнения."
    );
}

$token = $_POST["token"] ?? "";

if (
    !is_string($token)
    || $token === ""
    || strlen($token) > 2097152
) {
    dtComparePopupError(
        "Некорректные параметры товара."
    );
}

try {

    $signer = new \Bitrix\Main\Security\Sign\Signer();

    $signedData = $signer->unsign(
        $token,
        "dt.catalog.compare.popup"
    );

    $decoded = base64_decode($signedData, true);

    if ($decoded === false) {
        throw new \RuntimeException("Invalid data");
    }

    $payload = unserialize(
        $decoded,
        array("allowed_classes" => false)
    );

    if (
        !is_array($payload)
        || empty($payload["EXPIRES"])
        || empty($payload["SESSION"])
        || !isset($payload["PARAMS"])
        || !is_array($payload["PARAMS"])
    ) {
        throw new \RuntimeException("Invalid payload");
    }

    if (
        (int)$payload["EXPIRES"] < time()
        || !hash_equals(
            (string)$payload["SESSION"],
            (string)bitrix_sessid()
        )
    ) {
        dtComparePopupError(
            "Данные сравнения устарели. Обновите страницу."
        );
    }

    $dtVerifiedPopupParams = $payload["PARAMS"];

} catch (\Throwable $e) {

    dtComparePopupError(
        "Не удалось проверить параметры товара."
    );
}

define("DT_CATALOG_COMPARE_POPUP_VERIFIED", true);

require(
    $_SERVER["DOCUMENT_ROOT"] .
    "/local/templates/elektro_flat/components/" .
    "bitrix/catalog/.default/bitrix/" .
    "catalog.compare.result/.default/popup.php"
);