<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Email
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Email {

	/**
	 * The contsructor method.
	 */
	public function __construct() {
		add_action( 'woocommerce_email_after_order_table', array( $this, 'add_subscription_table' ) );
	}

	/**
	 * Add subscription sections inside order mail.
	 *
	 * @param \WC_Order $order Order Object.
	 *
	 * @return void
	 */
	public function add_subscription_table( $order ) {
		$histories = Helper::get_subscriptions_from_order( $order->get_id() );

		if ( count( $histories ) > 0 ) :
			?>
			<div style="margin-bottom: 40px;">
				<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
					<tbody>
						<tr>
							<h2><?php esc_html_e( 'Related Subscriptions', 'sdevs_subscrpt' ); ?></h2>
							<?php
							if ( ! $order->has_status( 'completed' ) ) :
								?>
								<p><small>Your subscription will be activated when order status is completed.</small></p>
							<?php endif; ?>
						</tr>
						<?php
						foreach ( $histories as $history ) :
							$item                       = $order->get_item( $history->order_item_id );
							$item_meta                  = wc_get_order_item_meta( $history->order_item_id, '_subscrpt_meta', true );
							$subscription_id            = $history->subscription_id;
							$subscription_status_object = get_post_status_object( get_post_status( $subscription_id ) );
							$cost                       = get_post_meta( $subscription_id, '_subscrpt_price', true );
							$has_trial                  = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;
							$start_date                 = get_post_meta( $subscription_id, '_subscrpt_start_date', true );
							$next_date                  = get_post_meta( $subscription_id, '_subscrpt_next_date', true );
							?>
								<tr>
									<th class="td" scope="row" colspan="3" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: center;"><?php echo get_the_title( $subscription_id ); ?></th>
								</tr>
								<tr>
									<th class="td" scope="row" colspan="3" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;"><a href="<?php echo get_permalink( $item->get_product_id() ); ?>"><?php echo $item->get_name(); ?></a>
										<strong class="product-quantity">×&nbsp;<?php echo esc_html( $item->get_quantity() ); ?></strong>
									</th>
								</tr>
								<tr>
									<th class="td" scope="row" colspan="2" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;"><?php esc_html_e( 'Status:', 'sdevs_subscrpt' ); ?> </th>
									<td class="td" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;"><?php echo esc_html( $subscription_status_object->label ); ?></td>
								</tr>
								<tr>
									<th class="td" scope="row" colspan="2" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;">
										<?php esc_html_e( 'Recurring amount:', 'sdevs_subscrpt' ); ?> </th>
									<td class="td" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;"><?php echo wp_kses_post( Helper::format_price_with_order_item( $cost, $item->get_id() ) ); ?></td>
								</tr>
								<?php if ( ! $has_trial ) { ?>
									<tr>
										<th class="td" scope="row" colspan="2" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;"><?php esc_html_e( 'Next billing on', 'sdevs_subscrpt' ); ?>: </th>
										<td class="td" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;"><?php echo ! empty( $next_date ) ? esc_html( gmdate( 'F d, Y', $next_date ) ) : '-'; ?></td>
									</tr>
								<?php } else { ?>
									<tr>
										<th class="td" scope="row" colspan="2" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;"><?php esc_html_e( 'Trial', 'sdevs_subscrpt' ); ?>: </th>
										<td class="td" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;"><?php echo esc_html( $item_meta['trial'] ); ?></td>
									</tr>
									<tr>
										<th class="td" scope="row" colspan="2" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;"><?php esc_html_e( 'First billing on', 'sdevs_subscrpt' ); ?>: </th>
										<td class="td" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;"><?php echo ! empty( $start_date ) ? esc_html( gmdate( 'F d, Y', $start_date ) ) : '-'; ?></td>
									</tr>
								<?php } ?>
								<tr>
									<th class="td" scope="row" colspan="3" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left; padding-bottom: 30px;"></th>
								</tr>
								<?php
						endforeach;
						?>
					</tbody>
				</table>
			</div>
			<?php
		endif;
	}
}
