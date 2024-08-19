<table class="widefat striped">
	<thead>
		<tr>
			<th></th>
			<th><?php

			use SpringDevs\Subscription\Illuminate\Helper;

			esc_html_e( 'Started on', 'sdevs_subscrpt' ); ?></th>
			<th><?php esc_html_e( 'Recurring', 'sdevs_subscrpt' ); ?></th>
			<th><?php esc_html_e( 'Expiry date', 'sdevs_subscrpt' ); ?></th>
			<th><?php esc_html_e( 'Status', 'sdevs_subscrpt' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $histories as $history ) :
				$order_item_id = get_post_meta( $history->subscription_id, '_subscrpt_order_item_id', true );
				$order_item    = $order->get_item( $history->order_item_id );
				$price         = get_post_meta( $history->subscription_id, '_subscrpt_price', true );
				$trial         = get_post_meta( $history->subscription_id, '_subscrpt_trial', true );
				$start_date    = get_post_meta( $history->subscription_id, '_subscrpt_start_date', true );
				$next_date     = get_post_meta( $history->subscription_id, '_subscrpt_next_date', true );
				$status_object = get_post_status_object( get_post_status( $history->subscription_id ) );
			?>
				<tr>
					<td>
						<a href="<?php echo esc_html( get_edit_post_link( $history->subscription_id ) ); ?>" target="_blank">#<?php echo esc_html( $history->subscription_id ); ?> - <?php echo esc_html( $order_item->get_name() ); ?></a>
					</td>
					<td>
						<?php echo null == $trial ? ( ! empty( $start_date ) ? esc_html( gmdate( 'F d, Y', $start_date ) ) : '-' ) : '+' . esc_html( $trial ) . ' ' . __( 'free trial', 'sdevs_subscrpt' ); ?>
					</td>
					<td><?php echo wp_kses_post( Helper::format_price_with_order_item( $price, $order_item->get_id() ) ); ?></td>
					<td><?php echo esc_html( ! empty( $start_date ) && ! empty( $next_date ) ? ( $trial == null ? gmdate( 'F d, Y', $next_date ) : gmdate( 'F d, Y', $start_date ) ) : '-' ); ?></td>
					<td><span class="subscrpt-<?php echo esc_attr( $status_object->name ); ?>"><?php echo esc_html( $status_object->label ); ?></span></td>
				</tr>
				<?php
		endforeach;
		?>
	</tbody>
</table>
