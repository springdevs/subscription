<?php
/*
Plugin Name: Subscription for WooCommerce
Plugin URI: https://wordpress.org/plugins/subscription
Description: Allow your customers to order once and get their products and services every month/week.
Version: 1.3
Author: SpringDevs
Author URI: https://springdevs.com/
Requires Plugins: woocommerce
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: sdevs_subscrpt
Domain Path: /languages
*/

/**
 * Copyright (c) 2021 SpringDevs (email: contact@springdevs.com). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */

// don't call the file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	const version = '1.3.0';

	/**
	 * Holds various class instances
	 *
	 * @var array
	 */
	private array $container = array();

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
		define( 'SUBSCRPT_VERSION', self::version );
		define( 'SUBSCRPT_FILE', __FILE__ );
		define( 'SUBSCRPT_PATH', dirname( SUBSCRPT_FILE ) );
		define( 'SUBSCRPT_INCLUDES', SUBSCRPT_PATH . '/includes' );
		define( 'SUBSCRPT_TEMPLATES', SUBSCRPT_PATH . '/templates/' );
		define( 'SUBSCRPT_URL', plugins_url( '', SUBSCRPT_FILE ) );
		define( 'SUBSCRPT_ASSETS', SUBSCRPT_URL . '/assets' );
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
	 *
	 * Nothing being called here yet.
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
		wp_clear_scheduled_hook( 'subscrpt_daily_cron' );
	}

	/**
	 * Include the required files
	 *
	 * @return void
	 */
	public function includes() {
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
			$this->container['ajax'] = new SpringDevs\Subscription\Ajax();
		}

		$this->container['api']    = new SpringDevs\Subscription\Api();
		$this->container['assets'] = new SpringDevs\Subscription\Assets();
	}

	/**
	 * Initialize plugin for localization
	 *
	 * @uses load_plugin_textdomain()
	 */
	public function localization_setup() {
		load_plugin_textdomain( 'sdevs_subscrpt', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
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
