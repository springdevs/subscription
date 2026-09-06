<?php
/**
 * Plugin Name: Subscriptions for WooCommerce with Stripe Recurring Payments
 * Plugin URI: https://wpsubscription.co/
 * Description: WPSubscription allows WooCommerce to enable recurring payments, subscriptions, and auto-renewals for digital and physical products. Supports Stripe, PayPal, Paddle, and more.
 *
 * Version: #WPSUBS_VERSION
 *
 * Author: ConversWP
 * Author URI: https://wpsubscription.co/
 *
 * Text Domain: subscription
 * Domain Path: /languages
 *
 * Requires PHP: 7.4
 *
 * Requires at least: 6.0
 * Tested up to: 7.1
 *
 * WC requires at least: 6.0
 * WC tested up to: 10.3
 *
 * Requires Plugins: woocommerce
 *
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Subscription
 */

// don't call the file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use SpringDevs\Subscription\Illuminate\Gateways\Paypal\Paypal_Blocks_Integration;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Sdevs_Subscription class
 *
 * @class Sdevs_Subscription The class that holds the entire plugin
 */
final class Sdevs_Subscription {


	/**
	 * Plugin version
	 *
	 * @var string
	 */
	const VERSION = '#WPSUBS_VERSION';

	/**
	 * Holds various class instances
	 *
	 * @var array
	 */
	private $container = array();

	/**
	 * Constructor for the Sdevs_Wc_Subscription class
	 *
	 * Sets up all the appropriate hooks and actions
	 * within our plugin.
	 */
	private function __construct() {
		$this->define_constants();

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
	}

	/**
	 * Initializes the Sdevs_Wc_Subscription() class
	 *
	 * Checks for an existing Sdevs_Wc_Subscription() instance
	 * and if it doesn't find one, creates it.
	 *
	 * @return Sdevs_Subscription|bool
	 */
	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new Sdevs_Subscription();
		}

		return $instance;
	}

	/**
	 * Magic getter to bypass referencing plugin.
	 *
	 * @param mixed $prop Prop.
	 *
	 * @return mixed
	 */
	public function __get( $prop ) {
		if ( array_key_exists( $prop, $this->container ) ) {
			return $this->container[ $prop ];
		}

		return $this->{$prop};
	}

	/**
	 * Magic isset to bypass referencing plugin.
	 *
	 * @param mixed $prop Prop.
	 *
	 * @return bool
	 */
	public function __isset( $prop ) {
		return isset( $this->{$prop} ) || isset( $this->container[ $prop ] );
	}

	/**
	 * Define the constants
	 *
	 * @return void
	 */
	public function define_constants() {
		define( 'SUBSCRPT_VERSION', self::VERSION );
		define( 'SUBSCRPT_FILE', __FILE__ );
		define( 'SUBSCRPT_PATH', dirname( SUBSCRPT_FILE ) );
		define( 'SUBSCRPT_INCLUDES', SUBSCRPT_PATH . '/includes' );
		define( 'SUBSCRPT_TEMPLATES', SUBSCRPT_PATH . '/templates/' );
		define( 'SUBSCRPT_URL', plugins_url( '', SUBSCRPT_FILE ) );
		define( 'SUBSCRPT_ASSETS', SUBSCRPT_URL . '/assets' );

		// Load legacy constant aliases (WP_SUBSCRIPTION_* → SUBSCRPT_*) for
		// backwards compatibility with subscription-pro and third-party code.
		require_once SUBSCRPT_INCLUDES . '/LegacyCompat.php';
	}

	/**
	 * Load the plugin after all plugins are loaded
	 *
	 * @return void
	 */
	public function init_plugin() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Placeholder for activation function
	 */
	public function activate() {
		$installer = new SpringDevs\Subscription\Installer();
		$installer->run();
	}

	/**
	 * Placeholder for deactivation function
	 *
	 * Nothing being called here yet.
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'subscrpt_hourly_cron' );
		wp_clear_scheduled_hook( 'subscrpt_daily_cron' ); // legacy cleanup for sites migrating from old hook name
	}

	/**
	 * Include the required files
	 *
	 * @return void
	 */
	public function includes() {
		// Include functions file first to ensure global functions are available
		require_once SUBSCRPT_INCLUDES . '/functions.php';
		require_once SUBSCRPT_INCLUDES . '/Admin/AdminComponents.php';

		if ( $this->is_request( 'admin' ) ) {
			$this->container['admin'] = new SpringDevs\Subscription\Admin();
		}

		if ( $this->is_request( 'frontend' ) ) {
			$this->container['frontend'] = new SpringDevs\Subscription\Frontend();
		}

		$this->container['illuminate'] = new SpringDevs\Subscription\Illuminate();
	}

	/**
	 * Initialize the hooks
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'init_classes' ) );
		add_action( 'init', array( $this, 'localization_setup' ) );
		add_action( 'init', array( $this, 'run_update' ) );

		// HPOS Compatibility: Declare support for custom order tables
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				}
			}
		);

		// HPOS Compatibility: Register subscription post type with HPOS support
		add_action(
			'init',
			function () {
				register_post_type(
					'subscrpt_order',
					array(
						'hpos'            => true,
						'public'          => false,
						'show_ui'         => true,
						'show_in_menu'    => false,
						'supports'        => array( 'title' ),
						'capability_type' => 'post',
						'capabilities'    => array(
							'create_posts' => false,
						),
						'map_meta_cap'    => true,
					)
				);
			},
			0
		);
	}

	/**
	 * Need to do some actions after update plugin
	 *
	 * @return void
	 */
	public function run_update() {
		$upgrade = new \SpringDevs\Subscription\Upgrade();
		$upgrade->run();
	}

	/**
	 * Instantiate the required classes
	 *
	 * @return void
	 */
	public function init_classes() {
		if ( $this->is_request( 'ajax' ) ) {
			$this->container['ajax']            = new SpringDevs\Subscription\Ajax();
			$this->container['onboarding_ajax'] = new SpringDevs\Subscription\Admin\OnboardingAjax();
		}

		$this->container['api']    = new SpringDevs\Subscription\API();
		$this->container['assets'] = new SpringDevs\Subscription\Assets();
	}

	/**
	 * Initialize plugin for localization
	 *
	 * Note: WordPress automatically loads translations for plugins hosted on WordPress.org
	 * since version 4.6, so manual loading is not required.
	 */
	public function localization_setup() {
		// WordPress auto-loads translations for plugins hosted on WordPress.org since v4.6.
	}

	/**
	 * What type of request is this?
	 *
	 * @param string $type admin, ajax, cron or frontend.
	 *
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();

			case 'ajax':
				return defined( 'DOING_AJAX' );

			case 'rest':
				return defined( 'REST_REQUEST' );

			case 'cron':
				return defined( 'DOING_CRON' );

			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}
} // Sdevs_Wc_Subscription

// Add Paypal Gateway Blocks.
if ( ! function_exists( 'subscrpt_register_paypal_block' ) ) {
	/**
	 * Register the PayPal block for WooCommerce Blocks.
	 */
	function subscrpt_register_paypal_block() {
		// Check if the required class exists.
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		// Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action.
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new Paypal_Blocks_Integration() );
			}
		);
	}

	// Register PayPal integration only if WordPress functions are available
	if ( function_exists( 'get_option' ) ) {
		// Is PayPal integration enabled?
		$is_paypal_integration_enabled = 'on' === get_option( 'wp_subs_paypal_integration_enabled', 'off' );
		if ( $is_paypal_integration_enabled ) {
			add_action( 'woocommerce_blocks_loaded', 'subscrpt_register_paypal_block' );
		}
	}
}

/**
 * Initialize the main plugin
 *
 * @return Sdevs_Subscription|bool
 */
function sdevs_subscription() {
	return Sdevs_Subscription::init();
}

/**
 *  Kick-off the plugin
 */
sdevs_subscription();
