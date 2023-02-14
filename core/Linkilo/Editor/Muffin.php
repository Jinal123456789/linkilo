<?php

/**
 * Muffin editor
 *
 * Class Linkilo_Build_Editor_Muffin
 */
class Linkilo_Build_Editor_Muffin
{
    /**
     * Gets the post content for the Muffin builder
     * 
     * @param $post_id
     */
    public static function getContent($post_id){
        // muffin stores it's data in a vast array under a single index
        $muffin = get_post_meta($post_id, 'mfn-page-items', true);
        // get if the wp editor content is being hidden from view
        $hiding_post_content = get_post_meta($post_id, 'mfn-post-hide-content', true);

        $content = '';

        if(!empty($muffin)){
            // if the builder isn't set to hide the wp editor's content
            if(empty($hiding_post_content)){
                // get the post content
                $post = get_post($post_id);
                $content .= $post->post_content;
            }

            foreach($muffin as $item){
                if(isset($item['wraps'])){
                    foreach($item['wraps'] as $wrap){
                        if(isset($wrap['items']) && !empty($wrap['items']) && is_array($wrap['items'])){
                            foreach($wrap['items'] as $item){
                                if(isset($item['fields']) && isset($item['fields']['content'])){
                                    $content .= "\n" . $item['fields']['content'];
                                }elseif(isset($item['type']) && 'content' === $item['type']){
                                    // if the current item is a "WP Editor" content item, pull the post content
                                    $content .= "\n" . get_post($post_id)->post_content;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id, &$content)
    {
        $muffin = get_post_meta($post_id, 'mfn-page-items', true);
        $update_content = $muffin;

        if (!empty($muffin)) {
            $muffin_seo = get_post_meta($post_id, 'mfn-page-items-seo', true);
            foreach ($meta as $link) {
                $changed_sentence = Linkilo_Build_Feed::getSentenceWithAnchor($link);
                $slashed_sentence = addslashes($link['sentence']);

                foreach($muffin as $key1 => $item){
                    if(isset($item['wraps'])){
                        foreach($item['wraps'] as $key2 => $wrap){
                            if(isset($wrap['items'])){
                                foreach($wrap['items'] as $key3 => $item){
                                    if(isset($item['fields']) && isset($item['fields']['content'])){
                                        if (strpos($item['fields']['content'], $link['sentence']) === false) {
                                            Linkilo_Build_Feed::insertLink($update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content'], $slashed_sentence, $changed_sentence);
                                        }else{
                                            Linkilo_Build_Feed::insertLink($update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content'], $link['sentence'], $changed_sentence);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if (strpos($muffin_seo, $link['sentence']) === false) {
                    Linkilo_Build_Feed::insertLink($muffin_seo, $slashed_sentence, $changed_sentence);
                }else{
                    Linkilo_Build_Feed::insertLink($muffin_seo, $link['sentence'], $changed_sentence);
                }
            }

            update_post_meta($post_id, 'mfn-page-items', $update_content);
            update_post_meta($post_id, 'mfn-page-items-seo', $muffin_seo);
        }
    }

    /**
     * Delete link
     *
     * @param $post_id
     * @param $url
     * @param $anchor
     */
    public static function deleteLink($post_id, $url, $anchor)
    {
        $muffin = get_post_meta($post_id, 'mfn-page-items', true);
        $update_content = $muffin;

        if (!empty($muffin)) {
            $muffin_seo = get_post_meta($post_id, 'mfn-page-items-seo', true);

            $slashed_url = addslashes($url);
            $slashed_anchor = addslashes($anchor);

            foreach($muffin as $key1 => $item){
                if(isset($item['wraps'])){
                    foreach($item['wraps'] as $key2 => $wrap){
                        if(isset($wrap['items'])){
                            foreach($wrap['items'] as $key3 => $item){
                                if(isset($item['fields']) && isset($item['fields']['content'])){
                                    preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content'],  $matches);
                                    if (empty($matches[0])) {
                                        $update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content'] = preg_replace('|<a [^>]+'.$slashed_url.'[^>]+>'.$slashed_anchor.'</a>|i', $slashed_anchor,  $update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content']);
                                    }else{
                                        $update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content'] = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content']);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $muffin_seo,  $matches);
            if(empty($matches[0])) {
                $muffin_seo = preg_replace('|<a [^>]+'.$slashed_url.'[^>]+>'.$slashed_anchor.'</a>|i', $slashed_anchor,  $muffin_seo);
            }else{
                $muffin_seo = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $muffin_seo);
            }

            update_post_meta($post_id, 'mfn-page-items', $update_content);
            update_post_meta($post_id, 'mfn-page-items-seo', $muffin_seo);
        }
    }

    /**
     * Remove keyword links
     *
     * @param $keyword
     * @param $post_id
     * @param bool $left_one
     */
    public static function removeKeywordLinks($keyword, $post_id, $left_one = false)
    {
        $muffin = get_post_meta($post_id, 'mfn-page-items', true);
        $update_content = $muffin;

        if (!empty($muffin)) {
            $changed = false;
            $muffin_seo = get_post_meta($post_id, 'mfn-page-items-seo', true);

            $slashed_keyword = $keyword;
            $slashed_keyword->link = addslashes($keyword->link);
            $slashed_keyword->keyword = addslashes($keyword->keyword);

            foreach($muffin as $key1 => $item){
                if(isset($item['wraps'])){
                    foreach($item['wraps'] as $key2 => $wrap){
                        if(isset($wrap['items'])){
                            foreach($wrap['items'] as $key3 => $item){
                                if(isset($item['fields']) && isset($item['fields']['content'])){
                                    $matches = Linkilo_Build_RelateUrlKeyword::findKeywordLinks($keyword, $update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content']);
                                    if(empty($matches[0])){
                                        if($left_one && !$changed){
                                            $matches2 = Linkilo_Build_RelateUrlKeyword::findKeywordLinks($slashed_keyword, $update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content']);
                                            if(!empty($matches2[0])){
                                                Linkilo_Build_RelateUrlKeyword::removeNonFirstLinks($slashed_keyword, $update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content']);
                                                $changed = true;
                                            }
                                        }else{
                                            Linkilo_Build_RelateUrlKeyword::removeAllLinks($slashed_keyword, $update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content']);
                                        }
                                    }else{
                                        if($left_one && !$changed){
                                            Linkilo_Build_RelateUrlKeyword::removeNonFirstLinks($keyword, $update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content']);
                                            $changed = true;
                                        }else{
                                            Linkilo_Build_RelateUrlKeyword::removeAllLinks($keyword, $update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content']);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $matches = Linkilo_Build_RelateUrlKeyword::findKeywordLinks($keyword, $muffin_seo);

            if ($left_one) {
                if(empty($matches[0])) {
                    Linkilo_Build_RelateUrlKeyword::removeNonFirstLinks($slashed_keyword, $muffin_seo);
                }else{
                    Linkilo_Build_RelateUrlKeyword::removeNonFirstLinks($keyword, $muffin_seo);
                }
            } else {
                if(empty($matches[0])) {
                    Linkilo_Build_RelateUrlKeyword::removeAllLinks($slashed_keyword, $muffin_seo);
                }else{
                    Linkilo_Build_RelateUrlKeyword::removeAllLinks($keyword, $muffin_seo);
                }
            }

            update_post_meta($post_id, 'mfn-page-items', $update_content);
            update_post_meta($post_id, 'mfn-page-items-seo', $muffin_seo);
        }
    }

    /**
     * Replace URLs
     *
     * @param $post
     * @param $url
     */
    public static function replaceURLs($post, $url)
    {
        $muffin = get_post_meta($post->id, 'mfn-page-items', true);
        $update_content = $muffin;

        if (!empty($muffin)) {
            $muffin_seo = get_post_meta($post->id, 'mfn-page-items-seo', true);

            foreach($muffin as $key1 => $item){
                if(isset($item['wraps'])){
                    foreach($item['wraps'] as $key2 => $wrap){
                        if(isset($wrap['items'])){
                            foreach($wrap['items'] as $key3 => $item){
                                if(isset($item['fields']) && isset($item['fields']['content'])){
                                    Linkilo_Build_UrlReplace::replaceLink($update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content'], $url, true, $post);
                                }
                            }
                        }
                    }
                }
            }

            Linkilo_Build_UrlReplace::replaceLink($muffin_seo, $url, true, $post);

            update_post_meta($post->id, 'mfn-page-items', $update_content);
            update_post_meta($post->id, 'mfn-page-items-seo', $muffin_seo);
        }

    }

    /**
     * Revert URLs
     *
     * @param $post
     * @param $url
     */
    public static function revertURLs($post, $url)
    {
        $muffin = get_post_meta($post->id, 'mfn-page-items', true);
        $update_content = $muffin;

        if (!empty($muffin)) {
            $muffin_seo = get_post_meta($post->id, 'mfn-page-items-seo', true);

            foreach($muffin as $key1 => $item){
                if(isset($item['wraps'])){
                    foreach($item['wraps'] as $key2 => $wrap){
                        if(isset($wrap['items'])){
                            foreach($wrap['items'] as $key3 => $item){
                                if(isset($item['fields']) && isset($item['fields']['content'])){
                                    Linkilo_Build_UrlReplace::revertURL($update_content[$key1]['wraps'][$key2]['items'][$key3]['fields']['content'], $url);
                                }
                            }
                        }
                    }
                }
            }

            Linkilo_Build_UrlReplace::revertURL($muffin_seo, $url);

            update_post_meta($post->id, 'mfn-page-items', $update_content);
            update_post_meta($post->id, 'mfn-page-items-seo', $muffin_seo);
        }
    }
}