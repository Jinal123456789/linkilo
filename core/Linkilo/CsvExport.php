<?php

/**
 * Export controller
 */
class Linkilo_Build_CsvExport
{

    private static $instance;

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function getInstance()
    {
        if (null === self::$instance)
        {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Export data
     */
    function export($post)
    {
        $data = self::getExportData($post);
        $data = json_encode($data, JSON_PRETTY_PRINT);
        $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

        //create filename
        if ($post->type == 'term') {
            $term = get_term($post->id);
            $filename = $post->id . '-' . $host . '-' . $term->slug . '.json';
        } else {
            $post_slug = get_post_field('post_name', $post->id);
            $filename = $post->id . '-' . $host . '-' . $post_slug . '.json';
        }

        //download export file
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $data;
        exit;
    }

    /**
     * Get post data, links and settings for export
     *
     * @param $post_id
     * @return array
     */
    public static function getExportData($post)
    {
        $thrive_content = get_post_meta($post->id, 'tve_updated_post', true);
        $beaver_content = get_post_meta($post->id, '_fl_builder_data', true);
        $elementor_content = get_post_meta($post->id, '_elementor_data', true);
        $enfold_content = get_post_meta($post->id, '_aviaLayoutBuilderCleanData', true);
        $oxygen_content = get_post_meta($post->id, 'ct_builder_shortcodes', true);

        set_transient('linkilo_transients_enabled', 'true', 600);
        $transient_enabled = (!empty(get_transient('linkilo_transients_enabled'))) ? true: false;

        //export settings
        $settings = [];
        foreach (Linkilo_Build_AdminSettings::$keys as $key) {
            $settings[$key] = get_option($key, null);
        }
        $settings['ignore_words'] = get_option('linkilo_2_ignore_words', null);

        $res = [
            'v' => strip_tags(Linkilo_Build_Root::showVersion()),
            'created' => date('c'),
            'post_id' => $post->id,
            'type' => $post->type,
            'wp_post_type' => $post->getRealType(),
            'last_scanned' => ($post->type === 'post') ? get_post_meta($post->id, 'linkilo_sync_report2_time', true): get_term_meta($post->id, 'linkilo_sync_report2_time', true),
            'url' => $post->getLinks()->view,
            'title' => $post->getTitle(),
            'content' => $post->getContent(false),
            'thrive_content' => $thrive_content,
            'beaver_content' => $beaver_content,
            'elementor_content' => $elementor_content,
            'enfold_content' => $enfold_content,
            'oxygen_content' => $oxygen_content,
            'focus_keywords' => Linkilo_Build_FocusKeyword::get_active_keywords_by_post_ids($post->id, $post->type),
            'focus_keywords_sources' => Linkilo_Build_FocusKeyword::get_active_keyword_sources(),
            'transients_enabled' => $transient_enabled,
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'max_input_vars' => ini_get('max_input_vars'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'php_version' => phpversion(),
            'curl_active' => function_exists('curl_init'),
            'curl_version' => (function_exists('curl_version')) ? curl_version(): false,
            // 'license_type' => Linkilo_Build_ActiveLicense::getItemId(),    Commented unusable code ref:license
            'registered_sites' => Linkilo_Build_ConnectMultipleSite::get_registered_sites(),
            'linked_sites' => Linkilo_Build_ConnectMultipleSite::get_linked_sites(),
            'settings' => $settings
        ];

        // if we're including meta in the export
        if(!empty(get_option('linkilo_include_post_meta_in_support_export'))){
            $res['post_meta'] = ($post->type === 'post') ? get_post_meta($post->id, '', true) : get_term_meta($post->id, '', true);
        }

        // add reporting data to export
        $keys = [
            LINKILO_TOTAL_COUNT_OF_OUTGOING_INTERNAL_LINKS,
            LINKILO_TOTAL_COUNT_OF_INCOMING_INTERNAL_LINKS,
            LINKILO_TOTAL_COUNT_OF_OUTGOING_EXTERNAL_LINKS,
        ];

        $report = [];
        foreach($keys as $key) {
            if ($post->type == 'term') {
                $report[$key] = get_term_meta($post->id, $key, true);
                $report[$key.'_data'] = get_term_meta($post->id, $key.'_data', true);
            } else {
                $report[$key] = get_post_meta($post->id, $key, true);
                $report[$key.'_data'] = get_post_meta($post->id, $key.'_data', true);
            }
        }

        $res['report'] = $report;
        $res['phrases'] = Linkilo_Build_UrlRecommendation::getPostSuggestions($post, null, true);

        return $res;
    }

    /**
     * Export table data to CSV
     */
    public static function ajax_csv()
    {
        $type = !empty($_POST['type']) ? $_POST['type'] : null;
        $count = !empty($_POST['count']) ? $_POST['count'] : null;

        if (!$type || !$count) {
            wp_send_json([
                    'error' => [
                    'title' => __('Request Error', 'linkilo'),
                    'text'  => __('Bad request. Please try again later', 'linkilo')
                ]
            ]);
        }

        if ($count == 1) {
            $fp = fopen(LINKILO_PLUGIN_DIR_PATH . 'includes/' . $type . '_export.csv', 'w');
            switch ($type) {
                case 'links':
                    if(!empty(Linkilo_Build_GoogleSearchConsole::is_authenticated())){
                        $header = "Title,Type,Category,Published,Organic Traffic,AVG Position,Source Page URL - (The page we are linking from),Outbound Link URL,Outbound Link Anchor,Incoming Link Page Source URL,Incoming Link Anchor\n";
                    }else{
                        $header = "Title,Type,Category,Published,Source Page URL - (The page we are linking from),Outbound Link URL,Outbound Link Anchor,Incoming Link Page Source URL,Incoming Link Anchor\n";
                    }
                    break;
                case 'links_summary':
                    if(!empty(Linkilo_Build_GoogleSearchConsole::is_authenticated())){
                        $header = "Title,URL,Type,Category,Published,Organic Traffic,AVG Position,Incoming Inner URLs,Outgoing Inner URLs,Outgoing Outer URLs\n";
                    }else{
                        $header = "Title,URL,Type,Category,Published,Incoming Inner URLs,Outgoing Inner URLs,Outgoing Outer URLs\n";
                    }
                    break;
                case 'domains':
                    $header = "Domain,Post URL,Anchor Text,Anchor URL,Post Edit Link\n";
                    break;
                case 'domains_summary':
                    $header = "Domain,Post Count,Link Count\n";
                    break;
                case 'error':
                    $header = "Post,Broken URL,Type,Status,Discovered\n";
                    break;
            }
            fwrite($fp, $header);
        } else {
            $fp = fopen(LINKILO_PLUGIN_DIR_PATH . 'includes/' . $type . '_export.csv', 'a');
        }

        //get data
        $data = '';
        $func = 'csv_' . $type;
        if (method_exists('Linkilo_Build_CsvExport', $func)) {
            $data = self::$func($count);
        }

        //send finish response
        if (empty($data)) {
            header('Content-disposition: attachment; filename=' . $type . '_export.csv');

            wp_send_json([
                'filename' => LINKILO_PLUGIN_DIR_URL . 'includes/' . $type . '_export.csv'
            ]);
        }

        //write to file
        fwrite($fp, $data);

        wp_send_json([
            'filename' => '',
            'type' => $type,
            'count' => $count
        ]);

        die;
    }

    /**
     * Prepare links data for export
     *
     * @return string
     */
    public static function csv_links($count)
    {
        $links = Linkilo_Build_UrlRecord::getData($count, '', 'ASC', '', 500);
        $authed = Linkilo_Build_GoogleSearchConsole::is_authenticated();
        $data = '';
        $post_url_cache = array();
        foreach ($links['data'] as $link) {
            if (!empty($link['post']->getTitle())) {
                $incoming_internal  = $link['post']->getIncomingInternalLinks();
                $outgoing_internal = $link['post']->getOutboundInternalLinks();
                $outgoing_external = $link['post']->getOutboundExternalLinks();
                $outgoing_links = array_merge($outgoing_internal, $outgoing_external);
                if($authed){
                    $organic_traffic = $link['post']->get_organic_traffic();
                    $position = $link['post']->get_avg_position();
                }

                // if there's more Incoming Inner URLs than outgoing links
                $diff = count($outgoing_links) - count($incoming_internal);
                if($diff < 0){
                    for ($j = 0; $j < max(abs($diff), 1); $j++) {
                        $outgoing_links[] = false;
                    }
                }

                for ($i = 0; $i < max(count($outgoing_links), 1); $i++) {
                    $post = $link['post'];
                    $category = '';
                    if ($post->getRealType() == 'post') {
                        $category_ids = wp_get_post_categories($post->id, ['fields' => 'names']);
                        $category = '"' . addslashes(implode(', ', $category_ids)) . '"';
                    }

                    $incoming_post_source_url = '';
                    if(!empty($incoming_internal[$i])){
                        $inbnd_id = $incoming_internal[$i]->post->id;
                        if(!isset($post_url_cache[$inbnd_id])){
                            $post_url_cache[$inbnd_id] = wp_make_link_relative($incoming_internal[$i]->post->getLinks()->view);
                        }
                        $incoming_post_source_url = $post_url_cache[$inbnd_id];
                    }

                    $item = [
                        !$i ? '"' . addslashes($post->getTitle()) . '"' : '',
                        !$i ? $post->getType() : '',
                        !$i ? '"' . $link['date'] . '"' : '',
                        wp_make_link_relative($post->getLinks()->view),
                        !empty($outgoing_links[$i]) ? (
                            Linkilo_Build_PostUrl::isInternal($outgoing_links[$i]->url) ? wp_make_link_relative($outgoing_links[$i]->url) : $outgoing_links[$i]->url
                        ) : '',
                        !empty($outgoing_links[$i]) ? '"' . addslashes(substr(trim(strip_tags($outgoing_links[$i]->anchor)), 0, 100)) . '"' : '',
                        $incoming_post_source_url,
                        !empty($incoming_internal[$i]) ? '"' . addslashes(substr(trim(strip_tags($incoming_internal[$i]->anchor)), 0, 100)) . '"' : '',
                    ];

                    if($authed){
                        $data .= $item[0] . "," . $item[1] . "," . $category . "," . $item[2] . "," . $organic_traffic . "," . $position . "," . $item[3] . "," . $item[4] . "," . $item[5] .  "," . $item[6] . "," . $item[7] . "\n";
                    }else{
                        $data .= $item[0] . "," . $item[1] . "," . $category . "," . $item[2] . "," . $item[3] . "," . $item[4] . "," . $item[5] .  "," . $item[6] . "," . $item[7] . "\n";
                    }
                }
            }
        }

        return $data;
    }

    public static function csv_links_summary($count)
    {
        $links = Linkilo_Build_UrlRecord::getData($count, '', 'ASC', '', 500);
        $authed = Linkilo_Build_GoogleSearchConsole::is_authenticated();
        $data = '';
        foreach ($links['data'] as $link) {
            if (!empty($link['post']->getTitle())) {
                //prepare data
                $post = $link['post'];
                $title = '"' . addslashes($post->getTitle()) . '"';
                $url = wp_make_link_relative($post->getLinks()->view);
                $type = $post->getType();
                $category = '';
                if ($post->getRealType() == 'post') {
                    $category_ids = wp_get_post_categories($post->id, ['fields' => 'names']);
                    $category = '"' . addslashes(implode(', ', $category_ids)) . '"';
                }
                $date = '"' . $link['date'] . '"';
                $ii_count = $post->getIncomingInternalLinks(true);
                $oi_count = $post->getOutboundInternalLinks(true);
                $oe_count = $post->getOutboundExternalLinks(true);
                if($authed){
                    $data .= $title . "," . $url . "," . $type . "," . $category . "," . $date . "," . $post->get_organic_traffic() . "," . $post->get_avg_position() . "," . $ii_count . "," . $oi_count . "," . $oe_count . "\n";
                }else{
                    $data .= $title . "," . $url . "," . $type . "," . $category . "," . $date . "," . $ii_count . "," . $oi_count . "," . $oe_count . "\n";
                }
            }
        }

        return $data;
    }

    /**
     * Prepare domains data for export
     *
     * @return string
     */
    public static function csv_domains($count)
    {
        $domains = Linkilo_Build_Console::getDomainsData(500, $count, '');
        $data = '';
        foreach ($domains['domains'] as $domain) {
            $max = max(count($domain['posts']), count($domain['links']), 1);
            for ($i=0; $i < $max; $i++) {
                $post = $domain['links'][$i]->post;
                $item = [
                    $domain['host'],
                    !empty($post) ? str_replace('&amp;', '&', $post->getLinks()->view) : '',
                    !empty($domain['links'][$i]->url) ? $domain['links'][$i]->anchor : '',
                    !empty($domain['links'][$i]->url) ? $domain['links'][$i]->url : '',
                    !empty($post) ? str_replace('&amp;', '&', $post->getLinks()->edit) : '',
                ];

                $data .= $item[0] . "," . $item[1] . "," . $item[2] . "," . $item[3] . "," . $item[4] . "\n";
            }
        }

        return $data;
    }

    /**
     * Prepare domains summary data for export
     *
     * @param $count
     * @return string
     */
    public static function csv_domains_summary($count)
    {
        $domains = Linkilo_Build_Console::getDomainsData(500, $count, '');
        $data = '';
        foreach ($domains['domains'] as $domain) {
            $data .= $domain['host'] . "," . count($domain['posts']) . "," . count($domain['links']) . "\n";
        }

        return $data;
    }

    /**
     * Prepare errors data for export
     *
     * @return string
     */
    public static function csv_error($count)
    {
        $links = Linkilo_Build_BrokenUrlError::getData(500, $count);
        $data = '';
        foreach ($links['links'] as $link) {
            $item = [
                '"' . addslashes($link->post_title) . '"',
                $link->url,
                $link->internal ? 'internal' : 'external',
                $link->code . ' ' . Linkilo_Build_BrokenUrlError::getCodeMessage($link->code),
                date('d M Y (H:i)', strtotime($link->created))
            ];
            $data .= $item[0] . "," . $item[1] . "," . $item[2] . "," . $item[3] . "," . $item[4] . "\n";
        }

        return $data;
    }
}
