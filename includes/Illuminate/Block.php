<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Globally Load Scripts.
 */
class Block {

	/**
	 * Initialize the Class.
	 */
	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'block_registration' ) );
	}

	/**
	 * Block Registration.
	 *
	 * @return void
	 */
	public function block_registration() {
		require_once __DIR__ . '/wc-block-integration.php';
		add_action( 'woocommerce_blocks_cart_block_registration', array( $this, 'register' ) );
		add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'register' ) );
	}

	/**
	 * Register the class.
	 *
	 * @param \Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry $integration_registry registry.
	 *
	 * @return void
	 */
	public function register( $integration_registry ) {
		$integration_registry->register( new \Sdevs_Subscrpt_WC_Integration() );
	}
}
