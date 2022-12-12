<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Order
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Order {

	public function __construct() {
		add_filter( 'woocommerce_order_formatted_line_subtotal', array( $this, 'format_order_price' ), 10, 3 );
		add_action( 'woocommerce_admin_order_item_headers', array( $this, 'admin_order_item_header' ) );
		add_action( 'woocommerce_admin_order_item_values', array( $this, 'admin_order_item_value' ), 10, 2 );
		add_action( 'woocommerce_before_order_itemmeta', array( $this, 'add_order_item_data' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ) );
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

	public function admin_order_item_header( $order ) {
		?>
		<th class="item_recurring sortable" data-sort="float"><?php esc_html_e( 'Recurring', 'sdevs_subscrpt' ); ?></th>
		<?php
	}

	public function admin_order_item_value( $product, $item ) {
		if ( ! method_exists( $item, 'get_id' ) || ! method_exists( $item, 'get_subtotal' ) ) {
			return;
		}

		$subtotal   = '-';
		$item_id = $item->get_id();
		$subtotal = Helper::format_price_with_order_item( $item->get_subtotal(), $item_id );
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

		$item_meta 			 = wc_get_order_item_meta( $item_id, '_subscrpt_meta', true );

		if ( !$item_meta || !is_array($item_meta) ) {
			return false;
		}

		$trial           = $item_meta['trial'];
		$has_trial       = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;

		if ( $has_trial ) {
			echo '<br/><small> + Got ' . $trial . ' free trial!</small>';
		}

	}

	public function order_status_changed( $order_id ) {
		$order       = new \WC_Order( $order_id );
		$post_status = 'active';

		switch ( $order->get_status() ) {
			case 'on-hold':
			case 'pending';
				$post_status = 'pending';
				break;

			case 'refunded':
			case 'failed':
			case 'cancelled';
				$post_status = 'cancelled';
				break;

			default;
				$post_status = 'active';
				break;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_histories';
		$histories = $wpdb->get_results("SELECT * FROM ${table_name} WHERE order_id=${order_id}");

		foreach ($histories as $history) {
			wp_update_post(
				array(
					'ID'          => $history->subscription_id,
					'post_status' => $post_status,
				)
			);

			Action::write_comment( $post_status, $history->subscription_id );
		}
	}
}
