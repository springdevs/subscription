<?php


namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Action;
use SpringDevs\Subscription\Illuminate\Helper;

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
		if ( ! wp_verify_nonce( $wpnonce, 'subscrpt_nonce' ) ) {
			wp_die( esc_html( __( 'Sorry !! You cannot permit to access.', 'sdevs_subscrpt' ) ) );
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
		} elseif ( 'reactive' === $action ) {
			Action::status( 'active', $subscrpt_id );
		} elseif ( 'renew-on' === $action ) {
			update_post_meta( $subscrpt_id, '_subscrpt_auto_renew', 1 );
		} elseif ( 'renew-off' === $action ) {
			update_post_meta( $subscrpt_id, '_subscrpt_auto_renew', 0 );
		} elseif ( 'renew' === $action && subscrpt_is_auto_renew_enabled() ) {
			Helper::create_renewal_order( $subscrpt_id );
		} else {
			do_action( 'subscrpt_execute_actions', $subscrpt_id, $action );
		}
		// phpcs:ignore
		echo ( "<script>location.href = '" . wc_get_endpoint_url( 'view-subscription', $subscrpt_id, wc_get_page_permalink( 'myaccount' ) ) . "';</script>" );
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

		wc_add_notice( get_option( 'subscrpt_manual_renew_cart_notice' ), 'success' );
		$this->redirect( wc_get_cart_url() );
	}

	/**
	 * Redirect on URL.
	 *
	 * @param String $url URL.
	 */
	public function redirect( $url ) {
		?>
		<script>
			window.location.href = '<?php echo esc_url_raw( $url ); ?>';
		</script>
		<?php
	}
}
