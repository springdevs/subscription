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
		add_action( 'subscrpt_renew_reminder_cron', array( $this, 'send_renew_reminder_mail' ) );
	}

	/**
	 * Send renew reminder mail.
	 *
	 * @return void
	 */
	public function send_renew_reminder_mail() {
		$time = strtotime( '7 days' );

		$args = array(
			'post_type'   => 'subscrpt_order',
			'post_status' => array( 'active', 'pe_cancelled' ),
			'fields'      => 'ids',
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'     => '_subscrpt_next_date',
					'value'   => $time,
					'compare' => '<=',
				),
				array(
					'key'   => '_subscrpt_trial',
					'compare' => 'NOT EXISTS',
				),
                array(
					'key'   => '_subscrpt_reminder_mail_sent',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		$subscriptions = get_posts( $args );

        if ( 0 === count($subscriptions) ) {
            return;
        }

        WC()->mailer();
        foreach ( $subscriptions as $subscription_id ) {
            do_action( 'subscrpt_renew_reminder_email_notification', $subscription_id );
        }
	}

	/**
	 * Run daily cron task to check if subscription expired.
	 */
	public function daily_cron_task() {
		$args = array(
			'post_type'   => 'subscrpt_order',
			'post_status' => array( 'active', 'pe_cancelled' ),
			'fields'      => 'ids',
			'meta_query'  => array(
				'relation' => 'OR',
				array(
					'key'     => '_subscrpt_next_date',
					'value'   => time(),
					'compare' => '<=',
				),
				array(
					'relation' => 'AND',
					array(
						'key'     => '_subscrpt_trial',
						'value'   => null,
						'compare' => '!=',
					),
					array(
						'key'     => '_subscrpt_start_date',
						'value'   => time(),
						'compare' => '<=',
					),
				),
			),
		);

		$expired_subscriptions = get_posts( $args );

		if ( $expired_subscriptions && count( $expired_subscriptions ) > 0 ) {
			foreach ( $expired_subscriptions as $subscription ) {
				if ( 'pe_cancelled' === get_post_status( $subscription ) ) {
					Action::status( 'cancelled', $subscription );
				} else {
					Action::status( 'expired', $subscription );
				}
			}
		}
	}
}
