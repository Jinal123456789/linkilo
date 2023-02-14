<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Linkilo_Build_Table_FocusKeyword
 */
class Linkilo_Build_Table_FocusKeyword extends WP_List_Table
{
    function get_columns()
    {
        $screen_options = get_user_meta(get_current_user_id(), 'focus_keyword_options', true);
        $show_date = (!empty($screen_options['show_date']) && $screen_options['show_date'] == 'off') ? false : true;
        $show_traffic = (!empty($screen_options['show_traffic']) && $screen_options['show_traffic'] == 'off') ? false : true;

        $options = array(
            'post_title' => __('Feed', 'linkilo'),
        );

        if($show_date){
            $options['date'] = 
            '<div class="linkilo-focus-keyword-header-tooltip">' . __('Created At', 'linkilo') . 
                '<div class="linkilo_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="linkilo-help-text" style="display: none;">' . __('The Created date is the date that the post was published on.', 'linkilo') . '</div>
                </div>
            </div>';
        }

        $options['word_cloud'] = 
        '<div class="linkilo-focus-keyword-header-tooltip">' . __('Functional keywords', 'linkilo') . 
            '<div class="linkilo_help">
                <i class="dashicons dashicons-editor-help"></i>
                <div class="linkilo-help-text" style="display: none;">' . __('The Functional keywords are the keywords that Linkilo will use to improve it\'s link suggestions.', 'linkilo') . '</div>
            </div>
        </div>';

        if($show_traffic){
            $options['organic_traffic'] = 
            '<div class="linkilo-focus-keyword-header-tooltip">' . __('Live dealing', 'linkilo') . 
                '<div class="linkilo_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="linkilo-help-text" style="display: none;">' . __('The number of clicks this page has received from Google organic search in the last 30 days. Google search console does not always provide all the data, so your actual organic traffic may vary from this number.', 'linkilo') . '</div>
                </div>
            </div>';
        }

        if(Linkilo_Build_GoogleSearchConsole::is_authenticated()){
            $options['gsc'] = 
            '<div class="linkilo-focus-keyword-header-tooltip">' . __('GSC Keywords', 'linkilo') . 
                '<div class="linkilo_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="linkilo-help-text" style="display: none;">' . __('The GSC Keywords are the keywords that we\'ve received from Google and can use when making link suggestions. The keywords are pulled from a date range of 30 days.', 'linkilo') . '</div>
                </div>
            </div>';
        }

        if(defined('WPSEO_VERSION')){
            $options['yoast'] = 
            '<div class="linkilo-focus-keyword-header-tooltip">' . __('Yoast Keywords', 'linkilo') . 
                '<div class="linkilo_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="linkilo-help-text" style="display: none;">' . __('The Yoast Keywords are the keywords that Linkilo has extracted from the Yoast SEO data for the post and can use when making link suggestions.', 'linkilo') . '</div>
                </div>
            </div>';
        }

        if(defined('RANK_MATH_VERSION')){
            $options['rank-math'] = 
            '<div class="linkilo-focus-keyword-header-tooltip">' . __('Rank Math Keywords', 'linkilo') . 
                '<div class="linkilo_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="linkilo-help-text" style="display: none;">' . __('The Rank Math Keywords are the keywords that Linkilo has extracted from the Rank Math data for the post and can use when making link suggestions.', 'linkilo') . '</div>
                </div>
            </div>';
        }

        if(defined('AIOSEO_PLUGIN_DIR')){
            $options['aioseo'] = 
            '<div class="linkilo-focus-keyword-header-tooltip">' . __('All in One SEO Keywords', 'linkilo') . 
                '<div class="linkilo_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="linkilo-help-text" style="display: none;">' . __('The All in One SEO Keywords are the keywords that Linkilo has extracted from the All in One SEO data for the post and can use when making link suggestions.', 'linkilo') . '</div>
                </div>
            </div>';
        }

        if(defined('SEOPRESS_VERSION')){
            $options['seopress'] = 
            '<div class="linkilo-focus-keyword-header-tooltip">' . __('SEOPress Keywords', 'linkilo') . 
                '<div class="linkilo_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="linkilo-help-text" style="display: none;">' . __('The SEOPress Keywords are the keywords that Linkilo has extracted from the SEOPress data for the post and can use when making link suggestions.', 'linkilo') . '</div>
                </div>
            </div>';
        }

        $options['custom'] = 
        '<div class="linkilo-focus-keyword-header-tooltip">' . __('Mannered keyword', 'linkilo') . 
            '<div class="linkilo_help">
                <i class="dashicons dashicons-editor-help"></i>
                <div class="linkilo-help-text" style="display: none;">' . __('The Custom Keywords are the keywords that you create for use in making link suggestions', 'linkilo') . '</div>
            </div>
        </div>';

        return $options;
    }

    function prepare_items()
    {
        $options = get_user_meta(get_current_user_id(), 'focus_keyword_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : false;
        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $search = !empty($_GET['s']) ? $_GET['s'] : '';
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : '';

        if(empty($per_page)){
            $options2 = get_user_meta(get_current_user_id(), 'report_options', true);
            $per_page = !empty($options2['per_page']) ? $options2['per_page'] : 20;
        }

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Linkilo_Build_FocusKeyword::getData($per_page, $page, $search, $orderby, $order);
        $this->items = $data['data'];

        $this->set_pagination_args(array(
            'total_items' => $data['total_items'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total_items'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        if(is_array($item) && isset($item['post'])){
            $post = $item['post'];
        }elseif(!empty($item)){
            $post = new Linkilo_Build_Model_Feed($item->ID, $item->post_type);
        }

        switch($column_name) {
            case 'post_title':

                $actions = [];

                $title = '<a href="' . $post->getLinks()->edit . '" class="row-title">' . esc_attr($post->getTitle()) . '</a>';
                $actions['view'] = '<a target=_blank href="' . $post->getLinks()->view . '">View</a>';
                $actions['edit'] = '<a target=_blank href="' . $post->getLinks()->edit . '">Edit</a>';
                $actions['add_incoming'] = '<a target=_blank href="' . admin_url("admin.php?post_id={$post->id}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI'])) . '">Add Incoming Links</a>';
        
                return $title . $this->row_actions($actions);
            case 'word_cloud':
                $keywords = Linkilo_Build_FocusKeyword::get_keywords_by_post_ids($post->id, $post->type);
                $keyword_string = '';
                $has_active_keywords = false;
                foreach($keywords as $keyword){
                    $hidden = 'style="display:none;"';
                    if(!empty($keyword->checked)){
                        $has_active_keywords = true;
                        $hidden = '';
                    }

                    $keyword_string .= '<li id="active-keyword-' . $keyword->keyword_index . '" class="linkilo-focus-keyword-active-kywrd" ' . $hidden . '>' . $keyword->keywords . '</li>';
                }

                $hidden_notice = '';
                if($has_active_keywords){
                    $hidden_notice = ' style="display:none;" ';
                }

                $no_active_keys = '<li class="no-active-keywords-notice"' . $hidden_notice . '>' . __('No Functional keywords', 'linkilo') . '</li>';

                return '<ul>' . $no_active_keys . $keyword_string . '</ul>';
            case 'organic_traffic':
                $keywords = Linkilo_Build_FocusKeyword::get_post_keywords_by_type($post->id, $post->type, 'gsc-keyword', false);
                $clicks = 0;
                $position = 0;
                foreach($keywords as $keyword){
                    $clicks += $keyword->clicks;
                    $position += floatval($keyword->position);
                }

                if($position > 0){
                    $position = round($position/count($keywords), 2);
                }

                return '<ul>
                            <li>' . __('Clicks: ', 'linkilo') . $clicks . '</li>
                            <li>' . __('AVG Position: ', 'linkilo') . $position . '</li>
                        </ul>';
            case 'gsc':
                $keywords = Linkilo_Build_FocusKeyword::filter_duplicate_gsc_keywords(Linkilo_Build_FocusKeyword::get_post_keywords_by_type($post->id, $post->type));
                $keyword_type = 'gsc-keyword';
                ob_start();
                include LINKILO_PLUGIN_DIR_PATH . '/templates/blocks/collapsible_focus_keywords.php';
                return ob_get_clean();
            case 'yoast':
                $keywords = Linkilo_Build_FocusKeyword::get_post_keywords_by_type($post->id, $post->type, 'yoast-keyword');
                $keyword_type = 'yoast-keyword';
                ob_start();
                include LINKILO_PLUGIN_DIR_PATH . '/templates/blocks/collapsible_focus_keywords.php';
                return ob_get_clean();
            case 'rank-math':
                $keywords = Linkilo_Build_FocusKeyword::get_post_keywords_by_type($post->id, $post->type, 'rank-math-keyword');
                $keyword_type = 'rank-math-keyword';
                ob_start();
                include LINKILO_PLUGIN_DIR_PATH . '/templates/blocks/collapsible_focus_keywords.php';
                return ob_get_clean();
            case 'aioseo':
                $keywords = Linkilo_Build_FocusKeyword::get_post_keywords_by_type($post->id, $post->type, 'aioseo-keyword');
                $keyword_type = 'aioseo-keyword';
                ob_start();
                include LINKILO_PLUGIN_DIR_PATH . '/templates/blocks/collapsible_focus_keywords.php';
                return ob_get_clean();
            case 'seopress':
                $keywords = Linkilo_Build_FocusKeyword::get_post_keywords_by_type($post->id, $post->type, 'seopress-keyword');
                $keyword_type = 'seopress-keyword';
                ob_start();
                include LINKILO_PLUGIN_DIR_PATH . '/templates/blocks/collapsible_focus_keywords.php';
                return ob_get_clean();
            case 'custom':
                $keywords = Linkilo_Build_FocusKeyword::get_post_keywords_by_type($post->id, $post->type, 'custom-keyword');
                $keyword_type = 'custom-keyword';
                ob_start();
                include LINKILO_PLUGIN_DIR_PATH . '/templates/blocks/collapsible_focus_keywords.php';
                return ob_get_clean();
            case 'date':
                if($post->type === 'post'){
                    return get_the_date('', $post->id);
                }else{
                    return __('Not Set', 'linkilo');
                }
            default:
                return $item->$column_name;
        }
    }

    function get_sortable_columns()
    {
        return [
            'post_title'        => ['post_title', false],
            'gsc'               => ['gsc', false],
            'yoast'             => ['yoast', true],
            'rank-math'         => ['rank-math', true],
            'aioseo'            => ['aioseo', true],
            'seopress'          => ['seopress', true],
            'organic_traffic'   => ['organic_traffic', true],
            'custom'            => ['custom', false],
            'date'              => ['date', false]
        ];
    }

    function extra_tablenav( $which ) {
        if ($which == "top") {
            $post_type = Linkilo_Build_RecordFilter::linksPostType();
            $post_type = !empty($post_type) ? $post_type : 0;
            ?>
            <div class="alignright actions bulkactions" id="linkilo_links_table_filter">
                <select name="keyword_post_type">
                    <option value="0"><?php _e('All Post Types', 'linkilo'); ?></option>
                    <?php foreach (Linkilo_Build_AdminSettings::getAllTypes() as $type) : ?>
                        <option value="<?=$type?>" <?=$type===$post_type?' selected':''?>><?=ucfirst($type)?></option>
                    <?php endforeach; ?>
                </select>
                <span class="button-primary">Filter</span>
                <input type="hidden" class="post-filter-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'linkilo_filter_nonce'); ?>">
            </div>
            <?php
        }
    }

    /**
     * Generates the columns for a single row of the table.
     *
     * @since 3.1.0
     *
     * @param object $item The current item.
     */
    protected function single_row_columns( $item ) {
        list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

        foreach ( $columns as $column_name => $column_display_name ) {
            $classes = "$column_name column-$column_name";
            if ( $primary === $column_name ) {
                $classes .= ' has-row-actions column-primary';
            }

            if ( in_array( $column_name, $hidden, true ) ) {
                $classes .= ' hidden';
            }
 
            if(in_array($column_name, array('gsc', 'yoast', 'rank-math', 'aioseo', 'seopress', 'custom'), true)){
                $classes .= ' linkilo-dropdown-column';
            }

            // Comments column uses HTML in the display name with screen reader text.
            // Instead of using esc_attr(), we strip tags to get closer to a user-friendly string.
            $data = 'data-colname="' . wp_strip_all_tags( $column_display_name ) . '"';
 
            $attributes = "class='$classes' $data";
 
            if ( 'cb' === $column_name ) {
                echo '<th scope="row" class="check-column">';
                echo $this->column_cb( $item );
                echo '</th>';
            } elseif ( method_exists( $this, '_column_' . $column_name ) ) {
                echo call_user_func(
                    array( $this, '_column_' . $column_name ),
                    $item,
                    $classes,
                    $data,
                    $primary
                );
            } elseif ( method_exists( $this, 'column_' . $column_name ) ) {
                echo "<td $attributes>";
                echo call_user_func( array( $this, 'column_' . $column_name ), $item );
                echo $this->handle_row_actions( $item, $column_name, $primary );
                echo '</td>';
            } else {
                echo "<td $attributes>";
                echo $this->column_default( $item, $column_name );
                echo $this->handle_row_actions( $item, $column_name, $primary );
                echo '</td>';
            }
        }
    }

}