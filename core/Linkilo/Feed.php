<?php

/**
 * Work with post
 */
class Linkilo_Build_Feed
{
    public static $advanced_custom_fields_list = null;

    /**
     * Register services
     */
    public function register()
    {
        add_filter('wp_insert_post_data', [$this, 'addLinksToContent'], 9999, 2);
        add_action('wp_ajax_linkilo_editor_reload', [$this, 'editorReload']);
        add_action('wp_ajax_linkilo_is_outgoing_urls_added', [$this, 'isOutboundLinksAdded']);
        add_action('wp_ajax_linkilo_is_incoming_urls_added', [$this, 'isIncomingLinksAdded']);
        add_action('wp_ajax_linkilo_ignore_stray_feed', [$this, 'ajaxIgnoreOrphanedPost']);
        add_action('draft_to_published', [$this, 'updateStatMark']);
        add_action('save_post', [$this, 'updateStatMark']);
        add_action('before_delete_post', [$this, 'deleteReferences']);
        add_action('save_post', [$this, 'addLinkToAdvancedCustomFields'], 9999, 1);
        add_filter('wp_link_query_args', array(__CLASS__, 'filter_custom_link_post_types'), 10, 1);
        add_filter('wp_link_query', array(__CLASS__, 'custom_link_category_search'), 10, 2);
        add_filter('et_fb_ajax_save_verification_result', array(__CLASS__, 'verify_divi_save_status'));
    }

    /**
     * Add links to content before post update
     */
    public static function addLinksToContent($data, $post)
    {   
        $meta = get_post_meta($post['ID'], 'linkilo_links', true);
 
        if (is_null($data)) {
            $data = get_post($post['ID'], ARRAY_A);
            $data['post_content'] = addslashes($data['post_content']);
            $data_null = true;
        }
        
        if (!empty($meta)) {
            //update post text
            foreach ($meta as $link) {
                $changed_sentence = self::getSentenceWithAnchor($link);

                $link['sentence'] = Linkilo_Build_WordFunctions::removeQuotes($link['sentence']);


                if (strpos($data['post_content'], $link['sentence']) === false) {
                    $sentence = addslashes($link['sentence']);
                } else {
                    $sentence = $link['sentence'];
                }

                Linkilo_Build_Editor_Kadence::insertLink($data['post_content'], $sentence, $changed_sentence);

                if (strpos($data['post_content'], $sentence) !== false) {
                    $changed_sentence = self::changeByACF($data['post_content'], $link['sentence'], $changed_sentence);
                    self::insertLink($data['post_content'], $sentence, $changed_sentence);
                }

                    // if the Enfold Advanced editor is active
                if( isset($post['aviaLayoutBuilder_active']) && 'active' === $post['aviaLayoutBuilder_active'] && 
                    isset($post['_aviaLayoutBuilderCleanData']) && !empty($post['_aviaLayoutBuilderCleanData']))
                {
                    // add links to the submitted form content
                    if (strpos($_POST['_aviaLayoutBuilderCleanData'], $sentence) !== false) {
                        self::insertLink($_POST['_aviaLayoutBuilderCleanData'], $sentence, $changed_sentence);
                    }
                }
            }

            self::editors('addLinks', [$meta, $post['ID'], &$data['post_content']]);

            if (!empty($data_null)) {
                Linkilo_Build_WordFunctions::addSlashesToNewLine($data['post_content']);

                $update = [
                    'ID' => $post['ID'],
                    'post_content' => $data['post_content']
                ];

                wp_update_post($update);

                // remove any ghost links
                $new_post = new Linkilo_Build_Model_Feed($post['ID']);
                Linkilo_Build_RelateUrlKeyword::deleteGhostLinks($new_post);
            }

            if (LINKILO_IS_LINKS_TABLE_EXISTS){
                Linkilo_Build_UrlRecord::update_post_in_link_table($post['ID']);
            }
        }
        //return updated post data
        return $data;
    }

    /**
     * Check if it need to force page reload
     */
    function editorReload(){
        if (!empty($_POST['post_id'])) {
            $meta = get_post_meta((int)$_POST['post_id'], 'linkilo_gutenberg_restart', true);
            if (!empty($meta)) {
                delete_post_meta((int)$_POST['post_id'], 'linkilo_gutenberg_restart');
                echo 'reload';
            }
        }

        wp_die();
    }

    /**
     * Check if outgoing links were added to show dialog box
     */
    function isOutboundLinksAdded(){
        if (!empty($_POST['id']) && !empty($_POST['type'])) {
            if ($_POST['type'] == 'term') {
                $meta = get_term_meta((int)$_POST['id'], 'linkilo_is_outgoing_urls_added', true);
            } else {
                $meta = get_post_meta((int)$_POST['id'], 'linkilo_is_outgoing_urls_added', true);
            }
            if (!empty($meta)) {
                if ($_POST['type'] == 'term') {
                    delete_term_meta((int)$_POST['id'], 'linkilo_is_outgoing_urls_added');
                } else {
                    delete_post_meta((int)$_POST['id'], 'linkilo_is_outgoing_urls_added');
                }
                echo 'success';
            }
        }
        wp_die();
    }

    /**
     * Check if incoming links were added to show dialog box
     */
    function isIncomingLinksAdded(){
        if (!empty($_POST['id']) && !empty($_POST['type'])) {
            if ($_POST['type'] == 'term') {
                $meta = get_term_meta((int)$_POST['id'], 'linkilo_is_incoming_urls_added', true);
            } else {
                $meta = get_post_meta((int)$_POST['id'], 'linkilo_is_incoming_urls_added', true);
            }
            if (!empty($meta)) {
                if ($_POST['type'] == 'term') {
                    delete_term_meta((int)$_POST['id'], 'linkilo_is_incoming_urls_added');
                } else {
                    delete_post_meta((int)$_POST['id'], 'linkilo_is_incoming_urls_added');
                }
                echo 'success';
            }
        }

        wp_die();
    }

    /**
     * Ignores the selected orphaned post on the orphaned post view.
     **/
    function ajaxIgnoreOrphanedPost(){
        $post_id = (int)$_POST['post_id'];
        if(empty($post_id)){
            wp_send_json(array('error' => array('title' => __('Post id empty', 'linkilo'),'text' => __('The post id was missing from the ignore orphaned post request.', 'linkilo'))));
        }

        if(empty(wp_verify_nonce($_POST['nonce'], 'ignore-orphaned-post-' . $post_id))){
            wp_send_json(array('error' => array('title' => __('Expired data', 'linkilo'),'text' => __('Some of the data was too old to process, please reload the page and try again.', 'linkilo'))));
        }

        // get the post
        $post = new Linkilo_Build_Model_Feed($post_id, sanitize_text_field($_POST['type']));

        // get the ignored orphaned posts
        $ignored = Linkilo_Build_AdminSettings::getIgnoreKeywordsPosts();

        // if the post is ignored, send back that the post is on the list
        if(in_array($post->type . '_' . $post_id, $ignored, true)){
            wp_send_json(array('success' => true));
        }

        $ignored_posts = get_option('linkilo_ignore_stray_feeds', '');

        $ignored_posts .= "\n" . $post->getLinks()->view;

        update_option('linkilo_ignore_stray_feeds', $ignored_posts);

        wp_send_json(array('success' => true));
    }

    /**
     * Filters the post types that the custom link search box will look for so the user is only shown selected post types
     **/
    public static function filter_custom_link_post_types($query_args){
        if(!empty($_POST) && isset($_POST['linkilo_custom_link_search'])){
            $selected_post_types = Linkilo_Build_AdminSettings::getPostTypes();
            if(!empty($selected_post_types)){
                $query_args['post_type'] = $selected_post_types;
            }
        }
        return $query_args;
    }

    /**
     * Queries for terms when the user does a custom link search for outgoing suggestions.
     * The existing search only does posts, so we have to do the terms separately
     **/
    public static function custom_link_category_search($queried_items = array()){
        if(!empty($_POST) && isset($_POST['linkilo_custom_link_search'])){

            $selected_terms = get_option('linkilo_2_term_types', array());

            if(empty($selected_terms)){
                return $queried_items;
            }

            $args = array('taxonomy' => $selected_terms, 'search' => $_POST['search'], 'number' => 20);

            $term_query = new WP_Term_Query($args);
            $terms = $term_query->get_terms();

            if(empty($terms)){
                return $queried_items;
            }

            foreach($terms as $term){
                $queried_items[] = array(
                    'ID' => $term->term_id,
                    'title' => $term->name,
                    'permalink' => get_term_link($term->term_id),
                    'info' => ucfirst($term->taxonomy),
                );

            }
        }
        
        return $queried_items;
    }

    /**
     * Insert links into sentence
     *
     * @param $sentence
     * @param $anchor
     * @param $url
     * @param $to_post_id
     * @return string
     */
    public static function getSentenceWithAnchor($link) {
        if (!empty($link['custom_sentence'])) {
            $link['custom_sentence'] = mb_ereg_replace(preg_quote(',</a>'), '</a>,', $link['custom_sentence']);
            return $link['custom_sentence'];
        }

        //get URL
        preg_match('/<a href="([^\"]+)"[^>]+>(.*)<\/a>/i', $link['sentence_with_anchor'], $matches);

        if (empty($matches[1])) {
            return $link['sentence'];
        }

        // update the sentence's tags
        $link['sentence'] = self::update_sentence_tags($link['sentence'], $link['sentence_with_anchor']);

        $url = $matches[1];

        //get anchor from source sentence
        $words = [];
        $word_start = false;
        $word_end = 0;
        preg_match_all('/<span[^>]+>([^<]+)<\/span>/i', $matches[2], $matches);
        if (count($matches[1])) {
            foreach ($matches[1] as $word) {
                if ($word_start === false) {
                    $word_start = stripos($link['sentence'], $word . ' ');
                    if(false === $word_start){
                        $word_start = stripos($link['sentence'], $word);
                    }
                    $word_end = $word_start + strlen($word);
                } else {
                    $word_end = stripos($link['sentence'], $word, $word_end) + strlen($word);
                }

                $words[] = $word;
            }
        }

        //get start position by nearest whitespace
        $start = 0;
        $i = 0;
        while(strpos($link['sentence'], ' ', $start+1) < $word_start && $i < 100) {
            $start = strpos($link['sentence'], ' ', $start+1);
            $next_whitespace = strpos($link['sentence'], ' ', $start+1);
            $tag = strpos($link['sentence'], '>', $start +1);
            if ($tag && $tag < $next_whitespace) {
                $start = $tag;
            }
            $tag = strpos($link['sentence'], '(', $start +1);
            if ($tag && $tag < $next_whitespace) {
                $start = $tag;
            }
            $i++;

            // exit the loop if there's no further whitespace
            if(empty($next_whitespace)){
                break;
            }
        }
        if ($start) {
            $start++;
        }

        //get end position by nearest whitespace
        $end = 0;
        $prev_end = 0;
        while($end < $word_end && $end !== false) {
            $prev_end = $end;
            $end = strpos($link['sentence'], ' ', $end + 1);
            $tag = strpos($link['sentence'], ')', $prev_end +1);
            if ($tag && $tag < $end) {
                $end = $tag;
            }
        }

        if (substr($link['sentence'], $end-1, 1) == ',') {
            $end -= 1;
        }

        if ($end === false) {
            $end = strlen($link['sentence']);
        }

        $anchor = substr($link['sentence'], $start, $end - $start);

        $external = (isset($link['post_origin']) && $link['post_origin'] === 'external') ? true: false;
        $open_new_tab = (int)get_option('linkilo_2_links_open_new_tab', 0);
        $open_external_new_tab = false;
        if($external){
            $open_external_new_tab = get_option('linkilo_external_links_open_new_tab', null);
        }

        //add target blank if needed
        $blank = '';
        $rel = '';
        if (($open_new_tab == 1 && empty($external)) || 
            ($external && $open_external_new_tab) ||
            ($open_new_tab == 1 && $open_external_new_tab === null)
        ) {
            $noreferrer = !empty(get_option('linkilo_add_noreferrer', false)) ? ' noreferrer': '';
        $blank = 'target="_blank"';
        $rel = 'rel="noopener' . $noreferrer;
    }

        // if the user has set external links to be nofollow, this is an external link, and this isn't an interlinked site
    if(
        !empty(get_option('linkilo_add_nofollow', false)) && 
        !Linkilo_Build_PostUrl::isInternal($url) && 
        !empty(wp_parse_url($url, PHP_URL_HOST)) &&
        !in_array(wp_parse_url($url, PHP_URL_HOST), Linkilo_Build_ConnectMultipleSite::get_linked_site_domains(), true))
    {
        if(empty($rel)){
            $rel = 'rel="nofollow';
        }else{
            $rel .= ' nofollow';
        }
    }

    if(!empty($rel)){
        $rel .= '"';
    }

        //add slashes to the anchor if it doesn't found in the sentence
    if (stripos(addslashes($link['sentence']), $anchor) === false) {
//            $anchor = addslashes($anchor);
    }

    $anchor2 = str_replace('$', '\\$', $anchor);

        // get any classes the user wants to add
    $classes = apply_filters('linkilo_link_classes', '', $external);

        // if the user returned an array, stringify it
    if(is_array($classes)){
        $classes = implode(' ', $classes);
    }

    $classes = (!empty($classes)) ? 'class="' . sanitize_text_field($classes) . '"': '';

    $title = '';
    if(!empty(get_option('linkilo_add_destination_title', false))){
        $dest_post = self::getPostByLink($url);

        if(!empty($dest_post)){
            if($dest_post->type === 'post'){
                $title = 'title="'. get_the_title($dest_post->id) .'"';
            }else{
                $title = 'title="'. get_term($dest_post->id)->name .'"';
            }
        }
    }

        // todo build into a separate attr function with the other checks
    $attrs = '';
    if(!empty($title)){
        $attrs .= ' ' . $title;
    }
    if(!empty($blank)){
        $attrs .= ' ' . $blank;
    }
    if(!empty($rel)){
        $attrs .= ' ' . $rel;
    }
    if(!empty($classes)){
        $attrs .= ' ' . $classes;
    }

        //add link to sentence
    $sentence = preg_replace('/'.preg_quote($anchor, '/').'/i', '<a href="'.$url.'"' . $attrs . '>'.$anchor2.'</a>', $link['sentence'], 1);

    $sentence = str_replace('$', '\\$', $sentence);

        // format the tags inside the sentence to make sure there's no half-in half-out tags
    $sentence = self::format_sentence_tags($sentence);

    return $sentence;
}

    /**
     * Updates the html style tags in the sentence with the results from sentence with anchor.
     **/
    public static function update_sentence_tags($sentence, $sentence_with_anchor){

        // find all the encoded style tags
        preg_match_all('/<span[^><]*?class=["\'][^"\']*?linkilo_suggestion_tag[^"\']*?["\'][^>]*?>([^<]*?)<\/span>/', $sentence_with_anchor, $matches);

        if(empty($matches)){
            return $sentence;
        }

        foreach($matches[0] as $key => $match){
            $decoded = base64_decode($matches[1][$key]);
            if(preg_match('/' . preg_quote($match, '/') . '\s*/', $sentence_with_anchor)){
                $sentence_with_anchor = preg_replace('/' . preg_quote($match, '/') . '\s*/', $decoded, $sentence_with_anchor);
            }else{
                $sentence_with_anchor = str_replace($match, $decoded, $sentence_with_anchor);
            }
        }

        // find all the non word tags
        preg_match_all('/<span[^><]*?class=["\'][^"\']*?linkilo-non-word[^"\']*?["\'][^>]*?>([^<]*?)<\/span>/', $sentence_with_anchor, $matches);

        // if there are non word tags, remove them so they don't throw off the formatting
        if(!empty($matches)){
            foreach($matches[0] as $key => $match){
                $sentence_with_anchor = str_replace($match, $matches[1][$key], $sentence_with_anchor);
            }
        }

        $new_sentence = strip_tags($sentence_with_anchor, '<b><i><u><strong><em>');

        // remove any tags that are opening and closing without content
        $new_sentence = str_replace(array('<b></b>', '<i></i>', '<u></u>', '<strong></strong>', '<em></em>'), '', $new_sentence);
        $new_sentence = str_replace(array('<b> </b>', '<i> </i>', '<u> </u>', '<strong> </strong>', '<em> </em>'), '', $new_sentence);

        // if the sentences are the same after removing all tags
        if(trim(strip_tags($sentence)) === trim(strip_tags($sentence_with_anchor)) || trim(strip_tags($sentence)) === str_replace('  ', ' ', trim(strip_tags($sentence_with_anchor))) ){
            // update the sentence with the new tagged version
            $sentence = trim($new_sentence);
        }

        return $sentence;
    }

    /**
     * Makes sure there aren't any tags that are half-in/half-out of the anchor tag.
     * Moves any offending tags along the same lines as the JS mover:
     ** If just the closing tag is inside the anchor, move it left until it's outside the anchor.
     ** If just the opening tag is inside the anchor, move it right until it's outside the anchor.
     ** If opening and closing tags are next to each other, remove them.
     **/
    public static function format_sentence_tags($sentence){

        // return the sentence if there's no tags inside the anchor
        if(empty(preg_match('/<a.*?>.*?(<[A-z\/]*?>)<\/a>/', $sentence))){
            return $sentence;
        }

        // get the anchor tag and it's position data
        $link_start = mb_strpos($sentence, '<a href="');
        $link_end = mb_strpos($sentence, '</a>', $link_start);
        $link_length = ($link_end + 4 - $link_start);
        $link = mb_substr($sentence, $link_start, $link_length);
        $link_copy = $link;

        $tags_before_anchor = array();
        $tags_after_anchor = array();

        // check the anchor to see what tags it contains
        $tags_to_check = array('(<b>|<\/b>)', '(<i>|<\/i>)', '(<u>|<\/u>)', '(<strong>|<\/strong>)', '(<em>|<\/em>)');
        foreach($tags_to_check as $tag){
            // if it only contains one tag
            if(preg_match_all('/' . $tag . '/', $link, $matches, PREG_OFFSET_CAPTURE) === 1){
                // extract the tag
                $pulled_tag = $matches[0][0][0];
                // get the tag's position
                $position = $matches[0][0][1];
                // replace the tag in the copied link
                $link_copy = mb_ereg_replace(preg_quote($pulled_tag), '', $link_copy);
                
                // if the tag is a closing tag
                if(strpos($pulled_tag, '/')){
                    // put it on the list of tags that come before the anchor
                    $tags_before_anchor[$position] = $pulled_tag;
                }else{
                    // if it's an opening tag, put it on the list of tags that come after the anchor
                    $tags_after_anchor[$position] = $pulled_tag;
                }
            }
        }

        // if there are tags that should be moved in front of the anchor
        if(!empty($tags_before_anchor)){
            // sort them to make sure we don't make a mess
            ksort($tags_before_anchor);
            // and insert them before the anchor
            $link_copy = implode('', $tags_before_anchor) . $link_copy;
        }

        // if there are tags that should be moved past the end of the anchor
        if(!empty($tags_after_anchor)){
            // sort them to make sure we don't make a mess
            ksort($tags_after_anchor);
            // and add them after the anchor
            $link_copy = $link_copy . implode('', $tags_after_anchor);
        }

        // replace the old link with the new link
        $sentence = mb_ereg_replace(preg_quote($link), $link_copy, $sentence);

        // remove any double tags // it is possible that a user will have something like <strong><em><u></u></em></strong> that should be removed, but we'll cross that bridge when we get there
        $sentence = str_replace(array('<b></b>', '<i></i>', '<u></u>', '<strong></strong>', '<em></em>'), '', $sentence);
        $sentence = str_replace(array('<b> </b>', '<i> </i>', '<u> </u>', '<strong> </strong>', '<em> </em>'), ' ', $sentence);

        return $sentence;
    }

    /**
     * Get post content
     *
     * @param $post_id integer
     * @return string
     */
    public static function getPostContent($post_id)
    {
        $post = get_post($post_id);

        return !empty($post->post_content) ? $post->post_content : '';
    }

    /**
     * Set mark for post to update report
     *
     * @param $post_id
     */
    public static function updateStatMark($post_id)
    {      
        // don't save links for revisions
        if(wp_is_post_revision($post_id)){
            return;
        }

        // clear the meta flag
        update_post_meta($post_id, 'linkilo_sync_report3', 0);

        if (get_option('linkilo_option_update_reporting_data_on_save', false)) {
            Linkilo_Build_UrlRecord::fillMeta();
            if(LINKILO_IS_LINKS_TABLE_EXISTS){
                Linkilo_Build_UrlRecord::remove_post_from_link_table(new Linkilo_Build_Model_Feed($post_id));
                Linkilo_Build_UrlRecord::fillLinkiloLinkTable();
            }
            Linkilo_Build_UrlRecord::refreshAllStat();
        }else{
            if(LINKILO_IS_LINKS_TABLE_EXISTS){
                $post = new Linkilo_Build_Model_Feed($post_id);
                // if the current post has the Thrive builder active, load the Thrive content
                $thrive_active = get_post_meta($post->id, 'tcb_editor_enabled', true);
                if(!empty($thrive_active)){
                    $thrive_content = get_post_meta($post->id, 'tve_updated_post', true);
                    if($thrive_content){
                        $post->setContent($thrive_content);
                    }
                }
                // update the links stored in the link table
                Linkilo_Build_UrlRecord::update_post_in_link_table($post);
                // update the meta data for the post
                Linkilo_Build_UrlRecord::statUpdate($post, true);
                // and update the link counts for the posts that this one links to
                Linkilo_Build_UrlRecord::updateReportInternallyLinkedPosts($post);
            }
        }
        
        if (empty(get_option('linkilo_post_procession'))) {
            $post = new Linkilo_Build_Model_Feed($post_id);
            Linkilo_Build_RelateUrlKeyword::addKeywordsToPost($post);
            Linkilo_Build_UrlReplace::replacePostURLs($post);
        }
    }

    /**
     * Delete all post meta on post delete
     *
     * @param $post_id
     */
    public static function deleteReferences($post_id)
    {
        foreach (array_merge(Linkilo_Build_UrlRecord::$meta_keys, ['linkilo_sync_report3', 'linkilo_sync_report2_time']) as $key) {
            delete_post_meta($post_id, $key);
        }
        if(LINKILO_IS_LINKS_TABLE_EXISTS){
            // remove the current post from the links table and the links that point to it
            Linkilo_Build_UrlRecord::remove_post_from_link_table(new Linkilo_Build_Model_Feed($post_id), true);
        }
    }

    /**
     * Get linked post Ids for current post
     *
     * @param $post
     * @return string
     */
    public static function getLinkedPostIDs($post)
    {
        $linked_post_ids = [$post->id];
        $links_incoming = Linkilo_Build_UrlRecord::getInternalIncomingLinks($post);
        foreach ($links_incoming as $link) {
            if (!empty($link->post->id)) {
                $linked_post_ids[] = $link->post->id;
            }
        }

        return implode(',', $linked_post_ids);
    }

    /**
     * Get all Advanced Custom Fields names
     *
     * @return array
     */
    public static function getAdvancedCustomFieldsList($post_id)
    {
        global $wpdb;

        $fields = [];

        if(!class_exists('ACF') || get_option('linkilo_disable_acf', false)){
            return $fields;
        }

        // get any ACF fields the user has ignored
        $ignored_fields = Linkilo_Build_AdminSettings::getIgnoredACFFields();
        
        $fields_query = $wpdb->get_results("SELECT SUBSTR(meta_key, 2) as `name` FROM {$wpdb->postmeta} WHERE post_id = $post_id AND meta_value IN (SELECT DISTINCT post_name FROM {$wpdb->posts} WHERE post_name LIKE 'field_%') AND SUBSTR(meta_key, 2) != ''");
        foreach ($fields_query as $field) {
            $name = trim($field->name);
            if(in_array($name, $ignored_fields, true)){
                continue;
            }

            if ($name) {
                $fields[] = $field->name;
            }
        }

        return $fields;
    }

    public static function getAllCustomFields()
    {
        global $wpdb;

        if(!class_exists('ACF') || get_option('linkilo_disable_acf', false)){
            return array();
        }

        if (self::$advanced_custom_fields_list === null) {
            $fields = [];
            $ignored_fields = Linkilo_Build_AdminSettings::getIgnoredACFFields();
            $result = $wpdb->get_results("SELECT DISTINCT post_name FROM {$wpdb->posts} WHERE post_name LIKE 'field_%'");
            $post_names = [];
            foreach ($result as $r) {
                $post_names[] = $r->post_name;
            }

            if (!empty($post_names)) {
                $fields_query = $wpdb->get_results("SELECT DISTINCT meta_key as `key` FROM {$wpdb->postmeta} WHERE meta_value IN ('" . implode("', '", $post_names) . "')");
                foreach ($fields_query as $field) {
                    $key = substr($field->key, 1);
                    if (trim($key) && !in_array($key, $ignored_fields, true)) {
                        $fields[] = $key;
                    }
                }
            }

            self::$advanced_custom_fields_list = $fields;
        }

        return self::$advanced_custom_fields_list;
    }

    /**
     * Add link to the content in advanced custom fields
     *
     * @param $link
     * @param $post
     */
    public static function addLinkToAdvancedCustomFields($post_id)
    {
        // don't save the data if this is the result of using wp_update_post // there's no form submission, so $_POST will be empty
        if(empty($_POST)){
            return;
        }

        $meta = get_post_meta($post_id, 'linkilo_links', true);

        if (!empty($meta)) {
            foreach ($meta as $link) {
                $fields = self::getAdvancedCustomFieldsList($post_id);
                if (!empty($fields)) {
                    foreach ($fields as $field) {
                        if ($content = get_post_meta($post_id, $field, true)) {
                            if (strpos($content, $link['sentence']) !== false) {
                                $changed_sentence = self::getSentenceWithAnchor($link);
                                $content = preg_replace('/' . preg_quote($link['sentence'], '/') . '/i', $changed_sentence, $content, 1);
                                update_post_meta($post_id, $field, $content);
                            }
                        }
                    }
                }
            }
            $fake_content = false;
            Linkilo_Build_Editor_Oxygen::addLinks($meta, $post_id, $fake_content);
            //remove DB record with links
            delete_post_meta($post_id, 'linkilo_links');
        }
    }

    /**
     * Get all posts with the same language
     *
     * @param $post_id
     * @return array
     */
    public static function getSameLanguagePosts($post_id)
    {
        global $wpdb;
        $ids = [];
        $posts = [];

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}icl_languages'");
        if ($table == $wpdb->prefix . 'icl_languages') {
            //WPML
            $post_types = self::getSelectedLanguagePostTypes();
            $language = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $post_id AND `element_type` IN ({$post_types}) ");
            if (!empty($language)) {
                $posts = $wpdb->get_results("SELECT element_id as id FROM {$wpdb->prefix}icl_translations WHERE element_id != $post_id AND language_code = '$language' AND `element_type` IN ({$post_types}) ");
            }
        } else {
            //Polylang
            $taxonomy_id = $wpdb->get_var("SELECT t.term_taxonomy_id FROM {$wpdb->term_taxonomy} t INNER JOIN {$wpdb->term_relationships} r ON t.term_taxonomy_id = r.term_taxonomy_id WHERE t.taxonomy = 'language' AND r.object_id = " . $post_id);
            if (!empty($taxonomy_id)) {
                $posts = $wpdb->get_results("SELECT object_id as id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = $taxonomy_id AND object_id != $post_id");
            }
        }

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $ids[] = $post->id;
            }
        }

        return $ids;
    }

    /**
     * Gets the selected post types formatted for WPML
     **/
    public static function getSelectedLanguagePostTypes(){
        $post_types = implode("', 'post_", Linkilo_Build_AdminSettings::getPostTypes());

        if(!empty($post_types)){
            $post_types = "'post_" . $post_types . "'";
        }

        return $post_types;
    }

    public static function getAnchors($post)
    {
        preg_match_all('|<a [^>]+>([^<]+)</a>|i', $post->getContent(), $matches);

        if (!empty($matches[1])) {
            return $matches[1];
        }

        return [];
    }

    /**
     * Get URLs from post content
     *
     * @param $post
     * @return array|mixed
     */
    public static function getUrls($post)
    {
        preg_match_all('#<a\s.*?(?:href=[\'"](.*?)[\'"]).*?>#is', $post->getContent(), $matches);

        if (!empty($matches[1])) {
            return $matches[1];
        }

        return [];
    }

    public static function getSentencesWithUrls($post)
    {
        $data = [];
        preg_match_all('#(\!|\?|\.|^|)[^.!?\n]*<a\s.*?(?:href=[\'"](.*?)[\'"]).*?>.*?<\/a>((?!<a)[^.!?\n])*#is', $post->getContent(),$matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            if (!empty($matches[0][$i]) && !empty($matches[2][$i])) {
                $sentence = $matches[0][$i];
                if (in_array(substr($sentence, 0, 1), ['.', '!', '?'])) {
                    $sentence = substr($sentence, 1);
                }
                $data[] = [
                    'sentence' => trim(strip_tags($sentence)),
                    'url' => $matches[2][$i]
                ];
            }
        }

        return $data;
    }

    /**
     * Change sentence if it located inside embedded ACF blocks
     *
     * @param $content
     * @param $sentence
     * @param $changed_sentence
     * @return string
     */
    public static function changeByACF($content, $sentence, $changed_sentence){
        //find all blocks
        $blocks = [];
        $end = 0;
        while(strpos($content, '<!-- wp:acf', $end) !== false) {
            $begin = strpos($content, '<!-- wp:acf', $end);
            $end = strpos($content, '-->', $begin);
            $blocks[] = [$begin, $end];
        }

        //change sentence
        if (!empty($blocks)) {
            $pos = strpos($content, $sentence);
            foreach ($blocks as $block) {
                if ($block[0] < $pos && $block[1] > $pos) {
                    $changed_sentence = str_replace('"', "'", $changed_sentence);
                }
            }
        }

        return $changed_sentence;
    }

    /**
     * Get post model by view link
     *
     * @param $link
     * @return Linkilo_Build_Model_Feed|null
     */
    public static function getPostByLink($link)
    {
        $post = null;

        $post_id = url_to_postid($link);
        if (!empty($post_id)) {
            $post = new Linkilo_Build_Model_Feed($post_id);
        } else {
            $slug = array_filter(explode('/', $link));
            $term = Linkilo_Build_WpTerm::getTermBySlug(end($slug));
            if(!empty($term)){
                $post = new Linkilo_Build_Model_Feed($term->term_id, 'term');
            }
        }

        return $post;
    }

    /**
     * Insert link into content
     *
     * @param $content The post content or content segment to update
     * @param $sentence The sentence in the content that will have a link inserted
     * @param $changed_sentence The sentence with a link inserted
     * @param $content_json Is the content that we're inserting the link into stored in a json format? Mostly applies to page builders
     * @return null
     */
    public static function insertLink(&$content, $sentence, $changed_sentence, $content_json = false)
    {
        if(empty($sentence)){
            return;
        }

        if(false !== mb_strpos($content, $changed_sentence)){
            return;
        }

        $position_start = mb_strpos($content, $sentence, 0);
        if(false === $position_start){
            $position_start = mb_strpos(self::normalize_slashes($content), self::normalize_slashes($sentence), 0);

            // if we have a start point now that the slashes have been normalized
            if(false !== $position_start){
                // find out if normalizing the slashes has changed the start position
                $letter1 = mb_substr(self::normalize_slashes($content), $position_start, 1);
                $letter2 = mb_substr($content, $position_start, 1);
                // if the letters don't match
                if($letter1 !== $letter2){
                    // figure out how far the string has changed to calculate the correct start point
                    $search = mb_substr(self::normalize_slashes($content), $position_start, mb_strlen($sentence));

                    // clean up the string to hopefully get a good search term
                    $search = explode('{linkilo-explode-token}', str_replace(array('\'', '"', '\\'), '{linkilo-explode-token}', $search));

                    $term = '';
                    foreach($search as $part){
                        if(strlen($part) > strlen($term)){
                            $term = $part;
                        }
                    }

                    // if we've found a term
                    if(!empty($term)){
                        $start1 = mb_strpos($content, $term);
                        $start2 = mb_strpos(self::normalize_slashes($content), $term);

                        if(false !== $start1 && false !== $start2){
                            if($start1 < $start2){ // 29 < 30; 30 - 29 = 1; 
                                $position_start = $position_start - ($start2 - $start1);
                            }else{ // 30 > 29; 30 - 29 = 1;
                                $position_start = $position_start + ($start1 - $start2);
                            }
                        }else{
                            $position_start = abs(intval($start1) - intval($start2));
                        }

                    }else{
                        $position_start = 0;
                    }
                }
            }
        }


        $position_end = 0;
        $old_end = 0;
        $endings = array_diff(Linkilo_Build_WordFunctions::$endings, array('\'', '"', ','));
        $sent_len = mb_strlen($sentence);

        // while we have words
        while($position_start !== false){

            // go over all the endings and find out which one is the actual end to the current string
            $shortest = false;


            foreach($endings as $ending){
                // the shortest string will have the ending punctuation
                $current_end = mb_strpos($content, $ending, ($position_start + $sent_len));
                if(false === $shortest){
                    $shortest = $current_end;
                }elseif($current_end < $shortest && $current_end !== false){
                    $shortest = $current_end;
                }
            }
            $position_end = (false !== $shortest) ? $shortest: mb_strlen($content); // if no ending was found, give the end of the content

            // now find the ending of the string that comes before the current one.
            $old_shortest = false;

            foreach($endings as $ending){
                // the longest string will have the ending punctuation since it's closest to the end of the current string.
                $current_end = mb_strrpos($content, $ending, (1 + $position_start - mb_strlen($content)));

                if(false === $current_end){
                    continue;
                }

                // if there's a closing html tag that comes after the current old ending
                $closing = mb_strpos($content, '>', $current_end);
                if(false !== $closing && $closing < $position_end){
                    // find the opening tag so we can tell what kind of tag this is
                    $current_end = mb_strrpos($content, '<', (1 + $current_end - mb_strlen($content)));
                }

                if(false === $old_shortest){
                    $old_shortest = $current_end;
                }elseif($current_end > $old_shortest && $current_end !== false){
                    $old_shortest = $current_end;
                }
            }


            $old_end = (false !== $old_shortest) ? $old_shortest: 0;
            $length = ($position_end - $position_start);
            $replace = mb_substr($content, $position_start, $length);

            // get the slice of text that we'll be checking for links
            $examine_text = mb_substr($content, $old_end, ($position_end - $old_end));

            // if there isn't a link in the text
            if(!Linkilo_Build_PostUrl::checkForForbiddenTags($examine_text, $replace, $sentence)){
                // get the text that comes before and after the sentence

                $front = mb_substr($content, 0, $position_start);
                $back = mb_substr($content, ($position_start + $length));

                // remove any quotes from the sentence to change
                $changed_sentence = Linkilo_Build_WordFunctions::removeQuotes($changed_sentence);

                // check if the user only wants to insert relative links
                if(!empty(get_option('linkilo_insert_links_as_relative', false))){
                    // if he does, extract the url
                    preg_match('/<a href="([^\"]+)"[^>]*?>(.*)<\/a>/i', $changed_sentence, $matches);
                    
                    // if we've got the url
                    if(!empty($matches) && isset($matches[1])){
                        // check the url to make sure it's internal
                        if(Linkilo_Build_PostUrl::isInternal($matches[1])){
                            // if it is, make it relative
                            $url = wp_make_link_relative($matches[1]);
                            // and replace the existing url with the new one
                            $changed_sentence = mb_ereg_replace(preg_quote($matches[1]), $url, $changed_sentence);
                        }
                    }
                }

                // check if the content is inside a json object
                $is_json = self::checkIfContentInJson($content, $position_start, $position_end);

                // if the content is inside a json object
                if($is_json){
                    // add double slashes
                    $changed_sentence = addslashes(addslashes($changed_sentence));
                }elseif($content_json){
                    // if the content is stored as json, add one set of slashes
                    $changed_sentence = addslashes($changed_sentence); // todo remove if I don't use this by version 1.8.0
                }

                $changed_text = mb_ereg_replace('(?<!=[\"\'\\\"\\\'])(' . preg_quote($sentence) . ')(?![\"\'\\\"\\\'].*?>)', $changed_sentence, $replace);

                // if the link has been inserted multiple times
                if(substr_count($changed_text, '</a>') > 1){
                    // remove all but the first version of the link
                    global $linkilo_link_insert_count, $linkilo_link_insert_sentence;
                    $linkilo_link_insert_count = 0;
                    $linkilo_link_insert_sentence = $sentence;

                    $changed_text = mb_ereg_replace_callback(preg_quote($changed_sentence), function($matches){global $linkilo_link_insert_count, $linkilo_link_insert_sentence; $linkilo_link_insert_count++; return ($linkilo_link_insert_count === 1) ? $matches[0] : $linkilo_link_insert_sentence; }, $changed_text);
                }

                // if the link has been inserted
                if($changed_text !== $replace){
                    // add the link to the text
                    $content = ($front . $changed_text . $back);
                    // and exit the loop since we only add one link at a time.
                    break;
                }else{
                    // if the link couldn't be inserted, continue the loop so hopefully we find the place to insert the link
                    $position_start = mb_strpos($content, $sentence, $position_end + 1);
                }
            }else{
                // if the keyword text is in a link, move to the next instance of the keyword
                $position_start = mb_strpos($content, $sentence, $position_end + 1);
            }
        }
    }

    /**
     * Get post IDs from certain category
     *
     * @param $category_id
     * @return array
     */
    public static function getCategoryPosts($category_id)
    {
        global $wpdb;

        $posts = [];
        $categories = $wpdb->get_results("SELECT r.object_id as `id` FROM {$wpdb->term_relationships} r INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id WHERE tt.term_id = " . $category_id);
        foreach ($categories as $post) {
            $posts[] = $post->id;
        }

        return $posts;
    }

    /**
     * Run function for all editors
     *
     * @param $action
     * @param $params
     */
    public static function editors($action, $params)
    {
        $editors = [
            'Beaver',
            'Elementor',
            'Origin',
            'Oxygen',
            'Thrive',
            'Themify',
            'Muffin',
            'Enfold',
            'Cornerstone',
            'WPRecipe'
        ];

        foreach ($editors as $editor) {
            $class = 'Linkilo_Build_Editor_' . $editor;
            call_user_func_array([$class, $action], $params);
        }
    }

    /**
     * Makes sure all single and double qoutes are excaped once in the supplied text.
     * @param string $text The text that needs to have it's quotes escaped
     * @return string $text The updated text with the single and double qoutes escaped
     **/
    public static function normalize_slashes($text){
        // add slashes to the single qoutes
        $text = mb_eregi_replace("(?<!\\\\)'", "\'", $text);
        // add slashes to the double qoutes
        $text = mb_eregi_replace('(?<!\\\\)"', '\"', $text);
        // and return the text
        return $text;
    }

    /**
     * Performs some checks to see if the current content is inside items known to be json.
     * I would rather do a check for JSON directly, but that will probably be opening a big can of worms...
     **/
    public static function checkIfContentInJson($content, $position_start = 0, $position_end = 0){
        // if Block Lab is active
        if(class_exists('Block_Lab\\Component_Abstract')){
            // check if the content is inside a block
            $len = strlen($content);
            $block_start = strrpos($content, '<!-- wp:block-lab', ($position_start - $len));
            $block_end = strrpos($content, '/-->', ($position_start - $len));

            // if the replace is inside a block, the opening block tag will be closer than the closing tag of whatever block came before.
            if($block_start > $block_end){
                return true;
            }
        }

        // by default, content is not json.
        return false;
    }

    /**
     * Gets the most recent revision id for the current post.
     * @param int|string|$post The id or post object that we want to get the revsions for
     * @return int|false The id of the most recent revsion or false if we couldn't find it.
     **/
    public static function get_most_recent_revision_id($post_id = 0){
        if(empty($post_id)){
            return false;
        }

        $revisions = wp_get_post_revisions($post_id);

        if(empty($revisions) || !is_array($revisions)){
            return false;
        }

        $latest = 0;
        foreach($revisions as $revision){
            if($latest < $revision->ID){
                $latest = $revision->ID;
            }
        }

        return $latest;
    }

    /**
     * Checks to see if the recently saved Divi content without links is the same as the Divi content submitted with the edit form.
     * What happens normally is we save the links before Divi has a chance to save it's content.
     * Then after Divi saves it's content, it checks to see if the 
     **/
    public static function verify_divi_save_status($content_saved){
        global $wpdb;

        if($content_saved){
            return $content_saved;
        }

        $post_id = absint( $_POST['post_id'] );
        $saved_post = get_post( $post_id );
        $current_gmt = current_time('mysql', true);

        // if it's been longer than 5 minutes since the post was last updated
        if( empty($saved_post) ||
            !isset($saved_post->post_modified_gmt) ||
            abs(strtotime($saved_post->post_modified_gmt) - strtotime($current_gmt)) > 300)
        {
            // return the current state of content savedness because it's unlikely that the post has been updated
            return $content_saved;
        }

        $layout_type = isset( $_POST['layout_type'] ) ? sanitize_text_field( $_POST['layout_type'] ) : '';
        $shortcode_data = json_decode( stripslashes( $_POST['modules'] ), true );
        $post_content = et_fb_process_to_shortcode( $shortcode_data, $_POST['options'], $layout_type );
        $sanitized_content = sanitize_post_field( 'post_content', $post_content, $post_id, 'db' );
        
        $saved_post_content   = $saved_post->post_content;
        $builder_post_content = stripslashes( $sanitized_content );

        if ( 'utf8' === $wpdb->get_col_charset( $wpdb->posts, 'post_content' ) ) {
           $builder_post_content = wp_encode_emoji( $builder_post_content );
       }

       $unlinked_saved_post_content = Linkilo_Build_PostUrl::remove_all_links_from_text($saved_post_content);
       $unlinked_builder_post_content = Linkilo_Build_PostUrl::remove_all_links_from_text($builder_post_content);

       $saved_verification = $unlinked_saved_post_content === $unlinked_builder_post_content;

       return $saved_verification;
   }
}
