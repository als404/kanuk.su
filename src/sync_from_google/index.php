<?php
// Включаем отображение ошибок
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Подключаем include.php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include.php');

// Проверяем, что include.php подключён
if (!defined('BX_ROOT')) {
    die("Файл include.php не подключён корректно.\n");
}

// Проверяем, что классы Bitrix24 доступны
if (!class_exists('\Bitrix\Main\Application')) {
    die("Класс Bitrix\Main\Application не доступен.\n");
}

// Проверяем, загружен ли модуль main
if (!CModule::IncludeModule('main')) {
    die("Модуль main не загружен.\n");
}

// Проверяем, загружен ли модуль iblock
if (!CModule::IncludeModule('iblock')) {
    die("Модуль iblock не загружен.\n");
}

// Проверяем, что классы CIBlockElement и CIBlock доступны
if (!class_exists('CIBlockElement')) {
    die("Класс CIBlockElement не доступен.\n");
}
if (!class_exists('CIBlock')) {
    die("Класс CIBlock не доступен.\n");
}

// Функция для загрузки данных из Google Sheets
function fetchGoogleSheetData($id, $gid, $range)
{
    $url = "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv&gid={$gid}&range={$range}";
    $csv = @file_get_contents($url);

    if ($csv === false) {
        throw new Exception("Не удалось загрузить данные из Google Sheets.");
    }

    $csvLines = explode("\r\n", $csv);
    $array = array_map('str_getcsv', $csvLines);

    return $array;
}

// Класс для выполнения SQL-запросов
class StockQuery
{
    public function get(array $params)
    {
        $art = $params['art'];
        $brand_id = $params['brand_id'];
        $iblock_id = (int)$params['iblock_id'];
        $facet_id = (int)$params['facet_id'];

        $sql = 'SELECT product.ID AS prodId, product.QUANTITY AS qty FROM b_catalog_product AS product JOIN b_catalog_price AS price ON price.PRODUCT_ID = product.ID JOIN b_iblock_element AS element ON element.ID = product.ID WHERE product.ID = (SELECT link.ELEMENT_ID FROM b_iblock_element_property AS prop JOIN b_iblock_16_index AS link ON link.ELEMENT_ID = prop.IBLOCK_ELEMENT_ID WHERE prop.VALUE = "' . $art . '" AND prop.IBLOCK_PROPERTY_ID = ' . $iblock_id . ' AND link.FACET_ID = ' . $facet_id . ' AND link.VALUE = "' . $brand_id . '" GROUP BY prop.IBLOCK_ELEMENT_ID LIMIT 1)';

        $connection = \Bitrix\Main\Application::getConnection();
        $res = $connection->query($sql);

        $result = [];
        while ($row = $res->fetch()) {
            $result[] = (object)$row;
        }

        return $result;
    }

    public function set(array $data)
    {
        $art = $data['art'];
        $brand_id = $data['brand_id'];
        $new_qty = $data['new_qty'];
        $available = $new_qty > 0 ? 'Y' : 'N';
        $modyfied_by = 1;
        $timestamp_x = date("Y-m-d H:i:s");
        $iblock_id = 51;
        $facet_id = 104;

        $helper = \Bitrix\Main\Application::getConnection()->getSqlHelper();

        $sql = 'UPDATE b_catalog_price AS price JOIN b_catalog_product AS product ON price.PRODUCT_ID = product.ID JOIN b_iblock_element AS element ON product.ID = element.ID SET product.QUANTITY = ' . $helper->forSql($new_qty) . ', product.AVAILABLE = "' . $helper->forSql($available) . '", element.MODIFIED_BY = ' . $helper->forSql($modyfied_by) . ', element.TIMESTAMP_X = "' . $helper->forSql($timestamp_x) . '" WHERE product.ID = (SELECT link.ELEMENT_ID FROM b_iblock_element_property AS prop JOIN b_iblock_16_index AS link ON link.ELEMENT_ID = prop.IBLOCK_ELEMENT_ID WHERE prop.VALUE = "' . $helper->forSql($art) . '" AND prop.IBLOCK_PROPERTY_ID = ' . $helper->forSql($iblock_id) . ' AND link.FACET_ID = ' . $helper->forSql($facet_id) . ' AND link.VALUE = "' . $helper->forSql($brand_id) . '" GROUP BY prop.IBLOCK_ELEMENT_ID LIMIT 1)';

        $connection = \Bitrix\Main\Application::getConnection();
        $result = $connection->query($sql);

        return $result;
    }
}

// Основная логика
$id = '1DDMulcd1VOjMaF9N_pCOfiQUqEs9HWbi96bdXDouOmM';
$gid = '1078493664';
$range = 'D325:H330';
$brand_id = '267';
$iblock_id = 51;
$facet_id = 104;

// Обновление остатков всегда
function updateStock(array $data, string $brand_id, string $iblock_id, string $facet_id)
{
    $stockQuery = new StockQuery();

    foreach ($data as $row) {
        $art = trim($row[0]);
        $new_qty = trim($row[4]);

        if (!empty($art) && is_numeric($new_qty)) {
            $params = [
                'art' => $art,
                'brand_id' => $brand_id,
                'iblock_id' => $iblock_id,
                'facet_id' => $facet_id
            ];

            $result = $stockQuery->get($params);

            if (!empty($result)) {
                $stmt = $stockQuery->set([
                    'art' => $art,
                    'brand_id' => $brand_id,
                    'new_qty' => $new_qty
                ]);

                if ($stmt) {
                    echo "Остатки товара с артикулом {$art} обновлены.\n";
                } else {
                    echo "Ошибка обновления остатков товара с артикулом {$art}.\n";
                }
            }
        }
    }
}

// Определение товаров, у которых изменилась доступность
function processData(array $data, string $brand_id, string $iblock_id, string $facet_id)
{
    $query = new StockQuery();
    $result = [];

    foreach ($data as $row) {
        $art = trim($row[0]);
        $new_qty = trim($row[4]);

        if (!empty($art) && is_numeric($new_qty)) {
            $params = [
                'art' => $art,
                'brand_id' => $brand_id,
                'iblock_id' => $iblock_id,
                'facet_id' => $facet_id
            ];

            $dbResult = $query->get($params);

            if (!empty($dbResult)) {
                $old_qty = $dbResult[0]->qty;

                // Проверяем, изменилась ли доступность
                if (($old_qty == 0 && $new_qty > 0) || ($old_qty > 0 && $new_qty == 0)) {
                    $result[] = ['prodId' => $dbResult[0]->prodId];
                }
            }
        }
    }

    return $result;
}

// Путь к кэш-файлу
$cacheFile = __DIR__ . '/leftover-product.json';

// Проверка даты кэша
if (file_exists($cacheFile)) {
    $cacheData = json_decode(file_get_contents($cacheFile), true);
    if (isset($cacheData['date']) && ($cacheData['date'] + 86400) > time()) {
        // Кэш актуален
        $array = $cacheData;
    } else {
        // Кэш устарел — обновляем
        $array = fetchGoogleSheetData($id, $gid, $range);
        $array['date'] = time();
        file_put_contents($cacheFile, json_encode($array));
    }
} else {
    // Кэш не существует — загружаем данные
    $array = fetchGoogleSheetData($id, $gid, $range);
    $array['date'] = time();
    file_put_contents($cacheFile, json_encode($array));
}

// Функция для очистки кэша элемента и категорий
function clearHtmlCacheByElementId($elementId, $iblockId) {
    echo "Начало выполнения clearHtmlCacheByElementId для элемента ID: {$elementId}, инфоблока ID: {$iblockId}\n";

    $element = new CIBlockElement();
    $dbElement = $element->GetByID($elementId);
    $elementData = $dbElement->Fetch();

    if (!$elementData) {
        echo "Элемент с ID {$elementId} не существует.\n";
        return;
    }

    echo "Элемент найден: ID={$elementId}, IBLOCK_ID={$elementData['IBLOCK_ID']}\n";
    echo "CODE элемента: {$elementData['CODE']}\n";

    $sectionIds = [];

    $dbSections = CIBlockElement::GetElementGroups($elementId, true, array("ID", "CODE", "IBLOCK_SECTION_ID"));

    while ($arSection = $dbSections->Fetch()) {
        $sectionIds[] = $arSection['ID'];
        echo "Найден раздел ID={$arSection['ID']}, CODE={$arSection['CODE']}\n";
    }

    if (empty($sectionIds)) {
        echo "Элемент не привязан ни к одному разделу.\n";
        return;
    }

    $documentRoot = \Bitrix\Main\Application::getInstance()->getDocumentRoot();
    $baseCachePath = $documentRoot . "/bitrix/html_pages/discount-tools.ru/catalog/";

    foreach ($sectionIds as $sectionId) {
        $rsSection = CIBlockSection::GetList(array(), array("ID" => $sectionId), true);
        $sectionData = $rsSection->GetNext();

        if ($sectionData) {
            $sectionCode = $sectionData['CODE'];
            $elementCode = $elementData['CODE'];

            $elementCachePath = $baseCachePath . $sectionCode . "/" . $elementCode . "/";
            echo "Путь к кэшу элемента: {$elementCachePath}\n";

            if (!is_dir($elementCachePath)) {
                echo "Папка кэша элемента не существует: {$elementCachePath}\n";
                continue;
            }

            $files = glob($elementCachePath . "index@.html");

            if ($files) {
                foreach ($files as $file) {
                    if (is_writable($file)) {
                        unlink($file);
                        echo "Файл кэша удален: {$file}\n";
                    } else {
                        echo "Файл недоступен для удаления: {$file}\n";
                    }
                }
            } else {
                echo "Файлы кэша элемента не найдены: {$elementCachePath}\n";
            }

            $sectionCachePath = $baseCachePath . $sectionCode . "/";
            echo "Путь к кэшу раздела: {$sectionCachePath}\n";

            $sectionFiles = glob($sectionCachePath . "index@.html");

            if ($sectionFiles) {
                foreach ($sectionFiles as $file) {
                    if (is_writable($file)) {
                        unlink($file);
                        echo "Файл кэша раздела удален: {$file}\n";
                    } else {
                        echo "Файл недоступен для удаления: {$file}\n";
                    }
                }
            } else {
                echo "Файлы кэша раздела не найдены: {$sectionCachePath}\n";
            }

            $currentSectionId = $sectionId;

            while ($currentSectionId) {
                $rsParentSection = CIBlockSection::GetList(array(), array("ID" => $currentSectionId), true);
                $parentSectionData = $rsParentSection->GetNext();

                if ($parentSectionData) {
                    $parentSectionCode = $parentSectionData['CODE'];
                    $parentCachePath = $baseCachePath . $parentSectionCode . "/";
                    echo "Путь к кэшу родительского раздела: {$parentCachePath}\n";

                    $parentFiles = glob($parentCachePath . "index@.html");

                    if ($parentFiles) {
                        foreach ($parentFiles as $file) {
                            if (is_writable($file)) {
                                unlink($file);
                                echo "Файл кэша родительского раздела удален: {$file}\n";
                            } else {
                                echo "Файл недоступен для удаления: {$file}\n";
                            }
                        }
                    } else {
                        echo "Файлы кэша родительского раздела не найдены: {$parentCachePath}\n";
                    }

                    $currentSectionId = $parentSectionData['IBLOCK_SECTION_ID'];
                } else {
                    $currentSectionId = false;
                }
            }
        }
    }

    $tagged = \Bitrix\Main\Application::getInstance()->getTaggedCache();
    $tagged->clearByTag('iblock_id_' . $iblockId);
    $tagged->clearByTag('element_' . $elementId);
    echo "Тегированный кэш очищен.\n";

    echo "Конец clearHtmlCacheByElementId для элемента ID: {$elementId}\n";
}

// Обработка данных
try {
    $data['json'] = $array;

    // Обновляем остатки всегда
    updateStock($data['json'], $brand_id, $iblock_id, $facet_id);

    // Определяем товары, у которых изменилась доступность
    $data['refresh'] = processData($data['json'], $brand_id, $iblock_id, $facet_id);

    // Очистка кэша для найденных товаров
    if (!empty($data['refresh'])) {
        $iblockId = 16;
        $productIds = array_column($data['refresh'], 'prodId');
        
        foreach ($productIds as $prodId) {
            try {
                clearHtmlCacheByElementId($prodId, $iblockId);
            } catch (\Exception $e) {
                echo "Ошибка при очистке кэша для элемента ID={$prodId}: " . $e->getMessage() . "\n";
            }
        }

        echo "Кэш для товаров очищен.\n";
    }

    // Логирование
    if (!empty($data['refresh'])) {
        $logFile = __DIR__ . '/cache_clear_log.txt';
        $logMessage = date('Y-m-d H:i:s') . " - Очищен кэш для товаров: " . implode(', ', $productIds) . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

} catch (\Exception $e) {
    echo 'Ошибка: ' . htmlspecialchars($e->getMessage()) . "\n";
    echo 'Файл: ' . $e->getFile() . "\n";
    echo 'Строка: ' . $e->getLine() . "\n";
}
