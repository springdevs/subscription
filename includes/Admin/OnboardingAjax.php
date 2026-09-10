<?php

namespace SpringDevs\Subscription\Admin;

/**
 * AJAX handlers for the onboarding wizard.
 *
 * The wizard creates a plan + duration and connects it to a product entirely
 * through the Plans REST API (wpsubscription/v1/plans). The one thing REST does
 * not offer is creating a WooCommerce product, so that single step lives here;
 * everything else — group, term, relation — is done client-side against REST.
 *
 * @package SpringDevs\Subscription\Admin
 */
class OnboardingAjax {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'wp_ajax_subscrpt_create_wizard_product', array( $this, 'create_wizard_product' ) );
		add_action( 'wp_ajax_subscrpt_reset_wizard', array( $this, 'reset_wizard' ) );
	}

	/**
	 * Create a bare simple product (name + price) for the wizard to connect a
	 * plan to. No subscription meta is written here — connecting the plan (the
	 * REST relation) is what makes the product subscribable.
	 *
	 * @return void Sends JSON.
	 */
	public function create_wizard_product() {
		check_ajax_referer( 'subscrpt_onboarding_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'subscription' ) ), 403 );
		}

		$product_name  = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
		$product_price = isset( $_POST['product_price'] ) ? sanitize_text_field( wp_unslash( $_POST['product_price'] ) ) : '';

		if ( '' === trim( $product_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Product name is required.', 'subscription' ) ) );
		}

		$product = new \WC_Product_Simple();
		$product->set_name( $product_name );
		if ( '' !== trim( $product_price ) ) {
			$product->set_regular_price( wc_format_decimal( $product_price ) );
		}
		$product->set_status( 'publish' );
		$product_id = $product->save();

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create product. Please try again.', 'subscription' ) ) );
		}

		wp_send_json_success(
			array(
				'product_id'    => $product_id,
				'product_name'  => $product_name,
				'product_price' => $product_price,
			)
		);
	}

	/**
	 * Reset wizard session (for "Add another" / "Start over" actions).
	 *
	 * @return void Sends JSON.
	 */
	public function reset_wizard() {
		check_ajax_referer( 'subscrpt_onboarding_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'subscription' ) ), 403 );
		}

		if ( ! session_id() ) {
			session_start();
		}

		unset( $_SESSION['subscrpt_onboarding_wizard'] );

		wp_send_json_success();
	}
}
