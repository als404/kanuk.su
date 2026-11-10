<?php
namespace DT\Documents;

use Bitrix\Main\Config\Option;

/** Durable work-items: the rule snapshots are recorded in the same DB transaction as the edit. */
final class CacheQueue
{
    const TABLE = 'dt_doc_cache_task';

    public static function enqueueChange($old, $new)
    {
        // Until the PHP renderer is publicly enabled, changing imported metadata cannot
        // affect public pages. Avoid generating thousands of jobs during initial import.
        if (Option::get('dt.documents', 'public_enabled', 'N') !== 'Y') {
            return 0;
        }
        $scopes = array();
        foreach (array($old, $new) as $record) {
            if (!is_array($record) || !isset($record['ACTIVE']) || $record['ACTIVE'] !== 'Y') {
                continue;
            }
            $mode = isset($record['MATCH_MODE']) ? (string)$record['MATCH_MODE'] : 'legacy';
            $sections = isset($record['SECTIONS']) ? $record['SECTIONS'] : array();
            $brands = isset($record['BRANDS']) ? $record['BRANDS'] : array();
            $articles = isset($record['ARTICLES']) ? $record['ARTICLES'] : array();
            if ($mode === 'legacy' || $mode === 'section') {
                foreach ($sections as $s) {
                    $sid = isset($s['id']) ? (int)$s['id'] : 0;
                    if ($sid <= 0) continue;
                    $include = isset($s['children']) && ($s['children'] === false || $s['children'] === 'N') ? 'N' : 'Y';
                    $scopes['S:'.$sid.':'.$include] = array('kind'=>'S','section'=>$sid,'children'=>$include,'brand'=>'','articles'=>array());
                }
            }
            if ($mode === 'brand') {
                foreach ($brands as $brand) {
                    $key = 'B:'.hash('sha256', mbSafeLower($brand));
                    $scopes[$key] = array('kind'=>'B','section'=>0,'children'=>'N','brand'=>(string)$brand,'articles'=>array());
                }
            }
            if ($mode === 'brand_section') {
                foreach ($brands as $brand) {
                    foreach ($sections as $s) {
                        $sid = isset($s['id']) ? (int)$s['id'] : 0;
                        if ($sid <= 0) continue;
                        $include = isset($s['children']) && ($s['children'] === false || $s['children'] === 'N') ? 'N' : 'Y';
                        $key = 'X:'.hash('sha256', mbSafeLower($brand).'|'.$sid.'|'.$include);
                        $scopes[$key] = array('kind'=>'X','section'=>$sid,'children'=>$include,'brand'=>(string)$brand,'articles'=>array());
                    }
                }
            }
            if (($mode === 'legacy' || $mode === 'brand_article') && $brands && $articles) {
                $articles = array_values(array_unique(array_map('strval', $articles)));
                foreach ($brands as $brand) {
                    foreach (array_chunk($articles, 50) as $chunk) {
                        $key = 'A:'.hash('sha256', mbSafeLower($brand).'|'.implode("\n", $chunk));
                        $scopes[$key] = array('kind'=>'A','section'=>0,'children'=>'N','brand'=>(string)$brand,'articles'=>$chunk);
                    }
                }
            }
        }
        if (!$scopes) return 0;
        $db = Repository::db();
        if (!$db->isTableExists(self::TABLE)) {
            throw new \RuntimeException('Таблица очереди отсутствует. Сначала проверьте установку модуля.');
        }
        // A single tag invalidation precedes the per-product tasks.
        $documentId = is_array($new) && isset($new['ID']) ? (int)$new['ID'] : (is_array($old) && isset($old['ID']) ? (int)$old['ID'] : 0);
        self::insertTask($documentId, array('kind'=>'T','section'=>0,'children'=>'N','brand'=>'','articles'=>array()));
        foreach ($scopes as $scope) self::insertTask($documentId, $scope);
        return count($scopes) + 1;
    }

    private static function insertTask($documentId, $scope)
    {
        Repository::db()->queryExecute('INSERT INTO '.self::TABLE.' SET DOCUMENT_ID='.(int)$documentId.
            ', KIND='.Repository::quote($scope['kind']).
            ', SECTION_ID='.(int)$scope['section'].
            ', INCLUDE_SUBSECTIONS='.Repository::quote($scope['children']).
            ', BRAND='.Repository::quote($scope['brand']).
            ', ARTICLES_JSON='.Repository::quote(Encoding::json($scope['articles'])).
            ", STATUS='N', CREATED_AT=NOW(), UPDATED_AT=NOW()");
    }

    public static function stats()
    {
        $out = array('N'=>0,'D'=>0,'E'=>0,'processed'=>0);
        $rs = Repository::db()->query('SELECT STATUS, COUNT(*) AS CNT, COALESCE(SUM(PROCESSED),0) AS AMOUNT FROM '.self::TABLE.' GROUP BY STATUS');
        while ($r = $rs->fetch()) {
            $out[$r['STATUS']] = (int)$r['CNT'];
            $out['processed'] += (int)$r['AMOUNT'];
        }
        return $out;
    }

    public static function recent($limit)
    {
        $list = array();
        $rs = Repository::db()->query('SELECT ID, DOCUMENT_ID, KIND, SECTION_ID, BRAND, STATUS, LAST_ELEMENT_ID, PROCESSED, ERROR_TEXT, CREATED_AT, UPDATED_AT FROM '.self::TABLE.' ORDER BY ID DESC LIMIT '.max(1,min(100,(int)$limit)));
        while ($r = $rs->fetch()) $list[] = $r;
        return $list;
    }

    public static function retry($id)
    {
        $id = (int)$id;
        if ($id <= 0) throw new \RuntimeException('Некорректный ID задания.');
        Repository::db()->queryExecute("UPDATE ".self::TABLE." SET STATUS='N', ERROR_TEXT='', UPDATED_AT=NOW() WHERE ID=".$id." AND STATUS='E'");
    }
}

/** Does not assume mbstring is installed on PHP 7.4. */
function mbSafeLower($value)
{
    return function_exists('mb_strtolower') ? mb_strtolower((string)$value, Encoding::charset()) : strtolower((string)$value);
}
