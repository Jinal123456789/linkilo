<?php

/**
 * Work with URL Changer
 */
class Linkilo_Build_UrlReplace
{
    /**
     * Register hooks
     */
    public function register()
    {
        add_action('wp_ajax_linkilo_url_changer_delete', [$this, 'delete']);
        add_action('wp_ajax_linkilo_url_changer_reset', [$this, 'reset']);
    }

    /**
     * Show table page
     */
    public static function init()
    {
        if (!empty($_POST['old']) && !empty($_POST['new'])) {
            $url = self::store();
            self::replaceURL($url);
        }

        $user = wp_get_current_user();
        $reset = !empty(get_option('linkilo_url_changer_reset'));
        $table = new Linkilo_Build_Table_UrlReplace();
        $table->prepare_items();
        /*  Commented unusable code
        include LINKILO_PLUGIN_DIR_PATH . '/templates/url_changer.php';*/
    }

    public static function reset()
    {
        global $wpdb;

        //verify input data
        Linkilo_Build_Root::verify_nonce('linkilo_url_changer');
        if (empty($_POST['count']) || (int)$_POST['count'] > 9999) {
            wp_send_json([
                'nonce' => $_POST['nonce'],
                'finish' => true
            ]);
        }

        $start = microtime(true);
        $memory_break_point = Linkilo_Build_UrlRecord::get_mem_break_point();
        $total = !empty($_POST['total']) ? (int)$_POST['total'] : 1;

        if ($_POST['count'] == 1) {
            //make matched posts array on the first call
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}linkilo_url_links");
            $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses();
            $posts = $wpdb->get_results("SELECT ID as id, 'post' as type FROM {$wpdb->posts} WHERE post_content LIKE '%data-linkilo=\"url\"%' $statuses_query");
            $posts = self::getLinkedPostsFromAlternateLocations($posts);
            $terms = $wpdb->get_results("SELECT term_id as id, 'term' as type FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('category', 'post_tag') AND description LIKE '%data-linkilo=\"url\"%'");
            $posts = array_merge($posts, $terms);
            $total = count($posts);
        } else {
            //get unprocessed posts
            $posts = get_option('linkilo_url_changer_reset', []);
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
            preg_match_all('`data-linkilo=\"url\" (href|url)=[\'\"](.*?)[\'\"].*?[>\]](.*?)[<\[]`i', $content, $matches);
            for ($i = 0; $i < count($matches[0]); $i++) {
                if (!empty($matches[2][$i]) && !empty($matches[3][$i])) {
                    $link = $matches[2][$i];
                    $link2 = substr($link, -1) == '/' ? substr($link, 0, -1) : $link . '/';
                    $anchor = $matches[3][$i];

                    $url_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}linkilo_urls WHERE new = '$link' OR new = '$link2'");
                    if (!empty($url_id)) {
                        $wpdb->insert($wpdb->prefix . 'linkilo_url_links', [
                            'url_id' => $url_id,
                            'post_id' => $post->id,
                            'post_type' => $post->type,
                            'anchor' => $anchor,
                            'relative_link' => self::isRelativeLink($link)
                        ]);
                    }
                }
            }

            unset($posts[$key]);

            //break process if limits were reached
            if (microtime(true) - $start > 10 || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point)) {
                update_option('linkilo_url_changer_reset', $posts);
                break;
            }
        }

        if (empty($posts)) {
            update_option('linkilo_url_changer_reset', []);
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
     * Create tables if they not exists
     */
    public static function prepareTable()
    {
        global $wpdb;
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_urls'");
        if ($table != $wpdb->prefix . 'linkilo_urls') {
            $linkilo_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}linkilo_urls (
                                    id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                    old varchar(255) NOT NULL,
                                    new varchar(255) NOT NULL,
                                    PRIMARY KEY  (id)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($linkilo_link_table_query);
        }

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}linkilo_url_links'");
        if ($table != $wpdb->prefix . 'linkilo_changed_url_links') {
            $linkilo_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}linkilo_url_links (
                                    id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                    url_id int(10) unsigned NOT NULL,
                                    post_id int(10) unsigned NOT NULL,
                                    post_type varchar(10) NOT NULL,
                                    anchor varchar(255) NOT NULL,
                                    relative_link tinyint(1) DEFAULT 0,
                                    PRIMARY KEY  (id)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($linkilo_link_table_query);
        }

        Linkilo_Build_Root::fixCollation($wpdb->prefix . 'linkilo_urls');
        Linkilo_Build_Root::fixCollation($wpdb->prefix . 'linkilo_url_links');
    }

    /**
     * Get data for table
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
        global $wpdb;
        self::prepareTable();
        $limit = " LIMIT " . (($page - 1) * $per_page) . ',' . $per_page;

        $sort = " ORDER BY id DESC ";
        if ($orderby && $order) {
            $sort = " ORDER BY $orderby $order ";
        }

        $search = !empty($search) ? " AND (old LIKE '%$search%' OR new LIKE '%$search%') " : '';
        $total = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}linkilo_urls");
        $urls = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_urls WHERE 1 $search $sort $limit");

        //get posts with inserted links
        foreach ($urls as $key => $url) {
            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_url_links WHERE url_id = " . $url->id);
            $links = [];
            foreach ($result as $r) {
                $links[] = (object)[
                    'post' => new Linkilo_Build_Model_Feed($r->post_id, $r->post_type),
                    'anchor' => $r->anchor,
                    'url' => $url->new,
                ];
            }
            $urls[$key]->links = $links;
        }

        return [
            'total' => $total,
            'urls' => $urls
        ];
    }

    /**
     * Save URL to DB
     *
     * @return object
     */
    public static function store()
    {
        global $wpdb;
        self::prepareTable();
        $wpdb->insert($wpdb->prefix . 'linkilo_urls', [
            'old' => $_POST['old'],
            'new' => $_POST['new']
        ]);

        return self::getURL($wpdb->insert_id);
    }

    /**
     * Get URL by ID
     *
     * @param $id
     * @return object
     */
    public static function getURL($id)
    {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}linkilo_urls WHERE id = " . $id);
    }

    /**
     * Delete URL
     */
    public static function delete()
    {
        if (!empty($_POST['id'])) {
            global $wpdb;
            $url = self::getURL((int)$_POST['id']);
            $links = $wpdb->get_results("SELECT post_id, post_type FROM {$wpdb->prefix}linkilo_url_links WHERE url_id = {$url->id} GROUP BY post_id, post_type");
            foreach ($links as $link) {
                $post = new Linkilo_Build_Model_Feed($link->post_id, $link->post_type);
                $content = $post->getCleanContent();
                if ($post->type == 'post') {
                    Linkilo_Build_Feed::editors('revertURLs', [$post, $url]);
                    Linkilo_Build_Editor_Kadence::revertURLs($content, $url);
                }
                self::revertURL($content, $url);
                $post->updateContent($content);
            }
            $wpdb->delete($wpdb->prefix . 'linkilo_urls', ['id' => $url->id]);
            $wpdb->delete($wpdb->prefix . 'linkilo_url_links', ['url_id' => $url->id]);
        }
    }

    /**
     * Revert link URL
     *
     * @param $content
     * @param $url
     * @param $anchor
     */
    public static function revertURL(&$content, $url)
    {
        $content = preg_replace('`data-linkilo=\"url\" (href|url)=([\'\"])' . $url->new . '\/*([\'\"])`i', '$1=$2' . $url->old . '$3', $content);
        self::prepareLinks($url);
        $content = preg_replace('`data-linkilo=\"url\" (href|url)=([\'\"])' . $url->new . '\/*([\'\"])`i', '$1=$2' . $url->old . '$3', $content);
    }

    /**
     * Replace URL for all posts
     *
     * @param $url
     */
    public static function replaceURL($url)
    {
        global $wpdb;

        $posts_relative = '';
        $meta_relative = '';
        if(self::isRelativeLink($url->old)){
            $unprepared_url = unserialize(serialize($url));
            $posts_relative =  "OR post_content LIKE '%href=\\\"{$url->old}\\\"%'";
            $meta_relative = "OR m.meta_value LIKE '%href=\\\"{$url->old}\\\"%'";
        }

        $ignore_posts = Linkilo_Build_AdminSettings::getIgnoreKeywordsPosts();
        update_option('linkilo_post_procession', 1);
        //get matched posts and categories
        $posts = [];
        self::prepareLinks($url);
        $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses();
        $results = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE (post_content LIKE '%{$url->old}%' $posts_relative) $statuses_query 
                                                UNION
                                                SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key IN ('_themify_builder_settings_json', 'ct_builder_shortcodes', 'mfn-page-items-seo') AND (m.meta_value LIKE '%{$url->old}%' $meta_relative) $statuses_query");
        $results = self::getPostsFromAlternateLocations($results, $url, $meta_relative);
        foreach ($results as $post) {
            $posts[] = new Linkilo_Build_Model_Feed($post->ID);
        }

        $taxonomy_query = "";
        $taxonomies = implode("','", Linkilo_Build_AdminSettings::getTermTypes());
        if (!empty($taxonomies)) {
            $taxonomy_query = " taxonomy IN ('{$taxonomies}') AND ";
        }

        $results = $wpdb->get_results("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE $taxonomy_query `description` LIKE '%{$url->old}%'");
        foreach ($results as $category) {
            $posts[] = new Linkilo_Build_Model_Feed($category->term_id, 'term');
        }

        //proceed posts
        foreach ($posts as $post) {
            if (!in_array($post->type . '_' . $post->id, $ignore_posts)) {
                if(!empty($posts_relative)){
                    self::checkLink($unprepared_url, $post);
                }

                self::checkLink($url, $post);
            }
        }
        update_option('linkilo_post_procession', 0);
    }

    /**
     * Get all URLs
     *
     * @return array
     */
    public static function getURLs()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_urls ORDER BY id ASC");
    }

    /**
     * Replace URLs for certain post
     *
     * @param $post
     */
    public static function replacePostURLs($post)
    {
        if (in_array($post->type . '_' . $post->id, Linkilo_Build_AdminSettings::getIgnoreKeywordsPosts())) {
            return;
        }

        self::prepareTable();
        $content = $post->getCleanContent();
        foreach (self::getURLs() as $url) {
            if(self::isRelativeLink($url->old) && strpos($content, $url->old)){
                self::checkLink($url, $post);
            }

            self::prepareLinks($url);
            if (strpos($content, $url->old)) {
                self::checkLink($url, $post);
            }
        }

        self::checkLinksCount($post);
    }

    /**
     * Check if content has certain URL
     *
     * @param $content
     * @param $url
     * @param $post
     */
    public static function checkLink($url, $post)
    {
        $content = $post->getCleanContent();

        if (self::hasUrl($content, $url)) {
            self::replaceLink($content, $url, true, $post);

            if ($post->type == 'post') {
                Linkilo_Build_Feed::editors('replaceURLs', [$post, $url]);
                Linkilo_Build_Editor_Kadence::replaceURLs($content, $url);
            }

            $post->updateContent($content);
        } elseif (self::hasUrl(Linkilo_Build_Editor_Themify::getContent($post->id), $url)) {
            Linkilo_Build_Editor_Themify::replaceURLs($post, $url);
        } elseif (self::hasUrl(Linkilo_Build_Editor_Oxygen::getContent($post->id), $url)) {
            Linkilo_Build_Editor_Oxygen::replaceURLs($post, $url);
        } elseif (self::hasUrl(Linkilo_Build_Editor_Muffin::getContent($post->id), $url)) {
            Linkilo_Build_Editor_Muffin::replaceURLs($post, $url);
        } elseif(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Linkilo_Build_AdminSettings::getPostTypes()) && 'post' === $post->type && 'wprm_recipe' === get_post_type($post->id)){
            Linkilo_Build_Editor_WPRecipe::replaceURLs($post, $url);
        }
    }

    /**
     * Check if content has URL
     *
     * @param $content
     * @param $url
     * @return bool
     */
    public static function hasUrl($content, $url)
    {
        preg_match('`(href|url)=[\'\"]' . preg_quote($url->old, '`') . '\/*[\'\"].*?[>\]](.*?)[<\[]`i', $content, $matches);
        return !empty($matches);
    }

    /**
     * Replace certain URL
     *
     * @param $content
     * @param $url
     * @param bool $db_insert
     * @param null $post
     */
    public static function replaceLink(&$content, $url, $db_insert = false, $post = null)
    {
        $text = 'data-linkilo="url" ';
        preg_match_all('`(href|url)=[\'\"]' . preg_quote($url->old, '`') . '\/*[\'\"].*?[>\]](.*?)[<\[]`i', $content, $matches);
        foreach ($matches[2] as $key => $anchor) {
            $link = str_replace([$url->old, 'href=', 'url='], [$url->new, $text . 'href=', $text . 'url='], $matches[0][$key]);
            $content = str_replace($matches[0][$key], $link, $content);

            if ($db_insert) {
                global $wpdb;
                $si = $wpdb->insert($wpdb->prefix . 'linkilo_url_links', [
                    'url_id' => $url->id,
                    'post_id' => $post->id,
                    'post_type' => $post->type,
                    'anchor' => $anchor,
                    'relative_link' => self::isRelativeLink($url->old) // todo add setting
                ]);
            }
        }
    }

    /**
     * Remove ghost DB link records
     *
     * @param $post
     */
    public static function checkLinksCount($post)
    {
        global $wpdb;

        $links = $wpdb->get_results("SELECT url_id, anchor, count(*) as cnt FROM {$wpdb->prefix}linkilo_url_links WHERE post_id = {$post->id} AND post_type = '{$post->type}' GROUP BY anchor");
        foreach ($links as $link) {
            $url = self::getURL($link->url_id);
            $unprepared_url = unserialize(serialize($url));
            self::prepareLinks($url);
            if(self::isRelativeLink($unprepared_url->old)){
                $regex = '`(href|url)=[\'\"](' . $url->new . '|' . $unprepared_url->new . ')\/*[\'\"].*?[>\]]' . $link->anchor . '[<\[]`i';
            }else{
                $regex = '`(href|url)=[\'\"]' . $url->new . '\/*[\'\"].*?[>\]]' . $link->anchor . '[<\[]`i';
            }

            preg_match_all($regex, $post->getCleanContent(), $matches);
            if (count($matches[0]) < $link->cnt) {
                $link_ids = [];
                $result = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}linkilo_url_links WHERE post_id = {$post->id} AND post_type = '{$post->type}' AND url_id = {$url->id} ORDER BY id");
                foreach ($result as $r) {
                    $link_ids[] = $r->id;
                }
                $link_ids = array_slice($link_ids, count($matches[0]));
                if(!empty($link_ids)){
                    $wpdb->query("DELETE FROM {$wpdb->prefix}linkilo_url_links WHERE id IN (" . implode(', ', $link_ids) . ")");
                }
            }
        }
    }

    /**
     * Checks if the link is relative
     * 
     * @param string $link
     **/
    public static function isRelativeLink($link = ''){
        if(empty($link)){
            return false;
        }

        if(strpos($link, 'http') === false && substr($link, 0, 1) === '/'){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Transform link to the common view
     *
     * @param $link
     * @return string
     */
    public static function prepareLink(&$link)
    {
        if (strpos($link, 'http') !== 0) {
            $link = site_url($link);
        }
        if (substr($link, -1) == '/') {
            $link = substr($link, 0, -1);
        }

        return $link;
    }

    /**
     * Prepare both links and check if they are not the same
     *
     * @param $url
     */
    public static function prepareLinks(&$url) {
        $insert_relative = get_option('linkilo_insert_links_as_relative', false);
        $old = $url->old;
        $new = $url->new;

        self::prepareLink($old);
        
        // if the link isn't relative or the user hasn't chosen to only insert relative links
        if(!self::isRelativeLink($new) || empty($insert_relative)){
            // prepare the new link
            self::prepareLink($new);
        }else{
            // make sure there's only one slash
            $new = ltrim($new, '/');
            $new = '/' . $new;
        }

        if ($old !== $new) {
            $url->old = $old;
            $url->new = $new;
        }
    }

    public static function getLinkedPostsFromAlternateLocations($posts){
        global $wpdb;

        $found_posts = false;
        // if WP Recipes is active and the user wants to add links to the recipe notes
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Linkilo_Build_AdminSettings::getPostTypes())){
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS id, 'post' AS type, 1 AS alt FROM {$wpdb->prefix}postmeta m WHERE `meta_key` = 'wprm_notes' AND meta_value LIKE '%data-linkilo=\"url\"%'");

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

    public static function getPostsFromAlternateLocations($posts, $url, $meta_relative){
        global $wpdb;

        $found_posts = false;
        // if WP Recipes is active and the user wants to add links to the recipe notes
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Linkilo_Build_AdminSettings::getPostTypes())){
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->prefix}postmeta m WHERE `meta_key` = 'wprm_notes' AND (meta_value LIKE '%{$url->old}%' $meta_relative)");

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
