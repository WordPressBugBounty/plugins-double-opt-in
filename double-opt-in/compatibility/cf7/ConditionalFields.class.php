<?php

namespace forge12\contactform7\CF7DoubleOptIn {
    if (!defined('ABSPATH')) {
        exit;
    }


    /**
     * Class ConditionalFields Support
     * Add support for conditional fields if available.
     *
     * @package forge12\contactform7\CF7DoubleOptIn
     */
    class ConditionalFields
    {
        private $conditionalFieldsParameter = array(
            '_wpcf7cf_hidden_group_fields' => '',
            '_wpcf7cf_hidden_groups' => '',
            '_wpcf7cf_visible_groups' => '',
            '_wpcf7cf_repeaters' => '',
            '_wpcf7cf_steps' => '',
            '_wpcf7cf_options' => ''
        );

        /**
         * Admin constructor.
         */
        public function __construct()
        {
            add_filter('f12_cf7_doubleoptin_add_request_parameter', array($this,'_initConditionalFieldParameter'), 10, 1);
            add_filter('f12_cf7_doubleoptin_body', array($this, '_getOptinBody'), 10, 1);
        }

        /**
         * Add the option to add conditional fields also to the optin mail.
         * @return string
         */
        public function _getOptinBody($body)
        {
            if(!defined('WPCF7CF_PLUGIN') || !class_exists('\Wpcf7cfMailParser')){
                return $body;
            }

            $CFMP = new \Wpcf7cfMailParser($body, $this->conditionalFieldsParameter['_wpcf7cf_visible_groups'], $this->conditionalFieldsParameter['_wpcf7cf_hidden_groups'], $this->conditionalFieldsParameter['_wpcf7cf_repeaters'], array());
            return $CFMP->getParsedMail();
        }

        /**
         * Check if conditional field plugin is available and if yes, check for the parameter
         * which has to be stored within the content.
         * @param $parameter
         */
        public function _initConditionalFieldParameter($parameter)
        {
            if (!defined('WPCF7CF_PLUGIN')) {
                return $parameter;
            }

            foreach ($this->conditionalFieldsParameter as $key => $value) {
                if (isset($_POST[$key])) {
                    $parameter[$key] = sanitize_text_field($_POST[$key]);
                    $this->conditionalFieldsParameter[$key] = sanitize_text_field(json_decode(stripslashes($_POST[$key])));
                }
            }
            return $parameter;
        }
    }
}