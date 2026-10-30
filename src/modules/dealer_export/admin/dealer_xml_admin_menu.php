<?php
// Bitrix 17.5.8: Магазин -> Продукция -> XML для дилеров.
// Подключается из /bitrix/php_interface/init.php.
// Не создаёт новые разделы: вставляет пункт после «Характеристики категорий».

if (!function_exists('discountDealerXmlOnBuildGlobalMenu')) {
    function discountDealerXmlContainsItemId($node, $itemId)
    {
        if (!is_array($node)) {
            return false;
        }
        if (isset($node['items_id']) && $node['items_id'] === $itemId) {
            return true;
        }
        if (!empty($node['items']) && is_array($node['items'])) {
            foreach ($node['items'] as $child) {
                if (discountDealerXmlContainsItemId($child, $itemId)) {
                    return true;
                }
            }
        }
        return false;
    }

    function discountDealerXmlInsertAfterId(&$node, $targetId, $newItem)
    {
        if (!is_array($node) || !isset($node['items']) || !is_array($node['items'])) {
            return false;
        }

        foreach ($node['items'] as $index => $child) {
            if (!is_array($child)) {
                continue;
            }
            if (isset($child['items_id']) && $child['items_id'] === $targetId) {
                array_splice($node['items'], $index + 1, 0, array($newItem));
                return true;
            }
            if (discountDealerXmlInsertAfterId($node['items'][$index], $targetId, $newItem)) {
                return true;
            }
        }
        return false;
    }

    function discountDealerXmlOnBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
    {
        global $USER;
        if (!is_object($USER) || !$USER->IsAdmin() || !is_array($aModuleMenu)) {
            return;
        }

        $itemId = 'menu_discount_dealer_xml';
        foreach ($aModuleMenu as $section) {
            if (discountDealerXmlContainsItemId($section, $itemId)) {
                return;
            }
        }

        $item = array(
            'text' => 'XML для дилеров',
            'title' => 'BREXIT и ESSON: ссылки, статус и ручная генерация',
            'url' => 'discount_dealer_xml.php?lang=' . LANGUAGE_ID,
            'more_url' => array('discount_dealer_xml.php'),
            'items_id' => $itemId,
            'sort' => 160,
            'module_id' => 'catalog',
        );

        // Модуль dt.catalogproperties добавляет этот пункт с приоритетом 10000.
        // Если его нет, используем штатный ID «Свойства товаров» инфоблока 16.
        foreach (array('dt_catalog_properties', 'menu_catalog_attributes_16') as $targetId) {
            foreach ($aModuleMenu as &$section) {
                if (discountDealerXmlInsertAfterId($section, $targetId, $item)) {
                    unset($section);
                    return;
                }
            }
            unset($section);
        }
        // Без подходящего пункта ничего не добавляем — никаких дублей «Продукции».
    }

    // Обработчик «Характеристики категорий» зарегистрирован с приоритетом 10000.
    AddEventHandler('main', 'OnBuildGlobalMenu', 'discountDealerXmlOnBuildGlobalMenu', 11000);
}
