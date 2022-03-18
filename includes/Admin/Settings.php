<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Class Settings
 *
 * @package SpringDevs\Subscription\Admin
 */
class Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function admin_menu() {
		$post_type_link = 'edit.php?post_type=subscrpt_order';
		add_submenu_page( $post_type_link, 'Subscription Settings', 'Settings', 'manage_options', 'subscrpt_settings', array( $this, 'settings_content' ) );
	}

	/**
	 * register settings options
	 **/
	public function register_settings() {
		register_setting( 'subscrpt_settings', 'subscrpt_active_role' );
		register_setting( 'subscrpt_settings', 'subscrpt_unactive_role' );
		do_action( 'subscrpt_register_settings', 'subscrpt_settings' );
	}

	public function settings_content() {
		include 'views/settings.php';
	}
}
