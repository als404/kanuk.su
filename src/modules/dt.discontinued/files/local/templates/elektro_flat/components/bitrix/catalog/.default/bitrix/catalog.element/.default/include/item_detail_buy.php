
<?//OFFERS_DETAIL_TIME_BUY_TIMER_BUY//?>
<div class="item-detail-buy" id="<?=$arItemIDs['BUY'];?>">
	<?if(isset($arResult["OFFERS"]) && !empty($arResult["OFFERS"])):						
		//OFFERS_TIME_BUY_TIMER//
		if(array_key_exists("TIME_BUY", $arResult["PROPERTIES"]) && !$arResult["PROPERTIES"]["TIME_BUY"]["VALUE"] == false):
			if(!empty($arResult["CURRENT_DISCOUNT"]["ACTIVE_TO"])):								
				$new_date = ParseDateTime($arResult["CURRENT_DISCOUNT"]["ACTIVE_TO"], FORMAT_DATETIME);?>
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
			<?endif;
		endif;						
		//OFFERS_BUY//
		if($arSetting["OFFERS_VIEW"]["VALUE"] != "LIST"):
			foreach($arResult["OFFERS"] as $key => $arOffer):?>
				<div id="buy_more_detail_<?=$arItemIDs['ID'].'_'.$arOffer['ID']?>" class="buy_more_detail hidden">
					<?if($arOffer["CAN_BUY"]):
						if($arOffer["MIN_PRICE"]["DISCOUNT_VALUE"] <= 0):
							//OFFERS_ASK_PRICE//?>
							<a class="btn_buy apuo_detail" id="ask_price_anch_<?=$arItemIDs['ID'].'_'.$arOffer['ID']?>" href="javascript:void(0)" rel="nofollow"><i class="fa fa-comment-o"></i><?=GetMessage("CATALOG_ELEMENT_ASK_PRICE")?></a>
						<?else:?>
							<div class="add2basket_block">
								<form action="<?=SITE_DIR?>ajax/add2basket.php" class="add2basket_form">
									<div class="qnt_cont">
										<a href="javascript:void(0)" class="minus" onclick="if (BX('quantity_<?=$arItemIDs['ID'].'_'.$arOffer["ID"]?>').value > <?=$arOffer["CATALOG_MEASURE_RATIO"]?>) BX('quantity_<?=$arItemIDs['ID'].'_'.$arOffer["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs['ID'].'_'.$arOffer["ID"]?>').value)-<?=$arOffer["CATALOG_MEASURE_RATIO"]?>;"><span>-</span></a>
										<input type="text"
												id="quantity_<?=$arItemIDs['ID'].'_'.$arOffer['ID']?>"
												name="quantity"
												class="quantity"
												value="<?=$arOffer['CATALOG_MEASURE_RATIO']?>"
												<?if(!empty($arResult["DT_DISCONTINUED"])):?>
													data-dt-max-quantity="<?=max(0, (float)$arOffer["CATALOG_QUANTITY"])?>"
													data-dt-quantity-step="<?=(float)$arOffer["CATALOG_MEASURE_RATIO"]?>"
												<?endif;?>
											/>
										<a href="javascript:void(0)" class="plus" onclick="BX('quantity_<?=$arItemIDs['ID'].'_'.$arOffer["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs['ID'].'_'.$arOffer["ID"]?>').value)+<?=$arOffer["CATALOG_MEASURE_RATIO"]?>;"><span>+</span></a>
									</div>
									<input type="hidden" name="ID" class="offer_id" value="<?=$arOffer['ID']?>" />
									<?$props = array();
									if(!empty($arOffer["PROPERTIES"]["ARTNUMBER"]["VALUE"])):		
										$props[] = array(
											"NAME" => $arOffer["PROPERTIES"]["ARTNUMBER"]["NAME"],
											"CODE" => $arOffer["PROPERTIES"]["ARTNUMBER"]["CODE"],
											"VALUE" => $arOffer["PROPERTIES"]["ARTNUMBER"]["VALUE"]
										);																
									endif;
									foreach($arOffer["DISPLAY_PROPERTIES"] as $propOffer) {
										$props[] = array(
											"NAME" => $propOffer["NAME"],
											"CODE" => $propOffer["CODE"],
											"VALUE" => strip_tags($propOffer["DISPLAY_VALUE"])
										);
									}
									$props = !empty($props) ? strtr(base64_encode(addslashes(gzcompress(serialize($props),9))), '+/=', '-_,') : "";?>
									<input type="hidden" name="PROPS" value="<?=$props?>" />
									<?if(!empty($arResult["SELECT_PROPS"])):?>
										<input type="hidden" name="SELECT_PROPS" id="select_props_<?=$arItemIDs['ID'].'_'.$arOffer['ID']?>" value="" />
									<?endif;?>
									<button type="button" class="btn_buy detail" name="add2basket" value="<?=GetMessage('CATALOG_ELEMENT_ADD_TO_CART')?>"><i class="fa fa-shopping-cart"></i><span><?=GetMessage('CATALOG_ELEMENT_ADD_TO_CART')?></span></button>
								</form>
								<?//COMPARE_DELAY//
								include_once("item_compare_delay.php"); ?>
							</div>
						<?endif;
					elseif(!$arOffer["CAN_BUY"]):
						//OFFERS_UNDER_ORDER?>
						<a class="btn_buy apuo_detail" id="under_order_anch_<?=$arItemIDs['ID'].'_'.$arOffer['ID']?>" href="javascript:void(0)" rel="nofollow"><i class="fa fa-clock-o"></i><?=GetMessage("CATALOG_ELEMENT_UNDER_ORDER")?></a>
						<?//OFFERS_NOTIFY//?>
						<?$APPLICATION->IncludeComponent("bitrix:sale.notice.product", "", 
							array(
								"NOTIFY_ID" => $arOffer["ID"],
								"NOTIFY_URL" => htmlspecialcharsback($arOffer["SUBSCRIBE_URL"]),
								"NOTIFY_USE_CAPTHA" => "Y"
							),									
							false,
							array("HIDE_ICONS" => "Y")
						);?>
					<?endif;?>								
				</div>
			<?endforeach;
		//OFFERS_LIST_BUY//
		elseif($arSetting["OFFERS_VIEW"]["VALUE"] == "LIST"):?>
			<div class="buy_more_detail">								
				<script type="text/javascript">
					$(function() {
						$("button[name=choose_offer]").click(function() {											
							var destination = $("#item-detail-offers-list").offset().top;
							$("html:not(:animated),body:not(:animated)").animate({scrollTop: destination}, 500);
							return false;
						});
					});
				</script>
				<button name="choose_offer" class="btn_buy detail" value="<?=GetMessage('CATALOG_ELEMENT_CHOOSE_OFFER')?>"><?=GetMessage('CATALOG_ELEMENT_CHOOSE_OFFER')?></button>
			</div>							
		<?endif;
	else:						
		//DETAIL_TIME_BUY_TIMER//
		if(array_key_exists("TIME_BUY", $arResult["PROPERTIES"]) && !$arResult["PROPERTIES"]["TIME_BUY"]["VALUE"] == false):
			if(!empty($arResult["CURRENT_DISCOUNT"]["ACTIVE_TO"])):
				if($arResult["CAN_BUY"]):
					$new_date = ParseDateTime($arResult["CURRENT_DISCOUNT"]["ACTIVE_TO"], FORMAT_DATETIME);?>
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
				<?endif;
			endif;
		endif;
		//DETAIL_BUY//?>						
		<div class="buy_more_detail">							
			<?if($arResult["CAN_BUY"]):
				if($arResult["MIN_PRICE"]["DISCOUNT_VALUE"] <= 0):
					//DETAIL_ASK_PRICE//?>
						<a class="btn_buy detail-ask-price" id="ask_price_anch_<?=$arItemIDs['ID']?>" href="javascript:void(0)" rel="nofollow">
							<i class="fa fa-comment-o"></i>
							<?=GetMessage("CATALOG_ELEMENT_ASK_PRICE")?>
						</a>
						<?//COMPARE_DELAY//
						include_once("item_compare_delay.php");
				else:?>
					<form action="<?=SITE_DIR?>ajax/add2basket.php" class="add2basket_form">
						<div class="qnt_cont">
							<a href="javascript:void(0)" class="minus" onclick="if(BX('quantity_<?=$arItemIDs["ID"]?>').value > <?=$arResult["CATALOG_MEASURE_RATIO"]?>) BX('quantity_<?=$arItemIDs["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs["ID"]?>').value)-<?=$arResult["CATALOG_MEASURE_RATIO"]?>;"><span>-</span></a>
							<input type="text"
										id="quantity_<?=$arItemIDs['ID']?>"
										name="quantity"
										class="quantity"
										value="<?=$arResult['CATALOG_MEASURE_RATIO']?>"
										<?if(!empty($arResult["DT_DISCONTINUED"])):?>
											data-dt-max-quantity="<?=(float)$arResult["DT_STOCK_QUANTITY"]?>"
											data-dt-quantity-step="<?=(float)$arResult["CATALOG_MEASURE_RATIO"]?>"
										<?endif;?>
									/>
							<a href="javascript:void(0)" class="plus" onclick="BX('quantity_<?=$arItemIDs["ID"]?>').value = parseFloat(BX('quantity_<?=$arItemIDs["ID"]?>').value)+<?=$arResult["CATALOG_MEASURE_RATIO"]?>;"><span>+</span></a>
						</div>
						<input type="hidden" name="ID" class="id" value="<?=$arResult['ID']?>" />
						<?$props = array();
						if(!empty($arResult["PROPERTIES"]["ARTNUMBER"]["VALUE"])):				
							$props[] = array(
								"NAME" => $arResult["PROPERTIES"]["ARTNUMBER"]["NAME"],
								"CODE" => $arResult["PROPERTIES"]["ARTNUMBER"]["CODE"],
								"VALUE" => $arResult["PROPERTIES"]["ARTNUMBER"]["VALUE"]
							);
							$props = strtr(base64_encode(addslashes(gzcompress(serialize($props),9))), '+/=', '-_,');?>
							<input type="hidden" name="PROPS" value="<?=$props?>" />
						<?endif;
						if(!empty($arResult["SELECT_PROPS"])):?>
							<input type="hidden" name="SELECT_PROPS" id="select_props_<?=$arItemIDs['ID']?>" value="" />
						<?endif;?>
						<button type="button" id="<?=$arItemIDs['BTN_BUY']?>" class="btn_buy detail" name="add2basket" value="<?=GetMessage('CATALOG_ELEMENT_ADD_TO_CART')?>"><i class="fa fa-shopping-cart"></i><span><?=GetMessage('CATALOG_ELEMENT_ADD_TO_CART')?></span></button>
					</form>
					<?//COMPARE_DELAY//
					include_once("item_compare_delay.php");
				endif;
			elseif(!$arResult["CAN_BUY"]):
				//DETAIL_UNDER_ORDER//?>
				<a class="btn_buy detail-ask-price" id="under_order_anch_<?=$arItemIDs['ID']?>" href="javascript:void(0)" rel="nofollow"><i class="fa fa-clock-o"></i><?=GetMessage("CATALOG_ELEMENT_UNDER_ORDER")?></a>
				<?//COMPARE_DELAY//
					include_once("item_compare_delay.php");?>
				<?//DETAIL_NOTIFY//?>										
				<?$APPLICATION->IncludeComponent("bitrix:sale.notice.product", "", 
					array(
						"NOTIFY_ID" => $arResult["ID"],
						"NOTIFY_URL" => htmlspecialcharsback($arResult["SUBSCRIBE_URL"]),
						"NOTIFY_USE_CAPTHA" => "Y"
					),									
					false
				);?>
			<?endif;?>										
		</div>
	<?endif;?>
</div>
