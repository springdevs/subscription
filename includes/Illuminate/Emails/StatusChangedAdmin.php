<?php

namespace SpringDevs\Subscription\Illuminate\Emails;

use SpringDevs\Subscription\Illuminate\Helper;
use WC_Email;

/**
 * Mail - Sent to Admin when subscription status changed.
 *
 * @package SpringDevs\Subscription\Illuminate\Emails
 */
class StatusChangedAdmin extends WC_Email {

	/**
	 * Subscription id.
	 *
	 * @var int
	 */
	public $subscription_id;

	/**
	 * Product Name ( Order Item ).
	 *
	 * @var string
	 */
	public $product_name;

	/**
	 * Subscription's quantity.
	 *
	 * @var int
	 */
	public $qty;

	/**
	 * Formatted recurring amount.
	 *
	 * @var string
	 */
	public $amount;

	/**
	 * Next payment date of subscription.
	 *
	 * @var string
	 */
	public $next_date;

	/**
	 * Old status of subscription.
	 *
	 * @var string
	 */
	public $old_status;

	/**
	 * New status of subscription.
	 *
	 * @var string
	 */
	public $new_status;

	/**
	 * Initialize the class.
	 *
	 * @param array $templates Templates.
	 */
	public function __construct( array $templates ) {
		$this->id          = 'subscrpt_status_changed_admin_email';
		$this->title       = __( 'Subscription status changed ( Admin )', 'sdevs_subscrpt' );
		$this->description = __( 'This email is received when a subscription status changed.', 'sdevs_subscrpt' );

		// email template path.
		$this->template_html  = $templates['html'];
		$this->template_plain = $templates['plain'];

		// Triggers for this email.
		add_action( 'subscrpt_status_changed_admin_email_notification', array( $this, 'trigger' ), 10, 3 );

		// Call parent constructor.
		parent::__construct();

		// Other settings.
		$this->template_base = SUBSCRPT_TEMPLATES;

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
	 * Get email heading.
	 *
	 * @return string
	 * @since  3.1.0
	 */
	public function get_default_heading(): string {
		return __( 'Subscription: #{subscription_id}', 'sdevs_subscrpt' );
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
		$this->placeholders['{subscription_id}'] = $subscription_id;
		$this->subscription_id                   = $subscription_id;
		$this->old_status                        = $old_status;
		$this->new_status                        = $new_status;

		$order_item_id = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
		$order_id      = wc_get_order_id_by_order_item_id( $order_item_id );
		$order         = wc_get_order( $order_id );
		$order_item    = $order->get_item( $order_item_id );

		$this->product_name = $order_item->get_name();
		$this->qty          = $order_item->get_quantity();
		$this->amount       = Helper::format_price_with_order_item( get_post_meta( $subscription_id, '_subscrpt_price', true ), $order_item_id );

		$this->next_date = '<next-date>';

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * Get the email content in HTML format.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return $this->render_template( $this->template_html );
	}

	/**
	 * Get the email content in Plain Format.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return $this->render_template( $this->template_plain );
	}

	/**
	 * Render template for email.
	 *
	 * @param string $path Template Path.
	 *
	 * @return string
	 */
	private function render_template( string $path ): string {
		return wc_get_template_html(
			$path,
			array(
				'id'            => $this->subscription_id,
				'email_heading' => $this->get_heading(),
				'product_name'  => $this->product_name,
				'qty'           => $this->qty,
				'amount'        => $this->amount,
				'next_date'     => $this->next_date,
				'old_status'    => $this->old_status,
				'new_status'    => $this->new_status,
			),
			'subscription',
			$this->template_base
		);
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
