<?php

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;

class Order {
	public function __construct() {
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_subscrpt_details' ) );
	}

	public function display_subscrpt_details( $order ) {
		$histories = Helper::get_subscriptions_from_order( $order->get_id() );

		if ( is_array( $histories ) && count( $histories ) > 0 ) :
			?>
			<h2 class="woocommerce-order-details__title"><?php _e( 'Related Subscriptions', 'sdevs_subscrpt' ); ?></h2>
			<?php
			foreach ( $histories as $history ) :
					$order_item      = $order->get_item( $history->order_item_id );
					$order_item_meta = wc_get_order_item_meta( $history->order_item_id, '_subscrpt_meta', true );

					$product_name = $order_item->get_name();
					$product_link = get_the_permalink( $order_item->get_product_id() );
					$post         = $history->subscription_id;

					$trial_status = $order_item_meta['trial'] == null ? false : true;
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
							<th scope="row"><?php _e( 'Status', 'sdevs_subscrpt' ); ?>:</th>
							<td><?php echo get_post_status( $post ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php _e( 'Recurring amount', 'sdevs_subscrpt' ); ?>:</th>
							<td class="woocommerce-table__product-total product-total">
								<?php echo wp_kses_post( Helper::format_price_with_order_item( $order_item->get_total(), $history->order_item_id ) ); ?>
							</td>
						</tr>
						<?php if ( $trial_status == null ) { ?>
							<tr>
								<th scope="row"><?php _e( 'Next billing on', 'sdevs_subscrpt' ); ?>:</th>
								<td><?php echo esc_html( date( 'F d, Y', $order_item_meta['next_date'] ) ); ?></td>
							</tr>
						<?php } else { ?>
							<tr>
								<th scope="row"><?php _e( 'Trial', 'sdevs_subscrpt' ); ?>:</th>
								<td><?php echo esc_html( $order_item_meta['trial'] ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php _e( 'First billing on', 'sdevs_subscrpt' ); ?>:</th>
								<td><?php echo esc_html( date( 'F d, Y', $order_item_meta['start_date'] ) ); ?></td>
							</tr>
						<?php } ?>
						</tfoot>
					</table>
				<?php
			endforeach;
		endif;
	}
}
