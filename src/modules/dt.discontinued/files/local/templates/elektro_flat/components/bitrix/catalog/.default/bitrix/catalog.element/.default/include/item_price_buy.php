                    <div class="buy_detail" itemprop="offers" itemscope itemtype="http://schema.org/Offer">
                        <!-- .catalog-detail-price -->
                        <? include_once("item_detail_price.php");?>
                        <!-- /.catalog-detail-price -->
                        <!-- .catalog-detail-buy -->
                        <?if(empty($arResult["DT_DISCONTINUED_NO_STOCK"])):?>
                            <?include_once("item_detail_buy.php");?>
                        <?endif;?>
                        <!-- /.catalog-detail-buy -->
                        <!-- -->
                        <?if(empty($arResult["DT_DISCONTINUED_NO_STOCK"])):?>
                            <?include_once("item_btn_anch.php");?>
                        <?endif;?>
                        <!-- /. -->
						<?/*//DETAIL_DELIVERY//
						if(!empty($arResult["PROPERTIES"]["DELIVERY"]["VALUE"])):?>
							<div class="catalog-detail-delivery">
								<span class="name"><?=$arResult["PROPERTIES"]["DELIVERY"]["NAME"]?></span> 
								<span class="val"><?=$arResult["PROPERTIES"]["DELIVERY"]["VALUE"]?></span>
							</div>
						<?endif;*/?>
                        <!-- .catalog-detail-buttons -->
						<? include_once("item_detail_buttons.php");?>
                        <!-- /.catalog-detail-buttons -->
                        <!-- .payment_methods -->
						<? include_once("payment_methods.php");?>
                        <!-- /.payment_methods -->
                    </div>