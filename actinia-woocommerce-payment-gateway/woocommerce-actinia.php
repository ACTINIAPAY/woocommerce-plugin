<?php
/*
Plugin Name: WooCommerce - Actinia payment gateway
Plugin URI: https://actinia.eu
Description: Actinia Payment Gateway for WooCommerce.
Version: 1.0.0
Author: ACTINIA - Unified Payment Platform
Author URI: https://actinia.eu/
Domain Path: /languages
Text Domain: actinia-woocommerce-payment-gateway
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 2.5.0
WC tested up to: 4.7.1
*/

defined( 'ABSPATH' ) or exit;
define( 'ACTINIA_BASE_PATH' ,  plugin_dir_url( __FILE__ ) );
if ( ! class_exists( 'WC_PaymentActinia' ) ) :
    class WC_PaymentActinia {
        private $subscription_support_enabled = false;
        private static $instance;

        /**
         * @return WC_PaymentActinia
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * WC_PaymentActinia constructor.
         */
        protected function __construct() {
            add_action( 'plugins_loaded', [$this, 'init']);
        }

        /**
         * init actinia
         */
        public function init() {
            if ( self::check_environment() ) {
                return;
            }
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [$this, 'plugin_action_links']);
            $this->init_actinia();
        }

        /**
         * init actinia
         */
        public function init_actinia() {
            require_once( dirname( __FILE__ ) . '/includes/class-wc-actinia-gateway.php' );
            require_once( dirname( __FILE__ ) . '/includes/wc-actiniaapi.php' );
            load_plugin_textdomain( "actinia-woocommerce-payment-gateway", false, basename( dirname( __FILE__ )) . '/languages' );
            add_filter( 'woocommerce_payment_gateways', [$this, 'woocommerce_add_actinia_gateway']);
            add_action('wp_ajax_nopriv_generate_ajax_order_actinia_info', ['WC_actinia', 'generate_ajax_order_actinia_info'], 99);
            add_action('wp_ajax_generate_ajax_order_actinia_info', ['WC_actinia', 'generate_ajax_order_actinia_info'], 99);

        }

        /**
         * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
         * found or false if the environment has no problems.
         */
        static function check_environment() {
            if ( version_compare( phpversion(), '5.4.0', '<' ) ) {
                $message = __( ' The minimum PHP version required for Actinia is %1$s. You are running %2$s.', 'woocommerce-actinia' );

                return sprintf( $message, '5.4.0', phpversion() );
            }

            if ( ! defined( 'WC_VERSION' ) ) {
                return __( 'WooCommerce needs to be activated.', 'woocommerce-actinia' );
            }

            if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
                $message = __( 'The minimum WooCommerce version required for Actinia is %1$s. You are running %2$s.', 'woocommerce-actinia' );

                return sprintf( $message, '2.0.0', WC_VERSION );
            }

            return false;
        }
        public function plugin_action_links( $links ) {
            $plugin_links = [
                '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=actinia' ) . '">' . __( 'Settings', 'woocommerce-actinia' ) . '</a>',
            ];

            return array_merge( $plugin_links, $links );
        }

        /**
         * Add the Gateway to WooCommerce
         * @param $methods
         * @return array
         */
        public function woocommerce_add_actinia_gateway( $methods ) {
            if ( $this->subscription_support_enabled ) {
                $methods[] = 'WC_Actinia_Subscriptions';
            } else {
                $methods[] = 'WC_actinia';
            }
            return $methods;
        }
    }

    $GLOBALS['wc_actinia'] = WC_PaymentActinia::get_instance();
endif;