<?php

/**
 * Elementor editor
 *
 * Class Linkilo_Build_Editor_Elementor
 */
class Linkilo_Build_Editor_Elementor
{
    public static $link_processed;
    public static $keyword_links_count;
    public static $link_confirmed;

    /**
     * Gets the Elementor content for making suggestions
     *
     * @param $post
     */
    public static function getContent($post_id)
    {
        $elementor = get_post_meta($post_id, '_elementor_data', true);

        // if there's elementor data and the editor is active for this post
        if (!empty($elementor) && !empty(get_post_meta($post_id, '_elementor_edit_mode', true))) {
            $content = '';
            foreach($elementor as $data){
                self::getProcessableData($data, $content);
            }
        }

        return false;
    }

    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id, &$content)
    {
        $elementor = get_post_meta($post_id, '_elementor_data', true);

        // if there's elementor data and the editor is active for this post
        if (!empty($elementor) && !empty(get_post_meta($post_id, '_elementor_edit_mode', true))) {
            $elementor = json_decode($elementor);
            foreach ($meta as $link) {
                $before = md5(json_encode($elementor));

                self::manageLink($elementor, [
                    'action' => 'add',
                    'sentence' => Linkilo_Build_WordFunctions::replaceUnicodeCharacters($link['sentence']),
                    'replacement' => Linkilo_Build_Feed::getSentenceWithAnchor($link)
                ]);

                $after = md5(json_encode($elementor));

                // if the link hasn't been added to the elementor module
                if($before === $after && empty(self::$link_confirmed) && empty(self::$link_processed)){
                    // remove the link from the post content
                    $content = self::removeLinkFromPostContent($link, $content);
                }
            }

            $elementor = addslashes(json_encode($elementor));
            update_post_meta($post_id, '_elementor_data', $elementor);
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
        $elementor = get_post_meta($post_id, '_elementor_data', true);

        if (!empty($elementor)) {
            $elementor = json_decode($elementor);
            self::manageLink($elementor, [
                'action' => 'remove',
                'url' => Linkilo_Build_WordFunctions::replaceUnicodeCharacters($url),
                'anchor' => Linkilo_Build_WordFunctions::replaceUnicodeCharacters($anchor)
            ]);
            $elementor = addslashes(json_encode($elementor));
            update_post_meta($post_id, '_elementor_data', $elementor);
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
        $elementor = get_post_meta($post_id, '_elementor_data', true);

        if (!empty($elementor)) {
            $elementor = json_decode($elementor);
            $keyword->link = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($keyword->link);
            $keyword->keyword = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($keyword->keyword);
            self::$keyword_links_count = 0;
            self::manageLink($elementor, [
                'action' => 'remove_keyword',
                'keyword' => $keyword,
                'left_one' => $left_one
            ]);

            $elementor = addslashes(json_encode($elementor));
            update_post_meta($post_id, '_elementor_data', $elementor);
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
        $elementor = get_post_meta($post->id, '_elementor_data', true);

        if (!empty($elementor)) {
            $elementor = json_decode($elementor);
            $url->old = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($url->old);
            $url->new = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($url->new);
            self::manageLink($elementor, [
                'action' => 'replace_urls',
                'url' => $url,
            ]);

            $elementor = addslashes(json_encode($elementor));
            update_post_meta($post->id, '_elementor_data', $elementor);
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
        $elementor = get_post_meta($post->id, '_elementor_data', true);

        if (!empty($elementor)) {
            $elementor = json_decode($elementor);
            $url->old = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($url->old);
            $url->new = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($url->new);
            self::manageLink($elementor, [
                'action' => 'revert_urls',
                'url' => $url,
            ]);

            $elementor = addslashes(json_encode($elementor));
            update_post_meta($post->id, '_elementor_data', $elementor);
        }
    }

    /**
     * Find all text elements
     *
     * @param $data
     * @param $params
     */
    public static function manageLink(&$data, $params)
    {
        self::$link_processed = false;
        self::$link_confirmed = false;
        if (is_countable($data)) {
            foreach ($data as $item) {
                self::checkItem($item, $params);
            }
        }
    }

    /**
     * Check certain text element
     *
     * @param $item
     * @param $params
     */
    public static function checkItem(&$item, $params)
    {
        if (!empty($item->widgetType) && (!in_array($item->widgetType, ['heading', 'button']) || $item->widgetType === 'heading' && self::canAddLinksToHeading($item)) ) {
            if (!empty($item->settings->icon_list)) {
                foreach ($item->settings->icon_list as $key => $icon) {
                    self::manageBlock($item->settings->icon_list[$key]->text, $params);
                }
            }
            if (isset($item->settings) && isset($item->settings->tabs) && !empty($item->settings->tabs)) {
                foreach ($item->settings->tabs as $key => $tab) {
                    foreach(array('tab_content', 'faq_answer', 'accordion_content') as $tab_index){
                        if( isset($item->settings->tabs[$key]->$tab_index) && 
                            !empty($item->settings->tabs[$key]->$tab_index))
                        {
                            self::manageBlock($item->settings->tabs[$key]->$tab_index, $params);
                        }
                    }
                }
            }

            // look over any HBTheme repeating modules // todo abstract into a more refined form when more data is available. There will be other module packs that have items with sub content in the same way as this.
            foreach (['accordions', 'images'] as $key) {
                if (!empty($item->settings->$key)) {
                    foreach($item->settings->$key as $sub_item){
                        foreach(['desc', 'description', 'caption'] as $content_type){
                            self::manageBlock($sub_item->$content_type, $params);
                        }
                    }
                }
            }

            foreach (['editor', 'title', 'caption', 'text', 'description_text', 'testimonial_content', 'html', 'alert_title', 'alert_description', 'description', 'faq_answer', 'accordion_content', 'protected_content_text'] as $key) {
                if (!empty($item->settings->$key)) {
                    self::manageBlock($item->settings->$key, $params);
                }
            }
        }

        if (!empty($item->elements)) {
            foreach ($item->elements as $element) {
                if (!self::$link_processed) {
                    self::checkItem($element, $params);
                }
            }
        }
    }

    /**
     * Checks the given item to see if its a heading and it can have links added to it.
     * @param object $item The Elementor item that we're going to check
     * @return bool
     **/
    public static function canAddLinksToHeading($item){
        if($item->widgetType !== 'heading'){
            return true; // possibly remove this. I'm returning true in case I accidentally use this somewhere that doesn't strictly check for headings, but this could allow false positives.
        }

        // if a custom heading element has been selected, and the element is a div, span, or p
        if(isset($item->settings) && isset($item->settings->header_size) && in_array($item->settings->header_size, array('div', 'span', 'p'))){
            // return that a link can be inserted here
            return true;
        }

        return false;
    }

    /**
     * Remove links from the post content when they're not added to the Elementor content
     **/
    public static function removeLinkFromPostContent($link, $content){
        $sentence_with_anchor = Linkilo_Build_Feed::getSentenceWithAnchor($link);

        if(!empty($sentence_with_anchor) && false !== strpos($content, $sentence_with_anchor)){
            $content2 = preg_replace('`' . preg_quote($sentence_with_anchor, '`') . '`', $link['sentence'], $content, 1);
            if(!empty($content2)){
                $content = $content2;
            }
        }

        return $content;
    }

    /**
     * Route current action
     *
     * @param $block
     * @param $params
     */
    public static function manageBlock(&$block, $params)
    {
        if ($params['action'] == 'add') {
            self::addLinkToBlock($block, $params['sentence'], $params['replacement']);
        } elseif ($params['action'] == 'remove') {
            self::removeLinkFromBlock($block, $params['url'], $params['anchor']);
        } elseif ($params['action'] == 'remove_keyword') {
            self::removeKeywordFromBlock($block, $params['keyword'], $params['left_one']);
        } elseif ($params['action'] == 'replace_urls') {
            self::replaceURLInBlock($block, $params['url']);
        } elseif ($params['action'] == 'revert_urls') {
            self::revertURLInBlock($block, $params['url']);
        }
    }

    /**
     * Insert link into block
     *
     * @param $block
     * @param $sentence
     * @param $replacement
     */
    public static function addLinkToBlock(&$block, $sentence, $replacement)
    {
        $block_unicode = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($block);
        if (strpos($block_unicode, $sentence) !== false) {
            $block = $block_unicode;
            Linkilo_Build_Feed::insertLink($block, $sentence, $replacement);
            $block = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($block, true);
            self::$link_processed = true;
        }elseif(false !== strpos($block_unicode, Linkilo_Build_WordFunctions::replaceUnicodeCharacters($replacement)) || false !== strpos($block_unicode, 'linkilo_keyword_link') || false !== strpos($block_unicode, 'data-linkilo-keyword-link')){
            self::$link_confirmed = true;
        }
    }

    /**
     * Remove link from block
     *
     * @param $block
     * @param $url
     * @param $anchor
     */
    public static function removeLinkFromBlock(&$block, $url, $anchor)
    { 
        // decode the url if it's base64 encoded
        if(base64_encode(base64_decode($url, true)) === $url){
            $url = base64_decode($url);
        }

        $block_unicode = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($block);
        preg_match('`<a .+?' . preg_quote(Linkilo_Build_WordFunctions::replaceUnicodeCharacters($url), '`') . '.+?>' . preg_quote(Linkilo_Build_WordFunctions::replaceUnicodeCharacters($anchor), '`') . '</a>`i', $block_unicode,  $matches);

        if (!empty($matches[0])) {
            $block = $block_unicode;
            $before = md5($block);
            $block = preg_replace('|<a [^>]+' . preg_quote(Linkilo_Build_WordFunctions::replaceUnicodeCharacters($url), '`') . '[^>]+>' . preg_quote(Linkilo_Build_WordFunctions::replaceUnicodeCharacters($anchor), '`') . '</a>|i', $anchor,  $block);
            $after = md5($block);
            $block = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($block, true);
            if($before !== $after){
                self::$link_processed = true;
            }
        }
    }

    /**
     * Remove keyword links
     *
     * @param $block
     * @param $keyword
     * @param $left_one
     */
    public static function removeKeywordFromBlock(&$block, $keyword, $left_one)
    {
        $block_unicode = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($block);
        $matches = Linkilo_Build_RelateUrlKeyword::findKeywordLinks($keyword, $block_unicode);
        if (!empty($matches[0])) {
            $block = $block_unicode;
            if (!$left_one || self::$keyword_links_count) {
                Linkilo_Build_RelateUrlKeyword::removeAllLinks($keyword, $block);
            }
            if($left_one && self::$keyword_links_count == 0 and count($matches[0]) > 1) {
                Linkilo_Build_RelateUrlKeyword::removeNonFirstLinks($keyword, $block);
            }
            self::$keyword_links_count += count($matches[0]);
            $block = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($block, true);
        }
    }


    /**
     * Replace URL in block
     *
     * @param $block
     * @param $url
     */
    public static function replaceURLInBlock(&$block, $url)
    {
        $block_unicode = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($block);

        if (Linkilo_Build_UrlReplace::hasUrl($block_unicode, $url)) {
            $block = $block_unicode;
            Linkilo_Build_UrlReplace::replaceLink($block, $url);
            $block = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($block, true);
        }
    }

    /**
     * Revert URL in block
     *
     * @param $block
     * @param $url
     */
    public static function revertURLInBlock(&$block, $url)
    {
        $block_unicode = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($block);

        preg_match('`data\\\u2013linkilo=\"url\" (href|url)=[\'\"]' . preg_quote($url->new, '`') . '\/*[\'\"]`i', $block_unicode, $matches);
        if (!empty($matches)) {
            $block = $block_unicode;
            $block = preg_replace('`data\\\u2013linkilo=\"url\" (href|url)=([\'\"])' . $url->new . '\/*([\'\"])`i', '$1=$2' . $url->old . '$3', $block);
            $block = Linkilo_Build_WordFunctions::replaceUnicodeCharacters($block, true);
        }
    }

    /**
     * Check certain text element
     *
     * @param $item
     * @param $params
     */
    public static function getProcessableData($item, &$content)
    {
        if (!empty($item->widgetType) && (!in_array($item->widgetType, ['heading', 'button']) || $item->widgetType === 'heading' && self::canAddLinksToHeading($item)) ) {
            if (!empty($item->settings->icon_list)) {
                foreach ($item->settings->icon_list as $key => $icon) {
                    $content .= "\n" . $item->settings->icon_list[$key]->text;
                }
            }
            if (isset($item->settings) && isset($item->settings->tabs) && !empty($item->settings->tabs)) {
                foreach ($item->settings->tabs as $key => $tab) {
                    foreach(array('tab_content', 'faq_answer', 'accordion_content') as $tab_index){
                        if( isset($item->settings->tabs[$key]->$tab_index) && 
                            !empty($item->settings->tabs[$key]->$tab_index))
                        {
                            $content .= "\n" . $item->settings->tabs[$key]->$tab_index;
                        }
                    }
                }
            }

            // look over any HBTheme repeating modules // todo abstract into a more refined form when more data is available. There will be other module packs that have items with sub content in the same way as this.
            foreach (['accordions', 'images'] as $key) {
                if (!empty($item->settings->$key)) {
                    foreach($item->settings->$key as $sub_item){
                        foreach(['desc', 'description', 'caption'] as $content_type){
                            $content .= "\n" . $sub_item->$content_type;
                        }
                    }
                }
            }

            foreach (['editor', 'title', 'caption', 'text', 'description_text', 'testimonial_content', 'html', 'alert_title', 'alert_description', 'description', 'faq_answer', 'accordion_content', 'protected_content_text'] as $key) {
                if (!empty($item->settings->$key)) {
                    $content .= "\n" . $item->settings->$key;
                }
            }
        }

        if (!empty($item->elements)) {
            foreach ($item->elements as $element) {
                if (!self::$link_processed) {
                    self::getProcessableData($element, $content);
                }
            }
        }
    }
}