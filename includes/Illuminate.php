<?php
/**
 * Service container bootstrap.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\Checkout;
use SpringDevs\Subscription\Frontend\PlanCheckout;
use SpringDevs\Subscription\Illuminate\AutoRenewal;
use SpringDevs\Subscription\Illuminate\Block;
use SpringDevs\Subscription\Illuminate\Cancellation;
use SpringDevs\Subscription\Illuminate\Cron;
use SpringDevs\Subscription\Illuminate\Email;
use SpringDevs\Subscription\Illuminate\Order;
use SpringDevs\Subscription\Illuminate\Post;
use SpringDevs\Subscription\Illuminate\Stats;
use SpringDevs\Subscription\Illuminate\Gateways\Stripe\Stripe;
use SpringDevs\Subscription\Illuminate\GuestCheckout;
use SpringDevs\Subscription\Illuminate\RoleManagement;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;

/**
 * Globally Load Scripts.
 */
class Illuminate {

	/**
	 * Initialize the Class.
	 */
	public function __construct() {
		$this->stripe_initialization();
		$this->paypal_initialization();

		new Subscription();
		new RoleManagement();
		new Order();
		new Cron();
		new Cancellation();
		new Stats();
		new Post();
		new Block();
		new Checkout();
		// Pro ships a superset plan checkout (Subscribe & Save, Installments,
		// variable / per-variation) on the same hooks and priorities, so free's
		// Recurring-simple checkout runs only when Pro is absent — otherwise the two
		// would create the subscription twice.
		if ( ! subscrpt_pro_activated() ) {
			new PlanCheckout();
		}
		new GuestCheckout();
		new AutoRenewal();
		new Email();

		// Hide the internal plan snapshot meta from the admin order-item screen.
		// WooCommerce's admin item view lists every meta key not in this filter
		// (it does not hide the leading-underscore prefix there). Registered here —
		// always loaded, both plugins share these keys — so it covers free and pro.
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hidden_plan_order_itemmeta' ) );
	}

	/**
	 * Hide the plan snapshot meta keys on the admin order-item screen.
	 *
	 * @param array $keys Hidden order-item meta keys.
	 *
	 * @return array
	 */
	public function hidden_plan_order_itemmeta( $keys ) {
		return array_merge(
			(array) $keys,
			array(
				'_subscrpt_plan_id',
				'_subscrpt_plan_group_id',
				'_subscrpt_plan_price',
				'_subscrpt_plan_payment_type',
				'_subscrpt_plan_max_no_payment',
				'_subscrpt_plan_terms',
			)
		);
	}

	/**
	 * Stripe Initialization.
	 *
	 * @return void
	 */
	public function stripe_initialization() {
		if ( function_exists( 'woocommerce_gateway_stripe' ) ) {
			if ( ! class_exists( 'WC_Payment_Gateway_CC' ) ) {
				include_once dirname( WC_PLUGIN_FILE ) . '/includes/gateways/class-wc-payment-gateway-cc.php';
			}

			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/compat/trait-wc-stripe-subscriptions-utilities.php';
			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/compat/trait-wc-stripe-pre-orders.php';
			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/compat/trait-wc-stripe-subscriptions.php';
			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/abstracts/abstract-wc-stripe-payment-gateway.php';

			if ( class_exists( '\WC_Stripe_Payment_Gateway' ) ) {
				new Stripe();
			}
		}
	}

	/**
	 * PayPal Gateway Initialization.
	 *
	 * @return void
	 */
	public function paypal_initialization() {
		// Forcefully enable PayPal integration if the option is not set.
		update_option( 'wp_subs_paypal_integration_enabled', 'on' );

		$is_paypal_integration_enabled = 'on' === get_option( 'wp_subs_paypal_integration_enabled', 'off' );

		// Register the PayPal gateway with WooCommerce.
		if ( $is_paypal_integration_enabled ) {
			add_filter( 'woocommerce_payment_gateways', [ $this, 'register_paypal_gateway' ] );
		}
	}

	/**
	 * Register our custom PayPal gateway with WooCommerce
	 *
	 * @param array $gateways Payment gateways.
	 * @return array
	 */
	public function register_paypal_gateway( $gateways ) {
		$gateways[] = 'SpringDevs\\Subscription\\Illuminate\\Gateways\\Paypal\\Paypal';
		return $gateways;
	}
}
