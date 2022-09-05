<div style="overflow: auto;">
	<table class="booking-customer-details" style="width: 100%;">
		<tbody>
			<tr>
				<th>Name:</th>
				<td><?php echo wp_kses_post( $order->get_formatted_billing_full_name() ); ?></td>
			</tr>
			<tr>
				<th>Email:</th>
				<td><a href="mailto:<?php echo esc_html( $order->get_billing_email() ); ?>"><?php echo esc_html( $order->get_billing_email() ); ?></a></td>
			</tr>
			<tr>
				<th>Address:</th>
				<td><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?></td>
			</tr>
			<tr>
				<th>Phone:</th>
				<td><?php echo esc_html( $order->get_billing_phone() ); ?></td>
			</tr>
			<tr class="view">
				<th>&nbsp;</th>
				<td><a class="button button-small" target="_blank" href="<?php echo esc_html( get_edit_post_link( $post_meta['order_id'] ) ); ?>">View Order</a></td>
			</tr>
		</tbody>
	</table>
</div>
