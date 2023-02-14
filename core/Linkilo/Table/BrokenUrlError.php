<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Linkilo_Build_Table_BrokenUrlError
 */
class Linkilo_Build_Table_BrokenUrlError extends WP_List_Table
{
    function get_columns()
    {
        $options = get_user_meta(get_current_user_id(), 'report_options', true);

        $columns = array(
            'checkbox' => '<input type="checkbox" id="linkilo_check_all_errors" />',
            'post' => __('Post', 'linkilo'),
        );

        if (!empty($options['show_type']) && $options['show_type'] == 'on') {
            $columns['post_type'] = __('Post Type', 'linkilo');
        }

        $columns = array_merge($columns, array(
            'url' => __('Broken URL', 'linkilo'),
            'sentence' => __('Sentence', 'linkilo'),
            'type' => __('Type', 'linkilo'),
            'code' => __('Status', 'linkilo'),
            'created' => __('Discovered', 'linkilo'),
            'actions' => '',
        ));

        return $columns;
    }

    function prepare_items()
    {
        //pagination
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : 20;
        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : '';
        $post_id = isset($_REQUEST['post_id']) ? (int)$_REQUEST['post_id'] : 0;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Linkilo_Build_BrokenUrlError::getData($per_page, $page, $orderby, $order, $post_id);
        $this->items = $data['links'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        switch($column_name) {
            case 'checkbox':
                return '<input type="checkbox" data-id="' . $item->id . '" />';
            case 'url':
                return '<a class="linkilo-error-report-url" href="' . $item->$column_name . '" target="_blank">' . $item->$column_name . '</a>
                        <div class="linkilo-error-report-url-edit-wrapper">
                            <input class="linkilo-error-report-url-edit" type="text" value="' . $item->$column_name . '">
                            <button title="' . __('Confirm Edit', 'linkilo') . '" class="linkilo-error-report-url-edit-confirm linkilo-error-edit-link-btn">
                                <i class="dashicons dashicons-yes"></i>
                            </button>
                            <button title="' . __('Cancel Edit', 'linkilo') . '" class="linkilo-error-report-url-edit-cancel linkilo-error-edit-link-btn">
                                <i class="dashicons dashicons-no"></i>
                            </button>
                        </div>
                        <div class="row-actions">
                            <span class="ignore">' . $item->ignore_link . '</span> | 
                            <span class="edit">' . $item->edit_link . '</span>
                        </div>';
            case 'created':
                return date('d M Y (H:i)', strtotime($item->created));
            case 'code':
                $class = ($item->code > 403 && $item->code < 500) ? 'code-red': 'code-orange';
                return '<span class="' . $class . '">' . Linkilo_Build_BrokenUrlError::getCodeMessage($item->code, true) . '</span>';
            case 'type':
                return $item->internal ? 'internal' : 'external';
            case 'actions':
                return $item->delete_icon;
            default:
                return $item->{$column_name};
        }
    }

    function get_sortable_columns()
    {
        return [
            'post' => ['post', false],
            'type' => ['internal', false],
            'code' => ['code', false],
            'created' => ['created', false],
        ];
    }

    function extra_tablenav( $which ) {
        global $wpdb;

        $post_types = get_post_types(array('public' => true));
        $post_types = array_values($post_types);
        $taxonomies = get_object_taxonomies($post_types);

        $taxes = array();
        $tax_index = array();
        foreach($post_types as $ind_post_type){
            $taxonomies = get_object_taxonomies($ind_post_type);
            if(!empty($taxonomies)){
                foreach($taxonomies as $tax){
                    $taxo = get_taxonomy($tax);
                    if($taxo->hierarchical){
                        $taxes[] = $taxo->name;
                        $tax_index[$ind_post_type][] = array($taxo->name => array());
                    }
                }
            }
        }

        $taxonomies2 = get_categories(array('taxonomy' => $taxes, 'hide_empty' => false));
        $options = '';
        $cat = isset($_GET['category']) ? (int)$_GET['category']: 0;

        if(!empty($taxonomies2)){
            foreach($taxonomies2 as $tax){
                foreach($tax_index as $ind_post_type => $tax_names){
                    foreach($tax_names as $key => $tax_name){
                        if(isset($tax_name[$tax->taxonomy])){
                            $selected = $tax->cat_ID===(int)$cat?' selected':'';
                            $options .= '<option value="' . $tax->cat_ID . '" ' . $selected . ' class="linkilo_filter_post_type ' . $ind_post_type . '">' . $tax->name . '</option>';
                        }
                    }
                }
            }
        }

        $codes = [];
        $result = $wpdb->get_results("SELECT DISTINCT code FROM {$wpdb->prefix}linkilo_broken_links ORDER BY code ASC");
        foreach ($result as $item) {
            $codes[] = $item->code;
        }
        $current_codes = !empty($_GET['codes']) ? explode(',', $_GET['codes']) : array(6, 7, 28, 404, 451, 500, 503, 925);

        if ( $which == "top" ){
            ?>
            <div class="alignleft actions bulkactions" id="error_table_code_filter">
                <input type="hidden" class="current-post" value="<?php echo (isset($_GET['post_id']) && !empty($_GET['post_id'])) ? (int) $_GET['post_id']: 0; ?>">
                <a href="javascript:void(0)" id="linkilo_error_delete_selected" class="button-primary button-disabled">Delete Selected</a>
                <div class="codes">
                    <div class="item closed">Status Codes <i class="dashicons dashicons-arrow-down"></i><i class="dashicons dashicons-arrow-up"></i></div>
                    <?php foreach ($codes as $code) : ?>
                        <div class="item">
                            <input type="checkbox" name="code" data-code="<?= $code ?>" <?= in_array($code, $current_codes) ? 'checked' : '' ?>> <?= Linkilo_Build_BrokenUrlError::getCodeMessage($code, true); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <span class="button-primary" id="linkilo_error_filter">Search</span>
            </div>

            <?php
            $post_type = !empty($_GET['post_type']) ? $_GET['post_type'] : 0;
            $cat = !empty($_GET['category']) ? $_GET['category'] : 0;
            ?>
            <div class="alignright actions bulkactions" id="linkilo_error_table_post_filter" style="padding-right:0px;">
                <!--filter by post type-->
                <select name="post_type" class="filter-by-type">
                    <option value="0">All types</option>
                    <?php foreach (Linkilo_Build_AdminSettings::getAllTypes() as $type) : ?>
                        <option value="<?=$type?>" <?=$type===$post_type?' selected':''?>><?=ucfirst($type)?></option>
                    <?php endforeach; ?>
                </select>
                <select name="category" class="filter-by-type">
                    <option value="0">All categories</option>
                    <?php echo $options; ?>
                    <?php /*foreach (get_categories() as $category) : ?>
                        <option value="<?=$category->cat_ID?>" <?=$category->cat_ID===(int)$cat?' selected':''?>><?=$category->name?></option>
                    <?php endforeach; */ ?>
                </select>
                <!--/filter by post type-->
                <span class="button-primary">Filter Posts</span>
                <input type="hidden" class="post-filter-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'linkilo_filter_nonce'); ?>">
            </div>
            <?php
        }
    }
}
