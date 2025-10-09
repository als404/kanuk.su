<?php
$_SERVER["DOCUMENT_ROOT"] = "/home/bitrix/ext_www/public_html";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

$siteID = 's1';
//$siteID = '#SITE_ID#';  // your site ID - need for language ID

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS",true);
define("BX_CAT_CRON", true);
define('NO_AGENT_CHECK', true);
if (preg_match('/^[a-z0-9_]{2}$/i', $siteID) === 1)
{
	define('SITE_ID', $siteID);
}
else
{
	die('No defined site - $siteID');
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
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
