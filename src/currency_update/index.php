<?php
define('BASE', '/home/bitrix/ext_www/public_html/local/modules');
require(BASE.'/currency_update/class_currency_update.php');

$data['xml'] = simpleXML_load_file('https://cbr.ru/scripts/XML_daily.asp', 'SimpleXMLElement',LIBXML_NOCDATA);
$data['sql']['query'] = 'SELECT * FROM b_catalog_currency';

if($data['xml'] === false){
    $data['msg'] = 'Произошла ошибка, данные не обновились!';
} else { 
    $query = new currencyUpdate;
    foreach($query->getCurrency($data) as $q) {
        if ($q->CURRENCY == 'RUB') {
            continue;
        } else {
            $amount = currencyUpdate::getAmount($q->CURRENCY, $data['xml']);
            $data['sql']['query'] = 'UPDATE b_catalog_currency SET AMOUNT = :amount, DATE_UPDATE = NOW(), CURRENT_BASE_RATE = :amount WHERE CURRENCY = :currency';
            $data['sql']['params'] = [
                ':amount' => $amount,
                ':currency' => $q->CURRENCY
            ];
            $query->setAmount($data);
        }
    }
    $data['msg'] = 'Данные валюты обновлены!';
}
//  echo '<p>'.$data['msg'].'</p>';