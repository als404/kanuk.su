<?php
// Включаем отображение ошибок
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Подключаем файлы 1С-битрикс
require_once(__DIR__.'/bitrix.php');
// Подключаем класс для выполнения SQL-запросов
require_once(__DIR__.'/classes.php');
// Подключаем functions
require_once(__DIR__.'/func.php');

// Основная логика
$id = '1DDMulcd1VOjMaF9N_pCOfiQUqEs9HWbi96bdXDouOmM';
$iblock_id = 51;
$facet_id = 104;
$data['resource'] = [
    [
    'docId' => $id,
    'listId' => '1078493664', 
    'range' => 'D3:H350',
    'brandName' => 'BREXIT', 
    'iblock_id' => $iblock_id,
    'facet_id' => $facet_id,
    ],
    [
    'docId' => $id,
    'listId' => '1145536272',
    'range' => 'D4:H40',
    'brandName' => 'Esson', 
    'iblock_id' => $iblock_id,
    'facet_id' => $facet_id,
    ],
];

// Обработка данных
try {
    $data['json'] = checkingCacheDate($data['resource']);

    // Определяем товары, у которых изменилась доступность
    $data['refresh'] = processData($data);
       
    // Обновляем остатки
    updateStock($data);

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
