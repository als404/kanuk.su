<?php
namespace DT\Documents;

/** Read-only search by ARTNUMBER SQL LIKE pattern and linked MANUFACTURER, iblock 16. */
final class ProductSearch
{
    const MAX_PAGE_SIZE = 50;

    private static function metadata()
    {
        $db = Repository::db();
        $ib = $db->query('SELECT VERSION FROM b_iblock WHERE ID='.CatalogSections::IBLOCK_ID)->fetch();
        if (!$ib) {
            throw new \RuntimeException('Инфоблок 16 не найден.');
        }
        $props = array();
        $rs = $db->query("SELECT ID, CODE, PROPERTY_TYPE, LINK_IBLOCK_ID, MULTIPLE FROM b_iblock_property " .
            "WHERE IBLOCK_ID=".CatalogSections::IBLOCK_ID." AND CODE IN ('ARTNUMBER','MANUFACTURER')");
        while ($row = $rs->fetch()) {
            $props[$row['CODE']] = $row;
        }
        if (!isset($props['ARTNUMBER'], $props['MANUFACTURER']) ||
            $props['ARTNUMBER']['PROPERTY_TYPE'] !== 'S' ||
            $props['MANUFACTURER']['PROPERTY_TYPE'] !== 'E' ||
            $props['MANUFACTURER']['MULTIPLE'] !== 'N' ||
            $props['ARTNUMBER']['MULTIPLE'] !== 'N' ||
            (int)$props['MANUFACTURER']['LINK_IBLOCK_ID'] <= 0) {
            throw new \RuntimeException('Свойства ARTNUMBER/MANUFACTURER инфоблока 16 не соответствуют ожидаемой схеме.');
        }
        return array(
            'version' => (int)$ib['VERSION'],
            'article_id' => (int)$props['ARTNUMBER']['ID'],
            'brand_id' => (int)$props['MANUFACTURER']['ID'],
            'brands_iblock' => (int)$props['MANUFACTURER']['LINK_IBLOCK_ID'],
        );
    }

    public static function brands()
    {
        $meta = self::metadata();
        $brands = array();
        $rs = Repository::db()->query('SELECT ID, NAME FROM b_iblock_element WHERE IBLOCK_ID='.
            (int)$meta['brands_iblock'].' ORDER BY NAME, ID');
        while ($row = $rs->fetch()) {
            $brands[(int)$row['ID']] = (string)$row['NAME'];
        }
        return $brands;
    }

    public static function search($articlePattern, $brandId, $page, $pageSize)
    {
        $articlePattern = trim((string)$articlePattern);
        $brandId = max(0, (int)$brandId);
        $page = max(1, (int)$page);
        $pageSize = max(1, min(self::MAX_PAGE_SIZE, (int)$pageSize));
        if ($articlePattern === '' && !$brandId) {
            return array('total'=>0, 'items'=>array());
        }
        if (strlen($articlePattern) > 191 || preg_match('/[\x00-\x1F\x7F]/', $articlePattern)) {
            throw new \RuntimeException('Некорректный шаблон артикула (не более 191 байта).');
        }
        $meta = self::metadata();
        $sql = self::baseSql($meta);
        $where = array('e.IBLOCK_ID='.CatalogSections::IBLOCK_ID);
        if ($brandId) {
            $where[] = 'CAST(p_manufacturer AS UNSIGNED)='.$brandId;
        }
        if ($articlePattern !== '') {
            // % means any number of characters, _ exactly one character.
            // MySQL ESCAPE ! lets an administrator use !_ and !% literally.
            $hasWildcard = strpos($articlePattern, '_') !== false || strpos($articlePattern, '%') !== false;
            if ($hasWildcard) {
                $where[] = 'p_article LIKE '.Repository::quote($articlePattern)." ESCAPE '!'";
            } else {
                $where[] = 'p_article='.Repository::quote($articlePattern);
            }
        }
        // Expressions are substituted only from a trusted fixed SQL map, never from input.
        $whereSql = ' WHERE '.implode(' AND ', $where);
        $whereSql = str_replace(array('p_manufacturer', 'p_article'),
            array($sql['brand_expr'], $sql['article_expr']), $whereSql);
        $db = Repository::db();
        $countSql = 'SELECT COUNT(DISTINCT e.ID) AS CNT '.$sql['from'].$whereSql;
        $countRow = $db->query($countSql)->fetch();
        $total = $countRow ? (int)$countRow['CNT'] : 0;
        $offset = ($page-1)*$pageSize;
        $dataSql = 'SELECT DISTINCT e.ID, e.NAME, e.ACTIVE, e.IBLOCK_SECTION_ID, '.
            $sql['article_expr'].' AS ARTICLE, brand_e.ID AS BRAND_ID, brand_e.NAME AS BRAND '.
            $sql['from'].$whereSql.' ORDER BY e.ID ASC LIMIT '.$pageSize.' OFFSET '.$offset;
        $result = array();
        $rs = $db->query($dataSql);
        while ($row = $rs->fetch()) {
            $result[] = $row;
        }
        return array('total'=>$total, 'items'=>$result);
    }

    private static function baseSql($meta)
    {
        if ($meta['version'] === 2) {
            $article = 'props.PROPERTY_'.(int)$meta['article_id'];
            $manufacturer = 'props.PROPERTY_'.(int)$meta['brand_id'];
            $from = 'FROM b_iblock_element e INNER JOIN b_iblock_element_prop_s'.CatalogSections::IBLOCK_ID.
                ' props ON props.IBLOCK_ELEMENT_ID=e.ID ';
        } elseif ($meta['version'] === 1) {
            $article = 'art.VALUE';
            $manufacturer = 'maker.VALUE';
            $from = 'FROM b_iblock_element e '.
                'LEFT JOIN b_iblock_element_property art ON art.IBLOCK_ELEMENT_ID=e.ID AND art.IBLOCK_PROPERTY_ID='.(int)$meta['article_id'].' '.
                'LEFT JOIN b_iblock_element_property maker ON maker.IBLOCK_ELEMENT_ID=e.ID AND maker.IBLOCK_PROPERTY_ID='.(int)$meta['brand_id'].' ';
        } else {
            throw new \RuntimeException('Неизвестный режим хранения свойств инфоблока 16.');
        }
        $from .= 'LEFT JOIN b_iblock_element brand_e ON brand_e.ID=CAST('.$manufacturer.' AS UNSIGNED) ';
        return array('from'=>$from,'article_expr'=>$article,'brand_expr'=>$manufacturer);
    }
}
