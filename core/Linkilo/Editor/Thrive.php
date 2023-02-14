<?php

/**
 * Thrive editor
 *
 * Class Linkilo_Build_Editor_Thrive
 */
class Linkilo_Build_Editor_Thrive
{
    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id, &$content)
    {
        $thrive = get_post_meta($post_id, 'tve_updated_post', true);

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post_id, 'tve_content_before_more', true);
            foreach ($meta as $link) {
                $changed_sentence = Linkilo_Build_Feed::getSentenceWithAnchor($link);
                if (strpos($thrive, $link['sentence']) === false) {
                    $link['sentence'] = addslashes($link['sentence']);
                }
                Linkilo_Build_Feed::insertLink($thrive_before, $link['sentence'], $changed_sentence);
                Linkilo_Build_Feed::insertLink($thrive, $link['sentence'], $changed_sentence);
            }

            update_post_meta($post_id, 'tve_updated_post', $thrive);
            update_post_meta($post_id, 'tve_content_before_more', $thrive_before);
        }

        $template = get_post_meta($post_id, 'tve_landing_page', true);
        // if the post has the Thrive Template active
        if($template){
            $thrive = get_post_meta($post_id, 'tve_updated_post_' . $template, true);

            if($thrive){
                $thrive_before = get_post_meta($post_id, 'tve_content_before_more_', true);
                foreach ($meta as $link) {
                $changed_sentence = Linkilo_Build_Feed::getSentenceWithAnchor($link);
                    if (strpos($thrive, $link['sentence']) === false) {
                        $link['sentence'] = addslashes($link['sentence']);
                    }
                    Linkilo_Build_Feed::insertLink($thrive_before, $link['sentence'], $changed_sentence);
                    Linkilo_Build_Feed::insertLink($thrive, $link['sentence'], $changed_sentence);
                }

                update_post_meta($post_id, 'tve_updated_post_' . $template, $thrive);
                update_post_meta($post_id, 'tve_content_before_more_', $thrive_before);
            }
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
        $thrive = get_post_meta($post_id, 'tve_updated_post', true);

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post_id, 'tve_content_before_more', true);

            preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $thrive,  $matches);
            if (!empty($matches[0])) {
                $url = addslashes($url);
                $anchor = addslashes($anchor);
            }

            $thrive_before = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $thrive_before);
            $thrive = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $thrive);

            update_post_meta($post_id, 'tve_updated_post', $thrive);
            update_post_meta($post_id, 'tve_content_before_more', $thrive_before);
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
        $thrive = get_post_meta($post_id, 'tve_updated_post', true);

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post_id, 'tve_content_before_more', true);
            $matches = Linkilo_Build_RelateUrlKeyword::findKeywordLinks($keyword, $thrive);
            if (!empty($matches[0])) {
                $keyword->link = addslashes($keyword->link);
                $keyword->keyword = addslashes($keyword->keyword);
            }

            if ($left_one) {
                Linkilo_Build_RelateUrlKeyword::removeNonFirstLinks($keyword, $thrive_before);
                Linkilo_Build_RelateUrlKeyword::removeNonFirstLinks($keyword, $thrive);
            } else {
                Linkilo_Build_RelateUrlKeyword::removeAllLinks($keyword, $thrive_before);
                Linkilo_Build_RelateUrlKeyword::removeAllLinks($keyword, $thrive);
            }

            update_post_meta($post_id, 'tve_updated_post', $thrive);
            update_post_meta($post_id, 'tve_content_before_more', $thrive_before);
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
        $thrive = get_post_meta($post->id, 'tve_updated_post', true);

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post->id, 'tve_content_before_more', true);
            Linkilo_Build_UrlReplace::replaceLink($thrive, $url);
            Linkilo_Build_UrlReplace::replaceLink($thrive_before, $url);

            update_post_meta($post->id, 'tve_updated_post', $thrive);
            update_post_meta($post->id, 'tve_content_before_more', $thrive_before);
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
        $thrive = get_post_meta($post->id, 'tve_updated_post', true);

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post->id, 'tve_content_before_more', true);
            Linkilo_Build_UrlReplace::revertURL($thrive, $url);
            Linkilo_Build_UrlReplace::revertURL($thrive_before, $url);

            update_post_meta($post->id, 'tve_updated_post', $thrive);
            update_post_meta($post->id, 'tve_content_before_more', $thrive_before);
        }
    }
}
