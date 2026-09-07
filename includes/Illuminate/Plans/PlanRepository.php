<?php
/**
 * Plan data-access layer and cached product → plans resolver.
 *
 * @package SpringDevs\Subscription\Illuminate\Plans
 */

namespace SpringDevs\Subscription\Illuminate\Plans;

/**
 * Plan data-access layer.
 *
 * Read/write repository over the three plan tables (subscrpt_plan_group,
 * subscrpt_plan, subscrpt_plan_relation). Provides the cached, batch-loaded
 * product → plans resolver that runs on the shop / product hot path; a
 * per-product N+1 here would degrade catalog pages, so reads are batched and
 * cached in the object cache.
 *
 * This is the free base. Pro subclasses/decorates it to add Pro-only columns
 * and semantics (variable/per-variation, taxonomy, live pricing); the base is
 * complete and functional standalone on a free-only install.
 *
 * @package SpringDevs\Subscription\Illuminate\Plans
 */
class PlanRepository {

	/**
	 * Object-cache group for resolved product → plans lookups.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'subscrpt_plans';

	/**
	 * Relation type: row targets a specific product / variation.
	 *
	 * @var int
	 */
	const REL_PRODUCT = 1;

	/**
	 * Relation type: row targets a taxonomy term (category / tag).
	 *
	 * @var int
	 */
	const REL_TAXONOMY = 2;

	/**
	 * UI plan-type string ⇄ stored integer.
	 *
	 * Recurring = 2, subscribe_save = 1, installments = 3 (see Installer).
	 *
	 * @var array<string,int>
	 */
	const TYPE_MAP = array(
		'subscribe_save' => 1,
		'recurring'      => 2,
		'installments'   => 3,
	);

	/**
	 * Convert a UI plan-type string to its stored integer.
	 *
	 * @param string $type Plan type key.
	 *
	 * @return int
	 */
	public static function type_to_int( $type ) {
		return self::TYPE_MAP[ $type ] ?? 2;
	}

	/**
	 * Convert a stored plan-type integer back to its UI string.
	 *
	 * @param int $type Stored type integer.
	 *
	 * @return string
	 */
	public static function type_to_string( $type ) {
		$flipped = array_flip( self::TYPE_MAP );
		return $flipped[ (int) $type ] ?? 'recurring';
	}

	/**
	 * Billing-interval integer → free-engine timing_option string.
	 *
	 * @var array<int,string>
	 */
	const INTERVAL_OPTION = array(
		1 => 'days',
		2 => 'weeks',
		3 => 'months',
		4 => 'years',
	);

	/**
	 * Map a billing-interval integer to the free engine's timing_option string.
	 *
	 * @param int $interval 1=day, 2=week, 3=month, 4=year.
	 *
	 * @return string days|weeks|months|years
	 */
	public static function interval_to_option( $interval ) {
		return self::INTERVAL_OPTION[ (int) $interval ] ?? 'months';
	}

	/**
	 * Plan group table name.
	 *
	 * @return string
	 */
	public static function group_table() {
		global $wpdb;
		return $wpdb->prefix . 'subscrpt_plan_group';
	}

	/**
	 * Plan (term) table name.
	 *
	 * @return string
	 */
	public static function plan_table() {
		global $wpdb;
		return $wpdb->prefix . 'subscrpt_plan';
	}

	/**
	 * Plan relation (m2m) table name.
	 *
	 * @return string
	 */
	public static function relation_table() {
		global $wpdb;
		return $wpdb->prefix . 'subscrpt_plan_relation';
	}

	/**
	 * Resolve every active plan term that applies to a single product.
	 *
	 * Result is cached in the object cache keyed by product id. Variation
	 * filtering is applied in PHP on the cached product-level set so a product
	 * with many variations still costs one query per product.
	 *
	 * @param int $product_id   Product (parent) id.
	 * @param int $variation_id Variation id, or 0 for simple products.
	 *
	 * @return array List of resolved plan rows (group + plan + relation merged).
	 */
	public static function resolve_for_product( $product_id, $variation_id = 0 ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return array();
		}

		$cache_key = 'product_' . $product_id;
		$resolved  = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $resolved ) {
			$batch    = self::resolve_for_products( array( $product_id ) );
			$resolved = $batch[ $product_id ] ?? array();
			wp_cache_set( $cache_key, $resolved, self::CACHE_GROUP );
		}

		if ( $variation_id ) {
			$variation_id = absint( $variation_id );
			$resolved     = array_values(
				array_filter(
					$resolved,
					static function ( $row ) use ( $variation_id ) {
						// vid 0 = applies to the parent / all variations.
						return 0 === (int) $row['vid'] || $variation_id === (int) $row['vid'];
					}
				)
			);
		}

		/**
		 * Filter the resolved plan rows for a product.
		 *
		 * Pro's extension point to inject taxonomy- and variation-resolved rows.
		 *
		 * @param array $resolved     Resolved plan rows.
		 * @param int   $product_id   Product id.
		 * @param int   $variation_id Variation id (0 for simple).
		 */
		return apply_filters( 'subscrpt_resolve_plans_for_product', $resolved, $product_id, $variation_id );
	}

	/**
	 * Batch-resolve active plan terms for many products in a single query.
	 *
	 * One JOIN across relation → plan → group, filtered to active rows and
	 * product-type relations. Used to prime the per-product cache and to avoid
	 * N+1 on shop loops.
	 *
	 * @param int[] $product_ids Product (parent) ids.
	 *
	 * @return array<int, array> Map of product id → list of resolved plan rows.
	 */
	public static function resolve_for_products( array $product_ids ) {
		global $wpdb;

		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );

		if ( empty( $product_ids ) ) {
			return array();
		}

		$map = array_fill_keys( $product_ids, array() );

		$relation_table = self::relation_table();
		$plan_table     = self::plan_table();
		$group_table    = self::group_table();

		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.id AS relation_id, r.plan_id, r.oid, r.vid, r.data AS relation_data, r.exclude,
						p.plan_group_id, p.title AS plan_title, p.type AS plan_type,
						p.billing_frequency, p.billing_interval, p.billing_length,
						p.signup_fee, p.free_trial, p.prepaid, p.offer, p.price_mode,
						p.data AS plan_data,
						g.title AS group_title, g.type AS group_type, g.product_type, g.data AS group_data
				FROM {$relation_table} r
				INNER JOIN {$plan_table} p ON p.id = r.plan_id
				INNER JOIN {$group_table} g ON g.id = p.plan_group_id
				WHERE r.type = %d
					AND r.exclude = 0
					AND r.status = 'active'
					AND p.status = 'active'
					AND g.status = 'active'
					AND r.oid IN ({$placeholders})
				ORDER BY g.id ASC, p.id ASC",
				array_merge( array( self::REL_PRODUCT ), $product_ids )
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( empty( $rows ) ) {
			return $map;
		}

		foreach ( $rows as $row ) {
			$oid = (int) $row['oid'];

			if ( ! isset( $map[ $oid ] ) ) {
				continue;
			}

			$row['relation_data'] = self::maybe_json( $row['relation_data'] );
			$row['signup_fee']    = self::maybe_json( $row['signup_fee'] );
			$row['offer']         = self::maybe_json( $row['offer'] );
			$row['plan_data']     = self::maybe_json( $row['plan_data'] );
			$row['group_data']    = self::maybe_json( $row['group_data'] );

			$map[ $oid ][] = $row;
		}

		return $map;
	}

	/**
	 * Decode a JSON column to an array, tolerating null / plain strings.
	 *
	 * @param string|null $value Raw column value.
	 *
	 * @return array
	 */
	protected static function maybe_json( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	// ---- Read side (admin / REST) ----

	/**
	 * Admin read: every plan term a product is attached to, any status.
	 *
	 * Unlike the storefront resolver this ignores status filters and the cache
	 * so the product editor reflects the live DB. Returns one row per relation
	 * with its group + term joined in.
	 *
	 * @param int $product_id   Product (parent) id.
	 * @param int $variation_id Variation id, or 0 to include all.
	 *
	 * @return array<int,array>
	 */
	public static function get_product_connections( $product_id, $variation_id = 0 ) {
		global $wpdb;

		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return array();
		}

		$relation_table = self::relation_table();
		$plan_table     = self::plan_table();
		$group_table    = self::group_table();

		$where  = 'r.type = %d AND r.oid = %d';
		$params = array( self::REL_PRODUCT, $product_id );

		if ( $variation_id ) {
			$where   .= ' AND r.vid = %d';
			$params[] = absint( $variation_id );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.id AS relation_id, r.plan_id, r.oid, r.vid, r.data AS relation_data, r.exclude, r.status AS relation_status,
						p.plan_group_id, p.title AS plan_title, p.type AS plan_type, p.billing_frequency, p.billing_interval, p.status AS plan_status,
						g.title AS group_title, g.type AS group_type, g.product_type, g.data AS group_data, g.status AS group_status
				FROM {$relation_table} r
				INNER JOIN {$plan_table} p ON p.id = r.plan_id
				INNER JOIN {$group_table} g ON g.id = p.plan_group_id
				WHERE {$where}
				ORDER BY g.id ASC, p.id ASC",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( empty( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['relation_data'] = self::maybe_json( $row['relation_data'] );
			$row['group_data']    = self::maybe_json( $row['group_data'] );
		}
		unset( $row );

		return $rows;
	}

	/**
	 * List plan groups, newest first.
	 *
	 * @return array<int,array> Raw group rows (data column decoded).
	 */
	public static function get_groups() {
		global $wpdb;

		$table = self::group_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
		// phpcs:enable

		return array_map( array( __CLASS__, 'decode_group_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Fetch a single plan group.
	 *
	 * @param int $group_id Group id.
	 *
	 * @return array|null
	 */
	public static function get_group( $group_id ) {
		global $wpdb;

		$table = self::group_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $group_id ) ), ARRAY_A );
		// phpcs:enable

		return $row ? self::decode_group_row( $row ) : null;
	}

	/**
	 * Fetch the plan terms for a group.
	 *
	 * @param int $group_id Group id.
	 *
	 * @return array<int,array>
	 */
	public static function get_plans( $group_id ) {
		global $wpdb;

		$table = self::plan_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE plan_group_id = %d ORDER BY id ASC", absint( $group_id ) ), ARRAY_A );
		// phpcs:enable

		return array_map( array( __CLASS__, 'decode_plan_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Fetch a single plan term.
	 *
	 * @param int $plan_id Plan id.
	 *
	 * @return array|null
	 */
	public static function get_plan( $plan_id ) {
		global $wpdb;

		$table = self::plan_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $plan_id ) ), ARRAY_A );
		// phpcs:enable

		return $row ? self::decode_plan_row( $row ) : null;
	}

	/**
	 * Fetch relations for a plan term.
	 *
	 * @param int $plan_id Plan id.
	 *
	 * @return array<int,array>
	 */
	public static function get_relations( $plan_id ) {
		global $wpdb;

		$table = self::relation_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE plan_id = %d ORDER BY id ASC", absint( $plan_id ) ), ARRAY_A );
		// phpcs:enable

		return array_map( array( __CLASS__, 'decode_relation_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Fetch a single relation row.
	 *
	 * @param int $relation_id Relation id.
	 *
	 * @return array|null
	 */
	public static function get_relation( $relation_id ) {
		global $wpdb;

		$table = self::relation_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $relation_id ) ), ARRAY_A );
		// phpcs:enable

		return $row ? self::decode_relation_row( $row ) : null;
	}

	/**
	 * Assemble a group with its plans and relations as one nested structure.
	 *
	 * @param int $group_id Group id.
	 *
	 * @return array|null
	 */
	public static function get_group_tree( $group_id ) {
		$group = self::get_group( $group_id );

		if ( ! $group ) {
			return null;
		}

		$plans = self::get_plans( $group_id );

		foreach ( $plans as &$plan ) {
			$plan['relations'] = self::get_relations( $plan['id'] );
		}
		unset( $plan );

		$group['plans'] = $plans;

		return $group;
	}

	// ---- Write side (REST) ----

	/**
	 * Insert a plan group.
	 *
	 * @param array $data Column values (data is JSON-encoded if array).
	 *
	 * @return int|false New group id, or false on failure.
	 */
	public static function insert_group( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$row = self::prepare_group_columns( $data );

		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( self::group_table(), $row );

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a plan group.
	 *
	 * @param int   $group_id Group id.
	 * @param array $data     Column values to update.
	 *
	 * @return bool
	 */
	public static function update_group( $group_id, array $data ) {
		global $wpdb;

		$row               = self::prepare_group_columns( $data );
		$row['updated_at'] = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->update( self::group_table(), $row, array( 'id' => absint( $group_id ) ) );

		self::flush_group_products( $group_id );

		return false !== $ok;
	}

	/**
	 * Delete a plan group and cascade its plans + relations.
	 *
	 * @param int $group_id Group id.
	 *
	 * @return bool
	 */
	public static function delete_group( $group_id ) {
		global $wpdb;

		$group_id = absint( $group_id );

		self::flush_group_products( $group_id );

		$plans = self::get_plans( $group_id );
		foreach ( $plans as $plan ) {
			self::delete_plan( $plan['id'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->delete( self::group_table(), array( 'id' => $group_id ) );

		return false !== $ok;
	}

	/**
	 * Insert a plan term.
	 *
	 * @param array $data Column values.
	 *
	 * @return int|false
	 */
	public static function insert_plan( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$row = self::prepare_plan_columns( $data );

		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( self::plan_table(), $row );

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a plan term.
	 *
	 * @param int   $plan_id Plan id.
	 * @param array $data    Column values.
	 *
	 * @return bool
	 */
	public static function update_plan( $plan_id, array $data ) {
		global $wpdb;

		$row               = self::prepare_plan_columns( $data );
		$row['updated_at'] = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->update( self::plan_table(), $row, array( 'id' => absint( $plan_id ) ) );

		self::flush_plan_products( $plan_id );

		return false !== $ok;
	}

	/**
	 * Delete a plan term and cascade its relations.
	 *
	 * @param int $plan_id Plan id.
	 *
	 * @return bool
	 */
	public static function delete_plan( $plan_id ) {
		global $wpdb;

		$plan_id = absint( $plan_id );

		self::flush_plan_products( $plan_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::relation_table(), array( 'plan_id' => $plan_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->delete( self::plan_table(), array( 'id' => $plan_id ) );

		return false !== $ok;
	}

	/**
	 * Find an existing relation by its natural key (plan term × product/variation).
	 *
	 * @param int $plan_id Plan term id.
	 * @param int $oid     Object (product) id.
	 * @param int $vid     Variation id (0 for none / all).
	 * @param int $type    Relation type (defaults to product).
	 *
	 * @return int Relation id, or 0 if none.
	 */
	public static function find_relation( $plan_id, $oid, $vid = 0, $type = self::REL_PRODUCT ) {
		global $wpdb;

		$table = self::relation_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE plan_id = %d AND oid = %d AND vid = %d AND type = %d LIMIT 1",
				absint( $plan_id ),
				absint( $oid ),
				absint( $vid ),
				(int) $type
			)
		);
		// phpcs:enable

		return $id ? (int) $id : 0;
	}

	/**
	 * Insert a relation (attach a product / variation / term to a plan).
	 *
	 * Idempotent on the natural key (plan term × product × variation): a repeat
	 * connect updates the existing relation rather than creating a duplicate, so
	 * a product never shows the same duration twice.
	 *
	 * @param array $data Column values.
	 *
	 * @return int|false
	 */
	public static function insert_relation( array $data ) {
		global $wpdb;

		$existing = self::find_relation(
			isset( $data['plan_id'] ) ? (int) $data['plan_id'] : 0,
			isset( $data['oid'] ) ? (int) $data['oid'] : 0,
			isset( $data['vid'] ) ? (int) $data['vid'] : 0,
			isset( $data['type'] ) ? (int) $data['type'] : self::REL_PRODUCT
		);

		if ( $existing ) {
			self::update_relation( $existing, $data );
			return $existing;
		}

		$row = self::prepare_relation_columns( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( self::relation_table(), $row );

		if ( ! $ok ) {
			return false;
		}

		self::flush_cache( $row['oid'] ?? 0 );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Backfill relations for a newly added term so every product already in the
	 * group is linked to the new duration (inheriting the price it uses on a
	 * sibling term). Keeps the Products tab in sync when a term is added later.
	 *
	 * @param int $group_id    Plan group id.
	 * @param int $new_plan_id Newly created term id.
	 *
	 * @return int Number of relations created.
	 */
	public static function backfill_term_relations( $group_id, $new_plan_id ) {
		$group_id    = absint( $group_id );
		$new_plan_id = absint( $new_plan_id );

		if ( ! $group_id || ! $new_plan_id ) {
			return 0;
		}

		// One representative relation per (oid, vid, type) across the other terms.
		$seen = array();

		foreach ( self::get_plans( $group_id ) as $term ) {
			if ( (int) $term['id'] === $new_plan_id ) {
				continue;
			}

			foreach ( self::get_relations( $term['id'] ) as $rel ) {
				$key = $rel['oid'] . ':' . $rel['vid'] . ':' . $rel['type'];

				if ( ! isset( $seen[ $key ] ) ) {
					$seen[ $key ] = $rel;
				}
			}
		}

		$created = 0;

		foreach ( $seen as $rel ) {
			// Create-only: never touch an existing relation, so a re-run can't
			// overwrite a per-product price the merchant already set.
			if ( self::find_relation( $new_plan_id, (int) $rel['oid'], (int) $rel['vid'], (int) $rel['type'] ) ) {
				continue;
			}

			$ok = self::insert_relation(
				array(
					'plan_id' => $new_plan_id,
					'oid'     => (int) $rel['oid'],
					'vid'     => (int) $rel['vid'],
					'type'    => (int) $rel['type'],
					'status'  => 'active',
					'exclude' => 0,
					'data'    => is_array( $rel['data'] ) ? $rel['data'] : array(),
				)
			);

			if ( $ok ) {
				++$created;
			}
		}

		return $created;
	}

	/**
	 * Update a relation.
	 *
	 * @param int   $relation_id Relation id.
	 * @param array $data        Column values.
	 *
	 * @return bool
	 */
	public static function update_relation( $relation_id, array $data ) {
		global $wpdb;

		$existing = self::get_relation( $relation_id );

		// Merge a partial `data` payload into the existing JSON so callers can
		// update a few fields (e.g. price, one_time) without resending all of it.
		if ( array_key_exists( 'data', $data ) && $existing && is_array( $existing['data'] ) ) {
			$data['data'] = array_merge( $existing['data'], (array) $data['data'] );
		}

		$row = self::prepare_relation_columns( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->update( self::relation_table(), $row, array( 'id' => absint( $relation_id ) ) );

		if ( $existing ) {
			self::flush_cache( $existing['oid'] );
		}
		if ( isset( $row['oid'] ) ) {
			self::flush_cache( $row['oid'] );
		}

		return false !== $ok;
	}

	/**
	 * Delete a relation.
	 *
	 * @param int $relation_id Relation id.
	 *
	 * @return bool
	 */
	public static function delete_relation( $relation_id ) {
		global $wpdb;

		$existing = self::get_relation( $relation_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->delete( self::relation_table(), array( 'id' => absint( $relation_id ) ) );

		if ( $existing ) {
			self::flush_cache( $existing['oid'] );
		}

		return false !== $ok;
	}

	// ---- Column / row helpers ----

	/**
	 * Whitelist + encode group columns for write.
	 *
	 * @param array $data Raw input.
	 *
	 * @return array
	 */
	protected static function prepare_group_columns( array $data ) {
		$row = array();

		if ( isset( $data['type'] ) ) {
			$row['type'] = is_numeric( $data['type'] ) ? (int) $data['type'] : self::type_to_int( $data['type'] );
		}
		if ( isset( $data['product_type'] ) ) {
			$row['product_type'] = (int) $data['product_type'];
		}
		if ( isset( $data['title'] ) ) {
			$row['title'] = sanitize_text_field( $data['title'] );
		}
		if ( isset( $data['status'] ) ) {
			$row['status'] = sanitize_key( $data['status'] );
		}
		if ( array_key_exists( 'data', $data ) ) {
			$row['data'] = wp_json_encode( $data['data'] );
		}

		return $row;
	}

	/**
	 * Whitelist + encode plan columns for write.
	 *
	 * @param array $data Raw input.
	 *
	 * @return array
	 */
	protected static function prepare_plan_columns( array $data ) {
		$row = array();

		if ( isset( $data['plan_group_id'] ) ) {
			$row['plan_group_id'] = absint( $data['plan_group_id'] );
		}
		if ( isset( $data['title'] ) ) {
			$row['title'] = sanitize_text_field( $data['title'] );
		}
		if ( isset( $data['type'] ) ) {
			$row['type'] = is_numeric( $data['type'] ) ? (int) $data['type'] : self::type_to_int( $data['type'] );
		}
		if ( isset( $data['billing_frequency'] ) ) {
			$row['billing_frequency'] = (int) $data['billing_frequency'];
		}
		if ( isset( $data['billing_interval'] ) ) {
			$row['billing_interval'] = (int) $data['billing_interval'];
		}
		if ( isset( $data['billing_length'] ) ) {
			$row['billing_length'] = (int) $data['billing_length'];
		}
		if ( array_key_exists( 'signup_fee', $data ) ) {
			$row['signup_fee'] = wp_json_encode( $data['signup_fee'] );
		}
		if ( isset( $data['free_trial'] ) ) {
			$row['free_trial'] = sanitize_text_field( $data['free_trial'] );
		}
		if ( isset( $data['prepaid'] ) ) {
			$row['prepaid'] = (int) (bool) $data['prepaid'];
		}
		if ( array_key_exists( 'offer', $data ) ) {
			$row['offer'] = wp_json_encode( $data['offer'] );
		}
		if ( isset( $data['price_mode'] ) ) {
			$row['price_mode'] = sanitize_key( $data['price_mode'] );
		}
		if ( isset( $data['status'] ) ) {
			$row['status'] = sanitize_key( $data['status'] );
		}
		if ( array_key_exists( 'data', $data ) ) {
			$row['data'] = wp_json_encode( $data['data'] );
		}

		return $row;
	}

	/**
	 * Whitelist + encode relation columns for write.
	 *
	 * @param array $data Raw input.
	 *
	 * @return array
	 */
	protected static function prepare_relation_columns( array $data ) {
		$row = array();

		if ( isset( $data['plan_id'] ) ) {
			$row['plan_id'] = absint( $data['plan_id'] );
		}
		if ( isset( $data['oid'] ) ) {
			$row['oid'] = absint( $data['oid'] );
		}
		if ( isset( $data['vid'] ) ) {
			$row['vid'] = absint( $data['vid'] );
		}
		if ( isset( $data['type'] ) ) {
			$row['type'] = (int) $data['type'];
		}
		if ( array_key_exists( 'data', $data ) ) {
			$row['data'] = wp_json_encode( $data['data'] );
		}
		if ( isset( $data['exclude'] ) ) {
			$row['exclude'] = (int) (bool) $data['exclude'];
		}
		if ( isset( $data['status'] ) ) {
			$row['status'] = sanitize_key( $data['status'] );
		}

		return $row;
	}

	/**
	 * Decode a raw group row's JSON column.
	 *
	 * @param array $row Raw row.
	 *
	 * @return array
	 */
	protected static function decode_group_row( $row ) {
		$row['id']           = (int) $row['id'];
		$row['type']         = (int) $row['type'];
		$row['product_type'] = (int) $row['product_type'];
		$row['data']         = self::maybe_json( $row['data'] );
		$row['type_key']     = self::type_to_string( $row['type'] );

		return $row;
	}

	/**
	 * Decode a raw plan row's JSON columns.
	 *
	 * @param array $row Raw row.
	 *
	 * @return array
	 */
	protected static function decode_plan_row( $row ) {
		$row['id']            = (int) $row['id'];
		$row['plan_group_id'] = (int) $row['plan_group_id'];
		$row['type']          = (int) $row['type'];
		$row['signup_fee']    = self::maybe_json( $row['signup_fee'] );
		$row['offer']         = self::maybe_json( $row['offer'] );
		$row['data']          = self::maybe_json( $row['data'] );

		return $row;
	}

	/**
	 * Decode a raw relation row's JSON column.
	 *
	 * @param array $row Raw row.
	 *
	 * @return array
	 */
	protected static function decode_relation_row( $row ) {
		$row['id']      = (int) $row['id'];
		$row['plan_id'] = (int) $row['plan_id'];
		$row['oid']     = (int) $row['oid'];
		$row['vid']     = (int) $row['vid'];
		$row['type']    = (int) $row['type'];
		$row['exclude'] = (int) $row['exclude'];
		$row['data']    = self::maybe_json( $row['data'] );

		return $row;
	}

	/**
	 * Flush the resolver cache for every product attached to a plan.
	 *
	 * @param int $plan_id Plan id.
	 *
	 * @return void
	 */
	protected static function flush_plan_products( $plan_id ) {
		foreach ( self::get_relations( $plan_id ) as $relation ) {
			self::flush_cache( $relation['oid'] );
		}
	}

	/**
	 * Flush the resolver cache for every product attached to any plan in a group.
	 *
	 * @param int $group_id Group id.
	 *
	 * @return void
	 */
	protected static function flush_group_products( $group_id ) {
		foreach ( self::get_plans( $group_id ) as $plan ) {
			self::flush_plan_products( $plan['id'] );
		}
	}

	/**
	 * Flush the resolver cache for one product, or the whole group.
	 *
	 * Call after any write to a plan / relation so the hot-path cache cannot
	 * serve stale terms. With no product id, bumps the whole cache group.
	 *
	 * @param int $product_id Product id, or 0 to flush everything.
	 *
	 * @return void
	 */
	public static function flush_cache( $product_id = 0 ) {
		$product_id = absint( $product_id );

		if ( $product_id ) {
			wp_cache_delete( 'product_' . $product_id, self::CACHE_GROUP );
			return;
		}

		// No targeted product: drop the whole group when the backend supports it.
		if ( function_exists( 'wp_cache_flush_group' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		}
	}
}
