<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class STS_Benefits {

	private static $instance = null;

	private $eligibility_cache = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'woocommerce_cart_calculate_fees',               array( $this, 'apply_discount' ) );
		add_action( 'woocommerce_checkout_create_order',             array( $this, 'record_discount_on_order' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'record_discount_on_order' ) );
	}

	// -------------------------------------------------------------------------
	// Eligibility
	// -------------------------------------------------------------------------

	public function is_user_eligible() {
		if ( null !== $this->eligibility_cache ) {
			return $this->eligibility_cache;
		}

		if ( ! get_option( 'sts_benefits_enabled', 1 ) ) {
			$this->eligibility_cache = false;
			return false;
		}

		if ( ! is_user_logged_in() ) {
			$this->eligibility_cache = false;
			return false;
		}

		$uid  = get_current_user_id();
		$mode = get_option( 'sts_benefits_eligibility_mode', 'unused_benefit' );

		if ( 'new_customers' === $mode ) {
			$orders = wc_get_orders( array(
				'customer_id' => $uid,
				'status'      => array( 'processing', 'completed' ),
				'limit'       => 1,
				'return'      => 'ids',
			) );
			$eligible = empty( $orders );
		} else {
			// unused_benefit: eligible unless they've already used this discount
			$orders = wc_get_orders( array(
				'customer_id' => $uid,
				'status'      => array( 'processing', 'completed' ),
				'meta_key'    => '_sts_benefits_first_order',
				'meta_value'  => 'yes',
				'limit'       => 1,
				'return'      => 'ids',
			) );
			$eligible = empty( $orders );
		}

		$this->eligibility_cache = $eligible;
		return $eligible;
	}

	public function reset_eligibility_cache() {
		$this->eligibility_cache = null;
	}

	// -------------------------------------------------------------------------
	// Cart fee
	// -------------------------------------------------------------------------

	public function apply_discount( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// Skip admin order recalculation — current user is the admin, not the customer
		if ( wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( in_array( $action, array( 'woocommerce_calc_line_taxes', 'woocommerce_save_order_items' ), true ) ) {
				return;
			}
		}

		if ( ! $this->is_user_eligible() ) {
			$this->clear_session_discount();
			return;
		}

		// Non-combinable: bail if any coupon is active
		if ( ! empty( $cart->get_applied_coupons() ) ) {
			$this->clear_session_discount();
			return;
		}

		$excluded_products   = $this->get_excluded_products();
		$excluded_categories = $this->get_excluded_categories();
		$base                = 0.0;

		foreach ( $cart->get_cart() as $item ) {
			// WC cart items always carry product_id as the parent; no extra DB lookup needed
			$product_id = (int) $item['product_id'];

			if ( ! empty( $excluded_products ) && in_array( $product_id, $excluded_products, true ) ) {
				continue;
			}

			if ( ! empty( $excluded_categories ) && has_term( $excluded_categories, 'product_cat', $product_id ) ) {
				continue;
			}

			$base += (float) $item['line_subtotal'];
		}

		if ( $base <= 0 ) {
			$this->clear_session_discount();
			return;
		}

		$pct      = $this->get_percentage();
		$discount = round( $base * $pct / 100, wc_get_price_decimals() );

		if ( $discount <= 0 ) {
			$this->clear_session_discount();
			return;
		}

		$label = 'הנחת הזמנה ראשונה - ' . $pct . '%-';
		$cart->add_fee( $label, -$discount, false );

		$this->set_session_discount( $discount );
	}

	private function clear_session_discount() {
		if ( WC()->session ) {
			WC()->session->set( 'sts_benefits_discount', 0 );
		}
	}

	private function set_session_discount( $amount ) {
		if ( WC()->session ) {
			WC()->session->set( 'sts_benefits_discount', $amount );
		}
	}

	// -------------------------------------------------------------------------
	// Order recording
	// -------------------------------------------------------------------------

	public function record_discount_on_order( $order ) {
		$amount = 0.0;

		// Classic checkout: read from session
		if ( WC()->session ) {
			$amount = (float) WC()->session->get( 'sts_benefits_discount', 0 );
			if ( $amount > 0 ) {
				WC()->session->set( 'sts_benefits_discount', 0 );
			}
		}

		// Block/Store API checkout fallback: derive from the order's negative fee lines
		if ( $amount <= 0 ) {
			foreach ( $order->get_fees() as $fee ) {
				if ( (float) $fee->get_total() < 0 ) {
					$amount = abs( (float) $fee->get_total() );
					break;
				}
			}
		}

		if ( $amount <= 0 ) {
			return;
		}

		$order->update_meta_data( '_sts_benefits_first_order',     'yes' );
		$order->update_meta_data( '_sts_benefits_discount_amount', $amount );

		delete_transient( 'sts_benefits_stats' );
		$this->reset_eligibility_cache();
	}

	// -------------------------------------------------------------------------
	// Settings getters
	// -------------------------------------------------------------------------

	public function is_enabled() {
		return (bool) get_option( 'sts_benefits_enabled', 1 );
	}

	public function get_percentage() {
		return (int) get_option( 'sts_benefits_percentage', 10 );
	}

	public function is_popup_enabled() {
		return (bool) get_option( 'sts_benefits_popup_enabled', 1 );
	}

	public function get_excluded_products() {
		$val = get_option( 'sts_benefits_excluded_products', array() );
		return is_array( $val ) ? array_map( 'absint', $val ) : array();
	}

	public function get_excluded_categories() {
		$val = get_option( 'sts_benefits_excluded_categories', array() );
		return is_array( $val ) ? array_map( 'absint', $val ) : array();
	}

	public function get_eligibility_mode() {
		return get_option( 'sts_benefits_eligibility_mode', 'unused_benefit' );
	}
}
