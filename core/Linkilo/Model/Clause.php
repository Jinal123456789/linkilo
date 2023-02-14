<?php

/**
 * Model for phrases
 *
 * Class Linkilo_Build_Model_Clause
 */
class Linkilo_Build_Model_Clause
{
    public static $prev_id = 0;
    public $src = '';
    public $text = '';
    public $sentence_src = '';
    public $sentence_text = '';
    public $suggestions = [];
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
