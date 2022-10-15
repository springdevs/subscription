<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Action [ helper class ]
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Action {

	public static function status( $status, $subscription_id ) {
		if ( current_user_can( 'edit_post', $subscription_id ) ) {
			wp_update_post( array(
				'ID' => $subscription_id,
				'post_status' => $status
			) );

			self::write_comment( $status, $subscription_id );
			self::user( $subscription_id );
		}
	}

	public static function write_comment( $status, $subscription_id ) {
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
		}
	}

	private static function expired( $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription is Expired',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Expired' );

        do_action('subscrpt_subscription_expired', $subscription_id);
	}

	private static function active( $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription activated.Next payment due date set.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Activated' );

        do_action('subscrpt_subscription_activated', $subscription_id);
	}

	private static function pending( $subscription_id ) {
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

	private static function cancelled( $subscription_id ) {
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

	private static function user( $subscription_id ) {
		$user = new \WP_User( get_current_user_id() );
		if ( ! empty( $user->roles ) && is_array( $user->roles ) && in_array( 'administrator', $user->roles ) ) {
			return;
		}

		if ( Helper::subscription_exists( $subscription_id, 'active' ) ) {
			$user->set_role( get_option( 'subscrpt_active_role', 'subscriber' ) );
		} else if ( Helper::subscription_exists( $subscription_id, array( 'cancelled', 'expired' ) ) ) {
			$user->set_role( get_option( 'subscrpt_unactive_role', 'customer' ) );
		}
	}
}
