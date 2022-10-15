<?php

namespace SpringDevs\Subscription\Illuminate;

use WC_Data_Exception;

/**
 * AutoRenewal [ helper class ]
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class AutoRenewal {

	public function __construct() {
		add_action( 'subscrpt_subscription_expired', array( $this, 'product_expired_action' ), 10, 4 );
	}

	/**
	 * @throws WC_Data_Exception
	 */
	public function product_expired_action( $subscription_id, $early_renew = false ) {
		$is_auto_renew = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		if ( get_option( 'subscrpt_manual_renew', '1' ) != '1' && $is_auto_renew == 0 ) {
			return;
		}

		$post_meta = get_post_meta( $subscription_id, '_order_subscrpt_meta', true );

		$original_order_id = $post_meta['order_id'];
		$old_order         = wc_get_order( $post_meta['order_id'] );
		if ( ! $old_order || $old_order->get_status() != 'completed' ) {
			return;
		}

		$user_id = $old_order->get_user_id();
		$new_order    = wc_create_order(
			array(
				'customer_id' => $user_id,
				'parent'      => $post_meta['order_id'],
			)
		);
		$order_id     = $new_order->get_id();

		$product_id	  = get_post_meta( $subscription_id, '_subscrpt_product_id', true );
		$product      = wc_get_product( $product_id );
		if ( !$product || $product === null ) {
			return;
		}

		$order_item = $old_order->get_item( $post_meta['order_item_id'] );

		if ( $product->is_type('simple') ) {
			$new_order_item_id = $new_order->add_product(
				$product,
				$order_item->get_quantity(),
				array(
					'name'         => $order_item->get_name(),
					'subtotal' 	   => $order_item->get_subtotal(),
					'total'		   => $order_item->get_total()
				)
			);

			$product_meta          = wc_get_order_item_meta( $post_meta['order_item_id'], '_subscrpt_meta', true );
			$type                  = subscrpt_get_typos( $product_meta['time'], $product_meta['type'] );
			$post_meta['order_id'] = $new_order->get_id();
			if ( ! $early_renew ) {
				if ( time() <= $post_meta['next_date'] ) {
					$post_meta['next_date'] = strtotime( $product_meta['time'] . ' ' . $type, $post_meta['next_date'] );
				} else {
					$post_meta['next_date'] = strtotime( $product_meta['time'] . ' ' . $type );
				}
			}
			update_post_meta( $subscription_id, '_order_subscrpt_meta', $post_meta );

			wc_update_order_item_meta( $new_order_item_id, '_subscrpt_meta', array(
				'time'                =>  $product_meta['time'],
				'type'                =>  $product_meta['type'],
				'trial'               =>  null,
				'start_date'          =>  $product_meta['start_date'],
				'next_date'           =>  $post_meta['next_date']
			) );

			global $wpdb;
			$history_table = $wpdb->prefix . 'subscrpt_histories';
			$wpdb->insert( $history_table, array(
				'subscription_id'     => $subscription_id,
				'order_id'            => $new_order->get_id(),
				'order_item_id'       => $new_order_item_id,
				'stat'                => 'Renewal Order'
			) );
		} else {
			do_action('subscrpt_renewal_order_add_product', $subscription_id, $new_order);
		}

		update_post_meta( $order_id, '_order_key', 'wc_' . apply_filters( 'woocommerce_generate_order_key', uniqid( 'order_' ) ) );
		update_post_meta( $order_id, '_customer_user', get_post_meta( $original_order_id, '_customer_user', true ) );
		update_post_meta( $order_id, '_order_currency', get_post_meta( $original_order_id, '_order_currency', true ) );

		// 3 Add Billing Fields
		update_post_meta( $order_id, '_billing_city', get_user_meta( $user_id, 'billing_city', true ) );
		update_post_meta( $order_id, '_billing_state', get_user_meta( $user_id, 'billing_state', true ) );
		update_post_meta( $order_id, '_billing_postcode', get_user_meta( $user_id, 'billing_postcode', true ) );
		update_post_meta( $order_id, '_billing_email', get_user_meta( $user_id, 'billing_email', true ) );
		update_post_meta( $order_id, '_billing_phone', get_user_meta( $user_id, 'billing_phone', true ) );
		update_post_meta( $order_id, '_billing_address_1', get_user_meta( $user_id, 'billing_address_1', true ) );
		update_post_meta( $order_id, '_billing_address_2', get_user_meta( $user_id, 'billing_address_2', true ) );
		update_post_meta( $order_id, '_billing_country', get_user_meta( $user_id, 'billing_country', true ) );
		update_post_meta( $order_id, '_billing_first_name', get_user_meta( $user_id, 'billing_first_name', true ) );
		update_post_meta( $order_id, '_billing_last_name', get_user_meta( $user_id, 'billing_last_name', true ) );
		update_post_meta( $order_id, '_billing_company', get_user_meta( $user_id, 'billing_company', true ) );

		// 4 Add Shipping Fields
		update_post_meta( $order_id, '_shipping_country', get_user_meta( $user_id, 'shipping_country', true ) );
		update_post_meta( $order_id, '_shipping_first_name', get_user_meta( $user_id, 'shipping_first_name', true ) );
		update_post_meta( $order_id, '_shipping_last_name', get_user_meta( $user_id, 'shipping_last_name', true ) );
		update_post_meta( $order_id, '_shipping_company', get_user_meta( $user_id, 'shipping_company', true ) );
		update_post_meta( $order_id, '_shipping_address_1', get_user_meta( $user_id, 'shipping_address_1', true ) );
		update_post_meta( $order_id, '_shipping_address_2', get_user_meta( $user_id, 'shipping_address_2', true ) );
		update_post_meta( $order_id, '_shipping_city', get_user_meta( $user_id, 'shipping_city', true ) );
		update_post_meta( $order_id, '_shipping_state', get_user_meta( $user_id, 'shipping_state', true ) );
		update_post_meta( $order_id, '_shipping_postcode', get_user_meta( $user_id, 'shipping_postcode', true ) );

		if ( $old_order->get_payment_method() == 'stripe' ) {
			update_post_meta( $order_id, '_stripe_customer_id', get_user_meta( $user_id, '_stripe_customer_id', true ) );
			update_post_meta( $order_id, '_stripe_source_id', get_post_meta( $original_order_id, '_stripe_source_id', true ) );
		}

		// $new_order->add_coupon('Fresher', '10', '2'); // accepted $couponcode, $couponamount,$coupon_tax
		$new_order->set_payment_method( $old_order->get_payment_method() ); // stripe
		$new_order->set_payment_method_title( $old_order->get_payment_method_title() ); // Credit Card (Stripe)
		$new_order->calculate_totals();

		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => sprintf( __( 'Subscription successfully created.	order is %s', 'sdevs_subscrpt' ), $new_order->get_id() ),
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Renewal Order' );
		wc_add_notice( 'Renewal Order(#' . $order_id . ') Created . Please Pay now', 'success' );

		do_action( 'subscrpt_after_create_renew_order', $new_order, $old_order, $subscription_id, $early_renew );
	}
}
