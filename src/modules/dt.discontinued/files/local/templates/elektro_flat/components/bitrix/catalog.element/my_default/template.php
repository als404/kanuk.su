<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$this->addExternalCss(SITE_TEMPLATE_PATH."/components/bitrix/sale.notice.product/.default/style.css");

global $arSetting;


$strMainID = $this->GetEditAreaId($arResult["ID"]);
$arItemIDs = array(
	"ID" => $strMainID,
	"PICT" => $strMainID."_picture",
	"PRICE" => $strMainID."_price",
	"BUY" => $strMainID."_buy",
	"DELAY" => $strMainID."_delay",
	"DELIVERY" => $strMainID."_geolocation_delivery",
	"ARTICLE" => $strMainID."_article",
	"PROPERTIES" => $strMainID."_properties",
	"PROP_DIV" => $strMainID."_skudiv",
	"PROP" => $strMainID."_prop_",
	"SELECT_PROP_DIV" => $strMainID."_propdiv",
	"SELECT_PROP" => $strMainID."_select_prop_",
	"BTN_BUY" => $strMainID."_btn_buy"
);
$strObName = "ob".preg_replace("/[^a-zA-Z0-9_]/", "x", $strMainID);
$templateData["JS_OBJ"] = $strObName;

//JS//?>
<script type="text/javascript">
	BX.ready(function() {			
		//DETAIL_GEOLOCATION_DELIVERY//
		<?if($arSetting["USE_GEOLOCATION"]["VALUE"] == "Y" && $arSetting["GEOLOCATION_DELIVERY"]["VALUE"] == "Y"):
			if(isset($arResult["OFFERS"]) && !empty($arResult["OFFERS"])):
				if($arSetting["OFFERS_VIEW"]["VALUE"] != "LIST"):
					foreach($arResult["OFFERS"] as $key => $arOffer):
						if($arOffer["CAN_BUY"] && $arOffer["MIN_PRICE"]["DISCOUNT_VALUE"] > 0):?>
							BX("<?=$arItemIDs['DELIVERY']?>").appendChild(BX.style(BX("geolocation-delivery-from-<?=$arItemIDs['ID'].'-'.$arOffer['ID']?>"), "display", ""));
						<?endif;
					endforeach;
				endif;
			else:
				if($arResult["CAN_BUY"] && $arResult["MIN_PRICE"]["DISCOUNT_VALUE"] > 0):?>
					BX("<?=$arItemIDs['DELIVERY']?>").appendChild(BX.style(BX("geolocation-delivery-from"), "display", ""));
				<?endif;
			endif;
		endif;
		
		//OFFERS_LIST_PROPS//
		/*if(isset($arResult["OFFERS"]) && !empty($arResult["OFFERS"]) && $arSetting["OFFERS_VIEW"]["VALUE"] == "LIST"):
			foreach($arResult["OFFERS"] as $key_off => $arOffer):?>
				props = BX.findChildren(BX("catalog-offer-item-<?=$arItemIDs['ID'].'-'.$arOffer['ID']?>"), {className: "catalog-item-prop"}, true);
				if(!!props && 0 < props.length) {
					for(i = 0; i < props.length; i++) {
						if(!BX.hasClass(props[i], "empty")) {
							BX("catalog-item-props-mob-<?=$arItemIDs['ID'].'-'.$arOffer['ID']?>").appendChild(BX.create(
								"DIV",
								{
									props: {
										className: "catalog-item-prop"
									},
									html: props[i].innerHTML
								}
							));
						}
					}
				}
			<?endforeach;
		endif;*/?>

		//FANCYBOX//
		$(".fancybox").fancybox({
			"transitionIn": "elastic",
			"transitionOut": "elastic",
			"speedIn": 600,
			"speedOut": 200,
			"overlayShow": false,
			"cyclic" : true,
			"padding": 20,
			"titlePosition": "over",
			"onComplete": function() {
				$("#fancybox-title").css({"top":"100%", "bottom":"auto"});
			} 
		});
	});
</script>

<?//NEW_HIT_DISCOUNT_TIME_BUY//
$sticker = "";
$timeBuy = "";
if(array_key_exists("PROPERTIES", $arResult) && is_array($arResult["PROPERTIES"])):
	//NEW//
	if(array_key_exists("NEWPRODUCT", $arResult["PROPERTIES"]) && !$arResult["PROPERTIES"]["NEWPRODUCT"]["VALUE"] == false):
		$sticker .= "<span class='new'>".GetMessage("CATALOG_ELEMENT_NEWPRODUCT")."</span>";
	endif;
	//HIT//
	if(array_key_exists("SALELEADER", $arResult["PROPERTIES"]) && !$arResult["PROPERTIES"]["SALELEADER"]["VALUE"] == false):
		$sticker .= "<span class='hit'>".GetMessage("CATALOG_ELEMENT_SALELEADER")."</span>";
	endif;
	//DISCOUNT//
	if(isset($arResult["OFFERS"]) && !empty($arResult["OFFERS"])):
		if($arSetting["OFFERS_VIEW"]["VALUE"] == "LIST"):			
			if($arResult["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_DIFF_PERCENT"] > 0):
				$sticker .= "<span class='discount'>-".$arResult["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_DIFF_PERCENT"]."%</span>";
			else:
				if(array_key_exists("DISCOUNT", $arResult["PROPERTIES"]) && !$arResult["PROPERTIES"]["DISCOUNT"]["VALUE"] == false):	
					$sticker .= "<span class='discount'>%</span>";
				endif;
			endif;
		endif;	
	else:
		if($arResult["MIN_PRICE"]["DISCOUNT_DIFF_PERCENT"] > 0):
			$sticker .= "<span class='discount'>-".$arResult["MIN_PRICE"]["DISCOUNT_DIFF_PERCENT"]."%</span>";
		else:
			if(array_key_exists("DISCOUNT", $arResult["PROPERTIES"]) && !$arResult["PROPERTIES"]["DISCOUNT"]["VALUE"] == false):
				$sticker .= "<span class='discount'>%</span>";
			endif;
		endif;
	endif;
	//TIME_BUY//
	if(array_key_exists("TIME_BUY", $arResult["PROPERTIES"]) && !$arResult["PROPERTIES"]["TIME_BUY"]["VALUE"] == false):
		if(!empty($arResult["CURRENT_DISCOUNT"]["ACTIVE_TO"])):
			if((isset($arResult["OFFERS"]) && !empty($arResult["OFFERS"])) || ((!isset($arResult["OFFERS"]) || empty($arResult["OFFERS"])) && $arResult["CAN_BUY"])):
				$timeBuy = "<span class='time_buy_figure'></span><span class='time_buy_text'>".GetMessage("CATALOG_ELEMENT_TIME_BUY")."</span>";
			endif;
		endif;
	endif;
endif;

//DETAIL_PICTURE_ALT//
$strAlt = (isset($arResult["IPROPERTY_VALUES"]["ELEMENT_DETAIL_PICTURE_FILE_ALT"]) && $arResult["IPROPERTY_VALUES"]["ELEMENT_DETAIL_PICTURE_FILE_ALT"] != "" ? $arResult["IPROPERTY_VALUES"]["ELEMENT_DETAIL_PICTURE_FILE_ALT"] : $arResult["NAME"]);

//DETAIL_PICTURE_TITLE//
$strTitle = (isset($arResult["IPROPERTY_VALUES"]["ELEMENT_DETAIL_PICTURE_FILE_TITLE"]) && $arResult["IPROPERTY_VALUES"]["ELEMENT_DETAIL_PICTURE_FILE_TITLE"] != "" ? $arResult["IPROPERTY_VALUES"]["ELEMENT_DETAIL_PICTURE_FILE_TITLE"] : $arResult["NAME"]);?>

<?//CATALOG_DETAIL//?>
<div id="<?=$arItemIDs['ID']?>" class="catalog-detail-element quick-view" itemscope itemtype="http://schema.org/Product">
	<meta content="<?=$arResult['NAME']?>" itemprop="name" />

	<div class="h1" style="font-size: 18px;"><?=$arResult["NAME"]?></div>
	<div class="item-detail">
		<div class="col__first">
			<div class="catalog-detail-pictures">
				<?//OFFERS_DETAIL_PICTURE//?>
				<div class="catalog-detail-picture<?if(!empty($arResult["DT_DISCONTINUED"])):?> dt-discontinued-image<?endif;?>" id="<?=$arItemIDs['PICT']?>">

					<?if(!empty($arResult["DT_DISCONTINUED"])):?>
						<span class="dt-discontinued-badge">
							Снят с производства!
						</span>
					<?endif;?>
					<?//OFFERS_PICTURE//
					if(isset($arResult["OFFERS"]) && !empty($arResult["OFFERS"]) && $arSetting["OFFERS_VIEW"]["VALUE"] != "LIST"):
						foreach($arResult["OFFERS"] as $key => $arOffer):?>							
							<div id="detail_picture_<?=$arItemIDs['ID'].'_'.$arOffer['ID']?>" class="detail_picture hidden">
								<meta content="<?=is_array($arOffer['DETAIL_IMG']) ? $arOffer['DETAIL_PICTURE']['SRC'] : $arResult['DETAIL_PICTURE']['SRC']?>" itemprop="image" />
								<a class="catalog-detail-images fancybox" id="catalog-detail-images-<?=$arItemIDs['ID'].'-'.$arOffer['ID']?>" href="<?=is_array($arOffer['DETAIL_IMG']) ? $arOffer['DETAIL_PICTURE']['SRC'] : $arResult['DETAIL_PICTURE']['SRC']?>">
									<?if(is_array($arOffer["DETAIL_IMG"])):?>
										<img src="<?=$arOffer['DETAIL_IMG']['SRC']?>" width="<?=$arOffer['DETAIL_IMG']['WIDTH']?>" height="<?=$arOffer['DETAIL_IMG']['HEIGHT']?>" alt="<?=(isset($arOffer['NAME']) && !empty($arOffer['NAME']) ? $arOffer['NAME'] : $arResult['NAME']);?>" title="<?=(isset($arOffer['NAME']) && !empty($arOffer['NAME']) ? $arOffer['NAME'] : $arResult['NAME']);?>" />
									<?else:?>
										<img src="<?=$arResult['DETAIL_IMG']['SRC']?>" width="<?=$arResult['DETAIL_IMG']['WIDTH']?>" height="<?=$arResult['DETAIL_IMG']['HEIGHT']?>" alt="<?=$strAlt?>" title="<?=$strTitle?>" />
									<?endif;?>
									<div class="time_buy_sticker">
										<?=$timeBuy?>
									</div>
									<div class="sticker">
										<?=$sticker;
										if($arOffer["MIN_PRICE"]["DISCOUNT_DIFF_PERCENT"] > 0):?>
											<span class="discount">-<?=$arOffer["MIN_PRICE"]["DISCOUNT_DIFF_PERCENT"]?>%</span>
										<?else:
											if(array_key_exists("DISCOUNT", $arResult["PROPERTIES"]) && !$arResult["PROPERTIES"]["DISCOUNT"]["VALUE"] == false):?>	
												<span class="discount">%</span>
											<?endif;
										endif;?>
									</div>
									<?if(is_array($arResult["PROPERTIES"]["MANUFACTURER"]["PREVIEW_PICTURE"])):?>
										<img class="manufacturer" src="<?=$arResult['PROPERTIES']['MANUFACTURER']['PREVIEW_PICTURE']['SRC']?>" width="<?=$arResult['PROPERTIES']['MANUFACTURER']['PREVIEW_PICTURE']['WIDTH']?>" height="<?=$arResult['PROPERTIES']['MANUFACTURER']['PREVIEW_PICTURE']['HEIGHT']?>" alt="<?=$arResult['PROPERTIES']['MANUFACTURER']['NAME']?>" title="<?=$arResult['PROPERTIES']['MANUFACTURER']['NAME']?>" />
									<?endif;?>
								</a>
							</div>
						<?endforeach;
					//DETAIL_PICTURE//
					else:?>	
						<div class="detail_picture">
							<meta content="<?=is_array($arResult['DETAIL_IMG']) ? $arResult['DETAIL_PICTURE']['SRC'] : SITE_TEMPLATE_PATH.'/images/no-photo.jpg'?>" itemprop="image" />
							<?if(is_array($arResult["DETAIL_IMG"])):?>
								<a rel="lightbox" class="catalog-detail-images fancybox" href="<?=$arResult['DETAIL_PICTURE']['SRC']?>"> 
									<img src="<?=$arResult['DETAIL_IMG']['SRC']?>" width="<?=$arResult['DETAIL_IMG']['WIDTH']?>" height="<?=$arResult['DETAIL_IMG']['HEIGHT']?>" alt="<?=$strAlt?>" title="<?=$strTitle?>" />
							<?else:?>
								<div class="catalog-detail-images">
									<img src="<?=SITE_TEMPLATE_PATH?>/images/no-photo.jpg" width="150" height="150" alt="<?=$strAlt?>" title="<?=$strTitle?>" />
							<?endif;?>
							<div class="time_buy_sticker">
								<?=$timeBuy?>
							</div>
							<div class="sticker">
								<?=$sticker?>
							</div>
							<?if(is_array($arResult["PROPERTIES"]["MANUFACTURER"]["PREVIEW_PICTURE"])):?>
								<img class="manufacturer" src="<?=$arResult['PROPERTIES']['MANUFACTURER']['PREVIEW_PICTURE']['SRC']?>" width="<?=$arResult['PROPERTIES']['MANUFACTURER']['PREVIEW_PICTURE']['WIDTH']?>" height="<?=$arResult['PROPERTIES']['MANUFACTURER']['PREVIEW_PICTURE']['HEIGHT']?>" alt="<?=$arResult['PROPERTIES']['MANUFACTURER']['NAME']?>" title="<?=$arResult['PROPERTIES']['MANUFACTURER']['NAME']?>" />
							<?endif;?>
							<?=is_array($arResult["DETAIL_IMG"]) ? "</a>" : "</div>";?>							
						</div>					
					<?endif;?>
				</div>
				<?//DETAIL_VIDEO_MORE_PHOTO//
				if(!empty($arResult["PROPERTIES"]["VIDEO"]) || count($arResult["MORE_PHOTO"])>0):?>
					<div class="clr"></div>
					<div class="more_photo">
						<ul>
							<?if(!empty($arResult["PROPERTIES"]["VIDEO"]["VALUE"])):?>
								<li class="catalog-detail-video" style="<?=($arParams['DISPLAY_MORE_PHOTO_WIDTH'] ? 'width:'.$arParams['DISPLAY_MORE_PHOTO_WIDTH'].'px;' : '').($arParams['DISPLAY_MORE_PHOTO_HEIGHT'] ? 'height:'.$arParams['DISPLAY_MORE_PHOTO_HEIGHT'].'px;' : '');?>">
									<a rel="lightbox" class="catalog-detail-images fancybox" href="#video">
										<i class="fa fa-play-circle-o"></i>
										<span><?=GetMessage("CATALOG_ELEMENT_VIDEO")?></span>
									</a>
									<div id="video" style="overflow:hidden;">
										<?=$arResult["PROPERTIES"]["VIDEO"]["~VALUE"]["TEXT"];?>
									</div>
								</li>
							<?endif;
							if(count($arResult["MORE_PHOTO"]) > 0):
								foreach($arResult["MORE_PHOTO"] as $PHOTO):?>
									<li style="<?=($arParams['DISPLAY_MORE_PHOTO_WIDTH'] ? 'width:'.$arParams['DISPLAY_MORE_PHOTO_WIDTH'].'px;' : '').($arParams['DISPLAY_MORE_PHOTO_HEIGHT'] ? 'height:'.$arParams['DISPLAY_MORE_PHOTO_HEIGHT'].'px;' : '');?>">
										<a rel="lightbox" class="catalog-detail-images fancybox" href="<?=$PHOTO['SRC']?>">
											<img src="<?=$PHOTO['PREVIEW']['SRC']?>" width="<?=$PHOTO['PREVIEW']['WIDTH']?>" height="<?=$PHOTO['PREVIEW']['HEIGHT']?>" alt="<?=$arResult['NAME']?>" title="<?=$arResult['NAME']?>" />
										</a>
									</li>
								<?endforeach;
							endif;?>
						</ul>
					</div>
				<?endif?>
				<div class="clr"></div>
				<div class="catalog_btn-add-review_wrap">
					<a class="catalog_btn-add-review_link" id="catalog_btn-add-review" href="javascript:void(0)" rel="nofollow">
						<div class="sk_otz">Скидка 5% за отзыв на сайте</div>
					</a>
				</div>
			</div>
		</div>
		<div class="col__second">

			<div class="buy-zone">
				<?
				//ARTICLE_AND_RATING//
				include_once(__DIR__."/include/item_article_rating.php");
				//DETAIL_PREVIEW_TEXT//
				include_once(__DIR__."/include/item_teaser.php");
				//DETAIL_GIFT//	
				include_once(__DIR__."/include/item_gift.php");
				//OFFERS_SELECT_PROPS//
				include_once(__DIR__."/include/item_offers_select_props.php");
				//DETAIL_ADVANTAGES//
				include_once(__DIR__."/include/item_advantages.php");
				// PRICE_BUY//
				include_once(__DIR__."/include/item_price_buy.php"); 
				?>
			</div>
			<?//OFFERS_DETAIL_PROPERTIES//
			include_once(__DIR__."/include/item_detail_prop.php"); 
			?>
		</div>
	</div>

	<div class="ilink"><a href="<?=$arResult['DETAIL_PAGE_URL']?>">Смотреть полную информацию о товаре</a></div>


	<div class="clr"></div>
</div>

<?if(isset($arResult["OFFERS"]) && !empty($arResult["OFFERS"])) {
	$arJSParams = array(
		"CONFIG" => array(
			"USE_CATALOG" => $arResult["CATALOG"],
		),
		"PRODUCT_TYPE" => $arResult["CATALOG_TYPE"],
		"VISUAL" => array(
			"ID" => $arItemIDs["ID"],
			"PICT_ID" => $arItemIDs["PICT"],
			"PRICE_ID" => $arItemIDs["PRICE"],
			"BUY_ID" => $arItemIDs["BUY"],
			"DELAY_ID" => $arItemIDs["DELAY"],
			"DELIVERY_ID" => $arItemIDs["DELIVERY"],
			"ARTICLE_ID" => $arItemIDs["ARTICLE"],
			"PROPERTIES_ID" => $arItemIDs["PROPERTIES"],
			"CONSTRUCTOR_ID" => $arItemIDs["CONSTRUCTOR"],
			"STORE_ID" => $arItemIDs["STORE"],
			"TREE_ID" => $arItemIDs["PROP_DIV"],
			"TREE_ITEM_ID" => $arItemIDs["PROP"],
		),
		"PRODUCT" => array(
			"ID" => $arResult["ID"],
			"NAME" => $arResult["~NAME"],
			"PICT" => is_array($arResult["PREVIEW_IMG"]) ? $arResult["PREVIEW_IMG"] : array("SRC" => SITE_TEMPLATE_PATH."/images/no-photo.jpg", "WIDTH" => 150, "HEIGHT" => 150),
		),
		"OFFERS_VIEW" => $arSetting["OFFERS_VIEW"]["VALUE"],
		"OFFERS" => $arResult["JS_OFFERS"],
		"OFFER_SELECTED" => $arResult["OFFERS_SELECTED"],
		"TREE_PROPS" => $arSkuProps
	);	
} else {
	$arJSParams = array(
		"CONFIG" => array(
			"USE_CATALOG" => $arResult["CATALOG"]
		),
		"PRODUCT_TYPE" => $arResult["CATALOG_TYPE"],	
		"VISUAL" => array(
			"ID" => $arItemIDs["ID"],
			"BTN_BUY_ID" => $arItemIDs["BTN_BUY"],
		),
		"PRODUCT" => array(
			"ID" => $arResult["ID"],
			"NAME" => $arResult["~NAME"],
			"PICT" => is_array($arResult["PREVIEW_IMG"]) ? $arResult["PREVIEW_IMG"] : array("SRC" => SITE_TEMPLATE_PATH."/images/no-photo.jpg", "WIDTH" => 150, "HEIGHT" => 150),
		)
	);	
}

if(isset($arResult["SELECT_PROPS"]) && !empty($arResult["SELECT_PROPS"])) {
	$arJSParams["VISUAL"]["SELECT_PROP_ID"] = $arItemIDs["SELECT_PROP_DIV"];
	$arJSParams["VISUAL"]["SELECT_PROP_ITEM_ID"] = $arItemIDs["SELECT_PROP"];
	$arJSParams["SELECT_PROPS"] = $arSelProps;
}?>

<script type="text/javascript">
	var <?=$strObName;?> = new JCCatalogElement(<?=CUtil::PhpToJSObject($arJSParams, false, true);?>);	
	BX.message({			
		DETAIL_ADDITEMINCART_ADDED: "<?=GetMessageJS('CATALOG_ELEMENT_ADDED')?>",
		DETAIL_POPUP_WINDOW_TITLE: "<?=GetMessageJS('CATALOG_ELEMENT_ADDITEMINCART_TITLE')?>",			
		DETAIL_POPUP_WINDOW_BTN_CLOSE: "<?=GetMessageJS('CATALOG_ELEMENT_ADDITEMINCART_BTN_CLOSE')?>",
		DETAIL_POPUP_WINDOW_BTN_ORDER: "<?=GetMessageJS('CATALOG_ELEMENT_ADDITEMINCART_BTN_ORDER')?>",
		DETAIL_SITE_ID: "<?=SITE_ID;?>",
		DETAIL_SITE_DIR: "<?=SITE_DIR?>"
	});

	//SHOW_DETAIL_PROPERTY_FILTER_HINT//
	if(!window.showDetailPropertyFilterHint) {
		function showDetailPropertyFilterHint(target, hint) {		
			BX.DetailPropertyFilterHint = {
				popup: null
			};
			BX.DetailPropertyFilterHint.popup = BX.PopupWindowManager.create("detailPropertyFilterHint", null, {
				autoHide: true,
				offsetLeft: 0,
				offsetTop: 0,
				overlay: {
					opacity: 100
				},
				draggable: false,
				closeByEsc: false,
				closeIcon: { right : "-10px", top : "-10px"},			
				titleBar: false
			});
			BX.DetailPropertyFilterHint.popup.setContent(hint);
			
			BX.addClass(BX("detailPropertyFilterHint"), "pop-up filter-hint");
			close = BX.findChildren(BX("detailPropertyFilterHint"), {className: "popup-window-close-icon"}, true);
			if(!!close && 0 < close.length) {
				for(i = 0; i < close.length; i++) {					
					close[i].innerHTML = "<i class='fa fa-times'></i>";
				}
			}

			BX.remove(BX("popup-window-overlay-detailPropertyFilterHint"));
			target.parentNode.appendChild(BX("detailPropertyFilterHint"));
			
			BX.DetailPropertyFilterHint.popup.show();
		}
	}
</script>

<script type="text/javascript">

	function getParam(selector) {
		return new Promise(resolve => {
			let i = 0
			const id = setInterval(() => {
				const result = document.querySelector(selector).clientHeight
				i += 1
				if (i === 25 || result > 0) { 
					clearInterval(id)
					resolve(result)
				}
			}, 100)
		})
	}
	
	Promise.all([getParam('#fancybox-inner'), getParam('.quick-view'), getParam('.quick-view .h1')])
		.then(([inner, content, title]) => {
			let requiredSize = inner - 60
			requiredSize = (title > 23) ? requiredSize - (title - 23) : requiredSize

			if (content > inner) {
				document.querySelector('.quick-view .col__first').style.height = `${requiredSize}px`
			}

			document.querySelectorAll('.catalog-detail-property').forEach((prop, i) => {
				if((prop.offsetTop + prop.offsetHeight + 60 > inner)) {
					prop.style.display = 'none'
				}
			})
		})

</script>