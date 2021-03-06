<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Process order lines for sending them to Payson
 *
 * @class    WC_PaysonCheckout_Process_Order_Lines
 * @version  1.0.0
 * @package  WC_Gateway_PaysonCheckout/Classes
 * @category Class
 * @author   Krokedil
 */
class WC_PaysonCheckout_Process_Order_Lines {

	/**
	 * Get order lines from order or cart
	 *
	 * @param mixed $order_id WooCommerce order ID.
	 *
	 * @return array $order_lines
	 */
	public function get_order_lines( $order_id = false ) {
		if ( $order_id ) {
			return $this->get_order_lines_from_order( $order_id );
		} else {
			return $this->get_order_lines_from_cart();
		}
	}

	/**
	 * Process WooCommerce order into Payson order lines.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return \PaysonEmbedded\PayData
	 */
	private function get_order_lines_from_order( $order_id ) {
		require_once PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php';
		$order       = new WC_Order( $order_id );

		if ( 'EUR' === $order->get_order_currency() ) {
			$pay_data = new PaysonEmbedded\PayData( PaysonEmbedded\CurrencyCode::EUR );
		} else {
			$pay_data = new PaysonEmbedded\PayData( PaysonEmbedded\CurrencyCode::SEK );
		}

		// Process order lines.
		if ( count( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item_key => $item ) {
				$_product = $order->get_product_from_item( $item );
				$title    = $item['name'];
				$price    = $order->get_item_total( $item, true );
				$qty      = $item['qty'];

				// We manually calculate the tax here.
				if ( $order->get_line_tax( $item ) !== 0 ) {
					$vat = round( $order->get_item_tax( $item ) / $order->get_item_total( $item, false ), 2 );
				} else {
					$vat = $order->get_line_tax( $item );
				}
				$sku = $this->get_item_reference( $_product );
				$pay_data->AddOrderItem( new  PaysonEmbedded\OrderItem( $title, $price, $qty, $vat, $sku, PaysonEmbedded\OrderItemType::PHYSICAL, 0 ) );
			}
		}

		// Process shipping.
		if ( $order->get_total_shipping() > 0 ) {
			foreach ( $order->get_shipping_methods() as $shipping_method_key => $shipping_method_value ) {
				$shipping_method_tax = array_sum( maybe_unserialize( $shipping_method_value['taxes'] ) );
				$title               = $shipping_method_value['name'];
				$price               = $shipping_method_value['cost'] + $shipping_method_tax;
				$qty                 = 1;
				$vat                 = round( $shipping_method_tax / $shipping_method_value['cost'], 2 );
				$sku                 = 'Shipping';
				$pay_data->AddOrderItem( new  PaysonEmbedded\OrderItem( $title, $price, $qty, $vat, $sku, PaysonEmbedded\OrderItemType::PHYSICAL, 0 ) );
			}
		}

		// Process fees.
		$order_fees = $order->get_fees();
		if ( ! empty( $order_fees ) ) {
			foreach ( $order->get_fees() as $order_fee_key => $order_fee_value ) {
				$title = $order_fee_value['name'];
				$price = round( ( $order_fee_value['line_tax'] + $order_fee_value['line_total'] ), 2 );
				$qty   = 1;
				$vat   = round( $order_fee_value['line_tax'] / $order_fee_value['line_total'], 2 );
				$sku   = 'Fee';
				$pay_data->AddOrderItem( new  PaysonEmbedded\OrderItem( $title, $price, $qty, $vat, $sku, PaysonEmbedded\OrderItemType::PHYSICAL, 0 ) );
			}
		}

		return $pay_data;
	}

	/**
	 * Process WooCommerce cart into Payson order lines
	 *
	 * @return array
	 */
	public function get_order_lines_from_cart() {
		require_once PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php';
		if ( 'EUR' === get_woocommerce_currency() ) {
			$pay_data = new  PaysonEmbedded\PayData( PaysonEmbedded\CurrencyCode::EUR );
		} else {
			$pay_data = new  PaysonEmbedded\PayData( PaysonEmbedded\CurrencyCode::SEK );
		}

		// Process order lines.
		if ( count( WC()->cart->cart_contents ) > 0 ) {
			foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
				$_product      = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				$product_name  = apply_filters( 'woocommerce_cart_item_name', $_product->get_title(), $cart_item, $cart_item_key );
				$product_price = ( $cart_item['line_total'] + $cart_item['line_tax'] ) / $cart_item['quantity'];
				$qty           = $cart_item['quantity'];
				$sku           = $this->get_item_reference( $_product );
				$vat           = round( $cart_item['line_tax'] / $cart_item['line_total'], 2 );
				$permalink     = get_permalink( $_product->get_id() );
				$image         = $_product->get_image_id() ? wp_get_attachment_url( $_product->get_image_id() ) : null;
				$pay_data->AddOrderItem( new PaysonEmbedded\OrderItem(
					$product_name,
					$product_price,
					$qty,
					$vat,
					$sku,
					PaysonEmbedded\OrderItemType::PHYSICAL,
					0,
					'ean12345',
					$permalink,
					$image
				) );
			}
		}

		// Process shipping.
		if ( WC()->shipping->get_packages() ) {
			foreach ( WC()->shipping->get_packages() as $shipping_package ) {
				foreach ( $shipping_package['rates'] as $shipping_rate_key => $shipping_rate_value ) {
					$shipping_tax = array_sum( $shipping_rate_value->taxes );
					if ( $shipping_rate_value->cost > 0 ) {
						$vat_percent = round( $shipping_tax / $shipping_rate_value->cost, 2 );
					} else {
						$vat_percent = 0;
					}
					$title = $shipping_rate_value->label;
					$price = $shipping_rate_value->cost + $shipping_tax;
					$qty   = 1;
					$sku   = 'Shipping';
					$pay_data->AddOrderItem( new  PaysonEmbedded\OrderItem( $title, $price, $qty, $vat_percent, $sku, PaysonEmbedded\OrderItemType::PHYSICAL, 0 ) );
				}
			}
		}

		// Process fees.
		if ( WC()->cart->fee_total > 0 ) {
			foreach ( WC()->cart->get_fees() as $cart_fee ) {
				$cart_fee_tax = array_sum( $cart_fee->tax_data );
				$title        = $cart_fee->label;
				$price        = round( ( $cart_fee->amount + $cart_fee_tax ), 2 );
				$qty          = 1;
				$vat          = round( $cart_fee_tax / $cart_fee->amount, 2 );
				$sku          = 'Fee';
				$pay_data->AddOrderItem( new  PaysonEmbedded\OrderItem( $title, $price, $qty, $vat, $sku, PaysonEmbedded\OrderItemType::PHYSICAL, 0 ) );
			}
		}

		return $pay_data;
	}

	/**
	 * Returns item reference for a product.
	 *
	 * @param WC_Product $_product WooCommerce product.
	 *
	 * @return string
	 */
	public function get_item_reference( $_product ) {
		if ( '' !== $_product->get_sku() ) {
			$item_reference = $_product->get_sku();
		} elseif ( $_product->variation_id ) {
			$item_reference = $_product->variation_id;
		} else {
			$item_reference = $_product->id;
		}

		return strval( $item_reference );
	}

}
