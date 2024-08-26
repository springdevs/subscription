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
		if ( 'new' === $subscription_history->type ) {
			$start_date = time();
			$next_date  = sdevs_wp_strtotime( 1 . ' ' . $type, $start_date );
			if ( $trial && ! empty( $trial ) ) {
				$trial_started = get_post_meta( $subscription_id, '_subscrpt_trial_started', true );
				$trial_ended   = get_post_meta( $subscription_id, '_subscrpt_trial_ended', true );
				if ( empty( $trial_started ) && empty( $trial_ended ) ) {
					$start_date = sdevs_wp_strtotime( $trial );
					update_post_meta( $subscription_id, '_subscrpt_trial_started', time() );
					update_post_meta( $subscription_id, '_subscrpt_trial_ended', $start_date );
					update_post_meta( $subscription_id, '_subscrpt_trial_mode', 'on' );
					$next_date = $start_date;
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
			$next_date = sdevs_wp_strtotime( 1 . ' ' . $type, time() );
		} elseif ( 'early-renew' === $subscription_history->type ) {
			$next_date = sdevs_wp_strtotime( 1 . ' ' . $type, get_post_meta( $subscription_id, '_subscrpt_next_date', true ) );

			if ( $trial ) {
				$trial_mode = get_post_meta( $subscription_id, '_subscrpt_trial_mode', true );
				if ( 'on' === $trial_mode ) {
					update_post_meta( $subscription_id, '_subscrpt_trial_mode', 'extended' );
					$next_date = sdevs_wp_strtotime( 1 . ' ' . $type, get_post_meta( $subscription_id, '_subscrpt_trial_ended', true ) );
				}
			}
		}
		update_post_meta( $subscription_id, '_subscrpt_next_date', $next_date );
	}

	/**
	 * Add custom column on order item.
	 *
	 * @return void
	 */
	public function register_custom_column() {
		?>
		<th class="item_recurring sortable" data-sort="float"><?php esc_html_e( 'Recurring', 'sdevs_subscrpt' ); ?></th>
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
		$item_id         = $item->get_id();
		$subscription_id = Helper::get_subscription_from_order_item_id( $item->get_id() )->subscription_id;
		$price           = get_post_meta( $subscription_id, '_subscrpt_price', true );
		$subtotal        = Helper::format_price_with_order_item( $price, $item_id );
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
			echo '<br/><small> + Got ' . $trial . ' free trial!</small>';
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
				wp_update_post(
					array(
						'ID'          => $history->subscription_id,
						'post_status' => $post_status,
					)
				);

				Action::write_comment( $post_status, $history->subscription_id );
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
}
