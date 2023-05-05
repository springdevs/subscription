<table class="widefat striped">
	<thead>
		<tr>
			<th><?php

			use SpringDevs\Subscription\Illuminate\Helper;

			_e( 'Order', 'sdevs_subscrpt' ); ?></th>
			<th></th>
			<th><?php _e( 'Date', 'sdevs_subscrpt' ); ?></th>
			<th><?php _e( 'Status', 'sdevs_subscrpt' ); ?></th>
			<th><?php _e( 'Amount', 'sdevs_subscrpt' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $order_histories as $order_history ) : ?>
			<?php
			$order      = wc_get_order( $order_history->order_id );
			$order_item = $order->get_item( $order_history->order_item_id );
			?>
			<tr>
				<td><a href="<?php echo wp_kses_post( get_edit_post_link( $order_history->order_id ) ); ?>" target="_blank"><?php echo wp_kses_post( $order_history->order_id ); ?></a></td>
				<td><?php echo wp_kses_post( order_relation_type_cast( $order_history->type ) ); ?></td>
				<td>
					<?php
					if ( $order ) {
						echo wp_kses_post( gmdate( 'F d, Y', strtotime( $order->get_date_created() ) ) );}
					?>
				</td>
				<td>
				<?php
				if ( $order ) {
					echo esc_html( $order->get_status() );}
				?>
				</td>
				<td><?php echo wp_kses_post( Helper::format_price_with_order_item( $order_item->get_total(), $order_item->get_id() ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
