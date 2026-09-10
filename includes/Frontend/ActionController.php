<?php


namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Action;
use SpringDevs\Subscription\Illuminate\Helper;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;

/**
 * Class ActionController
 *
 * @package SpringDevs\Subscription\Frontend
 */
class ActionController {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'before_single_subscrpt_content', array( $this, 'control_action_subscrpt' ) );
	}

	/**
	 * Take Subscription Action.
	 */
	public function control_action_subscrpt() {
		if ( ! ( isset( $_GET['subscrpt_id'] ) && isset( $_GET['action'] ) && isset( $_GET['wpnonce'] ) ) ) {
			return;
		}

		$subscrpt_id = sanitize_text_field( wp_unslash( $_GET['subscrpt_id'] ) );
		$action      = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		$wpnonce     = sanitize_text_field( wp_unslash( $_GET['wpnonce'] ) );

		// Nonce check
		if ( ! wp_verify_nonce( $wpnonce, 'subscrpt_nonce' ) ) {
			$error_notice = __( "You don't have permission to modify this subscription. If you believe this is an error, please contact support.", 'subscription' );
			wc_add_notice( $error_notice, 'error' );

			$view_subscription_endpoint = Subscription::get_user_endpoint( 'view_subs' );
			$redirect_url               = wc_get_endpoint_url( $view_subscription_endpoint, $subscrpt_id, wc_get_page_permalink( 'myaccount' ) );
			return wp_safe_redirect( $redirect_url );
		}

		// User check
		$subs_post       = get_post( $subscrpt_id );
		$author_id       = $subs_post ? (int) $subs_post->post_author : 0;
		$current_user_id = get_current_user_id();
		$user_is_admin   = current_user_can( 'manage_options' );

		if ( ! $user_is_admin && (int) $author_id !== (int) $current_user_id ) {
			$error_notice = __( "You don't have permission to modify this subscription. If you believe this is an error, please contact support.", 'subscription' );
			wc_add_notice( $error_notice, 'error' );

			$view_subscription_endpoint = Subscription::get_user_endpoint( 'view_subs' );
			$redirect_url               = wc_get_endpoint_url( $view_subscription_endpoint, $subscrpt_id, wc_get_page_permalink( 'myaccount' ) );
			return wp_safe_redirect( $redirect_url );
		}

		// Get view subscription endpoint slug.
		$view_subs_endpoint = Subscription::get_user_endpoint( 'view_subs' );

		// Check maximum payment limit for renewal-related actions (including early renewal)
		$renewal_actions = apply_filters( 'subscrpt_renewal_actions', array( 'renew', 'renew-on', 'early-renew' ) );
		if ( in_array( $action, $renewal_actions, true ) && subscrpt_is_max_payments_reached( $subscrpt_id ) ) {
			wc_add_notice( __( 'This subscription has reached its maximum payment limit and cannot be renewed further.', 'subscription' ), 'error' );
			wp_safe_redirect( wc_get_endpoint_url( $view_subs_endpoint, $subscrpt_id, wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}

		if ( 'renew' === $action && ! subscrpt_is_auto_renew_enabled() ) {
			$this->manual_renew_product( $subscrpt_id );
		} elseif ( 'cancelled' === $action ) {
			$status      = get_post_status( $subscrpt_id );
			$user_cancel = get_post_meta( $subscrpt_id, '_subscrpt_user_cancel', true );
			if ( 'no' === $user_cancel ) {
				return;
			} elseif ( 'active' === $status ) {
				Action::status( 'pe_cancelled', $subscrpt_id );
			} else {
				Action::status( $action, $subscrpt_id );
			}
		} elseif ( 'reactivate' === $action ) {
			Action::status( 'active', $subscrpt_id );
		} elseif ( 'renew-on' === $action ) {
			update_post_meta( $subscrpt_id, '_subscrpt_auto_renew', 1 );
		} elseif ( 'renew-off' === $action ) {
			update_post_meta( $subscrpt_id, '_subscrpt_auto_renew', 0 );
		} elseif ( 'renew' === $action && subscrpt_is_auto_renew_enabled() ) {
			Helper::create_renewal_order( $subscrpt_id );
		} else {
			// Safety check: If this is any kind of renewal action and limit is reached, block it
			if ( subscrpt_is_max_payments_reached( $subscrpt_id ) &&
				( strpos( $action, 'renew' ) !== false || strpos( $action, 'renewal' ) !== false ) ) {
				wc_add_notice( __( 'This subscription has reached its maximum payment limit and cannot be renewed further.', 'subscription' ), 'error' );
				wp_safe_redirect( wc_get_endpoint_url( $view_subs_endpoint, $subscrpt_id, wc_get_page_permalink( 'myaccount' ) ) );
				exit;
			}

			do_action( 'subscrpt_execute_actions', $subscrpt_id, $action );
		}
		wp_safe_redirect( wc_get_endpoint_url( $view_subs_endpoint, $subscrpt_id, wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	/**
	 * Manually Renew Subscription.
	 *
	 * @param Int $subscrpt_id Subscription ID.
	 */
	public function manual_renew_product( $subscrpt_id ) {
		$product_id                = get_post_meta( $subscrpt_id, '_subscrpt_product_id', true );
		$subscription_variation_id = get_post_meta( $subscrpt_id, '_subscrpt_variation_id', true );

		$variation_id = 0;
		if ( isset( $variation_id ) ) {
			$variation_id = $variation_id;
		}

		WC()->cart->empty_cart();

		WC()->cart->add_to_cart(
			$product_id,
			1,
			$variation_id,
			array(),
			array( 'renew_subscrpt' => true )
		);

		// Empty unless the store set one, and wc_add_notice( '' ) renders an empty
		// green box rather than nothing, so only add it when there is a message.
		$cart_notice = subscrpt_get_manual_renew_cart_notice();
		if ( '' !== $cart_notice ) {
			wc_add_notice( $cart_notice, 'success' );
		}
		$this->redirect( wc_get_cart_url() );
	}

	/**
	 * Redirect on URL.
	 *
	 * @param String $url URL.
	 */
	public function redirect( $url ) {
		wp_safe_redirect( esc_url( $url ) );
		exit;
	}
}
