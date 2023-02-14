<?php /*
<table class="wp-list-table widefat fixed striped posts tbl_keywords_x js-table linkilo-outgoing-links" id="tbl_keywords">
    <thead>
        <tr>
            <th>
                <div>
                    <b>
                        <?php  _e('Linkilo Related Posts ', 'linkilo');  ?> 
                    </b>
                </div>
            </th>
            <th>
                <div>
                    <br />
                    <b><?php _e('Post Excerpt', 'linkilo'); ?></b>
                </div>
            </th>
        </tr>
    </thead>
    <tbody id="the-list">

        <?php foreach ($related_posts as $index => $post_obj) : ?>
            <tr>
                <td class="sentences">
                    <div class="sentence top-level-sentence">
                        <span class="linkilo_sentence_with_anchor">
                            <?php echo $post_obj->post_title; ?>        
                        </span>
                    </div>
                </td>
                <td>
                    <div class="linkilo-collapsible-wrapper">
                        <div class="linkilo-collapsible linkilo-collapsible-static linkilo-links-count">
                            <div style="opacity:1">
                                <?php echo "View content";
                                //$post_obj->ID; ?>
                            </div>
                        </div>
                        <div class="linkilo-content" style="display: none;">
                            <p>
                                <?php 
                                    $excerpt = wp_trim_words( $post_obj->post_content, 50 );
                                    echo $excerpt; ?>
                            </p>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
*/ ?>