<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\Checkout;
use SpringDevs\Subscription\Illuminate\AutoRenewal;
use SpringDevs\Subscription\Illuminate\Block;
use SpringDevs\Subscription\Illuminate\Cron;
use SpringDevs\Subscription\Illuminate\Email;
use SpringDevs\Subscription\Illuminate\Order;
use SpringDevs\Subscription\Illuminate\Post;
use SpringDevs\Subscription\Illuminate\Stripe;

/**
 * Globally Load Scripts.
 */
class Illuminate {

	/**
	 * Initialize the Class.
	 */
	public function __construct() {
		$this->stripe_initialization();
		new Order();
		new Cron();
		new Post();
		new Block();
		new Checkout();
		new AutoRenewal();
		new Email();
	}

	/**
	 * Stripe Initialization.
	 *
	 * @return void
	 */
	public function stripe_initialization() {
		if ( function_exists( 'woocommerce_gateway_stripe' ) ) {
			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/compat/trait-wc-stripe-subscriptions-utilities.php';
			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/compat/trait-wc-stripe-pre-orders.php';
			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/compat/trait-wc-stripe-subscriptions.php';
			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/abstracts/abstract-wc-stripe-payment-gateway.php';

			new Stripe();
		}
	}
}
