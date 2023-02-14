<?php

/**
 * Work with keywords
 */
class Linkilo_Build_RelatedMetaPosts
{
    private $relate_meta_post_types;
    private $enable_disable_meta_posts;
    private $limit;
    private $order;

    public function __construct() {

        $this->enable_disable_meta_posts = Linkilo_Build_AdminSettings::getRelatedMetaPostEnableDisable();
        if ($this->enable_disable_meta_posts == "1") {
            // enable/show
            $post_types_active = Linkilo_Build_AdminSettings::getPostTypes();

            $this->relate_meta_post_types = $post_types_active;

            self::create_fulltext_indices();

            $posts_limit = Linkilo_Build_AdminSettings::getRelatedMetaPostLimit();
            $posts_order = Linkilo_Build_AdminSettings::getRelatedMetaPostOrder();

            $this->limit = intval($posts_limit);
            $this->order = $posts_order;

            add_action( 'add_meta_boxes', array($this, 'linkilo_meta_box_side_bar_settings'));
            self::add_default_option();
            add_action('wp_ajax_update_related_posts_same_title', array($this, 'ajax_update_related_posts_same_title'));
        }
    }
    public function ajax_update_related_posts_same_title()
    {
        $option = $_POST['same_title_option'];

        if (isset($_POST['same_title_option']) && !empty($_POST['same_title_option'])) {
            update_option('linkilo_relate_meta_post_same_title', $option);
            $return = array(
                'flag'  => 'success',
            );
        }else{
            $return = array(
                'flag'  => 'error',
                'msg'   => 'Option not updated',
            );
        }
        wp_send_json($return);
    }

    public function add_default_option()
    {
        $same_title_option = get_option('linkilo_relate_meta_post_same_title');
        if ($same_title_option == false && empty($same_title_option)) {
            update_option('linkilo_relate_meta_post_same_title', 'hide');
        }
    }
    public function linkilo_meta_box_side_bar_settings()
    {
        $screens = get_option('linkilo_relate_meta_post_types',true);
        if (!empty($screens) && sizeof($screens) > 0) {
            $context = 'side';
            $priority = 'default';

            foreach ( $screens as $screen ) {
                add_meta_box(
                    'linkilo-related-meta-posts-settings', // $id
                    __( 'Linkilo Related Posts', 'linkilo' ), // $title
                    array($this, 'linkilo_meta_box_side_bar_settings_callback'), // $callback
                    $screen, // $screen
                    $context, // $context  'normal', 'side', and 'advanced'
                    $priority // 'high', 'core', 'default', or 'low'
                );
            }
        }
    }
    public function linkilo_meta_box_side_bar_settings_callback( $post ) {

        $post_obj = new Linkilo_Build_Model_Feed($post->ID);
        $related_urls = $this->linkilo_related_meta_posts($post_obj);

        if (!empty($related_urls) && sizeof($related_urls) > 0) {
            $loop_count = 0;
            if ($this->order == 'random') {
                $this->limit = $this->limit / 3;
                shuffle($related_urls); // shuffle option
            }

            echo '<p id="copy_url_alert" style="display:none">Url Copied!</p>';
            foreach ($related_urls as $index => $post) {
                if ($this->limit != $loop_count) {
                    $link = get_permalink($post->ID);
                    $title = $post->post_title;
                    echo '
                    <div style="display: flex;margin-bottom: 10px;max-width: 250px;"> 
                    <div style="margin-top: 4px; padding-right: 10px;">
                    <button title="Copy Url" onclick="linkilo_copy_related_posts(\'related_posts_url_'.$post->ID.'\')" style="cursor: pointer;">
                    <span class="dashicons dashicons-admin-page"></span>
                    </button>
                    </div>

                    <div style="font-size: 15px;">
                    <a 
                    href="javascript:void(0);" 
                    id="related_posts_url_'.$post->ID.'"
                    data-post_link="'.$link.'"
                    >
                    '.$title.'
                    </a>
                    </div>
                    </div>
                    ';
                    $loop_count++;
                }
            }
            ?>
            <script>
                function linkilo_copy_related_posts(copyId){
                    let inputElement = document.createElement("input");
                    inputElement.type = "text";
                    // let copyText = document.getElementById(copyId).innerHTML;
                    let copyText = document.getElementById(copyId).dataset.post_link;
                    inputElement.value = copyText;
                    document.body.appendChild(inputElement);
                    inputElement.select();
                    document.execCommand('copy');
                    document.body.removeChild(inputElement);

                    document.getElementById("copy_url_alert").style.display = "block";
                    setTimeout(function(){
                        document.getElementById("copy_url_alert").style.display = "none";
                    }, 1000);
                }
            </script>
            <?php
        }else{
            echo '<p class="components-form-token-field__help">No related urls found for this post.</p>';
        }
    }

    public function create_fulltext_indices()
    {
        global $wpdb;
        $post_table = $wpdb->prefix . 'posts';

        // Check this sample index name PRIMARY
        $index_one = 'linkilo_related';
        $index_two = 'linkilo_related_title';
        $get_indexes = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND (INDEX_NAME = %s OR INDEX_NAME = %s)", 
                DB_NAME, 
                $post_table,
                $index_one,
                $index_two
            )
        );

        if (sizeof($get_indexes) == 0 && empty($get_indexes)) {
            $sql1 = "ALTER TABLE {$post_table} ADD FULLTEXT linkilo_related (post_title, post_content);";
            $sql2 = "ALTER TABLE {$post_table} ADD FULLTEXT linkilo_related_title (post_title);";

            $result1 = $wpdb->query($sql1);
            $result2 = $wpdb->query($sql2);

            if (false === $result1 || false === $result2) {
                $class = 'notice notice-error';
                $message = __( 'Query error', 'linkilo' );
                add_action( 'admin_notices', array($this,'query_admin_notice'));
            }else{
                $class = 'notice notice-success';
                $message = __( 'Success ', 'linkilo' );
                add_action( 'admin_notices', array($this,'query_admin_notice'));
            }

        }
        /*else{
            $sql1  = "ALTER TABLE {$post_table} DROP INDEX linkilo_related;";
            $sql2 = "ALTER TABLE {$post_table} DROP INDEX linkilo_related_title;";
        }*/

        /*echo "<pre>";
        print_r(sizeof($get_indexes));
        echo "</pre>";
        die();*/

    }
    public function query_admin_notice($class, $message) {

        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
    }



    /*public function get_matching_screens($action_name)
    {
        return $this->matching_screens;
    }*/


    public function linkilo_related_meta_posts($post)
    {
        $same_title_option = get_option('linkilo_relate_meta_post_same_title'); 

        $this_post_id = $post->id; 
        $get_post_by_id   = get_post( $this_post_id );
        $get_content_of_post =  apply_filters( 'the_content', $get_post_by_id->post_content );
        $get_curr_post_type = $get_post_by_id->post_type;


        /* Check for post if password protected */
        if ( post_password_required( $get_post_by_id ) ) {
            $output = __( 'There is no excerpt because this is a protected post.', 'contextual-related-posts' );
            echo $output;
            die();
        }

        /*Create excerpt from content*/
        if ($same_title_option == 'show') {
            $match_fields_content = array(
                str_ireplace( ' from', '', $get_post_by_id->post_title ),
            );            
        }else{
            $match_fields_content = array(
                str_ireplace( ' from', '', $get_post_by_id->post_title ),
            );
            if (!empty($get_content_of_post)) {
                $get_content_of_post = wp_strip_all_tags( strip_shortcodes( $get_content_of_post ) );
                $limited_content = wp_trim_words( $get_content_of_post, 100 );
                $match_fields_content[] = str_ireplace( ' from', '', $limited_content );
            }
        }



        $fulltext_to_match = implode( ' ', $match_fields_content );
        $real_time_date = current_time( 'mysql' );

        $matching_post_types = "";
        $get_post_types = get_option('linkilo_relate_meta_post_types_include',true);

        if (!empty($get_post_types) || sizeof($get_post_types) > 0 )
        {
            $matching_post_types = "'".implode("', '",$get_post_types)."'";
        }

        $related_posts = "";

        if (!empty($matching_post_types)) :
            global $wpdb;

            if ($same_title_option == "show" || empty($get_content_of_post)) {
                $match_cols = "MATCH ($wpdb->posts.post_title)";
            }else{
                $match_cols = "MATCH ($wpdb->posts.post_title,$wpdb->posts.post_content)";
            }

            if ($this->order == 'date') {
                $orderby = " ORDER BY $wpdb->posts.post_date DESC LIMIT 0, " . $this->limit;
            }elseif ($this->order == 'random') {
                $this->limit = $this->limit * 3;
                $orderby = " ORDER BY score DESC LIMIT 0, " . $this->limit;
            }elseif ($this->order == 'relevance') {
                $orderby = " ORDER BY score DESC LIMIT 0, " . $this->limit;
            }else{
                $orderby = " ORDER BY score DESC LIMIT 0, " . $this->limit;
            }

            $related_posts = $wpdb->get_results("SELECT $wpdb->posts.*, $match_cols AGAINST ('$fulltext_to_match') as score FROM $wpdb->posts WHERE 1=1 AND ( $wpdb->posts.post_date <= '$real_time_date' ) AND $wpdb->posts.ID NOT IN ($this_post_id) AND $wpdb->posts.post_type IN ($matching_post_types) AND (($wpdb->posts.post_status = 'publish' OR $wpdb->posts.post_status = 'inherit')) AND $match_cols AGAINST ('$fulltext_to_match')".$orderby);
        endif;
        return $related_posts;
    }

}
