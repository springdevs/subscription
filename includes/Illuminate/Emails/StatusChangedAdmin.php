<?php

namespace SpringDevs\Subscription\Illuminate\Emails;

use SpringDevs\Subscription\Traits\Email;
use WC_Email;

/**
 * Mail - Sent to Admin when subscription status changed.
 *
 * @package SpringDevs\Subscription\Illuminate\Emails
 */
class StatusChangedAdmin extends WC_Email {

	use Email;

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->id          = 'subscrpt_status_changed_admin_email';
		$this->title       = __( 'Subscription status changed ( Admin )', 'sdevs_subscrpt' );
		$this->description = __( 'This email is received when a subscription status changed.', 'sdevs_subscrpt' );

		// email template path.
		$this->set_template( $this->id );

		// Triggers for this email.
		add_action( 'subscrpt_status_changed_admin_email_notification', array( $this, 'trigger' ), 10, 3 );

		// Call parent constructor.
		parent::__construct();

		// default the email recipient to the admin email address.
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Get default subject.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( '#{subscription_id} subscription status changed', 'sdevs_subscrpt' );
	}

	/**
	 * Trigger the mail.
	 *
	 * @param int    $subscription_id Subscription id.
	 * @param string $old_status Old Status.
	 * @param string $new_status New Status.
	 *
	 * @return void
	 */
	public function trigger( int $subscription_id, string $old_status, string $new_status ) {

		if ( 'no' === $this->enabled ) {
			return;
		}

		$this->placeholders['{subscription_id}'] = $subscription_id;
		$this->subscription_id                   = $subscription_id;

		$this->extra = array(
			'old_status' => $old_status,
			'new_status' => $new_status,
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
	}
}
