<?php

/**
 * Class Linkilo_Build_Console
 */
class Linkilo_Build_Console
{
    /**
     * Get posts count with selected types
     *
     * @return string|null
     */
    public static function getPostCount()
    {
        global $wpdb;
        $post_types = implode("','", Linkilo_Build_AdminSettings::getPostTypes());
        $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses();
        $count = $wpdb->get_var("SELECT count(p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE post_type IN ('$post_types') $statuses_query AND meta_key = 'linkilo_sync_report3' AND meta_value = '1'");
        $taxonomies = Linkilo_Build_AdminSettings::getTermTypes();
        if (!empty($taxonomies)) {
            $count += $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy IN ('" . implode("', '", $taxonomies) . "')");
        }

        return $count;
    }

    /**
     * Get all links count
     *
     * @return string|null
     */
    public static function getLinksCount()
    {
        if (!Linkilo_Build_UrlRecord::link_table_is_created()) {
            return 0;
        }

        global $wpdb;
        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}linkilo_report_links");
    }

    /**
     * Get internal links count
     *
     * @return string|null
     */
    public static function getInternalLinksCount()
    {
        if (!Linkilo_Build_UrlRecord::link_table_is_created()) {
            return 0;
        }

        global $wpdb;
        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}linkilo_report_links WHERE internal = 1");
    }

    /**
     * Get posts count without Incoming Inner URLs
     *
     * @return string|null
     */
    public static function getOrphanedPostsCount()
    {
        global $wpdb;

        $ignore_string = '';
        $ignored_ids = Linkilo_Build_AdminSettings::getItemTypeIds(Linkilo_Build_AdminSettings::getIgnoreOrphanedPosts(), 'post');
        if(!empty($ignored_ids)){
            $ignore_string = " AND m.post_id NOT IN ('" . implode("', '", $ignored_ids) . "')";
        }

        $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses('p');
        $post_types = Linkilo_Build_DatabaseQuery::postTypes();
        $ids = $wpdb->get_col("SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON m.post_id = p.ID WHERE m.meta_key = 'linkilo_links_incoming_internal_count' AND m.meta_value = 0 $ignore_string $statuses_query $post_types");
        if(!empty($ids)){

            // if RankMath is active, remove any ids that are set to "noIndex"
            if(defined('RANK_MATH_VERSION')){
                $id_string = " `post_id` IN ('" . implode("', '", $ids) . "')";
                $rank_math_meta = $wpdb->get_results("SELECT `post_id`, `meta_value` FROM {$wpdb->postmeta} WHERE {$id_string} AND `meta_key` = 'rank_math_robots'");
                $ids = array_flip($ids);
                foreach($rank_math_meta as $data){
                    if(false !== strpos($data->meta_value, 'noindex')){ // we can check the unserialized data because Rank Math uses a simple flag like structure to the saved data.
                        unset($ids[$data->post_id]);
                    }
                }
                $ids = array_flip($ids);
            }

            // if Yoast is active, remove any posts that are set to "noIndex"
            if(defined('WPSEO_VERSION')){
                $id_string = " `post_id` IN ('" . implode("', '", $ids) . "')";
                $no_index_ids = $wpdb->get_col("SELECT DISTINCT `post_id` FROM {$wpdb->postmeta} WHERE $id_string AND meta_key = '_yoast_wpseo_meta-robots-noindex' AND meta_value = '1'");
                $ids = array_diff($ids, $no_index_ids);
            }

            // also remove any posts that are hidden by redirects
            $redirected = Linkilo_Build_AdminSettings::getRedirectedPosts();
            $ids = array_diff($ids, $redirected);
        }

        // count the remaining ids
        $count = count($ids);

        // get if the user wants to include categories in the report
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $show_categories = (!empty($options['show_categories']) && $options['show_categories'] == 'off') ? false : true;

        // if there are terms selected in the settings
        if (!empty(Linkilo_Build_AdminSettings::getTermTypes()) && $show_categories) {
            $term_ids = $wpdb->get_col("SELECT DISTINCT term_id FROM {$wpdb->prefix}termmeta WHERE meta_key = 'linkilo_links_incoming_internal_count' AND meta_value = 0");

            $ignored_ids = Linkilo_Build_AdminSettings::getItemTypeIds(Linkilo_Build_AdminSettings::getIgnoreOrphanedPosts(), 'term');
            if(!empty($ignored_ids)){
                $term_ids = array_diff($term_ids, $ignored_ids);
            }

            // if RankMath is active, remove any ids that are set to "noIndex"
            if(defined('RANK_MATH_VERSION')){
                foreach($term_ids as $key => $id){
                    if(empty(\RankMath\Helper::is_term_indexable(get_term($id)))){
                        unset($term_ids[$key]);
                    }
                }
            }

            // if Yoast is active rmeove any ids that are set to "noIndex"
            if(defined('WPSEO_VERSION')){
                $yoast_taxonomy_data = get_site_option('wpseo_taxonomy_meta');
                if(!empty($yoast_taxonomy_data)){
                    foreach($term_ids as $key => $id){
                        // if the category has been set to noIndex
                        if( isset($yoast_taxonomy_data[$id]) &&
                            isset($yoast_taxonomy_data[$id]['wpseo_noindex']) && 
                            'noindex' === $yoast_taxonomy_data[$id]['wpseo_noindex'])
                        {
                            // remove the id from the list
                            unset($term_ids[$key]);
                        }
                    }
                }
            }

            if(!empty($term_ids)){
                $taxonomies = Linkilo_Build_DatabaseQuery::taxonomyTypes();
                $term_ids = implode(',', $term_ids);
                $count += $wpdb->get_var("SELECT count(term_id) FROM {$wpdb->term_taxonomy} WHERE term_id IN ({$term_ids}) {$taxonomies}");
            }
        }

        return $count;
    }

    /**
     * Get 10 most used domains from external links
     *
     * @return array
     */
    public static function getTopDomains()
    {
        if (!Linkilo_Build_UrlRecord::link_table_is_created()) {
            return [];
        }

        global $wpdb;
        $result = $wpdb->get_results("SELECT host, count(*) as `cnt` FROM {$wpdb->prefix}linkilo_report_links WHERE host IS NOT NULL GROUP BY host ORDER BY count(*) DESC LIMIT 10");

        return $result;
    }

    /**
     * Get broken external links count
     *
     * @return string|null
     */
    public static function getBrokenLinksCount()
    {
        global $wpdb;
        Linkilo_Build_BrokenUrlError::prepareTable(false);
        if(!empty(get_option('linkilo_site_db_version', false))){
            return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}linkilo_broken_links WHERE `ignore_link` != 1");
        }else{
            return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}linkilo_broken_links");
        }
    }

    /**
     * Get broken external links count
     *
     * @return array|null
     */
    public static function getAllErrorCodes()
    {
        global $wpdb;
        Linkilo_Build_BrokenUrlError::prepareTable(false);
        if(!empty(get_option('linkilo_site_db_version', false))){
            return $wpdb->get_col("SELECT DISTINCT `code` FROM {$wpdb->prefix}linkilo_broken_links WHERE `code` != 768");
        }

        return array();
    }

    /**
     * Get broken internal links count
     *
     * @return string
     */
    public static function get404LinksCount()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}linkilo_broken_links WHERE code = 404");
    }

    /**
     * Get data for domains table
     *
     * @param $per_page
     * @param $page
     * @param $search
     * @return array
     */
    public static function getDomainsData($per_page, $page, $search)
    {
        global $wpdb;
        $domains = [];
        $search = !empty($search) ? " AND host LIKE '%$search%'" : '';
        $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkilo_report_links WHERE host IS NOT NULL $search");
        $post_objs = array();
        foreach ($result as $link) {
            $host = $link->host;
            $id = $link->post_id;
            $type = $link->post_type;
            $cache_id = $type . $id;

            // if we haven't used this post yet
            if(!isset($post_objs[$cache_id])){
                // create it fresh for the post var
                $p = new Linkilo_Build_Model_Feed($id, $type);
                // and then add it to the object array so we can use it later
                $post_objs[$cache_id] = $p;
            }else{
                // if we have used this post, obtain it from the object list
                $p = $post_objs[$cache_id];
            }

            if (empty($domains[$host])) {
                $domains[$host] = ['host' => $host, 'posts' => [], 'links' => []];
            }

            if (empty($domains[$host]['posts'][$id])) {
                $domains[$host]['posts'][$id] = $p;
            }

            // get the protocol for the domain as best we can
            if(!isset($domains[$host]['protocol']) || 'https://' !== $domains[$host]['protocol']){
                if(false !== strpos($link->clean_url, 'https:')){
                    $domains[$host]['protocol'] = 'https://';
                }else{
                    $domains[$host]['protocol'] = 'http://';
                }
            }

            $domains[$host]['links'][] = new Linkilo_Build_Model_FeedUrl([
                'link_id' => $link->link_id,
                'url' => $link->raw_url,
                'anchor' => strip_tags($link->anchor),
                'post' => $p
            ]);
        }

        usort($domains, function($a, $b){
            if (count($a['links']) == count($b['links'])) {
                return 0;
            }

            return (count($a['links']) < count($b['links'])) ? 1 : -1;
        });

        return [
            'total' => count($domains),
            'domains' => array_slice($domains, ($page - 1) * $per_page, $per_page)
        ];
    }
}
