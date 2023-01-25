<table class="widefat striped">
	<thead>
		<tr>
			<th></th>
			<th><?php

			use SpringDevs\Subscription\Illuminate\Helper;

			_e( 'Started on', 'sdevs_subscrpt' ); ?></th>
			<th><?php _e( 'Recurring', 'sdevs_subscrpt' ); ?></th>
			<th><?php _e( 'Expiry date', 'sdevs_subscrpt' ); ?></th>
			<th><?php _e( 'Status', 'sdevs_subscrpt' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $histories as $history ) :
				$post_meta  = get_post_meta( $history->subscription_id, '_order_subscrpt_meta', true );
				$order_item = $order->get_item( $history->order_item_id );
			?>
				<tr>
					<td>
						<a href="<?php echo wp_kses_post( get_edit_post_link( $history->subscription_id ) ); ?>" target="_blank">#<?php echo esc_html( $history->subscription_id ); ?> - <?php echo esc_html( $order_item->get_name() ); ?></a>
					</td>
					<td>
						<?php echo $post_meta['trial'] == null ? esc_html( date( 'F d, Y', $post_meta['start_date'] ) ) : '+' . esc_html( $post_meta['trial'] ) . ' ' . __( 'free trial', 'sdevs_subscrpt' ); ?>
					</td>
					<td><?php echo wp_kses_post( Helper::format_price_with_order_item( $order_item->get_total(), $order_item->get_id() ) ); ?></td>
					<td><?php echo esc_html( $post_meta['trial'] == null ? date( 'F d, Y', $post_meta['next_date'] ) : date( 'F d, Y', $post_meta['start_date'] ) ); ?></td>
					<td><?php echo esc_html( get_post_status( $history->subscription_id ) ); ?></td>
				</tr>
				<?php
		endforeach;
		?>
	</tbody>
</table>
