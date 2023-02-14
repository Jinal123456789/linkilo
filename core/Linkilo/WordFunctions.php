<?php

/**
 * Work with words and sentences
 */
class Linkilo_Build_WordFunctions
{
    public static $endings = ['"', '!', '?', ',', ')','(', '.', '`', "'", ':', ';', '|'];

    /**
     * Clean sentence from ignore phrases and divide to words
     *
     * @param $sentence
     * @return array
     */
    public static function cleanFromIgnorePhrases($sentence)
    {
        $phrases = Linkilo_Build_AdminSettings::getIgnorePhrases();
        $sentence = self::clearFromUnicode($sentence);
        mb_eregi_replace('\s+', ' ', $sentence);
        $sentence = trim($sentence);
        $sentence = str_ireplace($phrases, '', $sentence);

        return explode(' ', $sentence);
    }

    /**
     * Get words from sentence
     *
     * @param $sentence
     * @return array
     */
    public static function getWords($sentence)
    {
        $sentence = self::clearFromUnicode($sentence);
        $words = explode(' ', str_replace(self::$endings, '', $sentence));
        foreach ($words as $key => $word) {
            $word = trim($word);

            if (!empty($word)) {
                $words[$key] = self::strtolower($word);
            }
        }

        return $words;
    }

    /**
     * Clear the sentence of Unicode whitespace symbols
     *
     * @param $sentence
     * @return string
     */
    public static function clearFromUnicode($sentence)
    {   
        $selected_lang = (defined('LINKILO_CURRENTLY_SET_LANGUAGE')) ? LINKILO_CURRENTLY_SET_LANGUAGE : 'english';
        
        if('russian' === $selected_lang){
            // just remove a limited set of chars since Cyrillic chars can be defined with a pair of UTF-8 hex codes.
            // So what is a control char in latin-01, in the Cyrillic char set can be the first hex code in the "Э" char.
            // And removing the "control" hex code breaks the "Э" char.
            $sentence = preg_replace('/[\x00-\x1F\x7F]/', ' ', $sentence);
            return $sentence;
        }elseif('french' === $selected_lang){
            $urlEncodedWhiteSpaceChars   = '%81,%7F,%8D,%8F,%C2%90,%C2,%90,%9D,%C2%A0,%C2%AD,%AD,%08,%09,%0A,%0D';
        }elseif('spanish' === $selected_lang || 'portuguese' === $selected_lang){
            $urlEncodedWhiteSpaceChars   = '%81,%7F,%8D,%8F,%C2%90,%C2,%90,%9D,%C2%A0,%A0,%C2%AD,%08,%09,%0A,%0D';
        }elseif('slovak' === $selected_lang){
            $sentence = preg_replace('/\xC2\x90|\xC2\xA0|\xC2\xAD|&nbsp;/', ' ', $sentence);
            return preg_replace('/[\x00-\x1F]/', ' ', $sentence);
        }elseif('arabic' === $selected_lang){
            $sentence = preg_replace('/\xC2\x90|\xC2\xA0|\xC2\xAD|&nbsp;/', ' ', $sentence);
            return preg_replace('/[\x00-\x1F]/', ' ', $sentence);
        }else{
            $urlEncodedWhiteSpaceChars   = '%81,%7F,%8D,%8F,%C2%90,%C2,%90,%9D,%C2%A0,%A0,%C2%AD,%AD,%08,%09,%0A,%0D';
        }

        $temp = explode(',', $urlEncodedWhiteSpaceChars);
        $sentence  = urlencode($sentence);
        foreach($temp as $v){
            $sentence  =  str_replace($v, ' ', $sentence);
        }
        $sentence = urldecode($sentence);

        return $sentence;
    }

    /**
     * Clean words from ignore words
     *
     * @param $words
     * @return mixed
     */
    public static function cleanIgnoreWords($words)
    {
        $ignore_words = Linkilo_Build_AdminSettings::getIgnoreWords();
        $ignore_numbers = get_option(LINKILO_NUMBERS_TO_IGNORE_OPTIONS, 1);

        foreach ($words as $key => $word) {
            if (($ignore_numbers && is_numeric(str_replace(['.', ',', '$'], '', $word))) || in_array($word, $ignore_words)) {
                unset($words[$key]);
            }
        }

        return $words;
    }

    /**
     * Divice text to words and Stem them
     *
     * @param $text
     * @return array
     */
    public static function getStemmedWords($text)
    {
        $words = Linkilo_Build_WordFunctions::cleanFromIgnorePhrases($text);
        $words = array_unique(Linkilo_Build_WordFunctions::cleanIgnoreWords($words));

        foreach ($words as $key_word => $word) {
            $words[$key_word] = Linkilo_Build_Stemmer::Stem($word);
        }

        return $words;
    }
    
    /**
     * Takes a string of words and lowercases and stemms the words.
     * Will strip out punctuation, so should only be used on single sentences
     * 
     * @param string $text The input string to be set to lower case and stemmed
     * @return string $words The stemmed and lower cased string of words.
     **/
    public static function getStemmedSentence($text){
        $text = Linkilo_Build_WordFunctions::strtolower($text);
        $words = self::getWords($text);

        foreach ($words as $key_word => $word) {
            $words[$key_word] = Linkilo_Build_Stemmer::Stem($word);
        }

        return implode(' ', $words);
    }

    /**
     * A strtolower function for use on languages that are accented, or non latin.
     * 
     * @param string $string (The text to be lowered)
     * @return string (The string that's been put into lower case)
     */
    public static function strtolower($string){
        // if the wamania project is active, use their strtolower function
        if(class_exists('Wamania\\Snowball\\Utf8')){
            return Wamania\Snowball\Utf8::strtolower($string);
        }else{
            return strtolower($string);
        }
    }

    /**
     * Remove quotes in the begin and in the end of sentence
     *
     * @param $sentence
     * @return false|string
     */
    public static function removeQuotes($sentence)
    {
        if (substr($sentence, 0, 1) == '"' || substr($sentence, 0, 1) == "'") {
            $sentence = substr($sentence, 1);
        }

        if (substr($sentence, -1) == '"' || substr($sentence, -1) == "'") {
            $sentence = substr($sentence, 0,  -1);
        }

        return $sentence;
    }

    /**
     * Replace non ASCII symbols with unicode
     *
     * @param $content
     * @return string
     */
    public static function replaceUnicodeCharacters($content, $revert = false)
    {
        $replacements = [
            ['à', '\u00E0'],
            ['À', '\u00C0'],
            ['â', '\u00E2'],
            ['Â', '\u00C2'],
            ['è', '\u00E8'],
            ['È', '\u00C8'],
            ['é', '\u00E9'],
            ['É', '\u00C9'],
            ['ê', '\u00EA'],
            ['Ê', '\u00CA'],
            ['ë', '\u00EB'],
            ['Ë', '\u00CB'],
            ['î', '\u00EE'],
            ['Î', '\u00CE'],
            ['ï', '\u00EF'],
            ['Ï', '\u00CF'],
            ['ô', '\u00F4'],
            ['Ô', '\u00D4'],
            ['ù', '\u00F9'],
            ['Ù', '\u00D9'],
            ['û', '\u00FB'],
            ['Û', '\u00DB'],
            ['ü', '\u00FC'],
            ['Ü', '\u00DC'],
            ['ÿ', '\u00FF'],
            ['Ÿ', '\u0178'],
            ['-', '\u2013'],
            ["'", '\u2019'],
            ["’", '\u2019']
        ];

        $from = [];
        $to = [];
        foreach ($replacements as $replacement) {
            if ($revert) {
                $from[] = $replacement[1];
                $to[] = $replacement[0];
            } else {
                $from[] = $replacement[0];
                $to[] = $replacement[1];
            }
        }

        return str_ireplace($from, $to, $content);
    }

    /**
     * Add slashes to the new line code
     *
     * @param $content
     */
    public static function addSlashesToNewLine(&$content)
    {
        $content = str_replace('\n', '\\\n', $content);
    }

    /**
     * Remove emoji from text
     *
     * @param $text
     * @return string|string[]|null
     */
    public static function remove_emoji($text){
        $pattern = file_get_contents(LINKILO_PLUGIN_DIR_PATH . 'includes/emoji_pattern.txt');
        return preg_replace($pattern, '', $text);
    }

    /**
     * Remove everything except of characters from the text
     *
     * @param $text
     * @return string|string[]|null
     */
    public static function onlyText($text) {
        $text = mb_convert_encoding($text, 'UTF-8');
        return mb_eregi_replace('/[^A-Za-z0-9[:alpha:]\-\s]/', '', $text);
    }
}
