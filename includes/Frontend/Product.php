<?php
/**
 * Storefront product handling for subscriptions.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;

// HPOS: This file is compatible with WooCommerce High-Performance Order Storage (HPOS).
// All WooCommerce order data is accessed via WooCommerce CRUD methods (wc_get_order, wc_get_order_item_meta, etc.).
// All direct post meta access is for subscription data only, not WooCommerce order data.
// If you add new order data access, use WooCommerce CRUD for HPOS compatibility.

/**
 * Product class
 * control single product page
 */
class Product {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_filter(
			'woocommerce_product_single_add_to_cart_text',
			array(
				$this,
				'change_single_add_to_cart_text',
			),
			10,
			2
		);
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'change_loop_add_to_cart_text' ), 10, 2 );
		// Plan-tied simple products behave like a variable product on the shop /
		// Product Collection loops: a "Select options" link to the product page (so
		// the customer picks a plan), never a direct/ajax add-to-cart. These native
		// product-method filters drive both the classic loop and the block button.
		add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'plan_loop_add_to_cart_url' ), 10, 2 );
		add_filter( 'woocommerce_product_supports', array( $this, 'plan_loop_disable_ajax' ), 10, 3 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'change_price_html' ), 10, 2 );
		add_filter( 'woocommerce_quantity_input_args', array( $this, 'update_input_args' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart', array( $this, 'update_cart_quantity' ), 10, 3 );
		add_filter(
			'woocommerce_store_api_product_quantity_minimum',
			array(
				$this,
				'update_quantity_min_max',
			),
			10,
			2
		);
		add_filter(
			'woocommerce_store_api_product_quantity_maximum',
			array(
				$this,
				'update_quantity_min_max',
			),
			10,
			2
		);
		add_action(
			'woocommerce_after_cart_item_quantity_update',
			array(
				$this,
				'validate_quantity_on_manual_renewal',
			),
			10,
			2
		);
		add_filter( 'woocommerce_is_purchasable', array( $this, 'check_if_purchasable' ), 20, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'text_if_active' ) );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'remove_button_active_products' ), 10, 2 );
	}

	/**
	 * Remove button if product already subscribed.
	 *
	 * @param mixed       $button Button.
	 * @param \WC_Product $product Product.
	 *
	 * @return mixed
	 */
	public function remove_button_active_products( $button, $product ) {
		$product = Subscription::get_subs_product( $product );
		if ( ! $product->is_type( 'simple' ) ) {
			return $button;
		}

		if ( $product->is_enabled() ) {
			$limit = $product->get_limit();
			if ( 'one' === $limit ) {
				$unexpired = Helper::subscription_exists( $product->get_id(), array( 'active', 'pending' ) );
				if ( $unexpired ) {
					return;
				}
			} elseif ( 'only_one' === $limit && ! Helper::check_trial( $product->get_id() ) ) {
				return;
			}
		}

		return $button;
	}

	/**
	 * Display notice if already purchased.
	 */
	public function text_if_active() {
		global $product;
		$sdevs_product = Subscription::get_subs_product( $product );
		if ( ! $sdevs_product->is_type( 'simple' ) ) {
			return;
		}

		if ( $sdevs_product->is_enabled() ) {
			$limit = $sdevs_product->get_limit();
			if ( 'unlimited' === $limit ) {
				return;
			}
			if ( 'one' === $limit ) {
				$unexpired = Helper::subscription_exists( $sdevs_product->get_id(), array( 'active', 'pending' ) );
				if ( ! $unexpired ) {
					return false;
				} else {
					echo '<strong>' . esc_html_e( 'You Already Subscribed These Product!', 'subscription' ) . '</strong>';
				}
			}
			if ( 'only_one' === $limit ) {
				if ( ! Helper::check_trial( $sdevs_product->get_id() ) ) {
					echo '<strong>' . esc_html_e( 'You Already Subscribed These Product!', 'subscription' ) . '</strong>';
				}
			}
		}
	}

	/**
	 * Check if product purchasable.
	 *
	 * @param boolean     $is_purchasable True\False.
	 * @param \WC_Product $product Product.
	 *
	 * @return boolean
	 */
	public function check_if_purchasable( $is_purchasable, $product ) {
		$product = Subscription::get_subs_product( $product );
		if ( $product->is_enabled() ) {
			$limit = $product->get_limit();
			if ( 'unlimited' === $limit ) {
				return true;
			} elseif ( 'only_one' === $limit ) {
				return Helper::check_trial( $product->get_id() );
			} elseif ( 'one' === $limit ) {
				return ! Helper::subscription_exists( $product->get_id(), array( 'active', 'pending' ) );
			}
		}

		return $is_purchasable;
	}

	/**
	 * Validate quantity after add to cart cart on manual renewal process.
	 *
	 * @param string $cart_item_key Cart Item Key.
	 * @param int    $quantity Quantity.
	 *
	 * @return void
	 */
	public function validate_quantity_on_manual_renewal( $cart_item_key, $quantity ) {
		$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		$expired   = Helper::subscription_exists( $cart_item['data']->get_id(), 'expired' );
		if ( $expired ) {
			$order_item_id = get_post_meta( $expired, '_subscrpt_order_item_id', true );
			$item_quantity = (int) wc_get_order_item_meta( $order_item_id, '_qty', true );
			if ( $item_quantity !== $quantity ) {
				WC()->cart->set_quantity( $cart_item_key, $item_quantity );
				wc_add_notice( 'You can only add ' . $item_quantity . ' ' . ( $item_quantity > 1 ? 'items' : 'item' ) . ' on cart!', 'error' );
			}
		}
	}

	/**
	 * Update quantity min max for renewal process.
	 *
	 * @param int         $value Value.
	 * @param \WC_Product $product Product Object.
	 *
	 * @return int
	 */
	public function update_quantity_min_max( $value, $product ) {
		$expired = Helper::subscription_exists( $product->get_id(), 'expired' );
		if ( $expired ) {
			$order_item_id = get_post_meta( $expired, '_subscrpt_order_item_id', true );
			$item_quantity = (int) wc_get_order_item_meta( $order_item_id, '_qty', true );

			return $item_quantity;
		}

		return $value;
	}

	/**
	 * Update cart quantity on manual renewal process.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id Product Id.
	 * @param int    $quantity Quantity.
	 *
	 * @return void
	 */
	public function update_cart_quantity( $cart_item_key, $product_id, $quantity ) {
		$expired = Helper::subscription_exists( $product_id, 'expired' );
		if ( $expired ) {
			$order_item_id = get_post_meta( $expired, '_subscrpt_order_item_id', true );
			$item_quantity = (int) wc_get_order_item_meta( $order_item_id, '_qty', true );
			if ( $item_quantity !== $quantity ) {
				WC()->cart->set_quantity( $cart_item_key, $item_quantity );
				wc_add_notice( 'You can only add ' . $item_quantity . ' ' . ( $item_quantity > 1 ? 'items' : 'item' ) . ' on cart!', 'error' );
			}
		}
	}

	/**
	 * Update quantity input args.
	 *
	 * @param array       $args args.
	 * @param \WC_Product $product Product object.
	 *
	 * @return array
	 */
	public function update_input_args( $args, $product ) {
		$expired = Helper::subscription_exists( $product->get_id(), 'expired' );
		if ( $expired ) {
			$order_item_id       = get_post_meta( $expired, '_subscrpt_order_item_id', true );
			$item_quantity       = (int) wc_get_order_item_meta( $order_item_id, '_qty', true );
			$args['input_value'] = $item_quantity;
			$args['min_value']   = $item_quantity;
			$args['max_value']   = $item_quantity;
			$args['step']        = 1;
		}

		return $args;
	}

	/**
	 * Change single product add-to-cart button text.
	 *
	 * @param string      $text Add-to-cart button Text.
	 * @param \WC_Product $product Product Object.
	 */
	public function change_single_add_to_cart_text( $text, $product ) {
		$product = Subscription::get_subs_product( $product );
		if ( $product->is_type( 'variable' ) || '' === $product->get_price() ) {
			return $text;
		}
		$cart_btn_label = $product->get_button_label();
		$expired        = Helper::subscription_exists( $product->get_id(), 'expired' );
		if ( $expired ) {
			$text = __( 'Renew', 'subscription' );
		} elseif ( $product->is_enabled() && $cart_btn_label ) {
			$text = $cart_btn_label;
		}

		return $text;
	}

	/**
	 * Whether this is a plan-tied simple product that should behave like a
	 * variable product on the shop / Product Collection loops.
	 *
	 * @param mixed $product Product object.
	 *
	 * @return bool
	 */
	private function is_plan_loop_product( $product ) {
		return $product instanceof \WC_Product
			&& $product->is_type( 'simple' )
			&& subscrpt_plan_offered( $product->get_id() );
	}

	/**
	 * Loop / block add-to-cart button text: "Select options" for plan products,
	 * otherwise the classic label logic.
	 *
	 * @param string      $text    Add-to-cart button text.
	 * @param \WC_Product $product Product object.
	 *
	 * @return string
	 */
	public function change_loop_add_to_cart_text( $text, $product ) {
		if ( $this->is_plan_loop_product( $product ) ) {
			return __( 'Select options', 'subscription' );
		}

		return $this->change_single_add_to_cart_text( $text, $product );
	}

	/**
	 * Loop / block add-to-cart URL: the product page for plan products, so the
	 * customer lands on the plan selector instead of a bare add-to-cart.
	 *
	 * @param string      $url     Add-to-cart URL.
	 * @param \WC_Product $product Product object.
	 *
	 * @return string
	 */
	public function plan_loop_add_to_cart_url( $url, $product ) {
		return $this->is_plan_loop_product( $product ) ? $product->get_permalink() : $url;
	}

	/**
	 * Disable ajax add-to-cart for plan products so their loop / block button
	 * renders as a "Select options" link (navigates to the product page) rather
	 * than an ajax add.
	 *
	 * @param bool        $supports Whether the feature is supported.
	 * @param string      $feature  Feature name.
	 * @param \WC_Product $product  Product object.
	 *
	 * @return bool
	 */
	public function plan_loop_disable_ajax( $supports, $feature, $product ) {
		if ( 'ajax_add_to_cart' === $feature && $this->is_plan_loop_product( $product ) ) {
			return false;
		}

		return $supports;
	}

	/**
	 * Add trial, signup fee etc. with product price.
	 *
	 * @param mixed       $price Price.
	 * @param \WC_Product $product Product.
	 *
	 * @return mixed
	 */
	public function change_price_html( $price, $product ) {
		$product = Subscription::get_subs_product( $product );
		if ( ! $product->is_type( 'simple' ) || '' === $price ) {
			return $price;
		}

		if ( $product->is_enabled() ) :
			$timing_option = $product->get_timing_option();
			$type          = ucfirst( Helper::get_typos( 1, $timing_option, true ) );

			$trial = null;
			if ( $product->has_trial() ) {
				$meta_trial_time = $product->get_trial_timing_per();
				$trial_type      = Helper::get_typos( $meta_trial_time, $product->get_trial_timing_option(), true );
				$trial           = '<br/><small> + Get ' . $meta_trial_time . ' ' . ucfirst( $trial_type ) . ' free trial!</small>';
			}

			$timing_html = "<span class='wpsubs-subscription-timing'>&nbsp;/&nbsp;{$type}</span>";
			$price_html  = $price . $timing_html . $trial;

			return apply_filters( 'subscrpt_simple_price_html', $price_html, $product, $price, $trial );
		else :
			return $price;
		endif;
	}
}
