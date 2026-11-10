<?php
namespace DT\Documents;

final class AdminMenu
{
    public static function onBuildGlobalMenu(&$globalMenu, &$moduleMenu)
    {
        global $USER;
        if (!is_object($USER) || !$USER->IsAdmin()) {
            return;
        }
        if (!is_array($moduleMenu)) {
            $moduleMenu = array();
        }
        foreach ($moduleMenu as $item) {
            if (isset($item['items_id']) && $item['items_id'] === 'dt_documents') {
                return;
            }
        }
        $moduleMenu[] = array(
            'parent_menu' => 'global_menu_content',
            'section' => 'dt_documents',
            'sort' => 160,
            'text' => 'Документы товаров',
            'title' => 'Документы в карточках каталога',
            'url' => 'dt_documents_list.php?lang='.LANGUAGE_ID,
            'items_id' => 'dt_documents',
            'module_id' => 'dt.documents',
            'more_url' => array('dt_documents_edit.php', 'dt_documents_import.php', 'dt_documents_settings.php', 'dt_documents_preview.php', 'dt_documents_cache.php'),
            'items' => array(
                array('text'=>'Все материалы', 'url'=>'dt_documents_list.php?lang='.LANGUAGE_ID, 'more_url'=>array('dt_documents_edit.php')),
                array('text'=>'Проверка товара', 'url'=>'dt_documents_preview.php?lang='.LANGUAGE_ID),
                array('text'=>'Импорт старой базы', 'url'=>'dt_documents_import.php?lang='.LANGUAGE_ID),
                array('text'=>'Очередь кеша', 'url'=>'dt_documents_cache.php?lang='.LANGUAGE_ID),
                array('text'=>'Настройки вывода', 'url'=>'dt_documents_settings.php?lang='.LANGUAGE_ID),
            ),
        );
    }
}
