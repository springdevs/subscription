<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Single subscription page
 *
 * @var WC_Order $order
 * @var WC_Order_Item $order_item
 * @var array $related_orders
 * @var string $start_date
 * @var string $next_date
 * @var string|null $trial
 * @var string|null $trial_mode
 * @var string $status
 * @var array $action_buttons
 * @var bool $is_grace_period
 * @var int $grace_remaining
 * @var float $price Renewal total, after any discount that recurs.
 * @var float $price_excl_tax Renewal subtotal before discount and tax.
 * @var float $tax Tax on the discounted renewal amount.
 * @var float $discount Discount that applies to each renewal. 0 when none recurs.
 *
 * This template can be overridden by copying it to <your_theme>/subscription/myaccount/single.php
 *
 * @package SpringDevs\Subscription
 */

use SpringDevs\Subscription\Illuminate\Helper;

if ( ! isset( $id ) ) {
	return;
}

if ( ! get_the_title( $id ) ) {
	return;
}

do_action( 'before_single_subscrpt_content', $id );
?>
<style>
	.auto-renew-on,
	.subscription_renewal_early,
	.auto-renew-off {
		margin-bottom: 10px;
	}
	.subscrpt_action_buttons {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
	}
	.product-name .subscrpt-plan-term {
		display: block;
		margin-top: 4px;
		font-size: 13px;
		color: #6b7280;
	}
</style>
<table class="woocommerce-table woocommerce-table--order-details shop_table order_details subscription_details">
	<tbody>
		<tr>
			<td><?php esc_html_e( 'Order', 'subscription' ); ?></td>
			<td><a href="<?php echo esc_html( wc_get_endpoint_url( 'view-order', $order->get_id(), wc_get_page_permalink( 'myaccount' ) ) ); ?>" target="_blank"># <?php echo esc_html( $order->get_id() ); ?></a></td>
		</tr>
		<tr>
			<td><?php esc_html_e( 'Status', 'subscription' ); ?></td>
			<td>
				<?php if ( $is_grace_period && $grace_remaining > 0 ) : ?>
					<span class="subscrpt-legacy-status subscrpt-legacy-status--active grace-active">
						Active

						<?php
							$grace_remaining_text = sprintf(
								// translators: Number of days remaining in grace period.
								__( '%d days remaining!', 'subscription' ),
								$grace_remaining
							);
						?>
						<span class="grace-icon" data-tooltip="<?php echo esc_attr( $grace_remaining_text ); ?>">
							<span class="dashicons dashicons-warning"></span>
						</span>
					</span>
				<?php else : ?>
					<span class="subscrpt-legacy-status subscrpt-legacy-status--<?php echo esc_attr( strtolower( $status ) ); ?>">
						<?php echo esc_html( $verbose_status ); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>

		<?php if ( $is_grace_period && $grace_remaining > 0 ) : ?>
			<tr>
				<td><?php esc_html_e( 'Grace Period Ends On', 'subscription' ); ?></td>
				<td><?php echo esc_html( $grace_end_date ); ?></td>
			</tr>
		<?php endif; ?>

		<?php if ( null != $trial && 'off' !== $trial ) : ?>
		<tr>
			<td><?php esc_html_e( 'Trial', 'subscription' ); ?></td>
			<td><?php echo esc_html( $trial ); ?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td>
			<?php
			if ( 'null' == $trial || 'off' === $trial_mode ) {
				esc_html_e( 'Start date', 'subscription' );
			} elseif ( 'extended' === $trial_mode ) {
				esc_html_e( 'Trial End & Subscription Start', 'subscription' );
			} else {
				esc_html_e( 'Trial End & First Billing', 'subscription' );
			}
			?>
			</td>
			<td><?php echo esc_html( $start_date ); ?></td>
		</tr>
		<?php if ( ( null == $trial || in_array( $trial_mode, array( 'off', 'extended' ), true ) ) && get_post_meta( $id, '_subscrpt_next_date', true ) ) : ?>
			<tr>
				<td>
				<?php
					esc_html_e( 'Next payment date', 'subscription' );
				?>
				</td>
				<td>
					<?php echo esc_html( $next_date ); ?>
				</td>
			</tr>
		<?php endif; ?>

		<!-- get the max_no_payment info using Helper -->
		<?php $remaining_payments = subscrpt_get_remaining_payments( $id ); ?>
		<?php $payments_made = subscrpt_count_payments_made( $id ); ?>
		<?php
		// Get maximum payments using helper function (handles variations properly)
		$product_id   = get_post_meta( $id, '_subscrpt_product_id', true );
		$max_payments = subscrpt_get_max_payments( $id );

		// Get Payment Type and Access Duration Information
		$payment_type = $product_id ? get_post_meta( $product_id, '_subscrpt_payment_type', true ) : 'recurring';
		$payment_type = $payment_type ?: 'recurring'; // Default to recurring if empty

		// Also check subscription's own meta data
		$subscription_payment_type = get_post_meta( $id, '_subscrpt_payment_type', true );
		$subscription_max_payments = get_post_meta( $id, '_subscrpt_max_no_payment', true );

		// Also check if this subscription has variation data
		$variation_id = get_post_meta( $id, '_subscrpt_variation_id', true );
		if ( $variation_id ) {
			$variation_payment_type = get_post_meta( $variation_id, '_subscrpt_payment_type', true );
			$variation_max_payments = get_post_meta( $variation_id, '_subscrpt_max_no_payment', true );

			// Use variation data if product data is not available
			if ( empty( $payment_type ) || 'recurring' === $payment_type ) {
				if ( ! empty( $variation_payment_type ) ) {
					$payment_type = $variation_payment_type;
				}
			}
			if ( empty( $max_payments ) && ! empty( $variation_max_payments ) ) {
				$max_payments = $variation_max_payments;
			}
		}

		// Use subscription's own data if available and more specific
		if ( ! empty( $subscription_payment_type ) ) {
			$payment_type = $subscription_payment_type;
		}
		if ( ! empty( $subscription_max_payments ) ) {
			$max_payments = $subscription_max_payments;
		}

		// Final fallback: Infer payment type from max_payments if not explicitly set
		if ( ( empty( $payment_type ) || 'recurring' === $payment_type ) && $max_payments > 0 ) {
			$payment_type = 'split_payment';
		}

		// Ensure max_payments is properly set for display
		$max_payments = (int) $max_payments;
		?>

		<!-- show payment progress if max_payments is set and not unlimited -->
		<?php if ( ( $remaining_payments !== 'unlimited' && $max_payments > 0 ) || ( 'split_payment' === $payment_type && $max_payments > 0 ) ) : ?>
			<tr>
				<td><?php esc_html_e( 'Total Payments', 'subscription' ); ?></td>
				<td><?php echo esc_html( $payments_made ) . ' / ' . esc_html( $max_payments ); ?></td>
			</tr>
		<?php endif; ?>
		
		<tr>
			<td><?php esc_html_e( 'Payment Type', 'subscription' ); ?></td>
			<td>
				<?php
				if ( 'split_payment' === $payment_type ) {
					esc_html_e( 'Split Payment', 'subscription' );
				} else {
					esc_html_e( 'Recurring', 'subscription' );
				}
				?>
				<!-- DEBUG: Show raw values -->
				<!-- <small style="color: #666; font-size: 11px; display: block;">
					Debug: Product ID: <?php echo esc_html( $product_id ); ?>, 
					Variation ID: <?php echo esc_html( $variation_id ?: 'None' ); ?>, 
					Type: <?php echo esc_html( $payment_type ); ?>, 
					Max: <?php echo esc_html( $max_payments ); ?>
				</small> -->
			</td>
		</tr>
		
		<!-- Access Duration Information for Split Payments -->
		<?php if ( 'split_payment' === $payment_type && $max_payments > 0 ) : ?>
			<?php
			// Plan subscriptions store access-ends on the subscription; classic ones on
			// the product. Prefer the subscription meta, fall back to the product.
			$access_ends_timing   = get_post_meta( $id, '_subscrpt_access_ends_timing', true );
			$access_ends_timing   = $access_ends_timing ? $access_ends_timing : ( get_post_meta( $product_id, '_subscrpt_access_ends_timing', true ) ?: 'after_full_duration' );
			$custom_duration_time = get_post_meta( $id, '_subscrpt_custom_access_duration_time', true ) ?: ( get_post_meta( $product_id, '_subscrpt_custom_access_duration_time', true ) ?: 1 );
			$custom_duration_type = get_post_meta( $id, '_subscrpt_custom_access_duration_type', true ) ?: ( get_post_meta( $product_id, '_subscrpt_custom_access_duration_type', true ) ?: 'months' );

			// Calculate access end date if Pro version is available
			$access_end_date_string = null;
			if ( function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated() ) {
				if ( class_exists( '\SpringDevs\SubscriptionPro\Illuminate\SplitPaymentHandler' ) ) {
					$access_end_date_string = \SpringDevs\SubscriptionPro\Illuminate\SplitPaymentHandler::get_access_end_date_string( $id );
				}
			}
			?>
			<tr>
				<td><?php esc_html_e( 'Access Duration', 'subscription' ); ?></td>
				<td>
					<?php
					switch ( $access_ends_timing ) {
						case 'lifetime':
							esc_html_e( 'Lifetime access after completion', 'subscription' );
							break;
						case 'after_full_duration':
							esc_html_e( 'Full subscription duration', 'subscription' );
							break;
						case 'custom_duration':
							printf(
								/* translators: %1$s: duration time, %2$s: duration type */
								esc_html__( '%1$s %2$s after first payment', 'subscription' ),
								esc_html( $custom_duration_time ),
								esc_html( ucfirst( Helper::get_typos( $custom_duration_time, $custom_duration_type, true ) ) )
							);
							break;
						default:
							esc_html_e( 'Full subscription duration', 'subscription' );
							break;
					}
					?>
				</td>
			</tr>
			
			<!-- Show calculated access end date if available -->
			<?php if ( $access_end_date_string ) : ?>
			<tr>
				<td><?php esc_html_e( 'Access Ends On', 'subscription' ); ?></td>
				<td><?php echo esc_html( $access_end_date_string ); ?></td>
			</tr>
			<?php endif; ?>
		<?php endif; ?>
		
		<?php if ( ! empty( $order->get_payment_method_title() ) ) : ?>
		<tr>
			<td><?php esc_html_e( 'Payment', 'subscription' ); ?></td>
			<td>
				<span data-is_manual="yes" class="subscription-payment-method"><?php echo esc_html( $order->get_payment_method_title() ); ?></span>
			</td>
		</tr>
		<?php endif; ?>
		<?php if ( 0 < count( $action_buttons ) ) : ?>
			<tr>
				<td><?php echo esc_html_e( 'Actions', 'subscription' ); ?></td>
				<td class="subscrpt_action_buttons">
					<?php foreach ( $action_buttons as $action_button ) : ?>
						<a href="<?php echo esc_attr( $action_button['url'] ); ?>" class="button
											<?php
											if ( isset( $action_button['class'] ) ) {
												echo esc_attr( $action_button['class'] );}
											?>
						<?php echo esc_attr( $wp_button_class ); ?>"><?php echo esc_html( $action_button['label'] ); ?></a>
						<?php endforeach; ?>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<?php do_action( 'subscrpt_before_subscription_totals', (int) $id ); ?>

<h2><?php echo esc_html_e( 'Subscription Totals', 'subscription' ); ?></h2>
<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
	<thead>
		<tr>
			<th class="product-name"><?php echo esc_html_e( 'Product', 'subscription' ); ?></th>
			<th class="product-total"><?php echo esc_html_e( 'Total', 'subscription' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		$product_name       = $order_item->get_name();
		$product_link       = get_permalink( $order_item->get_variation_id() !== 0 ? $order_item->get_variation_id() : $order_item->get_product_id() );
		$order_item_meta    = $order_item->get_meta( '_subscrpt_meta', true );
		$time               = '1' === $order_item_meta['time'] ? null : $order_item_meta['time'];
		$type               = subscrpt_get_typos( $order_item_meta['time'], $order_item_meta['type'] );
		$product_price_html = Helper::format_price_with_order_item( $price, $order_item->get_id() );
		?>
		<tr class="order_item">
			<td class="product-name">
				<a href="<?php echo esc_html( $product_link ); ?>"><?php echo esc_html( $product_name ); ?></a>
				<strong class="product-quantity">× <?php echo esc_html( $order_item->get_quantity() ); ?></strong>
				<?php
				$plan_label = function_exists( 'subscrpt_get_subscription_plan_label' ) ? subscrpt_get_subscription_plan_label( $id ) : '';
				if ( $plan_label ) :
					?>
					<span class="subscrpt-plan-term"><?php printf( esc_html__( 'Plan: %s', 'subscription' ), esc_html( $plan_label ) ); ?></span>
				<?php endif; ?>
			</td>
			<td class="product-total">
				<span class="woocommerce-Price-amount amount">
					<?php
					$unit_price    = (float) get_post_meta( $id, '_subscrpt_price', true );
					$display_price = $unit_price * max( 1, (int) $order_item->get_quantity() );
					echo wp_kses_post( Helper::format_price_with_order_item( $display_price, $order_item->get_id() ) );
					?>
				</span>
			</td>
		</tr>
	</tbody>
	<tfoot>
		<tr>
			<th scope="row"><?php esc_html_e( 'Subtotal', 'subscription' ); ?>:</th>
			<td>
				<span class="woocommerce-Price-amount amount">
					<?php echo wp_kses_post( wc_price( $price_excl_tax, array( 'currency' => $order->get_currency() ) ) ); ?>
				</span>
			</td>
		</tr>

		<?php if ( ! empty( $discount ) && $discount > 0 ) : ?>
		<tr class="subscrpt-recurring-discount">
			<th scope="row"><?php esc_html_e( 'Discount', 'subscription' ); ?>:</th>
			<td>
				<span class="woocommerce-Price-amount amount">
					<?php
					printf(
						'-%s',
						wp_kses_post( wc_price( $discount, array( 'currency' => $order->get_currency() ) ) )
					);
					?>
				</span>
			</td>
		</tr>
		<?php endif; ?>

		<?php if ( $tax > 0 ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Tax', 'subscription' ); ?>:</th>
			<td>
				<span class="woocommerce-Price-amount amount">
					<?php echo wp_kses_post( wc_price( $tax, array( 'currency' => $order->get_currency() ) ) ); ?>
				</span>
			</td>
		</tr>
		<?php endif; ?>

		<tr>
			<th scope="row"><?php esc_html_e( 'Renew', 'subscription' ); ?>:</th>
			<td>
				<span class="woocommerce-Price-amount amount">
					<?php echo wp_kses_post( $product_price_html ); ?>
				</span>
			</td>
		</tr>
	</tfoot>
</table>

<?php do_action( 'subscrpt_after_subscription_totals', (int) $id ); ?>

<!-- Show related subscription orders -->
<?php
/*
 * Sequence number within this subscription: 1 = first (parent) order, then each
 * renewal in order. Helper::get_related_orders() returns newest-first, so the
 * map is built from the reversed list. Relations whose order no longer exists
 * are skipped here as well, keeping the numbering gapless with the rendered rows.
 */
$related_order_seq = [];
foreach ( array_reverse( $related_orders ) as $subscrpt_relation ) {
	if ( isset( $related_order_seq[ $subscrpt_relation->order_id ] ) || ! wc_get_order( $subscrpt_relation->order_id ) ) {
		continue;
	}
	$related_order_seq[ $subscrpt_relation->order_id ] = count( $related_order_seq ) + 1;
}
?>
<h2><?php echo esc_html_e( 'Related Orders', 'subscription' ); ?></h2>
<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
	<thead>
		<tr>
			<th class="order-number"><?php echo esc_html_e( '#', 'subscription' ); ?></th>
			<th class="order-type"><?php echo esc_html_e( 'Type', 'subscription' ); ?></th>
			<th class="order-date"><?php echo esc_html_e( 'Date', 'subscription' ); ?></th>
			<th class="order-status"><?php echo esc_html_e( 'Status', 'subscription' ); ?></th>
			<th class="order-total"><?php echo esc_html_e( 'Total', 'subscription' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $related_orders ) ) : ?>
			<tr class="order_item">
				<td colspan="5" class="no-orders">
					<?php echo esc_html_e( 'No related orders found.', 'subscription' ); ?>
				</td>
			</tr>
		<?php endif; ?>

		<?php foreach ( $related_orders as $related_order ) : ?>
			<?php
				$order_id = $related_order->order_id;
				$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

				$order_type       = $related_order->type;
				$order_type_label = wps_subscription_order_relation_type_cast( $order_type );

				$order_created_date = $order->get_date_created()->date_i18n( get_option( 'date_format' ) );

				$order_status      = $order->get_status();
				$order_status_name = wc_get_order_status_name( $order_status );

				$order_total           = $order->get_total();
				$formatted_order_total = wc_price( $order_total, array( 'currency' => $order->get_currency() ) );
			?>

			<tr class="order_item">
				<td class="order-number">
					<?php
					// translators: %s: WooCommerce order number.
					$order_number_label = sprintf( __( 'Order #%s', 'subscription' ), $order->get_order_number() );
					?>
					<a href="<?php echo esc_url( wc_get_endpoint_url( 'view-order', $order_id, wc_get_page_permalink( 'myaccount' ) ) ); ?>" title="<?php echo esc_attr( $order_number_label ); ?>" aria-label="<?php echo esc_attr( $order_number_label ); ?>">
						<?php echo esc_html( number_format_i18n( $related_order_seq[ $order_id ] ?? 0 ) ); ?>
					</a>
				</td>
				<td class="order-type">
					<?php echo esc_html( $order_type_label ); ?>
				</td>
				<td class="order-date">
					<?php echo esc_html( $order_created_date ); ?>
				</td>
				<td class="order-status">
					<span class="order-status-<?php echo esc_attr( $order_status ); ?>">
						<?php echo esc_html( $order_status_name ); ?>
					</span>
				</td>
				<td class="order-total">
					<?php echo wp_kses_post( $formatted_order_total ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<section class="woocommerce-customer-details">
	<h2 class="woocommerce-column__title"><?php esc_html_e( 'Billing address', 'subscription' ); ?></h2>
	<address>
		<?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?>
		<p class="woocommerce-customer-details--phone"><?php echo esc_html( $order->get_billing_phone() ); ?></p>
		<p class="woocommerce-customer-details--email"><?php echo esc_html( $order->get_billing_email() ); ?></p>
	</address>
</section>
<div class="clear"></div>
