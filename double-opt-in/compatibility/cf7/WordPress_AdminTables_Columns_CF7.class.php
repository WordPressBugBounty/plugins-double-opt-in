<?php

namespace forge12\contactform7\CF7DoubleOptIn {
    if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Extend the Contact Form 7 Table view
     * to add custom Columns
     *
     * @package forge12\contactform7\CF7OptIn
     */
    class WordPress_AdminTables_Columns_CF7
    {
        /**
         * Admin constructor.
         */
        public function __construct()
        {
            add_action('admin_enqueue_scripts', [$this, 'addAssets']);

            add_filter( 'manage_toplevel_page_wpcf7_columns', array( $this, '_onLoadColumn' ), 999, 1 );
            add_filter( 'wpcf7_custom_default', array( $this, '_onLoadColumnValue' ), 10, 3 );
        }

        public function addAssets(){
            wp_register_style('f12-cf7-doi-cf7', plugin_dir_url(__FILE__).'assets/admin-cf7.css');
            wp_enqueue_style('f12-cf7-doi-cf7');
        }

        public function _onLoadColumn( $columns ) {
            $columns['optins']  = __( 'Opt-Ins', 'affiliater' );

            return $columns;
        }

        public function _onLoadColumnValue( $html, $item, $column_name ) {
            switch ( $column_name ) {
                case 'optins':
                    $this->theOptInsCounter( $item );
                    break;
            }
        }

        public function theOptInsCounter($item){
            $counter = OptIn::get_count($item->id());

            if($counter <= 0){
                echo esc_attr($counter);
            }else {
                echo '<a href="' . admin_url('admin.php') . '?page=f12-cf7-doubleoptin&cf_form_id=' . esc_attr($counter) . '">' . esc_attr($counter) . '</a>';
            }
        }
    }
}