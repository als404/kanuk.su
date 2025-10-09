<?php
// Функция для загрузки данных из Google Sheets
function fetchGoogleSheetData($id, $gid, $range) {
    $url = "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv&gid={$gid}&range={$range}";
    $csv = @file_get_contents($url);

    if ($csv === false) {
        throw new Exception("Не удалось загрузить данные из Google Sheets.");
    }

    $csvLines = explode("\r\n", $csv);
    $array = array_map('str_getcsv', $csvLines);

    return $array;
}

//  Функция проверки даты кэша файла
function checkingCacheDate(array $data){
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
            foreach($data as $resource){
                $array[$resource['brandName']] = fetchGoogleSheetData($resource['docId'], $resource['listId'], $resource['range']);
            }
            
            $array['date'] = time();
            file_put_contents($cacheFile, json_encode($array));
        }
    } else {
        // Кэш не существует — загружаем данные
        foreach($data as $resource){
            $array[$resource['brandName']] = fetchGoogleSheetData($resource['docId'], $resource['listId'], $resource['range']);
        }
        $array['date'] = time();
        file_put_contents($cacheFile, json_encode($array));
    }
    
    return $array;
}

// Функция получает имя бренда и возвращает ID для него
function getBrandId(string $name){
    $stockQuery = new StockQuery();
    
    return $stockQuery->getBrandID(strtolower($name));
}

// Обновление остатков всегда
function updateStock(array $data){
    $stockQuery = new StockQuery();

    foreach($data['resource'] as $resource) {
        $brand_name = $resource['brandName'];
        $brand_id = getBrandId($brand_name);
        $iblock_id = $resource['iblock_id'];
        $facet_id = $resource['facet_id'];

        foreach($data['json'][$brand_name] as $row){
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
}

// Определение товаров, у которых изменилась доступность
function processData(array $data){

    $query = new StockQuery();
    $result = [];

    foreach($data['resource'] as $resource) {
        $brand_name = $resource['brandName'];
        $brand_id = getBrandId($brand_name);
        $iblock_id = $resource['iblock_id'];
        $facet_id = $resource['facet_id'];

        foreach($data['json'][$brand_name] as $row) {
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
    }

    return $result;
}

// Функция для очистки кэша элемента и категорий
function clearHtmlCacheByElementId($elementId, $iblockId) {
    // echo "Начало выполнения clearHtmlCacheByElementId для элемента ID: {$elementId}, инфоблока ID: {$iblockId}\n";

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
        echo "Конец clearHtmlCacheByElementId для элемента ID: {$elementId}\n";
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
            $files = glob($elementCachePath . "index@*.html");

            if ($files) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                        echo "Файл кэша удален: {$file}\n";
                    } else {
                        echo "Файл кэша не найден: {$file}\n";
                    }
                }
            } else {
                echo "Файлы кэша не найдены в папке: {$elementCachePath}\n";
            }

            // Возвращаемся к разделу для очистки его кэша
            $elementCachePath = \Bitrix\Main\Application::getInstance()->getDocumentRoot() . "/bitrix/html_pages/discount-tools.ru/catalog/";
            $elementCachePath .= $sectionCode . "/";

            echo "Путь к кэшу раздела: {$elementCachePath}\n";

            $sectionFiles = glob($elementCachePath . "index@*.html");

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
                echo "Файлы кэша раздела не найдены в патке: {$elementCachePath}\n";
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

                    echo "Путь к кэшу родительского раздела: {$parentCachePath}\n";

                    $parentFiles = glob($parentCachePath . "index@*.html");

                    if ($parentFiles) {
                        foreach ($parentFiles as $file) {
                            if (file_exists($file)) {
                                unlink($file);
                                echo "Файл кэша родительского раздела удален: {$file}\n";
                            } else {
                                echo "Файл кэша родительского раздела не найден: {$file}\n";
                            }
                        }
                    } else {
                        echo "Файлы кэша родительского раздела не найдены в папке: {$parentCachePath}\n";
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
    echo "Тегированный кэш очищен.\n";

    // echo "Конец clearHtmlCacheByElementId для элемента ID: {$elementId}\n";
}
