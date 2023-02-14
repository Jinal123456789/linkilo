<?php

/**
 * Class Linkilo_Build_BrokenUrlError
 */
class Linkilo_Build_BrokenUrlError
{
    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_linkilo_reset_broken_url_error_data', [$this, 'ajaxErrorResetData']);
        add_action('wp_ajax_linkilo_broken_url_error_process', [$this, 'ajaxErrorProcess']);
        add_action('wp_ajax_record_edit_url', [$this, 'ajaxEditReportLink']);
        add_action('wp_ajax_linkilo_remove_error_links', [$this, 'ajaxDeleteLinks']);
        add_filter('cron_schedules', [$this, 'addLinkCheckInterval']);
        add_action('admin_init', [$this, 'scheduleLinkCheck']);
        add_action('linkilo_broken_link_check_cron', [$this, 'performCronErrorChecks']);
        register_deactivation_hook(__FILE__, [$this, 'clearCronSchedules']);
    }

    /**
     * Reset DB fields before search
     */
    public static function ajaxErrorResetData()
    {
        Linkilo_Build_Root::verify_nonce('linkilo_reset_broken_url_error_data');
        self::fillPosts();
        self::fillTerms();
        self::prepareIgnoreTable();
        self::prepareTable();
        update_option('linkilo_error_reset_run', 1);
        update_option('linkilo_error_check_links_cron', 0);

        ob_start();
        include LINKILO_PLUGIN_DIR_PATH . 'templates/broken_feed_url_error_process.php';
        $template = ob_get_clean();
        wp_send_json(['template' => $template]);

        die;
    }

    /**
     * Search broken links
     */
    public static function ajaxErrorProcess()
    {
        ini_set('default_socket_timeout', 15);
        $start = microtime(true);
        $total = self::getTotalPostsCount();
        $not_ready = self::getNotReadyPosts();
        $time_limit = 10;
        $proceed = 0;
        $link_batch_size = 100;
        
        if(LINKILO_DEBUG_CURL){
            $link_call_results = fopen(trailingslashit(WP_CONTENT_DIR) . 'link_call_results.log', 'a'); // logs the batch results of calling the links.
        }

        //send response with search status to update progress bar
        if (!empty($_POST['get_status'])) {
            self::sendResponse(count($not_ready), $proceed, $total);
        }

        //proceed posts
        foreach ($not_ready as $item) {
            $post = new Linkilo_Build_Model_Feed($item['id'], $item['type']);
            $links = Linkilo_Build_Feed::getSentencesWithUrls($post);
            $sentences = self::createSentenceIndex($links);
            $link_batch = array();
            $link_count = count($links);

            foreach ($links as $key => $link_data) {
                $link = $link_data['url'];
                if ((strpos($link, 'http://') === 0 || strpos($link, 'https://') === 0 || strpos($link, '//') === 0) && !self::linkSaved($link) && !in_array($link, $link_batch)) {
                    // add the current link to the processing batch
                    $link_batch[] = $link;
                }

                // if the batch size has been reached or we're on the last link
                if(count($link_batch) >= $link_batch_size || (!empty($link_batch) && ($link_count -1) <= $key)){
                    // process the batch
                    $codes = Linkilo_Build_PostUrl::getResponseCodes($link_batch, true);
                    // create an array for the links we want to make a second call to
                    $second_pass = array();
                    // and save the results
                    foreach($codes as $url => $code){
                        // if the code is in the 2xx range, save it
                        if($code > 199 && $code < 300){
                            self::saveLink($url, $post, $code, $sentences[$url]);
                        }else{
                            // if the code falls outside the 2xx-3xx range, slate it for another call
                            $second_pass[] = $url;
                        }
                    }

                    // if there are links we want to check a second time
                    if(!empty($second_pass)){
                        // check them with a GET call instead of HEAD
                        $second_codes = Linkilo_Build_PostUrl::getResponseCodes($second_pass, false);
                        // go over each link
                        foreach($second_codes as $url => $code){
                            // if the code is something other than a curl error, save it directly
                            if($code > 99){
                                self::saveLink($url, $post, $code, $sentences[$url]);
                            }else{
                                // if the error code is for a curl error, see if the HEAD call had a HTTP response
                                if(isset($codes[$url]) && $codes[$url] > 99){
                                    // if it did, save that instead
                                    self::saveLink($url, $post, $codes[$url], $sentences[$url]);
                                }else{
                                    // otherwise, save the result of the GET call
                                    self::saveLink($url, $post, $code, $sentences[$url]);
                                }
                            }
                        }
                    }

                    if(LINKILO_DEBUG_CURL){
                        //** Get the difference between the HEAD and GET link calls **//
                        $head_vs_get = $codes;
                        $get_vs_head = $second_codes;
                        (isset($second_codes)) ? $second_codes : array();

                        foreach($codes as $url => $code){
                            // if the HEAD call got 200 or had the same result as the GET call
                            if(isset($second_codes[$url]) && (isset($second_codes[$url]) && (int)$second_codes[$url] === (int)$code)){
                                // remove the link from the list
                                unset($head_vs_get[$url]);
                            }
                        }

                        foreach($second_codes as $url => $code){
                            // if the GET call had the same result as the HEAD call
                            if((int)$codes[$url] === (int)$code){
                                // remove the link from the list
                                unset($get_vs_head[$url]);
                            }
                        }

                        // save the data to file
                        fwrite($link_call_results, print_r(array('HEAD' => $codes, 'GET' => $second_codes, 'head_vs_get' => $head_vs_get, 'get_vs_head' => $get_vs_head),true)); // save the batch response data to file based on the calling method
                        // and clear the second codes so they don't show up if there's no GET call
                        unset($second_codes);
                    }

                    // clear the link batch
                    $link_batch = array();

                    if (microtime(true) - $start > $time_limit) {
                        self::sendResponse(count($not_ready), $proceed, $total);
                    }
                }
            }

            $proceed++;
            self::markReady($post);

            if (microtime(true) - $start > $time_limit) {
                break;
            }
        }

        self::sendResponse(count($not_ready), $proceed, $total);

        die;
    }

    /**
     * Send response search status
     *
     * @param $not_ready
     * @param $proceed
     * @param $total
     * @param string $link
     */
    public static function sendResponse($not_ready, $proceed, $total, $link_id = 0)
    {
        $ready = $total - $not_ready + $proceed;
        $percents = ceil($ready / $total * 100);
        $status =  "$percents%, $ready/$total completed";
        $finish = $total == $ready ? true : false;

        if ($finish) {
            update_option('linkilo_error_reset_run', 0);
            self::mergeIgnoreLinks();
            self::deleteValidLinks();
            update_option('linkilo_error_check_links_cron', 1);
        }

        wp_send_json([
            'finish' => $finish,
            'status' => $status,
            'percents' => $percents,
            'link_id' => $link_id,
        ]);
    }

    /**
     * Mark post as processed
     *
     * @param $post
     */
    public static function markReady($post)
    {
        global $wpdb;

        if ($post->type == 'term') {
            $wpdb->update($wpdb->termmeta, ['meta_value' => 1], ['term_id' => $post->id, 'meta_key' => 'linkilo_sync_error']);
        } else {
            $wpdb->update($wpdb->postmeta, ['meta_value' => 1], ['post_id' => $post->id, 'meta_key' => 'linkilo_sync_error']);
        }
    }

    /**
     * Reset links data about posts
     */
    public static function fillPosts()
    {
        global $wpdb;

        $wpdb->delete($wpdb->postmeta, ['meta_key' => 'linkilo_sync_error']);
        $post_types = implode("','", Linkilo_Build_AdminSettings::getPostTypes());
        $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses();
        $posts = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('$post_types') $statuses_query");
        foreach ($posts as $post) {
            $wpdb->insert($wpdb->postmeta, ['post_id' => $post->ID, 'meta_key' => 'linkilo_sync_error', 'meta_value' => '0']);
        }
    }

    /**
     * Reset links data about terms
     */
    public static function fillTerms()
    {
        global $wpdb;

        $taxonomies = Linkilo_Build_AdminSettings::getTermTypes();

        $wpdb->delete($wpdb->termmeta, ['meta_key' => 'linkilo_sync_error']);
        if (!empty($taxonomies)) {
            $terms = $wpdb->get_results("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('" . implode("', '", $taxonomies) . "')");
            foreach ($terms as $term) {
                $wpdb->insert($wpdb->termmeta, ['term_id' => $term->term_id, 'meta_key' => 'linkilo_sync_error', 'meta_value' => '0']);
            }
        }
    }

    /**
     * Get total posts count
     *
     * @return string|null
     */
    public static function getTotalPostsCount()
    {
        global $wpdb;

        $count = $wpdb->get_var("SELECT count(`post_id`) FROM {$wpdb->postmeta} WHERE meta_key = 'linkilo_sync_error'");
        if (!empty(Linkilo_Build_AdminSettings::getTermTypes())) {
            $count += $wpdb->get_var("SELECT count(`term_id`) FROM {$wpdb->termmeta} WHERE meta_key = 'linkilo_sync_error'");
        }

        return $count;
    }

    /**
     * Get posts that should be processed
     *
     * @return array
     */
    public static function getNotReadyPosts()
    {
        global $wpdb;
        $posts = [];

        $result = $wpdb->get_results("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'linkilo_sync_error' AND meta_value = 0 ORDER BY post_id ASC");
        foreach ($result as $post) {
            $posts[] = array('id' => $post->post_id, 'type' => 'post');
        }

        if (!empty(Linkilo_Build_AdminSettings::getTermTypes())) {
            $result = $wpdb->get_results("SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = 'linkilo_sync_error' AND meta_value = 0 ORDER BY term_id ASC");
            foreach ($result as $post) {
                $posts[] = array('id' => $post->term_id, 'type' => 'term');
            }
        }

        return $posts;
    }

    /**
     * Create broken links table if it not exists and truncate it
     */
    public static function prepareTable($truncate = true){
        global $wpdb;

        // if the broken link table doesn't exist
        $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_broken_links'");
        if(empty($error_tbl_exists)){
            $linkilo_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}linkilo_broken_links (
                                        id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                        post_id int(10) unsigned NOT NULL,
                                        post_type text,
                                        url text,
                                        internal tinyint(1) DEFAULT 0,
                                        code int(10),
                                        created DATETIME,
                                        last_checked DATETIME,
                                        check_count INT(2) DEFAULT 0,
                                        ignore_link tinyint(1) DEFAULT 0,
                                        sentence varchar(255) DEFAULT 0,
                                        PRIMARY KEY  (id),
                                        INDEX (url(512))
                                    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($linkilo_link_table_query);

            if (strpos($wpdb->last_error, 'Index column size too large') !== false) {
                $linkilo_link_table_query = str_replace('INDEX (url(512))', 'INDEX (url(191))', $linkilo_link_table_query);
                dbDelta($linkilo_link_table_query);
            }
        }

        // run the table update just to make sure columns 'ignore_link' and 'sentence' are set
        Linkilo_Build_Root::updateTables();

        // check to see if there's stored data
        $data = $wpdb->get_results("SELECT `url` FROM {$wpdb->prefix}linkilo_broken_links LIMIT 1");
        if($data && empty($data->last_error) && $truncate){
            // if there is, prepare the ignore table
            self::prepareIgnoreTable();
        }

        if ($truncate) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}linkilo_broken_links");
        }

        Linkilo_Build_Root::fixCollation($wpdb->prefix . 'linkilo_broken_links');
    }

    /**
     * Creates and clears a table for storing links that the user wants the scan to ignore
     **/
    public static function prepareIgnoreTable(){
        global $wpdb;
        
        $ignore_links = $wpdb->prefix . "linkilo_ignore_links";
        
        // if the broken link's ignore table doesn't exist
        $ignore_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ignore_links}'");
        if(empty($ignore_tbl_exists)){
            $linkilo_ignore_link_table_query = "CREATE TABLE IF NOT EXISTS {$ignore_links} (
                                                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                                post_id int(10) unsigned NOT NULL,
                                                post_type text,
                                                url text,
                                                internal tinyint(1) DEFAULT 0,
                                                code int(10),
                                                created DATETIME,
                                                PRIMARY KEY  (id),
                                                INDEX (url(512))
                                            )";


            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($linkilo_ignore_link_table_query);

            if (strpos($wpdb->last_error, 'Index column size too large') !== false) {
                $linkilo_ignore_link_table_query = str_replace('INDEX (url(512))', 'INDEX (url(191))', $linkilo_ignore_link_table_query);
                dbDelta($linkilo_ignore_link_table_query);
            }
        }

        $wpdb->query("TRUNCATE TABLE {$ignore_links}");

        // if the broken link table exists
        $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_broken_links'");
        if(!empty($error_tbl_exists)){
            // copy all the ignored links into the ignore table
            $wpdb->query("INSERT INTO {$ignore_links} SELECT `id` AS 'id', `post_id` AS 'post_id', `post_type` AS 'post_type', `url` AS 'url', `internal` AS 'internal', `code` AS 'code', `created` AS 'created' FROM {$wpdb->prefix}linkilo_broken_links WHERE `ignore_link` = 1");
        }
    }

    /**
     * Save broken link to DB
     *
     * @param $url
     * @param $post
     * @param $code
     */
    public static function saveLink($url, $post, $code, $sentence)
    {
        global $wpdb;

        $internal = Linkilo_Build_PostUrl::isInternal($url) ? 1 : 0;
        $wpdb->insert($wpdb->prefix . 'linkilo_broken_links', [
            'post_id' => $post->id,
            'post_type' => $post->type,
            'url' => $url,
            'internal' => $internal,
            'code' => $code,
            'created' => current_time('mysql', 1),
            'sentence' => $sentence,
        ]);

        if (!$wpdb->insert_id) {
            $wpdb->insert($wpdb->prefix . 'linkilo_broken_links', [
                'post_id' => $post->id,
                'post_type' => $post->type,
                'url' => $url,
                'internal' => $internal,
                'code' => $code,
                'created' => current_time('mysql', 1),
                'sentence' => '',
            ]);
        }

        return $wpdb->insert_id;
    }

    /**
     * Get data for Error table
     *
     * @param $per_page
     * @param $page
     * @param string $orderby
     * @param string $order
     * @return array
     */
    public static function getData($per_page, $page, $orderby = '', $order = '', $post_id = 0)
    {
        global $wpdb;

        $options = get_user_meta(get_current_user_id(), 'report_options', true);

        $get_type = (!empty($options['show_type']) && $options['show_type'] == 'on') ? true: false;

        $where = Linkilo_Build_RecordFilter::errorCodes();

        if(!empty($post_id)){
            $where .= " AND `post_id` = " . (int) $post_id;
        }

        $limit = " LIMIT " . (($page - 1) * $per_page) . ',' . $per_page;

        if ($orderby == 'post') {
            $limit = '';
        }

        $sort = " ORDER BY id DESC ";
        if ($orderby && $order && $orderby != 'post') {
            $sort = " ORDER BY $orderby $order ";
        }

        $post_ids = array();
        $filtering = false;
        if (Linkilo_Build_RecordFilter::linksCategory()) {
            $filtering = true;
            $post_ids = Linkilo_Build_RecordFilter::getLinksCatgeoryIDs();
        }

        if ( ($filtering && !empty($post_ids) || empty($filtering)) && $post_type = Linkilo_Build_RecordFilter::linksPostType()) {
            $filtering = true;
            $error_post_ids = $wpdb->get_col("SELECT `post_id` FROM {$wpdb->prefix}linkilo_broken_links WHERE 1 $where");
            $error_post_ids = implode(',', $error_post_ids);
            $post_ids2 = $wpdb->get_col("SELECT `ID` FROM {$wpdb->posts} WHERE `ID` IN ({$error_post_ids}) AND `post_type` = '{$post_type}'");

            if(!empty($post_ids) && !empty($post_ids2)){
                $post_ids = array_intersect($post_ids2, $post_ids);
            }else{
                $post_ids = array_merge($post_ids, $post_ids2);
            }
        }

        if($filtering){
            if(!empty($post_ids)){
                $post_ids = implode(',', $post_ids);
                $where .= " AND `post_id` IN ({$post_ids})";
            }else{
                $where .= " AND `post_id` = null";
            }
        }

        $total = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}linkilo_broken_links WHERE 1 $where $sort");
        $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_broken_links WHERE 1 $where $sort $limit");
        foreach ($result as $key => $link) {
            $result[$key]->post = '';
            $p = new Linkilo_Build_Model_Feed($link->post_id, $link->post_type);
            if (!empty($p)) {
                $result[$key]->post = '<a href="' . $p->getLinks()->view . '" target="_blank"><strong>' . $p->getTitle() . '</strong></a>
                                    <div class="row-actions">
                                        <span class="view"><a target="_blank" href="' . $p->getLinks()->view . '">View</a> | </span>
                                        <span class="edit"><a target="_blank" href="' . $p->getLinks()->edit . '">Edit</a></span>
                                    </div>';
            }

            if($get_type){
                $result[$key]->post_type = $p->getRealType();
            }

            $result[$key]->post_title = $p->getTitle();
            if(768 == $link->code){
                $result[$key]->ignore_link = '<a class="linkilo_stop_ignore_link" target="_blank" data-link_id="' . $link->id . '" data-post_id="'.$p->id.'" data-post_type="'.$p->type.'" data-anchor="" data-url="'.$link->url.'">' . __('Stop Ignoring', 'linkilo') . '</a>';
            }else{
                $result[$key]->ignore_link = '<a class="linkilo_ignore_link" target="_blank" data-link_id="' . $link->id . '" data-post_id="'.$p->id.'" data-post_type="'.$p->type.'" data-anchor="" data-url="'.$link->url.'">' .  __('Ignore', 'linkilo') . '</a>';
            }
            $result[$key]->edit_link = '<a class="linkilo_edit_link" target="_blank" data-link_id="' . $link->id . '" data-post_id="'.$p->id.'" data-post_type="'.$p->type.'" data-anchor="" data-url="'.$link->url.'" data-nonce="' . wp_create_nonce('linkilo_report_edit_' . $p->id . '_nonce_' . $link->id) . '">' . __('Edit', 'linkilo') . '</a>';
            $result[$key]->delete_icon = '<i data-link_id="' . $link->id . '" data-post_id="'.$p->id.'" data-post_type="'.$p->type.'" data-anchor="" data-url="'.base64_encode($link->url).'" class="linkilo_link_delete broken_link dashicons dashicons-no-alt"></i>';
        }

        if ($orderby == 'post') {
            usort($result, function($a, $b) use($order){
                if ($a->post_title == $b->post_title) {
                    return 0;
                }

                if ($order == 'desc') {
                    return ($a->post_title < $b->post_title) ? 1 : -1;
                } else {
                    return ($a->post_title < $b->post_title) ? -1 : 1;
                }
            });

            $result = array_slice($result, (($page - 1) * $per_page), $per_page);
        }

        return [
            'total' => $total,
            'links' => $result
        ];
    }

    public static function getCodeMessage($code, $code_in_message = false){
        
        $status_codes = array(
            // [cURL 1-9x]
            6   => __('Server Not Found', 'linkilo'),
            7   => __('Connection Failed', 'linkilo'),
            28  => __('Request Timeout', 'linkilo'),
            35  => __('SSL/TLS Connect Error', 'linkilo'),
            56  => __('Network Error', 'linkilo'),
            // [Informational 1xx]
            100 => __('Continue', 'linkilo'),
            101 => __('Switching Protocols', 'linkilo'),
            // [Successful 2xx]
            200 => __('OK', 'linkilo'),
            201 => __('Created', 'linkilo'),
            202 => __('Accepted', 'linkilo'),
            203 => __('Non-Authoritative Information', 'linkilo'),
            204 => __('No Content', 'linkilo'),
            205 => __('Reset Content', 'linkilo'),
            206 => __('Partial Content', 'linkilo'),
            // [Redirection 3xx]
            300 => __('Multiple Choices', 'linkilo'),
            301 => __('Moved Permanently', 'linkilo'),
            302 => __('Moved Temporarily', 'linkilo'),
            303 => __('See Other', 'linkilo'),
            304 => __('Not Modified', 'linkilo'),
            305 => __('Use Proxy', 'linkilo'),
            //306=>'(Unused)',
            307 => __('Temporary Redirect', 'linkilo'),
            // [Client Error 4xx]
            400 => __('Bad Request', 'linkilo'),
            401 => __('Unauthorized', 'linkilo'),
            402 => __('Payment Required', 'linkilo'),
            403 => __('Forbidden', 'linkilo'),
            404 => __('Not Found', 'linkilo'),
            405 => __('Method Not Allowed', 'linkilo'),
            406 => __('Not Acceptable', 'linkilo'),
            407 => __('Proxy Authentication Required', 'linkilo'),
            408 => __('Request Timeout', 'linkilo'),
            409 => __('Conflict', 'linkilo'),
            410 => __('Gone', 'linkilo'),
            411 => __('Length Required', 'linkilo'),
            412 => __('Precondition Failed', 'linkilo'),
            413 => __('Request Entity Too Large', 'linkilo'),
            414 => __('Request-URI Too Long', 'linkilo'),
            415 => __('Unsupported Media Type', 'linkilo'),
            416 => __('Requested Range Not Satisfiable', 'linkilo'),
            417 => __('Expectation Failed', 'linkilo'),
            // [Server Error 5xx]
            500 => __('Internal Server Error', 'linkilo'),
            501 => __('Not Implemented', 'linkilo'),
            502 => __('Bad Gateway', 'linkilo'),
            503 => __('Service Unavailable', 'linkilo'),
            504 => __('Gateway Timeout', 'linkilo'),
            505 => __('HTTP Version Not Supported', 'linkilo'),
            509 => __('Bandwidth Limit Exceeded', 'linkilo'),
            510 => __('Not Extended', 'linkilo'),
            // [Other Errors]
            768 => __('Ignored Link', 'linkilo'),
            925 => __('Link Format Error', 'linkilo'),
            999 => __('Request Denied', 'linkilo'),
		);
        
        $message = (isset($status_codes[$code])) ? $status_codes[$code]: __('Unknown Error', 'linkilo');
        
        // if we're supposed to include the error code and this isn't a curl error, misformatted, or an ignored link
        if($code_in_message && $code > 99 && 925 != $code && 768 != $code){
            // add it at the start of the message
            $message = $code . ' ' . $message;
        }
        
        return $message;
    }

    /**
     * Creates an index of sentences from the link data.
     * Keyed to the url
     **/
    public static function createSentenceIndex($links = array()){
        $index = array();
        if(!empty($links)){
            foreach($links as $link_data){
                $index[$link_data['url']] = $link_data['sentence'];
            }
        }
        return $index;
    }

    /**
     * Updates the link in a given post on ajax call
     **/
    public static function ajaxEditReportLink(){
        // exit if critical data is missing
        if( !isset($_POST['url']) ||
             empty($_POST['url']) ||
            !isset($_POST['new_url']) ||
             empty($_POST['new_url']) ||
            !isset($_POST['post_id']) ||
            !isset($_POST['post_type']) ||
            !isset($_POST['nonce']) ||
            !isset($_POST['link_id']))
        {
            // let the user know that some data was missing
            wp_send_json(array('error' => array('title' => __('Update Error', 'linkilo'), 'text' => __('Link couldn\'t be updated because some data was missing from the request.', 'linkilo'))));
        }

        $old_url = esc_url_raw($_POST['url']);
        $new_url = esc_url_raw($_POST['new_url']);
        $post_id = (int)$_POST['post_id'];
        $link_id = (int) $_POST['link_id'];
        $post_type = $_POST['post_type'];
        
        // if the nonce doesn't check out, exit
        if(!wp_verify_nonce($_POST['nonce'], 'linkilo_report_edit_' . $post_id . '_nonce_' . $link_id) || 0 === $post_id || 0 === $link_id){
            // let the user know that reloading the page _should_ fix it.
            wp_send_json(array('error' => array('title' => __('Update Error', 'linkilo'), 'text' => __('Couldn\'t process the data because the authentication was out of date. Please reload the page and try again.', 'linkilo'))));
        }
        
        // if the old link is the same as the new link
        if($old_url === $new_url){
            // let the user know that we won't be updating the link
            wp_send_json(array('error' => array('title' => __('Link Unchanged', 'linkilo'), 'text' => __('The new link is the same as the original one, so we\'re unable to update it.', 'linkilo'))));
        }
        
        // update the link in the body content
        $status = Linkilo_Build_PostUrl::updateExistingLink($post_id, $post_type, $old_url, $new_url);
        
        // if the link has been successfully updated
        if($status){
            $post = new Linkilo_Build_Model_Feed($post_id, $post_type);
            if(isset($_POST['status']) && $_POST['status'] === 'domains'){
                Linkilo_Build_UrlRecord::update_post_in_link_table($post);
            }else{
                // remove the old reference from the broken_links table
                self::deleteLink($link_id);
            }

            // update the post's stats to reflect the changes
            Linkilo_Build_UrlRecord::statUpdate($post);

            // and tell the user the good news!
            wp_send_json(array('success' => array('title' => __('Link Updated!', 'linkilo'), 'text' => __('The link has been successfully updated!', 'linkilo'))));
        }        
        
        // if we made it this far, the link must not have been updated... Probably because the post content has changed
        wp_send_json(array('error' => array('title' => __('Update Error', 'linkilo'), 'text' => __('Couldn\'t find the link to update. It\'s possible the link has been changed or removed since the last time the error scan was run.', 'linkilo'))));
    }

    /**
     * Sets a link in the error table to "ignored" status.
     */
    public static function markLinkIgnored(){
        global $wpdb;

        if(!isset($_POST['url'])){
            return;
        }

        $url = htmlentities(esc_url_raw($_POST['url']));

        if(!empty($url)){
            // make sure the url is in the DB
            if(self::linkSaved($url)){
                // if it is, set the link to "ignored"
                $wpdb->update($wpdb->prefix . "linkilo_broken_links", array('code' => 768, 'ignore_link' => 1), array('url' => $url), array('%d', '%d'));
            }
        }
    }

    /**
     * Unmarks a link from being ignored by the scan, and does a scan of the link to see what it's status is.
     */
    public static function unmarkLinkIgnored(){
        global $wpdb;
        $broken_links = $wpdb->prefix . "linkilo_broken_links";

        if(!isset($_POST['url'])){
            return;
        }

        $url = htmlentities(esc_url_raw($_POST['url']));
        if(!empty($url)){
            // make sure the url is in the DB and it's being ignored
            $link = $wpdb->get_results("SELECT * FROM {$broken_links} WHERE `url` = '{$url}' && `ignore_link` = 1 LIMIT 1");
            if(!empty($link)){
                // if it is, do a rescan of the link and reset it's status
                $link = $link[0];
                $head = Linkilo_Build_PostUrl::getResponseCodes(array($url), true);
                $get  = Linkilo_Build_PostUrl::getResponseCodes(array($url));
                $code_1 = (isset($head[$url])) ? $head[$url] : 0;
                $code_2 = (isset($get[$url])) ? $get[$url] : 0;
                $updated_code = $link->code;

                if(200 == $code_1 || 200 == $code_2){
                    $updated_code = 200;
                }elseif($code_1 > 99 && $code_2 < 100){
                    $updated_code = $code_1;
                }elseif($code_2 > 0){
                    $updated_code = $code_2;
                }
    
                if(200 === $updated_code){
                    // if the link is good, remove it from the DB
                    $wpdb->delete($broken_links, array('id' => $link->id));
                }else{
                    // if it's not, update the listing
                    $wpdb->update($broken_links, array('code' => $updated_code, 'last_checked' => current_time('mysql', 1), 'check_count' => 1, 'ignore_link' => 0), array('id' => $link->id));
                }
            }
        }
    }

    /**
     * Delete link record from DB
     *
     * @param $link_id
     */
    public static function deleteLink($link_id)
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'linkilo_broken_links', ['id' => $link_id]);
    }

    /**
     * Check if URL is already saved in the DB
     *
     * @param $url
     * @return string|null
     */
    public static function linkSaved($url)
    {
        global $wpdb;
        $count = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}linkilo_broken_links WHERE url = '$url'");
        return !empty($count) ? true : false;
    }

    public static function mergeIgnoreLinks()
    {
        global $wpdb;
        $ignore_links = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_ignore_links");
        
        foreach($ignore_links as $link_data){
            // if the current ignore link is in the error table
            if(self::linkSaved($link_data->url)){
                // set it's status to "ignored"
                $wpdb->update($wpdb->prefix . "linkilo_broken_links", array('code' => 768, 'ignore_link' => 1, 'created' => $link_data->created), array('url' => $link_data->url), array('%d', '%d', '%s'));
            }
        }
    }

    public static function deleteValidLinks()
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}linkilo_broken_links WHERE code IN (200, 301, 302)");
    }
    
    /**
     * Controls what broken link related check is going to be perfomed via cron.
     * Currently, toggles between scanning unscanned links to see if they're broken and checking broken links to make sure they're broken
     **/
    public static function performCronErrorChecks(){
        global $wpdb;

        // check for the error table before running any of this
        $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_broken_links'");
        if(empty($error_tbl_exists)){
            // exit if it doesn't exist
            return;
        }

        if(empty(get_option('linkilo_error_cron_toggle', false))){
            update_option('linkilo_error_cron_toggle', 0);
            self::cronLinkScan();
        }else{
            update_option('linkilo_error_cron_toggle', 1);
            self::cronCheckLink();
        }
    }

    /**
     * Scans the report links table in small batches looking for broken links.
     * Runs via cron task
     **/
    public static function cronLinkScan(){
        $links = self::getReportLinksToCheck();
        $saved_broken_links = false;

        if(!empty($links)){
            // format the data for use
            $links_to_check = array();
            $link_data = array();
            $link_ids = array();
            foreach($links as $link){
                $links_to_check[] = $link->raw_url;
                $link_data[$link->raw_url] = $link;
                $link_ids[] = $link->link_id;
            }

            // mark the links as checked now in case there's an error in processing.
            // This will allow the processing of the link report table to continue even if there's a link that consistently causes a problem
            self::updateCheckedReportLinks($link_ids);

            // remove any broken links that already are in the broken links table
            $links_to_check = self::removeExistingBrokenLinks($links_to_check);

            if(empty($links_to_check)){
                return;
            }

            // make a HEAD call for the batch of links
            $codes_1 = Linkilo_Build_PostUrl::getResponseCodes($links_to_check, true);

            // go over the links and remove the good ones
            foreach($codes_1 as $url => $code_1){
                if($code_1 > 199 || $code_1 < 300){
                    unset($codes_1[$url]);
                }
            }

            // if all of the links were good, exit now since we're done
            if(empty($codes_1)){
                return;
            }

            // if there are still some broken linkst, double check them with the GET method
            $codes_2 = Linkilo_Build_PostUrl::getResponseCodes(array_keys($codes_1));

            if(LINKILO_DEBUG_CURL){
                $test = fopen(trailingslashit(WP_CONTENT_DIR) . 'curl_link_check_cron_log.log', 'a');     // logs the actions that curl goes through in contacting the server
                fwrite($test, print_r(array('from the cron based link checker process', $codes_1, $codes_2),true));
            }

            // create a cache of post objects
            $post_cache = array();

            // go over the second set of codes
            foreach($codes_2 as $url => $code_2){
                // skip any good codes
                if($code_2 > 199 || $code_2 < 300){
                    continue;
                }

                $data = $link_data[$url];
                $id = $data->post_type . '_' . $data->post_id;
                $post = (isset($post_cache[$id])) ? $post_cache[$id]: new Linkilo_Build_Model_Feed($data->post_id, $data->post_type);

                // compare the results of the GET request against the HEAD request to see which one we'll be storing
                if($codes_1[$url] > 99 && $code_2 < 100){     // if the HEAD method got an http code, while the GET method got a curl error
                    $url_sentence = self::getUrlSentence($url, $data->anchor, $post->getContent());
                    self::saveLink($url, $post, $code_1[$url], $url_sentence);
                    $saved_broken_links = true;

                }elseif($code_2 > 0){// if the last two were false, go with the GET method results since they tend to be more correct
                    $url_sentence = self::getUrlSentence($url, $data->anchor,  $post->getContent());
                    self::saveLink($url, $post, $code_2, $url_sentence);
                    $saved_broken_links = true;
                }

                // save the post to the cache since it's likely we'll need it again
                $post_cache[$id] = $post;
            }
        }

        // if we've saved links
        if($saved_broken_links){
            // make sure they'll be double checked
            update_option('linkilo_error_check_links_cron', 1);
        }
    }

    /**
     * Obtains a set of links from the link report table to run through the broken link checker.
     * Part of the cron-based broken link checker.
     * Doesn't obtain links that are already in the broken link report
     **/
    public static function getReportLinksToCheck(){
        global $wpdb;
        $links_table = $wpdb->prefix . "linkilo_report_links";

        // exit if the link table doesn't exist
        if(!Linkilo_Build_UrlRecord::link_table_is_created()){
            return false;
        }

        $option = (int) get_option('linkilo_error_scan_toggle', 0);

        $links = $wpdb->get_results("SELECT `link_id`, `post_id`, `post_type`, `clean_url`, `raw_url`, `anchor` FROM {$links_table} WHERE `broken_link_scanned` = {$option} AND `location` = 'content' LIMIT 10");

        // if we didn't find any broken links, flip the scan flag so we can re-check previously scanned links
        if(empty($links) && !empty($wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}linkilo_broken_links"))){
            $option = !empty($option) ? 1: 0;
            update_option('linkilo_error_scan_toggle', $option);
        }

        return $links;
    }

    /**
     * Updates the report links that have just been checked by the error checker.
     * @param array $link_ids
     **/
    public static function updateCheckedReportLinks($link_ids = array()){
        global $wpdb;
        $links_table = $wpdb->prefix . "linkilo_report_links";

        if(empty($link_ids)){
            return;
        }

        $link_ids = implode(', ', $link_ids);

        $wpdb->query("UPDATE {$links_table} SET broken_link_scanned = 1 WHERE link_id IN ({$link_ids})");
    }

    /**
     * Checks the broken link table for existing copies of the supplied urls.
     * If the url does exist, this will remove the url from the supplied url list.
     * @param array $url_list
     * @return array $url_list
     **/
    public static function removeExistingBrokenLinks($url_list = array()){
        global $wpdb;
        $broken_links = $wpdb->prefix . "linkilo_broken_links";

        if(empty($url_list)){
            return $url_list;
        }

        $search_urls = implode('\', \'', $url_list);
        $found_urls = $wpdb->get_col("SELECT `url` FROM {$broken_links} WHERE `url` IN ('{$search_urls}')");

        // if we've fount some matching urls
        if(!empty($found_urls)){
            // go over the urls and remove them from the url list
            foreach($url_list as $key => $url){
                if(in_array($url, $found_urls, true)){
                    unset($url_list[$key]);
                }
            }
        }

        return $url_list;
    }

    /**
     * Obtains the sentence text surrounding the given url.
     * @param string $url
     * @param string $anchor
     * @param string $content
     * @param string $return_text
     **/
    public static function getUrlSentence($url, $anchor, $content){
        $return_text = '';
        $found = preg_match('`(\!|\?|\.|^|)([^.!?\n]*<a\s.*?(?:href=[\'"]' . preg_quote($url, '`') . '[\'"]).*?>' . preg_quote($anchor, '`') . '<\/a>((?!<a)[^.!?\n])*)`i', $content, $matches);

        if($found){
            $return_text = strip_tags($matches[2]);
        }

        return $return_text;
    }

    public static function cronCheckLink(){
        global $wpdb;
        $broken_links = $wpdb->prefix . "linkilo_broken_links";

        $run_scan = get_option('linkilo_error_check_links_cron', 0);
        if(empty($run_scan)){
            return;
        }

        // TODO: delete when we're pretty sure we don't need this check. Added in v1.0.0
        // find out if the table has a last_checked col
        $col = $wpdb->query("SHOW COLUMNS FROM {$broken_links} LIKE 'last_checked'");
        if(empty($col)){
            return;
        }

        // get the link that's gone the longest without being checked and has been checked less than 10 times
        if(1.0 < LINKILO_CHECK_STATUS_OF_DATABASE_VERSION_OF_SITE){
            $link = $wpdb->get_results("SELECT * FROM {$broken_links} WHERE `check_count` < 10 && `ignore_link` = 0 ORDER BY `last_checked` ASC LIMIT 1");
        }else{
            $link = $wpdb->get_results("SELECT * FROM {$broken_links} WHERE `check_count` < 10 ORDER BY `last_checked` ASC LIMIT 1");
        }
        if(!empty($link)){
            $link = $link[0];
            $head = Linkilo_Build_PostUrl::getResponseCodes(array($link->url), true);
            $get  = Linkilo_Build_PostUrl::getResponseCodes(array($link->url));
            $code_1 = (isset($head[$link->url])) ? $head[$link->url] : 0;
            $code_2 = (isset($get[$link->url])) ? $get[$link->url] : 0;
            $updated_code = $link->code;

            if(200 == $code_1 || 200 == $code_2){       // if one of the methods said the link is good
                $updated_code = 200;
            }elseif($code_1 > 99 && $code_2 < 100){     // if the HEAD method got an http code, while the GET method got a curl error
                $updated_code = $code_1;
            }elseif($code_2 > 0){                       // if the last two were false, go with the GET method results since they tend to be more correct
                $updated_code = $code_2;
            }

            if(200 === $updated_code){
                // if the link is good, remove it from the DB
                $wpdb->delete($broken_links, array('id' => $link->id));
            }else{
                // if it's not, update the listing
                $wpdb->update($broken_links, array('code' => $updated_code, 'last_checked' => current_time('mysql', 1), 'check_count' => ($link->check_count + 1)), array('id' => $link->id));
            }

        }else{
            // if there are no links, disable the scan
            update_option('linkilo_error_check_links_cron', 0);
        }

        if(LINKILO_DEBUG_CURL){
            $test = fopen(trailingslashit(WP_CONTENT_DIR) . 'curl_link_check_cron_log.log', 'a');     // logs the actions that curl goes through in contacting the server
            fwrite($test, print_r(array($code_1, $code_2, $link, $col),true));
        }
    }

    public static function addLinkCheckInterval($schedules){
        if(!isset($schedules['5min'])){
            $schedules['5min'] = array(
                'interval' => 60 * 5,
                'display' => __('Every five Minutes', 'linkilo')
            );
        }
        return $schedules;
    }

    /**
     * Schedules the broken link checks if the user hasn't disabled checking.
     * If the user has, then it disables the checks
     **/
    public static function scheduleLinkCheck(){
        if(empty(get_option('linkilo_disable_broken_link_cron_check', false))){
            if(!wp_get_schedule('linkilo_broken_link_check_cron')){
                wp_schedule_event(time(), '5min', 'linkilo_broken_link_check_cron');
            }
        }elseif(wp_get_schedule('linkilo_broken_link_check_cron')){
            self::clearCronSchedules();
        }
    }

    public static function clearCronSchedules(){
        $timestamp = wp_next_scheduled('linkilo_broken_link_check_cron');
        wp_unschedule_event($timestamp, 'linkilo_broken_link_check_cron');
    }

    public static function getLinkById($id) {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}linkilo_broken_links WHERE id = " . $id);
    }

    public static function getBrokenLinkCountByPostId($id) {
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(`id`) FROM {$wpdb->prefix}linkilo_broken_links WHERE `post_id` = " . (int) $id . " AND `ignore_link` = 0");
        return (!empty($count)) ? $count: 0;
    }

    public static function ajaxDeleteLinks() {
        if (empty($_POST['links'])) {
            wp_send_json(array('error' => array('title' => __('Error', 'linkilo'), 'text' => __('No links selected.', 'linkilo'))));
        }

        $links = !empty($_POST['links']) ? $_POST['links'] : [];
        foreach ($links as $link) {
            $link = self::getLinkById($link);
            if ($link) {
                Linkilo_Build_PostUrl::delete([
                    'link_id' => $link->id,
                    'post_id' => $link->post_id,
                    'post_type' => $link->post_type,
                    'url' => $link->url,
                ], true);
            }
        }

        wp_send_json(array('success' => true));
    }
}
