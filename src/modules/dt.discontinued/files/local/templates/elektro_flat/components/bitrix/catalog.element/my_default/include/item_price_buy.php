                    <div class="buy_detail" itemprop="offers" itemscope itemtype="http://schema.org/Offer">
                        <!-- .catalog-detail-price -->
                        <? include_once("item_detail_price.php");?>
                        <!-- /.catalog-detail-price -->
                        <!-- .catalog-detail-buy -->
                        <?if(empty($arResult["DT_DISCONTINUED_NO_STOCK"])):?>
                            <?include_once("item_detail_buy.php");?>
                        <?endif;?>
                        <!-- /.catalog-detail-buy -->
                        <!-- .btn__anch -->
                        <?if(empty($arResult["DT_DISCONTINUED_NO_STOCK"])):?>
                            <?include("item_btn_anch.php");?>
                        <?endif;?>
                        <!-- /.btn__anch -->
                        <!-- .payment_methods -->
						<? include_once("payment_methods.php");?>
                        <!-- /.payment_methods -->
                    </div>