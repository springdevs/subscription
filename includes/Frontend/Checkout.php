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
	}

	/**
	 * Create subscription during checkout.
	 *
	 * @param Int $order_id Order ID.
	 */
	public function create_subscription_after_checkout( $order_id ) {
		global $wpdb;
		$history_table = $wpdb->prefix . 'subscrpt_histories';
		$order         = wc_get_order( $order_id );

		// Grab the post status based on order status.
		$post_status = 'active';
		switch ( $order->get_status() ) {
			case 'on-hold':
			case 'pending';
				$post_status = 'pending';
				break;

			case 'failed':
			case 'cancelled';
				$post_status = 'cancelled';
				break;

			default;
				break;
		}

		// Create subscription for order items.
		$order_items = $order->get_items();
		foreach ( $order_items as $order_item ) {
			$product = wc_get_product( $order_item['product_id'] );

			if ( ! $product->is_type( 'variable' ) ) {
				$post_meta = $product->get_meta( '_subscrpt_meta' );

				if ( is_array( $post_meta ) && $post_meta['enable'] ) {
					$is_renew = isset( $order_item['_renew_subscrpt'] );
					$type     = Helper::get_typos( $post_meta['time'], $post_meta['type'] );

					$start_date = time();
					$trial      = null;
					$has_trial  = Helper::check_trial( $product->get_id() );

					if ( ! empty( $post_meta['trial_time'] ) && $post_meta['trial_time'] > 0 && ! $is_renew && $has_trial ) {
						$trial      = $post_meta['trial_time'] . ' ' . Helper::get_typos( $post_meta['trial_time'], $post_meta['trial_type'] );
						$start_date = strtotime( $trial );
					}

					$order_item_meta = array(
						'order_id'      => $order_id,
						'order_item_id' => $order_item->get_id(),
						'trial'         => $trial,
						'start_date'    => $start_date,
						'next_date'     => strtotime( $post_meta['time'] . ' ' . $type, $start_date ),
					);

					wc_update_order_item_meta(
						$order_item->get_id(),
						'_subscrpt_meta',
						array(
							'time'       => $post_meta['time'],
							'type'       => $post_meta['type'],
							'trial'      => $trial,
							'start_date' => $start_date,
							'next_date'  => strtotime( $post_meta['time'] . ' ' . $type, $start_date ),
						)
					);

					// Renew subscription if need!
					$renew_subscription_id = Helper::subscription_exists( $product->get_id(), 'expired' );
					if ( $is_renew && $renew_subscription_id && $post_status !== 'cancelled' ) {
						$comment_id = wp_insert_comment(
							array(
								'comment_author'  => 'Subscription for WooCommerce',
								'comment_content' => sprintf( __( 'The order %s has been created for the subscription', 'sdevs_subscrpt' ), $order_id ),
								'comment_post_ID' => $renew_subscription_id,
								'comment_type'    => 'order_note',
							)
						);
						update_comment_meta( $comment_id, '_subscrpt_activity', __( 'Renewal Order', 'sdevs_subscrpt' ) );

						update_post_meta( $renew_subscription_id, '_order_subscrpt_meta', $order_item_meta );
						$wpdb->insert(
							$history_table,
							array(
								'subscription_id' => $renew_subscription_id,
								'order_id'        => $order_id,
								'order_item_id'   => $order_item->get_id(),
								'stat'            => 'Renewal Order',
							)
						);
					}

					if ( ! $is_renew && ! $renew_subscription_id ) {
						$args            = array(
							'post_title'  => 'Subscription',
							'post_type'   => 'subscrpt_order',
							'post_status' => $post_status,
						);
						$subscription_id = wp_insert_post( $args );
						wp_update_post(
							array(
								'ID'         => $subscription_id,
								'post_title' => "Subscription #{$subscription_id}",
							)
						);
						$comment_id = wp_insert_comment(
							array(
								'comment_author'  => 'Subscription for WooCommerce',
								'comment_content' => sprintf( __( 'Subscription successfully created.	order is %s', 'sdevs_subscrpt' ), $order_id ),
								'comment_post_ID' => $subscription_id,
								'comment_type'    => 'order_note',
							)
						);
						update_comment_meta( $comment_id, '_subscrpt_activity', __( 'New Subscription', 'sdevs_subscrpt' ) );

						update_post_meta( $subscription_id, '_order_subscrpt_meta', $order_item_meta );
						update_post_meta( $subscription_id, '_subscrpt_product_id', $product->get_id() );
						update_post_meta( $subscription_id, '_subscrpt_user_cancel', $post_meta['user_cancell'] );

						$wpdb->insert(
							$history_table,
							array(
								'subscription_id' => $subscription_id,
								'order_id'        => $order_id,
								'order_item_id'   => $order_item->get_id(),
								'stat'            => 'Parent Order',
							)
						);
					}
				}
			}

			do_action( 'subscrpt_product_checkout', $order, $order_item, $post_status );
		}
	}
}
