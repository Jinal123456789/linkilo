<?php

/**
 * Work with links
 */
class Linkilo_Build_PostUrl
{
    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_linkilo_save_feed_url_references', [$this, 'addLinks']);
        add_action('wp_ajax_linkilo_get_feed_url_title', [$this, 'getLinkTitle']);
        add_action('wp_ajax_linkilo_add_feed_url_to_ignore', [$this, 'addLinkToIgnore']);
    }

    /**
     * Update post links
     */
    function addLinks()
    {
        $err_msg = false;

        //check if request has needed data
        if (empty($_POST['data'])) {
            $err_msg = "No links selected";
        } elseif (empty($_POST['id']) || empty($_POST['type']) || empty($_POST['page'])){
            $err_msg = "Broken links data";
        } else {
            $page = $_POST['page'];

            foreach ($_POST['data'] as $item) {
                $id = !empty($item['id']) ? (int)$item['id'] : (int)$_POST['id'];
                $type = !empty($item['type']) ? $item['type'] : $_POST['type'];

                $links = $item['links'];
                //trim sentences
                foreach ($links as $key => $link) {
                    if ($page == 'incoming') {
                        $link['id'] = (int)$_POST['id'];
                        $link['type'] = sanitize_text_field($_POST['type']);
                    }

                    $external = (isset($link['post_origin']) && $link['post_origin'] === 'external') ? true: false;

                    if (!empty($link['custom_link'])) {
                        $view_link = $link['custom_link'];
                    } elseif($external) {
                        $item = new Linkilo_Build_Model_OuterFeed(array('post_id' => (int)$link['id'], 'type' => $link['type'], 'site_url' => esc_url_raw($link['site_url'])));
                        
                        $view_link = $item->getLinks()->view;
                    } elseif ($link['type'] == 'term') {
                        $post = new Linkilo_Build_Model_Feed((int)$link['id'], 'term');
                        $view_link = $post->getViewLink();
                    } else {
                        $post = new Linkilo_Build_Model_Feed((int)$link['id']);
                        $view_link = $post->getViewLink();
                    }

                    $links[$key]['sentence'] = trim(base64_decode($link['sentence']));
                    $links[$key]['sentence_with_anchor'] = trim(str_replace('%view_link%', $view_link, $link['sentence_with_anchor']));

                    if (!empty($link['custom_sentence'])) {
                        $links[$key]['custom_sentence'] = trim(str_replace('|href="([^"]+)"|', $view_link, base64_decode($link['custom_sentence'])));

                        if (!empty($link['custom_link'])) {
                            $links[$key]['custom_sentence'] = preg_replace('|href="([^"]+)"|', 'href="'.$link['custom_link'].'"', $links[$key]['custom_sentence']);
                        }
                    }

                    update_post_meta($link['id'], 'linkilo_sync_report3', 0);
                }

                if ($type == 'term') {
                    update_term_meta($id, 'linkilo_links', $links);
                } else {
                    update_post_meta($id, 'linkilo_links', $links);

                    if ($page == 'outgoing') {
                        //create DB record with success flag
                        update_post_meta($id, 'linkilo_is_outgoing_urls_added', '1');

                        //create DB record to refresh page after post update if Gutenberg is active
                        if (!empty($_POST['gutenberg']) && $_POST['gutenberg'] == 'true') {
                            update_post_meta($id, 'linkilo_gutenberg_restart', '1');
                        }
                    }
                }
            }
            
            if ($page == 'incoming') {
                foreach ($_POST['data'] as $item) {
                    if ($item['type'] == 'term') {
                        Linkilo_Build_WpTerm::addLinksToTerm($item['id']);
                    } else {
                        ob_start();
                        Linkilo_Build_Feed::addLinksToContent(null, ['ID' => $item['id']]);
                        ob_end_clean();
                    }
                }

                if ($item['type'] == 'term') {
                    update_term_meta((int)$_POST['id'], 'linkilo_is_incoming_urls_added', '1');
                } else {
                    update_post_meta((int)$_POST['id'], 'linkilo_is_incoming_urls_added', '1');
                }
            }
        }
        //return response
        header("Content-type: application/json");
        echo json_encode(['err_msg' => $err_msg]);

        exit;
    }

    /**
     * Delete link from post
     * @param null $params
     */
    public static function delete($params = null, $no_die = false)
    {
        foreach (['post_id', 'post_type', 'url', 'anchor', 'link_id'] as $key) {
            $$key = self::getDeleteParam($params, $key);
        }
        $anchor = !empty($anchor) ? base64_decode($anchor) : null;

        if ($post_id && $post_type && $url) {
            $post = new Linkilo_Build_Model_Feed($post_id, $post_type);
            $content = $post->getCleanContent();

            $url = base64_decode($url);
            if ($post_type == 'post') {
                Linkilo_Build_Feed::editors('deleteLink', [$post_id, $url, $anchor]);
                Linkilo_Build_Editor_Kadence::deleteLink($content, $url, $anchor);
            }

            //delete link from post content
            if (empty($anchor)) {
                $content = preg_replace('`<a [^>]+(\'|\")' . preg_quote($url, '`') . '(\'|\")[^>]*>(.*?)</a>`i', '$3',  $content);
            } else {
                $old_content = md5($content);
                $content = preg_replace('`<a [^>]+(\'|\")' . preg_quote($url, '`') . '(\'|\")[^>]*>' . preg_quote($anchor, '`') . '</a>`i', $anchor,  $content);
            
                // if the link hasn't been removed
                if($old_content === md5($content)){
                    // use a more aggresive regex to remove it
                    $content = preg_replace('`<a [^>]+(\'|\")' . preg_quote($url, '`') . '(\'|\")[^>]*>(.*?)' . preg_quote($anchor, '`') . '(.*?)</a>`i', $anchor,  $content);
                }
            }

            $updated = $post->updateContent($content);

            if($updated){
                $post->setContent($content);
                $post->clearPostCache();
            }

            //delete link record from linkilo_broken_links table
            if (!empty($link_id)) {
                Linkilo_Build_BrokenUrlError::deleteLink($link_id);
            }

            if (LINKILO_IS_LINKS_TABLE_EXISTS){
                Linkilo_Build_UrlRecord::update_post_in_link_table($post);
            }

            Linkilo_Build_UrlRecord::statUpdate($post);

            //update second post if link was internal incoming
            $second_post = Linkilo_Build_Feed::getPostByLink($url);
            if (!empty($second_post)) {
                Linkilo_Build_UrlRecord::statUpdate($second_post);
            }
        }

        if (!$no_die) {
            die;
        }
    }

    public static function getDeleteParam($params, $key)
    {
        if (!empty($params[$key])) {
            return $params[$key];
        } elseif (!empty($_POST[$key])) {
            return $_POST[$key];
        } else {
            return null;
        }
    }

    /**
     * Check if link is internal
     *
     * @param $url
     * @return bool
     */
    public static function isInternal($url)
    {
        if (strpos($url, '://') === false) {
            return true;
        }

        if (self::markedAsExternal($url)) {
            return false;
        }

        if(self::isAffiliateLink($url)){
            return false;
        }

        $localhost = parse_url(get_site_url(), PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);

        if (!empty($localhost) && !empty($host)) {
            $localhost = str_replace('www.', '', $localhost);
            $host = str_replace('www.', '', $host);
            if ($localhost == $host) {
                return true;
            }

            $internal_domains = Linkilo_Build_AdminSettings::getInternalDomains();

            if(in_array($host, $internal_domains, true)){
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the url is a known cloaked affiliate link.
     * 
     * @param string $url The url to be checked
     * @return bool Whether or not the url is to a cloaked affiliate link. 
     **/
    public static function isAffiliateLink($url){
        // if ThirstyAffiliates is active
        if(class_exists('ThirstyAffiliates')){
            $links = self::getThirstyAffiliateLinks();

            if(isset($links[$url])){
                return true;
            }
        }


        return false;
    }

    /**
     * Check if link is broken
     *
     * @param $url
     * @return bool|int
     */
    public static function getResponseCode($url)
    {
        // if a url was provided and it's formatted correctly, make a curl call to see if it's valid
        if(!empty($url) && (parse_url($url, PHP_URL_SCHEME) || substr($url, 0, 2) == '//') && parse_url($url, PHP_URL_HOST)){
            return self::getResponseCodeCurl($url);
        }

        return 925;
    }

    public static function getResponseCodeCurl($url) {
        $c = curl_init(html_entity_decode($url));
        $user_ip = get_transient('linkilo_site_ip_address');
        
        // if the ip transient isn't set yet
        if(empty($user_ip)){
            // get the site's ip
            $host = gethostname();
            $user_ip = gethostbyname($host);

            // if that didn't work
            if(empty($user_ip)){
                // get the curent user's ip as best we can
                if (!empty($_SERVER['HTTP_CLIENT_IP'])){
                    $user_ip = $_SERVER['HTTP_CLIENT_IP'];
                }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
                    $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                }else{
                    $user_ip = $_SERVER['REMOTE_ADDR'];
                }
            }
        }

        // save the ip so we don't have to look it up next time
        set_transient('linkilo_site_ip_address', $user_ip, (10 * MINUTE_IN_SECONDS));

        // create the list of headers to make the cURL request with
        $request_headers = array(
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: max-age=0',
            'Keep-Alive: 300',
            'Pragma: ',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?0',
            'Host: ' . parse_url($url, PHP_URL_HOST),
            'Referer: ' . site_url(),
            'User-Agent: ' . 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
        );

        if(!empty($user_ip)){
            $request_headers[] = 'X-Real-Ip: ' . $user_ip;
        }

        curl_setopt($c, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($c, CURLOPT_HEADER, true);
        curl_setopt($c, CURLOPT_FILETIME, true);
        curl_setopt($c, CURLOPT_NOBODY, true);
        curl_setopt($c, CURLOPT_HTTPGET, true);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($c, CURLOPT_MAXREDIRS, 30);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($c, CURLOPT_TIMEOUT, 20);
        curl_setopt($c, CURLOPT_COOKIEFILE, null);

        $curl_version = curl_version();
        if (version_compare(phpversion(), '7.0.7') >= 0 && version_compare($curl_version['version'], '7.42.0') >= 0) {
            curl_setopt($c, CURLOPT_SSL_FALSESTART, true);
        }

        //Set the proxy configuration. The user can provide this in wp-config.php
        if(defined('WP_PROXY_HOST')){
            curl_setopt($c, CURLOPT_PROXY, WP_PROXY_HOST);
        }
        if(defined('WP_PROXY_PORT')){
            curl_setopt($c, CURLOPT_PROXYPORT, WP_PROXY_PORT);
        }
        if(defined('WP_PROXY_USERNAME')){
            $auth = WP_PROXY_USERNAME;
            if(defined('WP_PROXY_PASSWORD')){
                $auth .= ':' . WP_PROXY_PASSWORD;
            }
            curl_setopt($c, CURLOPT_PROXYUSERPWD, $auth);
        }

        //Make CURL return a valid result even if it gets a 404 or other error.
        curl_setopt($c, CURLOPT_FAILONERROR, false);

        $headers = curl_exec($c);
        if(defined('CURLINFO_RESPONSE_CODE')){
            $http_code = intval(curl_getinfo($c, CURLINFO_RESPONSE_CODE));
        }else{
            $info = curl_getinfo($c);
            if(isset($info['http_code']) && !empty($info['http_code'])){
                $http_code = intval($info['http_code']);
            }else{
                $http_code = 0;
            }
        }

        $curl_error_code = curl_errno($c);

        // if the curl request ultimately got a http code
        if(!empty($http_code)){
            // return the code
            return $http_code;
        }elseif(!empty($curl_error_code)){
            // if we got a curl error, return that
            return $curl_error_code;
        }

        return 925;
    }

    /**
     * Check if link is broken
     *
     * @param $url
     * @return array
     */
    public static function getResponseCodes($urls = array(), $head_call = false)
    {
        $return_urls = array();
        $good_urls = array();
        foreach($urls as $url){
            // if a url was provided and it's formatted correctly, add it to the list to process
            if(!empty($url) && (parse_url($url, PHP_URL_SCHEME) || substr($url, 0, 2) == '//') && parse_url($url, PHP_URL_HOST)){
                $good_urls[] = $url;
            }else{
                // if it wasn't, add it to the return list as a 925
                $return_urls[$url] = 925;
            }
        }

        // if there are good urls
        if(!empty($good_urls)){
            // get the curl response codes for each of them
            $codes = self::getResponseCodesCurl($good_urls, $head_call);
            // and merge the reponses into the return links
            $return_urls = array_merge($return_urls, $codes);
        }

        return $return_urls;
    }

    public static function getResponseCodesCurl($urls, $head_call = false) {
        $start = microtime(true);
        $redirect_codes = array(301, 302, 307);
        $user_ip = get_transient('linkilo_site_ip_address');
        $return_urls = array();
        
        // if the ip transient isn't set yet
        if(empty($user_ip)){
            // get the site's ip
            $host = gethostname();
            $user_ip = gethostbyname($host);

            // if that didn't work
            if(empty($user_ip)){
                // get the curent user's ip as best we can
                if (!empty($_SERVER['HTTP_CLIENT_IP'])){
                    $user_ip = $_SERVER['HTTP_CLIENT_IP'];
                }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
                    $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                }else{
                    $user_ip = $_SERVER['REMOTE_ADDR'];
                }
            }
        }

        // save the ip so we don't have to look it up next time
        set_transient('linkilo_site_ip_address', $user_ip, (10 * MINUTE_IN_SECONDS));

        // create the multihandle
        $mh = curl_multi_init();

        // if we're debugging curl
        if(LINKILO_DEBUG_CURL){
            // setup the log files
            $verbose = fopen(trailingslashit(WP_CONTENT_DIR) . 'curl_connection_log.log', 'a');     // logs the actions that curl goes through in contacting the server
            $connection = fopen(trailingslashit(WP_CONTENT_DIR) . 'curl_connection_info.log', 'a'); // logs the result of contacting the server.
        }

        $handles = array();
        foreach($urls as $url){
            // create the curl handle and add it to the list keyed with the url its using
            $handles[$url] = curl_init(html_entity_decode($url));

            // create the list of headers to make the cURL request with
            $request_headers = array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: max-age=0, no-cache',
                'Pragma: ',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?0',
                'Host: ' . parse_url($url, PHP_URL_HOST),
                'Referer: ' . site_url(),
                'User-Agent: ' . 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
            );

            if(!empty($user_ip)){
                $request_headers[] = 'X-Real-Ip: ' . $user_ip;
            }

            if($head_call){
                $request_headers[] = 'Connection: close';
            }else{
                $request_headers[] = 'Connection: keep-alive';
                $request_headers[] = 'Keep-Alive: 300';
            }

            curl_setopt($handles[$url], CURLOPT_HTTPHEADER, $request_headers);
            curl_setopt($handles[$url], CURLOPT_HEADER, true);
            curl_setopt($handles[$url], CURLOPT_FILETIME, true);
            curl_setopt($handles[$url], CURLOPT_NOBODY, true);
            curl_setopt($handles[$url], CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($handles[$url], CURLOPT_MAXREDIRS, 10);
            curl_setopt($handles[$url], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handles[$url], CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($handles[$url], CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($handles[$url], CURLOPT_TIMEOUT, 15);
            curl_setopt($handles[$url], CURLOPT_COOKIEFILE, null);
            curl_setopt($handles[$url], CURLOPT_FORBID_REUSE, true);
            curl_setopt($handles[$url], CURLOPT_FRESH_CONNECT, true);
            curl_setopt($handles[$url], CURLOPT_COOKIESESSION, true);
            curl_setopt($handles[$url], CURLOPT_SSL_VERIFYPEER, false);

            $curl_version = curl_version();
            if (version_compare(phpversion(), '7.0.7') >= 0 && version_compare($curl_version['version'], '7.42.0') >= 0) {
                curl_setopt($handles[$url], CURLOPT_SSL_FALSESTART, true);
            }

            if(false === $head_call){
                curl_setopt($handles[$url], CURLOPT_HTTPGET, true);
            }

            //Set the proxy configuration. The user can provide this in wp-config.php
            if(defined('WP_PROXY_HOST')){
                curl_setopt($handles[$url], CURLOPT_PROXY, WP_PROXY_HOST);
            }
            if(defined('WP_PROXY_PORT')){
                curl_setopt($handles[$url], CURLOPT_PROXYPORT, WP_PROXY_PORT);
            }
            if(defined('WP_PROXY_USERNAME')){
                $auth = WP_PROXY_USERNAME;
                if(defined('WP_PROXY_PASSWORD')){
                    $auth .= ':' . WP_PROXY_PASSWORD;
                }
                curl_setopt($handles[$url], CURLOPT_PROXYUSERPWD, $auth);
            }

            //Make CURL return a valid result even if it gets a 404 or other error.
            curl_setopt($handles[$url], CURLOPT_FAILONERROR, false);

            // if we're debugging curl
            if(LINKILO_DEBUG_CURL){
                // set curl to verbose logging and set where to write it to
                curl_setopt($handles[$url], CURLOPT_VERBOSE, true);
                curl_setopt($handles[$url], CURLOPT_STDERR, $verbose);
            }

            // and add it to the multihandle
            curl_multi_add_handle($mh, $handles[$url]);
        }

        // if there are handles, execute the multihandle
        if(!empty($handles)){
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh);
                }
            } while ($active && $status == CURLM_OK);
        }

        // get any error codes from the operations
        $curl_codes = array();
        foreach($handles as $handle){
            $info = curl_multi_info_read($mh);
            $handle_int = intval($info['handle']);
            if(isset($info['result'])){
                $curl_codes[$handle_int] = $info['result'];
            }else{
                $curl_codes[$handle_int] = 0;
            }
        }

        // when the multihandle is finished, go over the handles and process the responses
        foreach($handles as $handle_url => $handle){
            $handle_int = intval($handle);
            $http_code = intval(curl_getinfo($handle, CURLINFO_RESPONSE_CODE));
            $curl_error_code = (isset($curl_codes[$handle_int])) ? $curl_codes[$handle_int]: 0;

            // if we're debugging curl
            if(LINKILO_DEBUG_CURL){
                // save the results of the connection
                fwrite($connection, print_r(curl_getinfo($handle),true));
            }

            // if the curl request ultimately got a http code
            if(!empty($http_code)){
                // if the code is for a redirect and we have some time to chase it
                if(in_array($http_code, $redirect_codes) && (microtime(true) - $start) < 15){
                    // get the url from the curl data
                    $new_url = trim(curl_getinfo($handle, CURLINFO_EFFECTIVE_URL));
                    if(!empty($new_url)){
                        // call _that_ url to see what happens and add the response to the link list
                        $return_urls[$handle_url] = self::getResponseCodeCurl($new_url);
                    }
                }else{
                    // if the code wasn't a redirect or we don't have the time to check, add the code to the list
                    $return_urls[$handle_url] =  $http_code;
                }
            }elseif(!empty($curl_error_code)){
                // curl error list: https://curl.haxx.se/libcurl/c/libcurl-errors.html
                // useful for diagnosing errors < 100
                $return_urls[$handle_url] = $curl_error_code;
            }

            // if a status hasn't been added to the link yet
            if(!isset($return_urls[$handle_url])){
                // mark it as 925
                $return_urls[$handle_url] = 925;
            }
            
            // close the current handle
            curl_multi_remove_handle($mh, $handle);
            curl_close($handle);
        }

        // close the multi handle
        curl_multi_close($mh);

        return $return_urls;
    }

    /**
     * Get link title by URL
     */
    public static function getLinkTitle()
    {
        $link = !empty($_POST['link']) ? $_POST['link'] : '';
        $title = '';
        $id = '';
        $type = '';

        if ($link) {
            if (self::isInternal($link)) {
                $post_id = url_to_postid($link);
                if ($post_id) {
                    $post = get_post($post_id);
                    $title = $post->post_title;
                    $link = '/' . $post->post_name;
                    $id = $post_id;
                    $type = 'post';
                } else {
                    $slugs = array_filter(explode('/', $link));
                    $term = Linkilo_Build_WpTerm::getTermBySlug(end($slugs));
                    if (!empty($term)) {
                        $title = $term->name;
                        $link = get_term_link($term->term_id);
                        $id = $term->term_id;
                        $type = 'term';
                    }
                }
            }

            //get title if link is not post or term
            if (!$title) {
                $str = file_get_contents($link);
                if(strlen($str)>0){
                    $str = trim(preg_replace('/\s+/', ' ', $str)); // supports line breaks inside <title>
                    preg_match("/\<title\>(.*)\<\/title\>/i",$str,$title); // ignore case
                    $title = $title[1];
                }
            }

            echo json_encode([
                'title' => $title,
                'link' => $link,
                'id' => $id,
                'type' => $type
            ]);
        }

        die;
    }

    /**
     * Remove class "linkilo_internal_link" from links
     */
    public static function removeLinkClass()
    {
        global $wpdb;

        $wpdb->get_results("UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, 'linkilo_internal_link', '') WHERE post_content LIKE '%linkilo_internal_link%'");
    }

    /**
     * Add link to ignore list
     */
    public static function addLinkToIgnore()
    {
        $error = false;
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $type = !empty($_POST['type']) ? sanitize_text_field($_POST['type']) : null;
        $site_url = (isset($_POST['site_url']) && !empty($_POST['site_url'])) ? esc_url_raw($_POST['site_url']): null;
        $origin = (isset($_POST['post_origin'])) ? $_POST['post_origin']: null;

        if ($id && $type) {
            // if the object is known to be external
            if($origin === 'external'){
                // create an external post object
                $post = new Linkilo_Build_Model_OuterFeed(array('post_id' => $id, 'type' => $type, 'site_url' => $site_url));
            }else{
                // otherwise, assume it's an internal post object
                $post = new Linkilo_Build_Model_Feed($id, $type);
            }
            
            $link = $post->getLinks()->view;

            if (!empty($link)) {
                $links = get_option('linkilo_ignore_links');
                if (!empty($links)) {
                    $links_array = explode("\n", $links);
                    if (!in_array($link, $links_array)) {
                        $links .= "\n" . $link;
                    }
                } else {
                    $links = $link;
                }
                // clear any ignore link cache that exists
                delete_transient('linkilo_ignore_links');
                // save the ignore link
                update_option('linkilo_ignore_links', $links);
            } else {
                $error = 'Empty post link';
            }
        } else {
            $error = 'Wrong data';
        }

        echo json_encode(['error' => $error]);
        die;
    }

    /**
     * Clean link from trash symbols
     *
     * @param $link
     * @return string
     */
    public static function clean($link)
    {
        $link = str_replace(['http://', 'https://', '//www.'], '//', strtolower(trim($link)));
        if (substr($link, -1) == '/') {
            $link = substr($link, 0, -1);
        }

        return $link;
    }
    
    /**
     * Updates an existing link in a post with a new link
     * 
     * @param $post_id
     * @param $post_type
     * @param $old_link
     * @param $new_link
     **/
    public static function updateExistingLink($post_id = 0, $post_type = '', $old_link = '', $new_link = ''){
        if(empty($post_id) || empty($post_type) || empty($old_link) || empty($new_link)){
            return false;
        }
        
        // get the post we want to update
        $post = new Linkilo_Build_Model_Feed($post_id, $post_type);

        // if there is a post, update it with the new link 
        if(!empty($post)){
            $content = $post->getContent();
            
            if(false !== strpos($content, $old_link)){
                $content = str_replace($old_link, $new_link, $content);
                $updated = $post->updateContent($content);
                return $updated;
            }
        }
        
        return false;
    }

    /**
     * Check if link was marked as external
     *
     * @param $link
     * @return bool
     */
    public static function markedAsExternal($link)
    {
        $external_links = Linkilo_Build_AdminSettings::getMarkedAsExternalLinks();

        if (in_array($link, $external_links)) {
            return true;
        }

        foreach ($external_links as $external_link) {
            if (substr($external_link, -1) == '*' && strpos($link, substr($external_link, 0, -1)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks to see if the supplied text contains a link.
     * The check is pretty simple at this point, just seeing if the form of an opening tag or a closing tag is present in the text
     * 
     * @param string $text
     * @return bool
     **/
    public static function hasLink($text = '', $replace_text = ''){

        // if there's no link anywhere to be seen, return false
        if(empty(preg_match('/<a [^><]*?(href|src)[^><]*?>|<\/a>/i', $text))){
            return false;
        }

        // if there is a link in the replace text, return true
        if(preg_match('/<a [^><]*?(href|src)[^><]*?>|<\/a>/i', $replace_text)){
            return true;
        }

        // if there is a link, see if it ends before the replace text
        $replace_start = mb_strpos($text, $replace_text);
        if(preg_match('/<\/a>/i', mb_substr($text, 0, $replace_start)) ){
            // if it does, no worries!
            return false;
        }elseif(preg_match('/<a [^><]*?(href|src)[^><]*?>/i', mb_substr($text, 0, $replace_start)) || preg_match('/<\/a>/i', mb_substr($text, $replace_start)) ){
            // if there's an opening tag before the replace text or somewhere after the start, then presumably the replace text is in the middle of a link
            return true;
        }

        return false;
    }

    
    /**
     * Checks to see if the supplied text contains a heading tag.
     * The check is pretty simple at this point, just seeing if the form of an opening tag or a closing tag is present in the text
     * 
     * @param string $text
     * @return bool
     **/
    public static function hasHeading($text = '', $replace_text = '', $sentence = ''){
        // if there's no heading anywhere to be seen, return false
        if(empty(preg_match('/<h[1-6][^><]*?>|<\/h[1-6]>/i', $text))){
            return false;
        }

        // if there is a heading, see if it ends before the replace text
        $replace_start = mb_strpos($text, $sentence);
        if(preg_match('/<\/h[1-6]>/i', mb_substr($text, 0, $replace_start)) ){
            // if it does, no worries!
            return false;
        }elseif(preg_match('/<h[1-6][^><]*?>/i', mb_substr($text, 0, $replace_start)) || (preg_match('/<\/h[1-6]>/i', mb_substr($text, $replace_start)) && !preg_match('/<h[1-6][^><]*?>/i', mb_substr($text, $replace_start)) ) ){
            // if there's an opening tag before the replace text or somewhere after the start, then presumably the replace text is in the middle of a heading
            return true;
        }

        // if there is a heading in the replace text, return true
        if(substr_count($replace_text, $sentence) > 1 && preg_match('/<h[1-6][^><]*?>|<\/h[1-6]>/i', $replace_text)){
            return true;
        }

        return false;
    }

    /**
     * Checks to see if the current slice of text contains any tags that we don't want to insert a link into
     * 
     * @param string $text
     * @return bool
     **/
    public static function checkForForbiddenTags($text, $replace_text, $sentence){
        if(self::hasLink($text, $replace_text)){
            return true;
        }elseif(self::hasHeading($text, $replace_text, $sentence)){
            return true;
        }

        return false;
    }

    public static function remove_all_links_from_text($text = ''){
        if(empty($text)){
            return $text;
        }

        $text = preg_replace('/<a[^>]+>(.*?)<\/a>/', '$1', $text);
        
        return $text;
    }

    /**
     * Gets all ThirstyAffiliate links in an array keyed with the urls.
     * Caches the results to save processing time later
     **/
    public static function getThirstyAffiliateLinks(){
        global $wpdb;
        $links = get_transient('linkilo_thirsty_affiliate_links');

        if(empty($links)){
            // query for the link posts
            $results = $wpdb->get_col("SELECT `ID` FROM {$wpdb->posts} WHERE `post_type` = 'thirstylink'");

            // store a flag if there are no link posts
            if(empty($results)){
                set_transient('linkilo_thirsty_affiliate_links', 'no-links', 5 * MINUTE_IN_SECONDS);
                return array();
            }

            // get the urls to the link posts
            $links = array();
            foreach($results as $id){
                $links[] = get_permalink($id);
            }

            // flip the array for easy searching
            $links = array_flip($links);

            // store the results
            set_transient('linkilo_thirsty_affiliate_links', $links, 5 * MINUTE_IN_SECONDS);

        }elseif($links === 'no-links'){
            return array();
        }

        return $links;
    }
}
