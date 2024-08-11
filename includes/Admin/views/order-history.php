<table class="widefat striped">
	<thead>
		<tr>
			<th><?php
			esc_html_e( 'Order', 'sdevs_subscrpt' ); ?></th>
			<th></th>
			<th><?php esc_html_e( 'Date', 'sdevs_subscrpt' ); ?></th>
			<th><?php esc_html_e( 'Status', 'sdevs_subscrpt' ); ?></th>
			<th><?php esc_html_e( 'Amount', 'sdevs_subscrpt' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $order_histories as $order_history ) : ?>
			<?php
			$order      = wc_get_order( $order_history->order_id );
			$order_item = $order->get_item( $order_history->order_item_id );
			?>
			<tr>
				<td><a href="<?php echo wp_kses_post( $order->get_edit_order_url() ); ?>" target="_blank"><?php echo wp_kses_post( $order_history->order_id ); ?></a></td>
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
					echo esc_html( sdevs_order_status_label( $order->get_status() ) );
				}
				?>
				</td>
				<td>
				<?php
				echo wc_price(
					$order_item->get_total(),
					array(
						'currency' => $order->get_currency(),
					)
				);
				?>
			</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
