<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Generate PaysonCheckout 2.0 iframe
 *
 * @class    WC_PaysonCheckout_Create_Checkout_Iframe
 * @version  1.0.0
 * @package  WC_Gateway_PaysonCheckout/Classes
 * @category Class
 * @author   Krokedil
 */
class WC_PaysonCheckout_Create_Checkout {

	/*
	 * Required parameters:
	 * CheckoutID
	 * User - ClientID
	 * User - Password
	 * User - Username
	 * PaymentInfo - PaymentMethod
	 */


	/** @var string */
	private $payment_method_id = '';

	/** @var array */
	private $settings = array();

	/**
	 * WC_PaysonCheckout_Create_Checkout_Iframe constructor.
	 *
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_after_order_review', array( $this, 'get_iframe' ) );
		//add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'get_iframe' ) ); // Flatsome
		
		$this->payment_method_id 	= 'paysoncheckout';
		$this->settings          	= get_option( 'woocommerce_' . $this->payment_method_id . '_settings' );
		$this->enabled				= ( isset( $this->settings['enabled'] ) ) ? $this->settings['enabled'] : '';
	}


	public function get_iframe() {
		
		if( 'yes' !== $this->enabled ) {
			return;
		}
		
		//$order_id		= $this->prepare_wc_order();
		$wc_order 		= new WC_PaysonCheckout_WC_Order();
		$order_id		= $wc_order->update_or_create_local_order();
		
		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api = new WC_PaysonCheckout_Setup_Payson_API();
		try {
			$checkout = $payson_api->get_checkout( $order_id );
		} catch ( Exception $e ) {
			print_r( $e->getMessage() );
		}
		/*
		 * Step 4 Print out checkout html
		 */
		
		//print '<h2 style="text-align:center"> CheckoutId:'.$checkout->id.'</h2>';
		//$my_order = wc_get_order( $order_id );
		/*
		echo '<pre>';
		print_r($order_id);
		echo '</pre>';
		
		echo '<pre>CheckoutId<br/>';
		print_r($checkout->id);
		
		echo '</pre>';
		*/
		echo '<div class="col2-set checkout-group" id="customer_details_payson">';
		echo '<div class="paysonceckout-container" style="width:100%;  margin-left:auto; margin-right:auto;">';
		    echo $checkout->snippet;
		echo '</div></div>';
		
	}
	
	
	
	
}
$wc_paysoncheckout_create_checkout = new WC_PaysonCheckout_Create_Checkout();