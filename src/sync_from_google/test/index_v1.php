<?php
// рабочий код в котором реализован поиск по артикулу товара и сравнение с остатками и если 
// товар обновился по условию (($old_qty == 0 && $new_qty > 0) || ($old_qty > 0 && $new_qty == 0))
// тогда записываем данные в массив и после сбрасываем кэш html-страниц и категорий к которым товар принадлежит

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
class Query
{
    public function get(array $params)
    {
        $art = htmlspecialchars($params['art']);
        $brand_id = htmlspecialchars($params['brand_id']);
        $iblock_id = (int)$params['iblock_id'];
        $facet_id = (int)$params['facet_id'];

        $sql = 'SELECT product.ID AS prodId, product.QUANTITY AS qty FROM b_catalog_product AS product JOIN b_catalog_price AS price ON price.PRODUCT_ID = product.ID JOIN b_iblock_element AS element ON element.ID = product.ID WHERE product.ID = (SELECT link.ELEMENT_ID FROM b_iblock_element_property AS prop JOIN b_iblock_16_index AS link ON link.ELEMENT_ID = prop.IBLOCK_ELEMENT_ID WHERE prop.VALUE = \'' . $art . '\' AND prop.IBLOCK_PROPERTY_ID = ' . $iblock_id . ' AND link.FACET_ID = ' . $facet_id . ' AND link.VALUE = \'' . $brand_id . '\' GROUP BY prop.ID LIMIT 1)';

        $connection = \Bitrix\Main\Application::getConnection();
        $res = $connection->query($sql);

        $result = [];
        while ($row = $res->fetch()) {
            $result[] = (object)$row;
        }

        return $result;
    }
}

// Основная логика
$id = '1DDMulcd1VOjMaF9N_pCOfiQUqEs9HWbi96bdXDouOmM';
$gid = '1078493664';
$range = 'D325:H332';
$brand_id = '267';
$iblock_id = 51;
$facet_id = 104;

function processData(array $data, string $brand_id, string $iblock_id, string $facet_id) {
    $query = new Query();
    $result = [];

    foreach ($data as $row) {
        $art = trim($row[0]);
        $new_qty = trim($row[4]);

        if (!empty($art)) {
            $params = [
                'art' => $art,
                'brand_id' => $brand_id,
                'iblock_id' => $iblock_id,
                'facet_id' => $facet_id
            ];

            foreach ($query->get($params) as $q) {
                $old_qty = $q->qty;
                if (($old_qty == 0 && $new_qty > 0) || ($old_qty > 0 && $new_qty == 0)) {
                    $result[] = ['prodId' => $q->prodId];
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

function clearHtmlCacheByElementId($elementId, $iblockId) {
    echo "Начало выполнения clearHtmlCacheByElementId для элемента ID: {$elementId}, инфоблока ID: {$iblockId}\n";

    // Получаем данные элемента
    $element = new CIBlockElement();
    $dbElement = $element->GetByID($elementId);
    $elementData = $dbElement->Fetch();

    if (!$elementData) {
        echo "Элемент с ID {$elementId} не существует.\n";
        echo "Конец clearHtmlCacheByElementId для элемента ID: {$elementId}\n";
        return;
    }

    echo "Элемент найден: ID={$elementId}, IBLOCK_ID={$elementData['IBLOCK_ID']}\n";

    $elementCode = $elementData['CODE'];

    // Получаем все разделы, к которым привязан элемент
    $sectionIds = [];

    $dbSections = CIBlockElement::GetElementGroups($elementId, true, array("ID", "CODE", "IBLOCK_SECTION_ID"));

    while ($arSection = $dbSections->Fetch()) {
        $sectionIds[] = $arSection['ID'];
    }

    if (empty($sectionIds)) {
        echo "Элемент не привязан ни к одному разделу.\n";
        // echo "Конец clearHtmlCacheByElementId для элемента ID: {$elementId}\n";
        return;
    }

    // Очищаем кэш самого элемента
    $elementCachePath = \Bitrix\Main\Application::getInstance()->getDocumentRoot() . "/bitrix/html_pages/discount-tools.ru/catalog/";

    foreach ($sectionIds as $sectionId) {
        $rsSection = CIBlockSection::GetList(array(), array("ID" => $sectionId), true);
        $sectionData = $rsSection->GetNext();

        if ($sectionData) {
            $sectionCode = $sectionData['CODE'];
            $elementCachePath .= $sectionCode . "/";

            // Добавляем код элемента
            $elementCachePath .= $elementCode . "/";

            echo "Путь к кэшу элемента: {$elementCachePath}\n";

            // Удаляем все файлы кэша элемента, включая пагинацию
            $files = glob($elementCachePath . "index@.html");

            if ($files) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                        echo "Файл кэша удален: {$file}\n";
                    } else {
                        // echo "Файл кэша не найден: {$file}\n";
                    }
                }
            } else {
                // echo "Файлы кэша не найдены в папке: {$elementCachePath}\n";
            }

            // Возвращаемся к разделу для очистки его кэша
            $elementCachePath = \Bitrix\Main\Application::getInstance()->getDocumentRoot() . "/bitrix/html_pages/discount-tools.ru/catalog/";
            $elementCachePath .= $sectionCode . "/";

            // echo "Путь к кэшу раздела: {$elementCachePath}\n";

            $sectionFiles = glob($elementCachePath . "index@.html");

            if ($sectionFiles) {
                foreach ($sectionFiles as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                        echo "Файл кэша раздела удален: {$file}\n";
                    } else {
                        echo "Файл кэша раздела не найден: {$file}\n";
                    }
                }
            } else {
                // echo "Файлы кэша раздела не найдены в патке: {$elementCachePath}\n";
            }

            // Очищаем кэш всех родительских разделов
            $currentSectionId = $sectionId;

            while ($currentSectionId) {
                $rsParentSection = CIBlockSection::GetList(array(), array("ID" => $currentSectionId), true);
                $parentSectionData = $rsParentSection->GetNext();

                if ($parentSectionData) {
                    $parentSectionCode = $parentSectionData['CODE'];
                    $parentCachePath = \Bitrix\Main\Application::getInstance()->getDocumentRoot() . "/bitrix/html_pages/discount-tools.ru/catalog/";
                    $parentCachePath .= $parentSectionCode . "/";

                    // echo "Путь к кэшу родительского раздела: {$parentCachePath}\n";

                    $parentFiles = glob($parentCachePath . "index@.html");

                    if ($parentFiles) {
                        foreach ($parentFiles as $file) {
                            if (file_exists($file)) {
                                unlink($file);
                                echo "Файл кэша родительского раздела удален: {$file}\n";
                            } else {
                                // echo "Файл кэша родительского раздела не найден: {$file}\n";
                            }
                        }
                    } else {
                        // echo "Файлы кэша родительского раздела не найдены в папке: {$parentCachePath}\n";
                    }

                    $currentSectionId = $parentSectionData['IBLOCK_SECTION_ID'];
                } else {
                    $currentSectionId = false;
                }
            }
        }
    }

    // Очищаем тегированный кэш
    $tagged = \Bitrix\Main\Application::getInstance()->getTaggedCache();
    $tagged->clearByTag('iblock_id_' . $iblockId);
    $tagged->clearByTag('element_' . $elementId);
    // echo "Тегированный кэш очищен.\n";

    echo "Конец clearHtmlCacheByElementId для элемента ID: {$elementId}\n";
}

// Обработка данных
try {
    $data['json'] = $array;
    $data['refresh'] = processData($data['json'], $brand_id, $iblock_id, $facet_id);
    // echo "Данные для очистки кэша: ";
    // print_r($data['refresh']);

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
