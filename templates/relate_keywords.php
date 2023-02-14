<div class="wrap linkilo-report-page linkilo_styles">
    <style type="text/css">
    <?php
    $num = 1;

    $options = get_user_meta(get_current_user_id(), 'linkilo_keyword_options', true);
    $select_links_active = Linkilo_Build_RelateUrlKeyword::keywordLinkSelectActive();
    
    if($select_links_active && ( empty($options) || (!empty($options) && isset($options['hide_select_links_column']) && $options['hide_select_links_column'] === 'off') ) ){
        $num += 1;
    }

    switch ($num) {
        case '2':
        ?>
        tr .linkilo-dropdown-column:nth-of-type(2n+1) .linkilo-content{
            width: calc(200% + 50px);
            position: relative;
            right: 0;
        }

        tr .linkilo-dropdown-column:nth-of-type(2n+1) .insert-selected-autolinks{
            width: 200%;
        }

        tr .linkilo-dropdown-column:nth-of-type(2n+2) .linkilo-content{
            width: calc(200% + 50px);
            position: relative;
            right: calc(100% + 60px);
        }

        tr .linkilo-dropdown-column:nth-of-type(2n+2) .create-post-keywords{
            width: calc(200% + 40px);
            position: relative;
            right: calc(100% + 40px);
        }
        <?php
        break;
    }
    ?>
</style>
<?=Linkilo_Build_Root::showVersion()?>

<?php  ?>
<h1 class="wp-heading-inline"><?php _e('Add New Auto Links','linkilo'); ?></h1>
<hr class="wp-header-end">
<div id="poststuff">
    <div id="post-body" class="metabox-holder">
        <div id="post-body-content" style="position: relative;">
            <div id="linkilo_keywords_table">
                <form>
                    <input type="hidden" name="page" value="linkilo_keywords" />
                    <?php $table->search_box('Search', 'search'); ?>
                </form>
                <div method="post" id="add_keyword_form">
                    <div>
                        <input type="text" name="keyword" placeholder="Target Word">
                        <input type="text" name="link" placeholder="URL">
                    </div>
                    <a href="javascript:void(0)" class="button-primary"><?php _e('Save URL Relation', 'linkilo')?></a>
                    <div class="progress_panel loader" style="display:none;">
                        <div class="progress_count"></div>
                    </div>
                    <div class="progress_panel_center" style="display:none;"> Loading </div>
                </div>
                <form method="post" class="linkilo_keywords_settings_form">
                    <div id="linkilo_keywords_settings">
                        <i class="dashicons dashicons-admin-generic"></i>
                        <div class="block" style="display:none;">
                            <div style="margin-bottom:10px;">
                                <input type="hidden" name="linkilo_keywords_add_same_link" value="0" />
                                <input 
                                type="checkbox" 
                                id="linkilo_keywords_add_same_link" 
                                name="linkilo_keywords_add_same_link" 
                                <?=get_option('linkilo_keywords_add_same_link')==1?'checked':''?> 
                                value="1" />

                                <label for="linkilo_keywords_add_same_link">
                                    <?php _e('Add URL to feed if it already contains this url? Top', 'linkilo'); ?> 
                                </label>
                            </div>
                            <div style="margin-bottom:10px;">
                                <input type="hidden" name="linkilo_keywords_link_once" value="0" />
                                <input type="checkbox" id="linkilo_keywords_link_once" name="linkilo_keywords_link_once" checked="checked" value="1" />
                                <label for="linkilo_keywords_link_once"><?php _e('Per feed link once?', 'linkilo'); ?></label>
                            </div>
                            <div style="margin-bottom:10px;">
                                <input type="hidden" name="linkilo_keywords_select_links" value="0" />
                                <input type="checkbox" id="linkilo_keywords_select_links" name="linkilo_keywords_select_links" <?=get_option('linkilo_keywords_select_links')==1?'checked':''?> value="1" />
                                <label for="linkilo_keywords_select_links"><?php _e('Select URL before insert?', 'linkilo'); ?></label>
                            </div>

                            <div style="margin-bottom:10px;">
                                <input type="hidden" name="linkilo_keywords_set_priority" value="0" />
                                <input type="checkbox" id="linkilo_keywords_set_priority" name="linkilo_keywords_set_priority" class="linkilo_keywords_set_priority_checkbox" <?=get_option('linkilo_keywords_set_priority')==1?'checked':''?> value="1" />
                                <label for="linkilo_keywords_set_priority"><?php _e('Set priority for url insertion?', 'linkilo'); ?></label>
                                <div class="linkilo_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div style="margin-left: 0px;"><?php _e('Setting a priority for the relate url will tell Linkilo which link to insert if it comes across a sentence that has keywords that match multiple relate urls. The relate url with the highest priority will be the one inserted in such a case.', 'linkilo'); ?></div>
                                </div>
                                <div class="linkilo_keywords_priority_setting_container" style="<?=get_option('linkilo_keywords_set_priority')==1?'display:block;':''?>">
                                    <input type="number" id="linkilo_keywords_priority_setting" style="max-width: 60px;" name="linkilo_keywords_priority_setting" min="0" step="1"/>
                                </div>
                            </div>
                            <div style="margin-bottom:10px;">
                                <input type="hidden" name="linkilo_keywords_restrict_date" value="0" />
                                <input type="checkbox" id="linkilo_keywords_restrict_date" class="linkilo_keywords_restrict_date_checkbox" name="linkilo_keywords_restrict_date" value="1"/>
                                <label for="linkilo_keywords_restrict_date"><?php _e('Add URL to feeds created after specific date', 'linkilo'); ?></label>
                                <div class="linkilo_keywords_restricted_date-container">
                                    <input type="date" id="linkilo_keywords_restricted_date" name="linkilo_keywords_restricted_date"/>
                                </div>
                            </div>
                            
                            <div class="linkilo_keywords_restrict_to_cats_container" style="margin-bottom:10px;">
                                <input type="hidden" name="linkilo_keywords_restrict_to_cats" value="0" />
                                <input type="checkbox" id="linkilo_keywords_restrict_to_cats" class="linkilo_keywords_restrict_to_cats" name="linkilo_keywords_restrict_to_cats" <?php echo get_option('linkilo_keywords_restrict_to_cats')==1?'checked':'' ?> value="1" />
                                <label for="linkilo_keywords_restrict_to_cats"><?php _e('Block particular category?', 'linkilo'); ?></label>
                                <div style="position: relative; left: 10px;"><span class="linkilo-keywords-restrict-cats-show"></span></div>

                                <?php 
                                $terms = Linkilo_Build_WpTerm::getAllCategoryTerms();
                                if(!empty($terms)){
                                    echo '<ul class="linkilo-keywords-restrict-cats" style="display:none;">';
                                    echo '<li>' . __('Available Categories:', 'linkilo') . '</li>';
                                    foreach($terms as $term_data){
                                        foreach($term_data as $term){
                                            echo '<li>
                                            <input type="hidden" name="linkilo_keywords_restrict_term_' . $term->term_id . '" value="0" />
                                            <input type="checkbox" class="linkilo-restrict-keywords-input" name="linkilo_keywords_restrict_term_' . $term->term_id . '" data-term-id="' . $term->term_id . '">' . $term->name . '</li>';
                                        }
                                    }
                                    echo '</ul>';
                                }
                                ?>
                            </div>
                            <div style="margin-bottom:10px;">
                                <input type="checkbox" id="linkilo_keywords_exact_phrase_match" name="linkilo_keywords_exact_phrase_match" value="" />
                                <label for="linkilo_keywords_exact_phrase_match">
                                    <?php _e('Exact phrase match', 'linkilo'); ?>
                                </label>
                                <div class="linkilo_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div style="margin-left: 0px;">
                                        <?php _e('Check this option in links to be attached to keywords with exact phrase match.', 'linkilo'); ?> 
                                    </div>
                                </div>
                            </div>

                            <div style="margin-bottom:10px;">
                                <input type="checkbox" id="linkilo_keywords_add_dofollow" name="linkilo_keywords_add_dofollow" value="" />
                                <label for="linkilo_keywords_add_dofollow">
                                    <?php _e('Dofollow?', 'linkilo'); ?>
                                </label>
                                <div class="linkilo_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div style="margin-left: 0px;">
                                        <?php _e('Check this setting to add rel="dofollow" to the anchor generated for the keyword', 'linkilo'); ?> 
                                    </div>
                                </div>
                            </div>

                            <div style="margin-bottom:10px;">
                                <input type="checkbox" id="linkilo_keywords_open_in_same_or_new_window" name="linkilo_keywords_open_in_same_or_new_window" value="" />
                                <label for="linkilo_keywords_open_in_same_or_new_window">
                                    <?php _e('Open in: same or new window', 'linkilo'); ?>
                                </label>
                                <div class="linkilo_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div style="margin-left: 0px;">
                                        <?php _e('Check this setting to  Add target="_blank" to the anchor generated for the keyword', 'linkilo'); ?> 
                                    </div>
                                </div>
                            </div>

                            <div class="ui-widget" style="margin-bottom:20px;">
                                <label for="linkilo_keywords_whitelist_of_post_types">
                                    <?php _e('Whitelist of post types, that should be used for linking', 'linkilo'); ?>
                                </label>
                                <div class="linkilo_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div style="margin-left: 0px;">
                                        <?php _e('Post types for which links would be shown or generated. Default are (post, page)', 'linkilo'); ?> 
                                    </div>
                                </div>
                                <br><br>
                                <input 
                                id="linkilo_keywords_whitelist_of_post_types" 
                                size="50" 
                                type="text" 
                                placeholder="Type Post Type name"
                                onpaste="event.preventDefault();">
                            </div>

                            <div class="ui-widget" style="margin-bottom:20px;">
                                <label for="linkilo_keywords_blacklist_of_posts">
                                    <?php _e('Blacklist of posts that should not be used for linking', 'linkilo'); ?>        
                                </label>
                                <div class="linkilo_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div style="margin-left: 0px;">
                                        <?php _e('Posts that needs to be excluded from link keywords showing.', 'linkilo'); ?> 
                                    </div>
                                </div>
                                <br><br>
                                <input 
                                id="linkilo_keywords_blacklist_of_posts" 
                                size="50"
                                type="text"
                                placeholder="Type post name">
                            </div>

                            <div style="margin-bottom:20px;">
                                <label for="linkilo_keywords_max_rel_links_per_post">
                                    <?php _e('Maximum amount of links per post', 'linkilo'); ?>
                                </label>
                                <div class="linkilo_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div style="margin-left: 0px;">
                                        <?php _e('Limit the number of times a paragraph or sentence should be considered for anchor conversion. Set this option to 0 for unlimited. <br> [This setting will be applied per keyword]', 'linkilo'); ?> 
                                    </div>
                                </div>
                                <br><br>
                                <input 
                                type="number" 
                                id="linkilo_keywords_max_rel_links_per_post" 
                                name="linkilo_keywords_max_rel_links_per_post" 
                                value="0" 
                                min="0" 
                                max="100" 
                                placeholder="Number" 
                                style="width: 66%;"/>
                            </div>

                            <div style="margin-bottom:20px;">
                                <label for="linkilo_keywords_post_linking_maximum_frequency">
                                    <?php _e('Maximum frequency of how often a post gets linked within another one', 'linkilo'); ?>
                                </label>
                                <div class="linkilo_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div style="margin-left: 0px;">
                                        <?php _e('Limit the number of times same keyword get converted to anchor in a sentence. Set this option to 0 for making it unlimited. <br> [This setting will be applied per keyword]', 'linkilo'); ?> 
                                    </div>
                                </div>
                                <br><br>
                                <input 
                                type="number" 
                                id="linkilo_keywords_post_linking_maximum_frequency" 
                                name="linkilo_keywords_post_linking_maximum_frequency" 
                                value="0" 
                                min="0" 
                                max="100"
                                placeholder="Number" 
                                style="width: 66%;"/>
                            </div>

                            <div style="margin-bottom:10px;">
                                <label for="linkilo_keywords_excluded_html_elements">
                                    <?php _e('Excluded html elements', 'linkilo'); ?>
                                </label>
                                <div class="linkilo_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div style="margin-left: 0px;">
                                        <?php _e('List html elemet tags that would not be used for linking if the keyword is wrapped inside that html tag.', 'linkilo'); ?> 
                                    </div>
                                </div>
                                <br><br>
                                <input 
                                type="text" 
                                id="linkilo_keywords_excluded_html_elements" 
                                name="linkilo_keywords_excluded_html_elements" 
                                value="" 
                                placeholder="Type tag name" 
                                size="50" />
                            </div>
                            <input type="hidden" name="save_settings" value="1">
                            <input type="submit" class="button-primary" value="Save">
                        </div>
                    </div>
                </form>
                <div style="clear: both"></div>
                <a href="javascript:void(0)" class="button-primary" id="linkilo_keywords_reset_button"><?php _e('Refresh Records', 'linkilo'); ?></a>
                <?php if (!$reset) : ?>
                    <div class="table">
                        <?php $table->display(); ?>
                    </div>
                <?php endif; ?>
                <div class="progress" <?=$reset?'style="display:block"':''?>>
                    <h4 class="progress_panel_msg"><?php _e('Synchronizing your data..','linkilo'); ?></h4>
                    <div class="progress_panel loader">
                        <div class="progress_count"></div>
                    </div>
                    <div class="progress_panel_center" > Loading </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script>
    // (function ($) {
    //     $('#linkilo_keywords_add_dofollow').change(function(){
    //         // console.log('change');
    //         if($(this).prop("checked") == true){
    //             console.log("Checkbox is checked.");
    //         }
    //         else if($(this).prop("checked") == false){
    //             console.log("Checkbox is unchecked.");
    //         }
    //     });
    // })(jQuery);

    var linkilo_keyword_nonce = '<?=wp_create_nonce($user->ID . 'linkilo_keyword')?>';
    var is_linkilo_reset_relate_url_keyword = <?=$reset?'true':'false'?>;
</script>