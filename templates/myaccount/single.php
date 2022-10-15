<?php

/**
 * Single subscription page
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
			<td><?php _e( 'Order', 'sdevs_subscrpt' ); ?></td>
			<td><a href="<?php echo get_permalink( wc_get_page_id( 'myaccount' ) ) . 'view-order/' . esc_html( $post_meta['order_id'] ); ?>" target="_blank"># <?php echo esc_html( $post_meta['order_id'] ); ?></a></td>
		</tr>
		<tr>
			<td><?php _e( 'Status', 'sdevs_subscrpt' ); ?></td>
			<td><span class="subscrpt-<?php echo esc_html( $status ); ?>"><?php echo esc_html( $status ); ?></span></td>
		</tr>
		<tr>
			<td><?php _e( 'Start date', 'sdevs_subscrpt' ); ?></td>
			<td><?php echo date( 'F d, Y', $post_meta['start_date'] ); ?></td>
		</tr>
		<?php if ( $post_meta['trial'] == null ) : ?>
			<tr>
				<td><?php _e( 'Next payment date', 'sdevs_subscrpt' ); ?></td>
				<td><?php echo date( 'F d, Y', $post_meta['next_date'] ); ?></td>
			</tr>
		<?php else : ?>
			<tr>
				<td><?php _e( 'Trial', 'sdevs_subscrpt' ); ?></td>
				<td><?php echo esc_html( $post_meta['trial'] ); ?></td>
			</tr>
			<tr>
				<td><?php _e( 'Trial End & First Billing', 'sdevs_subscrpt' ); ?></td>
				<td><?php echo date( 'F d, Y', $post_meta['start_date'] ); ?></td>
			</tr>
		<?php endif; ?>
		<tr>
			<td><?php _e( 'Payment', 'sdevs_subscrpt' ); ?></td>
			<td>
				<span data-is_manual="yes" class="subscription-payment-method"><?php echo esc_html( $order->get_payment_method_title() ); ?></span>
			</td>
		</tr>
		<?php
		$subscrpt_nonce = wp_create_nonce( 'subscrpt_nonce' );
		?>
		<?php if ( $status != 'cancelled' ) : ?>
			<tr>
				<td><?php _e( 'Actions', 'sdevs_subscrpt' ); ?></td>
				<td>
					<?php if ( ( $status == 'pending' || $status == 'active' || $status == 'on_hold' ) && $user_cancell == 'yes' ) : ?>
						<a href="<?php echo esc_js( get_permalink( wc_get_page_id( 'myaccount' ) ) . 'view-subscrpt/' . $id . '?subscrpt_id=' . $id . '&action=cancelled&wpnonce=' . $subscrpt_nonce ); ?>" class="button cancel">Cancel</a>
					<?php elseif ( trim( $status ) == trim( 'pe_cancelled' ) ) : ?>
						<a href="" class="button subscription_renewal_early"><?php _e( 'Reactive', 'sdevs_subscrpt' ); ?></a>
					<?php endif; ?>
					<?php if ( $order->get_status() === 'pending' ) : ?>
						<a href="<?php echo $order->get_checkout_payment_url(); ?>" class="button subscription_renewal_early"><?php _e( 'Pay now', 'sdevs_subscrpt' ); ?></a>
					<?php elseif ( ( get_option( 'subscrpt_early_renew', '' ) == 1 || trim( $status ) == trim( 'expired' ) ) && $order->get_status() == 'completed' ) : ?>
						<a href="<?php echo esc_js( get_permalink( wc_get_page_id( 'myaccount' ) ) . 'view-subscrpt/' . $id . '?subscrpt_id=' . $id . '&action=early-renew&wpnonce=' . $subscrpt_nonce ); ?>" class="button subscription_renewal_early"><?php _e( 'Renew now', 'sdevs_subscrpt' ); ?></a>
					<?php endif; ?>
					<?php do_action( 'subscrpt_single_action_buttons', $id, $order, $subscrpt_nonce ); ?>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<?php do_action('subscrpt_before_subscription_totals', (int)$id); ?>

<h2><?php _e( 'Subscription totals', 'sdevs_subscrpt' ); ?></h2>
<table class="shop_table order_details">
	<thead>
		<tr>
			<th class="product-name"><?php _e( 'Product', 'sdevs_subscrpt' ); ?></th>
			<th class="product-total"><?php _e( 'Total', 'sdevs_subscrpt' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		$product_name       = $order_item->get_name();
		$product_link       = get_permalink( $order_item->get_product_id() );
		$order_item_meta	= $order_item->get_meta( '_subscrpt_meta', true );
		$time               = $order_item_meta['time'] == 1 ? null : $order_item_meta['time'];
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
