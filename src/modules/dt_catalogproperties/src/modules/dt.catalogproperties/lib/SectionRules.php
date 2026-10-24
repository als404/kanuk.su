<?php
namespace DT\CatalogProperties;

use Bitrix\Main\Application;
use Bitrix\Main\SystemException;

/** Category-specific explicit lists. Never reads or writes product property values. */
class SectionRules
{
    const IBLOCK_ID = 16;

    private static function db()
    {
        return Application::getConnection();
    }

    public static function assertSection($sectionId)
    {
        $sectionId = (int)$sectionId;
        if ($sectionId <= 0) {
            throw new SystemException('Некорректный ID раздела.');
        }
        $row = self::db()->query('SELECT ID, IBLOCK_SECTION_ID, NAME FROM b_iblock_section WHERE ID='.$sectionId.' AND IBLOCK_ID='.self::IBLOCK_ID)->fetch();
        if (!$row) {
            throw new SystemException('Раздел не найден в инфоблоке 16.');
        }
        return $row;
    }

    public static function assertProperty($propertyId)
    {
        $propertyId = (int)$propertyId;
        $row = self::db()->query('SELECT ID, NAME FROM b_iblock_property WHERE ID='.$propertyId.' AND IBLOCK_ID='.self::IBLOCK_ID)->fetch();
        if (!$row) {
            throw new SystemException('Свойство не найдено в инфоблоке 16.');
        }
        return $row;
    }

    public static function sections()
    {
        $result = array();
        $rs = self::db()->query('SELECT ID, IBLOCK_SECTION_ID, DEPTH_LEVEL, NAME FROM b_iblock_section WHERE IBLOCK_ID='.self::IBLOCK_ID.' ORDER BY LEFT_MARGIN, ID');
        while ($row = $rs->fetch()) {
            $result[] = $row;
        }
        return $result;
    }

    public static function properties()
    {
        $result = array();
        $rs = self::db()->query("SELECT ID, CODE, NAME, PROPERTY_TYPE, ACTIVE FROM b_iblock_property WHERE IBLOCK_ID=".self::IBLOCK_ID." ORDER BY SORT, NAME, ID");
        while ($row = $rs->fetch()) {
            $result[] = $row;
        }
        return $result;
    }

    public static function assigned($sectionId)
    {
        self::assertSection($sectionId);
        $result = array();
        $rs = self::db()->query('SELECT r.PROPERTY_ID, r.SORT_ORDER, p.NAME, p.CODE, p.ACTIVE, p.PROPERTY_TYPE FROM dt_cp_section_property r INNER JOIN b_iblock_property p ON p.ID=r.PROPERTY_ID AND p.IBLOCK_ID='.self::IBLOCK_ID.' WHERE r.SECTION_ID='.(int)$sectionId.' ORDER BY r.SORT_ORDER, r.PROPERTY_ID');
        while ($row = $rs->fetch()) {
            $result[] = $row;
        }
        return $result;
    }

    public static function isConfigured($sectionId)
    {
        self::assertSection($sectionId);
        return (bool)self::db()->query('SELECT SECTION_ID FROM dt_cp_section_config WHERE SECTION_ID='.(int)$sectionId)->fetch();
    }

    private static function configure($sectionId)
    {
        self::db()->queryExecute('INSERT IGNORE INTO dt_cp_section_config (SECTION_ID) VALUES ('.(int)$sectionId.')');
    }

    /**
     * Увеличивает версию конфигурации характеристик категории.
     * Вызывается после успешного изменения настроек.
     */
    private static function bumpVersion($sectionId)
    {
        self::db()->queryExecute(
            'UPDATE dt_cp_section_config
            SET CONFIG_VERSION = CONFIG_VERSION + 1
            WHERE SECTION_ID = '.(int)$sectionId
        );
    }


    public static function add($sectionId, $propertyId)
    {
        self::assertSection($sectionId);
        self::assertProperty($propertyId);
        $db = self::db();
        $db->startTransaction();
        try {
            self::configure($sectionId);
            $row = $db->query('SELECT COALESCE(MAX(SORT_ORDER), 0) AS MAX_SORT FROM dt_cp_section_property WHERE SECTION_ID='.(int)$sectionId)->fetch();
            $sort = (int)$row['MAX_SORT'] + 100;
            $db->queryExecute('INSERT IGNORE INTO dt_cp_section_property (SECTION_ID,PROPERTY_ID,SORT_ORDER) VALUES ('.(int)$sectionId.','.(int)$propertyId.','.$sort.')');

            self::bumpVersion($sectionId);

            $db->commitTransaction();
        } catch (\Exception $e) {
            $db->rollbackTransaction();
            throw $e;
        }
    }

    public static function remove($sectionId, $propertyId)
    {
        self::assertSection($sectionId);
        self::assertProperty($propertyId);
        $db = self::db();
        $db->startTransaction();

        try {
            $db->queryExecute(
                'DELETE FROM dt_cp_section_property
                WHERE SECTION_ID='.(int)$sectionId.'
                AND PROPERTY_ID='.(int)$propertyId
            );

            self::bumpVersion($sectionId);

            $db->commitTransaction();
        } catch (\Exception $e) {
            $db->rollbackTransaction();
            throw $e;
        }
        // Empty list remains explicitly configured, so downstream code can distinguish it from unconfigured.
    }

    public static function setOrder($sectionId, $propertyIds)
    {
        self::assertSection($sectionId);
        $current = array();
        foreach (self::assigned($sectionId) as $row) {
            $current[(int)$row['PROPERTY_ID']] = true;
        }
        $provided = array();
        foreach ($propertyIds as $propertyId) {
            $id = (int)$propertyId;
            if (!isset($current[$id]) || isset($provided[$id])) {
                throw new SystemException('Некорректный состав списка сортировки.');
            }
            $provided[$id] = true;
        }
        if (count($provided) !== count($current)) {
            throw new SystemException('Список сортировки неполон.');
        }
        $db = self::db();
        $db->startTransaction();

        try {
            $sort = 100;

            foreach (array_keys($provided) as $id) {
                $db->queryExecute(
                    'UPDATE dt_cp_section_property
                    SET SORT_ORDER='.$sort.'
                    WHERE SECTION_ID='.(int)$sectionId.'
                    AND PROPERTY_ID='.$id
                );

                $sort += 100;
            }

            self::bumpVersion($sectionId);

            $db->commitTransaction();
        } catch (\Exception $e) {
            $db->rollbackTransaction();
            throw $e;
        }
    }

    /** Merge-only explicit copy. Existing child properties are never removed or reordered. */
    public static function copyMissingFromParent($sectionId)
    {
        $section = self::assertSection($sectionId);
        $parentId = (int)$section['IBLOCK_SECTION_ID'];
        if ($parentId <= 0) {
            throw new SystemException('У раздела нет родителя.');
        }
        self::assertSection($parentId);
        if (!self::isConfigured($parentId)) {
            throw new SystemException('У родительского раздела набор ещё не настроен.');
        }
        $db = self::db();
        $db->startTransaction();
        try {
            self::configure($sectionId);
            $existing = self::assigned($sectionId);
            $known = array();
            $maxSort = 0;
            foreach ($existing as $item) {
                $known[(int)$item['PROPERTY_ID']] = true;
                $maxSort = max($maxSort, (int)$item['SORT_ORDER']);
            }
            $added = 0;
            foreach (self::assigned($parentId) as $item) {
                $id = (int)$item['PROPERTY_ID'];
                if (isset($known[$id])) {
                    continue;
                }
                $maxSort += 100;
                $db->queryExecute('INSERT INTO dt_cp_section_property (SECTION_ID,PROPERTY_ID,SORT_ORDER) VALUES ('.(int)$sectionId.','.$id.','.$maxSort.')');
                $known[$id] = true;
                $added++;
            }
            if ($added > 0) {
                self::bumpVersion($sectionId);
            }

            $db->commitTransaction();
            return $added;
        } catch (\Exception $e) {
            $db->rollbackTransaction();
            throw $e;
        }
    }
}
