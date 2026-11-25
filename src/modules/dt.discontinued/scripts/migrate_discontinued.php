<?
/**
 * DT Discontinued Migration
 *
 * Stage 1: DRY RUN
 * Bitrix 17.5.8 / PHP 7.4
 *
 * No database changes.
 */

if (PHP_SAPI !== "cli") {
    exit;
}

$backupFile = __DIR__ . "/products_before_migration.ser";
$planFile = __DIR__ . "/migration_plan.ser";

$expectedHash =
    "f472f875b340bf6012bb2f1bbc411f0221d5d7f0b414e2766d77ab2848aedc03";

if (
    !is_file($backupFile)
    || hash_file("sha256", $backupFile) !== $expectedHash
) {
    exit("ERROR: BACKUP CHECKSUM FAILED\n");
}

$backup = unserialize(
    file_get_contents($backupFile),
    array("allowed_classes" => false)
);

if (!is_array($backup) || count($backup) !== 29) {
    exit("ERROR: INVALID BACKUP\n");
}

function dtCleanDescription($text, $id, $field)
{
    $text = (string)$text;

    // ROSCOPE 1000: preserve replacement information.
    if ($id === 2777) {

        if ($field === "PREVIEW_TEXT") {
            return str_replace(
                "Снят с производства, см. ROSCOPE i2000",
                "См. ROSCOPE i2000",
                $text
            );
        }

        return str_replace(
            "ROSCOPE 1000 снят с производства. ",
            "",
            $text
        );
    }

    // Supported legacy status phrases.
    $phrase =
        "(?:Модель\s+)?снят[аоы]?\s+с\s+прои?зводства[!.]?";

    // Remove a dedicated paragraph or heading.
    $text = preg_replace(
        '~<(p|h[1-6])\b[^>]*>\s*'
        . '(?:<(?:b|strong|span)\b[^>]*>\s*)*'
        . $phrase
        . '\s*(?:</(?:b|strong|span)>\s*)*'
        . '</\1>~iu',
        "",
        $text
    );

    // Remove a dedicated formatted inline status.
    $text = preg_replace(
        '~<(b|strong|span)\b[^>]*>\s*'
        . '(?:<(?:b|strong|span)\b[^>]*>\s*)*'
        . $phrase
        . '\s*(?:</(?:b|strong|span)>\s*)*'
        . '</\1>~iu',
        "",
        $text
    );

    // Remove descriptions consisting only of status text.
    $text = preg_replace(
        '~^\s*' . $phrase . '\s*$~iu',
        "",
        $text
    );

    if ($text === null) {
        throw new RuntimeException(
            "REGEX ERROR: " . $id . " " . $field
        );
    }

    // Remove empty legacy markup only if no content remains.
    if (
        preg_match(
            '~^(?:\s|</?p\b[^>]*>|<br\s*/?>|&nbsp;)*$~iu',
            $text
        ) === 1
    ) {
        return "";
    }

    // Remove a line break left by a removed leading status.
    $text = preg_replace(
        '~^\s*<br\s*/?>\s*~i',
        "",
        $text,
        1
    );

    return ltrim($text);
}

$plan = array();

$stats = array(
    "products" => 0,
    "set_property" => 0,
    "already_marked" => 0,
    "preview_changed" => 0,
    "detail_changed" => 0,
    "preview_empty" => 0,
    "detail_empty" => 0,
    "errors" => 0
);

echo "=== DT DISCONTINUED MIGRATION: DRY RUN ===\n";

foreach ($backup as $id => $item) {

    $id = (int)$id;

    $marked = false;

    foreach ($item["DISCONTINUED_PROPERTY"] as $prop) {

        if (
            isset($prop["VALUE_XML_ID"])
            && $prop["VALUE_XML_ID"] === "Y"
        ) {
            $marked = true;
            break;
        }
    }

    $oldPreview = (string)$item["PREVIEW_TEXT"];
    $oldDetail = (string)$item["DETAIL_TEXT"];

    $newPreview = dtCleanDescription(
        $oldPreview,
        $id,
        "PREVIEW_TEXT"
    );

    $newDetail = dtCleanDescription(
        $oldDetail,
        $id,
        "DETAIL_TEXT"
    );

    $previewChanged = $oldPreview !== $newPreview;
    $detailChanged = $oldDetail !== $newDetail;

    $stats["products"]++;

    if ($marked) {
        $stats["already_marked"]++;
    } else {
        $stats["set_property"]++;
    }

    if ($previewChanged) {
        $stats["preview_changed"]++;
    }

    if ($detailChanged) {
        $stats["detail_changed"]++;
    }

    if ($newPreview === "") {
        $stats["preview_empty"]++;
    }

    if ($newDetail === "") {
        $stats["detail_empty"]++;
    }

    $remainingPattern =
        "/снят[аоы]?\s+с\s+прои?зводства/iu";

    $remainingStatus =
        preg_match(
            $remainingPattern,
            strip_tags($newPreview)
        )
        || preg_match(
            $remainingPattern,
            strip_tags($newDetail)
        );

    // Verify that links, images, videos and comments remain intact.
    $protectedPattern =
        '~<a\b[^>]*>|<img\b[^>]*>|'
        . '<iframe\b[^>]*>.*?</iframe\s*>|'
        . '<!--.*?-->~is';

    $oldProtected = array();
    $newProtected = array();

    preg_match_all(
        $protectedPattern,
        $oldPreview . "\n" . $oldDetail,
        $oldProtected
    );

    preg_match_all(
        $protectedPattern,
        $newPreview . "\n" . $newDetail,
        $newProtected
    );

    $mediaOk =
        $oldProtected[0] === $newProtected[0];

    if (!$mediaOk || $remainingStatus) {
        $stats["errors"]++;
    }

    $plan[$id] = array(
        "ID" => $id,
        "ARTICLE" => $item["PROPERTY_ARTNUMBER_VALUE"],
        "ACTIVE" => $item["ACTIVE"],
        "SET_DISCONTINUED" => !$marked,
        "PREVIEW_CHANGED" => $previewChanged,
        "DETAIL_CHANGED" => $detailChanged,
        "PREVIEW_TEXT" => $newPreview,
        "PREVIEW_TEXT_TYPE" => $item["PREVIEW_TEXT_TYPE"],
        "DETAIL_TEXT" => $newDetail,
        "DETAIL_TEXT_TYPE" => $item["DETAIL_TEXT_TYPE"]
    );

    echo "\n========================================\n";
    echo "ID: " . $id . "\n";
    echo "ARTICLE: " .
        $item["PROPERTY_ARTNUMBER_VALUE"] . "\n";

    echo "PROPERTY: " .
        ($marked ? "KEEP" : "SET") . "\n";

    echo "PREVIEW: " .
        ($previewChanged ? "CHANGE" : "KEEP") .
        " (" . strlen($oldPreview) .
        " -> " . strlen($newPreview) . " bytes)\n";

    echo "DETAIL: " .
        ($detailChanged ? "CHANGE" : "KEEP") .
        " (" . strlen($oldDetail) .
        " -> " . strlen($newDetail) . " bytes)\n";

    echo "MEDIA: " .
        ($mediaOk ? "OK" : "ERROR") . "\n";

    echo "REMAINING STATUS: " .
        ($remainingStatus ? "ERROR" : "NONE") . "\n";

    echo "--- NEW PREVIEW ---\n";
    echo $newPreview . "\n";

    echo "--- NEW DETAIL START ---\n";
    echo substr($newDetail, 0, 1200) . "\n";
}

echo "\n=== MIGRATION STATISTICS ===\n";

echo json_encode(
    $stats,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
) . PHP_EOL;

if (
    $stats["errors"] > 0
    || $stats["set_property"] !== 26
    || $stats["already_marked"] !== 3
) {
    exit("DRY RUN: FAILED\n");
}

umask(0077);

$data = serialize($plan);

if (
    file_put_contents($planFile, $data, LOCK_EX)
    !== strlen($data)
) {
    exit("ERROR: PLAN WRITE FAILED\n");
}

chmod($planFile, 0600);

echo "\nPLAN: " . $planFile . "\n";
echo "PLAN SHA256: " .
    hash_file("sha256", $planFile) . "\n";

echo "DRY RUN: SUCCESS\n";
echo "DATABASE CHANGES: NONE\n";
