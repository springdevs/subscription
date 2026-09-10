<?php
/**
 * Admin notice that a subscription was cancelled (free).
 *
 * Fires the moment the customer confirms, not when the cancellation finally
 * takes effect, so the store owner hears about it while there is still time to
 * act. Reports the reason and nothing identifying.
 *
 * @package SpringDevs\Subscription\Illuminate\Emails
 */

namespace SpringDevs\Subscription\Illuminate\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Admin notice that a subscription was cancelled (free).
 */
class CancellationAdmin extends AdminCancellationEmail {

	/**
	 * Post meta marking that this cancellation has already been reported, so the
	 * pending and final steps cannot both mail.
	 */
	const SENT_FLAG = '_subscrpt_cancel_admin_emailed';

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->id          = 'subscrpt_cancellation_admin';
		$this->title       = __( 'Subscription cancelled ( Admin )', 'subscription' );
		$this->description = __( 'Sent to the store owner when a customer cancels a subscription.', 'subscription' );

		// A customer's confirm puts an active subscription into `pe_cancelled`, and
		// the cron only finalises it to `cancelled` up to a day later. Listen to
		// both, and let trigger() make sure only the first one sends.
		add_action( 'subscrpt_subscription_pending_cancellation', array( $this, 'trigger' ) );
		add_action( 'subscrpt_subscription_cancelled_email_notification', array( $this, 'trigger' ) );

		// A reactivated subscription can be cancelled again, and should mail again.
		add_action( 'subscrpt_subscription_resumed', array( $this, 'clear_sent_flag' ) );

		parent::__construct();

		$this->template_html  = 'emails/cancellation-admin-html.php';
		$this->template_plain = 'emails/plains/cancellation-admin-plain.php';
	}

	/**
	 * Get default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}] A subscription was cancelled', 'subscription' );
	}

	/**
	 * Get default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'A subscription was cancelled', 'subscription' );
	}

	/**
	 * Opening sentence of the mail.
	 *
	 * @return string
	 */
	protected function get_intro() {
		return __( 'A customer has cancelled their subscription.', 'subscription' );
	}

	/**
	 * Send once per cancellation, at whichever step comes first.
	 *
	 * @param int   $subscription_id Subscription post ID.
	 * @param array $context         Event context.
	 *
	 * @return void
	 */
	public function trigger( $subscription_id, $context = array() ) {
		$subscription_id = (int) $subscription_id;

		if ( $subscription_id && get_post_meta( $subscription_id, self::SENT_FLAG, true ) ) {
			return;
		}

		parent::trigger( $subscription_id, $context );

		if ( $subscription_id ) {
			update_post_meta( $subscription_id, self::SENT_FLAG, '1' );
		}
	}

	/**
	 * Let a resumed subscription be reported again if it is cancelled a second time.
	 *
	 * @param int $subscription_id Subscription post ID.
	 *
	 * @return void
	 */
	public function clear_sent_flag( $subscription_id ) {
		delete_post_meta( (int) $subscription_id, self::SENT_FLAG );
	}
}
