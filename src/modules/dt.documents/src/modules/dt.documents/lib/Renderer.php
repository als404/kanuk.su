<?php
namespace DT\Documents;

use Bitrix\Main\Config\Option;

final class Renderer
{
    public static function render($product)
    {
        if (Option::get('dt.documents', 'public_enabled', 'N') !== 'Y') {
            return;
        }
        $items = Resolver::matched($product);
        if (!$items) {
            return;
        }
        ?>
        <div id="addition-info" class="dt-documents">
            <hr>
            <h3>Полезная информация:</h3>
            <div class="addition-info__container">
                <?php foreach ($items as $item): ?>
                    <div class="addition-info__item">
                        <a href="<?=Repository::escapeHtml($item['TARGET_URL'])?>" target="_blank" rel="noopener noreferrer">
                            <div class="addition-info__item_inner">
                                <?php if ($item['IMAGE_URL'] !== ''): ?>
                                    <img class="manual"
                                         src="<?=Repository::escapeHtml($item['IMAGE_URL'])?>"
                                         width="221" height="312"
                                         alt="<?=Repository::escapeHtml($item['LINK_ALT'])?>"
                                         title="<?=Repository::escapeHtml($item['LINK_TITLE'])?>">
                                <?php endif; ?>
                            </div>
                            <?=Repository::escapeHtml($item['LINK_TEXT'])?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
