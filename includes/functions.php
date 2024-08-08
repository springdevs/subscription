<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Generate Url for Subscription Action.
 *
 * @param string $action Action.
 * @param string $nonce nonce.
 * @param int    $subscription_id Subscription ID.
 *
 * @return string
 */
function subscrpt_get_action_url( $action, $nonce, $subscription_id ) {
	return add_query_arg(
		array(
			'subscrpt_id' => $subscription_id,
			'action'      => $action,
			'wpnonce'     => $nonce,
		),
		wc_get_endpoint_url( 'view-subscription', $subscription_id, wc_get_page_permalink( 'myaccount' ) )
	);
}


function subscrpt_get_typos( $number, $typo ) {
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

/**
 * Format time with trial.
 *
 * @param mixed       $time Time.
 * @param null|string $trial Trial.
 *
 * @return string
 */
function subscrpt_next_date( $time, $trial = null ) {
	if ( null === $trial ) {
		$start_date = time();
	} else {
		$start_date = strtotime( $trial );
	}

	return gmdate( 'F d, Y', strtotime( $time, $start_date ) );
}

/**
 * Check if subscription-pro activated.
 *
 * @return bool
 */
function subscrpt_pro_activated(): bool {
	return class_exists( 'Sdevs_Wc_Subscription_Pro' );
}

/**
 * Get renewal process settings.
 *
 * @return string
 */
function subscrpt_get_renewal_process() {
	if ( ! subscrpt_pro_activated() ) {
		return 'manual';
	} else {
		return get_option( 'subscrpt_renewal_process', 'auto' );
	}
}

/**
 * Return Label against key.
 *
 * @param string $key Key to return cast Value.
 *
 * @return string
 */
function order_relation_type_cast( string $key ) {
	$relational_type_keys = apply_filters(
		'subscrpt_order_relational_types',
		array(
			'new'   => __( 'New Subscription Order', 'sdevs_subscrpt' ),
			'renew' => __( 'Renewal Order', 'sdevs_subscrpt' ),
		)
	);

	return isset( $relational_type_keys[ $key ] ) ? $relational_type_keys[ $key ] : '-';
}

if ( ! function_exists( 'is_wc_order_hpos_enabled' ) ) {
	/**
	 * Check if HPOS enabled.
	 */
	function is_wc_order_hpos_enabled() {
			return function_exists( 'wc_get_container' ) ?
					wc_get_container()
					->get( CustomOrdersTableController::class )
					->custom_orders_table_usage_is_enabled()
				: false;
	}
}

if ( ! function_exists( 'sdevs_wp_strtotime' ) ) {
	/**
	 * Get strtotime with WordPress timezone config.
	 *
	 * @param string   $str string.
	 * @param int|null $base_timestamp base timestamp.
	 *
	 * @return int
	 */
	function sdevs_wp_strtotime( $str, $base_timestamp = null ) {
		return strtotime( wp_date( 'Y-m-d H:i:s', strtotime( $str, $base_timestamp ) ) );
	}
}
