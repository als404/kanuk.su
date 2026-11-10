<?php
namespace DT\Documents;

use Bitrix\Main\Config\Option;

/** Invalidate only configured-host composite pages of known catalog elements. */
final class CachePurger
{
    public static function htmlCacheRoot()
    {
        $host = trim((string)Option::get('dt.documents', 'cache_host', ''));
        if ($host === '' || strlen($host) > 253 || !preg_match('/^[a-z0-9.-]+$/i', $host) || strpos($host, '..') !== false) {
            throw new \RuntimeException('Сначала укажите корректный домен композитного кеша в настройках dt.documents.');
        }
        $root = realpath($_SERVER['DOCUMENT_ROOT'].'/bitrix/html_pages');
        if ($root === false) {
            throw new \RuntimeException('Каталог bitrix/html_pages не найден: проверьте композитный кеш.');
        }
        $base = realpath($root.'/'.$host);
        if ($base === false || strpos($base, $root.'/') !== 0) {
            throw new \RuntimeException('Каталог композитного кеша домена '.$host.' не найден. Обработка остановлена.');
        }
        return $base;
    }

    public static function clearCatalogTag()
    {
        // The original catalog component caches by iblock. This broader *tag* is
        // cleared ONCE per document edit, not once per product or batch.
        \Bitrix\Main\Application::getInstance()->getTaggedCache()->clearByTag('iblock_id_16');
    }

    public static function invalidateProduct($id, $base)
    {
        $id = (int)$id;
        if ($id <= 0) return 0;
        $rs = \CIBlockElement::GetByID($id);
        $product = $rs ? $rs->GetNext() : false;
        if (!$product || (int)$product['IBLOCK_ID'] !== CatalogSections::IBLOCK_ID) return 0;
        $code = isset($product['CODE']) ? (string)$product['CODE'] : '';
        if ($code === '' || strpos($code, '/') !== false || strpos($code, '\\') !== false || strpos($code, '..') !== false) {
            throw new \RuntimeException('Невозможно сформировать URL товара #'.$id.'.');
        }
        $paths = array();
        if (isset($product['DETAIL_PAGE_URL']) && strpos($product['DETAIL_PAGE_URL'], '/catalog/') === 0) {
            $paths[] = parse_url(html_entity_decode($product['DETAIL_PAGE_URL']), PHP_URL_PATH);
        }
        $groups = \CIBlockElement::GetElementGroups($id, true, array('ID','CODE','IBLOCK_SECTION_ID'));
        $foundGroups = 0;
        while ($group = $groups->Fetch()) {
            $foundGroups++;
            $codes = array();
            $sectionId = (int)$group['ID'];
            $guard = 0;
            while ($sectionId > 0 && ++$guard <= 40) {
                $sectionRS = \CIBlockSection::GetList(array(), array('ID'=>$sectionId,'IBLOCK_ID'=>16), false, array('ID','CODE','IBLOCK_SECTION_ID'));
                $section = $sectionRS->Fetch();
                if (!$section || !isset($section['CODE']) || !preg_match('/^[a-zA-Z0-9._-]+$/', (string)$section['CODE']) || strpos($section['CODE'],'..') !== false) break;
                array_unshift($codes, $section['CODE']);
                $sectionId = (int)$section['IBLOCK_SECTION_ID'];
            }
            if (!$codes) continue;
            $paths[] = '/catalog/'.implode('/', $codes).'/'.$code.'/';
            $paths[] = '/catalog/'.end($codes).'/'.$code.'/'; // legacy flat URLs
        }
        if (!$paths && $foundGroups === 0 && empty($product['DETAIL_PAGE_URL'])) {
            throw new \RuntimeException('Не найден адрес карточки товара #'.$id.'.');
        }
        $deleted = 0;
        foreach (array_unique(array_filter($paths)) as $url) {
            if (!is_string($url) || strpos($url, '/catalog/') !== 0 || strpos($url, '..') !== false || strpos($url, '\\') !== false) continue;
            $path = rawurldecode((string)parse_url($url, PHP_URL_PATH));
            if ($path === '' || strpos($path, '..') !== false || strpos($path, '\\') !== false) continue;
            $dir = realpath($base.'/'.trim($path, '/'));
            if ($dir === false || strpos($dir, $base.'/catalog/') !== 0) continue;
            foreach (array_merge(glob($dir.'/index@*.html') ?: array(), glob($dir.'/index.html') ?: array()) as $file) {
                if (is_link($file) || !is_file($file)) continue;
                if (!@unlink($file)) throw new \RuntimeException('Не удалось удалить композитный кеш: '.$file);
                $deleted++;
            }
        }
        \Bitrix\Main\Application::getInstance()->getTaggedCache()->clearByTag('element_'.$id);
        return $deleted;
    }
}
