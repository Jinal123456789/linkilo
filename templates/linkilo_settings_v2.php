<?php
    // Commented unusable code ref:license
    // get the license status data
    // $license    = get_option(LINKILO_LICENSE_KEY_OPTION, '');
    // $status     = get_option(LINKILO_STATUS_OF_LICENSE_OPTION);
    // $last_error = get_option(LINKILO_LAST_ERROR_FOR_LICENSE_OPTION, '');

    // get the current licensing state
    // $licensing_state;
    // if(empty($license) && empty($last_error) || ('invalid' === $status && 'Deactivated manually' === $last_error)){
    //     $licensing_state = 'not_activated';
    // }elseif(!empty($license) && 'valid' === $status){
    //     $licensing_state = 'activated';
    // }else{
    //     $licensing_state = 'error';
    // }

    // create titles for the license statuses
    // $status_titles   = array(
    //     'not_activated' => __('License Not Active', 'linkilo'),
    //     'activated'     => __('License Active', 'linkilo'),
    //     'error'         => __('License Error', 'linkilo')
    // );

    // create some helpful text to tell the user what's going on
    // $status_messages = array(
    //     'not_activated' => __('Please enter your Linkilo License Key to activate Linkilo.', 'linkilo'),
    //     'activated'     => __('Congratulations! Your Linkilo License Key has been confirmed and Linkilo is now active!', 'linkilo'),
    //     'error'         => $last_error
    // );

    // get if the user has enabled site interlinking
$site_linking_enabled = get_option('linkilo_link_external_sites', false);

    // get if the user has limited the number of links per post
$max_links_per_post = get_option('linkilo_max_links_per_post', 0);
?>
<div class="wrap linkilo_styles" id="settings_page">
    <?=Linkilo_Build_Root::showVersion()?>
    <h1 class="wp-heading-inline"><?php _e('Settings', 'linkilo'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <h2 class="nav-tab-wrapper" style="margin-bottom:1em;">
                <a class="nav-tab nav-tab-active" id="linkilo-general-settings" href="#"><?php _e('Global setting', 'linkilo'); ?></a>
                <a class="nav-tab " id="linkilo-content-ignoring-settings" href="#"><?php _e('Content Disregard', 'linkilo'); ?></a>
                <a class="nav-tab " id="linkilo-advanced-settings" href="#"><?php _e('Custom Setting', 'linkilo'); ?></a>
                <!-- Commented unusable link ref:license <a class="nav-tab " id="linkilo-licensing" href="#"><?php _e('Licensing', 'linkilo'); ?></a> -->
            </h2>
            <div id="post-body-content" style="position: relative;">
                <?php 
                Linkilo_Build_GoogleSearchConsole::refresh_auth_token();
                $authenticated = Linkilo_Build_GoogleSearchConsole::is_authenticated();
                $gsc_profile = Linkilo_Build_GoogleSearchConsole::get_site_profile();
                $profile_not_found = get_option('linkilo_gsc_profile_not_easily_found', false);
                ?>
                <?php if (isset($_REQUEST['success']) && !isset($_REQUEST['access_valid'])) : ?>
                <div class="notice update notice-success" id="linkilo_message" >
                    <p><?php _e('Settings has been updated successfully!', 'linkilo'); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($_REQUEST['access_valid']) && $message = get_transient('linkilo_gsc_access_status_message')){
                if('1' === $_REQUEST['access_valid']){
                    if(!empty($gsc_profile)){?>
                        <div class="notice update notice-success" id="linkilo_message" >
                            <p><?php echo $message; ?></p>
                            </div><?php
                        }
                    }else{?>
                        <div class="notice update notice-error" id="linkilo_message" >
                            <p><?php echo $message; ?></p>
                        </div>
                        <?php
                    }
                    ?>
                <?php } ?>
                <?php if(!empty($authenticated) && empty($gsc_profile)){?>
                    <div class="notice update notice-error" id="linkilo_message" >
                        <p><?php _e('Connection Error: Either the selected Google account doesn\'t have Search Console access for this site, or Linkilo is having trouble selecting this site. If you\'re sure the selected account has access to this site\'s GSC data, please select this site\'s profile from the "Select Site Profile From Search Console List" option.', 'linkilo'); ?></p>
                    </div>
                <?php } ?>
                <form name="frmSaveSettings" id="frmSaveSettings" action='' method='post'>
                    <?php wp_nonce_field('linkilo_save_settings','linkilo_save_settings_nonce'); ?>
                    <input type="hidden" name="hidden_action" value="linkilo_save_settings" />
                    <table class="form-table">
                        <tbody>
                            <!-- related meta posts -->
                            <tr class="linkilo-general-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Sidebar link suggestions', 'linkilo'); ?></td>
                                <td>
                                    <div style="display: inline-block;">
                                        <div class="linkilo_help" style="float:right; position: relative; /*left: 30px;*/">
                                            <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                            <div><?php _e('Show or hide linkilo related meta posts', 'linkilo'); ?></div>
                                        </div>
                                        <?php 
                                        if ($related_meta_posts_enable_disable == '1') {    
                                            $checked_show = 'checked="checked"';
                                        }else{
                                            $checked_show = "";
                                        }
                                        ?>
                                        <input 
                                        type="checkbox" 
                                        name="linkilo_relate_meta_post_enable_disable"
                                        value="1"
                                        <?php echo $checked_show; ?> 
                                        >
                                    </div>
                                </td>
                            </tr>
                            <?php if ($related_meta_posts_enable_disable == '1') { ?>

                                <tr class="linkilo-general-settings linkilo-setting-row">
                                    <td scope='row'><?php _e('Show related meta posts for', 'linkilo'); ?></td>
                                    <td>
                                        <div style="display: inline-block;">
                                            <div class="linkilo_help" style="float:right; position: relative; /*left: 30px;*/ ">
                                                <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                                <div><?php _e('Enabling this option will tell Linkilo to show related posts to the selected post type\'s admin edit post screen', 'linkilo'); ?></div>
                                            </div>
                                            <?php 
                                            foreach ($types_available as $type) : ?>
                                                <?php  if ($type == "post" || $type == "page") : ?> 
                                                    <input type="checkbox" name="linkilo_relate_meta_post_types[]" value="<?=$type?>" <?=in_array($type, $related_meta_posts_types_active)?'checked':''?>><label><?=ucfirst($type)?></label><br/>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="linkilo-general-settings linkilo-setting-row">
                                    <td scope='row'><?php _e('Include post types', 'linkilo'); ?></td>
                                    <td>
                                        <div style="display: inline-block;">
                                            <div class="linkilo_help" style="float:right; position: relative; /*left: 30px;*/ ">
                                                <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                                <div><?php _e('Check the post types to include while searching for related posts. Results will differ based on the changes inpost types.', 'linkilo'); ?></div>
                                            </div>
                                            <?php 
                                            foreach ($types_available as $type) : ?>
                                                <?php  if ($type == "post" || $type == "page") : ?> 
                                                    <input type="checkbox" name="linkilo_relate_meta_post_types_include[]" value="<?=$type?>" <?=in_array($type, $related_meta_posts_types_to_include)?'checked':''?>><label><?=ucfirst($type)?></label><br/>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="linkilo-general-settings linkilo-setting-row">
                                    <td scope='row'><?php _e('Show', 'linkilo'); ?></td>
                                    <td>
                                        <div style="display: inline-block;">
                                            <div class="linkilo_help" style="float:right; position: relative; /*left: 30px;*/ ">
                                                <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                                <div><?php _e('Number of posts to display as related posts', 'linkilo'); ?></div>
                                            </div>
                                            <input type="number" min="1" max="20" value="<?php echo $related_meta_posts_limit; ?>" name="linkilo_relate_meta_post_display_limit"><label> <?php _e('Related posts', 'linkilo'); ?></label>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="linkilo-general-settings linkilo-setting-row meta-post-setting-border-bottom">
                                    <td scope='row'><?php _e('Order related posts by', 'linkilo'); ?></td>
                                    <td>
                                        <div style="display: inline-block;">
                                            <div class="linkilo_help" style="float:right; position: relative; /*left: 30px;*/ ">
                                                <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                                <div><?php _e('Order related posts by date, random order or by relevance while displaying', 'linkilo'); ?></div>
                                            </div>
                                            <?php $checked = 'checked="checked"';?>
                                            <input 
                                            type="radio" 
                                            name="linkilo_relate_meta_post_display_order"
                                            value="date"
                                            <?php
                                            echo ($related_meta_posts_order == 'date') ? $checked : '';
                                            ?>                                            
                                            >
                                            <label> <?php _e('Date', 'linkilo'); ?></label><br/>
                                            <input 
                                            type="radio" 
                                            name="linkilo_relate_meta_post_display_order"
                                            value="random"
                                            <?php
                                            echo ($related_meta_posts_order == 'random') ? $checked : '';
                                            ?>
                                            >
                                            <label> <?php _e('Random', 'linkilo'); ?></label><br/>
                                            <input 
                                            type="radio" 
                                            name="linkilo_relate_meta_post_display_order"
                                            value="relevance"
                                            <?php
                                            echo ($related_meta_posts_order == 'relevance') ? $checked : '';
                                            ?>
                                            >
                                            <label> <?php _e('Relevance', 'linkilo'); ?></label><br/>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                            <!-- related meta posts ends-->
                            <tr class="linkilo-general-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Open New Window for Internal Links', 'linkilo'); ?></td>
                                <td>
                                    <div style="max-width:80px;">
                                        <input type="hidden" name="linkilo_2_links_open_new_tab" value="0" />
                                        <input type="checkbox" name="linkilo_2_links_open_new_tab" <?=get_option('linkilo_2_links_open_new_tab')==1?'checked':''?> value="1" />
                                        <div class="linkilo_help" style="float:right;">
                                            <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                            <div>
                                                <?php 
                                                _e('Checking this will tell Linkilo to set all links that it creates pointing to pages on this site to open in a new tab.', 'linkilo');
                                                echo '<br /><br />';
                                                _e('Changing this setting will not update existing links.', 'linkilo');
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php
                            $open_external = get_option('linkilo_external_links_open_new_tab', null);
                            // if open external isn't set, use the other link option
                            $open_external = ($open_external === null) ? get_option('linkilo_2_links_open_new_tab'): $open_external;
                            ?>
                            <tr class="linkilo-general-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Open New Window for External Links', 'linkilo'); ?></td>
                                <td>
                                    <div style="max-width:80px;">
                                        <input type="hidden" name="linkilo_external_links_open_new_tab" value="0" />
                                        <input type="checkbox" name="linkilo_external_links_open_new_tab" <?=$open_external==1?'checked':''?> value="1" />
                                        <div class="linkilo_help" style="float:right;">
                                            <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                            <div>
                                                <?php 
                                                _e('Checking this will tell Linkilo to set all links that it creates pointing to external sites to open in a new tab.', 'linkilo');
                                                echo '<br /><br />';
                                                _e('Changing this setting will not update existing links.', 'linkilo');
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="linkilo-general-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Exclude Numbers in Link Suggestions', 'linkilo'); ?></td>
                                <td>
                                    <input type="hidden" name="linkilo_2_ignore_numbers" value="0" />
                                    <input type="checkbox" name="linkilo_2_ignore_numbers" <?=get_option('linkilo_2_ignore_numbers')==1?'checked':''?> value="1" />
                                </td>
                            </tr>
                            <tr class="linkilo-general-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Current Language', 'linkilo'); ?></td>
                                <td>
                                    <select id="linkilo-selected-language" name="linkilo_selected_language">
                                        <?php
                                        $languages = Linkilo_Build_AdminSettings::getSupportedLanguages();
                                        $selected_language = Linkilo_Build_AdminSettings::getSelectedLanguage();
                                        ?>
                                        <?php foreach($languages as $language_key => $language_name) : ?>
                                            <option value="<?php echo $language_key; ?>" <?php selected($language_key, $selected_language); ?>><?php echo $language_name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" id="linkilo-currently-selected-language" value="<?php echo $selected_language; ?>">
                                    <input type="hidden" id="linkilo-currently-selected-language-confirm-text-1" value="<?php echo esc_attr__('Changing Linkilo\'s language will replace the current Words to be Ignored with a new list of words.', 'linkilo') ?>">
                                    <input type="hidden" id="linkilo-currently-selected-language-confirm-text-2" value="<?php echo esc_attr__('If you\'ve added any words to the Words to be Ignored area, this will erase them.', 'linkilo') ?>">
                                </td>
                            </tr>
                            <tr class="linkilo-general-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Excluded Words', 'linkilo'); ?></td>
                                <td>
                                    <?php
                                    $lang_data = array();
                                    foreach(Linkilo_Build_AdminSettings::getAllIgnoreWordLists() as $lang_id => $words){
                                        $lang_data[$lang_id] = $words;
                                    }
                                    ?>
                                    <textarea name='ignore_words' id='ignore_words' class='regular-text' style="float:left;" rows=10><?php echo esc_textarea(implode("\n", $lang_data[$selected_language])); ?></textarea>
                                    <div class="linkilo_help">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div><?php _e('Linkilo will ignore these words when making linking suggestions. Please enter each word on a new line', 'linkilo'); ?></div>
                                    </div>
                                    <input type="hidden" id="linkilo-available-language-word-lists" value="<?php echo esc_attr( wp_json_encode($lang_data, JSON_UNESCAPED_UNICODE) ); ?>">
                                </td>
                            </tr>
                            <tr class="linkilo-content-ignoring-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Disregard feeds', 'linkilo'); ?></td>
                                <td>
                                    <textarea name='linkilo_ignore_links' id='linkilo_ignore_links' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo get_option('linkilo_ignore_links'); ?></textarea>
                                    <div class="linkilo_help">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div><?php _e('Linkilo will not suggest links TO the posts entered here. To ignore a post, enter the post\'s full url on it\'s own line in the text area', 'linkilo'); ?></div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </td>
                            </tr>
                            <tr class="linkilo-content-ignoring-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Disregard Categories', 'linkilo'); ?></td>
                                <td>
                                    <textarea name='linkilo_ignore_categories' id='linkilo_ignore_links' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo get_option('linkilo_ignore_categories'); ?></textarea>
                                    <div class="linkilo_help">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div><?php _e('Linkilo will not suggest links TO posts in the listed categories. To ignore an entire category, enter the category\'s full url on it\'s own line in the text area', 'linkilo'); ?></div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </td>
                            </tr>
                            <tr class="linkilo-content-ignoring-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Posts to be Ignored<br>for Auto-Linking and URL Changer', 'linkilo'); ?></td>
                                <td>
                                    <textarea name='linkilo_ignore_keywords_posts' id='linkilo_ignore_keywords_posts' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo get_option('linkilo_ignore_keywords_posts'); ?></textarea>
                                    <div class="linkilo_help">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div><?php _e('Linkilo will not insert auto-links or change URLs on posts entered in this field. To ignore a post, enter the post\'s full url on it\'s own line in the text area', 'linkilo'); ?></div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </td>
                            </tr>
                            <tr class="linkilo-content-ignoring-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Disregard Foundling ', 'linkilo'); ?></td>
                                <td>
                                    <textarea name='linkilo_ignore_stray_feeds' id='linkilo_ignore_stray_feeds' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo get_option('linkilo_ignore_stray_feeds', ''); ?></textarea>
                                    <div class="linkilo_help">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div><?php _e('Linkilo will not show the listed posts on the Orphan URLs Records. To ignore a post, enter a post\'s full url on it\'s own line in the text area', 'linkilo'); ?></div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </td>
                            </tr>
                            <tr class="linkilo-content-ignoring-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Disregard ACF fields', 'linkilo'); ?></td>
                                <td>
                                    <textarea name='linkilo_ignore_acf_fields' id='linkilo_ignore_acf_fields' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo get_option('linkilo_ignore_acf_fields', ''); ?></textarea>
                                    <div class="linkilo_help">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div><?php _e('Linkilo will not process content in the ACF fields listed here. To ignore a field, enter each field\'s name on it\'s own line in the text area', 'linkilo'); ?></div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </td>
                            </tr>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Select URL as Outgoing', 'linkilo'); ?></td>
                                <td>
                                    <textarea name='linkilo_marked_as_external' id='linkilo_marked_as_external' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo get_option('linkilo_marked_as_external'); ?></textarea>
                                    <div class="linkilo_help">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div><?php _e('Linkilo will recognize these links as external on the Records Page. Please enter each link on it\'s own line in the text area', 'linkilo'); ?></div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </td>
                            </tr>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Recommend outgoing URL for Specific Feeds', 'linkilo'); ?></td>
                                <td>
                                    <textarea name='linkilo_suggest_to_outgoing_posts' id='linkilo_suggest_to_outgoing_posts' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo get_option('linkilo_suggest_to_outgoing_posts', ''); ?></textarea>
                                    <div class="linkilo_help">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div><?php _e('Linkilo will only suggest outgoing links to the listed posts. Please enter each link on it\'s own line in the text area. If you do not want to limit suggestions to specific posts, leave this empty', 'linkilo'); ?></div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </td>
                            </tr>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Mark Domains as Internal', 'linkilo'); ?></td>
                                <td>
                                    <textarea name='linkilo_domains_marked_as_internal' id='linkilo_domains_marked_as_internal' style="width: 800px;float:left;" class='regular-text' rows=5><?php echo get_option('linkilo_domains_marked_as_internal'); ?></textarea>
                                    <div class="linkilo_help">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div><?php _e('Linkilo will recognize links with these domains as internal on the Records Page. Please enter each domain on it\'s own line in the text area as it appears in your browser', 'linkilo'); ?></div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </td>
                            </tr>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Open Inner URLs in New Tab', 'linkilo'); ?></td>
                                <td>
                                    <div style="max-width:80px;">
                                        <input type="hidden" name="linkilo_open_all_internal_new_tab" value="0" />
                                        <input type="checkbox" name="linkilo_open_all_internal_new_tab" <?=get_option('linkilo_open_all_internal_new_tab')==1?'checked':''?> value="1" />
                                        <div class="linkilo_help" style="float:right;">
                                            <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                            <div>
                                                <?php 
                                                _e('Checking this will tell Linkilo to filter post content before displaying to make the links to other pages on this site open in new tabs.', 'linkilo');
                                                echo '<br /><br />';
                                                _e('This will cause existing links, and those not created with Linkilo to open in new tabs.', 'linkilo');
                                                echo '<br /><br />';
                                                _e('This works best with the default WordPress content editors and may not work with some page builders', 'linkilo');
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Open Outer URLs in New Tab', 'linkilo'); ?></td>
                                <td>
                                    <div style="max-width:80px;">
                                        <input type="hidden" name="linkilo_open_all_external_new_tab" value="0" />
                                        <input type="checkbox" name="linkilo_open_all_external_new_tab" <?=get_option('linkilo_open_all_external_new_tab')==1?'checked':''?> value="1" />
                                        <div class="linkilo_help" style="float:right;">
                                            <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                            <div>
                                                <?php 
                                                _e('Checking this will tell Linkilo to filter post content before displaying to make the links to external sites open in new tabs.', 'linkilo');
                                                echo '<br /><br />';
                                                _e('This will cause existing links, and those not created with Linkilo to open in new tabs.', 'linkilo');
                                                echo '<br /><br />';
                                                _e('This works best with the default WordPress content editors and may not work with some page builders', 'linkilo');
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="linkilo-advanced-settings linkilo-setting-row js-force-open-new-tabs">
                                <td scope='row'><?php _e('Use JS to force opening in new tabs', 'linkilo'); ?></td>
                                <td>
                                    <div style="max-width:80px;">
                                        <input type="hidden" name="linkilo_js_open_new_tabs" value="0" />
                                        <input type="checkbox" name="linkilo_js_open_new_tabs" <?=get_option('linkilo_js_open_new_tabs')==1?'checked':''?> value="1" />
                                        <div class="linkilo_help" style="float:right;">
                                            <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                            <div>
                                                <?php 
                                                _e('Checking this will tell Linkilo to use frontend scripting to set links to open in new tabs.', 'linkilo');
                                                echo '<br /><br />';
                                                _e('This is mainly intended for cases where the options for setting all links to open in new tabs aren\'t working.', 'linkilo');
                                                echo '<br /><br />';
                                                _e('This will only apply to links in the content areas. Navigation links will not be affected', 'linkilo');
                                                echo '<br /><br />';
                                                echo sprintf(__('This will also cause the %s, %s and %s scripts to be outputted on most pages if they aren\'t already there.', 'linkilo'), 'jQuery', 'jQuery Migrate', 'Linkilo Frontend');
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Relative URL Mode', 'linkilo'); ?></td>
                                <td>
                                    <div style="max-width:80px;">
                                        <input type="hidden" name="linkilo_insert_links_as_relative" value="0" />
                                        <input type="checkbox" name="linkilo_insert_links_as_relative" <?=!empty(get_option('linkilo_insert_links_as_relative', false))?'checked':''?> value="1" />
                                        <div class="linkilo_help" style="float:right;">
                                            <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                            <div>
                                                <?php 
                                                _e('Checking this will tell Linkilo to insert all suggested links as relative links instead of absolute links.', 'linkilo');
                                                echo '<br /><br />';
                                                _e('This will also allow the URL Changer to change links into relative ones if the "New URL" is relative.', 'linkilo');
                                                ?>
                                            </div>
                                        </div>
                                        <div style="clear:both;"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="linkilo-general-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Find Suggested URLs in these Post Types Only', 'linkilo'); ?></td>
                                <td>
                                    <div style="display: inline-block;">
                                        <div class="linkilo_help" style="float:right; position: relative; left: 30px;">
                                            <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                            <div><?php _e('After changing the post type selection, please go to the Records Page and click the "Perform Scan" button to clear the old link data.', 'linkilo'); ?></div>
                                        </div>
                                        <?php foreach ($types_available as $type) : ?>
                                            <input type="checkbox" name="linkilo_2_post_types[]" value="<?=$type?>" <?=in_array($type, $types_active)?'checked':''?>><label><?=ucfirst($type)?></label><br>
                                        <?php endforeach; ?>
                                        <input type="hidden" name="linkilo_2_show_all_post_types" value="0">
                                        <input type="checkbox" name="linkilo_2_show_all_post_types" value="1" <?=!empty(get_option('linkilo_2_show_all_post_types', false))?'checked':''?>><label><?php _e('Show Non-Public Post Types', 'linkilo'); ?></label><br>
                                    </div>
                                </td>
                            </tr>
                        <?php /*    Commented unusable code ref:settings
                        <tr class="linkilo-general-settings linkilo-setting-row">
                            <td scope='row'><?php _e('Add URLs for Term Types', 'linkilo'); ?></td>
                            <td>
                                <div style="display: inline-block;">
                                    <div class="linkilo_help" style="float:right; position: relative; left: 30px;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div><?php _e('After changing the term type selection, please go to the Records Page and click the "Perform Scan" button to clear the old link data.', 'linkilo'); ?></div>
                                    </div>
                                    <?php foreach ($term_types_available as $type) : ?>
                                        <input type="checkbox" name="linkilo_2_term_types[]" value="<?=$type?>" <?=in_array($type, $term_types_active)?'checked':''?>><label><?=ucfirst($type)?></label><br>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="linkilo-general-settings linkilo-setting-row">
                            <td scope='row'><?php _e('Add URLs for Post Statuses', 'linkilo'); ?></td>
                            <td>
                                <div style="display: inline-block;">
                                    <div class="linkilo_help" style="float:right; position: relative; left: 30px;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div><?php _e('After changing the post status selection, please go to the Records Page and click the "Perform Scan" button to clear the old link data.', 'linkilo'); ?></div>
                                    </div>
                                    <?php foreach ($statuses_available as $status) : ?>
                                        <input type="checkbox" name="linkilo_2_post_statuses[]" value="<?=$status?>" <?=in_array($status, $statuses_active)?'checked':''?>><label><?=ucfirst($status)?></label><br>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        */ ?>
                        <tr class="linkilo-general-settings linkilo-setting-row">
                            <td scope="row"><?php _e('Sentence length to skip', 'linkilo'); ?></td>
                            <td>
                                <select name="linkilo_skip_sentences" style="float:left; max-width:100px">
                                    <?php for($i = 0; $i <= 10; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i==Linkilo_Build_AdminSettings::getSkipSentences() ? 'selected' : '' ?>><?=$i?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="linkilo_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div><?php _e('Linkilo will not suggest links for this number of sentences appearing at the beginning of a post.', 'linkilo'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="linkilo-general-settings linkilo-setting-row">
                            <td scope="row"><?php _e('Maximal Feed URL', 'linkilo'); ?></td>
                            <td>
                                <select name="linkilo_max_links_per_post" style="float:left; max-width:100px">
                                    <option value="0" <?=0===(int)$max_links_per_post ? 'selected' : '' ?>><?php _e('No Limit', 'linkilo'); ?></option>
                                    <?php for($i = 1; $i <= 100; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i===(int)$max_links_per_post ? 'selected' : '' ?>><?=$i?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="linkilo_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div><?php _e('Linkilo will not suggest links for this number of sentences appearing at the beginning of a post.', 'linkilo'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <?php /*    Commented unusable code ref:settings
                        <tr class="linkilo-general-settings linkilo-setting-row">
                            <td scope='row'><?php _e('Generate address feed to URL', 'linkilo'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="linkilo_add_destination_title" value="0" />
                                    <input type="checkbox" name="linkilo_add_destination_title" <?=!empty(get_option('linkilo_add_destination_title', false))?'checked':''?> value="1" />
                                    <div class="linkilo_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -250px 0 0 30px;">
                                            <?php 
                                            _e('Checking this will tell Linkilo to insert the title of the post it\'s linking to in the link\'s title attribute.', 'linkilo');
                                            echo '<br /><br />';
                                            _e('This will also allow users to mouse over links to see what post is being linked to.', 'linkilo');
                                            echo '<br /><br />';
                                            _e('The post title is added when links are created and changing this setting will not affect existing links.', 'linkilo');
                                            ?>
                                        </div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </div>
                            </td>
                        </tr>
                        */ ?>
                        <?php if(class_exists('ACF')){ ?>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Disable Linking for Advanced Custom Fields', 'linkilo'); ?></td>
                                <td>
                                    <input type="hidden" name="linkilo_disable_acf" value="0" />
                                    <div style="max-width: 80px;">
                                        <input type="checkbox" name="linkilo_disable_acf" <?=get_option('linkilo_disable_acf', false)==1?'checked':''?> value="1" />
                                        <div class="linkilo_help" style="float: right;">
                                            <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                            <div style="margin-left: 30px; margin-top: -20px;">
                                                <p><i><?php _e('Checking this will tell Linkilo to not process any data created by Advanced Custom Fields.', 'linkilo'); ?></i></p>
                                                <p><i><?php _e('This will speed up the suggestion making and data saving, but will not update the ACF data.', 'linkilo'); ?></i></p>
                                                <p><i><?php _e('If you don\'t see Advanced Custom Fields in your Installed Plugins list, it may be included as a component in a plugin or your theme.', 'linkilo'); ?></i></p>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php /*    Commented unusable code ref:error_report
                        <tr class="linkilo-advanced-settings linkilo-setting-row">
                            <td scope='row'><?php _e('Disable Broken Link Check Cron Task', 'linkilo'); ?></td>
                            <td>
                                <input type="hidden" name="linkilo_disable_broken_link_cron_check" value="0" />
                                <div style="max-width: 80px;">
                                    <input type="checkbox" name="linkilo_disable_broken_link_cron_check" <?=get_option('linkilo_disable_broken_link_cron_check', false)==1?'checked':''?> value="1" />
                                    <div class="linkilo_help" style="float: right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin-left: 30px; margin-top: -20px;">
                                            <p><?php _e('Checking this will disable the cron task that broken link checker runs.', 'linkilo'); ?></p>
                                            <p><?php _e('This will disable the scanning for new broken links and the re-checking of suspected broken links.', 'linkilo'); ?></p>
                                            <p><?php _e('You can still manually activate the broken link scan by going to the Error Report and clicking "Scan for Broken Links" button.', 'linkilo'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        */?>
                        <tr class="linkilo-advanced-settings linkilo-setting-row">
                            <td scope='row'><?php _e('Enable Scan for non-content URLs', 'linkilo'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="linkilo_show_all_links" value="0" />
                                    <input type="checkbox" name="linkilo_show_all_links" <?=get_option('linkilo_show_all_links')==1?'checked':''?> value="1" />
                                    <div class="linkilo_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div><?php _e('Turning this on will cause menu links, footer links, sidebar links, and links from widgets to be displayed in the link reports.', 'linkilo'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="linkilo-advanced-settings linkilo-setting-row">
                            <td scope='row'><?php _e('HTML Recommendation', 'linkilo'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="linkilo_full_html_suggestions" value="0" />
                                    <input type="checkbox" name="linkilo_full_html_suggestions" <?=get_option('linkilo_full_html_suggestions')==1?'checked':''?> value="1" />
                                    <div class="linkilo_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div><?php _e('Turning this on will tell Linkilo to display the raw HTML version of the link suggestions under the suggestion box.', 'linkilo'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="linkilo-advanced-settings linkilo-setting-row">
                            <td scope='row'><?php _e('Disable Auto Recommendation', 'linkilo'); ?></td>
                            <td>
                                <input type="hidden" name="linkilo_manually_trigger_suggestions" value="0" />
                                <input type="checkbox" name="linkilo_manually_trigger_suggestions" <?=get_option('linkilo_manually_trigger_suggestions')==1?'checked':''?> value="1" />
                                <div class="linkilo_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div><?php _e('Checking this option will tell Linkilo to not generate suggestions until you tell it to.', 'linkilo'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="linkilo-advanced-settings linkilo-setting-row">
                            <td scope='row'><?php _e('Disable Outgoing Recommendation', 'linkilo'); ?></td>
                            <td>
                                <input type="hidden" name="linkilo_disable_outgoing_suggestions" value="0" />
                                <input type="checkbox" name="linkilo_disable_outgoing_suggestions" <?=get_option('linkilo_disable_outgoing_suggestions')==1?'checked':''?> value="1" />
                                <div class="linkilo_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div><?php _e('Checking this option will prevent Linkilo from doing suggestion scans inside post edit screens.', 'linkilo'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="linkilo-advanced-settings linkilo-setting-row">
                            <td scope='row'><?php _e('Connect to Google Search Console', 'linkilo'); ?></td>
                            <td>
                                <?php
                                $authorized = get_option('linkilo_gsc_app_authorized', false);
                                $has_custom = !empty(get_option('linkilo_gsc_custom_config', false)) ? true : false;
                                $auth_message = (!$has_custom) ? __('Authorize Linkilo', 'linkilo'): __('Authorize Your App', 'linkilo');
                                if(empty($authorized)){ ?>
                                    <div class="linkilo_gsc_app_inputs">
                                        <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="linkilo_gsc_access_code" class="linkilo_gsc_get_authorize" type="text" name="linkilo_gsc_access_code"/>
                                        <label for="linkilo_gsc_access_code" class="linkilo_gsc_get_authorize"><a class="linkilo_gsc_enter_app_creds linkilo_gsc_button button-primary"><?php _e('Authorize', 'linkilo'); ?></a></label>
                                        <a style="margin-top:5px;" class="linkilo-get-gsc-access-token button-primary" href="<?php echo Linkilo_Build_GoogleSearchConsole::get_auth_url(); ?>"><?php echo $auth_message; ?></a>
                                        <?php /*
                                        <a <?php echo ($has_custom) ? 'style="display:none"': ''; ?> class="linkilo_gsc_switch_app linkilo_gsc_button enter-custom button-primary button-purple"><?php _e('Connect with Custom App', 'linkilo'); ?></a>
                                        <a <?php echo ($has_custom) ? '': 'style="display:none"'; ?> class="linkilo_gsc_clear_app_creds button-primary button-purple" data-nonce="<?php echo wp_create_nonce('clear-gsc-creds'); ?>"><?php _e('Clear Custom App Credentials', 'linkilo'); ?></a>
                                        */ ?>
                                    </div>
                                    <?php /*
                                    <div style="display:none;" class="linkilo_gsc_custom_app_inputs">
                                        <p><i><?php _e('To create a Google app to connect with, please follow this guide. TODO: Write article', 'linkilo'); ?></i></p>
                                        <div>
                                            <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="linkilo_gsc_custom_app_name" class="connect-custom-app" type="text" name="linkilo_gsc_custom_app_name"/>
                                            <label for="linkilo_gsc_custom_app_name"><?php _e('App Name', 'linkilo'); ?></label>
                                        </div>
                                        <div>
                                            <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="linkilo_gsc_custom_client_id" class="connect-custom-app" type="text" name="linkilo_gsc_custom_client_id"/>
                                            <label for="linkilo_gsc_custom_client_id"><?php _e('Client Id', 'linkilo'); ?></label>
                                        </div>
                                        <div>
                                            <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="linkilo_gsc_custom_client_secret" class="connect-custom-app" type="text" name="linkilo_gsc_custom_client_secret"/>
                                            <label for="linkilo_gsc_custom_client_secret"><?php _e('Client Secret', 'linkilo'); ?></label>
                                        </div>
                                        <a style="margin: 0 0 10px 0;" class="linkilo_gsc_enter_app_creds linkilo_gsc_button button-primary"><?php _e('Save App Credentials', 'linkilo'); ?></a>
                                        <br />
                                        <a class="linkilo_gsc_switch_app linkilo_gsc_button enter-standard button-primary button-purple"><?php _e('Connect with Linkilo App', 'linkilo'); ?></a>
                                    </div>
                                    */ ?>
                                <?php }else{ ?>
                                    <a class="linkilo-gsc-deactivate-app button-primary"  data-nonce="<?php echo wp_create_nonce('disconnect-gsc'); ?>"><?php _e('Deactivate', 'linkilo'); ?></a>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php if(!empty($authenticated) && empty($gsc_profile) && $profile_not_found){?>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Select Site Profile From Search Console List', 'linkilo'); ?></td>
                                <td>
                                    <select name="linkilo_manually_select_gsc_profile" style="float:left; max-width:400px">
                                        <option value="0"><?php _e('Select Profile', 'linkilo'); ?>
                                        <?php foreach(Linkilo_Build_GoogleSearchConsole::get_profiles() as $key => $profile){ ?>
                                            <option value="<?=$key?>"><?=$profile?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="linkilo_help">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                        <div><?php _e('Please select the correct listing for this site. The listing that matches your site\'s current URL or looks like "sc-domain:example.com" is usually the correct one.', 'linkilo'); ?></div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if($authorized){ ?>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Disable Automatic Search Console Updates', 'linkilo'); ?></td>
                                <td>
                                    <input type="hidden" name="linkilo_disable_search_update" value="0" />
                                    <input type="checkbox" name="linkilo_disable_search_update" <?=get_option('linkilo_disable_search_update', false)==1?'checked':''?> value="1" />
                                    <div class="linkilo_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div><?php _e('Linkilo automatically scans for GSC updates via WordPress Cron. Turning this off will stop Linkilo from performing the scan.', 'linkilo'); ?></div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if(defined('WPSEO_VERSION')){?>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Only Create Outbound Links to Yoast Cornerstone Content', 'linkilo'); ?></td>
                                <td>
                                    <div style="max-width:80px;">
                                        <input type="hidden" name="linkilo_link_to_yoast_cornerstone" value="0" />
                                        <input type="checkbox" name="linkilo_link_to_yoast_cornerstone" <?=get_option('linkilo_link_to_yoast_cornerstone', false)==1?'checked':''?> value="1" />
                                        <div class="linkilo_help" style="float:right;">
                                            <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                            <div><?php _e('Turning this on will tell Linkilo to restrict the outgoing link suggestions to posts marked as Yoast Cornerstone content.', 'linkilo'); ?></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        <tr class="linkilo-advanced-settings linkilo-setting-row">
                            <td scope='row'><?php _e('Get Recommendations based on targeted keyword', 'linkilo'); ?></td>
                            <td>
                                <input type="hidden" name="linkilo_only_match_focus_keywords" value="0" />
                                <input type="checkbox" name="linkilo_only_match_focus_keywords" <?=!empty(get_option('linkilo_only_match_focus_keywords', false))?'checked':''?> value="1" />
                                <div class="linkilo_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php _e('Checking this will tell Linkilo to only show suggestions that have matches based on the current post\'s Focus Keyword.', 'linkilo'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="linkilo-advanced-settings linkilo-setting-row">
                            <td scope='row'><?php _e('Add Attribute rel="noreferrer" to Created URLs', 'linkilo'); ?></td>
                            <td>
                                <input type="hidden" name="linkilo_add_noreferrer" value="0" />
                                <input type="checkbox" name="linkilo_add_noreferrer" <?=!empty(get_option('linkilo_add_noreferrer', false))?'checked':''?> value="1" />
                                <div class="linkilo_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php _e('Checking this will tell Linkilo to add the noreferrer attribute to the links it creates. Adding this attribute will cause all clicks on inserted links to be counted as direct traffic on analytics systems.', 'linkilo'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="linkilo-advanced-settings linkilo-setting-row">
                            <td scope='row'><?php _e('Add Attribute "nofollow" to outer URLs', 'linkilo'); ?></td>
                            <td>
                                <input type="hidden" name="linkilo_add_nofollow" value="0" />
                                <input type="checkbox" name="linkilo_add_nofollow" <?=!empty(get_option('linkilo_add_nofollow', false))?'checked':''?> value="1" />
                                <div class="linkilo_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Checking this will tell Linkilo to add the "nofollow" attribute to all external links it creates.', 'linkilo'); ?>
                                        <br />
                                        <br />
                                        <?php _e('However, this does not apply to links to sites you\'ve interlinked', 'linkilo'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Links to those sites won\'t have "nofollow" added.', 'linkilo'); ?></div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <td scope='row'><?php _e('Exclude image URLs from anchor', 'linkilo'); ?></td>
                                <td>
                                    <input type="hidden" name="linkilo_ignore_image_urls" value="0" />
                                    <input type="checkbox" name="linkilo_ignore_image_urls" <?=!empty(get_option('linkilo_ignore_image_urls', false))?'checked':''?> value="1" />
                                    <div class="linkilo_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div>
                                            <?php _e('Checking this will tell Linkilo to ignore image URLs in the Links Report.', 'linkilo'); ?>
                                            <br />
                                            <br />
                                            <?php _e('This will include image URLs inside anchor href attributes.', 'linkilo'); ?></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="linkilo-advanced-settings linkilo-setting-row">
                                <?php if(current_user_can('activate_plugins')){ ?>
                                    <tr class="linkilo-advanced-settings linkilo-setting-row">
                                        <td scope='row'><?php _e('Interlink Outer Site URLs', 'linkilo'); ?></td>
                                        <td>
                                            <input type="hidden" name="linkilo_link_external_sites" value="0" />
                                            <input type="checkbox" name="linkilo_link_external_sites" <?=$site_linking_enabled==1?'checked':''?> value="1" />
                                            <div class="linkilo_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                                <i class="dashicons dashicons-editor-help"></i>
                                                <div>
                                                    <?php _e('Checking this will allow you to make links to external sites that you own.', 'linkilo'); ?>
                                                    <br />
                                                    <br />
                                                    <?php _e('All sites must have Linkilo installed and be in the same licensing plan.', 'linkilo'); ?>
                                                    <br />
                                                    <br />
                                                    <a href="https://testingsiteslive-com.okyanust.com/knowledge-base/how-to-make-link-suggestions-between-sites/" target="_blank"><?php _e('Read more...', 'linkilo'); ?></a>
                                                </div>
                                            </div>
                                            <div style="clear:both;"></div>
                                        </td>
                                    </tr>
                                    <?php $access_code = get_option('linkilo_link_external_sites_access_code', false); ?>
                                    <tr class="linkilo-site-linking-setting-row linkilo-advanced-settings linkilo-setting-row" <?php echo ($site_linking_enabled === '1') ? '': 'style="display:none;"'; ?>>
                                        <td scope='row'><?php _e('Site Interlinking Access Code', 'linkilo'); ?></td>
                                        <td>
                                            <input type="hidden" name="linkilo_link_external_sites_access_code" value="0" />
                                            <input type="text" name="linkilo_link_external_sites_access_code" style="width:400px;" <?php echo (!empty($access_code)) ? 'value="' . $access_code . '"': 'placeholder="' . __('Enter Access Code', 'linkilo') . '"';?> />
                                            <a href="#" class="linkilo-generate-id-code button-primary" data-linkilo-id-code="1" data-linkilo-base-id-string="<?php echo Linkilo_Build_ConnectMultipleSite::generate_random_id_string(); ?>"><?php _e('Generate Code', 'linkilo'); ?></a>
                                            <div class="linkilo_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                                <i class="dashicons dashicons-editor-help"></i>
                                                <div><?php _e('This code is used to secure the connection between all linked sites. Use the same code on all sites you want to link', 'linkilo'); ?></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php if(!empty($access_code)){ ?>
                                        <tr class="linkilo-linked-sites-row linkilo-site-linking-setting-row linkilo-advanced-settings linkilo-setting-row" <?php echo ($site_linking_enabled === '1') ? '': 'style="display:none;"'; ?>>
                                            <td scope='row'><?php _e('Home Urls of Linked Sites', 'linkilo'); ?></td>
                                            <td class="linkilo-linked-sites-cell">
                                                <?php
                                                $unregister_text = __('Unregister Site', 'linkilo');
                                                $remove_text    = __('Remove Site', 'linkilo');
                                                $import_text   = __('Import Post Data', 'linkilo');
                                                $refresh_text = __('Refresh Post Data', 'linkilo');
                                                // $import_loadingbar = '<div class="progress_panel loader site-import-loader" style="display: none;"><div class="progress_count" style="width:100%">' . __('Importing Post Data', 'linkilo') . '</div></div>';
                                                $import_loadingbar = '<div class="progress_panel loader site-import-loader" style="display: none;"><div class="progress_count" style="width:100%"></div></div><div class="progress_panel_center" > Loading </div>';
                                                $link_site_text = __('Attempt Site Linking', 'linkilo');
                                                $disable_external_linking = __('Disable Suggestions', 'linkilo');
                                                $enable_external_linking = __('Enable Suggestions', 'linkilo');
                                                $sites = Linkilo_Build_ConnectMultipleSite::get_registered_sites();
                                                $linked_sites = Linkilo_Build_ConnectMultipleSite::get_linked_sites();
                                                $disabled_suggestion_sites = get_option('linkilo_disable_external_site_suggestions', array());

                                                foreach($sites as $site){
                                        // if the site has been linked
                                                    if(in_array($site, $linked_sites, true)){
                                                        $button_text = (Linkilo_Build_ConnectMultipleSite::check_for_stored_data($site)) ? $refresh_text: $import_text;
                                                        $suggestions_disabled = isset($disabled_suggestion_sites[$site]);
                                                        echo '<div class="linkilo-linked-site-input">
                                                        <input type="text" name="linkilo_linked_site_url[]" style="width:600px" value="' . $site . '" />
                                                        <label>
                                                        <a href="#" class="linkilo-refresh-post-data button-primary site-linking-button" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'download-site-data-nonce') . '">' . $button_text . '</a>
                                                        <a href="#" class="linkilo-external-site-suggestions-toggle button-primary site-linking-button" data-suggestions-enabled="' . ($suggestions_disabled ? 0: 1) . '" data-site-url="' . esc_url($site) . '" data-enable-text="' . $enable_external_linking . '" data-disable-text="' . $disable_external_linking . '" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'toggle-external-site-suggestions-nonce') . '">' . ($suggestions_disabled ? $enable_external_linking: $disable_external_linking) . '</a>
                                                        <a href="#" class="linkilo-unlink-site-button button-primary button-purple site-linking-button" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'unlink-site-nonce') . '">' . $remove_text . '</a>
                                                        ' . $import_loadingbar . '
                                                        </label>
                                                        </div>';
                                                    }else{
                                            // if the site hasn't been linked, but only registered
                                                        echo '<div class="linkilo-linked-site-input">
                                                        <input type="text" name="linkilo_linked_site_url[]" style="width:600px" value="' . $site . '" />
                                                        <label>
                                                        <a href="#" class="linkilo-link-site-button button-primary" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'link-site-nonce') . '">' . $link_site_text . '</a>
                                                        <a href="#" class="linkilo-unregister-site-button button-primary button-purple site-linking-button" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'unregister-site-nonce') . '">' . $unregister_text . '</a>
                                                        </label>
                                                        </div>';
                                                    }
                                                }
                                                echo '<div class="linkilo-linked-site-add-button-container">
                                                <a href="#" class="button-primary linkilo-linked-site-add-button">' . __('Add Site Row', 'linkilo') . '</a>
                                                </div>';

                                                echo '<div class="linkilo-linked-site-input template-input hidden">
                                                <input type="text" name="linkilo_linked_site_url[]" style="width:600px;" />
                                                <label>
                                                <a href="#" class="linkilo-register-site-button button-primary" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'register-site-nonce') . '">' . __('Register Site', 'linkilo') . '</a>
                                                </label>
                                                </div>';
                                                ?>
                                                <input type="hidden" id="linkilo-site-linking-initial-loading-message" value="<?php echo esc_attr__('Importing Post Data', 'linkilo'); ?>">
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php }else{ ?>
                                    <tr class="linkilo-advanced-settings linkilo-setting-row">
                                        <td scope='row'><?php _e('Interlink External Sites', 'linkilo'); ?></td>
                                        <td>
                                            <p><i><?php _e('Only admins can access the site linking settings.', 'linkilo'); ?></i></p>
                                        </td>
                                    </tr>
                                <?php } ?>
                                <tr class="linkilo-advanced-settings linkilo-setting-row">
                                    <td scope='row'><?php _e('Remove Click Data Older Than', 'linkilo'); ?></td>
                                    <td>
                                        <div style="display: flex;">
                                            <select name="linkilo_delete_old_click_data" style="float:left;">
                                                <?php $day_count = get_option('linkilo_delete_old_click_data', '0'); ?>
                                                <option value="0" <?php selected('0', $day_count) ?>><?php _e('Never Delete'); ?></option>
                                                <option value="1" <?php selected('1', $day_count) ?>><?php _e('1 Day'); ?></option>
                                                <option value="3" <?php selected('3', $day_count) ?>><?php _e('3 Days'); ?></option>
                                                <option value="7" <?php selected('7', $day_count) ?>><?php _e('7 Days'); ?></option>
                                                <option value="14" <?php selected('14', $day_count) ?>><?php _e('14 Days'); ?></option>
                                                <option value="30" <?php selected('30', $day_count) ?>><?php _e('30 Days'); ?></option>
                                                <option value="180" <?php selected('180', $day_count) ?>><?php _e('180 Days'); ?></option>
                                                <option value="365" <?php selected('365', $day_count) ?>><?php _e('1 Year'); ?></option>
                                            </select>
                                            <div class="linkilo_help" style="float:right;">
                                                <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                                <div style="margin: -50px 0 0 30px;">
                                                    <?php _e("Linkilo will delete tracked clicks that are older than this setting.", 'linkilo'); ?>
                                                    <br />
                                                    <br />
                                                    <?php _e("By default, Linkilo doesn't delete tracked click data.", 'linkilo'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="linkilo-advanced-settings linkilo-setting-row">
                                    <td scope='row'><?php _e('Disable Click Tracking', 'linkilo'); ?></td>
                                    <td>
                                        <div style="max-width:80px;">
                                            <input type="hidden" name="linkilo_disable_click_tracking" value="0" />
                                            <input type="checkbox" name="linkilo_disable_click_tracking" <?=get_option('linkilo_disable_click_tracking', false)==1?'checked':''?> value="1" />
                                            <div class="linkilo_help" style="float:right;">
                                                <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                                <div style="margin: -180px 0 0 30px;">
                                                    <?php _e("Activating this will disable the Click Tracking and will remove the Click Report from the Summary", 'linkilo'); ?>
                                                    <br>
                                                    <br>
                                                    <?php _e("The Click Tracking uses frontend scripts to track clicks. The scripts are jQuery, jQuery migrate and the Linkilo Frontend script.", 'linkilo'); ?>
                                                    <br>
                                                    <br>
                                                    <?php _e("Disabling the Click Tracking will remove these scripts.", 'linkilo'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="linkilo-advanced-settings linkilo-setting-row">
                                    <td scope='row'><?php _e('Remove Linkilo Data', 'linkilo'); ?></td>
                                    <td>
                                        <div style="max-width:80px;">
                                            <input type="hidden" name="linkilo_delete_all_data" value="0" />
                                            <input type="checkbox" class="danger-zone" name="linkilo_delete_all_data" <?=get_option('linkilo_delete_all_data', false)==1?'checked':''?> value="1" />
                                            <input type="hidden" class="linkilo-delete-all-data-message" value="<?php echo sprintf(__('Activating this will tell Linkilo to delete ALL Linkilo related data when the plugin is deleted. %s This will remove all settings and stored data. Links inserted into content by Linkilo will still exist. %s Undoing actions like URL changes will be impossible since the records of what the url used to be will be deleted as well. %s Please only activate this option if you\'re sure you want to delete all data.', 'linkilo'), '&lt;br&gt;&lt;br&gt;', '&lt;br&gt;&lt;br&gt;', '&lt;br&gt;&lt;br&gt;'); ?>">
                                            <div class="linkilo_help" style="float:right;">
                                                <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                                <div style="margin: -50px 0 0 30px;">
                                                    <?php _e("Activating this will tell Linkilo to delete ALL Linkilo related data when the plugin is deleted.", 'linkilo'); ?>
                                                    <br>
                                                    <br>
                                                    <?php _e("Please only activate this option if you're sure you want to delete ALL Linkilo data.", 'linkilo'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="linkilo-advanced-settings linkilo-setting-row">
                                    <td scope='row'>
                                        <span class="settings-carrot">
                                            <?php _e('Debug Settings', 'linkilo'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="setting-control">
                                            <input type="hidden" name="linkilo_2_debug_mode" value="0" />
                                            <input type='checkbox' name="linkilo_2_debug_mode" <?=get_option('linkilo_2_debug_mode')==1?'checked':''?> value="1" />
                                            <label><?php _e('Enable Debug Mode?', 'linkilo'); ?></label>
                                            <p><i><?php _e('If you\'re having errors, or it seems that data is missing, activating Debug Mode may be useful in diagnosing the problem.', 'linkilo'); ?></i></p>
                                            <p><i><?php _e('Enabling Debug Mode will cause your site to display any errors or code problems it\'s expiriencing instead of hiding them from view.', 'linkilo'); ?></i></p>
                                            <p><i><?php _e('These error notices may be visible to your site\'s visitors, so it\'s recommended to only use this for limited periods of time.', 'linkilo'); ?></i></p>
                                            <p><i><?php _e('(If you are already debugging with WP_DEBUG, then there\'s no need to activate this.)', 'linkilo'); ?></i></p>
                                            <br>
                                        </div>
                                        <div class="setting-control">
                                            <input type="hidden" name="linkilo_option_update_reporting_data_on_save" value="0" />
                                            <input type='checkbox' name="linkilo_option_update_reporting_data_on_save" <?=get_option('linkilo_option_update_reporting_data_on_save')==1?'checked':''?> value="1" />
                                            <label><?php _e('Run a check for un-indexed posts on each post save?', 'linkilo'); ?></label>
                                            <p><i><?php _e('Checking this will tell Linkilo to look for any posts that haven\'t been indexed for the link reports every time a post is saved.', 'linkilo'); ?></i></p>
                                            <p><i><?php _e('In most cases this isn\'t necessary, but if you\'re finding that some of your posts aren\'t displaying in the reports screens, this may fix it.', 'linkilo'); ?></i></p>
                                            <p><i><?php _e('One word of caution: If you have many un-indexed posts on the site, this may cause memory / timeout errors.', 'linkilo'); ?></i></p>
                                            <br>
                                        </div>
                                        <div class="setting-control">
                                            <input type="hidden" name="linkilo_include_post_meta_in_support_export" value="0" />
                                            <input type='checkbox' name="linkilo_include_post_meta_in_support_export" <?=get_option('linkilo_include_post_meta_in_support_export')==1?'checked':''?> value="1" />
                                            <label><?php _e('Include post meta in support data export?', 'linkilo'); ?></label>
                                            <p><i><?php _e('Checking this will tell Linkilo to include additional post data in the data for support export.', 'linkilo'); ?></i></p>
                                            <p><i><?php _e('This isn\'t needed for most support cases. It\'s most commonly used for troubleshooting issues with page builders', 'linkilo'); ?></i></p>
                                            <br>
                                        </div>
                                    </td>
                                </tr>
                        <!-- Commented unusable code ref:license <tr class="linkilo-licensing linkilo-setting-row">
                            <td>
                                <div class="wrap linkilo_licensing_wrap postbox">
                                    <div class="linkilo_licensing_container">
                                        <div class="linkilo_licensing" style="">
                                            <h2 class="linkilo_licensing_header hndle ui-sortable-handle">
                                                <span>Linkilo Licensing</span>
                                            </h2>
                                            <div class="linkilo_licensing_content inside">
                                                <?php //settings_fields('linkilo_license'); ?>
                                                <input type="hidden" id="linkilo_license_action_input" name="hidden_action" value="activate_license" disabled="disabled">
                                                <table class="form-table">
                                                    <tbody>
                                                        <tr>
                                                            <td class="linkilo_license_table_title"><?php //_e('License Key:', 'linkilo');?></td>
                                                            <td><input id="linkilo_license_key" name="linkilo_license_key" type="text" class="regular-text" value="" /></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="linkilo_license_table_title"><?php //_e('License Status:', 'linkilo');?></td>
                                                            <td><span class="linkilo_licensing_status_text <?php //echo esc_attr($licensing_state); ?>"><?php //echo esc_attr($status_titles[$licensing_state]); ?></span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="linkilo_license_table_title"><?php //_e('License Message:', 'linkilo');?></td>
                                                            <td><span class="linkilo_licensing_status_text <?php //echo esc_attr($licensing_state); ?>"><?php //echo esc_attr($status_messages[$licensing_state]); ?></span></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <?php //wp_nonce_field( 'linkilo_activate_license_nonce', 'linkilo_activate_license_nonce' ); ?>
                                                <div class="linkilo_licensing_version_number"><?php //echo Linkilo_Build_Root::showVersion(); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr> -->
                    </tbody>
                </table>
                <p class='submit linkilo-setting-button save-settings'>
                    <input type='submit' name='btnsave' id='btnsave' value='Save Settings' class='button-primary' />
                </p>
                    <!-- Commented unusable code ref:license <p class='submit linkilo-setting-button activate-license' style="display:none">
                        <button type="submit" class="button button-primary linkilo_licensing_activation_button"><?php _e('Activate License', 'linkilo'); ?></button>
                    </p> -->
                </form>
            </div>
        </div>
    </div>
</div>