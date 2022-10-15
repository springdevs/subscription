<?php

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Downloadable class
 * control Download feature - woocommerce
 */
class Downloadable {

	public function __construct() {
		add_filter( 'woocommerce_customer_get_downloadable_products', array( $this, 'check_download_items' ), 10, 1 );
		add_filter( 'woocommerce_order_get_downloadable_items', array( $this, 'check_download_items' ), 10, 1 );
	}

	public function check_download_items( $downloads ) {
		foreach ( $downloads as $key => $download ) {
			$unactive_items = Helper::subscription_exists( $download['product_id'], array( 'expired', 'cancelled', 'pending' ) );

			if ( $unactive_items ) {
				unset( $downloads[ $key ] );
			}
		}
		return $downloads;
	}
}
