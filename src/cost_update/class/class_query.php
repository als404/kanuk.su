<?php
require_once(BASE.'/local/modules/connect_db/class_pdo.php');
class queryDB extends DB {
	private $_db = null;

	public function __construct() {
        $this->_db = $this->connect();
    }

    public function get($data) {
        $query = $this->_db->prepare($data['sql']['query']);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }
}