<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Order
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Order {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'woocommerce_admin_order_item_headers', array( $this, 'register_custom_column' ) );
		add_action( 'woocommerce_admin_order_item_values', array( $this, 'add_column_value' ), 10, 2 );
		add_action( 'woocommerce_before_order_itemmeta', array( $this, 'add_order_item_data' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ) );
		add_action( 'woocommerce_before_delete_order', array( $this, 'delete_the_subscription' ) );
		add_action( 'subscrpt_subscription_activated', array( $this, 'generate_dates_for_subscription' ) );

		add_action( 'subscrpt_queue_trial_order_autocomplete', array( $this, 'auto_complete_subscription_trial_order' ) );
	}

	/**
	 * Generate start, next and trial dates.
	 *
	 * @param int $subscription_id Subscription Id.
	 *
	 * @return void
	 */
	public function generate_dates_for_subscription( $subscription_id ) {
		$order_item_id        = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
		$subscription_history = Helper::get_subscription_from_order_item_id( $order_item_id );

		$order_item_meta = wc_get_order_item_meta( $order_item_id, '_subscrpt_meta' );
		$type            = Helper::get_typos( 1, $order_item_meta['type'] );
		$trial           = get_post_meta( $subscription_id, '_subscrpt_trial', true );
		$recurr_timing   = ( $order_item_meta['time'] ?? 1 ) . ' ' . $type;

		if ( 'new' === $subscription_history->type ) {
			$start_date = time();
			$next_date  = sdevs_wp_strtotime( $recurr_timing, $start_date );

			if ( $trial && ! empty( $trial ) ) {
				$trial_started = get_post_meta( $subscription_id, '_subscrpt_trial_started', true );
				$trial_ended   = get_post_meta( $subscription_id, '_subscrpt_trial_ended', true );

				if ( empty( $trial_started ) && empty( $trial_ended ) ) {
					$start_date = sdevs_wp_strtotime( $trial );
					$next_date  = $start_date;

					update_post_meta( $subscription_id, '_subscrpt_trial_started', time() );
					update_post_meta( $subscription_id, '_subscrpt_trial_ended', $start_date );
					update_post_meta( $subscription_id, '_subscrpt_trial_mode', 'on' );
				}

				if ( ! empty( $trial_ended ) ) {
					$start_date = $trial_ended;
					$next_date  = $start_date;
				}
			}

			update_post_meta( $subscription_id, '_subscrpt_start_date', $start_date );

		} elseif ( 'renew' === $subscription_history->type ) {
			if ( $trial ) {
				delete_post_meta( $subscription_id, '_subscrpt_trial' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_mode' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_started' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_ended' );
			}

			$next_date = $this->get_anchored_next_date( $subscription_id, $recurr_timing, (int) $subscription_history->order_id );

		} elseif ( 'early-renew' === $subscription_history->type ) {
			if ( $trial ) {
				delete_post_meta( $subscription_id, '_subscrpt_trial' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_mode' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_started' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_ended' );
			}

			$next_date = sdevs_wp_strtotime( $recurr_timing, time() );
		}

		// Split payment: no next date after the final installment.
		if (
			in_array( $subscription_history->type, array( 'renew', 'early-renew' ), true )
			&& function_exists( 'subscrpt_is_max_payments_reached' )
			&& subscrpt_is_max_payments_reached( $subscription_id )
		) {
			return;
		}

		/**
		 * Filter the subscription next payment date before it is saved.
		 *
		 * General-purpose hook (not scoped to split payment) for adjusting the
		 * computed next renewal date. Note `$next_date` may be null for history
		 * types other than 'new'/'renew'/'early-renew' (e.g. switch orders).
		 *
		 * The deprecated `subscrpt_split_payment_next_due_date` filter is bridged
		 * onto this hook in LegacyCompat.php for backward compatibility.
		 *
		 * @param int|null $next_date       Computed next payment timestamp, or null.
		 * @param int      $subscription_id Subscription ID.
		 * @param string   $recurr_timing   Recurring timing string (e.g. "1 month").
		 * @param string   $type            Subscription history type.
		 */
		$next_date = apply_filters( 'subscrpt_subscription_next_date', $next_date, $subscription_id, $recurr_timing, $subscription_history->type );

		update_post_meta( $subscription_id, '_subscrpt_next_date', $next_date );
	}

	/**
	 * Calculate a renewal's next payment date, anchored to the previous due date.
	 *
	 * Computing from time() instead makes every cycle inherit however late the
	 * renewal was actually processed — with an hourly cron a due date of 02:00:05
	 * is missed by the 02:00:03 run and lands at 03:00, and that hour is carried
	 * into every following cycle until a whole billing period is skipped. Anchoring
	 * to the stored due date keeps the billing time-of-day stable instead.
	 *
	 * When payment arrives more than one period late (manual renewal, cron outage),
	 * the anchor is stepped forward period by period so the returned date is always
	 * in the future — otherwise the subscription would be due again immediately.
	 *
	 * Because this runs on every activating status transition of the same renewal
	 * order (pending → processing → completed), the order that last moved the date
	 * is recorded so repeat transitions do not advance the cycle again.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $recurr_timing   Recurring timing string (e.g. "1 month").
	 * @param int    $renewal_order_id Renewal order driving this activation.
	 *
	 * @return int Next payment timestamp.
	 */
	private function get_anchored_next_date( $subscription_id, $recurr_timing, $renewal_order_id = 0 ) {
		$now    = time();
		$anchor = (int) get_post_meta( $subscription_id, '_subscrpt_next_date', true );

		if ( $anchor <= 0 ) {
			return sdevs_wp_strtotime( $recurr_timing, $now );
		}

		// This renewal order already advanced the date; keep it where it is.
		$dated_by = (int) get_post_meta( $subscription_id, '_subscrpt_next_date_set_by_order', true );
		if ( $renewal_order_id && $renewal_order_id === $dated_by ) {
			return $anchor;
		}

		if ( $renewal_order_id ) {
			update_post_meta( $subscription_id, '_subscrpt_next_date_set_by_order', $renewal_order_id );
		}

		$next_date = sdevs_wp_strtotime( $recurr_timing, $anchor );

		// Step forward until the date is in the future. Bail out if the timing string does not advance.
		$guard = 0;
		while ( $next_date <= $now && $guard < 1000 ) {
			$stepped = sdevs_wp_strtotime( $recurr_timing, $next_date );
			if ( $stepped <= $next_date ) {
				break;
			}
			$next_date = $stepped;
			++$guard;
		}

		if ( $next_date <= $now ) {
			return sdevs_wp_strtotime( $recurr_timing, $now );
		}

		return $next_date;
	}

	/**
	 * Add custom column on order item.
	 *
	 * @return void
	 */
	public function register_custom_column() {
		?>
		<th class="item_recurring sortable" data-sort="float"><?php esc_html_e( 'Recurring', 'subscription' ); ?></th>
		<?php
	}

	/**
	 * Display data for custom column.
	 *
	 * @param \WC_Product    $product Product Object.
	 * @param \WC_Order_Item $item Order Item.
	 *
	 * @return void
	 */
	public function add_column_value( $product, $item ) {
		if ( ! method_exists( $item, 'get_id' ) || ! method_exists( $item, 'get_subtotal' ) ) {
			return;
		}

		$subtotal        = '-';
		$subscription_id = Helper::get_subscription_from_order_item_id( $item->get_id() );

		if ( ! $subscription_id ) {
			echo "<td class='item_recurring' width='15%'>-</td>";
			return;
		}
		$subscription_id = $subscription_id->subscription_id;

		// Strikes the original amount when a discount carries into renewals.
		$subtotal = Helper::get_subscription_recurring_price_html( $subscription_id, $item );
		?>
		<td class="item_recurring" width="15%">
			<div class="view">
				<?php echo wp_kses_post( $subtotal ); ?>
			</div>
		</td>
		<?php
	}

	public function add_order_item_data( $item_id, $item, $product ) {
		if ( ! $product ) {
			return;
		}

		$item_meta = wc_get_order_item_meta( $item_id, '_subscrpt_meta', true );

		if ( ! $item_meta || ! is_array( $item_meta ) ) {
			return false;
		}

		$trial     = $item_meta['trial'];
		$has_trial = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;

		if ( $has_trial ) {
			echo '<br/><small> + Got ' . esc_html( $trial ) . ' free trial!</small>';
		}
	}

	/**
	 * Take some actions based on order status changed.
	 *
	 * @param int $order_id Order Id.
	 */
	public function order_status_changed( $order_id ) {
		$order       = wc_get_order( $order_id );
		$post_status = 'active';

		switch ( $order->get_status() ) {
			case 'on-hold':
			case 'pending':
				$post_status = 'pending';
				break;

			case 'refunded':
			case 'failed':
			case 'cancelled':
				$post_status = 'cancelled';
				break;

			default:
				$post_status = 'active';
				break;
		}
		$post_status = apply_filters( 'subscript_order_status_to_post_status', $post_status, $order );

		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		// @phpcs:ignore
		$histories = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id=%d', array( $table_name, $order_id ) ) );

		foreach ( $histories as $history ) {
			if ( 'new' === $history->type || 'renew' === $history->type ) {
				$subscription_id = $history->subscription_id;

				// Renewals ignore the intermediate `processing` state for renewal orders.
				if ( 'renew' === $history->type && 'processing' === $order->get_status() ) {
					continue;
				}

				// Capture the status before the max-payments check below may flip it.
				$current_status = get_post_status( $subscription_id );

				// Split payment: complete instead of re-activate after the final installment.
				$target_status = $post_status;
				if (
					'active' === $target_status
					&& function_exists( 'subscrpt_is_max_payments_reached' )
					&& subscrpt_is_max_payments_reached( $subscription_id )
				) {
					$target_status = 'completed';
				}

				// Skip no-op transitions so the note and side effects don't duplicate.
				if ( $current_status === $target_status ) {
					continue;
				}

				wp_update_post(
					array(
						'ID'          => $subscription_id,
						'post_status' => $target_status,
					)
				);

				// If possible change order status to completed if it has a trial subscription.
				$this->maybe_trigger_auto_complete_trial_order( $order_id, $subscription_id );

				// Increment renewal count for completed renewal orders (wps-pro)
				if ( 'renew' === $history->type && 'active' === $post_status && function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated() ) {
					if ( class_exists( '\\SpringDevs\\SubscriptionPro\\Illuminate\\LimitChecker' ) ) {
						\SpringDevs\SubscriptionPro\Illuminate\LimitChecker::increment_renewal_count( $history->subscription_id );
					}
				}

				// Add enhanced split payment activity logging
				$this->add_split_payment_activity_note( $history->subscription_id, $history->type, $post_status, $order );

				Action::write_comment( $target_status, $history->subscription_id );
			} else {
				do_action( 'subscrpt_order_status_changed', $order, $history );
			}
		}
	}

	/**
	 * Delete the subscription.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public function delete_the_subscription( $order_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';

		$histories = Helper::get_subscriptions_from_order( $order_id );
		foreach ( (array) $histories as $history ) {
			$subscription_order_id = get_post_meta( $history->subscription_id, '_subscrpt_order_id', true );
			if ( (int) $subscription_order_id === $order_id ) {
				wp_delete_post( $history->subscription_id, true );
			}
		}

		// phpcs:ignore
		$wpdb->delete( $table_name, array( 'order_id' => $order_id ), array( '%d' ) );
	}

	/**
	 * Add enhanced split payment activity note with payment progress and access information.
	 *
	 * @param int       $subscription_id Subscription ID.
	 * @param string    $history_type    History type (new, renew, etc.).
	 * @param string    $post_status     Post status.
	 * @param \WC_Order $order           WooCommerce order object.
	 */
	private function add_split_payment_activity_note( $subscription_id, $history_type, $post_status, $order ) {
		// Only add enhanced notes for active subscriptions
		if ( 'active' !== $post_status ) {
			return;
		}

		// Check if this is a split payment subscription
		if ( ! function_exists( 'subscrpt_get_payment_type' ) ) {
			return;
		}

		$payment_type = subscrpt_get_payment_type( $subscription_id );
		if ( 'split_payment' !== $payment_type ) {
			return;
		}

		// Get payment progress information
		$max_payments       = function_exists( 'subscrpt_get_max_payments' ) ? subscrpt_get_max_payments( $subscription_id ) : 0;
		$payments_made      = function_exists( 'subscrpt_count_payments_made' ) ? subscrpt_count_payments_made( $subscription_id ) : 0;
		$remaining_payments = function_exists( 'subscrpt_get_remaining_payments' ) ? subscrpt_get_remaining_payments( $subscription_id ) : 0;

		// Determine payment number for this order
		$payment_number = $payments_made;
		$order_total    = $order->get_total();
		$order_currency = $order->get_currency();

		// Create enhanced activity note
		$comment_content = '';
		$activity_type   = '';

		if ( 'new' === $history_type ) {
			$comment_content = sprintf(
				/* translators: %1$d: payment number, %2$d: total payments, %3$s: amount, %4$s: currency */
				__( 'Split payment %1$d of %2$d received (%3$s %4$s). Initial access granted.', 'subscription' ),
				$payment_number,
				$max_payments,
				$order_total,
				$order_currency
			);
			$activity_type = __( 'Split Payment - Initial', 'subscription' );
		} elseif ( 'renew' === $history_type ) {
			$comment_content = sprintf(
				/* translators: %1$d: payment number, %2$d: total payments, %3$s: amount, %4$s: currency, %5$d: remaining */
				__( 'Split payment %1$d of %2$d received (%3$s %4$s). %5$d payments remaining.', 'subscription' ),
				$payment_number,
				$max_payments,
				$order_total,
				$order_currency,
				$remaining_payments
			);
			$activity_type = __( 'Split Payment - Installment', 'subscription' );
		}

		// Add the enhanced activity note
		if ( $comment_content ) {
			$comment_id = wp_insert_comment(
				array(
					'comment_author'  => 'Subscription for WooCommerce',
					'comment_content' => $comment_content,
					'comment_post_ID' => $subscription_id,
					'comment_type'    => 'order_note',
				)
			);
			update_comment_meta( $comment_id, '_subscrpt_activity', $activity_type );
			update_comment_meta( $comment_id, '_subscrpt_activity_type', 'split_payment' );

			// Add order note with split payment context
			$order_note = sprintf(
				/* translators: %1$d: payment number, %2$d: total payments, %3$d: subscription id */
				__( 'Split payment %1$d of %2$d received for subscription #%3$d', 'subscription' ),
				$payment_number,
				$max_payments,
				$subscription_id
			);
			$order->add_order_note( $order_note );
		}
	}

	/**
	 * Maybe complete the order if it has a trial subscription and is still in processing status.
	 *
	 * @param int $order_id Order ID.
	 * @param int $subscription_id Subscription ID.
	 */
	public function maybe_trigger_auto_complete_trial_order( $order_id, $subscription_id ) {
		$order       = wc_get_order( $order_id );
		$order_items = $order->get_items();

		// Only attempt to complete if order is still in processing status.
		if ( 'processing' !== $order->get_status() ) {
			return;
		}

		$is_subs_trial_order = false;
		foreach ( $order_items as $order_item ) {
			$subscrpt_meta = $order_item->get_meta( '_subscrpt_meta', true );

			if ( ! empty( $subscrpt_meta ) && isset( $subscrpt_meta['trial'] ) ) {
				$is_subs_trial_order = true;
			}
		}

		if ( $is_subs_trial_order ) {
			as_enqueue_async_action( 'subscrpt_queue_trial_order_autocomplete', [ 'order_id' => $order_id ] );

			$log_message = "Queued auto complete task for free trial order [ID: {$order_id}]";
			subscrpt_write_log( $log_message );
			subscrpt_write_debug_log( $log_message );
		}
	}

	/**
	 * Autocomplete subscription trial order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function auto_complete_subscription_trial_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		sleep( 3 ); // Adding a small delay to ensure order status is updated before we check it.

		$order->update_status( 'completed', __( 'Subscription order with trial.', 'subscription' ) );
	}
}
