<?php
namespace DT\Documents;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

/** Resolves document bindings using catalog properties and section membership. */
final class Resolver
{
    public static function matched($product)
    {
        if ((int)(isset($product['IBLOCK_ID']) ? $product['IBLOCK_ID'] : 0) !== CatalogSections::IBLOCK_ID) {
            return array();
        }
        $productId = (int)(isset($product['ID']) ? $product['ID'] : 0);
        if ($productId <= 0) {
            return array();
        }
        $props = isset($product['PROPERTIES']) ? $product['PROPERTIES'] : array();
        $brand = trim((string)(isset($props['MANUFACTURER']['NAME']) ? $props['MANUFACTURER']['NAME'] : ''));
        // Normalize a comparison key, without changing the official display name.
        // mb_strtolower correctly handles Cyrillic; the DB column uses utf8mb4_unicode_ci.
        $brandKey = function_exists('mb_strtolower')
            ? mb_strtolower($brand, Encoding::charset()) : strtolower($brand);
        $article = trim((string)(isset($props['ARTNUMBER']['VALUE']) ? $props['ARTNUMBER']['VALUE'] : ''));

        $sections = CatalogSections::productSectionIds(
            $productId, isset($product['IBLOCK_SECTION_ID']) ? $product['IBLOCK_SECTION_ID'] : 0
        );
        $brandMatch = $brand !== '' ?
            'EXISTS (SELECT 1 FROM dt_doc_brand db WHERE db.DOCUMENT_ID=d.ID AND LOWER(db.BRAND)='.Repository::quote($brandKey).')' : '';
        $articleMatch = $article !== '' ?
            'EXISTS (SELECT 1 FROM dt_doc_article da WHERE da.DOCUMENT_ID=d.ID AND da.ARTICLE='.Repository::quote($article).')' : '';
        $sectionMatch = '';
        if ($sections) {
            $ids = implode(',', array_map('intval', $sections));
            $sectionMatch = 'EXISTS (SELECT 1 FROM dt_doc_section ds '.
                'INNER JOIN b_iblock_section rule_section ON rule_section.ID=ds.SECTION_ID AND rule_section.IBLOCK_ID=16 '.
                'INNER JOIN b_iblock_section member_section ON member_section.IBLOCK_ID=16 AND member_section.ID IN ('.$ids.') '.
                'WHERE ds.DOCUMENT_ID=d.ID AND (member_section.ID=rule_section.ID OR '.
                '(ds.INCLUDE_SUBSECTIONS=\'Y\' AND member_section.LEFT_MARGIN>=rule_section.LEFT_MARGIN '.
                'AND member_section.RIGHT_MARGIN<=rule_section.RIGHT_MARGIN)))';
        }
        $terms = array();
        if ($brandMatch !== '' && $articleMatch !== '') {
            $legacyParts = array('('.$brandMatch.' AND '.$articleMatch.')');
            if ($sectionMatch !== '') $legacyParts[] = $sectionMatch;
            $terms[] = '(d.MATCH_MODE=\'legacy\' AND ('.implode(' OR ', $legacyParts).'))';
            $terms[] = '(d.MATCH_MODE=\'brand_article\' AND '.$brandMatch.' AND '.$articleMatch.')';
        } elseif ($sectionMatch !== '') {
            $terms[] = '(d.MATCH_MODE=\'legacy\' AND '.$sectionMatch.')';
        }
        if ($brandMatch !== '') {
            $terms[] = '(d.MATCH_MODE=\'brand\' AND '.$brandMatch.')';
        }
        if ($sectionMatch !== '') {
            $terms[] = '(d.MATCH_MODE=\'section\' AND '.$sectionMatch.')';
        }
        if ($brandMatch !== '' && $sectionMatch !== '') {
            $terms[] = '(d.MATCH_MODE=\'brand_section\' AND '.$brandMatch.' AND '.$sectionMatch.')';
        }
        $result = array();
        $manual = self::automaticManual($brand, $article);
        if ($manual) $result[] = $manual;
        if (!$terms) return $result;
        $sql = 'SELECT d.* FROM dt_doc_document d WHERE d.ACTIVE=\'Y\' AND ('.implode(' OR ', $terms).') '.
            'ORDER BY FIELD(d.TYPE,\'manual\',\'document\',\'article\'), d.SORT_ORDER, d.ID';
        $rs = Application::getConnection()->query($sql);
        while ($row = $rs->fetch()) {
            // Only avoid duplicating the automatically found instruction.
            if ($manual && $row['TARGET_URL'] === $manual['TARGET_URL']) {
                continue;
            }
            $result[] = $row;
        }
        return $result;
    }

    public static function automaticManual($brand, $article)
    {
        if (Option::get('dt.documents', 'auto_manual', 'Y') !== 'Y') {
            return null;
        }
        $brand = Encoding::toUtf8(self::lower(trim((string)$brand)));
        $article = Encoding::toUtf8(self::lower(trim((string)$article)));
        // Path segments only; never construct or probe arbitrary filesystem paths.
        if ($brand === '' || $article === '' || strpos($brand, '/') !== false ||
            strpos($article, '/') !== false || strpos($brand, '\\') !== false ||
            strpos($article, '\\') !== false || strpos($brand, '..') !== false ||
            strpos($article, '..') !== false || preg_match('/[\x00-\x1F]/', $brand.$article)) {
            return null;
        }
        $base = '/upload/doc/manual/'.$brand.'/'.$article;
        if (!is_file($_SERVER['DOCUMENT_ROOT'].$base.'.pdf')) {
            return null;
        }
        $image = '/upload/doc/manual/'.$brand.'/pic/'.$article.'.jpg';
        return array(
            'ID' => 0,
            'TYPE' => 'manual',
            'NAME' => 'Инструкция (паспорт)',
            'LINK_TEXT' => 'Инструкция (паспорт)',
            'LINK_TITLE' => 'инструкция, паспорт изделия',
            'LINK_ALT' => 'инструкция, паспорт изделия',
            'TARGET_URL' => Encoding::fromUtf8($base.'.pdf'),
            'IMAGE_URL' => is_file($_SERVER['DOCUMENT_ROOT'].$image) ? Encoding::fromUtf8($image) : '',
            'SORT_ORDER' => -99999,
        );
    }

    private static function lower($text)
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text, Encoding::charset()) : strtolower($text);
    }
}
