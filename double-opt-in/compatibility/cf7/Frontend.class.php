<?php

namespace forge12\contactform7\CF7DoubleOptIn {
    if (!defined('ABSPATH')) {
        exit;
    }

    use forge12\plugins\ContactForm7;

    /**
     * Class Frontend
     * Responsible to handle the frontend of the Double OptIn
     *
     * @package forge12\contactform7\CF7DoubleOptIn
     */
    class Frontend
    {
        /**
         * Admin constructor.
         */
        public function __construct()
        {
            add_action('init', array($this, 'validateOptIn'));
            add_action('wpcf7_before_send_mail', array($this, 'onSubmit'), 5, 3);
            add_action('shutdown', array($this, 'cleanup'));
        }

        /**
         * Store the optin value for the given object by the hash value
         *
         * @param $hash
         * @param $value
         *
         * @return int The number of rows changed.
         */
        private function updateOptInByHash($hash, $value)
        {
            $OptIn = OptIn::get_by_hash($hash);
            if ($OptIn->is_confirmed()) {
                do_action('f12_cf7_doubleoptin_already_confirmed', $hash, $OptIn);
                return 0;
            }

            do_action('f12_cf7_doubleoptin_before_confirm', $hash, $OptIn);

            $OptIn->set_doubleoptin($value);
            $OptIn->set_updatetime(time());
            $OptIn->set_ipaddr_confirmation(CF7DoubleOptIn::getInstance()->getIPAdress());

            $result = $OptIn->save();

            if ($result) {
                do_action('f12_cf7_doubleoptin_after_confirm', $hash, $OptIn);
            }

            return (int)$result;
        }


        /**
         * Add Stylesheets
         */
        public function validateOptIn()
        {

            if (!isset($_GET['optin'])) {
                return;
            }

            $hash = sanitize_text_field($_GET['optin']);

            $OptIn = OptIn::get_by_hash($hash);

            if (null != $OptIn) {
                if ($OptIn->isType('cf7') && $this->updateOptInByHash($hash, 1) > 0) {
                    if (!apply_filters('f12_cf7_doubleoptin_send_default_mail', true, $OptIn->get_cf_form_id())) {
                        return;
                    }

                    // Remove the filter for the forge12 spam captcha
                    if (class_exists('\forge12\contactform7\CF7Captcha\TimerValidatorCF7')) {
                        remove_filter('wpcf7_spam', '\forge12\contactform7\CF7Captcha::isSpam');
                        remove_filter('wpcf7_spam', '\forge12\contactform7\CF7Captcha\CF7IPLog::isSpam');
                        remove_action('wpcf7_mail_sent', '\forge12\contactform7\CF7Captcha\CF7IPLog::doLogIP');
                    }

                    // Remove the filter for the google repatcha validation
                    remove_filter('wpcf7_spam', 'wpcf7_recaptcha_verify_response', 9);

                    // Setup the contact form 7 which will fire the form send after loading the page)
                    $data = maybe_unserialize($OptIn->get_content());
                    $_POST = CF7DoubleOptIn::getInstance()->sanitize_array($data);

                    $ContactForm = \WPCF7_ContactForm::get_instance($OptIn->get_cf_form_id());

                    $submission = \WPCF7_Submission::get_instance($ContactForm);

                    // re add the filter to ensure for all other forms the recaptcha is used
                    if (function_exists('wpcf7_recaptcha_verifiy_response')) {
                        add_filter('wpcf7_spam', 'wpcf7_recaptcha_verify_response', 9, 2);
                    }

                    // re add the filter for the forge12 spam captcha
                    if (class_exists('\forge12\contactform7\CF7Captcha\TimerValidatorCF7')) {
                        add_filter('wpcf7_spam', '\forge12\contactform7\CF7Captcha\TimerValidatorCF7::isSpam', 100, 2);
                        add_filter('wpcf7_spam', '\forge12\contactform7\CF7Captcha\CF7IPLog::isSpam', 100, 2);
                        add_action('wpcf7_mail_sent', '\forge12\contactform7\CF7Captcha\CF7IPLog::doLogIP', 100, 1);
                    }
                }
            }
        }

        /**
         * Add the double opt in link to the body
         *
         * @param string $body
         * @param OptIn  $OptIn
         *
         * @return string
         */
        private function addDoubleOptInLink($body, $OptIn)
        {
            $link = $OptIn->get_link_optin();

            return str_replace('[doubleoptinlink]', $link, $body);
        }

        /**
         * Add additional placeholder like time, date, subject
         *
         * @param $form       \WPCF7_ContactForm
         * @param $submission \WPCF7_Submission
         * @param $body
         * @param $OptIn
         * @param $parameter
         */
        private function addAdditionalPlaceholder($form, $submission, $body, $OptIn, $parameter)
        {
            # set the default timezone
            date_default_timezone_set(get_option('timezone_string'));
            $placeholder = array(
                'doubleoptin_form_url' => $submission->get_meta('url'),
                'doubleoptin_form_subject' => $parameter['subject'],
                'doubleoptin_form_date' => date(get_option('date_format')),
                'doubleoptin_form_time' => date(get_option('time_format')),
                'doubleoptin_form_email' => get_option('admin_email')
            );

            foreach ($placeholder as $key => $value) {
                $body = str_replace('[' . $key . ']', $value, $body);
            }
            return $body;
        }

        /**
         * Add formular informaition to the database
         *
         * @param $form \WPCF7_ContactForm
         * @param array $parameter
         * @param array $files
         *
         * @return OptIn|null
         */
        private function addRequest($form, $parameter, $files = array())
        {
            global $wpdb;

            if (null == $wpdb) {
                return 0;
            }

            $copiedFields = array();

            if (!empty($files)) {
                foreach ($files as $key => $subfiles) {
                    foreach ($subfiles as $file) {
                        $newFile = explode('/', $file);
                        $name = $newFile[count($newFile) - 1];
                        $name = time() . '_' . $name;
                        $newFile[count($newFile) - 1] = $name;
                        $newFile = implode("/", $newFile);

                        if (copy($file, $newFile)) {
                            $copiedFields[] = $newFile;
                        }
                    }
                }
            }

            $parameter = \apply_filters('f12_cf7_doubleoptin_add_request_parameter', $parameter);

            $formParameter = CF7DoubleOptIn::getInstance()->getParameter($form->id());

            // Get Recipient
            $recipient = $formParameter['recipient'];
            $recipient = str_replace('[', '',$recipient);
            $recipient = str_replace(']', '', $recipient);

            if(isset($_POST[$recipient])){
                $recipient = sanitize_email($_POST[$recipient]);
            }else{
                $recipient = '';
            }

            $data = array(
                'cf_form_id' => $form->id(),
                'doubleoptin' => 0,
                'createtime' => time(),
                'content' => maybe_serialize($parameter),
                'files' => maybe_serialize($copiedFields),
                'ipaddr_register' => CF7DoubleOptIn::getInstance()->getIPAdress(),
                'category' => (int)$formParameter['category'],
                'form' => $form->form_html(),
                'email' => $recipient
            );

            $OptIn = new OptIn($data);
            if ($OptIn->save()) {
                return $OptIn;
            }
            return null;
        }

        /**
         * Validate if the optin is enabled.
         */
        private function isOptinEnabled($form)
        {
            // Disable optin sending if the optin flag is set.
            if (isset($_GET['optin'])) {
                return false;
            }

            $parameter = CF7DoubleOptIn::getInstance()->getParameter($form->id());

            if ((int)$parameter['enable'] != 1) {
                return false;
            }

            // Check the custom condition
            if (isset($parameter['conditions'])) {
                $condition = sanitize_text_field($parameter['conditions']);

                if ($condition != 'disable' && (!isset($_POST[$condition]) || empty($_POST[$condition]))) {
                    return false;
                }
            }

            return true;
        }

        /**
         * On Form Submit add the double optin if enabled
         *
         * @param $form       \WPCF7_ContactForm
         * @param bool
         * @param $submission \WPCF7_Submission
         */
        public function onSubmit($form, &$abort, $submission)
        {
            $parameter = CF7DoubleOptIn::getInstance()->getParameter($form->id());

            if ($this->isOptinEnabled($form)) {
                // Remove Contact Form 7 DB hook to ensure the optin mail is not saved
                remove_action('wpcf7_before_send_mail', 'cfdb7_before_send_mail');

                $OptIn = $this->addRequest($form, $submission->get_posted_data(), $submission->uploaded_files());
                $body = $this->addDoubleOptInLink($parameter['body'], $OptIn);
                $body = $this->addAdditionalPlaceholder($form, $submission, $body, $OptIn, $parameter);

                $body = apply_filters('f12_cf7_doubleoptin_body', $body);

                // Update the opt in after setting the opt-in content.
                $OptIn->set_mail_optin($body);
                $OptIn->save();

                // Prepare the Opt-In Mail
                \WPCF7_Mail::send(array(
                    'subject' => $parameter['subject'],
                    'body' => $body,
                    'sender' => $parameter['sender'],
                    'recipient' => $parameter['recipient'],
                    'use_html' => true,
                ), 'mail');

                // Skip all mails following
                add_filter('wpcf7_skip_mail', '__return_true');

                do_action('f12_cf7_doubleoptin_sent', $form, $form->id());

                //$abort = true;
            } else if (isset($_GET['optin'])) {

                $OptIn = OptIn::get_by_hash(sanitize_text_field($_GET['optin']));

                if (null != $OptIn) {
                    $files = maybe_unserialize($OptIn->get_files());

                    foreach ($files as $file) {
                        if (!empty($file)) {
                            if (apply_filters('f12_cf7_doubleoptin_files_mail_1', true, $OptIn)) {
                                $submission->add_extra_attachments($file);
                            }

                            if (apply_filters('f12_cf7_doubleoptin_files_mail_2', true, $OptIn)) {
                                $submission->add_extra_attachments($file, 'mail_2');
                            }
                        }
                    }
                }
            }
        }

        /**
         * On Cleanup we delete all files that are not required anymore.
         */
        public function cleanup()
        {
            if (isset($_GET['optin'])) {
                $OptIn = OptIn::get_by_hash(esc_sql($_GET['optin']));

                if (null != $OptIn) {
                    $files = maybe_unserialize($OptIn->get_files());

                    foreach ($files as $file) {
                        if (!empty($file)) {
                            if (is_file($file)) {
                                if (!unlink($file)) {
                                    error_log("Could not delete file " . $file . "!");
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}