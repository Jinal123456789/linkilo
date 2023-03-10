<?php

/**
 * Work with licenses
 */
/*
    Commented unusable code ref:license
*/

// class Linkilo_Build_ActiveLicense
// {
//     /**
//      * Register services
//      */
//     public function register()
//     {
//         add_action('wp_ajax_linkilo_activate_user_license', array(__CLASS__, 'ajax_linkilo_activate_user_license'));
//     }

//     public static function init()
//     {
//         if (!empty($_GET['linkilo_deactivate']))
//         {
//             update_option(LINKILO_STATUS_OF_LICENSE_OPTION, 'invalid');
//             update_option(LINKILO_LAST_ERROR_FOR_LICENSE_OPTION, $message='Deactivated manually');
//         }

//         include LINKILO_PLUGIN_DIR_PATH . '/templates/linkilo_license.php';
//     }

//     /**
//      * Check if license is valid
//      *
//      * @return bool
//      */
//     public static function isValid()
//     {
//         if (get_option('linkilo_2_license_status') == 'valid') {
//             $prev = get_option('linkilo_2_license_check_time');
//             $delta = $prev ? time() - strtotime($prev) : 0;

//             if (!$prev || $delta > (60*60*24*3) || !empty($_GET['linkilo_check_license'])) {
//                 $license = self::getKey();
//                 self::check($license, $silent = true);
//             }

//             $status = get_option('linkilo_2_license_status');

//             if ($status !== false && $status == 'valid') {
//                 return true;
//             }
//         }

//         return false;
//     }

//     /**
//      * Get license key
//      *
//      * @param bool $key
//      * @return bool|mixed|void
//      */
//     public static function getKey($key = false)
//     {
//         if (empty($key)) {
//             $key = get_option('linkilo_2_license_key');
//         }

//         if (stristr($key, '-')) {
//             $ks = explode('-', $key);
//             $key = $ks[1];
//         }

//         return $key;
//     }

//     /**
//      * Check new license
//      *
//      * @param $license_key
//      * @param bool $silent
//      */
//     public static function check($license_key, $silent = true)
//     {
//         $base_url_path = 'admin.php?page=linkilo_license';
//         $item_id = self::getItemId($license_key);
//         $license = Linkilo_Build_ActiveLicense::getKey($license_key);
//         $code = null;

//         if (function_exists('curl_version')) {
//             //CURL is enabled
//             $ch = curl_init();
//             curl_setopt($ch, CURLOPT_HEADER, 0);
//             curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//             curl_setopt($ch, CURLOPT_URL, LINKILO_SHOP_URL);
//             curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//             curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
//             curl_setopt($ch, CURLOPT_POST, 1);
//             curl_setopt($ch, CURLOPT_POSTFIELDS,
//                 "edd_action=activate_license&license={$license}&item_id={$item_id}&url=".urlencode(home_url()));
//             $data = curl_exec($ch);
//             $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//         } else {
//             //CURL is disabled
//             $params = [
//                 'edd_action' => 'activate_license',
//                 'license' => $license,
//                 'item_id' => $item_id,
//                 'url' => urlencode(home_url()),
//             ];
//             $data = file_get_contents(LINKILO_SHOP_URL . '/?' . http_build_query($params));
//             if (!empty($data)) {
//                 $code = 200;
//             }
//         }

//         update_option(LINKILO_LICENSE_CHECK_TIME_OPTION, date('c'));

//         if (empty($data) || $code !== 200) {
//             $error_message = !empty($ch) ? curl_error($ch) : '';

//             if ($error_message) {
//                 $message = $error_message;
//             } else {
//                 $message = "$code response code on activation, please try again or check code";
//             }
//         } else {
//             $license_data = json_decode($data);

//             if ($license_data->success === false) {
//                 $message = self::getMessage($license, $license_data);
//             } else {
//                 update_option(LINKILO_STATUS_OF_LICENSE_OPTION, $license_data->license);
//                 update_option(LINKILO_LICENSE_KEY_OPTION, $license);
//                 update_option(LINKILO_CURRENT_LICENSE_DATA_OPTION, var_export($license_data, true));

//                 if (!$silent) {
//                     $base_url = admin_url('admin.php?page=linkilo_settings&licensing');
//                     $message = __("License key `%s` was activated", 'linkilo');
//                     $message = sprintf($message, $license);
//                     $redirect = add_query_arg(array('sl_activation' => 'true', 'message' => urlencode($message)), $base_url);
//                     wp_redirect($redirect);
//                     exit;
//                 } else {
//                     return;
//                 }
//             }
//         }

//         if (!empty($ch)) {
//             curl_close($ch);
//         }

//         update_option(LINKILO_STATUS_OF_LICENSE_OPTION, 'invalid');
//         update_option(LINKILO_LAST_ERROR_FOR_LICENSE_OPTION, $message);

//         if (!$silent) {
//             $base_url = admin_url($base_url_path);
//             $redirect = add_query_arg(array('sl_activation' => 'false', 'msg' => urlencode($message)), $base_url);
//             wp_redirect($redirect);
//             exit;
//         }
//     }

//     /**
//      * Check if a given site is licensed in the same plan as this site.
//      *
//      * @param string $site_url The url of the site we want to check.
//      * @return bool
//      */
//     public static function check_site_license($site_url = '')
//     {
//         if(empty($site_url)){
//             return false;
//         }

//         // if the site has been recently checked and does have a valid license
//         if(self::check_cached_site_licenses($site_url)){
//             // return true
//             return true;
//         }

//         $license_key = self::getKey();
//         $item_id = self::getItemId($license_key);
//         $license = Linkilo_Build_ActiveLicense::getKey($license_key);
//         $code = null;

//         if (function_exists('curl_version')) {
//             //CURL is enabled
//             $ch = curl_init();
//             curl_setopt($ch, CURLOPT_HEADER, 0);
//             curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//             curl_setopt($ch, CURLOPT_URL, LINKILO_SHOP_URL);
//             curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//             curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
//             curl_setopt($ch, CURLOPT_POST, 1);
//             curl_setopt($ch, CURLOPT_POSTFIELDS,
//                 "edd_action=check_license&license={$license}&item_id={$item_id}&url=".urlencode($site_url));
//             $data = curl_exec($ch);
//             $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//         } else {
//             //CURL is disabled
//             $params = [
//                 'edd_action' => 'check_license',
//                 'license' => $license,
//                 'item_id' => $item_id,
//                 'url' => urlencode($site_url),
//             ];
//             $data = file_get_contents(LINKILO_SHOP_URL . '/?' . http_build_query($params));
//             if (!empty($data)) {
//                 $code = 200;
//             }
//         }

//         if (!empty($ch)) {
//             curl_close($ch);
//         }

//         if (empty($data) || $code !== 200) {
//             return false;
//         } else {
//             $license_data = json_decode($data);

//             if(isset($license_data->license) && 'valid' === $license_data->license){
//                 self::update_cached_site_list($site_url);
//                 return true;
//             }
//         }

//         return false;
//     }

//     /**
//      * Checks a site url against the cached list of known licensed urls.
//      * Returns if the site is licensed and has been checked recently
//      * 
//      * @param string $site_url
//      * @return bool
//      **/
//     public static function check_cached_site_licenses($site_url = ''){
//         $site_urls = get_option('linkilo_cached_valid_sites', array());

//         if(empty($site_urls) || empty($site_url)){
//             return false;
//         }

//         $time = time();
//         foreach($site_urls as $url_data){
//             if($site_url === $url_data['site_url'] && $time < $url_data['expiration']){
//                 return true;
//             }
//         }

//         return false;
//     }

//     /**
//      * Updates the cached site list with news of licensed sites.
//      * 
//      **/
//     public static function update_cached_site_list($site_url = ''){
//         if(empty($site_url)){
//             return false;
//         }

//         $site_cache = get_option('linkilo_cached_valid_sites', array());

//         foreach($site_cache as $key => $site_data){
//             if($site_data['site_url'] === $site_url){
//                 unset($site_cache[$key]);
//             }
//         }

//         $site_cache[] = array('site_url' => $site_url, 'expiration' => (time() + (60*60*24*3)) );

//         update_option('linkilo_cached_valid_sites', $site_cache);
//     }

//     /**
//      * Get current license ID
//      *
//      * @param string $license_key
//      * @return false|string
//      */
//     public static function getItemId($license_key = '')
//     {
//         if ($license_key && stristr($license_key, '-')) {
//             $ks = explode('-', $license_key);
//             return $ks[0];
//         }

//         $item_id = file_get_contents(dirname(__DIR__) . '/../store-item-id.txt');

//         return $item_id;
//     }

//     /**
//      * Get license message
//      *
//      * @param $license
//      * @param $license_data
//      * @return string
//      */
//     public static function getMessage($license, $license_data)
//     {
//         switch ($license_data->error) {
//             case 'expired' :
//                 $d = date_i18n(get_option('date_format'), strtotime($license_data->expires, current_time('timestamp')));
//                 $message = sprintf('Your license key %s expired on %s. Please renew your subscription to continue using Linkilo.', $license, $d);
//                 break;

//             case 'revoked' :
//                 $message = 'Your License Key `%s` has been disabled';
//                 break;

//             case 'missing' :
//                 $message = 'Missing License `%s`';
//                 break;

//             case 'invalid' :
//             case 'site_inactive' :
//                 $message = 'The License Key `%s` is not active for this URL.';
//                 break;

//             case 'item_name_mismatch' :
//                 $message = 'It appears this License Key (%s) is used for a different product. Please log into your linkilo.com user account to find your Linkilo License Key.';
//                 break;

//             case 'no_activations_left':
//                 $message = 'The License Key `%s` has reached its activation limit. Please upgrade your subscription to add more sites.';
//                 break;

//             case 'invalid_item_id':
//                 $message = "The License Key `%s` doesn't go to any known products. Fairly often this is caused by a mistake in entering the License Key or after upgrading your Linkilo subscription. If you've just upgraded your subscription, please delete Linkilo from your site and download a fresh copy from linkilo.com.";
//                 break;
    
//             default :
//                 $message = "Error on activation: " . $license_data->error;
//                 break;
//         }

//         if (stristr($message, '%s')) {
//             $message = sprintf($message, $license);
//         }

//         return $message;
//     }

//     /**
//      * Activate license
//      */
//     public static function activate()
//     {
//         if (!isset($_POST['hidden_action']) || $_POST['hidden_action'] != 'activate_license' || !check_admin_referer('linkilo_activate_license_nonce', 'linkilo_activate_license_nonce')) {
//             return;
//         }

//         $license = sanitize_text_field(trim($_POST['linkilo_license_key']));

//         self::check($license, $silent = false);
//     }

//     /**
//      * Activate license via ajax call
//      **/
//     public static function ajax_linkilo_activate_user_license(){
        
//     }
// }
