<?php
namespace DT\Documents;

use Bitrix\Main\Application;

final class CatalogSections
{
    const IBLOCK_ID = 16;

    public static function all()
    {
        $rows = array();
        $rs = Application::getConnection()->query(
            'SELECT ID, IBLOCK_SECTION_ID, NAME, DEPTH_LEVEL, LEFT_MARGIN, RIGHT_MARGIN ' .
            'FROM b_iblock_section WHERE IBLOCK_ID=' . self::IBLOCK_ID . ' ORDER BY LEFT_MARGIN, ID'
        );
        while ($row = $rs->fetch()) {
            $rows[(int)$row['ID']] = $row;
        }
        $paths = array();
        foreach ($rows as $id => $row) {
            $chain = array();
            $visited = array();
            $current = $id;
            while ($current && isset($rows[$current]) && !isset($visited[$current])) {
                $visited[$current] = true;
                array_unshift($chain, $rows[$current]['NAME']);
                $current = (int)$rows[$current]['IBLOCK_SECTION_ID'];
            }
            $paths[$id] = implode(' / ', $chain);
        }
        $out = array();
        foreach ($rows as $id => $row) {
            $out[] = array(
                'id' => $id,
                'name' => (string)$row['NAME'],
                'parent' => (int)$row['IBLOCK_SECTION_ID'],
                'path' => $paths[$id],
            );
        }
        return $out;
    }

    public static function assertValidIds($ids)
    {
        $valid = array();
        foreach (self::all() as $row) {
            $valid[$row['id']] = true;
        }
        foreach ($ids as $id) {
            if (!isset($valid[(int)$id])) {
                throw new \RuntimeException('Раздел #'.(int)$id.' не принадлежит инфоблоку 16.');
            }
        }
    }

    public static function productSectionIds($productId, $primarySectionId)
    {
        $ids = array();
        $primarySectionId = (int)$primarySectionId;
        if ($primarySectionId > 0) {
            $ids[$primarySectionId] = $primarySectionId;
        }
        $productId = (int)$productId;
        if ($productId > 0) {
            $rs = Application::getConnection()->query(
                'SELECT s.ID FROM b_iblock_section_element se ' .
                'INNER JOIN b_iblock_section s ON s.ID=se.IBLOCK_SECTION_ID AND s.IBLOCK_ID=16 ' .
                'WHERE se.IBLOCK_ELEMENT_ID='.$productId
            );
            while ($row = $rs->fetch()) {
                $ids[(int)$row['ID']] = (int)$row['ID'];
            }
        }
        return array_values($ids);
    }
}
