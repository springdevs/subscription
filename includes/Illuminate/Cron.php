<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Cron
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Cron {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'subscrpt_daily_cron', array( $this, 'daily_cron_task' ) );
	}

	/**
	 * Run daily cron task to check if subscription expired.
	 */
	public function daily_cron_task() {
		$args = array(
			'post_type'   => 'subscrpt_order',
			'post_status' => array( 'active', 'pe_cancelled' ),
			'fields'      => 'ids',
			'author'      => get_current_user_id(),
		);

		$active_subscriptions = get_posts( $args );

		if ( $active_subscriptions && count( $active_subscriptions ) > 0 ) {
			foreach ( $active_subscriptions as $subscription ) {
				$start_date = get_post_meta( $subscription, '_subscrpt_start_date', true );
				$next_date  = get_post_meta( $subscription, '_subscrpt_next_date', true );
				$trial      = get_post_meta( $subscription, '_subscrpt_trial', true );

				if ( time() >= $next_date || ( null !== $trial && time() >= $start_date ) ) {
					if ( 'pe_cancelled' === get_post_status( $subscription ) ) {
						Action::status( 'cancelled', $subscription );
					} else {
						Action::status( 'expired', $subscription );
					}
				}
			}
		}
	}
}
