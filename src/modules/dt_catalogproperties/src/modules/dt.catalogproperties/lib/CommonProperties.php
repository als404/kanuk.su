<?php
/** Common catalog detail properties. Bitrix 17.5 / PHP 7.4 compatible. */
namespace DT\CatalogProperties;

final class CommonProperties
{
    private const IBLOCK_ID = 16;
    private const CODES = array('ARTNUMBER', 'MANUFACTURER', 'COUNTRY', 'GUARANTEE');

    /** Returns template-ready properties, ordered. Does not modify products. */
    public static function resolve($elementId)
    {
        if (!class_exists('CIBlockElement')) {
            throw new \RuntimeException('Bitrix iblock module is not loaded');
        }
        $elementId = (int)$elementId;
        if ($elementId <= 0) {
            return array();
        }

        $collected = array();
        $iterator = \CIBlockElement::GetProperty(self::IBLOCK_ID, $elementId, 'sort', 'asc');
        while ($property = $iterator->Fetch()) {
            $code = (string)$property['CODE'];
            if (!in_array($code, self::CODES, true)) {
                continue;
            }

            $value = $property['VALUE'];
            if ($value === null || $value === '' || $value === false || (is_array($value) && !$value)) {
                continue;
            }

            if ($code === 'MANUFACTURER') {
                $manufacturer = self::getManufacturer($value);
                if ($manufacturer !== null) {
                    $collected[$code][] = $manufacturer;
                }
            } elseif ($property['PROPERTY_TYPE'] === 'L') {
                $enum = $property['VALUE_ENUM'];
                if ($enum !== null && $enum !== '') {
                    $collected[$code][] = self::escape($enum);
                }
            } else {
                $collected[$code][] = self::escape($value);
            }
        }

        $names = array(
            'ARTNUMBER' => 'Артикул',
            'MANUFACTURER' => 'Производитель',
            'COUNTRY' => 'Производство',
            'GUARANTEE' => 'Гарантия',
        );
        $output = array();
        foreach (self::CODES as $code) {
            if (empty($collected[$code])) {
                continue;
            }
            $output[$code] = array(
                'NAME' => $names[$code],
                'DISPLAY_VALUE' => implode(', ', $collected[$code]),
            );
        }
        return $output;
    }

    private static function getManufacturer($elementId)
    {
        $iterator = \CIBlockElement::GetByID((int)$elementId);
        $element = $iterator ? $iterator->GetNext() : false;
        if (!$element || empty($element['NAME'])) {
            return null;
        }

        $name = self::escape(html_entity_decode((string)$element['NAME'], ENT_QUOTES | ENT_HTML5, self::charset()));
        $url = isset($element['DETAIL_PAGE_URL']) ? html_entity_decode((string)$element['DETAIL_PAGE_URL'], ENT_QUOTES | ENT_HTML5, self::charset()) : '';
        // Accept a resolved local URL only; never invent vendor URLs or output template macros.
        if ($url !== '' && $url[0] === '/' && substr($url, 0, 2) !== '//' && strpos($url, '#') === false && strpos($url, '{') === false) {
            return '<a href="'.self::escape($url).'">'.$name.'</a>';
        }
        return $name;
    }

    private static function charset()
    {
        return defined('LANG_CHARSET') ? LANG_CHARSET : 'UTF-8';
    }

    private static function escape($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, self::charset());
    }
}
