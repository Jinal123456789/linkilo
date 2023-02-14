<div class="wrap linkilo-report-page linkilo_styles" id="incoming_suggestions_page" data-id="<?=$post->id?>" data-type="<?=$post->type?>">
    <?=Linkilo_Build_Root::showVersion()?>
    <?php $same_category = !empty(get_user_meta(get_current_user_id(), 'linkilo_same_category_selected', true)) ? '&same_category=true': ''; ?>
    <h1 class="wp-heading-inline"><?php _e("Incoming links suggestions", "linkilo"); ?></h1>
    <a href="<?=esc_url($return_url)?>" class="page-title-action return_to_report"><?php _e('Return to Report','linkilo'); ?></a>
    <h2 id="incoming-suggestions-dest-post-title"><?php _e('Creating links pointing to: ', 'linkilo'); ?><a href="<?php echo $post->getViewLink(); ?>"><?php echo $post->getTitle();?></a></h2>
    <div id="keywords">
        <form action="" method="post">
            <label for="keywords_field">Search by Keyword</label>
            <textarea name="keywords" id="keywords_field"><?=!empty($_POST['keywords'])?sanitize_textarea_field($_POST['keywords']):''?></textarea>
            <button type="submit" class="button-primary">Search</button>
        </form>
    </div>
    <div id="linkilo-incoming-focus-keywords">
        <div class="linkilo-incoming-focus-keyword-edit-button"><button class="button-primary" data-nonce="<?php echo wp_create_nonce('linkilo-incoming-keyword-visibility-nonce'); ?>"><?php _e('Edit Focus Keyword', 'linkilo'); ?></button></div>
        <div class="linkilo-incoming-focus-keyword-edit-form" style="<?php echo (empty(get_user_meta(get_current_user_id(), 'linkilo_incoming_focus_keyword_visible', true))) ? 'display: none;' : ''; ?>"><?php
            $user = wp_get_current_user();
            $keywords = Linkilo_Build_FocusKeyword::get_keywords_by_post_ids($post->id, $post->type);
            $keyword_sources = Linkilo_Build_FocusKeyword::get_active_keyword_sources();?>
            <div id="linkilo_focus-keywords" class="postbox ">
                <h2 class="hndle no-drag"><span><?php _e('Linkilo Focus Keyword incoming', 'linkilo'); ?></span></h2>
                <div class="inside"><?php
                    $is_metabox = false;
                    include LINKILO_PLUGIN_DIR_PATH . '/templates/focus_keyword_list.php';?>
                </div>
            </div>
        </div>
    </div>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">

                <?php if (!empty($message_error)) : ?>
                    <div class="notice notice-error is-dismissible"><?=$message_error?></div>
                <?php endif; ?>

                <?php if (!empty($message_success)) : ?>
                    <div class="notice notice-success is-dismissible"><?=$message_success?></div>
                <?php endif; ?>

                <div id="linkilo_link-articles" class="postbox">
                    <h2 class="hndle no-drag"><span><?php _e('Linkilo Incoming Suggestions', 'linkilo'); ?></span></h2>
                    <div class="inside">
                        <div class="tbl-link-reports">
                            <?php $user = wp_get_current_user(); ?>
                            <?php if (!empty($_GET['linkilo_no_preload'])){ ?>
                                <?php if($manually_trigger_suggestions){ ?>
                                    <div class="linkilo_styles linkilo-get-manual-suggestions-container" style="min-height: 200px">
                                        <a href="#" id="linkilo-get-manual-suggestions" style="margin: 15px 0px;" class="button-primary"><?php _e('Get Suggestions', 'linkilo'); ?></a>
                                    </div>
                                <?php } ?>
                                <form method="post" action="">
                                    <div data-linkilo-ajax-container data-linkilo-ajax-container-url="<?=esc_url(admin_url('admin.php?page=linkilo&type=incoming_suggestions_page_container&'.($post->type=='term'?'term_id=':'post_id=').$post->id.(!empty($user->ID) ? '&nonce='.wp_create_nonce($user->ID . 'linkilo_suggestion_nonce') : '')).Linkilo_Build_UrlRecommendation::getKeywordsUrl().'&linkilo_no_preload=1' . $same_category); ?>" data-linkilo-manual-suggestions="<?php echo ($manually_trigger_suggestions) ? 1: 0;?>" <?php echo ($manually_trigger_suggestions) ? 'style="display:none"': ''; ?>>
                                        <div style="margin-bottom: 15px;">
                                            <input style="margin-bottom: -5px;" type="checkbox" name="same_category" id="field_same_category_page" <?=(isset($same_category) && !empty($same_category)) ? 'checked' : ''?>> <label for="field_same_category_page">Only Show Link Suggestions in the Same Category as This Post</label>
                                            <br>
                                            <input type="checkbox" name="same_tag" id="field_same_tag" <?=!empty($same_tag) ? 'checked' : ''?>> <label for="field_same_tag">Only Show Link Suggestions with the Same Tag as This Post</label>
                                        </div>
                                        <button id="incoming_suggestions_button" class="sync_linking_keywords_list button-primary" data-id="<?=esc_attr($post->id)?>" data-type="<?=esc_attr($post->type)?>" data-page="incoming">Add links</button>
                                    </div>
                                </form>
                            <?php }else{ ?>
                                <?php if($manually_trigger_suggestions){ ?>
                                    <div class="linkilo_styles linkilo-get-manual-suggestions-container" style="min-height: 200px">
                                        <a href="#" id="linkilo-get-manual-suggestions" style="margin: 15px 0px;" class="button-primary"><?php _e('Get Suggestions', 'linkilo'); ?></a>
                                    </div>
                                <?php } ?>
                            <div data-linkilo-ajax-container data-linkilo-ajax-container-url="<?=esc_url(admin_url('admin.php?page=linkilo&type=incoming_suggestions_page_container&'.($post->type=='term'?'term_id=':'post_id=').$post->id.(!empty($user->ID) ? '&nonce='.wp_create_nonce($user->ID . 'linkilo_suggestion_nonce') : '')).Linkilo_Build_UrlRecommendation::getKeywordsUrl() . $same_category)?>" data-linkilo-manual-suggestions="<?php echo ($manually_trigger_suggestions) ? 1: 0;?>" <?php echo ($manually_trigger_suggestions) ? 'style="display:none"': ''; ?>>
                                <div class='progress_panel loader'>
                                    <div class='progress_count' style='width: 100%'></div>
                                </div>
                                <div class="progress_panel_center" > Loading </div>
                            </div>
                            <p style="margin-top: 50px;">
                                <a href="<?=esc_url($_SERVER['REQUEST_URI'] . '&linkilo_no_preload=1')?>">Load without animation</a>
                            </p>
                            <?php } ?>
                            <div data-linkilo-page-incoming-links=1> </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
