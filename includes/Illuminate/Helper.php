<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Helper || Some Helper Methods
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Helper {

	/**
	 * Get type's singular or plural from time_per.
	 *
	 * @param int    $number timing_per.
	 * @param string $typo timing_option.
	 *
	 * @return string
	 */
	public static function get_typos( $number, $typo ) {
		if ( 1 === (int) $number && 'days' === $typo ) {
			return __( 'day', 'sdevs_subscrpt' );
		} elseif ( 1 === (int) $number && 'weeks' === $typo ) {
			return __( 'week', 'sdevs_subscrpt' );
		} elseif ( 1 === (int) $number && 'months' === $typo ) {
			return __( 'month', 'sdevs_subscrpt' );
		} elseif ( 1 === (int) $number && 'years' === $typo ) {
			return __( 'year', 'sdevs_subscrpt' );
		} else {
			return $typo;
		}
	}

	/**
	 * Generate start date
	 *
	 * @param null|string $trial Trial.
	 *
	 * @return string
	 */
	public static function start_date( $trial = null ) {
		if ( null === $trial ) {
			$start_date = time();
		} else {
			$start_date = strtotime( $trial );
		}
		return wp_date( get_option( 'date_format' ), $start_date );
	}

	/**
	 * Generate next date
	 *
	 * @param mixed       $time Time.
	 * @param Null|String $trial Trial.
	 *
	 * @return String
	 */
	public static function next_date( $time, $trial = null ) {
		if ( null === $trial ) {
			$start_date = time();
		} else {
			$start_date = strtotime( $trial );
		}
		return wp_date( get_option( 'date_format' ), strtotime( $time, $start_date ) );
	}

	/**
	 * Check subscription exists by product ID.
	 *
	 * @param int          $product_id Product ID.
	 * @param string|array $status Status.
	 *
	 * @return \WP_Post | false
	 */
	public static function subscription_exists( int $product_id, $status ) {
		if ( 0 === get_current_user_id() ) {
			return false;
		}

		$args = array(
			'post_type'   => 'subscrpt_order',
			'post_status' => $status,
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'key'   => '_subscrpt_product_id',
					'value' => $product_id,
				),
			),
			'author'      => get_current_user_id(),
		);

		$posts = get_posts( $args );
		return count( $posts ) > 0 ? $posts[0] : false;
	}

	/**
	 * Check if product trial exixts for an user.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return boolean
	 */
	public static function check_trial( int $product_id ): bool {
		return ! self::subscription_exists( $product_id, array( 'expired', 'pending', 'active', 'on-hold', 'pe_cancelled', 'cancelled' ) );
	}

	/**
	 * Rewew when expired.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public static function renew( int $subscription_id ) {
		$trial = get_post_meta( $subscription_id, '_subscrpt_trial', true );
		if ( null !== $trial ) {
			update_post_meta( $subscription_id, '_subscrpt_trial', null );
		}

		do_action( 'subscrpt_when_product_expired', $subscription_id, true );
	}

	/**
	 * Get Subscriptions Histories
	 *
	 * @param int $order_id Order ID.
	 */
	public static function get_subscriptions_from_order( $order_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		$histories  = $wpdb->get_results(
			$wpdb->prepare(
				// @phpcs:ignore
				'SELECT * FROM %i WHERE order_id=%d',
				array( $table_name, $order_id )
			)
		);

		return $histories;
	}

	/**
	 * Get Subscriptions Histories
	 *
	 * @param int $order_item_id Order item ID.
	 */
	public static function get_subscription_from_order_item_id( $order_item_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		return $wpdb->get_row(
			$wpdb->prepare(
				// @phpcs:ignore
				'SELECT * FROM %i WHERE order_item_id=%d',
				array( $table_name, $order_item_id )
			)
		);
	}

	/**
	 * Format price with Subscription
	 *
	 * @param string $price Price.
	 * @param int    $subscription_id Subscription ID.
	 * @param bool   $display_trial True/False.
	 *
	 * @return string
	 */
	public static function format_price_with_subscription( $price, $subscription_id, $display_trial = false ) {
		$order_id      = get_post_meta( $subscription_id, '_subscrpt_order_id', true );
		$order_item_id = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
		$item_meta     = wc_get_order_item_meta( $order_item_id, '_subscrpt_meta', true );

		$order = wc_get_order( $order_id );
		$time  = '1' === $item_meta['time'] ? null : $item_meta['time'] . ' ';
		$type  = self::get_typos( $item_meta['time'], $item_meta['type'] );

		$formatted_price = wc_price(
			$price,
			array(
				'currency' => $order->get_currency(),
			)
		) . ' / ' . $time . $type;

		if ( $display_trial ) {
			$trial     = $item_meta['trial'];
			$has_trial = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;

			if ( $has_trial ) {
				$trial_html       = '<br/><small> + Got ' . $trial . ' free trial!</small>';
				$formatted_price .= $trial_html;
			}
		}

		return apply_filters( 'subscrpt_format_price_with_subscription', $formatted_price, $price, $subscription_id );
	}

	/**
	 * Format price with order item
	 *
	 * @param string $price Price.
	 * @param int    $item_id Item Id.
	 * @param bool   $display_trial display trial?.
	 *
	 * @return string
	 */
	public static function format_price_with_order_item( $price, $item_id, $display_trial = false ) {
		$order_id = wc_get_order_id_by_order_item_id( $item_id );
		$order    = wc_get_order( $order_id );

		$item_meta = wc_get_order_item_meta( $item_id, '_subscrpt_meta', true );

		if ( ! $item_meta || ! is_array( $item_meta ) ) {
			return false;
		}

		$time = 1 === (int) $item_meta['time'] ? null : $item_meta['time'] . '-';
		$type = self::get_typos( $item_meta['time'], $item_meta['type'] );

		$formatted_price = wc_price(
			$price,
			array(
				'currency' => $order->get_currency(),
			)
		) . ' / ' . $time . $type;

		if ( $display_trial ) {
			$trial     = $item_meta['trial'];
			$has_trial = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;

			if ( $has_trial ) {
				$trial_html       = '<br/><small> + Got ' . $trial . ' free trial!</small>';
				$formatted_price .= $trial_html;
			}
		}

		return apply_filters( 'subscrpt_format_price_with_subscription', $formatted_price, $price, $item_id );
	}

	/**
	 * Get total subscriptions by product ID.
	 *
	 * @param Int            $product_id Product ID.
	 * @param String | array $status Status.
	 *
	 * @return \WP_Post | false
	 */
	public static function get_total_subscriptions_from_product( int $product_id, $status = array( 'active', 'pending', 'expired', 'pe_cancelled', 'cancelled' ) ) {
		$args = array(
			'post_type'   => 'subscrpt_order',
			'post_status' => $status,
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'key'   => '_subscrpt_product_id',
					'value' => $product_id,
				),
			),
		);

		$posts = get_posts( $args );

		return count( $posts );
	}

	/**
	 * Process renewal on order.
	 *
	 * @param int $subscription_id Subscription Id.
	 * @param int $order_id Order Id.
	 * @param int $order_item_id Order Item Id.
	 *
	 * @return void
	 */
	public static function process_order_renewal( $subscription_id, $order_id, $order_item_id ) {
		global $wpdb;
		$history_table = $wpdb->prefix . 'subscrpt_order_relation';

		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => sprintf(
					// translators: order id.
					__( 'The order %s has been created for the subscription', 'sdevs_subscrpt' ),
					$order_id
				),
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', __( 'Renewal Order', 'sdevs_subscrpt' ) );

		$wpdb->insert(
			$history_table,
			array(
				'subscription_id' => $subscription_id,
				'order_id'        => $order_id,
				'order_item_id'   => $order_item_id,
				'type'            => 'renew',
			)
		);
	}

	/**
	 * Process new subscription on order.
	 *
	 * @param \WC_Order_Item $order_item Order Item.
	 * @param string         $post_status status.
	 * @param \WC_Product    $product Product.
	 *
	 * @return int
	 */
	public static function process_new_subscription_order( $order_item, $post_status, $product ) {
		global $wpdb;
		$history_table = $wpdb->prefix . 'subscrpt_order_relation';

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
				'comment_content' => sprintf(
					// translators: Order Id.
					__( 'Subscription successfully created.	order is %s', 'sdevs_subscrpt' ),
					$order_item->get_order_id()
				),
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', __( 'New Subscription', 'sdevs_subscrpt' ) );

		update_post_meta( $subscription_id, '_subscrpt_product_id', $product->get_id() );

		$wpdb->insert(
			$history_table,
			array(
				'subscription_id' => $subscription_id,
				'order_id'        => $order_item->get_order_id(),
				'order_item_id'   => $order_item->get_id(),
				'type'            => 'new',
			)
		);

		return $subscription_id;
	}
}
