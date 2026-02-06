<?php

namespace forge12\contactform7\CF7DoubleOptIn {
    if (!defined('ABSPATH')) {
        exit;
    }

    require_once('HTMLSelectOption.class.php');

    /**
     * HTMLSelect converts an given array to a HTML Option List.
     *
     * @package forge12\contactform7\CF7DoubleOptIn
     */
    class HTMLSelect
    {
        /**
         * Create a new Select
         * @var array
         */
        private $options = array();

        /**
         * Stores the attributes like class, id ... for the select field
         */
        private $attributes = array();

        /**
         * Stores the identifier name for the select field.
         * @var string
         */
        private $name = '';

        /**
         * Create a new option list
         */
        public function __construct($name, $options = array(), $attributes = array())
        {
            $this->setName($name);
            $this->setAttributes($attributes);
            $this->setOptions($options);
        }

        /**
         * Set the name for the select field
         * @param string $name
         */
        private function setName($name)
        {
            $this->name = $name;
        }

        /**
         * Set additional attributes like class, id, etc.
         * @param array $attributes
         */
        private function setAttributes(array $attributes)
        {
            if (empty($attributes)) {
                return;
            }
            $this->attributes = $attributes;
        }

        /**
         * Get a list of attributes
         * @param $key
         * @return array|mixed
         */
        private function getAttributeByKey($key)
        {
            if (isset($this->attributes[$key])) {
                return $this->attributes[$key];
            }
            return array();
        }

        /**
         * Return a rendered HTML Select list
         * @return string
         */
        public function get()
        {
            $attr = array();

            foreach ($this->attributes as $key => $value) {
                $attr[] = esc_attr($key) . '="' . esc_attr(implode(' ', $value)) . '"';
            }

            $options = '';
            foreach ($this->options as $Option/* @var HTMLSelectOption $Option */) {
                $options .= $Option->get();
            }

            return '<select name="' . esc_attr($this->name) . '" ' . implode(' ', $attr) . '>' . $options . '</select>';
        }

        /**
         * Check if an option exists.
         * @param $key
         * @return bool
         */
        private function isOption($key)
        {
            if (isset($this->options[$key])) {
                return true;
            }
            return false;
        }

        /**
         * Get the given option by the key
         * @param string|int
         *
         * @return HTMLSelectOption|null
         */
        public function getOptionByKey($key)
        {
            if ($this->isOption($key)) {
                return $this->options[$key];
            }
            return null;
        }

        /**
         * Set an Option selected by the given key.
         * @param $key
         */
        public function setOptionSelectedByKey($key)
        {
            if ($this->isOption($key)) {
                /**
                 * Reset the select value for all options
                 */
                foreach ($this->options as $optionKey => $Option/** @var HTMLSelectOption $Option */) {
                    if ($key == $optionKey) {
                        $Option->setSelected(true);
                    } else {
                        $Option->setSelected(false);
                    }
                }
            }
        }

        /**
         * Add a new option to the select list
         * @param $key
         * @param $value
         * @param bool $selected
         * @throws \Exception
         */
        public function addOption($key, $value, bool $selected = false)
        {
            if (empty($key)) {
                throw new \Exception('HTMLSelect:addOption - key must have a value');
            }
            if (empty($value)) {
                throw new \Exception('HTMLSelect:addOption - value must have a value');
            }

            $this->options[$key] = new HTMLSelectOption($key, $value, $selected);
        }

        /**
         * @param array $options
         */
        public function setOptions(array $options)
        {
            foreach ($options as $key => $value) {
                try {
                    $this->addOption($key, $value, false);
                } catch (\Exception $e) {
                    echo $e->getMessage();
                }
            }
        }
    }
}