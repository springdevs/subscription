<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\Checkout;
use SpringDevs\Subscription\Illuminate\Cron;
use SpringDevs\Subscription\Illuminate\Post;

/**
 * Globally Load Scripts.
 */
class Illuminate {


	/**
	 * Initialize the Class.
	 */
	public function __construct() {
		$this->dispatch_actions();
		new Cron();
		new Post();
		new Checkout();
	}

	/**
	 * Dispatch and bind actions
	 *
	 * @return void
	 */
	public function dispatch_actions() {
		add_action(
			'woocommerce_blocks_loaded',
			function () {
				require_once __DIR__ . '/Illuminate/wc-block-integration.php';
				add_action(
					'woocommerce_blocks_cart_block_registration',
					function ( $integration_registry ) {
						$integration_registry->register( new \Sdevs_Subscrpt_WC_Integration() );
					}
				);
				add_action(
					'woocommerce_blocks_checkout_block_registration',
					function ( $integration_registry ) {
						$integration_registry->register( new \Sdevs_Subscrpt_WC_Integration() );
					}
				);
			}
		);
	}
}
