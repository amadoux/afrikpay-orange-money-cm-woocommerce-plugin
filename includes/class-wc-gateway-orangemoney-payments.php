<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( isset ($_GET["orangemoney"])) {
class WC_Gateway_OM extends WC_Payment_Gateway {}
?>
<form id="dataFormOM" action="<?php echo $_GET["urlafrikpay"]; ?>" method="post" target="_top">
<input type="hidden" name="provider" value="orange_money_cm"/>
<input type="hidden" name="store" value="<?php echo $_GET["store"]; ?>" />
<input type="hidden" name="brand" value="Mon Panier" />
<input type="hidden" name="currency" value="<?php echo get_woocommerce_currency(); ?>" /> 
<input type="hidden" name="amount" value="<?php echo $_GET["totalamount"] ?>" />
<input type="hidden" name="phonenumber" value="" />
<input type="hidden" name="purchaseref" value="<?php $json = json_decode($_GET["custom"], true); echo $json['order_id']; ?>" />
<input type="hidden" name="notifurl" value="<?php echo $_GET["notify_url"]; ?>" />
<input type="hidden" name="accepturl" value="<?php echo $_GET["return"]; ?>" />
<input type="hidden" name="cancelurl" value="<?php echo $_GET["cancel_return"]; ?>" />
<input type="hidden" name="declineurl" value="<?php echo $_GET["cancel_return"]; ?>" />
<input type="hidden" name="text" value="<?php echo $_GET["text"]; ?>" />
<input type="hidden" name="language" value="fr" />  

</form>
<script type="text/javascript">
    document.getElementById('dataFormOM').submit(); // SUBMIT FORM
</script>
<?php

} else {
/**
 * WC_Gateway_OM class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_OM extends WC_Payment_Gateway {

	/** @var bool Whether or not logging is enabled */
	public static $log_enabled = false;

	/** @var WC_Logger Logger instance */
	public static $log = false;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'orangemoney';
		$this->has_fields         = false;
		$this->order_button_text  = __( 'Payer avec Orange Money', 'woocommerce' );
		$this->method_title       = __( 'Orange Money', 'woocommerce' );
		$this->method_description = sprintf( __( 'votre paiement en ligne en toute s&eacute;curit&eacute; avec Orange Money', 'woocommerce' ), admin_url( 'admin.php?page=wc-status' ) );
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->store     = $this->get_option( 'store' );
                $this->password     = $this->get_option( 'password' );
		$this->urlafrikpay = $this->get_option( 'urlafrikpay' );
		$this->debug          = 'yes' === $this->get_option( 'debug', 'no' );
		$this->identity_token = $this->get_option( 'identity_token' );

		self::$log_enabled    = $this->debug;

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );

        add_action('woocommerce_api_wc_gateway_om', [$this, 'pay']);
                

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		}
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'orangemoney' ) );
		}
	}

    function pay( $order_id ) {
		$order_id = $_GET["purchaseref"];
		if($order_id == null){
			echo "Purhaseref missing";
			exit;
		}
        $order = wc_get_order($order_id);
        $status = $_GET["status"];
        switch ( $status ) {
			case 'OK' :
				$order->add_order_note( sprintf( __( "Le paiement s'est bien pass\E9: %1$s", 'woocommerce' ), $status ) );
				update_post_meta( $order->get_id(), '_mobilemoney_status', $status );
				update_post_meta( $order->get_id(), '_transaction_id', $status );
				$order->payment_complete();
				header('Location: '.$this->get_return_url( $order ));
			break;
			default :
				$order->add_order_note( sprintf( __( "Le paiement ne s'est pas bien pass\E9: %1$s", 'woocommerce' ), $status ) );
				header('Location: '.esc_url_raw( $order->get_cancel_order_url_raw()));
			break;
				
		}
	}

	/**
	 * Get gateway icon.
	 * @return string
	 */
	public function get_icon() {
		$icon_html = '';
		$icon      = (array) $this->get_icon_image( WC()->countries->get_base_country() );

		foreach ( $icon as $i ) {
			$icon_html .= '<img src="' . esc_attr( $i ) . '" alt="' . esc_attr__( 'Afrikpay acceptance mark', 'woocommerce' ) . '" />';
		}

		$icon_html .= sprintf( '', esc_url( $this->get_icon_url( WC()->countries->get_base_country() ) ) );

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	

	protected function get_icon_url( $country ) {
		$url           = 'https://www.afrikpay.com/' . strtolower( $country );
		$home_counties = array( 'BE', 'CZ', 'DK', 'HU', 'IT', 'JP', 'NL', 'NO', 'ES', 'SE', 'TR', 'IN' );
		$countries     = array( 'DZ', 'AU', 'BH', 'BQ', 'BW', 'CA', 'CN', 'CW', 'FI', 'FR', 'DE', 'GR', 'HK', 'ID', 'JO', 'KE', 'KW', 'LU', 'MY', 'MA', 'OM', 'PH', 'PL', 'PT', 'QA', 'IE', 'RU', 'BL', 'SX', 'MF', 'SA', 'SG', 'SK', 'KR', 'SS', 'TW', 'TH', 'AE', 'GB', 'US', 'VN' );

		if ( in_array( $country, $home_counties ) ) {
			return  $url . '/webapps/mpp/home';
		} elseif ( in_array( $country, $countries ) ) {
			return $url . '/webapps/mpp/afrikpay-popup';
		} else {
			return "https://www.afrikpay.com/";
		}
	}

	/**
	 * Get Afrikpay images for a country.
	 *
	 * @param string $country Country code.
	 * @return array of image URLs
	 */
	protected function get_icon_image( $country ) {
		switch ( $country ) {
			default :
				$icon = WC_HTTPS::force_https_url( '../wp-content/plugins/orangemoney-payments-for-woocommerce/assets/images/orange-money.png' );
			break;
		}
		return apply_filters( 'woocommerce_orangemoney_icon', $icon );
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 * @return bool
	 */
	public function is_valid_for_use() {
		return in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_orangemoney_supported_currencies', array( 'XAF' ) ) );
	}

	/**
	 * Admin Panel Options.
	 * - Options for bits like 'title' and availability on a country-by-country basis.
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway disabled', 'woocommerce' ); ?></strong>: <?php _e( 'Afrikpay does not support your store currency.', 'woocommerce' ); ?></p></div>
			<?php
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	 
	public function init_form_fields() {
		$this->form_fields = include( 'settings-orangemoney.php' );
	}

	/**
	 * Get the transaction URL.
	 * @param  WC_Order $order
	 * @return string
	 */
	public function get_transaction_url( $order ) {
			$this->view_transaction_url = $this->urlafrikpay;
		return parent::get_transaction_url( $order );
	}

	/**
	 * Process the payment and return the result.
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		include_once( 'class-wc-gateway-orangemoney-request.php' );

		$order          = wc_get_order( $order_id );
		$momo_request = new WC_Gateway_OM_Request( $this );

		return array(
			'result'   => 'success',
			'redirect' => $momo_request->get_request_url( $order ),
		);
	}

	/**
	 * Can the order be refunded via Afrikpay?
	 * @param  WC_Order $order
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return $order && $order->get_transaction_id();
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @param  int $order_id
	 */
	public function capture_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( 'orangemoney' === $order->get_payment_method() && 'pending' === get_post_meta( $order->get_id(), '_orangemoney_status', true ) && $order->get_transaction_id() ) {

if(isset($_GET["status"])) {
if ($_GET["status"]=="OK") {

$status = $_GET["status"];
$purchaseref=$_GET["purchaseref"];
if (isset($_GET["amount"])) {
$amount=$_GET["amount"];
$currency=$_GET["currency"];
$status=$_GET["status"];
$clientid=$_GET["clientid"];
$cname=$_GET["cname"];
$mobile=$_GET["mobile"];
$paymentref=$_GET["paymentref"];
$payid=$_GET["payid"];
$gar=$_GET["gar"];
$date=$_GET["date"];
$time=$_GET["time"];
$ipaddr=$_GET["ipaddr"];
$error=$_GET["error"];
}}
			if ( ! empty( $status ) ) {
				switch ( $status ) {
					case 'OK' :
						$order->add_order_note( sprintf( __( "Le paiement s'est bien pass\E9: %1$s", 'woocommerce' ), $status ) );
						update_post_meta( $order->get_id(), '_orangemoney_status', $status );
						update_post_meta( $order->get_id(), '_transaction_id', $status );
					break;
					default :
						$order->add_order_note( sprintf( __( "Le paiement ne s'est pas bien pass\E9: %1$s", 'woocommerce' ), $status ) );
					break;
				
			}
		} }
	}}

	/**
	 * Load admin scripts.
	 *
	 * @since 3.3.0
	 */
	public function admin_scripts() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id: '';

		if ( 'woocommerce_page_wc-settings' !== $screen_id ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'woocommerce_orangemoney_admin', '../wp-content/plugins/orangemoney-payments-for-woocommerce/assets/js/afrikpay-admin' . $suffix . '.js', array(), WC_VERSION, true );
	}
}
}
