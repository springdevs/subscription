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

	public static function CheckExpired( $product_id ) {
		$user_meta     = get_user_meta( get_current_user_id(), '_subscrpt_expired_items', true );
		$expired_items = array();
		if ( is_array( $user_meta ) ) {
			foreach ( $user_meta as $usermeta ) {
				array_push( $expired_items, $usermeta['product'] );
			}
		}
		return in_array( $product_id, $expired_items );
	}

	public static function Check_un_expired( $product_id ) {
		$author        = get_current_user_id();
		$active_items  = get_user_meta( $author, '_subscrpt_active_items', true );
		$pending_items = get_user_meta( $author, '_subscrpt_pending_items', true );

		if ( ! is_array( $active_items ) ) {
			$active_items = array();
		}
		if ( ! is_array( $pending_items ) ) {
			$pending_items = array();
		}

		foreach ( $active_items as $active_item ) {
			if ( $active_item['product'] == $product_id ) {
				return true;
			}
		}

		foreach ( $pending_items as $pending_item ) {
			if ( $pending_item['product'] == $product_id ) {
				return true;
			}
		}

		return false;
	}

	public static function Check_Trial( $product_id ) {
		$result  = true;
		$author  = get_current_user_id();
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}
		$cancelled_items = get_user_meta( $author, '_subscrpt_cancelled_items', true );
		$expired_items   = get_user_meta( $author, '_subscrpt_expired_items', true );
		$active_items    = get_user_meta( $author, '_subscrpt_active_items', true );
		$pending_items   = get_user_meta( $author, '_subscrpt_pending_items', true );

		if ( ! is_array( $cancelled_items ) ) {
			$cancelled_items = array();
		}
		if ( ! is_array( $expired_items ) ) {
			$expired_items = array();
		}
		if ( ! is_array( $active_items ) ) {
			$active_items = array();
		}
		if ( ! is_array( $pending_items ) ) {
			$pending_items = array();
		}

		foreach ( $cancelled_items as $cancelled_item ) {
			if ( $cancelled_item['product'] == $product_id ) {
				$result = false;
			}
		}

		foreach ( $expired_items as $expired_item ) {
			if ( $expired_item['product'] == $product_id ) {
				$result = false;
			}
		}

		foreach ( $active_items as $active_item ) {
			if ( $active_item['product'] == $product_id ) {
				$result = false;
			}
		}

		foreach ( $pending_items as $pending_item ) {
			if ( $pending_item['product'] == $product_id ) {
				$result = false;
			}
		}

		if ( ! $product->is_type( 'variable' ) ) {
			return $result;
		}
		return apply_filters( 'subscrpt_filter_product_trial', $result, $product_id, $active_items, $pending_items, $cancelled_items, $expired_items );
	}
}
