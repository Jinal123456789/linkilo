<?php

if (!class_exists('WP_List_Table')) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Linkilo_Build_Table_UrlRecord extends WP_List_Table
{

    function __construct()
    {
        parent::__construct(array(
            'singular' => __('Linking Stats', 'linkilo'),
            'plural' => __('Linking Stats', 'linkilo'),
            'ajax' => false
        ));

        $this->prepare_items();
    }

    function column_default($item, $column_name)
    {
        if ($column_name == 'post_type') {
            return $item['post']->getType();
        }

        if (!array_key_exists($column_name, $item)) {
            return "<i>(not set)</i>";
        }

        if($column_name === 'organic_traffic'){
            return '<ul>
                        <li>' . __('Clicks: ', 'linkilo') . $item['organic_traffic'] . '</li>
                        <li>' . __('AVG Position: ', 'linkilo') . $item['position'] . '</li>
                    </ul>';
        }

        $v = $item[$column_name];
        if (!$v) {
            $v = 0;
        }

        $v_num = $v;

        $post_id = $item['post']->id;
        if (in_array($column_name, Linkilo_Build_UrlRecord::$meta_keys)) {
            $opts = [];
            $opts['target'] = '_blank';
            $opts['style'] = 'text-decoration: underline';

            $opts['data-linkilo-report-post-id'] = $post_id;
            $opts['data-linkilo-report-type'] = $column_name;
            $opts['data-linkilo-report'] = 1;

            $v = "<span class='linkilo_ul'>$v</span>";

            switch ($column_name) {
                case LINKILO_TOTAL_COUNT_OF_INCOMING_INTERNAL_LINKS:
                    $v = "<div class='incoming-link-count'>&#x2799;";
                    $links_data = $item['post']->getIncomingInternalLinks();
                    $title = __('Incoming Inner URLs', 'linkilo');
                    break;

                case LINKILO_TOTAL_COUNT_OF_OUTGOING_EXTERNAL_LINKS:
                    $v = "&#x279A;";
                    $links_data = $item['post']->getOutboundExternalLinks();
                    $title = __('Outgoing Outer URLs', 'linkilo');
                    break;

                case LINKILO_TOTAL_COUNT_OF_OUTGOING_INTERNAL_LINKS:
                    $v = "&#x2799;";
                    $links_data = $item['post']->getOutboundInternalLinks();
                    $title = __('Outgoing Inner URLs', 'linkilo');
                    break;
            }


            if ($v_num > 0 || LINKILO_TOTAL_COUNT_OF_INCOMING_INTERNAL_LINKS == $column_name) {

            } else {
                $v = "<div title='$title' style='margin:0px; text-align: center; padding: 5px'>0 $v</div>";
            }

            $get_all_links = Linkilo_Build_AdminSettings::showAllLinks();

            if ($v_num > 0 || LINKILO_TOTAL_COUNT_OF_INCOMING_INTERNAL_LINKS == $column_name) {
                $rep = '';

                if (is_array($links_data)) {
                    $rep .= '<ul class="report_links">';

                    switch ($column_name) {
                        case 'linkilo_links_incoming_internal_count':
                            $count = 0;
                            foreach ($links_data as $link) {
                                if (!Linkilo_Build_RecordFilter::linksLocation() || $link->location == Linkilo_Build_RecordFilter::linksLocation()) {
                                    $count++;
                                    if (!empty($link->post)) {
                                        $rep .= '<li>
                                                    <i class="linkilo_link_delete dashicons dashicons-no-alt" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="'.base64_encode($link->anchor).'" data-url="'.base64_encode($link->url).'"></i>
                                                    <div>
                                                        <div style="margin: 3px 0;"><b>Post Title:</b> ' . $link->post->getTitle() . '</div>
                                                        <div style="margin: 3px 0;"><b>Anchor Text:</b> ' . strip_tags($link->anchor) . '</div>';
                                        $rep .= ($get_all_links) ? '<div style="margin: 3px 0;"><b>Link Location:</b> ' . $link->location . '</div>' : '';
                                        $rep .=         '<a href="' . admin_url('post.php?post=' . $link->post->id . '&action=edit') . '" target="_blank">[edit]</a> 
                                                        <a href="' . $link->post->getLinks()->view . '" target="_blank">[view]</a>
                                                        <br>
                                                    </div>
                                                </li>';
                                    } else {
                                        $rep .= '<li><div><b>[' . strip_tags($link->anchor) . ']</b><br>[' . $link->location . ']<br><br></div></li>';
                                    }
                                }
                            }
                            $v .= '<span class="linkilo_ul">' . $count . '</span></div>' . '<a class="add-internal-links" href="'.$item['links_incoming_page_url'].'" style="text-decoration: underline;" data-linkilo-report-post-id="1" data-linkilo-report-type="linkilo_links_incoming_internal_count" data-linkilo-report="1">Add</a>';
                            break;
                        case 'linkilo_links_outgoing_internal_count':
                        case 'linkilo_links_outgoing_external_count':
                            $count = 0;
                            foreach ($links_data as $link) {
                                if (!Linkilo_Build_RecordFilter::linksLocation() || $link->location == Linkilo_Build_RecordFilter::linksLocation()) {
                                    $count++;
                                    $rep .= '<li>
                                                <i class="linkilo_link_delete dashicons dashicons-no-alt" data-post_id="' . $item['post']->id . '" data-post_type="' . $item['post']->type . '" data-anchor="' . base64_encode($link->anchor) . '" data-url="' . base64_encode($link->url) . '"></i>
                                                <div>
                                                    <div style="margin: 3px 0;"><b>Link:</b> <a href="' . $link->url . '" target="_blank" style="text-decoration: underline">' . $link->url . '</a></div>
                                                    <div style="margin: 3px 0;"><b>Anchor Text:</b> ' . strip_tags($link->anchor) . '</div>';
                                    $rep .= ($get_all_links) ? '<div style="margin: 3px 0;"><b>Link Location:</b> ' . $link->location . '</div>' : '';
                                    $rep .=     '</div>
                                            </li>';
                                }
                            }
                            $v = '<span class="linkilo_ul">' . $count . '</span> ' . $v;
                            break;
                    }

                    $rep .= '</ul>';
                }

                $e_rt = esc_attr($column_name);
                $e_p_id = esc_attr($post_id);

                $v = "<div class='linkilo-collapsible-wrapper'>
  			            <div class='linkilo-collapsible linkilo-collapsible-static linkilo-links-count' title='$title' data-linkilo-report-type='$e_rt' data-linkilo-report-post-id='$e_p_id' >$v</div>
  				        <div class='linkilo-content'>
          			        $rep
  				        </div>
  				    </div>";
            }

        }

        return $v;
    }

    function get_columns()
    {
        $columns = ['post_title' => __('Feed Title', 'linkilo')];
        $options = get_user_meta(get_current_user_id(), 'report_options', true);

        if (!empty($options['show_date']) && $options['show_date'] == 'on') {
            $columns['date'] = __('Published', 'linkilo');
        }

        if (!empty($options['show_type']) && $options['show_type'] == 'on') {
            $columns['post_type'] = __('Type', 'linkilo');
        }

        if (!empty($options['show_traffic']) && $options['show_traffic'] == 'on' && !empty(Linkilo_Build_GoogleSearchConsole::is_authenticated())) {
            $columns['organic_traffic'] = __('Organic Traffic', 'linkilo');
        }

        $columns = array_merge($columns, [
            LINKILO_TOTAL_COUNT_OF_INCOMING_INTERNAL_LINKS => __('Incoming Inner URLs', 'linkilo'),
            LINKILO_TOTAL_COUNT_OF_OUTGOING_INTERNAL_LINKS => __('Outgoing Inner URLs', 'linkilo'),
            LINKILO_TOTAL_COUNT_OF_OUTGOING_EXTERNAL_LINKS => __('Outgoing Outer URLs', 'linkilo'),
        ]);

        return $columns;
    }

    function column_post_title($item)
    {
        $post = $item['post'];

        $actions = [];

        $title = '<a href="' . $post->getLinks()->edit . '" class="row-title">' . esc_attr($post->getTitle()) . '</a>';
        $actions['view'] = '<a target=_blank  href="' . $post->getLinks()->view . '">View Page</a>';
        $actions['edit'] = '<a target=_blank href="' . $post->getLinks()->edit . '">Edit Page</a>';
        /*  Commented unusable link
        $actions['export'] = '<a target=_blank href="' . $post->getLinks()->export . '">Export data for support</a>';
        $actions['excel_export'] = '<a target=_blank href="' . $post->getLinks()->excel_export . '">Export to Excel</a>';
        */
        $actions['refresh'] = '<a href="' . $post->getLinks()->refresh . '">Refresh links count</a>';

        if(isset($_GET['orphaned'])){
            $actions['ignore-orphaned'] = '<a href="#" class="linkilo-ignore-orphaned-post" data-post-id="' . $post->id . '" data-type="' . $post->type . '" data-nonce="'. wp_create_nonce('ignore-orphaned-post-' . $post->id) .'">Ignore orphaned post</a>';
        }

        return $title . $this->row_actions($actions);
    }


    function get_sortable_columns()
    {
        $cols = $this->get_columns();

        $sortable_columns = [];

        foreach ($cols as $col_k => $col_name) {
            $sortable_columns[$col_k] = [$col_k, false];
        }

        return $sortable_columns;
    }

    function prepare_items()
    {
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : 20;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $start = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 0;
        $orderby = (isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : '';
        $order = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        $search = (!empty($_REQUEST['s'])) ? sanitize_text_field($_REQUEST['s']) : '';
        $orphaned = !empty($_REQUEST['orphaned']);

        if (empty($orderby)) {
            $saved_order = get_transient('linkilo_link_report_order');
            if (!empty($saved_order)) {
                $saved_order = explode(';', $saved_order);
                if (count($saved_order) == 2) {
                    $orderby = !empty($saved_order[0]) ? $saved_order[0] : '';
                    $order = !empty($saved_order[1]) ? $saved_order[1] : 'DESC';
                }
            }
        }

        if (!empty($orderby)) {
            set_transient('linkilo_link_report_order', $orderby . ';' . $order);
        }

        $data = Linkilo_Build_UrlRecord::getData($start, $orderby, $order, $search, $limit = $per_page, $orphaned);

        $total_items = $data['total_items'];
        $data = $data['data'];

        $this->items = $data;

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }

    /**
     * Displays the search box.
     *
     * @param string $text     The 'submit' button label.
     * @param string $input_id ID attribute value for the search input field.
     */
    public function search_box( $text, $input_id ) {
        if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
            return;
        }

        $input_id = $input_id . '-search-input';

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
        }
        if ( ! empty( $_REQUEST['order'] ) ) {
            echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
        }
        if ( ! empty( $_REQUEST['post_mime_type'] ) ) {
            echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
        }
        if ( ! empty( $_REQUEST['detached'] ) ) {
            echo '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
        }
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" placeholder="Focus Keyword/URL" />
            <?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
        </p>
        <?php
    }

    function extra_tablenav( $which ) {
        if ($which == "top") {
            $post_type = !empty($_GET['post_type']) ? $_GET['post_type'] : 0;
            $cat = !empty($_GET['category']) ? $_GET['category'] : 0;
            $location = !empty($_GET['location']) ? $_GET['location'] : null;
            $filter_type = !empty($_GET['filter_type']) ? $_GET['filter_type'] : 0;
            $link_type = !empty($_GET['link_type']) ? $_GET['link_type'] : null;
            $min = !empty($_GET['link_min_count']) ? $_GET['link_min_count'] : 0;
            $max = array_key_exists('link_max_count', $_GET) ? $_GET['link_max_count'] : null;

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
            ?>
            <style>
                <?php
                switch ($filter_type) {
                    case '2':
                        // do nothing to hide the inputs
                        break;
                    case '1':
                        echo '.filter-by-type{display:none;}';
                        break;
                    case '0':
                    default:
                        echo '.filter-by-count{display:none;}';
                    break;
                }
                ?>
            </style>
            <div class="alignright actions bulkactions" id="linkilo_links_table_filter">
                <select name="filter_type">
                    <option value="0" <?php selected($filter_type, '0'); ?>><?php _e('Post Type', 'linkilo'); ?></option>
                    <option value="1" <?php selected($filter_type, '1'); ?>><?php _e('Link Count', 'linkilo'); ?></option>
                    <option value="2" <?php selected($filter_type, '2'); ?>><?php _e('Post Type & Link Count', 'linkilo'); ?></option>
                </select>
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
                </select>
                <?php if (Linkilo_Build_AdminSettings::showAllLinks()) : ?>
                    <select name="location" class="filter-by-type">
                        <option value="0">All locations</option>
                        <?php foreach (['header', 'content', 'footer'] as $loc) : ?>
                            <option value="<?=$loc?>" <?=$loc==$location?' selected':''?>><?=$loc?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <!--/filter by post type-->
                <!--filter by link counts-->
                <select name="link_type" class="filter-by-count">
                    <option value="incoming-internal"  <?php selected($link_type, 'incoming-internal'); ?>><?php _e('Incoming Inner URLs', 'linkilo'); ?></option>
                    <option value="outgoing-internal" <?php selected($link_type, 'outgoing-internal'); ?>><?php _e('Outgoing Inner URLs', 'linkilo'); ?></option>
                    <option value="outgoing-external" <?php selected($link_type, 'outgoing-external'); ?>><?php _e('Outgoing Outer URLs', 'linkilo'); ?></option>
                </select>
                <label for="linkilo_link_min_count" class="filter-by-count">Min</label>
                <input id="linkilo_link_min_count"type="number" name="link_min_count" class="filter-by-count" min="0" value="<?php echo $min; ?>" style="max-width: 70px;">
                <label for="linkilo_link_max_count" class="filter-by-count">Max</label>
                <input id="linkilo_link_max_count" type="number" name="link_max_count" class="filter-by-count" min="0" <?php if(null !== $max){ echo 'value="' . $max . '"';} ?> style="max-width: 70px;">
                <!--/filter by link counts-->
                <span class="button-primary">Filter</span>
                <input type="hidden" class="post-filter-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'linkilo_filter_nonce'); ?>">
            </div>
            <?php
        }
    }
}
