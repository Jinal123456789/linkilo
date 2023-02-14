<div class="linkilo-collapsible-wrapper">
    <div class="linkilo-collapsible linkilo-collapsible-static linkilo-links-count"><?=count($possible_links)?></div>
    <div class="linkilo-content">
        <ul class="report_links">
            <?php foreach ($possible_links as $possible_link) : ?>
                <?php 
                $post = new Linkilo_Build_Model_Feed($possible_link->post_id, $possible_link->post_type);
                $display_sentence = preg_replace('/' . preg_quote($possible_link->case_keyword, '/') . '/', '<b>' . $possible_link->case_keyword . '</b>', $possible_link->sentence_text, 1);
                ?>
                <li id="select-keyword-<?php echo $possible_link->id; ?>">
                    <div style="display: inline-block;"><?php echo '<b>' . __('Post', 'linkilo') . '</b>: '; ?><a href="<?php echo $post->getViewLink(); ?>" target="_blank"><?php echo $post->getTitle(); ?></a></div>
                    <br />
                    <span><?php echo '<b>' . __('Sentence', 'linkilo') . '</b>: '; ?></span>
                    <br />
                    <label><div style="display: inline-block;"><input id="select-keyword-<?php echo $possible_link->id; ?>" type="checkbox" name="linkilo_keyword_select_link" data-select-keyword-id="<?php echo $possible_link->id; ?>"></div><span><?php echo $display_sentence; ?></span></label>
                    <br />
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if(!empty($possible_links)){ ?>
    <div class="insert-selected-autolinks">
        <a href="#" class="button-primary linkilo-insert-selected-keywords" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'insert-selected-autolinks-' . $post->id); ?>" data-post-id="<?php echo $post->id; ?>"  data-selected-link-id="<?php echo $post->id; ?>"><?php _e('Create Links', 'linkilo'); ?></a>
    </div>
    <?php } ?>
</div>
<div class="progress_panel loader" style="display: none; margin: 0;">
    <div class="progress_count" style="width: 100%"></div>
</div>
<div class="progress_panel_center" style="display: none;"> Loading </div>