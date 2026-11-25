                <?//DETAIL_PREVIEW_TEXT//
				if(!empty($arResult["PREVIEW_TEXT"]) || !empty($arResult["DT_DISCONTINUED"])):?>			
					<div class="preview-text">
						<?if(!empty($arResult["DT_DISCONTINUED"])):?>
							<div class="dt-discontinued-text">
								<strong>Снят с производства!</strong>
							</div>
						<?endif;?>
						<?=$arResult["PREVIEW_TEXT"]?>
					</div>
				<meta itemprop="brand" content="<?=$arResult["PROPERTIES"]["MANUFACTURER"]["NAME"]?>">
				<meta itemprop="description" content="<?=$arResult["PREVIEW_TEXT"]?> <?if(!empty($arResult["DISPLAY_PROPERTIES"])):
							foreach($arResult["DISPLAY_PROPERTIES"] as $k => $v):
								$names = strip_tags($v["NAME"]);
								$values	= strip_tags($v["DISPLAY_VALUE"]);
   								echo $names . ' - ' . $values . '. ';
							endforeach;
						endif;?>">
				<?endif;?>