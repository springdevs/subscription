<?php

/**
 * Generate Url for Subscription Action.
 *
 * @param String $action Action.
 * @param String $nonce nonce.
 * @param Int    $subscription_id Subscription ID.
 *
 * @return String
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

function subscrpt_next_date( $time, $trial = null ) {
	if ( $trial == null ) {
		$start_date = time();
	} else {
		$start_date = strtotime( $trial );
	}

	return date( 'F d, Y', strtotime( $time, $start_date ) );
}

function subscrpt_pro_activated(): bool {
	return class_exists( 'Sdevs_Wc_Subscription_Pro' );
}
