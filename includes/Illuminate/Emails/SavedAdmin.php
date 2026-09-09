<?php
/**
 * Admin notice that a subscription was saved (free).
 *
 * The customer opened the cancellation flow and backed out. Throttled upstream
 * to once a day per subscription, so this never fires for someone idly opening
 * and closing the modal - see Illuminate\Cancellation::record_save().
 *
 * @package SpringDevs\Subscription\Illuminate\Emails
 */

namespace SpringDevs\Subscription\Illuminate\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Admin notice that a subscription was saved (free).
 */
class SavedAdmin extends AdminCancellationEmail {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->id          = 'subscrpt_cancellation_saved_admin';
		$this->title       = __( 'Subscription saved ( Admin )', 'subscription' );
		$this->description = __( 'Sent to the store owner when a customer starts cancelling and then keeps their subscription.', 'subscription' );

		add_action( 'subscrpt_subscription_saved', array( $this, 'trigger' ), 10, 2 );

		parent::__construct();

		$this->template_html  = 'emails/cancellation-saved-admin-html.php';
		$this->template_plain = 'emails/plains/cancellation-saved-admin-plain.php';
	}

	/**
	 * Get default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}] A subscription was saved', 'subscription' );
	}

	/**
	 * Get default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'A subscription was saved', 'subscription' );
	}

	/**
	 * Opening sentence of the mail.
	 *
	 * @return string
	 */
	protected function get_intro() {
		return __( 'A customer started cancelling a subscription and then decided to keep it.', 'subscription' );
	}
}
