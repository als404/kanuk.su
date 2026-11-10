<?php
namespace DT\Documents;

use Bitrix\Main\Application;

/** One bounded queue operation. Intended for administrator or an explicitly enabled agent. */
final class CacheWorker
{
    const MAX_ITEMS = 25;

    public static function runOnce($limit = self::MAX_ITEMS)
    {
        $limit = max(1, min(self::MAX_ITEMS, (int)$limit));
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            throw new \RuntimeException('Не загружен модуль iblock.');
        }
        $db = Application::getConnection();
        $lock = $db->query("SELECT GET_LOCK('dt_documents_cache_worker', 0) AS LOCKED")->fetch();
        if (!$lock || (int)$lock['LOCKED'] !== 1) {
            return array('status'=>'busy','processed'=>0,'task'=>0,'deleted_files'=>0);
        }
        try {
            $task = $db->query("SELECT * FROM dt_doc_cache_task WHERE STATUS='N' ORDER BY ID ASC LIMIT 1")->fetch();
            if (!$task) return array('status'=>'empty','processed'=>0,'task'=>0,'deleted_files'=>0);
            $id = (int)$task['ID'];
            try {
                if ($task['KIND'] === 'T') {
                    CachePurger::clearCatalogTag();
                    self::finish($id, 0, true, 0);
                    return array('status'=>'done','processed'=>0,'task'=>$id,'deleted_files'=>0);
                }
                if (!in_array($task['KIND'], array('S','A','B','X'), true)) throw new \RuntimeException('Неизвестный тип задания.');
                // Validate the host *before* scanning any products or advancing the queue.
                $base = CachePurger::htmlCacheRoot();
                $filter = array('IBLOCK_ID'=>CatalogSections::IBLOCK_ID, '>ID'=>(int)$task['LAST_ELEMENT_ID']);
                if ($task['KIND'] === 'S' || $task['KIND'] === 'X') {
                    $filter['SECTION_ID'] = (int)$task['SECTION_ID'];
                    $filter['INCLUDE_SUBSECTIONS'] = $task['INCLUDE_SUBSECTIONS'] === 'Y' ? 'Y' : 'N';
                }
                if ($task['KIND'] === 'A' || $task['KIND'] === 'B' || $task['KIND'] === 'X') {
                    $manufacturerIds = self::manufacturerIds($task['BRAND']);
                    if (!$manufacturerIds) {
                        self::finish($id, 0, true, 0);
                        return array('status'=>'done','processed'=>0,'task'=>$id,'deleted_files'=>0);
                    }
                    $filter['PROPERTY_MANUFACTURER'] = $manufacturerIds;
                    if ($task['KIND'] === 'A') {
                        $articles = json_decode((string)$task['ARTICLES_JSON'], true);
                        if (!is_array($articles) || !$articles) throw new \RuntimeException('Отсутствуют артикулы задания.');
                        $filter['=PROPERTY_ARTNUMBER'] = Encoding::fromUtf8($articles);
                    }
                }
                $rs = \CIBlockElement::GetList(array('ID'=>'ASC'), $filter, false, array('nTopCount'=>$limit), array('ID'));
                $ids = array();
                while ($p = $rs->Fetch()) {
                    $productId = (int)$p['ID'];
                    if ($productId > (int)$task['LAST_ELEMENT_ID']) $ids[$productId] = $productId;
                }
                $lastId = $ids ? max($ids) : (int)$task['LAST_ELEMENT_ID'];
                $deleted = 0;
                foreach ($ids as $productId) $deleted += CachePurger::invalidateProduct($productId, $base);
                // If the chunk is shorter than the requested size there can be no more IDs.
                $done = count($ids) < $limit;
                self::finish($id, $lastId, $done, count($ids));
                return array('status'=>$done ? 'done' : 'pending', 'processed'=>count($ids), 'task'=>$id,'deleted_files'=>$deleted);
            } catch (\Throwable $e) {
                $db->queryExecute("UPDATE dt_doc_cache_task SET STATUS='E', ERROR_TEXT=".Repository::quote(substr($e->getMessage(), 0, 1000)).", UPDATED_AT=NOW() WHERE ID=".$id);
                throw $e;
            }
        } finally {
            $db->query("SELECT RELEASE_LOCK('dt_documents_cache_worker') AS RELEASED")->fetch();
        }
    }

    private static function finish($id, $lastId, $done, $count)
    {
        $fields = "STATUS='".($done ? 'D' : 'N')."', PROCESSED=PROCESSED+".(int)$count.
            ', UPDATED_AT=NOW(), ERROR_TEXT=\'\'';
        if ($lastId > 0) $fields .= ', LAST_ELEMENT_ID='.(int)$lastId;
        Repository::db()->queryExecute('UPDATE dt_doc_cache_task SET '.$fields.' WHERE ID='.(int)$id);
    }

    private static function manufacturerIds($brand)
    {
        $p = \CIBlockProperty::GetList(array(), array('IBLOCK_ID'=>CatalogSections::IBLOCK_ID, 'CODE'=>'MANUFACTURER'))->Fetch();
        $linkedIblock = isset($p['LINK_IBLOCK_ID']) ? (int)$p['LINK_IBLOCK_ID'] : 0;
        if ($linkedIblock <= 0) throw new \RuntimeException('Не найдена привязка свойства MANUFACTURER к инфоблоку производителей.');
        $ids = array();
        $rs = \CIBlockElement::GetList(array(), array('IBLOCK_ID'=>$linkedIblock,'=NAME'=>(string)$brand), false, false, array('ID'));
        while ($r = $rs->Fetch()) {
            $ids[(int)$r['ID']] = (int)$r['ID'];
            if (count($ids) > 100) throw new \RuntimeException('Слишком много производителей с одинаковым названием.');
        }
        return array_values($ids);
    }
}
