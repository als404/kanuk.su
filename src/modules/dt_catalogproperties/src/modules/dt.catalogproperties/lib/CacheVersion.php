<?php

namespace DT\CatalogProperties;

use Bitrix\Main\Application;

class CacheVersion
{
    const IBLOCK_ID = 16;

    /**
     * Возвращает версию конфигурации характеристик товара.
     *
     * Не загружает свойства товара и не изменяет данные.
     */
    public static function forElement($elementId, $elementCode)
    {
        $db = Application::getConnection();

        $elementId = (int)$elementId;
        $elementCode = trim((string)$elementCode);

        if ($elementId > 0) {
            $where = 'e.ID = '.$elementId;
        } elseif ($elementCode !== '') {
            $helper = $db->getSqlHelper();
            $where = "e.CODE = '".$helper->forSql($elementCode)."'";
        } else {
            return 0;
        }

        $sql = '
            SELECT COALESCE(c.CONFIG_VERSION, 0) AS CONFIG_VERSION
            FROM b_iblock_element e
            LEFT JOIN dt_cp_section_config c
                ON c.SECTION_ID = e.IBLOCK_SECTION_ID
            WHERE e.IBLOCK_ID = '.self::IBLOCK_ID.'
              AND '.$where.'
            LIMIT 1
        ';

        $row = $db->query($sql)->fetch();

        return $row ? (int)$row['CONFIG_VERSION'] : 0;
    }
}