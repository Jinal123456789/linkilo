<?php

/**
 * Model for posts and terms
 *
 * Class Linkilo_Build_Model_Feed
 */
class Linkilo_Build_Model_Feed
{
    public $id;
    public $title;
    public $type;
    public $status;
    public $content;
    public $links;
    public $slug = null;
    public $clicks = null;
    public $position = null;

    public function __construct($id, $type = 'post')
    {
        $this->id = $id;
        $this->type = $type;
    }

    function getTitle()
    {
        if (empty($this->title)) {
            if ($this->type == 'term') {
                $term = get_term($this->id);
                if (!empty($term) && !isset($term->errors)) {
                    $this->title = $term->name;
                }
                unset($term);
            } elseif ($this->type == 'post') {
                $this->title = get_the_title($this->id);
            }
        }

        return $this->title;
    }

    function getLinks()
    {
        if (empty($this->links)) {
            if ($this->type == 'term') {
                $term = get_term($this->id);
                if (!empty($term) && !isset($term->errors)) {
                    $this->links = (object)[
                        'view' => $this->getViewLink(),
                        'edit' => esc_url(admin_url('term.php?taxonomy=' . $term->taxonomy . '&post_type=post&tag_ID=' . $this->id)),
                        'export' => esc_url(admin_url("post.php?area=linkilo_export&term_id=" . $this->id)),
                        'excel_export' => esc_url(admin_url("post.php?area=linkilo_excel_export&term_id=" . $this->id)),
                        'refresh' => esc_url(admin_url("admin.php?page=linkilo&type=post_links_count_update&term_id=" . $this->id))
                    ];
                }
                unset($term);
            } elseif ($this->type == 'post') {
                $this->links = (object)[
                    'view' => $this->getViewLink(),
                    'edit' => get_edit_post_link($this->id),
                    'export' => esc_url(admin_url("post.php?area=linkilo_export&post_id=" . $this->id)),
                    'excel_export' => esc_url(admin_url("post.php?area=linkilo_excel_export&post_id=" . $this->id)),
                    'refresh' => esc_url(admin_url("admin.php?page=linkilo&type=post_links_count_update&post_id=" . $this->id)),
                ];
            }
        }

        if (empty($this->links)) {
            $this->links = (object)[
                'view' => '',
                'edit' => '',
                'export' => '',
                'excel_export' => '',
                'refresh' => '',
            ];
        }

        return $this->links;
    }

    /**
     * Gets the view link for the current post
     **/
    function getViewLink(){
        if ($this->type == 'term') {
            $term = get_term($this->id);
            if (!empty($term) && !isset($term->errors)) {
                $link = get_term_link($term);

                if(defined('BWLM_file')){
                    $woo_link_manage = new BeRocketLinkManager;
                    $link = $woo_link_manage->rewrite_terms($link, $term, $term->taxonomy);
                }

                return $link;
            }
            unset($term);
        } elseif ($this->type == 'post') {
            // if the post isn't published yet
            if(in_array($this->getStatus(), array('draft', 'pending', 'future'))){
                // get the sample permalink
                if(function_exists('get_sample_permalink')){
                    $url_data = get_sample_permalink($this->id);
                }else{
                    $url_data = $this->get_sample_permalink($this->id);
                }

                if(false === strpos($url_data[0], '%postname%') && false === strpos($url_data[0], '%pagename%')){
                    $view_link = $url_data[0];
                }else{
                    $view_link = str_replace(array('%pagename%', '%postname%'), $url_data[1], $url_data[0]);    
                }
            }else{
                $view_link = get_the_permalink($this->id);

                if(defined('BWLM_file')){
                    $woo_link_manage = new BeRocketLinkManager;
                    $view_link = $woo_link_manage->rewrite_products($view_link, get_post($this->id));
                }
            }

            return $view_link;
        }

        return '';
    }

    /**
     * Update post content
     *
     * @param $content
     * @return $this
     */
    function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get post content depends on post type
     *
     * @return string
     */
    function getContent($remove_recipes = true)
    {
        if (empty($this->content)) {
            if ($this->type == 'term') {
                $content = term_description($this->id);
            } else {
                $content = '';
                // if the Thrive plugin is active
                if(defined('TVE_PLUGIN_FILE') || defined('TVE_EDITOR_URL')){
                    $thrive_active = get_post_meta($this->id, 'tcb_editor_enabled', true);
                    if(!empty($thrive_active)){
                        $thrive_content = get_post_meta($this->id, 'tve_updated_post', true);
                        if($thrive_content){
                            $content = $thrive_content;
                        }
                    }
                    
                    if(get_post_meta($this->id, 'tve_landing_set', true) && $thrive_template = get_post_meta($this->id, 'tve_landing_page', true)){
                        $content = get_post_meta($this->id, 'tve_updated_post_' . $thrive_template, true);
                    }
                }

                // if there's no content and the muffin builder is active
                if(empty($content) && defined('MFN_THEME_VERSION')){
                    // try getting the Muffin content
                    $content = Linkilo_Build_Editor_Muffin::getContent($this->id);
                }

                // if the Enfold Advanced editor is active
                if(defined('AV_FRAMEWORK_VERSION') && 'active' === get_post_meta($this->id, '_aviaLayoutBuilder_active', true)){
                    // get the editor content from the meta
                    $content = get_post_meta($this->id, '_aviaLayoutBuilderCleanData', true);
                }

                // if we have no content and Cornerstone is active
                if(empty($content) && defined('CS_ASSET_REV')){
                    // try getting the Cornerstone content
                    $content = Linkilo_Build_Editor_Cornerstone::getContent($this->id);
                }

                // if we have no content and Elementor is active // todo delete or uncomment and continue work later. If I don't need this by release 1.8.5, it's probably not needed.
//                if(empty($content) && defined('ELEMENTOR_VERSION')){
//                    $content = Linkilo_Build_Editor_Elementor::getContent($this->id);
//                }

                // if WP Recipe is active and we're REALLY sure that this is a recipe
                if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Linkilo_Build_AdminSettings::getPostTypes()) && 'post' === $this->type && 'wprm_recipe' === get_post_type($this->id)){
                    // get the recipe content
                    $content = get_post_meta($this->id, 'wprm_notes', true);
                }

                if(empty($content)){
                    $item = get_post($this->id);
                    $content = $item->post_content;
                    $content .= $this->getAdvancedCustomFields();
                    $content .= $this->getThemifyContent();
                    $content .= Linkilo_Build_Editor_Oxygen::getContent($this->id);
                }
            }

            if($remove_recipes){
                //Remove WPRM plugin content
                $content = preg_replace('#(?<=<!--WPRM Recipe)(.*?)(?=<!--End WPRM Recipe-->)#ms', '', $content);
            }

            // if Yoast is active and there's a Yoast block in the content
            if(defined('WPSEO_VERSION') && false !== strpos($content, '<!-- wp:yoast/')){
                // remove the Yoast blocks since adding links to them breaks them...
                $content = preg_replace('#(?<=<!-- wp:yoast/)(.*?)(?=<!-- /wp:yoast/)#ms', '', $content);
            }

            // if Rank Math is active and there's a Rank Math block in the content
            if(defined('RANK_MATH_VERSION') && false !== strpos($content, '<!-- wp:rank-math/')){
                // remove the Rank Math blocks since adding links to them breaks them...
                $content = preg_replace('#(?<=<!-- wp:rank-math/)(.*?)(?=<!-- /wp:rank-math/)#ms', '', $content);
            }

            $this->content = $content;
        }

        return $this->content;
    }

    /**
     * Gets the post content without updating the post's content var.
     * This is mostly so we can deal with WP Recipe posts
     **/
    function getContentWithoutSetting($remove_recipes = true){
        // store the existing content
        $existing = $this->content;
        // unset the current content
        $this->content = '';
        // get the new content
        $content = $this->getContent($remove_recipes);
        // reset the post's existing content
        $this->content = $existing;
        // and return the found content
        return $content;
    }

    /**
     * Clears the post/term cache for the current item
     *
     * @return bool
     */
    function clearPostCache()
    {
        if($this->type === 'post'){
            $clear = wp_cache_delete($this->id, 'posts');
        }else{
            $clear = wp_cache_delete($this->id, 'terms');
        }

        return $clear;
    }

    /**
     * Get updated post content
     *
     * @return string
     */
    function getFreshContent()
    {
        if($this->type === 'post'){
            wp_cache_delete($this->id, 'posts');
        }else{
            wp_cache_delete($this->id, 'terms');
        }
        $this->content = null;
        return $this->getContent();
    }

    /**
     * Get not modified post content
     *
     * @return string
     */
    function getCleanContent()
    {
        if ($this->type == 'term') {
            wp_cache_delete($this->id, 'terms');
            $term = get_term($this->id);
            $content = $term->description;
        } else {
            wp_cache_delete($this->id, 'posts');
            $p = get_post($this->id);
            $content = $p->post_content;
        }

        return $content;
    }

    /**
     * Get post slug depends on post type
     *
     * @return string|null
     */
    function getSlug()
    {
        if (empty($this->slug)) {
            if ($this->type == 'term') {
                $term = get_term($this->id);
                $this->slug = $term->slug;
            } else {
                // Todo make a slug getter that uses the post url so it works with draft posts
                $post = get_post($this->id);
                $this->slug = $post->post_name;
            }
        }

        return '/' . $this->slug;
    }

    /**
     * Get post content from advanced custom fields
     *
     * @return string
     */
    function getAdvancedCustomFields()
    {
        $content = '';

        if(!class_exists('ACF') || get_option('linkilo_disable_acf', false)){
            return $content;
        }

        foreach (Linkilo_Build_Feed::getAdvancedCustomFieldsList($this->id) as $field) {
            if ($c = get_post_meta($this->id, $field, true)) {
                if(is_array($c)){
                    continue;
                }

                $content .= "\n" . $c;
            }
        }

        return $content;
    }

    /**
     * Get post type
     */
    function getType()
    {
        $type = 'Post';
        if ($this->type == 'term') {
            $type = 'Category';
            $term = get_term($this->id);
            if ($term->taxonomy == 'post_tag') {
                $type = 'Tag';
            }
        } elseif ($this->type == 'post') {
            $item = get_post($this->id);
            $type = ucfirst($item->post_type);
        }

        return $type;
    }

    /**
     * Get real post type
     *
     * @return string
     */
    function getRealType()
    {
        $type = '';
        if ($this->type == 'term') {
            $term = get_term($this->id);
            $type = !empty($term->taxonomy) ? $term->taxonomy : '';
        } elseif ($this->type == 'post') {
            $item = get_post($this->id);
            $type = !empty($item->post_type) ? $item->post_type : '';
        }

        return $type;
    }

    /**
     * Get post status
     *
     * @return string
     */
    function getStatus()
    {
        if (empty($this->status)) {
            $this->status = 'publish';
            if ($this->type == 'post') {
                $item = get_post($this->id);
                if(!empty($item)){
                    $this->status = $item->post_status;
                }
            }
        }

        return $this->status;
    }

    /**
     * Update post content
     *
     * @param $content
     */
    function updateContent($content)
    {
        global $wpdb;

        if ($this->type == 'term') {
            $updated = $wpdb->update($wpdb->term_taxonomy, ['description' => $content], ['term_id' => $this->id]);
        } else {
            $updated = $wpdb->update($wpdb->posts, ['post_content' => $content], ['ID' => $this->id]);
        }

        return $updated;
    }

    /**
     * Get Incoming Inner URLs list
     *
     * @return array
     */
    function getIncomingInternalLinks($count = false)
    {
        return $this->getLinksData('linkilo_links_incoming_internal_count', $count);
    }

    /**
     * Get Outgoing Inner URLs list
     *
     * @return array
     */
    function getOutboundInternalLinks($count = false)
    {
        return $this->getLinksData('linkilo_links_outgoing_internal_count', $count);
    }

    /**
     * Get Outgoing Outer URLs list
     *
     * @return array
     */
    function getOutboundExternalLinks($count = false)
    {
        return $this->getLinksData('linkilo_links_outgoing_external_count', $count);
    }

    /**
     * Get Post Links list
     *
     * @return array|int
     */
    function getLinksData($key, $count)
    {
        if (!$count) {
            $key .= '_data';
        }

        if ($this->type == 'term') {
            $links = get_term_meta($this->id, $key, $single = true);
        } else {
            $links = get_post_meta($this->id, $key, $single = true);
        }

        if (empty($links)) {
            $links = $count ? 0 : [];
        }

        return $links;
    }

    /**
     * Get Themify Builder content
     *
     * @return string
     */
    function getThemifyContent()
    {
        $content = '';
        $item = get_post($this->id);

        if (strpos($item->post_content, 'wp:themify-builder') !== false) {
            $content = Linkilo_Build_Editor_Themify::getContent($this->id);
        }

        return $content;
    }

    /**
     * Check if post status is checked in the settings page
     *
     * @return bool
     */
    function statusApproved()
    {
        if (in_array($this->getStatus(), Linkilo_Build_AdminSettings::getPostStatuses())) {
            return true;
        }

        return false;
    }

    /**
     * Borrowed from WP without changing except for restricting when the 'get_sample_permalink' filter is called.
     * From V 5.5.1
     **/
    function get_sample_permalink( $id, $title = null, $name = null ) {
        $post = get_post( $id );
        if ( ! $post ) {
            return array( '', '' );
        }
     
        $ptype = get_post_type_object( $post->post_type );
     
        $original_status = $post->post_status;
        $original_date   = $post->post_date;
        $original_name   = $post->post_name;
     
        // Hack: get_permalink() would return ugly permalink for drafts, so we will fake that our post is published.
        if ( in_array( $post->post_status, array( 'draft', 'pending', 'future' ), true ) ) {
            $post->post_status = 'publish';
            $post->post_name   = sanitize_title( $post->post_name ? $post->post_name : $post->post_title, $post->ID );
        }
     
        // If the user wants to set a new name -- override the current one.
        // Note: if empty name is supplied -- use the title instead, see #6072.
        if ( ! is_null( $name ) ) {
            $post->post_name = sanitize_title( $name ? $name : $title, $post->ID );
        }
     
        $post->post_name = wp_unique_post_slug( $post->post_name, $post->ID, $post->post_status, $post->post_type, $post->post_parent );
     
        $post->filter = 'sample';
     
        $permalink = get_permalink( $post, true );
     
        // Replace custom post_type token with generic pagename token for ease of use.
        $permalink = str_replace( "%$post->post_type%", '%pagename%', $permalink );
     
        // Handle page hierarchy.
        if ( $ptype->hierarchical ) {
            $uri = get_page_uri( $post );
            if ( $uri ) {
                $uri = untrailingslashit( $uri );
                $uri = strrev( stristr( strrev( $uri ), '/' ) );
                $uri = untrailingslashit( $uri );
            }
     
            /** This filter is documented in wp-admin/edit-tag-form.php */
            $uri = apply_filters( 'editable_slug', $uri, $post );
            if ( ! empty( $uri ) ) {
                $uri .= '/';
            }
            $permalink = str_replace( '%pagename%', "{$uri}%pagename%", $permalink );
        }
     
        /** This filter is documented in wp-admin/edit-tag-form.php */
        $permalink         = array( $permalink, apply_filters( 'editable_slug', $post->post_name, $post ) );
        $post->post_status = $original_status;
        $post->post_date   = $original_date;
        $post->post_name   = $original_name;
        unset( $post->filter );
     
        /**
         * Filters the sample permalink.
         *
         * @since 4.4.0
         *
         * @param array   $permalink {
         *     Array containing the sample permalink with placeholder for the post name, and the post name.
         *
         *     @type string $0 The permalink with placeholder for the post name.
         *     @type string $1 The post name.
         * }
         * @param int     $post_id   Post ID.
         * @param string  $title     Post title.
         * @param string  $name      Post name (slug).
         * @param WP_Post $post      Post object.
         */
        if(!defined('EDIT_FLOW_VERSION')){ // don't apply filters for the Edit Flow plugin since it makes a call to 'get_sample_permalink', which doesn't exist...
            return apply_filters( 'get_sample_permalink', $permalink, $post->ID, $title, $name, $post );
        }

    }

    /**
     * Gets the organic traffic for the current post.
     **/
    function get_organic_traffic(){
        if(is_null($this->clicks)){
            $keywords = Linkilo_Build_FocusKeyword::get_post_keywords_by_type($this->id, $this->type, 'gsc-keyword', false);

            if(empty($keywords) || !is_array($keywords)){
                $this->clicks = 0;
                return $this->clicks;
            }

            $position = 0;
            $clickes = 0;
            foreach($keywords as $keyword){
                if(!empty($keyword->clicks)){
                    $clickes += $keyword->clicks;
                }

                $position += floatval($keyword->position);
            }

            if($position > 0){
                $position = round($position/count($keywords), 2);
            }

            $this->clicks = $clickes;
            $this->position = $position; // we've already processed the keywords, we might as well set the position now too.
        }

        return $this->clicks;
    }

    /**
     * Gets the organic traffic for the current post.
     **/
    function get_avg_position(){
        if(is_null($this->position)){
            $keywords = Linkilo_Build_FocusKeyword::get_post_keywords_by_type($this->id, $this->type, 'gsc-keyword', false);

            if(empty($keywords) || !is_array($keywords)){
                $this->position = 0;
                return $this->position;
            }

            $position = 0;
            $clickes = 0;
            foreach($keywords as $keyword){
                if(!empty($keyword->clicks)){
                    $clickes += $keyword->clicks;
                }

                $position += floatval($keyword->position);
            }

            if($position > 0){
                $position = round($position/count($keywords), 2);
            }

            $this->position = $position;
            $this->clicks = $clickes; // we've already processed the keywords, we might as well set the clicks now too.
        }

        return $this->position;
    }
}
