<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Дилерская XML-выгрузка");

global $USER;

$python    = "/usr/bin/python3";
$script    = "/var/www/kanuk/data/www/kanuk.brexit.by/local/tools/dealer-export/dealer_export.py";
$config    = "/var/www/kanuk/data/www/kanuk.brexit.by/local/tools/dealer-export/dealer_export.json";
$outputDir = "/var/www/kanuk/data/www/kanuk.brexit.by/dealer-export";

$brexitFile = $outputDir . "/brexit.xml";
$essonFile  = $outputDir . "/esson.xml";

$message = "";
$messageOk = false;
$commandOutput = array();

function dealer_export_h($value)
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function dealer_export_disabled_function($name)
{
    $disabled = ini_get("disable_functions");
    if (!$disabled) {
        return false;
    }

    $items = array_map("trim", explode(",", $disabled));
    return in_array($name, $items, true);
}

function dealer_export_file_info($path)
{
    if (!is_file($path)) {
        return "файл отсутствует";
    }

    $generated = "";

    $fp = @fopen($path, "rb");
    if ($fp) {
        $head = @fread($fp, 2048);
        @fclose($fp);

        if (
            $head !== false
            && preg_match('/generated_at="([^"]+)"/', $head, $m)
        ) {
            $generated = $m[1];
        }
    }

    $parts = array();

    if ($generated !== "") {
        try {
            $date = new DateTime($generated);
            $date->setTimezone(new DateTimeZone("Europe/Minsk"));
            $parts[] = "сформирован " . $date->format("d.m.Y H:i:s");
        } catch (Exception $e) {
            $parts[] = "сформирован " . $generated;
        }
    }

    $size = @filesize($path);

    if ($size !== false) {
        if ($size >= 1024 * 1024) {
            $parts[] = number_format(
                $size / (1024 * 1024),
                2,
                ".",
                " "
            ) . " МБ";
        } elseif ($size >= 1024) {
            $parts[] = number_format(
                $size / 1024,
                1,
                ".",
                " "
            ) . " КБ";
        } else {
            $parts[] = $size . " Б";
        }
    }

    return implode(", ", $parts);
}

if (!$USER->IsAuthorized() || !$USER->IsAdmin()) {
    ShowError("Доступ разрешён только администраторам.");
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
    exit;
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["dealer_export_run"])
) {
    if (!check_bitrix_sessid()) {
        $message = "Ошибка проверки сессии. Обновите страницу и повторите запуск.";
    } elseif (!function_exists("exec") || dealer_export_disabled_function("exec")) {
        $message = "PHP-функция exec() недоступна на этом сервере.";
    } elseif (!is_file($python) || !is_executable($python)) {
        $message = "Python не найден или недоступен: " . $python;
    } elseif (!is_file($script) || !is_readable($script)) {
        $message = "Скрипт выгрузки недоступен для чтения: " . $script;
    } elseif (!is_file($config) || !is_readable($config)) {
        $message = "Конфигурация выгрузки недоступна для чтения: " . $config;
    } elseif (!is_dir($outputDir) || !is_writable($outputDir)) {
        $message =
            "PHP-пользователь не может записывать в каталог выгрузки: " .
            $outputDir;
    } else {
        @set_time_limit(60);
        ignore_user_abort(true);

        $cmd =
            escapeshellarg($python) . " " .
            escapeshellarg($script) . " " .
            "--config " . escapeshellarg($config) .
            " 2>&1";

        $returnCode = 0;
        exec($cmd, $commandOutput, $returnCode);

        if ($returnCode === 0) {
            clearstatcache(true, $brexitFile);
            clearstatcache(true, $essonFile);

            $messageOk = true;
            $message = "XML BREXIT и ESSON успешно обновлены.";
        } else {
            $message =
                "Выгрузка завершилась с ошибкой. Код возврата: " .
                intval($returnCode) . ". Старые опубликованные XML должны остаться без изменений.";
        }
    }
}
?>

<style type="text/css">
.dealer-export-box {
    max-width: 850px;
    margin: 20px 0;
    padding: 18px 20px;
    border: 1px solid #d6d6d6;
    background: #f8f8f8;
}
.dealer-export-box h2 {
    margin: 0 0 15px;
    font-size: 20px;
}
.dealer-export-button {
    display: inline-block;
    padding: 10px 18px;
    border: 0;
    background: #9b1b45;
    color: #fff;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
}
.dealer-export-button[disabled] {
    opacity: 0.65;
    cursor: default;
}
.dealer-export-links {
    margin-top: 18px;
    line-height: 1.8;
}
.dealer-export-status {
    margin: 15px 0;
    padding: 12px 14px;
    border: 1px solid #69a66d;
    background: #eef8ee;
}
.dealer-export-status.error {
    border-color: #c75b5b;
    background: #fff0f0;
}
.dealer-export-output {
    max-height: 360px;
    overflow: auto;
    margin-top: 15px;
    padding: 12px;
    border: 1px solid #ccc;
    background: #222;
    color: #eee;
    white-space: pre-wrap;
    font: 12px/1.45 monospace;
}
.dealer-export-meta {
    color: #666;
    font-size: 12px;
}
</style>

<div class="dealer-export-box">
    <h2>Дилерские XML-файлы BREXIT / ESSON</h2>

    <form method="post" action="">
        <?=bitrix_sessid_post()?>
        <input type="hidden" name="dealer_export_run" value="1">

        <button
            type="submit"
            class="dealer-export-button"
            onclick="this.disabled=true; this.innerHTML='Обновление...'; this.form.submit();"
        >Обновить XML сейчас</button>
    </form>

    <div class="dealer-export-links">
        <div>
            <a href="/dealer-export/brexit.xml" target="_blank">Открыть BREXIT XML</a>
            <span class="dealer-export-meta">
                (<?=dealer_export_h(dealer_export_file_info($brexitFile))?>)
            </span>
        </div>

        <div>
            <a href="/dealer-export/esson.xml" target="_blank">Открыть ESSON XML</a>
            <span class="dealer-export-meta">
                (<?=dealer_export_h(dealer_export_file_info($essonFile))?>)
            </span>
        </div>
    </div>
</div>

<?php if ($message !== ""): ?>
    <div class="dealer-export-status<?=$messageOk ? "" : " error"?>">
        <?=dealer_export_h($message)?>
    </div>
<?php endif; ?>

<?php if (!empty($commandOutput)): ?>
    <details>
        <summary>Показать технический вывод</summary>
        <div class="dealer-export-output"><?=dealer_export_h(implode("\n", array_slice($commandOutput, -40)))?></div>
    </details>
<?php endif; ?>

<p>
    <a href="/about/employee-only/">← Вернуться на страницу для сотрудников</a>
</p>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
