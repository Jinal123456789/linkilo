<?php

/**
 * Work with suggestions
 */
class Linkilo_Build_UrlRecommendation
{
    public static $undeletable = false;

    /**
     * Gets the suggestions for the current post/cat on ajax call.
     * Processes the suggested posts in batches to avoid timeouts on large sites.
     **/
    public static function ajax_get_recommended_url(){
        $post_id = intval($_POST['post_id']);
        $term_id = intval($_POST['term_id']);
        $key = intval($_POST['key']);
        $user = wp_get_current_user();
        $max_links_per_post = get_option('linkilo_max_links_per_post', 0);

        if((empty($post_id) && empty($term_id)) || empty($key) || 999999 > $key || empty($user->ID)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'linkilo'),
                    'text'  => __('There was some data missing while processing the site content, please refresh the page and try again.', 'linkilo'),
                )
            ));
        }

        // if the nonce doesn't check out, exit
        Linkilo_Build_Root::verify_nonce('linkilo_suggestion_nonce');

        if(!empty($term_id)){
            $post = new Linkilo_Build_Model_Feed($term_id, 'term');
        }else{
            $post = new Linkilo_Build_Model_Feed($post_id);
        }

        $count = null;
        if(isset($_POST['count'])){
            $count = intval($_POST['count']);
        }

        $batch_size = Linkilo_Build_AdminSettings::getProcessingBatchSize();

        $same_category = isset($_POST['same_category']) && !empty($_POST['same_category']) ? 1: 0;
        if(empty($count)){
            update_user_meta(get_current_user_id(), 'linkilo_same_category_selected', $same_category);
        }

        if(isset($_POST['type']) && 'outgoing_suggestions' === $_POST['type']){
            // get the total number of posts that we'll be going through
            if(!isset($_POST['post_count']) || empty($_POST['post_count'])){
                $post_count = self::getPostProcessCount($post);
            }else{
                $post_count = intval($_POST['post_count']);
            }

            $phrase_array = array();
            while(!Linkilo_Build_Root::overTimeLimit(15, 45) && (($count - 1) * $batch_size) < $post_count){

                // get the phrases for this batch of posts
                $phrases = self::getPostSuggestions($post, null, false, null, $count, $key);

                if(!empty($phrases)){
                    $phrase_array[] = $phrases;
                }

                $count++;
            }

            $status = 'no_suggestions';
            if(!empty($phrase_array)){
                $stored_phrases = get_transient('linkilo_post_suggestions_' . $key);
                if(empty($stored_phrases)){
                    $stored_phrases = $phrase_array;
                }else{
                    // decompress the suggestions so we can add more to the list
                    $stored_phrases = self::decompress($stored_phrases);
                    
                    foreach($phrase_array as $phrases){
                        // add the suggestions
                        $stored_phrases[] = $phrases;
                    }
                }

                // compress the suggestions to save space
                $stored_phrases = self::compress($stored_phrases);

                // store the current suggestions in a transient
                set_transient('linkilo_post_suggestions_' . $key, $stored_phrases, MINUTE_IN_SECONDS * 15);
                // send back our status
                $status = 'has_suggestions';
            }

            $num = ($batch_size * $count < $post_count) ? $batch_size * $count : $post_count;
            $message = sprintf(__('Loading: %d of %d processed', 'linkilo'), $num, $post_count);
            // $message = sprintf(__('Processing Link Suggestions: %d of %d processed', 'linkilo'), $num, $post_count);

            wp_send_json(array('status' => $status, 'post_count' => $post_count, 'batch_size' => $batch_size, 'count' => $count, 'message' => $message));
            
        }elseif(isset($_POST['type']) && 'incoming_suggestions' === $_POST['type']){

            $phrases = [];
            $memory_break_point = Linkilo_Build_UrlRecord::get_mem_break_point();
            $ignore_posts = Linkilo_Build_AdminSettings::getIgnorePosts();
            $batch_size = $batch_size * 10;
            
            // if the keywords list only contains newline semicolons
            if(isset($_POST['keywords']) && empty(trim(str_replace(';', '', $_POST['keywords'])))){
                // remove the "keywords" index
                unset($_POST['keywords']);
                unset($_REQUEST['keywords']);
            }

            $completed_processing_count = (isset($_POST['completed_processing_count']) && !empty($_POST['completed_processing_count'])) ? (int) $_POST['completed_processing_count'] : 0;

            $keywords = self::getKeywords($post);

            $suggested_post_ids = get_transient('linkilo_incoming_suggested_post_ids_' . $key);
            // get all the suggested posts for linking TO this post
            if(empty($suggested_post_ids)){
                $search_keywords = (is_array($keywords)) ? $keywords[0] : $keywords;
                $suggested_posts = self::getIncomingSuggestedPosts($search_keywords, Linkilo_Build_Feed::getLinkedPostIDs($post));
                $suggested_post_ids = array();
                foreach($suggested_posts as $suggested_post){
                    $suggested_post_ids[] = $suggested_post->ID;
                }
                set_transient('linkilo_incoming_suggested_post_ids_' . $key, $suggested_post_ids, MINUTE_IN_SECONDS * 10);
            }else{
                // if there are stored ids, re-save the transient to refresh the count down
                set_transient('linkilo_incoming_suggested_post_ids_' . $key, $suggested_post_ids, MINUTE_IN_SECONDS * 10);
            }

            $last_post = (isset($_POST['last_post'])) ? (int) $_POST['last_post'] : 0;

            if(isset(array_flip($suggested_post_ids)[$last_post])){
                $post_ids_to_process = array_slice($suggested_post_ids, (array_search($last_post, $suggested_post_ids) + 1), $batch_size);
            }else{
                $post_ids_to_process = array_slice($suggested_post_ids, 0, $batch_size);
            }

            $process_count = 0;
            $current_post = $last_post;
            foreach ($keywords as $keyword) {
                $temp_phrases = [];
                foreach($post_ids_to_process as $post_id) {
                    if (Linkilo_Build_Root::overTimeLimit(15, 60) || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point) ){
                        break;
                    }

                    $links_post = new Linkilo_Build_Model_Feed($post_id);
                    $current_post = $post_id;

                    // if the post isn't being ignored
                    if(!in_array( ($links_post->type . '_' . $post_id), $ignore_posts)){
                        // if the user has set a max link count for posts
                        if(!empty($max_links_per_post)){
                            // skip any posts that are at the limit
                            preg_match_all('`<a[^>]*?href=(\"|\')([^\"\']*?)(\"|\')[^>]*?>([\s\w\W]*?)<\/a>|<!-- wp:core-embed\/wordpress {"url":"([^"]*?)"[^}]*?"} -->|(?:>|&nbsp;|\s)((?:(?:http|ftp|https)\:\/\/)(?:[\w_-]+(?:(?:\.[\w_-]+)+))(?:[\w.,@?^=%&:/~+#-]*[\w@?^=%&/~+#-]))(?:<|&nbsp;|\s)`i', $links_post->getContent(), $matches);
                            if(isset($matches[0]) && count($matches[0]) >= $max_links_per_post){
                                $process_count++;
                                continue;
                            }
                        }

                        //get suggestions for post
                        if (!empty($_REQUEST['keywords'])) {
                            $suggestions = self::getPostSuggestions($links_post, $post, false, $keyword, null, $key);
                        } else {
                            $suggestions = self::getPostSuggestions($links_post, $post, false, null, null, $key);
                        }

                        //skip if no suggestions
                        if (!empty($suggestions)) {
                            $temp_phrases = array_merge($temp_phrases, $suggestions);
                        }
                    }

                    $process_count++;
                }

                if (count($temp_phrases)) {
                    Linkilo_Build_Clause::TitleKeywordsCheck($temp_phrases, $keyword);
                    $phrases = array_merge($phrases, $temp_phrases);
                }
            }

            // get the suggestions transient
            $stored_phrases = get_transient('linkilo_post_suggestions_' . $key);

            // if there are suggestions stored
            if(!empty($stored_phrases)){
                // decompress the suggestions so we can add more to the list
                $stored_phrases = self::decompress($stored_phrases);
            }else{
                $stored_phrases = array();
            }

            // if there are phrases to save
            if($phrases){
                if(empty($stored_phrases)){
                    $stored_phrases = $phrases;
                }else{
                    // add the suggestions
                    $stored_phrases = array_merge($stored_phrases, $phrases);
                }
            }

            // compress the suggestions to save space
            $stored_phrases = self::compress($stored_phrases);

            // save the suggestion data
            set_transient('linkilo_post_suggestions_' . $key, $stored_phrases, MINUTE_IN_SECONDS * 15);

            $processing_status = array( 
                    'status' => 'no_suggestions', 
                    'keywords' => $keywords,
                    'last_post' => $current_post, 
                    'post_count' => count($suggested_post_ids), 
                    'id_count_to_process' => count($post_ids_to_process),
                    'completed' => empty(count($post_ids_to_process)), // has the processing run completed? If it has, then there won't be any posts to process
                    'completed_processing_count' => ($completed_processing_count += $process_count),
                    'batch_size' => $batch_size,
                    'posts_processed' => $process_count,
            );

            if(!empty($phrases)){
                $processing_status['status'] = 'has_suggestions';
            }

            wp_send_json($processing_status);

        }else{
            wp_send_json(array(
                'error' => array(
                    'title' => __('Unknown Error', 'linkilo'),
                    'text'  => __('The data is incomplete for processing the request, please reload the page and try again.', 'linkilo'),
                )
            ));
        }
    }

    /**
     * Gets the suggestions for any external sites that the user has linked to this one.
     * 
     **/
    public static function ajax_get_outer_site_recommendation(){
        $post_id = intval($_POST['post_id']);
        $term_id = intval($_POST['term_id']);
        $key = intval($_POST['key']);
        $user = wp_get_current_user();
        
        // exit the processing if there's no external linking to do
        $linking_enabled = get_option('linkilo_link_external_sites', false);
        if(empty($linking_enabled)){
            wp_send_json(array('status' => 'no_suggestions', 'post_count' => 0, 'batch_size' => 300, 'count' => 0, 'message' => __('Processing Complete', 'linkilo')));
        }

        if((empty($post_id) && empty($term_id)) || empty($key) || 999999 > $key || empty($user->ID)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'linkilo'),
                    'text'  => __('There was some data missing while processing the site content, please refresh the page and try again.', 'linkilo'),
                )
            ));
        }

        // if the nonce doesn't check out, exit
        Linkilo_Build_Root::verify_nonce('linkilo_suggestion_nonce');

        if(!empty($term_id)){
            $post = new Linkilo_Build_Model_Feed($term_id, 'term');
        }else{
            $post = new Linkilo_Build_Model_Feed($post_id);
        }

        $count = 0;
        if(isset($_POST['count'])){
            $count = intval($_POST['count']);
        }

        $batch_size = Linkilo_Build_AdminSettings::getProcessingBatchSize();

        if(!isset($_POST['post_count']) || empty($_POST['post_count'])){
            // get the total number of posts that we'll be going through
            $post_count = Linkilo_Build_ConnectMultipleSite::count_data_items();
        }else{
            $post_count = (int) $_POST['post_count'];
        }

        // exit the processing if there's no external posts to link to
        if(empty($post_count)){
            wp_send_json(array('status' => 'no_suggestions', 'post_count' => 0, 'batch_size' => $batch_size, 'count' => $count, 'message' => __('Processing Complete', 'linkilo')));
        }

        $phrase_array = array();
        while(!Linkilo_Build_Root::overTimeLimit(15, 30) && (($count - 1) * $batch_size) < $post_count){

            // get the phrases for this batch of posts
            $phrases = self::getExternalSiteSuggestions($post, false, null, $count, $key);

            if(!empty($phrases)){
                $phrase_array[] = $phrases;
            }

            $count++;
        }

        $status = 'no_suggestions';
        if(!empty($phrase_array)){
            $stored_phrases = get_transient('linkilo_post_suggestions_' . $key);
            if(empty($stored_phrases)){
                $stored_phrases = $phrase_array;
            }else{
                // decompress the suggestions so we can add more to the list
                $stored_phrases = self::decompress($stored_phrases);
                
                foreach($phrase_array as $phrases){
                    // add the suggestions
                    $stored_phrases[] = $phrases;
                }
            }
            
            // compress the suggestions to save space
            $stored_phrases = self::compress($stored_phrases);

            // store the current suggestions in a transient
            set_transient('linkilo_post_suggestions_' . $key, $stored_phrases, MINUTE_IN_SECONDS * 15);
            // send back our status
            $status = 'has_suggestions';
        }

        $num = ($batch_size * $count < $post_count) ? $batch_size * $count : $post_count;
        $message = sprintf(__('Processing External Site Link Suggestions: %d of %d processed', 'linkilo'), $num, $post_count);

        wp_send_json(array('status' => $status, 'post_count' => $post_count, 'batch_size' => $batch_size, 'count' => $count, 'message' => $message));

    }

    /**
     * Updates the link report displays with the suggestion results from ajax_get_recommended_url.
     **/
    public static function ajax_update_recommendation_display(){
        $post_id = intval($_POST['post_id']);
        $term_id = intval($_POST['term_id']);
        $key = intval($_POST['key']);
        $user = wp_get_current_user();

        // if the processing specifics are missing, exit
        if((empty($post_id) && empty($term_id)) || empty($key) || 999999 > $key || empty($user->ID)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'linkilo'),
                    'text'  => __('There was some data missing while processing the site content, please refresh the page and try again.', 'linkilo'),
                )
            ));
        }

        // if the nonce doesn't check out, exit
        Linkilo_Build_Root::verify_nonce('linkilo_suggestion_nonce');

        if(!empty($term_id)){
            $post = new Linkilo_Build_Model_Feed($term_id, 'term');
        }else{
            $post = new Linkilo_Build_Model_Feed($post_id);
        }
        
        $same_category = !empty(get_user_meta(get_current_user_id(), 'linkilo_same_category_selected', true));

        if('outgoing_suggestions' === $_POST['type']){
            // get the suggestions from the database
            $phrases = get_transient('linkilo_post_suggestions_' . $key);

            // if there are suggestions 
            if(!empty($phrases)){
                // decompress the suggestions
                $phrases = self::decompress($phrases);
            }

            // merge them all into a suitable array
            $phrase_groups = self::merge_phrase_suggestion_arrays($phrases);

            foreach($phrase_groups as $phrases){
                foreach($phrases as $phrase){
                    usort($phrase->suggestions, function ($a, $b) {
                        if ($a->post_score == $b->post_score) {
                            return 0;
                        }
                        return ($a->post_score > $b->post_score) ? -1 : 1;
                    });
                }
            }

            $used_posts = array($post_id . ($post->type == 'term' ? 'cat' : ''));

            //remove same suggestions on top level
            foreach($phrase_groups as $phrases){
                foreach ($phrases as $key => $phrase) {
                    if(is_a($phrase->suggestions[0]->post, 'Linkilo_Build_Model_OuterFeed')){

                        $post_key = ($phrase->suggestions[0]->post->type=='term'?'ext_cat':'ext_post') . $phrase->suggestions[0]->post->id;
                    }else{
                        $post_key = ($phrase->suggestions[0]->post->type=='term'?'cat':'') . $phrase->suggestions[0]->post->id;
                    }

                    if (!empty($target) || !in_array($post_key, $used_posts)) {
                        $used_posts[] = $post_key;
                    } else {
                        if (!empty(self::$undeletable)) {
                            $phrase->suggestions[0]->opacity = .5;
                        } else {
                            unset($phrase->suggestions[0]);
                        }

                    }

                    if (!count($phrase->suggestions)) {
                        unset($phrases[$key]);
                    } else {
                        if (!empty(self::$undeletable)) {
                            $i = 1;
                            foreach ($phrase->suggestions as $suggestion) {
                                $i++;
                                if ($i > 10) {
                                    $suggestion->opacity = .5;
                                }
                            }
                        } else {
                            $phrase->suggestions = array_slice($phrase->suggestions, 0, 10);
                        }
                    }
                }
            }

            foreach($phrase_groups as $type => $phrases){
                if (!empty($phrase_groups[$type])) {
                    $phrase_groups[$type] = self::deleteWeakPhrases(array_filter($phrase_groups[$type]));
                    $phrase_groups[$type] = self::addAnchors($phrase_groups[$type]);
                }
            }

            $selected_category = !empty($_POST['selected_category']) ? (int)$_POST['selected_category'] : 0;
            if ($same_category) {
                $taxes = get_object_taxonomies(get_post($post_id));
                $query_taxes = array();
                foreach($taxes as $tax){
                    if(get_taxonomy($tax)->hierarchical){
                        $query_taxes[] = $tax;
                    }
                }
                $categories = wp_get_object_terms($post_id, $query_taxes, ['fields' => 'all_with_object_id']);
                if (empty($categories) || count($categories) < 2) {
                    $categories = [];
                }
            }

            $same_tag = !empty($_POST['same_tag']);
            $selected_tag = !empty($_POST['selected_tag']) ? (int)$_POST['selected_tag'] : 0;
            if ($same_tag) {
                $taxes = get_object_taxonomies(get_post($post_id));
                $query_taxes = array();
                foreach($taxes as $tax){
                    if(empty(get_taxonomy($tax)->hierarchical)){
                        $query_taxes[] = $tax;
                    }
                }
                $tags = wp_get_object_terms($post_id, $query_taxes, ['fields' => 'all_with_object_id']);
                if (empty($tags) || count($tags) < 2) {
                    $tags = [];
                }
            }
            /*Related meta posts*/
            /*$linkilo_related_post_obj = new Linkilo_Build_RelatedMetaPosts();
            $related_posts = $linkilo_related_post_obj->linkilo_related_meta_posts($post);
            $get_post_types = get_option('linkilo_relate_meta_post_types',true);
            $match_screen_type = get_post_type($post->id);
            */
            $same_title_option = get_option('linkilo_relate_meta_post_same_title');
            if (!empty($same_title_option) && $same_title_option == "show") {
                $same_title_checked = 'checked="checked"';
            }else{
                $same_title_checked = '';
            }
            /*Related meta posts ends*/

            include LINKILO_PLUGIN_DIR_PATH . '/templates/url_recommend_data_list.php';
            // clear the suggestion cache now that we're done with it
            self::clearSuggestionProcessingCache($key, $post->id);
        }elseif('incoming_suggestions' === $_POST['type']){
            $phrases = get_transient('linkilo_post_suggestions_' . $key);
            // decompress the suggestions
            $phrases = self::decompress($phrases);
            //add links to phrases
            Linkilo_Build_Clause::IncomingSort($phrases);
            $phrases = self::addAnchors($phrases);
            $groups = self::getIncomingGroups($phrases);
            $selected_category = !empty($_POST['selected_category']) ? (int)$_POST['selected_category'] : 0;
            if ($same_category) {
                $categories = wp_get_post_categories($post_id, ['fields' => 'all_with_object_id']);
                if (empty($categories) || count($categories) < 2) {
                    $categories = [];
                }
            }

            $same_tag = !empty($_POST['same_tag']);
            $selected_tag = !empty($_POST['selected_tag']) ? (int)$_POST['selected_tag'] : 0;
            if ($same_tag) {
                $tags = wp_get_post_tags($post_id, ['fields' => 'all_with_object_id']);
                if (empty($tags) || count($tags) < 2) {
                    $tags = [];
                }
            }
            include LINKILO_PLUGIN_DIR_PATH . '/templates/incoming_recommendation_page_container.php';
            self::clearSuggestionProcessingCache($key, $post->id);
        }

        exit;
    }

    /**
     * Merges multiple arrays of phrase data into a single array suitable for displaying.
     **/
    public static function merge_phrase_suggestion_arrays($phrase_array = array(), $incoming_suggestions = false){
        
        if(empty($phrase_array)){
            return array();
        }
        
        $merged_phrases = array('internal_site' => array(), 'external_site' => array());
        if(true === $incoming_suggestions){ // a simpler process is used for the incoming suggestions // Note: not currently used but might be used for incoming external matches
            foreach($phrase_array as $phrase_batch){
                $unserialized_batch = maybe_unserialize($phrase_batch);
                if(!empty($unserialized_batch)){
                    $merged_phrases = array_merge($merged_phrases, $unserialized_batch);
                }
            }
        }else{
            foreach($phrase_array as $phrase_batch){
                $unserialized_batch = maybe_unserialize($phrase_batch);
                if(is_array($unserialized_batch) && !empty($unserialized_batch)){
                    foreach($unserialized_batch as $phrase_key => $phrase_obj){
                        // go over each suggestion in the phrase obj
                        foreach($phrase_obj->suggestions as $post_id => $suggestion){
                            if(is_a($suggestion->post, 'Linkilo_Build_Model_OuterFeed')){
                                if(!isset($merged_phrases['external_site'][$phrase_key])){
                                    $base_phrase = $phrase_obj;
                                    unset($base_phrase->suggestions);
                                    $merged_phrases['external_site'][$phrase_key] = $base_phrase;
                                }
                                $merged_phrases['external_site'][$phrase_key]->suggestions[] = $suggestion;
                            }else{
                                if(!isset($merged_phrases['internal_site'][$phrase_key])){
                                    $base_phrase = $phrase_obj;
                                    unset($base_phrase->suggestions);
                                    $merged_phrases['internal_site'][$phrase_key] = $base_phrase;
                                }
                                $merged_phrases['internal_site'][$phrase_key]->suggestions[] = $suggestion;
                            }
                        }
                    }
                }
            }
        }

        return $merged_phrases;
    }

    public static function getPostProcessCount($post){
        global $wpdb;
        //add all posts to array
        $post_count = 0;
        $exclude = self::getTitleQueryExclude($post);
        $post_types = implode("','", Linkilo_Build_AdminSettings::getPostTypes());
        $exclude_categories = Linkilo_Build_AdminSettings::getIgnoreCategoriesPosts();
        if (!empty($exclude_categories)) {
            $exclude_categories = " AND ID NOT IN (" . implode(',', $exclude_categories) . ") ";
        } else {
            $exclude_categories = '';
        }

        $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses();
        $results = $wpdb->get_results("SELECT COUNT('ID') AS `COUNT` FROM {$wpdb->prefix}posts WHERE 1=1 $exclude $exclude_categories AND post_type IN ('{$post_types}') $statuses_query");
        $post_count = $results[0]->COUNT;

        $taxonomies = Linkilo_Build_AdminSettings::getTermTypes();
        if (!empty($taxonomies)) {
            //add all categories to array
            $exclude = "";
            if ($post->type == 'term') {
                $exclude = " AND t.term_id != {$post->id} ";
            }

            $results = $wpdb->get_results("SELECT COUNT(t.term_id)  AS `COUNT` FROM {$wpdb->prefix}term_taxonomy tt LEFT JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id WHERE tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $exclude");
            $post_count += $results[0]->COUNT;
        }    
        
        return $post_count;
    }

    public static function getExternalSitePostCount(){
        global $wpdb;
        $data_table = $wpdb->prefix . 'linkilo_site_linking_data';
        $post_count = 0;


        $linked_sites = Linkilo_Build_ConnectMultipleSite::get_linked_sites();
        if(!empty($linked_sites)){
            $results = $wpdb->get_var("SELECT COUNT(item_id) FROM {$data_table}");
            $post_count += $results;
        }

        return $results;
    }

    /**
     * Get link suggestions for the post
     *
     * @param $post_id
     * @param $ui
     * @param null $target_post_id
     * @return array|mixed
     */
    public static function getPostSuggestions($post, $target = null, $all = false, $keyword = null, $count = null, $process_key = 0)
    {
        global $wpdb;
        $ignored_words = Linkilo_Build_AdminSettings::getIgnoreWords();
        $is_outgoing = (empty($target)) ? true: false;

        if ($target) {
            if(LINKILO_IS_LINKS_TABLE_EXISTS){
                $internal_links = Linkilo_Build_UrlRecord::getCachedReportInternalIncomingLinks($target);
            }else{
                $internal_links = Linkilo_Build_UrlRecord::getInternalIncomingLinks($target);
            }
            
        } else {

            $internal_links = get_transient('linkilo_outgoing_post_links' . $process_key);
            if(empty($internal_links)){
                $internal_links = Linkilo_Build_UrlRecord::getOutboundLinks($post);
                $internal_links = $internal_links['internal'];
                set_transient('linkilo_outgoing_post_links' . $process_key, self::compress($internal_links), MINUTE_IN_SECONDS * 15);

            }else{
                $internal_links = self::decompress($internal_links);
            }

        }

        $used_posts = [];
        foreach ($internal_links as $link) {
            if (!empty($link->post)) {
                $used_posts[] = ($link->post->type == 'term' ? 'cat' : '') . $link->post->id;
            }
        }

        //get all possible words from post titles
        $words_to_posts = self::getTitleWords($post, $target, $keyword, $count, $process_key);

        // if this is an incoming suggestion call
        if(!empty($target)){
            // get all selected focus keywords
            $focus_keywords = self::getPostKeywords($target, $process_key);
        }else{
            $post_keywords = self::getPostKeywords($post, $process_key);
            $focus_keywords = self::getOutboundPostKeywords($words_to_posts);

//            $unique_keywords = self::getPostUniqueKeywords($focus_keywords, $process_key);
        }

        //get all posts with same category
        $result = self::getSameCategories($post, $process_key, $is_outgoing);
        $category_posts = [];
        foreach ($result as $cat) {
            $category_posts[] = $cat->object_id;
        }

        $word_segments = array();
        if(!empty($target) && method_exists('Linkilo_Build_Stemmer', 'get_segments') && empty($_REQUEST['keywords'])){
            // todo consider caching this too since it'll have to be called multiple times
            $word_segments = array();
            if(!empty($focus_keywords)){
                foreach($focus_keywords as $dat){
                    $dat_words = explode(' ', $dat->stemmed);
                    $word_segments = array_merge($word_segments, $dat_words);
                }
            }

            $word_segments = array_merge($word_segments, array_keys($words_to_posts));
            $word_segments = Linkilo_Build_Stemmer::get_segments($word_segments);
        }

        // if this is an incoming link scan
        if(!empty($target)){
            $phrases = self::getPhrases($post->getContent(), false, $word_segments);
        }else{
            // if this is an outgoing link scan, get the phrases formatted for outgoing use
            $phrases = self::getOutboundPhrases($post, $process_key);
        }

        // get if the user wants to only match on focus keywords, isn't searching, and this is for incoming suggestions
        $only_match_focus_keywords = (!empty(get_option('linkilo_only_match_focus_keywords', false)) && empty($_REQUEST['keywords']) && !empty($target));

        //divide text to phrases
        foreach ($phrases as $key_phrase => $phrase) {
            if(empty($target)){
                // if this is an outgoing link search, remove all phrases that contain the focus keywords.
                $has_keywords = self::checkSentenceForKeywords($phrase->text, $post_keywords/*, $unique_keywords*/);
                if($has_keywords){
                    unset($phrases[$key_phrase]);
                    continue;
                }
            }

            //get array of unique sentence words cleared from ignore phrases
            if (!empty($_REQUEST['keywords'])) {
                $sentence = trim(preg_replace('/\s+/', ' ', $phrase->text));
                $words_uniq = array_unique(Linkilo_Build_WordFunctions::getWords($sentence));
            } else {
                // if this is an incoming scan
                if(!empty($target)){
                    $words_uniq = array_unique(Linkilo_Build_WordFunctions::cleanFromIgnorePhrases($phrase->text));
                }else{
                    // if this is an outgoing scan
                    $words_uniq = $phrase->words_uniq;
                }
            }

            $suggestions = [];
            foreach ($words_uniq as $word) {
                // if we're only matching with focus keywords, exit the loop
                if($only_match_focus_keywords){
                    break;
                }

                if (empty($_REQUEST['keywords']) && in_array($word, $ignored_words)) {
                    continue;
                }

                $word = str_replace(['.', '!', '?', '\'', ':', '"'], '', $word);
                $word = Linkilo_Build_Stemmer::Stem(Linkilo_Build_WordFunctions::strtolower($word));

                //skip word if no one post title has this word
                if (empty($words_to_posts[$word])) {
                    continue;
                }

                //create array with all possible posts for current word
                foreach ($words_to_posts[$word] as $p) {
                    if (is_null($target)) {
                        $key = $p->type == 'term' ? 'cat' . $p->id : $p->id;
                    } else {
                        $key = $post->type == 'term' ? 'cat' . $post->id : $post->id;
                    }

                    if (in_array($key, $used_posts)) {
                        continue;
                    }

                    //create new suggestion
                    if (empty($suggestions[$key])) {
                        //check if post have same category with main post
                        $same_category = false;
                        if ($p->type == 'post' && in_array($p->id, $category_posts)) {
                            $same_category = true;
                        }

                        if (!is_null($target)) {
                            $suggestion_post = $post;
                        } else {
                            $suggestion_post = $p;
                        }

                        // unset the suggestions post content if it's set
                        if(isset($suggestion_post->content)){
                            $suggestion_post->content = null;
                        }
                
                        $suggestions[$key] = [
                            'post' => $suggestion_post,
                            'post_score' => $same_category ? .5 : 0,
                            'words' => []
                        ];
                    }

                    //add new word to suggestion
                    if (!in_array($word, $suggestions[$key]['words'])) {
                        $suggestions[$key]['words'][] = $word;
                        $suggestions[$key]['post_score'] += 1;
                    }
                }
            }

            // if there are focus keywords
            if(!empty($focus_keywords)){
                // stemm the current sentence
                $stemmed_phrase = Linkilo_Build_WordFunctions::getStemmedSentence($phrase->text);

                foreach($focus_keywords as $focus_keyword){
                    // skip the keyword if it's only 2 chars long
                    if(3 > strlen($focus_keyword->keywords)){
                        continue;
                    }

                    // if the keyword is in the phrase
                    if(false !== strpos($stemmed_phrase, $focus_keyword->stemmed)){
                        
                        // if we're doing outgoing suggestion matching
                        if(empty($target)){
                            $key = $focus_keyword->post_type == 'term' ? 'cat' . $focus_keyword->post_id : $focus_keyword->post_id;
                            $link_post = new Linkilo_Build_Model_Feed($focus_keyword->post_id, $focus_keyword->post_type);
                        }else{
                            $key = $post->type == 'term' ? 'cat' . $post->id : $post->id;
                            $link_post = $post;
                        }
                        if (in_array($key, $used_posts)) {
                            break;
                        }

                        //create new suggestion
                        if (!isset($suggestions[$key])) {

                            //check if post have same category with main post
                            $same_category = false;
                            if ($link_post->type == 'post' && in_array($link_post->id, $category_posts)) {
                                $same_category = true;
                            }

                            // unset the suggestions post content if it's set
                            if(isset($link_post->content)){
                                $link_post->content = null;
                            }

                            $suggestions[$key] = [
                                'post' => $link_post,
                                'post_score' => $same_category ? .5 : 0,
                                'words' => []
                            ];
                        }

                        foreach(explode(' ', $focus_keyword->stemmed) as $word){
                            //add new word to suggestion
                            if (!in_array($word, $suggestions[$key]['words'])) {
                                $suggestions[$key]['words'][] = $word;
                                $suggestions[$key]['post_score'] += 30; // add more points since this is for a focus keyword
                                $suggestions[$key]['passed_focus_keywords'] = true;
                            }elseif(!isset($suggestions[$key]['passed_focus_keywords'])){
                                $suggestions[$key]['post_score'] += 20; // add more points since this is for a focus keyword
                                $suggestions[$key]['passed_focus_keywords'] = true;
                            }
                        }
                    }
                }
            }

            /** Performs a word-by-word keyword match. So if a "Keyword" contains text like "best business site", it will check for matches to "best", "business", and "site". Rather than seeing if the text contains "best business site" specifically. **//*
            // create the focus keyword suggestions
            foreach ($uniq_word_list as $word) {
                if(!isset($focus_keywords[$word])){
                    continue;
                }

                foreach($focus_keywords[$word] as $key_id => $kwrd){
                    $key = $post->type == 'term' ? 'cat' . $post->id : $post->id;
                    if (in_array($key, $used_posts)) {
                        continue;
                    }

                    //create new suggestion
                    if (!isset($suggestions[$key])) {

                        //check if post have same category with main post
                        $same_category = false;
                        if ($post->type == 'post' && in_array($post->id, $category_posts)) {
                            $same_category = true;
                        }

                        // unset the suggestions post content if it's set
                        if(isset($post->content)){
                            $post->content = null;
                        }

                        $suggestions[$key] = [
                            'post' => $post,
                            'post_score' => $same_category ? .5 : 0,
                            'words' => []
                        ];
                    }

                    //add new word to suggestion
                    if (!in_array($word, $suggestions[$key]['words'])) {
                        $suggestions[$key]['words'][] = $word;
                        $suggestions[$key]['post_score'] += 3; // add more points since this is for a focus keyword
                        $suggestions[$key]['passed_focus_keywords'] = true;
                    }elseif(!isset($suggestions[$key]['passed_focus_keywords'])){
                        $suggestions[$key]['post_score'] += 2; // add more points since this is for a focus keyword
                        $suggestions[$key]['passed_focus_keywords'] = true;
                    }

                    // award more points if the suggestion has an exact match with the keywords
                    if($kwrd->word_count > 1 && false !== strpos(Linkilo_Build_WordFunctions::getStemmedSentence($phrase->text), $kwrd->stemmed)){
                        $suggestions[$key]['post_score'] += 1000;
                    }
                }
            }*/

            //check if suggestion has at least 2 words & is less than 10 words long, and then calculate count of close words
            foreach ($suggestions as $key => $suggestion) {
                if ((!empty($_REQUEST['keywords']) && count($suggestion['words']) != count(array_unique(explode(' ', $keyword))))
                    || (empty($_REQUEST['keywords']) && count($suggestion['words']) < 2 && !isset($suggestion['passed_focus_keywords']))
                    || (10 < self::getSuggestionAnchorLength($phrase, $suggestion['words']))
                ) {
                    unset ($suggestions[$key]);
                    continue;
                }

                sort($suggestion['words']);

                $close_words = self::getMaxCloseWords($suggestion['words'], $suggestion['post']->getTitle());

                if ($close_words > 1) {
                    $suggestion['post_score'] += $close_words;
                }

                //calculate anchor score
                $close_words = self::getMaxCloseWords($suggestion['words'], $phrase->text);
                $suggestion['anchor_score'] = count($suggestion['words']);
                if ($close_words > 1) {
                    $suggestion['anchor_score'] += $close_words * 2;
                }
                $suggestion['total_score'] = $suggestion['anchor_score'] + $suggestion['post_score'];

                $phrase->suggestions[$key] = new Linkilo_Build_Model_UrlRecommendation($suggestion);
            }

            if (!count($phrase->suggestions)) {
                unset($phrases[$key_phrase]);
                continue;
            }

            usort($phrase->suggestions, function ($a, $b) {
                if ($a->post_score == $b->post_score) {
                    return 0;
                }
                return ($a->post_score > $b->post_score) ? -1 : 1;
            });
        }

        //remove same suggestions on top level
        foreach ($phrases as $key => $phrase) {
            if(empty($phrase->suggestions)){
                unset($phrases[$key]);
                continue;
            }
            $post_key = ($phrase->suggestions[0]->post->type=='term'?'cat':'') . $phrase->suggestions[0]->post->id;
            if (!empty($target) || !in_array($post_key, $used_posts)) {
                $used_posts[] = $post_key;
            } else {
                if (!empty(self::$undeletable)) {
                    $phrase->suggestions[0]->opacity = .5;
                } else {
                    unset($phrase->suggestions[0]);
                }

            }

            if (!count($phrase->suggestions)) {
                unset($phrases[$key]);
            } else {
                if (!empty(self::$undeletable)) {
                    $i = 1;
                    foreach ($phrase->suggestions as $suggestion) {
                        $i++;
                        if ($i > 10) {
                            $suggestion->opacity = .5;
                        }
                    }
                } else {
                    if (!$all) {
                        $phrase->suggestions = array_slice($phrase->suggestions, 0, 10);
                    }else{
                        $phrase->suggestions = array_values($phrase->suggestions);
                    }
                }
            }
        }

        $phrases = self::deleteWeakPhrases($phrases);

        return $phrases;
    }

    public static function getExternalSiteSuggestions($post, $all = false, $keyword = null, $count = null, $process_key = 0){
        $ignored_words = Linkilo_Build_AdminSettings::getIgnoreWords();

        $link_index = get_transient('linkilo_external_post_link_index_' . $process_key);

        if(empty($link_index)){
            $external_links = Linkilo_Build_UrlRecord::getOutboundLinks($post);
            $link_index = array();
            if(isset($external_links['external'])){
                foreach($external_links['external'] as $link){
                    $link_index[$link->url] = true;
                }
            }
            unset($external_links);
            set_transient('linkilo_external_post_link_index_' . $process_key, self::compress($link_index), MINUTE_IN_SECONDS * 15);
        }else{
            $link_index = self::decompress($link_index);
        }

        //get all possible words from external post titles
        $words_to_posts = self::getExternalTitleWords(false, false, $count, $link_index);

        $used_posts = array();

        $phrases = self::getOutboundPhrases($post, $process_key);

        //divide text to phrases
        foreach ($phrases as $key_phrase => $phrase) {

            $suggestions = [];
            foreach ($phrase->words_uniq as $word) {
                if (empty($_REQUEST['keywords']) && in_array($word, $ignored_words)) {
                    continue;
                }

                //skip word if no one post title has this word
                if (empty($words_to_posts[$word])) {
                    continue;
                }

                //create array with all possible posts for current word
                foreach ($words_to_posts[$word] as $p) {
                    $key = $p->type == 'term' ? 'ext_cat' . $p->id : 'ext_post' . $p->id;

                    //create new suggestion
                    if (empty($suggestions[$key])) {
                        $suggestion_post = $p;
                
                        $suggestions[$key] = [
                            'post' => $suggestion_post,
                            'post_score' => 0,
                            'words' => []
                        ];
                    }

                    //add new word to suggestion
                    if (!in_array($word, $suggestions[$key]['words'])) {
                        $suggestions[$key]['words'][] = $word;
                        $suggestions[$key]['post_score'] += 1;
                    }
                }
            }

            //check if suggestion has at least 2 words & is less than 10 words long, and then calculate count of close words
            foreach ($suggestions as $key => $suggestion) {
                if ((!empty($_REQUEST['keywords']) && count($suggestion['words']) != count(array_unique(explode(' ', $keyword))))
                    || (empty($_REQUEST['keywords']) && count($suggestion['words']) < 2)
                    || (10 < self::getSuggestionAnchorLength($phrase, $suggestion['words']))
                ) {
                    unset ($suggestions[$key]);
                    continue;
                }

                sort($suggestion['words']);

                $close_words = self::getMaxCloseWords($suggestion['words'], $suggestion['post']->getTitle());

                if ($close_words > 1) {
                    $suggestion['post_score'] += $close_words;
                }

                //calculate anchor score
                $close_words = self::getMaxCloseWords($suggestion['words'], $phrase->text);
                $suggestion['anchor_score'] = count($suggestion['words']);
                if ($close_words > 1) {
                    $suggestion['anchor_score'] += $close_words * 2;
                }
                $suggestion['total_score'] = $suggestion['anchor_score'] + $suggestion['post_score'];

                $phrase->suggestions[$key] = new Linkilo_Build_Model_UrlRecommendation($suggestion);
            }

            if (!count($phrase->suggestions)) {
                unset($phrases[$key_phrase]);
                continue;
            }

            usort($phrase->suggestions, function ($a, $b) {
                if ($a->post_score == $b->post_score) {
                    return 0;
                }
                return ($a->post_score > $b->post_score) ? -1 : 1;
            });
        }

        //remove same suggestions on top level
        foreach ($phrases as $key => $phrase) {
            $post_key = ($phrase->suggestions[0]->post->type=='term'?'ext_cat':'ext_post') . $phrase->suggestions[0]->post->id;
            if (!empty($target) || !in_array($post_key, $used_posts)) {
                $used_posts[] = $post_key;
            } else {
                if (!empty(self::$undeletable)) {
                    $phrase->suggestions[0]->opacity = .5;
                } else {
                    unset($phrase->suggestions[0]);
                }

            }

            if (!count($phrase->suggestions)) {
                unset($phrases[$key]);
            } else {
                if (!empty(self::$undeletable)) {
                    $i = 1;
                    foreach ($phrase->suggestions as $suggestion) {
                        $i++;
                        if ($i > 10) {
                            $suggestion->opacity = .5;
                        }
                    }
                } else {
                    if (!$all) {
                        $phrase->suggestions = array_slice($phrase->suggestions, 0, 10);
                    }else{
                        $phrase->suggestions = array_values($phrase->suggestions);
                    }
                }
            }
        }

        $phrases = self::deleteWeakPhrases($phrases);

        return $phrases;
    }

    /**
     * Divide text to sentences
     *
     * @param $content
     * @return array
     */
    public static function getPhrases($content, $with_links = false, $word_segments = array(), $single_words = false, $keyword_exclude_elements = "")
    {
        // replace unicode chars with their decoded forms
        $replace_unicode = array('\u003c', '\u003', '\u0022');
        $replacements = array('<', '>', '"');

        $content = str_ireplace($replace_unicode, $replacements, $content);

        // remove the heading tags from the text
        $content = mb_ereg_replace('<h1(?:[^>]*)>(.*?)<\/h1>|<h2(?:[^>]*)>(.*?)<\/h2>|<h3(?:[^>]*)>(.*?)<\/h3>|<h4(?:[^>]*)>(.*?)<\/h4>|<h5(?:[^>]*)>(.*?)<\/h5>|<h6(?:[^>]*)>(.*?)<\/h6>', '', $content);

        /*Perform exclusion check*/
        /* under this area check if the provided keyword is assgined any html tags that needs to be excluded, if so then exlusion process will be performed as below */
        $excluded_html_elements = ($keyword_exclude_elements != null) ? explode(",", $keyword_exclude_elements) : array();
        $preg_regex = "";

        $last_key = end(array_keys($excluded_html_elements));

        if (sizeof($excluded_html_elements) > 0 || $excluded_html_elements != null) {
            foreach ($excluded_html_elements as $i => $tag) {
                if (sizeof($excluded_html_elements) > 1 && $i !== $last_key) {
                    $preg_regex .= "<".$tag."(?:[^>]*)>(.*?)<\/".$tag.">|";
                }else{
                    $preg_regex .= "<".$tag."(?:[^>]*)>(.*?)<\/".$tag.">";
                }
            }
            $content = mb_ereg_replace($preg_regex, '', $content);
        }
        /*Perform exclusion check ends*/
        // echo $last_key;
        // echo $preg_regex;
        // die();

        // encode the contents of attributes so we don't have mistakes when breaking the content into sentences
        $content = preg_replace_callback('|(?:[a-zA-Z-]*="([^"]*?)")[^<>]*?|i', function($i){ return str_replace($i[1], 'linkilo-attr-replace_' . base64_encode($i[1]), $i[0]); }, $content);

        //divide text to sentences
        $replace = [
            ['.<', '. ', '. ', '.\\', '!<', '! ', '! ', '!\\', '?<', '? ', '? ', '?\\', '<div', '<br', '<li', '<p', '<h1', '<h2', '<h3', '<h4', '<h5', '<h6'],
            [".\n<", ". \n", ".\n", ".\n\\", "!\n<", "! \n", "!\n", "!\n\\", "?\n<", "? \n", "?\n", "?\n\\", "\n<div", "\n<br", "\n<li", "\n<p", "\n<h1", "\n<h2", "\n<h3", "\n<h4", "\n<h5", "\n<h6"]
        ];
        $content = str_ireplace($replace[0], $replace[1], $content);
        $content = preg_replace('|\.([A-Z]{1})|', ".\n$1", $content);
        $content = preg_replace('|\[[^\]]+\]|i', "\n", $content);

        $list = explode("\n", $content);


        // decode all the attributes now that the content has been broken into sentences
        foreach($list as $key => $item){
            if(false !== strpos($item, 'linkilo-attr-replace_')){
                $list[$key] = preg_replace_callback('|(?:[a-zA-Z-]*="(linkilo-attr-replace_([^"]*?))")[^<>]*?|i', function($i){
                    return str_replace($i[1], base64_decode($i[2]), $i[0]); 
                }, $item);
            }
        }

        self::removeEmptySentences($list, $with_links);
        self::trimTags($list, $with_links);
        $list = array_slice($list, Linkilo_Build_AdminSettings::getSkipSentences());

        $phrases = [];
        foreach ($list as $item) {
            $item = trim($item);

            if(!empty($word_segments)){
                // check if the phrase contains 2 title words
                $wcount = 0;
                foreach($word_segments as $seg){
                    if(false !== stripos($item, $seg)){
                        $wcount++;
                        if($wcount > 1){
                            break;
                        }
                    }
                }
                if($wcount < 2){
                    continue;
                }
            }

            if (in_array(substr($item, -1), ['.', ',', '!', '?'])) {
                $item = substr($item, 0, -1);
            }

            $sentence = [
                'src' => $item,
                'text' => strip_tags(htmlspecialchars_decode($item))
            ];

            $sentence['text'] = trim($sentence['text']);

            //add sentence to array if it has at least 2 words
            if (!empty($sentence['text']) && ($single_words || count(explode(' ', $sentence['text'])) > 1)) {
                $phrases = array_merge($phrases, self::getPhrasesFromSentence($sentence, true));
            }
        }


        return $phrases;
    }

    /**
     * Get phrases from sentence
     */
    public static function getPhrasesFromSentence($sentence, $one_word = false)
    {
        $phrases = [];
        $replace = [', ', ': ', '; ', ' – ', ' (', ') ', ' {', '} '];
        $src = $sentence['src'];

        //change divided symbols inside tags to special codes
        preg_match_all('|<[^>]+>|', $src, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $tag) {
                $tag_replaced = $tag;
                foreach ($replace as $key => $value) {
                    if (strpos($tag, $value) !== false) {
                        $tag_replaced = str_replace($value, "[rp$key]", $tag_replaced);
                    }
                }

                if ($tag_replaced != $tag) {
                    $src = str_replace($tag, $tag_replaced, $src);
                }
            }
        }

        //divide sentence to phrases
        $src = str_ireplace($replace, "\n", $src);

        //change special codes to divided symbols inside tags
        foreach ($replace as $key => $value) {
            $src = str_replace("[rp$key]", $value, $src);
        }

        $list = explode("\n", $src);

        foreach ($list as $item) {
            $phrase = new Linkilo_Build_Model_Clause([
                'text' => trim(strip_tags(htmlspecialchars_decode($item))),
                'src' => $item,
                'sentence_text' => $sentence['text'],
                'sentence_src' => $sentence['src'],
            ]);

            if (!empty($phrase->text) && ($one_word || count(explode(' ', $phrase->text)) > 1)) {
                $phrases[] = $phrase;
            }
        }

        return $phrases;
    }

    /**
     * Collect uniques words from all post titles
     *
     * @param $post_id
     * @param null $target
     * @return array
     */
    public static function getTitleWords($post, $target = null, $keyword = null, $count = null, $process_key = 0)
    {
        global $wpdb;

        $start = microtime(true);
        $ignore_words = Linkilo_Build_AdminSettings::getIgnoreWords();
        $ignore_posts = Linkilo_Build_AdminSettings::getIgnorePosts();
        $ignore_categories_posts = Linkilo_Build_AdminSettings::getIgnoreCategoriesPosts();
        $ignore_numbers = get_option(LINKILO_NUMBERS_TO_IGNORE_OPTIONS, 1);
        $only_show_cornerstone = (get_option('linkilo_link_to_yoast_cornerstone', false) && empty($target));
        $outgoing_selected_posts = Linkilo_Build_AdminSettings::getOutboundSuggestionPostIds();

        $posts = [];
        if (!is_null($target)) {
            $posts[] = $target;
        } else {
            $limit  = Linkilo_Build_AdminSettings::getProcessingBatchSize();
            $post_ids = get_transient('linkilo_title_word_ids_' . $process_key);
            if(empty($post_ids) && !is_array($post_ids)){
                //add all posts to array
                $exclude = self::getTitleQueryExclude($post);
                $post_types = implode("','", Linkilo_Build_AdminSettings::getPostTypes());
                $offset = intval($count) * $limit;
                
                // get all posts in the same language if translation active
                $include = "";
                if (Linkilo_Build_AdminSettings::translation_enabled()) {
                    $ids = Linkilo_Build_Feed::getSameLanguagePosts($post->id);

                    if (!empty($ids)) {
                        $ids = array_slice($ids, $offset, $limit);
                        $include = " AND ID IN (" . implode(', ', $ids) . ") ";
                    } else {
                        $include = " AND ID IS NULL ";
                    }
                }

                $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses();
                $post_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->prefix}posts WHERE 1=1 $exclude AND post_type IN ('{$post_types}') $statuses_query " . $include);

                if(empty($post_ids)){
                    $post_ids = array();
                }

                set_transient('linkilo_title_word_ids_' . $process_key, $post_ids, MINUTE_IN_SECONDS * 15);
            }

            // if we're only supposed to show links to the Yoast cornerstone content
            if($only_show_cornerstone && !empty($result)){
                // get the ids from the initial query
                $ids = array();
                foreach($result as $item){
                    $ids[] = $item->ID;
                }

                // query the meta to see what posts have been set as cornerstone content
                $result = $wpdb->get_results("SELECT `post_id` AS ID FROM {$wpdb->prefix}postmeta WHERE `post_id` IN (" . implode(', ', $ids) . ") AND `meta_key` = '_yoast_wpseo_is_cornerstone'");
            }

            // if we're limiting outgoing suggestions to specfic posts
            if(empty($target) && !empty($outgoing_selected_posts) && !empty($result)){
                // get all of the ids that the user wants to make suggestions to
                $ids = array();
                foreach($outgoing_selected_posts as $selected_post){
                    if(false !== strpos($selected_post, 'post_')){
                        $ids[substr($selected_post, 5)] = true;
                    }
                }

                // filter out all the items that aren't in the outgoing suggestion limits
                $result_items = array();
                foreach($result as $item){
                    if(isset($ids[$item->ID])){
                        $result_items[] = $item;
                    }
                }

                // update the results with the filtered ids
                $result = $result_items;
            }


            $posts = [];
            $process_ids = array_slice($post_ids, 0, $limit);

            if(!empty($process_ids)){
                $process_ids = implode("', '", $process_ids);
                $result = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->prefix}posts WHERE ID IN ('{$process_ids}')");

                foreach ($result as $item) {
                    if (!in_array('post_' . $item->ID, $ignore_posts) && !in_array($item->ID, $ignore_categories_posts)) {
                        $post_obj = new Linkilo_Build_Model_Feed($item->ID);
                        $post_obj->title = $item->post_title;
                        $posts[] = $post_obj;
                    }
                }

                // remove this batch of post ids from the list and save the list
                $save_ids = array_slice($post_ids, $limit);
                set_transient('linkilo_title_word_ids_' . $process_key, $save_ids, MINUTE_IN_SECONDS * 15);
            }

            if (!empty(Linkilo_Build_AdminSettings::getTermTypes()) && empty($_POST['same_category']) && empty($only_show_cornerstone)) {
                if (is_null($count) || $count == 0) {
                    //add all categories to array
                    $exclude = "";
                    if ($post->type == 'term') {
                        $exclude = " AND t.term_id != {$post->id} ";
                    }

                    $taxonomies = Linkilo_Build_AdminSettings::getTermTypes();
                    $result = $wpdb->get_results("SELECT t.term_id FROM {$wpdb->prefix}term_taxonomy tt LEFT JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id WHERE tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $exclude");

                    // if the user only wants to make outgoing suggestions to specific categories
                    if(empty($target) && !empty($outgoing_selected_posts) && !empty($result)){
                        // get all of the ids that the user wants to make suggestions to
                        $ids = array();
                        foreach($outgoing_selected_posts as $selected_term){
                            if(false !== strpos($selected_term, 'term_')){
                                $ids[substr($selected_term, 5)] = true;
                            }
                        }

                        foreach($result as $key => $item){
                            if(!isset($ids[$item->term_id])){
                                unset($result[$key]);
                            }
                        }
                    }

                    foreach ($result as $term) {
                        if (!in_array('term_' . $term->term_id, $ignore_posts)) {
                            $posts[] = new Linkilo_Build_Model_Feed($term->term_id, 'term');
                        }
                    }
                }
            }
        }

        $words = [];
        foreach ($posts as $key => $p) {
            //get unique words from post title
            if (!empty($keyword)) {
                $title_words = array_unique(Linkilo_Build_WordFunctions::getWords($keyword));
            } else {
                $title_words = array_unique(Linkilo_Build_WordFunctions::getWords($p->getTitle()));
            }

            foreach ($title_words as $word) {
                $word = Linkilo_Build_Stemmer::Stem(Linkilo_Build_WordFunctions::strtolower($word));

                //check if word is not a number and is not in the ignore words list
                if (!empty($_REQUEST['keywords']) ||
                    (strlen($word) > 2 && !in_array($word, $ignore_words) && (!$ignore_numbers || !is_numeric(str_replace(['.', ',', '$'], '', $word))))
                ) {
                    $words[$word][] = $p;
                }
            }

            if ($key % 100 == 0 && microtime(true) - $start > 10) {
                break;
            }
        }

        return $words;
    }

    public static function getExternalTitleWords($post, $keyword = null, $count = null, $external_links = array()){
        global $wpdb;

        $start = microtime(true);
        $ignore_words = Linkilo_Build_AdminSettings::getIgnoreWords();
        $ignore_numbers = get_option(LINKILO_NUMBERS_TO_IGNORE_OPTIONS, 1);
        $ignore_post_urls = Linkilo_Build_AdminSettings::getIgnoreLinks(); // get the ignored links since we only have the external post's view url to go on
        $external_site_data = $wpdb->prefix  . 'linkilo_site_linking_data';

        $posts = [];

        //add all posts to array
        $limit  = Linkilo_Build_AdminSettings::getProcessingBatchSize();
        $offset = intval($count) * $limit;

        // check if the user has disabled suggestions for an external site
        $no_suggestions = get_option('linkilo_disable_external_site_suggestions', array());
        $ignore_sites = '';
        if(!empty($no_suggestions)){
            $urls = array_keys($no_suggestions);
            $ignore = implode('\', \'', $urls);
            $ignore_sites = "WHERE `site_url` NOT IN ('$ignore')";
        }

        $result = $wpdb->get_results("SELECT * FROM {$external_site_data} {$ignore_sites} LIMIT {$limit} OFFSET {$offset}");

        $posts = [];
        foreach ($result as $item) {
            if (!in_array($item->post_url, $ignore_post_urls, true) && !isset($external_links[$item->post_url])) {
                $posts[] = new Linkilo_Build_Model_OuterFeed($item); // pass the whole object from the db
            }
        }
        
        $words = [];
        foreach ($posts as $key => $p) {
            //get unique words from post title
            if (!empty($keyword)) {
                $title_words = array_unique(Linkilo_Build_WordFunctions::getWords($keyword));
            } else {
                $title_words = array_unique(explode(' ', $p->stemmedTitle));
            }
            
            foreach ($title_words as $word) {
                //check if word is not a number and is not in the ignore words list
                if (!empty($_REQUEST['keywords']) ||
                    (strlen($word) > 2 && !in_array($word, $ignore_words) && (!$ignore_numbers || !is_numeric(str_replace(['.', ',', '$'], '', $word))))
                ) {
                    $words[$word][] = $p;
                }
            }

            if ($key % 100 == 0 && microtime(true) - $start > 10) {
                break;
            }
        }

        return $words;
    }

    /**
     * Gets all the keywords the post is trying to rank for.
     * First checks to see if the keywords have been cached before running through the keyword loading process.
     * 
     * @param object $post The Linkilo post object that we're getting the keywords for.
     * @param int $key The key assigned to this suggestion processing program.
     * @return array $all_keywords All the keywords the current post has.
     **/
    public static function getPostKeywords($post, $process_key = 0){
        $all_keywords = get_transient('linkilo_post_suggestions_keywords_' . $process_key);
        if(empty($all_keywords)){
            // get the focus keywords for the current post
            $focus_keywords = self::getIncomingTargetKeywords($post);

            // if there's no focus keywords
            if(empty($focus_keywords)){
                // get the post's possible keywords from the content and url
                $content_keywords = self::getContentKeywords($post);
            }else{
                $content_keywords = array();
            }

            // merge the keywords together
            $all_keywords = array_merge($focus_keywords, $content_keywords);

            set_transient('linkilo_post_suggestions_keywords_' . $process_key, $all_keywords, MINUTE_IN_SECONDS * 10);
        }

        return $all_keywords;
    }

    /**
     * Gets all the keywords the post is trying to rank for.
     * First checks to see if the keywords have been cached before running through the keyword loading process.
     * 
     * @param object $post The Linkilo post object that we're getting the keywords for.
     * @param int $key The key assigned to this suggestion processing program.
     * @return array $all_keywords All the keywords the current post has.
     **/
    public static function getOutboundPostKeywords($words_to_posts = array()){
        if(empty($words_to_posts)){
            return array();
        }

        // obtain the post ids from the post word data
        $id_data = array();
        foreach($words_to_posts as $word_data){
            foreach($word_data as $post){
                $id_data[$post->type][$post->id] = true;
            }
        }

        $all_keywords = array();
        foreach($id_data as $type => $ids){
            $keywords = Linkilo_Build_FocusKeyword::get_active_keywords_by_post_ids(array_keys($ids), $type);

            if(!empty($keywords)){
                foreach($keywords as $keyword){
                    $all_keywords[] = new Linkilo_Build_Model_FocusRelateKeyword($keyword);
                }
            }
        }

        return $all_keywords;
    }

    /**
     * Gets the focus keyword data from the database for the current post, formatted for use in the incoming suggestions.
     **/
    public static function getIncomingTargetKeywords($post){
        $keywords = Linkilo_Build_FocusKeyword::get_active_keywords_by_post_ids($post->id, $post->type);
        if(empty($keywords)){
            return array();
        }

        $keyword_list = array();
        foreach($keywords as $keyword){
            $keyword_list[] = new Linkilo_Build_Model_FocusRelateKeyword($keyword);
        }

        return $keyword_list;
    }

    /**
     * Extracts the post's keywords from the post's content and url.
     * 
     **/
    public static function getContentKeywords($post_obj){
        if(empty($post_obj)){
            return array();
        }

        $post_keywords = array();
        
        // first get the keyword data from the url/slug
        if($post_obj->type === 'post'){
            $post = get_post($post_obj->id);
            $url_words = $post->post_name;
        }else{
            $post = get_term($post_obj->id);
            $url_words = $post->slug;
        }

        $keywords = implode(' ', explode('-', $url_words));

        $data = array(
            'post_id' => $post_obj->id, 
            'post_type' => $post_obj->type, 
            'keyword_type' => 'post-keyword', 
            'keywords' => $keywords,
            'checked' => 1 // the keyword is effectively checked since it's in the url
        );

        // create the url keyword object and add it to the list of keywords
        $post_keywords[] = new Linkilo_Build_Model_FocusRelateKeyword($data);

        // now pull the keywords from the h1 tags if present
        if($post_obj->type === 'post'){
            $content = $post->post_title;
        }else{
            $content = $post->name;
        }

        $data = array(
            'post_id' => $post_obj->id, 
            'post_type' => $post_obj->type, 
            'keyword_type' => 'post-keyword', 
            'keywords' => strip_tags($content),
            'checked' => 1 // the keyword is effectively checked since it's in the header
        );

        // create the h1 keyword object and add it to the list of keywords
        $post_keywords[] = new Linkilo_Build_Model_FocusRelateKeyword($data);

/*        
        preg_match('/<h1[^>]*.?>([[:alpha:]\s]*.?)(<\/h1>)/', $content, $matches);
error_log(print_r($post, true));
        if(!empty($matches) && isset($matches[1])){
            $data = array(
                'post_id' => $post_obj->id, 
                'post_type' => $post_obj->type, 
                'keyword_type' => 'post-keyword', 
                'keywords' => strip_tags($matches[1]),
                'checked' => 1 // the keyword is effectively checked since it's in the header
            );

            // create the h1 keyword object and add it to the list of keywords
            $post_keywords[] = new Linkilo_Build_Model_FocusRelateKeyword($data);
        }*/

        return $post_keywords;
    }

    /**
     * Creates a list of all the unique keyword words for use in comparisons.
     * So far only used in loose keyword matching. (Currently disabled)
     **/
    public static function getPostUniqueKeywords($focus_keywords = array(), $process_key = 0){
        if(empty($focus_keywords) || empty($process_key)){
            return array();
        }

        $keywords = get_transient('linkilo_post_suggestions_unique_keywords_' . $process_key);
        if(empty($keywords)){
            $keywords = array();
            // add all the keywords to the array
            foreach($focus_keywords as $keyword){
                $words = explode(' ', $keyword->stemmed);
                $keywords = array_merge($keywords, $words);
            }

            // at the end, do a flip flip to get the unique words
            $keywords = array_flip(array_flip($keywords));

            // save the results to the cache
            set_transient('linkilo_post_suggestions_unique_keywords_' . $process_key, $keywords, MINUTE_IN_SECONDS * 10);
        }

        return $keywords;
    }

    /**
     * Gets the focus keyword data from the database for all the posts, formatted for use in the incoming suggestions.
     * Not currently used.
     **/
    public static function getOutboundTargetKeywords($ignore_ids = array(), $ignore_item_types = array()){
        if(isset($_REQUEST['type']) && 'incoming_suggestions' === $_REQUEST['type']){
            $keywords = Linkilo_Build_FocusKeyword::get_all_active_keywords($ignore_ids, $ignore_item_types);
            if(empty($keywords)){
                return array();
            }

            $keyword_index = array();
            foreach($keywords as $keyword){
                $words = explode(' ', $keyword->keywords);
                foreach($words as $word){
                    $keyword_index[Linkilo_Build_Stemmer::Stem(Linkilo_Build_WordFunctions::strtolower($word))][$keyword->keyword_index] = $keyword;
                }
            }

            return $keyword_index;
        }else{
            return array();
        }
    }

    /**
     * Get max amount of words in group between sentence
     *
     * @param $words
     * @param $title
     * @return int
     */
    public static function getMaxCloseWords($words_used_in_suggestion, $phrase_text)
    {
        // get the individual words in the source phrase, cleaned of puncuation and spaces
        $phrase_text = Linkilo_Build_WordFunctions::getWords($phrase_text);

        // stem each word in the phrase text
        foreach ($phrase_text as $key => $value) {
            $phrase_text[$key] = Linkilo_Build_Stemmer::Stem(Linkilo_Build_WordFunctions::strtolower($value));
        }

        // loop over the phrase words, and find the largest grouping of the suggestion's words that occur in sequence in the phrase
        $max = 0;
        $temp_max = 0;
        foreach($phrase_text as $key => $phrase_word){
            if(in_array($phrase_word, $words_used_in_suggestion)){
                $temp_max++;
                if($temp_max > $max){
                    $max = $temp_max;
                }
            }else{
                if($temp_max > $max){
                    $max = $temp_max;
                }
                $temp_max = 0;
            }
        }

        return $max;
    }

    /**
     * Measures how long an anchor text suggestion would be based on the words from the match
     **/
    public static function getSuggestionAnchorLength($phrase = '', $words = array()){
        // stem the sentence words
        $stemmed_phrase_words = array_map(array('Linkilo_Build_Stemmer', 'Stem'), Linkilo_Build_WordFunctions::getWords($phrase->text));
        // measure how long the anchor text is to be
        $intersect = array_keys(array_intersect($stemmed_phrase_words, $words));
        $start = current($intersect);
        $end = end($intersect);

        return (($end - $start) + 1);
    }

    /**
     * Add anchors to sentences
     *
     * @param $sentences
     * @return mixed
     */
    public static function addAnchors($phrases)
    {
        if(empty($phrases)){
            return array();
        }

        $post = Linkilo_Build_Root::getPost();
        $used_anchors = Linkilo_Build_Feed::getAnchors($post);
        $nbsp = urldecode('%C2%A0');
        $post_focus_keywords = Linkilo_Build_WordFunctions::strtolower(implode(' ', Linkilo_Build_FocusKeyword::get_active_keyword_list($post->id, $post->type)));

        $ignored_words = Linkilo_Build_AdminSettings::getIgnoreWords();
        foreach ($phrases as $key_phrase => $phrase) {
            //prepare rooted words array from phrase
            $words = trim(preg_replace('/\s+|'.$nbsp.'/', ' ', $phrase->text));
            $words = $words_real = explode(' ', $words);
            foreach ($words as $key => $value) {
                $value = str_replace(['[', ']', '(', ')', '{', '}', '.', ',', '!', '?', '\'', ':', '"'], '', $value);
                if (!empty($_REQUEST['keywords']) || !in_array($value, $ignored_words) || false !== strpos($post_focus_keywords, Linkilo_Build_WordFunctions::strtolower($value))) {
                    $words[$key] = Linkilo_Build_Stemmer::Stem(Linkilo_Build_WordFunctions::strtolower(strip_tags($value)));
                } else {
                    unset($words[$key]);
                }
            }

            foreach ($phrase->suggestions as $suggestion_key => $suggestion) {
                //get min and max words position in the phrase
                $min = count($words_real);
                $max = 0;
                foreach ($suggestion->words as $word) {
                    if (in_array($word, $words)) {
                        $pos = array_search($word, $words);
                        $min = $pos < $min ? $pos : $min;
                        $max = $pos > $max ? $pos : $max;
                    }
                }

                // check to see if we can get a link in this suggestion
                $has_words = array_slice($words_real, $min, $max - $min + 1);
                if(empty($has_words)){
                    // if it can't, remove it from the list
                    unset($phrase->suggestions[$suggestion_key]);
                    // and proceed
                    continue;
                }

                //get anchors and sentence with anchor
                $anchor = '';
                $sentence_with_anchor = '<span class="linkilo_sentence"><span class="linkilo_word">' . implode('</span> <span class="linkilo_word">', explode(' ', str_replace($nbsp, ' ', strip_tags($phrase->sentence_src, '<b><i><u><strong>')))) . '</span></span>';
                $sentence_with_anchor = str_replace(['<span class="linkilo_word">(', ')</span>'], ['<span class="linkilo_word no-space-right linkilo-non-word">(</span><span class="linkilo_word">', '</span><span class="linkilo_word no-space-left linkilo-non-word">)</span>'], $sentence_with_anchor);
                $sentence_with_anchor = str_replace(',</span>', '</span><span class="linkilo_word no-space-left linkilo-non-word">,</span>', $sentence_with_anchor);
                $sentence_with_anchor = self::formatTags($sentence_with_anchor);
                if ($max >= $min) {
                    if ($max == $min) {
                        $anchor = '<span class="linkilo_word">' . $words_real[$min] . '</span>';
                        $to = '<a href="%view_link%" target="_blank" class="cst-href-clr">' . $anchor . '</a>';
                        $sentence_with_anchor = preg_replace('/'.preg_quote($anchor, '/').'/', $to, $sentence_with_anchor, 1);
                    } else {
                        $anchor = '<span class="linkilo_word">' . implode('</span> <span class="linkilo_word">', array_slice($words_real, $min, $max - $min + 1)) . '</span>';
                        $from = [
                            '<span class="linkilo_word">' . $words_real[$min] . '</span>',
                            '<span class="linkilo_word">' . $words_real[$max] . '</span>'
                        ];
                        $to = [
                            '<a href="%view_link%" target="_blank" class="cst-href-clr"><span class="linkilo_word">' . $words_real[$min] . '</span>',
                            '<span class="linkilo_word">' . $words_real[$max] . '</span></a>'
                        ];

                        $sentence_with_anchor = preg_replace('/'.preg_quote($from[0], '/').'/', $to[0], $sentence_with_anchor, 1);
                        $begin = strpos($sentence_with_anchor, '<a ');
                        if ($begin !== false) {
                            $first = substr($sentence_with_anchor, 0, $begin);
                            $second = substr($sentence_with_anchor, $begin);
                            $second = preg_replace('/'.preg_quote($from[1], '/').'/', $to[1], $second, 1);
                            $sentence_with_anchor = $first . $second;
                        }
                    }
                }

                self::setSentenceSrcWithAnchor($suggestion, $phrase->sentence_src, $words_real[$min], $words_real[$max]);

                //add results to suggestion
                $suggestion->anchor = $anchor;

                if (in_array(strip_tags($anchor), $used_anchors)) {
                    unset($phrases[$key_phrase]);
                }

                $suggestion->sentence_with_anchor = self::setSuggestionTags($sentence_with_anchor);
            }

            // if there are no suggestions, remove the phrase
            if(empty($phrase->suggestions)){
                unset($phrases[$key_phrase]);
            }
        }

        return $phrases;
    }

    public static function formatTags($sentence_with_anchor){
        $tags = array(
            '<span class="linkilo_word"><b>',
            '<span class="linkilo_word"><i>',
            '<span class="linkilo_word"><u>',
            '<span class="linkilo_word"><strong>',
            '<span class="linkilo_word"><em>',
            '<span class="linkilo_word"></b>',
            '<span class="linkilo_word"></i>',
            '<span class="linkilo_word"></u>',
            '<span class="linkilo_word"></strong>',
            '<span class="linkilo_word"></em>',
            '<b></span>',
            '<i></span>',
            '<u></span>',
            '<strong></span>',
            '<em></span>',
            '</b></span>',
            '</i></span>',
            '</u></span>',
            '</strong></span>',
            '</em></span>'
        );

        // the replace tokens of the tags
        $replace_tags = array(
            '<span class="linkilo_word linkilo_suggestion_tag open-tag linkilo-bold-open linkilo-bold">PGI+</span><span class="linkilo_word">', // these are the base64ed versions of the tags so we can process them later
            '<span class="linkilo_word linkilo_suggestion_tag open-tag linkilo-ital-open linkilo-ital">PGk+</span><span class="linkilo_word">',
            '<span class="linkilo_word linkilo_suggestion_tag open-tag linkilo-under-open linkilo-under">PHU+</span><span class="linkilo_word">',
            '<span class="linkilo_word linkilo_suggestion_tag open-tag linkilo-strong-open linkilo-strong">PHN0cm9uZz4=</span><span class="linkilo_word">',
            '<span class="linkilo_word linkilo_suggestion_tag open-tag linkilo-em-open linkilo-em">PGVtPg==</span><span class="linkilo_word">',
            '<span class="linkilo_word linkilo_suggestion_tag close-tag linkilo-bold-close linkilo-bold">PC9iPg==</span><span class="linkilo_word">',
            '<span class="linkilo_word linkilo_suggestion_tag close-tag linkilo-ital-close linkilo-ital">PC9pPg==</span><span class="linkilo_word">',
            '<span class="linkilo_word linkilo_suggestion_tag close-tag linkilo-under-close linkilo-under">PC91Pg==</span><span class="linkilo_word">',
            '<span class="linkilo_word linkilo_suggestion_tag close-tag linkilo-strong-close linkilo-strong">PC9zdHJvbmc+</span><span class="linkilo_word">',
            '<span class="linkilo_word linkilo_suggestion_tag close-tag linkilo-em-close linkilo-em">PC9lbT4=</span><span class="linkilo_word">',
            '</span><span class="linkilo_word linkilo_suggestion_tag open-tag linkilo-bold-open linkilo-bold">PGI+</span>', 
            '</span><span class="linkilo_word linkilo_suggestion_tag open-tag linkilo-ital-open linkilo-ital">PGk+</span>',
            '</span><span class="linkilo_word linkilo_suggestion_tag open-tag linkilo-under-open linkilo-under">PHU+</span>',
            '</span><span class="linkilo_word linkilo_suggestion_tag open-tag linkilo-strong-open linkilo-strong">PHN0cm9uZz4=</span>',
            '</span><span class="linkilo_word linkilo_suggestion_tag open-tag linkilo-em-open linkilo-em">PGVtPg==</span>',
            '</span><span class="linkilo_word linkilo_suggestion_tag close-tag linkilo-bold-close linkilo-bold">PC9iPg==</span>',
            '</span><span class="linkilo_word linkilo_suggestion_tag close-tag linkilo-ital-close linkilo-ital">PC9pPg==</span>',
            '</span><span class="linkilo_word linkilo_suggestion_tag close-tag linkilo-under-close linkilo-under">PC91Pg==</span>',
            '</span><span class="linkilo_word linkilo_suggestion_tag close-tag linkilo-strong-close linkilo-strong">PC9zdHJvbmc+</span>',
            '</span><span class="linkilo_word linkilo_suggestion_tag close-tag linkilo-em-close linkilo-em">PC9lbT4=</span>',
        );

        return str_replace($tags, $replace_tags, $sentence_with_anchor);
    }

    /**
     * Add anchor to the sentence source
     *
     * @param $suggestion
     * @param $sentence
     * @param $word_start
     * @param $word_end
     */
    public static function setSentenceSrcWithAnchor(&$suggestion, $sentence, $word_start, $word_end)
    {
        $begin = strpos($sentence, $word_start);
        while($begin && substr($sentence, $begin - 1, 1) !== ' ') {
            $begin--;
        }

        $end = strpos($sentence, $word_end, $begin) + strlen($word_end);
        while($end < strlen($sentence) && substr($sentence, $end, 1) !== ' ') {
            $end++;
        }

        $anchor = substr($sentence, $begin, $end - $begin);
        $replace = '<a href="%view_link%" target="_blank">' . $anchor . '</a>';
        $suggestion->sentence_src_with_anchor = str_replace($anchor, $replace, $sentence);

    }

    public static function setSuggestionTags($sentence_with_anchor){
        // if there isn't a tag inside the suggested text, return it
        if(false === strpos($sentence_with_anchor, 'linkilo_suggestion_tag')){
            return $sentence_with_anchor;
        }

        // see if the tag is inside the link
        $link_start = mb_strpos($sentence_with_anchor, '<a href="%view_link%"');
        $link_end = mb_strpos($sentence_with_anchor, '</a>', $link_start);
        $link_length = ($link_end + 4 - $link_start);
        $link = mb_substr($sentence_with_anchor, $link_start, $link_length);

        // if it's not or the open and close tags are in the link, return the link
        if(false === strpos($link, 'linkilo_suggestion_tag') || (false !== strpos($link, 'open-tag') && false !== strpos($link, 'close-tag'))){ // todo make this tag specific. As it is now, we _could_ get the opening of one tag and the closing tag of another one since we're only looking for open and close tags. But considering that we've not had much trouble at all from the prior system, this isn't a priority.
            return $sentence_with_anchor;
        }

        // if we have the opening tag inside the link, move it right until it's outside the link
        if(false !== strpos($link, 'open-tag')){
            // get the tag start
            $open_tag = mb_strpos($sentence_with_anchor, '<span class="linkilo_word linkilo_suggestion_tag open-tag');
            // extract the tag
            $tag = mb_substr($sentence_with_anchor, $open_tag, (mb_strpos($sentence_with_anchor, '</span>', $open_tag) + 7) - $open_tag);
            // replace the tag
            $sentence_with_anchor = mb_ereg_replace(preg_quote($tag), '', $sentence_with_anchor);
            // get the points before and after the link's closing tag
            $link_end = mb_strpos($sentence_with_anchor, '</a>', $link_start);
            $before = mb_substr($sentence_with_anchor, 0, ($link_end + 4));
            $after = mb_substr($sentence_with_anchor, ($link_end + 4));
            // and insert the closing tag just after the link
            $sentence_with_anchor = ($before . $tag . $after);
        }

        // if we have the closing tag inside the link, move it left until it's outside the link
        if(false !== strpos($link, 'close-tag')){
            // get the tag start
            $close_tag = mb_strpos($sentence_with_anchor, '<span class="linkilo_word linkilo_suggestion_tag close-tag');
            // extract the tag
            $tag = mb_substr($sentence_with_anchor, $close_tag, (mb_strpos($sentence_with_anchor, '</span>', $close_tag) + 7) - $close_tag);
            // replace the tag
            $sentence_with_anchor = mb_ereg_replace(preg_quote($tag), '', $sentence_with_anchor);
            // get the points before and after the link opening tag
            $before = mb_substr($sentence_with_anchor, 0, $link_start);
            $after = mb_substr($sentence_with_anchor, $link_start);
            // and insert the cloasing tag just before the link
            $sentence_with_anchor = ($before . $tag . $after);
        }

        return $sentence_with_anchor;
    }

    /**
     * Get Incoming Inner URLs page search keywords
     *
     * @param $post
     * @return array
     */
    public static function getKeywords($post)
    {
        $keywords = array();
        if(!empty($_POST['keywords'])){
            $keywords = explode(";", sanitize_text_field($_POST['keywords']));
        }elseif (!empty($_GET['keywords'])){
            $keywords = explode(";", sanitize_text_field($_GET['keywords']));
        }

        $keywords = array_filter($keywords);

        if(empty($keywords)){
            $words = $post->getTitle() . ' ' . Linkilo_Build_FocusKeyword::get_active_keyword_string($post->id, $post->type);
            $keywords = array(implode(' ', Linkilo_Build_WordFunctions::cleanIgnoreWords(explode(' ', Linkilo_Build_WordFunctions::strtolower($words)))));
        }

        return $keywords;
    }

    /**
     * Search posts with common words in the content and return an array of all found post ids
     *
     * @param $keyword
     * @param $exluded_posts
     * @return array
     */
    public static function getIncomingSuggestedPosts($keyword, $exluded_posts)
    {
        global $wpdb;

        $post_types = implode("','", Linkilo_Build_AdminSettings::getPostTypes());

        $category = '';
        if (!empty($_POST['same_category']) || !empty($_GET['same_category'])) {
            $post = Linkilo_Build_Root::getPost();
            if ($post->type === 'post') {
                if (!empty($_REQUEST['selected_category'])) {
                    $categories = (int)$_REQUEST['selected_category'];
                } else {
                    $taxes = get_object_taxonomies(get_post($post->id));
                    $query_taxes = array();
                    foreach($taxes as $tax){
                        if(get_taxonomy($tax)->hierarchical){
                            $query_taxes[] = $tax;
                        }
                    }
                    $categories = wp_get_object_terms($post->id, $query_taxes, ['fields' => 'ids']);
                    $categories = count($categories) ? implode(',', $categories) : "''";
                }
                $category .= " AND ID in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id in ($categories)) ";
            }
        }

        if (!empty($_POST['same_tag']) || !empty($_GET['same_tag'])) {
            $post = Linkilo_Build_Root::getPost();
            if ($post->type === 'post') {
                if (!empty($_REQUEST['selected_tag'])) {
                    $tags = (int)$_REQUEST['selected_tag'];
                } else {
                    $taxes = get_object_taxonomies(get_post($post->id));
                    $query_taxes = array();
                    foreach($taxes as $tax){
                        if(empty(get_taxonomy($tax)->hierarchical)){
                            $query_taxes[] = $tax;
                        }
                    }
                    $tags = wp_get_object_terms($post->id, $query_taxes, ['fields' => 'ids']);
                    $tags = count($tags) ? implode(',', $tags) : "''";
                }
                $category .= " AND ID in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id in ($tags)) ";
            }
        }

        //get all posts contains words from post title
        $post_content = self::getIncomingPostContent($keyword);

        $include_ids = array();
        $custom_fields = self::getIncomingCustomFields($keyword);
        if (!empty($custom_fields)) {
            $posts = $custom_fields;
            $excluded = explode(',', $exluded_posts);
            foreach ($posts as $key => $included_post) {
                if (in_array($included_post, $excluded)) {
                    unset($posts[$key]);
                }
            }

            if (!empty($posts)) {
                $include_ids = $posts;
            }
        }

        //WPML
        $post = Linkilo_Build_Root::getPost();
        $same_language_posts = array();
        $multi_lang = false;
        if ($post->type == 'post') {
            if (Linkilo_Build_AdminSettings::translation_enabled()) {
                $multi_lang = true;
                $same_language_posts = Linkilo_Build_Feed::getSameLanguagePosts($post->id);
            }
        }

        $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses();

        // create the array of posts 
        $posts = array();

        // if there are ids to process
        if(!empty($same_language_posts) && $multi_lang){
            // chunk the ids to query so we don't ask for too many
            $id_batches = array_chunk($same_language_posts, 2500);
            foreach($id_batches as $batch){
                $include = " AND ID IN (" . implode(', ', $batch) . ") ";
                $batch_ids = $wpdb->get_results("SELECT `ID` FROM {$wpdb->prefix}posts WHERE post_type IN ('{$post_types}') $statuses_query AND ID NOT IN ({$exluded_posts}) $category {$post_content} $include ORDER BY ID DESC");

                if(!empty($batch_ids)){
                    $posts = array_merge($posts, $batch_ids);
                }
            }
        }elseif(empty($multi_lang)){
            $posts = $wpdb->get_results("SELECT `ID` FROM {$wpdb->prefix}posts WHERE post_type IN ('{$post_types}') $statuses_query AND ID NOT IN ({$exluded_posts}) $category {$post_content} ORDER BY ID DESC");
        }

        if(!empty($include_ids)){
            foreach($include_ids as $included){
                $posts[] = (object)array('ID' => $included);
            }
        }

        // get any posts from alternate storage locations
        $posts = self::getPostsFromAlternateLocations($posts, $keyword, $exluded_posts);

        // if there are posts found, remove any duplicate ids and posts hidden by redirects
        if(!empty($posts)){
            $redirected = Linkilo_Build_AdminSettings::getRedirectedPosts(true);
            $post_ids = array();
            foreach($posts as $post){
                if(!isset($redirected[$post->ID])){
                    $post_ids[$post->ID] = $post;
                }
            }

            $posts = array_values($post_ids);
        }

        return $posts;
    }

    public static function getIncomingPostContent($keyword)
    {
        //get unique words from post title
        $words = Linkilo_Build_WordFunctions::getWords($keyword);
        $words = Linkilo_Build_WordFunctions::cleanIgnoreWords(array_unique($words));
        $words = array_filter($words);

        if (empty($words)) {
            return '';
        }

        $post_content = "AND (post_content LIKE '%" . implode("%' OR post_content LIKE '%", $words) . "%')";

        return $post_content;
    }

    /**
     * Gets the posts that store content in locations other than the post_content.
     * Most page builders update post_content as a fallback measure, so we can typically get the content that way.
     * But some items are unique and don't update the post_content.
     **/
    public static function getPostsFromAlternateLocations($posts, $keyword, $exclude_ids){
        global $wpdb;

        // if WP Recipes is active and the user wants to add links to the recipe notes
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Linkilo_Build_AdminSettings::getPostTypes())){

            //get unique words from post title
            $words = Linkilo_Build_WordFunctions::getWords($keyword);
            $words = Linkilo_Build_WordFunctions::cleanIgnoreWords(array_unique($words));
            $words = array_filter($words);

            if(!empty($words)){
                $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->prefix}postmeta m WHERE `meta_key` = 'wprm_notes' AND m.post_id NOT IN ($exclude_ids) AND (meta_value LIKE '%" . implode("%' OR meta_value LIKE '%", $words) . "%')");

                if(!empty($results)){
                    $posts = array_merge($posts, $results);
                }
            }
        }

        return $posts;
    }

    public static function getKeywordsUrl()
    {
        $url = '';
        if (!empty($_POST['keywords'])) {
            $url = '&keywords=' . str_replace("\n", ";", $_POST['keywords']);
        }

        return $url;
    }

    /**
     * Delete phrases with sugeestion point < 3
     *
     * @param $phrases
     * @return array
     */
    public static function deleteWeakPhrases($phrases)
    {
        if (count($phrases) <= 10) {
            return $phrases;
        }

        $three_and_more = 0;
        foreach ($phrases as $key => $phrase) {
            if(!isset($phrase->suggestions[0])){
                unset($phrases[$key]);
                continue;
            }
            if ($phrase->suggestions[0]->post_score >=3) {
                $three_and_more++;
            }
        }

        if ($three_and_more < 10) {
            foreach ($phrases as $key => $phrase) {
                if ($phrase->suggestions[0]->post_score < 3) {
                    if ($three_and_more < 10) {
                        $three_and_more++;
                    } else {
                        unset($phrases[$key]);
                    }
                }
            }
        } else {
            foreach ($phrases as $key => $phrase) {
                if ($phrase->suggestions[0]->post_score < 3) {
                    unset($phrases[$key]);
                }
            }
        }

        return $phrases;
    }

    /**
     * Get post IDs from incoming custom fields
     *
     * @param $keyword
     * @return array
     */
    public static function getIncomingCustomFields($keyword)
    {
        global $wpdb;
        $posts = [];

        if(!class_exists('ACF') || get_option('linkilo_disable_acf', false)){
            return $posts;
        }

        $post_content = str_replace('post_content', 'm.meta_value', self::getIncomingPostContent($keyword));
        $fields = Linkilo_Build_Feed::getAllCustomFields();
        $fields = !empty($fields) ? " AND m.meta_key in ('" . implode("', '", $fields) . "') " : '';
        $statuses_query = Linkilo_Build_DatabaseQuery::postStatuses('p');
        $post_types = implode("','", Linkilo_Build_AdminSettings::getPostTypes());
        $posts_query = $wpdb->get_results("SELECT m.post_id FROM {$wpdb->prefix}postmeta m INNER JOIN {$wpdb->prefix}posts p ON m.post_id = p.ID WHERE p.post_type IN ('{$post_types}') $fields $post_content $statuses_query");
        foreach ($posts_query as $post) {
            $posts[] = $post->post_id;
        }

        return $posts;
    }

    /**
     * Group incoming suggestions by post ID
     *
     * @param $phrases
     * @return array
     */
    public static function getIncomingGroups($phrases)
    {
        $groups = [];
        foreach ($phrases as $phrase) {
            $post_id = $phrase->suggestions[0]->post->id;
            $post_score = $phrase->suggestions[0]->post_score;
            if (empty($groups[$post_id])) {
                $groups[$post_id] = [$phrase];
            } else {
                $groups[$post_id][] = $phrase;
            }
        }

        return $groups;
    }

    /**
     * Remove empty sentences from the list
     *
     * @param $sentences
     */
    public static function removeEmptySentences(&$sentences, $with_links = false)
    {
        $prev_key = null;
        foreach ($sentences as $key => $sentence)
        {
            //Remove text from alt and title attributes
            $pos = 0;
            if ($prev_key && ($pos = strpos($prev_sentence, 'alt="') !== false || $pos = strpos($prev_sentence, 'title="') !== false)) {
                if (isset($sentences[$prev_key]) && strpos($sentences[$prev_key], '"', $pos) == false) {
                    $pos = strpos($sentence, '"');
                    if ($pos !== false) {
                        $sentences[$key] = substr($sentence, $pos + 1);
                    } else {
                        unset ($sentences[$key]);
                    }
                }
            }
            $prev_sentence = $sentence;

            $endings = ['</h1>', '</h2>', '</h3>'];

            if (!$with_links) {
                $endings[] = '</a>';
            }

            if (in_array(trim($sentence), $endings) && $prev_key) {
                unset($sentences[$prev_key]);
            }
            if (empty(trim(strip_tags($sentence)))) {
                unset($sentences[$key]);
            }

            if (substr($sentence, 0, 5) == '<!-- ' && substr($sentence, 0, -4) == ' -->') {
                unset($sentences[$key]);
            }

            if('&nbsp;' === $sentence){
                unset($sentences[$key]);
            }

            $prev_key = $key;
        }
    }

    /**
     * Remove tags from the beginning and the ending of the sentence
     *
     * @param $sentences
     */
    public static function trimTags(&$sentences, $with_links = false)
    {
        foreach ($sentences as $key => $sentence)
        {
            if (strpos($sentence, '<h') !== false || strpos($sentence, '</h') !== false) {
                unset($sentences[$key]);
                continue;
            }

            if (!$with_links && (strpos($sentence, '<a ') !== false || strpos($sentence, '</a>') !== false)) {
                unset($sentences[$key]);
                continue;
            }

            if (substr_count($sentence, '<a ') >  substr_count($sentence, '</a>')) {
                unset($sentences[$key]);
                continue;
            }

            $sentence = trim($sentence);
            while (substr($sentence, 0, 1) == '<' || substr($sentence, 0, 1) == '[') {
                $end_char = substr($sentence, 0, 1) == '<' ? '>' : ']';
                $end = strpos($sentence, $end_char);
                $tag = substr($sentence, 0, $end + 1);
                if (in_array($tag, ['<b>', '<i>', '<u>', '<strong>'])) {
                    break;
                }
                if (substr($tag, 0, 3) == '<a ') {
                    break;
                }
                $sentence = trim(substr($sentence, $end + 1));
            }

            while (substr($sentence, -1) == '>' || substr($sentence, -1) == ']') {
                $start_char = substr($sentence, -1) == '>' ? '<' : '[';
                $start = strrpos($sentence, $start_char);
                $tag = substr($sentence, $start);
                if (in_array($tag, ['</b>', '</i>', '</u>', '</strong>', '</a>'])) {
                    break;
                }
                $sentence = trim(substr($sentence, 0, $start));
            }

            $sentences[$key] = $sentence;
        }
    }

    /**
     * Generate subquery to search posts or products only with same categories
     *
     * @param $post
     * @return string
     */
    public static function getTitleQueryExclude($post)
    {
        global $wpdb;

        $exclude = "";
        if ($post->type == 'post') {
            $redirected = Linkilo_Build_AdminSettings::getRedirectedPosts();  // ignore any posts that are hidden by redirects
            $redirected[] = $post->id;                          // ignore the current post
            $redirected = implode(', ', $redirected);
            $exclude .= " AND ID NOT IN ({$redirected}) ";
        }

        if (!empty($_POST['same_category']) || !empty($_GET['same_category'])) {
            if ($post->type === 'post') {
                if (!empty($_REQUEST['selected_category'])) {
                    $categories = (int)$_REQUEST['selected_category'];
                } else {
                    $taxes = get_object_taxonomies(get_post($post->id));
                    $query_taxes = array();
                    foreach($taxes as $tax){
                        if(get_taxonomy($tax)->hierarchical){
                            $query_taxes[] = $tax;
                        }
                    }
                    $categories = wp_get_object_terms($post->id, $query_taxes, ['fields' => 'ids']);
                    $categories = count($categories) ? implode(',', $categories) : "''";
                }
                $exclude .= " AND ID in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id in ($categories))";
            }
        }

        if (!empty($_REQUEST['same_tag'])) {
            if ($post->type === 'post') {
                if (!empty($_REQUEST['selected_tag'])) {
                    $tags = (int)$_REQUEST['selected_tag'];
                } else {
                    $taxes = get_object_taxonomies(get_post($post->id));
                    $query_taxes = array();
                    foreach($taxes as $tax){
                        if(empty(get_taxonomy($tax)->hierarchical)){
                            $query_taxes[] = $tax;
                        }
                    }
                    $tags = wp_get_object_terms($post->id, $query_taxes, ['fields' => 'ids']);
                    $tags = count($tags) ? implode(',', $tags) : "''";
                }
                $exclude .= " AND ID in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id in ($tags))";
            }
        }

        return $exclude;
    }

    /**
     * Compresses and base64's the given data so it can be saved in the db.
     * 
     * @param string $data The data to be compressed
     * @return null|string Returns a string of compressed and base64 encoded data 
     **/
    public static function compress($data = false){
        return base64_encode(gzdeflate(serialize($data)));
    }

    /**
     * Decompresses stored data that was compressed with compress.
     * 
     * @param string $data The data to be decompressed
     * @return mixed $data 
     **/
    public static function decompress($data){
        if(empty($data) || !is_string($data)){
            return $data;
        }

        return unserialize(gzinflate(base64_decode($data)));
    }

    /**
     * Gets the phrases from the current post for use in outgoing linking suggestions.
     * Caches the phrase data so subsequent requests are faster
     * 
     * @param $post The post object we're getting the phrases from.
     * @param int $process_key The ajax processing key for the current process.
     * @return array $phrases The phrases from the given post
     **/
    public static function getOutboundPhrases($post, $process_key){
        // try getting cached phrase data
        $phrases = get_transient('linkilo_processed_phrases_' . $process_key);

        // if there aren't any phrases, process them now
        if(empty($phrases)){
            $phrases = self::getPhrases($post->getContent());

            //divide text to phrases
            foreach ($phrases as $key_phrase => &$phrase) {
                // replace any punctuation in the text and lower the string
                $text = Linkilo_Build_WordFunctions::strtolower(str_replace(['.', '!', '?', '\''], '', $phrase->text));

                //get array of unique sentence words cleared from ignore phrases
                if (!empty($_REQUEST['keywords'])) {
                    $sentence = trim(preg_replace('/\s+/', ' ', $text));
                    $words_uniq = Linkilo_Build_WordFunctions::getWords($sentence);
                } else {
                    $words_uniq = Linkilo_Build_WordFunctions::cleanFromIgnorePhrases($text);
                }

                // remove words less than 3 letters long and stem the words
                foreach($words_uniq as $key => $word){
                    if(strlen($word) < 3){
                        unset($words_uniq[$key]);
                        continue;
                    }

                    $words_uniq[$key] = Linkilo_Build_Stemmer::Stem($word);
                }

                $phrase->words_uniq = $words_uniq;
            }

            $save_phrases = self::compress($phrases);
            set_transient('linkilo_processed_phrases_' . $process_key, $save_phrases, MINUTE_IN_SECONDS * 15);
            reset($phrases);
            unset($save_phrases);
        }else{
            $phrases = self::decompress($phrases);
        }

        return $phrases;
    }

    /**
     * Gets the categories that are assigned to the current post.
     * If we're doing an outgoing scan, it caches the cat ids so they can be pulled up without a query
     **/
    public static function getSameCategories($post, $process_key = 0, $is_outgoing = false){
        global $wpdb;

        if($is_outgoing){
            $cats = get_transient('linkilo_post_same_categories_' . $process_key);
            if(empty($cats) && !is_array($cats)){
                $cats = $wpdb->get_results("SELECT object_id FROM {$wpdb->prefix}term_relationships where term_taxonomy_id in (SELECT r.term_taxonomy_id FROM {$wpdb->prefix}term_relationships r inner join {$wpdb->prefix}term_taxonomy t on t.term_taxonomy_id = r.term_taxonomy_id where r.object_id = {$post->id} and t.taxonomy = 'category')");
            
                if(empty($cats)){
                    $cats = array();
                }

                set_transient('linkilo_post_same_categories_' . $process_key, $cats, MINUTE_IN_SECONDS * 15);
            }
            
        }else{
            $cats = $wpdb->get_results("SELECT object_id FROM {$wpdb->prefix}term_relationships where term_taxonomy_id in (SELECT r.term_taxonomy_id FROM {$wpdb->prefix}term_relationships r inner join {$wpdb->prefix}term_taxonomy t on t.term_taxonomy_id = r.term_taxonomy_id where r.object_id = {$post->id} and t.taxonomy = 'category')");
        }

        return $cats;
    }

    /**
     * Checks to see if the current sentence contains any of the post's keywords.
     * Performs a strict check and a loose keyword check.
     * The strict check examines the sentence to see if there's an exact match of the keywords in the sentence.
     * The loose checks sees if the sentence contains any matching words from the keyword in any order.
     * 
     * @param string $sentence The sentence to examine
     * @param array $focus_keywords The processed keywords to check for
     * 
     * @return bool True if the sentence contains any of the keywords, False if the sentence doesn't contain any keywords.
     **/
    public static function checkSentenceForKeywords($sentence = '', $focus_keywords = array(), $unique_keywords = array()){
        $stemmed_sentence = Linkilo_Build_WordFunctions::getStemmedSentence($sentence);
        $loose_match_count = false; // get_option('linkilo_loose_keyword_match_count', 0);

        // if we're supposed to check for loose matching of the keywords
        if(!empty($loose_match_count) && !empty($unique_keywords)){
            // find out how many times the keywords show up in the sentence
            $words = array_flip(explode(' ', $stemmed_sentence));
            $unique_keywords = array_flip($unique_keywords);

            $matches = array_intersect_key($unique_keywords, $words);

            // if the count is more than what the user has set
            if(count($matches) > $loose_match_count){
                // report that the sentence contains the keywords
                return true;
            }
        }

        foreach($focus_keywords as $keyword){
            // skip the keyword if it's only 2 chars long
            if(3 > strlen($keyword->keywords)){
                continue;
            }

            // if the keyword is in the phrase, return true
            if(false !== strpos($stemmed_sentence, $keyword->stemmed)){
                return true;
            }

            // if the keyword is a post content keyword, 
            // check if the there's at least 2 consecutively matching words between the sentence and the keyword
            if($keyword->keyword_type === 'post-keyword'){
                $k_words = explode(' ', $keyword->keywords);
                $s_words = explode(' ', $stemmed_sentence);

                foreach($k_words as $key => $word){
                    // see if the current word is in the stemmed sentence
                    $pos = array_search($word, $s_words, true);

                    // if it is, see if the next word is the same for both strings
                    if( false !== $pos && 
                        isset($s_words[$pos + 1]) &&
                        isset($k_words[$key + 1]) &&
                        ($s_words[$pos + 1] === $k_words[$key + 1])
                    ){
                        // if it is, return true
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Clears the cached data when the suggestion processing is complete
     * 
     * @param int $processing_key The id of the suggestion processing run.
     **/
    public static function clearSuggestionProcessingCache($processing_key = 0, $post_id = 0){
        // clear the suggestions
        delete_transient('linkilo_post_suggestions_' . $processing_key);
        // clear the incoming suggestion ids
        delete_transient('linkilo_incoming_suggested_post_ids_' . $processing_key);
        // clear the keyword cache
        delete_transient('linkilo_post_suggestions_keywords_' . $processing_key);
        // clear the unique keyword cache
        delete_transient('linkilo_post_suggestions_unique_keywords_' . $processing_key);
        // clear any cached incoming links cache
        delete_transient('linkilo_stored_post_internal_incoming_links_' . $post_id);
        // clear the processed phrase cache
        delete_transient('linkilo_processed_phrases_' . $processing_key);
        // clear the post link cache
        delete_transient('linkilo_external_post_link_index_' . $processing_key);
    }
}
