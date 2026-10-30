<?php
/**
 * Read-only resolver for Bitrix iblock 16 product specifications.
 * Resolves S:directory UF_XML_ID values to Highload-block UF_NAME.
 * Does not alter DISPLAY_PROPERTIES or save product data.
 * PHP 7.4 compatible.
 */
namespace DT\CatalogProperties;

final class ElementProperties
{
    const IBLOCK_ID = 16;

    /**
     * @return array {configured: bool, section_id: int, properties: array, skipped: array}
     */
    public static function resolve($elementId, $sectionId)
    {
        global $DB;
        $elementId = (int)$elementId;
        $sectionId = (int)$sectionId;
        $result = array('configured' => false, 'section_id' => $sectionId, 'properties' => array(), 'skipped' => array());
        if ($elementId <= 0 || $sectionId <= 0) {
            return $result;
        }
        if (!isset($DB) || !is_object($DB) || !class_exists('CIBlockElement')) {
            throw new \RuntimeException('Bitrix iblock/prolog not loaded');
        }

        // Only a category explicitly configured in dt_cp_section_config switches mode.
        $rs = $DB->Query('SELECT SECTION_ID FROM dt_cp_section_config WHERE SECTION_ID='.$sectionId.' LIMIT 1');
        if (!$rs->Fetch()) {
            return $result;
        }
        $result['configured'] = true;

        $rules = array();
        $rs = $DB->Query(
            'SELECT r.PROPERTY_ID, p.CODE, p.NAME, p.PROPERTY_TYPE, p.USER_TYPE, p.USER_TYPE_SETTINGS, p.ACTIVE '.
            'FROM dt_cp_section_property r '.
            'INNER JOIN b_iblock_property p ON p.ID=r.PROPERTY_ID AND p.IBLOCK_ID='.self::IBLOCK_ID.' '.
            'WHERE r.SECTION_ID='.$sectionId.' ORDER BY r.SORT_ORDER, r.PROPERTY_ID'
        );
        while ($row = $rs->Fetch()) {
            $pid = (int)$row['PROPERTY_ID'];
            if ($row['ACTIVE'] !== 'Y') {
                $result['skipped'][$pid] = 'inactive';
                continue;
            }
            if (!in_array($row['PROPERTY_TYPE'], array('S', 'N', 'L'), true)) {
                $result['skipped'][$pid] = 'unsupported_type_'.$row['PROPERTY_TYPE'];
                continue;
            }
            $rules[$pid] = $row;
        }
        if (!$rules) {
            return $result;
        }

        // One Bitrix API call, not a separate query for each property.
        $values = array();
        $dbProperties = \CIBlockElement::GetProperty(
            self::IBLOCK_ID,
            $elementId,
            array('sort' => 'asc', 'id' => 'asc'),
            array('EMPTY' => 'N')
        );
        while ($prop = $dbProperties->Fetch()) {
            $pid = (int)$prop['ID'];
            if (!isset($rules[$pid])) {
                continue;
            }
            $value = ($prop['PROPERTY_TYPE'] === 'L') ? $prop['VALUE_ENUM'] : $prop['VALUE'];
            if (!is_scalar($value)) {
                $result['skipped'][$pid] = 'non_scalar_value';
                continue;
            }
            $value = trim((string)$value);
            // Zero is a meaningful characteristic value.
            if ($value === '') {
                continue;
            }
            if (!isset($values[$pid])) {
                $values[$pid] = array();
            }
            $values[$pid][] = $value;
        }

        // Resolve XML IDs in directory properties once per property (also supports multi-value).
        foreach ($rules as $pid => $rule) {
            if (empty($values[$pid]) || $rule['PROPERTY_TYPE'] !== 'S' || $rule['USER_TYPE'] !== 'directory') {
                continue;
            }
            $settings = $rule['USER_TYPE_SETTINGS'];
            if (is_string($settings)) {
                $settings = @unserialize($settings, array('allowed_classes' => false));
            }
            $tableName = is_array($settings) && isset($settings['TABLE_NAME'])
                ? (string)$settings['TABLE_NAME'] : '';
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
                $result['skipped'][$pid] = 'invalid_directory_table';
                continue;
            }
            $map = self::directoryNames($tableName, $values[$pid]);
            // Unknown XML IDs remain visible rather than silently disappearing.
            foreach ($values[$pid] as $index => $xmlId) {
                if (array_key_exists($xmlId, $map)) {
                    $values[$pid][$index] = $map[$xmlId];
                }
            }
        }

        // Ensure output order follows category configuration, not the property API.
        foreach ($rules as $pid => $rule) {
            if (empty($values[$pid])) {
                continue;
            }
            $result['properties'][] = array(
                'ID' => $pid,
                'CODE' => (string)$rule['CODE'],
                'NAME' => html_entity_decode((string)$rule['NAME'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'PROPERTY_TYPE' => (string)$rule['PROPERTY_TYPE'],
                'VALUES' => $values[$pid],
            );
        }
        return $result;
    }

    /** @return array<string,string> */
    private static function directoryNames($tableName, array $xmlIds)
    {
        if (!\Bitrix\Main\Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Bitrix highloadblock module not loaded');
        }
        $hl = \Bitrix\Highloadblock\HighloadBlockTable::getList(array(
            'filter' => array('=TABLE_NAME' => $tableName),
            'limit' => 1,
        ))->fetch();
        if (!$hl) {
            throw new \RuntimeException('Highload directory not found: '.$tableName);
        }
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hl);
        $dataClass = $entity->getDataClass();
        $rows = $dataClass::getList(array(
            'select' => array('UF_XML_ID', 'UF_NAME'),
            'filter' => array('@UF_XML_ID' => array_values(array_unique($xmlIds))),
        ));
        $map = array();
        while ($row = $rows->fetch()) {
            if (isset($row['UF_XML_ID']) && is_scalar($row['UF_XML_ID']) &&
                isset($row['UF_NAME']) && is_scalar($row['UF_NAME'])) {
                $map[(string)$row['UF_XML_ID']] = (string)$row['UF_NAME'];
            }
        }
        return $map;
    }
}
