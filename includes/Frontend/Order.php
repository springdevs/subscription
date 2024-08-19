<?php

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Order class of frontend.
 */
class Order {
	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_subscrpt_details' ) );
	}

	/**
	 * Display subscriptions related to the order.
	 *
	 * @param \WC_Order $order Order Object.
	 *
	 * @return void
	 */
	public function display_subscrpt_details( $order ) {
		$histories = Helper::get_subscriptions_from_order( $order->get_id() );

		if ( is_array( $histories ) && count( $histories ) > 0 ) :
			?>
			<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Related Subscriptions', 'sdevs_subscrpt' ); ?></h2>
			<?php if ( ! $order->has_status( 'completed' ) ) : ?> 
				<p><small>Your subscription will be activated when order status is completed.</small></p>
			<?php endif; ?>
			<?php
			foreach ( $histories as $history ) :
					$order_item      = $order->get_item( $history->order_item_id );
					$order_item_meta = wc_get_order_item_meta( $history->order_item_id, '_subscrpt_meta', true );

					$product_name = $order_item->get_name();
					$product_link = get_the_permalink( $order_item->get_product_id() );
					$post         = $history->subscription_id;
					$cost         = get_post_meta( $post, '_subscrpt_price', true );
					$order        = $order_item->get_order();
					$start_date   = get_post_meta( $history->subscription_id, '_subscrpt_start_date', true );

					$trial_status = null !== $order_item_meta['trial'];
				?>
					<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
						<thead>
						<tr>
							<th class="woocommerce-table__product-name product-name"><?php echo get_the_title( $post ); ?></th>
							<th class="woocommerce-table__product-table product-total"></th>
						</tr>
						</thead>
						<tbody>
						<tr class="woocommerce-table__line-item order_item">
							<td class="woocommerce-table__product-name product-name">
								<a href="<?php echo esc_html( $product_link ); ?>"><?php echo esc_html( $product_name ); ?></a>
								<strong
									class="product-quantity">×&nbsp;<?php echo esc_html( $order_item->get_quantity() ); ?></strong>
							</td>
							<td class="woocommerce-table__product-total product-total"></td>
						</tr>
						</tbody>
						<tfoot>
						<tr>
							<th scope="row"><?php esc_html_e( 'Status', 'sdevs_subscrpt' ); ?>:</th>
							<td><?php echo esc_html( get_post_status_object( get_post_status( $post ) )->label ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Recurring amount', 'sdevs_subscrpt' ); ?>:</th>
							<td class="woocommerce-table__product-total product-total">
								<?php echo wp_kses_post( Helper::format_price_with_order_item( $cost, $history->order_item_id ) ); ?>
							</td>
						</tr>
						<?php
						if ( null == $trial_status ) {
							?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Next billing on', 'sdevs_subscrpt' ); ?>:</th>
								<td><?php echo $order->has_status( 'completed' ) ? esc_html( gmdate( 'F d, Y', get_post_meta( $history->subscription_id, '_subscrpt_next_date', true ) ) ) : '-'; ?></td>
							</tr>
						<?php } else { ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Trial', 'sdevs_subscrpt' ); ?>:</th>
								<td><?php echo esc_html( get_post_meta( $history->subscription_id, '_subscrpt_trial', true ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'First billing on', 'sdevs_subscrpt' ); ?>:</th>
								<td><?php echo ! empty( $start_date ) ? esc_html( gmdate( 'F d, Y', $start_date ) ) : '-'; ?></td>
							</tr>
						<?php } ?>
						</tfoot>
					</table>
				<?php
				endforeach;
				endif;
	}
}
