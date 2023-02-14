<div class="linkilo-collapsible-wrapper">
    <div class="linkilo-collapsible linkilo-collapsible-static linkilo-links-count"><?=count($keywords)?></div>
    <div class="linkilo-content">
        <ul class="report_links">
            <?php foreach ($keywords as $keyword) : ?>
                <li id="focus-keyword-<?php echo $keyword->keyword_index; ?>">
                    <?php
                    if('custom-keyword' === $keyword->keyword_type){
                        echo '<div style="display: inline-block;"><label><span>'. $keyword->keywords . '</span></label></div>';
                        echo '<i class="linkilo_focus_keyword_delete dashicons dashicons-no-alt" data-keyword-id="' . $keyword->keyword_index . '" data-keyword-type="custom-keyword" data-nonce="' . wp_create_nonce(get_current_user_id() . 'delete-focus-keywords-' . $keyword->keyword_index) . '"></i>';
                    }else{
                        ?><div style="display: inline-block;"><input id="keyword-<?php echo $keyword->keyword_index; ?>" style="vertical-align: sub;" type="checkbox" name="keyword_active" data-keyword-id="<?php echo $keyword->keyword_index; ?>" <?php echo (!empty($keyword->checked)) ? 'checked="checked"': '';?>><label for="keyword-<?php echo $keyword->keyword_index; ?>"><span><?php echo $keyword->keywords; ?></span></label></div><?php
                    }
                    
                    if('gsc-keyword' === $keyword->keyword_type){
                        echo 
                        '<div>
                            <div style="margin: 3px 0;"><b>' . __('Impressions', 'linkilo') . ':</b> ' . $keyword->impressions . '</div>
                            <div style="margin: 3px 0;"><b>' . __('Clicks', 'linkilo') . ':</b> ' . $keyword->clicks . '</div>
                            <div style="margin: 3px 0;"><b>' . __('Position', 'linkilo') . ':</b> ' . $keyword->position . '</div>
                            <div style="margin: 3px 0;"><b>' . __('CTR', 'linkilo') . ':</b> ' . $keyword->ctr . '</div>
                        </div>';
                    } ?>
                    <br>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if(!empty($keywords) && 'custom-keyword' !== $keyword_type){ ?>
    <div class="update-post-keywords">
        <a href="#" class="button-primary linkilo-update-selected-keywords" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'update-selected-keywords-' . $post->id); ?>" data-post-id="<?php echo $post->id; ?>"><?php _e('Update Active', 'linkilo'); ?></a>
    </div>
    <?php } ?>
    <?php if('custom-keyword' === $keyword_type){ ?>
    <div class="create-post-keywords">
        <a href="#" style="vertical-align: top;" class="button-primary linkilo-create-focus-keywords" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'create-focus-keywords-' . $post->id); ?>" data-post-id="<?php echo $post->id; ?>" data-post-type="<?php echo $post->type; ?>"><?php _e('Create', 'linkilo'); ?></a>
        <div class="linkilo-create-focus-keywords-row-container"  style="display: inline-block; width: calc(100% - 200px);">
            <input type="text" style="width: 100%" class="create-custom-focus-keyword-input" placeholder="<?php _e('New Custom Keyword', 'linkilo'); ?>">
        </div>
        <a href="#" class="button-primary linkilo-add-focus-keyword-row" style="margin-left:0px; vertical-align: top;"><?php _e('Add Row', 'linkilo'); ?></a>
    </div>
    <?php } ?>
</div>