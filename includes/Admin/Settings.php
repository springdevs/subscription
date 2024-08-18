<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Class Settings
 *
 * @package SpringDevs\Subscription\Admin
 */
class Settings {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register submenu on `Subscriptions` menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		$post_type_link = 'edit.php?post_type=subscrpt_order';
		add_submenu_page( $post_type_link, 'Subscription Settings', 'Settings', 'manage_options', 'subscrpt_settings', array( $this, 'settings_content' ) );
	}

	/**
	 * Register settings options.
	 **/
	public function register_settings() {
		register_setting( 'subscrpt_settings', 'subscrpt_renewal_process' );
		register_setting( 'subscrpt_settings', 'subscrpt_manual_renew_cart_notice' );
		register_setting( 'subscrpt_settings', 'subscrpt_active_role' );
		register_setting( 'subscrpt_settings', 'subscrpt_unactive_role' );
		register_setting( 'subscrpt_settings', 'subscrpt_stripe_auto_renew' );
		register_setting( 'subscrpt_settings', 'subscrpt_auto_renewal_toggle' );

		do_action( 'subscrpt_register_settings', 'subscrpt_settings' );
	}

	/**
	 * Settings HTML.
	 */
	public function settings_content() {
		include 'views/settings.php';
	}
}
