<?php

final class Linkilo_Build_Initialize
{
    /**
     * Store all the classes inside an array
     * @return array Full list of classes
     */
    public static function get_services()
    {
        return [
            Linkilo_Build_Root::class,
            Linkilo_Build_BrokenUrlError::class,
            Linkilo_Build_RelatedMetaPosts::class,      //Added new class to handle related meta posts
            Linkilo_Build_RelateUrlKeyword::class,
            Linkilo_Build_PostUrl::class,
            // Linkilo_Build_ActiveLicense::class,        #Commented unusable code ref:license
            Linkilo_Build_Feed::class,
            Linkilo_Build_UrlRecord::class,
            Linkilo_Build_LanguageWordStemmer::class,
            Linkilo_Build_WpTerm::class,
            Linkilo_Build_UrlReplace::class,
            Linkilo_Build_FocusKeyword::class,
            Linkilo_Build_ConnectMultipleSite::class,
            Linkilo_Build_UrlClickChecker::class,
        ];
    }

    /**
     * Loop through the classes, initialize them,
     * and call the register() method if it exists
     * @return
     */
    public static function register_services()
    {
        foreach ( self::get_services() as $class ) {
            $service = self::instantiate( $class );
            if ( method_exists( $service, 'register' ) ) {
                $service->register();
            }
        }
    }

    /**
     * Initialize the class
     * @param  class $class    class from the services array
     * @return class instance  new instance of the class
     */
    private static function instantiate( $class )
    {
        $service = new $class();
        return $service;
    }
}
