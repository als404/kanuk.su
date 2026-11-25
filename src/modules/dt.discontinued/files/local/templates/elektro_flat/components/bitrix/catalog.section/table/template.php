<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

if(count($arResult["ITEMS"]) < 1):
	echo "<br />";
	ShowNote(GetMessage("CATALOG_EMPTY_RESULT"));
	return;
endif;

global $arSetting;

//JS//?>
<script type="text/javascript">
	//<![CDATA[
	BX.ready(function() {
		//ITEMS_HEIGHT//
		var itemsTable = $(".catalog-item-table-view .catalog-item-card");
		if(!!itemsTable && itemsTable.length > 0) {
			$(window).resize(function() {
				adjustItemHeight(itemsTable);
			});
			adjustItemHeight(itemsTable);
		}
	});
	//]]>
</script>

<?//CATALOG//?>
<div id="catalog" itemtype="http://schema.org/ItemList">
	<link href="<?=$APPLICATION->GetCurPage()?>" itemprop="url" />
	<div class="catalog-item-table-view">
		<?foreach($arResult["ITEMS"] as $key => $arElement):
		// DT_NATIVE_LAZYLOAD
			$isPriorityImage = ($key < 8);
			$imageLoading = $isPriorityImage ? 'eager' : 'lazy';	
			$strMainID = $this->GetEditAreaId($arElement["ID"]);
			$arItemIDs = array(
				"ID" => $strMainID,
				"BTN_BUY" => $strMainID."_btn_buy"
			);
			
			//NEW_HIT_DISCOUNT_TIME_BUY//
			$sticker = "";
			$timeBuy = "";
			$class = "";
			if(array_key_exists("PROPERTIES", $arElement) && is_array($arElement["PROPERTIES"])):
				//NEW//
				if(array_key_exists("NEWPRODUCT", $arElement["PROPERTIES"]) && !$arElement["PROPERTIES"]["NEWPRODUCT"]["VALUE"] == false):
					$sticker .= "<span class='new'>".GetMessage("CATALOG_ELEMENT_NEWPRODUCT")."</span>";
				endif;
				//HIT//
				if(array_key_exists("SALELEADER", $arElement["PROPERTIES"]) && !$arElement["PROPERTIES"]["SALELEADER"]["VALUE"] == false):
					$sticker .= "<span class='hit'>".GetMessage("CATALOG_ELEMENT_SALELEADER")."</span>";
				endif;
				//DISCOUNT//				
				if(isset($arElement["OFFERS"]) && !empty($arElement["OFFERS"])):						
					if($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_DIFF_PERCENT"] > 0):
						$sticker .= "<span class='discount'>-".$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_DIFF_PERCENT"]."%</span>";
					else:
						if(array_key_exists("DISCOUNT", $arElement["PROPERTIES"]) && !$arElement["PROPERTIES"]["DISCOUNT"]["VALUE"] == false):
							$sticker .= "<span class='discount'>%</span>";
						endif;
					endif;
				else:
					if($arElement["MIN_PRICE"]["DISCOUNT_DIFF_PERCENT"] > 0):
						$sticker .= "<span class='discount'>-".$arElement["MIN_PRICE"]["DISCOUNT_DIFF_PERCENT"]."%</span>";
					else:
						if(array_key_exists("DISCOUNT", $arElement["PROPERTIES"]) && !$arElement["PROPERTIES"]["DISCOUNT"]["VALUE"] == false):
							$sticker .= "<span class='discount'>%</span>";
						endif;
					endif;
				endif;
				//TIME_BUY//
				if(array_key_exists("TIME_BUY", $arElement["PROPERTIES"]) && !$arElement["PROPERTIES"]["TIME_BUY"]["VALUE"] == false):
					if(!empty($arElement["CURRENT_DISCOUNT"]["ACTIVE_TO"])):						
						if(isset($arElement["OFFERS"]) && !empty($arElement["OFFERS"])):
							$class = " item-tb";
							$timeBuy = "<div class='time_buy_sticker'><span class='time_buy_figure'></span><span class='time_buy_text'>".GetMessage("CATALOG_ELEMENT_TIME_BUY")."</span></div>";
						else:
							if($arElement["CAN_BUY"]):
								$class = " item-tb";
								$timeBuy = "<div class='time_buy_sticker'><span class='time_buy_figure'></span><span class='time_buy_text'>".GetMessage("CATALOG_ELEMENT_TIME_BUY")."</span></div>";
							endif;
						endif;
					endif;
				endif;
			endif;
			
			//PREVIEW_PICTURE_ALT//
			$strAlt = (isset($arElement["IPROPERTY_VALUES"]["ELEMENT_PREVIEW_PICTURE_FILE_ALT"]) && $arElement["IPROPERTY_VALUES"]["ELEMENT_PREVIEW_PICTURE_FILE_ALT"] != "" ? $arElement["IPROPERTY_VALUES"]["ELEMENT_PREVIEW_PICTURE_FILE_ALT"] : $arElement["NAME"]);

			//PREVIEW_PICTURE_TITLE//
			$strTitle = (isset($arElement["IPROPERTY_VALUES"]["ELEMENT_PREVIEW_PICTURE_FILE_TITLE"]) && $arElement["IPROPERTY_VALUES"]["ELEMENT_PREVIEW_PICTURE_FILE_TITLE"] != "" ? $arElement["IPROPERTY_VALUES"]["ELEMENT_PREVIEW_PICTURE_FILE_TITLE"] : $arElement["NAME"]);
			
			//ITEM//?>				
			<div class="catalog-item-card<?=$class?>" itemprop="itemListElement" itemscope="" itemtype="http://schema.org/Product">
				<div class="catalog-item-info">							
					<?//ITEM_PREVIEW_PICTURE//?>
					<div class="item-image-cont">
						<div class="item-image<?if(!empty($arElement["DT_DISCONTINUED"])):?> dt-discontinued-image<?endif;?>">

						<?if(!empty($arElement["DT_DISCONTINUED"])):?>
							<span class="dt-discontinued-badge">
								Снят с производства!
							</span>
						<?endif;?>							
							<meta content="<?=(is_array($arElement['PREVIEW_PICTURE']) ? $arElement['PREVIEW_PICTURE']['SRC'] : SITE_TEMPLATE_PATH.'/images/no-photo.jpg');?>" itemprop="image" />
							<a href="<?=$arElement['DETAIL_PAGE_URL']?>">
								<?if(is_array($arElement["PREVIEW_PICTURE"])):?>
									<img class="item_img" loading="<?=$imageLoading?>" decoding="async" src="<?=$arElement['PREVIEW_PICTURE']['SRC']?>" width="<?=$arElement['PREVIEW_PICTURE']['WIDTH']?>" height="<?=$arElement['PREVIEW_PICTURE']['HEIGHT']?>" alt="<?=$strAlt?>"  />
								<?else:?>
									<img class="item_img" loading="<?=$imageLoading?>" decoding="async" src="<?=SITE_TEMPLATE_PATH?>/images/no-photo.jpg" width="150" height="150" alt="<?=$strAlt?>"  />
								<?endif;?>
								<?=$timeBuy?>									
								<span class="sticker">
									<?=$sticker?>
								</span>
								<?if(is_array($arElement["PROPERTIES"]["MANUFACTURER"]["PREVIEW_PICTURE"])):?>
									<img class="manufacturer" loading="lazy" decoding="async" src="<?=$arElement['PROPERTIES']['MANUFACTURER']['PREVIEW_PICTURE']['SRC']?>" width="<?=$arElement['PROPERTIES']['MANUFACTURER']['PREVIEW_PICTURE']['WIDTH']?>" height="<?=$arElement['PROPERTIES']['MANUFACTURER']['PREVIEW_PICTURE']['HEIGHT']?>" alt="<?=$arElement['PROPERTIES']['MANUFACTURER']['NAME']?>" title="<?=$arElement['PROPERTIES']['MANUFACTURER']['NAME']?>" />
								<?endif;?>
							</a>							
						</div>
					</div>
					<?//ITEM_TITLE//?>
					<div class="item-all-title">
						<a class="item-title" href="<?=$arElement['DETAIL_PAGE_URL']?>"  itemprop="url">
							<span itemprop="name"><?=$arElement['NAME']?></span>
						</a>
					</div>
					<?//ARTICLE_RATING//
					if(in_array("ARTNUMBER", $arSetting["PRODUCT_TABLE_VIEW"]["VALUE"]) || in_array("RATING", $arSetting["PRODUCT_TABLE_VIEW"]["VALUE"])):?>
						<div class="article_rating">
							<?//ARTICLE//
							if(in_array("ARTNUMBER", $arSetting["PRODUCT_TABLE_VIEW"]["VALUE"])):?>
								<div class="article">
									<?=GetMessage("CATALOG_ELEMENT_ARTNUMBER")?><?=!empty($arElement["PROPERTIES"]["ARTNUMBER"]["VALUE"]) ? $arElement["PROPERTIES"]["ARTNUMBER"]["VALUE"] : "-";?>
								</div>
							<?endif;
							//RATING//
							if(in_array("RATING", $arSetting["PRODUCT_TABLE_VIEW"]["VALUE"])):?>
								<div class="rating">
									<?$APPLICATION->IncludeComponent("bitrix:iblock.vote", "ajax",
										Array(
											"DISPLAY_AS_RATING" => "vote_avg",
											"IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
											"IBLOCK_ID" => $arParams["IBLOCK_ID"],
											"ELEMENT_ID" => $arElement["ID"],
											"ELEMENT_CODE" => "",
											"MAX_VOTE" => "5",
											"VOTE_NAMES" => array("1","2","3","4","5"),
											"SET_STATUS_404" => "N",
											"CACHE_TYPE" => $arParams["CACHE_TYPE"],
											"CACHE_TIME" => $arParams["CACHE_TIME"],
											"CACHE_NOTES" => "",
											"READ_ONLY" => "Y"
										),
										false,
										array("HIDE_ICONS" => "Y")
									);?>
								</div>
							<?endif;?>
							<div class="clr"></div>
						</div>
					<?endif;
					//ITEM_PREVIEW_TEXT//
					if(in_array("PREVIEW_TEXT", $arSetting["PRODUCT_TABLE_VIEW"]["VALUE"])):?>
						<div class="item-desc" itemprop="description">
							<?=strip_tags($arElement["PREVIEW_TEXT"]);?>
						</div>
					<?endif;
					//TOTAL_OFFERS_ITEM_PRICE//?>
					<div class="item-price-cont<?=(!in_array('OLD_PRICE', $arSetting['PRODUCT_TABLE_VIEW']['VALUE']) && !in_array('PERCENT_PRICE', $arSetting['PRODUCT_TABLE_VIEW']['VALUE']) ? ' one' : '').((in_array('OLD_PRICE', $arSetting['PRODUCT_TABLE_VIEW']['VALUE']) && !in_array('PERCENT_PRICE', $arSetting['PRODUCT_TABLE_VIEW']['VALUE'])) || (!in_array('OLD_PRICE', $arSetting['PRODUCT_TABLE_VIEW']['VALUE']) && in_array('PERCENT_PRICE', $arSetting['PRODUCT_TABLE_VIEW']['VALUE'])) ? ' two' : '').($arSetting["REFERENCE_PRICE"]["VALUE"] == "Y" && !empty($arSetting["REFERENCE_PRICE_COEF"]["VALUE"]) ? ' reference' : '');?>" itemprop="offers" itemscope itemtype="http://schema.org/Offer">
						<?//TOTAL_OFFERS_PRICE//
						if(isset($arElement["OFFERS"]) && !empty($arElement["OFFERS"])):
							$price = CCurrencyLang::GetCurrencyFormat($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CURRENCY"], "ru");
							if(empty($price["THOUSANDS_SEP"])):
								$price["THOUSANDS_SEP"] = " ";
							endif;							
							if($price["HIDE_ZERO"] == "Y"):								
								if(round($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"], $price["DECIMALS"]) == round($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"], 0)):
									$price["DECIMALS"] = 0;
								endif;
							endif;
							$currency = str_replace("# ", " ", $price["FORMAT_STRING"]);
							
							if($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"] <= 0):?>							
								<div class="item-no-price">			
									<span class="unit">
										<?=GetMessage("CATALOG_ELEMENT_NO_PRICE")?>
										<span><?=(!empty($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_NAME"])) ? GetMessage("CATALOG_ELEMENT_UNIT")." ".$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_NAME"] : "";?></span>
									</span>									
								</div>
							<?else:?>
								<div class="item-price">
									<?if($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"] < $arElement["TOTAL_OFFERS"]["MIN_PRICE"]["VALUE"]):
										if(in_array("OLD_PRICE", $arSetting["PRODUCT_TABLE_VIEW"]["VALUE"])):?>
											<span class="catalog-item-price-old">
												<?=$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["PRINT_VALUE"];?>
											</span>
										<?endif;
										if(in_array("PERCENT_PRICE", $arSetting["PRODUCT_TABLE_VIEW"]["VALUE"])):?>
											<span class="catalog-item-price-percent">
												<?=GetMessage("CATALOG_ELEMENT_SKIDKA")." ".$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["PRINT_DISCOUNT_DIFF"];?>
											</span>
										<?endif;
									endif;?>
									<span class="catalog-item-price">
										<?=($arElement["TOTAL_OFFERS"]["FROM"] == "Y") ? "<span class='from'>".GetMessage("CATALOG_ELEMENT_FROM")."</span>" : "";?>	
										<?=number_format($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"], $price["DECIMALS"], $price["DEC_POINT"], $price["THOUSANDS_SEP"]);?>
										<span class="unit">
											<?=$currency?>
											<span><?=(!empty($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_NAME"])) ? GetMessage("CATALOG_ELEMENT_UNIT")." ".$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_NAME"] : "";?></span>
										</span>
									</span>
									<?if($arSetting["REFERENCE_PRICE"]["VALUE"] == "Y" && !empty($arSetting["REFERENCE_PRICE_COEF"]["VALUE"])):?>
										<span class="catalog-item-price-reference">
											<?=CCurrencyLang::CurrencyFormat($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"] * $arSetting["REFERENCE_PRICE_COEF"]["VALUE"], $arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CURRENCY"], true);?>
										</span>
									<?endif;?>
								</div>							
							<?endif;?>
							<meta itemprop="price" content="<?=$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"]?>" />
							<meta itemprop="priceCurrency" content="<?=$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CURRENCY"]?>" />
							<?if($arElement["TOTAL_OFFERS"]["QUANTITY"] > 0):?>							
								<meta content="InStock" itemprop="availability" />									
							<?else:?>
								<meta content="OutOfStock" itemprop="availability" />
							<?endif;
						//ITEM_PRICE//
						else:								
							foreach($arElement["PRICES"] as $code=>$arPrice):
								if($arPrice["MIN_PRICE"] == "Y"):
									if($arPrice["CAN_ACCESS"]):
										
										$price = CCurrencyLang::GetCurrencyFormat($arPrice["CURRENCY"], "ru");
										if(empty($price["THOUSANDS_SEP"])):
											$price["THOUSANDS_SEP"] = " ";
										endif;										
										if($price["HIDE_ZERO"] == "Y"):											
											if(round($arPrice["DISCOUNT_VALUE"], $price["DECIMALS"]) == round($arPrice["DISCOUNT_VALUE"], 0)):
												$price["DECIMALS"] = 0;
											endif;
										endif;
										$currency = str_replace("# ", " ", $price["FORMAT_STRING"]);

										if($arPrice["DISCOUNT_VALUE"] <= 0):?>
											<div class="item-no-price">	
												<span class="unit">
													<?=GetMessage("CATALOG_ELEMENT_NO_PRICE")?>
													<span><?=(!empty($arElement["CATALOG_MEASURE_NAME"])) ? GetMessage("CATALOG_ELEMENT_UNIT")." ".$arElement["CATALOG_MEASURE_NAME"] : "";?></span>
												</span>												
											</div>
										<?else:?>
											<div class="item-price">
												<?if($arPrice["DISCOUNT_VALUE"] < $arPrice["VALUE"]):								
													if(in_array("OLD_PRICE", $arSetting["PRODUCT_TABLE_VIEW"]["VALUE"])):?>
														<span class="catalog-item-price-old">
															<?=$arPrice["PRINT_VALUE"];?>
														</span>
													<?endif;
													if(in_array("PERCENT_PRICE", $arSetting["PRODUCT_TABLE_VIEW"]["VALUE"])):?>
														<span class="catalog-item-price-percent">
															<?=GetMessage("CATALOG_ELEMENT_SKIDKA")." ".$arPrice["PRINT_DISCOUNT_DIFF"];?>
														</span>
													<?endif;
												endif;?>
												<span class="catalog-item-price">
													<?=number_format($arPrice["DISCOUNT_VALUE"], $price["DECIMALS"], $price["DEC_POINT"], $price["THOUSANDS_SEP"]);?>
													<span class="unit">
														<?=$currency?>
														<span><?=(!empty($arElement["CATALOG_MEASURE_NAME"])) ? GetMessage("CATALOG_ELEMENT_UNIT")." ".$arElement["CATALOG_MEASURE_NAME"] : "";?></span>
													</span>
												</span>
												<?if($arSetting["REFERENCE_PRICE"]["VALUE"] == "Y" && !empty($arSetting["REFERENCE_PRICE_COEF"]["VALUE"])):?>
													<span class="catalog-item-price-reference">
														<?=CCurrencyLang::CurrencyFormat($arPrice["DISCOUNT_VALUE"] * $arSetting["REFERENCE_PRICE_COEF"]["VALUE"], $arPrice["CURRENCY"], true);?>
													</span>
												<?endif;?>
											</div>										
										<?endif;?>
										<meta itemprop="price" content="<?=$arPrice["DISCOUNT_VALUE"]?>" />
										<meta itemprop="priceCurrency" content="<?=$arPrice["CURRENCY"]?>" />
									<?endif;
								endif;
							endforeach;
							if($arElement["CAN_BUY"]):?>
								<meta content="InStock" itemprop="availability" />
							<?elseif(!$arElement["CAN_BUY"]):?>
								<meta content="OutOfStock" itemprop="availability" />									
							<?endif;
						endif;?>
					</div>						
					<?//TIME_BUY//
					if(array_key_exists("TIME_BUY", $arElement["PROPERTIES"]) && !$arElement["PROPERTIES"]["TIME_BUY"]["VALUE"] == false):
						if(!empty($arElement["CURRENT_DISCOUNT"]["ACTIVE_TO"])):							
							$showBar = false;													
							if(isset($arElement["OFFERS"]) && !empty($arElement["OFFERS"])):
								if($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_QUANTITY_TRACE"] == "Y"):
									$showBar = true;									
									$startQnt = $arElement["PROPERTIES"]["TIME_BUY_FROM"]["VALUE"] ? $arElement["PROPERTIES"]["TIME_BUY_FROM"]["VALUE"] : $arElement["TOTAL_OFFERS"]["QUANTITY"];	
									$currQnt = $arElement["PROPERTIES"]["TIME_BUY_TO"]["VALUE"] ? $arElement["PROPERTIES"]["TIME_BUY_TO"]["VALUE"] : $arElement["TOTAL_OFFERS"]["QUANTITY"];		
									$currQntPercent = round($currQnt * 100 / $startQnt);
								else:
									$showBar = true;
									$currQntPercent = 100;
								endif;
							else:
								if($arElement["CAN_BUY"]):
									if($arElement["CATALOG_QUANTITY_TRACE"] == "Y"):
										$showBar = true;
										$startQnt = $arElement["PROPERTIES"]["TIME_BUY_FROM"]["VALUE"] ? $arElement["PROPERTIES"]["TIME_BUY_FROM"]["VALUE"] : $arElement["CATALOG_QUANTITY"];
										$currQnt = $arElement["PROPERTIES"]["TIME_BUY_TO"]["VALUE"] ? $arElement["PROPERTIES"]["TIME_BUY_TO"]["VALUE"] : $arElement["CATALOG_QUANTITY"];
										$currQntPercent = round($currQnt * 100 / $startQnt);
									else:
										$showBar = true;
										$currQntPercent = 100;
									endif;
								endif;
							endif;							
							if($showBar == true):?>
								<div class="item_time_buy_cont">
									<div class="item_time_buy">
										<div class="progress_bar_block">
											<span class="progress_bar_title"><?=GetMessage("CATALOG_ELEMENT_QUANTITY_PERCENT")?></span>
											<div class="progress_bar_cont">
												<div class="progress_bar_bg">
													<div class="progress_bar_line" style="width:<?=$currQntPercent?>%;"></div>
												</div>
											</div>
											<span class="progress_bar_percent"><?=$currQntPercent?>%</span>
										</div>
										<?$new_date = ParseDateTime($arElement["CURRENT_DISCOUNT"]["ACTIVE_TO"], FORMAT_DATETIME);?>
										<script type="text/javascript">												
											$(function() {														
												$("#time_buy_timer_<?=$arItemIDs['ID']?>").countdown({
													until: new Date(<?=$new_date["YYYY"]?>, <?=$new_date["MM"]?> - 1, <?=$new_date["DD"]?>, <?=$new_date["HH"]?>, <?=$new_date["MI"]?>),
													format: "DHMS",
													expiryText: "<div class='over'><?=GetMessage('CATALOG_ELEMENT_TIME_BUY_EXPIRY')?></div>"
												});
											});												
										</script>
										<div class="time_buy_cont">
											<div class="time_buy_clock">
												<i class="fa fa-clock-o"></i>
											</div>
											<div class="time_buy_timer" id="time_buy_timer_<?=$arItemIDs['ID']?>"></div>
										</div>
									</div>
								</div>
							<?endif;
						endif;
					endif;
					//OFFERS_ITEM_BUY//?>						
					<div class="buy_more here_temp">
						<?if(!empty($arElement["DT_DISCONTINUED_NO_STOCK"])):?>

							<div class="available">
								<div class="not_avl">
									<i class="fa fa-times-circle"></i>
									<span>Нет в наличии</span>
								</div>
							</div>

						<?else:?>

						<?//OFFERS_AVAILABILITY_BUY//
						if(isset($arElement["OFFERS"]) && !empty($arElement["OFFERS"])):
							//TOTAL_OFFERS_AVAILABILITY//?>
							<div class="available">
								<?if($arElement["TOTAL_OFFERS"]["QUANTITY"] > 0 || $arElement["CATALOG_QUANTITY_TRACE"] == "N"):?>	
									<div class="avl">
										<i class="fa fa-check-circle"></i>
										<span>
											<?=GetMessage("CATALOG_ELEMENT_AVAILABLE");
											if($arElement["CATALOG_QUANTITY_TRACE"] == "Y"):
												if(in_array("PRODUCT_QUANTITY", $arSetting["GENERAL_SETTINGS"]["VALUE"])):
													echo " ".$arElement["TOTAL_OFFERS"]["QUANTITY"];
												endif;
											endif;?>
										</span>
									</div>
								<?else:?>									
									<div class="not_avl">
										<i class="fa fa-times-circle"></i>
										<span><?=GetMessage("CATALOG_ELEMENT_NOT_AVAILABLE")?></span>
									</div>
								<?endif;?>
							</div>
							<?//OFFERS_BUY//?>
							<div class="add2basket_block">
								<form action="<?=$APPLICATION->GetCurPage()?>" class="add2basket_form">
									<a href="javascript:void(0)" class="minus" onclick="if (BX('quantity_<?=$arItemIDs["ID"]?>').value > <?=$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_RATIO"]?>) BX('quantity_<?=$arItemIDs["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs["ID"]?>').value)-<?=$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_RATIO"]?>;"><span>-</span></a>
									<input type="text" id="quantity_<?=$arItemIDs['ID']?>" name="quantity" class="quantity" value="<?=$arElement['TOTAL_OFFERS']['MIN_PRICE']['CATALOG_MEASURE_RATIO']?>"/>
									<a href="javascript:void(0)" class="plus" onclick="BX('quantity_<?=$arItemIDs["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs["ID"]?>').value)+<?=$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CATALOG_MEASURE_RATIO"]?>;"><span>+</span></a>									
									<button type="button" class="btn_buy" name="add2basket" value="<?=GetMessage('CATALOG_ELEMENT_ADD_TO_CART')?>" onclick="OpenPropsPopup('<?=$arItemIDs["ID"]?>'<?=($arSetting["OFFERS_VIEW"]["VALUE"] == "LIST" ? ", true" : "");?>);"><i class="fa fa-shopping-cart"></i><span><?=GetMessage("CATALOG_ELEMENT_ADD_TO_CART")?></span></button>
								</form>
							</div>
						<?//ITEM_AVAILABILITY_BUY//
						else:
							//ITEM_AVAILABILITY//?>
							<div class="available">
								<?if($arElement["CAN_BUY"]):?>									
									<div class="avl">
										<i class="fa fa-check-circle"></i>
										<span>
											<?=GetMessage("CATALOG_ELEMENT_AVAILABLE");
											if($arElement["CATALOG_QUANTITY_TRACE"] == "Y"):
												if(in_array("PRODUCT_QUANTITY", $arSetting["GENERAL_SETTINGS"]["VALUE"])):
													echo " ".$arElement["CATALOG_QUANTITY"];
												endif;
											endif;?>
										</span>
									</div>
								<?elseif(!$arElement["CAN_BUY"]):?>									
									<div class="not_avl">
										<i class="fa fa-times-circle"></i>
										<span><?=GetMessage("CATALOG_ELEMENT_NOT_AVAILABLE")?></span>
									</div>
								<?endif;?>
							</div>
							<?//ITEM_BUY//?>
							<div class="add2basket_block">
								<?if($arElement["CAN_BUY"]):
									if($arElement["MIN_PRICE"]["DISCOUNT_VALUE"] <= 0):
										//ITEM_ASK_PRICE//?>
										<a class="btn_buy apuo" id="ask_price_anch_<?=$arItemIDs['ID']?>" href="javascript:void(0)" rel="nofollow"><i class="fa fa-comment-o"></i><span class="full"><?=GetMessage("CATALOG_ELEMENT_ASK_PRICE_FULL")?></span><span class="short"><?=GetMessage("CATALOG_ELEMENT_ASK_PRICE_SHORT")?></span></a>
									<?else:
										if(isset($arElement["SELECT_PROPS"]) && !empty($arElement["SELECT_PROPS"])):?>
											<form action="<?=$APPLICATION->GetCurPage()?>" class="add2basket_form">
										<?else:?>
											<form action="<?=SITE_DIR?>ajax/add2basket.php" class="add2basket_form">
										<?endif;?>
											<a href="javascript:void(0)" class="minus" onclick="if (BX('quantity_<?=$arItemIDs["ID"]?>').value > <?=$arElement["CATALOG_MEASURE_RATIO"]?>) BX('quantity_<?=$arItemIDs["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs["ID"]?>').value)-<?=$arElement["CATALOG_MEASURE_RATIO"]?>;"><span>-</span></a>
											<input type="text"
													id="quantity_<?=$arItemIDs['ID']?>"
													name="quantity"
													class="quantity"
													value="<?=$arElement['CATALOG_MEASURE_RATIO']?>"
													<?if(!empty($arElement["DT_DISCONTINUED"])):?>
														data-dt-max-quantity="<?=(float)$arElement["DT_STOCK_QUANTITY"]?>"
														data-dt-quantity-step="<?=(float)$arElement["CATALOG_MEASURE_RATIO"]?>"
													<?endif;?>
												/>
											<a href="javascript:void(0)" class="plus" onclick="BX('quantity_<?=$arItemIDs["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs["ID"]?>').value)+<?=$arElement["CATALOG_MEASURE_RATIO"]?>;"><span>+</span></a>
											<?if(!isset($arElement["SELECT_PROPS"]) || empty($arElement["SELECT_PROPS"])):?>
												<input type="hidden" name="ID" value="<?=$arElement['ID']?>" />
												<?if(!empty($arElement["PROPERTIES"]["ARTNUMBER"]["VALUE"])):
													$props = array();
													$props[] = array(
														"NAME" => $arElement["PROPERTIES"]["ARTNUMBER"]["NAME"],
														"CODE" => $arElement["PROPERTIES"]["ARTNUMBER"]["CODE"],
														"VALUE" => $arElement["PROPERTIES"]["ARTNUMBER"]["VALUE"]
													);												
													$props = strtr(base64_encode(addslashes(gzcompress(serialize($props),9))), '+/=', '-_,');?>
													<input type="hidden" name="PROPS" value="<?=$props?>" />
												<?endif;
											endif;?>
											<button type="button" class="btn_buy" name="add2basket" value="<?=GetMessage('CATALOG_ELEMENT_ADD_TO_CART')?>"<?=(isset($arElement["SELECT_PROPS"]) && !empty($arElement["SELECT_PROPS"]) ? " onclick=\"OpenPropsPopup('".$arItemIDs["ID"]."')\"" : " id='".$arItemIDs["BTN_BUY"]."'");?>><i class="fa fa-shopping-cart"></i><span><?=GetMessage("CATALOG_ELEMENT_ADD_TO_CART")?></span></button>
										</form>
									<?endif;
								elseif(!$arElement["CAN_BUY"]):
									//ITEM_UNDER_ORDER//?>
									<a class="btn_buy apuo" id="under_order_anch_<?=$arItemIDs['ID']?>" href="javascript:void(0)" rel="nofollow"><i class="fa fa-clock-o"></i><span><?=GetMessage("CATALOG_ELEMENT_UNDER_ORDER")?></span></a>
								<?endif;?>								
							</div>
						<?endif;?>
						<div class="clr"></div>
						<?//ITEM_COMPARE//
						if($arParams["DISPLAY_COMPARE"]=="Y"):?>
							<div class="compare">
								<a href="javascript:void(0)" class="catalog-item-compare" id="catalog_add2compare_link_<?=$arItemIDs['ID']?>" onclick="return addToCompare('<?=$arElement["COMPARE_URL"]?>', 'catalog_add2compare_link_<?=$arItemIDs["ID"]?>', '<?=SITE_DIR?>');" title="<?=GetMessage('CATALOG_ELEMENT_ADD_TO_COMPARE')?>" rel="nofollow"><i class="fa fa-bar-chart"></i><i class="fa fa-check"></i></a>
							</div>
						<?endif;
						//OFFERS_DELAY//
						if(isset($arElement["OFFERS"]) && !empty($arElement["OFFERS"])):								
							if($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["CAN_BUY"]):
								if($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISCOUNT_VALUE"] > 0):
									$props = array();
									if(!empty($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["PROPERTIES"]["ARTNUMBER"]["VALUE"])):
										$props[] = array(
											"NAME" => $arElement["TOTAL_OFFERS"]["MIN_PRICE"]["PROPERTIES"]["ARTNUMBER"]["NAME"],
											"CODE" => $arElement["TOTAL_OFFERS"]["MIN_PRICE"]["PROPERTIES"]["ARTNUMBER"]["CODE"],
											"VALUE" => $arElement["TOTAL_OFFERS"]["MIN_PRICE"]["PROPERTIES"]["ARTNUMBER"]["VALUE"]
										);																
									endif;
									foreach($arElement["TOTAL_OFFERS"]["MIN_PRICE"]["DISPLAY_PROPERTIES"] as $propOffer) {
										$props[] = array(
											"NAME" => $propOffer["NAME"],
											"CODE" => $propOffer["CODE"],
											"VALUE" => strip_tags($propOffer["DISPLAY_VALUE"])
										);
									}
									$props = !empty($props) ? strtr(base64_encode(addslashes(gzcompress(serialize($props),9))), '+/=', '-_,') : "";?>
									<div class="delay">
										<a href="javascript:void(0)" id="catalog-item-delay-min-<?=$arItemIDs['ID'].'-'.$arElement['TOTAL_OFFERS']['MIN_PRICE']['ID']?>" class="catalog-item-delay" onclick="return addToDelay('<?=$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["ID"]?>', 'quantity_<?=$arItemIDs["ID"]?>', '<?=$props?>', '', 'catalog-item-delay-min-<?=$arItemIDs["ID"]."-".$arElement["TOTAL_OFFERS"]["MIN_PRICE"]["ID"]?>', '<?=SITE_DIR?>')" title="<?=GetMessage('CATALOG_ELEMENT_ADD_TO_DELAY')?>" rel="nofollow"><i class="fa fa-heart-o"></i><i class="fa fa-check"></i></a>
									</div>
								<?endif;
							endif;
						//ITEM_DELAY//
						else:
							if($arElement["CAN_BUY"]):
								foreach($arElement["PRICES"] as $code=>$arPrice):
									if($arPrice["MIN_PRICE"] == "Y"):
										if($arPrice["DISCOUNT_VALUE"] > 0):
											$props = "";
											if(!empty($arElement["PROPERTIES"]["ARTNUMBER"]["VALUE"])):
												$props = array();
												$props[] = array(
													"NAME" => $arElement["PROPERTIES"]["ARTNUMBER"]["NAME"],
													"CODE" => $arElement["PROPERTIES"]["ARTNUMBER"]["CODE"],
													"VALUE" => $arElement["PROPERTIES"]["ARTNUMBER"]["VALUE"]
												);
												$props = strtr(base64_encode(addslashes(gzcompress(serialize($props),9))), '+/=', '-_,');
											endif;?>
											<div class="delay">
												<a href="javascript:void(0)" id="catalog-item-delay-<?=$arItemIDs['ID']?>" class="catalog-item-delay" onclick="return addToDelay('<?=$arElement["ID"]?>', 'quantity_<?=$arItemIDs["ID"]?>', '<?=$props?>', '', 'catalog-item-delay-<?=$arItemIDs["ID"]?>', '<?=SITE_DIR?>')" title="<?=GetMessage('CATALOG_ELEMENT_ADD_TO_DELAY')?>" rel="nofollow"><i class="fa fa-heart-o"></i><i class="fa fa-check"></i></a>
											</div>
										<?endif;
									endif;
								endforeach;
							endif;
						endif;							
					endif;?>			
					</div>
					<a style="margin: 15px 43px;" class="modalbox" rel="lightbox" href="/ajax/fast_wiu.php?ID=<?=$arElement["ID"]?>&SECTION_ID=<?=$arElement["IBLOCK_SECTION_ID"]?>">Быстрый просмотр</a>			
					
					<?if(empty($arElement["DT_DISCONTINUED_NO_STOCK"])):?>

						<div class="dsone-click"
							data-dsconfig="{
							'url':'https://discount-tools.ru<?=$arElement['DETAIL_PAGE_URL']?>',
							'name':'<?=$arElement['NAME']?>',
							'image':'https://discount-tools.ru<?=$arElement['PREVIEW_PICTURE']['SRC']?>'
							}">Купить в 1 клик</div>

					<?endif;?>
							
				</div>
			</div>			
		<?endforeach;?>
	</div>	
	<?//PAGINATION//
	if($arParams["DISPLAY_BOTTOM_PAGER"]):
		echo $arResult["NAV_STRING"];
	endif;?>	
</div>
<div class="clr"></div>

<?//POPUP_JS//	
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
foreach($arResult["ITEMS"] as $key => $arElement):
	$strMainID = $this->GetEditAreaId($arElement["ID"]);
	$arItemIDs = array(
		"ID" => $strMainID,				
		"BTN_BUY" => $strMainID."_btn_buy"
	);
	$strObName = "ob".preg_replace("/[^a-zA-Z0-9_]/", "x", $strMainID);			
	if($arElement["OFFERS"] || $arElement["SELECT_PROPS"]):
		//POPUP//
		$popupParams["STR_MAIN_ID"] = $strMainID;
		$popupParams["ELEMENT"] = strtr(base64_encode(addslashes(gzcompress(serialize($arElement),9))), '+/=', '-_,');?>
		<script type="text/javascript">
			if(!window.arSetParams) {
				window.arSetParams = [{'<?=$arItemIDs["ID"]?>' : <?=CUtil::PhpToJSObject($popupParams)?>}];
			} else {
				window.arSetParams.push({'<?=$arItemIDs["ID"]?>' : <?=CUtil::PhpToJSObject($popupParams)?>});
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
				"ID" => $arElement["ID"],
				"NAME" => $arElement["NAME"],
				"PICT" => is_array($arElement["PREVIEW_PICTURE"]) ? $arElement["PREVIEW_PICTURE"] : array("SRC" => SITE_TEMPLATE_PATH."/images/no-photo.jpg", "WIDTH" => 150, "HEIGHT" => 150),
			)
		);?>
		<script type="text/javascript">
			var <?=$strObName;?> = new JCCatalogSection(<?=CUtil::PhpToJSObject($arJSParams, false, true);?>);
		</script>			
	<?endif;
endforeach;

//JS//?>	
<script type="text/javascript">
	BX.ready(function() { 
            $("a.modalbox").fancybox( 
            {                                  
                "maxWidth" :950,     
                "maxHeight" :1040, 
                "autoSize" :false,
                "hideOnContentClick" :false               
            }); 
        }); 
	BX.message({			
		TABLE_ADDITEMINCART_ADDED: "<?=GetMessageJS('CATALOG_ELEMENT_ADDED')?>",
		TABLE_POPUP_WINDOW_TITLE: "<?=GetMessageJS('CATALOG_ELEMENT_ADDITEMINCART_TITLE')?>",			
		TABLE_POPUP_WINDOW_BTN_CLOSE: "<?=GetMessageJS('CATALOG_ELEMENT_ADDITEMINCART_BTN_CLOSE')?>",
		TABLE_POPUP_WINDOW_BTN_ORDER: "<?=GetMessageJS('CATALOG_ELEMENT_ADDITEMINCART_BTN_ORDER')?>",
		TABLE_SITE_DIR: "<?=SITE_DIR?>"
	});
	if(!window.OpenPropsPopup) {		
		function OpenPropsPopup(visual_id, offers_list) {
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
								"<?=$this->GetFolder();?>/popup.php",
								{							
									arParams:curSetParams
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
									qntItems[i].value = BX("quantity_" + visual_id).value;
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