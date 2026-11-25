<?
/**
 * DT Discontinued Migration
 *
 * Modes:
 * --check
 * --apply
 * --verify
 * --rollback
 *
 * Bitrix 17.5.8 / PHP 7.4
 */

if (PHP_SAPI !== "cli") {
    exit(1);
}

umask(0077);

$mode = $argv[1] ?? "--check";

if (!in_array(
    $mode,
    array("--check", "--apply", "--verify", "--rollback"),
    true
)) {
    exit("ERROR: UNKNOWN MODE\n");
}

function dtFail($message)
{
    fwrite(STDERR, "ERROR: ".$message.PHP_EOL);
    exit(1);
}

// Prevent simultaneous migration processes.

$lock = fopen(__DIR__."/migration.lock", "c");

if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    dtFail("MIGRATION ALREADY RUNNING");
}

// Verify source files.

$backupFile = __DIR__."/products_before_migration.ser";
$planFile = __DIR__."/migration_plan.ser";

$backupHash =
    "f472f875b340bf6012bb2f1bbc411f0221d5d7f0b414e2766d77ab2848aedc03";

$planHash =
    "16d36669fa4f2b131dd4cf46c30a45b094a79f380951712186ae61183c50b2c0";

if (
    !is_file($backupFile)
    || hash_file("sha256", $backupFile) !== $backupHash
) {
    dtFail("BACKUP CHECKSUM MISMATCH");
}

if (
    !is_file($planFile)
    || hash_file("sha256", $planFile) !== $planHash
) {
    dtFail("PLAN CHECKSUM MISMATCH");
}

$backup = unserialize(
    file_get_contents($backupFile),
    array("allowed_classes" => false)
);

$plan = unserialize(
    file_get_contents($planFile),
    array("allowed_classes" => false)
);

if (
    !is_array($backup)
    || !is_array($plan)
    || count($backup) !== 29
    || count($plan) !== 29
    || array_keys($backup) !== array_keys($plan)
) {
    dtFail("INVALID MIGRATION DATA");
}

// Initialize Bitrix.

$_SERVER["DOCUMENT_ROOT"] =
    "/var/www/discount-tools/public";

chdir($_SERVER["DOCUMENT_ROOT"]);

define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_CHECK", true);
define("NO_AGENT_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require $_SERVER["DOCUMENT_ROOT"] .
    "/bitrix/modules/main/include/prolog_before.php";

if (!CModule::IncludeModule("iblock")) {
    dtFail("IBLOCK MODULE NOT FOUND");
}

if (!CModule::IncludeModule("catalog")) {
    dtFail("CATALOG MODULE NOT FOUND");
}

// Validate property configuration.

$property = CIBlockProperty::GetByID(692)->Fetch();

if (
    !$property
    || (int)$property["IBLOCK_ID"] !== 16
    || $property["CODE"] !== "DISCONTINUED"
    || $property["PROPERTY_TYPE"] !== "L"
    || $property["MULTIPLE"] !== "N"
) {
    dtFail("INVALID PROPERTY CONFIGURATION");
}

$enum = CIBlockPropertyEnum::GetList(
    array(),
    array(
        "PROPERTY_ID" => 692,
        "XML_ID" => "Y"
    )
);

$enumValue = $enum->Fetch();

if (!$enumValue || $enum->Fetch()) {
    dtFail("INVALID ENUM CONFIGURATION");
}

$enumId = (int)$enumValue["ID"];

$fields = array(
    "PREVIEW_TEXT",
    "PREVIEW_TEXT_TYPE",
    "DETAIL_TEXT",
    "DETAIL_TEXT_TYPE"
);

function dtGetPropertyValue($id)
{
    $values = array();

    $db = CIBlockElement::GetProperty(
        16,
        $id,
        array(),
        array("CODE" => "DISCONTINUED")
    );

    while ($row = $db->Fetch()) {

        if (
            $row["VALUE"] !== false
            && $row["VALUE"] !== null
            && $row["VALUE"] !== ""
        ) {
            $values[] = (string)$row["VALUE"];
        }
    }

    return $values;
}

function dtGetBackupProperty($item)
{
    $values = array();

    foreach ($item["DISCONTINUED_PROPERTY"] as $row) {

        if (
            $row["VALUE"] !== false
            && $row["VALUE"] !== null
            && $row["VALUE"] !== ""
        ) {
            $values[] = (string)$row["VALUE"];
        }
    }

    return $values;
}

function dtGetProduct($id)
{
    $db = CIBlockElement::GetList(
        array(),
        array(
            "IBLOCK_ID" => 16,
            "ID" => $id
        ),
        false,
        false,
        array(
            "ID",
            "ACTIVE",
            "PREVIEW_TEXT",
            "PREVIEW_TEXT_TYPE",
            "DETAIL_TEXT",
            "DETAIL_TEXT_TYPE",
            "PROPERTY_ARTNUMBER"
        )
    );

    return $db->Fetch();
}

function dtMatches($current, $expected)
{
    global $fields;

    foreach ($fields as $field) {

        if (
            (string)$current[$field]
            !== (string)$expected[$field]
        ) {
            return false;
        }
    }

    return true;
}

function dtUpdateProduct($id, $data)
{
    global $fields;

    $update = array();

    foreach ($fields as $field) {
        $update[$field] = $data[$field];
    }

    $element = new CIBlockElement();

    if (!$element->Update($id, $update)) {
        throw new RuntimeException(
            "UPDATE FAILED: ".$id." ".
            $element->LAST_ERROR
        );
    }
}

echo "=== DT DISCONTINUED MIGRATION ===\n";
echo "MODE: ".$mode."\n";
echo "PROPERTY ID: 692\n";
echo "ENUM ID: ".$enumId."\n";

// Preflight verification for all products.

$states = array();

foreach ($backup as $id => $original) {

    $id = (int)$id;
    $target = $plan[$id];

    $current = dtGetProduct($id);

    if (!$current) {
        dtFail("PRODUCT NOT FOUND: ".$id);
    }

    if (
        $current["PROPERTY_ARTNUMBER_VALUE"]
        !== $original["PROPERTY_ARTNUMBER_VALUE"]
        || $current["ACTIVE"] !== $original["ACTIVE"]
    ) {
        dtFail("PRODUCT IDENTITY CHANGED: ".$id);
    }

    $currentProperty = dtGetPropertyValue($id);
    $originalProperty = dtGetBackupProperty($original);

    $textBefore = dtMatches($current, $original);
    $textAfter = dtMatches($current, $target);

    $propertyBefore =
        $currentProperty === $originalProperty;

    $propertyAfter =
        $currentProperty === array((string)$enumId);

    if ($textBefore && $propertyBefore) {

        $state = "BEFORE";

    } elseif ($textAfter && $propertyAfter) {

        $state = "AFTER";

    } elseif (
        ($textBefore || $textAfter)
        && ($propertyBefore || $propertyAfter)
    ) {

        $state = "PARTIAL";

    } else {

        $state = "CONFLICT";
    }

    $states[$id] = $state;

    echo "ID: ".$id.
        " ARTICLE: ".$target["ARTICLE"].
        " STATE: ".$state.PHP_EOL;

    if ($state === "CONFLICT") {
        dtFail("UNEXPECTED DATABASE CHANGES: ".$id);
    }
}

if ($mode === "--check") {

    if (count(array_filter(
        $states,
        function ($state) {
            return $state === "BEFORE";
        }
    )) !== 29) {
        dtFail("DATABASE DOES NOT MATCH INITIAL BACKUP");
    }

    echo "CHECK: SUCCESS\n";
    echo "DATABASE CHANGES: NONE\n";

    exit(0);
}

if ($mode === "--verify") {

    foreach ($states as $id => $state) {

        if ($state !== "AFTER") {
            dtFail("VERIFICATION FAILED: ".$id);
        }
    }

    echo "VERIFY: SUCCESS\n";
    echo "PRODUCTS: 29\n";

    exit(0);
}

if ($mode === "--apply") {

    foreach ($states as $id => $state) {

        if ($state !== "BEFORE") {
            dtFail("APPLY REQUIRES INITIAL STATE: ".$id);
        }
    }
}

// Database changes.

$updated = 0;

foreach ($backup as $id => $original) {

    $id = (int)$id;

    if ($mode === "--rollback" && $states[$id] === "BEFORE") {
        echo "SKIP: ".$id." ALREADY RESTORED\n";
        continue;
    }

    try {

        $current = dtGetProduct($id);

        if (!$current) {
            throw new RuntimeException("PRODUCT NOT FOUND");
        }

        if ($mode === "--apply") {

            if (
                !dtMatches($current, $original)
                || dtGetPropertyValue($id)
                    !== dtGetBackupProperty($original)
                || $current["ACTIVE"] !== $original["ACTIVE"]
                || $current["PROPERTY_ARTNUMBER_VALUE"]
                    !== $original["PROPERTY_ARTNUMBER_VALUE"]
            ) {
                throw new RuntimeException(
                    "DATABASE CHANGED DURING MIGRATION"
                );
            }

            // Set status before removing legacy text.

            if ($plan[$id]["SET_DISCONTINUED"]) {

                CIBlockElement::SetPropertyValuesEx(
                    $id,
                    16,
                    array(692 => $enumId)
                );
            }

            if (
                dtGetPropertyValue($id)
                !== array((string)$enumId)
            ) {
                throw new RuntimeException(
                    "PROPERTY UPDATE FAILED"
                );
            }

            dtUpdateProduct($id, $plan[$id]);

            if (
                !dtMatches(dtGetProduct($id), $plan[$id])
                || dtGetPropertyValue($id)
                    !== array((string)$enumId)
            ) {
                throw new RuntimeException(
                    "POST UPDATE VERIFICATION FAILED"
                );
            }

        } else {

            // Rollback: accept only known original/target states.

            $currentProperty = dtGetPropertyValue($id);
            $originalProperty = dtGetBackupProperty($original);

            if (
                (!dtMatches($current, $original)
                    && !dtMatches($current, $plan[$id]))
                || ($currentProperty !== $originalProperty
                    && $currentProperty !== array((string)$enumId))
                || $current["ACTIVE"] !== $original["ACTIVE"]
                || $current["PROPERTY_ARTNUMBER_VALUE"]
                    !== $original["PROPERTY_ARTNUMBER_VALUE"]
            ) {
                throw new RuntimeException("ROLLBACK CONFLICT");
            }

            // Restore legacy descriptions first.

            dtUpdateProduct($id, $original);

            CIBlockElement::SetPropertyValuesEx(
                $id,
                16,
                array(
                    692 => empty($originalProperty)
                        ? false
                        : (int)$originalProperty[0]
                )
            );

            if (
                !dtMatches(dtGetProduct($id), $original)
                || dtGetPropertyValue($id)
                    !== $originalProperty
            ) {
                throw new RuntimeException(
                    "ROLLBACK VERIFICATION FAILED"
                );
            }
        }

        $updated++;

        echo "UPDATED: ".$id.
            " MODE: ".$mode.PHP_EOL;

    } catch (Throwable $e) {

        echo "FAILED ID: ".$id.PHP_EOL;
        echo "UPDATED BEFORE ERROR: ".$updated.PHP_EOL;

        dtFail($e->getMessage());
    }
}

echo "\n=== RESULT ===\n";
echo "MODE: ".$mode.PHP_EOL;
echo "UPDATED: ".$updated.PHP_EOL;
echo "SUCCESS\n";
