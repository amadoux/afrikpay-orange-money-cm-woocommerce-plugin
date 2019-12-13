<?php
/*
 * Plugin Name: Orange Money Payments for WooCommerce
 * Plugin URI: http://afrikpay.com/docs/OM-payments-woocommerce/
 * Description: Provides Afrikpay Payments as payment method to WooCommerce.
 * Author: Afrikpay
 * Author URI: https://afrikpay.com
 * Version: 1.0
 * Text Domain: orangemoney-payments-for-woocommerce
 * Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_OM_PAYMENTS_VERSION', '1.0' );
define( 'WC_OM_PAYMENTS_MIN_PHP_VER', '5.4.0' );
define( 'WC_OM_PAYMENTS_MIN_WC_VER', '3.0.0' );
define( 'WC_OM_PAYMENTS_MAIN_FILE', __FILE__ );
define( 'WC_OM_PAYMENTS_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! class_exists( 'WC_OM' ) ) {

	/**
	 * Class WC_OM
	 */
	class WC_OM {

		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var $instance
		 */
		private static $instance;

		/**
		 * Reference to logging class.
		 *
		 * @var $log
		 */
		private static $log;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return self::$instance The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {
		}

		/**
		 * Notices (array)
		 *
		 * @var array
		 */
		public $notices = array();

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			//add_action( 'admin_notices', array( $this, 'order_management_check' ) );
			//add_filter( 'woocommerce_process_checkout_field_billing_phone', array( $this, 'maybe_filter_billing_phone',) );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Init the gateway itself.
			$this->init_gateways();

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		}

		/**
		 * Show admin notice if Order Management plugin is not active.
		 */
		public function order_management_check() {
			if ( ! class_exists( 'WC_OM_Order_Management' ) ) {
				$current_screen = get_current_screen();
				if ( 'shop_order' === $current_screen->id || 'plugins' === $current_screen->id || 'woocommerce_page_wc-settings' === $current_screen->id ) {
					?>
					<div class="notice notice-warning">
						<p><?php _e( 'Afrikpay Order Management is not active. Please activate it so you can capture, cancel, update and refund Afrikpay orders.', 'woocommerce-orangemoney-payments' ); ?></p>
					</div>
					<?php
				}
			}
		}

		/**
		 * Adds plugin action links
		 *
		 * @param array $links Plugin action link before filtering.
		 *
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();

			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'orangemoney-payments-for-woocommerce' ) . '</a>',
				'<a href="http://afrikpay.cederconsulting.com/">' . __( 'Support', 'orangemoney-payments-for-woocommerce' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @since 1.0.0
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

			$section_slug = $use_id_as_section ? 'orangemoney' : strtolower( 'WC_Gateway_OM' );

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {
			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
		 *
		 * @since 1.0.0
		 */
		public function init_gateways() {
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			include_once( WC_OM_PAYMENTS_PLUGIN_PATH . '/includes/class-wc-gateway-orangemoney-payments.php' );
			//include_once( WC_OM_PAYMENTS_PLUGIN_PATH . '/includes/class-wc-afrikpay-payments-order-lines.php' );

			load_plugin_textdomain( 'orangemoney-payments-for-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @param  array $methods Array of payment methods.
		 * @return array $methods Array of payment methods.
		 * @since  1.0.0
		 */
		public function add_gateways( $methods ) {
			$methods[] = 'WC_Gateway_OM';

			return $methods;
		}

		/**
		 * Instantiate WC_Logger class.
		 *
		 * @param string $message Log message.
		 */
		public static function log( $message ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}

			self::$log->add( 'orangemoney-payments-for-woocommerce', $message );
		}

		/**
		 * Maybe filter posted billing phone number.
		 *
		 * Has to be done here, because we can't hook into this filter from gateway class.
		 * Afrikpay Payments phone validation is not the same as WooCommerce phone validation, so in case Afrikpay Payments
		 * says a phone is OK that would not be validated by WooCommerce we need to filter it here.
		 *
		 * @param string $phone_value Billing phone value.
		 *
		 * @return mixed
		 */
		public function maybe_filter_billing_phone( $phone_value ) {
			// Get rid of everything that's not what WC_Validation::is_phone requires.
			if ( 'orangemoney_payments' === $_POST['payment_method'] ) { // Input var okay.
				if ( trim( preg_replace( '/[^\s\#0-9_\-\+\/\(\)]/', '', $phone_value ) ) !== $phone_value ) {
					$phone_value = trim( preg_replace( '/[^\s\#0-9_\-\+\/\(\)]/', '', $phone_value ) );
				}
			}

			return $phone_value;
		}
	}

	WC_OM::get_instance();

}
