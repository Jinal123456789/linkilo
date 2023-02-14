<?php

/**
 * PageBuilder by Site Origin editor
 *
 * Class Linkilo_Build_Editor_Origin
 */
class Linkilo_Build_Editor_Origin
{
    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id, &$content)
    {
        $data = get_post_meta($post_id, 'panels_data', true);

        if (!empty($data['widgets'])) {
            foreach ($meta as $link) {
                foreach($data['widgets'] as $key => $widget) {
                    if (!empty($widget['text']) && strpos($widget['text'], $link['sentence']) !== false) {
                        $changed_sentence = Linkilo_Build_Feed::getSentenceWithAnchor($link);
                        $changed_sentence = str_replace('"', "'", $changed_sentence);
                        Linkilo_Build_Feed::insertLink($data['widgets'][$key]['text'], $link['sentence'], $changed_sentence);
                    }
                }
            }

            update_post_meta($post_id, 'panels_data', $data);
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
        $data = get_post_meta($post_id, 'panels_data', true);

        if (!empty($data['widgets'])) {
            foreach($data['widgets'] as $key => $widget) {
                if (!empty($widget['text'])) {
                    preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $widget['text'],  $matches);
                    if (!empty($matches[0])) {
                        $data['widgets'][$key]['text'] = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $widget['text']);
                    }
                }
            }

            update_post_meta($post_id, 'panels_data', $data);
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
        $data = get_post_meta($post_id, 'panels_data', true);

        if (!empty($data['widgets'])) {
            $links_count = 0;
            foreach($data['widgets'] as $key => $widget) {
                if (!empty($widget['text'])) {
                    $matches = Linkilo_Build_RelateUrlKeyword::findKeywordLinks($keyword, $widget['text']);
                    if (!empty($matches[0])) {
                        if (!$left_one || $links_count) {
                            Linkilo_Build_RelateUrlKeyword::removeAllLinks($keyword, $data['widgets'][$key]['text']);
                        }
                        if($left_one && $links_count == 0 and count($matches[0]) > 1) {
                            Linkilo_Build_RelateUrlKeyword::removeNonFirstLinks($keyword, $data['widgets'][$key]['text']);
                        }
                        $links_count += count($matches[0]);
                    }
                }
            }

            update_post_meta($post_id, 'panels_data', $data);
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
        $data = get_post_meta($post->id, 'panels_data', true);

        if (!empty($data['widgets'])) {
            foreach($data['widgets'] as $key => $widget) {
                if (!empty($widget['text']) && Linkilo_Build_UrlReplace::hasUrl($widget['text'], $url)) {
                    Linkilo_Build_UrlReplace::replaceLink($data['widgets'][$key]['text'], $url);
                }
            }

            update_post_meta($post->id, 'panels_data', $data);
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
        $data = get_post_meta($post->id, 'panels_data', true);

        if (!empty($data['widgets'])) {
            foreach($data['widgets'] as $key => $widget) {
                if (!empty($widget['text'])) {
                    preg_match('`data-linkilo=\"url\" (href|url)=[\'\"]' . preg_quote($url->new, '`') . '\/*[\'\"]`i', $widget['text'], $matches);
                    if (!empty($matches)) {
                        Linkilo_Build_UrlReplace::revertURL($data['widgets'][$key]['text'], $url);
                    }
                }
            }

            update_post_meta($post->id, 'panels_data', $data);
        }
    }
}