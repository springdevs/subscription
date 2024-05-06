<table class="form-table">
	<tbody>
		<tr>
			<?php

			use SpringDevs\Subscription\Illuminate\Helper;

			$product_name = $order_item->get_name();
			$product_link = get_the_permalink( $order_item->get_product_id() );
			?>
			<th scope="row">Product : </th>
			<td>
				<a href="<?php echo esc_html( $product_link ); ?>" target="_blank"><?php echo esc_html( $product_name ); ?></a>
			</td>
		</tr>
		<tr>
			<th scope="row">Cost : </th>
			<td><?php echo wp_kses_post( Helper::format_price_with_order_item( $order_item->get_total(), $order_item->get_id() ) ); ?></td>
		</tr>
		<tr>
			<th scope="row">Qty : </th>
			<td>x<?php echo esc_html( $order_item->get_quantity() ); ?></td>
		</tr>
		<?php if ( ! empty( $post_meta['trial'] ) ) : ?>
			<tr>
				<th scope="row">Trial</th>
				<td><?php echo esc_html( $post_meta['trial'] ); ?></td>
			</tr>
			<tr>
				<th scope="row">Trial Date</th>
				<td><?php echo esc_html( ' [ ' . date( 'F d, Y', strtotime( $order->get_date_created() ) ) . ' - ' . date( 'F d, Y', strtotime( $post_meta['trial'], strtotime( $order->get_date_created() ) ) ) . ' ] ' ); ?></td>
			</tr>
		<?php endif; ?>
		<tr>
			<th scope="row">Started date:</th>
			<td><?php echo esc_html( date( 'F d, Y', $post_meta['start_date'] ) ); ?></td>
		</tr>
		<tr>
			<th scope="row">Payment due date:</th>
			<td><?php echo esc_html( date( 'F d, Y', $post_meta['next_date'] ) ); ?></td>
		</tr>
		<tr>
			<th scope="row">Status:</th>
			<td><span class="subscrpt-<?php echo get_post_status(); ?>"><?php echo get_post_status_object( get_post_status() )->label; ?></span></td>
		</tr>
		<tr>
			<th scope="row">Payment Method:</th>
			<td><?php echo esc_html( $order->get_payment_method_title() ); ?></td>
		</tr>
		<?php if ( class_exists( 'WC_Stripe' ) && 'stripe' === $order->get_payment_method() ) : ?>
		<tr>
			<?php
			$is_auto_renew = get_post_meta( get_the_ID(), '_subscrpt_auto_renew', true );
			?>
			<th scope="row">Stripe Auto Renewal:</th>
			<td>
				<?php echo esc_html( '0' !== $is_auto_renew ? 'On' : 'Off' ); ?>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th scope="row">Billing:</th>
			<td><?php echo wp_kses_post( $order->get_formatted_billing_address() ? $order->get_formatted_billing_address() : 'No billing address set.' ); ?></td>
		</tr>
		<tr>
			<th scope="row">Shipping:</th>
			<td><?php echo wp_kses_post( $order->get_formatted_shipping_address() ? $order->get_formatted_shipping_address() : 'No shipping address set.' ); ?></td>
		</tr>
	</tbody>
</table>
