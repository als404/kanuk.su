<?php
    session_start();
    define('BASE', __DIR__);
    define('MODULES', $_SERVER['DOCUMENT_ROOT'] . '/local/modules');
    define('TMP', BASE.'/tmp');

    require_once(BASE.'/class/class_query.php');
    $query = new queryDB;