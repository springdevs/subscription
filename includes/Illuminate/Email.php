<?php

namespace SpringDevs\Subscription\Illuminate;

use SpringDevs\Subscription\Illuminate\Emails\CancellationAdmin;
use SpringDevs\Subscription\Illuminate\Emails\SavedAdmin;
use SpringDevs\Subscription\Illuminate\Emails\StatusChangedAdmin;
use SpringDevs\Subscription\Illuminate\Emails\SubscriptionCancelled;
use SpringDevs\Subscription\Illuminate\Emails\SubscriptionExpired;

/**
 * Class Email
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Email {

	/**
	 * The constructor method.
	 */
	public function __construct() {
		add_action( 'woocommerce_email_after_order_table', array( $this, 'add_subscription_table' ) );
		add_filter( 'woocommerce_email_classes', array( $this, 'register_emails' ) );

		add_action( 'subscrpt_subscription_expired', array( $this, 'schedule_expired_email' ) );
		add_action( 'subscrpt_send_delayed_expired_email', array( $this, 'send_expired_email' ), 10, 1 );
		add_action( 'subscrpt_subscription_activated', array( $this, 'cancel_pending_expired_email' ) );

		add_action( 'subscrpt_status_changed_admin_email', array( 'WC_Emails', 'send_transactional_email' ), 10, 3 );
		add_action( 'subscrpt_subscription_expired_email', array( 'WC_Emails', 'send_transactional_email' ), 10, 3 );
	}

	/**
	 * Schedule the "subscription expired" email to fire after a short delay.
	 *
	 * @param int $subscription_id Subscription id.
	 * @return void
	 */
	public function schedule_expired_email( int $subscription_id ) {
		$delay_seconds = (int) apply_filters( 'subscrpt_expired_email_delay', HOUR_IN_SECONDS );

		// Zero delay: send immediately.
		if ( $delay_seconds <= 0 ) {
			$this->send_expired_email( $subscription_id );
			return;
		}

		$hook           = 'subscrpt_send_delayed_expired_email';
		$args           = array( $subscription_id );
		$scheduled_time = time() + $delay_seconds;

		// Existing queue check.
		$existing = wp_next_scheduled( $hook, $args );
		if ( $existing ) {
			wp_unschedule_event( $existing, $hook, $args );
		}

		wp_schedule_single_event( $scheduled_time, $hook, $args );
	}

	/**
	 * Fire the delayed "subscription expired" email.
	 *
	 * @param int $subscription_id Subscription id.
	 * @return void
	 */
	public function send_expired_email( int $subscription_id ) {
		if ( 'expired' !== get_post_status( $subscription_id ) ) {
			return;
		}

		WC()->mailer();
		do_action( 'subscrpt_subscription_expired_email_notification', $subscription_id );
	}

	/**
	 * Cancel any pending delayed "subscription expired" email.
	 *
	 * @param int $subscription_id Subscription id.
	 * @return void
	 */
	public function cancel_pending_expired_email( int $subscription_id ) {
		$hook = 'subscrpt_send_delayed_expired_email';
		$args = array( $subscription_id );

		if ( function_exists( 'as_unschedule_action' ) ) {
			as_unschedule_action( $hook, $args );
		}
		wp_clear_scheduled_hook( $hook, $args );
	}

	/**
	 * Register emails.
	 *
	 * @param array $emails Email classes.
	 *
	 * @return array
	 */
	public function register_emails( array $emails ): array {
		$emails['subscrpt_status_changed_admin_email']   = new StatusChangedAdmin();
		$emails['subscrpt_subscription_expired_email']   = new SubscriptionExpired();
		$emails['subscrpt_subscription_cancelled_email'] = new SubscriptionCancelled();

		// The admin cancellation notices are free's reduced stand-ins. Pro ships
		// richer equivalents on the same events, so registering both would list
		// two of each in WooCommerce and mail the store owner twice.
		if ( ! subscrpt_pro_activated() ) {
			$emails['subscrpt_cancellation_admin']       = new CancellationAdmin();
			$emails['subscrpt_cancellation_saved_admin'] = new SavedAdmin();
		}

		return $emails;
	}

	/**
	 * Add subscription sections inside order mail.
	 *
	 * @param \WC_Order $order Order Object.
	 *
	 * @return void
	 */
	public function add_subscription_table( \WC_Order $order ) {
		$histories = Helper::get_subscriptions_from_order( $order->get_id() );

		if ( count( $histories ) > 0 ) {
			include 'views/subscription-table.php';
		}
	}
}
