<?
/**
 * DT Search Title Popup
 *
 * Защищённая точка входа для окна
 * дополнительных параметров быстрого поиска.
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

function dtSearchPopupError($message)
{
    echo '<div class="cont" style="padding:20px;">';
    echo htmlspecialcharsbx($message);
    echo '</div>';
    exit;
}

if (!check_bitrix_sessid()) {
    dtSearchPopupError(
        "Сессия устарела. Обновите страницу и повторите поиск."
    );
}

$token = $_POST["token"] ?? "";

if (
    !is_string($token)
    || $token === ""
    || strlen($token) > 2097152
) {
    dtSearchPopupError(
        "Некорректные параметры товара."
    );
}

try {

    $signer = new \Bitrix\Main\Security\Sign\Signer();

    $signedData = $signer->unsign(
        $token,
        "dt.search.title.popup"
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
        dtSearchPopupError(
            "Данные поиска устарели. Обновите страницу и повторите поиск."
        );
    }

    $dtVerifiedPopupParams = $payload["PARAMS"];

} catch (\Throwable $e) {

    dtSearchPopupError(
        "Не удалось проверить параметры товара. Повторите поиск."
    );
}

define("DT_SEARCH_TITLE_POPUP_VERIFIED", true);

require(
    $_SERVER["DOCUMENT_ROOT"] .
    "/local/templates/elektro_flat/components/" .
    "altop/search.title/.default/popup.php"
);
