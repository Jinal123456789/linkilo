<?php

/**
 * Model for keywords
 *
 * Class Linkilo_Build_Model_FocusRelateKeyword
 */
class Linkilo_Build_Model_FocusRelateKeyword
{
    public $post_id;
    public $post_type;
    public $keyword_type;
    public $keywords;
    public $stemmed;
    public $checked;
    public $impressions;
    public $clicks;
    public $word_count;

    public function __construct($params = [])
    {
        //fill model properties from initial array
        foreach ($params as $key => $value) {
            if (property_exists($this, $key)) {
                switch($key){
                    case 'keywords':
                        // if the current item is the keywords, save the keywords
                        $this->{$key} = $value;
                        // save the stemmed version of the keywords
                        $this->stemmed = Linkilo_Build_WordFunctions::getStemmedSentence($value);
                        // and save the word count
                        $words = explode(' ', $value);
                        $this->word_count = count($words);
                    break;
                    default:
                    // for everything else, there's saving
                    $this->{$key} = $value;
                }
            }
        }
    }
}