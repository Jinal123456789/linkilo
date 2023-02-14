<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Linkilo_Build_Table_DomainUrl
 */
class Linkilo_Build_Table_DomainUrl extends WP_List_Table
{
    function get_columns()
    {
        return [
            'host' => 'Domain Name',
            'posts' => 'Feeds',
            'links' => 'URLs',
        ];
    }

    function prepare_items()
    {
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : 20;
        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $search = !empty($_GET['s']) ? $_GET['s'] : '';

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Linkilo_Build_Console::getDomainsData($per_page, $page, $search);
        $this->items = $data['domains'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        switch($column_name) {
            case 'host':
                return '<a href="'.$item['protocol'] . $item[$column_name].'" target="_blank">'. $item['protocol'] . $item[$column_name].'</a>';
            case 'posts':
                $posts = $item[$column_name];

                $list = '<ul class="report_links">';
                foreach ($posts as $post) {
                    $list .= '<li>'
                                . $post->getTitle() . '<br>
                                <a href="' . admin_url('post.php?post=' . $post->id . '&action=edit') . '" target="_blank">[edit]</a> 
                                <a href="' . $post->getLinks()->view . '" target="_blank">[view]</a><br><br>
                              </li>';
                }
                $list .= '</ul>';

                return '<div class="linkilo-collapsible-wrapper">
  			                <div class="linkilo-collapsible linkilo-collapsible-static linkilo-links-count">'.count($posts).'</div>
  				            <div class="linkilo-content">'.$list.'</div>
  				        </div>';
            case 'links':
                $links = $item[$column_name];

                $list = '<ul class="report_links">';
                foreach ($links as $link) {
                    $list .= '<li>
                                <i data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="" data-url="'.base64_encode($link->url).'" class="linkilo_link_delete dashicons dashicons-no-alt"></i>
                                <div>
                                    <a href="' . $link->url . '" target="_blank">' . $link->url . '</a>
                                    <br>
                                    <a href="' . $link->post->getLinks()->view . '" target="_blank"><b>[' . $link->anchor . ']</b></a>
                                    <br>
                                    <a href="#" class="linkilo_edit_link" target="_blank">[' . __('Edit URL', 'linkilo') . ']</a>
                                    <div class="linkilo-domains-report-url-edit-wrapper">
                                        <input class="linkilo-domains-report-url-edit" type="text" value="' . $link->url . '">
                                        <button class="linkilo-domains-report-url-edit-confirm linkilo-domains-edit-link-btn" data-link_id="' . $link->link_id . '" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="' . $link->anchor . '" data-url="'.$link->url.'" data-nonce="' . wp_create_nonce('linkilo_report_edit_' . $link->post->id . '_nonce_' . $link->link_id) . '">
                                            <i class="dashicons dashicons-yes"></i>
                                        </button>
                                        <button class="linkilo-domains-report-url-edit-cancel linkilo-domains-edit-link-btn">
                                            <i class="dashicons dashicons-no"></i>
                                        </button>
                                    </div>
                                </div>
                            </li>';
                }
                $list .= '</ul>';

                return '<div class="linkilo-collapsible-wrapper">
  			                <div class="linkilo-collapsible linkilo-collapsible-static linkilo-links-count">'.count($links).'</div>
  				            <div class="linkilo-content">'.$list.'</div>
  				        </div>';
            default:
                return print_r($item, true);
        }
    }

    function extra_tablenav( $which ) {
        if ($which == "bottom") {
            ?>
            <div class="alignright actions bulkactions detailed_export">
                <a href="javascript:void(0)" class="button-primary csv_button" data-type="domains" id="linkilo_cvs_export_button">Featured CSV Report</a>
            </div>
            <?php
        }
    }
}
