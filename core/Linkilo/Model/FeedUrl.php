<?php

/**
 * Model for links
 *
 * Class Linkilo_Build_Model_FeedUrl
 */
class Linkilo_Build_Model_FeedUrl
{
    public $link_id = 0;
    public $url = '';
    public $host = '';
    public $internal = false;
    public $post = false;
    public $anchor = '';
    public $added_by_plugin = false;
    public $location = 'content';

    public function __construct($params = [])
    {
        //fill model properties from initial array
        foreach ($params as $key => $value) {
            if (isset($this->{$key})) {
                $this->{$key} = $value;
            }
        }
    }
}
