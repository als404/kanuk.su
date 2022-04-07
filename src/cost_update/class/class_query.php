<?php
require_once(MODULES.'/lib/class_pdo.php');
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

    public function set($data) {
        $query = $this->_db->prepare($data['sql']['query']);
        $query->execute($data['sql']['params']);
    }
}