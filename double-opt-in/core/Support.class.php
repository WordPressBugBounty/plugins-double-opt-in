<?php

namespace forge12\contactform7\CF7DoubleOptIn {
    if(!defined('ABSPATH')){
        exit();
    }

    /**
     * Class Support
     */
    class Support
    {
        /**
         * @var null
         */
        private static $_instance = null;

        /**
         * @return Support|null
         */
        public static function getInstance(){
            if(self::$_instance == null){
                self::$_instance = new Support();
            }
            return self::$_instance;
        }

        private function __construct()
        {
            $settings = get_option('f12-doi-settings');

            if(is_array($settings) && (isset($settings['support']) && $settings['support'] != 0) || !isset($settings['support'])) {
                add_action('wp_footer', array($this, 'addLink'), 9999);
            }
        }

        public function addLink(){
            ?>
            <!-- Double Opt-in Powered By Forge12 Interactive --><a title="WordPress Double-Opt-In" href="https://www.forge12.com/product/contact-form-7-double-opt-in/">WordPress Double Opt-in by Forge12</a>
            <?php
        }
    }
}