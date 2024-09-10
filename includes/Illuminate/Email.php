<?php

namespace SpringDevs\Subscription\Illuminate;

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
		add_action( 'subscrpt_subscription_expired', array( $this, 'after_subscription_expired' ) );

		add_action( 'subscrpt_status_changed_admin_email', array( 'WC_Emails', 'send_transactional_email' ), 10, 3 );
		add_action( 'subscrpt_subscription_expired_email', array( 'WC_Emails', 'send_transactional_email' ), 10, 3 );
	}

	/**
	 * Sent mail after subscription expired!
	 *
	 * @param int $subscription_id Subscription id.
	 * @return void
	 */
	public function after_subscription_expired( int $subscription_id ) {
		WC()->mailer();
		do_action( 'subscrpt_subscription_expired_email_notification', $subscription_id );
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
