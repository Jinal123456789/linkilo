<?php

/**
 * Recipe editor
 *
 * Class Linkilo_Build_Editor_WPRecipe
 */
class Linkilo_Build_Editor_WPRecipe
{
    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id, &$content)
    {
        $recipe = get_post_meta($post_id, 'wprm_notes', true);

        if (!empty($recipe)) {
            foreach ($meta as $link) {
                $changed_sentence = Linkilo_Build_Feed::getSentenceWithAnchor($link);
                if (strpos($recipe, $link['sentence']) === false) {
                    $link['sentence'] = addslashes($link['sentence']);
                }
                Linkilo_Build_Feed::insertLink($recipe, $link['sentence'], $changed_sentence);
            }

            update_post_meta($post_id, 'wprm_notes', $recipe);
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
        $recipe = get_post_meta($post_id, 'wprm_notes', true);

        if (!empty($recipe)) {
            preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $recipe,  $matches);
            if (!empty($matches[0])) {
                $url = addslashes($url);
                $anchor = addslashes($anchor);
            }

            $recipe = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $recipe);

            update_post_meta($post_id, 'wprm_notes', $recipe);
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
        $recipe = get_post_meta($post_id, 'wprm_notes', true);

        if (!empty($recipe)) {
            $matches = Linkilo_Build_RelateUrlKeyword::findKeywordLinks($keyword, $recipe);
            if (!empty($matches[0])) {
                $keyword->link = addslashes($keyword->link);
                $keyword->keyword = addslashes($keyword->keyword);
            }

            if ($left_one) {
                Linkilo_Build_RelateUrlKeyword::removeNonFirstLinks($keyword, $recipe);
            } else {
                Linkilo_Build_RelateUrlKeyword::removeAllLinks($keyword, $recipe);
            }

            update_post_meta($post_id, 'wprm_notes', $recipe);
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
        $recipe = get_post_meta($post->id, 'wprm_notes', true);

        if (!empty($recipe)) {
            Linkilo_Build_UrlReplace::replaceLink($recipe, $url, true, $post);

            update_post_meta($post->id, 'wprm_notes', $recipe);
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
        $recipe = get_post_meta($post->id, 'wprm_notes', true);

        if (!empty($recipe)) {
            Linkilo_Build_UrlReplace::revertURL($recipe, $url);

            update_post_meta($post->id, 'wprm_notes', $recipe);
        }
    }
}
