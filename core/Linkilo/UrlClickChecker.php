<?php

/**
 * Handles all of the click tracking related functionality
 */
class Linkilo_Build_UrlClickChecker
{

    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_linkilo_url_clicked', array(__CLASS__, 'ajax_url_clicked'));
        add_action('wp_ajax_nopriv_linkilo_url_clicked', array(__CLASS__, 'ajax_url_clicked'));
        add_action('wp_ajax_linkilo_clear_url_click_data', array(__CLASS__, 'ajax_clear_url_click_data')); // clear is for erasing all click data
        add_action('wp_ajax_linkilo_delete_url_click_data', array(__CLASS__, 'ajax_delete_url_click_data')); // delete is for specific pieces of click data
        self::init_cron();
    }

    /**
     * Creates the click tracking table if it doesn't already exist
     **/
    public static function prepare_table(){
        global $wpdb;

        $clicks_table = $wpdb->prefix . 'linkilo_url_click_data';

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$clicks_table}'");
        if($table != $clicks_table){
            $clicks_table_query = "CREATE TABLE IF NOT EXISTS {$clicks_table} (
                                        click_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                        post_id bigint(20) unsigned,
                                        post_type varchar(10),
                                        click_date datetime,
                                        user_ip varchar(191),
                                        user_id bigint(20) unsigned,
                                        link_url text,
                                        link_anchor text,
                                        PRIMARY KEY (click_id),
                                        INDEX (post_id),
                                        INDEX (link_url(191))
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($clicks_table_query);
        }
    }

    /**
     * Clears the data in the click table if it exists
     **/
    public static function clear_click_tracking_table(){
        global $wpdb;

        $clicks_table = $wpdb->prefix . 'linkilo_url_click_data';

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$clicks_table}'");
        if($table === $clicks_table){
            $updated = $wpdb->query("TRUNCATE TABLE {$clicks_table}");
        }else{
            // if the table doesn't exist, create it
            self::prepare_table();
            $updated = true;
        }

        if(!empty($updated)){
            wp_send_json(array(
                'success' => array(
                    'title' => __('Click Data Cleared', 'linkilo'),
                    'text'  => __('The click data has been successfully cleared!', 'linkilo'),
            )));
        }else{
            wp_send_json(array(
                'error' => array(
                    'title' => __('Database Error', 'linkilo'),
                    'text'  => sprintf(__('There was an error in creating the links database table. The error message was: %s', 'linkilo'), $wpdb->last_error),
            )));
        }
    }

    /**
     * Inits cron
     **/
    public static function init_cron(){
        if(!empty(get_option('linkilo_delete_old_click_data', '0'))){
            add_action('admin_init', array(__CLASS__, 'schedule_click_data_delete'));
            add_action('linkilo_scheduled_click_data_delete', array(__CLASS__, 'do_scheduled_click_data_delete'));
        }

        register_deactivation_hook(__FILE__, array(__CLASS__, 'clear_cron_schedules'));
    }

    /**
     * Schedules the click data deletion hook
     **/
    public static function schedule_click_data_delete(){
        if(!wp_get_schedule('linkilo_scheduled_click_data_delete')){
            wp_schedule_event(time(), 'daily', 'linkilo_scheduled_click_data_delete');
        }
    }

    /**
     * Deletes click data that's older than the user's selection in the settings via cron
     **/
    public static function do_scheduled_click_data_delete(){
        global $wpdb;
        $click_table = $wpdb->prefix . 'linkilo_url_click_data';
        $delete_age = get_option('linkilo_delete_old_click_data', '0');

        // if the user has disabled click data deleting
        if(empty($delete_age)){
            // unschedule the the cron task for future runs
            $timestamp = wp_next_scheduled('linkilo_delete_old_click_data');
            if(!empty($timestamp)){
                wp_unschedule_event($timestamp, 'linkilo_delete_old_click_data');
            }
            // and exit
            return;
        }

        $delete_time = (time() - ($delete_age * DAY_IN_SECONDS) );
        $date = date('Y-m-d H:i:s', $delete_time);

        if(empty($date)){
            return;
        }

        $wpdb->query("DELETE FROM {$click_table} WHERE `click_date` < '{$date}'");
    }

    /**
     * Stores data related to the user's recent link click
     **/
    public static function ajax_url_clicked(){
        // exit if any critical data is missing
        if( !isset($_POST['post_id']) || 
            !isset($_POST['post_type']) || 
            !isset($_POST['link_url']) || 
            !isset($_POST['link_anchor']))
        {
            die();
        }

        global $wpdb;

        // assemble the click data
        $post_id = intval($_POST['post_id']);
        $post_type = ($_POST['post_type'] === 'term') ? 'term': 'post';
        $url = esc_url_raw(urldecode($_POST['link_url']));
        $anchor = sanitize_text_field(urldecode($_POST['link_anchor']));

        // get some user data
        $user_ip = self::get_current_client_ip();
        $user_id = get_current_user_id();

        // if the user is an admin, exit
        if(!empty($user_id) && current_user_can('edit_posts')){
            die();
        }

        // get when the click was made
        $click_time = current_time('mysql', true);

        // create in the insert data
        $insert_data = array(
            'post_id' => $post_id, 
            'post_type' => $post_type, 
            'click_date' => $click_time, 
            'user_ip' => $user_ip,
            'user_id' => $user_id,
            'link_url' => $url,
            'link_anchor' => $anchor
        );

        // create the format array
        $format_array = array(
            '%d', // post_id
            '%s', // post_type
            '%s', // click_date
            '%s', // user_ip
            '%d', // user_id
            '%s', // url
            '%s', // anchor
        );

        // save the click data to the database
        $wpdb->insert($wpdb->prefix . 'linkilo_url_click_data', $insert_data, $format_array);

        // and exit
        die();
    }

    /**
     * Clears the stored click data on ajax call
     **/
    public static function ajax_clear_url_click_data(){

        Linkilo_Build_Root::verify_nonce('linkilo_clear_clicks_data');

        if(isset($_POST['clear_data'])){
            self::clear_click_tracking_table();
        }
        
        die();
    }

    /**
     * Deletes a specific piece of click data on ajax call
     **/
    public static function ajax_delete_url_click_data(){
        Linkilo_Build_Root::verify_nonce('delete_click_data');

        if( !array_key_exists('click_id', $_POST) || 
            !array_key_exists('post_id', $_POST) ||
            !isset($_POST['post_type']) ||
            !array_key_exists('anchor', $_POST) ||
            !isset($_POST['url']))
        {
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'linkilo'),
                    'text'  => __('There was some data missing from the request, please reload the page and try again.', 'linkilo'),
                )
            ));
        }

        global $wpdb;
        $click_table = $wpdb->prefix . 'linkilo_url_click_data';
        $click_id = (int)$_POST['click_id'];
        $post_id = (int)$_POST['post_id'];
        $post_type = ($_POST['post_type'] === 'term' ? 'term' : 'post');
        $anchor = sanitize_text_field(stripslashes($_POST['anchor']));
        $url = esc_url_raw(base64_decode($_POST['url']));
        $query = '';

        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $show_click_traffic = (isset($options['show_click_traffic'])) ? true : false;

        // if we're working with individual clicks
        if($show_click_traffic){
            // delete the single instance
            $query = $wpdb->prepare("DELETE FROM {$click_table} WHERE `click_id` = %d", $click_id);
        }else{
            // if we're working with aggregate data, delete the data from the post
            $query = $wpdb->prepare("DELETE FROM {$click_table} WHERE `post_id` = %d AND `post_type` = %s AND `link_url` = %s AND `link_anchor` = %s", $post_id, $post_type, $url, $anchor);
        }

        $deleted = $wpdb->query($query);

        if(!empty($deleted) && empty($wpdb->last_error)){
            $response = array('success' => array(
                'title' => __('Success', 'linkilo'),
                'text'  => __('The click data has been successfully deleted!', 'linkilo'),
            ));
        }elseif(!empty($wpdb->last_error)){
            $response = array('error' => array(
                'title' => __('Data Error', 'linkilo'),
                'text'  => sprint_f(__('There was an error when trying to delete the click data. The error was: %s', 'linkilo'), $wpdb->last_error),
            ));
        }else{
            $response = array('error' => array(
                'title' => __('Data Error', 'linkilo'),
                'text'  => __('Unfortunately, the click data couldn\'t be deleted. Please reload the page and try again.', 'linkilo'),
            ));
        }

        wp_send_json($response);
    }

    /**
     * Gets the user's ip address.
     * @return string $ipaddress The user's ip address.
     **/
    public static function get_current_client_ip() {
        $ipaddress = (isset($_SERVER['REMOTE_ADDR']))?sanitize_text_field( $_SERVER['REMOTE_ADDR'] ):'';

        if(isset($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP'] != '127.0.0.1') {
            $ipaddress = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
        }
        elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '127.0.0.1') {
            $ipaddress = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
        }
        elseif(isset($_SERVER['HTTP_X_FORWARDED']) && $_SERVER['HTTP_X_FORWARDED'] != '127.0.0.1') {
            $ipaddress = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED'] );
        }
        elseif(isset($_SERVER['HTTP_FORWARDED_FOR']) && $_SERVER['HTTP_FORWARDED_FOR'] != '127.0.0.1') {
            $ipaddress = sanitize_text_field( $_SERVER['HTTP_FORWARDED_FOR'] );
        }
        elseif(isset($_SERVER['HTTP_FORWARDED']) && $_SERVER['HTTP_FORWARDED'] != '127.0.0.1') {
            $ipaddress = sanitize_text_field( $_SERVER['HTTP_FORWARDED'] );
        }

        $ips = explode(',', $ipaddress);
        if(isset($ips[1])) {
            $ipaddress = $ips[0]; //Fix for flywheel
        }

        return $ipaddress;
    }

    public static function get_data($limit=20, $start = 0, $search='', $orderby = '', $order = 'desc'){
        global $wpdb;

        $clicks_table = $wpdb->prefix . 'linkilo_url_click_data';

        //check if it need to show categories in the list
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $show_categories = (!empty($options['show_categories']) && $options['show_categories'] == 'off') ? false : true;
        $hide_ignored = (isset($options['hide_ignore'])) ? ( ($options['hide_ignore'] == 'off') ? false : true) : false;
        $hide_noindex = (isset($options['hide_noindex'])) ? ( ($options['hide_noindex'] == 'off') ? false : true) : false;
        $limit = (int)$limit;
        $start = (int)$start;
        $search = sanitize_text_field($search);
        $orderby = sanitize_text_field($orderby);
        $order = (strtolower($order) === 'desc') ? 'DESC': 'ASC';
        $process_terms = !empty(Linkilo_Build_AdminSettings::getTermTypes());


        // set the limit and offset
        $limit = " LIMIT " . (($start - 1) * $limit) . ',' . $limit;

        $post_types = "'" . implode("','", Linkilo_Build_AdminSettings::getPostTypes()) . "'";

        //create search query requests
        $term_search = '';
        $title_search = '';
        $term_title_search = '';
        if (!empty($search)) {
            $is_internal = Linkilo_Build_PostUrl::isInternal($search);
            $search_post = Linkilo_Build_Feed::getPostByLink($search);
            if ($is_internal && $search_post && ($search_post->type != 'term' || ($show_categories && $process_terms))) {
                if ($search_post->type == 'term') {
                    $term_search = " AND t.term_id = {$search_post->id} ";
                    $search = " AND 2 > 3 ";
                } else {
                    $term_search = " AND 2 > 3 ";
                    $search = " AND p.ID = {$search_post->id} ";
                }
            } else {
                $term_title_search = ", IF(t.name LIKE '%$search%', 1, 0) as title_search ";
                $title_search = ", IF(p.post_title LIKE '%$search%', 1, 0) as title_search ";
                $term_search = " AND (t.name LIKE '%$search%' OR tt.description LIKE '%$search%') ";
                $search = " AND (p.post_title LIKE '%$search%' OR p.post_content LIKE '%$search%') ";
            }
        }

        //filters
        $post_ids = Linkilo_Build_RecordFilter::getLinksLocationIDs();
        if (Linkilo_Build_RecordFilter::linksCategory()) {
            $process_terms = false;
            if (!empty($post_ids)) {
                $post_ids = array_intersect($post_ids, Linkilo_Build_RecordFilter::getLinksCatgeoryIDs());
            } else {
                $post_ids = Linkilo_Build_RecordFilter::getLinksCatgeoryIDs();
            }
        }

        if (!empty($post_ids)) {
            $search .= " AND p.ID IN (" . implode(', ', $post_ids) . ") ";
        }

        if ($post_type = Linkilo_Build_RecordFilter::linksPostType()) {
            $term_search .= " AND tt.taxonomy = '$post_type' ";
            $search .= " AND p.post_type = '$post_type' ";
        }

        //sorting
        if (empty($orderby) && !empty($title_search)) {
            $orderby = 'title_search';
            $order = 'DESC';
        } elseif (empty($orderby) || $orderby == 'date') {
            $orderby = 'post_date';
        }

        //get data
        $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses('p');
        $report_post_ids = Linkilo_Build_DatabaseQuery::reportPostIds(false, $hide_noindex);
        $report_term_ids = Linkilo_Build_DatabaseQuery::reportTermIds(false, $hide_noindex);

        $link_filters = Linkilo_Build_RecordFilter::filterLinkCount();
        if($link_filters){
            switch($link_filters['link_type']){
                case 'incoming-internal':
                    $key = 'linkilo_links_incoming_internal_count';
                    break;
                case 'outgoing-internal':
                    $key = 'linkilo_links_outgoing_internal_count';
                    break;
                case 'outgoing-external':
                default:
                    $key = 'linkilo_links_outgoing_external_count';
                    break;
            }

            $filter_query = " meta_key = '{$key}' AND meta_value >= {$link_filters['link_min_count']}";
            $filter_query .= ($link_filters['link_max_count'] !== null) ? " AND meta_value <= {$link_filters['link_max_count']}": '';
            
            if(!empty($report_post_ids)){
                $report_post_ids = str_replace('AND p.ID', 'post_id', $report_post_ids);
                $report_post_ids = $wpdb->get_col("SELECT `post_id` FROM $wpdb->postmeta WHERE $report_post_ids AND $filter_query");
                $report_post_ids = !empty($report_post_ids) ? " AND p.ID IN (" . implode(',', $report_post_ids) . ")" : "AND p.ID = null";
            }

            if(!empty($report_term_ids)){
                $report_term_ids = "term_id IN ($report_term_ids)";
                $report_term_ids = $wpdb->get_col("SELECT `term_id` FROM $wpdb->termmeta WHERE $report_term_ids AND $filter_query");
                $report_term_ids = implode(',', $report_term_ids);
            }
        }

        // hide ignored
        $ignored_posts = '';
        $ignored_terms = '';
        if($hide_ignored){
            $ignored_posts = Linkilo_Build_DatabaseQuery::ignoredPostIds();
            if($show_categories){
                $ignored_terms = Linkilo_Build_DatabaseQuery::ignoredTermIds();
            }
        }

        //create query for other orders
        $query = "SELECT a.ID, a.post_title, a.post_type, a.post_date, a.type, COUNT(`click_id`) as clicks FROM (SELECT p.ID, p.post_title, p.post_type, p.post_date as `post_date`, 'post' as `type` $title_search  
                    FROM {$wpdb->prefix}posts p 
                    WHERE 1 = 1 $report_post_ids $statuses_query $ignored_posts AND p.post_type IN ($post_types) $search";

        if ($show_categories && $process_terms && !empty($report_term_ids)) {
            $taxonomies = Linkilo_Build_AdminSettings::getTermTypes();
            $query .= " UNION
                        SELECT t.term_id as `ID`, t.name as `post_title`, tt.taxonomy as `post_type`, NOW() as `post_date`, 'term' as `type` $term_title_search  
                        FROM {$wpdb->prefix}termmeta m INNER JOIN {$wpdb->prefix}terms t ON m.term_id = t.term_id INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
                        WHERE t.term_id in ($report_term_ids) $ignored_terms AND tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $term_search";
        }

        $query .= ") a LEFT JOIN {$clicks_table} c ON c.post_id = a.ID AND c.post_type = a.type GROUP BY ID ORDER BY {$orderby} {$order} {$limit}";


        $result = $wpdb->get_results($query);

        //calculate total count
        $total_items = self::get_total_items($query);

        //prepare report data
        foreach ($result as $key => &$post_data) {
            if ($post_data->type == 'term') {
                $p = new Linkilo_Build_Model_Feed($post_data->ID, 'term');
                $incoming = admin_url("admin.php?term_id={$post_data->ID}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI']));
            } else {
                $p = new Linkilo_Build_Model_Feed($post_data->ID);
                $incoming = admin_url("admin.php?post_id={$post_data->ID}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI']));
            }

            $post_data->post = $p;
            $post_data->links_incoming_page_url = $incoming;
        }

        return array( 'data' => $result , 'total_items' => $total_items);

    }

    /**
     * Get total items depend on filters
     *
     * @param $query
     * @return string|null
     */
    public static function get_total_items($query)
    {
        global $wpdb;

        $query = str_replace('UNION', 'UNION ALL', $query);
        $limit = strpos($query, ' LIMIT');
        $query = "SELECT count(*) FROM (" . substr($query, 0, $limit) . ") as t1";
        return $wpdb->get_var($query);
    }

    public static function get_detailed_click_table_data($id, $type = 'post', $page = 1, $orderby = '', $order = 'desc', $range = array('start' => false, 'end' => false)){

        global $wpdb;

        $clicks_table = $wpdb->prefix . 'linkilo_url_click_data';

        //check if it need to show categories in the list
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $limit = (isset($options['per_page'])) ? $options['per_page'] : 20;
        $show_click_traffic = (isset($options['show_click_traffic'])) ? true : false;
        $search = (isset($_POST['keywords']) && !empty($_POST['keywords'])) ? sanitize_text_field($_POST['keywords']): '';
        $orderby = (in_array($orderby, array('post_id', 'link_url', 'link_anchor', 'click_date', 'user_ip', 'total_clicks'), true)) ? $orderby: '';
        $order = ($order === 'desc') ? 'desc': 'asc';
        $start = (isset($range['start']) && !empty($range['start'])) ? date('Y-m-d H:i:s', intval($range['start'])): date('Y-m-d H:i:s', (time() - (30 * DAY_IN_SECONDS)));
        $end = (isset($range['end']) && !empty($range['end'])) ? date('Y-m-d H:i:s', intval($range['end'])): date('Y-m-d H:i:s', time());

        if(!empty($orderby)){
            $orderby = "ORDER BY `{$orderby}` {$order}";
        }

        // set the limit and offset
        $limit = "LIMIT " . (((int)$page - 1) * $limit) . ',' . $limit;

        $count_clicks = (!$show_click_traffic) ? ", COUNT(`link_url`) AS 'total_clicks'": '';
        $group_clicks = (!$show_click_traffic) ? "GROUP BY `link_url`, `link_anchor`": '';
        $group_posts  = (!$show_click_traffic) ? "GROUP BY `post_id`": '';

        if($type === 'url'){
            $id = esc_url_raw($id);
            if(!empty($search)){
                $query = "SELECT post_id, a.post_type AS post_type, click_date, user_ip, user_id, link_url, link_anchor {$count_clicks} FROM {$clicks_table} a LEFT JOIN {$wpdb->posts} b ON a.post_id = b.ID WHERE a.link_url = '{$id}' AND a.click_date > '{$start}' AND '{$end}' > a.click_date AND (a.link_anchor LIKE '%{$search}%' OR b.post_title LIKE '%{$search}%') {$group_posts} {$orderby} {$limit}";
            }else{
                $query = "SELECT * {$count_clicks} FROM {$clicks_table} WHERE `link_url` = '{$id}' AND `click_date` > '{$start}' AND '{$end}' > `click_date` {$group_posts} {$orderby} {$limit}";
            }
        }else{
            $id = intval($id);
            $type = ($type === 'post') ? 'post': 'term';
            if(!empty($search)){
                $search = "AND (`link_url` LIKE '%{$search}%' OR `link_anchor` LIKE '%{$search}%')";
            }

            $query = "SELECT  `post_id`, `post_type`, `link_url`, `link_anchor` {$count_clicks} FROM {$clicks_table} WHERE `post_id` = {$id} AND `post_type` = '{$type}' AND `click_date` > '{$start}' AND '{$end}' > `click_date` {$search} {$group_clicks} {$orderby} {$limit}";

            if($show_click_traffic){
                $query = "SELECT * FROM {$clicks_table} WHERE `post_id` = {$id} AND `post_type` = '{$type}' AND `click_date` > '{$start}' AND '{$end}' > `click_date` {$search} {$orderby} {$limit}";
            }
        }

        $result = $wpdb->get_results($query);
        $total_items = !empty($result) ? self::get_total_detailed_click_items($query) : 0;

        return array( 'data' => $result , 'total_items' => $total_items);
    }

    /**
     * Get total items depend on filters
     *
     * @param $query
     * @return string|null
     */
    public static function get_total_detailed_click_items($query)
    {
        global $wpdb;

        $limit = strpos($query, 'LIMIT');
        $query = "SELECT count(*) FROM (" . substr($query, 0, $limit) . ") as t1";
        return $wpdb->get_var($query);
    }

    /**
     * Gets data for the click report dropdowns.
     * @param int $post_id
     * @param string (post|term) $post_type The LW post type, so is it a 'post' or a 'term? Should really be called something like 'data_type'.
     **/
    public static function get_click_dropdown_data($post_id, $post_type){
        global $wpdb;
        
        $post_id = (int) $post_id;
        $post_type = ($post_type === 'post') ? 'post': 'term';
        $range = wp_date('Y-m-d H:i:s', (time() - (30 * DAY_IN_SECONDS)));

        $query = "SELECT    b.`link_url`,
                            b.`link_anchor`, 
                            COUNT(b.link_url) AS 'most_clicked_count', 
                            (select count(a.`click_id`) from {$wpdb->prefix}linkilo_url_click_data a where `post_id` = {$post_id} and `post_type` = '{$post_type}' AND `click_date` > '{$range}') AS 'clicks_over_30_days', 
                            (select count(a.`click_id`) from {$wpdb->prefix}linkilo_url_click_data a where `post_id` = {$post_id} and `post_type` = '{$post_type}') AS 'total_clicks'
        FROM {$wpdb->prefix}linkilo_url_click_data b WHERE `post_id` = {$post_id} AND `post_type` = '{$post_type}' AND `click_date` > '{$range}' GROUP BY `link_url` ORDER BY `most_clicked_count` DESC LIMIT 1";

        $click_data = $wpdb->get_results($query);

        return $click_data;
    }

    /**
     * Gets the detailed click data for the given post and date range.
     * This is use
     **/
    public static function get_detailed_click_data($post_id, $post_type, $range = array('start' => false, 'end' => false)){
        global $wpdb;
        
        $clicks_table = $wpdb->prefix . 'linkilo_url_click_data';
        $start = (isset($range['start']) && !empty($range['start'])) ? date('Y-m-d H:i:s', intval($range['start'])): date('Y-m-d H:i:s', (time() - (30 * DAY_IN_SECONDS)));
        $end = (isset($range['end']) && !empty($range['end'])) ? date('Y-m-d H:i:s', intval($range['end'])): date('Y-m-d H:i:s', time());
        $search = (isset($_POST['keywords']) && !empty($_POST['keywords'])) ? sanitize_text_field($_POST['keywords']): '';

        if($post_type === 'url'){
            $post_id = esc_url_raw($post_id);
            if(!empty($search)){
                $query = "SELECT post_id, a.post_type AS post_type, click_date, user_ip, user_id, link_url, link_anchor FROM {$clicks_table} a LEFT JOIN {$wpdb->posts} b ON a.post_id = b.ID WHERE a.link_url = '{$post_id}' AND a.click_date > '{$start}' AND '{$end}' > a.click_date AND (a.link_anchor LIKE '%{$search}%' OR b.post_title LIKE '%{$search}%')";
            }else{
                $query = "SELECT * FROM {$clicks_table} WHERE `link_url` = '{$post_id}' AND `click_date` > '{$start}' AND `click_date` < '{$end}'";
            }
        }else{
            $post_id = (int) $post_id;
            $post_type = ($post_type === 'post') ? 'post': 'term';
            if(!empty($search)){
                $search = "AND (`link_url` LIKE '%{$search}%' OR `link_anchor` LIKE '%{$search}%')";
            }
            $query = "SELECT * FROM {$clicks_table} WHERE `post_id` = {$post_id} AND `post_type` = '{$post_type}' AND `click_date` > '{$start}' AND `click_date` < '{$end}' {$search}";
        }

        $click_data = $wpdb->get_results($query);

        return $click_data;
    }

}