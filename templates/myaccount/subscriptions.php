<?php

/**
 * Subscriptions Table
 *
 * This template can be overridden by copying it to yourtheme/simple-booking/myaccount/subscriptions.php
 */

use SpringDevs\Subscription\Illuminate\Helper;

$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

$args = array(
	'author'         => get_current_user_id(),
	'posts_per_page' => 10,
	'paged'          => $paged,
	'post_type'      => 'subscrpt_order',
	'post_status'    => array( 'pending', 'active', 'on_hold', 'cancelled', 'expired', 'pe_cancelled' ),
);

$postslist = new WP_Query( $args );
?>

<table class="shop_table my_account_subscrpt">
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
				$post_meta = get_post_meta( get_the_ID(), '_order_subscrpt_meta', true );
				$product_id = get_post_meta( get_the_ID(), '_subscrpt_product_id', true );
				$order = wc_get_order( $post_meta['order_id'] );
				$order_item = $order->get_item( $post_meta['order_item_id'] );

				$product_name       = $order_item->get_name();
				$product_link       = get_the_permalink( $product_id );
				$product_price_html = Helper::format_price_with_order_item( $order_item->get_total(), $order_item->get_id() );
				?>
				<tr>
					<td><?php the_ID(); ?></td>
					<td><span class="subscrpt-<?php echo get_post_status(); ?>"><?php echo get_post_status(); ?></span></td>
					<td><a href="<?php echo esc_html( $product_link ); ?>" target="_blank"><?php echo esc_html( $product_name ); ?></a></td>
					<?php if ( $post_meta['trial'] == null ) : ?>
						<td><?php echo date( 'F d, Y', $post_meta['next_date'] ); ?></td>
					<?php else : ?>
						<td><small>First Billing : </small><?php echo date( 'F d, Y', $post_meta['start_date'] ); ?></td>
					<?php endif; ?>
					<td><?php echo wp_kses_post( $product_price_html ); ?></td>
					<td>
						<a href="<?php echo get_permalink( wc_get_page_id( 'myaccount' ) ) . 'view-subscription/' . get_the_ID(); ?>" class="woocommerce-button button view">View</a>
					</td>
				</tr>
				<?php
			endwhile;
			next_posts_link( 'Older Entries', $postslist->max_num_pages );
			previous_posts_link( 'Next Entries &raquo;' );
			wp_reset_postdata();
		endif;
		?>
	</tbody>
</table>
