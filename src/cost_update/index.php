<?php
// Ручная загрузка CSV. PHP 7.4 / Битрикс.
require_once __DIR__ . '/lib/common.php';
$c = cfg();
$_SERVER['DOCUMENT_ROOT'] = rtrim($c['document_root'], '/');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog.php';

global $USER;
if (!is_object($USER) || !$USER->IsAdmin()) {
    http_response_code(403);
    exit('Доступ разрешён только администратору.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['cost_import_csrf'])) {
    $_SESSION['cost_import_csrf'] = bin2hex(random_bytes(24));
}

function costHtml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$message = '';
$details = '';
$isError = false;

// Загружаем все активные бренды из инфоблока 14
$db = connectDb();

$stmt = $db->prepare(
    'SELECT ID, NAME, CODE
     FROM b_iblock_element
     WHERE IBLOCK_ID = ? AND ACTIVE = ?
     ORDER BY NAME'
);

$stmt->execute([
    $c['brands_iblock_id'],
    'Y'
]);

$availableBrands = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedBrand = (string)($_POST['brand'] ?? '');

$includeStock = !empty($_POST['include_stock']);
$applyRequested = !empty($_POST['apply']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();
    try {
        if (!hash_equals($_SESSION['cost_import_csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Ошибка проверки формы. Обновите страницу и повторите попытку.');
        }
        
        $brandFound = false;

        foreach ($availableBrands as $brandItem) {
            if ((string)$brandItem['ID'] === $selectedBrand) {
                $brandFound = true;
                break;
            }
        }

        if (!$brandFound) {
            throw new RuntimeException('Выберите корректный бренд.');
        }

        if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK ||
            !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            throw new RuntimeException('Не удалось получить CSV-файл.');
        }
        if ($_FILES['csv']['size'] > 10 * 1024 * 1024) {
            throw new RuntimeException('Размер CSV превышает 10 МБ.');
        }
        $content = file_get_contents($_FILES['csv']['tmp_name']);
        if ($content === false) {
            throw new RuntimeException('Не удалось прочитать CSV-файл.');
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $converted = iconv('Windows-1251', 'UTF-8//IGNORE', $content);
            if ($converted === false) {
                throw new RuntimeException('Не удалось преобразовать кодировку CSV.');
            }
            $content = $converted;
        }
        $rawRows = rowsFromCsvString($content, ';');
        $rows = extractRows($rawRows, [
            'article_index' => 0,
            'price_index' => 1,
            'stock_index' => 2,
        ], $selectedBrand);
        if (!$includeStock) {
            foreach ($rows as &$row) {
                $row['qty'] = null;
            }
            unset($row);
        }
        if (!$rows) {
            throw new RuntimeException('В файле нет строк с данными для обновления.');
        }
        require_once __DIR__ . '/lib/runner.php';
        $dryRun = !$applyRequested;
        $result = runBrands(
            ['MANUAL:' . $selectedBrand => $rows],
            $dryRun
        );
        $message = $dryRun ? 'Проверка завершена — данные в БД не менялись.' :
            'Обновление завершено. Проверьте отчёт и очистку кэша.';
        $details = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (Throwable $exception) {
        $isError = true;
        $message = 'Ошибка: ' . $exception->getMessage();
    }
    $output = trim(ob_get_clean());
    if ($output !== '') {
        $details .= ($details === '' ? '' : "\n\n") . $output;
    }
}

// Страница использует общий шаблон сайта; загрузка ядра допустима только для ручного режима.
$APPLICATION->SetTitle('Обновление цен и остатков');
// Шапка и шаблон сайта уже подключены через полный prolog.php.
?>
<style>
.costui {max-width:960px;margin:24px auto 52px;font-family:Arial,sans-serif;color:#28313b}
.costui * {box-sizing:border-box}
.costui .panel {border:1px solid #dbe2ea;border-radius:9px;background:#fff;margin-bottom:20px;overflow:hidden}
.costui .panel-head {padding:17px 22px;font-weight:700;background:#f4f6f8;border-bottom:1px solid #e2e6eb;font-size:18px}
.costui .panel-body {padding:22px}
.costui .help {color:#526270;line-height:1.6;font-size:14px;margin:0 0 17px}
.costui .hint {padding:13px 16px;border-radius:5px;background:#eaf7fc;color:#245066;margin:0 0 22px}
.costui .form-grid {display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:18px}
.costui label.caption {display:block;font-size:14px;font-weight:600;margin-bottom:8px}
.costui select,.costui input[type=file] {width:100%;max-width:100%;padding:12px;border:1px solid #cbd4df;border-radius:5px;background:white;font-size:14px}
.costui .checks {display:flex;flex-wrap:wrap;gap:24px;align-items:center;margin:20px 0}
.costui .checks label {display:flex;gap:9px;align-items:center;cursor:pointer}
.costui .checks input {width:17px;height:17px}
.costui button {padding:12px 24px;background:#cc2660;color:#fff;border:0;border-radius:5px;font-size:15px;font-weight:600;cursor:pointer}
.costui .notice {border:1px solid #b5d7b7;background:#eff9ef;border-radius:5px;padding:14px 16px;margin-bottom:20px}
.costui .notice.error {background:#fff0f0;border-color:#ebc4c4;color:#922b2b}
.costui pre {white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px;line-height:1.5;background:#f5f7f9;padding:15px;border-radius:5px;max-height:340px;overflow:auto}
.costui .warning {font-size:13px;color:#76551d;background:#fff5dd;border-radius:5px;padding:13px 16px;margin-top:18px}
@media (max-width:680px){.costui .form-grid{grid-template-columns:1fr}.costui .panel-body{padding:15px}}
</style>
<div class="costui">
    <?php if ($message !== ''): ?>
        <div class="notice<?= $isError ? ' error' : '' ?>">
            <strong><?= costHtml($message) ?></strong>
            <?php if ($details !== ''): ?><pre><?= costHtml($details) ?></pre><?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="panel">
        <div class="panel-head">Ручное обновление цен и остатков из CSV</div>
        <div class="panel-body">
            <p class="help">Выберите бренд, загрузите CSV-файл и сначала выполните проверку без записи. Товары ищутся по артикулу в пределах выбранного бренда.</p>
            <div class="hint">Формат: <strong>артикул;стоимость;кол-во</strong><br>
                Пустые значения не изменяют данные. Цена округляется до целого рубля. Отрицательный остаток преобразуется в 0.</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= costHtml($_SESSION['cost_import_csrf']) ?>">
                <div class="form-grid">
                    <div>
                        <label class="caption" for="cost-brand">Бренд</label>
                        <select id="cost-brand" name="brand">
                            <?php foreach ($c['brands'] as $brand => $settings): ?>
                                
                                <option value="">Выберите бренд...</option>

                                <?php foreach ($availableBrands as $brandItem): ?>
                                    <option
                                        value="<?= (int)$brandItem['ID'] ?>"
                                        <?= $selectedBrand === (string)$brandItem['ID']
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= costHtml(
                                            $brandItem['CODE'] !== ''
                                                ? $brandItem['CODE']
                                                : $brandItem['NAME']
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>

                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="caption" for="cost-file">CSV-файл</label>
                        <input type="file" id="cost-file" name="csv" accept=".csv,text/csv" required>
                    </div>
                </div>
                <div class="checks">
                    <label><input type="checkbox" name="include_stock" value="1" <?= $includeStock ? 'checked' : '' ?>> Учитывать остатки</label>
                    <label><input type="checkbox" name="apply" value="1" > Выполнить запись в MySQL</label>
                </div>
                <button type="submit">Запустить обработку</button>
                <div class="warning"><strong>Без галочки «Выполнить запись»</strong> запускается DRY-RUN. Галочка разрешает реальное обновление товаров и последующую очистку кэша.</div>
            </form>
        </div>
    </div>
    <div class="panel">
        <div class="panel-head">Последние события синхронизации</div>
        <div class="panel-body">
            <?php
            $logPath = runtimeDir() . '/sync.log';
            if (is_file($logPath)) {
                $log = file($logPath, FILE_IGNORE_NEW_LINES);
                echo '<pre>' . costHtml(implode("\n", array_slice($log ?: [], -35))) . '</pre>';
            } else {
                echo '<p class="help">Записей пока нет.</p>';
            }
            ?>
        </div>
    </div>
</div>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php'; ?>
