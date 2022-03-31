<?php
require(BASE.'/connect_db/class_pdo.php');

class queryDB extends DB {
	private $_db = null;

	public function __construct() {
        $this->_db = $this->connect();
    }

    public function getCurrency($data) {
        $query = $this->_db->prepare($data['sql']['query']);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    public function setAmount($data) {
        $query = $this->_db->prepare($data['sql']['query']);
        $query->execute($data['sql']['params']);
    }

    static function getAmount($curr, $xml) {
        foreach ($xml->Valute as $valute){ 
            if($valute->CharCode == $curr){
                return str_replace(',', '.', $valute->Value);
            }
        }
    }
}