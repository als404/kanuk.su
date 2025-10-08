<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include.php');

    // Проверяем, что include.php подключён
    if (!defined('BX_ROOT')) {
        die("Файл include.php не подключён корректно.\n");
    }

    // Проверяем, что классы Bitrix24 доступны
    if (!class_exists('\Bitrix\Main\Application')) {
        die("Класс Bitrix\Main\Application не доступен.\n");
    }

    // Проверяем, загружен ли модуль main
    if (!CModule::IncludeModule('main')) {
        die("Модуль main не загружен.\n");
    }

    // Проверяем, загружен ли модуль iblock
    if (!CModule::IncludeModule('iblock')) {
        die("Модуль iblock не загружен.\n");
    }

    // Проверяем, что классы CIBlockElement и CIBlock доступны
    if (!class_exists('CIBlockElement')) {
        die("Класс CIBlockElement не доступен.\n");
    }
    if (!class_exists('CIBlock')) {
        die("Класс CIBlock не доступен.\n");
    }
