<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Iblock,
	Bitrix\Main\Type\Collection;

global $arSetting;

$arResult["SEARCH"] = array();

$arSections = array();
$arItems = array();
foreach($arResult["CATEGORIES"] as $category_id => $arCategory) {
	foreach($arCategory["ITEMS"] as $i => $arItem) {
		if(isset($arItem["ITEM_ID"]) && $arItem["MODULE_ID"] == "iblock") {
			if(substr($arItem["ITEM_ID"], 0, 1) === "S") {
				$arSections[] = substr($arItem["ITEM_ID"], 1);				
			}			
			if(substr($arItem["ITEM_ID"], 0, 1) !== "S") {
				$arItems[] = $arItem["ITEM_ID"];
			}			
		}
	}
}

//ACTIVE_SECTIONS//
if(!empty($arSections) && CModule::IncludeModule("iblock")) {	
	$arActiveSections = array();
	
	$rsSection = CIBlockSection::GetList(
		array(), 
		array(
			"GLOBAL_ACTIVE" => "Y",
			"ID" => $arSections,
			"IBLOCK_ID" => $arParams["IBLOCK_ID"]
		),
		false,
		array("ID", "IBLOCK_ID", "PICTURE")
	);
	while($arSection = $rsSection->GetNext()) {
		//PICTURE//
		if($arSection["PICTURE"] > 0) {
			$arFile = CFile::GetFileArray($arSection["PICTURE"]);
			if($arFile["WIDTH"] > 62 || $arFile["HEIGHT"] > 62) {
				$arFileTmp = CFile::ResizeImageGet(
					$arFile,
					array("width" => 62, "height" => 62),
					BX_RESIZE_IMAGE_PROPORTIONAL,
					true
				);
				$arSection["PICTURE"] = array(
					"SRC" => $arFileTmp["src"],
					"WIDTH" => $arFileTmp["width"],
					"HEIGHT" => $arFileTmp["height"],
				);
			} else {
				$arSection["PICTURE"] = $arFile;
			}
		}
		$arActiveSections[$arSection["ID"]] = $arSection;
	}
	
	if(!empty($arActiveSections)) {
		foreach($arResult["CATEGORIES"] as $category_id => $arCategory) {
			foreach($arCategory["ITEMS"] as $i => $arItem) {
				if(isset($arItem["ITEM_ID"]) && $arItem["MODULE_ID"] == "iblock") {
					if(substr($arItem["ITEM_ID"], 0, 1) === "S") {
						$arItem["ITEM_ID"] = substr($arItem["ITEM_ID"], 1);
						if($arActiveSections[$arItem["ITEM_ID"]]) {
							if($arActiveSections[$arItem["ITEM_ID"]]["PICTURE"])
								$arResult["CATEGORIES"][$category_id]["ITEMS"][$i]["PREVIEW_PICTURE"] = $arActiveSections[$arItem["ITEM_ID"]]["PICTURE"];
							$arResult["CATEGORIES"][$category_id]["ITEMS"][$i]["ICON"] = true;
						} else {
							unset($arResult["CATEGORIES"][$category_id]["ITEMS"][$i]);
						}
					}
				}
			}
		}
	}
}

//ACTIVE_ELEMENTS//
if(!empty($arItems) && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")) {	
	$ids = array();
	$arNewItems = array();
	$arActiveItems = array();
	$arNewActiveItems = array();
	
	foreach($arItems as $i => $arItemId) {
		$mxResult = CCatalogSku::GetProductInfo($arItemId);			
		$ids[] = is_array($mxResult) ? $mxResult["ID"] : $arItemId;
		$arNewItems[] = array(
			"ID" => $arItemId,
			"PRODUCT_ID" => is_array($mxResult) ? $mxResult["ID"] : $arItemId
		);
	}
	
	$rsElement = CIBlockElement::GetList(
		array(),
		array(
			"ID" => array_unique($ids),
			"ACTIVE" => "Y",
			"IBLOCK_ID" => $arParams["IBLOCK_ID"],
			"SECTION_GLOBAL_ACTIVE" => "Y"
		),
		false, 
		false, 
		array("ID", "IBLOCK_ID")
	);
	while($arElement = $rsElement->GetNext()) {		
		$arActiveItems[$arElement["ID"]] = $arElement;
	}	

	foreach($arNewItems as $i => $arNewItem) {
		if($arActiveItems[$arNewItem["PRODUCT_ID"]])
			$arNewActiveItems[$arNewItem["ID"]] = $arNewItem["ID"];
	}
	if(!empty($arNewActiveItems)) {
		foreach($arResult["CATEGORIES"] as $category_id => $arCategory) {
			foreach($arCategory["ITEMS"] as $i => $arItem) {
				if(isset($arItem["ITEM_ID"]) && $arItem["MODULE_ID"] == "iblock") {
					if(substr($arItem["ITEM_ID"], 0, 1) !== "S") {						
						if($arNewActiveItems[$arItem["ITEM_ID"]]) {							
							$arResult["CATEGORIES"][$category_id]["ITEMS"][$i]["ICON"] = true;
							$arResult["SEARCH"][$arItem["ITEM_ID"]] = &$arResult["CATEGORIES"][$category_id]["ITEMS"][$i];
						} else {
							unset($arResult["CATEGORIES"][$category_id]["ITEMS"][$i]);
						}
					}
				}
			}
		}
	}
}

if(!empty($arNewActiveItems) && CModule::IncludeModule("iblock")) {	
	$arConvertParams = array();
	if("Y" == $arParams["CONVERT_CURRENCY"]) {
		if(!CModule::IncludeModule("currency")) {
			$arParams["CONVERT_CURRENCY"] = "N";
			$arParams["CURRENCY_ID"] = "";
		} else {
			$arResultModules["currency"] = true;
			$arCurrencyInfo = CCurrency::GetByID($arParams["CURRENCY_ID"]);
			if(!(is_array($arCurrencyInfo) && !empty($arCurrencyInfo))) {
				$arParams["CONVERT_CURRENCY"] = "N";
				$arParams["CURRENCY_ID"] = "";
			} else {
				$arParams["CURRENCY_ID"] = $arCurrencyInfo["CURRENCY"];
				$arConvertParams["CURRENCY_ID"] = $arCurrencyInfo["CURRENCY"];
			}
		}
	}

	if(is_array($arParams["PRICE_CODE"]))
		$arr["PRICES"] = CIBlockPriceTools::GetCatalogPrices(0, $arParams["PRICE_CODE"]);
	else
		$arr["PRICES"] = array();
	
	$arSelect = array("ID", "IBLOCK_ID", "PREVIEW_PICTURE", "DETAIL_PICTURE");

	$arFilter = array(
		"IBLOCK_LID" => SITE_ID,
		"IBLOCK_ACTIVE" => "Y",
		"ACTIVE_DATE" => "Y",
		"ACTIVE" => "Y",
		"CHECK_PERMISSIONS" => "Y",
		"MIN_PERMISSION" => "R",
		"ID" => array_keys($arNewActiveItems)	
	);

	foreach($arr["PRICES"] as $key => $value) {
		$arSelect[] = $value["SELECT"];
		$arrFilter["CATALOG_SHOP_QUANTITY_".$value["ID"]] = 1;
	}
	
	$rsElements = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);	
	while($obElement = $rsElements->GetNextElement()) {
		$arItem = $obElement->GetFields();		

		$mxResult = CCatalogSku::GetProductInfo($arItem["ID"]);
		
		if($arItem["PREVIEW_PICTURE"] <= 0 && $arItem["DETAIL_PICTURE"] <= 0) {
			if(is_array($mxResult)) {
				$res = CIBlockElement::GetByID($mxResult["ID"]);
				if($ar_res = $res->GetNext()) {
					$arItem["PREVIEW_PICTURE"] = $ar_res["PREVIEW_PICTURE"];
					$arItem["DETAIL_PICTURE"] = $ar_res["DETAIL_PICTURE"];
				}
			}
		}
		
		//PREVIEW_PICTURE//
		if($arItem["PREVIEW_PICTURE"] > 0) {
			$arFile = CFile::GetFileArray($arItem["PREVIEW_PICTURE"]);
			if($arFile["WIDTH"] > 178 || $arFile["HEIGHT"] > 178) {
				$arFileTmp = CFile::ResizeImageGet(
					$arFile,
					array("width" => 178, "height" => 178),
					BX_RESIZE_IMAGE_PROPORTIONAL,
					true
				);
				$arResult["SEARCH"][$arItem["ID"]]["PREVIEW_PICTURE"] = array(
					"SRC" => $arFileTmp["src"],
					"WIDTH" => $arFileTmp["width"],
					"HEIGHT" => $arFileTmp["height"],
				);
			} else {
				$arResult["SEARCH"][$arItem["ID"]]["PREVIEW_PICTURE"] = $arFile;
			}
		} elseif($arItem["DETAIL_PICTURE"] > 0) {
			$arFile = CFile::GetFileArray($arItem["DETAIL_PICTURE"]);
			if($arFile["WIDTH"] > 178 || $arFile["HEIGHT"] > 178) {
				$arFileTmp = CFile::ResizeImageGet(
					$arFile,
					array("width" => 178, "height" => 178),
					BX_RESIZE_IMAGE_PROPORTIONAL,
					true
				);
				$arResult["SEARCH"][$arItem["ID"]]["PREVIEW_PICTURE"] = array(
					"SRC" => $arFileTmp["src"],
					"WIDTH" => $arFileTmp["width"],
					"HEIGHT" => $arFileTmp["height"],
				);
			} else {
				$arResult["SEARCH"][$arItem["ID"]]["PREVIEW_PICTURE"] = $arFile;
			}
		}

		$arItem["PROPERTIES"] = $obElement->GetProperties();
		$arResult["SEARCH"][$arItem["ID"]]["PROPERTIES"] = $arItem["PROPERTIES"];		
		
		if(is_array($mxResult)) {
			foreach($arParams["OFFERS_PROPERTY_CODE"] as $pid) {
				if(!isset($arItem["PROPERTIES"][$pid]))
					continue;
				$prop = &$arItem["PROPERTIES"][$pid];
				$boolArr = is_array($prop["VALUE"]);
				if(($boolArr && !empty($prop["VALUE"])) || (!$boolArr && strlen($prop["VALUE"]) > 0)) {
					$arItem["DISPLAY_PROPERTIES"][$pid] = CIBlockFormatProperties::GetDisplayValue($arItem, $prop, "catalog_out");
				}
			}
			$arResult["SEARCH"][$arItem["ID"]]["DISPLAY_PROPERTIES"] = $arItem["DISPLAY_PROPERTIES"];			
		}

		$grab_price = CIBlockPriceTools::GetItemPrices($arItem["IBLOCK_ID"], $arr["PRICES"], $arItem, $arParams["PRICE_VAT_INCLUDE"], $arConvertParams);
		if(!empty($grab_price))
			$arResult["SEARCH"][$arItem["ID"]]["MIN_PRICE"] = CIBlockPriceTools::getMinPriceFromList($grab_price);
		$arResult["SEARCH"][$arItem["ID"]]["PRICES"] = $grab_price;
		
		$arResult["SEARCH"][$arItem["ID"]]["CAN_BUY"] = CIBlockPriceTools::CanBuy($arItem["IBLOCK_ID"], $arr["PRICES"], $arItem);

		//MEASURE//
		if(!isset($arItem["CATALOG_MEASURE_RATIO"]))
			$arResult["SEARCH"][$arItem["ID"]]["CATALOG_MEASURE_RATIO"] = 1;
		
		$rsRatios = CCatalogMeasureRatio::getList(
			array(),
			array("PRODUCT_ID" => $arItem["ID"]),
			false,
			false,
			array("PRODUCT_ID", "RATIO")
		);
		if($arRatio = $rsRatios->Fetch()) {
			$intRatio = intval($arRatio["RATIO"]);
			$dblRatio = doubleval($arRatio["RATIO"]);
			$mxRatio = ($dblRatio > $intRatio ? $dblRatio : $intRatio);
			if(CATALOG_VALUE_EPSILON > abs($mxRatio))
				$mxRatio = 1;
			elseif(0 > $mxRatio)
				$mxRatio = 1;
			$arResult["SEARCH"][$arItem["ID"]]["CATALOG_MEASURE_RATIO"] = $mxRatio;
		}		

		if(!isset($arItem["CATALOG_MEASURE"]))
			$arItem["CATALOG_MEASURE"] = 0;
		$arItem["CATALOG_MEASURE"] = intval($arItem["CATALOG_MEASURE"]);
		if(0 > $arItem["CATALOG_MEASURE"])
			$arItem["CATALOG_MEASURE"] = 0;
		if(!isset($arItem["CATALOG_MEASURE_NAME"]))
			$arItem["CATALOG_MEASURE_NAME"] = "";
			
		if(0 < $arItem["CATALOG_MEASURE"]) {
			$rsMeasures = CCatalogMeasure::getList(
				array(),
				array("ID" => $arItem["CATALOG_MEASURE"]),
				false,
				false,
				array("ID", "SYMBOL_RUS")
			);
			if($arMeasure = $rsMeasures->GetNext()) {				
				$arItem["CATALOG_MEASURE_NAME"] = $arMeasure["SYMBOL_RUS"];
				$arResult["SEARCH"][$arItem["ID"]]["CATALOG_MEASURE_NAME"] = $arItem["CATALOG_MEASURE_NAME"];
			}
		}
		if("" == $arItem["CATALOG_MEASURE_NAME"]) {
			$arDefaultMeasure = CCatalogMeasure::getDefaultMeasure(true, true);
			$arItem["CATALOG_MEASURE_NAME"] = $arDefaultMeasure["SYMBOL_RUS"];
			$arResult["SEARCH"][$arItem["ID"]]["CATALOG_MEASURE_NAME"] = $arItem["CATALOG_MEASURE_NAME"];
		}
	}

	//OFFERS//
	$arOffers = array();
	$arOffersIblock = CIBlockPriceTools::GetOffersIBlock($arParams["IBLOCK_ID"]);
	$OFFERS_IBLOCK_ID = is_array($arOffersIblock) ? $arOffersIblock["OFFERS_IBLOCK_ID"] : 0;
	$arElementsOffer = array();	
	foreach($arResult["SEARCH"] as $key2 => $arElement)
		if($arElement["PARAM2"] == $arParams["IBLOCK_ID"])
			$arElementsOffer[$key2] = $arElement["ITEM_ID"];
			
	$arOffers = CIBlockPriceTools::GetOffersArray(
		$arParams["IBLOCK_ID"],
		$arElementsOffer,
		array(
			$arParams["OFFERS_SORT_FIELD"] => $arParams["OFFERS_SORT_ORDER"],
			$arParams["OFFERS_SORT_FIELD2"] => $arParams["OFFERS_SORT_ORDER2"],
		),
		$arParams["OFFERS_FIELD_CODE"],
		$arParams["OFFERS_PROPERTY_CODE"],
		$arParams["OFFERS_LIMIT"],
		$arr["PRICES"],
		$arParams["PRICE_VAT_INCLUDE"],
		$arConvertParams
	);

	if(!empty($arOffers)) {
		$arElementOffer = array();
		foreach($arElementsOffer as $i => $id) {
			$arResult["SEARCH"][$i]["OFFERS"] = array();
			$arElementOffer[$id] = &$arResult["SEARCH"][$i]["OFFERS"];
		}
		foreach($arOffers as $key=>$arOffer) {
			if(array_key_exists($arOffer["LINK_ELEMENT_ID"], $arElementOffer)) {
				$arElementOffer[$arOffer["LINK_ELEMENT_ID"]][] = $arOffer;
			}
		}
	}
	
	//ELEMENTS//
	foreach($arResult["SEARCH"] as $key => $arElement):		
		//SELECT_PROPS//
		$mxResult = CCatalogSku::GetProductInfo($arElement["ITEM_ID"]);
		if(is_array($mxResult)) {			
			$arFilter["ID"] = $mxResult["ID"];			
			$arElement["PROPERTIES"] = array();
			$rsElements = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "IBLOCK_ID"));	
			while($obElement = $rsElements->GetNextElement()) {
				$arElement = $obElement->GetFields();
				$arElement["PROPERTIES"] = $obElement->GetProperties();
			}
		}
		if(is_array($arParams["PROPERTY_CODE_MOD"]) && !empty($arParams["PROPERTY_CODE_MOD"])) {
			$arResult["SEARCH"][$key]["SELECT_PROPS"] = array();
			foreach($arParams["PROPERTY_CODE_MOD"] as $pid) {

			// DT SEARCH FIXED PRODUCT PROPERTIES
			if ($pid === "GUARANTEE") {
				continue;
			}
			// END DT SEARCH FIXED PRODUCT PROPERTIES

			if(!isset($arElement["PROPERTIES"][$pid]))
				continue;
				$prop = &$arElement["PROPERTIES"][$pid];				
				$boolArr = is_array($prop["VALUE"]);				
				if($prop["MULTIPLE"] == "Y" && $boolArr && !empty($prop["VALUE"])) {
					$arResult["SEARCH"][$key]["SELECT_PROPS"][$pid] = CIBlockFormatProperties::GetDisplayValue($arElement, $prop, "catalog_out");
					if(!is_array($arResult["SEARCH"][$key]["SELECT_PROPS"][$pid]["DISPLAY_VALUE"]) && !empty($arResult["SEARCH"][$key]["SELECT_PROPS"][$pid]["DISPLAY_VALUE"])) {
						$arTmp = $arResult["SEARCH"][$key]["SELECT_PROPS"][$pid]["DISPLAY_VALUE"];
						unset($arResult["SEARCH"][$key]["SELECT_PROPS"][$pid]["DISPLAY_VALUE"]);
						$arResult["SEARCH"][$key]["SELECT_PROPS"][$pid]["DISPLAY_VALUE"][0] = $arTmp;
					}
				} elseif($prop["MULTIPLE"] == "N" && !$boolArr) {
					if($prop["PROPERTY_TYPE"] == "L") {
						$arResult["SEARCH"][$key]["SELECT_PROPS"][$pid] = $prop;
						$property_enums = CIBlockPropertyEnum::GetList(Array("SORT" => "ASC"), Array("IBLOCK_ID" => $arParams["IBLOCK_ID"], "CODE" => $pid));
						while($enum_fields = $property_enums->GetNext()) {
							$arResult["SEARCH"][$key]["SELECT_PROPS"][$pid]["DISPLAY_VALUE"][] = $enum_fields["VALUE"];
						}
					}
				}
			}
		}		
		
		//OFFERS//
		if(isset($arElement["OFFERS"]) && !empty($arElement["OFFERS"])):
			//TOTAL_OFFERS//			
			$totalDiscount = array();			
			
			$minPrice = false;	
			$minDiscount = false;			
			$minCurr = false;
			$minMeasureRatio = false;
			$minMeasure = false;
			
			$arResult["SEARCH"][$key]["TOTAL_OFFERS"] = array();
			
			foreach($arElement["OFFERS"] as $key_off => $arOffer):				
				if($arOffer["MIN_PRICE"]["DISCOUNT_VALUE"] == 0)
					continue;

				$totalDiscount[] = $arOffer["MIN_PRICE"]["DISCOUNT_VALUE"];
				
				if($minDiscount === false || $minDiscount > $arOffer["MIN_PRICE"]["DISCOUNT_VALUE"]) {					
					$minPrice = $arOffer["MIN_PRICE"]["VALUE"];			
					$minDiscount = $arOffer["MIN_PRICE"]["DISCOUNT_VALUE"];					
					$minCurr = $arOffer["MIN_PRICE"]["CURRENCY"];			
					$minMeasureRatio = $arOffer["CATALOG_MEASURE_RATIO"];
					$minMeasure = $arOffer["CATALOG_MEASURE_NAME"];
				}		
			endforeach;
			
			if(count($totalDiscount) > 0):
				$arResult["SEARCH"][$key]["TOTAL_OFFERS"]["MIN_PRICE"] = array(					
					"VALUE" => $minPrice,		
					"DISCOUNT_VALUE" => $minDiscount,					
					"CURRENCY" => $minCurr,		
					"CATALOG_MEASURE_RATIO" => $minMeasureRatio,
					"CATALOG_MEASURE_NAME" => $minMeasure
				);
			else:
				$arResult["SEARCH"][$key]["TOTAL_OFFERS"]["MIN_PRICE"] = array(
					"VALUE" => "0",
					"CURRENCY" => $arElement["OFFERS"][0]["MIN_PRICE"]["CURRENCY"],
					"CATALOG_MEASURE_RATIO" => $arElement["OFFERS"][0]["CATALOG_MEASURE_RATIO"],
					"CATALOG_MEASURE_NAME" => $arElement["OFFERS"][0]["CATALOG_MEASURE_NAME"]
				);			
			endif;			
			
			if(count(array_unique($totalDiscount)) > 1):
				$arResult["SEARCH"][$key]["TOTAL_OFFERS"]["FROM"] = "Y";
			else:
				$arResult["SEARCH"][$key]["TOTAL_OFFERS"]["FROM"] = "N";
			endif;
			//END_TOTAL_OFFERS//
			
			//PREVIEW_PICTURE//
			foreach($arElement["OFFERS"] as $key_off => $arOffer):	
				if(is_array($arOffer["PREVIEW_PICTURE"])) {
					if($arOffer["PREVIEW_PICTURE"]["WIDTH"] > 178 || $arOffer["PREVIEW_PICTURE"]["HEIGHT"] > 178) {
						$arFileTmp = CFile::ResizeImageGet(
							$arOffer["PREVIEW_PICTURE"],
							array("width" => 178, "height" => 178),
							BX_RESIZE_IMAGE_PROPORTIONAL,
							true
						);
						$arResult["SEARCH"][$key]["OFFERS"][$key_off]["PREVIEW_PICTURE"] = array(
							"SRC" => $arFileTmp["src"],
							"WIDTH" => $arFileTmp["width"],
							"HEIGHT" => $arFileTmp["height"]
						);
					}
				} elseif(is_array($arOffer["DETAIL_PICTURE"])) {
					if($arOffer["DETAIL_PICTURE"]["WIDTH"] > 178 || $arOffer["DETAIL_PICTURE"]["HEIGHT"] > 178) {
						$arFileTmp = CFile::ResizeImageGet(
							$arOffer["DETAIL_PICTURE"],
							array("width" => 178, "height" => 178),
							BX_RESIZE_IMAGE_PROPORTIONAL,
							true
						);
						$arResult["SEARCH"][$key]["OFFERS"][$key_off]["PREVIEW_PICTURE"] = array(
							"SRC" => $arFileTmp["src"],
							"WIDTH" => $arFileTmp["width"],
							"HEIGHT" => $arFileTmp["height"]
						);
					}
				}
			endforeach;
			//END_PREVIEW_PICTURE//
		endif;
		//END_OFFERS//
	endforeach;
	//END_ELEMENTS//

	// DT DISCONTINUED PRODUCTS
	if (class_exists("DTDiscontinuedProduct")) {
		foreach ($arResult["SEARCH"] as $dtKey => &$dtItem) {

			$dtProductId = (int)$dtItem["ITEM_ID"];

			$dtItem["DT_DISCONTINUED"] = false;
			$dtItem["DT_STOCK_QUANTITY"] = 0;
			$dtItem["DT_DISCONTINUED_NO_STOCK"] = false;

			if ($dtProductId <= 0) {
				continue;
			}

			$dtItem["DT_DISCONTINUED"] =
				DTDiscontinuedProduct::isDiscontinued($dtProductId);

			if (!$dtItem["DT_DISCONTINUED"]) {
				continue;
			}

			if (!empty($dtItem["OFFERS"])) {

				$dtTotalQuantity = 0;

				foreach ($dtItem["OFFERS"] as &$dtOffer) {

					$dtOffer["DT_DISCONTINUED"] = true;

					$dtOffer["DT_STOCK_QUANTITY"] =
						DTDiscontinuedProduct::getQuantity(
							(int)$dtOffer["ID"]
						);

					$dtOffer["DT_DISCONTINUED_NO_STOCK"] =
						$dtOffer["DT_STOCK_QUANTITY"] <= 0;

					if ($dtOffer["DT_DISCONTINUED_NO_STOCK"]) {
						$dtOffer["CAN_BUY"] = false;
					}

					$dtTotalQuantity +=
						$dtOffer["DT_STOCK_QUANTITY"];
				}

				unset($dtOffer);

				$dtItem["DT_STOCK_QUANTITY"] = $dtTotalQuantity;

			} else {

				$dtItem["DT_STOCK_QUANTITY"] =
					DTDiscontinuedProduct::getQuantity($dtProductId);
			}

			$dtItem["DT_DISCONTINUED_NO_STOCK"] =
				$dtItem["DT_STOCK_QUANTITY"] <= 0;

			if ($dtItem["DT_DISCONTINUED_NO_STOCK"]) {
				$dtItem["CAN_BUY"] = false;
			}
		}

		unset($dtItem);
	}
	// END DT DISCONTINUED PRODUCTS

	//PROPERTIES_JS_OFFERS//
	$arParams["OFFER_TREE_PROPS"] = $arParams["OFFERS_PROPERTY_CODE"];
	if(!is_array($arParams["OFFER_TREE_PROPS"]))
		$arParams["OFFER_TREE_PROPS"] = array($arParams["OFFER_TREE_PROPS"]);
	foreach($arParams["OFFER_TREE_PROPS"] as $key => $value) {
		$value = (string)$value;
		if("" == $value || "-" == $value)
			unset($arParams["OFFER_TREE_PROPS"][$key]);
	}
	if(empty($arParams["OFFER_TREE_PROPS"]) && isset($arParams["OFFERS_CART_PROPERTIES"]) && is_array($arParams["OFFERS_CART_PROPERTIES"])) {
		$arParams["OFFER_TREE_PROPS"] = $arParams["OFFERS_CART_PROPERTIES"];
		foreach($arParams["OFFER_TREE_PROPS"] as $key => $value) {
			$value = (string)$value;
			if("" == $value || "-" == $value)
				unset($arParams["OFFER_TREE_PROPS"][$key]);
		}
	}

	$arSKUPropList = array();
	$arSKUPropIDs = array();
	$arSKUPropKeys = array();
	$boolSKU = false;
			
	if(CModule::IncludeModule("catalog")) {
		$arSKU = CCatalogSKU::GetInfoByProductIBlock($arParams["IBLOCK_ID"]);
		$boolSKU = !empty($arSKU) && is_array($arSKU);
		if($boolSKU && !empty($arParams["OFFER_TREE_PROPS"])) {
			$arSKUPropList = CIBlockPriceTools::getTreeProperties(
				$arSKU,
				$arParams["OFFER_TREE_PROPS"],
				array()
			);
			$arNeedValues = array();
			CIBlockPriceTools::getTreePropertyValues($arSKUPropList, $arNeedValues);

			if($arSetting["OFFERS_VIEW"]["VALUE"] == "LIST") {
				$propertyIterator = Iblock\PropertyTable::getList(array(
					"select" => array(
						"ID", "IBLOCK_ID", "CODE", "NAME", "SORT", "LINK_IBLOCK_ID", "PROPERTY_TYPE", "USER_TYPE", "USER_TYPE_SETTINGS"
					),
					"filter" => array(
						"=IBLOCK_ID" => $arSKU["IBLOCK_ID"],
						"=PROPERTY_TYPE" => array(
							Iblock\PropertyTable::TYPE_STRING
						),
						"=ACTIVE" => "Y", "=MULTIPLE" => "N"
					),
					"order" => array(
						"SORT" => "ASC", "ID" => "ASC"
					)
				));
				while($propInfo = $propertyIterator->fetch()) {			
					if(!in_array($propInfo["CODE"], $arParams["OFFER_TREE_PROPS"]))
						continue;			
					$arSKUPropList[$propInfo["CODE"]] = $propInfo;
					$arSKUPropList[$propInfo["CODE"]]["VALUES"] = array();
					$arSKUPropList[$propInfo["CODE"]]["SHOW_MODE"] = "TEXT";
					$arSKUPropList[$propInfo["CODE"]]["DEFAULT_VALUES"] = array(
						"PICT" => false,
						"NAME" => "-"
					);
				}
			}

			$arSKUPropIDs = array_keys($arSKUPropList);
			$arSKUPropKeys = array_fill_keys($arSKUPropIDs, false);
		}
	}
	
	foreach($arResult["SEARCH"] as $key => $arItem) {
		if(CModule::IncludeModule("catalog")) {
			$arItem["CATALOG"] = true;
			if(!isset($arItem["CATALOG_TYPE"]))
				$arItem["CATALOG_TYPE"] = CCatalogProduct::TYPE_PRODUCT;
			if((CCatalogProduct::TYPE_PRODUCT == $arItem["CATALOG_TYPE"] || CCatalogProduct::TYPE_SKU == $arItem["CATALOG_TYPE"]) && !empty($arItem["OFFERS"])) {
				$arItem["CATALOG_TYPE"] = CCatalogProduct::TYPE_SKU;
			}
			switch($arItem["CATALOG_TYPE"]) {
				case CCatalogProduct::TYPE_SET:
					$arItem["OFFERS"] = array();
					break;
				case CCatalogProduct::TYPE_SKU:
					break;
				case CCatalogProduct::TYPE_PRODUCT:
				default:
					break;
			}
		} else {
			$arItem["CATALOG_TYPE"] = 0;
			$arItem["OFFERS"] = array();
		}
		
		if($arItem["CATALOG"] && isset($arItem["OFFERS"]) && !empty($arItem["OFFERS"])) {
			$arMatrixFields = $arSKUPropKeys;
			$arMatrix = array();

			$arNewOffers = array();
			$arItem["OFFERS_PROP"] = false;

			$arDouble = array();
			foreach($arItem["OFFERS"] as $keyOffer => $arOffer) {
				$arOffer["ID"] = intval($arOffer["ID"]);
				if(isset($arDouble[$arOffer["ID"]]))
					continue;
				$arRow = array();
				foreach($arSKUPropIDs as $propkey => $strOneCode) {
					$arCell = array(
						"VALUE" => 0,
						"SORT" => PHP_INT_MAX,
						"NA" => true
					);
					if(isset($arOffer["DISPLAY_PROPERTIES"][$strOneCode])) {
						$arMatrixFields[$strOneCode] = true;
						$arCell["NA"] = false;
						if("directory" == $arSKUPropList[$strOneCode]["USER_TYPE"]) {
							$intValue = $arSKUPropList[$strOneCode]["XML_MAP"][$arOffer["DISPLAY_PROPERTIES"][$strOneCode]["VALUE"]];
							$arCell["VALUE"] = $intValue;
						} elseif("L" == $arSKUPropList[$strOneCode]["PROPERTY_TYPE"]) {
							$arCell["VALUE"] = intval($arOffer["DISPLAY_PROPERTIES"][$strOneCode]["VALUE_ENUM_ID"]);
						} elseif("E" == $arSKUPropList[$strOneCode]["PROPERTY_TYPE"]) {
							$arCell["VALUE"] = intval($arOffer["DISPLAY_PROPERTIES"][$strOneCode]["VALUE"]);
						} elseif("S" == $arSKUPropList[$strOneCode]["PROPERTY_TYPE"]) {
							$arCell["VALUE"] = intval($arOffer["DISPLAY_PROPERTIES"][$strOneCode]["PROPERTY_VALUE_ID"]);					
						}
						$arCell["SORT"] = $arSKUPropList[$strOneCode]["VALUES"][$arCell["VALUE"]]["SORT"];
					}
					$arRow[$strOneCode] = $arCell;
				}
				$arMatrix[$keyOffer] = $arRow;
				unset($arRow);

				$arDouble[$arOffer["ID"]] = true;
				$arNewOffers[$keyOffer] = $arOffer;
			}
			unset($keyOffer, $arOffer);
			$arItem["OFFERS"] = $arNewOffers;
				
			$arUsedFields = array();
			$arSortFields = array();

			$matrixKeys = array_keys($arMatrix);
			foreach($arSKUPropIDs as $propkey => $propCode) {
				$boolExist = $arMatrixFields[$propCode];
				foreach($matrixKeys as $keyOffer) {
					if($boolExist) {
						if(!isset($arItem["OFFERS"][$keyOffer]["TREE"]))
							$arItem["OFFERS"][$keyOffer]["TREE"] = array();
						$propId = $arSKUPropList[$propCode]["ID"];
						$value = $arMatrix[$keyOffer][$propCode]["VALUE"];
						if(!isset($arItem["SKU_TREE_VALUES"][$propId]))
							$arItem["SKU_TREE_VALUES"][$propId] = array();
						$arItem["SKU_TREE_VALUES"][$propId][$value] = true;
						$arItem["OFFERS"][$keyOffer]["TREE"]["PROP_".$propId] = $value;
						$arItem["OFFERS"][$keyOffer]["SKU_SORT_".$propCode] = $arMatrix[$keyOffer][$propCode]["SORT"];
						$arUsedFields[$propCode] = true;
						$arSortFields["SKU_SORT_".$propCode] = SORT_NUMERIC;
						unset($value, $propId);
					} else {
						unset($arMatrix[$keyOffer][$propCode]);
					}
				}
				unset($keyOffer);
			}
			unset($propkey, $propCode);
			unset($matrixKeys);
			$arItem["OFFERS_PROP"] = $arUsedFields;
			
			Collection::sortByColumn($arItem["OFFERS"], $arSortFields);

			$intSelected = -1;
			$arItem["MIN_PRICE"] = false;
			foreach($arItem["OFFERS"] as $keyOffer => $arOffer) {
				if($arOffer["MIN_PRICE"]["DISCOUNT_VALUE"] <= 0)
					continue;
				if($arItem["MIN_PRICE"] === false || $arItem["MIN_PRICE"] > $arOffer["MIN_PRICE"]["DISCOUNT_VALUE"]) {
					$intSelected = $keyOffer;
					$arItem["MIN_PRICE"] = $arOffer["MIN_PRICE"]["DISCOUNT_VALUE"];
				}
			}
			$arMatrix = array();
			foreach($arItem["OFFERS"] as $keyOffer => $arOffer) {				
				$arOneRow = array(
					"ID" => $arOffer["ID"],
					"NAME" => $arOffer["~NAME"],
					"PREVIEW_PICTURE" => $arOffer["PREVIEW_PICTURE"],
					"TREE" => $arOffer["TREE"],
					"CAN_BUY" => $arOffer["CAN_BUY"]
				);
				$arMatrix[$keyOffer] = $arOneRow;
			}
			if(-1 == $intSelected)
				$intSelected = 0;
			$arItem["JS_OFFERS"] = $arMatrix;
			$arItem["OFFERS_SELECTED"] = $intSelected;
		}
		$arResult["SEARCH"][$key] = $arItem;
	}
	
	//SKU_PROPS_PICT//
	$arSelect = array("ID", "IBLOCK_ID", "NAME", "PROPERTY_HEX", "PROPERTY_PICT");
	foreach($arSKUPropList as $key => $arSKUProp) {
		if($arSKUProp["SHOW_MODE"] == "PICT") {		
			$arSkuID = array();
			foreach($arSKUProp["VALUES"] as $key2 => $arSKU) {
				if($arSKU["ID"] > 0)
					$arSkuID[] = $arSKU["ID"];
			}
			$arFilter = array("IBLOCK_ID" => $arSKUProp["LINK_IBLOCK_ID"], "ID" => $arSkuID);
			$res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
			while($ob = $res->GetNextElement()) {
				$arFields = $ob->GetFields();
				if(!empty($arFields["PROPERTY_HEX_VALUE"]))
					$arSKUPropList[$key]["VALUES"][$arFields["ID"]]["HEX"] = $arFields["PROPERTY_HEX_VALUE"];
				if($arFields["PROPERTY_PICT_VALUE"] > 0) {
					$arFile = CFile::GetFileArray($arFields["PROPERTY_PICT_VALUE"]);
					if($arFile["WIDTH"] > 24 || $arFile["HEIGHT"] > 24) {
						$arFileTmp = CFile::ResizeImageGet(
							$arFile,
							array("width" => 24, "height" => 24),
							BX_RESIZE_IMAGE_PROPORTIONAL,
							true
						);
						$arSKUPropList[$key]["VALUES"][$arFields["ID"]]["PICT"] = array(
							"SRC" => $arFileTmp["src"],
							"WIDTH" => $arFileTmp["width"],
							"HEIGHT" => $arFileTmp["height"],
						);
					} else {
						$arSKUPropList[$key]["VALUES"][$arFields["ID"]]["PICT"] = $arFile;
					}
				}
			}			
		}
	}
	
	$arResult["SKU_PROPS"] = $arSKUPropList;
}

if(empty($arActiveSections) && empty($arNewActiveItems)) {
	$arResult["CATEGORIES"] = array();
}?>