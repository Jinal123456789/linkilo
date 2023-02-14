<?php
$link_external = get_option('linkilo_link_external_sites', false);
foreach($phrase_groups as $phrase_group_type => $phrases){
    // omit the external section if external linking isn't enabled
    if(empty($link_external) && 'external_site' === $phrase_group_type){
        continue;
    }

    // output the spacer if this is the external suggestions
    if('external_site' === $phrase_group_type && !empty($phrases)){
        echo '<div style="border-top: solid 2px #ccd0d4; margin: 0 -13px;"></div>';
    }   
?>
<table class="wp-list-table widefat fixed striped posts tbl_keywords_x js-table linkilo-outgoing-links" id="tbl_keywords">
    <?php if (!empty($phrases)) : ?>
        <thead>
        <tr>
            <th>
                <div>
                    <b><?php if('internal_site' === $phrase_group_type){ _e('Find & Add New Links', 'linkilo'); }else{ _e('Add Outbound Links to External Sites', 'linkilo'); } ?></b>
                    <!-- <br /> -->
                    <div style="margin:5px 0 0 0; display: none;">
                        <input type="checkbox" id="select_all"><span style="margin:0 0 0 5px; font-weight:300"><?php _e('Check All', 'linkilo'); ?></span>
                    </div>
                </div>
            </th>
            <th>
                <div>
                    <!-- <br /> -->
                    <b><?php _e('Posts to link to', 'linkilo'); ?></b>
                </div>
            </th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php foreach ($phrases as $key_phrase => $phrase) : ?>
            <tr data-linkilo-sentence-id="<?=esc_attr($key_phrase)?>">
                <td class="sentences">
                    <?php foreach ($phrase->suggestions as $suggestion) : ?>
                        <div class="sentence top-level-sentence" data-id="<?=esc_attr($suggestion->post->id)?>" data-type="<?=esc_attr($suggestion->post->type)?>">
                            <div class="linkilo_edit_sentence_form">
                                <textarea class="linkilo_content"><?=$suggestion->sentence_src_with_anchor?></textarea>
                                <span class="button-primary">Save</span>
                                <span class="button-secondary">Cancel</span>
                            </div>
                            <input type="checkbox" name="link_keywords[]" class="chk-keywords" linkilo-link-new="">
                            <span class="linkilo_sentence_with_anchor" title="<?php _e('Double clicking a word will select it.', 'linkilo'); ?>"><?=$suggestion->sentence_with_anchor?></span>
                            <span class="linkilo_edit_sentence link-form-button">| <a href="javascript:void(0)" class="cst-btn-clr">Edit Sentence</a></span>
                            <?=!empty(Linkilo_Build_UrlRecommendation::$undeletable)?' ('.esc_attr($suggestion->anchor_score).')':''?>
                            <input type="hidden" name="sentence" value="<?=base64_encode($phrase->sentence_src)?>">
                            <input type="hidden" name="custom_sentence" value="">

                            <?php if (Linkilo_Build_AdminSettings::fullHTMLSuggestions()) : ?>
                                <div class="raw_html"><?=htmlspecialchars($suggestion->sentence_src_with_anchor)?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                </td>
                <td>
                    <?php if (count($phrase->suggestions) > 1) : ?>
                        <?php 
                            $index = key($phrase->suggestions);
                            $a_post = $phrase->suggestions[$index]->post;
                        ?>
                        <div class="linkilo-collapsible-wrapper">
                            <div class="linkilo-collapsible linkilo-collapsible-static linkilo-links-count">
                                <div style="opacity:<?=$phrase->suggestions[$index]->opacity?>" data-id="<?=esc_attr($a_post->id)?>" data-type="<?=esc_attr($a_post->type)?>" data-post-origin="<?php echo (!isset($a_post->site_url)) ? 'internal': 'external'; ?>" data-site-url="<?php echo (isset($a_post->site_url)) ? esc_url($a_post->site_url): ''; ?>">
                                    <?=esc_attr($a_post->getTitle())?>
                                    <?=!empty(Linkilo_Build_UrlRecommendation::$undeletable)?' ('.esc_attr($phrase->suggestions[$index]->post_score).')':''?>
                                    <br>
                                    <a class="post-slug cst-href-clr" target="_blank" href="<?=$a_post->getLinks()->view?>"><?php echo $a_post->getSlug(); ?></a>
                                    <span class="add_custom_link_button link-form-button"> | <a href="javascript:void(0)" class="cst-btn-clr" >Add Your Own</a></span>
                                    <?php 
                                    /*<span class="linkilo_add_feed_url_to_ignore link-form-button"> | <a href="javascript:void(0)" class="cst-href-clr">Ignore Link</a></span>*/
                                    ?>    
                                </div>
                            </div>
                            <div class="linkilo-content" style="display: none;">
                                <ul>
                                    <?php foreach ($phrase->suggestions as $key_suggestion => $suggestion) : ?>
                                        <li class="dated-outgoing-suggestion" data-linkilo-post-published-date="<?php echo strtotime(get_the_date('F j, Y', $suggestion->post->id)); ?>">
                                            <div>
                                                <input type="radio" <?=!$key_suggestion?'checked':''?> data-id="<?=esc_attr($suggestion->post->id)?>" data-type="<?=esc_attr($suggestion->post->type)?>" data-suggestion="<?=esc_attr($key_suggestion)?>" data-post-origin="<?php echo (!isset($suggestion->post->site_url)) ? 'internal': 'external'; ?>" data-site-url="<?php echo (isset($suggestion->post->site_url)) ? esc_url($suggestion->post->site_url): ''; ?>">
                                                <span class="data">
                                                    <span style="opacity:<?=$suggestion->opacity?>"><?=esc_attr($suggestion->post->getTitle())?></span>
                                                    <?=!empty(Linkilo_Build_UrlRecommendation::$undeletable)?' ('.esc_attr($suggestion->post_score).')':''?>
                                                    <br>
                                                    <a class="post-slug cst-href-clr" target="_blank" href="<?=$suggestion->post->getLinks()->view?>"><?php echo $suggestion->post->getSlug(); ?></a>
                                                </span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php else : ?>
                        <?php
                        if(!isset($phrase->suggestions[0]->post) || empty($phrase->suggestions[0]->post)){
                            continue;
                        }
                        $a_post = $phrase->suggestions[0]->post
                        ?>
                        <div style="opacity:<?=$phrase->suggestions[0]->opacity?>" class="suggestion dated-outgoing-suggestion" data-id="<?=esc_attr($a_post->id)?>" data-type="<?=esc_attr($a_post->type)?>" data-linkilo-post-published-date="<?php echo strtotime(get_the_date('F j, Y', $phrase->suggestions[0]->post->id)); ?>" data-post-origin="<?php echo (!isset($a_post->site_url)) ? 'internal': 'external'; ?>" data-site-url="<?php echo (isset($a_post->site_url)) ? esc_url($a_post->site_url): ''; ?>">
                            <?=esc_attr($a_post->getTitle())?>
                            <?=!empty(Linkilo_Build_UrlRecommendation::$undeletable)?' ('.esc_attr($phrase->suggestions[0]->post_score).')':''?>
                            <br>
                            <a class="post-slug cst-href-clr" target="_blank" href="<?=$a_post->getLinks()->view?>">
                            <?php echo urldecode($a_post->getSlug()); ?>
                            </a>
                            <span class="add_custom_link_button link-form-button"> | <a href="javascript:void(0)" class="cst-btn-clr">Add Your Own</a></span>
                            <?php /*
                            <span class="linkilo_add_feed_url_to_ignore link-form-button"> | <a href="javascript:void(0)" class="cst-href-clr">Ignore Link</a></span>
                            */ ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
            <tr class="linkilo-no-posts-in-range" style="display:none">
                <td>No suggestions found</td>
            </tr>
        </tbody>
    <?php else : ?>
        <?php
            if('external_site' === $phrase_group_type){
                echo '<div style="border-top: solid 2px #ccd0d4; margin: 0 -13px;"></div>';
            }    
        ?>
        <thead>
            <tr>
                <th>
                    <div>
                        <b><?php if('internal_site' === $phrase_group_type){ _e('Find & Add New Links', 'linkilo'); }else{ _e('Add Outbound Links to External Sites', 'linkilo'); } ?></b>
                        <br />
                    </div>
                </th>
                <th>
                    <div>
                        <br />
                        <b><?php _e('Posts to link to', 'linkilo'); ?></b>
                    </div>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php _e('No suggestions found', 'linkilo'); ?></td>
            </tr>
        </tbody>
    <?php endif; ?>
</table>
<?php
}
?>