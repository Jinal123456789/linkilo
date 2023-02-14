<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Linkilo_Build_Table_DomainUrl
 */
class Linkilo_Build_Table_RelateUrlKeyword extends WP_List_Table
{
    function get_columns()
    {
        $cols = array(
            'keyword' => 'Target Words',
            'link' => 'URL',
        );

        $options = get_user_meta(get_current_user_id(), 'linkilo_keyword_options', true);
        $select_links_active = Linkilo_Build_RelateUrlKeyword::keywordLinkSelectActive();

        if($select_links_active && (empty($options) || isset($options['hide_select_links_column']) && $options['hide_select_links_column'] === 'off')){
            $cols['select_links'] = 'Possible Links';
        }

        $cols['links'] = 'URLs Inserted';
        $cols['actions'] = '';

        return $cols;
    }

    function prepare_items()
    {
        $options = get_user_meta(get_current_user_id(), 'linkilo_keyword_options', true);

        if(!empty($options) && isset($options['per_page'])){
            $per_page = intval($options['per_page']);
            if(empty($per_page)){
                $per_page = 20;
            }
        }else{
            $per_page = 20;
        }

        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $search = !empty($_GET['s']) ? $_GET['s'] : '';
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : '';

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Linkilo_Build_RelateUrlKeyword::getData($per_page, $page, $search, $orderby, $order);
        $this->items = $data['keywords'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        switch($column_name) {
            case 'keyword':
                $terms = Linkilo_Build_WpTerm::getAllCategoryTerms();
                $cat_selector = '';
                if(!empty($terms)){
                    $restricted_cats = explode(',', $item->restricted_cats);
                    $cat_selector ='
                    <div class="linkilo_keywords_restrict_to_cats_container">
                        <input type="hidden" name="linkilo_keywords_restrict_to_cats" value="0" />
                        <input type="checkbox" class="linkilo_keywords_restrict_to_cats" name="linkilo_keywords_restrict_to_cats" ' . (!empty($item->restrict_cats)?'checked':'') . ' value="1" />
                        <label for="linkilo_keywords_restrict_to_cats">' . __('Block particular category?', 'linkilo') . '</label>
                        <span class="linkilo-keywords-restrict-cats-show"></span>
                    </div>';

                    $cat_selector .= '<ul class="linkilo-keywords-restrict-cats" style="display:none;">';
                    $cat_selector .= '<li>' . __('Available Categories:', 'linkilo') . '</li>';
                    foreach($terms as $term_data){
                        foreach($term_data as $term){

                            $cat_selector .= '<li>
                                    <input type="hidden" name="linkilo_keywords_restrict_term_' . $term->term_id . '" value="0" />
                                    <input type="checkbox" class="linkilo-restrict-keywords-input" name="linkilo_keywords_restrict_term_' . $term->term_id . '" ' . (in_array($term->term_id, $restricted_cats)?'checked':'') . ' data-term-id="' . $term->term_id . '">' . $term->name . '</li>';
                        }
                    }
                    $cat_selector .= '</ul>';
                    $cat_selector .= '<br />';
                }

                $date_restricted = (!empty($item->restrict_date) && !empty($item->restricted_date));

                return '<div>' . stripslashes($item->$column_name) . '<i class="dashicons dashicons-admin-generic"></i></div>
                        <div class="local_settings">
                            <div class="block" data-id="' . $item->id . '">
                                <input type="hidden" name="linkilo_keywords_add_same_link" value="0" />
                                <input type="checkbox" name="linkilo_keywords_add_same_link" ' . ($item->add_same_link==1?'checked':'') . ' value="1" />
                                <label for="linkilo_keywords_add_same_link">' . __('Add URL to feed if it already contains this url? Table', 'linkilo') . '</label>
                                <br>
                                <input type="hidden" name="linkilo_keywords_link_once" value="0" />
                                <input type="checkbox" name="linkilo_keywords_link_once" ' . ($item->link_once==1?'checked':'') . ' value="1" />
                                <label for="linkilo_keywords_link_once">' . __('Per feed link once?', 'linkilo') . '</label>
                                <br>
                                <input type="hidden" name="linkilo_keywords_select_links" value="0" />
                                <input type="checkbox" name="linkilo_keywords_select_links" ' . ($item->select_links==1?'checked':'') . ' value="1" />
                                <label for="linkilo_keywords_select_links">Select URL before insert?</label>
                                <br>
                                <input type="hidden" name="linkilo_keywords_set_priority" value="0" />
                                <input type="checkbox" name="linkilo_keywords_set_priority" class="linkilo_keywords_set_priority_checkbox" ' . ($item->set_priority==1?'checked':'') . ' value="1" />
                                <label for="linkilo_keywords_set_priority">' . __('Set priority for url insertion?', 'linkilo') . '</label>
                                <div class="linkilo_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div style="margin-left: 0px;">' . __('Setting a priority for the relate url will tell Linkilo which link to insert if it comes across a sentence that has keywords that match multiple relate urls. The relate url with the highest priority will be the one inserted in such a case.', 'linkilo') . '</div>
                                </div>
                                <div class="linkilo_keywords_priority_setting_container" style="' . ($item->set_priority==1?'display:block;':''). '">
                                    <input type="number" style="max-width: 60px;" name="linkilo_keywords_priority_setting" min="0" value="'. ((isset($item->priority_setting) && !empty($item->priority_setting)) ? $item->priority_setting : 0) .'" step="1"/>
                                </div>
                                <br>
                                <input type="hidden" name="linkilo_keywords_restrict_date" value="0" />
                                <input type="checkbox" id="linkilo_keywords_restrict_date_' . $item->id . '" name="linkilo_keywords_restrict_date" class="linkilo_keywords_restrict_date_checkbox" ' . ($item->restrict_date==1?'checked':'') . ' value="1" />
                                <label for="linkilo_keywords_restrict_date_' . $item->id . '">' . __('Add URL to feeds created after specific date', 'linkilo') . '</label>
                                <div class="linkilo_keywords_restricted_date-container" ' . (($date_restricted) ? 'style="display:block;"' : ''). '>
                                    <input type="date" name="linkilo_keywords_restricted_date" ' . ((!empty($item->restricted_date)) ? 'value="' . str_replace(' 00:00:00', '', $item->restricted_date) . '"': '') . '/>
                                </div>
                                <br>
                                ' . $cat_selector . '
                                <a href="javascript:void(0)" class="button-primary linkilo_keyword_local_settings_save" data-id="' . $item->id . '">Save</a>
                            </div>
                            <div class="progress_panel loader">
                                <div class="progress_count"></div>
                            </div>

                        </div>';
                            /*<div class="progress_panel_center">
                                '._e('Loading', 'linkilo').'
                            </div>*/
            case 'link':
                return $item->$column_name;
            case 'select_links':
                $possible_links = Linkilo_Build_RelateUrlKeyword::getPossibleLinksByKeyword($item->id);
                ob_start();
                include LINKILO_PLUGIN_DIR_PATH . '/templates/blocks/collapsible_relate_keywords.php';
                return ob_get_clean();
            case 'links':
                $links = $item->$column_name;
                ob_start();
                include LINKILO_PLUGIN_DIR_PATH . '/templates/blocks/collapsible_feeds.php';
                return ob_get_clean();
            case 'actions':
                return '<a href="javascript:void(0)" class="delete" data-id="' . $item->id . '">Delete</a>';
            default:
                return print_r($item, true);
        }
    }

    function get_sortable_columns()
    {
        return [
            'keyword' => ['keyword', false],
            'link' => ['link', false],
        ];
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
 
            if(in_array($column_name, array('select_links', 'links'), true)){
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