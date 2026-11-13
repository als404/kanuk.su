<?
/** CLI entrypoint, test 247 only. Works with Bitrix 17.5.8 / PHP 7.4. */
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
require_once $root . '/local/php_interface/dt_catalog_sort/DtCatalogSortAuto.php';

function dtSortAutoIds($raw)
{
    $result = array();
    foreach (explode(',', $raw) as $item) {
        if (!preg_match('/^[0-9]+$/', $item)) {
            throw new RuntimeException('Invalid product ID: ' . $item);
        }
        $id = (int)$item;
        if ($id < 1) {
            throw new RuntimeException('Product ID must be positive');
        }
        $result[$id] = $id;
    }
    if (!$result || count($result) > 20) {
        throw new RuntimeException('Use 1 to 20 IDs');
    }
    return array_values($result);
}


/**
 * Strict product lookup for an operator. The UI article is ARTNUMBER, not ID.
 * Never identify a product by a partial NAME or infer its brand from its NAME.
 */
function dtSortAutoBrandValues($productId)
{
    $values = array();
    $rs = \CIBlockElement::GetProperty(
        DtCatalogSortAuto::PRODUCT_IBLOCK, (int)$productId,
        array(), array('CODE' => 'MANUFACTURER')
    );
    while ($property = $rs->Fetch()) {
        if (empty($property['VALUE'])) {
            continue;
        }
        $type = isset($property['PROPERTY_TYPE']) ? (string)$property['PROPERTY_TYPE'] : '';
        if ($type === 'E') {
            $linked = \CIBlockElement::GetByID((int)$property['VALUE'])->Fetch();
            if ($linked) {
                if (isset($linked['NAME'])) {
                    $values[] = (string)$linked['NAME'];
                }
                if (isset($linked['CODE'])) {
                    $values[] = (string)$linked['CODE'];
                }
            }
        } elseif ($type === 'L') {
            if (!empty($property['VALUE_ENUM'])) {
                $values[] = (string)$property['VALUE_ENUM'];
            }
            if (!empty($property['VALUE_XML_ID'])) {
                $values[] = (string)$property['VALUE_XML_ID'];
            }
            $values[] = (string)$property['VALUE'];
        } elseif ($type === 'S') {
            // Plain string or a directory external ID, e.g. "BREXIT" or "brexit".
            $values[] = (string)$property['VALUE'];
        }
    }
    return array_values(array_unique(array_filter(array_map('trim', $values), 'strlen')));
}

function dtSortAutoBrandEqual($actual, $expected)
{
    $actual = trim((string)$actual);
    $expected = trim((string)$expected);
    if (strcasecmp($actual, $expected) === 0) {
        return true;
    }
    if (function_exists('mb_strtolower') && mb_check_encoding($actual, 'UTF-8') &&
        mb_check_encoding($expected, 'UTF-8')) {
        return mb_strtolower($actual, 'UTF-8') === mb_strtolower($expected, 'UTF-8');
    }
    return false;
}

function dtSortAutoFindExact($article, $brand)
{
    $article = trim((string)$article);
    $brand = trim((string)$brand);
    if ($article === '' || strlen($article) > 100 ||
        $brand === '' || strlen($brand) > 100) {
        throw new RuntimeException('--find requires nonempty --article and --brand (at most 100 bytes)');
    }
    foreach (array('ARTNUMBER', 'MANUFACTURER') as $code) {
        $p = \CIBlockProperty::GetByID($code, DtCatalogSortAuto::PRODUCT_IBLOCK)->Fetch();
        if (!$p || $p['CODE'] !== $code) {
            throw new RuntimeException('Missing property ' . $code . ' in iblock 16');
        }
    }
    // Indexed filter narrows the candidate set; an exact value re-check follows.
    $rs = \CIBlockElement::GetList(
        array('ID' => 'ASC'),
        array('IBLOCK_ID' => DtCatalogSortAuto::PRODUCT_IBLOCK,
            'PROPERTY_ARTNUMBER' => $article),
        false, false, array('ID', 'NAME', 'ACTIVE')
    );
    $matches = array();
    $candidates = 0;
    while ($product = $rs->Fetch()) {
        $id = (int)$product['ID'];
        $p = \CIBlockElement::GetProperty(
            DtCatalogSortAuto::PRODUCT_IBLOCK, $id, array(), array('CODE' => 'ARTNUMBER')
        )->Fetch();
        if (!$p || trim((string)$p['VALUE']) !== $article) {
            continue;
        }
        ++$candidates;
        $brands = dtSortAutoBrandValues($id);
        $brandMatches = false;
        foreach ($brands as $actual) {
            if (dtSortAutoBrandEqual($actual, $brand)) {
                $brandMatches = true;
                break;
            }
        }
        if ($brandMatches) {
            $matches[] = $product;
            echo 'MATCH ID=' . $id . ' ARTICLE=' . $article .
                ' BRAND=' . $brand . ' ACTIVE=' . $product['ACTIVE'] .
                ' NAME=' . $product['NAME'] . "\n";
        } else {
            echo 'SKIP ID=' . $id . ' ARTICLE=' . $article .
                ' MANUFACTURER=' . ($brands ? implode('|', $brands) : 'UNRESOLVED') . "\n";
        }
    }
    if (count($matches) === 1) {
        echo 'RESULT=UNIQUE ID=' . (int)$matches[0]['ID'] . "\n";
        return;
    }
    if (!$matches) {
        echo 'RESULT=NOT_FOUND ARTICLE_CANDIDATES=' . $candidates . "\n";
        return;
    }
    echo 'RESULT=AMBIGUOUS MATCHES=' . count($matches) . "\n";
    throw new RuntimeException('More than one product has the exact article and brand; use a verified ID');
}

function dtSortAutoShow($data)
{
    echo 'ID=' . $data['id'] . ' STATUS=' . $data['status'];
    if (isset($data['group'])) {
        echo ' GROUP=' . $data['group'] . ' PRICE=' . number_format($data['price'], 2, '.', '') .
            ' ZERO=' . $data['zero'] . ' CURRENT_GROUP=' . var_export($data['current_group'], true) .
            ' CURRENT_PRICE=' . var_export($data['current_price'], true) .
            ' CURRENT_ZERO=' . var_export($data['current_zero'], true);
    }
    echo "\n";
}

try {
    $command = isset($argv[1]) ? $argv[1] : '--help';
    $apply = in_array('--apply', $argv, true);
    $confirm = in_array('--confirm=test', $argv, true);
    $ids = null;
    $article = null;
    $brand = null;
    $queueLimit = 100;
    $sweepLimit = 300;
    foreach (array_slice($argv, 2) as $arg) {
        if (strpos($arg, '--ids=') === 0) {
            $ids = dtSortAutoIds(substr($arg, 6));
        } elseif (strpos($arg, '--article=') === 0) {
            $article = substr($arg, 10);
        } elseif (strpos($arg, '--brand=') === 0) {
            $brand = substr($arg, 8);
        } elseif (strpos($arg, '--queue=') === 0) {
            $queueLimit = (int)substr($arg, 8);
        } elseif (strpos($arg, '--sweep=') === 0) {
            $sweepLimit = (int)substr($arg, 8);
        } elseif (!in_array($arg, array('--apply', '--confirm=test'), true)) {
            throw new RuntimeException('Unknown argument: ' . $arg);
        }
    }
    if ($queueLimit < 0 || $queueLimit > 500 || $sweepLimit < 0 || $sweepLimit > 1000) {
        throw new RuntimeException('Invalid queue/sweep batch size');
    }
    $writingCommands = array('--install', '--enqueue', '--recalc', '--run', '--restart-sweep');
    if (in_array($command, $writingCommands, true)) {
        if (!$apply || !$confirm || gethostname() !== 'discount-tools-test') {
            throw new RuntimeException('Writes only on discount-tools-test with --apply --confirm=test');
        }
    }
    DtCatalogSortAuto::modules();
    echo 'HOST=' . gethostname() . ' COMMAND=' . $command .
        ' MODE=' . (in_array($command, $writingCommands, true) ? 'APPLY' : 'READ_ONLY') . "\n";

    if ($command === '--install') {
        DtCatalogSortAuto::installSchema();
        echo "SCHEMA=OK\n";
    } elseif ($command === '--find') {
        if ($article === null || $brand === null) {
            throw new RuntimeException('--find requires --article=EXACT_ARTNUMBER --brand=EXACT_BRAND');
        }
        dtSortAutoFindExact($article, $brand);
    } elseif ($command === '--preview') {
        if (!$ids) {
            throw new RuntimeException('--preview requires --ids');
        }
        foreach ($ids as $id) {
            dtSortAutoShow(DtCatalogSortAuto::recalc($id, false));
        }
    } elseif ($command === '--cache-preview') {
        if (!$ids) {
            throw new RuntimeException('--cache-preview requires --ids');
        }
        foreach ($ids as $id) {
            echo 'CACHE_PREVIEW ' . json_encode(DtCatalogSortCache::preview($id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }
    } elseif ($command === '--enqueue') {
        if (!$ids) {
            throw new RuntimeException('--enqueue requires --ids');
        }
        foreach ($ids as $id) {
            DtCatalogSortAuto::enqueue($id, 'manual-test');
            echo 'ENQUEUED ID=' . $id . "\n";
        }
    } elseif ($command === '--recalc') {
        if (!$ids) {
            throw new RuntimeException('--recalc requires --ids');
        }
        foreach ($ids as $id) {
            dtSortAutoShow(DtCatalogSortAuto::recalcAndInvalidate($id));
        }
    } elseif ($command === '--restart-sweep') {
        DtCatalogSortAuto::restartSweep();
        echo "SWEEP_RESTARTED\n";
    } elseif ($command === '--run') {
        $lock = fopen('/tmp/dt_catalog_sort_auto_247.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Worker already running');
        }
        $stats = DtCatalogSortAuto::runWorker($queueLimit, $sweepLimit);
        echo 'QUEUE ' . json_encode($stats['queue'], JSON_UNESCAPED_UNICODE) . "\n";
        echo 'SWEEP ' . json_encode($stats['sweep'], JSON_UNESCAPED_UNICODE) . "\n";
        flock($lock, LOCK_UN);
        fclose($lock);
    } elseif ($command === '--status') {
        echo 'STATUS ' . json_encode(DtCatalogSortAuto::status(), JSON_UNESCAPED_UNICODE) . "\n";
    } elseif ($command === '--help') {
        echo "--install --apply --confirm=test\n";
        echo "--find --article=2100089 --brand=BREXIT\n";
        echo "--preview --ids=1234\n";
        echo "--cache-preview --ids=1234\n";
        echo "--enqueue --ids=1234 --apply --confirm=test\n";
        echo "--recalc --ids=1234 --apply --confirm=test\n";
        echo "--run --queue=100 --sweep=300 --apply --confirm=test\n";
        echo "--restart-sweep --apply --confirm=test\n";
        echo "--status\n";
    } else {
        throw new RuntimeException('Unknown command: ' . $command);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
    exit(1);
}
