<?php

/**
 * Work with settings
 */
class Linkilo_Build_AdminSettings
{
    public static $ignore_phrases = null;
    public static $ignore_words = null;
    public static $keys = [
        'linkilo_2_ignore_numbers',
        'linkilo_2_post_types',
        'linkilo_relate_meta_post_types',   //related meta posts
        'linkilo_relate_meta_post_display_limit',   //related meta posts
        'linkilo_relate_meta_post_display_order',   //related meta posts
        'linkilo_relate_meta_post_enable_disable',   //related meta posts
        'linkilo_relate_meta_post_types_include',   //related meta posts
        'linkilo_2_term_types',
        'linkilo_2_post_statuses',
        'linkilo_2_links_open_new_tab',
        'linkilo_2_ll_use_h123',
        'linkilo_2_ll_pairs_mode',
        'linkilo_2_ll_pairs_rank_pc',
        'linkilo_2_debug_mode',
        'linkilo_option_update_reporting_data_on_save',
        'linkilo_skip_sentences',
        'linkilo_selected_language',
        'linkilo_ignore_links',
        'linkilo_ignore_categories',
        'linkilo_show_all_links',
        'linkilo_manually_trigger_suggestions',
        'linkilo_disable_outgoing_suggestions',
        'linkilo_full_html_suggestions',
        'linkilo_ignore_keywords_posts',
        'linkilo_ignore_stray_feeds',
        'linkilo_marked_as_external',
        'linkilo_disable_acf',
        'linkilo_link_external_sites',
        'linkilo_link_external_sites_access_code',
        'linkilo_2_show_all_post_types',
        'linkilo_disable_search_update',
        'linkilo_domains_marked_as_internal',
        'linkilo_link_to_yoast_cornerstone',
        'linkilo_suggest_to_outgoing_posts',
        'linkilo_only_match_focus_keywords',
        'linkilo_add_noreferrer',
        'linkilo_add_nofollow',
        'linkilo_delete_all_data',
        'linkilo_external_links_open_new_tab',
        'linkilo_insert_links_as_relative',
        'linkilo_ignore_image_urls',
        'linkilo_include_post_meta_in_support_export',
        'linkilo_ignore_acf_fields',
        'linkilo_open_all_internal_new_tab',
        'linkilo_open_all_external_new_tab',
        'linkilo_js_open_new_tabs',
        'linkilo_add_destination_title',
        'linkilo_disable_broken_link_cron_check',
        'linkilo_disable_click_tracking',
        'linkilo_delete_old_click_data',
        'linkilo_max_links_per_post',
    ];

    /**
     * Show settings page
     */
    public static function init()
    {
        $types_active = Linkilo_Build_AdminSettings::getPostTypes();
        $term_types_active = Linkilo_Build_AdminSettings::getTermTypes();
        if(empty(get_option('linkilo_2_show_all_post_types', false))){
            $types_available = get_post_types(['public' => true]);
        }else{
            $types_available = get_post_types();
        }

        $term_types_available = get_taxonomies();
        $statuses_available = [
            'publish',
            // 'private',
            // 'future',
            // 'pending',
            // 'draft'
        ];
        $statuses_active = Linkilo_Build_AdminSettings::getPostStatuses();

        $related_meta_posts_types_active = Linkilo_Build_AdminSettings::getRelatedMetaPostTypes();
        $related_meta_posts_limit = Linkilo_Build_AdminSettings::getRelatedMetaPostLimit();
        $related_meta_posts_order = Linkilo_Build_AdminSettings::getRelatedMetaPostOrder();
        $related_meta_posts_enable_disable = Linkilo_Build_AdminSettings::getRelatedMetaPostEnableDisable();
        $related_meta_posts_types_to_include = Linkilo_Build_AdminSettings::getRelatedMetaPostInclude();
        

        include LINKILO_PLUGIN_DIR_PATH . '/templates/linkilo_settings_v2.php';
    }

    /**
     * Get ignore phrases
     */
    public static function getIgnorePhrases()
    {
        if (is_null(self::$ignore_phrases)) {
            $phrases = [];
            foreach (self::getIgnoreWords() as $word) {
                if (strpos($word, ' ') !== false) {
                    $phrases[] = preg_replace('/\s+/', ' ',$word);
                }
            }

            self::$ignore_phrases = $phrases;
        }

        return self::$ignore_phrases;
    }

    /**
     * Get ignore words
     */
    public static function getIgnoreWords()
    {
        if (is_null(self::$ignore_words)) {
            $words = get_option('linkilo_2_ignore_words', null);
            // get the user's current language
            $selected_language = self::getSelectedLanguage();

            // if there are no stored words or the current language is different from the selected one
            if (is_null($words) || (LINKILO_CURRENTLY_SET_LANGUAGE !== $selected_language)) {
                $ignore_words_file = self::getIgnoreFile($selected_language);
                $words = file($ignore_words_file);
                
                foreach($words as $key => $word) {
                    $words[$key] = trim(Linkilo_Build_WordFunctions::strtolower($word));
                }
            } else {
                $words = explode("\n", $words);
                $words = array_unique($words);
                sort($words);

                foreach($words as $key => $word) {
                    $words[$key] = trim(Linkilo_Build_WordFunctions::strtolower($word));
                }
            }

            self::$ignore_words = $words;
        }
        
        return self::$ignore_words;
    }

    /**
     * Gets all current ignore word lists.
     * The word list for the language the user is currently using is loaded from the settings.
     * All other languages are loaded from the word files
     **/
    public static function getAllIgnoreWordLists(){
        $current_language       = self::getSelectedLanguage();
        $supported_languages    = self::getSupportedLanguages();
        $all_ignore_lists       = array();

        // go over all currently supported languages
        foreach($supported_languages as $language_id => $supported_language){

            // if the current language is the user's selected one
            if($language_id === $current_language){
                
                $words = get_option('linkilo_2_ignore_words', null);
                if(is_null($words)){
                    $words = self::getIgnoreWords();
                }

                $words = explode("\n", $words);
                $words = array_unique($words);
                sort($words);
                foreach($words as $key => $word) {
                    $words[$key] = trim(Linkilo_Build_WordFunctions::strtolower($word));
                }
                
                $all_ignore_lists[$language_id] = $words;
            }else{
                $ignore_words_file = self::getIgnoreFile($language_id);
                $words = array();
                if(file_exists($ignore_words_file)){
                    $words = file($ignore_words_file);
                }else{
                    // if there is no word file, skip to the next one
                    continue;
                }
                
                if(empty($words)){
                    $words = array();
                }
                
                foreach($words as $key => $word) {
                    $words[$key] = trim(Linkilo_Build_WordFunctions::strtolower($word));
                }
                
                $all_ignore_lists[$language_id] = $words;
            }
        }

        return $all_ignore_lists;
    }

    /**
     * Get ignore words file based on current language
     *
     * @param $language
     * @return string
     */
    public static function getIgnoreFile($language)
    {
        switch($language){
            case 'spanish':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/ES_ignore_words.txt';
                break;
            case 'french':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/FR_ignore_words.txt';
                break;
            case 'german':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/DE_ignore_words.txt';
                break;
            case 'russian':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/RU_ignore_words.txt';
                break;
            case 'portuguese':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/PT_ignore_words.txt';
                break;
            case 'dutch':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/NL_ignore_words.txt';
                break;
            case 'danish':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/DA_ignore_words.txt';
                break;
            case 'italian':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/IT_ignore_words.txt';
                break;
            case 'polish':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/PL_ignore_words.txt';
                break;            
            case 'slovak':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/SK_ignore_words.txt';
                break;
            case 'norwegian':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/NO_ignore_words.txt';
                break;
            case 'swedish':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/SW_ignore_words.txt';
                break;            
            case 'arabic':
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/AR_ignore_words.txt';
                break;
            default:
                $file = LINKILO_PLUGIN_DIR_PATH . 'includes/ignore_word_lists/EN_ignore_words.txt';
                break;
        }

        return $file;
    }

    /**
     * Get selected post types
     *
     * @return mixed|void
     */
    public static function getPostTypes()
    {
        return get_option('linkilo_2_post_types', ['post', 'page']);
    }

    /**
     * Get selected post types for related meta posts
     *
     * @return mixed|void
     */
    public static function getRelatedMetaPostTypes()
    {
        return get_option('linkilo_relate_meta_post_types', ['post', 'page']);
    }

    /**
     * Get number limit for display related meta posts
     *
     * @return mixed|void
     */
    public static function getRelatedMetaPostLimit()
    {
        return get_option('linkilo_relate_meta_post_display_limit');
    }

    /**
     * Get order for posts display related meta posts
     *
     * @return mixed|void
     */
    public static function getRelatedMetaPostOrder()
    {
        return get_option('linkilo_relate_meta_post_display_order');
    }

    /**
     * Get option for posts to check if display related meta posts or not
     *
     * @return mixed|void
     */
    public static function getRelatedMetaPostEnableDisable()
    {
        return get_option('linkilo_relate_meta_post_enable_disable');
    }

    /**
     * Get option for posts to check if display related meta posts or not
     *
     * @return mixed|void
     */
    public static function getRelatedMetaPostInclude()
    {
        return get_option('linkilo_relate_meta_post_types_include', ['post', 'page']);
    }

    /**
     * Get merged array of post types and term types
     *
     * @return array
     */
    public static function getAllTypes()
    {
        return array_merge(self::getPostTypes(), self::getTermTypes());
    }

    /**
     * Get selected post statuses
     *
     * @return array
     */
    public static function getPostStatuses()
    {
        return get_option('linkilo_2_post_statuses', ['publish']);
    }

    public static function getInternalDomains(){
        $domains = get_transient('linkilo_domains_marked_as_internal');
        if(empty($domains)){
            $domains = array();
            $domain_data = get_option('linkilo_domains_marked_as_internal');
            $domain_data = explode("\n", $domain_data);
            foreach ($domain_data as $domain) {
                $pieces = wp_parse_url($domain);
                if(!empty($pieces) && isset($pieces['host'])){
                    $domains[] = str_replace('www.', '', $pieces['host']);
                }
            }

            set_transient('linkilo_domains_marked_as_internal', $domains, 15 * MINUTE_IN_SECONDS);
        }

        return $domains;
    }

    /**
     * Gets the currently supported languages
     * 
     * @return array
     **/
    public static function getSupportedLanguages(){
        $languages = array(
            'english'       => 'English',
            'spanish'       => 'Español',
            'french'        => 'Français',
            'german'        => 'Deutsch',
            'russian'       => 'Русский',
            'portuguese'    => 'Português',
            'dutch'         => 'Dutch',
            'danish'        => 'Dansk',
            'italian'       => 'Italiano',
            'polish'        => 'Polskie',
            'norwegian'     => 'Norsk bokmål',
            'swedish'       => 'Svenska',
            'slovak'        => 'Slovenčina',
            'arabic'        => 'سنڌي'
        );
        
        return $languages;
    }

    /**
     * Gets the currently selected language
     * 
     * @return array
     **/
    public static function getSelectedLanguage(){
        return get_option('linkilo_selected_language', 'english');
    }

    /**
     * Gets the language for the current processing run.
     * Does a check to see if there's a translation plugin active.
     * If there is, it tries to set the current language to the current post's language.
     * If that's not possible, or there isn't a translation plugin, it defaults to the set language
     **/
    public static function getCurrentLanguage(){

        // if Polylang is active
        if(defined('POLYLANG_VERSION')){
            // see if we're creating suggestions and there's a post
            if( isset($_POST['action']) && ($_POST['action'] === 'get_recommended_url' || $_POST['action'] === 'update_recommendation_display') &&
                isset($_POST['post_id']) && !empty($_POST['post_id']))
            {
                global $wpdb;
                $post_id = (int) $_POST['post_id'];

                // get the language ids
                $language_ids = $wpdb->get_col("SELECT `term_taxonomy_id` FROM $wpdb->term_taxonomy WHERE `taxonomy` = 'language'");

                // if there are no ids, return the selected language from the settings
                if(empty($language_ids)){
                    return self::getSelectedLanguage();
                }

                $language_ids = implode(', ', $language_ids);

                // check the term_relationships to see if any are applied to the current post
                $tax_id = $wpdb->get_var("SELECT `term_taxonomy_id` FROM $wpdb->term_relationships WHERE `object_id` = {$post_id} AND `term_taxonomy_id` IN ({$language_ids})");

                // if there are no ids, return the selected language from the settings
                if(empty($tax_id)){
                    return self::getSelectedLanguage();
                }

                // query the wp_terms to get the language code for the applied language
                $code = $wpdb->get_var("SELECT `slug` FROM $wpdb->terms WHERE `term_id` = {$tax_id}");

                // if we've gotten the language code, see if we support the language
                if($code){
                    $supported_language_codes = array(
                        'en' => 'english',
                        'es' => 'spanish',
                        'fr' => 'french',
                        'de' => 'german',
                        'ru' => 'russian',
                        'pt' => 'portuguese',
                        'nl' => 'dutch',
                        'da' => 'danish',
                        'it' => 'italian',
                        'pl' => 'polish',
                        'sk' => 'slovak',
                        'nb' => 'norwegian',
                        'sv_SE' => 'swedish',
                        'sd' => 'arabic',
                        'snd' => 'arabic',
                    );

                    // if we support the language, return it as the active one
                    if(isset($supported_language_codes[$code])){
                        return $supported_language_codes[$code];
                    }
                }
            }
        }

        // if WPML is active
        if(self::wpml_enabled()){
            // see if we're creating suggestions and there's a post
            if( isset($_POST['action']) && ($_POST['action'] === 'get_recommended_url' || $_POST['action'] === 'update_recommendation_display') &&
            isset($_POST['post_id']) && !empty($_POST['post_id']))
            {
                global $wpdb;
                $post_id = (int) $_POST['post_id'];
                $post_type = get_post_type($post_id);
                $post_type = 'post_' . $post_type;
                $code = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $post_id AND `element_type` = '{$post_type}'");

                if(!empty($code)){

                    $supported_language_codes = array(
                        'en' => 'english',
                        'es' => 'spanish',
                        'fr' => 'french',
                        'de' => 'german',
                        'ru' => 'russian',
                        'pt-br' => 'portuguese',
                        'pt-pt' => 'portuguese',
                        'nl' => 'dutch',
                        'da' => 'danish',
                        'it' => 'italian',
                        'pl' => 'polish',
                        'sk' => 'slovak',
                        'no' => 'norwegian',
                        'sv' => 'swedish',
                        'ar' => 'arabic'
                    );

                    // if we support the language, return it as the active one
                    if(isset($supported_language_codes[$code])){
                        return $supported_language_codes[$code];
                    }
                }
            }
        }

        return self::getSelectedLanguage();
    }

    public static function getProcessingBatchSize(){
        $batch_size = (int) get_option('linkilo_option_suggestion_batch_size', 300);
        if($batch_size < 10){
            $batch_size = 10;
        }
        return $batch_size;
    }

    /**
     * This function is used handle settting page submission
     *
     * @return  void
     */
    public static function save()
    {
        if (isset($_POST['linkilo_save_settings_nonce'])
            && wp_verify_nonce($_POST['linkilo_save_settings_nonce'], 'linkilo_save_settings')
            && isset($_POST['hidden_action'])
            && $_POST['hidden_action'] == 'linkilo_save_settings'
        ) {
            //prepare ignore words to save
            $ignore_words = sanitize_textarea_field(stripslashes(trim($_POST['ignore_words'])));
            if (!in_array($_POST['linkilo_selected_language'], array('polish', 'arabic'))) {
                $ignore_words = preg_split("/\R/", $ignore_words);
            } else {
                $ignore_words = explode("\n", $ignore_words);
            }
            $ignore_words = array_unique($ignore_words);
            $ignore_words = array_filter(array_map('trim', $ignore_words));
            sort($ignore_words);
            $ignore_words = implode(PHP_EOL, $ignore_words);

            //update ignore words
            update_option(LINKILO_WORDS_LIST_TO_IGNORE_OPTIONS, $ignore_words);

            // if the customer has manually selected the active GSC profile
            if( isset($_POST['linkilo_manually_select_gsc_profile']) && // only shows once GSC is activated
                !empty($_POST['linkilo_manually_select_gsc_profile']))
            {
                // get the GSC setting data
                $setting_data = get_option('linkilo_search_console_data', array());
                if(isset($setting_data['profiles'])){
                    $setting_data['profiles'] = array(sanitize_text_field($_POST['linkilo_manually_select_gsc_profile']));

                    $up = update_option('linkilo_search_console_data', $setting_data);
                    if($up){
                        set_transient('linkilo_gsc_access_status_message', __('The connection has been verified! Please go to the Focus Keyword report and refresh the keywords.', 'linkilo'), 60);
        
                        if(!empty($response['access_valid'])){
                            update_option('linkilo_gsc_app_authorized', true);
                        }
                    }
                }
            }

            // save the API tokens if an access key is supplied
            $access_status = '';
            if( isset($_POST['linkilo_gsc_access_code']) && !empty(trim($_POST['linkilo_gsc_access_code']))){
                $response = Linkilo_Build_GoogleSearchConsole::get_access_token(trim($_POST['linkilo_gsc_access_code']));
                $access_status = (!empty($response['access_valid'])) ? '&access_valid=1': '&access_valid=0';
                set_transient('linkilo_gsc_access_status_message', $response['message'], 60);

                if(!empty($response['access_valid'])){
                    update_option('linkilo_gsc_app_authorized', true);
                }
            }

            if( isset($_POST['linkilo_gsc_custom_app_name']) &&
                isset($_POST['linkilo_gsc_custom_client_id']) && 
                isset($_POST['linkilo_gsc_custom_client_secret']) && 
                !empty($_POST['linkilo_gsc_custom_app_name']) &&
                !empty($_POST['linkilo_gsc_custom_client_id']) && 
                !empty($_POST['linkilo_gsc_custom_client_secret']))
            {
                $config = array('application_name'  => sanitize_text_field($_POST['linkilo_gsc_custom_app_name']), 
                                'client_id'         => sanitize_text_field($_POST['linkilo_gsc_custom_client_id']), 
                                'client_secret'     => sanitize_text_field($_POST['linkilo_gsc_custom_client_secret']));

                $response = Linkilo_Build_GoogleSearchConsole::save_custom_auth_config($config);
                $access_status  = (!empty($response)) ? '&access_valid=1': '&access_valid=0';
                $save_message   = (!empty($response)) ? 'Your Google app credentials have been saved! Please scroll down and authorize the connection to your app.': 'There was an error in saving the app credentials.';
                set_transient('linkilo_gsc_access_status_message', $save_message, 60);
            }

            if (empty($_POST[LINKILO_SELECTED_POST_TYPES_OPTIONS]))
            {
                $_POST[LINKILO_SELECTED_POST_TYPES_OPTIONS] = [];
            }

            /*related meta posts*/
            if (empty($_POST[LINKILO_RELATE_META_POST_TYPES_OPTIONS]))
            {
                $_POST[LINKILO_RELATE_META_POST_TYPES_OPTIONS] = [];
            }

            if (empty($_POST[LINKILO_RELATE_META_POST_DISPLAY_LIMIT_OPTIONS]))
            {
                $_POST[LINKILO_RELATE_META_POST_DISPLAY_LIMIT_OPTIONS] = 10;
            }

            if (empty($_POST[LINKILO_RELATE_META_POST_DISPLAY_ORDER_OPTIONS]))
            {
                $_POST[LINKILO_RELATE_META_POST_DISPLAY_ORDER_OPTIONS] = '';
            }

            if (!isset($_POST[LINKILO_RELATE_META_POST_ENABLE_DISABLE_OPTIONS]))
            {
                $_POST[LINKILO_RELATE_META_POST_ENABLE_DISABLE_OPTIONS] = '0';
            }

            if (empty($_POST[LINKILO_RELATE_META_POST_TYPES_INCLUDE_OPTIONS]))
            {
                $_POST[LINKILO_RELATE_META_POST_TYPES_INCLUDE_OPTIONS] = [];
            }

            
            /*related meta posts ends*/


            if (empty($_POST['linkilo_2_term_types'])) {
                $_POST['linkilo_2_term_types'] = [];
            }

            
            /*related meta posts*/
            // realted meta posts if the settings aren't set for showing all post types, remove all but the public ones
            if( ($_POST['linkilo_2_show_all_post_types'] == 1) &&
                isset($_POST['linkilo_relate_meta_post_types']) &&
                !empty($_POST['linkilo_relate_meta_post_types'])
            ) {
                $types_available = get_post_types(['public' => true]);
                foreach($_POST['linkilo_relate_meta_post_types'] as $key => $type){
                    if( 
                        (isset($types_available[$type])) && 
                        ($type == "post" || $type == "page")
                    ){
                        unset($_POST['linkilo_relate_meta_post_types'][$key]);
                    }
                }
            }

            if( ($_POST['linkilo_2_show_all_post_types'] == 1) &&
                isset($_POST['linkilo_relate_meta_post_types_include']) &&
                !empty($_POST['linkilo_relate_meta_post_types_include'])
            ) {
                $types_available = get_post_types(['public' => true]);
                foreach($_POST['linkilo_relate_meta_post_types_include'] as $key => $type){
                    if( 
                        (isset($types_available[$type])) && 
                        ($type == "post" || $type == "page")
                    ){
                        unset($_POST['linkilo_relate_meta_post_types_include'][$key]);
                    }
                }
            }

            /*related meta posts ends*/

            // if the settings aren't set for showing all post types, remove all but the public ones
            if( empty($_POST['linkilo_2_show_all_post_types']) &&
                isset($_POST['linkilo_2_post_types']) &&
                !empty($_POST['linkilo_2_post_types']))
            {
                $types_available = get_post_types(['public' => true]);
                foreach($_POST['linkilo_2_post_types'] as $key => $type){
                    if(!isset($types_available[$type])){
                        unset($_POST['linkilo_2_post_types'][$key]);
                    }
                }
            }

            //save other settings
            $opt_keys = self::$keys;
            foreach($opt_keys as $opt_key) {
                if (array_key_exists($opt_key, $_POST)) {
                    update_option($opt_key, $_POST[$opt_key]);
                }
            }

            // make sure the external data table is created when external linking is activated
            if(array_key_exists('linkilo_link_external_sites', $_POST) && !empty($_POST['linkilo_link_external_sites'])){
                Linkilo_Build_ConnectMultipleSite::create_data_table();
            }

            // clear the item caches if they're set
            delete_transient('linkilo_ignore_links');
            delete_transient('linkilo_ignore_keywords_posts');
            delete_transient('linkilo_ignore_categories');
            delete_transient('linkilo_domains_marked_as_internal');
            delete_transient('linkilo_suggest_to_outgoing_posts');
            delete_transient('linkilo_ignore_acf_fields');

            wp_redirect(admin_url('admin.php?page=linkilo_settings&success' . $access_status));
            exit;
        }
    }

    public static function getSkipSentences()
    {
        return get_option('linkilo_skip_sentences', 3);
    }

    /**
     * Checks to see if the site has a translation plugin active
     * 
     * @return bool
     **/
    public static function translation_enabled(){
        if(defined('POLYLANG_VERSION')){
            return true;
        }elseif(self::wpml_enabled()){
            return true;
        }

        return false;
    }

    /**
     * Check if WPML installed and has at least 2 languages
     *
     * @return bool
     */
    public static function wpml_enabled()
    {
        global $wpdb;

        // if WPML is activated
        if(function_exists('icl_object_id') || class_exists('SitePress')){
            $languages_count = 1;
            $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}icl_languages'");
            if ($table == $wpdb->prefix . 'icl_languages') {
                $languages_count = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}icl_languages WHERE active = 1");
            } else {
                $languages_count = $wpdb->get_var("SELECT count(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'language'");
            }

            if (!empty($languages_count) && $languages_count > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get checked term types
     *
     * @return array
     */
    public static function getTermTypes()
    {
        return get_option('linkilo_2_term_types', []);
    }

    /**
     * Get ignore posts
     * Pulls posts from cache if available to save processing time.
     *
     * @return array
     */
    public static function getIgnorePosts()
    {
        $posts = get_transient('linkilo_ignore_links');
        if(empty($posts)){
            $posts = [];
            $links = get_option('linkilo_ignore_links');
            $links = explode("\n", $links);
            foreach ($links as $link) {
                $post = Linkilo_Build_Feed::getPostByLink($link);
                if (!empty($post)) {
                    $posts[] = $post->type . '_' . $post->id;
                }
            }

            set_transient('linkilo_ignore_links', $posts, 15 * MINUTE_IN_SECONDS);
        }

        return $posts;
    }

    /**
     * Get ignore post links
     *
     * @return array
     */
    public static function getIgnoreLinks()
    {
        $links = get_option('linkilo_ignore_links', array());
        $links = explode("\n", $links);

        return $links;
    }

    /**
     * Get ignore posts
     *
     * @return array
     */
    public static function getIgnoreKeywordsPosts()
    {
        $posts = get_transient('linkilo_ignore_keywords_posts');
        if(empty($posts)){
            $posts = [];
            $links = get_option('linkilo_ignore_keywords_posts');
            $links = explode("\n", $links);
            foreach ($links as $link) {
                $post = Linkilo_Build_Feed::getPostByLink($link);
                if (!empty($post)) {
                    $posts[] = $post->type . '_' . $post->id;
                }
            }

            set_transient('linkilo_ignore_keywords_posts', $posts, 15 * MINUTE_IN_SECONDS);
        }

        return $posts;
    }

    /**
     * Get ignored orphaned posts
     * Used in the link records page
     *
     * @return array
     */
    public static function getIgnoreOrphanedPosts()
    {
        $posts = [];
        $links = get_option('linkilo_ignore_stray_feeds');
        $links = explode("\n", $links);
        foreach ($links as $link) {
            $post = Linkilo_Build_Feed::getPostByLink($link);
            if (!empty($post)) {
                $posts[] = $post->type . '_' . $post->id;
            }
        }

        return $posts;
    }

    /**
     * Get categories list to be ignored
     *
     * @return array
     */
    public static function getIgnoreCategoriesPosts()
    {
        $posts = get_transient('linkilo_ignore_categories');
        if(empty($posts)){
            $posts = [];
            $links = get_option('linkilo_ignore_categories', '');
            $links = explode("\n", $links);
            foreach ($links as $link) {
                $category = Linkilo_Build_Feed::getPostByLink(trim($link));
                if (!empty($category)) {
                    $posts = array_merge($posts, Linkilo_Build_Feed::getCategoryPosts($category->id));
                }
            }

            set_transient('linkilo_ignore_categories', $posts, 15 * MINUTE_IN_SECONDS);
        }

        return $posts;
    }

    /**
     * Gets an array of post ids to affirmatively make outgoing links to.
     *
     * @return array
     */
    public static function getOutboundSuggestionPostIds()
    {
        $posts = get_transient('linkilo_suggest_to_outgoing_posts');
        if(empty($posts)){
            $posts = [];
            $links = get_option('linkilo_suggest_to_outgoing_posts', '');
            $links = explode("\n", $links);
            foreach ($links as $link) {
                $post = Linkilo_Build_Feed::getPostByLink($link);
                if (!empty($post)) {
                    $posts[] = $post->type . '_' . $post->id;
                }
            }

            if(empty($posts)){
                $posts = 'no-posts';
            }

            set_transient('linkilo_suggest_to_outgoing_posts', $posts, 15 * MINUTE_IN_SECONDS);
        }

        // if there are no posts
        if($posts === 'no-posts'){
            // return an empty array
            $posts = array();
        }

        return $posts;
    }

    /**
     * Gets an array of type specific ids from the url input settings.
     */
    public static function getItemTypeIds($ids = array(), $type = 'post'){
        if($type === 'post'){
            $ids = array_map(function($id){ if(false !== strpos($id, 'post_')){ return substr($id, 5); }else{ return false;} }, $ids);
            $ids = array_filter($ids);
        }else{
            $ids = array_map(function($id){ if(false !== strpos($id, 'term_')){ return substr($id, 5); }else{ return false;} }, $ids);
            $ids = array_filter($ids);
        }

        return $ids;
    }

    //Check if need to show ALL links
    public static function showAllLinks()
    {
        return !empty(get_option('linkilo_show_all_links'));
    }

    /**
     * Check if need to show full HTML in suggestions
     *
     * @return bool
     */
    public static function fullHTMLSuggestions()
    {
        return !empty(get_option('linkilo_full_html_suggestions'));
    }

    /**
     * Get links that was marked as external
     *
     * @return array
     */
    public static function getMarkedAsExternalLinks()
    {
        $links = get_option('linkilo_marked_as_external', '');

        if (!empty($links)) {
            $links = explode("\n", $links);
            foreach ($links as $key => $link) {
                $links[$key] = trim($link);
            }

            return $links;
        }

        return [];
    }

    /**
     * Gets an array of ACF fields that the user wants to ignore from processing
     **/
    public static function getIgnoredACFFields(){
        $field_data = get_transient('linkilo_ignore_acf_fields');
        if(empty($field_data)){
            $field_data = get_option('linkilo_ignore_acf_fields', array());

            if(is_string($field_data)){
                $field_data = array_map('trim', explode("\n", $field_data));
            }

            set_transient('linkilo_ignore_acf_fields', $field_data, 60 * MINUTE_IN_SECONDS);
        }

        return $field_data;
    }

    /**
     * Gets a list of posts that have had redirects applied to their urls.
     * Obtains the redirect list from plugins that offer redirects.
     * Results are cached for 5 minutes
     * 
     * @param bool $flip Should we return a flipped array of post ids so they can be searched easily?
     * @return array $post_ids And array of posts that have had redirections applied to them
     **/
    public static function getRedirectedPosts($flip = false){
        global $wpdb;

        $post_ids = get_transient('linkilo_redirected_post_ids');

        if(!empty($post_ids)){
            // refresh the transient
            set_transient('linkilo_redirected_post_ids', $post_ids, 5 * MINUTE_IN_SECONDS);
            // and return the ids
            return ($flip) ? array_flip($post_ids) : $post_ids;
        }

        // set up the id array
        $post_ids = array();

        if(defined('RANK_MATH_VERSION')){
            $dest_url_cache = array();

            $permalink_format = get_option('permalink_structure', '');
            $post_name_position = false;

            if(false !== strpos($permalink_format, '%postname%')){
                $pieces = explode('/', $permalink_format);
                $piece_count = count($pieces);
                $post_name_position = array_search('%postname%', $pieces);
            }

            // get the active redirect rules from Rank Math
            $active_redirections = $wpdb->get_results("SELECT `id`, `url_to` FROM {$wpdb->prefix}rank_math_redirections WHERE `status` = 'active'");

            // if there are redirections
            if(!empty($active_redirections)){
                
                $redirection_ids = array();
                foreach($active_redirections as $dat){
                    if(!isset($dest_url_cache[$dat->url_to])){
                        $id = url_to_postid($dat->url_to);
                        $dest_url_cache[$dat->url_to] = $id;
                    }

                    $redirection_ids[] = $dat->id;
                }

                // if there are posts with updated urls, get the ids so we can ignore them
                $ignore_posts = '';
                if(!empty($dest_url_cache)){
                    $ignore_posts = "AND `object_id` NOT IN (" . implode(', ',array_filter(array_values($dest_url_cache))) . ")";
                }

                $redirection_ids = implode(', ', $redirection_ids);
                $redirection_data = $wpdb->get_results("SELECT `from_url`, `object_id` FROM {$wpdb->prefix}rank_math_redirections_cache WHERE `redirection_id` IN ({$redirection_ids}) {$ignore_posts}"); // we're getting the redriects from the cache to save processing time. Rules based searching could take a long time

                // go over the data from the Rank Math cache
                $post_names = array();
                foreach($redirection_data as $dat){
                    // if a redirect was specified for a post, grab the id directly
                    if(isset($dat->object_id) && !empty($dat->object_id)){
                        $post_ids[] = $dat->object_id;
                    }else{
                        // if a url was redirected based on a rule, try to get the post name from the data so we can search the post table for it
                        $url_pieces = explode('/', $dat->from_url);
                        $url_pieces_count = count($url_pieces);

                        if($post_name_position && $url_pieces_count === $piece_count){  // if the url uses the permalink settings and therefor has the same number of pieces as the permalink string (EX: it's a post)
                            $post_names[] = $url_pieces[$post_name_position];
                        }elseif($url_pieces_count === 1){                               // if the url is just the slug
                            $post_names[] = $dat->from_url;
                        }elseif($url_pieces_count === 2 || $url_pieces_count === 3){    // if the url is just the slug, but there's a slash or two
                            $post_names[] = $url_pieces[1];
                        }
                    }
                    
                }

                // if we've found the post names
                if(!empty($post_names)){
                    // query the post table with them to get the post ids
                    $post_names = implode('\', \'', $post_names);
                    $ids = $wpdb->get_col("SELECT `ID` FROM {$wpdb->posts} WHERE `post_name` IN ('{$post_names}')");

                    // if there's ids
                    if(!empty($ids)){
                        // add them to the list of post ids that are redirected away from
                        $post_ids = array_merge($post_ids, $ids);
                    }
                }
            }
        }

        // save the fruits of our labours in the cache
        set_transient('linkilo_redirected_post_ids', $post_ids, 5 * MINUTE_IN_SECONDS);

        return ($flip && !empty($post_ids)) ? array_flip($post_ids) : $post_ids;
    }
}
