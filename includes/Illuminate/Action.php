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
		wp_update_post(
			array(
				'ID'          => $subscription_id,
				'post_status' => $status,
			)
		);

		if ( $write_comment ) {
			self::write_comment( $status, $subscription_id );
		}

		self::user( $subscription_id );
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
		}
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
				'comment_content' => 'Subscription activated.Next payment due date set.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Activated' );
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
	}

	/**
	 * Write Comment About Subscription Cancelled.
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
	}

	/**
	 * Update user role.
	 *
	 * @param Int $subscription_id Subscription ID.
	 */
	private static function user( $subscription_id ) {
		$user = new \WP_User( get_current_user_id() );
		if ( ! empty( $user->roles ) && is_array( $user->roles ) && in_array( 'administrator', $user->roles, true ) ) {
			return;
		}

		if ( Helper::subscription_exists( $subscription_id, 'active' ) ) {
			$user->set_role( get_option( 'subscrpt_active_role', 'subscriber' ) );
		} elseif ( Helper::subscription_exists( $subscription_id, array( 'cancelled', 'expired' ) ) ) {
			$user->set_role( get_option( 'subscrpt_unactive_role', 'customer' ) );
		}
	}
}
