<?php

namespace SpringDevs\Subscription\Illuminate\Emails;

use SpringDevs\Subscription\Traits\Email;
use WC_Email;

/**
 * Subscription Expired Mail to Customer.
 */
class SubscriptionExpired extends WC_Email {

	use Email;

	/**
	 *
	 * Initialize the class.
	 */
	public function __construct() {
		$this->customer_email = true;

		$this->id          = 'subscrpt_subscription_expired_email';
		$this->title       = __( 'Subscription expired', 'sdevs_subscrpt' );
		$this->description = __( 'This email is sent to customer when a subscription expired.', 'sdevs_subscrpt' );

		// email template path.
		$this->set_template( $this->id );

		// Triggers for this email.
		add_action( 'subscrpt_subscription_expired_email_notification', array( $this, 'trigger' ) );

		// Call parent constructor.
		parent::__construct();
	}

	/**
	 * Get default subject.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( '#{subscription_id} subscription expired!', 'sdevs_subscrpt' );
	}

	/**
	 * Get the customer email.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return get_the_author_meta( 'email', get_post_field( 'post_author', $this->subscription_id ) );
	}

	/**
	 * Trigger the mail.
	 *
	 * @param int $subscription_id Subscription id.
	 *
	 * @return void
	 */
	public function trigger( int $subscription_id ) {
		if ( 'no' === $this->enabled ) {
			return;
		}
		$this->placeholders['{subscription_id}'] = $subscription_id;
		$this->subscription_id                   = $subscription_id;

		$this->set_table_data();

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}
}
