<?php

/**
 * Work with keywords
 */
class Linkilo_Build_RelateUrlKeyword
{
    public function register()
    {
        add_action('wp_ajax_linkilo_remove_relate_url_keyword', [$this, 'delete']);
        add_action('wp_ajax_linkilo_add_relate_url_keyword', [$this, 'add']);
        add_action('wp_ajax_linkilo_reset_relate_url_keyword', [$this, 'reset']);
        add_action('wp_ajax_linkilo_insert_selected_keyword_links', [$this, 'insertSelectedLinks']);
        add_filter('screen_settings', array(__CLASS__, 'show_screen_options'), 11, 2);
        add_filter('set_screen_option_linkilo_keyword_options', array(__CLASS__, 'saveOptions'), 12, 3);
        add_filter('linkilo_process_keyword_list', array(__CLASS__, 'processKeywords'), 10, 1);

        /*new settings*/
        add_action( "wp_ajax_linkilo_keyword_search_post_id", array ( $this, 'get_matcing_post_id' ) );
    }

    public function get_matcing_post_id()
    {
        global $wpdb;
        $custom_post_type = "'".implode('\',\'', array('post', 'page')) . "'";
        $wild = '%';
        $find = $_POST['term'];
        $like = $wild . $wpdb->esc_like( $find ) . $wild;


        if (intval($_POST['term_length']) == 0) {
            $results = $wpdb->get_results( 
                $wpdb->prepare( "
                    SELECT ID as id, post_title as title
                    FROM {$wpdb->posts} 
                    WHERE post_type IN ($custom_post_type)
                    AND post_status = %s
                    ORDER BY id DESC LIMIT 0,10",
                    'publish'
                ), ARRAY_A 
            );
        }else{
            $results = $wpdb->get_results( 
                $wpdb->prepare( "
                    SELECT ID as id, post_title as title
                    FROM {$wpdb->posts} 
                    WHERE post_title LIKE %s 
                    AND post_type IN ($custom_post_type)
                    AND post_status = %s",
                    $like,
                    'publish'
                ), ARRAY_A 
            );
        }

        // A sql query to return all post titles and id

        // Return null if we found no results
        if ( ! $results ){
            return;
        }else{

            $response = array();
            foreach($results as $result){
                $push = array(
                    'label' => $result['title'],
                    'value' => $result['id'],
                );
                array_push($response, $push);
            }
            echo json_encode($response);
            die;
        }
    }
    /**
     * Show settings page
     */
    public static function init()
    {
        if (!empty($_POST['save_settings'])) {
            self::saveSettings();
        }

        $user = wp_get_current_user();
        $reset = !empty(get_option('linkilo_keywords_reset'));
        $table = new Linkilo_Build_Table_RelateUrlKeyword();
        $table->prepare_items();
        include LINKILO_PLUGIN_DIR_PATH . '/templates/relate_keywords.php';
    }

    public static function show_screen_options($settings, $screen_obj){

        $screen = get_current_screen();
        $options = get_user_meta(get_current_user_id(), 'linkilo_keyword_options', true);

        // exit if we're not on the focus keywords page
        if(!is_object($screen) || $screen->id != 'linkilo-admin_page_linkilo_keywords'){
            return $settings;
        }

        // Check if the screen options have been saved. If so, use the saved value. Otherwise, use the default values.
        if ( $options ) {
            $per_page = !empty($options['per_page']) ? $options['per_page'] : 20 ;
            $hide_select_links = !empty($options['hide_select_links_column']) && $options['hide_select_links_column'] != 'off';
        } else {
            $per_page = 20;
            $hide_select_links = false;
        }

        //get apply button
        $button = get_submit_button( __( 'Apply', 'wp-screen-options-framework' ), 'primary large', 'screen-options-apply', false );

        //show HTML form
        ob_start();
        include LINKILO_PLUGIN_DIR_PATH . 'templates/relate_keyword_options.php';
        return ob_get_clean();
    }

    public static function saveOptions($status, $option, $value) {
        if(!wp_verify_nonce($_POST['screenoptionnonce'], 'screen-options-nonce')){
            return;
        }

        if ($option == 'linkilo_keyword_options') {
            $value = [];
            if (isset( $_POST['linkilo_keyword_options'] ) && is_array( $_POST['linkilo_keyword_options'] )) {
                if (!isset($_POST['linkilo_keyword_options']['hide_select_links_column'])) {
                    $_POST['linkilo_keyword_options']['hide_select_links_column'] = 'off';
                }
                $value = $_POST['linkilo_keyword_options'];
            }

            return $value;
        }

        return $status;
    }

    /**
     * Add new keyword
     */
    public static function add()
    {
        Linkilo_Build_Root::verify_nonce('linkilo_keyword');
        if (!empty($_POST['keyword_id'])) {
            if (isset($_POST['linkilo_keywords_add_same_link']) && isset($_POST['linkilo_keywords_link_once'])) {
                self::updateKeywordSettings();
            }
            $keyword = self::getKeywordByID((int)$_POST['keyword_id']);
        } else {
            $keyword = self::store();
        }

        self::checkPosts($keyword);
    }

    /**
     * Runs the autolink creation process for existing keywords based on keyword id.
     * Accepts an array of ids to process, and removes ids from the array as they are processed.
     * If there isn't enough time to complete the processing run, 
     * the ids to be processed are returned so they can be sent for another processing run.
     * 
     * @param array $ids An array of keyword ids to run the content insertion process for.
     * @return array|bool Returns unprocessed ids if there's more to process, an empty array when all ids are processed, and false if no ids are supplied.
     */
    public static function processKeywords($ids = array())
    {   
        if(empty($ids)){
            return false;
        }

        // if a single id was given, wrap it in an array
        if(is_int($ids)){
            $ids = array($ids);
        }

        // loop over the ids
        foreach($ids as $key => $id){
            // try getting the keyword from the DB
            $keyword = self::getKeywordByID((int)$id);

            // skip to the next id if there's no keyword
            if(empty($keyword)){
                continue;
            }

            // run the autolink insertion process for a batch of posts using this keyword
            $results = self::checkPosts($keyword, true);

            // if all of the autolinks have been inserted
            if($results['finish']){
                // remove the current id from the list and proceed to the next one
                unset($ids[$key]);
            }else{
                // if we have more posts to go over, break out of the loop
                break;
            }

        }

        // return any remaining ids so they can be processed on another run
        return $ids;
    }

    /**
     * Reset links data
     */
    public static function reset()
    {
        global $wpdb;

        //verify input data
        Linkilo_Build_Root::verify_nonce('linkilo_keyword');
        if (empty($_POST['count']) || (int)$_POST['count'] > 9999) {
            wp_send_json([
                'nonce' => $_POST['nonce'],
                'finish' => true
            ]);
        }

        $memory_break_point = Linkilo_Build_UrlRecord::get_mem_break_point();
        $total = !empty($_POST['total']) ? (int)$_POST['total'] : 1;

        if ($_POST['count'] == 1) {
            //make matched posts array on the first call
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}linkilo_keyword_links");
            $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses();
            $posts = $wpdb->get_results("SELECT ID as id, 'post' as type FROM {$wpdb->posts} WHERE post_content LIKE '%linkilo_keyword_link%' $statuses_query");
            $posts = self::getLinkedPostsFromAlternateLocations($posts);
            $terms = $wpdb->get_results("SELECT term_id as id, 'term' as type FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('category', 'post_tag') AND description LIKE '%linkilo_keyword_link%'");
            $posts = array_merge($posts, $terms);
            $total = count($posts);
        } else {
            //get unprocessed posts
            $posts = get_option('linkilo_keywords_reset', []);
            if ($total < count($posts)) {
                $total = count($posts);
            }
        }

        foreach ($posts as $key => $post) {
            $alt = (isset($post->alt)) ? true: false;
            $post = new Linkilo_Build_Model_Feed($post->id, $post->type);
            if($alt){
                $content = $post->getContent();
            }else{
                $content = $post->getCleanContent();
            }
            preg_match_all('`<a [^><]*?(?:class=["\'][^"\']*?linkilo_keyword_link[^"\']*?["\']|data-linkilo-keyword-link="linked")[^><]*?href="([^"\'].*?)"[^><]*?>(.*?)<\/a>|<a [^><]*?href="([^"\']*?)"[^><]*?(?:class=["\'][^"\']*?linkilo_keyword_link[^"\']*?["\']|data-linkilo-keyword-link="linked")[^><]*?>(.*?)<\/a>`i', $content, $matches);
            for ($i = 0; $i < count($matches[0]); $i++) {

                if(!empty($matches[1][$i]) && !empty($matches[2][$i])){
                    $link = $matches[1][$i];
                    $keyword = $matches[2][$i];
                }

                if(!empty($matches[3][$i]) && !empty($matches[4][$i])){
                    $link = $matches[3][$i];
                    $keyword = $matches[4][$i];
                }

                if (!empty($link) && !empty($keyword)) {
                    $keyword_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}linkilo_keywords WHERE keyword = '$keyword' AND link = '$link'");

                    if (empty($keyword_id)) {
                        //create new keyword
                        $wpdb->insert($wpdb->prefix . 'linkilo_keywords', [
                            'keyword' => $keyword,
                            'link' => $link,
                            'add_same_link' => get_option('linkilo_keywords_add_same_link'),
                            'link_once' => get_option('linkilo_keywords_link_once'),
                            'select_links' => get_option('linkilo_keywords_select_links'),
                        ]);
                        $keyword_id = $wpdb->insert_id;
                    }

                    $wpdb->insert($wpdb->prefix . 'linkilo_keyword_links', [
                        'keyword_id' => $keyword_id,
                        'post_id' => $post->id,
                        'post_type' => $post->type,
                        'anchor' => $keyword,
                    ]);
                }
            }

            unset($posts[$key]);

            //break process if limits were reached
            if (Linkilo_Build_Root::overTimeLimit(7, 15) || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point)) {
                update_option('linkilo_keywords_reset', $posts);
                break;
            }
        }

        if (empty($posts)) {
            update_option('linkilo_keywords_reset', []);
        }

        wp_send_json([
            'nonce' => $_POST['nonce'],
            'ready' => $total - count($posts),
            'count' => ++$_POST['count'],
            'total' => $total,
            'finish' => empty($posts)
        ]);
    }

    /**
     * Inserts the links the user has selected from the autolink records page
     **/
    public static function insertSelectedLinks(){
        Linkilo_Build_Root::verify_nonce('linkilo_keyword');

        $selected_ids = array_map(function($id){ return (int)$id; }, $_POST['link_ids']);

        if(empty($selected_ids)){
            wp_send_json(array('error' => array('title' => __('No Links Selected', 'linkilo'), 'text' => __('Please select', 'linkilo'))));
        }

        $insert = array();
        $links = self::getPossibleLinksByID($selected_ids);
        $keyword_cache = array();

        foreach($links as $link){
            $post = (object) array('id' => $link->post_id, 'type' => $link->post_type);

            if(isset($link->keyword_id) && !isset($keyword_cache[$link->keyword_id])){
                $keyword_cache[$link->keyword_id] = self::getKeywordByID($link->keyword_id);
            }

            if(isset($keyword_cache[$link->keyword_id])){
                // add the link to the list of links to create
                $insert[$post->id . '_' . $post->type][] = maybe_unserialize($link->meta_data);

                // save the link ref to the db
                self::saveLinkToDB($keyword_cache[$link->keyword_id], $post, $link->case_keyword);

                // and remove the link from the potential list
                self::deletePossibleLinksById($link->id);
            }
        }

        //add links to all editors
        if (!empty($insert)) {
            // unhook the link adding to content from the post data insert action so duplicates aren't inserted
            Linkilo_Build_Root::remove_hooked_function('wp_insert_post_data', 'Linkilo_Build_Feed', 'addLinksToContent', 9999);

            foreach($insert as $key => $meta){
                $post = explode('_', $key);

                if ($post[1] == 'term') {
                    update_term_meta($post[0], 'linkilo_links', $meta);
                    Linkilo_Build_WpTerm::addLinksToTerm($post[0]);
                    // delete the term meta to avoid duplicate inserts
                    delete_term_meta($post[0], 'linkilo_links', true);
                } else {
                    update_post_meta($post[0], 'linkilo_links', $meta);
                    Linkilo_Build_Feed::addLinksToContent(null, ['ID' => $post[0]]);
                    // delete the post meta to avoid duplicate inserts
                    delete_post_meta($post[0], 'linkilo_links', true);
                }
            }
        }

        wp_send_json(array('success' => array('title' => __('Selected Links Created!', 'linkilo'), 'text' => __('The selected relate urls have been inserted!', 'linkilo'))));
    }

    /**
     * Save keyword to DB
     *
     * @param $keyword
     * @param $link
     * @return object
     */
    public static function store()
    {
        global $wpdb;
        $keyword = trim(sanitize_text_field($_POST['keyword']));
        $link = trim(sanitize_text_field($_POST['link']));

        $priority = (isset($_POST['linkilo_keywords_priority_setting']) && !empty($_POST['linkilo_keywords_priority_setting'])) ? (int)$_POST['linkilo_keywords_priority_setting']: 0;

        $restrict_date = (isset($_POST['linkilo_keywords_restrict_date']) && !empty($_POST['linkilo_keywords_restrict_date'])) ? 1: 0;
        $date = null;
        if(isset($_POST['linkilo_keywords_restricted_date']) && !empty($_POST['linkilo_keywords_restricted_date'])){
            $date = preg_replace("([^0-9-])", "", $_POST['linkilo_keywords_restricted_date']);
            if($date !== $_POST['linkilo_keywords_restricted_date']){
                $date = null;
            }
        }

        $restrict_cats = (isset($_POST['linkilo_keywords_restrict_to_cats']) && !empty($_POST['linkilo_keywords_restrict_to_cats'])) ? 1: 0;
        $term_ids = '';
        if(isset($_POST['restricted_cats']) && !empty($_POST['restricted_cats'])){
            $ids = array_map(function($num){ return (int)$num; }, $_POST['restricted_cats']);
            $term_ids = implode(',', $ids);
        }


        /*New settings*/
        //  numeric/integer
        $exact_phrase_match = intval($_POST['linkilo_keywords_exact_phrase_match']); 

        //  numeric/integer
        $add_dofollow = isset($_POST['linkilo_keywords_add_dofollow']) ? intval($_POST['linkilo_keywords_add_dofollow']) : 0;

        //  numeric
        $open_in_same_or_new_window = intval($_POST['linkilo_keywords_open_in_same_or_new_window']); 

        // Array to be convert to comma separated string
        $whitelist_of_post_types = $_POST['linkilo_keywords_whitelist_of_post_types']; 
        $whitelist_of_post_types = implode(',', $whitelist_of_post_types);


        // Array to be convert to comma separated string
        $blacklist_of_posts = $_POST['linkilo_keywords_blacklist_of_posts']; 
        $blacklist_of_posts = implode(',', $blacklist_of_posts);

        // numeric
        $max_rel_links_per_post = intval($_POST['linkilo_keywords_max_rel_links_per_post']); 

        // numeric
        $post_linking_maximum_frequency = intval($_POST['linkilo_keywords_post_linking_maximum_frequency']); 

        // Array to be convert to comma separated string
        $excluded_html_elements = $_POST['linkilo_keywords_excluded_html_elements'];
        $excluded_html_elements = implode(',', $excluded_html_elements);


        /*New settings ends*/

        self::saveSettings();
        self::prepareTable();
        $wpdb->insert($wpdb->prefix . 'linkilo_keywords', [
            'keyword' => $keyword,
            'link' => $link,
            'add_same_link' => get_option('linkilo_keywords_add_same_link'),
            'link_once' => get_option('linkilo_keywords_link_once'),
            'select_links' => get_option('linkilo_keywords_select_links'),
            'set_priority' => get_option('linkilo_keywords_set_priority'),
            'priority_setting' => $priority,
            'restrict_date' => $restrict_date,
            'restricted_date' => $date,
            'restrict_cats' => $restrict_cats,
            'restricted_cats' => $term_ids,
            'exact_phrase_match' => $exact_phrase_match, // new setting
            'add_dofollow' => $add_dofollow, // new setting
            'open_in_same_or_new_window' => $open_in_same_or_new_window, // new setting
            'whitelist_of_post_types' => $whitelist_of_post_types, // new setting
            'blacklist_of_posts' => $blacklist_of_posts, // new setting
            'max_rel_links_per_post' => $max_rel_links_per_post, // new setting
            'post_linking_maximum_frequency' => $post_linking_maximum_frequency, // new setting
            'excluded_html_elements' => $excluded_html_elements // new setting
        ]);

        return self::getKeywordByID($wpdb->insert_id);
    }

    /**
     * Create keywords DB table if not exists
     */
    public static function prepareTable()
    {   
        global $wpdb;
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_keywords'");
        if ($table != $wpdb->prefix . 'linkilo_keywords') {
                $linkilo_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}linkilo_keywords (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                keyword varchar(255) NOT NULL,
                link varchar(255) NOT NULL,
                add_same_link int(1) unsigned NOT NULL,
                link_once int(1) unsigned NOT NULL,
                select_links tinyint(1) DEFAULT 0,
                set_priority tinyint(1) DEFAULT 0,
                priority_setting int DEFAULT 0,
                restrict_date tinyint(1) DEFAULT 0,
                restricted_date DATETIME DEFAULT NULL,
                restrict_cats tinyint(1) DEFAULT 0,
                restricted_cats text,
                exact_phrase_match int DEFAULT 0,
                add_dofollow int DEFAULT 0,
                open_in_same_or_new_window int DEFAULT 0,
                whitelist_of_post_types varchar(255) DEFAULT NULL,
                blacklist_of_posts varchar(255) DEFAULT NULL,
                max_rel_links_per_post int DEFAULT 0,
                post_linking_maximum_frequency int DEFAULT 0,
                excluded_html_elements varchar(255) DEFAULT NULL,
                PRIMARY KEY  (id)
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

                    // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($linkilo_link_table_query);
        }

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_keyword_links'");
        if ($table != $wpdb->prefix . 'linkilo_keyword_links') {

                $linkilo_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}linkilo_keyword_links (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                keyword_id int(10) unsigned NOT NULL,
                post_id int(10) unsigned NOT NULL,
                post_type varchar(10) NOT NULL,
                anchor text,
                PRIMARY KEY  (id)
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

                            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($linkilo_link_table_query);
        }

        Linkilo_Build_Root::fixCollation($wpdb->prefix . 'linkilo_keywords');
        Linkilo_Build_Root::fixCollation($wpdb->prefix . 'linkilo_keyword_links');

                    // set up the possible links table
        self::preparePossibleLinksTable();
    }

    /**
     * Creates the table for storing possible relate urls so the user can select what links are to be inserted.
     **/
    public static function preparePossibleLinksTable(){
        global $wpdb;
        $data_table = $wpdb->prefix . 'linkilo_keyword_select_links';
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$data_table}'");
        if ($table != $data_table) {
                $linkilo_link_table_query = "CREATE TABLE IF NOT EXISTS {$data_table} (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                keyword_id int(10) unsigned NOT NULL,
                post_id int(10) unsigned NOT NULL,
                post_type varchar(10) NOT NULL,
                sentence_text text,
                case_keyword text,
                meta_data text,
                PRIMARY KEY  (id),
                INDEX (keyword_id)
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

                    // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($linkilo_link_table_query);
        }
    }

    /**
     * Get data for keywords table
     *
     * @param $per_page
     * @param $page
     * @param $search
     * @param string $orderby
     * @param string $order
     * @return array
     */
    public static function getData($per_page, $page, $search,  $orderby = '', $order = '')
    {
        self::prepareTable();
        global $wpdb;
        $limit = " LIMIT " . (($page - 1) * $per_page) . ',' . $per_page;

        $sort = " ORDER BY id DESC ";
        if ($orderby && $order) {
            $sort = " ORDER BY $orderby $order ";
        }

        $search = !empty($search) ? " AND (keyword LIKE '%$search%' OR link LIKE '%$search%') " : '';
        $total = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}linkilo_keywords");
        $keywords = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_keywords WHERE 1 $search $sort $limit" );


        $keyword_ids = array();

        foreach($keywords as $kword){
            $keyword_ids[] = $kword->id;
        }

        $results = array();
        if(!empty($keyword_ids)){
            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_keyword_links WHERE keyword_id IN (" . implode(', ', $keyword_ids) . ")");
            foreach($result as $r){
                $results[$r->keyword_id][] = $r;
            }
            $result = null;
        }
        // echo $keyword->id;

        //get posts with inserted links
        foreach ($keywords as $key => $keyword) {
            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_keyword_links WHERE keyword_id = " . $keyword->id);
            $links = [];
            if(isset($results[$keyword->id])){
                foreach ($results[$keyword->id] as $r) {
                    $links[] = (object)[
                        'post' => new Linkilo_Build_Model_Feed($r->post_id, $r->post_type),
                        'anchor' => $r->anchor,
                        'url' => $keyword->link,
                    ];
                }
            }
            $keywords[$key]->links = $links;
        }

        return [
            'total' => $total,
            'keywords' => $keywords
        ];
    }

    /**
     * Delete keyword from DB
     */
    public static function delete()
    {
        if (!empty($_POST['id'])) {
            global $wpdb;
            $keyword = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}linkilo_keywords WHERE id = " . $_POST['id']);

            foreach(self::getLinksByKeyword($keyword->id) as $link) {
                $keyword = self::getKeywordByID($keyword->id);
                $post = new Linkilo_Build_Model_Feed($link->post_id, $link->post_type);
                $content = $post->getCleanContent();
                self::removeAllLinks($keyword, $content);
                self::updateContent($content, $keyword, $post);
            }

            $wpdb->delete($wpdb->prefix . 'linkilo_keywords', ['id' => $keyword->id]);
            $wpdb->delete($wpdb->prefix . 'linkilo_keyword_links', ['keyword_id' => $keyword->id]);
            $wpdb->delete($wpdb->prefix . 'linkilo_keyword_select_links', ['keyword_id' => $keyword->id]);
        }
    }

    /**
     * Deletes all stored possible links for the given keyword id
     **/
    public static function deletePossibleLinksForKeyword($keyword_id){
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'linkilo_keyword_select_links', ['keyword_id' => $keyword_id]);
    }

    /**
     * Deletes all stored possible links for the given post
     **/
    public static function deletePossibleLinksByPost($post){
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'linkilo_keyword_select_links', ['post_id' => $post->id, 'post_type' => $post->type]);
    }

    /**
     * Delete inserted link DB record
     *
     * @param $link_id
     */
    public static function deleteLink($link, $count = 999) {
        global $wpdb;
        $links = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}linkilo_keyword_links WHERE post_id = {$link->post_id} AND post_type = '{$link->post_type}' AND keyword_id = {$link->keyword_id}");

        foreach ($links as $key => $link) {
            if ($key >= $count) {
                $wpdb->delete($wpdb->prefix . 'linkilo_keyword_links', ['id' => $link->id]);
            }
        }
    }

    /**
     * Get inserted links by keyword
     *
     * @param $keyword_id
     * @return array
     */
    public static function getLinksByKeyword($keyword_id)
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_keyword_links WHERE keyword_id = " . $keyword_id);
    }

    /**
     * Get possible links by keyword id
     *
     * @param $keyword_id
     * @return array
     */
    public static function getPossibleLinksByKeyword($keyword_id)
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_keyword_select_links WHERE keyword_id = " . $keyword_id);
    }

    /**
     * Get inserted links by post
     *
     * @param $post
     * @return array
     */
    public static function getLinksByPost($post)
    {
        global $wpdb;
        return $wpdb->get_results("SELECT *, count(keyword_id) as `cnt` FROM {$wpdb->prefix}linkilo_keyword_links WHERE post_id = {$post->id} AND post_type = '{$post->type}' GROUP BY keyword_id");
    }

    /**
     * Create link from keyword in all posts and terms
     *
     * @param $keyword
     * @param bool $return Should this return or echo the results? Default is echo for built in ajax. Passing true will also allow the function to process until the PHP time limit is nearly up.
     */
    public static function checkPosts($keyword, $return = false)
    {   
        global $wpdb;
        update_option('linkilo_post_procession', 1);
        Linkilo_Build_Root::update_option_cache('linkilo_post_procession', 1);
        $max_links_per_post = get_option('linkilo_max_links_per_post', 0);

        $posts = get_transient('linkilo_keyword_posts_' . $keyword->id);
        $total = !empty($_POST['total']) ? (int)$_POST['total'] : 0.1;
        if (empty($posts)) {

            $ignore_posts = Linkilo_Build_AdminSettings::getIgnoreKeywordsPosts();

            // post types 
            $post_types = implode("','", Linkilo_Build_AdminSettings::getPostTypes());

            //get matched posts and categories
            $link_post = Linkilo_Build_Feed::getPostByLink($keyword->link);

            $where = " AND post_type IN ('{$post_types}')";

            if (!empty($link_post->type) && $link_post->type == 'post') {
                $where .= " AND ID != " . $link_post->id;
            }

            $when = '';
            if(!empty($keyword->restrict_date) && !empty($keyword->restricted_date)){
                $when = " AND `post_date_gmt` > '{$keyword->restricted_date}'";
            }

            $posts = [];

            $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses();
            $statuses_query_p = Linkilo_Build_DatabaseQuery::postStatuses('p');

            $results = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%{$keyword->keyword}%' $statuses_query $where $when UNION SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key IN ('_themify_builder_settings_json', 'ct_builder_shortcodes', 'mfn-page-items-seo') AND m.meta_value LIKE BINARY '%{$keyword->keyword}%' $statuses_query_p
               $where");

            $results = self::getPostsFromAlternateLocations($results, $keyword->keyword);

            foreach ($results as $post) {
                $posts[] = new Linkilo_Build_Model_Feed($post->ID);
            }

            if (!empty(Linkilo_Build_AdminSettings::getTermTypes())) {
                $taxonomies = implode("','", Linkilo_Build_AdminSettings::getTermTypes());
                $where = " AND taxonomy IN ('{$taxonomies}') ";
                if (!empty($link_post->type) && $link_post->type == 'term') {
                    $where .= " AND term_id != " . $link_post->id;
                }
                $results = $wpdb->get_results("SELECT * FROM {$wpdb->term_taxonomy} WHERE description LIKE '%{$keyword->keyword}%' $where ");
                foreach ($results as $category) {
                    $posts[] = new Linkilo_Build_Model_Feed($category->term_id, 'term');
                }
            }
            foreach ($posts as $key => $post) {
                if (in_array($post->type . '_' . $post->id, $ignore_posts)) {
                    unset($posts[$key]);
                }
            }

            $total = count($posts) + .1;
        }
        global $post;
        //proceed posts
        $memory_break_point = Linkilo_Build_UrlRecord::get_mem_break_point();
        foreach ($posts as $key => $post) {
            // skip to the next post if this one is at the limit
            if(!empty($max_links_per_post)) {
                preg_match_all('`<a[^>]*?href=(\"|\')([^\"\']*?)(\"|\')[^>]*?>([\s\w\W]*?)<\/a>|<!-- wp:core-embed\/wordpress {"url":"([^"]*?)"[^}]*?"} -->|(?:>|&nbsp;|\s)((?:(?:http|ftp|https)\:\/\/)(?:[\w_-]+(?:(?:\.[\w_-]+)+))(?:[\w.,@?^=%&:/~+#-]*[\w@?^=%&/~+#-]))(?:<|&nbsp;|\s)`i', $post->getContent(), $matches);
                if(isset($matches[0]) && count($matches[0]) >= $max_links_per_post){
                    unset($posts[$key]);
                    continue;
                }
            }

            $phrases = Linkilo_Build_UrlRecommendation::getPhrases($post->getContent(), false, array(), true, $keyword->excluded_html_elements, $keyword->keyword);

            self::makeLinks($phrases, $keyword, $post);
            unset($posts[$key]);

            if ( (Linkilo_Build_Root::overTimeLimit(10, 15) && empty($return)) || ($return && Linkilo_Build_Root::overTimeLimit(25)) || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point)) {
                set_transient('linkilo_keyword_posts_' . $keyword->id, $posts, 60 * 5);
                break;
            }
        }

        if (empty($posts)) {
            delete_transient('linkilo_keyword_posts_' . $keyword->id);
        }

        update_option('linkilo_post_procession', 1);
        Linkilo_Build_Root::update_option_cache('linkilo_post_procession', 1);

        $data = [
            'nonce' => isset($_POST['nonce']) ? $_POST['nonce']: false,
            'keyword_id' => $keyword->id,
            'progress' => 100 - floor((count($posts) / $total) * 100),
            'total' => $total,
            'finish' => empty($posts)
        ];

        if($return){
            return $data;
        }else{
            wp_send_json($data);
        }
    }

    /**
     * Check if keyword is part of word
     *
     * @param $sentence
     * @param $keyword
     * @param $pos
     * @return bool
     */
    public static function isPartOfWord($sentence, $keyword, $pos)
    {
        $endings = array_merge(Linkilo_Build_WordFunctions::$endings, ['', ' ', '>', '<', ' ', '-', urldecode('%C2%A0')]); // '%C2%A0' === nbsp

        if ($pos > 1) {
            $char_prev = Linkilo_Build_WordFunctions::onlyText(trim(mb_substr($sentence, $pos - 1, 1)));
        } else {
            $char_prev = '';
        }
        $char_next = Linkilo_Build_WordFunctions::onlyText(trim(mb_substr($sentence, $pos + mb_strlen($keyword), 1)));

        if (in_array($char_prev, $endings) && in_array($char_next, $endings)) {
            return false;
        }

        return true;
    }

    /**
     * Check if keyword is inside link
     *
     * @param $sentence
     * @param $keyword
     * @return bool
     */
    public static function insideLink($sentence, $keyword)
    {
        preg_match_all('`<a[^>]+>.*?' . preg_quote($keyword, '`') . '.*?</a>`i', $sentence, $matches);
        if (!empty($matches[0])) {
            return true;
        }
        return false;
    }

    /**
     * Checks to see if the sentence occurs inside a header tag
     **/
    public static function insideHeading($sentence, $keyword, $post){
        preg_match_all('`<h[1-6][^><]*?>(.*?)<\/h[1-6]>`i', $post->getContent(), $matches);

        if (!empty($matches)){
            foreach($matches[0] as $match){
                if(false !== strpos($match, $sentence)){
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get all keywords
     *
     * @return array
     */
    public static function getKeywords()
    {
        global $wpdb;
        $keywords = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_keywords ORDER BY id");

        $sorted = array();
        foreach($keywords as $keyword){
            $sorted[$keyword->priority_setting][] = $keyword;
        }

        $sorted2 = array();
        foreach($sorted as $key => $sort){
            shuffle($sort);
            $sorted2[$key] = $sort;
        }

        // sort the keyowrds by priority
        krsort($sorted2, SORT_NUMERIC);

        $results = array();
        foreach($sorted2 as $sort){
            foreach($sort as $kword){
                $results[] = $kword;
            }
        }

        return $results;
    }

    /**
     * Get keyword by ID
     *
     * @param $id
     * @return object|null
     */
    public static function getKeywordByID($id)
    {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}linkilo_keywords WHERE id = " . $id);
    }

    /**
     * Get possible links by id.
     * Can accept a single id or array of ids
     *
     * @param int|array $id
     * @return object|null
     */
    public static function getPossibleLinksByID($id)
    {
        global $wpdb;

        if(is_array($id)){
            $id = implode(',', $id);
            return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_keyword_select_links WHERE `id` IN (" . $id . ")");
        }else{
            return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}linkilo_keyword_select_links WHERE `id` = " . $id);
        }
    }

    /**
     * Deletes possible links by id.
     * Can accept a single id or array of ids
     *
     * @param int|array $id
     * @return null
     */
    public static function deletePossibleLinksById($id)
    {
        global $wpdb;

        if(is_array($id)){
            $id = implode(',', $id);
            return $wpdb->query("DELETE FROM {$wpdb->prefix}linkilo_keyword_select_links WHERE id IN (" . $id . ")");
        }else{
            return $wpdb->query("DELETE FROM {$wpdb->prefix}linkilo_keyword_select_links WHERE id = " . $id);
        }
    }

    /**
     * Make links from all keywords for certain post
     *
     * @param $post
     */
    public static function addKeywordsToPost($post)
    {
        if (!in_array($post->getRealType(), Linkilo_Build_AdminSettings::getAllTypes()) || !$post->statusApproved()) {
            return;
        }

        if (in_array($post->type . '_' . $post->id, Linkilo_Build_AdminSettings::getIgnoreKeywordsPosts())) {
            return;
        }

        // exit if we've just inserted selected links so we don't insert duplicates
        if(!empty($_POST) && isset($_POST['action']) && 'linkilo_insert_selected_keyword_links' === $_POST['action']){
            return;
        }

        $max_links_per_post = get_option('linkilo_max_links_per_post', 0);

        self::prepareTable();
        update_option('linkilo_post_procession', 1);
        Linkilo_Build_Root::update_option_cache('linkilo_post_procession', 1);
        $keywords = self::getKeywords();
        $url_index = array();
        foreach ($keywords as $key => $keyword) {
            $keyword->keyword = stripslashes($keyword->keyword);
            $link_post = Linkilo_Build_Feed::getPostByLink($keyword->link);
            if (!empty($link_post->type) && $link_post->type == $post->type && $link_post->id == $post->id) {
                unset($keywords[$key]);
                continue;
            }
            if (stripos($post->getContent(), $keyword->keyword) === false) {
                unset($keywords[$key]);
                continue;
            }
            // if a link with the current link's url is slated to be installed and the current link doesn't have rules to insert more than once
            if(isset($url_index[$keyword->link]) && !empty($keyword->link_once) && empty($keyword->add_same_link)){
                // remove it from the list
                unset($keywords[$key]);
                continue;
            }
            $url_index[$keyword->link] = true;
        }

        // remove any existing possible links
        self::deletePossibleLinksByPost($post);

        if (!empty($keywords)) {
            $phrases = Linkilo_Build_UrlRecommendation::getPhrases($post->getFreshContent(), false, array(), true);
            foreach ($keywords as $keyword) {
                // if there is a limit to the number of links and this isn't a manually selected autolink
                if(!empty($max_links_per_post) && empty($keyword->select_links)){
                    preg_match_all('`<a[^>]*?href=(\"|\')([^\"\']*?)(\"|\')[^>]*?>([\s\w\W]*?)<\/a>|<!-- wp:core-embed\/wordpress {"url":"([^"]*?)"[^}]*?"} -->|(?:>|&nbsp;|\s)((?:(?:http|ftp|https)\:\/\/)(?:[\w_-]+(?:(?:\.[\w_-]+)+))(?:[\w.,@?^=%&:/~+#-]*[\w@?^=%&/~+#-]))(?:<|&nbsp;|\s)`i', $post->getContent(), $matches);
                    if(isset($matches[0]) && count($matches[0]) >= $max_links_per_post){
                        continue;
                    }
                }

                self::makeLinks($phrases, $keyword, $post);
            }
        }

        self::deleteGhostLinks($post);
        update_option('linkilo_post_procession', 0);
        Linkilo_Build_Root::update_option_cache('linkilo_post_procession', 0);
    }

    /**
     * Replace keyword with link
     *
     * @param $phrases
     * @param $keyword
     * @param $post
     */
    public static function makeLinks($phrases, $keyword, $post)
    {   
        // echo "\n phrases \n";
        // print_r($phrases);

        // echo "\n keyword \n";
        // print_r($keyword);

        // echo "\n post \n";
        // print_r($post);

        // die();

        if (self::canAddLink($post, $keyword)) {
            $meta = [];
            $keyword->keyword = stripslashes($keyword->keyword);

            // sentence process/phrases process limit
            $phrases_process_limit = (intval($keyword->max_rel_links_per_post) == 0) ? sizeof($phrases) : intval($keyword->max_rel_links_per_post);

            // keyword process limit in a sentence/phrases
            $keword_frequency_per_post_sentence = (intval($keyword->post_linking_maximum_frequency) == 0) ? 0 : intval($keyword->post_linking_maximum_frequency);

            // counter for phrase process limit
            $counter = 0;

            foreach ($phrases as $phrase) {
                $begin = 0;
                $freq = 0;
                $full_sentence_processed = false;

                if($counter < $phrases_process_limit){
                    /*$begin = 0;
                    while (mb_stripos($phrase->text, $keyword->keyword, $begin) !== false) {

                        $begin = mb_stripos($phrase->text, $keyword->keyword, $begin);

                        if (!self::isPartOfWord($phrase->text, $keyword->keyword, $begin) && !self::insideLink($phrase->src, $keyword->keyword) && !self::insideHeading($phrase->src, $keyword->keyword, $post)) {

                            // original commented untile dump testing
                            // preg_match('/'.preg_quote($keyword->keyword, '/').'/i', $phrase->src, $case_match);

                            // if(empty($case_match[0])){
                            //     break;
                            // } 
                            // $case_keyword = $case_match[0];
                            // $custom_sentence = preg_replace('/(?<![a-zA-Z])'.preg_quote($case_keyword, '/').'(?![a-zA-Z])/', self::getFullLink($keyword, $case_keyword, $post), $phrase->src, 1);
                            // original commented untile dump testing ends


                            // Dump testing starts
                            preg_match_all('/'.preg_quote($keyword->keyword, '/').'/i', $phrase->src, $case_match);

                            if(empty($case_match[0])){
                                break;
                            }

                            foreach ($case_match[0] as $key => $value) {
                                $case_keyword = $value;
                                $custom_sentence = preg_replace('/(?<![a-zA-Z])'.preg_quote($case_keyword, '/').'(?![a-zA-Z])/', self::getFullLink($keyword, $case_keyword, $post), $phrase->src, sizeof($case_match[0]));
                            }
                            // Dump testing ends

                            if ($custom_sentence == $phrase->src) {
                                break;
                            }

                            // if the user wants to select links before inserting
                            if($keyword->select_links){
                                // save the link data to the possible links table
                                // self::savePossibleLinkToDB($post, $phrase, $keyword, $case_keyword, $custom_sentence);
                            }else{
                                //replace changed phrase inside the sentence
                                $custom_sentence = str_replace($phrase->src, $custom_sentence, $phrase->sentence_src);

                                $meta[] = [
                                    'id' => $post->id,
                                    'type' => $post->type,
                                    'sentence' => $phrase->sentence_src,
                                    'sentence_with_anchor' => '',
                                    'added_by_keyword' => 1,
                                    'custom_sentence' => $custom_sentence,
                                    'keyword_data' => $keyword
                                ];

                                // self::saveLinkToDB($keyword, $post, $case_keyword);
                            }
                            //Break loop if post should contain only one link for this keyword
                            if (!empty($keyword->link_once)) {
                                break 2;
                            }
                        }
                        $begin++;
                    }*/

                    while (mb_stripos($phrase->text, $keyword->keyword, $begin) !== false) {
                        if ($full_sentence_processed == false) {
                            $begin = mb_stripos($phrase->text, $keyword->keyword, $begin);
                            if (!self::isPartOfWord($phrase->text, $keyword->keyword, $begin) && !self::insideLink($phrase->src, $keyword->keyword) && !self::insideHeading($phrase->src, $keyword->keyword, $post)) { 

                                /*Dump testing starts*/

                                // process all instances of the keyword in the current phrases source sentence 
                                preg_match_all('/'.preg_quote($keyword->keyword, '/').'/i', $phrase->src, $case_match);

                                if(empty($case_match[0])){
                                    break;
                                }

                                foreach ($case_match[0] as $key => $value) {
                                    $case_keyword = $value;
                                    // // $case_keyword = $case_match[0];
                                    if ($keword_frequency_per_post_sentence == 0) {
                                        $process_count = sizeof($case_match[0]);
                                    }else{
                                        $process_count = $keword_frequency_per_post_sentence;
                                    }
                                    $custom_sentence = preg_replace('/(?<![a-zA-Z])'.preg_quote($case_keyword, '/').'(?![a-zA-Z])/', self::getFullLink($keyword, $case_keyword, $post), $phrase->src, $process_count);

                                    if ($key == (sizeof($case_match[0]) - 1 ) ) {
                                        // counte the phrase as fully anchor converted for every presence of keyword in the sentence or paragraph 
                                        $full_sentence_processed = true;
                                    }
                                }
                                /*Dump testing ends*/

                                if ($custom_sentence == $phrase->src) {
                                    break;
                                }

                                // if the user wants to select links before inserting
                                if($keyword->select_links){
                                    // save the link data to the possible links table
                                    self::savePossibleLinkToDB($post, $phrase, $keyword, $case_keyword, $custom_sentence);
                                }else{
                                    //replace changed phrase inside the sentence
                                    $custom_sentence = str_replace($phrase->src, $custom_sentence, $phrase->sentence_src);

                                    $meta[] = [
                                        'id' => $post->id,
                                        'type' => $post->type,
                                        'sentence' => $phrase->sentence_src,
                                        'sentence_with_anchor' => '',
                                        'added_by_keyword' => 1,
                                        'custom_sentence' => $custom_sentence,
                                        'keyword_data' => $keyword
                                    ];
                                    self::saveLinkToDB($keyword, $post, $case_keyword);
                                }
                            }
                            $begin++;
                        }else{
                            break;
                        }
                    }
                }
                $counter++;
            }
            /*
                ===================================================================================
                Dummy loop upside
                ===================================================================================
            */
            /*
                ===================================================================================
                Original loop wrap
                ===================================================================================

            */
            /*$counter = 0;
            foreach ($phrases as $phrase) {
                if($counter < $phrases_process_limit){
                    $begin = 0;
                    while (mb_stripos($phrase->text, $keyword->keyword, $begin) !== false) {

                        $begin = mb_stripos($phrase->text, $keyword->keyword, $begin);

                        if (!self::isPartOfWord($phrase->text, $keyword->keyword, $begin) && !self::insideLink($phrase->src, $keyword->keyword) && !self::insideHeading($phrase->src, $keyword->keyword, $post)) {

                            preg_match('/'.preg_quote($keyword->keyword, '/').'/i', $phrase->src, $case_match);
                            if(empty($case_match[0])){
                                break;
                            }


                            $case_keyword = $case_match[0];
                            $custom_sentence = preg_replace('/(?<![a-zA-Z])'.preg_quote($case_keyword, '/').'(?![a-zA-Z])/', self::getFullLink($keyword, $case_keyword, $post), $phrase->src, 1);

                            if ($custom_sentence == $phrase->src) {
                                break;
                            }

                            // if the user wants to select links before inserting
                            if($keyword->select_links){
                                // save the link data to the possible links table
                                self::savePossibleLinkToDB($post, $phrase, $keyword, $case_keyword, $custom_sentence);
                            }else{

                                //replace changed phrase inside the sentence
                                $custom_sentence = str_replace($phrase->src, $custom_sentence, $phrase->sentence_src);

                                $meta[] = [
                                    'id' => $post->id,
                                    'type' => $post->type,
                                    'sentence' => $phrase->sentence_src,
                                    'sentence_with_anchor' => '',
                                    'added_by_keyword' => 1,
                                    'custom_sentence' => $custom_sentence,
                                    'keyword_data' => $keyword
                                ];

                                self::saveLinkToDB($keyword, $post, $case_keyword);
                            }

                            //Break loop if post should contain only one link for this keyword
                            if (!empty($keyword->link_once)) {
                                break 2;
                            }
                        }

                        $begin++;
                    }
                }
                $counter++;
            }*/
            /*
                ===================================================================================
                Original loop wrap
                ===================================================================================
            */
            //add links to all editors
                if (!empty($meta)) {
                    if ($post->type == 'term') {
                        update_term_meta($post->id, 'linkilo_links', $meta);
                        Linkilo_Build_WpTerm::addLinksToTerm($post->id);
                    } else {
                        update_post_meta($post->id, 'linkilo_links', $meta);
                        Linkilo_Build_Feed::addLinksToContent(null, ['ID' => $post->id]);
                    }
                }
            }
        }

    /**
     * Get full link for replace
     *
     * @param $keyword
     * @param $link
     * @return string
     */
    public static function getFullLink($keyword, $caseKeyword = '', $post = null)
    {   
        $is_external = !Linkilo_Build_PostUrl::isInternal($keyword->link);
        $open_new_tab = (int)get_option('linkilo_2_links_open_new_tab', 0);
        $open_external_new_tab = false;
        if($is_external){
            $open_external_new_tab = get_option('linkilo_external_links_open_new_tab', null);
        }

        //add target blank if needed
        $blank = '';
        $rel_array = array();

        if (($open_new_tab == 1 && empty($is_external)) || 
            ($is_external && $open_external_new_tab) ||
            ($open_new_tab == 1 && $open_external_new_tab === null)
        ) {
            $noreferrer = !empty(get_option('linkilo_add_noreferrer', false)) ? 'noreferrer': '';
        $blank = 'target="_blank" ';
        array_push($rel_array, "noopener");
        if (!empty($noreferrer)) {
            array_push($rel_array, $noreferrer);
        }
            // $rel_array .= 'noopener ' . $noreferrer;
    }


        // if the user has set external links to be nofollow, this is an external link, and this isn't an interlinked site
    if(
        !empty(get_option('linkilo_add_nofollow', false)) && 
        $is_external && 
        !empty(wp_parse_url($keyword->link, PHP_URL_HOST)) &&
        !in_array(wp_parse_url($keyword->link, PHP_URL_HOST), Linkilo_Build_ConnectMultipleSite::get_linked_site_domains(), true))
    {
        if(empty($rel_array)){
            array_push($rel_array, "nofollow");
                // $rel_array .= ' nofollow';
        }else{
            array_push($rel_array, "nofollow");
                // $rel_array .= ' nofollow';
        }
    }

        // get any classes the user wants to add
    $classes = apply_filters('linkilo_link_classes', '', $is_external);

            // if the user returned an array, stringify it
    if(is_array($classes)){
        $classes = implode(' ', $classes);
    }

    $classes = (!empty($classes)) ? sanitize_text_field($classes): '';

    /*New settings*/

        // add_dofollow
    if ($keyword->add_dofollow == 1) {
            // add rel="dofollow" to anchor html
        array_push($rel_array, "dofollow");
            // $rel_array .= ' dofollow';  
    }


        // if(!empty($rel_array)){
        //     $rel_array .= '"';
        // }
    if (sizeof($rel_array) > 0) {
        $rel = 'rel="'.implode(" ", $rel_array).'"';
    }else{
        $rel= "";
    }

        // open_in_same_or_new_window
    if ($keyword->open_in_same_or_new_window == 1) {
            // add target="_blank" to anchor html
        $blank = 'target="_blank"';
    }

        // prepare return output 
    $anchor_html = '<a class="linkilo_keyword_link ' . $classes . '" href="' . $keyword->link . '" ' . $blank . ' ' . $rel . ' title="' . $caseKeyword . '" data-linkilo-keyword-link="linked">' . $caseKeyword . '</a>';

        // exact_phrase_match
    if ($keyword->exact_phrase_match == 1) {
        if ($keyword->keyword === $caseKeyword) {
            $anchor_html = $anchor_html;
        }else{
            $anchor_html = $caseKeyword;
        }
    }

    $post_types_allowed = ($keyword->whitelist_of_post_types != null) ? explode(",", $keyword->whitelist_of_post_types) : array();
    $post_ids_to_exclude = ($keyword->blacklist_of_posts != null) ? explode(",", $keyword->blacklist_of_posts) : array();

        // check if current post type is matching with post types allowed for keyword
        // convert keyword to link if post type matches or in array of allowed post types
        // else return keyword simple without conversion to anchor   
    $current_posts_type = get_post_type($post->id);

    if (sizeof($post_types_allowed) > 0 && in_array($current_posts_type,  $post_types_allowed)) {
            // return anchor text
            // because this post's post type is matching in array of allowed post types 
        $anchor_html = $anchor_html;
    }elseif (sizeof($post_types_allowed) <= 0) {
            // return anchor text
            // because (if) condition evaluates to false and size of array is 0
            // indicates no any specific post types passed for keyword
            // so return the anchor  
        $anchor_html = $anchor_html;
    }else{
            // return non anchor text 
            // becasue not any specific post types passed for keyword or the post type is passed but it's not matching in array of allowed post types 
        $anchor_html = $caseKeyword;
    }

        // echo $post->id."\n\n";
        // echo "For post type \n";
        // var_dump($post_types_allowed);
        // var_dump(in_array($current_posts_type,  $post_types_allowed));
        // var_dump(sizeof($post_types_allowed) <= 0);
        // echo $anchor_html. "\n\n";


        // check if current post id is matching within the post id's not to be allowed for keyword
        // convert to anchor if post current post id is not in array of un-allowed post id's 
        // else return keyword simple without conversion to anchor   
    if (sizeof($post_ids_to_exclude) > 0 && in_array($post->id, $post_ids_to_exclude)) {
            // return non anchor text
            // because this post's post id is matching in array of un-allowed post id's
        $anchor_html = $caseKeyword;
    }elseif (sizeof($post_ids_to_exclude) <= 0) {
            // return anchor text
            // because the post id array is empty and so no rules for excluding post id's
        $anchor_html = $anchor_html;
    }else{
            // return anchor text 
            // becasue not any specific post id's passed for keyword or the post id is passed but it's not matching in array of allowed post id's
        $anchor_html = $anchor_html;
    }

    /*New settings*/
    return $anchor_html;
        // return '<a class="linkilo_keyword_link ' . $classes . '" href="' . $keyword->link . '" ' . $blank . ' ' . $rel . ' title="' . $caseKeyword . '" data-linkilo-keyword-link="linked">' . $caseKeyword . '</a>';
}

    /**
     * Check if link can be added to certain post
     *
     * @param $post
     * @param $keyword
     * @return bool
     */
    public static function canAddLink($post, $keyword)
    {
        global $wpdb;
        if (empty($keyword->add_same_link)) {
            $links = [];
            $outgoing = Linkilo_Build_UrlRecord::getOutboundLinks($post);
            foreach (array_merge($outgoing['internal'], $outgoing['external']) as $l) {
                $links[] = Linkilo_Build_PostUrl::clean($l->url);
            }

            if (in_array(Linkilo_Build_PostUrl::clean($keyword->link), $links)) {
                return false;
            }
        }

        if (!empty($keyword->link_once)) {
            preg_match('|<a .*href=[\'"]' . $keyword->link . '.+>.*?</a>|i', $post->getContent(), $matches);

            if (!empty($matches[0])) {
                return false;
            }
        }


        $link_post = Linkilo_Build_Feed::getPostByLink($keyword->link);

        if (!empty($link_post->type) && $link_post->getType() == 'Category') {
            $category_post = $wpdb->get_var("SELECT count(*) FROM {$wpdb->postmeta} WHERE post_id = {$post->id} AND meta_key = '_elementor_conditions' AND meta_value LIKE '%include/archive/category/{$link_post->id}%'");

            if (!empty((int)$category_post)) {
                return false;
            }
        }

        if($post->type === 'post' && isset($keyword->restricted_cats) && !empty($keyword->restricted_cats)){
            $in_cats = $wpdb->get_col("SELECT `object_id` FROM {$wpdb->term_relationships} WHERE `object_id` = {$post->id} && `term_taxonomy_id` IN ({$keyword->restricted_cats})");

            if(empty($in_cats)){
                return false;
            }
        }

        return true;
    }

    /**
     * Save inserted link to the DB table
     *
     * @param $keyword
     * @param $post
     */
    public static function saveLinkToDB($keyword, $post, $anchor = '')
    {
        global $wpdb;

        if(empty($anchor)){
            $anchor = $keyword->keyword;
        }

        $wpdb->insert($wpdb->prefix . 'linkilo_keyword_links', [
            'keyword_id' => $keyword->id,
            'post_id' => $post->id,
            'post_type' => $post->type,
            'anchor' => $anchor,
        ]);
    }

    /**
     * Save inserted link to the DB table
     *
     * @param object $post
     * @param object $phrase
     * @param object $keyword
     * @param string $case_keyword
     * @param string $custom_sentence
     */
    public static function savePossibleLinkToDB($post, $phrase, $keyword, $case_keyword, $custom_sentence)
    {
        global $wpdb;

        //replace changed phrase inside the sentence
        $custom_sentence = str_replace($phrase->src, $custom_sentence, $phrase->sentence_src);

        $meta_data = array(
            'id' => $post->id,
            'type' => $post->type,
            'sentence' => $phrase->sentence_src,
            'sentence_with_anchor' => '',
            'added_by_keyword' => 1,
            'custom_sentence' => $custom_sentence,
            'keyword_data' => $keyword
        );

        $wpdb->insert($wpdb->prefix . 'linkilo_keyword_select_links', [
            'keyword_id' => $keyword->id,
            'post_id' => $post->id,
            'post_type' => $post->type,
            'sentence_text' => $phrase->sentence_src,
            'case_keyword' => $case_keyword,
            'meta_data' => serialize($meta_data)
        ]);
    }

    /**
     * Save keywords settings
     */
    public static function saveSettings()
    {
        update_option('linkilo_keywords_add_same_link', (int)$_POST['linkilo_keywords_add_same_link']);
        update_option('linkilo_keywords_link_once', (int)$_POST['linkilo_keywords_link_once']);
        update_option('linkilo_keywords_select_links', (int) $_POST['linkilo_keywords_select_links']);
        update_option('linkilo_keywords_set_priority', (int) $_POST['linkilo_keywords_set_priority']);
        update_option('linkilo_keywords_restrict_to_cats', (int)$_POST['linkilo_keywords_restrict_to_cats']);
    }

    /**
     * Find deleted links in the post content and remove them from DB
     *
     * @param $post
     */
    public static function deleteGhostLinks($post)
    {
        foreach (self::getLinksByPost($post) as $link) {
            $keyword = self::getKeywordByID($link->keyword_id);
            if (!empty($keyword)) {$c =$post->getFreshContent();
                preg_match_all('`<a (?:[^><]*?(?:class=["\'][^"\']*?linkilo_keyword_link[^"\']*?["\']|data-linkilo-keyword-link="linked")[^><]*?href="' . preg_quote($keyword->link, '`') . '"|[^><]*?href="' . preg_quote($keyword->link, '`') . '".*?(?:data-linkilo-keyword-link="linked"))[^><]*?>' . preg_quote($keyword->keyword, '`') . '</a>`i', $c, $matches);
                if (empty($matches[0]) || count($matches[0]) != (int)$link->cnt) {
                    self::deleteLink($link, count($matches[0]));
                }
            }
        }
    }

    /**
     * Update keyword settings
     */
    public static function updateKeywordSettings()
    {
        $keyword = self::getKeywordByID($_POST['keyword_id']);

        if (!empty($keyword)) {
            global $wpdb;

            $priority_setting = 0;
            if(isset($_POST['linkilo_keywords_priority_setting'])){
                $priority_setting = (int)$_POST['linkilo_keywords_priority_setting'];
            }

            $date = null;
            if(isset($_POST['linkilo_keywords_restricted_date']) && !empty($_POST['linkilo_keywords_restricted_date'])){
                $date = preg_replace("([^0-9-])", "", $_POST['linkilo_keywords_restricted_date']);
                if($date !== $_POST['linkilo_keywords_restricted_date']){
                    $date = null;
                }
            }

            $term_ids = '';
            if(isset($_POST['restricted_cats']) && !empty($_POST['restricted_cats'])) {
                $ids = array_map(function($num){ return (int)$num; }, $_POST['restricted_cats']);
                $term_ids = implode(',', $ids);
            }

            $restrict_to_date = (int)$_POST['linkilo_keywords_restrict_date'];
            $restrict_to_cats = (int)$_POST['linkilo_keywords_restrict_to_cats'];

            $wpdb->update($wpdb->prefix . 'linkilo_keywords', [
                'add_same_link' => (int)$_POST['linkilo_keywords_add_same_link'],
                'link_once' => (int)$_POST['linkilo_keywords_link_once'],
                'select_links' => (int)$_POST['linkilo_keywords_select_links'],
                'set_priority' => (int)$_POST['linkilo_keywords_set_priority'],
                'priority_setting' => $priority_setting,
                'restrict_date' => $restrict_to_date,
                'restricted_date' => $date,
                'restrict_cats' => $restrict_to_cats,
                'restricted_cats' => $term_ids
            ], ['id' => $keyword->id]);

            if ($keyword->link_once == 0 && $_POST['linkilo_keywords_link_once'] == 1) {
                self::leftOneLink($keyword);
            }

            if ($keyword->add_same_link == 1 && $_POST['linkilo_keywords_add_same_link'] == 0) {
                self::removeSameLink($keyword);
            }

            // if date restricting has been turned on and a date is given or the given date is older than the saved date
            if( ($keyword->restrict_date == 0 && $restrict_to_date == 1 &&
                !empty($date)) ||
                (!empty($restrict_to_date) && !empty($date) && strtotime($date) > strtotime($keyword->restricted_date)) || true
            ){
                // update the keyword with the date
                $keyword->restricted_date = $date;
                // remove any autolinks on posts older than the set time
                self::removeTooOldLinks($keyword->id);
            }

            if(!empty($term_ids)){
                $keyword->restricted_cats = $term_ids;
                self::removeCategoryRestrictedLinks($keyword);
            }

            // clear any stored selectable links/possible links since we'll be adding new ones after this
            self::deletePossibleLinksForKeyword($keyword->id);
        }
    }

    /**
     * Remove all keyword links except one
     *
     * @param $keyword
     */
    public static function leftOneLink($keyword)
    {
        global $wpdb;
        $links = $wpdb->get_results("SELECT *, count(keyword_id) as cnt FROM {$wpdb->prefix}linkilo_keyword_links WHERE keyword_id = {$keyword->id} GROUP BY post_id, post_type HAVING count(keyword_id) > 1");
        foreach ($links as $link) {
            $keyword = self::getKeywordByID($keyword->id);
            $post = new Linkilo_Build_Model_Feed($link->post_id, $link->post_type);
            $content = $post->getCleanContent();
            self::removeNonFirstLinks($keyword, $content);
            self::updateContent($content, $keyword, $post, true);
            self::deleteGhostLinks($post);
        }
    }

    /**
     * Remove keyword links if post already has this link
     *
     * @param $keyword
     */
    public static function removeSameLink($keyword)
    {
        global $wpdb;
        $links = $wpdb->get_results("SELECT post_id, post_type FROM {$wpdb->prefix}linkilo_keyword_links WHERE keyword_id = {$keyword->id} GROUP BY post_id, post_type");
        foreach ($links as $link) {
            $post = new Linkilo_Build_Model_Feed($link->post_id, $link->post_type);
            $keyword = self::getKeywordByID($keyword->id);
            $content = $post->getCleanContent();

            $matches_keyword = self::findKeywordLinks($keyword, $content);
            preg_match_all('|<a\s[^>]*href=["\']' . $keyword->link . '[\'"][^>]*>|', $content, $matches_all);

            if (count($matches_all[0]) > count($matches_keyword[0])) {
                self::removeAllLinks($keyword, $content);
                self::updateContent($content, $keyword, $post);
                self::deleteGhostLinks($post);
            }
        }
    }

    /**
     * Removes the keyword links from all posts that we're published before the link's time.
     * 
     * @param int $keyword_id
     **/
    public static function removeTooOldLinks($keyword_id){
        global $wpdb;

        if(empty($keyword_id)){
            return;
        }

        $keyword = self::getKeywordByID($keyword_id);

        // exit if there's no date
        if(empty($keyword->restricted_date)){
            return;
        }

        // get all the posts with the keywords
        $links = self::getLinksByKeyword($keyword->id);

        // exit if there's no links
        if(empty($links)){
            return;
        }

        // extract the post ids from the keywords
        $ids = array();
        foreach($links as $link){
            $ids[$link->post_id] = true;
        }        

        $ids = implode(', ', array_keys($ids));

        // get all the posts that have been published before the given date
        $posts = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE `ID` IN ({$ids}) AND `post_date_gmt` < '{$keyword->restricted_date}'");

        // exit if there's no posts published before the date
        if(empty($posts)){
            return;
        }

        // remove the links from the post contents
        foreach($posts as $post){
            $post = new Linkilo_Build_Model_Feed($post->ID, 'post');
            $content = $post->getCleanContent();
            self::removeAllLinks($keyword, $content);
            self::updateContent($content, $keyword, $post);
            self::deleteGhostLinks($post);
        }
    }

    /**
     * Remove all keyword links except one from curtain post
     *
     * @param $keyword
     * @param $content
     */
    public static function removeNonFirstLinks($keyword, &$content)
    {
        $links = self::findKeywordLinks($keyword, $content);

        if(is_array($links[0])){
            $links = $links[0];
        }

        if (count($links) > 1) {
            $begin = stripos($content, $links[0]) + strlen($links[0]);
            $first = substr($content, 0, $begin);
            $second = substr($content, $begin);
            self::removeAllLinks($keyword, $second);
            $content = $first . $second;
        }
    }

    /**
     * Remove all keyword links
     *
     * @param $keyword
     * @param $content
     */
    public static function removeAllLinks($keyword, &$content)
    {
        $links = self::findKeywordLinks($keyword, $content, true);
        if(!empty($links)){
            foreach($links as $link){
                foreach($links as $link){
                    $content = preg_replace('`' . preg_quote($link['link'], '`') . '`', $link['anchor'],  $content);
                }
            }
        }
    }

    /**
     * Removes links from all items that aren't in the categories listed by the user.
     * 
     * @param $keyword
     **/
    public static function removeCategoryRestrictedLinks($keyword){
        global $wpdb;
        $links = self::getLinksByKeyword($keyword->id);

        if(empty($links) || !isset($keyword->restricted_cats) || empty($keyword->restricted_cats)){
            return false;
        }

        // get all of the linked post ids
        $ids = array();
        foreach($links as $link){
            // skip the current item if it's a term
            if('term' === $link->post_type){
                continue;
            }
            $ids[$link->post_id] = true;
        }

        $ids = array_keys($ids);
        $search_ids = implode(',', $ids);

        // get all the linked post ids that do have the desired terms
        $post_ids_with_terms = $wpdb->get_results("SELECT `object_id` FROM {$wpdb->term_relationships} WHERE `object_id` IN ({$search_ids}) && `term_taxonomy_id` IN ({$keyword->restricted_cats})");

        // process the results
        $found_ids = array();
        foreach($post_ids_with_terms as $object_id){
            $found_ids[$object_id->object_id] = true;
        }

        $found_ids = array_keys($found_ids);

        // diff the ids that have the terms against the autolinks on record to find the ones we need to clean
        $cleanup_ids = array_diff($ids, $found_ids);

        // remove the current keyword from the items
        foreach($cleanup_ids as $id){
            $post = new Linkilo_Build_Model_Feed($id);
            $content = $post->getCleanContent();

            self::removeAllLinks($keyword, $content);
            self::updateContent($content, $keyword, $post);
            self::deleteGhostLinks($post);
        }
    }

    /**
     * Find keyword links in the content
     *
     * @param $keyword
     * @param $content
     * @param bool $return_text Should the anchor texts be returned for case sensitive matching?
     * @return array
     */
    public static function findKeywordLinks($keyword, $content, $return_text = false)
    {
        preg_match_all('`(?:<a\s[^><]*?(?:class=["\'][^"\']*?linkilo_keyword_link[^"\']*?["\']|data-linkilo-keyword-link="linked")[^><]*?(href|url)=[\'\"]' . preg_quote($keyword->link, '`') . '*[\'\"][^><]*?>|<a\s[^><]*?(href|url)=[\'\"]' . preg_quote($keyword->link, '`') . '*[\'\"][^><]*?(?:class=["\'][^"\']*?linkilo_keyword_link[^"\']*?["\']|data-linkilo-keyword-link="linked")[^><]*?>)(?!<a)(' . preg_quote($keyword->keyword, '`') . ')<\/a>`i', $content, $matches);

        if($return_text){
            $return_matches = array();
            foreach($matches[0] as $key => $match){
                if(!$return_text){
                    $return_matches[] = $match;
                }else{
                    $return_matches[] = array('link' => $match, 'anchor' => $matches[3][$key]);
                }
            }

            return $return_matches;
        }else{
            return $matches;
        }
    }

    /**
     * Update post content in all editors
     */
    public static function updateContent($content, $keyword, $post, $left_one = false)
    {
        if ($post->type == 'post') {
            Linkilo_Build_Feed::editors('removeKeywordLinks', [$keyword, $post->id, $left_one]);
            Linkilo_Build_Editor_Kadence::removeKeywordLinks($content, $keyword, $left_one);
        }

        $post->updateContent($content);

    }

    /**
     * Does a check to see if the user has set any autolinks for manual select
     **/
    public static function keywordLinkSelectActive(){
        global $wpdb;
        $keyword_table = $wpdb->prefix . 'linkilo_keywords';

        $set = false;
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_keywords'");
        if($table === $wpdb->prefix . 'linkilo_keywords'){
            $set = $wpdb->get_results("SELECT `id` FROM {$keyword_table} WHERE `select_links` = 1 LIMIT 1");
        }

        return (!empty($set)) ? true: false;
    }

    public static function getLinkedPostsFromAlternateLocations($posts){
        global $wpdb;

        $found_posts = false;
        // if WP Recipes is active and the user wants to add links to the recipe notes
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Linkilo_Build_AdminSettings::getPostTypes())){
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS id, 'post' as type, 1 AS alt FROM {$wpdb->prefix}postmeta m WHERE `meta_key` = 'wprm_notes' AND (meta_value LIKE '%linkilo_keyword_link%')");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        if($found_posts){
            // if there are posts found, remove any duplicate ids
            $post_ids = array();
            foreach($posts as $post){
                $post_ids[$post->id] = $post;
            }

            $posts = array_values($post_ids);
        }


        return $posts;
    }

    public static function getPostsFromAlternateLocations($posts, $keyword){
        global $wpdb;

        $found_posts = false;
        // if WP Recipes is active and the user wants to add links to the recipe notes
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Linkilo_Build_AdminSettings::getPostTypes())){
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->prefix}postmeta m WHERE `meta_key` = 'wprm_notes' AND (meta_value LIKE '%$keyword%')");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        if($found_posts){
            // if there are posts found, remove any duplicate ids
            $post_ids = array();
            foreach($posts as $post){
                $post_ids[$post->ID] = $post;
            }

            $posts = array_values($post_ids);
        }

        return $posts;
    }

}
