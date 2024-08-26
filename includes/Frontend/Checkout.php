<?php

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Checkout class
 */
class Checkout {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'create_subscription_after_checkout' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'create_subscription_after_checkout_storeapi' ) );
		add_action( 'woocommerce_resume_order', array( $this, 'remove_subscriptions' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_product_meta' ), 10, 3 );
	}

	/**
	 * Create subscription during checkout on storeAPI.
	 *
	 * @param \WC_Order $order Order Object.
	 */
	public function create_subscription_after_checkout_storeapi( $order ) {
		$this->create_subscription_after_checkout( $order->get_id() );
	}

	/**
	 * Create subscription during checkout.
	 *
	 * @param int $order_id Order ID.
	 */
	public function create_subscription_after_checkout( $order_id ) {
		$order = wc_get_order( $order_id );

		// Grab the post status based on order status.
		$post_status = 'active';
		switch ( $order->get_status() ) {
			case 'on-hold':
			case 'pending':
				$post_status = 'pending';
				break;

			case 'failed':
			case 'cancelled':
				$post_status = 'cancelled';
				break;

			default:
				break;
		}

		// Create subscription for order items.
		$order_items = $order->get_items();
		foreach ( $order_items as $order_item ) {
			$product = wc_get_product( $order_item['product_id'] );

			if ( $product->is_type( 'simple' ) && ! subscrpt_pro_activated() ) {
				$enabled = $product->get_meta( '_subscrpt_enabled' );

				if ( $enabled ) {
					$is_renew = isset( $order_item['renew_subscrpt'] );

					$timing_option = $product->get_meta( '_subscrpt_timing_option' );
					$trial         = null;
					$has_trial     = Helper::check_trial( $product->get_id() );

					$trial_per    = $product->get_meta( '_subscrpt_trial_timing_per' );
					$trial_option = $product->get_meta( '_subscrpt_trial_timing_option' );
					if ( ! empty( $trial_per ) && $trial_per > 0 && ! $is_renew && $has_trial ) {
						$trial = $trial_per . ' ' . Helper::get_typos( $trial_per, $trial_option );
					}

					wc_update_order_item_meta(
						$order_item->get_id(),
						'_subscrpt_meta',
						array(
							'time'  => 1,
							'type'  => $timing_option,
							'trial' => $trial,
						)
					);

					// Renew subscription if need!
					$renew_subscription_id    = Helper::subscription_exists( $product->get_id(), 'expired' );
					$selected_subscription_id = null;
					if ( $is_renew && $renew_subscription_id && 'cancelled' !== $post_status ) {
						$selected_subscription_id = $renew_subscription_id;
						Helper::process_order_renewal(
							$selected_subscription_id,
							$order_id,
							$order_item->get_id()
						);
					} else {
						$selected_subscription_id = Helper::process_new_subscription_order( $order_item, $post_status, $product );
					}

					if ( $selected_subscription_id ) {
						// product related.
						update_post_meta( $selected_subscription_id, '_subscrpt_timing_option', $timing_option );
						update_post_meta( $selected_subscription_id, '_subscrpt_price', $product->get_price() * $order_item['quantity'] );
						update_post_meta( $selected_subscription_id, '_subscrpt_user_cancel', $product->get_meta( '_subscrpt_user_cancel' ) );

						// order related.
						update_post_meta( $selected_subscription_id, '_subscrpt_order_id', $order_id );
						update_post_meta( $selected_subscription_id, '_subscrpt_order_item_id', $order_item->get_id() );

						// subscription related.
						update_post_meta( $selected_subscription_id, '_subscrpt_trial', $trial );

						do_action( 'subscrpt_order_checkout', $selected_subscription_id, $order_item );
					}
				}
			}

			do_action( 'subscrpt_product_checkout', $order_item, $product, $post_status );
		}
	}

	/**
	 * Remove subscriptions for resumed orders.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return void
	 */
	public function remove_subscriptions( $order_id ) {
		global $wpdb;
		// delete subscriptions & order item meta.
		$histories = Helper::get_subscriptions_from_order( $order_id );
		foreach ( $histories as $history ) {
			$table_name = $wpdb->prefix . 'subscrpt_order_relation';
			// @phpcs:ignore
			$relation_count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE subscription_id=%d', array( $table_name, $history->subscription_id ) ) );
			if ( 1 === (int) $relation_count ) {
				wp_delete_post( $history->subscription_id, true );
			}
			wc_delete_order_item_meta( $history->order_item_id, '_subscrpt_meta' );
		}

		// delete order subscription relation.
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		// phpcs:ignore
		$wpdb->delete( $table_name, array( 'order_id' => $order_id ), array( '%d' ) );
	}

	/**
	 * Save renew meta
	 *
	 * @param Object $item Item.
	 * @param String $cart_item_key Cart Item Key.
	 * @param Array  $cart_item Cart Item.
	 */
	public function save_order_item_product_meta( $item, $cart_item_key, $cart_item ) {
		if ( isset( $cart_item['renew_subscrpt'] ) ) {
			$item->update_meta_data( '_renew_subscrpt', $cart_item['renew_subscrpt'] );
		}
	}
}
