<?php
// Включаем отображение ошибок
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Подключаем файлы 1С-битрикс
require_once(__DIR__.'/_bitrix.php');
// Подключаем класс для выполнения SQL-запросов
require_once(__DIR__.'/_classes.php');
// Подключаем functions
require_once(__DIR__.'/_func.php');

// Основная логика
$id = '1DDMulcd1VOjMaF9N_pCOfiQUqEs9HWbi96bdXDouOmM';
$gid = '1078493664';
$range = 'D325:H330';
$brand_id = '267';
$iblock_id = 51;
$facet_id = 104;

// Обработка данных
try {
    $data['json'] = checkingCacheDate($id, $gid, $range);

    // Определяем товары, у которых изменилась доступность
    $data['refresh'] = processData($data['json'], $brand_id, $iblock_id, $facet_id);
       
    // Обновляем остатки
    updateStock($data['json'], $brand_id, $iblock_id, $facet_id);

    // Очистка кэша для найденных товаров
    if (!empty($data['refresh'])) {

        $iblockId = 16;
        $productIds = array_column($data['refresh'], 'prodId');
        
        foreach ($productIds as $prodId) {
            try {
                clearHtmlCacheByElementId($prodId, $iblockId);
                
            } catch (\Exception $e) {
                echo "Ошибка при очистке кэша для элемента ID={$prodId}: " . $e->getMessage() . "<br>\n";
            }
        }

        echo "Кэш для товаров очищен.\n";
    }


    // Логирование
    if (!empty($data['refresh'])) {
        $logFile = __DIR__ . '/cache_clear_log.txt';
        $logMessage = date('Y-m-d H:i:s') . " - Очищен кэш для товаров: " . implode(', ', $productIds) . "<br>\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

} catch (\Exception $e) {
    echo 'Ошибка: ' . htmlspecialchars($e->getMessage()) . "<br>\n";
    echo 'Файл: ' . $e->getFile() . "<br>\n";
    echo 'Строка: ' . $e->getLine() . "<br>\n";
}
