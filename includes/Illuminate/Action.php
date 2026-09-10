<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Action [ helper class ]
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Action {

	/**
	 * Did when status changes.
	 *
	 * @param string $status Status.
	 * @param int    $subscription_id Subscription ID.
	 * @param bool   $write_comment Write comment?.
	 */
	public static function status( string $status, int $subscription_id, bool $write_comment = true ) {
		// A split-payment subscription whose final installment is paid is terminal:
		// never (re)activate it. Any renewal-payment path that tries to set it active
		// after completion is coerced to `completed` so the status can't flip back.
		if (
			'active' === $status
			&& function_exists( 'subscrpt_is_max_payments_reached' )
			&& subscrpt_is_max_payments_reached( $subscription_id )
		) {
			$status = 'completed';
		}

		$old_status = get_post_status( $subscription_id );

		wp_update_post(
			array(
				'ID'          => $subscription_id,
				'post_status' => $status,
			)
		);

		// Only note a real transition — never re-log the same status.
		if ( $write_comment && $old_status !== $status ) {
			self::write_comment( $status, $subscription_id );
		}

		// Trigger status change action
		do_action( 'subscrpt_subscription_status_changed', $subscription_id, $old_status, $status );

		// Trigger resumption event if subscription is being activated from cancelled or pending cancellation
		if ( $status === 'active' && in_array( $old_status, array( 'cancelled', 'pe_cancelled' ) ) ) {
			do_action( 'subscrpt_subscription_resumed', $subscription_id, $old_status );
		}
	}

	/**
	 * Write Comment based on status.
	 *
	 * @param string $status Status.
	 * @param Int    $subscription_id Subscription ID.
	 */
	public static function write_comment( string $status, int $subscription_id ) {
		switch ( $status ) {
			case 'expired':
				self::expired( $subscription_id );
				break;
			case 'active':
				self::active( $subscription_id );
				break;
			case 'pending':
				self::pending( $subscription_id );
				break;
			case 'cancelled':
				self::cancelled( $subscription_id );
				break;
			case 'pe_cancelled':
				self::pe_cancelled( $subscription_id );
				break;
			case 'on-hold':
				self::on_hold( $subscription_id );
				break;
			case 'completed':
				self::completed( $subscription_id );
				break;
		}
	}

	/**
	 * Write Comment About Completed Subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function completed( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription completed. All payments made.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Completed' );
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'subs_completed' );

		do_action( 'subscrpt_subscription_completed', $subscription_id );
	}

	/**
	 * Write Comment About expired Subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function expired( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription is Expired',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Expired' );
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'subs_expired' );

		do_action( 'subscrpt_subscription_expired', $subscription_id );
	}

	/**
	 * Write Comment About Active Subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function active( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription activated. Next payment due date set.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Activated' );
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'subs_activated' );

		do_action( 'subscrpt_subscription_activated', $subscription_id );
	}

	/**
	 * Write Comment About Subscription Pending.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function pending( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription is pending.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Pending' );
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'subs_pending' );

		do_action( 'subscrpt_subscription_pending', $subscription_id );
	}

	/**
	 * Write Comment About cancelled Subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function cancelled( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription is Cancelled.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Cancelled' );
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'subs_cancelled' );

		WC()->mailer();
		do_action( 'subscrpt_subscription_cancelled_email_notification', $subscription_id );
		do_action( 'subscrpt_subscription_cancelled', $subscription_id );

		// Fire split payment cancelled action
		do_action( 'subscrpt_split_payment_cancelled', $subscription_id );
	}

	/**
	 * Write Comment About On-Hold Subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function on_hold( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription is On Hold. Access suspended after payment failure.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription On Hold' );
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'subs_on_hold' );

		do_action( 'subscrpt_subscription_on_hold', $subscription_id );
	}

	/**
	 * Write Comment About Pending Cancellation.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function pe_cancelled( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription is Pending Cancellation.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Pending Cancellation' );
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'subs_pe_cancel' );

		// WC_Email classes only exist once the mailer has been built, and they
		// attach their own listeners from their constructors. Without this, an
		// email listening for a pending cancellation is simply not registered yet
		// when the action fires - the same reason cancelled() calls it.
		WC()->mailer();

		do_action( 'subscrpt_subscription_pending_cancellation', $subscription_id );
	}
}
