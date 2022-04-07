<?php
require_once('/home/bitrix/ext_www/public_html/local/modules/cost_update/core.php');
require_once(MODULES.'/lib/class_csv.php');

if($_FILES){
    if($_FILES["userfile"]["size"] > 64*1024) { // ��������� ������ �����, ���� ���� ������ 64 Kb ������������� ������
		echo ("File size exceeds 64 Kb");
		exit;
	}

    $uploadFile = TMP .'/'. basename($_FILES['file']['name']); // ������� ���� ��� �������� � ��� �����

    if (is_uploaded_file($_FILES["file"]["tmp_name"])) { // ��������� �������� �����
		move_uploaded_file($_FILES["file"]["tmp_name"], $uploadFile); // ���������� ���� �� ��������� ����� � ��������

		try {
			$csv = new CSV($uploadFile);
			$get_csv = $csv->getCSV();
			$count = count($get_csv);
            
            if($_POST['checkbox'] == 'true') { // ���� ������� checkbox ��������� ���������� ������� �� ������
                $data['sql']['query'] = 'UPDATE b_catalog_price AS price, b_catalog_product AS product, b_iblock_element AS element SET price.PRICE = :ammount, price.PRICE_SCALE = :ammount, product.QUANTITY = :qty, product.AVAILABLE = :available, element.MODIFIED_BY = :modyfied_by, element.TIMESTAMP_X = :timestamp_x WHERE product.ID = (SELECT link.ELEMENT_ID FROM b_iblock_element_property AS prop JOIN b_iblock_16_index AS link ON link.ELEMENT_ID = prop.IBLOCK_ELEMENT_ID WHERE prop.VALUE = :art AND prop.IBLOCK_PROPERTY_ID = :iblock_id AND link.FACET_ID = :facet_id AND link.VALUE = :brand_id GROUP BY prop.ID) AND price.PRODUCT_ID = product.ID AND element.ID = product.ID';
            } else { // ���� �� ������� checkbox ����� �� �������
                $data['sql']['query'] = 'UPDATE b_catalog_price AS price, b_catalog_product AS product, b_iblock_element AS element SET price.PRICE = :ammount, price.PRICE_SCALE = :ammount, element.MODIFIED_BY = :modyfied_by, element.TIMESTAMP_X = :timestamp_x WHERE product.ID = (SELECT link.ELEMENT_ID FROM b_iblock_element_property AS prop JOIN b_iblock_16_index AS link ON link.ELEMENT_ID = prop.IBLOCK_ELEMENT_ID WHERE prop.VALUE = :art AND prop.IBLOCK_PROPERTY_ID = :iblock_id AND link.FACET_ID = :facet_id AND link.VALUE = :brand_id GROUP BY prop.ID) AND price.PRODUCT_ID = product.ID AND element.ID = product.ID';
            }

            foreach($get_csv as $v) {
                if(!empty(trim($v[0]))) {
                    if($_POST['checkbox'] == 'true') { // ���� ������� checkbox ��������� ���������� ������� �� ������
                        $data['sql']['params'] = [
                            'art' => trim($v[0]),
                            'brand_id' => $_POST['brandId'],
                            'ammount' => (int) trim($v[1]),
                            'qty' => (int) trim($v[2]),
                            'available' => (int) trim($v[2]) != 0 ? 'Y' : 'N',
                            'modyfied_by' => 1,
                            'timestamp_x' => date("Y-m-d H:i:s"),
                            'iblock_id' => 51,
                            'facet_id' => 104
                        ];
                    } else { // ���� �� ������� checkbox ����� �� �������
                        $data['sql']['params'] = [
                            'art' => trim($v[0]),
                            'brand_id' => $_POST['brandId'],
                            'ammount' => (int) trim($v[1]),
                            'modyfied_by' => 1,
                            'timestamp_x' => date("Y-m-d H:i:s"),
                            'iblock_id' => 51,
                            'facet_id' => 104
                        ];
                    }
                    $query->set($data);
                }
            }
			$msg['message'] = 'The data has been updated!';
            $msg['event'] = 'success';
            unlink($uploadFile);
		}
		catch (Exception $e) {
			$msg['message'] = "Error: " . $e->getMessage();
            $msg['event'] = 'error';
		}

	} else {
		$msg['message'] = "File upload error";
        $msg['event'] = 'error';
	}
    echo json_encode($msg, JSON_UNESCAPED_UNICODE);
}
