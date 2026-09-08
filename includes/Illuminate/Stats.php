<?php

namespace SpringDevs\Subscription\Illuminate;

use SpringDevs\Subscription\Installer;

/**
 * Collects subscription statistics over time.
 *
 * Computes monthly recurring revenue (MRR) and status counts, and writes one
 * snapshot per calendar day into {prefix}subscrpt_stats_snapshot so reports
 * (free, pro) and the recovery add-on can chart MRR/subscriptions over time.
 *
 * The snapshot runs at most once per day, triggered by the hourly cron and,
 * as a low-traffic-site safety net, on admin page loads. The heavy aggregation
 * therefore happens only once daily regardless of trigger.
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Stats {

	/**
	 * Option key holding the last snapshot date (Y-m-d, UTC).
	 *
	 * @var string
	 */
	const LAST_SNAPSHOT_OPTION = 'subscrpt_stats_last_snapshot';

	/**
	 * Months represented by one unit of each billing period (for MRR).
	 *
	 * @var array<string,float>
	 */
	const MONTHS_PER_UNIT = array(
		'day'   => 0.03333333333,
		'week'  => 0.23333333333,
		'month' => 1.0,
		'year'  => 12.0,
	);

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		// Self-heal the snapshot table for installs that updated without reactivating.
		Installer::maybe_upgrade();

		add_action( 'subscrpt_hourly_cron', array( $this, 'maybe_take_daily_snapshot' ) );
		add_action( 'admin_init', array( $this, 'maybe_take_daily_snapshot' ) );
	}

	/**
	 * Normalize a recurring amount to a monthly figure (MRR).
	 *
	 * mrr = amount / (interval * months_per_unit[period]). Unknown periods are
	 * treated as monthly; a zero cycle returns 0 to avoid division by zero.
	 *
	 * @param float  $amount   Raw recurring price.
	 * @param string $period   Billing period: day|week|month|year.
	 * @param int    $interval Billing interval (the "every N").
	 * @return float Monthly-normalized amount.
	 */
	public static function normalize_mrr( $amount, $period, $interval ) {
		$months_per_unit = isset( self::MONTHS_PER_UNIT[ $period ] ) ? self::MONTHS_PER_UNIT[ $period ] : 1.0;
		$cycle_in_months = $interval * $months_per_unit;

		if ( $cycle_in_months <= 0 ) {
			return 0.0;
		}

		return round( $amount / $cycle_in_months, 2 );
	}

	/**
	 * Total monthly recurring revenue across all active subscriptions.
	 *
	 * @return float
	 */
	public static function calculate_active_mrr() {
		$ids = Helper::get_subscriptions(
			array(
				'status'         => 'active',
				'user_id'        => -1,
				'return'         => 'ids',
				'posts_per_page' => -1,
			)
		);

		$total = 0.0;

		foreach ( (array) $ids as $id ) {
			$amount = (float) get_post_meta( $id, '_subscrpt_price', true );

			$product_id   = (int) get_post_meta( $id, '_subscrpt_product_id', true );
			$variation_id = (int) get_post_meta( $id, '_subscrpt_variation_id', true );
			$fallback_id  = $variation_id ? $variation_id : $product_id;

			$period = get_post_meta( $id, '_subscrpt_timing_option', true );
			$period = $period ? (string) $period : get_post_meta( $fallback_id, '_subscrpt_timing_option', true );
			$period = $period ? $period : 'month';

			$interval = get_post_meta( $id, '_subscrpt_timing_per', true );
			$interval = ! empty( $interval ) ? $interval : get_post_meta( $fallback_id, '_subscrpt_timing_per', true );
			$interval = max( 1, (int) $interval );

			$total += self::normalize_mrr( $amount, $period, $interval );
		}

		return round( $total, 2 );
	}

	/**
	 * Count subscriptions per status.
	 *
	 * @return array<string,int> Keyed by status (active, pending, ...).
	 */
	public static function get_status_counts() {
		$counts   = wp_count_posts( 'subscrpt_order' );
		$statuses = array( 'active', 'pending', 'on_hold', 'cancelled', 'expired', 'completed', 'pe_cancelled' );
		$out      = array();

		foreach ( $statuses as $status ) {
			$out[ $status ] = isset( $counts->$status ) ? (int) $counts->$status : 0;
		}

		return $out;
	}

	/**
	 * Count active subscriptions whose next payment falls inside a window.
	 *
	 * `_subscrpt_next_date` holds a Unix timestamp, so this compares against
	 * one rather than parsing a date string.
	 *
	 * @param int $days Number of days ahead to look.
	 * @return int
	 */
	public static function count_renewals_due_within( int $days = 7 ): int {
		global $wpdb;

		$now   = time();
		$until = $now + ( max( 1, $days ) * DAY_IN_SECONDS );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1)
				 FROM {$wpdb->postmeta} m
				 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				 WHERE m.meta_key = '_subscrpt_next_date'
				   AND p.post_type = 'subscrpt_order'
				   AND p.post_status = 'active'
				   AND CAST( m.meta_value AS UNSIGNED ) BETWEEN %d AND %d",
				$now,
				$until
			)
		);
	}

	/**
	 * Count renewal orders that failed recently.
	 *
	 * Renewal orders are identified from the subscription relation table rather
	 * than from order meta, then looked up through `wc_get_orders()` — reading
	 * the posts table directly would return nothing on a store using HPOS.
	 *
	 * @param int $hours How far back to look.
	 * @return int
	 */
	public static function count_failed_renewals_since( int $hours = 24 ): int {
		global $wpdb;

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$since = time() - ( max( 1, $hours ) * HOUR_IN_SECONDS );

		/*
		 * Ask WooCommerce first, not the relation table.
		 *
		 * The relation table holds every renewal order ever created, so starting
		 * there means pulling an unbounded id list out of a store's whole
		 * history and handing it to wc_get_orders(). Starting from the orders
		 * side bounds the set by the time window before anything else runs —
		 * usually a handful of rows — and only those ids reach the second query.
		 *
		 * wc_get_orders() rather than SQL against posts, because a store on HPOS
		 * keeps orders in their own tables and a posts query returns nothing.
		 */
		$failed = wc_get_orders(
			array(
				'status'        => array( 'failed' ),
				'date_modified' => '>' . $since,
				'limit'         => -1,
				'return'        => 'ids',
			)
		);

		$failed = array_filter( array_map( 'intval', (array) $failed ) );

		if ( empty( $failed ) ) {
			return 0;
		}

		$table        = $wpdb->prefix . 'subscrpt_order_relation';
		$placeholders = implode( ',', array_fill( 0, count( $failed ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the prefix; ids are placeheld below.
		$sql = "SELECT COUNT( DISTINCT order_id ) FROM {$table} WHERE type = 'renew' AND order_id IN ( {$placeholders} )";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared immediately above.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $failed ) );
	}

	/**
	 * Count subscriptions created within the last N days.
	 *
	 * @param int $days Number of days back to look.
	 * @return int
	 */
	public static function count_new_since( int $days = 7 ): int {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $days ) * DAY_IN_SECONDS ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM {$wpdb->posts}
				 WHERE post_type = 'subscrpt_order'
				   AND post_status NOT IN ( 'trash', 'auto-draft' )
				   AND post_date_gmt >= %s",
				$since
			)
		);
	}

	/**
	 * Take today's snapshot unless one already exists for today.
	 *
	 * @return void
	 */
	public function maybe_take_daily_snapshot() {
		$today = gmdate( 'Y-m-d' );

		if ( get_option( self::LAST_SNAPSHOT_OPTION ) === $today ) {
			return;
		}

		$this->take_snapshot( $today );
		update_option( self::LAST_SNAPSHOT_OPTION, $today );
	}

	/**
	 * Compute and persist a snapshot row for the given date.
	 *
	 * @param string $date Snapshot date (Y-m-d, UTC). Defaults to today.
	 * @return void
	 */
	public function take_snapshot( $date = '' ) {
		global $wpdb;

		$date   = $date ? $date : gmdate( 'Y-m-d' );
		$counts = self::get_status_counts();

		$wpdb->replace(
			$wpdb->prefix . 'subscrpt_stats_snapshot',
			array(
				'snapshot_date'      => $date,
				'active_count'       => $counts['active'],
				'pending_count'      => $counts['pending'],
				'on_hold_count'      => $counts['on_hold'],
				'cancelled_count'    => $counts['cancelled'],
				'expired_count'      => $counts['expired'],
				'pe_cancelled_count' => $counts['pe_cancelled'],
				'active_mrr'         => self::calculate_active_mrr(),
				'created_at'         => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%s' )
		);
	}

	/**
	 * Read snapshot rows within a date range (inclusive), oldest first.
	 *
	 * @param string $from Start date (Y-m-d). Empty for no lower bound.
	 * @param string $to   End date (Y-m-d). Empty for no upper bound.
	 * @return array<int,object> Snapshot rows.
	 */
	public static function get_snapshots( $from = '', $to = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'subscrpt_stats_snapshot';
		$where = '1=1';
		$args  = array();

		if ( $from ) {
			$where .= ' AND snapshot_date >= %s';
			$args[] = $from;
		}

		if ( $to ) {
			$where .= ' AND snapshot_date <= %s';
			$args[] = $to;
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY snapshot_date ASC";

		if ( $args ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $args );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}
}
