<?php

namespace SpringDevs\Subscription;

/**
 * Class Installer
 *
 * @package SpringDevs\Subscription
 */
class Installer {

	/**
	 * Run the installer
	 *
	 * @return void
	 */
	public function run() {
		$this->add_version();
		$this->create_tables();
	}

	/**
	 * Add time and version on DB
	 */
	public function add_version() {
		$installed = get_option( 'subscrpt_installed' );

		if ( ! $installed ) {
			update_option( 'subscrpt_installed', time() );
		}

		update_option( 'subscrpt_version', SUBSCRPT_VERSION );

		if ( ! wp_next_scheduled( 'subscrpt_daily_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'subscrpt_daily_cron' );
		}

		update_option( 'subscrpt_manual_renew_cart_notice', 'Subscriptional product added to cart. Please complete the checkout to renew subscription.' );
	}

	/**
	 * Create necessary database tables
	 *
	 * @return void
	 */
	public function create_tables() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$this->create_histories_table();
	}

	/**
	 * Create histories table
	 *
	 * @return void
	 */
	public function create_histories_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'subscrpt_order_relation';

		$schema = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
                      `id` INT(255) NOT NULL AUTO_INCREMENT,
                      `subscription_id` INT(100) NOT NULL,
                      `order_id` INT(100) NOT NULL,
                      `order_item_id` INT(100) NOT NULL,
                      `type` VARCHAR(50) NOT NULL,
                      PRIMARY KEY (`id`)
                    ) $charset_collate";

		dbDelta( $schema );
	}
}
