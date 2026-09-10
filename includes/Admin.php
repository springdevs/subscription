<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Admin\Dashboard;
use SpringDevs\Subscription\Admin\Integrations;
use SpringDevs\Subscription\Admin\Required;
use SpringDevs\Subscription\Admin\Links;
use SpringDevs\Subscription\Admin\CancellationFlow;
use SpringDevs\Subscription\Admin\Menu;
use SpringDevs\Subscription\Admin\Order as AdminOrder;
use SpringDevs\Subscription\Admin\Plans;
use SpringDevs\Subscription\Admin\Product;
use SpringDevs\Subscription\Admin\ProSettingsFields;
use SpringDevs\Subscription\Admin\Settings;
use SpringDevs\Subscription\Admin\Subscriptions;
use SpringDevs\Subscription\Illuminate\Comments;

/**
 * The admin class
 */
class Admin {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		$this->dispatch_actions();
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		// Only show required plugins notice if Pro is NOT active
		if ( ! is_plugin_active( 'subscription-pro/subscription-pro.php' ) ) {
			if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				// Show required notice only, do not load other admin content
				new Required();
				return;
			} else {
				new Required();
			}
		}
		// Only load admin content if WooCommerce is active
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			new Menu();
			new Dashboard();
			new Plans();
			new CancellationFlow();
			new Product\Plans();
			new Integrations();
			new Product();
			new Subscriptions();
			new AdminOrder();
			new Comments();
			new Settings();
			new ProSettingsFields();
			new Links();
		}
	}

	/**
	 * Dispatch and bind actions
	 *
	 * @return void
	 */
	public function dispatch_actions() {
		add_action( 'save_post_product', array( $this, 'flush_gsc_cache' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );
	}

	/**
	 * Send the admin to Overview once, just after activating the plugin.
	 *
	 * A plugin that activates and leaves you on the plugins list has told you
	 * nothing about itself. This opens the screen it just added.
	 *
	 * Deliberately not onboarding: the wizard's entry points are commented out
	 * while it is being reworked, so Overview is what actually exists.
	 *
	 * The flag is cleared before the redirect rather than after, so a
	 * redirect that fails for any reason cannot leave the admin bouncing here
	 * on every request.
	 *
	 * @return void
	 */
	public function maybe_redirect_after_activation() {
		if ( ! get_transient( 'subscrpt_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'subscrpt_activation_redirect' );

		// Activating several plugins at once ends on the plugins list by
		// design; jumping away from it would hide the others' notices.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading WordPress's own bulk-activation marker, no state change.
		if ( isset( $_GET['activate-multi'] ) || wp_doing_ajax() || is_network_admin() ) {
			return;
		}

		// The menu this points at is only registered when WooCommerce is
		// active; without it the page does not exist, and the plugins list is
		// where the "WooCommerce required" notice is waiting anyway.
		if ( ! current_user_can( 'manage_options' ) || ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wp-subscription' ) );
		exit;
	}

	/**
	 * Bust the GSC product-exists cache when any product is saved.
	 *
	 * @return void
	 */
	public function flush_gsc_cache() {
		delete_transient( 'subscrpt_has_enabled_product' );
	}
}
