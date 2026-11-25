<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

global $arSetting;

//JS//?>
<script type="text/javascript">
	//<![CDATA[
	$(function() {
		$(".search_close").click(function() {
			$(".title-search-result").fadeOut(300);
		});
		$(this).keydown(function(eventObject) {
			if(eventObject.which == 27)
				$(".title-search-result").fadeOut(300);
		});
	});
	//]]>
</script>

<?//CATALOG_SEARCH//
if(!empty($arResult["CATEGORIES"])):?>
	<a href="javascript:void(0)" class="search_close"><i class="fa fa-times"></i></a>		
	<div id="catalog_search">
		<?foreach($arResult["CATEGORIES"] as $category_id => $arCategory):
			foreach($arCategory["ITEMS"] as $i => $arElement):
				$strMainID = $this->GetEditAreaId($arElement["ITEM_ID"]);
				$arItemIDs = array(
					"ID" => $strMainID,
					"BTN_BUY" => $strMainID."_btn_buy"
				);						
				if($category_id === "all"):
					//SEARCH_ALL//
					if($arParams["SHOW_ALL_RESULTS"]=="Y"):?>
						<a class="search_all" href="<?=$arElement['URL']?>"><?=$arElement["NAME"]?></a>
					<?endif;
				elseif(isset($arElement["ICON"])):
					//SEARCH_ITEM//?>
					<div class="tvr_search">						
						<?//ITEM_PREVIEW_PICTURE//?>
						<a class="image" href="<?=$arElement['URL']?>">
							<?if(is_array($arElement["PREVIEW_PICTURE"])):?>
								<img src="<?=$arElement['PREVIEW_PICTURE']['SRC']?>" width="<?=$arElement['PREVIEW_PICTURE']['WIDTH']?>" height="<?=$arElement['PREVIEW_PICTURE']['HEIGHT']?>" alt="<?=$arElement['NAME']?>" title="<?=$arElement['NAME']?>" />
							<?else:?>
								<img src="<?=SITE_TEMPLATE_PATH?>/images/no-photo.jpg" width="62" height="62" alt="<?=$arElement['NAME']?>" title="<?=$arElement['NAME']?>" />
							<?endif;?>
						</a>
						<div class="<?if(!empty($arElement['PRICES']) || !empty($arElement['TOTAL_OFFERS']['MIN_PRICE'])): echo 'item_'; else: echo 'cat_'; endif;?>title">
							<?//ITEM_ARTICLE//
							if(!empty($arElement["PROPERTIES"]["ARTNUMBER"]["VALUE"])):?>
								<span class="article"><?=GetMessage("CATALOG_ELEMENT_ARTNUMBER").$arElement["PROPERTIES"]["ARTNUMBER"]["VALUE"];?></span>
							<?endif;
							//ITEM_TITLE//?>
							<a href="<?=$arElement['URL']?>"><?=$arElement["NAME"]?></a>

							<?// DT DISCONTINUED STATUS
							if (!empty($arElement["DT_DISCONTINUED"])):?>
								<div class="dt-discontinued-status">
									<strong>Снят с производства!</strong>
								</div>
							<?endif;
							// END DT DISCONTINUED STATUS

							//ITEM_PROPERTIES//
							if(!empty($arElement["DISPLAY_PROPERTIES"])):?>
								<div class="properties">
									<?foreach($arElement["DISPLAY_PROPERTIES"] as $k => $v):?>
										<span class="property"><?=$v["NAME"].": ".strip_tags($v["DISPLAY_VALUE"])?></span>
									<?endforeach;?>
								</div>
							<?endif;?>
						</div>						
						<?//TOTAL_OFFERS_ITEM_PRICE//
						if($arParams["SHOW_PRICE"]=="Y"):
							//TOTAL_OFFERS_PRICE//
							if(isset($arElement["OFFERS"]) && !empty($arElement["OFFERS"])):
								$price = CCurrencyLang::GetCurrencyFormat($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CURRENCY"], "ru");
								if(empty($price["THOUSANDS_SEP"])):
									$price["THOUSANDS_SEP"] = " ";
								endif;
								$price["REFERENCE_DECIMALS"] = $price["DECIMALS"];
								if($price["HIDE_ZERO"] == "Y"):
									if($arSetting["REFERENCE_PRICE"]["VALUE"] == "Y" && !empty($arSetting["REFERENCE_PRICE_COEF"]["VALUE"])):
										if(round($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"] * $arSetting["REFERENCE_PRICE_COEF"]["VALUE"], $price["DECIMALS"]) == round($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"] * $arSetting["REFERENCE_PRICE_COEF"]["VALUE"], 0)):
											$price["REFERENCE_DECIMALS"] = 0;
										endif;
									endif;
									if(round($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"], $price["DECIMALS"]) == round($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"], 0)):
										$price["DECIMALS"] = 0;
									endif;
								endif;
								$currency = str_replace("# ", " ", $price["FORMAT_STRING"]);?>

								<div class="search_price">
									<?if($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"] <= 0):?>
										<span class="no-price">											
											<span class="unit">
												<?=GetMessage("CATALOG_ELEMENT_NO_PRICE")?>
												<br />
												<span><?=(!empty($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_NAME"])) ? GetMessage("CATALOG_ELEMENT_UNIT")." ".$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_NAME"] : "";?></span>
											</span>
										</span>													
									<?else:?>										
										<span class="price">
											<?=($arElement["TOTAL_OFFERS"]["FROM"] == "Y") ? "<span class='from'>".GetMessage("CATALOG_ELEMENT_FROM")."</span>" : "";?>
											<?=number_format($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"], $price["DECIMALS"], $price["DEC_POINT"], $price["THOUSANDS_SEP"]);
											if($arSetting["REFERENCE_PRICE"]["VALUE"] == "Y" && !empty($arSetting["REFERENCE_PRICE_COEF"]["VALUE"])):?>
												<span class="price-reference">
													<?=number_format($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"] * $arSetting["REFERENCE_PRICE_COEF"]["VALUE"], $price["REFERENCE_DECIMALS"], $price["DEC_POINT"], $price["THOUSANDS_SEP"]);?>
												</span>
											<?endif;?>
											<span class="unit">												
												<?=$currency?>
												<span><?=(!empty($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_NAME"])) ? GetMessage("CATALOG_ELEMENT_UNIT")." ".$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_NAME"] : "";?></span>
											</span>											
										</span>									
									<?endif;?>
								</div>
							<?//ITEM_PRICE//
							else:
								foreach($arElement["PRICES"] as $code=>$arPrice):
									if($arPrice["MIN_PRICE"] == "Y"):
										if($arPrice["CAN_ACCESS"]):
											
											$price = CCurrencyLang::GetCurrencyFormat($arPrice["CURRENCY"], "ru");
											if(empty($price["THOUSANDS_SEP"])):
												$price["THOUSANDS_SEP"] = " ";
											endif;
											$price["REFERENCE_DECIMALS"] = $price["DECIMALS"];
											if($price["HIDE_ZERO"] == "Y"):
												if($arSetting["REFERENCE_PRICE"]["VALUE"] == "Y" && !empty($arSetting["REFERENCE_PRICE_COEF"]["VALUE"])):
													if(round($arPrice["DISCOUNT_VALUE"] * $arSetting["REFERENCE_PRICE_COEF"]["VALUE"], $price["DECIMALS"]) == round($arPrice["DISCOUNT_VALUE"] * $arSetting["REFERENCE_PRICE_COEF"]["VALUE"], 0)):
														$price["REFERENCE_DECIMALS"] = 0;													
													endif;
												endif;
												if(round($arPrice["DISCOUNT_VALUE"], $price["DECIMALS"]) == round($arPrice["DISCOUNT_VALUE"], 0)):
													$price["DECIMALS"] = 0;
												endif;
											endif;
											$currency = str_replace("# ", " ", $price["FORMAT_STRING"]);?>

											<div class="search_price">
												<?if($arPrice["DISCOUNT_VALUE"] <= 0):?>
													<span class="no-price">
														<span class="unit">
															<?=GetMessage("CATALOG_ELEMENT_NO_PRICE")?>
															<br />
															<span><?=(!empty($arElement["CATALOG_MEASURE_NAME"])) ? GetMessage("CATALOG_ELEMENT_UNIT")." ".$arElement["CATALOG_MEASURE_NAME"] : "";?></span>
														</span>
													</span>																
												<?else:?>													
													<span class="price">
														<?=number_format($arPrice["DISCOUNT_VALUE"], $price["DECIMALS"], $price["DEC_POINT"], $price["THOUSANDS_SEP"]);
														if($arSetting["REFERENCE_PRICE"]["VALUE"] == "Y" && !empty($arSetting["REFERENCE_PRICE_COEF"]["VALUE"])):?>
															<span class="price-reference">
																<?=number_format($arPrice["DISCOUNT_VALUE"] * $arSetting["REFERENCE_PRICE_COEF"]["VALUE"], $price["REFERENCE_DECIMALS"], $price["DEC_POINT"], $price["THOUSANDS_SEP"]);?>
															</span>
														<?endif;?>
														<span class="unit">
															<?=$currency?>
															<span><?=(!empty($arElement["CATALOG_MEASURE_NAME"])) ? GetMessage("CATALOG_ELEMENT_UNIT")." ".$arElement["CATALOG_MEASURE_NAME"] : "";?></span>
														</span>														
													</span>
												<?endif;?>
											</div>
										
										<?endif;
									endif;
								endforeach;
							endif;
						endif;						
						//OFFERS_ITEM_BUY//
						if (!empty($arElement["DT_DISCONTINUED_NO_STOCK"])):?>
							<div class="buy_more">
								<div class="not_avl">
									<i class="fa fa-times-circle"></i>
									<span>Нет в наличии</span>
								</div>
							</div>
						<?elseif($arParams["SHOW_ADD_TO_CART"]=="Y"):
							//OFFERS_BUY//
							if(isset($arElement["OFFERS"]) && !empty($arElement["OFFERS"])):?>
								<div class="buy_more">
									<div class="add2basket_block">
										<form action="<?=$APPLICATION->GetCurPage()?>" class="add2basket_form">
											<a href="javascript:void(0)" class="minus" onclick="if (BX('quantity_<?=$arItemIDs["ID"]?>').value > <?=$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_RATIO"]?>) BX('quantity_<?=$arItemIDs["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs["ID"]?>').value)-<?=$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_RATIO"]?>;"><span>-</span></a>
											<input type="text" id="quantity_<?=$arItemIDs['ID']?>" name="quantity" class="quantity" value="<?=$arElement['TOTAL_OFFERS']['MIN_PRICE']['CATALOG_MEASURE_RATIO']?>"/>
											<a href="javascript:void(0)" class="plus" onclick="BX('quantity_<?=$arItemIDs["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs["ID"]?>').value)+<?=$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_RATIO"]?>;"><span>+</span></a>			
											<button type="button" class="btn_buy" name="add2basket" value="<?=GetMessage('CATALOG_ELEMENT_ADD_TO_CART')?>" onclick="OpenPropsPopupSearch('<?=$arItemIDs["ID"]?>'<?=($arSetting["OFFERS_VIEW"]["VALUE"] == "LIST" ? ", true" : "");?>);"><i class="fa fa-shopping-cart"></i></button>
										</form>
									</div>
								</div>
							<?//ITEM_BUY//
							else:
								if($arElement["CAN_BUY"]):?>
									<div class="buy_more">
										<div class="add2basket_block">
											<?if($arElement["MIN_PRICE"]["DISCOUNT_VALUE"] <= 0):
												//ITEM_ASK_PRICE//?>
												<a class="btn_buy apuo" id="ask_price_anch_<?=$arItemIDs['ID']?>" href="javascript:void(0)" rel="nofollow"><i class="fa fa-comment-o"></i><span><?=GetMessage("CATALOG_ELEMENT_ASK_PRICE_SHORT")?></span></a>
											<?else:
												if(isset($arElement["SELECT_PROPS"]) && !empty($arElement["SELECT_PROPS"])):?>
													<form action="<?=$APPLICATION->GetCurPage()?>" class="add2basket_form">
												<?else:?>
													<form action="<?=SITE_DIR?>ajax/add2basket.php" class="add2basket_search_form">
												<?endif;?>
													<a href="javascript:void(0)" class="minus" onclick="if (BX('quantity_<?=$arItemIDs["ID"]?>').value > <?=$arElement["CATALOG_MEASURE_RATIO"]?>) BX('quantity_<?=$arItemIDs["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs["ID"]?>').value)-<?=$arElement["CATALOG_MEASURE_RATIO"]?>;"><span>-</span></a>
													<input type="text"
															id="quantity_<?=$arItemIDs['ID']?>"
															name="quantity"
															class="quantity"
															value="<?=$arElement['CATALOG_MEASURE_RATIO']?>"
															<?if (!empty($arElement["DT_DISCONTINUED"])):?>
																data-dt-max-quantity="<?=(float)$arElement['DT_STOCK_QUANTITY']?>"
																data-dt-quantity-step="<?=(float)$arElement['CATALOG_MEASURE_RATIO']?>"
															<?endif;?>
														/>
													<a href="javascript:void(0)" class="plus" onclick="BX('quantity_<?=$arItemIDs["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs["ID"]?>').value)+<?=$arElement["CATALOG_MEASURE_RATIO"]?>;"><span>+</span></a>
													<?if(!isset($arElement["SELECT_PROPS"]) || empty($arElement["SELECT_PROPS"])):?>
														<input type="hidden" name="ID" value="<?=$arElement['ITEM_ID']?>"/>
														<?$props = array();
														if(!empty($arElement["PROPERTIES"]["ARTNUMBER"]["VALUE"])):			
															$props[] = array(
																"NAME" => $arElement["PROPERTIES"]["ARTNUMBER"]["NAME"],
																"CODE" => $arElement["PROPERTIES"]["ARTNUMBER"]["CODE"],
																"VALUE" => $arElement["PROPERTIES"]["ARTNUMBER"]["VALUE"]
															);												
														endif;
														if(!empty($arElement["DISPLAY_PROPERTIES"])):										
															foreach($arElement["DISPLAY_PROPERTIES"] as $propOffer) {
																$props[] = array(
																	"NAME" => $propOffer["NAME"],
																	"CODE" => $propOffer["CODE"],
																	"VALUE" => strip_tags($propOffer["DISPLAY_VALUE"])
																);
															}
														endif;
														// DT SEARCH BASKET PROPERTIES ENCODING
														$props = !empty($props)
															? strtr(base64_encode(serialize($props)), '+/=', '-_,')
															: "";
														?>
														<input type="hidden" name="PROPS" value="<?=$props?>" />
													<?endif;?>															
													<button type="button" class="btn_buy" name="add2basket" value="<?=GetMessage('CATALOG_ELEMENT_ADD_TO_CART')?>"<?=(isset($arElement["SELECT_PROPS"]) && !empty($arElement["SELECT_PROPS"]) ? " onclick=\"OpenPropsPopupSearch('".$arItemIDs["ID"]."')\"" : " id='".$arItemIDs["BTN_BUY"]."'");?>><i class="fa fa-shopping-cart"></i></button>
												</form>
											<?endif;?>
										</div>
									</div>
								<?elseif(!$arElement["CAN_BUY"]):
									if(!empty($arElement["PRICES"])):?>
										<div class="buy_more">
											<div class="add2basket_block">
												<?//ITEM_UNDER_ORDER//?>
												<a class="btn_buy apuo" id="under_order_anch_<?=$arItemIDs['ID']?>" href="javascript:void(0)" rel="nofollow"><i class="fa fa-clock-o"></i><span><?=GetMessage("CATALOG_ELEMENT_UNDER_ORDER")?></span></a>
											</div>
										</div>
									<?endif;												
								endif;
							endif;
						endif;?>										
					</div>							
				<?endif;
			endforeach;
		endforeach;?>
	</div>

	<?foreach($arResult["CATEGORIES"] as $category_id => $arCategory):
		foreach($arCategory["ITEMS"] as $key => $arElement):
			// DT DISCONTINUED SKIP FORMS
			if (!empty($arElement["DT_DISCONTINUED_NO_STOCK"])):
				continue;
			endif;
			// END DT DISCONTINUED SKIP FORMS
			$strMainID = $this->GetEditAreaId($arElement["ITEM_ID"]);
			if(!isset($arElement["OFFERS"]) || (isset($arElement["OFFERS"]) && empty($arElement["OFFERS"]))):
				if($arElement["CAN_BUY"] && $arElement["MIN_PRICE"]["DISCOUNT_VALUE"] <= 0):
					//ASK_PRICE//
					global $arAskPriceFilter;
					$arAskPriceFilter = array(
						"ELEMENT_ID" => $arElement["ITEM_ID"],
						"ELEMENT_AREA_ID" => $strMainID,
						"ELEMENT_NAME" => $arElement["NAME"],
						"BUTTON_ID" => "ask_price_anch_".$strMainID,
						"HIDE_ICONS" => "Y"
					);
					$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/form_ask_price.php"), false, array("HIDE_ICONS" => "Y"));
				elseif(!$arElement["CAN_BUY"]):
					//UNDER_ORDER//
					global $arUnderOrderFilter;
					$arUnderOrderFilter = array(
						"ELEMENT_ID" => $arElement["ITEM_ID"],
						"ELEMENT_AREA_ID" => $strMainID,
						"ELEMENT_NAME" => $arElement["NAME"],
						"BUTTON_ID" => "under_order_anch_".$strMainID,
						"HIDE_ICONS" => "Y"
					);
					$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/form_under_order.php"), false, array("HIDE_ICONS" => "Y"));
				endif;
			endif;
		endforeach;
	endforeach;
	
	//POPUP_JS//	
	$popupParams["MESS"] = array(	
		"CATALOG_ELEMENT_ARTNUMBER" => GetMessage("CATALOG_ELEMENT_ARTNUMBER"),
		"CATALOG_ELEMENT_NO_PRICE" => GetMessage("CATALOG_ELEMENT_NO_PRICE"),
		"CATALOG_ELEMENT_SKIDKA" => GetMessage("CATALOG_ELEMENT_SKIDKA"),
		"CATALOG_ELEMENT_UNIT" => GetMessage("CATALOG_ELEMENT_UNIT"),
		"CATALOG_ELEMENT_AVAILABLE" => GetMessage("CATALOG_ELEMENT_AVAILABLE"),
		"CATALOG_ELEMENT_NOT_AVAILABLE" => GetMessage("CATALOG_ELEMENT_NOT_AVAILABLE"),
		"CATALOG_ELEMENT_ADD_TO_CART" => GetMessage("CATALOG_ELEMENT_ADD_TO_CART"),
		"CATALOG_ELEMENT_ADDED" => GetMessage("CATALOG_ELEMENT_ADDED"),
		"CATALOG_ELEMENT_ASK_PRICE_FULL" => GetMessage("CATALOG_ELEMENT_ASK_PRICE_FULL"),
		"CATALOG_ELEMENT_ASK_PRICE_SHORT" => GetMessage("CATALOG_ELEMENT_ASK_PRICE_SHORT"),
		"CATALOG_ELEMENT_UNDER_ORDER" => GetMessage("CATALOG_ELEMENT_UNDER_ORDER"),									
		"CATALOG_ELEMENT_OFFERS_LIST" => GetMessage("CATALOG_ELEMENT_OFFERS_LIST"),
		"CATALOG_ELEMENT_OFFERS_LIST_IMAGE" => GetMessage("CATALOG_ELEMENT_OFFERS_LIST_IMAGE"),
		"CATALOG_ELEMENT_OFFERS_LIST_NAME" => GetMessage("CATALOG_ELEMENT_OFFERS_LIST_NAME"),
		"CATALOG_ELEMENT_OFFERS_LIST_PRICE" => GetMessage("CATALOG_ELEMENT_OFFERS_LIST_PRICE"),
		"CATALOG_ELEMENT_BOC_SHORT" => GetMessage("CATALOG_ELEMENT_BOC_SHORT")
	);	
	$popupParams["SKU_PROPS"] = strtr(base64_encode(addslashes(gzcompress(serialize($arResult["SKU_PROPS"]),9))), '+/=', '-_,');	
	$popupParams["PARAMS"] = strtr(base64_encode(addslashes(gzcompress(serialize($arParams),9))), '+/=', '-_,');	
	$popupParams["SETTINGS"] = strtr(base64_encode(addslashes(gzcompress(serialize($arSetting),9))), '+/=', '-_,');	
	foreach($arResult["CATEGORIES"] as $category_id => $arCategory):
		foreach($arCategory["ITEMS"] as $key => $arElement):
			// DT DISCONTINUED SKIP PURCHASE JS
			if (!empty($arElement["DT_DISCONTINUED_NO_STOCK"])) {
				continue;
			}
			// END DT DISCONTINUED SKIP PURCHASE JS
			$strMainID = $this->GetEditAreaId($arElement["ITEM_ID"]);
			$arItemIDs = array(
				"ID" => $strMainID,				
				"BTN_BUY" => $strMainID."_btn_buy"
			);
			$strObName = "ob".preg_replace("/[^a-zA-Z0-9_]/", "x", $strMainID);			
			if($arElement["OFFERS"] || $arElement["SELECT_PROPS"]):				
				//POPUP//
				$popupParams["STR_MAIN_ID"] = $strMainID;
				$popupParams["ELEMENT"] = strtr(
					base64_encode(
						addslashes(
							gzcompress(serialize($arElement), 9)
						)
					),
					'+/=',
					'-_,'
				);

				// DT SEARCH POPUP SIGNATURE
				$dtPopupPayload = array(
					"EXPIRES" => time() + 1800,
					"SESSION" => bitrix_sessid(),
					"PARAMS" => $popupParams
				);

				$dtPopupSigner =
					new \Bitrix\Main\Security\Sign\Signer();

				$dtSignedPopup = $dtPopupSigner->sign(
					base64_encode(serialize($dtPopupPayload)),
					"dt.search.title.popup"
				);

				$dtPopupClientParams = array(
					"token" => $dtSignedPopup
				);
				// END DT SEARCH POPUP SIGNATURE
				?>
				<script type="text/javascript">
					if(!window.arSetParams) {
						window.arSetParams = [{'<?=$arItemIDs["ID"]?>' : <?=CUtil::PhpToJSObject($dtPopupClientParams)?>}];
					} else {
						window.arSetParams.push({'<?=$arItemIDs["ID"]?>' : <?=CUtil::PhpToJSObject($dtPopupClientParams)?>});
					}
				</script>
			<?else:
				//JS//
				$arJSParams = array(
					"PRODUCT_TYPE" => $arElement["CATALOG_TYPE"],
					"VISUAL" => array(
						"ID" => $arItemIDs["ID"],
						"BTN_BUY_ID" => $arItemIDs["BTN_BUY"],
					),
					"PRODUCT" => array(
						"ID" => $arElement["ITEM_ID"],
						"NAME" => $arElement["NAME"],
						"PICT" => is_array($arElement["PREVIEW_PICTURE"]) ? $arElement["PREVIEW_PICTURE"] : array("SRC" => SITE_TEMPLATE_PATH."/images/no-photo.jpg", "WIDTH" => 150, "HEIGHT" => 150),
					)
				);?>
				<script type="text/javascript">
					var <?=$strObName;?> = new JCCatalogSectionSearch(<?=CUtil::PhpToJSObject($arJSParams, false, true);?>);
				</script>			
			<?endif;
		endforeach;
	endforeach;

	//JS//?>	
	<script type="text/javascript">
		BX.message({			
			SEARCH_ADDITEMINCART_ADDED: "<?=GetMessageJS('CATALOG_ELEMENT_ADDED')?>",
			SEARCH_POPUP_WINDOW_TITLE: "<?=GetMessageJS('CATALOG_ELEMENT_ADDITEMINCART_TITLE')?>",			
			SEARCH_POPUP_WINDOW_BTN_CLOSE: "<?=GetMessageJS('CATALOG_ELEMENT_ADDITEMINCART_BTN_CLOSE')?>",
			SEARCH_POPUP_WINDOW_BTN_ORDER: "<?=GetMessageJS('CATALOG_ELEMENT_ADDITEMINCART_BTN_ORDER')?>",
			SEARCH_SITE_DIR: "<?=SITE_DIR?>"
		});
		if(!window.OpenPropsPopupSearch) {
			function OpenPropsPopupSearch(visual_id, offers_list) {
				offers_list = offers_list || false;

				if(window.arSetParams) {
					for(var obj in window.arSetParams) {
						if(window.arSetParams.hasOwnProperty(obj)) {
							for(var obj2 in window.arSetParams[obj]) {
								if(window.arSetParams[obj].hasOwnProperty(obj2)) {
									if(obj2 == visual_id)
										var curSetParams = window.arSetParams[obj][obj2]
								}
							}
						}
					}
				}
				BX.PropsSet =
				{			
					popup: null,
					arParams: {}
				};
				BX.PropsSet.popup = BX.PopupWindowManager.create(visual_id, null, {
					autoHide: offers_list == true ? false : true,
					offsetLeft: 0,
					offsetTop: 0,
					overlay: {
						opacity: 100
					},
					draggable: false,
					closeByEsc: false,
					closeIcon: { right : "-10px", top : "-10px"},
					titleBar: {content: BX.create("span", {html: "<?=GetMessage('CATALOG_ELEMENT_MORE_OPTIONS')?>"})},
					content: "<div class='popup-window-wait'><i class='fa fa-spinner fa-pulse'></i></div>",
					events: {
						onAfterPopupShow: function()
						{													
							if(!BX(visual_id + "_info")) {
								BX.ajax.post(
									"<?=SITE_DIR?>ajax/search_title_popup.php",
									{
										token: curSetParams.token,
										sessid: BX.bitrix_sessid()
									},
									BX.delegate(function(result)
									{
										this.setContent(result);
										var windowSize =  BX.GetWindowInnerSize(),
										windowScroll = BX.GetWindowScrollPos(),
										popupHeight = BX(visual_id).offsetHeight;
										BX(visual_id).style.top = windowSize.innerHeight/2 - popupHeight/2 + windowScroll.scrollTop + "px";
									},
									this)
								);
							} else {
								qntItems = BX.findChildren(BX(visual_id), {className: "quantity"}, true);
								if(!!qntItems && 0 < qntItems.length) {
									for(i = 0; i < qntItems.length; i++) {					
										// DT POPUP QUANTITY REOPEN
										var dtSourceQuantity = BX("quantity_" + visual_id);

										if (dtSourceQuantity) {

											qntItems[i].value = dtSourceQuantity.value;

											if (qntItems[i].hasAttribute('data-dt-max-quantity')) {
												qntItems[i].dispatchEvent(
													new Event('change', {
														bubbles: true
													})
												);
											}
										}
										// END DT POPUP QUANTITY REOPEN
									}
								}
							}
						}
					}
				});			
				BX.addClass(BX(visual_id), "pop-up more_options");
				if(offers_list == true) {
					BX.addClass(BX(visual_id), "offers-list");
				}
				close = BX.findChildren(BX(visual_id), {className: "popup-window-close-icon"}, true);
				if(!!close && 0 < close.length) {
					for(i = 0; i < close.length; i++) {					
						close[i].innerHTML = "<i class='fa fa-times'></i>";
					}
				}
				BX.PropsSet.popup.show();
			}
		}
	</script>
<?else:?>
	<a href="javascript:void(0)" class="pop-up-close search_close"><i class="fa fa-times"></i></a>	
	<div id="catalog_search_empty"><?=GetMessage("CATALOG_EMPTY_RESULT")?></div>			
<?endif;?>