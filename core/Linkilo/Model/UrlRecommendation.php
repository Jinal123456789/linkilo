<?php

/**
 * Model for suggestions
 *
 * Class Linkilo_Build_Model_UrlRecommendation
 */
class Linkilo_Build_Model_UrlRecommendation
{
    public $post = false;
    public $words = [];
    public $anchor = '';
    public $sentence_with_anchor = '';
    public $post_score = 0;
    public $anchor_score = 0;
    public $total_score = 0;
    public $opacity = 1;

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
