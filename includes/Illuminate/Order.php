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
		// add_filter( 'woocommerce_order_formatted_line_subtotal', array( $this, 'format_order_price' ), 10, 3 );
		add_action( 'woocommerce_admin_order_item_headers', array( $this, 'register_custom_column' ) );
		add_action( 'woocommerce_admin_order_item_values', array( $this, 'add_column_value' ), 10, 2 );
		add_action( 'woocommerce_before_order_itemmeta', array( $this, 'add_order_item_data' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ) );
		add_action( 'woocommerce_before_delete_order', array( $this, 'delete_the_subscription' ) );
	}

	public function format_order_price( $subtotal, $item, $order ) {
		$price_html = Helper::format_price_with_order_item(
			$item->get_subtotal(),
			$item->get_id(),
			true
		);

		if ( ! $price_html ) {
			return $subtotal;
		}

		return $price_html;
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
		$order       = new \WC_Order( $order_id );
		$post_status = 'active';

		switch ( $order->get_status() ) {
			case 'on-hold':
			case 'pending':
			case 'processing':
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
