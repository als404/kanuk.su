<?php
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
