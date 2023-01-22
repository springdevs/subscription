<?php
/**
 * Single subscription page
 *
 * @var WC_Order $order
 * @var array $post_meta
 * @var stdClass $status
 * @var array $action_buttons
 *
 * This template can be overridden by copying it to yourtheme/subscription/myaccount/single.php
 */

use SpringDevs\Subscription\Illuminate\Helper;

if ( ! isset( $id ) ) {
	return;
}

if ( ! get_the_title( $id ) ) {
	return;
}

do_action( 'before_single_subscrpt_content' );
?>
<style>
	.auto-renew-on,
	.subscription_renewal_early,
	.auto-renew-off {
		margin-bottom: 10px;
	}
</style>
<table class="shop_table subscription_details">
	<tbody>
		<tr>
			<td><?php esc_html_e( 'Order', 'sdevs_subscrpt' ); ?></td>
			<td><a href="<?php echo esc_html( wc_get_endpoint_url( 'view-order', $order->get_id(), wc_get_page_permalink( 'myaccount' ) ) ); ?>" target="_blank"># <?php echo esc_html( $post_meta['order_id'] ); ?></a></td>
		</tr>
		<tr>
			<td><?php esc_html_e( 'Status', 'sdevs_subscrpt' ); ?></td>
			<td><span class="subscrpt-<?php echo esc_html( $status->name ); ?>"><?php echo esc_html( $status->label ); ?></span></td>
		</tr>
		<tr>
			<td><?php esc_html_e( 'Start date', 'sdevs_subscrpt' ); ?></td>
			<td><?php echo esc_html( gmdate( 'F d, Y', $post_meta['start_date'] ) ); ?></td>
		</tr>
		<?php if ( null === $post_meta['trial'] ) : ?>
			<tr>
				<td><?php esc_html_e( 'Next payment date', 'sdevs_subscrpt' ); ?></td>
				<td><?php echo esc_html( gmdate( 'F d, Y', $post_meta['next_date'] ) ); ?></td>
			</tr>
		<?php else : ?>
			<tr>
				<td><?php esc_html_e( 'Trial', 'sdevs_subscrpt' ); ?></td>
				<td><?php echo esc_html( $post_meta['trial'] ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Trial End & First Billing', 'sdevs_subscrpt' ); ?></td>
				<td><?php echo esc_html( gmdate( 'F d, Y', $post_meta['start_date'] ) ); ?></td>
			</tr>
		<?php endif; ?>
		<tr>
			<td><?php esc_html_e( 'Payment', 'sdevs_subscrpt' ); ?></td>
			<td>
				<span data-is_manual="yes" class="subscription-payment-method"><?php echo esc_html( $order->get_payment_method_title() ); ?></span>
			</td>
		</tr>
		<?php if ( 0 < count( $action_buttons ) ) : ?>
			<tr>
				<td><?php echo esc_html_e( 'Actions', 'sdevs_subscrpt' ); ?></td>
				<td>
					<?php foreach ( $action_buttons as $action_button ) : ?>
						<a href="<?php echo esc_attr( $action_button['url'] ); ?>" class="button
											<?php
											if ( isset( $action_button['class'] ) ) {
												echo esc_attr( $action_button['class'] );}
											?>
						"><?php echo esc_html( $action_button['label'] ); ?></a>
						<?php endforeach; ?>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<?php do_action( 'subscrpt_before_subscription_totals', (int) $id ); ?>

<h2><?php echo esc_html_e( 'Subscription totals', 'sdevs_subscrpt' ); ?></h2>
<table class="shop_table order_details">
	<thead>
		<tr>
			<th class="product-name"><?php echo esc_html_e( 'Product', 'sdevs_subscrpt' ); ?></th>
			<th class="product-total"><?php echo esc_html_e( 'Total', 'sdevs_subscrpt' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		$product_name       = $order_item->get_name();
		$product_link       = get_permalink( $order_item->get_product_id() );
		$order_item_meta    = $order_item->get_meta( '_subscrpt_meta', true );
		$time               = '1' === $order_item_meta['time'] ? null : $order_item_meta['time'];
		$type               = subscrpt_get_typos( $order_item_meta['time'], $order_item_meta['type'] );
		$product_price_html = Helper::format_price_with_order_item( $order_item->get_total(), $order_item->get_id() );
		?>
		<tr class="order_item">
			<td class="product-name">
				<a href="<?php echo esc_html( $product_link ); ?>"><?php echo esc_html( $product_name ); ?></a>
				<strong class="product-quantity">× <?php echo esc_html( $order_item->get_quantity() ); ?></strong>
			</td>
			<td class="product-total">
				<span class="woocommerce-Price-amount amount">
					<?php
					echo wp_kses_post( Helper::format_price_with_order_item( $order_item->get_total(), $order_item->get_id() ) );
					?>
				</span>
			</td>
		</tr>
	</tbody>
	<tfoot>
		<tr>
			<th scope="row"><?php _e( 'Subtotal', 'sdevs_subscrpt' ); ?>:</th>
			<td>
				<span class="woocommerce-Price-amount amount"><?php echo wc_price( $order_item->get_subtotal(), array( 'currency' => $order->get_currency() ) ); ?></span>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php _e( 'Renew', 'sdevs_subscrpt' ); ?>:</th>
			<td>
				<span class="woocommerce-Price-amount amount">
					<?php echo wp_kses_post( $product_price_html ); ?>
				</span>
			</td>
		</tr>
	</tfoot>
</table>

<section class="woocommerce-customer-details">
	<h2 class="woocommerce-column__title"><?php _e( 'Billing address', 'sdevs_subscrpt' ); ?></h2>
	<address>
		<?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?>
		<p class="woocommerce-customer-details--phone"><?php echo esc_html( $order->get_billing_phone() ); ?></p>
		<p class="woocommerce-customer-details--email"><?php echo esc_html( $order->get_billing_email() ); ?></p>
	</address>
</section>
<div class="clear"></div>
