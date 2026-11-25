<?php
//-- Добавление обработчика события

AddEventHandler("sale", "OnOrderNewSendEmail", "bxModifySaleMails");

//-- Собственно обработчик события

function bxModifySaleMails($orderID, &$eventName, &$arFields)
{
  $arOrder = CSaleOrder::GetByID($orderID);
  
  //-- получаем телефоны и адрес
  $order_props = CSaleOrderPropsValue::GetOrderProps($orderID);
  $phone="";
  $index = ""; 
  $country_name = "";
  $city_name = "";  
  $address = "";
  while ($arProps = $order_props->Fetch())
  {
    if ($arProps["CODE"] == "PHONE")
    {
       $phone = htmlspecialchars($arProps["VALUE"]);
    }
    if ($arProps["CODE"] == "LOCATION")
    {
        $arLocs = CSaleLocation::GetByID($arProps["VALUE"]);
        $country_name =  $arLocs["COUNTRY_NAME_ORIG"];
        $city_name = $arLocs["CITY_NAME_ORIG"];
    }

    if ($arProps["CODE"] == "INDEX")
    {
      $index = $arProps["VALUE"];   
    }

    if ($arProps["CODE"] == "ADDRESS")
    {
      $address = $arProps["VALUE"];
    }
  }

  $full_address = $index.", ".$country_name."-".$city_name.", ".$address;

  //-- получаем название службы доставки
  $arDeliv = CSaleDelivery::GetByID($arOrder["DELIVERY_ID"]);
  $delivery_name = "";
  if ($arDeliv)
  {
    $delivery_name = $arDeliv["NAME"];
  }

  //-- получаем название платежной системы   
  $arPaySystem = CSalePaySystem::GetByID($arOrder["PAY_SYSTEM_ID"]);
  $pay_system_name = "";
  if ($arPaySystem)
  {
    $pay_system_name = $arPaySystem["NAME"];
  }

  //-- добавляем новые поля в массив результатов
  $arFields["ORDER_DESCRIPTION"] = $arOrder["USER_DESCRIPTION"]; 
  $arFields["PHONE"] =  $phone;
  $arFields["DELIVERY_NAME"] =  $delivery_name;
  $arFields["PAY_SYSTEM_NAME"] =  $pay_system_name;
  $arFields["FULL_ADDRESS"] = $full_address;   
}

AddEventHandler("main", "OnEpilog", "SetSeoData");
//AddEventHandler("main", "OnEndBufferContent", "SEO_modifier");

function SetSeoData() {
  file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/_log.txt', __FUNCTION__.':'.time());
    $aSEOData['title'] = '';
    $aSEOData['descr'] = '';
    $aSEOData['keywr'] = '';
    $aSEOData['h1'] = '';

    global $APPLICATION;
    $dir = $APPLICATION->GetCurDir();
    $uri = $APPLICATION->GetCurUri();

    $m_title = $APPLICATION->GetTitle();
    $seo_is_product = $APPLICATION->GetPageProperty("seo_is_product");

  $breads =$APPLICATION->arAdditionalChain;
  $breads = end($breads);
    $sSEOUrl = $_SERVER['REQUEST_URI'];

    if(isset($_GET['PAGEN_1'])) {
        if(!empty($aSEOData['descr'])) {
          $aSEOData['descr'] .= ' - Страница '.$_GET['PAGEN_1'].'. Наш телефон в Москве: 8 (800) 555-89-34';
        } else { 
          $aSEOData['descr'] = 'Покупайте профессиональный инструмент и строительное оборудование по выгодной цене в интернет-магазине «Канюк». '.$m_title.' | Страница '.$_GET['PAGEN_1'].'. Наш телефон в Москве: 8 (800) 555-89-34.';
        }
    }

    // Для карточек
    // if (isset($seo_is_product) && isset($seo_is_product) == 'Y') {
    //     $aSEOData['title'] = $m_title . ' купить с доставкой в Москве';        
    // }


  switch ($sSEOUrl) {
 
    case '/catalog/newproduct/': // это ЧПУ
        $aSEOData['title'] = 'Новинки | Интернет-магазин "Канюк"';
        $aSEOData['descr'] = 'Новинки. "Канюк" - компания профессионального строительного инструмента и оборудования. Наш телефон в Москве: 8 (800) 555-89-34';
      break;

      case '/catalog/saleleader/': // это ЧПУ
        $aSEOData['title'] = 'Хиты продаж | Интернет-магазин "Канюк"';
        $aSEOData['descr'] = 'Хиты продаж. "Канюк" - компания профессионального строительного инструмента и оборудования. Наш телефон в Москве: 8 (800) 555-89-34';
        break;

       case '/catalog/discount/': // это ЧПУ
        $aSEOData['title'] = 'Скидки | Интернет-магазин "Канюк"';
        $aSEOData['descr'] = 'Скидки. Компания «Канюк» предлагает широкий ассортимент профессионального инструмента и качественного оборудования для строительных, монтажных и ремонтных организаций от ведущих мировых производителей. Наш телефон в Москве: 8 (800) 555-89-34';
      break;
  }




// $generationTitle = Array();

// $artikul = $APPLICATION->GetPageProperty('artikul');
// if(!empty($artikul)){
//   if(in_array($APPLICATION->GetCurPage(), $generationTitle)){
//     $title = $APPLICATION->GetPageProperty('title');
//     $aSEOData['title']= $title/*.' | ??????? '.$artikul*/;
//   }
// }

  // $generationdesc = Array();

  // if(in_array($APPLICATION->GetCurPage(), $generationdesc)){
  //  // $description = $APPLICATION->GetPageProperty('description');
  //  // $title = $APPLICATION->GetPageProperty('title');
  //   $aSEOData['descr']= $m_title . ' - купить с доставкой в Москве. Компания «Канюк» предлагает широкий ассортимент профессионального инструмента и качественного оборудования для строительных, монтажных и ремонтных организаций от ведущих мировых производителей.';
  // }
    // ????????? ????? ????????
    if(!empty($aSEOData['title'])) $APPLICATION->SetPageProperty('title', $aSEOData['title']);
    if(!empty($aSEOData['descr'])) $APPLICATION->SetPageProperty('description', $aSEOData['descr']);
    if(!empty($aSEOData['h1'])) $APPLICATION->SetTitle($aSEOData['h1']);
}

/////////////////////////////////

$redirect = array(
  '/catalog/almaznye-kolcevye-sverla/' => '/catalog/almaznye-koronki-po-betonu/',

);

if (isset($redirect[$_SERVER['REQUEST_URI']])) {
  header("HTTP/1.1 301 Moved Permanently");
  header("Location: ".$redirect[$_SERVER['REQUEST_URI']]);
    exit;
}

// DT Catalog Properties — административная карточка товара.
if (
    isset($_SERVER['SCRIPT_NAME'])
    && in_array(
        basename($_SERVER['SCRIPT_NAME']),
        array(
            'iblock_element_edit.php',
            'cat_product_edit.php'
        ),
        true
    )
) {
    $dtAdminPropertiesFile =
        $_SERVER['DOCUMENT_ROOT'] .
        '/local/modules/dt.catalogproperties/lib/AdminElementProperties.php';

    if (is_file($dtAdminPropertiesFile)) {
        require_once $dtAdminPropertiesFile;

        AddEventHandler(
            'main',
            'OnAdminTabControlBegin',
            array(
                '\DT\CatalogProperties\AdminElementProperties',
                'onAdminTabControlBegin'
            )
        );
    }
}

// DT Catalog Properties — пункт административного меню.
if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
    $dtAdminMenuFile =
        $_SERVER['DOCUMENT_ROOT'] .
        '/local/modules/dt.catalogproperties/lib/AdminMenu.php';

    if (is_file($dtAdminMenuFile)) {
        require_once $dtAdminMenuFile;

        AddEventHandler(
            'main',
            'OnBuildGlobalMenu',
            array(
                '\DT\CatalogProperties\AdminMenu',
                'onBuildGlobalMenu'
            ),
            10000
        );

    }
}

// DT Discontinued Products — серверная блокировка покупки.
$dtDiscontinuedFile =
    $_SERVER["DOCUMENT_ROOT"] .
    "/local/php_interface/dt_discontinued.php";

if (is_file($dtDiscontinuedFile)) {

    require_once $dtDiscontinuedFile;

    \Bitrix\Main\EventManager::getInstance()->addEventHandler(
        "sale",
        "OnSaleBasketBeforeSaved",
        array(
            "DTDiscontinuedProduct",
            "onBasketBeforeSaved"
        )
    );

    \Bitrix\Main\EventManager::getInstance()->addEventHandler(
        "sale",
        "OnSaleOrderBeforeSaved",
        array(
            "DTDiscontinuedProduct",
            "onOrderBeforeSaved"
        )
    );
}

unset($dtDiscontinuedFile);
// END DT Discontinued Products

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/dealer_xml_admin_menu.php';

?>
