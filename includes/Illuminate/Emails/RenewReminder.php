<?php

namespace SpringDevs\Subscription\Illuminate\Emails;

use SpringDevs\Subscription\Traits\Email;
use WC_Email;

/**
 * Subscription Renewal Reminder Mail to Customer.
 */
class RenewReminder extends WC_Email {

	use Email;

	/**
	 *
	 * Initialize the class.
	 */
	public function __construct() {
		$this->customer_email = true;

		$this->id          = 'subscrpt_renew_reminder';
		$this->title       = __( 'Subscription Renewal Reminder', 'sdevs_subscrpt' );
		$this->description = __( 'This email is sent to customer for renewing subscription before expire.', 'sdevs_subscrpt' );

		// email template path.
		$this->set_template( $this->id );

		// Triggers for this email.
		add_action( 'subscrpt_renew_reminder_email_notification', array( $this, 'trigger' ) );

		// Call parent constructor.
		parent::__construct();
	}

	/**
	 * Get default subject.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'Reminder for the Subscription renewal #{subscription_id}', 'sdevs_subscrpt' );
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

		$this->extra = array(
			'num_of_days_before' => $this->get_option( 'num_of_days_before' ),
		);
		$this->set_table_data();

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * Initialize form fields for settings.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		unset( $this->form_fields['additional_content'] );

		$this->form_fields = array_slice( $this->form_fields, 0, 2 ) + array(
			'num_of_days_before' => array(
				'title'   => __(
					'Number of days before the next subscription payment.',
					'sdevs_subscrpt'
				),
				'type'    => 'number',
				'default' => 7,
			),
		) + array_slice( $this->form_fields, 2, 2 );
	}
}
