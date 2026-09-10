<?php
/**
 * Global helper functions.
 *
 * @package SpringDevs\Subscription
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;
use SpringDevs\Subscription\Utils\Product;

/**
 * Generate URL for Subscription Action.
 *
 * @param string $action Action.
 * @param string $nonce nonce.
 * @param int    $subscription_id Subscription ID.
 *
 * @return string
 */
function subscrpt_get_action_url( $action, $nonce, $subscription_id ) {
	$view_subscription_endpoint = Subscription::get_user_endpoint( 'view_subs' );
	return add_query_arg(
		array(
			'subscrpt_id' => $subscription_id,
			'action'      => $action,
			'wpnonce'     => $nonce,
		),
		wc_get_endpoint_url( $view_subscription_endpoint, $subscription_id, wc_get_page_permalink( 'myaccount' ) )
	);
}


/**
 * Get typos.
 *
 * @param int    $number Number.
 * @param string $typo   Typo.
 *
 * @return string
 */
function subscrpt_get_typos( $number, $typo ) {
	if ( $number == 1 && $typo == 'days' ) {
		return ucfirst( __( 'day', 'subscription' ) );
	} elseif ( $number == 1 && $typo == 'weeks' ) {
		return ucfirst( __( 'week', 'subscription' ) );
	} elseif ( $number == 1 && $typo == 'months' ) {
		return ucfirst( __( 'month', 'subscription' ) );
	} elseif ( $number == 1 && $typo == 'years' ) {
		return ucfirst( __( 'year', 'subscription' ) );
	} else {
		return ucfirst( $typo );
	}
}

/**
 * Format time with trial.
 *
 * @param mixed       $time Time.
 * @param null|string $trial Trial.
 *
 * @return string
 */
function subscrpt_next_date( $time, $trial = null ) {
	if ( null === $trial ) {
		$start_date = time();
	} else {
		$start_date = strtotime( $trial );
	}

	return gmdate( 'F d, Y', strtotime( $time, $start_date ) );
}

/**
 * Check if subscription-pro activated.
 *
 * @return bool
 */
function subscrpt_pro_activated(): bool {
	return class_exists( 'Sdevs_Wc_Subscription_Pro' );
}

/**
 * Whether a product is tied to at least one active subscription plan.
 *
 * The single fallback guard every surface (storefront, checkout, admin) branches
 * on: when this returns false, code must fall back to the classic `_subscrpt_*`
 * per-product meta and must not read or write any plan table. Keeps plan
 * detection consistent so no surface invents its own.
 *
 * @param int $product_id   Product (parent) id.
 * @param int $variation_id Variation id, or 0 for simple products.
 *
 * @return bool
 */
function subscrpt_product_has_plan( $product_id, $variation_id = 0 ): bool {
	return ! empty(
		\SpringDevs\Subscription\Illuminate\Plans\PlanRepository::resolve_for_product( $product_id, $variation_id )
	);
}

/**
 * Whether a product / variation is actually offered as a subscription on the
 * storefront: it must be tied to a plan AND be subscription-enabled
 * (`_subscrpt_enabled`) on the exact entity — the variation when a variation id
 * is given, otherwise the product. Storefront surfaces (plan selector, plan
 * price, variation visibility) branch on this so a plan-tied but disabled
 * product / variation shows no subscription UI at all.
 *
 * @param int $product_id   Product (parent) id.
 * @param int $variation_id Variation id, or 0 for simple products.
 *
 * @return bool
 */
function subscrpt_plan_offered( $product_id, $variation_id = 0 ): bool {
	if ( ! subscrpt_product_has_plan( $product_id, $variation_id ) ) {
		return false;
	}

	return subscrpt_is_subscription_enabled( $product_id, $variation_id );
}

/**
 * Whether a product / variation is subscription-enabled (`_subscrpt_enabled`).
 *
 * When the enable meta was never explicitly saved, a connected plan turns the
 * subscription on by default — so attaching a plan enables it automatically, and
 * it stays on until a save explicitly clears the toggle (an empty saved value).
 * With no plan and no saved meta it is off (a fresh product defaults to off).
 *
 * @param int $product_id   Product (parent) id.
 * @param int $variation_id Variation id, or 0 for simple products.
 *
 * @return bool
 */
function subscrpt_is_subscription_enabled( $product_id, $variation_id = 0 ): bool {
	$entity_id = $variation_id ? (int) $variation_id : (int) $product_id;

	if ( metadata_exists( 'post', $entity_id, '_subscrpt_enabled' ) ) {
		return ! empty( get_post_meta( $entity_id, '_subscrpt_enabled', true ) );
	}

	// Never explicitly set: a connected plan enables the subscription by default.
	return subscrpt_product_has_plan( $product_id, $variation_id );
}

/**
 * Discount badge text for a storefront plan selector card.
 *
 * The single source both selectors share, so free and Pro word a discount
 * identically. Returning an empty string from the filter hides the badge.
 *
 * @param array       $group   Plan group (id, type, label, terms, discount_percent, …).
 * @param \WC_Product $product Product or variation being rendered.
 * @param int         $percent The group's best discount percentage.
 * @param bool        $varying Whether the group's terms discount by differing
 *                             amounts, in which case the badge reads "up to".
 *
 * @return string
 */
function subscrpt_card_badge_text( $group, $product, $percent = 0, $varying = false ) {
	if ( $percent > 0 ) {
		$default = $varying
			/* translators: %d: discount percentage. */
			? sprintf( __( 'Save up to %d%%', 'subscription' ), $percent )
			/* translators: %d: discount percentage. */
			: sprintf( __( 'Save %d%%', 'subscription' ), $percent );
	} else {
		$default = __( 'Sale', 'subscription' );
	}

	/**
	 * Filters the discount badge text on a storefront plan selector card.
	 *
	 * @param string      $text    Badge text (empty string hides the badge).
	 * @param array       $group   The plan group (id, type, label, terms, discount_percent, …).
	 * @param \WC_Product $product Product or variation being rendered.
	 * @param int         $percent Computed discount percentage for the group.
	 */
	return (string) apply_filters( 'subscrpt_plan_card_badge', $default, $group, $product, $percent );
}

/**
 * Build the storefront One-Time Purchase card for a product or variation.
 *
 * Offered only when the merchant opted in on this exact product or variation:
 * `_subscrpt_one_time_enabled` is stored per variation, so pass the variation
 * itself, never its parent, whose flag only means "any variation enabled".
 *
 * The single source of the one-time price maths. Both selectors call it so the
 * free and Pro storefronts can never disagree on a price; Pro layers its
 * discount badge onto the returned group rather than recomputing anything.
 *
 * @param \WC_Product $product Product or variation.
 *
 * @return array|null Selector group in plan-selector.php shape, or null when
 *                    one-time purchase is not offered for this product.
 */
function subscrpt_one_time_group( $product ) {
	if ( ! $product instanceof \WC_Product || ! function_exists( 'wc_price' ) ) {
		return null;
	}

	if ( 'yes' !== get_post_meta( $product->get_id(), '_subscrpt_one_time_enabled', true ) ) {
		return null;
	}

	$regular = (float) $product->get_regular_price();
	$sale    = $product->get_sale_price();
	$price   = '' !== $sale ? (float) $sale : $regular;

	// Strike the regular price through only when one-time is genuinely on sale.
	$old_price = ( '' !== $sale && (float) $sale < $regular ) ? wc_price( $regular ) : '';
	$percent   = ( '' !== $old_price && $regular > 0 )
		? (int) round( ( $regular - $price ) / $regular * 100 )
		: 0;

	$group = array(
		'id'               => 'one_time',
		'type'             => 'one_time',
		'label'            => __( 'One Time Purchase', 'subscription' ),
		'price'            => wc_price( $price ),
		'old_price'        => $old_price,
		'terms'            => array(),
		'note'             => '',
		'badge'            => '',
		'discount_percent' => $percent,
	);

	if ( $percent > 0 ) {
		$group['badge'] = subscrpt_card_badge_text( $group, $product, $percent, false );
	}

	return $group;
}

/**
 * Truncate a string to a max length, appending an ellipsis when shortened.
 *
 * Multibyte-safe. Returns the text unchanged when it is within the limit, so
 * callers can compare the result to the original to detect truncation (e.g. to
 * add a title attribute with the full text).
 *
 * @param string $text   Text to truncate.
 * @param int    $length Maximum length before truncation. Default 30.
 *
 * @return string
 */
function subscrpt_truncate_text( $text, $length = 30 ) {
	$text = (string) $text;
	return mb_strlen( $text ) > $length ? mb_substr( $text, 0, $length ) . '…' : $text;
}

/**
 * Resolve a setting that was renamed without its readers being updated.
 *
 * Commit d4719e1 ("changed SUBSCRPT to WP_SUBSCRIPTION") renamed six option ids
 * inside Admin/Settings.php and touched no reader. Four were caught later; two
 * were not, so since 2025-05-08 the settings screen has been writing
 * `wp_subscription_*` while the code kept reading `subscrpt_*` — the saved value
 * never reached the feature, and the feature's default never reached the screen.
 *
 * Reading both names is what makes the two agree again. It is deliberately a
 * read and not a migration: `subscrpt_is_auto_renew_enabled()` is called from
 * the Stripe gateway and the renewal actions, and an option write on that path
 * to fix a display problem is a bad trade. A site that saves its settings once
 * writes the current name and never consults the legacy one again.
 *
 * @param string $option        Current option name.
 * @param string $legacy_option Name used before the rename.
 * @param mixed  $default_value Value when neither is set.
 * @return mixed
 */
function subscrpt_get_renamed_option( $option, $legacy_option, $default_value = '' ) {
	$value = get_option( $option, '' );

	if ( '' !== $value && false !== $value && null !== $value ) {
		return $value;
	}

	return get_option( $legacy_option, $default_value );
}

/**
 * Get renewal process settings.
 *
 * Must be used everywhere the renewal process is read, including the settings
 * field itself — if the screen resolved the value differently from the code it
 * would show "Automatic" to a site that is in fact set to manual.
 *
 * @return string 'auto' or 'manual'.
 */
function subscrpt_get_renewal_process() {
	return (string) subscrpt_get_renamed_option( 'wp_subscription_renewal_process', 'subscrpt_renewal_process', 'auto' );
}

/**
 * Notice shown when a manual renewal puts the product in the cart.
 *
 * @return string
 */
function subscrpt_get_manual_renew_cart_notice() {
	return (string) subscrpt_get_renamed_option( 'wp_subscription_manual_renew_cart_notice', 'subscrpt_manual_renew_cart_notice', '' );
}

/**
 * Get renewal process settings.
 *
 * @return bool
 */
function subscrpt_is_auto_renew_enabled() {
	return 'auto' === subscrpt_get_renewal_process();
}

/**
 * Split-payment amounts for a given total and installment count.
 *
 * Single source of truth for split math so the product page, cart, checkout and
 * subscription always agree:
 *   - per_installment = total / count, rounded UP to 2 decimals (ceil)
 *   - total           = the price exactly as entered (never per × count)
 *
 * @param float|string $total Total price as entered on the plan/product.
 * @param int          $count Number of installments (minimum 1).
 * @return array{total:float,count:int,per_installment:float}
 */
function subscrpt_split_amounts( $total, $count ) {
	$total = (float) $total;
	$count = max( 1, (int) $count );

	return array(
		'total'           => $total,
		'count'           => $count,
		'per_installment' => ceil( $total / $count * 100 ) / 100,
	);
}

/**
 * Get maximum payments for a subscription, checking variation, product, and subscription meta.
 *
 * @param int $subscription_id Subscription ID.
 * @return string|int Maximum payments or empty string if not set.
 */
function subscrpt_get_max_payments( $subscription_id ) {
	$product_id = get_post_meta( $subscription_id, '_subscrpt_product_id', true );
	if ( ! $product_id ) {
		return '';
	}

	$max_payments = null;

	// Check for variation first
	$variation_id = get_post_meta( $subscription_id, '_subscrpt_variation_id', true );
	if ( $variation_id ) {
		$max_payments = get_post_meta( $variation_id, '_subscrpt_max_no_payment', true );
	}

	// Fallback to product if variation doesn't have max payments or no variation
	if ( ! $max_payments ) {
		$max_payments = get_post_meta( $product_id, '_subscrpt_max_no_payment', true );
	}

	// Also check subscription's own meta data as final fallback
	if ( ! $max_payments ) {
		$max_payments = get_post_meta( $subscription_id, '_subscrpt_max_no_payment', true );
	}

	return $max_payments ? $max_payments : '';
}

/**
 * Count total payments made.
 *
 * @param int $subscription_id Subscription ID.
 * @return int Number of payments made.
 */
function subscrpt_count_payments_made( $subscription_id ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'subscrpt_order_relation';

	// Query the relation table only. Joining wp_posts would drop every row under
	// HPOS (orders are not stored there); wc_get_order() below is HPOS-safe.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$relations = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $table_name WHERE subscription_id = %d ORDER BY id ASC",
			$subscription_id
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Define all payment-related order types (allow filtering for extensibility)
	$payment_types = apply_filters( 'subscrpt_payment_order_types', array( 'new', 'renew', 'early-renew' ) );

	// Count successful payments
	$successful_count = 0;
	foreach ( $relations as $relation ) {
		// Count all payment-related types
		if ( in_array( $relation->type, $payment_types ) ) {
			// Get the actual WooCommerce order
			$order = wc_get_order( $relation->order_id );
			if ( $order ) {
				// Check if order was paid/successful
				if ( $order->is_paid() || in_array( $order->get_status(), array( 'completed', 'processing', 'on-hold' ) ) ) {
					++$successful_count;
				}
			}
		}
	}

	return $successful_count;
}

/**
 * Check if subscription has reached its maximum payment limit.
 *
 * @param int $subscription_id Subscription ID.
 * @return bool True if limit reached, false otherwise.
 */
function subscrpt_is_max_payments_reached( $subscription_id ) {
	// Get the product ID from subscription
	$product_id = get_post_meta( $subscription_id, '_subscrpt_product_id', true );
	if ( ! $product_id ) {
		return false;
	}

	// Get maximum payments using helper function
	$max_payments = subscrpt_get_max_payments( $subscription_id );

	// Allow override of total installments
	$max_payments = apply_filters( 'subscrpt_split_payment_total_override', $max_payments, $subscription_id, $product_id );

	// If no limit set or unlimited, more payments are allowed
	if ( ! $max_payments || intval( $max_payments ) <= 0 ) {
		return false;
	}

	// Count payments made
	$payments_made = subscrpt_count_payments_made( $subscription_id );

	// Enhanced completion logic considering failed payments
	$is_reached = subscrpt_check_enhanced_completion( $subscription_id, $payments_made, $max_payments );

	// Fire action when split payment plan is completed (first time only)
	if ( $is_reached && ! get_post_meta( $subscription_id, '_subscrpt_split_payment_completed_fired', true ) ) {
		// All installments paid: complete the subscription (no renewal / expiry / grace).
		$expire_status = apply_filters( 'subscrpt_split_payment_expire_status', 'completed', $subscription_id, $payments_made, $max_payments );

		// Update subscription status if different from current
		$current_status = get_post_status( $subscription_id );
		if ( $current_status !== $expire_status ) {
			wp_update_post(
				array(
					'ID'          => $subscription_id,
					'post_status' => $expire_status,
				)
			);
		}

		// Clear the next date so cron never expires it into a grace period.
		delete_post_meta( $subscription_id, '_subscrpt_next_date' );

		do_action( 'subscrpt_split_payment_completed', $subscription_id, $payments_made, $max_payments );
		update_post_meta( $subscription_id, '_subscrpt_split_payment_completed_fired', true );

		// Handle split payment access timing if Pro version is active
		if ( function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated() ) {
			if ( class_exists( '\SpringDevs\SubscriptionPro\Illuminate\SplitPaymentHandler' ) ) {
				\SpringDevs\SubscriptionPro\Illuminate\SplitPaymentHandler::handle_split_payment_completion( $subscription_id, $payments_made, $max_payments );
			}
		}
	}

	return $is_reached;
}

/**
 * Get remaining payments for a subscription.
 *
 * @param int $subscription_id Subscription ID.
 * @return int|string Number of remaining payments or 'unlimited'.
 */
function subscrpt_get_remaining_payments( $subscription_id ) {
	// Get maximum payments using helper function
	$max_payments = subscrpt_get_max_payments( $subscription_id );

	// If no limit set or unlimited
	if ( ! $max_payments || intval( $max_payments ) <= 0 ) {
		return 'unlimited';
	}

	// Count payments made
	$payments_made = subscrpt_count_payments_made( $subscription_id );

	// Calculate remaining
	$remaining = intval( $max_payments ) - intval( $payments_made );

	return max( 0, $remaining );
}

/**
 * Get payment type for a subscription (handles variations properly).
 *
 * @param int $subscription_id Subscription ID.
 * @return string Payment type ('split_payment' or 'recurring').
 */
function subscrpt_get_payment_type( $subscription_id ) {
	$product_id   = get_post_meta( $subscription_id, '_subscrpt_product_id', true );
	$variation_id = get_post_meta( $subscription_id, '_subscrpt_variation_id', true );

	$payment_type = 'recurring'; // Default

	// Check variation first if it exists
	if ( $variation_id ) {
		$variation_payment_type = get_post_meta( $variation_id, '_subscrpt_payment_type', true );
		if ( $variation_payment_type ) {
			$payment_type = $variation_payment_type;
		}
	}

	// Fallback to product if no variation payment type
	if ( $payment_type === 'recurring' && $product_id ) {
		$product_payment_type = get_post_meta( $product_id, '_subscrpt_payment_type', true );
		if ( $product_payment_type ) {
			$payment_type = $product_payment_type;
		}
	}

	// Final fallback: check subscription's own meta data
	if ( $payment_type === 'recurring' ) {
		$subscription_payment_type = get_post_meta( $subscription_id, '_subscrpt_payment_type', true );
		if ( $subscription_payment_type ) {
			$payment_type = $subscription_payment_type;
		}
	}

	return $payment_type;
}

/**
 * Human-readable label of the plan a subscription was purchased on.
 *
 * Combines the plan group name and the plan-term title (e.g. "Split Pay – Every
 * Day"). Returns an empty string for legacy per-product subscriptions that were
 * not bought through a plan.
 *
 * @param int $subscription_id Subscription ID.
 * @return string Plan label, or '' when the subscription has no plan.
 */
function subscrpt_get_subscription_plan_label( $subscription_id ) {
	$plan_id = (int) get_post_meta( $subscription_id, '_subscrpt_plan_id', true );
	if ( ! $plan_id || ! class_exists( '\SpringDevs\Subscription\Illuminate\Plans\PlanRepository' ) ) {
		return '';
	}

	$plan = \SpringDevs\Subscription\Illuminate\Plans\PlanRepository::get_plan( $plan_id );
	if ( ! $plan ) {
		return '';
	}

	$term_title  = isset( $plan['title'] ) ? trim( (string) $plan['title'] ) : '';
	$group_title = '';
	$group_id    = (int) ( $plan['plan_group_id'] ?? 0 );
	if ( $group_id ) {
		$group = \SpringDevs\Subscription\Illuminate\Plans\PlanRepository::get_group( $group_id );
		if ( $group && isset( $group['title'] ) ) {
			$group_title = trim( (string) $group['title'] );
		}
	}

	$parts = array_filter( array( $group_title, $term_title ) );

	return implode( ' – ', $parts );
}

/**
 * Enhanced completion check considering failed payments and access suspension.
 *
 * @param int $subscription_id Subscription ID.
 * @param int $payments_made Number of successful payments made.
 * @param int $max_payments Maximum payments required.
 * @return bool True if subscription should be considered complete.
 */
function subscrpt_check_enhanced_completion( $subscription_id, $payments_made, $max_payments ) {
	// Standard completion check
	if ( $payments_made >= $max_payments ) {
		return true;
	}

	// Check for access suspension due to payment failures
	if ( function_exists( '\SpringDevs\SubscriptionPro\Illuminate\PaymentFailureHandler::is_access_suspended' ) ) {
		$is_suspended = \SpringDevs\SubscriptionPro\Illuminate\PaymentFailureHandler::is_access_suspended( $subscription_id );
		if ( $is_suspended ) {
			// If access is suspended, check if we should force completion
			$force_completion_on_suspension = apply_filters( 'subscrpt_force_completion_on_suspension', false, $subscription_id );
			if ( $force_completion_on_suspension ) {
				return true;
			}
		}
	}

	// Check for maximum failure threshold
	$failure_count                  = (int) get_post_meta( $subscription_id, '_subscrpt_payment_failure_count', true );
	$max_failures_before_completion = apply_filters( 'subscrpt_max_failures_before_completion', 0, $subscription_id );

	if ( $max_failures_before_completion > 0 && $failure_count >= $max_failures_before_completion ) {
		// Force completion after too many failures
		return true;
	}

	// Check for time-based completion (e.g., if too much time has passed)
	$completion_timeout_days = apply_filters( 'subscrpt_completion_timeout_days', 0, $subscription_id );
	if ( $completion_timeout_days > 0 ) {
		$start_date = get_post_meta( $subscription_id, '_subscrpt_start_date', true );
		if ( $start_date ) {
			$timeout_timestamp = $start_date + ( $completion_timeout_days * DAY_IN_SECONDS );
			if ( current_time( 'timestamp' ) >= $timeout_timestamp ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Count total payment attempts (including failed ones) for a subscription.
 *
 * @param int $subscription_id Subscription ID.
 * @return array Array with 'successful', 'failed', and 'total' counts.
 */
function subscrpt_count_all_payment_attempts( $subscription_id ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'subscrpt_order_relation';

	// Query the relation table only. Joining wp_posts would drop every row under
	// HPOS (orders are not stored there); wc_get_order() below is HPOS-safe.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$relations = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $table_name WHERE subscription_id = %d ORDER BY id ASC",
			$subscription_id
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Define all payment-related order types
	$payment_types = apply_filters( 'subscrpt_payment_order_types', array( 'new', 'renew', 'early-renew' ) );

	$successful_count = 0;
	$failed_count     = 0;

	foreach ( $relations as $relation ) {
		if ( in_array( $relation->type, $payment_types ) ) {
			$order = wc_get_order( $relation->order_id );
			if ( $order ) {
				if ( $order->is_paid() || in_array( $order->get_status(), array( 'completed', 'processing', 'on-hold' ) ) ) {
					++$successful_count;
				} elseif ( in_array( $order->get_status(), array( 'failed', 'cancelled' ) ) ) {
					++$failed_count;
				}
			}
		}
	}

	return array(
		'successful' => $successful_count,
		'failed'     => $failed_count,
		'total'      => $successful_count + $failed_count,
	);
}

if ( ! function_exists( 'wps_subscription_order_relation_type_cast' ) ) {
	/**
	 * Return Label against key.
	 *
	 * @param string $key Key to return cast Value.
	 *
	 * @return string
	 */
	function order_relation_type_cast( string $key ) {
		// add Deprecated notice
		_deprecated_function( 'order_relation_type_cast', '1.5.3', 'wps_subscription_order_relation_type_cast' );
		return wps_subscription_order_relation_type_cast( $key );
	}
	/**
	 * Order relation type cast.
	 *
	 * @param string $key Key.
	 *
	 * @return string
	 */
	function wps_subscription_order_relation_type_cast( string $key ) {
		$relational_type_keys = apply_filters(
			'subscrpt_order_relational_types',
			array(
				'new'   => __( 'New Subscription Order', 'subscription' ),
				'renew' => __( 'Renewal Order', 'subscription' ),
			)
		);

		return isset( $relational_type_keys[ $key ] ) ? $relational_type_keys[ $key ] : '-';
	}
}

if ( ! function_exists( 'wps_subscription_is_wc_order_hpos_enabled' ) ) {
	/**
	 * Check if HPOS enabled.
	 */
	function is_wc_order_hpos_enabled() {
		// add Deprecated notice
		_deprecated_function( 'is_wc_order_hpos_enabled', '1.5.3', 'wps_subscription_is_wc_order_hpos_enabled' );
		return wps_subscription_is_wc_order_hpos_enabled();
	}
	/**
	 * Check if HPOS enabled.
	 *
	 * @return bool
	 */
	function wps_subscription_is_wc_order_hpos_enabled() {
		return function_exists( 'wc_get_container' ) ?
			wc_get_container()
				->get( CustomOrdersTableController::class )
				->custom_orders_table_usage_is_enabled()
			: false;
	}
}

if ( ! function_exists( 'sdevs_wp_strtotime' ) ) {
	/**
	 * Resolve a relative date string against a base timestamp, in site timezone.
	 *
	 * The relative interval is applied to the site-local wall clock (so "+1 month"
	 * keeps the same local time across DST changes), and a real UTC timestamp is
	 * returned.
	 *
	 * Do not reimplement this as strtotime( wp_date( ... ) ): wp_date() renders the
	 * site-local wall clock while strtotime() parses it as UTC (WP sets PHP's default
	 * timezone to UTC), so the site's UTC offset gets added on every call. For
	 * recurring dates that compounds — a daily subscription on a UTC+7 site renews
	 * every 31 hours and skips a calendar day every few renewals.
	 *
	 * @param string   $str string.
	 * @param int|null $base_timestamp base timestamp.
	 *
	 * @return int
	 */
	function sdevs_wp_strtotime( $str, $base_timestamp = null ) {
		$base = null === $base_timestamp ? time() : (int) $base_timestamp;

		try {
			$date     = new DateTime( '@' . $base );
			$modified = $date->setTimezone( wp_timezone() )->modify( $str );

			if ( $modified instanceof DateTime ) {
				return $modified->getTimestamp();
			}
		} catch ( Exception $e ) {
			// Unparsable string — fall through to strtotime().
			return strtotime( $str, $base );
		}

		return strtotime( $str, $base );
	}
}

if ( ! function_exists( 'sdevs_order_status_label' ) ) {
	/**
	 * Get order status label from slug.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	function sdevs_order_status_label( $status ) {
		$order_statuses = wc_get_order_statuses();

		return ( isset( $order_statuses[ "wc-{$status}" ] ) ? $order_statuses[ "wc-{$status}" ] : $status );
	}
}

if ( ! function_exists( 'wps_subscription_get_timing_types' ) ) {
	/**
	 * Get labels.
	 *
	 * @param bool $key_value key_value.
	 *
	 * @return array
	 */
	function get_timing_types( $key_value = false ): array {
		// add Deprecated notice
		_deprecated_function( 'get_timing_types', '1.5.3', 'wps_subscription_get_timing_types' );
		return wps_subscription_get_timing_types( $key_value );
	}
	/**
	 * Get timing types.
	 *
	 * @param bool $key_value Key value.
	 *
	 * @return array
	 */
	function wps_subscription_get_timing_types( $key_value = false ): array {
		return $key_value ? array(
			'days'   => 'Daily',
			'weeks'  => 'Weekly',
			'months' => 'Monthly',
			'years'  => 'Yearly',
		) : array(
			array(
				'label' => __( 'Day', 'subscription' ),
				'value' => 'days',
			),
			array(
				'label' => __( 'Week', 'subscription' ),
				'value' => 'weeks',
			),
			array(
				'label' => __( 'Month', 'subscription' ),
				'value' => 'months',
			),
			array(
				'label' => __( 'Year', 'subscription' ),
				'value' => 'years',
			),
		);
	}
}

/**
 * Get WC product in subscription wrapper.
 *
 * @deprecated 1.8.17 Use SpringDevs\Subscription\Illuminate\Subscription\Subscription::get_subs_product().
 *
 * @param \WC_Product|int $product Product object or product id.
 * @return mixed Subscription product wrapper.
 */
function sdevs_get_subscription_product( $product ) {
	// Deprecated notice.
	_deprecated_function( 'sdevs_get_subscription_product', '1.8.17', 'SpringDevs\Subscription\Illuminate\Subscription\Subscription::get_subs_product' );

	return Subscription::get_subs_product( $product );
}

/**
 * Logger
 *
 * @param mixed $message      Message.
 * @param bool  $should_print Print the output.
 */
function subscrpt_write_log( $message, bool $should_print = false ): void {
	$logger = wc_get_logger();

	$message = is_array( $message ) || is_object( $message ) ? wp_json_encode( $message ) : $message;
	$logger->add( 'wp_subscription', $message );

	echo esc_html( $should_print ? $message : '' );
}

/**
 * Debug Logger
 *
 * @param mixed $log logs.
 */
function subscrpt_write_debug_log( $log ): void {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		} else {
			error_log( 'wp_subscription: ' . $log ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
}
