<?php
// Offline mock test: no Bitrix, DB connection, file deletion or server access.
final class FakeRows
{
    private $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function Fetch() { return array_shift($this->rows) ?: false; }
}
class CIBlockElement
{
    public static function GetByID($id)
    {
        return new FakeRows(array(array(
            'ID' => (int)$id, 'IBLOCK_ID' => 16, 'CODE' => 'test-product', 'IBLOCK_SECTION_ID' => 120
        )));
    }
    public static function GetElementGroups($id, $extra, $fields)
    {
        return new FakeRows(array(
            array('ID' => 120, 'IBLOCK_ID' => 16),
            array('ID' => 200, 'IBLOCK_ID' => 16)
        ));
    }
}
class CIBlockSection
{
    public static function GetList($order, $filter, $count, $fields)
    {
        $data = array(
            100 => array('ID'=>100,'CODE'=>'parent','IBLOCK_SECTION_ID'=>0),
            120 => array('ID'=>120,'CODE'=>'child','IBLOCK_SECTION_ID'=>100),
            200 => array('ID'=>200,'CODE'=>'other','IBLOCK_SECTION_ID'=>0),
        );
        return new FakeRows(isset($data[$filter['ID']]) ? array($data[$filter['ID']]) : array());
    }
}
require_once dirname(__DIR__) . '/lib/DtCatalogSortCache.php';
$method = new ReflectionMethod('DtCatalogSortCache', 'pathsForProduct');
$method->setAccessible(true);
$paths = $method->invoke(null, 5238);
$required = array('', 'parent', 'child', 'parent/child', 'parent/child/test-product',
    'child/test-product', 'other', 'other/test-product');
foreach ($required as $value) {
    if (!in_array($value, $paths, true)) {
        throw new Exception('Missing cache path: ' . $value);
    }
}
if (count($paths) !== count(array_unique($paths))) {
    throw new Exception('Duplicate target paths');
}
if (in_array('other/child', $paths, true)) {
    throw new Exception('Unrelated section was targeted');
}
echo "PASS: flat/nested/linked sections, parent paths, product pages, deduplication\n";
