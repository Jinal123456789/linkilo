<?php if (get_option('linkilo_disable_outgoing_suggestions')) : ?>
    <div style="min-height: 200px">
        <p style="display: inline-block;"><?php _e('Outbound Link Suggestions Disabled', 'linkilo') ?></p>
        <a style="float: right; margin: 15px 0px;" href="<?=admin_url("admin.php?{$post->type}_id={$post->id}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode($post->getLinks()->edit))?>" class="button-primary">Add Incoming links</a>
    </div>
    <?php else : ?>
        <div class="linkilo_notice" id="linkilo_message" style="display: none">
            <p></p>
        </div>
        <div class="best_keywords outgoing">
            <?=Linkilo_Build_Root::showVersion()?>
            <p>
                <div style="margin-bottom: 15px;">
                    <input style="margin: 0px;" type="checkbox" name="same_category" id="field_same_category" <?=!empty($same_category) ? 'checked' : ''?>> <label for="field_same_category">Show Links Based on Category</label>
                    <br>
                    <input style="margin: 0px;" type="checkbox" name="same_tag" id="field_same_tag" <?=!empty($same_tag) ? 'checked' : ''?>> <label for="field_same_tag">Show Links Based on Tags</label>
                    <?php if ($same_category && !empty($categories)) : ?>
                        <br>
                        <select name="linkilo_selected_category">
                            <option value="0">All categories</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?=$category->term_id?>" <?=$category->term_id==$selected_category?'selected':''?>><?=$category->name?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <?php if ($same_tag && !empty($tags)) : ?>
                        <br>
                        <select name="linkilo_selected_tag">
                            <option value="0">All tags</option>
                            <?php foreach ($tags as $tag) : ?>
                                <option value="<?=$tag->term_id?>" <?=$tag->term_id==$selected_tag?'selected':''?>><?=$tag->name?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <br>
                    <input style="margin: 0px;" type="checkbox" name="same_title" id="field_same_title" <?= $same_title_checked; ?>> <label for="field_same_title">Show Links Based on Title Tags</label>
                </div>
                <!-- Commented unusable link <a href="<?=$post->getLinks()->export?>" target="_blank">Export data for support</a><br> -->
                <!-- Commented unusable link <a class="cst-href-clr" href="<?=$post->getLinks()->excel_export?>" target="_blank">Export to Excel</a> -->
            </p>
            <button class="sync_linking_keywords_list button-primary cst-btn-clr" data-id="<?=esc_attr($post->id)?>" data-type="<?=esc_attr($post->type)?>"  data-page="outgoing">
                <?php _e('Save Changes', 'linkilo') ?>
                    <?php
                        /*_e('Insert links into', 'linkilo'); 
                        echo ($post->type == 'term') ? 'description' : 'post';*/
                    ?>
                </button>
            <!-- Commented unusable link <a href="<?=admin_url("admin.php?{$post->type}_id={$post->id}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode($post->getLinks()->edit))?>" target="_blank" class="linkilo_incoming_links_button button-primary">Add Incoming links</a> -->

            <br/>
            <?php
            /*<!-- Linkilo related meta posts table -->
            <div>
                <?php 
                    if (
                        !empty($related_posts) && 
                        in_array($match_screen_type, $get_post_types)
                    ) : 
                ?>
                    <div>
                        <?php require LINKILO_PLUGIN_DIR_PATH . 'templates/table_meta_related_posts.php'; ?>
                    </div>
                <?php endif; ?>
            </div><!-- Linkilo related meta posts table ends-->*/ ?>
            <?php if (!empty($phrase_groups)){ ?>
                <br/>
                <div>
                    <label for="linkilo-outgoing-daterange" style="font-weight: bold; font-size: 16px !important; margin: 18px 0 8px; display: block; display: inline-block;">
                            <?php //_e('Filter Displayed Posts by Published Date', 'linkilo'); ?> 
                            <?php _e('Show Links Based On Published Date', 'linkilo'); ?> 
                        </label> 
                        <br/>
                    <input id="linkilo-outgoing-daterange" type="text" name="daterange" class="linkilo-date-range-filter" value="<?php echo '01/01/2000 - ' . date('m/d/Y', strtotime('today')); ?>">
                </div>
                <script>
                    var rows = jQuery('tr[data-linkilo-sentence-id]');
                    jQuery('#linkilo-outgoing-daterange').on('apply.daterangepicker, hide.daterangepicker', function(ev, picker) {
                        jQuery(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
                        var start = picker.startDate.unix();
                        var end   = picker.endDate.unix();

                        rows.each(function(index, element){
                            var suggestions = jQuery(element).find('.dated-outgoing-suggestion');
                            var first = true;
                            suggestions.each(function(index, element2){
                                var elementTime = jQuery(element2).data('linkilo-post-published-date');
                        var checkbox = jQuery(element2).find('input'); // linkilo_dropdown checkbox for the current suggestion, not the suggestion's checkbox

                        if(!start || (start < elementTime && elementTime < end)){
                            jQuery(element2).removeClass('linkilo-outgoing-date-filtered');

                            // check the first visible suggested post 
                            if(first && checkbox.length > 0){
                                checkbox.trigger('click');
                                first = false;
                            }
                        }else{
                            jQuery(element2).addClass('linkilo-outgoing-date-filtered');

                            // if this is a suggestion in a collapsible box, uncheck it
                            if(checkbox.length > 0){
                                checkbox.prop('checked', false);
                            }
                        }
                    });

                    // if all of the suggestions have been hidden
                    if(suggestions.length === jQuery(element).find('.dated-outgoing-suggestion.linkilo-outgoing-date-filtered').length){
                        // hide the suggestion row and uncheck it's checkboxes
                        jQuery(element).css({'display': 'none'});
                        jQuery(element).find('.chk-keywords').prop('checked', false);
                    }else{
                        // if not, make sure the suggestion row is showing
                        jQuery(element).css({'display': 'table-row'});
                    }
                });

                // handle the results of hiding any posts
                handleHiddenPosts();
            });

                    jQuery('#linkilo-outgoing-daterange').on('cancel.daterangepicker', function(ev, picker) {
                        jQuery(this).val('');
                        jQuery('.linkilo-outgoing-date-filtered').removeClass('linkilo-outgoing-date-filtered');
                    });

                    jQuery('#linkilo-outgoing-daterange').daterangepicker({
                        autoUpdateInput: false,
                        linkedCalendars: false,
                        locale: {
                            cancelLabel: 'Clear'
                        }
                    });

            /**
             * Handles the table display elements when the date range changes
             **/
             function handleHiddenPosts(){
                if(jQuery('.chk-keywords:visible').length < 1){
                    // hide the table elements
                    jQuery('.wp-list-table thead, .sync_linking_keywords_list, .linkilo_incoming_links_button').css({'display': 'none'});
                    // make sure the "Check All" box is unchecked
                    jQuery('.incoming-check-all-col input, #select_all').prop('checked', false);
                    // show the "No matches" message
                    jQuery('.linkilo-no-posts-in-range').css({'display': 'table-row'});
                }else{
                    // show the table elements
                    jQuery('.wp-list-table thead').css({'display': 'table-header-group'});
                    jQuery('.sync_linking_keywords_list, .linkilo_incoming_links_button').css({'display': 'inline-block'});
                    // hide the "No matches" message
                    jQuery('.linkilo-no-posts-in-range').css({'display': 'none'});
                }
            }
        </script>
    <?php } ?>
    <?php require LINKILO_PLUGIN_DIR_PATH . 'templates/table_recommendations.php'; ?>
</div>
<br>
<button class="sync_linking_keywords_list button-primary cst-btn-clr" data-id="<?=esc_attr($post->id)?>" data-type="<?=esc_attr($post->type)?>"  data-page="outgoing">
    <?php _e('Save Changes', 'linkilo') ?>
    <?php
        /*_e('Insert links into', 'linkilo'); 
        echo ($post->type == 'term') ? 'description' : 'post';*/
    ?> 
</button>
<!-- Commented unusable link <a href="<?=admin_url("admin.php?{$post->type}_id={$post->id}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI']))?>" target="_blank" class="linkilo_incoming_links_button button-primary">Add Incoming links</a> -->
<br>
<br>
<?php endif; ?>