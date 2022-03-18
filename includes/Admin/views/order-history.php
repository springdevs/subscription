<table class="widefat striped">
	<thead>
		<tr>
			<th><?php _e( 'Order', 'sdevs_subscrpt' ); ?></th>
			<th></th>
			<th><?php _e( 'Date', 'sdevs_subscrpt' ); ?></th>
			<th><?php _e( 'Status', 'sdevs_subscrpt' ); ?></th>
			<th><?php _e( 'Amount', 'sdevs_subscrpt' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $order_histories as $order_history ) : ?>
			<?php
			$order = wc_get_order( $order_history['order_id'] );
			?>
			<tr>
				<td><a href="<?php echo wp_kses_post( get_edit_post_link( $order_history['order_id'] ) ); ?>" target="_blank"><?php echo wp_kses_post( $order_history['order_id'] ); ?></a></td>
				<td><?php echo wp_kses_post( $order_history['stats'] ); ?></td>
				<td>
					<?php
					if ( $order ) {
						echo wp_kses_post( date( 'F d, Y', strtotime( $order->get_date_created() ) ) );}
					?>
				</td>
				<td>
				<?php
				if ( $order ) {
					echo esc_html( $order->get_status() );}
				?>
				</td>
				<td><?php echo wp_kses_post( $order_history['subtotal_price_html'] ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
