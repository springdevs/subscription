<?php
/**
 * Storefront plan selector (free).
 *
 * Renders the plan selector — a radio card per plan group, with the group's
 * terms as buttons — on a simple product tied to a plan, and carries the chosen
 * plan-term id onto the add-to-cart request. Guarded by `subscrpt_plan_offered()`:
 * with no tied plan this class does nothing and the classic price suffix stands.
 *
 * Runs only when Pro is inactive (see Frontend::__construct). Pro ships a superset
 * on the same hooks — One-Time card, discount badges, variable products and the
 * Subscribe & Save / Installments plan types.
 *
 * The storefront never calls REST; plan data is read directly through
 * `PlanRepository::resolve_for_product()` (object cache → DB).
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Admin\PlanPresenter;
use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * Frontend plan selector for simple products.
 */
class Plans {

	/**
	 * Register storefront hooks.
	 */
	public function __construct() {
		// Runs after Frontend\Product::change_price_html (priority 10) so the plan
		// price replaces the classic suffix rather than appending to it.
		add_filter( 'woocommerce_get_price_html', array( $this, 'plan_price_html' ), 20, 2 );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_selector' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Whether the plan selector should render on this request.
	 *
	 * @return bool
	 */
	private function should_render() {
		return function_exists( 'is_product' ) && is_product();
	}

	/**
	 * Whether a simple product offers a subscription: tied to a plan AND
	 * subscription-enabled. Free is simple-only.
	 *
	 * @param mixed $product Product object.
	 *
	 * @return bool
	 */
	private function product_has_plans( $product ) {
		return $product instanceof \WC_Product
			&& $product->is_type( 'simple' )
			&& subscrpt_plan_offered( $product->get_id() );
	}

	/**
	 * Enqueue selector assets on product pages that expose plans.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->should_render() ) {
			return;
		}

		// global $product is not set yet at wp_enqueue_scripts; resolve from the query.
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $this->product_has_plans( $product ) ) {
			return;
		}

		wp_enqueue_style(
			'subscrpt_plans_selector_css',
			SUBSCRPT_ASSETS . '/css/frontend/plans.css',
			array(),
			SUBSCRPT_VERSION
		);

		wp_enqueue_script(
			'subscrpt_plans_selector_js',
			SUBSCRPT_ASSETS . '/js/frontend/plans.js',
			array(),
			SUBSCRPT_VERSION,
			true
		);
	}

	/**
	 * Replace the price HTML with the resolved plan price when a plan is tied;
	 * otherwise return the price unchanged (classic suffix stands).
	 *
	 * A single tied plan shows the offer price (regular struck-through when
	 * discounted); multiple plans show a "min – max" range across every term. No
	 * cadence — the selector below lists each term's cadence.
	 *
	 * @param string            $price_html Price HTML.
	 * @param \WC_Product|mixed $product    Product.
	 *
	 * @return string
	 */
	public function plan_price_html( $price_html, $product ) {
		if ( ! $this->product_has_plans( $product ) ) {
			return $price_html;
		}

		$rows = PlanRepository::resolve_for_product( $product->get_id() );
		if ( empty( $rows ) ) {
			return $price_html;
		}

		// Single plan: offer price, with the regular struck-through when discounted.
		if ( 1 === count( $rows ) ) {
			$row     = $rows[0];
			$data    = is_array( $row['relation_data'] ) ? $row['relation_data'] : array();
			$regular = isset( $data['regular_price'] ) && '' !== $data['regular_price'] ? (float) $data['regular_price'] : null;
			$offer   = $this->term_price( $row );

			return ( null !== $regular && $offer < $regular )
				? '<del aria-hidden="true">' . wc_price( $regular ) . '</del> <ins>' . wc_price( $offer ) . '</ins>'
				: wc_price( $offer );
		}

		// Multiple plans: a min–max range across every attached term.
		$prices = array();
		foreach ( $rows as $row ) {
			$prices[] = $this->term_price( $row );
		}

		$min = min( $prices );
		$max = max( $prices );

		return $min === $max
			? wc_price( $min )
			: wc_price( $min ) . ' &ndash; ' . wc_price( $max );
	}

	/**
	 * Render the plan selector inside the add-to-cart form.
	 *
	 * @return void
	 */
	public function render_selector() {
		if ( ! $this->should_render() ) {
			return;
		}

		global $product;
		if ( ! $this->product_has_plans( $product ) ) {
			return;
		}

		$groups = $this->build_groups( $product );
		if ( empty( $groups ) ) {
			return;
		}

		wc_get_template(
			'product/plan-selector.php',
			array( 'groups' => $groups ),
			'subscription',
			SUBSCRPT_TEMPLATES
		);
	}

	/**
	 * Build the selector groups for a simple product from resolved plan data.
	 *
	 * One entry per plan group, each with its terms (id, label, price, note)
	 * and a discount badge when its offer price beats the regular one, followed
	 * by the One-Time card when the merchant offers one.
	 *
	 * @param \WC_Product $product Simple product.
	 *
	 * @return array
	 */
	private function build_groups( $product ) {
		$resolved = PlanRepository::resolve_for_product( $product->get_id() );
		if ( empty( $resolved ) ) {
			return array();
		}

		$groups = array();
		foreach ( $resolved as $row ) {
			$gid = (int) $row['plan_group_id'];

			if ( ! isset( $groups[ $gid ] ) ) {
				$groups[ $gid ] = array(
					'id'               => 'grp_' . $gid,
					'type'             => PlanRepository::type_to_string( (int) $row['group_type'] ),
					'label'            => $row['group_title'],
					'price'            => '',
					'terms'            => array(),
					'badge'            => '',
					'discount_percent' => 0,
					'pcts'             => array(),
				);
			}

			$price_num = $this->term_price( $row );

			// Each term's discount (offer below regular). Installments price on a
			// different basis, so they never contribute a percentage.
			$row_regular = isset( $row['relation_data']['regular_price'] ) ? (float) $row['relation_data']['regular_price'] : 0.0;
			if ( 'installments' !== $groups[ $gid ]['type'] && $row_regular > 0 && $price_num < $row_regular ) {
				$groups[ $gid ]['pcts'][] = (int) round( ( $row_regular - $price_num ) / $row_regular * 100 );
			}

			$groups[ $gid ]['terms'][] = array(
				'id'    => (int) $row['plan_id'],
				'label' => $row['plan_title'],
				'price' => wc_price( $price_num ),
				'note'  => $this->term_note( $row, $price_num ),
			);
		}

		// Card header price = the first term of each group; the badge reports the
		// group's best discount, and says "up to" when its terms differ.
		foreach ( $groups as &$group ) {
			$group['price'] = $group['terms'][0]['price'];

			if ( ! empty( $group['pcts'] ) ) {
				$max                       = max( $group['pcts'] );
				$group['discount_percent'] = $max;
				$group['badge']            = subscrpt_card_badge_text( $group, $product, $max, min( $group['pcts'] ) !== $max );
			}
			unset( $group['pcts'] );
		}
		unset( $group );

		$groups = array_values( $groups );

		// One-Time purchase card, after the plans so a subscription stays the
		// pre-selected default. The base template already renders this type.
		$one_time = subscrpt_one_time_group( $product );
		if ( $one_time ) {
			$groups[] = $one_time;
		}

		return $groups;
	}

	/**
	 * Compute the numeric offer price for a resolved plan term.
	 *
	 * @param array $row Resolved plan row.
	 *
	 * @return float
	 */
	private function term_price( $row ) {
		$data    = is_array( $row['relation_data'] ) ? $row['relation_data'] : array();
		$regular = isset( $data['regular_price'] ) ? (string) $data['regular_price'] : '';
		$selling = isset( $data['sale_price'] ) ? (string) $data['sale_price'] : '';
		$dtype   = isset( $data['discount_type'] ) ? (string) $data['discount_type'] : 'percentage';
		$dvalue  = isset( $data['discount_value'] ) ? (string) $data['discount_value'] : '0';

		return (float) PlanPresenter::offer_price( $regular, $selling, $dtype, $dvalue );
	}

	/**
	 * Build the billing-cadence note shown under a term ("Billed $10 / month").
	 *
	 * @param array $row       Resolved plan row.
	 * @param float $price_num Computed term price.
	 *
	 * @return string
	 */
	private function term_note( $row, $price_num ) {
		$interval = PlanPresenter::interval_label( (int) $row['billing_interval'] );
		$freq     = max( 1, (int) $row['billing_frequency'] );
		$every    = 1 === $freq ? strtolower( $interval ) : $freq . ' ' . strtolower( $interval ) . 's';

		$data    = is_array( $row['relation_data'] ) ? $row['relation_data'] : array();
		$regular = isset( $data['regular_price'] ) && '' !== $data['regular_price'] ? (float) $data['regular_price'] : null;

		$price_disp = ( null !== $regular && $price_num < $regular )
			? '<del>' . $this->price_text( $regular ) . '</del> ' . $this->price_text( $price_num )
			: $this->price_text( $price_num );

		return sprintf(
			/* translators: 1: price (may include a struck-through regular price), 2: billing interval. */
			__( 'Billed %1$s / %2$s', 'subscription' ),
			$price_disp,
			$every
		);
	}

	/**
	 * Plain-text formatted price (currency symbol as a real character, not an
	 * HTML entity) so it renders cleanly inside the note / data attributes.
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	private function price_text( $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount ) ), ENT_QUOTES, 'UTF-8' );
	}
}
