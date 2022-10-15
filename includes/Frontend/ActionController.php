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


	public function __construct() {
		 add_action( 'before_single_subscrpt_content', array( $this, 'control_action_subscrpt' ) );
	}

	public function control_action_subscrpt() {
		if ( ! ( isset( $_GET['subscrpt_id'] ) && isset( $_GET['action'] ) && isset( $_GET['wpnonce'] ) ) ) {
			return;
		}
		$subscrpt_id = sanitize_text_field( $_GET['subscrpt_id'] );
		$action      = sanitize_text_field( $_GET['action'] );
		$wpnonce     = sanitize_text_field( $_GET['wpnonce'] );
		if ( ! wp_verify_nonce( $wpnonce, 'subscrpt_nonce' ) ) {
			wp_die( __( 'Sorry !! You cannot permit to access.', 'sdevs_subscrpt' ) );
		}
		if ( $action == 'renew' ) {
			$this->RenewProduct( $subscrpt_id );
		} elseif ( $action == 'early-renew' ) {
			Helper::renew( $subscrpt_id );
		} elseif ( $action == 'renew-on' ) {
			update_post_meta( $subscrpt_id, '_subscrpt_auto_renew', 1 );
		} elseif ( $action == 'renew-off' ) {
			update_post_meta( $subscrpt_id, '_subscrpt_auto_renew', 0 );
		} else {
			if ( $action == 'cancelled' ) {
				$user_cancell = get_post_meta( $subscrpt_id, '_subscrpt_user_cancell', true );
				if ( $user_cancell == 'no' ) {
					return;
				}
			}
			Action::status( $action, $subscrpt_id );
		}
		echo ( "<script>location.href = '" . get_permalink( wc_get_page_id( 'myaccount' ) ) . 'view-subscrpt/' . $subscrpt_id . "';</script>" );
	}

	public function RenewProduct( $subscrpt_id ) {
		$post_meta    = get_post_meta( $subscrpt_id, '_order_subscrpt_meta', true );
		$product_id    = get_post_meta( $subscrpt_id, '_subscrpt_product_id', true );

		$variation_id = 0;
		if ( isset( $post_meta['variation_id'] ) ) {
			$variation_id = $post_meta['variation_id'];
		}

		WC()->cart->add_to_cart(
			$product_id,
			1,
			$variation_id,
			array(),
			array( 'renew_subscrpt' => true )
		);

		wc_add_notice( __( 'Product added to cart', 'sdevs_subscrpt' ), 'success' );
		$this->redirect( wc_get_cart_url() );
	}

	public function redirect( $url ) {
		?>
		<script>
			window.location.href = '<?php echo esc_url_raw( $url ); ?>';
		</script>
		<?php
	}
}
