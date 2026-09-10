<?php
/**
 * Plan presenter - maps PlanRepository rows to the admin template shape.
 *
 * Builds the array contract the Plans admin templates render (name / type /
 * terms / products / rows …) from the real DB rows, so the templates stay
 * dumb view files.
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * Plan presenter.
 */
class PlanPresenter {

	/**
	 * Build every plan group in the template contract, keyed by group id.
	 *
	 * @return array
	 */
	public static function all() {
		$plans = array();

		foreach ( PlanRepository::get_groups() as $group ) {
			$plans[ $group['id'] ] = self::group( $group['id'] );
		}

		return array_filter( $plans );
	}

	/**
	 * Build a single plan group tree in the template contract.
	 *
	 * @param int $group_id Group id.
	 *
	 * @return array|null
	 */
	public static function group( $group_id ) {
		$tree = PlanRepository::get_group_tree( $group_id );

		if ( ! $tree ) {
			return null;
		}

		$type_key = $tree['type_key'];
		$terms    = array();

		foreach ( $tree['plans'] as $plan ) {
			$terms[] = array(
				'id'        => $plan['id'],
				'name'      => $plan['title'],
				'breakdown' => self::breakdown( $plan ),
				'status'    => $plan['status'],
				'chips'     => self::term_chips( $plan ),
			);
		}

		return array(
			'id'       => $tree['id'],
			'name'     => $tree['title'],
			'type'     => $type_key,
			'status'   => $tree['status'],
			'created'  => self::ago( $tree['created_at'] ),
			'edited'   => self::ago( $tree['updated_at'] ),
			'terms'    => $terms,
			'products' => self::products( $tree, $type_key ),
		);
	}

	/**
	 * Group relations by product into the products[] contract (read-only).
	 *
	 * @param array  $tree     Group tree (plans → relations).
	 * @param string $type_key Plan type key.
	 *
	 * @return array
	 */
	protected static function products( $tree, $type_key ) {
		$by_product = array();

		// First pass: register each connected product + index its relations by
		// [vid][plan_id] (vid 0 = the product-level / seed connection).
		foreach ( $tree['plans'] as $plan ) {
			foreach ( $plan['relations'] as $relation ) {
				if ( PlanRepository::REL_PRODUCT !== (int) $relation['type'] ) {
					continue;
				}

				$oid     = (int) $relation['oid'];
				$vid     = (int) $relation['vid'];
				$plan_id = (int) $plan['id'];

				if ( ! isset( $by_product[ $oid ] ) ) {
					$product            = function_exists( 'wc_get_product' ) ? wc_get_product( $oid ) : null;
					$is_variable        = $product ? $product->is_type( 'variable' ) : false;
					$image_id           = $product ? $product->get_image_id() : 0;
					$by_product[ $oid ] = array(
						'id'          => $oid,
						'name'        => $product ? $product->get_name() : sprintf( '#%d', $oid ),
						'image'       => $image_id ? wp_get_attachment_image_url( $image_id, array( 88, 88 ) ) : '',
						'is_variable' => $is_variable,
						'base_price'  => ( $product && ! $is_variable ) ? self::money( (float) $product->get_price() ) : '',
						'one_time_on' => $product ? ( 'yes' === $product->get_meta( '_subscrpt_one_time_enabled' ) ) : false,
						'ot_regular'  => ( $product && ! $is_variable ) ? (string) $product->get_regular_price() : '',
						'ot_offer'    => ( $product && ! $is_variable ) ? (string) $product->get_sale_price() : '',
						'edit_url'    => get_edit_post_link( $oid, 'raw' ),
						'view_url'    => get_permalink( $oid ),
						'rows'        => array(),
						'variations'  => array(),
						'_rel'        => array(),
					);
				}

				$by_product[ $oid ]['_rel'][ $vid ][ $plan_id ] = $relation;
			}
		}

		// Second pass: build the price cards.
		foreach ( $by_product as $oid => &$entry ) {
			$rel = $entry['_rel'];

			if ( $entry['is_variable'] ) {
				// One card per real variation. Each (variation × plan) price uses
				// the variation's own relation, falling back to the product-level
				// (vid 0) seed; editing seeds a per-variation relation.
				$product  = function_exists( 'wc_get_product' ) ? wc_get_product( $oid ) : null;
				$children = $product ? $product->get_children() : array();

				foreach ( $children as $cvid ) {
					$cvid = (int) $cvid;
					$rows = array();
					foreach ( $tree['plans'] as $plan ) {
						$plan_id  = (int) $plan['id'];
						$vid_rel  = isset( $rel[ $cvid ][ $plan_id ] ) ? $rel[ $cvid ][ $plan_id ] : null;
						$seed_rel = isset( $rel[0][ $plan_id ] ) ? $rel[0][ $plan_id ] : null;
						$use      = $vid_rel ? $vid_rel : $seed_rel;
						if ( ! $use ) {
							continue;
						}
						// relation_id 0 → not saved for this variation yet (seeded).
						$rows[] = self::row( $plan, $use, $type_key, $vid_rel ? (int) $vid_rel['id'] : 0, $cvid );
					}
					if ( empty( $rows ) ) {
						continue;
					}
					$variation             = function_exists( 'wc_get_product' ) ? wc_get_product( $cvid ) : null;
					$entry['variations'][] = array(
						'vid'         => $cvid,
						'name'        => self::variation_name( $variation, $entry['name'] ),
						'base_price'  => $variation ? self::money( (float) $variation->get_price() ) : '-',
						'one_time_on' => $variation ? ( 'yes' === $variation->get_meta( '_subscrpt_one_time_enabled' ) ) : false,
						'ot_regular'  => $variation ? (string) $variation->get_regular_price() : '',
						'ot_offer'    => $variation ? (string) $variation->get_sale_price() : '',
						'rows'        => $rows,
					);
				}
			} else {
				// Simple product: one card from the vid 0 relations.
				foreach ( $tree['plans'] as $plan ) {
					$plan_id = (int) $plan['id'];
					if ( ! isset( $rel[0][ $plan_id ] ) ) {
						continue;
					}
					$entry['rows'][] = self::row( $plan, $rel[0][ $plan_id ], $type_key, (int) $rel[0][ $plan_id ]['id'], 0 );
				}
			}

			unset( $entry['_rel'] );
		}
		unset( $entry );

		$products = array_values( $by_product );

		// Sort by product ID (newest first).
		usort(
			$products,
			function ( $a, $b ) {
				return (int) $b['id'] - (int) $a['id'];
			}
		);

		return $products;
	}

	/**
	 * Display name for a variation: its attribute values (e.g. "Large, Red"),
	 * falling back to the WC formatted name or the parent name.
	 *
	 * @param \WC_Product|null $variation   Variation product.
	 * @param string           $parent_name Parent product name.
	 *
	 * @return string
	 */
	protected static function variation_name( $variation, $parent_name ) {
		if ( ! $variation ) {
			return $parent_name;
		}

		$attributes = array_filter( array_values( $variation->get_variation_attributes() ) );

		return $attributes ? implode( ', ', $attributes ) : $variation->get_name();
	}

	/**
	 * Build one read-only price row for a (plan term × product) relation.
	 *
	 * @param array    $plan        Plan term row.
	 * @param array    $relation    Relation row (or the seed relation for a
	 *                              not-yet-saved variation price).
	 * @param string   $type_key    Plan type key.
	 * @param int|null $relation_id Relation id to save against; 0 = new (seeded),
	 *                              null = use the relation's own id.
	 * @param int      $vid         Variation id this row targets (0 = product).
	 *
	 * @return array
	 */
	protected static function row( $plan, $relation, $type_key, $relation_id = null, $vid = 0 ) {
		$data = $relation['data'];

		$regular        = isset( $data['regular_price'] ) ? (string) $data['regular_price'] : '';
		$selling        = isset( $data['sale_price'] ) ? (string) $data['sale_price'] : '';
		$discount_type  = $data['discount_type'] ?? 'percentage';
		$discount_value = isset( $data['discount_value'] ) ? (string) $data['discount_value'] : '0';

		// A real offer exists only when the effective price is below the regular
		// (an explicit sale price or a discount). Without one, offer_price() equals
		// the regular price, so the display must not repeat it in the offer column.
		$offer_num = self::offer_price( $regular, $selling, $discount_type, $discount_value );
		$has_offer = '' !== $regular && $offer_num < (float) $regular;

		return array(
			'relation_id' => null === $relation_id ? (int) $relation['id'] : (int) $relation_id,
			'plan_id'     => (int) $plan['id'],
			'vid'         => (int) $vid,
			'term'        => $plan['title'],
			'regular'     => '' !== $regular ? self::money( (float) $regular ) : '-',
			'offer'       => self::money( $offer_num ),
			'has_offer'   => $has_offer,
			'regular_raw' => $regular,
			'offer_raw'   => $selling,
			'exclude'     => ! empty( $relation['exclude'] ),
		);
	}

	/**
	 * Compute the offer price: (sale ?? regular) minus the discount.
	 *
	 * @param string $regular        Regular price.
	 * @param string $selling        Sale price (may be empty).
	 * @param string $discount_type  percentage|fixed.
	 * @param string $discount_value Discount amount.
	 *
	 * @return float
	 */
	public static function offer_price( $regular, $selling, $discount_type, $discount_value ) {
		$base     = '' !== $selling ? (float) $selling : (float) $regular;
		$discount = (float) $discount_value;

		if ( 'percentage' === $discount_type ) {
			$base -= $base * ( $discount / 100 );
		} else {
			$base -= $discount;
		}

		return max( 0, $base );
	}

	/**
	 * The term meta parts (billing breakdown + chips) for display under a plan
	 * name. Same set the Plans tab shows. Accepts a raw plan row.
	 *
	 * @param array $plan Plan term row (from PlanRepository::get_plans()).
	 *
	 * @return array<int,string>
	 */
	public static function term_meta( $plan ) {
		return array_merge( array( self::breakdown( $plan ) ), self::term_chips( $plan ) );
	}

	/**
	 * Build the short info chips for a term (free trial, signup fee, expiry).
	 *
	 * @param array $plan Plan term row.
	 *
	 * @return array<int,string>
	 */
	protected static function term_chips( $plan ) {
		$chips = array();

		$installments = isset( $plan['data']['installment_count'] ) ? (int) $plan['data']['installment_count'] : 0;
		if ( $installments > 1 ) {
			$chips[] = sprintf(
				/* translators: %d: number of installment payments. */
				_n( '%d payment', '%d payments', $installments, 'subscription' ),
				$installments
			);
		}

		$trial_days = (int) ( $plan['free_trial'] ?? 0 );
		if ( $trial_days > 0 ) {
			$trial_unit = isset( $plan['data']['free_trial_interval'] ) ? (string) $plan['data']['free_trial_interval'] : 'day';
			$chips[]    = sprintf(
				/* translators: 1: number, 2: unit (day/week/month/year). */
				__( '%1$d-%2$s free trial', 'subscription' ),
				$trial_days,
				$trial_unit
			);
		}

		$signup_fee = isset( $plan['signup_fee']['amount'] ) ? (float) $plan['signup_fee']['amount'] : 0.0;
		if ( $signup_fee > 0 ) {
			/* translators: %s: formatted signup fee amount. */
			$chips[] = sprintf( __( 'Signup fee: %s', 'subscription' ), self::money( $signup_fee ) );
		}

		$length = (int) ( $plan['billing_length'] ?? 0 );
		if ( $length > 0 ) {
			$chips[] = sprintf(
				/* translators: %d: number of billing cycles. */
				_n( 'Ends after %d cycle', 'Ends after %d cycles', $length, 'subscription' ),
				$length
			);
		}

		return $chips;
	}

	/**
	 * Build a term's pricing-breakdown display string.
	 *
	 * @param array $plan Plan term row.
	 *
	 * @return string
	 */
	protected static function breakdown( $plan ) {
		if ( ! empty( $plan['data']['pricing_breakdown'] ) ) {
			return $plan['data']['pricing_breakdown'];
		}

		$freq     = max( 1, (int) $plan['billing_frequency'] );
		$interval = self::interval_label( (int) $plan['billing_interval'] );
		$every    = 1 === $freq ? strtolower( $interval ) : $freq . ' ' . strtolower( $interval ) . 's';

		/* translators: %s: billing interval, e.g. "month" or "2 weeks". */
		return sprintf( __( 'Billed every %s', 'subscription' ), $every );
	}

	/**
	 * Map a billing-interval integer to its label.
	 *
	 * @param int $interval 1=day, 2=week, 3=month, 4=year.
	 *
	 * @return string
	 */
	public static function interval_label( $interval ) {
		$labels = array(
			1 => __( 'Day', 'subscription' ),
			2 => __( 'Week', 'subscription' ),
			3 => __( 'Month', 'subscription' ),
			4 => __( 'Year', 'subscription' ),
		);

		return $labels[ $interval ] ?? __( 'Month', 'subscription' );
	}

	/**
	 * Human "x ago" string from a MySQL datetime.
	 *
	 * @param string $datetime MySQL datetime (UTC).
	 *
	 * @return string
	 */
	protected static function ago( $datetime ) {
		$ts = strtotime( (string) $datetime );

		if ( ! $ts ) {
			return '';
		}

		/* translators: %s: human time difference, e.g. "2 hours". */
		return sprintf( __( '%s ago', 'subscription' ), human_time_diff( $ts, time() ) );
	}

	/**
	 * Format an amount as a bare number string (no currency symbol).
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	protected static function amount( $amount ) {
		return number_format( (float) $amount, 2, '.', '' );
	}

	/**
	 * Format an amount with the WooCommerce currency symbol (suffix style).
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	public static function money( $amount ) {
		$symbol = function_exists( 'get_woocommerce_currency_symbol' )
			? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
			: '$';

		return self::amount( $amount ) . $symbol;
	}
}
