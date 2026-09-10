<?php
/**
 * Subscription helper utilities.
 *
 * @package SpringDevs\Subscription\Illuminate
 */

namespace SpringDevs\Subscription\Illuminate;

use SpringDevs\Subscription\Illuminate\Gateways\Stripe\Stripe;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;

// HPOS: This file is compatible with WooCommerce High-Performance Order Storage (HPOS).
// All WooCommerce order data is accessed via WooCommerce CRUD methods (wc_get_order, wc_get_orders, etc.).
// All direct post meta access is for subscription data only, not WooCommerce order data.
// If you add new order data access, use WooCommerce CRUD for HPOS compatibility.

/**
 * Class Helper || Some Helper Methods
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Helper {
	/**
	 * Get type's singular or plural from time_per.
	 *
	 * @param int    $number timing_per.
	 * @param string $typo timing_option.
	 * @param bool   $translate Whether to translate the output.
	 * @return string
	 */
	public static function get_typos( $number, $typo, $translate = false ) {
		switch ( strtolower( $typo ) ) {
			case 'day':
			case 'days':
				return $translate
					? _n( 'day', 'days', $number, 'subscription' )
					: ( (int) $number === 1 ? 'day' : 'days' );

			case 'week':
			case 'weeks':
				return $translate
					? _n( 'week', 'weeks', $number, 'subscription' )
					: ( (int) $number === 1 ? 'week' : 'weeks' );

			case 'month':
			case 'months':
				return $translate
					? _n( 'month', 'months', $number, 'subscription' )
					: ( (int) $number === 1 ? 'month' : 'months' );

			case 'year':
			case 'years':
				return $translate
					? _n( 'year', 'years', $number, 'subscription' )
					: ( (int) $number === 1 ? 'year' : 'years' );

			default:
				return $typo;
		}
	}

	/**
	 * Get verbose status from status slug.
	 *
	 * @param string $status Status.
	 * @param bool   $return_all Whether to return all statuses or a single status.
	 *
	 * @return string|array Verbose status label, or the full status map when $return_all is true.
	 */
	public static function get_verbose_status( $status, $return_all = false ) {
		$statuses = array(
			'pending'      => __( 'Pending', 'subscription' ),
			'active'       => __( 'Active', 'subscription' ),
			'on-hold'      => __( 'On Hold', 'subscription' ),
			'expired'      => __( 'Expired', 'subscription' ),
			'completed'    => __( 'Completed', 'subscription' ),
			'pe_cancelled' => __( 'Pending Cancellation', 'subscription' ),
			'cancelled'    => __( 'Cancelled', 'subscription' ),
			'draft'        => __( 'Draft', 'subscription' ),
			'trash'        => __( 'Trash', 'subscription' ),
		);

		if ( $return_all ) {
			return $statuses;
		}

		$status = strtolower( $status );
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : '';
	}

	/**
	 * Generate start date
	 *
	 * @param null|string $trial Trial.
	 *
	 * @return string
	 */
	public static function start_date( $trial = null ) {
		if ( null === $trial ) {
			$start_date = time();
		} else {
			$start_date = strtotime( $trial );
		}
		return wp_date( get_option( 'date_format' ), $start_date );
	}

	/**
	 * Generate next date
	 *
	 * @param string      $time Time.
	 * @param null|string $trial Trial.
	 *
	 * @return string
	 */
	public static function next_date( $time, $trial = null ) {
		if ( null === $trial ) {
			$start_date = time();
		} else {
			$start_date = strtotime( $trial );
		}
		return wp_date( get_option( 'date_format' ), strtotime( $time, $start_date ) );
	}

	/**
	 * Get Subscriptions
	 *
	 * Args:
	 * - status         => [ any, active, pending, expired, pe_cancelled, cancelled, trash ]
	 * - user_id        => user_id, -1 for all users.
	 * - posts_per_page => limit number of subscriptions.
	 * - return         => return data: ids, post, subscription_data
	 *
	 * @param array $args Args.
	 */
	public static function get_subscriptions( array $args = array() ) {
		$default_args = array(
			'post_type'      => 'subscrpt_order',
			'post_status'    => 'active',
			'author'         => get_current_user_id(),
			'posts_per_page' => -1,
			'fields'         => 'all',
			'return'         => 'post',
		);

		// Normalize some args.
		if ( isset( $args['status'] ) ) {
			$args['post_status'] = $args['status'];
			unset( $args['status'] );
		}
		if ( isset( $args['user_id'] ) ) {
			$args['author'] = $args['user_id'];
			unset( $args['user_id'] );
		}

		// Merge default args with provided args.
		$final_args = wp_parse_args( $args, $default_args );

		if ( isset( $args['author'] ) ) {
			if ( $args['author'] === -1 ) {
				unset( $final_args['author'] );
			} else {
				$final_args['author'] = (int) $args['author'];
			}
		}

		if ( isset( $args['product_id'] ) ) {
			$final_args['meta_query'] = array(
				array(
					'key'   => '_subscrpt_product_id',
					'value' => (int) $args['product_id'],
				),
			);
			unset( $final_args['product_id'] );
		}

		// Fields check
		$only_ids = false;
		if ( $final_args['fields'] === 'ids' || $final_args['return'] === 'ids' ) {
			$final_args['fields'] = 'all';
			$only_ids             = true;
		}

		// Status check
		$statuses                  = $final_args['post_status'];
		$final_args['post_status'] = 'any';

		// Get all subscriptions.
		$subscriptions = get_posts( $final_args );

		// Fallback filtering.
		// ? Sometime status filtering not works properly. So, we need to filter manually.
		$filtered_subscriptions = [];

		// Filter by status.
		foreach ( $subscriptions as $subscription ) {
			if ( ( is_array( $statuses ) && in_array( 'any', $statuses, true ) ) || $statuses === 'any' ) {
				$filtered_subscriptions[] = $subscription;
				continue;
			}

			if ( ( is_array( $statuses ) && in_array( $subscription->post_status, $statuses, true ) ) || $subscription->post_status === $statuses ) {
				$filtered_subscriptions[] = $subscription;
			}
		}

		// Final filtering (only ids, post, or full data)
		$subscriptions = [];
		foreach ( $filtered_subscriptions as $subscription ) {
			if ( $only_ids ) {
				$subscriptions[] = $subscription->ID;
			} elseif ( $final_args['return'] === 'subscription_data' ) {
				$subs_id           = $subscription->ID;
				$subscription_data = self::get_subscription_data( $subs_id );
				$subscriptions[]   = $subscription_data;
			} else {
				$subscriptions[] = $subscription;
			}
		}

		return $subscriptions;
	}

	/**
	 * Check subscription exists by product ID.
	 *
	 * @param int          $product_id Product ID.
	 * @param string|array $status Status.
	 *
	 * @return \WP_Post | false
	 */
	public static function subscription_exists( int $product_id, $status ) {
		if ( 0 === get_current_user_id() ) {
			return false;
		}

		$args = array(
			'post_status' => $status,
			'fields'      => 'ids',
			'product_id'  => $product_id,
		);

		$posts = self::get_subscriptions( $args );
		return count( $posts ) > 0 ? $posts[0] : false;
	}

	/**
	 * Check if product trial exixts for an user.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return boolean
	 */
	public static function check_trial( int $product_id ): bool {
		return ! self::subscription_exists( $product_id, array( 'expired', 'pending', 'active', 'on-hold', 'pe_cancelled', 'cancelled' ) );
	}

	/**
	 * Rewew when expired.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public static function renew( int $subscription_id ) {
		$trial = get_post_meta( $subscription_id, '_subscrpt_trial', true );
		if ( null !== $trial ) {
			update_post_meta( $subscription_id, '_subscrpt_trial', null );
		}

		do_action( 'subscrpt_when_product_expired', $subscription_id, true );
	}

	/**
	 * Get Subscriptions Histories
	 *
	 * @param int $order_id Order ID.
	 */
	public static function get_subscriptions_from_order( $order_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		$histories  = $wpdb->get_results(
			$wpdb->prepare(
				// @phpcs:ignore
				'SELECT * FROM %i WHERE order_id=%d',
				array( $table_name, $order_id )
			)
		);

		return $histories;
	}

	/**
	 * Get Subscriptions Histories
	 *
	 * @param int $order_item_id Order item ID.
	 */
	public static function get_subscription_from_order_item_id( $order_item_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		return $wpdb->get_row(
			$wpdb->prepare(
				// @phpcs:ignore
				'SELECT * FROM %i WHERE order_item_id=%d',
				array( $table_name, $order_item_id )
			)
		);
	}

	/**
	 * Format price with Subscription
	 *
	 * @param string $price Price.
	 * @param int    $subscription_id Subscription ID.
	 * @param bool   $display_trial True/False.
	 *
	 * @return string
	 */
	public static function format_price_with_subscription( $price, $subscription_id, $display_trial = false ) {
		$order_id      = get_post_meta( $subscription_id, '_subscrpt_order_id', true );
		$order_item_id = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
		$item_meta     = wc_get_order_item_meta( $order_item_id, '_subscrpt_meta', true );

		$order = wc_get_order( $order_id );
		$time  = '1' === $item_meta['time'] ? null : $item_meta['time'] . ' ';
		$type  = self::get_typos( $item_meta['time'], $item_meta['type'] );

		$formatted_price = wc_price(
			$price,
			array(
				'currency' => $order->get_currency(),
			)
		) . ' / ' . $time . $type;

		if ( $display_trial ) {
			$trial     = $item_meta['trial'];
			$has_trial = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;

			if ( $has_trial ) {
				$trial_html       = '<br/><small> + Got ' . $trial . ' free trial!</small>';
				$formatted_price .= $trial_html;
			}
		}

		return apply_filters( 'subscrpt_format_price_with_subscription', $formatted_price, $price, $subscription_id );
	}

	/**
	 * Format price with order item
	 *
	 * @param string $price Price.
	 * @param int    $item_id Item Id.
	 * @param bool   $display_trial display trial?.
	 *
	 * @return string
	 */
	public static function format_price_with_order_item( $price, $item_id, $display_trial = false ) {
		$order_id = wc_get_order_id_by_order_item_id( $item_id );
		$order    = wc_get_order( $order_id );

		$item_meta = wc_get_order_item_meta( $item_id, '_subscrpt_meta', true );

		if ( ! $item_meta || ! is_array( $item_meta ) ) {
			return false;
		}

		$time = 1 === (int) $item_meta['time'] ? null : $item_meta['time'] . '-';
		$type = self::get_typos( $item_meta['time'], $item_meta['type'], true );

		$formatted_price = wc_price(
			$price,
			array(
				'currency' => $order->get_currency(),
			)
		) . ' / ' . $time . ucfirst( $type );

		if ( $display_trial ) {
			$has_trial = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;
			$trial     = $item_meta['trial'] ?? '';

			if ( $has_trial ) {
				// translators: %s: trial period.
				$trial_html       = '<br/><small> ' . sprintf( __( '+ %s free trial!', 'subscription' ), $trial ) . '</small>';
				$formatted_price .= $trial_html;
			}
		}

		return apply_filters( 'subscrpt_format_price_with_subscription', $formatted_price, $price, $item_id );
	}

	/**
	 * Get total subscriptions by product ID.
	 *
	 * @param int            $product_id Product ID.
	 * @param string | array $status Status.
	 *
	 * @return \WP_Post | false
	 */
	public static function get_total_subscriptions_from_product( int $product_id, $status = array( 'active', 'pending', 'expired', 'pe_cancelled', 'cancelled' ) ) {
		$args = array(
			'post_type'   => 'subscrpt_order',
			'post_status' => $status,
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'key'   => '_subscrpt_product_id',
					'value' => $product_id,
				),
			),
		);

		$posts = get_posts( $args );

		return count( $posts );
	}

	/**
	 * Process renewal on order.
	 *
	 * @param int $subscription_id Subscription Id.
	 * @param int $order_id Order Id.
	 * @param int $order_item_id Order Item Id.
	 *
	 * @return void
	 */
	public static function process_order_renewal( $subscription_id, $order_id, $order_item_id ) {
		global $wpdb;
		$history_table = $wpdb->prefix . 'subscrpt_order_relation';

		// Check if this is a split payment subscription
		$payment_type  = function_exists( 'subscrpt_get_payment_type' ) ? subscrpt_get_payment_type( $subscription_id ) : 'recurring';
		$max_payments  = function_exists( 'subscrpt_get_max_payments' ) ? subscrpt_get_max_payments( $subscription_id ) : 0;
		$payments_made = function_exists( 'subscrpt_count_payments_made' ) ? subscrpt_count_payments_made( $subscription_id ) : 0;

		$comment_content = '';
		$activity_type   = '';

		if ( 'split_payment' === $payment_type && $max_payments ) {
			$comment_content = sprintf(
				/* translators: %1$s: order id, %2$d: payment number, %3$d: total payments */
				__( 'Split payment installment %2$d of %3$d. Order %1$s created for subscription.', 'subscription' ),
				$order_id,
				$payments_made + 1, // +1 because this is a new renewal
				$max_payments
			);
			$activity_type = __( 'Split Payment - Renewal', 'subscription' );
		} else {
			$comment_content = sprintf(
				/* translators: order id. */
				__( 'The order %s has been created for the subscription', 'subscription' ),
				$order_id
			);
			$activity_type = __( 'Renewal Order', 'subscription' );
		}

		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => $comment_content,
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', $activity_type );
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'renewal_order' );

		$wpdb->insert(
			$history_table,
			array(
				'subscription_id' => $subscription_id,
				'order_id'        => $order_id,
				'order_item_id'   => $order_item_id,
				'type'            => 'renew',
			)
		);

		// Fire action when split payment is renewed
		do_action( 'subscrpt_split_payment_renewed', $subscription_id, $order_id, $order_item_id );
	}

	/**
	 * Process new subscription on order.
	 *
	 * @param \WC_Order_Item $order_item Order Item.
	 * @param string         $post_status status.
	 * @param \WC_Product    $product Product.
	 *
	 * @return int
	 */
	public static function process_new_subscription_order( $order_item, $post_status, $product ) {
		global $wpdb;
		$history_table = $wpdb->prefix . 'subscrpt_order_relation';

		// Prepare split payment arguments
		$split_payment_args = array(
			'product_id'    => $product->get_id(),
			'order_id'      => $order_item->get_order_id(),
			'order_item_id' => $order_item->get_id(),
			'post_status'   => $post_status,
			'max_payments'  => $product->get_meta( '_subscrpt_max_no_payment' ),
			'timing_per'    => $product->get_meta( '_subscrpt_timing_per' ),
			'timing_option' => $product->get_meta( '_subscrpt_timing_option' ),
			'price'         => $product->get_price(),
		);

		// Allow modification of split payment arguments
		$split_payment_args = apply_filters( 'subscrpt_split_payment_args', $split_payment_args, $order_item, $product );

		// Own the subscription from the order's customer, not the current user.
		$parent_order = wc_get_order( $order_item->get_order_id() );

		$args            = array(
			'post_title'  => 'Subscription',
			'post_type'   => 'subscrpt_order',
			'post_status' => $split_payment_args['post_status'],
			'post_author' => $parent_order ? (int) $parent_order->get_customer_id() : get_current_user_id(),
		);
		$subscription_id = wp_insert_post( $args );
		wp_update_post(
			array(
				'ID'         => $subscription_id,
				'post_title' => "Subscription #{$subscription_id}",
			)
		);
		// Check if this is a split payment subscription
		$payment_type = $product->get_meta( '_subscrpt_payment_type' );
		$payment_type = $payment_type ? $payment_type : 'recurring';
		$max_payments = $product->get_meta( '_subscrpt_max_no_payment' );

		$comment_content = '';
		$activity_type   = '';

		if ( 'split_payment' === $payment_type && $max_payments ) {
			$comment_content = sprintf(
				/* translators: %1$s: order id, %2$d: max payments */
				__( 'Split payment subscription created successfully. Order: %1$s. Total installments: %2$d.', 'subscription' ),
				$order_item->get_order_id(),
				$max_payments
			);
			$activity_type = __( 'Split Payment - New Subscription', 'subscription' );
		} else {
			$comment_content = sprintf(
				/* translators: Order Id. */
				__( 'Subscription successfully created. Order is %s', 'subscription' ),
				$order_item->get_order_id()
			);
			$activity_type = __( 'New Subscription', 'subscription' );
		}

		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => $comment_content,
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', $activity_type );
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'subs_created' );

		update_post_meta( $subscription_id, '_subscrpt_product_id', $product->get_id() );

		$wpdb->insert(
			$history_table,
			array(
				'subscription_id' => $subscription_id,
				'order_id'        => $order_item->get_order_id(),
				'order_item_id'   => $order_item->get_id(),
				'type'            => 'new',
			)
		);

		// Fire action when split payment plan is created
		do_action( 'subscrpt_split_payment_created', $subscription_id, $split_payment_args, $order_item );

		return $subscription_id;
	}

	/**
	 * Resolve applied coupon discounts for a single cart item, split by whether they recur.
	 *
	 * WooCommerce computes a per-coupon, per-item discount breakdown while calculating cart
	 * totals but never persists it — only the aggregated per-coupon and per-item sums survive.
	 * Replaying the coupons through a fresh WC_Discounts instance recovers that breakdown,
	 * which is what lets recurring and one-time discounts be told apart for one cart line.
	 *
	 * Amounts are returned in the same space WC_Discounts works in, i.e. `get_price() * qty`,
	 * so they are tax-inclusive only when the store's prices include tax.
	 *
	 * @param string $cart_item_key Cart item key.
	 *
	 * @return array{recurring:float,non_recurring:float,total:float,recurring_limit:int}
	 */
	public static function get_cart_item_coupon_discounts( $cart_item_key ) {
		$empty = array(
			'recurring'       => 0.0,
			'non_recurring'   => 0.0,
			'total'           => 0.0,
			'recurring_limit' => 0,
		);

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $empty;
		}

		$coupons = WC()->cart->get_coupons();
		if ( empty( $coupons ) ) {
			return $empty;
		}

		// Memoized per cart state: recurring totals are read several times per request, and
		// replaying coupons re-runs coupon validation, which hits the database.
		static $cache = array();

		$cache_key = md5( WC()->cart->get_cart_hash() . '|' . implode( ',', array_keys( $coupons ) ) );

		if ( ! isset( $cache[ $cache_key ] ) ) {
			// Replay in cart order so stacked coupons resolve exactly as WC_Cart_Totals resolved them.
			$discounts = new \WC_Discounts( WC()->cart );
			foreach ( $coupons as $coupon ) {
				$discounts->apply_coupon( $coupon );
			}
			$cache[ $cache_key ] = $discounts->get_discounts();
		}

		$result = $empty;
		$limits = array();

		foreach ( $cache[ $cache_key ] as $coupon_code => $item_discounts ) {
			$amount = (float) ( $item_discounts[ $cart_item_key ] ?? 0 );
			if ( 0 >= $amount ) {
				continue;
			}

			$coupon = $coupons[ $coupon_code ] ?? new \WC_Coupon( $coupon_code );

			/**
			 * Filters whether a coupon's discount also applies to subscription renewals.
			 *
			 * The free plugin has no recurring-coupon concept, so discounts apply to the
			 * first payment only unless an extension — the pro plugin — says otherwise.
			 *
			 * @param bool       $is_recurring  Whether the discount recurs. Default false.
			 * @param \WC_Coupon $coupon        Coupon object.
			 * @param string     $cart_item_key Cart item key the discount applies to.
			 */
			$is_recurring = (bool) apply_filters( 'subscrpt_coupon_is_recurring', false, $coupon, $cart_item_key );

			if ( ! $is_recurring ) {
				$result['non_recurring'] += $amount;
				continue;
			}

			/**
			 * Filters how many payments a recurring coupon's discount covers.
			 *
			 * @param int        $limit         Number of payments, including the initial one. 0 means unlimited.
			 * @param \WC_Coupon $coupon        Coupon object.
			 * @param string     $cart_item_key Cart item key the discount applies to.
			 */
			$limit = (int) apply_filters( 'subscrpt_coupon_recurring_limit', 0, $coupon, $cart_item_key );

			// A limit of one covers the initial payment only, so it never reaches a renewal —
			// whatever the coupon is flagged as, its effect here is a one-time discount.
			if ( 1 === $limit ) {
				$result['non_recurring'] += $amount;
				continue;
			}

			$result['recurring'] += $amount;

			if ( $limit > 0 ) {
				$limits[] = $limit;
			}
		}

		$result['total'] = $result['recurring'] + $result['non_recurring'];

		// The earliest limit to expire is when the discounted figure stops being true.
		$result['recurring_limit'] = empty( $limits ) ? 0 : min( $limits );

		return $result;
	}

	/**
	 * Build the discount-aware recurring price figures and markup for one cart item.
	 *
	 * A recurring coupon lowers what every renewal costs, so it is folded into the recurring
	 * figures. A one-time coupon lowers only what is paid today, so the recurring figures keep
	 * the full price and the caller discloses the first-payment amount separately.
	 *
	 * Discounts come back from WC_Discounts in `get_price() * qty` space, so they are converted
	 * to the tax-inclusive display space by ratio rather than by re-deriving tax.
	 *
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @param string $type_label    Human readable timing label, e.g. "Month".
	 *
	 * @return array
	 */
	public static function build_cart_recurring_price_data( $cart_item, $cart_item_key, $type_label ) {
		$product  = $cart_item['data'];
		$quantity = (int) $cart_item['quantity'];
		$per_cost = (float) ( $cart_item['subscription']['per_cost'] ?? 0 );

		$full_total = (float) wc_get_price_including_tax( $product, [ 'qty' => $quantity ] );
		$discounts  = self::get_cart_item_coupon_discounts( $cart_item_key );

		// Same basis WC_Discounts used, so the discount and the basis are directly comparable.
		$basis           = (float) $product->get_price() * $quantity;
		$recurring_ratio = $basis > 0 ? ( $basis - $discounts['recurring'] ) / $basis : 1.0;
		$first_ratio     = $basis > 0 ? ( $basis - $discounts['total'] ) / $basis : 1.0;

		$total       = $full_total * $recurring_ratio;
		$timing_html = "<span class='wpsubs-subscription-timing'>&nbsp;/&nbsp;{$type_label}</span>";

		$has_recurring_discount = $discounts['recurring'] > 0;
		$full_price_html        = wc_price( $full_total ) . $timing_html;
		$price_html             = $has_recurring_discount
			? '<del aria-hidden="true">' . wc_price( $full_total ) . '</del> <ins>' . wc_price( $total ) . '</ins>' . $timing_html
			: $full_price_html;

		return array(
			'price_html'             => $price_html,
			'full_price_html'        => $full_price_html,
			'price'                  => $per_cost * $recurring_ratio,
			'full_price'             => $per_cost,
			'total'                  => $total,
			'full_total'             => $full_total,
			'first_total'            => $full_total * $first_ratio,
			'has_recurring_discount' => $has_recurring_discount,
			'has_one_time_discount'  => $discounts['non_recurring'] > 0,
			'recurring_limit'        => $discounts['recurring_limit'],
		);
	}

	/**
	 * Resolve the discount that still applies to a subscription's future renewals.
	 *
	 * Only coupons flagged as recurring survive into renewal orders, and only while their
	 * recurring limit holds — this mirrors the skip conditions in the pro plugin's
	 * `Coupon::maybe_add_coupon_to_renewal_order()`, so what is displayed matches what the
	 * next renewal order will actually be charged.
	 *
	 * A subscription order always holds exactly one line item (enforced by
	 * `Frontend\Cart::validate_cart_items()`), so each coupon line's whole discount belongs
	 * to that item.
	 *
	 * @param int                 $subscription_id Subscription ID.
	 * @param \WC_Order|null      $order           Source order. Resolved from the subscription when omitted.
	 * @param \WC_Order_Item|null $order_item      Source order item. Used to rebase the discount when the
	 *                                             recurring price has since drifted, e.g. after a switch.
	 *
	 * @return array{amount:float,limit:int,exhausted:bool}
	 */
	public static function get_subscription_recurring_discount( $subscription_id, $order = null, $order_item = null ) {
		$result = array(
			'amount'    => 0.0,
			'limit'     => 0,
			'exhausted' => false,
		);

		// Memoized per request: list views and the single view each resolve the same
		// subscription two or three times, and a coupon'd subscription costs a query.
		static $cache = array();

		$cache_key = $subscription_id . '|' . ( $order_item ? $order_item->get_id() : 0 );

		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		if ( ! $order ) {
			$order_item_id = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
			$order         = $order_item_id ? wc_get_order( wc_get_order_id_by_order_item_id( $order_item_id ) ) : null;
		}

		if ( ! $order ) {
			$cache[ $cache_key ] = $result;
			return $result;
		}

		$coupon_lines = $order->get_items( 'coupon' );
		if ( empty( $coupon_lines ) ) {
			$cache[ $cache_key ] = $result;
			return $result;
		}

		/*
		 * Position the next renewal will take in this subscription's order sequence.
		 *
		 * Note this is deliberately one more than the current order count, and so is NOT the
		 * same expression pro's Coupon::maybe_add_coupon_to_renewal_order() evaluates: that
		 * runs after the new order's relation row is already inserted, so its count includes
		 * the order being created. Both mean "is this order still within the limit".
		 */
		$next_order_position = count( self::get_related_orders( (int) $subscription_id ) ) + 1;
		$limits              = array();

		foreach ( $coupon_lines as $coupon_line ) {
			$coupon = new \WC_Coupon( $coupon_line->get_code() );

			/** This filter is documented in includes/Illuminate/Helper.php */
			if ( ! apply_filters( 'subscrpt_coupon_is_recurring', false, $coupon, '' ) ) {
				continue;
			}

			/** This filter is documented in includes/Illuminate/Helper.php */
			$limit = (int) apply_filters( 'subscrpt_coupon_recurring_limit', 0, $coupon, '' );

			if ( $limit > 0 ) {
				$limits[] = $limit;

				// The next renewal is past the limit, so it will be charged full price.
				if ( $next_order_position > $limit ) {
					$result['exhausted'] = true;
					continue;
				}
			}

			$result['amount'] += (float) $coupon_line->get_discount();
		}

		$result['limit'] = empty( $limits ) ? 0 : min( $limits );

		// Rebase onto the current recurring price when it no longer matches what was discounted.
		$discounted_subtotal = $order_item ? (float) $order_item->get_subtotal() : 0.0;
		$recurring_subtotal  = $order_item ? (float) self::get_subscription_total( $subscription_id ) * max( 1, (int) $order_item->get_quantity() ) : 0.0;

		if ( $result['amount'] > 0 && $discounted_subtotal > 0 && abs( $discounted_subtotal - $recurring_subtotal ) > 0.01 ) {
			$result['amount'] = $result['amount'] * ( $recurring_subtotal / $discounted_subtotal );
		}

		$cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Build the figures the My Account subscription views display.
	 *
	 * The recurring price in `_subscrpt_price` is always the undiscounted product price, so the
	 * renewal figure has to be derived: full price, minus whatever discount recurs, plus tax on
	 * the discounted amount. Tax is scaled from the order item's own tax ratio rather than
	 * recalculated, which keeps the figures consistent with the order they came from.
	 *
	 * @param int                 $subscription_id Subscription ID.
	 * @param \WC_Order_Item|null $order_item      Source order item.
	 *
	 * @return array{full_excl:float,discount:float,discount_tax:float,tax:float,total:float,has_discount:bool}
	 */
	public static function get_subscription_display_totals( $subscription_id, $order_item = null ) {
		$quantity  = $order_item ? max( 1, (int) $order_item->get_quantity() ) : 1;
		$full_excl = (float) self::get_subscription_total( $subscription_id ) * $quantity;

		$item_subtotal = $order_item ? (float) $order_item->get_subtotal() : 0.0;
		$item_tax      = $order_item ? (float) $order_item->get_subtotal_tax() : 0.0;
		$tax_ratio     = $item_subtotal > 0 ? $item_tax / $item_subtotal : 0.0;

		$order    = $order_item ? wc_get_order( $order_item->get_order_id() ) : null;
		$discount = self::get_subscription_recurring_discount( $subscription_id, $order, $order_item );
		$discount = min( (float) $discount['amount'], $full_excl );

		$discount_tax = $discount * $tax_ratio;
		$tax          = ( $full_excl * $tax_ratio ) - $discount_tax;

		return array(
			'full_excl'    => $full_excl,
			'discount'     => $discount,
			'discount_tax' => $discount_tax,
			'tax'          => $tax,
			'total'        => $full_excl - $discount + $tax,
			'has_discount' => $discount > 0,
		);
	}

	/**
	 * Resolve the pieces every recurring-amount display needs.
	 *
	 * @param int                 $subscription_id Subscription ID.
	 * @param \WC_Order_Item|null $order_item      Source order item.
	 *
	 * @return array|false {discounted:string,full:string,has_discount:bool}, or false when the
	 *                     order item is missing or carries no subscription meta.
	 */
	protected static function get_subscription_recurring_price_parts( $subscription_id, $order_item = null ) {
		if ( ! $order_item ) {
			return false;
		}

		$totals     = self::get_subscription_display_totals( $subscription_id, $order_item );
		$discounted = self::format_price_with_order_item( $totals['total'], $order_item->get_id() );

		if ( ! $discounted ) {
			return false;
		}

		// Undiscounted amount including its own tax.
		$full  = $totals['full_excl'] + $totals['tax'] + $totals['discount_tax'];
		$order = wc_get_order( $order_item->get_order_id() );

		return array(
			'discounted'   => $discounted,
			'full'         => wc_price(
				$full,
				array(
					'currency' => $order ? $order->get_currency() : '',
				)
			),
			'has_discount' => $totals['has_discount'],
		);
	}

	/**
	 * Formatted recurring amount for a subscription, striking the original when a discount recurs.
	 *
	 * Produces the same `<del>` / `<ins>` shape the cart's recurring totals use, so a customer
	 * sees one consistent treatment of a recurring discount from cart through to order details.
	 * Use `get_subscription_recurring_price_text()` anywhere the output may reach a plain-text
	 * context, such as an email that renders in both HTML and plain.
	 *
	 * @param int                 $subscription_id Subscription ID.
	 * @param \WC_Order_Item|null $order_item      Source order item.
	 * @param array               $args            Optional. 'del_style' is an inline style for the struck-through
	 *                                             amount — email clients strip stylesheets, so email callers
	 *                                             must pass one.
	 *
	 * @return string|false Formatted price, or false when the order item has no subscription meta.
	 */
	public static function get_subscription_recurring_price_html( $subscription_id, $order_item = null, $args = array() ) {
		$parts = self::get_subscription_recurring_price_parts( $subscription_id, $order_item );

		if ( ! $parts ) {
			return false;
		}

		if ( ! $parts['has_discount'] ) {
			return $parts['discounted'];
		}

		$del_style      = $args['del_style'] ?? '';
		$del_attributes = $del_style ? ' style="' . esc_attr( $del_style ) . '"' : '';

		return '<del aria-hidden="true"' . $del_attributes . '>' . $parts['full'] . '</del> <ins>' . $parts['discounted'] . '</ins>';
	}

	/**
	 * Formatted recurring amount for a subscription, as markup-free text.
	 *
	 * For contexts that cannot render `<del>` — plain-text emails above all, where stripping the
	 * tags would leave two bare amounts side by side and no way to tell which is charged.
	 *
	 * @param int                 $subscription_id Subscription ID.
	 * @param \WC_Order_Item|null $order_item      Source order item.
	 *
	 * @return string|false Formatted price, or false when the order item has no subscription meta.
	 */
	public static function get_subscription_recurring_price_text( $subscription_id, $order_item = null ) {
		$parts = self::get_subscription_recurring_price_parts( $subscription_id, $order_item );

		if ( ! $parts ) {
			return false;
		}

		$discounted = wp_strip_all_tags( $parts['discounted'] );

		if ( ! $parts['has_discount'] ) {
			return $discounted;
		}

		return sprintf(
			// translators: 1: discounted recurring amount, 2: original amount before the discount.
			__( '%1$s (discounted from %2$s)', 'subscription' ),
			$discounted,
			wp_strip_all_tags( $parts['full'] )
		);
	}

	/**
	 * Get recurrings items from cart items.
	 *
	 * @param array $cart_items Cart items.
	 *
	 * @return array
	 */
	public static function get_recurrs_from_cart( $cart_items ) {
		$recurrs = array();
		foreach ( $cart_items as $key => $cart_item ) {
			$product = $cart_item['data'];
			if ( $product->is_type( 'simple' ) && isset( $cart_item['subscription'] ) ) {
				$cart_subscription = $cart_item['subscription'];
				// Cadence word must respect the frequency (plan items store the raw
				// plural interval, e.g. "months"): singular for 1, plural + count above.
				$sub_time   = max( 1, (int) ( $cart_subscription['time'] ?? 1 ) );
				$type       = ( 1 === $sub_time ? '' : $sub_time . ' ' ) . ucfirst( self::get_typos( $sub_time, $cart_subscription['type'] ) );
				$price_data = self::build_cart_recurring_price_data( $cart_item, $key, $type );

				$recurrs[ $key ] = array_merge(
					$price_data,
					array(
						'trial_status'    => ! is_null( $cart_subscription['trial'] ),
						'start_date'      => self::start_date( $cart_subscription['trial'] ),
						'next_date'       => self::next_date( ( $cart_subscription['time'] ?? 1 ) . ' ' . $cart_subscription['type'], $cart_subscription['trial'] ),
						'can_user_cancel' => $cart_item['data']->get_meta( '_subscrpt_user_cancel' ),
						'max_no_payment'  => ! empty( $cart_item['subscrpt_max_no_payment'] )
							? (int) $cart_item['subscrpt_max_no_payment']
							: $cart_item['data']->get_meta( '_subscrpt_max_no_payment' ),
						// Exact plan total for split items (the entered price the split is
						// divided from); null for classic split items which have no plan total.
						'split_total'     => isset( $cart_item['subscrpt_split_total'] ) ? (float) $cart_item['subscrpt_split_total'] : null,
						'quantity'        => (int) $cart_item['quantity'],
					)
				);
			}
		}

		return apply_filters( 'wpsubs_cart_recurring_items', $recurrs, $cart_items );
	}

	/**
	 * Check if the order has subscription item.
	 *
	 * @param \WC_Order|int $order Order object.
	 */
	public static function order_has_subscription_item( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$is_subscription_order = false;
		foreach ( $order->get_items() as $item ) {
			$item_data         = $item->get_data() ?? array();
			$item_product_id   = $item_data['product_id'] ?? 0;
			$item_variation_id = $item_data['variation_id'] ?? 0;

			$product_id = $item_variation_id ? $item_variation_id : $item_product_id;
			$product    = Subscription::get_subs_product( $product_id );

			if ( $product && $product->is_enabled() ) {
				$is_subscription_order = true;
				break;
			}
		}
		return $is_subscription_order;
	}

	/**
	 * Create renewal order when subscription expired. [wip]
	 *
	 * @param  int $subscription_id Subscription ID.
	 * @return false|\WC_Order Renewal order object or false on failure.
	 * @throws \WC_Data_Exception Exception.
	 * @throws \Exception Exception.
	 */
	public static function create_renewal_order( $subscription_id ) {
		// Check if maximum payment limit has been reached
		if ( subscrpt_is_max_payments_reached( $subscription_id ) ) {
			// Mark subscription as expired due to limit reached
			Action::status( 'expired', $subscription_id );

			error_log( "WPS: Maximum payment limit reached for subscription #{$subscription_id}. No renewal order created." );
			return false;
		}

		$order_item_id = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
		$order_id      = wc_get_order_id_by_order_item_id( $order_item_id );
		$old_order     = self::check_order_for_renewal( $order_id );

		if ( ! $old_order ) {
			// The stored item may belong to a trashed or non-completed order (e.g. a renewal that was deleted). Walk the relation table newest-first to find the last completed order we can use as the renewal source.
			foreach ( self::get_related_orders( $subscription_id ) as $row ) {
				$candidate = wc_get_order( (int) ( $row->order_id ?? 0 ) );
				if ( $candidate && 'completed' === $candidate->get_status() ) {
					$old_order     = $candidate;
					$order_item_id = (int) ( $row->order_item_id ?? 0 );
					break;
				}
			}
		}

		if ( ! $old_order ) {
			subscrpt_write_log( "Old order not found for renewal. Skipping creating renewal order. [ Subscription ID: {$subscription_id} ]" );
			return false;
		}

		$order_item         = $old_order->get_item( $order_item_id );
		$subscription_price = (float) get_post_meta( $subscription_id, '_subscrpt_price', true );
		$qty                = $order_item->get_quantity();

		// Subtract tax from per-unit subscription price if prices include tax. WC_Order will calculate tax on line total.
		if ( wc_prices_include_tax() ) {
			$product            = ( $order_item instanceof \WC_Order_Item_Product ) ? $order_item->get_product() : null;
			$tax_class          = $product ? $product->get_tax_class() : '';
			$tax_rates          = \WC_Tax::get_rates( $tax_class );
			$taxes              = \WC_Tax::calc_inclusive_tax( $subscription_price, $tax_rates );
			$subscription_price = $subscription_price - array_sum( $taxes );
		}

		$line_total   = $subscription_price * $qty;
		$product_args = array(
			'name'     => $order_item->get_name(),
			'subtotal' => $line_total,
			'total'    => $line_total,
		);

		// creating new order.
		$new_order_data = self::create_new_order_for_renewal( $old_order, $order_item, $product_args );
		if ( ! $new_order_data ) {
			subscrpt_write_log( "Failed to create renewal order. [ Subscription ID: {$subscription_id} ]" );
			return false;
		}
		$new_order         = $new_order_data['order'];
		$new_order_item_id = $new_order_data['order_item_id'];

		self::create_renewal_history( $subscription_id, $new_order->get_id(), $new_order_item_id );
		update_post_meta( $subscription_id, '_subscrpt_order_id', $new_order->get_id() );
		update_post_meta( $subscription_id, '_subscrpt_order_item_id', $new_order_item_id );

		self::clone_order_metadata( $new_order, $old_order );

		// Allow modification of the renewal order before saving.
		$new_order = apply_filters( 'subscrpt_before_saving_renewal_order', $new_order, $old_order, $subscription_id );

		// Save the new order.
		$new_order->calculate_totals();
		$new_order->save();

		if ( ! is_admin() && function_exists( 'wc_add_notice' ) && WC()->session ) {
			$message = 'Renewal Order(#' . $new_order->get_id() . ') Created.';
			if ( $new_order->has_status( 'pending' ) ) {
				$message .= 'Please <a href="' . $new_order->get_checkout_payment_url() . '">Pay now</a>';
			}
			wc_add_notice( $message, 'success' );
		}

		do_action( 'subscrpt_after_create_renew_order', $new_order, $old_order, $subscription_id, false );

		return $new_order;
	}

	/**
	 * Get subscription total price.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return float
	 */
	public static function get_subscription_total( $subscription_id ) {
		return (float) get_post_meta( $subscription_id, '_subscrpt_price', true );
	}

	/**
	 * Get subscription status.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return string
	 */
	public static function get_subscription_status( $subscription_id ) {
		return get_post_status( $subscription_id );
	}

	/**
	 * Check if subscription has status.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $status Status to check.
	 * @return bool
	 */
	public static function subscription_has_status( $subscription_id, $status ) {
		return self::get_subscription_status( $subscription_id ) === $status;
	}

	/**
	 * Check if subscription needs payment.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return bool
	 */
	public static function subscription_needs_payment( $subscription_id ) {
		return true; // Always true for now
	}

	/**
	 * Get product period (timing option).
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_product_period( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? $product->get_meta( '_subscrpt_timing_option' ) : '';
	}

	/**
	 * Get product interval (timing per).
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function get_product_interval( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? (int) $product->get_meta( '_subscrpt_timing_per' ) : 1;
	}

	/**
	 * Get product length (max payments).
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function get_product_length( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? (int) $product->get_meta( '_subscrpt_max_no_payment' ) : 0;
	}

	/**
	 * Get product trial length.
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function get_product_trial_length( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? (int) $product->get_meta( '_subscrpt_trial_timing_per' ) : 0;
	}

	/**
	 * Get product signup fee.
	 *
	 * @param int $product_id Product ID.
	 * @return float
	 */
	public static function get_product_signup_fee( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? (float) $product->get_meta( '_subscrpt_signup_fee' ) : 0.0;
	}

	/**
	 * Get first renewal payment time.
	 *
	 * @param int $product_id Product ID.
	 * @return int Timestamp
	 */
	public static function get_first_renewal_payment_time( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return 0;
		}

		$trial_period = $product->get_meta( '_subscrpt_trial_timing_per' );
		$trial_option = $product->get_meta( '_subscrpt_trial_timing_option' );

		if ( ! empty( $trial_period ) && ! empty( $trial_option ) ) {
			return strtotime( "+{$trial_period} {$trial_option}" );
		}

		return 0;
	}

	/**
	 * Update subscription next payment date.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $new_date New Date string.
	 * @return void
	 */
	public static function update_subscription_next_payment_date( $subscription_id, $new_date ) {
		update_post_meta( $subscription_id, '_subscrpt_next_date', strtotime( $new_date ) );
	}

	/**
	 * Cancel subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public static function cancel_subscription( $subscription_id ) {
		Action::status( 'cancelled', $subscription_id );
	}

	/**
	 * Pause subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public static function pause_subscription( $subscription_id ) {
		Action::status( 'on-hold', $subscription_id );
	}

	/**
	 * Resume subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public static function resume_subscription( $subscription_id ) {
		Action::status( 'active', $subscription_id );
	}

	/**
	 * Mark subscription payment as complete.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $payment_id Payment/Transaction ID.
	 * @return void
	 */
	public static function subscription_payment_complete( $subscription_id, $payment_id ) {
		if ( 'active' !== get_post_status( $subscription_id ) ) {
			Action::status( 'active', $subscription_id );
		}

		// Allow payment gateways to add their own comments/notes
		do_action( 'subscrpt_subscription_payment_completed', $subscription_id, $payment_id );
	}

	/**
	 * Clone stripe metadata from old order.
	 *
	 * @param int       $subscription_id Subscription Id.
	 * @param \WC_Order $old_order Old Order Object.
	 * @param \WC_Order $new_order New Order Object.
	 *
	 * @return void
	 */
	public static function clone_stripe_metadata_for_renewal( $subscription_id, $old_order, $new_order ) {
		$is_auto_renew = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		if ( empty( $is_auto_renew ) && subscrpt_is_auto_renew_enabled() ) {
			$is_auto_renew = true;
			update_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		}

		$is_auto_renew = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		$is_auto_renew = in_array( $is_auto_renew, array( 1, '1' ), true );

		$is_global_auto_renew = get_option( 'wp_subscription_stripe_auto_renew', '1' );
		$is_global_auto_renew = in_array( $is_global_auto_renew, array( 1, '1' ), true );

		$stripe_supported_methods = Stripe::WPSUBS_SUPPORTED_METHODS;
		$old_method               = $old_order->get_payment_method();
		$is_stripe_pm             = ! empty( $old_method ) && in_array( $old_method, $stripe_supported_methods, true );

		$has_stripe_meta = ! empty( $old_order->get_meta( '_stripe_customer_id' ) ) || ! empty( $old_order->get_meta( '_stripe_source_id' ) );

		$stripe_enabled = ( ( $is_stripe_pm || $has_stripe_meta ) && $is_auto_renew && $is_global_auto_renew && subscrpt_is_auto_renew_enabled() );

		if ( $stripe_enabled ) {
			$new_order->update_meta_data( '_stripe_customer_id', $old_order->get_meta( '_stripe_customer_id' ) );
			$new_order->update_meta_data( '_stripe_source_id', $old_order->get_meta( '_stripe_source_id' ) );
			$new_order->set_payment_method( $old_order->get_payment_method() );
			$new_order->set_payment_method_title( $old_order->get_payment_method_title() );

			// Add debug log.
			subscrpt_write_debug_log( "Stripe metadata cloned for renewal order #{$new_order->get_id()} from old order #{$old_order->get_id()}" );
		} else {
			subscrpt_write_log( "Stripe metadata not processed. Auto renewal may fail. [ Renewal order #{$new_order->get_id()}, Old order #{$old_order->get_id()} ]" );
			subscrpt_write_debug_log( "Stripe metadata did not clone for renewal order #{$new_order->get_id()} from old order #{$old_order->get_id()}" );
		}
	}

	/**
	 * Create history for renewal.
	 *
	 * @param int $subscription_id Subscription Id.
	 * @param int $new_order_id New Order Id.
	 * @param int $new_order_item_id New Order Item Id.
	 *
	 * @return void
	 */
	public static function create_renewal_history( $subscription_id, $new_order_id, $new_order_item_id ) {
		global $wpdb;
		$history_table = $wpdb->prefix . 'subscrpt_order_relation';
		$wpdb->insert(
			$history_table,
			array(
				'subscription_id' => $subscription_id,
				'order_id'        => $new_order_id,
				'order_item_id'   => $new_order_item_id,
				'type'            => 'renew',
			)
		);

		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => sprintf( 'Subscription Renewal order successfully created. Order #%s', $new_order_id ),
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Renewal Order' );
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'renewal_order' );
	}

	/**
	 * Get a subscription data.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return array|null
	 */
	public static function get_subscription_data( int $subscription_id ): ?array {
		if ( empty( get_post_meta( $subscription_id ) ) ) {
			return null;
		}

		$subs_post = get_post( $subscription_id );
		$user_id   = ! empty( $subs_post ) ? (int) $subs_post->post_author : 0;

		$product_id = get_post_meta( $subscription_id, '_subscrpt_product_id', true );
		$product_id = ! empty( $product_id ) ? (int) $product_id : 0;

		$variation_id = get_post_meta( $subscription_id, '_subscrpt_variation_id', true );
		$variation_id = ! empty( $variation_id ) ? (int) $variation_id : 0;

		$chk_product_id = $variation_id ? $variation_id : $product_id;

		$status = get_post_status( $subscription_id ); // pending, active, cancelled, pe_cancelled, expired
		$price  = get_post_meta( $subscription_id, '_subscrpt_price', true );

		$signup_fee = get_post_meta( $subscription_id, '_subscrpt_signup_fee', true );
		$signup_fee = ! empty( $signup_fee ) ? $signup_fee : 0;

		$order_id      = get_post_meta( $subscription_id, '_subscrpt_order_id', true );
		$order_item_id = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );

		$can_user_cancel = in_array( get_post_meta( $subscription_id, '_subscrpt_user_cancel', true ), array( 1, '1', 'true', 'yes' ), true );

		$start_datetime = (int) get_post_meta( $subscription_id, '_subscrpt_start_date', true );
		$start_date     = ! empty( $start_datetime ) ? gmdate( DATE_RFC2822, $start_datetime ) : null;

		$next_datetime = (int) get_post_meta( $subscription_id, '_subscrpt_next_date', true );
		$next_date     = ! empty( $next_datetime ) ? gmdate( DATE_RFC2822, $next_datetime ) : null;

		$timing_per = get_post_meta( $subscription_id, '_subscrpt_timing_per', true );
		$timing_per = empty( $timing_per ) ? get_post_meta( $chk_product_id, '_subscrpt_timing_per', true ) : $timing_per;

		$timing_option = get_post_meta( $subscription_id, '_subscrpt_timing_option', true );
		$timing_option = empty( $timing_option ) ? get_post_meta( $chk_product_id, '_subscrpt_timing_option', true ) : $timing_option;

		$trial_timing_per = get_post_meta( $subscription_id, '_subscrpt_trial_timing_per', true );
		$trial_timing_per = empty( $trial_timing_per ) ? get_post_meta( $chk_product_id, '_subscrpt_trial_timing_per', true ) : $trial_timing_per;

		$trial_timing_option = get_post_meta( $subscription_id, '_subscrpt_trial_timing_option', true );
		$trial_timing_option = empty( $trial_timing_option ) ? get_post_meta( $chk_product_id, '_subscrpt_trial_timing_option', true ) : $trial_timing_option;

		$is_auto_renew = in_array( get_post_meta( $subscription_id, '_subscrpt_auto_renew', true ), array( 1, '1', 'true', 'yes' ), true );
		$is_auto_renew = ! empty( $is_auto_renew ) ? $is_auto_renew : subscrpt_is_auto_renew_enabled();

		$default_grace_period = (int) get_option( 'subscrpt_default_payment_grace_period', '7' );
		$default_grace_period = subscrpt_pro_activated() ? $default_grace_period : 0;
		$grace_end_datetime   = $next_datetime + ( $default_grace_period * DAY_IN_SECONDS );
		$grace_end_date       = gmdate( DATE_RFC2822, $grace_end_datetime );
		$grace_remaining_days = ceil( max( 0, $grace_end_datetime - time() ) / DAY_IN_SECONDS );

		$subscription_data = array(
			'id'              => $subscription_id,
			'status'          => $status,
			'schedule'        => array(
				'timing_per'    => $timing_per,
				'timing_option' => $timing_option,
			),
			'price'           => $price,
			'signup_fee'      => $signup_fee,
			'start_date'      => $start_date,
			'next_date'       => $next_date,
			'product'         => array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
			),
			'order'           => array(
				'order_id'      => $order_id,
				'order_item_id' => $order_item_id,
			),
			'can_user_cancel' => $can_user_cancel,
			'is_auto_renew'   => (bool) $is_auto_renew,
			'user_id'         => $user_id,
		);

		if ( ! empty( $trial_timing_per ) ) {
			$subscription_data['trial'] = array(
				'timing_per'    => $trial_timing_per,
				'timing_option' => $trial_timing_option,
			);
		}

		if (
			! in_array( strtolower( $status ), array( 'cancelled', 'pending', 'completed' ), true )
			&& ! empty( $next_date )
			&& $next_datetime - time() <= 0
			&& (int) $default_grace_period > 0
		) {
			$subscription_data['grace_period'] = array(
				'remaining_days' => $grace_remaining_days,
				'end_date'       => $grace_end_date,
			);
		}

		return $subscription_data;
	}

	/**
	 * Get related orders of a subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return array
	 */
	public static function get_related_orders( int $subscription_id ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';

		// @phpcs:ignore
		$order_histories = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT order_id, order_item_id, type FROM %i WHERE subscription_id=%d ORDER BY id DESC',
				array(
					$table_name,
					$subscription_id,
				)
			)
		);

		return $order_histories;
	}

	/**
	 * Get parent order from subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public static function get_parent_order( int $subscription_id ) {
		$related_orders = self::get_related_orders( $subscription_id );
		$last_order     = end( $related_orders );

		if ( ! $last_order || strtolower( $last_order->type ?? '' ) !== 'new' ) {
			foreach ( $related_orders as $order ) {
				if ( strtolower( $order->type ?? '' ) === 'new' ) {
					$last_order = $order;
					break;
				}
			}
		}

		$parent_order_id = $last_order->order_id ?? 0;
		$parent_order    = wc_get_order( $parent_order_id );
		return $parent_order;
	}

	/**
	 * Create new order for renewal.
	 *
	 * @param \WC_Order              $old_order Old Order Object.
	 * @param \WC_Order_Item_Product $order_item Old Order Item Object.
	 * @param array                  $product_args Product args for add product.
	 *
	 * @return array|false
	 */
	public static function create_new_order_for_renewal( \WC_Order $old_order, \WC_Order_Item_Product $order_item, array $product_args ) {
		$product      = $order_item->get_product();
		$user_id      = $old_order->get_user_id();
		$new_order    = wc_create_order(
			array(
				'customer_id' => $user_id,
				'status'      => 'pending',
			)
		);
		$product_meta = apply_filters( 'subscrpt_renewal_item_meta', wc_get_order_item_meta( $order_item->get_id(), '_subscrpt_meta', true ), $product, $order_item );
		$product_args = apply_filters( 'subscrpt_renewal_product_args', $product_args, $product, $order_item );
		if ( ! $product_args ) {
			return false;
		}

		$new_order_item_id = $new_order->add_product(
			$product,
			$order_item->get_quantity(),
			$product_args
		);
		wc_update_order_item_meta(
			$new_order_item_id,
			'_subscrpt_meta',
			array(
				'time'  => $product_meta['time'],
				'type'  => $product_meta['type'],
				'trial' => null,
			)
		);

		// Add debug log.
		subscrpt_write_debug_log( "Renewal order #{$new_order->get_id()} created for old order #{$old_order->get_id()}" );

		return array(
			'order'         => $new_order,
			'order_item_id' => $new_order_item_id,
		);
	}

	/**
	 * Check if old order is completed or deleted!
	 *
	 * @param mixed $old_order_id Old Order Id.
	 *
	 * @return \WC_Order|false
	 */
	public static function check_order_for_renewal( $old_order_id ) {
		$old_order = wc_get_order( $old_order_id );
		if ( ! $old_order || 'completed' !== $old_order->get_status() ) {
			if ( ! is_admin() && function_exists( 'wc_add_notice' ) && WC()->session ) {
				return wc_add_notice( __( 'Subscription renewal isn\'t possible due to previous order not completed or deletion.', 'subscription' ), 'error' );
			}
			return false;
		}

		return $old_order;
	}

	/**
	 * Get delivery info from order.
	 *
	 * @param \WC_Order $order Order object.
	 * @return array
	 */
	public static function get_delivery_info_from_order( \WC_Order $order ) {
		$customer_id = $order->get_customer_id();
		$customer    = new \WC_Customer( $customer_id );
		$email       = $customer->get_email();

		// Billing info (get from order first, if empty get from customer).
		$billing_first_name = ! empty( $order->get_billing_first_name() ) ? $order->get_billing_first_name() : $customer->get_billing_first_name();
		$billing_last_name  = ! empty( $order->get_billing_last_name() ) ? $order->get_billing_last_name() : $customer->get_billing_last_name();
		$billing_email      = ! empty( $order->get_billing_email() ) ? $order->get_billing_email() : $customer->get_billing_email();
		$billing_phone      = ! empty( $order->get_billing_phone() ) ? $order->get_billing_phone() : $customer->get_billing_phone();
		$billing_company    = ! empty( $order->get_billing_company() ) ? $order->get_billing_company() : $customer->get_billing_company();

		$billing_city      = ! empty( $order->get_billing_city() ) ? $order->get_billing_city() : $customer->get_billing_city();
		$billing_state     = ! empty( $order->get_billing_state() ) ? $order->get_billing_state() : $customer->get_billing_state();
		$billing_country   = ! empty( $order->get_billing_country() ) ? $order->get_billing_country() : $customer->get_billing_country();
		$billing_postcode  = ! empty( $order->get_billing_postcode() ) ? $order->get_billing_postcode() : $customer->get_billing_postcode();
		$billing_address_1 = ! empty( $order->get_billing_address_1() ) ? $order->get_billing_address_1() : $customer->get_billing_address_1();
		$billing_address_2 = ! empty( $order->get_billing_address_2() ) ? $order->get_billing_address_2() : $customer->get_billing_address_2();

		// Shipping info (get from order first, if empty get from customer).
		$shipping_first_name = ! empty( $order->get_shipping_first_name() ) ? $order->get_shipping_first_name() : $customer->get_shipping_first_name();
		$shipping_last_name  = ! empty( $order->get_shipping_last_name() ) ? $order->get_shipping_last_name() : $customer->get_shipping_last_name();
		$shipping_phone      = ! empty( $order->get_shipping_phone() ) ? $order->get_shipping_phone() : $customer->get_shipping_phone();
		$shipping_company    = ! empty( $order->get_shipping_company() ) ? $order->get_shipping_company() : $customer->get_shipping_company();

		$shipping_city      = ! empty( $order->get_shipping_city() ) ? $order->get_shipping_city() : $customer->get_shipping_city();
		$shipping_state     = ! empty( $order->get_shipping_state() ) ? $order->get_shipping_state() : $customer->get_shipping_state();
		$shipping_country   = ! empty( $order->get_shipping_country() ) ? $order->get_shipping_country() : $customer->get_shipping_country();
		$shipping_postcode  = ! empty( $order->get_shipping_postcode() ) ? $order->get_shipping_postcode() : $customer->get_shipping_postcode();
		$shipping_address_1 = ! empty( $order->get_shipping_address_1() ) ? $order->get_shipping_address_1() : $customer->get_shipping_address_1();
		$shipping_address_2 = ! empty( $order->get_shipping_address_2() ) ? $order->get_shipping_address_2() : $customer->get_shipping_address_2();

		$order_meta = [
			'customer_id' => $order->get_customer_id(),
			'email'       => $email,
			'billing'     => [
				'first_name' => $billing_first_name,
				'last_name'  => $billing_last_name,
				'email'      => $billing_email,
				'phone'      => $billing_phone,
				'company'    => $billing_company,
				'city'       => $billing_city,
				'state'      => $billing_state,
				'country'    => $billing_country,
				'postcode'   => $billing_postcode,
				'address_1'  => $billing_address_1,
				'address_2'  => $billing_address_2,
			],
			'shipping'    => [
				'first_name' => $shipping_first_name,
				'last_name'  => $shipping_last_name,
				'phone'      => $shipping_phone,
				'company'    => $shipping_company,
				'city'       => $shipping_city,
				'state'      => $shipping_state,
				'country'    => $shipping_country,
				'postcode'   => $shipping_postcode,
				'address_1'  => $shipping_address_1,
				'address_2'  => $shipping_address_2,
			],
		];

		return $order_meta;
	}

	/**
	 * Set delivery info to order.
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $order_meta Order meta data.
	 */
	public static function set_delivery_info_to_order( \WC_Order $order, array $order_meta ) {
		// Set Billing Info.
		$order->set_billing_first_name( $order_meta['billing']['first_name'] ?? '' );
		$order->set_billing_last_name( $order_meta['billing']['last_name'] ?? '' );
		$order->set_billing_email( $order_meta['billing']['email'] ?? '' );
		$order->set_billing_phone( $order_meta['billing']['phone'] ?? '' );
		$order->set_billing_company( $order_meta['billing']['company'] ?? '' );
		$order->set_billing_city( $order_meta['billing']['city'] ?? '' );
		$order->set_billing_state( $order_meta['billing']['state'] ?? '' );
		$order->set_billing_country( $order_meta['billing']['country'] ?? '' );
		$order->set_billing_postcode( $order_meta['billing']['postcode'] ?? '' );
		$order->set_billing_address_1( $order_meta['billing']['address_1'] ?? '' );
		$order->set_billing_address_2( $order_meta['billing']['address_2'] ?? '' );

		// Set Shipping Info.
		$order->set_shipping_first_name( $order_meta['shipping']['first_name'] ?? '' );
		$order->set_shipping_last_name( $order_meta['shipping']['last_name'] ?? '' );
		$order->set_shipping_phone( $order_meta['shipping']['phone'] ?? '' );
		$order->set_shipping_company( $order_meta['shipping']['company'] ?? '' );
		$order->set_shipping_city( $order_meta['shipping']['city'] ?? '' );
		$order->set_shipping_state( $order_meta['shipping']['state'] ?? '' );
		$order->set_shipping_country( $order_meta['shipping']['country'] ?? '' );
		$order->set_shipping_postcode( $order_meta['shipping']['postcode'] ?? '' );
		$order->set_shipping_address_1( $order_meta['shipping']['address_1'] ?? '' );
		$order->set_shipping_address_2( $order_meta['shipping']['address_2'] ?? '' );
	}

	/**
	 * Save meta-data from old order
	 *
	 * @param \WC_Order $new_order new order object.
	 * @param \WC_Order $old_order old order object.
	 *
	 * @return void
	 */
	public static function clone_order_metadata( $new_order, $old_order ) {
		// Set customer and currency info.
		$new_order->set_customer_id( $old_order->get_customer_id() );
		$new_order->set_currency( $old_order->get_currency() );

		// Get delivery info from old order.
		$order_meta = self::get_delivery_info_from_order( $old_order );

		// Check for any missing information.
		$missing_billing_info = true;
		foreach ( $order_meta['billing'] as $key => $value ) {
			if ( ! empty( $value ) ) {
				$missing_billing_info = false;
				break;
			}
		}
		$missing_shipping_info = true;
		foreach ( $order_meta['shipping'] as $key => $value ) {
			if ( ! empty( $value ) ) {
				$missing_shipping_info = false;
				break;
			}
		}
		$missing_info = $missing_billing_info || $missing_shipping_info;

		// Get info from the parent order if missing.
		if ( $missing_info ) {
			$subscription    = self::get_subscriptions_from_order( $old_order->get_id() );
			$subscription    = reset( $subscription );
			$subscription_id = ! empty( $subscription ) ? $subscription->subscription_id : 0;

			subscrpt_write_log( "Missing delivery info in old order #{$old_order->get_id()} for subscription #{$subscription_id}. Trying to get from parent order." );

			$parent_order = self::get_parent_order( $subscription_id );
			if ( ! empty( $parent_order ) ) {
				$order_meta = self::get_delivery_info_from_order( $parent_order );
			}
		}

		// Set delivery info to new order.
		self::set_delivery_info_to_order( $new_order, $order_meta );
	}
}

// HPOS: All order data access below uses WooCommerce CRUD and is HPOS compatible.
