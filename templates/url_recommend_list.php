<?php
$type = (!empty($term_id)?'term':'post');
$linkilo_post = new Linkilo_Build_Model_Feed($post_id, $type);
$max_links_per_post = get_option('linkilo_max_links_per_post', 0);

if(get_option('linkilo_disable_outgoing_suggestions')){ ?>
    <div class="linkilo_styles" style="min-height: 200px">
        <p style="display: inline-block;"><?php _e('Outbound Link Suggestions Disabled', 'linkilo') ?></p>
        <a style="float: right; margin: 15px 0px;" href="<?=admin_url("admin.php?{$linkilo_post->type}_id={$linkilo_post->id}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode($linkilo_post->getLinks()->edit))?>" class="button-primary">Add Incoming links</a>
    </div>
    <?php
    return;
}elseif(!empty($max_links_per_post)){
    // check if the current post is at the link limit
    preg_match_all('`<a[^>]*?href=(\"|\')([^\"\']*?)(\"|\')[^>]*?>([\s\w\W]*?)<\/a>|<!-- wp:core-embed\/wordpress {"url":"([^"]*?)"[^}]*?"} -->|(?:>|&nbsp;|\s)((?:(?:http|ftp|https)\:\/\/)(?:[\w_-]+(?:(?:\.[\w_-]+)+))(?:[\w.,@?^=%&:/~+#-]*[\w@?^=%&/~+#-]))(?:<|&nbsp;|\s)`i', $linkilo_post->getContent(), $matches);
    if(isset($matches[0]) && count($matches[0]) >= $max_links_per_post){?>
    <div class="linkilo_styles" style="min-height: 200px">
        <p style="display: inline-block;"><?php _e('Post has reached the max link limit. To enable suggestions, please increase the Max Links Per Post setting from the Linkilo Settings.', 'linkilo') ?></p>
        <a style="float: right; margin: 15px 0px;" href="<?=admin_url("admin.php?{$linkilo_post->type}_id={$linkilo_post->id}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode($linkilo_post->getLinks()->edit))?>" class="button-primary">Add Incoming links</a>
    </div>
    <?php
        return;
    }
}

?>
<?php $same_category = !empty(get_user_meta(get_current_user_id(), 'linkilo_same_category_selected', true)) ? '&same_category=true': ''; ?>
<?php if($manually_trigger_suggestions){ ?>
    <div class="linkilo_styles linkilo-get-manual-suggestions-container" style="min-height: 200px">
        <a href="#" id="linkilo-get-manual-suggestions" style="margin: 15px 0px;" class="button-primary"><?php _e('Get Suggestions', 'linkilo'); ?></a>
        <a style="float: right; margin: 15px 0px;" href="<?=admin_url("admin.php?{$linkilo_post->type}_id={$linkilo_post->id}&page=linkilo&type=incoming_suggestions_page&ret_url=" . base64_encode($linkilo_post->getLinks()->edit))?>" class="button-primary">Add Incoming links</a>
    </div>
<?php } ?>
<div data-linkilo-ajax-container="" data-linkilo-ajax-container-url="<?=esc_url(admin_url('admin.php?post_id=' . $post_id . '&page=linkilo&type=outgoing_suggestions_ajax'.(!empty($term_id)?'&term_id='.$term_id:'').(!empty($user->ID) ? '&nonce='.wp_create_nonce($user->ID .'linkilo_suggestion_nonce') : '')) . $same_category)?>" class="linkilo_keywords_list linkilo_styles" data-linkilo-manual-suggestions="<?php echo ($manually_trigger_suggestions) ? 1: 0;?>" <?php echo ($manually_trigger_suggestions) ? 'style="display:none"': ''; ?>>
    <div class="progress_panel loader">
        <div class="progress_count" style="width: 100%">
            <?php //_e('Processing Link Suggestions', 'linkilo');?>        
            <?php //_e('Loading', 'linkilo');?>        
        </div>
    </div>
    <div class="progress_panel_center" > Loading </div>
</div>
