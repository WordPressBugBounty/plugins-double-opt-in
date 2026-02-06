<?php

namespace forge12\contactform7\CF7DoubleOptIn {
    if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * HTMLSelectOption converts an given array to a HTML Option List.
     *
     * @package forge12\contactform7\CF7DoubleOptIn
     */
    class HTMLSelectOption
    {
        /**
         * The key of the option field
         * @var string|int
         */
        private $key = '';
        /**
         * The value of the option field
         * @var string|int
         */
        private $value = '';

        /**
         * Flag if selected or not
         * @var bool
         */
        private $selected = false;

        /**
         * Create a new option list
         */
        public function __construct($key, $value, $selected = false)
        {
            $this->setKey($key);
            $this->setValue($value);
            $this->setSelected($selected);
        }

        /**
         * Return the HTML String for the option value
         * @return string
         */
        public function get()
        {
            if($this->isSelected()){
                return '<option value="' . esc_attr($this->getKey()) . '" selected="selected">' . esc_html($this->getValue()) . '</option>';
            }else{
                return '<option value="' . esc_attr($this->getKey()) . '">' . esc_html($this->getValue()) . '</option>';
            }
        }

        /**
         * @return string
         */
        public function getValue(): string
        {
            return $this->value;
        }

        /**
         * @param string $value
         */
        private function setValue(string $value)
        {
            $this->value = $value;
        }

        /**
         * @return string
         */
        public function getKey(): string
        {
            return $this->key;
        }

        /**
         * @param string $key
         */
        private function setKey(string $key)
        {
            $this->key = $key;
        }

        /**
         * @return bool
         */
        public function isSelected(): bool
        {
            return $this->selected;
        }

        /**
         * @param bool $selected
         */
        public function setSelected(bool $selected)
        {
            $this->selected = $selected;
        }
    }
}