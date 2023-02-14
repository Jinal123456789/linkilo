<?php
/**
 * Plugin Name: Linkilo
 * Plugin URI: https://testingsiteslive-com.okyanust.com
 * Version: 1.0
 * Description: Set internal links in post for other posts and pages, check for report for each link and export data to excel.
 * Author: JK
 * Author URI: https://testingsiteslive-com.okyanust.com
 * Text Domain: linkilo
 */

/*  Commented unusable code
function removeDirectory($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                    removeDirectory($dir. DIRECTORY_SEPARATOR .$object);
                else
                    unlink($dir. DIRECTORY_SEPARATOR .$object);
            }
        }
        rmdir($dir);
    }
}

if (is_dir(ABSPATH . 'wp-content/plugins/linkilo/')) {
    $plugins = get_option('active_plugins');
    foreach ($plugins as $key => $plugin) {
        if ($plugin == 'linkilo/linkilo.php') {
            unset($plugins[$key]);
        }
    }
    update_option('active_plugins', $plugins);
    removeDirectory(ABSPATH . 'wp-content/plugins/linkilo/');
}*/

// remove the Free plugin's autoloader if it's present
/*$auto_loader_functions = spl_autoload_functions();
if(!empty($auto_loader_functions)){
    foreach($auto_loader_functions as $function){
        if($function === 'linkilo_autoloader'){
            spl_autoload_unregister( 'linkilo_autoloader' );
        }
    }
}*/

//autoloader
spl_autoload_register( 'linkilo_autoloader_advanced' );
function linkilo_autoloader_advanced( $class_name ) {
    if ( false !== strpos( $class_name, 'Linkilo' ) ) {
        $classes_dir = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR;
        $class_name = str_replace( '_Build', '', $class_name );
        $class_file = str_replace( '_', DIRECTORY_SEPARATOR, $class_name ) . '.php';
    // die($class_name . "<br><br>" . $classes_dir . "<br><br>" . $class_file);
        require_once $classes_dir . $class_file;
    }
}
define( 'LINKILO_SHOP_URL', 'https://testingsiteslive-com.okyanust.com');
define( 'LINKILO_PLUGIN_BASE_NAME', plugin_basename( __FILE__ ));
define( 'LINKILO_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
define( 'LINKILO_PLUGIN_DIR_URL', plugin_dir_url(__FILE__));
define( 'LINKILO_HIDE_OPTION', 'LINKILO_HIDE_OPTION');
define( 'LINKILO_PAIRS_MODE_OPTION', 'linkilo_2_ll_pairs_mode');
define( 'LINKILO_PAIRS_MODE_NO_OPTION', 'linkilo_2_ll_pairs_mode_no');
define( 'LINKILO_PAIRS_MODE_EXACT_OPTION', 'linkilo_2_ll_pairs_mode_exact');
define( 'LINKILO_PAIRS_MODE_ANYWHERE_OPTION', 'linkilo_2_ll_pairs_mode_anywhere');
define( 'LINKILO_WORDS_LIST_TO_IGNORE_OPTIONS', 'linkilo_2_ignore_words');
define( 'LINKILO_NUMBERS_TO_IGNORE_OPTIONS', 'linkilo_2_ignore_numbers');
define( 'LINKILO_CHECK_DEBUG_MODE_OPTION', 'linkilo_2_debug_mode');
define( 'LINKILO_UPDATE_REPORT_AT_SAVE_OPTIONS', 'linkilo_option_update_reporting_data_on_save');
define( 'LINKILO_CASCADE_UPDATING_OPTION_REDUCE', 'linkilo_option_reduce_cascade_updating');
define( 'LINKILO_DO_NOT_COUNT_INCOMING_LINKS_OPTIONS', 'linkilo_option_dont_count_incoming_links');
/*  Commented unusable code ref:license
define( 'LINKILO_LICENSE_KEY_OPTION', 'linkilo_2_license_key');
define( 'LINKILO_LICENSE_CHECK_TIME_OPTION', 'linkilo_2_license_check_time');
define( 'LINKILO_STATUS_OF_LICENSE_OPTION', 'linkilo_2_license_status');
define( 'LINKILO_CURRENT_LICENSE_DATA_OPTION', 'linkilo_2_license_data');
define( 'LINKILO_LAST_ERROR_FOR_LICENSE_OPTION', 'linkilo_2_license_last_error');*/
define( 'LINKILO_SELECTED_POST_TYPES_OPTIONS', 'linkilo_2_post_types');
define( 'LINKILO_RELATE_META_POST_TYPES_OPTIONS', 'linkilo_relate_meta_post_types'); //related meta posts
define( 'LINKILO_RELATE_META_POST_DISPLAY_LIMIT_OPTIONS', 'linkilo_relate_meta_post_display_limit'); //related meta posts
define( 'LINKILO_RELATE_META_POST_DISPLAY_ORDER_OPTIONS', 'linkilo_relate_meta_post_display_order'); //related meta posts
define( 'LINKILO_RELATE_META_POST_ENABLE_DISABLE_OPTIONS', 'linkilo_relate_meta_post_enable_disable'); //related meta posts
define( 'LINKILO_RELATE_META_POST_TYPES_INCLUDE_OPTIONS', 'linkilo_relate_meta_post_types_include'); //related meta posts
define( 'LINKILO_OPEN_LINKS_IN_NEW_TAB_OPTION', 'linkilo_2_links_open_new_tab');
define( 'LINKILO_PREVIOUS_REPORT_RESET_DATE_TIME_OPTIONS', 'linkilo_2_report_last_updated');
define( 'LINKILO_VERSION_DEVLOP_DATE', '18-July-2019');
define( 'LINKILO_SHOW_DEVELOP_DETAILS', true);
define( 'LINKILO_TOTAL_COUNT_OF_OUTGOING_INTERNAL_LINKS', 'linkilo_links_outgoing_internal_count');
define( 'LINKILO_TOTAL_COUNT_OF_INCOMING_INTERNAL_LINKS', 'linkilo_links_incoming_internal_count');
define( 'LINKILO_TOTAL_COUNT_OF_OUTGOING_EXTERNAL_LINKS', 'linkilo_links_outgoing_external_count');
define( 'LINKILO_SYNC_POST_META_KEY', 'linkilo_sync_report3');
define( 'LINKILO_SYNC_POST_META_KEY_TIME', 'linkilo_sync_report2_time');
define( 'LINKILO_POST_META_KEY_ADD_LINKS', 'linkilo_add_links');
define( 'LINKILO_IS_LINKS_TABLE_CREATED', 'linkilo_link_table_is_created');
define( 'LINKILO_IS_LINKS_TABLE_EXISTS', get_option(LINKILO_IS_LINKS_TABLE_CREATED, false));
define( 'LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_PLUGIN', '1.23');  // simple version counter that gets incremented when we change the existing DB tables. That way update_tables knows when and what to update.
define( 'LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE', get_option('linkilo_site_db_version', '0'));  // existing DB version on this site
define( 'LINKILO_STATUS_PROCESSING_START', microtime(true));
define( 'LINKILO_STATUS_HAS_RUN_SCAN', get_option('linkilo_has_run_initial_scan', false));


define( 'LINKILO_DEBUG_CURL', false); // should be activated only for short term use, can generate huge log files

Linkilo_Build_Initialize::register_services();

register_activation_hook(__FILE__, [Linkilo_Build_Root::class, 'activate'] );
register_uninstall_hook(__FILE__, array(Linkilo_Build_Root::class, 'delete_linkilo_data'));

if (is_admin())
{
    /*  Commented unusable code ref:license
    if(!class_exists( 'EDD_SL_Plugin_Updater'))
    {
        // load our custom updater if it doesn't already exist
        include (dirname(__FILE__).'/vendor/EDD_SL_Plugin_Updater.php');
    }*/

    if(!function_exists('get_plugin_data'))
    {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    /*  Commented unusable code ref:license
    if (Linkilo_Build_ActiveLicense::isValid()) {

        $license_key = trim(get_option( LINKILO_LICENSE_KEY_OPTION));
        $edd_item_id = Linkilo_Build_ActiveLicense::getItemId($license_key);
        $license = Linkilo_Build_ActiveLicense::getKey($license_key);

        $plugin_data = get_plugin_data(__FILE__);
        $plugin_version = $plugin_data['Version'];

        // setup the updater
        $edd_updater = new EDD_SL_Plugin_Updater( LINKILO_SHOP_URL, __FILE__, array(
            'version' => $plugin_version,		// current version number
            'license' => $license,	// license key (used get_option above to retrieve from DB)
            'item_id' => $edd_item_id,	// id of this plugin
            'author' => 'Spencer Haws',	// author of this plugin
            'url' => home_url(),
            'beta' => false, // set to true if you wish customers to receive update notifications of beta releases
        ));

    }*/

}


add_action('plugins_loaded', 'linkilo_init');

if (!function_exists('linkilo_init'))
{
    function linkilo_init()
    {
        $locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, 'linkilo');
        unload_textdomain('linkilo');
        load_textdomain('linkilo', LINKILO_PLUGIN_DIR_PATH . 'languages/' . "linkilo-" . $locale . '.mo');
        load_plugin_textdomain('linkilo', false, LINKILO_PLUGIN_DIR_PATH . 'languages');
    }
}

/**
 * A text logging function for use when error_log isn't a possibility.
 * I find myself copy-pasting file writers often enough that it makes sense to add a logger here for debugging
 * Can accept a string or array/object for writing
 * 
 * @param mixed $content The content to write to the file.
 **/
if(!function_exists('LINKILO_LOG_TEXT')){
    function LINKILO_LOG_TEXT($content){
        $file = fopen(trailingslashit(LINKILO_PLUGIN_DIR_PATH) . 'linkilo_text_log.txt', 'a');
        fwrite($file, print_r($content, true));
        fclose($file);
    }
}
