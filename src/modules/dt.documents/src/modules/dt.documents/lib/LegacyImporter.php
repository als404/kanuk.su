<?php
namespace DT\Documents;

/** Strict snapshot import. No eval(), no JS execution, no filesystem deletions. */
final class LegacyImporter
{
    const EXPECTED_SHA256 = '50d7be19297584447fc79a736d463adab72d7c960dacbc072047eeea796353cf';
    const SNAPSHOT_SHA256 = '732e4a28e007d739c58bfa9725c2b9e2596e613527bcb5886643bee3d298d393';

    public static function readSnapshot()
    {
        $path = dirname(__DIR__).'/tools/legacy-data.json';
        if (!is_file($path) || hash_file('sha256', $path) !== self::SNAPSHOT_SHA256) {
            throw new \RuntimeException('Контрольная сумма JSON-снимка не совпадает с ожидаемой.');
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException('Отсутствует файл snapshot legacy-data.json.');
        }
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items']) ||
            !isset($data['sha256']) || $data['sha256'] !== self::EXPECTED_SHA256 ||
            count($data['items']) !== 49 || $data['items'][0]['template'] !== 'manual') {
            throw new \RuntimeException('Неподдерживаемый формат исходного снимка.');
        }
        return $data;
    }

    public static function verifyProductionSource()
    {
        $path = $_SERVER['DOCUMENT_ROOT'].'/upload/doc/db.js';
        if (!is_file($path) || hash_file('sha256', $path) !== self::EXPECTED_SHA256) {
            throw new \RuntimeException('Хэш действующего db.js не совпадает со снимком. Обновите и проверьте импорт до продолжения.');
        }
    }

    public static function convert($row, $legacyId)
    {
        $type = isset($row['template']) ? $row['template'] : '';
        if (!in_array($type, array('document','article'), true)) {
            throw new \RuntimeException('Неизвестный тип в исходной записи #'.$legacyId);
        }
        $name = Encoding::fromUtf8(isset($row['liknText']) ? $row['liknText'] : $row['linkTitle']);
        $linkName = isset($row['linkName']) ? $row['linkName'] : '';
        $linkSrc = isset($row['linkSRC']) ? $row['linkSRC'] : '';
        $linkImg = isset($row['linkIMG']) ? $row['linkIMG'] : '';
        // Preserve original JS URL precedence: linkName > linkSRC > manual-by-brand.
        if ($linkName !== '') {
            $url = '/upload/doc/other/'.$linkName.'.pdf';
            $image = '/upload/doc/other/pic/'.$linkName.'.jpg';
        } elseif ($linkSrc !== '') {
            $url = $linkSrc;
            $image = '/upload/doc/other/pic/'.$linkImg.'.jpg';
        } else {
            throw new \RuntimeException('Запись #'.$legacyId.' без linkName/linkSRC.');
        }
        return array(
            'TYPE' => $type,
            'NAME' => $name,
            'LINK_TEXT' => $name,
            'LINK_TITLE' => Encoding::fromUtf8(isset($row['linkTitle']) ? $row['linkTitle'] : ''),
            'LINK_ALT' => Encoding::fromUtf8(isset($row['linkAlt']) ? $row['linkAlt'] : ''),
            'TARGET_URL' => Encoding::fromUtf8($url),
            'IMAGE_URL' => Encoding::fromUtf8($image),
            'ACTIVE' => 'Y',
            'SORT_ORDER' => $legacyId * 100,
            'BRANDS' => Encoding::fromUtf8(isset($row['brands']) ? $row['brands'] : array()),
            'ARTICLES' => Encoding::fromUtf8(isset($row['arts']) ? $row['arts'] : array()),
            'SECTIONS' => array(),
            'LEGACY_ID' => $legacyId,
        );
    }

    public static function preview()
    {
        self::verifyProductionSource();
        $snapshot = self::readSnapshot();
        $info = array('total'=>0, 'ready'=>0, 'already'=>0, 'missing_pdf'=>array(), 'missing_image'=>array(), 'external_or_pages'=>0);
        foreach ($snapshot['items'] as $i => $row) {
            if ($i === 0) {
                continue; // Synthetic manual is not a document record.
            }
            $record = self::convert($row, $i);
            $info['total']++;
            if (Repository::legacyExists($i)) {
                $info['already']++;
            } else {
                $info['ready']++;
            }
            if (strpos($record['TARGET_URL'], '/upload/doc/') === 0) {
                if (!is_file($_SERVER['DOCUMENT_ROOT'].Encoding::toUtf8($record['TARGET_URL']))) {
                    $info['missing_pdf'][] = array('id'=>$i,'path'=>$record['TARGET_URL']);
                }
            } else {
                $info['external_or_pages']++;
            }
            if ($record['IMAGE_URL'] !== '' && !is_file($_SERVER['DOCUMENT_ROOT'].Encoding::toUtf8($record['IMAGE_URL']))) {
                $info['missing_image'][] = array('id'=>$i,'path'=>$record['IMAGE_URL']);
            }
        }
        return $info;
    }

    public static function import()
    {
        // No import without an explicit successful preflight against production.
        $info = self::preview();
        $snapshot = self::readSnapshot();
        $count = 0;
        foreach ($snapshot['items'] as $i => $row) {
            if ($i === 0 || Repository::legacyExists($i)) {
                continue;
            }
            Repository::save(self::convert($row, $i), 0);
            $count++;
        }
        return array('inserted'=>$count, 'total'=>$info['total']);
    }
}
