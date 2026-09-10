<?php
/**
 * Standalone subscription details page view.
 *
 * Rendered by Menu::render_subscription_details_page(). Matches the
 * WPSubscription admin design system (wpsubs-* components + :root tokens).
 *
 * @package SpringDevs\Subscription\Admin
 *
 * @var int       $subscription_id   Subscription post ID.
 * @var array     $subscription_data Structured data from Helper::get_subscription_data().
 * @var array     $rows              Filtered info rows from Subscriptions::get_info_rows().
 * @var \WC_Order $order             Related WooCommerce order.
 * @var mixed     $order_item        Related order line item.
 * @var array     $actions           Allowed action slugs for the current status.
 * @var array     $actions_data      Map of action slug => label/value.
 * @var array     $order_histories   Related order relation rows.
 * @var string    $list_url          Subscriptions list URL.
 * @var string    $form_action       URL the status form posts to.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SpringDevs\Subscription\Illuminate\Helper;

$product_name  = $order_item ? $order_item->get_name() : '-';
$product_id    = $order_item ? $order_item->get_product_id() : 0;
$product_link  = $product_id ? get_the_permalink( $product_id ) : '';
$product_obj   = $product_id ? wc_get_product( $product_id ) : null;
$product_image = $product_obj ? $product_obj->get_image( 'thumbnail', array( 'class' => 'subscrpt-plan-img' ) ) : '';

$subscrpt_status = $subscription_data['status'] ?? get_post_status( $subscription_id );
$verbose_status  = Helper::get_verbose_status( $subscrpt_status );

$badge_mod_map = array(
	'active'       => 'active',
	'pending'      => 'pending',
	'pe_cancelled' => 'pending-cancel',
	'cancelled'    => 'cancelled',
	'expired'      => 'expired',
	'completed'    => 'completed',
	'draft'        => 'draft',
	'trash'        => 'trash',
);
$badge_mod     = $badge_mod_map[ $subscrpt_status ] ?? 'expired';

$is_grace_period = isset( $subscription_data['grace_period'] );
$grace_remaining = $subscription_data['grace_period']['remaining_days'] ?? 0;
$grace_end_date  = $subscription_data['grace_period']['end_date'] ?? '';
$grace_end_date  = ! empty( $grace_end_date ) ? wp_date( get_option( 'date_format' ) . ' - ' . get_option( 'time_format' ), strtotime( $grace_end_date ) ) : '';

if ( $is_grace_period && $grace_remaining > 0 ) {
	$badge_mod      = 'active';
	$verbose_status = __( 'Active', 'subscription' );
}

$customer_id    = $order ? $order->get_customer_id() : 0;
$customer_name  = $order ? $order->get_formatted_billing_full_name() : '-';
$customer_email = $order ? $order->get_billing_email() : '';
$customer_phone = $order ? $order->get_billing_phone() : '';
$customer_url   = $customer_id ? admin_url( 'user-edit.php?user_id=' . $customer_id ) : '';

// Page header: product title + meta description (badge · #id · email · next payment).
$header_title = ( $product_name && '-' !== $product_name )
	? $product_name
	/* translators: %s: Subscription ID */
	: sprintf( __( 'Subscription #%s', 'subscription' ), $subscription_id );

$next_payment_date = $subscription_data['next_date'] ?? '';
$next_payment_date = ! empty( $next_payment_date ) ? wp_date( get_option( 'date_format' ), strtotime( $next_payment_date ) ) : '';

$header_badge = '<span class="wpsubs-badge wpsubs-badge--' . esc_attr( $badge_mod ) . '">' . esc_html( $verbose_status );
if ( $is_grace_period && $grace_remaining > 0 ) {
	/* translators: %d: days remaining */
	$grace_title   = sprintf( __( '%d days remaining in grace period', 'subscription' ), $grace_remaining );
	$header_badge .= ' <span class="dashicons dashicons-warning" style="font-size:11px;width:11px;height:11px;color:#d97706;" title="' . esc_attr( $grace_title ) . '"></span>';
}
$header_badge .= '</span>';

$header_segments = array(
	$header_badge,
	'#' . (int) $subscription_id,
);
if ( $customer_email ) {
	$header_segments[] = esc_html( $customer_email );
}
if ( $next_payment_date ) {
	/* translators: %s: next payment date */
	$header_segments[] = esc_html( sprintf( __( 'Next payment on %s', 'subscription' ), $next_payment_date ) );
}
$header_desc_html = implode( '<span class="subscrpt-detail-head__sep">·</span>', $header_segments );

$rows = is_array( $rows ) ? $rows : array();

// Top summary tiles — pulled from the info rows so Pro's filtered values stay
// in sync. Their keys are marked used so they are not repeated below.
$summary_tiles = array();
if ( $order && isset( $subscription_data['price'] ) && '' !== $subscription_data['price'] ) {
	// Build the recurring price from structured data so the price and period can
	// be styled separately (price prominent, "/ period" small and muted).
	$timing_per    = (int) ( $subscription_data['schedule']['timing_per'] ?? 1 );
	$timing_option = $subscription_data['schedule']['timing_option'] ?? '';
	$period_label  = '';
	if ( $timing_option ) {
		$timing_unit  = ucfirst( Helper::get_typos( $timing_per, $timing_option ) );
		$period_label = $timing_per > 1
			? $timing_per . ' ' . $timing_unit
			: $timing_unit;
	}

	// Net of any discount that carries into renewals, with the original struck through.
	$recurring_totals = Helper::get_subscription_display_totals( $subscription_id, $order_item );
	$recurring_full   = $recurring_totals['full_excl'] + $recurring_totals['tax'] + $recurring_totals['discount_tax'];

	$recurring_value = wc_price( (float) $recurring_totals['total'], array( 'currency' => $order->get_currency() ) );

	if ( $recurring_totals['has_discount'] ) {
		$recurring_value = '<del aria-hidden="true" class="subscrpt-summary__was">'
			. wc_price( $recurring_full, array( 'currency' => $order->get_currency() ) )
			. '</del> ' . $recurring_value;
	}

	if ( $period_label ) {
		$recurring_value .= '<span class="subscrpt-summary__unit"> / ' . esc_html( $period_label ) . '</span>';
	}

	$summary_tiles[] = array(
		'label' => __( 'Recurring', 'subscription' ),
		'value' => $recurring_value,
	);
}
if ( ! empty( $subscription_data['start_date'] ) ) {
	$summary_tiles[] = array(
		'label' => __( 'Started', 'subscription' ),
		'value' => esc_html( wp_date( get_option( 'date_format' ), strtotime( $subscription_data['start_date'] ) ) ),
	);
}
// Always show the Next Payment tile; dash when there is no next date.
$summary_tiles[] = array(
	'label' => __( 'Next Payment', 'subscription' ),
	'value' => ! empty( $subscription_data['next_date'] )
		? esc_html( wp_date( get_option( 'date_format' ), strtotime( $subscription_data['next_date'] ) ) )
		: '-',
);
// Total payments is not part of $subscription_data; pull it from the info rows.
if ( isset( $rows['total_payments'] ) ) {
	$summary_tiles[] = array(
		'label' => __( 'Total Payments', 'subscription' ),
		'value' => $rows['total_payments']['value'],
	);
}

// Group the remaining info rows into cards. Unmapped keys — including rows added
// through the subscrpt_admin_info_rows filter (Pro) — fall into "Additional
// Details" so nothing is dropped. Status is shown as the header badge.
$used_keys = array(
	'cost'           => true,
	'start_date'     => true,
	'next_date'      => true,
	'total_payments' => true,
	'status'         => true,
);

// Plan card: product + qty render side by side; trial/signup fee as extra rows.
$plan_product = isset( $rows['product'] ) ? $rows['product']['value'] : '';
$plan_qty     = isset( $rows['quantity'] ) ? $rows['quantity']['value'] : '';
$plan_label   = function_exists( 'subscrpt_get_subscription_plan_label' ) ? subscrpt_get_subscription_plan_label( $subscription_id ) : '';
$plan_extra   = array();
foreach ( array( 'product', 'quantity', 'signup_fee', 'trial', 'trial_period' ) as $plan_key ) {
	$used_keys[ $plan_key ] = true;
	if ( in_array( $plan_key, array( 'signup_fee', 'trial', 'trial_period' ), true ) && isset( $rows[ $plan_key ] ) ) {
		$plan_extra[ $plan_key ] = $rows[ $plan_key ];
	}
}

$build_sections = function ( array $map ) use ( $rows, &$used_keys ) {
	$out = array();
	foreach ( $map as $section ) {
		$section_rows = array();
		foreach ( $section['keys'] as $key ) {
			if ( isset( $rows[ $key ] ) ) {
				$section_rows[ $key ] = $rows[ $key ];
				$used_keys[ $key ]    = true;
			}
		}
		if ( ! empty( $section_rows ) ) {
			$out[] = array(
				'title' => $section['title'],
				'rows'  => $section_rows,
			);
		}
	}
	return $out;
};

// Payment Method renders next to the Plan card (main column). Shows the gateway
// icon + title only — no key/value table. Extra rows (e.g. Stripe auto renewal)
// keep their kv layout beneath.
$used_keys['payment_method']      = true;
$used_keys['stripe_auto_renewal'] = true;
$payment_title                    = $order ? $order->get_payment_method_title() : '';
$payment_icon                     = '';
$payment_plugin                   = '';
if ( $order ) {
	$gateways     = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
	$gateway_id   = $order->get_payment_method();
	$gateway_obj  = isset( $gateways[ $gateway_id ] ) ? $gateways[ $gateway_id ] : null;
	$payment_icon = $gateway_obj ? $gateway_obj->get_icon() : '';

	// Resolve which plugin registered this gateway (e.g. several Stripe methods
	// all come from "WooCommerce Stripe Gateway"). Map the gateway class file to
	// its plugin folder, then read that plugin's header Name.
	if ( $gateway_obj ) {
		try {
			$gateway_file = wp_normalize_path( ( new ReflectionClass( $gateway_obj ) )->getFileName() );
			$plugins_dir  = wp_normalize_path( WP_PLUGIN_DIR );
			if ( $gateway_file && 0 === strpos( $gateway_file, $plugins_dir ) ) {
				$gateway_slug = strtok( ltrim( substr( $gateway_file, strlen( $plugins_dir ) ), '/' ), '/' );
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				foreach ( get_plugins() as $plugin_path => $plugin_data ) {
					if ( strtok( $plugin_path, '/' ) === $gateway_slug ) {
						$payment_plugin = $plugin_data['Name'];
						break;
					}
				}
			}
		} catch ( \Exception $e ) {
			$payment_plugin = '';
		}
	}
}
// Payment Method card is always shown; the title falls back to '-' when no
// gateway is resolved.
$has_payment = true;

// Secondary info → sidebar.
$side_sections = $build_sections(
	array(
		array(
			'title' => __( 'Billing & Shipping', 'subscription' ),
			'keys'  => array( 'billing_address', 'shipping_address' ),
		),
	)
);

// Any leftover rows → Additional Details (main column).
$main_sections = array();
$leftover_rows = array_diff_key( $rows, $used_keys );
if ( ! empty( $leftover_rows ) ) {
	$main_sections[] = array(
		'title' => __( 'Additional Details', 'subscription' ),
		'rows'  => $leftover_rows,
	);
}

// Resolve related orders once.
$related_rows = array();
foreach ( $order_histories as $history ) {
	$related_order = wc_get_order( $history->order_id );
	if ( ! $related_order ) {
		continue;
	}
	$related_rows[] = array(
		'order' => $related_order,
		'label' => ucfirst( str_replace( '-', ' ', (string) $history->type ) ),
	);
}

/*
 * Sequence number within this subscription: 1 = first (parent) order, then each
 * renewal in order. Helper::get_related_orders() returns newest-first, so the
 * sequence counts down across the rendered rows.
 */
$related_count = count( $related_rows );
foreach ( array_keys( $related_rows ) as $related_index ) {
	$related_rows[ $related_index ]['seq'] = $related_count - $related_index;
}

// Reusable per-page + date toolbar for the paginated tables. Uses the admin
// advanced-select component; the date options are injected client-side from the
// table's date column.
$subscrpt_render_table_tools = function () {
	?>
	<div class="subscrpt-card__tools">
		<?php
		wpsubs_render_adv_select(
			array(
				'name'        => 'subscrpt_date_filter',
				'placeholder' => __( 'All Dates', 'subscription' ),
				'value'       => '',
				'align'       => 'right',
				'class'       => 'subscrpt-filter-date',
				'options'     => array(
					array(
						'value' => '',
						'label' => __( 'All Dates', 'subscription' ),
					),
				),
			)
		);
		wpsubs_render_per_page_select(
			array(
				'name'  => 'subscrpt_per_page',
				'align' => 'right',
				'class' => 'subscrpt-filter-perpage',
			)
		);
		?>
	</div>
	<?php
};

/**
 * Shared context passed to every subscription-details extension hook so that
 * third-party plugins can render extra sections without re-querying.
 *
 * @var array $subscrpt_details_ctx {
 *     @type int       $subscription_id   Subscription post ID.
 *     @type array     $subscription_data Structured data from Helper::get_subscription_data().
 *     @type \WC_Order $order             Related WooCommerce order (or null).
 *     @type mixed     $order_item        Related order line item (or null).
 *     @type string    $status            Subscription post status.
 * }
 */
$subscrpt_details_ctx = array(
	'subscription_id'   => $subscription_id,
	'subscription_data' => $subscription_data,
	'order'             => $order,
	'order_item'        => $order_item,
	'status'            => $subscrpt_status,
);
?>
<div class="wp-subscription-admin-content list-page subscrpt-subs-details">

	<!-- Page header -->
	<div class="subscrpt-detail-head">
		<h1 class="subscrpt-detail-head__title"><?php echo esc_html( $header_title ); ?></h1>
		<div class="subscrpt-detail-head__desc"><?php echo wp_kses_post( $header_desc_html ); ?></div>
		<div class="subscrpt-detail-head__divider"></div>
	</div>

	<div class="subscrpt-detail-grid">

		<!-- Main column -->
		<div class="subscrpt-detail-main">

			<?php
			/**
			 * Fires at the top of the details main (left) column, before the summary strip.
			 *
			 * Echo a `.subscrpt-card` to add a full-width section here.
			 *
			 * @param array $subscrpt_details_ctx Subscription details context.
			 */
			do_action( 'subscrpt_details_main_top', $subscrpt_details_ctx );
			?>

			<!-- Summary strip -->
			<?php if ( ! empty( $summary_tiles ) ) : ?>
				<div class="subscrpt-summary">
					<?php foreach ( $summary_tiles as $tile ) : ?>
						<div class="subscrpt-summary__tile">
							<span class="subscrpt-summary__label"><?php echo esc_html( $tile['label'] ); ?></span>
							<span class="subscrpt-summary__value"><?php echo wp_kses_post( $tile['value'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $is_grace_period ) : ?>
				<!-- Grace period card -->
				<div class="subscrpt-card">
					<div class="subscrpt-card__head"><?php esc_html_e( 'Grace Period', 'subscription' ); ?></div>
					<div class="subscrpt-card__body">
						<table class="subscrpt-kv">
							<tbody>
								<tr>
									<th><?php esc_html_e( 'Remaining', 'subscription' ); ?></th>
									<td><?php echo esc_html( sprintf( /* translators: %d: days */ _n( '%d day', '%d days', (int) $grace_remaining, 'subscription' ), (int) $grace_remaining ) ); ?></td>
								</tr>
								<?php if ( $grace_end_date ) : ?>
									<tr>
										<th><?php esc_html_e( 'End Date', 'subscription' ); ?></th>
										<td><?php echo esc_html( $grace_end_date ); ?></td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>

			<!-- Plan + Payment Method side by side -->
			<?php
			$has_plan = $plan_product || $plan_qty || ! empty( $plan_extra );
			if ( $has_plan || $has_payment ) :
				?>
				<div class="subscrpt-plan-pay">
					<?php if ( $has_plan ) : ?>
						<!-- Plan card (product + qty side by side) -->
						<div class="subscrpt-card subscrpt-card--plan">
							<div class="subscrpt-card__head"><?php esc_html_e( 'Product', 'subscription' ); ?></div>
							<div class="subscrpt-card__body">
								<div class="subscrpt-plan-row">
									<div class="subscrpt-plan-thumb">
										<?php if ( $product_image ) : ?>
											<?php echo wp_kses_post( $product_image ); ?>
										<?php else : ?>
											<span class="subscrpt-plan-thumb__ph dashicons dashicons-format-image"></span>
										<?php endif; ?>
									</div>
									<div class="subscrpt-plan-meta">
										<span class="subscrpt-plan-name"><?php echo $plan_product ? wp_kses_post( $plan_product ) : '&mdash;'; ?></span>
										<?php if ( $plan_label ) : ?>
											<span class="subscrpt-plan-term">
												<?php
												/* translators: %s: plan name */
												printf( esc_html__( 'Plan: %s', 'subscription' ), esc_html( $plan_label ) );
												?>
											</span>
										<?php endif; ?>
										<span class="subscrpt-plan-qty">
											<?php
											/* translators: %s: quantity */
											printf( esc_html__( 'Quantity: %s', 'subscription' ), $plan_qty ? wp_kses_post( $plan_qty ) : '1' );
											?>
										</span>
									</div>
								</div>
								<?php if ( ! empty( $plan_extra ) ) : ?>
									<table class="subscrpt-kv subscrpt-plan-extra">
										<tbody>
											<?php foreach ( $plan_extra as $row ) : ?>
												<tr>
													<th><?php echo esc_html( $row['label'] ); ?></th>
													<td><?php echo wp_kses_post( $row['value'] ); ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $has_payment ) : ?>
						<!-- Payment method card (gateway icon + title) -->
						<div class="subscrpt-card">
							<div class="subscrpt-card__head"><?php esc_html_e( 'Payment Method', 'subscription' ); ?></div>
							<div class="subscrpt-card__body">
								<?php if ( $payment_title ) : ?>
									<div class="subscrpt-payment">
										<span class="subscrpt-payment__icon">
											<?php if ( $payment_icon ) : ?>
												<?php echo wp_kses_post( $payment_icon ); ?>
											<?php else : ?>
												<span class="subscrpt-payment__icon-ph dashicons dashicons-bank"></span>
											<?php endif; ?>
										</span>
										<span class="subscrpt-payment__meta">
											<span class="subscrpt-payment__title"><?php echo esc_html( $payment_title ); ?></span>
											<?php if ( $payment_plugin ) : ?>
												<span class="subscrpt-payment__plugin"><?php echo esc_html( $payment_plugin ); ?></span>
											<?php endif; ?>
										</span>
									</div>
								<?php else : ?>
									<p class="subscrpt-muted"><?php esc_html_e( 'No payment method.', 'subscription' ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Detail sections -->
			<?php foreach ( $main_sections as $section ) : ?>
				<div class="subscrpt-card">
					<div class="subscrpt-card__head"><?php echo esc_html( $section['title'] ); ?></div>
					<div class="subscrpt-card__body">
						<table class="subscrpt-kv">
							<tbody>
								<?php foreach ( $section['rows'] as $row ) : ?>
									<tr>
										<th><?php echo esc_html( $row['label'] ); ?></th>
										<td><?php echo wp_kses_post( $row['value'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endforeach; ?>

			<?php if ( empty( $summary_tiles ) && empty( $main_sections ) && empty( $side_sections ) && ! $has_payment && ! $plan_product && ! $plan_qty && empty( $plan_extra ) && ! $is_grace_period ) : ?>
				<div class="subscrpt-card">
					<div class="subscrpt-card__head"><?php esc_html_e( 'Subscription Details', 'subscription' ); ?></div>
					<div class="subscrpt-card__body">
						<p class="subscrpt-muted"><?php esc_html_e( 'No subscription details available.', 'subscription' ); ?></p>
					</div>
				</div>
			<?php endif; ?>

			<!-- Related orders card -->
			<div class="subscrpt-card" data-subscrpt-paginate data-per-page="10" data-date-col="3">
				<div class="subscrpt-card__head subscrpt-card__head--toolbar">
					<span class="subscrpt-card__title"><?php esc_html_e( 'Related Orders', 'subscription' ); ?></span>
					<?php if ( ! empty( $related_rows ) ) : ?>
						<?php $subscrpt_render_table_tools(); ?>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $related_rows ) ) : ?>
					<table class="wpsubs-table subscrpt-table--compact">
						<thead>
							<tr>
								<th><?php esc_html_e( '#', 'subscription' ); ?></th>
								<th><?php esc_html_e( 'Order', 'subscription' ); ?></th>
								<th><?php esc_html_e( 'Type', 'subscription' ); ?></th>
								<th><?php esc_html_e( 'Date', 'subscription' ); ?></th>
								<th><?php esc_html_e( 'Status', 'subscription' ); ?></th>
								<th><?php esc_html_e( 'Payment', 'subscription' ); ?></th>
								<th><?php esc_html_e( 'Total', 'subscription' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							// Map WC order statuses onto the shared subscription badge
							// modifiers so they get the same bg/border/color treatment.
							$order_badge_map = array(
								'completed'  => 'active',         // green
								'pending'    => 'pending',        // blue
								'processing' => 'pending-cancel', // yellow
								'on-hold'    => 'pending-cancel', // yellow
								'failed'     => 'expired',        // red
								'refunded'   => 'expired',        // red
								'trash'      => 'trash',          // red
								'cancelled'  => 'cancelled',      // gray
							);
							foreach ( $related_rows as $related ) :
								$related_order  = $related['order'];
								$related_status = $related_order->get_status();
								$badge_class    = $order_badge_map[ $related_status ] ?? 'draft';
								?>
								<tr>
									<td><span class="wpsubs-cell-title"><?php echo esc_html( number_format_i18n( $related['seq'] ) ); ?></span></td>
									<td>
										<?php
										// translators: %s: WooCommerce order number.
										$order_id_label = sprintf( __( 'Order ID: #%s', 'subscription' ), $related_order->get_order_number() );
										?>
										<a href="<?php echo esc_url( $related_order->get_edit_order_url() ); ?>" target="_blank" class="wpsubs-cell-title" title="<?php echo esc_attr( $order_id_label ); ?>">#<?php echo esc_html( $related_order->get_order_number() ); ?></a>
									</td>
									<td><?php echo esc_html( $related['label'] ); ?></td>
									<td><?php echo esc_html( $related_order->get_date_created()->date( get_option( 'date_format' ) . ' - ' . get_option( 'time_format' ) ) ); ?></td>
									<td><span class="wpsubs-badge wpsubs-badge--<?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( wc_get_order_status_name( $related_status ) ); ?></span></td>
									<td><?php echo $related_order->get_payment_method_title() ? esc_html( $related_order->get_payment_method_title() ) : '&mdash;'; ?></td>
									<td><?php echo wp_kses_post( $related_order->get_formatted_order_total() ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php
					// Initial pager; WPSubsPager hydrates and re-derives totals on init.
					$related_total = count( $related_rows );
					wpsubs_render_pager(
						array(
							'current'     => 1,
							'total'       => max( 1, (int) ceil( $related_total / 10 ) ),
							'info'        => true,
							'per_page'    => 10,
							'item_count'  => $related_total,
							'link_mode'   => 'cb',
							'class'       => 'subscrpt-pager',
							// translators: %1$s: first item number, %2$s: last item number, %3$s: total items
							'info_format' => __( 'Showing %1$s–%2$s of %3$s related orders', 'subscription' ),
							'context'     => 'subscription-details-related-orders',
						)
					);
					?>
				<?php else : ?>
					<div class="subscrpt-card__body"><p class="subscrpt-muted"><?php esc_html_e( 'No related orders found.', 'subscription' ); ?></p></div>
				<?php endif; ?>
			</div>

			<!-- Activities card -->
			<?php $subscrpt_pro_on = function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated(); ?>
			<div class="subscrpt-card" data-subscrpt-paginate data-per-page="10" data-date-col="2">
				<div class="subscrpt-card__head subscrpt-card__head--toolbar">
					<span class="subscrpt-card__title"><?php esc_html_e( 'Subscription Activities', 'subscription' ); ?></span>
					<?php if ( $subscrpt_pro_on ) : ?>
						<?php $subscrpt_render_table_tools(); ?>
					<?php endif; ?>
				</div>
				<?php if ( $subscrpt_pro_on ) : ?>
					<div class="subscrpt-activities">
						<?php
						/**
						 * Fires inside the subscription details activities card.
						 *
						 * The Pro plugin renders the activity table here.
						 *
						 * @param int $subscription_id Subscription post ID.
						 */
						do_action( 'subscrpt_order_activities', $subscription_id );
						?>
					</div>
					<?php
					// Activities pager. Total pages are unknown at render time (Pro
					// injects the rows); WPSubsPager recomputes on init via render().
					wpsubs_render_pager(
						array(
							'current'     => 1,
							'total'       => 1,
							'info'        => true,
							'per_page'    => 10,
							'item_count'  => 0,
							'link_mode'   => 'cb',
							'class'       => 'subscrpt-pager',
							// translators: %1$s: first item number, %2$s: last item number, %3$s: total items
							'info_format' => __( 'Showing %1$s–%2$s of %3$s activities', 'subscription' ),
							'context'     => 'subscription-details-activities',
						)
					);
					?>
				<?php else : ?>
					<div class="subscrpt-card__body">
						<div class="subscrpt-upgrade-banner">
							<div>
								<strong><?php esc_html_e( 'Upgrade to WPSubscription Pro', 'subscription' ); ?></strong>
								<p><?php esc_html_e( 'Track subscription activity history, automation, and more.', 'subscription' ); ?></p>
							</div>
							<a href="https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro" target="_blank" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" rel="noreferrer noopener">
								<?php esc_html_e( 'Upgrade to Pro', 'subscription' ); ?>
							</a>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<?php
			/**
			 * Fires at the bottom of the details main (left) column, after the activities card.
			 *
			 * Echo a `.subscrpt-card` to add a full-width section here.
			 *
			 * @param array $subscrpt_details_ctx Subscription details context.
			 */
			do_action( 'subscrpt_details_main_bottom', $subscrpt_details_ctx );
			?>

		</div><!-- /.subscrpt-detail-main -->

		<!-- Sidebar column -->
		<div class="subscrpt-detail-side">

			<?php
			/**
			 * Fires at the top of the details sidebar (right) column, before the action card.
			 *
			 * Echo a `.subscrpt-card` to add a section here.
			 *
			 * @param array $subscrpt_details_ctx Subscription details context.
			 */
			do_action( 'subscrpt_details_side_top', $subscrpt_details_ctx );
			?>

			<!-- Status action card -->
			<div class="subscrpt-card subscrpt-card--overflow">
				<div class="subscrpt-card__head"><?php esc_html_e( 'Subscription Action', 'subscription' ); ?></div>
				<div class="subscrpt-card__body">
					<?php if ( ! empty( $actions ) ) : ?>
						<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="subscrpt-action-form">
							<?php wp_nonce_field( 'subscrpt_order_action_nonce', 'subscrpt_order_action_nonce_field' ); ?>
							<?php
							$action_options = array();
							foreach ( $actions as $action_slug ) {
								if ( isset( $actions_data[ $action_slug ] ) ) {
									$action_options[] = array(
										'value' => $actions_data[ $action_slug ]['value'],
										'label' => $actions_data[ $action_slug ]['label'],
									);
								}
							}
							wpsubs_render_adv_select(
								array(
									'name'        => 'subscrpt_order_action',
									'placeholder' => __( 'Choose Action', 'subscription' ),
									'value'       => '',
									'options'     => $action_options,
									'class'       => 'subscrpt-action-select',
								)
							);
							?>
							<button type="submit" class="wpsubs-btn wpsubs-btn--primary" style="width:100%;justify-content:center;margin-top:10px;">
								<?php esc_html_e( 'Process', 'subscription' ); ?>
							</button>
						</form>
					<?php else : ?>
						<p class="subscrpt-muted"><?php esc_html_e( 'No actions available for this status.', 'subscription' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Customer card -->
			<div class="subscrpt-card">
				<div class="subscrpt-card__head"><?php esc_html_e( 'Customer Details', 'subscription' ); ?></div>
				<div class="subscrpt-card__body">
					<?php if ( $order ) : ?>
						<div class="subscrpt-customer-name">
							<?php if ( $customer_url ) : ?>
								<a href="<?php echo esc_url( $customer_url ); ?>" target="_blank"><?php echo esc_html( $customer_name ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $customer_name ); ?>
							<?php endif; ?>
						</div>
						<?php if ( $customer_email ) : ?>
							<div class="subscrpt-customer-line"><a href="mailto:<?php echo esc_attr( $customer_email ); ?>"><?php echo esc_html( $customer_email ); ?></a></div>
						<?php endif; ?>
						<?php if ( $customer_phone ) : ?>
							<div class="subscrpt-customer-line"><a href="tel:<?php echo esc_attr( $customer_phone ); ?>"><?php echo esc_html( $customer_phone ); ?></a></div>
						<?php endif; ?>

					<?php else : ?>
						<p class="subscrpt-muted"><?php esc_html_e( 'Order not found.', 'subscription' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Secondary info cards (payment method, addresses) -->
			<?php foreach ( $side_sections as $section ) : ?>
				<div class="subscrpt-card">
					<div class="subscrpt-card__head"><?php echo esc_html( $section['title'] ); ?></div>
					<div class="subscrpt-card__body">
						<table class="subscrpt-kv">
							<tbody>
								<?php foreach ( $section['rows'] as $row ) : ?>
									<tr>
										<th><?php echo esc_html( $row['label'] ); ?></th>
										<td><?php echo wp_kses_post( $row['value'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endforeach; ?>

			<?php
			/**
			 * Fires at the bottom of the details sidebar (right) column, after the secondary info cards.
			 *
			 * Echo a `.subscrpt-card` to add a section here.
			 *
			 * @param array $subscrpt_details_ctx Subscription details context.
			 */
			do_action( 'subscrpt_details_side_bottom', $subscrpt_details_ctx );
			?>

		</div><!-- /.subscrpt-detail-side -->

	</div><!-- /.subscrpt-detail-grid -->

</div>

<style>
/* Page sits on WP admin gray; the cards are the white surfaces (matches list/health pages). */
.wp-subscription-admin-content.list-page.subscrpt-subs-details {
	background: transparent;
	padding: 0;
	border-radius: 0;
	margin-top: 24px;
	margin-bottom: 32px;
}
.subscrpt-subs-details .subscrpt-detail-head { margin-bottom: 20px; }
.subscrpt-detail-head__title {
	font-size: 1.375rem;
	font-weight: 700;
	color: var(--wpsubs-text);
	line-height: 1.2;
	margin: 0 0 8px;
	padding: 0;
}
.subscrpt-detail-head__desc {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 8px;
	font-size: 13px;
	color: var(--wpsubs-text-muted);
	margin: 0 0 12px;
	line-height: 1.5;
}
.subscrpt-detail-head__sep { color: var(--wpsubs-text-subtle); }
.subscrpt-detail-head__divider { border-top: 1px dashed #d0d3d7; }

/* Summary metric strip */
.subscrpt-summary {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 20px;
}
.subscrpt-summary__tile {
	background: var(--wpsubs-surface);
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	padding: 16px 18px;
	display: flex;
	flex-direction: column;
	gap: 6px;
}
.subscrpt-summary__label {
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	color: var(--wpsubs-text-muted);
}
.subscrpt-summary__value {
	font-size: 18px;
	font-weight: 700;
	color: #111827;
	line-height: 1.3;
}
.subscrpt-summary__value .subscrpt-summary__unit {
	font-size: 13px;
	font-weight: 500;
	color: var(--wpsubs-text-muted);
}
.subscrpt-summary__value .subscrpt-summary__was {
	font-size: 14px;
	font-weight: 500;
	color: var(--wpsubs-text-muted);
	margin-right: 4px;
}

.subscrpt-detail-grid {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 340px;
	gap: 20px;
	align-items: start;
}
@media (max-width: 960px) {
	.subscrpt-detail-grid { grid-template-columns: 1fr; }
}
.subscrpt-detail-main, .subscrpt-detail-side {
	display: flex;
	flex-direction: column;
	gap: 20px;
	min-width: 0;
}

.subscrpt-card {
	background: var(--wpsubs-surface);
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	box-shadow: none;
	overflow: hidden;
}
.subscrpt-card__head {
	padding: 14px 18px;
	font-size: 14px;
	font-weight: 600;
	color: #111827;
	border-bottom: 1px solid var(--wpsubs-border);
	background: var(--wpsubs-surface);
}
.subscrpt-card__body { padding: 16px 18px; }
/* Cards holding an adv-select dropdown must not clip the popover. Without
	overflow:hidden the head no longer inherits the card's rounded corners, so
	round the head (and body) explicitly. */
.subscrpt-card--overflow { overflow: visible; }
.subscrpt-card--overflow .subscrpt-card__head {
	border-top-left-radius: var(--wpsubs-radius);
	border-top-right-radius: var(--wpsubs-radius);
}
.subscrpt-card--overflow .subscrpt-card__body:last-child {
	border-bottom-left-radius: var(--wpsubs-radius);
	border-bottom-right-radius: var(--wpsubs-radius);
}

/* Card head with right-aligned filter tools. */
.subscrpt-card__head--toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
	font-weight: 400;
}
.subscrpt-card__title { font-size: 14px; font-weight: 600; color: #111827; }
/* Filter dropdown items must not inherit the card-head bold weight. */
.subscrpt-card__tools .wpsubs-adv-select__label,
.subscrpt-card__tools .wpsubs-adv-select__item { font-weight: 400; }
.subscrpt-card__tools {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}
.subscrpt-card__tools .wpsubs-adv-select__trigger {
	height: 32px;
	padding-top: 0;
	padding-bottom: 0;
}

/* Pager (uses .wpsubs-pagination layout; styles in admin-components.css). */
.subscrpt-pager { margin: 0; }
.subscrpt-empty-row td { color: var(--wpsubs-text-muted); font-size: 13px; }

/* Tables render flush to the card edges (the table thead is the column strip). */
.subscrpt-card .wpsubs-table { border: 0; }
.subscrpt-card .wpsubs-table tbody tr:last-child td { border-bottom: 0; }
/* Match the page's 13px body text (default wpsubs-table cells run larger). */
.subscrpt-card .wpsubs-table th,
.subscrpt-card .wpsubs-table td,
.subscrpt-activities .wpsubs-table th,
.subscrpt-activities .wpsubs-table td { font-size: 13px; }
/* Tighter rows for the data tables. */
.subscrpt-table--compact th,
.subscrpt-table--compact td,
.subscrpt-activities .wpsubs-table th,
.subscrpt-activities .wpsubs-table td { padding-top: 12px; padding-bottom: 12px; }
.subscrpt-table--compact .wpsubs-badge { font-size: 11px; }
.subscrpt-activities .widefat,
.subscrpt-activities .wpsubs-table { border: 0; margin: 0; }
.subscrpt-card__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 14px;
}

.subscrpt-kv { width: 100%; border-collapse: collapse; }
.subscrpt-kv th, .subscrpt-kv td {
	text-align: left;
	padding: 9px 0;
	font-size: 13px;
	vertical-align: top;
	border-bottom: 1px solid var(--wpsubs-border);
}
.subscrpt-kv tr:last-child th, .subscrpt-kv tr:last-child td { border-bottom: 0; }
.subscrpt-kv th {
	width: 40%;
	font-weight: 500;
	color: var(--wpsubs-text-muted);
	padding-right: 12px;
}
.subscrpt-kv td { color: var(--wpsubs-text); }
.subscrpt-kv a { color: var(--wpsubs-brand); text-decoration: none; }
.subscrpt-kv a:hover { text-decoration: underline; }

/* Plan + Payment Method row: Plan wider (2-col content), Payment narrower. */
.subscrpt-plan-pay {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 20px;
	align-items: stretch;
}
.subscrpt-plan-pay > .subscrpt-card { margin: 0; }
/* Plan spans 2 of the 3 columns so its right edge aligns with the summary's
	2nd/3rd column boundary; the gateway card fills the remaining column. */
.subscrpt-plan-pay > .subscrpt-card--plan { grid-column: span 2; }
@media (max-width: 782px) {
	.subscrpt-plan-pay { grid-template-columns: 1fr; }
}

/* Plan card: product image (1:1) + name/qty stacked. */
.subscrpt-plan-row {
	display: grid;
	grid-template-columns: 64px 1fr;
	gap: 16px;
	align-items: center;
}
.subscrpt-plan-thumb {
	width: 64px;
	height: 64px;
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius-sm);
	overflow: hidden;
	background: var(--wpsubs-surface-alt, #f6f7f7);
	display: flex;
	align-items: center;
	justify-content: center;
}
.subscrpt-plan-thumb img,
.subscrpt-plan-thumb .subscrpt-plan-img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
}
.subscrpt-plan-thumb__ph {
	font-size: 28px;
	width: 28px;
	height: 28px;
	color: var(--wpsubs-text-subtle, #a7aaad);
}
.subscrpt-plan-meta { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.subscrpt-plan-name { font-size: 14px; font-weight: 600; color: var(--wpsubs-text); line-height: 1.3; }
.subscrpt-plan-name a { color: var(--wpsubs-text); text-decoration: none; }
.subscrpt-plan-name a:hover { color: var(--wpsubs-brand); }
.subscrpt-plan-qty { font-size: 13px; color: var(--wpsubs-text-muted); }
.subscrpt-plan-term { font-size: 13px; color: var(--wpsubs-text-muted); }
.subscrpt-plan-col { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.subscrpt-plan-label {
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: var(--wpsubs-text-muted);
}
.subscrpt-plan-value { font-size: 13px; color: var(--wpsubs-text); }
.subscrpt-plan-value a { color: var(--wpsubs-brand); text-decoration: none; }
.subscrpt-plan-value a:hover { text-decoration: underline; }
.subscrpt-plan-extra { margin-top: 14px; }

/* Payment method: gateway icon + (gateway title / source plugin). Mirrors the
	plan-row layout so the two cards line up. */
.subscrpt-payment { display: grid; grid-template-columns: 40px 1fr; gap: 12px; align-items: center; }
.subscrpt-payment__icon {
	width: 40px;
	height: 40px;
	display: flex;
	align-items: center;
	justify-content: center;
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius-sm);
	background: var(--wpsubs-surface-alt, #f6f7f7);
	overflow: hidden;
}
.subscrpt-payment__icon img { width: 100%; height: 100%; object-fit: cover; display: block; }
.subscrpt-payment__icon-ph { font-size: 22px; width: 22px; height: 22px; color: var(--wpsubs-text-muted); }
.subscrpt-payment__meta { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.subscrpt-payment__title { font-size: 14px; font-weight: 600; color: var(--wpsubs-text); line-height: 1.3; }
.subscrpt-payment__plugin { font-size: 13px; color: var(--wpsubs-text-muted); }

/* Plan + Payment cards stretch to equal height; bodies center their content. */
.subscrpt-plan-pay > .subscrpt-card { display: flex; flex-direction: column; }
.subscrpt-plan-pay > .subscrpt-card .subscrpt-card__body { flex: 1; display: flex; flex-direction: column; justify-content: center; }

.subscrpt-customer-name { font-size: 14px; font-weight: 600; color: var(--wpsubs-text); }
.subscrpt-customer-name a { color: var(--wpsubs-text); text-decoration: none; }
.subscrpt-customer-name a:hover { color: var(--wpsubs-brand); }
.subscrpt-customer-line { font-size: 13px; margin-top: 3px; }
.subscrpt-customer-line a { color: var(--wpsubs-brand); text-decoration: none; }
.subscrpt-customer-line a:hover { text-decoration: underline; }

.subscrpt-subs-details .subscrpt-muted { color: var(--wpsubs-text-muted); font-size: 13px; margin: 0; }
.subscrpt-action-form .wpsubs-adv-select { width: 100%; }
.subscrpt-action-form .wpsubs-adv-select__trigger { width: 100%; }

.subscrpt-upgrade-banner {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
	padding: 16px;
	border: 1px solid var(--wpsubs-brand);
	border-radius: var(--wpsubs-radius-sm);
	background: var(--wpsubs-brand-light);
}
.subscrpt-upgrade-banner strong { color: var(--wpsubs-text); font-size: 14px; }
.subscrpt-upgrade-banner p { margin: 4px 0 0; font-size: 13px; color: var(--wpsubs-text-muted); }
</style>
