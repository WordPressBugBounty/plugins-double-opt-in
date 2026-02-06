<?php

namespace forge12\contactform7\CF7DoubleOptIn {

    use forge12\plugins\ContactForm7;

    if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Class Backend
     * Responsible to handle the admin settings for the double opt-in field
     *
     * @package forge12\contactform7\CF7OptIn
     */
    class Backend
    {
        /**
         * Admin constructor.
         */
        public function __construct()
        {
            add_action('admin_init', array($this, 'addHooks'));
            add_action('admin_enqueue_scripts', array($this, 'addStyles'));
            add_filter('f12_cf7_doubleoptin_get_parameter', array($this, '_getParameter'));
        }

        /**
         * Return a list containing the array with all data stored within the contact form 7 form
         *
         * @param int $postID
         *
         * @return array
         */
        public function _getParameter($data)
        {
            return array_merge($data, array(
                'enable' => 0,
                'sender' => '',
                'subject' => '',
                'body' => '',
                'recipient' => '',
                'page' => -1,
                'conditions' => 'disabled',
                'template' => '',
                'category' => 0,
            ));
        }

        /**
         * Add the styles for the form
         */
        public function addStyles($hook)
        {
            wp_enqueue_script('f12-cf7-doubleoptin-admin', plugins_url('assets/f12-cf7-popup.js', __FILE__), array('jquery'));
            wp_localize_script('f12-cf7-doubleoptin-admin', 'doi', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('f12_doi_details')
            ));

            wp_enqueue_script('f12-cf7-doubleoptin-templateloader', plugins_url('assets/f12-cf7-templateloader.js', __FILE__), array('jquery'));
            wp_localize_script('f12-cf7-doubleoptin-templateloader', 'templateloader', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('f12_doi_templateloader'),
                'label_placeholder' => __('Please wait while we load the template...', 'double-opt-in')
            ));

        }

        /**
         * Add the hooks responsible to handle wordpress functions
         */
        public function addHooks()
        {
            add_filter('wpcf7_editor_panels', array($this, 'addPanel'), 10, 1);
            add_action('wpcf7_save_contact_form', array($this, 'save'), 10, 3);
        }

        /**
         * Add a custom panel to the contact form 7 options
         */
        public function addPanel(array $panels)
        {
            $panels['optin'] = array(
                'title' => __('Double-Opt-in', 'f12-cf7-doubleoptin'),
                'callback' => array($this, 'render')
            );

            return $panels;
        }

        /**
         * On Contact Form save store the information in the database
         *
         * @param \WPCF7_ContactForm $contact_form
         * @param                    $args
         * @param                    $context
         */
        public function save($contact_form, $args, $context)
        {
            $postID = $contact_form->id();

            // Validate nonce
            if (!isset($_POST['f12_cf7_doubleoptin_save_form_nonce']) || !wp_verify_nonce($_POST['f12_cf7_doubleoptin_save_form_nonce'], 'f12_cf7_doubleoptin_save_form_action')) {
                return;
            }

            // Check if the double opt in settings is set
            if (!$postID || !isset($_POST['doubleoptin'])) {
                update_post_meta($postID, 'f12-cf7-doubleoptin', array());

                return;
            }

            $parameter = CF7DoubleOptIn::getInstance()->sanitize_array($_POST['doubleoptin']);

            // load the default parameter
            $metadata = CF7DoubleOptIn::getInstance()->getParameter($postID);

            // validated all parameter defined in the default parameter options and  sanitize them.
            foreach ($metadata as $key => $value) {
                if (isset($parameter[$key])) {
                    if ($key == 'body') {
                        $metadata[$key] = $parameter[$key];
                    } else if($key == 'enable'){
                        $metadata[$key] = (int)$parameter[$key];
                    }else if ($key == 'sender') {
                        $metadata[$key] = $parameter[$key];
                    } else {
                        $metadata[$key] = $parameter[$key];
                    }
                } else if ($key == 'enable') {
                    $metadata[$key] = 0;
                }
            }

            $metadata = apply_filters('f12_cf7_doubleoptin_save_form', $metadata);

            update_post_meta($postID, 'f12-cf7-doubleoptin', $metadata);
        }

        /**
         * Return an option list containing all tags for the condition and a default field to disable the condition.
         *
         * @param \WPCF7_ContactForm $post
         *
         * @return array
         */
        private function getTags($post)
        {
            $tags = array();
            $arrayTags = $post->scan_form_tags();
            foreach ($arrayTags as $formTag/** @var \WPCF7_FormTag $formTag */) {
                if (!empty($formTag->name)) {
                    $tags[$formTag->name] = $formTag->name;
                }
            }
            return $tags;
        }

        /**
         * Show the backend double opt in options
         *
         * @param \WPCF7_ContactForm $post
         */
        public function render($post)
        {
            if (!$post) {
                return;
            }

            $metadata = CF7DoubleOptIn::getInstance()->getParameter($post->id());
            $id = "doubleoptin";
            ?>
            <div class="forge12-plugin" style="padding-top:0;">
                <div class="forge12-plugin-content" style="margin-top:0;">
                    <div class="forge12-plugin-content-main">
                        <div class="box" style="width:100%;">
                            <h2><?php _e('Opt-In Formular Settings', 'double-opt-in'); ?></h2>
                            <div class="option">
                                <div class="label">
                                    <label for="doubleoptin[enable]">
                                        <?php _e('Enable', 'double-opt-in'); ?>
                                    </label>
                                </div>
                                <div class="input">
                                    <input type="checkbox" name="doubleoptin[enable]" id="doubleoptin"
                                           value="1" <?php echo isset($metadata['enable']) && $metadata['enable'] == 1 ? 'checked = "checked"' : ''; ?> >
                                    <span>
                                    <?php _e('Yes', 'double-opt-in'); ?>
                                </span>
                                    <p><?php _e('Enable this checkox to activate the Double-Opt-In Mail for this formular.', 'double-opt-in'); ?></p>
                                </div>
                            </div>

                            <div class="option">
                                <div class="label">
                                    <label for="doubleoptin[category]">
                                        <?php _e('Category', 'double-opt-in'); ?>
                                    </label>
                                </div>
                                <div class="input">
                                    <?php
                                    $atts = array(
                                        'perPage' => -1,
                                        'orderBy' => 'name',
                                        'order' => 'ASC'
                                    );

                                    $list = Category::get_list($atts, $numberOfPages);

                                    $response = '<option value="0">' . __('Please select', 'double-opt-in') . '</option>';
                                    foreach ($list as $Category/** @var Category $Category */) {
                                        $selected = "";
                                        if ($Category->get_id() == $metadata['category']) {
                                            $selected = 'selected="selected"';
                                        }
                                        $response .= '<option value="' . esc_attr($Category->get_id()) . '" '.$selected.'>' . esc_attr($Category->get_name()) . '</option>';
                                    }
                                    ?>
                                    <select name="doubleoptin[category]"><?php echo wp_kses($response, array('option' => array('value' => array(), 'selected' => array()))); ?></select>
                                    <span>
                                        <?php _e('Assign the Opt-In to a Category for an easier administration.', 'double-opt-in'); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="option">
                                <div class="label">
                                    <label for="doubleoptin[page]">
                                        <?php _e('Dynamic Condition', 'double-opt-in'); ?>
                                    </label>
                                </div>
                                <div class="input">
                                <span>
                                <?php _e('Enable the Opt-In only when the field ', 'double-opt-in'); ?>
                                </span>
                                    <?php
                                    $ConditionList = new HTMLSelect($id . '[conditions]', array_merge(array('disable' => __('Disabled', 'double-opt-in')), $this->getTags($post)), array('id' => array($id . '-conditions'), 'class' => array('large-text', 'code')));
                                    $ConditionList->setOptionSelectedByKey($metadata['conditions']);
                                    echo wp_kses($ConditionList->get(), array('select' => array('name' => array()), 'option' => array('value' => array(), 'selected' => array())));
                                    ?>
                                    <span>
                                <?php _e('has been filled/checked by the visitor.', 'double-opt-in'); ?>
                            </span>
                                </div>
                            </div>

                            <div class="option">
                                <div class="label">
                                    <label for="doubleoptin[page]">
                                        <?php _e('Confirmation Page', 'double-opt-in'); ?>
                                    </label>
                                </div>
                                <div class="input">
                                    <?php wp_dropdown_pages(array(
                                        'show_option_none' => __('default', 'double-opt-in'),
                                        'name' => 'doubleoptin[page]',
                                        'selected' => $metadata['page']
                                    )); ?>
                                    <p>
                                        <?php _e('Select the page that should be displayed after the user clicks on the confirmation link', 'double-opt-in'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="box" style="width:100%;">
                            <h2><?php _e('Customize your Opt-In Mail', 'double-opt-in'); ?></h2>
                            <div class="option">
                                <div class="label">
                                    <label for="doubleoptin-recipient">
                                        <label for="<?php echo esc_attr($id); ?>-recipient"><?php echo __('To', 'double-opt-in'); ?></label>
                                    </label>
                                </div>
                                <div class="input">
                                    <input type="text" id="<?php echo esc_attr($id); ?>-recipient"
                                           name="<?php echo esc_attr($id); ?>[recipient]"
                                           class="large-text code" size="70"
                                           value="<?php echo esc_attr($metadata['recipient']); ?>"/>
                                    <p>
                                        <?php _e('This field defines who will receive the Double-Opt-In mail. Enter one of the following tags that represents the mail for the customer:', 'double-opt-in'); ?>
                                    </p>
                                    <p>
                                        <code>
                                            <?php
                                            $arrayTags = $this->getTags($post);
                                            foreach ($arrayTags as $key => $value) {
                                                $arrayTags[$key] = "[" . esc_html($value) . "]";
                                            }
                                            echo esc_html(implode(",", array_filter($arrayTags)));
                                            ?>
                                        </code>
                                    </p>
                                </div>
                            </div>

                            <div class="option">
                                <div class="label">
                                    <label for="<?php echo esc_attr($id); ?>-sender">
                                        <?php echo esc_html(__('From', 'double-opt-in')); ?>
                                    </label>
                                </div>
                                <div class="input">
                                    <input type="text" id="<?php echo esc_attr($id); ?>-sender"
                                           name="<?php echo esc_attr($id); ?>[sender]"
                                           class="large-text code" size="70"
                                           value="<?php echo esc_attr($metadata['sender']); ?>"/>
                                    <p>
                                        <?php _e('This field defines who will sent the mail. You can either enter the e-Mail (e.g.: your-email@yourdomain.de) or use a placeholder (e.g.: [_site_admin_email]).', 'double-opt-in'); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="option">
                                <div class="label">
                                    <label for="<?php echo esc_attr($id); ?>-subject">
                                        <?php echo esc_html(__('Subject', 'double-opt-in')); ?>
                                    </label>
                                </div>
                                <div class="input">
                                    <input type="text" id="<?php echo esc_attr($id); ?>-subject"
                                           name="<?php echo esc_attr($id); ?>[subject]"
                                           class="large-text code" size="70"
                                           value="<?php echo esc_attr($metadata['subject']); ?>"/>
                                    <p>
                                        <?php _e('Enter a custom subject for the Double-Opt-In mail (e.g.: Please confirm your registration).', 'double-opt-in'); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="option">
                                <div class="label">
                                    <label for="<?php echo esc_attr($id); ?>-body">
                                        <?php echo esc_html(__('Template', 'double-opt-in')); ?>
                                    </label>
                                </div>
                                <div class="input">
                                    <div class="preview">
                                        <div class="preview-item">
                                            <div class="preview-item-inner <?php if ($metadata['template'] == 'blank') {
                                                echo 'active';
                                            }; ?>">
                                                <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'mails/blank.png'); ?>"
                                                     class="f12-cf7-templateloader-preview" data-template="blank"
                                                     title="Blank"/>
                                            </div>
                                        </div>
                                        <div class="preview-item">
                                            <div class="preview-item-inner <?php if ($metadata['template'] == 'newsletter_en') {
                                                echo 'active';
                                            }; ?>">
                                                <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'mails/newsletter_en.png'); ?>"
                                                     class="f12-cf7-templateloader-preview" title="Newsletter EN"
                                                     data-template="newsletter_en" alt=""/>
                                            </div>
                                        </div>
                                        <div class="preview-item">
                                            <div class="preview-item-inner <?php if ($metadata['template'] == 'newsletter_en_2') {
                                                echo 'active';
                                            }; ?>">
                                                <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'mails/newsletter_en_2.png'); ?>"
                                                     class="f12-cf7-templateloader-preview" title="Newsletter EN 2"
                                                     data-template="newsletter_en_2"/>
                                            </div>
                                        </div>
                                        <div class="preview-item">
                                            <div class="preview-item-inner <?php if ($metadata['template'] == 'newsletter_en_3') {
                                                echo 'active';
                                            }; ?>">
                                                <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'mails/newsletter_en_3.png'); ?>"
                                                     class="f12-cf7-templateloader-preview" title="Newsletter EN 3"
                                                     data-template="newsletter_en_3"/>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    $TemplateList = new HTMLSelect($id . '[template]', array(
                                        'blank' => 'blank',
                                        'newsletter_en' => 'newsletter_en',
                                        'newsletter_en_2' => 'newsletter_en_2',
                                        'newsletter_en_3' => 'newsletter_en_3'
                                    ), array(
                                        'class' => array('f12-cf7-templateloader'),
                                        'style' => array('display:none;')
                                    ));
                                    $TemplateList->setOptionSelectedByKey($metadata['template']);
                                    echo wp_kses($TemplateList->get(), array('select' => array('style' => array(), 'class' => array(), 'name' => array()), 'option' => array('value' => array(), 'selected' => array())));
                                    ?>
                                </div>
                            </div>

                            <div class="option">
                                <div class="label">
                                    <label for="<?php echo esc_attr($id); ?>-body">
                                        <?php echo esc_html(__('Message body', 'double-opt-in')); ?>
                                    </label>
                                </div>
                                <div class="input">
                                    <textarea id="<?php echo esc_attr($id); ?>-body"
                                              name="<?php echo esc_attr($id); ?>[body]"
                                              cols="100" rows="18"
                                              class="large-text code"><?php echo esc_textarea($metadata['body']); ?></textarea>
                                    <p>
                                        <?php _e('Enter the Message you want to sent to your customer. Do not forget to add the Double-Opt-In tag ([doubleoptinlink]) e.g.:', 'double-opt-in'); ?>
                                        <code>
                                            &lt;a href="[doubleoptinlink]"&gt;Confirm the registration&lt;/a&gt;
                                        </code>
                                    </p>
                                    <p>
                                        <?php _e('These placeholders are available for the opt-in Message body: ', 'double-opt-in'); ?>
                                    </p>
                                    <p>
                                        <code>[doubleoptinlink],[doubleoptin_form_url],[doubleoptin_form_subject],[doubleoptin_form_date],[doubleoptin_form_time],[_site_admin_email],<?php
                                            $arrayTags = $this->getTags($post);
                                            foreach ($arrayTags as $key => $value) {
                                                $arrayTags[$key] = "[" . esc_html($value) . "]";
                                            }
                                            echo implode(",", array_filter($arrayTags));
                                            ?>
                                        </code>
                                    </p>
                                </div>
                            </div>
                        </div>


                        <?php do_action('f12_cf7_doubleoptin_admin_panel', $post, $metadata, $id); ?>
                        <?php wp_nonce_field('f12_cf7_doubleoptin_save_form_action', 'f12_cf7_doubleoptin_save_form_nonce'); ?>
                    </div>
                </div>
            </div>
            <?php
        }
    }
}