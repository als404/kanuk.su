<?php
// Local fixtures only: no real Bitrix/MySQL server or HTTP requests.
namespace Bitrix\Main\Config {
    class Option {
        public static $public = 'N';
        public static function get($module, $key, $default = '') {
            if ($key === 'auto_manual') return 'N';
            if ($key === 'public_enabled') return self::$public;
            return $default;
        }
    }
}
namespace Bitrix\Main {
    class Application {
        public static $conn;
        public static function getConnection() { return self::$conn; }
    }
}
namespace DT\Documents {
    class CatalogSections {
        const IBLOCK_ID = 16;
        public static function productSectionIds($id, $section) { return array(36); }
        public static function assertValidIds($ids) {
            foreach ($ids as $id) if ((int)$id !== 36) throw new \RuntimeException('Unexpected section');
        }
    }
}
namespace {
    $root = dirname(__DIR__).'/src/modules/dt.documents';
    require $root.'/lib/Encoding.php';
    require $root.'/lib/Repository.php';
    require $root.'/lib/Resolver.php';
    require $root.'/lib/CacheQueue.php';
    require $root.'/lib/ProductSearch.php';
    class FakeResult {
        private $rows;
        public function __construct($rows) { $this->rows=$rows; }
        public function fetch() { return array_shift($this->rows) ?: null; }
    }
    class FakeHelper {
        public function forSql($s) { return str_replace(array('\\', "'"), array('\\\\', "\\'"), $s); }
    }
    class FakeDB {
        public $queries = array();
        public $version = 2;
        public function getSqlHelper() { return new FakeHelper(); }
        public function query($q) {
            $this->queries[]=$q;
            if (strpos($q, 'SELECT VERSION FROM b_iblock') === 0) return new FakeResult(array(array('VERSION'=>$this->version)));
            if (strpos($q, 'FROM b_iblock_property') !== false) return new FakeResult(array(
                array('ID'=>10,'CODE'=>'ARTNUMBER','PROPERTY_TYPE'=>'S','MULTIPLE'=>'N','LINK_IBLOCK_ID'=>0),
                array('ID'=>11,'CODE'=>'MANUFACTURER','PROPERTY_TYPE'=>'E','MULTIPLE'=>'N','LINK_IBLOCK_ID'=>9)));
            if (strpos($q, 'SELECT COUNT(DISTINCT e.ID)') === 0) return new FakeResult(array(array('CNT'=>1)));
            if (strpos($q, 'SELECT DISTINCT e.ID') === 0) return new FakeResult(array(array(
                'ID'=>123,'NAME'=>'Муфта', 'ACTIVE'=>'Y','IBLOCK_SECTION_ID'=>36,'ARTICLE'=>'4000125','BRAND_ID'=>9,'BRAND'=>'BREXIT')));
            return new FakeResult(array());
        }
        public function queryExecute($sql) { $this->queries[] = $sql; }
        public function isTableExists($name) { return true; }
    }
    function check($value, $label) { if (!$value) { throw new \RuntimeException('FAIL: '.$label); } echo "OK: $label\n"; }
    $db = new FakeDB();
    \Bitrix\Main\Application::$conn = $db;
    $ref = new \ReflectionMethod('DT\\Documents\\Repository', 'normalize');
    $ref->setAccessible(true);
    $base = array('TYPE'=>'manual','NAME'=>'Муфты', 'TARGET_URL'=>'/upload/doc/manual.pdf','ACTIVE'=>'Y');
    $brand = $base + array('MATCH_MODE'=>'brand','BRANDS'=>array('BREXIT'));
    $r=$ref->invoke(null,$brand);
    check($r['MATCH_MODE']==='brand' && $r['ARTICLES']===array() && $r['SECTIONS']===array(), 'brand-only rule normalized');
    $bs = $base + array('MATCH_MODE'=>'brand_section','BRANDS'=>array('BREXIT'), 'SECTIONS'=>array(array('id'=>36, 'children'=>true)));
    $r=$ref->invoke(null,$bs);
    check(count($r['SECTIONS'])===1 && count($r['BRANDS'])===1, 'brand+section intersection normalized');
    $legacy = $base + array('BRANDS'=>array('BREXIT'),'ARTICLES'=>array('4000125'));
    $r=$ref->invoke(null,$legacy);
    check($r['MATCH_MODE']==='legacy', 'imported legacy default preserved');
    try {
        $ref->invoke(null,$base + array('MATCH_MODE'=>'brand_section','BRANDS'=>array('BREXIT')));
        throw new \RuntimeException('FAIL: missing section was accepted');
    } catch (\RuntimeException $e) {
        check(strpos($e->getMessage(), 'выберите производителя и раздел')!==false, 'brand+section requires both conditions');
    }
    check(function_exists('mb_strtolower') ? mb_strtolower('БРЕКЗИТ', 'UTF-8') === 'брекзит' : true,
        'UTF-8 Cyrillic brand lowercasing available');
    $product=array('ID'=>123,'IBLOCK_ID'=>16,'IBLOCK_SECTION_ID'=>36,'PROPERTIES'=>array(
        'ARTNUMBER'=>array('VALUE'=>'4000125'),'MANUFACTURER'=>array('NAME'=>'BREXIT')));
    \DT\Documents\Resolver::matched($product);
    $sql=end($db->queries);
    check(strpos($sql,"d.MATCH_MODE='brand_section'")!==false && strpos($sql,' AND ')!==false,
        'resolver emits brand AND section');
    check(strpos($sql,"d.MATCH_MODE='legacy'")!==false, 'resolver retains legacy behavior');
    check(strpos($sql,'LOWER(db.BRAND)')!==false && strpos($sql,"'brexit'")!==false,
        'resolver compares normalized lowercase brand even for uppercase source');
    $search=\DT\Documents\ProductSearch::search('4000_25',9,1,50);
    $sql=end($db->queries);
    check($search['total']===1 && strpos($db->queries[count($db->queries)-2],"LIKE '4000_25' ESCAPE '!'")!==false,
        'underscore is one-character SQL LIKE wildcard');
    check(strpos($sql,'CAST(props.PROPERTY_11 AS UNSIGNED)=9')!==false, 'manufacturer selection is a linked element id');
    $db->version=1;
    \DT\Documents\ProductSearch::search('4000125',0,1,50);
    $sql=end($db->queries);
    check(strpos($sql,'b_iblock_element_property art')!==false && strpos($sql,"art.VALUE='4000125'")!==false,
        'common property storage and exact article match');
    \Bitrix\Main\Config\Option::$public='Y';
    $db->queries=array();
    \DT\Documents\CacheQueue::enqueueChange(null, array('ID'=>8,'ACTIVE'=>'Y','MATCH_MODE'=>'brand_section',
        'BRANDS'=>array('BREXIT'),'ARTICLES'=>array(),'SECTIONS'=>array(array('id'=>36,'children'=>true))));
    $joined=implode("\n",$db->queries);
    check(strpos($joined,"KIND='X'")!==false, 'cache queue uses combined brand+section task');
    check(strpos($joined,"KIND='S'")===false && strpos($joined,"KIND='B'")===false, 'intersection does not fan out into broad scopes');
    echo "ALL FIXTURE CHECKS PASSED\n";
}
