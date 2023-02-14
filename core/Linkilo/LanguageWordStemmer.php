<?php

class Linkilo_Build_LanguageWordStemmer{

    public function register(){
        self::load_word_stemmer();
    }
    
    public static function load_word_stemmer(){
        
        $selected_language = Linkilo_Build_AdminSettings::getCurrentLanguage();
        $stemmer_file = '';

        switch($selected_language){
            case 'spanish':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/ES_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'spanish');
                break;
            case 'french':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/FR_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'french');
                break;
            case 'german':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/DE_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'german');
                break;
            case 'russian':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/RU_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'russian');
                break;
            case 'portuguese':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/PT_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'portuguese');
                break;
            case 'dutch':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/NL_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'dutch');
                break;
            case 'danish':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/DA_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'danish');
                break;
            case 'italian':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/IT_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'italian');
                break;
            case 'polish':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/PL_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'polish');
                break;
            case 'norwegian':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/NO_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'norwegian');
                break;
            case 'swedish':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/SW_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'swedish');
                break;
            case 'slovak':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/SK_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'slovak');
                break;
            case 'arabic':
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/AR_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'arabic');
                break;
            default:
                $stemmer_file = LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/EN_Stemmer.php';
                define('LINKILO_CURRENTLY_SET_LANGUAGE', 'english');
                break;
        }

        include_once(LINKILO_PLUGIN_DIR_PATH . 'includes/word_stemmers/vendor/autoload.php');
        include_once($stemmer_file);
    }
}
?>
