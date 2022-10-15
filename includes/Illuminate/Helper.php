<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Helper || Some Helper Methods
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Helper {

	public static function get_typos( $number, $typo ) {
		if ( $number == 1 && $typo == 'days' ) {
			return __( 'day', 'sdevs_subscrpt' );
		} elseif ( $number == 1 && $typo == 'weeks' ) {
			return __( 'week', 'sdevs_subscrpt' );
		} elseif ( $number == 1 && $typo == 'months' ) {
			return __( 'month', 'sdevs_subscrpt' );
		} elseif ( $number == 1 && $typo == 'years' ) {
			return __( 'year', 'sdevs_subscrpt' );
		} else {
			return $typo;
		}
	}

	public static function next_date( $time, $trial = null ) {
		if ( $trial == null ) {
			$start_date = time();
		} else {
			$start_date = strtotime( $trial );
		}
		return date( 'F d, Y', strtotime( $time, $start_date ) );
	}

	/**
	 * @param Int $product_id
	 * @param String | Array $status
	 * 
	 * @return WP_Post | false
	 */
	public static function subscription_exists( $product_id, $status ) {
		$args = array(
			'post_type' => 'subscrpt_order',
			'post_status' => $status,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key'   => '_subscrpt_product_id',
					'value' => $product_id,
				)
			),
			'author' => get_current_user_id()
		);

		$posts = get_posts($args);

		if ( count($posts) > 0 ) {
			return $posts[0];
		}

		return false;
	}

	public static function check_trial( $product_id ) {
		return self::subscription_exists( $product_id, array( 'expired', 'pending', 'active', 'on-hold', 'cancelled' ) );
	}

	public static function renew( $subscription_id ) {
		$post_meta = get_post_meta( $subscription_id, '_order_subscrpt_meta', true );
		if ( $post_meta['trial'] != null ) {
			$post_meta['trial'] = null;
			update_post_meta( $subscription_id, '_order_subscrpt_meta', $post_meta );
		}

		do_action( 'subscrpt_when_product_expired', $subscription_id, true );
	}

	public static function get_subscriptions_from_order( $order_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_histories';
		$histories = $wpdb->get_results("SELECT * FROM ${table_name} WHERE order_id=${order_id}");

		return $histories;
	}

	public static function format_price_with_subscription( $price, $subscription_id, $display_trial = false )
	{
		$subscription_meta = get_post_meta( $subscription_id, '_order_subscrpt_meta', true );
		$item_meta = wc_get_order_item_meta( $subscription_meta['order_item_id'], '_subscrpt_meta', true );

		$order = wc_get_order( $subscription_meta['order_id'] );
		$time                = $item_meta['time'] == 1 ? null : $item_meta['time'] . ' ';
		$type                = self::get_typos( $item_meta['time'], $item_meta['type'] );

		$formatted_price = wc_price( $price, array(
			'currency' => $order->get_currency()
		) ) . ' / ' . $time . $type;

		if ( $display_trial ) {
			$trial           = $item_meta['trial'];
			$has_trial       = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;

			if ( $has_trial ) {
				$trial_html = '<br/><small> + Got ' . $trial . ' free trial!</small>';
				$formatted_price .= $trial_html;
			}
		}

		return apply_filters( 'subscrpt_format_price_with_subscription', $formatted_price, $price, $subscription_id );
	}

	public static function format_price_with_order_item( $price, $item_id, $display_trial = false )
	{
		$order_id 	= wc_get_order_id_by_order_item_id($item_id);
		$order 		= wc_get_order($order_id);

		$item_meta 			 = wc_get_order_item_meta( $item_id, '_subscrpt_meta', true );

		if ( !$item_meta || !is_array($item_meta) ) {
			return false;
		}

		$time                = $item_meta['time'] == 1 ? null : $item_meta['time'] . ' ';
		$type                = self::get_typos( $item_meta['time'], $item_meta['type'] );

		$formatted_price = wc_price( $price, array(
			'currency' => $order->get_currency()
		) ) . ' / ' . $time . $type;

		if ( $display_trial ) {
			$trial           = $item_meta['trial'];
			$has_trial       = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;

			if ( $has_trial ) {
				$trial_html = '<br/><small> + Got ' . $trial . ' free trial!</small>';
				$formatted_price .= $trial_html;
			}
		}

		return apply_filters( 'subscrpt_format_price_with_subscription', $formatted_price, $price, $item_id );
	}
}
