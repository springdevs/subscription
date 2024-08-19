<?php
/**
 * Subscriptions Table
 *
 * @var int $current_page
 * @var \WP_Query $postslist
 *
 * This template can be overridden by copying it to yourtheme/simple-booking/myaccount/subscriptions.php
 *
 * @package SpringDevs\Subscription
 */

use SpringDevs\Subscription\Illuminate\Helper;
?>

<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table my_account_subscrpt">
	<thead>
		<tr>
			<th scope="col" class="subscrpt-id"><?php esc_html_e( 'Subscription', 'sdevs_subscrpt' ); ?></th>
			<th scope="col" class="order-status"><?php esc_html_e( 'Status', 'sdevs_subscrpt' ); ?></th>
			<th scope="col" class="order-product"><?php esc_html_e( 'Product', 'sdevs_subscrpt' ); ?></th>
			<th scope="col" class="subscrpt-next-date"><?php esc_html_e( 'Next Payment', 'sdevs_subscrpt' ); ?></th>
			<th scope="col" class="subscrpt-total"><?php esc_html_e( 'Total', 'sdevs_subscrpt' ); ?></th>
			<th scope="col" class="subscrpt-action"></th>
		</tr>
	</thead>
	<tbody>
		<?php
		if ( $postslist->have_posts() ) :
			while ( $postslist->have_posts() ) :
				$postslist->the_post();
				$product_id    = get_post_meta( get_the_ID(), '_subscrpt_product_id', true );
				$order_id      = get_post_meta( get_the_ID(), '_subscrpt_order_id', true );
				$order_item_id = get_post_meta( get_the_ID(), '_subscrpt_order_item_id', true );
				$trial         = get_post_meta( get_the_ID(), '_subscrpt_trial', true );
				$trial_mode    = get_post_meta( get_the_ID(), '_subscrpt_trial_mode', true );
				$trial_mode    = empty( $trial_mode ) ? 'off' : $trial_mode;
				$start_date    = get_post_meta( get_the_ID(), '_subscrpt_start_date', true );
				$next_date     = get_post_meta( get_the_ID(), '_subscrpt_next_date', true );
				$order         = wc_get_order( $order_id );
				$order_item    = $order->get_item( $order_item_id );

				$post_status_object = get_post_status_object( get_post_status() );
				$product_name       = $order_item->get_name();
				$product_link       = get_the_permalink( $product_id );
				$product_price_html = Helper::format_price_with_order_item( get_post_meta( get_the_ID(), '_subscrpt_price', true ), $order_item->get_id() );
				?>
				<tr>
					<td data-title="Subscription"><?php the_ID(); ?></td>
					<td data-title="Status"><span class="subscrpt-<?php echo esc_attr( $post_status_object->name ); ?>"><?php echo esc_html( strlen( $post_status_object->label ) > 9 ? substr( $post_status_object->label, 0, 6 ) . '...' : $post_status_object->label ); ?></span></td>
					<td data-title="Product"><a href="<?php echo esc_html( $product_link ); ?>" target="_blank"><?php echo esc_html( $product_name ); ?></a></td>
					<?php if ( 'on' !== $trial_mode ) : ?>
						<td data-title="Next Payment"><?php echo esc_html( $next_date ? gmdate( 'F d, Y', $next_date ) : '-' ); ?></td>
					<?php else : ?>
						<td data-title="Next Payment"><small>First Billing : </small><?php echo esc_html( gmdate( 'F d, Y', $start_date ) ); ?></td>
					<?php endif; ?>
					<td data-title="Total"><?php echo wp_kses_post( $product_price_html ); ?></td>
					<td data-title="Actions">
						<a href="<?php echo esc_html( wc_get_endpoint_url( 'view-subscription', get_the_ID(), wc_get_page_permalink( 'myaccount' ) ) ); ?>" class="woocommerce-button <?php echo esc_attr( $wp_button_class ); ?> button view"><span class="dashicons dashicons-visibility"></span></a>
					</td>
				</tr>
				<?php
			endwhile;
			wp_reset_postdata();
		else :
			?>
			<tr>
				<td colspan="6">
					<p style="text-align: center;">
						<?php echo esc_html_e( 'No subscriptions available yet.', 'sdevs_subscrpt' ); ?>
					</p>
				</td>
			</tr>
			<?php
		endif;
		?>
	</tbody>
</table>

<?php if ( 1 < $postslist->max_num_pages ) : ?>
	<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
		<?php if ( 1 !== $current_page ) : ?>
			<a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button<?php echo esc_attr( $wp_button_class ); ?>" href="<?php echo esc_url( wc_get_endpoint_url( 'subscriptions', $current_page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'sdevs_subscrpt' ); ?></a>
		<?php endif; ?>

		<?php if ( intval( $postslist->max_num_pages ) !== $current_page ) : ?>
			<a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button<?php echo esc_attr( $wp_button_class ); ?>" href="<?php echo esc_url( wc_get_endpoint_url( 'subscriptions', $current_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'sdevs_subscrpt' ); ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>
