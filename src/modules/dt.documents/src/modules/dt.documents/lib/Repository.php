<?php
namespace DT\Documents;

use Bitrix\Main\Application;

/** Database access for this module only. Never modifies Bitrix catalog tables. */
final class Repository
{
    public static function db()
    {
        return Application::getConnection();
    }

    public static function quote($value)
    {
        return "'" . self::db()->getSqlHelper()->forSql((string)$value) . "'";
    }

    public static function escapeHtml($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, defined('LANG_CHARSET') ? LANG_CHARSET : 'UTF-8');
    }

    public static function splitLines($text)
    {
        $items = is_array($text) ? $text : preg_split('/[\r\n,;]+/', (string)$text);
        $out = array();
        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            if (strlen($item) > 191) {
                throw new \RuntimeException('Производитель или артикул длиннее 191 байта.');
            }
            $out[(function_exists('mb_strtolower') ? mb_strtolower($item, Encoding::charset()) : strtolower($item))] = $item;
        }
        return array_values($out);
    }

    public static function safeUrl($url, $allowExternal)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }
        if (strlen($url) > 1024 || preg_match('/[\x00-\x1F\x7F\\\\]/', $url)) {
            throw new \RuntimeException('Некорректный URL файла или страницы.');
        }
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            if (preg_match('~(?:^|/)\.\.(?:/|$)~', $url)) {
                throw new \RuntimeException('Путь с переходом в родительский каталог запрещён.');
            }
            return $url;
        }
        if ($allowExternal && preg_match('~^https?://[^\s/@]+(?:/[^\s]*)?$~i', $url)) {
            return $url;
        }
        throw new \RuntimeException('Допускаются внутренние пути, начинающиеся с /'.($allowExternal ? ', или http(s)-ссылки.' : '.'));
    }

    private static function normalize($input)
    {
        $type = isset($input['TYPE']) ? (string)$input['TYPE'] : '';
        if (!in_array($type, array('manual', 'document', 'article'), true)) {
            throw new \RuntimeException('Недопустимый тип материала.');
        }
        $name = trim((string)(isset($input['NAME']) ? $input['NAME'] : ''));
        if ($name === '' || strlen($name) > 255) {
            throw new \RuntimeException('Название обязательно, максимум 255 байт.');
        }
        $text = trim((string)(isset($input['LINK_TEXT']) ? $input['LINK_TEXT'] : ''));
        if ($text === '') {
            $text = $name;
        }
        if (strlen($text) > 255) {
            throw new \RuntimeException('Текст ссылки слишком длинный.');
        }
        $title = (string)(isset($input['LINK_TITLE']) ? $input['LINK_TITLE'] : '');
        $alt = (string)(isset($input['LINK_ALT']) ? $input['LINK_ALT'] : '');
        if (strlen($title) > 512 || strlen($alt) > 512) {
            throw new \RuntimeException('Title/Alt слишком длинные.');
        }
        $url = self::safeUrl(isset($input['TARGET_URL']) ? $input['TARGET_URL'] : '', true);
        $image = self::safeUrl(isset($input['IMAGE_URL']) ? $input['IMAGE_URL'] : '', false);
        if ($url === '') {
            throw new \RuntimeException('Укажите URL документа или страницы статьи.');
        }
        $brands = self::splitLines(isset($input['BRANDS']) ? $input['BRANDS'] : array());
        $articles = self::splitLines(isset($input['ARTICLES']) ? $input['ARTICLES'] : array());
        $sections = isset($input['SECTIONS']) && is_array($input['SECTIONS']) ? $input['SECTIONS'] : array();
        $selectedSections = array();
        foreach ($sections as $s) {
            if (!is_array($s)) {
                throw new \RuntimeException('Некорректная структура привязки к разделам.');
            }
            $id = isset($s['id']) ? (int)$s['id'] : 0;
            if ($id <= 0) {
                throw new \RuntimeException('Некорректный ID раздела.');
            }
            $selectedSections[$id] = array(
                'id' => $id,
                'children' => isset($s['children']) && $s['children'] === false ? 'N' : 'Y',
            );
        }
        if (count($selectedSections) > 0) {
            CatalogSections::assertValidIds(array_keys($selectedSections));
        }
        $mode = isset($input['MATCH_MODE']) ? (string)$input['MATCH_MODE'] : 'legacy';
        if (!in_array($mode, array('legacy','brand_article','brand','section','brand_section'), true)) {
            throw new \RuntimeException('Недопустимый режим привязки материала.');
        }
        // A new targeting mode intentionally drops fields not applicable to its scope.
        // Imported legacy rows keep their previous OR semantics until explicitly changed.
        if ($mode === 'brand') {
            $articles = array();
            $selectedSections = array();
        } elseif ($mode === 'section') {
            $brands = array();
            $articles = array();
        } elseif ($mode === 'brand_section') {
            $articles = array();
        } elseif ($mode === 'brand_article') {
            $selectedSections = array();
        }
        if ($mode === 'legacy') {
            if (!(count($brands) && count($articles)) && !count($selectedSections)) {
                throw new \RuntimeException('Для старого режима нужны разделы либо производители с артикулами.');
            }
            if ((!$brands && $articles) || ($brands && !$articles)) {
                throw new \RuntimeException('Для старой привязки по артикулу нужны производитель и артикулы.');
            }
        } elseif ($mode === 'brand_article' && (!$brands || !$articles)) {
            throw new \RuntimeException('Выберите производителя и укажите точные артикулы.');
        } elseif ($mode === 'brand' && !$brands) {
            throw new \RuntimeException('Выберите производителя для всех его товаров.');
        } elseif ($mode === 'section' && !$selectedSections) {
            throw new \RuntimeException('Выберите хотя бы один раздел.');
        } elseif ($mode === 'brand_section' && (!$brands || !$selectedSections)) {
            throw new \RuntimeException('Для пересечения условий выберите производителя и раздел.');
        }
        $sort = isset($input['SORT_ORDER']) ? (int)$input['SORT_ORDER'] : 500;
        return array(
            'TYPE' => $type, 'MATCH_MODE' => $mode, 'NAME' => $name, 'LINK_TEXT' => $text,
            'LINK_TITLE' => $title, 'LINK_ALT' => $alt,
            'TARGET_URL' => $url, 'IMAGE_URL' => $image,
            'ACTIVE' => isset($input['ACTIVE']) && $input['ACTIVE'] === 'N' ? 'N' : 'Y',
            'SORT_ORDER' => $sort,
            'BRANDS' => $brands, 'ARTICLES' => $articles,
            'SECTIONS' => array_values($selectedSections),
            'LEGACY_ID' => isset($input['LEGACY_ID']) && (int)$input['LEGACY_ID'] > 0
                ? (int)$input['LEGACY_ID'] : null,
        );
    }

    public static function save($input, $id)
    {
        $data = self::normalize($input);
        $id = (int)$id;
        $previous = $id ? self::get($id) : null;
        if ($id && !$previous) {
            throw new \RuntimeException('Материал не найден.');
        }
        $db = self::db();
        $db->startTransaction();
        try {
            $fields = array();
            foreach (array('TYPE','MATCH_MODE','NAME','LINK_TEXT','LINK_TITLE','LINK_ALT','TARGET_URL','IMAGE_URL','ACTIVE') as $key) {
                $fields[] = $key.'='.self::quote($data[$key]);
            }
            $fields[] = 'SORT_ORDER='.(int)$data['SORT_ORDER'];
            if (!$id) {
                $fields[] = 'LEGACY_ID='.($data['LEGACY_ID'] === null ? 'NULL' : (int)$data['LEGACY_ID']);
                $fields[] = 'CREATED_AT=NOW()';
                $fields[] = 'UPDATED_AT=NOW()';
                $db->queryExecute('INSERT INTO dt_doc_document SET '.implode(', ', $fields));
                $id = (int)$db->getInsertedId();
            } else {
                $fields[] = 'UPDATED_AT=NOW()';
                $db->queryExecute('UPDATE dt_doc_document SET '.implode(', ', $fields).' WHERE ID='.$id);
            }
            foreach (array('dt_doc_brand','dt_doc_article','dt_doc_section') as $table) {
                $db->queryExecute('DELETE FROM '.$table.' WHERE DOCUMENT_ID='.$id);
            }
            self::insertStrings('dt_doc_brand', 'BRAND', $id, $data['BRANDS']);
            self::insertStrings('dt_doc_article', 'ARTICLE', $id, $data['ARTICLES']);
            if ($data['SECTIONS']) {
                $rows = array();
                foreach ($data['SECTIONS'] as $s) {
                    $rows[] = '('.$id.','.(int)$s['id'].','.self::quote($s['children']).')';
                }
                $db->queryExecute('INSERT INTO dt_doc_section (DOCUMENT_ID, SECTION_ID, INCLUDE_SUBSECTIONS) VALUES '.implode(',', $rows));
            }
            $data['ID'] = $id;
            CacheQueue::enqueueChange($previous, $data);
            $db->commitTransaction();
            return $id;
        } catch (\Throwable $e) {
            $db->rollbackTransaction();
            throw $e;
        }
    }

    private static function insertStrings($table, $column, $documentId, $values)
    {
        foreach (array_chunk($values, 150) as $chunk) {
            $rows = array();
            foreach ($chunk as $value) {
                $rows[] = '('.(int)$documentId.','.self::quote($value).')';
            }
            self::db()->queryExecute(
                'INSERT INTO '.$table.' (DOCUMENT_ID, '.$column.') VALUES '.implode(',', $rows)
            );
        }
    }

    public static function get($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }
        $row = self::db()->query('SELECT * FROM dt_doc_document WHERE ID='.$id)->fetch();
        if (!$row) {
            return null;
        }
        $row['BRANDS'] = array();
        $row['ARTICLES'] = array();
        $row['SECTIONS'] = array();
        foreach (array('dt_doc_brand'=>'BRANDS', 'dt_doc_article'=>'ARTICLES') as $table => $key) {
            $field = $table === 'dt_doc_brand' ? 'BRAND' : 'ARTICLE';
            $rs = self::db()->query('SELECT '.$field.' FROM '.$table.' WHERE DOCUMENT_ID='.$id.' ORDER BY '.$field);
            while ($v = $rs->fetch()) {
                $row[$key][] = $v[$field];
            }
        }
        $rs = self::db()->query('SELECT SECTION_ID, INCLUDE_SUBSECTIONS FROM dt_doc_section WHERE DOCUMENT_ID='.$id.' ORDER BY SECTION_ID');
        while ($s = $rs->fetch()) {
            $row['SECTIONS'][] = array('id'=>(int)$s['SECTION_ID'], 'children'=>$s['INCLUDE_SUBSECTIONS']==='Y');
        }
        return $row;
    }

    public static function legacyExists($legacyId)
    {
        return (bool)self::db()->query('SELECT ID FROM dt_doc_document WHERE LEGACY_ID='.(int)$legacyId)->fetch();
    }

    public static function remove($id)
    {
        $id = (int)$id;
        $previous = $id > 0 ? self::get($id) : null;
        if (!$previous) {
            throw new \RuntimeException('Материал не найден.');
        }
        $db = self::db();
        $db->startTransaction();
        try {
            foreach (array('dt_doc_brand','dt_doc_article','dt_doc_section') as $table) {
                $db->queryExecute('DELETE FROM '.$table.' WHERE DOCUMENT_ID='.$id);
            }
            $db->queryExecute('DELETE FROM dt_doc_document WHERE ID='.$id);
            CacheQueue::enqueueChange($previous, null);
            $db->commitTransaction();
        } catch (\Throwable $e) {
            $db->rollbackTransaction();
            throw $e;
        }
    }

    public static function setActive($id, $active)
    {
        $id = (int)$id;
        $previous = $id > 0 ? self::get($id) : null;
        if (!$previous) {
            throw new \RuntimeException('Материал не найден.');
        }
        $newActive = $active ? 'Y' : 'N';
        if ($previous['ACTIVE'] === $newActive) return;
        $db = self::db();
        $db->startTransaction();
        try {
            $db->queryExecute('UPDATE dt_doc_document SET ACTIVE='.self::quote($newActive).
                ', UPDATED_AT=NOW() WHERE ID='.$id);
            $next = $previous;
            $next['ACTIVE'] = $newActive;
            CacheQueue::enqueueChange($previous, $next);
            $db->commitTransaction();
        } catch (\Throwable $e) {
            $db->rollbackTransaction();
            throw $e;
        }
    }

    public static function search($type, $query, $page, $pageSize)
    {
        $where = array('1=1');
        if (in_array($type, array('manual','document','article'), true)) {
            $where[] = 'TYPE='.self::quote($type);
        }
        if ($query !== '') {
            // The text field is an SQL value, never an SQL fragment.
            $where[] = 'NAME LIKE '.self::quote('%'.str_replace(array('%','_'), array('\\%','\\_'), $query).'%');
        }
        $clause = implode(' AND ', $where);
        $countRow = self::db()->query('SELECT COUNT(*) AS CNT FROM dt_doc_document WHERE '.$clause)->fetch();
        $total = (int)$countRow['CNT'];
        $offset = (max(1, (int)$page) - 1) * (int)$pageSize;
        $result = array();
        $rs = self::db()->query(
            'SELECT * FROM dt_doc_document WHERE '.$clause.
            ' ORDER BY FIELD(TYPE,\'manual\',\'document\',\'article\'), SORT_ORDER, ID '.
            'LIMIT '.(int)$pageSize.' OFFSET '.(int)$offset
        );
        while ($row = $rs->fetch()) {
            $result[] = $row;
        }
        return array('items'=>$result, 'total'=>$total);
    }
}
