<?php
namespace DT\CatalogProperties;

final class AdminMenu
{
    const TARGET_ITEM_ID = 'menu_catalog_attributes_16';
    const MENU_URL = 'dt_catalog_properties.php';

    public static function onBuildGlobalMenu(&$globalMenu, &$moduleMenu)
    {
        global $USER;

        if (!is_object($USER) || !$USER->IsAdmin()) {
            return;
        }

        if (!is_array($moduleMenu)) {
            return;
        }

        foreach ($moduleMenu as &$moduleItem) {
            if (!is_array($moduleItem)) {
                continue;
            }

            if (self::insertAfterProperties($moduleItem)) {
                break;
            }
        }

        unset($moduleItem);
    }

    private static function insertAfterProperties(&$menu)
    {
        if (!isset($menu['items']) || !is_array($menu['items'])) {
            return false;
        }

        foreach ($menu['items'] as $index => &$item) {
            if (!is_array($item)) {
                continue;
            }

            // Пункт уже добавлен.
            if (
                isset($item['url'])
                && strpos((string)$item['url'], self::MENU_URL) !== false
            ) {
                return true;
            }

            // Найден штатный пункт "Свойства товаров" инфоблока №16.
            if (
                isset($item['items_id'])
                && $item['items_id'] === self::TARGET_ITEM_ID
            ) {
                $newItem = array(
                    'text' => 'Характеристики категорий',
                    'title' => 'Настройка характеристик товаров по категориям',
                    'url' => self::MENU_URL,
                    'more_url' => array(self::MENU_URL),
                    'sort' => 150,
                    'items_id' => 'dt_catalog_properties',
                    'module_id' => 'catalog',
                );

                array_splice(
                    $menu['items'],
                    $index + 1,
                    0,
                    array($newItem)
                );

                return true;
            }

            if (self::insertAfterProperties($item)) {
                return true;
            }
        }

        unset($item);

        return false;
    }
}
