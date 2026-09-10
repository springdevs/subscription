<?php
/**
 * Plan checkout consumption (free).
 *
 * The plan-gated billing path: cart item → order line item → subscription
 * snapshot. Every hook here acts ONLY when a plan was chosen (`subscrpt_plan_id`
 * on the cart / order item), so classic (non-plan) items follow the existing
 * `Frontend\Checkout` path byte-for-byte. Free handles the **Recurring** type on
 * **simple** products only; Pro extends this with Subscribe & Save,
 * Installments, variable/per-variation, one-time, signup fees and live pricing.
 *
 * The subscription record is created from the plan term (price + cadence + trial
 * resolved at add-to-cart), written into the same classic snapshot meta the
 * engine already renews on — so a plan subscription renews on its snapshot with
 * no plan-specific renewal code.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Admin\PlanPresenter;
use SpringDevs\Subscription\Illuminate\Helper;
use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * Class PlanCheckout
 */
class PlanCheckout {

	/**
	 * Register the plan-gated checkout hooks.
	 */
	public function __construct() {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_plan_to_cart' ), 20, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ), 20 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_plan_order_item' ), 20, 3 );

		// Priority 9: create the subscription before the classic listener runs, so
		// the classic simple path (which we also gate off in Frontend\Checkout)
		// never double-creates for a plan item.
		add_action( 'subscrpt_product_checkout', array( $this, 'create_plan_subscription' ), 9, 3 );
	}

	/**
	 * Resolve the plan term a shopper chose for a product, validating that the
	 * chosen plan id is actually one of the product's connected terms.
	 *
	 * @param int $product_id Product id.
	 * @param int $plan_id    Chosen plan-term id.
	 *
	 * @return array|null Resolved plan row, or null if not valid for the product.
	 */
	private function resolve_chosen( $product_id, $plan_id ) {
		$plan_id = absint( $plan_id );
		if ( ! $plan_id ) {
			return null;
		}

		foreach ( PlanRepository::resolve_for_product( $product_id ) as $row ) {
			if ( (int) $row['plan_id'] === $plan_id ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Resolve a fallback plan for a bare add-to-cart — a direct link with no plan
	 * chosen (`?add-to-cart=ID`). One-time purchase wins when enabled: the item
	 * stays a plain one-time line at the native price (returns 0). Otherwise the
	 * product's first plan is selected, matching the on-page selector's default.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return int Plan-term id, or 0 to leave the item untouched.
	 */
	private function fallback_plan_id( $product_id ) {
		if ( ! function_exists( 'subscrpt_product_has_plan' ) || ! subscrpt_product_has_plan( $product_id ) ) {
			return 0;
		}

		// One-time enabled → keep it a one-time line at the native price.
		if ( 'yes' === get_post_meta( $product_id, '_subscrpt_one_time_enabled', true ) ) {
			return 0;
		}

		// Default to the product's first plan (same order the selector defaults to).
		foreach ( PlanRepository::resolve_for_product( $product_id ) as $row ) {
			return (int) $row['plan_id'];
		}

		return 0;
	}

	/**
	 * Map a resolved plan row to its Recurring billing terms.
	 *
	 * @param array $row Resolved plan row.
	 *
	 * @return array{price:float,time:int,option:string,trial:?string}
	 */
	private function term_terms( $row ) {
		$data    = is_array( $row['relation_data'] ) ? $row['relation_data'] : array();
		$regular = isset( $data['regular_price'] ) ? (string) $data['regular_price'] : '';
		$selling = isset( $data['sale_price'] ) ? (string) $data['sale_price'] : '';
		$dtype   = isset( $data['discount_type'] ) ? (string) $data['discount_type'] : 'percentage';
		$dvalue  = isset( $data['discount_value'] ) ? (string) $data['discount_value'] : '0';

		$trial = null;
		if ( ! empty( $row['free_trial'] ) && (int) $row['free_trial'] > 0 ) {
			$trial_interval = isset( $row['plan_data']['free_trial_interval'] ) ? (string) $row['plan_data']['free_trial_interval'] : 'days';
			$trial          = (int) $row['free_trial'] . ' ' . $trial_interval;
		}

		return array(
			'price'  => (float) PlanPresenter::offer_price( $regular, $selling, $dtype, $dvalue ),
			'time'   => max( 1, (int) $row['billing_frequency'] ),
			'option' => PlanRepository::interval_to_option( (int) $row['billing_interval'] ),
			'trial'  => $trial,
		);
	}

	/**
	 * Stamp the chosen plan onto the cart item.
	 *
	 * Reads `subscrpt_plan_id` from the add-to-cart request, resolves the term,
	 * and writes the plan id + a `subscription` array (same shape the classic
	 * engine uses) so cart display and calculation work unchanged.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product id.
	 *
	 * @return array
	 */
	public function add_plan_to_cart( $cart_item_data, $product_id ) {
		// Read from the request so both the product-page form (POST) and direct
		// checkout links (`?add-to-cart=ID&subscrpt_plan_id=TERM`, GET) select a plan.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce verifies the add-to-cart request; we only read a plan id.
		$plan_id = isset( $_REQUEST['subscrpt_plan_id'] ) ? absint( wp_unslash( $_REQUEST['subscrpt_plan_id'] ) ) : 0;
		if ( ! $plan_id ) {
			// Bare add-to-cart (direct link, no plan chosen): fall back to one-time
			// when enabled, otherwise the product's first plan.
			$plan_id = $this->fallback_plan_id( $product_id );
			if ( ! $plan_id ) {
				return $cart_item_data;
			}
		}

		$row = $this->resolve_chosen( $product_id, $plan_id );
		if ( ! $row ) {
			return $cart_item_data;
		}

		$terms = $this->term_terms( $row );

		$cart_item_data['subscrpt_plan_id']       = $plan_id;
		$cart_item_data['subscrpt_plan_group_id'] = (int) $row['plan_group_id'];
		$cart_item_data['subscrpt_plan_price']    = $terms['price'];
		$cart_item_data['subscription']           = array(
			'time'       => $terms['time'],
			'type'       => $terms['option'],
			'trial'      => $terms['trial'],
			'signup_fee' => null,
			'per_cost'   => $terms['price'],
		);

		return $cart_item_data;
	}

	/**
	 * Override the cart line price with the resolved plan price.
	 *
	 * @param \WC_Cart $cart Cart object.
	 *
	 * @return void
	 */
	public function set_cart_item_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['subscrpt_plan_price'], $cart_item['data'] ) ) {
				$cart_item['data']->set_price( (float) $cart_item['subscrpt_plan_price'] );
			}
		}
	}

	/**
	 * Persist the chosen plan + terms onto the order line item so the resolved
	 * snapshot survives past cart session (the product carries no such meta).
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $cart_item     Cart item data.
	 *
	 * @return void
	 */
	public function save_plan_order_item( $item, $cart_item_key, $cart_item ) {
		if ( empty( $cart_item['subscrpt_plan_id'] ) ) {
			return;
		}

		$item->update_meta_data( '_subscrpt_plan_id', (int) $cart_item['subscrpt_plan_id'] );
		$item->update_meta_data( '_subscrpt_plan_group_id', (int) ( $cart_item['subscrpt_plan_group_id'] ?? 0 ) );
		$item->update_meta_data( '_subscrpt_plan_price', (float) ( $cart_item['subscrpt_plan_price'] ?? 0 ) );
		$item->update_meta_data( '_subscrpt_plan_terms', $cart_item['subscription'] ?? array() );
	}

	/**
	 * Create the subscription record from the order item's plan terms.
	 *
	 * Fires on `subscrpt_product_checkout` for every order item; acts only when
	 * the item carries `_subscrpt_plan_id`. Writes the same classic snapshot meta
	 * the engine renews on, but sourced from the plan term rather than the product.
	 *
	 * @param \WC_Order_Item $order_item  Order item.
	 * @param \WC_Product    $product     Product object.
	 * @param string         $post_status Subscription status.
	 *
	 * @return void
	 */
	public function create_plan_subscription( $order_item, $product, $post_status ) {
		$plan_id = (int) $order_item->get_meta( '_subscrpt_plan_id' );
		if ( ! $plan_id ) {
			return;
		}

		$terms = $order_item->get_meta( '_subscrpt_plan_terms' );
		$terms = is_array( $terms ) ? $terms : array();

		$timing_per    = (int) ( $terms['time'] ?? 1 );
		$timing_option = (string) ( $terms['type'] ?? 'months' );
		$trial         = $terms['trial'] ?? null;
		$type          = Helper::get_typos( $timing_per, $timing_option );

		wc_update_order_item_meta(
			$order_item->get_id(),
			'_subscrpt_meta',
			array(
				'time'  => $timing_per,
				'type'  => $timing_option,
				'trial' => $trial,
			)
		);

		$subscription_id = Helper::process_new_subscription_order( $order_item, $post_status, $product );
		if ( ! $subscription_id ) {
			return;
		}

		update_post_meta( $subscription_id, '_subscrpt_timing_per', $timing_per );
		update_post_meta( $subscription_id, '_subscrpt_timing_option', $timing_option );
		update_post_meta( $subscription_id, '_subscrpt_price', (float) $order_item->get_meta( '_subscrpt_plan_price' ) );
		update_post_meta( $subscription_id, '_subscrpt_plan_id', $plan_id );
		update_post_meta( $subscription_id, '_subscrpt_plan_group_id', (int) $order_item->get_meta( '_subscrpt_plan_group_id' ) );
		update_post_meta( $subscription_id, '_subscrpt_user_cancel', $product->get_meta( '_subscrpt_user_cancel' ) );
		update_post_meta( $subscription_id, '_subscrpt_order_id', $order_item->get_order_id() );
		update_post_meta( $subscription_id, '_subscrpt_order_item_id', $order_item->get_id() );
		update_post_meta( $subscription_id, '_subscrpt_trial', $trial );

		if ( 'active' === $post_status ) {
			$start_date = time();
			$next_date  = sdevs_wp_strtotime( $timing_per . ' ' . $type, $start_date );
			if ( $trial ) {
				$start_date = sdevs_wp_strtotime( $trial );
				$next_date  = $start_date;
			}
			update_post_meta( $subscription_id, '_subscrpt_start_date', $start_date );
			update_post_meta( $subscription_id, '_subscrpt_next_date', $next_date );
		}

		do_action( 'subscrpt_order_checkout', $subscription_id, $order_item );
	}
}
