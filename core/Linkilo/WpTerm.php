<?php

/**
 * Work with terms
 */
class Linkilo_Build_WpTerm
{
    /**
     * Register services
     */
    public function register()
    {
        foreach (Linkilo_Build_AdminSettings::getTermTypes() as $term) {
            add_action($term . '_add_form_fields', [$this, 'showTermSuggestions']);
            add_action($term . '_edit_form', [$this, 'showTermSuggestions']);
            add_action('edited_' . $term, [$this, 'addLinksToTerm']);
            add_action('edited_' . $term, ['Linkilo_Build_FocusKeyword', 'update_keywords_on_term_save']);
            add_action($term . '_add_form_fields', [$this, 'showTargetKeywords']);
            add_action($term . '_edit_form', [$this, 'showTargetKeywords']);
        }
    }

    /**
     * Show suggestions on term page
     */
    public static function showTermSuggestions()
    {
        if(empty($_GET['tag_ID']) ||empty($_GET['taxonomy'] || !in_array($_GET['taxonomy'], Linkilo_Build_AdminSettings::getTermTypes()))){
            return;
        }

        $term_id = (int)$_GET['tag_ID'];
        $post_id = 0;
        $user = wp_get_current_user();
        $manually_trigger_suggestions = !empty(get_option('linkilo_manually_trigger_suggestions', false));
        ?>
        <div id="linkilo_link-articles" class="postbox">
            <h2 class="hndle no-drag"><span><?php _e('Linkilo Suggested Links', 'linkilo'); ?></span></h2>
            <div class="inside">
                <?php include LINKILO_PLUGIN_DIR_PATH . '/templates/url_recommend_list.php';?>
            </div>
        </div>
        <?php
    }

    /**
     * Show focus keywords on term page
     */
    public static function showTargetKeywords()
    {
        if(empty($_GET['tag_ID']) ||empty($_GET['taxonomy'] || !in_array($_GET['taxonomy'], Linkilo_Build_AdminSettings::getTermTypes()))){
            return;
        }

        $term_id = (int)$_GET['tag_ID'];
        $post_id = 0;
        $user = wp_get_current_user();
        $keywords = Linkilo_Build_FocusKeyword::get_keywords_by_post_ids($term_id, 'term');
        $post = new Linkilo_Build_Model_Feed($term_id, 'term');
        $keyword_sources = Linkilo_Build_FocusKeyword::get_active_keyword_sources();
        $is_metabox = true;
        ?>
        <div id="linkilo_focus-keywords" class="postbox ">
            <h2 class="hndle no-drag"><span><?php _e('Linkilo Focus Keyword', 'linkilo'); ?></span></h2>
            <div class="inside"><?php
                include LINKILO_PLUGIN_DIR_PATH . '/templates/focus_keyword_list.php';
            ?>
            </div>
        </div>
        <?php
    }
    /**
     * Add links to term description on term update
     *
     * @param $term_id
     */
    public static function addLinksToTerm($term_id)
    {
        global $wpdb;

        //get links
        $meta = get_term_meta($term_id,'linkilo_links', true);

        if (!empty($meta)) {
            $description = term_description($term_id);

            //add links to the term description
            foreach ($meta as $link) {
                $changed_sentence = Linkilo_Build_Feed::getSentenceWithAnchor($link);
                $description = preg_replace('/' . preg_quote($link['sentence'], '/') . '/i', $changed_sentence, $description, 1);
            }

            //delete links from DB
            delete_term_meta($term_id,'linkilo_links');

            //update term description
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}term_taxonomy SET description = %s WHERE term_id = {$term_id} AND description != ''", $description));

            if(LINKILO_IS_LINKS_TABLE_EXISTS){
                //update the link table
                $term = new Linkilo_Build_Model_Feed($term_id, 'term');
                Linkilo_Build_UrlRecord::update_post_in_link_table($term->setContent($description));
            }
        }

        if (empty(get_option('linkilo_post_procession'))) {
            $term = new Linkilo_Build_Model_Feed($term_id, 'term');
            Linkilo_Build_RelateUrlKeyword::addKeywordsToPost($term);
            Linkilo_Build_UrlReplace::replacePostURLs($term);
        }
    }

    /**
     * Get category or tag by slug
     *
     * @param $slug
     * @return WP_Term
     */
    public static function getTermBySlug($slug)
    {
        $term = get_term_by('slug', $slug, 'category');
        if (!$term) {
            $term = get_term_by('slug', $slug, 'post_tag');
        }

        return $term;
    }

    /**
     * Gets all category terms for all active post types
     * 
     * @return array 
     **/
    public static function getAllCategoryTerms(){
        $start = microtime(true);
        $post_types = Linkilo_Build_AdminSettings::getPostTypes();
        if(empty($post_types)){
            return false;
        }

        $terms = get_transient('linkilo_cached_category_terms');
        if(empty($terms)){

            $skip_terms = array(
                'product_type',
                'product_visibility',
                'product_shipping_class',
            );

            $terms = array();
            foreach($post_types as $type){
                $taxonomies = get_object_taxonomies($type);

                foreach($taxonomies as $taxonomy){
                    if(in_array($taxonomy, $skip_terms)){
                        continue;
                    }

                    $args = array(
                        'taxonomy' => $taxonomy,
                        'hide_empty' => false
                    );
                    $term = get_terms($args);

                    if(!is_a($term, 'WP_Error')){
                        $terms[] = $term;
                    }
                }
            }

            // cache the terms for 5 minutes
            set_transient('linkilo_cached_category_terms', $terms, MINUTE_IN_SECONDS * 5);
        }

        return $terms;
    }
}
