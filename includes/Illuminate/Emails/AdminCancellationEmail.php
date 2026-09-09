<?php
/**
 * Base for the free plugin's admin cancellation notices.
 *
 * Free deliberately reports less than Pro: the reason and the shape of what
 * happened, but never which customer and never a link to the subscription. The
 * closing block points at Reports, where Pro shows the full churn history.
 *
 * Registered only while Pro is inactive (see Illuminate\Email), so a store never
 * lists both this and Pro's richer equivalent.
 *
 * @package SpringDevs\Subscription\Illuminate\Emails
 */

namespace SpringDevs\Subscription\Illuminate\Emails;

use SpringDevs\Subscription\Illuminate\Cancellation;
use WC_Email;

defined( 'ABSPATH' ) || exit;

/**
 * Base for the free plugin's admin cancellation notices.
 */
abstract class AdminCancellationEmail extends WC_Email {

	/**
	 * Subscription post ID the mail is about.
	 *
	 * @var int
	 */
	public $subscription_id = 0;

	/**
	 * Reason context for the mail, as stored with the cancellation.
	 *
	 * @var array
	 */
	protected $context = array();

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		// Admin-only email - never addressed to the customer.
		$this->customer_email = false;
		$this->recipient      = $this->get_default_recipient();

		parent::__construct();

		$this->template_base = SUBSCRPT_TEMPLATES;
	}

	/**
	 * Opening sentence of the mail.
	 *
	 * @return string
	 */
	abstract protected function get_intro();

	/**
	 * Add a recipient field to the WooCommerce email settings panel.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		unset( $this->form_fields['additional_content'] );

		$this->form_fields['recipient'] = array(
			'title'       => __( 'Recipient(s)', 'subscription' ),
			'type'        => 'text',
			'description' => __( 'Enter recipients (comma-separated) for this email. Defaults to the WooCommerce store email.', 'subscription' ),
			'placeholder' => $this->get_default_recipient(),
			'default'     => '',
			'desc_tip'    => true,
		);
	}

	/**
	 * Get the admin recipient address.
	 *
	 * Prefers the WooCommerce store email; falls back to the WordPress admin email.
	 *
	 * @return string
	 */
	public function get_default_recipient() {
		$wc_email = get_option( 'woocommerce_email_from_address' );
		return ! empty( $wc_email ) ? $wc_email : get_option( 'admin_email' );
	}

	/**
	 * The reason the customer gave, or an empty string.
	 *
	 * Prefers the reason passed with the event; falls back to the stored row so
	 * a cancellation still reports its reason when called without context.
	 *
	 * @return string
	 */
	protected function get_reason_label() {
		if ( ! empty( $this->context['reason_label'] ) ) {
			return (string) $this->context['reason_label'];
		}

		$feedback = Cancellation::get_feedback( $this->subscription_id );

		return ! empty( $feedback['reason_label'] ) ? (string) $feedback['reason_label'] : '';
	}

	/**
	 * Variables shared by the HTML and plain templates.
	 *
	 * Deliberately carries no customer name, customer email or subscription link.
	 *
	 * @return array
	 */
	protected function get_template_vars() {
		$reason = $this->get_reason_label();

		return array(
			'email_heading' => $this->get_heading(),
			'intro'         => $this->get_intro(),
			'reason'        => $reason,
			'reported_at'   => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'reports_url'   => admin_url( 'admin.php?page=wp-subscription-stats' ),
			'upgrade_url'   => 'https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro',
			'email'         => $this,
		);
	}

	/**
	 * Get the HTML email content.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->get_template_vars(), '', $this->template_base );
	}

	/**
	 * Get the plain-text email content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->get_template_vars(), '', $this->template_base );
	}

	/**
	 * Send the mail for one subscription.
	 *
	 * @param int   $subscription_id Subscription post ID.
	 * @param array $context         Event context (reason_label, offer_accepted…).
	 *
	 * @return void
	 */
	public function trigger( $subscription_id, $context = array() ) {
		$subscription_id = (int) $subscription_id;

		if ( ! $subscription_id || ! $this->is_enabled() ) {
			return;
		}

		$this->subscription_id                   = $subscription_id;
		$this->context                           = is_array( $context ) ? $context : array();
		$this->placeholders['{subscription_id}'] = (string) $subscription_id;

		$saved           = $this->get_option( 'recipient' );
		$this->recipient = ! empty( $saved ) ? $saved : $this->get_default_recipient();

		if ( empty( $this->recipient ) ) {
			return;
		}

		$this->setup_locale();
		$this->send( $this->recipient, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		$this->restore_locale();
	}
}
