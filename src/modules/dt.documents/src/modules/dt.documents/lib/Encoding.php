<?php
namespace DT\Documents;

/** Legacy Bitrix installations may use windows-1251 frontend and DB connections. */
final class Encoding
{
    public static function charset()
    {
        return defined('LANG_CHARSET') ? (string)LANG_CHARSET : 'UTF-8';
    }

    private static function isUtf8()
    {
        return in_array(strtoupper(str_replace('-', '', self::charset())), array('UTF8', 'UTF8MB4'), true);
    }

    public static function fromUtf8($input)
    {
        if (is_array($input)) {
            $out = array();
            foreach ($input as $key => $value) {
                $out[$key] = self::fromUtf8($value);
            }
            return $out;
        }
        if (!is_string($input) || self::isUtf8()) {
            return $input;
        }
        $result = iconv('UTF-8', self::charset(), $input);
        if ($result === false) {
            throw new \RuntimeException('Не удалось преобразовать данные UTF-8 в '.self::charset());
        }
        return $result;
    }

    public static function toUtf8($input)
    {
        if (is_array($input)) {
            $out = array();
            foreach ($input as $key => $value) {
                $out[$key] = self::toUtf8($value);
            }
            return $out;
        }
        if (!is_string($input) || self::isUtf8()) {
            return $input;
        }
        $result = iconv(self::charset(), 'UTF-8', $input);
        if ($result === false) {
            throw new \RuntimeException('Не удалось преобразовать текст '.self::charset().' в UTF-8.');
        }
        return $result;
    }

    public static function json($input)
    {
        // Escaped non-ASCII chars remain valid inside both UTF-8 and CP1251 HTML.
        $json = json_encode(self::toUtf8($input),
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($json === false) {
            throw new \RuntimeException('Ошибка сериализации списка разделов.');
        }
        return $json;
    }
}
