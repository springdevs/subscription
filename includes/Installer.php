<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Illuminate\Gateways\Paypal\PaypalDB;

/**
 * Class Installer
 *
 * @package SpringDevs\Subscription
 */
class Installer {

	/**
	 * Database schema version. Bump whenever a custom table changes.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.3.0';

	/**
	 * Run the installer
	 *
	 * @return void
	 */
	public function run() {
		$this->add_version();
		$this->register_schedules();
		$this->create_tables();
	}

	/**
	 * Create/upgrade custom tables when the stored schema version is outdated.
	 *
	 * Lets existing installs pick up new tables on update without a manual
	 * deactivate/reactivate. A single option read short-circuits once current.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'subscrpt_db_version' ) === self::DB_VERSION ) {
			return;
		}

		( new self() )->create_tables();
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

		update_option( 'subscrpt_manual_renew_cart_notice', 'Subscriptional product added to cart. Please complete the checkout to renew subscription.' );
	}

	/**
	 * Register cron events.
	 *
	 * @return void
	 */
	public function register_schedules() {
		if ( ! wp_next_scheduled( 'subscrpt_hourly_cron' ) ) {
			wp_schedule_event( strtotime( 'tomorrow midnight' ), 'hourly', 'subscrpt_hourly_cron' );
		}
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
		$this->create_stats_snapshot_table();
		$this->create_cancellation_feedback_table();
		$this->create_plan_group_table();
		$this->create_plan_table();
		$this->create_plan_relation_table();
		PaypalDB::maybe_create_tables();

		update_option( 'subscrpt_db_version', self::DB_VERSION );
	}

	/**
	 * Create the daily stats snapshot table.
	 *
	 * One row per calendar day holding the subscription status counts and the
	 * total monthly recurring revenue (MRR) at snapshot time. Powers MRR/
	 * subscription "over time" charts in reports and the recovery report.
	 *
	 * @return void
	 */
	public function create_stats_snapshot_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'subscrpt_stats_snapshot';

		$schema = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
                      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `snapshot_date` DATE NOT NULL,
                      `active_count` INT(11) NOT NULL DEFAULT 0,
                      `pending_count` INT(11) NOT NULL DEFAULT 0,
                      `on_hold_count` INT(11) NOT NULL DEFAULT 0,
                      `cancelled_count` INT(11) NOT NULL DEFAULT 0,
                      `expired_count` INT(11) NOT NULL DEFAULT 0,
                      `pe_cancelled_count` INT(11) NOT NULL DEFAULT 0,
                      `active_mrr` DECIMAL(14,2) NOT NULL DEFAULT 0,
                      `created_at` DATETIME NOT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `snapshot_date` (`snapshot_date`)
                    ) $charset_collate";

		dbDelta( $schema );
	}

	/**
	 * Create the cancellation survey table.
	 *
	 * One row per subscription — the latest cancellation survey (a re-cancel after
	 * reactivation overwrites the previous row rather than accumulating a log, so
	 * churn-by-reason reports never double-count a subscription). Stores the
	 * customer's stated reason (key + a label snapshot that survives later reason
	 * edits/deletes) and optional comment. Consumed for churn tracking — the recovery
	 * plugin joins its recovery log to this table on subscription_id.
	 *
	 * @return void
	 */
	public function create_cancellation_feedback_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'subscrpt_cancellation_feedback';

		$schema = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
                      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `subscription_id` BIGINT(20) UNSIGNED NOT NULL,
                      `customer_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                      `reason_key` VARCHAR(60) NOT NULL DEFAULT '',
                      `reason_label` VARCHAR(191) NOT NULL DEFAULT '',
                      `comment` TEXT NULL,
                      `created_at` DATETIME NOT NULL,
                      PRIMARY KEY (`id`),
                      KEY `subscription_id` (`subscription_id`),
                      KEY `created_at` (`created_at`)
                    ) $charset_collate";

		dbDelta( $schema );
	}

	/**
	 * Create the subscription plan group table.
	 *
	 * A plan group is a named, reusable subscription plan (e.g. "Premium
	 * Membership") attached to one or more products. This is the free base
	 * schema; Pro adds columns on top via versioned migrations keyed on
	 * `subscrpt_db_version`.
	 *
	 * type:         1 = Subscribe & Save, 2 = Recurring, 3 = Installment (free writes 2).
	 * product_type: 1 = specific products, 2 = taxonomy (free writes 1).
	 * data:         JSON (group-level settings).
	 *
	 * @return void
	 */
	public function create_plan_group_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'subscrpt_plan_group';

		$schema = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
                      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `type` TINYINT(2) NOT NULL DEFAULT 2,
                      `product_type` TINYINT(2) NOT NULL DEFAULT 1,
                      `title` VARCHAR(255) NOT NULL DEFAULT '',
                      `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                      `data` LONGTEXT NULL,
                      `created_at` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                      `updated_at` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                      PRIMARY KEY (`id`),
                      KEY `type` (`type`),
                      KEY `status` (`status`)
                    ) $charset_collate";

		dbDelta( $schema );
	}

	/**
	 * Create the subscription plan (term) table.
	 *
	 * A plan is one billing term inside a group (e.g. "every 1 month"). There is
	 * no price column — pricing lives per product on the relation.
	 *
	 * billing_interval: 1 = day, 2 = week, 3 = month, 4 = year.
	 * billing_length:   expiry in cycles, 0 = unlimited.
	 * price_mode:       snapshot (default) | live (free always snapshot).
	 *
	 * @return void
	 */
	public function create_plan_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'subscrpt_plan';

		$schema = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
                      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `plan_group_id` BIGINT(20) UNSIGNED NOT NULL,
                      `title` VARCHAR(255) NOT NULL DEFAULT '',
                      `type` TINYINT(2) NOT NULL DEFAULT 2,
                      `billing_frequency` INT(11) NOT NULL DEFAULT 1,
                      `billing_interval` TINYINT(2) NOT NULL DEFAULT 3,
                      `billing_length` INT(11) NOT NULL DEFAULT 0,
                      `signup_fee` LONGTEXT NULL,
                      `free_trial` VARCHAR(50) NOT NULL DEFAULT '',
                      `prepaid` TINYINT(1) NOT NULL DEFAULT 0,
                      `offer` LONGTEXT NULL,
                      `price_mode` VARCHAR(20) NOT NULL DEFAULT 'snapshot',
                      `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                      `data` LONGTEXT NULL,
                      `created_at` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                      `updated_at` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                      PRIMARY KEY (`id`),
                      KEY `plan_group_id` (`plan_group_id`),
                      KEY `status` (`status`)
                    ) $charset_collate";

		dbDelta( $schema );
	}

	/**
	 * Create the subscription plan relation table.
	 *
	 * The many-to-many join between a plan term and a product, carrying the
	 * per-product price in its `data` JSON.
	 *
	 * oid:     product id (type 1) or term id (type 2).
	 * vid:     variation id; 0 for simple products (free always 0).
	 * type:    1 = product, 2 = taxonomy.
	 * data:    JSON price override (regular_price, sale_price, discount_type,
	 *          discount_value).
	 * exclude: per-row toggle to hide one term for one product.
	 *
	 * @return void
	 */
	public function create_plan_relation_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'subscrpt_plan_relation';

		$schema = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
                      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `plan_id` BIGINT(20) UNSIGNED NOT NULL,
                      `oid` BIGINT(20) UNSIGNED NOT NULL,
                      `vid` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                      `type` TINYINT(2) NOT NULL DEFAULT 1,
                      `data` LONGTEXT NULL,
                      `exclude` TINYINT(1) NOT NULL DEFAULT 0,
                      `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                      PRIMARY KEY (`id`),
                      KEY `plan_id` (`plan_id`),
                      KEY `plan_lookup` (`plan_id`,`type`,`oid`,`vid`),
                      KEY `product_lookup` (`type`,`oid`,`vid`)
                    ) $charset_collate";

		dbDelta( $schema );
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
