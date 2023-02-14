<?php

/**
 * Base controller
 */
class Linkilo_Build_Root
{
    public static $report_menu;

    /**
     * Register services
     */
    public function register()
    {
        add_action('admin_init', [$this, 'init']);
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('admin_enqueue_scripts', [$this, 'addScripts']);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_scripts'));
        add_action('plugin_action_links_' . LINKILO_PLUGIN_BASE_NAME, [$this, 'showSettingsLink']);
        add_action('upgrader_process_complete', [$this, 'upgrade_complete'], 10, 2);
        add_action('wp_ajax_get_recommended_url', ['Linkilo_Build_UrlRecommendation','ajax_get_recommended_url']);
        add_action('wp_ajax_linkilo_get_outer_site_recommendation', ['Linkilo_Build_UrlRecommendation', 'ajax_get_outer_site_recommendation']);
        add_action('wp_ajax_update_recommendation_display', ['Linkilo_Build_UrlRecommendation','ajax_update_recommendation_display']);
        add_action('wp_ajax_linkilo_csv_export', ['Linkilo_Build_CsvExport','ajax_csv']);
        add_action('wp_ajax_linkilo_clear_gsc_app_credentials', ['Linkilo_Build_GoogleSearchConsole','ajax_clear_custom_auth_config']);
        add_action('wp_ajax_linkilo_gsc_deactivate_app', ['Linkilo_Build_GoogleSearchConsole','ajax_disconnect']);
        add_filter('the_content', array(__CLASS__, 'open_links_in_new_tabs'));
        foreach(Linkilo_Build_AdminSettings::getPostTypes() as $post_type){
            add_filter("get_user_option_meta-box-order_{$post_type}", [$this, 'group_metaboxes'], 1000, 1 );
            add_filter($post_type . '_row_actions', array(__CLASS__, 'modify_list_row_actions'), 10, 2);
            //add_filter( "manage_{$post_type}_posts_columns", array(__CLASS__, 'add_columns'), 11 );
            add_action( "manage_{$post_type}_posts_custom_column", array(__CLASS__, 'columns_contents'), 11, 2);
        }

        foreach(Linkilo_Build_AdminSettings::getTermTypes() as $term_type){
            add_filter($term_type . '_row_actions', array(__CLASS__, 'modify_list_row_actions'), 10, 2); // we can only add the row actions. There's no modifying of the columns...
        }
    }

    /**
     * Initial function
     */
    function init()
    {
        $post = self::getPost();

        if (!empty($_GET['csv_export'])) {
            Linkilo_Build_CsvExport::csv();
        }

        if (!empty($_GET['type'])) { // if the current page has a "type" value
            $type = $_GET['type'];

            switch ($type) {
                case 'delete_link':
                    Linkilo_Build_PostUrl::delete();
                    break;
                case 'incoming_suggestions_page_container':
                    include LINKILO_PLUGIN_DIR_PATH . '/templates/incoming_recommendation_page_container.php';
                    exit;
                    break;
            }
        }

        if (!empty($_GET['area'])) {
            switch ($_GET['area']) {
                case 'linkilo_export':
                    Linkilo_Build_CsvExport::getInstance()->export($post);
                    break;
                case 'linkilo_excel_export':
                    $post = self::getPost();
                    if (!empty($post)) {
                        Linkilo_Build_SetExcelData::exportPost($post);
                    }
                    break;
            }
        }

        if (!empty($_POST['hidden_action'])) {
            switch ($_POST['hidden_action']) {
                case 'linkilo_save_settings':
                    Linkilo_Build_AdminSettings::save();
                    break;

                /*  Commented unusable code ref:license
                case 'activate_license':
                    Linkilo_Build_ActiveLicense::activate();
                    break;*/
            }
        }

        //add screen options
        add_action("load-" . self::$report_menu, function () {
            add_screen_option( 'report_options', array(
                'option' => 'report_options',
            ) );
        });
    }

    /**
     * This function is used for adding menu and submenus
     *
     *
     * @return  void
     */
    public function addMenu()
    {
        /*  Commented unusable code ref:license
        if (!Linkilo_Build_ActiveLicense::isValid()) {
            add_menu_page(
                __('Linkilo', 'linkilo'),
                __('Linkilo', 'linkilo'),
                'manage_categories',
                'linkilo_license',
                [Linkilo_Build_ActiveLicense::class, 'init'],
                plugin_dir_url(__DIR__).'../images/lw-icon-16x16.png'
            );

            return;
        }*/

        add_menu_page(
            __('Linkilo', 'linkilo'),
            __('Linkilo', 'linkilo'),
            'edit_posts',
            'linkilo',
            [Linkilo_Build_UrlRecord::class, 'init'],
            'dashicons-admin-links'
        );

        if(LINKILO_STATUS_HAS_RUN_SCAN){
            $page_title = __('Inner URLs Details', 'linkilo');
            $menu_title = __('Summary', 'linkilo');
        }else{
            $page_title = __('Inner URLs Details', 'linkilo');
            $menu_title = __('Complete Install', 'linkilo');
        }

        self::$report_menu = add_submenu_page(
            'linkilo',
            $page_title,
            $menu_title,
            'edit_posts',
            'linkilo',
            [Linkilo_Build_UrlRecord::class, 'init']
        );

        // add the advanced functionality if the first scan has been run
        if(!empty(LINKILO_STATUS_HAS_RUN_SCAN)){
            add_submenu_page(
                'linkilo',
                __('Add URLs', 'linkilo'),
                __('Add URLs', 'linkilo'),
                'edit_posts',
                'admin.php?page=linkilo&type=links'
            );

            $autolinks = add_submenu_page(
                'linkilo',
                __('Add New Auto Links', 'linkilo'),
                __('Add New Auto Links', 'linkilo'),
                'manage_categories',
                'linkilo_keywords',
                [Linkilo_Build_RelateUrlKeyword::class, 'init']
            );

            //add autolink screen options
            add_action("load-" . $autolinks, function () {
                add_screen_option( 'linkilo_keyword_options', array( // todo possibly update 'keywords' to 'autolink' to avoid confusion
                    'option' => 'linkilo_keyword_options',
                ) );
            });

            $focus_keywords = add_submenu_page(
                'linkilo',
                __('Focus Keyword', 'linkilo'),
                __('Focus Keyword', 'linkilo'),
                'manage_categories',
                'linkilo_focus_keywords',
                [Linkilo_Build_FocusKeyword::class, 'init']
            );

            //add focus keyword screen options
            add_action("load-" . $focus_keywords, function () {
                add_screen_option( 'focus_keyword_options', array(
                    'option' => 'focus_keyword_options',
                ) );
            });

            /*  Commented unusable code
            add_submenu_page(
                'linkilo',
                __('URL Changer', 'linkilo'),
                __('URL Changer', 'linkilo'),
                'manage_categories',
                'linkilo_url_changer',
                [Linkilo_Build_UrlReplace::class, 'init']
            );*/
        }
        add_submenu_page(
            'linkilo',
            __('Settings', 'linkilo'),
            __('Settings', 'linkilo'),
            'manage_categories',
            'linkilo_settings',
            [Linkilo_Build_AdminSettings::class, 'init']
        );
    }

    /**
     * Get post or term by ID from GET or POST request
     *
     * @return Linkilo_Build_Model_Feed|null
     */
    public static function getPost()
    {
        if (!empty($_REQUEST['term_id'])) {
            $post = new Linkilo_Build_Model_Feed((int)$_REQUEST['term_id'], 'term');
        } elseif (!empty($_REQUEST['post_id'])) {
            $post = new Linkilo_Build_Model_Feed((int)$_REQUEST['post_id']);
        } else {
            $post = null;
        }

        return $post;
    }

    /**
     * Show plugin version
     *
     * @return string
     */
    public static function showVersion()
    {
        $plugin_data = get_plugin_data(LINKILO_PLUGIN_DIR_PATH . 'linkilo.php');

        return "<p style='float: right'>version <b>".$plugin_data['Version']."</b></p>";
    }

    /**
     * Show extended error message
     *
     * @param $errno
     * @param $errstr
     * @param $error_file
     * @param $error_line
     */
    public static function handleError($errno, $errstr, $error_file, $error_line)
    {
        if (stristr($errstr, "WordPress could not establish a secure connection to WordPress.org")) {
            return;
        }

        $file = 'n/a';
        $func = 'n/a';
        $line = 'n/a';
        $debugTrace = debug_backtrace();
        if (isset($debugTrace[1])) {
            $file = isset($debugTrace[1]['file']) ? $debugTrace[1]['file'] : 'n/a';
            $line = isset($debugTrace[1]['line']) ? $debugTrace[1]['line'] : 'n/a';
        }
        if (isset($debugTrace[2])) {
            $func = $debugTrace[2]['function'] ? $debugTrace[2]['function'] : 'n/a';
        }

        $out = "call from <b>$file</b>, $func, $line";

        $trace = '';
        $bt = debug_backtrace();
        $sp = 0;
        foreach($bt as $k=>$v) {
            extract($v);

            $args = '';
            if (isset($v['args'])) {
                $args2 = array();
                foreach($v['args'] as $k => $v) {
                    if (!is_scalar($v)) {
                        $args2[$k] = "Array";
                    }
                    else {
                        $args2[$k] = $v;
                    }
                }
                $args = implode(", ", $args2);
            }

            $file = substr($file,1+strrpos($file,"/"));
            $trace .= str_repeat("&nbsp;",++$sp);
            $trace .= "file=<b>$file</b>, line=$line,
									function=$function(".
                var_export($args, true).")<br>";
        }

        $out .= $trace;

        echo "<b>Error:</b> [$errno] $errstr - $error_file:$error_line<br><br><hr><br><br>$out";
    }

    /**
     * Add meta box to the post edit page
     */
    public static function addMetaBoxes()
    {
        /*  Commented unusable code ref:license
        if (Linkilo_Build_ActiveLicense::isValid())
        {
            add_meta_box('linkilo_focus-keywords', 'Linkilo Focus Keyword', [Linkilo_Build_Root::class, 'showTargetKeywordsBox'], Linkilo_Build_AdminSettings::getPostTypes());
            add_meta_box('linkilo_link-articles', 'Linkilo Suggested Links', [Linkilo_Build_Root::class, 'showSuggestionsBox'], Linkilo_Build_AdminSettings::getPostTypes());
        }*/
        /*Old label : Linkilo Focus Keyword*/
        add_meta_box('linkilo_focus-keywords', 'Linkilo Settings', [Linkilo_Build_Root::class, 'showTargetKeywordsBox'], Linkilo_Build_AdminSettings::getPostTypes());
        add_meta_box('linkilo_link-articles', 'Linkilo Suggested Links', [Linkilo_Build_Root::class, 'showSuggestionsBox'], Linkilo_Build_AdminSettings::getPostTypes());
    }

    /**
     * Show meta box on the post edit page
     */
    public static function showSuggestionsBox()
    {
        $post_id = isset($_REQUEST['post']) ? (int)$_REQUEST['post'] : '';
        $user = wp_get_current_user();
        $manually_trigger_suggestions = !empty(get_option('linkilo_manually_trigger_suggestions', false));
        if ($post_id) {
            include LINKILO_PLUGIN_DIR_PATH . '/templates/url_recommend_list.php';
        }
    }

    /**
     * Show the focus keyword metabox on the post edit screen
     */
    public static function showTargetKeywordsBox()
    {
        $post_id = isset($_REQUEST['post']) ? (int)$_REQUEST['post'] : '';
        $user = wp_get_current_user();
        if ($post_id) {
            $keyword_sources = Linkilo_Build_FocusKeyword::get_active_keyword_sources();
            $keywords = Linkilo_Build_FocusKeyword::get_keywords_by_post_ids($post_id);
            $post = new Linkilo_Build_Model_Feed($post_id, 'post');
            $is_metabox = true;
            include LINKILO_PLUGIN_DIR_PATH . '/templates/focus_keyword_list.php';
        }
    }

    /**
     * Makes sure the link suggestions and the focus keyword metaboxes are in the same general grouping
     **/
    public static function group_metaboxes($option){
        // if there are no grouping settings, exit
        if(empty($option)){
            return $option;
        }

        $has_focus_keyword = false;
        $suggestion_box = '';
        foreach($option as $position => $boxes){
            if(false !== strpos($boxes, 'linkilo_focus-keywords')){
                $has_focus_keyword = true;
            }

            if(false !== strpos($boxes, 'linkilo_link-articles')){
                $suggestion_box = $position;
            }
        }
        
        // if the focus keyword box hasn't been set yet, but the suggestion box has
        if(empty($has_focus_keyword) && !empty($suggestion_box)){
            // place the focus keyword box above the suggestion box
            $option[$suggestion_box] = str_replace('linkilo_link-articles', 'linkilo_focus-keywords,linkilo_link-articles', $option[$suggestion_box]);
        }

        return $option;
    }

    /**
     * Add scripts to the admin panel
     *
     * @param $hook
     */
    public static function addScripts($hook)
    {
        if (strpos($_SERVER['REQUEST_URI'], '/post.php') !== false || strpos($_SERVER['REQUEST_URI'], '/term.php') !== false || (!empty($_GET['page']) && $_GET['page'] == 'linkilo')) {
            wp_enqueue_editor();
        }

        wp_register_script('linkilo_sweetalert_script_min', LINKILO_PLUGIN_DIR_URL . 'js/sweetalert.min.js', array('jquery'), $ver=false, $in_footer=true);
        wp_enqueue_script('linkilo_sweetalert_script_min');

        $js_path = 'js/linkilo_admin.js';
        $f_path = LINKILO_PLUGIN_DIR_PATH.$js_path;
        $ver = filemtime($f_path);
        $current_screen = get_current_screen();

        wp_register_script('linkilo_admin_script', LINKILO_PLUGIN_DIR_URL.$js_path, array('jquery'), $ver, $in_footer=true);
        wp_enqueue_script('linkilo_admin_script');

        if (isset($_GET['page']) && $_GET['page'] == 'linkilo' && isset($_GET['type']) && ($_GET['type'] == 'incoming_suggestions_page' || $_GET['type'] == 'click_details_page') || 
            (!empty($current_screen) && ('post' === $current_screen->base || 'page' === $current_screen->base))
            
        ){
            wp_register_style('linkilo_daterange_picker_css', LINKILO_PLUGIN_DIR_URL . 'css/daterangepicker.css');
            wp_enqueue_style('linkilo_daterange_picker_css');
            wp_register_script('linkilo_moment', LINKILO_PLUGIN_DIR_URL . 'js/moment.js', array('jquery'), $ver, $in_footer = true);
            wp_enqueue_script('linkilo_moment');
            wp_register_script('linkilo_daterange_picker', LINKILO_PLUGIN_DIR_URL . 'js/daterangepicker.js', array('jquery', 'linkilo_moment'), $ver, $in_footer = true);
            wp_enqueue_script('linkilo_daterange_picker');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'linkilo' && isset($_GET['type']) && $_GET['type'] == 'links') {
            wp_register_script('linkilo_url_record', LINKILO_PLUGIN_DIR_URL . 'js/linkilo_url_record.js', array('jquery'), $ver, $in_footer = true);
            wp_enqueue_script('linkilo_url_record');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'linkilo' && isset($_GET['type']) && $_GET['type'] == 'error') {
            wp_register_script('linkilo_broken_url_error', LINKILO_PLUGIN_DIR_URL . 'js/linkilo_broken_url_error.js', array('jquery'), $ver, $in_footer = true);
            wp_enqueue_script('linkilo_broken_url_error');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'linkilo' && isset($_GET['type']) && $_GET['type'] == 'domains') {
            wp_register_script('linkilo_domains', LINKILO_PLUGIN_DIR_URL . 'js/linkilo_domains.js', array('jquery'), $ver, $in_footer = true);
            wp_enqueue_script('linkilo_domains');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'linkilo' && isset($_GET['type']) && ( $_GET['type'] == 'click_details_page' || $_GET['type'] == 'clicks')) {
            wp_register_script('linkilo_url_click', LINKILO_PLUGIN_DIR_URL . 'js/linkilo_url_click.js', array('jquery'), $ver, $in_footer = true);
            wp_enqueue_script('linkilo_url_click');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'linkilo_keywords') {
            wp_register_script('linkilo_relate_keyword', LINKILO_PLUGIN_DIR_URL . 'js/linkilo_relate_keyword.js', array('jquery', 'jquery-ui-autocomplete'), $ver, $in_footer=true);
            wp_enqueue_script('linkilo_relate_keyword');
        }

        /*  Commented unusable code
        if (isset($_GET['page']) && $_GET['page'] == 'linkilo_url_changer') {
            wp_register_script('linkilo_keyword', LINKILO_PLUGIN_DIR_URL . 'js/linkilo_url_changer.js', array('jquery'), $ver, $in_footer=true);
            wp_enqueue_script('linkilo_keyword');
        }*/

        if (isset($_GET['page']) && ($_GET['page'] == 'linkilo_focus_keywords' || $_GET['page'] == 'linkilo' && isset($_GET['type']) && $_GET['type'] === 'incoming_suggestions_page') || ('post' === $current_screen->base || 'term' === $current_screen->base) ) {
            wp_register_script('linkilo_focus_keyword', LINKILO_PLUGIN_DIR_URL . 'js/linkilo_focus_keyword.js', array('jquery'), $ver, $in_footer=true);
            wp_enqueue_script('linkilo_focus_keyword');
        }

        $js_path = 'js/linkilo_admin_settings.js';
        $f_path = LINKILO_PLUGIN_DIR_PATH.$js_path;
        $ver = filemtime($f_path);

        wp_register_script('linkilo_admin_settings_script', LINKILO_PLUGIN_DIR_URL.$js_path, array('jquery'), $ver, $in_footer=true);
        wp_enqueue_script('linkilo_admin_settings_script');

        $style_path = 'css/linkilo_admin.css';
        $f_path = LINKILO_PLUGIN_DIR_PATH.$style_path;
        $ver = filemtime($f_path);

        wp_register_style('linkilo_admin_style', LINKILO_PLUGIN_DIR_URL.$style_path, $deps=[], $ver);
        wp_enqueue_style('linkilo_admin_style');

        $disable_fonts = apply_filters('linkilo_disable_fonts', false); // we've only got one font ATM
        if(empty($disable_fonts)){
            $style_path = 'css/linkilo_fonts.css';
            $f_path = LINKILO_PLUGIN_DIR_PATH.$style_path;
            $ver = filemtime($f_path);
    
            wp_register_style('linkilo_admin_fonts', LINKILO_PLUGIN_DIR_URL.$style_path, $deps=[], $ver);
            wp_enqueue_style('linkilo_admin_fonts');            
        }

        $ajax_url = admin_url('admin-ajax.php');

        $script_params = [];
        $script_params['ajax_url'] = $ajax_url;
        $script_params['completed'] = __('completed', 'linkilo');
        $script_params['site_linking_enabled'] = (!empty(get_option('linkilo_link_external_sites', false))) ? 1: 0;

        $script_params["LINKILO_PREVIOUS_REPORT_RESET_DATE_TIME_OPTIONS"] = get_option(LINKILO_PREVIOUS_REPORT_RESET_DATE_TIME_OPTIONS);

        wp_localize_script('linkilo_admin_script', 'linkilo_ajax', $script_params);
    }

    /**
     * Enqueues the scripts to use on the frontend.
     **/
    public static function enqueue_frontend_scripts(){
        global $post;

        // TODO: Add an option to disable the frontend scripts.
        if(empty($post)){
            return;
        }

        // get if the links are to be opened in new tabs
        $open_with_js       = (!empty(get_option('linkilo_js_open_new_tabs', false))) ? 1: 0;
        $open_all_intrnl    = (!empty(get_option('linkilo_open_all_internal_new_tab', false))) ? 1: 0;
        $open_all_extrnl    = (!empty(get_option('linkilo_open_all_external_new_tab', false))) ? 1: 0;

        // and if the user has disabled click tracking
        $dont_track_clicks = (!empty(get_option('linkilo_disable_click_tracking', false))) ? 1: 0;

        // if none of them are, exit
        if( ($open_with_js == 0 || $open_all_intrnl == 0 && $open_all_extrnl == 0) && $dont_track_clicks == 1){
            return;
        }

        // put together the ajax variables
        $ajax_url = admin_url('admin-ajax.php');
        $script_params = [];
        $script_params['ajaxUrl'] = $ajax_url;
        $script_params['postId'] = $post->ID;
        $script_params['postType'] = (is_a($post, 'WP_Term')) ? 'term': 'post'; // todo find out if the post can be a term, or if it's always a post. // I need to know for link tracking on term pages...
        $script_params['openInternalInNewTab'] = $open_all_intrnl;
        $script_params['openExternalInNewTab'] = $open_all_extrnl;
        $script_params['disableClicks'] = $dont_track_clicks;
        $script_params['openLinksWithJS'] = $open_with_js;



        // output some actual localizations
        $script_params['clicksI18n'] = array(
            'imageNoText'   => __('Image in link: No Text', 'linkilo'),
            'imageText'     => __('Image Title: ', 'linkilo'),
            'noText'        => __('No Anchor Text Found', 'linkilo'),
        );

        // enqueue the frontend scripts
        $file_path = LINKILO_PLUGIN_DIR_PATH . 'js/frontend.js';
        $url_path  = LINKILO_PLUGIN_DIR_URL . 'js/frontend.js';
        wp_enqueue_script('linkilo-frontend-script', $url_path, array('jquery'), filemtime($file_path), true);

        // output the ajax variables
        wp_localize_script('linkilo-frontend-script', 'linkiloFrontend', $script_params);
    }

    /**
     * Show settings link on the plugins page
     *
     * @param $links
     * @return array
     */
    public static function showSettingsLink($links)
    {
        $links[] = '<a href="admin.php?page=linkilo_settings">Settings</a>';

        return $links;
    }

    /**
     * Loads default Linkilo settings in to database on plugin activation.
     */
    public static function activate()
    {
        // only set default option values if the options are empty
        /*  Commented unusable code ref:license
        if('' === get_option(LINKILO_STATUS_OF_LICENSE_OPTION, '')){
            update_option(LINKILO_STATUS_OF_LICENSE_OPTION, '');
        }
        if('' === get_option(LINKILO_LICENSE_KEY_OPTION, '')){
            update_option(LINKILO_LICENSE_KEY_OPTION, '');
        }
        if('' === get_option(LINKILO_CURRENT_LICENSE_DATA_OPTION, '')){
            update_option(LINKILO_CURRENT_LICENSE_DATA_OPTION, '');
        }*/
        if('' === get_option(LINKILO_NUMBERS_TO_IGNORE_OPTIONS, '')){
            update_option(LINKILO_NUMBERS_TO_IGNORE_OPTIONS, '1');
        }
        if('' === get_option(LINKILO_SELECTED_POST_TYPES_OPTIONS, '')){
            update_option(LINKILO_SELECTED_POST_TYPES_OPTIONS, ['post', 'page']);
        }

        /*related meta posts*/
        if('' === get_option(LINKILO_RELATE_META_POST_TYPES_OPTIONS, '')){
            update_option(LINKILO_RELATE_META_POST_TYPES_OPTIONS, ['post', 'page']);
        }

        if('' === get_option(LINKILO_RELATE_META_POST_DISPLAY_LIMIT_OPTIONS, '')){
            update_option(LINKILO_RELATE_META_POST_DISPLAY_LIMIT_OPTIONS, 10);
        }

        if('' === get_option(LINKILO_RELATE_META_POST_DISPLAY_ORDER_OPTIONS, '')){
            update_option(LINKILO_RELATE_META_POST_DISPLAY_ORDER_OPTIONS, '');
        }

        if('' === get_option(LINKILO_RELATE_META_POST_ENABLE_DISABLE_OPTIONS, '')){
            update_option(LINKILO_RELATE_META_POST_ENABLE_DISABLE_OPTIONS, '0');
        }

        if('' === get_option(LINKILO_RELATE_META_POST_TYPES_INCLUDE_OPTIONS, '')){
            update_option(LINKILO_RELATE_META_POST_TYPES_INCLUDE_OPTIONS, ['post', 'page']);
        }
        /*related meta posts ends*/
        
        if('' === get_option(LINKILO_OPEN_LINKS_IN_NEW_TAB_OPTION, '')){
            update_option(LINKILO_OPEN_LINKS_IN_NEW_TAB_OPTION, '0');
        }
        if('' === get_option(LINKILO_CHECK_DEBUG_MODE_OPTION, '')){
            update_option(LINKILO_CHECK_DEBUG_MODE_OPTION, '0');
        }
        if('' === get_option(LINKILO_UPDATE_REPORT_AT_SAVE_OPTIONS, '')){
            update_option(LINKILO_UPDATE_REPORT_AT_SAVE_OPTIONS, '0');
        }
        if('' === get_option(LINKILO_WORDS_LIST_TO_IGNORE_OPTIONS, '')){
            $ignore = "-\r\n" . implode("\r\n", Linkilo_Build_AdminSettings::getIgnoreWords()) . "\r\n-";
            update_option(LINKILO_WORDS_LIST_TO_IGNORE_OPTIONS, $ignore);
        }
        if('' === get_option(LINKILO_IS_LINKS_TABLE_CREATED, '')){
            Linkilo_Build_UrlRecord::setupLinkiloLinkTable(true);
            // if the plugin is activating and the link table isn't set up, assume this is a fresh install
            update_option('linkilo_fresh_install', true); // the link table was created with ver 0.8.3 and was the first major table event, so it should be a safe test for new installs
        }
        if('' === get_option('linkilo_install_date', '')){
            // set the install date since it may come in handy
            update_option('linkilo_install_date', current_time('mysql', true));
        }

        Linkilo_Build_PostUrl::removeLinkClass();

        self::createDatabaseTables();
        self::updateTables();
    }

    /**
     * Runs any update routines after the plugin has been updated.
     */
    public static function upgrade_complete($upgrader_object, $options){
        // If an update has taken place and the updated type is plugins and the plugins element exists
        if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
            // Go through each plugin to see if Linkilo was updated
            foreach( $options['plugins'] as $plugin ) {
                if( $plugin == LINKILO_PLUGIN_BASE_NAME ) {
                    // create any tables that need creating
                    self::createDatabaseTables();
                    // and make sure the existing tables are up to date
                    self::updateTables();
                }
            }
        }
    }

    /**
     * Updates the existing LW data tables with changes as we add them.
     * Does a version check to see if any DB tables have been updated since the last time this was run.
     */
    public static function updateTables(){
        global $wpdb;

        $fresh_install = get_option('linkilo_fresh_install', false);

        // if the DB is up to date, exit
        if(LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE === LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_PLUGIN){
            return;
        }

        // if this is a fresh install of the plugin
        if($fresh_install && empty(LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE)){
            // set the DB version as the latest since all the created tables will be up to date
            update_option('linkilo_site_db_version', LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_PLUGIN);
            update_option('linkilo_fresh_install', false);
            // and exit
            return;
        }

        if((float)LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE < 0.9){
            // Added in v1.0.0
            // if the error links table exists
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_broken_links'");
            if(!empty($error_tbl_exists)){
                // find out if the table has a last_checked col
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_broken_links LIKE 'last_checked'");
                if(empty($col)){
                    // if it doesn't, add it and a check_count col to the table
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_broken_links ADD COLUMN check_count INT(2) DEFAULT 0 AFTER created, ADD COLUMN last_checked DATETIME NOT NULL DEFAULT NOW() AFTER created";
                    $wpdb->query($update_table);
                }
            }

            // update the state of the DB to this point
            update_option('linkilo_site_db_version', '0.9');
        }

        // if the current DB version is less than 1.0, run the 1.0 update
        if((float)LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE < 1.0){
            /** added in v1.0.1 **/
            // if the error links table exists
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_broken_links'");
            if(!empty($error_tbl_exists)){
                // find out if the table has a ignore_link col
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_broken_links LIKE 'ignore_link'");
                if(empty($col)){
                    // if it doesn't, update it with the "ignore_link" column
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_broken_links ADD COLUMN ignore_link tinyint(1) DEFAULT 0 AFTER `check_count`";
                    $wpdb->query($update_table);
                }
            }

            // update the state of the DB to this point
            update_option('linkilo_site_db_version', '1.0');
        }

        if((float)LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE < 1.16){
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_broken_links'");
            if(!empty($error_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_broken_links LIKE 'sentence'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_broken_links ADD COLUMN sentence varchar(1000) AFTER `ignore_link`";
                    $wpdb->query($update_table);
                }
            }

            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_report_links'");
            if(!empty($error_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_report_links LIKE 'location'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_report_links ADD COLUMN location varchar(20) AFTER `post_type`";
                    $wpdb->query($update_table);
                }
            }

            update_option('linkilo_site_db_version', '1.16');
        }

        if((float)LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE < 1.17){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_keyword_links'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_keyword_links LIKE 'anchor'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_keyword_links ADD COLUMN anchor text AFTER `post_type`";
                    $wpdb->query($update_table);
                }
            }

            update_option('linkilo_site_db_version', '1.17');
        }

        if((float)LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE < 1.18){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_keywords'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_keywords LIKE 'restrict_cats'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_keywords ADD COLUMN restrict_cats tinyint(1) DEFAULT 0 AFTER `link_once`";
                    $wpdb->query($update_table);
                }
            }

            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_keywords'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_keywords LIKE 'restricted_cats'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_keywords ADD COLUMN restricted_cats text AFTER `restrict_cats`";
                    $wpdb->query($update_table);
                }
            }

            update_option('linkilo_site_db_version', '1.18');
        }

        if((float)LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE < 1.19){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_keywords'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_keywords LIKE 'restrict_date'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_keywords ADD COLUMN restrict_date tinyint(1) DEFAULT 0 AFTER `link_once`";
                    $wpdb->query($update_table);
                }
            
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_keywords LIKE 'restricted_date'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_keywords ADD COLUMN restricted_date DATETIME AFTER `restrict_date`";
                    $wpdb->query($update_table);
                }
            }

            update_option('linkilo_site_db_version', '1.19');
        }

        if((float)LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE < 1.20){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_keywords'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_keywords LIKE 'select_links'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_keywords ADD COLUMN select_links tinyint(1) DEFAULT 0 AFTER `link_once`";
                    $wpdb->query($update_table);
                }
            }

            // make sure the possible links table is created too
            Linkilo_Build_RelateUrlKeyword::preparePossibleLinksTable();

            update_option('linkilo_site_db_version', '1.20');
        }

        if((float)LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE < 1.21){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_keywords'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_keywords LIKE 'set_priority'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_keywords ADD COLUMN set_priority tinyint(1) DEFAULT 0 AFTER `select_links`";
                    $wpdb->query($update_table);
                }
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_keywords LIKE 'priority_setting'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_keywords ADD COLUMN priority_setting int DEFAULT 0 AFTER `set_priority`";
                    $wpdb->query($update_table);
                }
            }

            update_option('linkilo_site_db_version', '1.21');
        }

        if((float)LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE < 1.22){
            $changed_urls_exist = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_url_links'");
            if(!empty($changed_urls_exist)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_url_links LIKE 'relative_link'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_url_links ADD COLUMN relative_link tinyint(1) DEFAULT 0 AFTER `anchor`";
                    $wpdb->query($update_table);
                }
            }

            update_option('linkilo_site_db_version', '1.22');
        }

        if((float)LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE < 1.23){
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_report_links'");
            if(!empty($error_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$wpdb->prefix}linkilo_report_links LIKE 'broken_link_scanned'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$wpdb->prefix}linkilo_report_links ADD COLUMN broken_link_scanned tinyint(1) DEFAULT 0 AFTER `location`";
                    $wpdb->query($update_table);
                }
            }

            update_option('linkilo_site_db_version', '1.23');
        }
    }


    /**
     * Modifies the post's row actions to add an "Add Incoming Links" button to the row actions.
     * Only adds the link to post types that we create links for.
     * 
     * @param $actions
     * @param $object
     * @return $actions
     **/
    public static function modify_list_row_actions( $actions, $object ) {
        $type = is_a($object, 'WP_Post') ? $object->post_type: $object->taxonomy;

        if(!in_array($type, Linkilo_Build_AdminSettings::getAllTypes())){
            return $actions;
        }

        $page = (isset($_GET['paged']) && !empty($_GET['paged'])) ? '&paged=' . (int)$_GET['paged']: '';

        if(is_a($object, 'WP_Post')){
            $actions['linkilo-add-incoming-links'] = '<a target=_blank href="' . admin_url("admin.php?post_id={$object->ID}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode(admin_url("edit.php?post_type={$type}{$page}&direct_return=1"))) . '">Add Incoming Links</a>';
        }else{
            global $wp_taxonomies;
            $post_type = $wp_taxonomies[$type]->object_type[0];
            $actions['linkilo-add-incoming-links'] = '<a target=_blank href="' . admin_url("admin.php?term_id={$object->term_id}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode(admin_url("edit-tags.php?taxonomy={$type}{$page}&post_type={$post_type}&direct_return=1"))) . '">Add Incoming Links</a>';
        }

        return $actions;
    }

	/**
	 * Add new columns for SEO title, description and focus keywords.
	 *
	 * @param array $columns Array of column names.
	 *
	 * @return array
	 */
	public static function add_columns($columns){
		global $post_type;

        if(!in_array($post_type, Linkilo_Build_AdminSettings::getPostTypes())){
            return $columns;
        }
        
		$columns['linkilo-link-stats'] = esc_html__('Link Stats', 'linkilo');

		return $columns;
	}

    /**
	 * Add content for custom column.
	 *
	 * @param string $column_name The name of the column to display.
	 * @param int    $post_id     The current post ID.
	 */
	public static function columns_contents($column_name, $post_id){
        if('linkilo-link-stats' === $column_name){
                $incoming_internal = (int)get_post_meta($post_id, 'linkilo_links_incoming_internal_count', true);
                $outgoing_internal = (int)get_post_meta($post_id, 'linkilo_links_outgoing_internal_count', true);
                $outgoing_external = (int)get_post_meta($post_id, 'linkilo_links_outgoing_external_count', true);
                $broken_links = Linkilo_Build_BrokenUrlError::getBrokenLinkCountByPostId($post_id);

                $post_type = get_post_type($post_id);
                $page = (isset($_GET['paged']) && !empty($_GET['paged'])) ? '&paged=' . (int)$_GET['paged']: '';
            ?>
            <span class="linkilo-link-stats-column-display linkilo-link-stats-content">
                <strong><?php _e('Links: ', 'linkilo'); ?></strong>
                <span title="<?php _e('Incoming Inner URLs', 'linkilo'); ?>"><a target=_blank href="<?php echo admin_url("admin.php?post_id={$post_id}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode(admin_url("admin.php/edit.php?post_type={$post_type}{$page}"))); ?>"><span class="dashicons dashicons-arrow-down-alt"></span><span><?php echo $incoming_internal; ?></span></a></span>
                <span class="divider"></span>
                <span title="<?php _e('Outgoing Inner URLs', 'linkilo'); ?>"><a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><span class="dashicons dashicons-external  <?php echo (!empty($outgoing_internal)) ? 'linkilo-has-outgoing': ''; ?>""></span> <span><?php echo $outgoing_internal; ?></span></a></span>
                <span class="divider"></span>
                <span title="<?php _e('Outgoing Outer URLs', 'linkilo'); ?>"><span class="dashicons dashicons-admin-site-alt3 <?php echo (!empty($outgoing_external)) ? 'linkilo-has-outgoing': ''; ?>"></span> <span><?php echo $outgoing_external; ?></span></span>
                <span class="divider"></span>
                <?php if(!empty($broken_links)){ ?>
                <span title="<?php _e('Broken Links', 'linkilo'); ?>"><a target=_blank href="<?php echo admin_url("admin.php?page=linkilo&type=error&post_id={$post_id}"); ?>"><span class="dashicons dashicons-editor-unlink broken-links"></span> <span><?php echo $broken_links; ?></span></a></span>
                <?php }else{ ?>
                <span title="<?php _e('Broken Links', 'linkilo'); ?>"><span class="dashicons dashicons-editor-unlink"></span> <span>0</span></span>
                <?php } ?>
            </span>
        <?php
        }
	}

    /**
     * Filters the post content to make links open in new tabs if they don't already.
     * Differentiates between internal and external links.
     * @param string $content 
     * @return string $content 
     **/
    public static function open_links_in_new_tabs($content = ''){

        $open_all_intrnl = !empty(get_option('linkilo_open_all_internal_new_tab', false));
        $open_all_extrnl = !empty(get_option('linkilo_open_all_external_new_tab', false));

        if($open_all_intrnl || $open_all_extrnl){
            preg_match_all( '/<(a\s[^>]*?href=[\'"]([^\'"]*?)[\'"][^>]*?)>/', $content, $matches );

            foreach($matches[0] as $key => $link){
                // if the link already opens in a new tab, skip to the next link
                if(false !== strpos($link, 'target="_blank"')){
                    continue;
                }

                $internal = Linkilo_Build_PostUrl::isInternal($matches[2][$key]);

                if($internal && $open_all_intrnl){
                    $new_link = str_replace($matches[1][$key], $matches[1][$key] . ' target="_blank"', $link);
                    $content = mb_ereg_replace(preg_quote($link), $new_link, $content);
                }elseif(!$internal && $open_all_extrnl){
                    $new_link = str_replace($matches[1][$key], $matches[1][$key] . ' target="_blank"', $link);
                    $content = mb_ereg_replace(preg_quote($link), $new_link, $content);
                }
            }
        }

        return $content;
    }

    public static function fixCollation($table)
    {
        global $wpdb;
        $table_status = $wpdb->get_results("SHOW TABLE STATUS where name like '$table'");
        if (empty($table_status[0]->Collation) || $table_status[0]->Collation != 'utf8mb4_unicode_ci') {
            $wpdb->query("alter table $table convert to character set utf8mb4 collate utf8mb4_unicode_ci");
        }
    }

    public static function verify_nonce($key)
    {
        $user = wp_get_current_user();
        if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $user->ID . $key)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'linkilo'),
                    'text'  => __('There was an error in processing the data, please reload the page and try again.', 'linkilo'),
                )
            ));
        }
    }

    /**
     * Removes a hooked function from the wp hook or filter.
     * We have to flip through the hooked functions because a lot of the methods use instantiated objects
     * 
     * @param string $tag The hook/filter name that the function is hooked to
     * @param string $object The object who's method we're removing from the hook/filter
     * @param string $function The object method that we're removing from the hook/filter
     * @param int $priority The priority of the function that we're removing
     **/
    public static function remove_hooked_function($tag, $object, $function, $priority){
        global $wp_filter;
        $priority = intval($priority);

        // if the hook that we're looking for does exist and at the priority we're looking for
        if( isset($wp_filter[$tag]) && 
            isset($wp_filter[$tag]->callbacks) && 
            !empty($wp_filter[$tag]->callbacks) &&
            isset($wp_filter[$tag]->callbacks[$priority]) && 
            !empty($wp_filter[$tag]->callbacks[$priority]))
        {
            // look over all the callbacks in the priority we're looking in
            foreach($wp_filter[$tag]->callbacks[$priority] as $key => $data)
            {
                // if the current item is the callback we're looking for
                if(isset($data['function']) && is_a($data['function'][0], $object) && $data['function'][1] === $function){
                    // remove the callback
                    unset($wp_filter[$tag]->callbacks[$priority][$key]);

                    // if there aren't any more callbacks, remove the priority setting too
                    if(empty($wp_filter[$tag]->callbacks[$priority])){
                        unset($wp_filter[$tag]->callbacks[$priority]);
                    }
                }
            }
        }
    }

    /**
     * Updates the WP option cache indepenently of the update_options functionality.
     * I've found that for some users the cache won't update and that keeps some option based processing from working.
     * The code is mostly pulled from the update_option function
     * 
     * @param string $option The name of the option that we're saving.
     * @param mixed $value The option value that we're saving.
     **/
    public static function update_option_cache($option = '', $value = ''){
        $option = trim( $option );
        if ( empty( $option ) ) {
            return false;
        }

        $serialized_value = maybe_serialize( $value );

        $alloptions = wp_load_alloptions( true );
        if ( isset( $alloptions[ $option ] ) ) {
            $alloptions[ $option ] = $serialized_value;
            wp_cache_set( 'alloptions', $alloptions, 'options' );
        } else {
            wp_cache_set( $option, $serialized_value, 'options' );
        }
    }

    /**
     * Deletes all Linkilo related data on plugin deletion
     **/
    public static function delete_linkilo_data(){
        global $wpdb;

        // if we're not really sure that the user wants to delete all data, exit
        if('1' !== get_option('linkilo_delete_all_data', false)){
            return;
        }

        // create a list of all possible tables
        $tables = array(
            "{$wpdb->prefix}linkilo_broken_links",
            "{$wpdb->prefix}linkilo_ignore_links",
            "{$wpdb->prefix}linkilo_url_click_data",
            "{$wpdb->prefix}linkilo_keywords",
            "{$wpdb->prefix}linkilo_keyword_links",
            "{$wpdb->prefix}linkilo_keyword_select_links",
            "{$wpdb->prefix}linkilo_report_links",
            "{$wpdb->prefix}linkilo_search_console_data",
            "{$wpdb->prefix}linkilo_site_linking_data",
            "{$wpdb->prefix}linkilo_focus_keyword_data",
            "{$wpdb->prefix}linkilo_urls",
            "{$wpdb->prefix}linkilo_url_links",
        );

        // go over the list of tables and delete all tables that exist
        foreach($tables as $table){
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if($table_exists === $table){
                $wpdb->query("DROP TABLE {$table}");
            }
        }

        // get the settings
        $settings = array(
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
            // and the other options
            // 'linkilo_2_license_status',    Commented unusable code ref:license
            // 'linkilo_2_license_key',       Commented unusable code ref:license
            // 'linkilo_2_license_data',      Commented unusable code ref:license
            'linkilo_2_ignore_words',
            'linkilo_has_run_initial_scan',
            'linkilo_site_db_version',
            'linkilo_link_table_is_created',
            'linkilo_fresh_install',
            'linkilo_install_date',
            // 'linkilo_2_license_check_time',    Commented unusable code ref:license
            // 'linkilo_2_license_last_error',    Commented unusable code ref:license
            'linkilo_post_procession',
            'linkilo_error_reset_run',
            'linkilo_error_check_links_cron',
            'linkilo_keywords_add_same_link',
            'linkilo_keywords_link_once',
            'linkilo_keywords_select_links',
            'linkilo_keywords_set_priority',
            'linkilo_keywords_restrict_to_cats',
            'linkilo_search_console_data',
            'linkilo_gsc_app_authorized',
            'linkilo_2_report_last_updated',
            'linkilo_cached_valid_sites',
            'linkilo_registered_sites',
            'linkilo_linked_sites',
            'linkilo_url_changer_reset',
            'linkilo_keywords_reset',
        );

        // delete each one from the option table
        foreach($settings as $setting){
            delete_option($setting);
        }
    }

    /**
     * Checks to see if we're over the time limit.
     * 
     * @param int $time_pad The amount of time in advance of the PHP time limit that is considered over the time limit
     * @param int $max_time The absolute time limit that we'll wait for the current process to complete
     * @return bool
     **/
    public static function overTimeLimit($time_pad = 0, $max_time = null){
        $limit = ini_get( 'max_execution_time' );

        // if there is no limit
        if(empty($limit) || $limit === '-1'){
            // create a self imposed limit so the user know LW is still working on looped actions
            $limit = 90;
        }

        // if the exit time pad is less than the limit
        if($limit < $time_pad){
            // default to a 5 second pad
            $time_pad = 5;
        }

        // get the current time
        $current_time = microtime(true);

        // if we've been running for longer than the PHP time limit minus the time pad, OR
        // a max time has been set and we've passed it
        if( ($current_time - LINKILO_STATUS_PROCESSING_START) > ($limit - $time_pad) || 
            $max_time !== null && ($current_time - LINKILO_STATUS_PROCESSING_START) > $max_time)
        {
            // signal that we're over the time limit
            return true;
        }else{
            return false;
        }
    }

    /**
     * Creates the database tables so we're sure that they're all set.
     * I'll still use the old method of creation for a while as a fallback.
     * But this will make LW more plug-n-play
     **/
    public static function createDatabaseTables(){
        Linkilo_Build_UrlClickChecker::prepare_table();
        Linkilo_Build_BrokenUrlError::prepareTable(false);
        Linkilo_Build_BrokenUrlError::prepareIgnoreTable();
        Linkilo_Build_RelateUrlKeyword::prepareTable(); // also prepares the possible links table
        Linkilo_Build_FocusKeyword::prepareTable();
        Linkilo_Build_UrlReplace::prepareTable();

        // search console table not included because it's explicitly activated by the user
        // linked site data table also not included because it's explicitly activated by the user
    }
}