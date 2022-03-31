<?php
/* DATABASE CONFIGURATION */
class DB {
    // private $driver = 'mysql';
    private $host = '127.0.0.1';
    private $port = 3306;
    private $user = 'cv43140_main';
    private $pass = 'yfNBUiz6FTRCXn2gqK6';
    private $name = 'cv43140_main';
    private $charset = 'utf8';
    // private $collation = 'utf8_unicode_ci';
    // private $prefix = '';
    private $dbh = null;
    
    public function connect() { //Подключаемся...
        try {
            $dsn = "mysql:host=$this->host;dbname=$this->name;charset=$this->charset";
            $opt = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            );
            return $this->dbh = new PDO($dsn, $this->user, $this->pass, $opt);
        }
        catch(PDOException $e) {
            echo 'Произошла ошибка подключения к базе данных!<br>';
            echo $e->getMessage();
            die();
        }
    }
}