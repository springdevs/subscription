<?php

namespace SpringDevs\Subscription\Frontend;

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
		$expired_items   = get_user_meta( get_current_user_id(), '_subscrpt_expired_items', true );
		$pending_items   = get_user_meta( get_current_user_id(), '_subscrpt_pending_items', true );
		$cancelled_items = get_user_meta( get_current_user_id(), '_subscrpt_cancelled_items', true );

		if ( ! is_array( $expired_items ) ) {
			$expired_items = array();
		}
		if ( ! is_array( $pending_items ) ) {
			$pending_items = array();
		}
		if ( ! is_array( $cancelled_items ) ) {
			$cancelled_items = array();
		}

		$expired_products = array();
		foreach ( $expired_items as $expired_item ) {
			$expired_products[] = $expired_item['product'];
		}

		$pending_products = array();
		foreach ( $pending_items as $pending_item ) {
			$pending_products[] = $pending_item['product'];
		}

		$cancelled_products = array();
		foreach ( $cancelled_items as $cancelled_item ) {
			$cancelled_products[] = $cancelled_item['product'];
		}

		foreach ( $downloads as $key => $download ) {
			if ( in_array( $download['product_id'], $expired_products ) ) {
				unset( $downloads[ $key ] );
			}
			if ( in_array( $download['product_id'], $pending_products ) ) {
				unset( $downloads[ $key ] );
			}
			if ( in_array( $download['product_id'], $cancelled_products ) ) {
				unset( $downloads[ $key ] );
			}
		}
		return $downloads;
	}
}
