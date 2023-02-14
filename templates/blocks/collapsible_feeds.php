<div class="linkilo-collapsible-wrapper">
    <div class="linkilo-collapsible linkilo-collapsible-static linkilo-links-count"><?=count($links)?></div>
    <div class="linkilo-content">
        <ul class="report_links">
            <?php foreach ($links as $link) : ?>
                <li>
                    <?=$link->post->getTitle()?> <?=!empty($link->anchor)?'<strong>[' . stripslashes($link->anchor) . ']</strong>':''?>
                    <br>
                    <a href="<?=$link->post->getLinks()->edit?>" target="_blank">[edit]</a>
                    <a href="<?=$link->post->getLinks()->view?>" target="_blank">[view]</a><br><br>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>