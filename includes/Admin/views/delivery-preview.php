<?php
/**
 * Delivery Schedules page — interactive preview with sample data (shown when Pro is not active).
 *
 * @package SpringDevs\Subscription\Admin
 */

defined( 'ABSPATH' ) || exit;

$upgrade_url = 'https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro';
$currency    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

$dummy_deliveries = array(
	array(
		'id'              => 301,
		'subscription_id' => 1042,
		'product'         => 'Monthly Wellness Box',
		'customer'        => 'Sarah Johnson',
		'email'           => 'sarah.j@example.com',
		'status'          => 'waiting',
		'ship_on'         => '2026-06-10',
		'shipped_on'      => '',
		'address'         => '42 Maple Street, Boston, MA 02101',
	),
	array(
		'id'              => 302,
		'subscription_id' => 1087,
		'product'         => 'Annual Premium Plan',
		'customer'        => 'Marcus Williams',
		'email'           => 'marcus.w@example.com',
		'status'          => 'shipped',
		'ship_on'         => '2026-05-15',
		'shipped_on'      => '2026-05-16',
		'address'         => '18 Oak Avenue, Chicago, IL 60601',
	),
	array(
		'id'              => 303,
		'subscription_id' => 1103,
		'product'         => 'Basic Weekly Kit',
		'customer'        => 'Emma Clarke',
		'email'           => 'emma.c@example.com',
		'status'          => 'in_process',
		'ship_on'         => '2026-06-07',
		'shipped_on'      => '',
		'address'         => '7 Pine Road, Austin, TX 78701',
	),
	array(
		'id'              => 304,
		'subscription_id' => 1155,
		'product'         => 'Monthly Wellness Box',
		'customer'        => 'Linda Zhao',
		'email'           => 'linda.z@example.com',
		'status'          => 'waiting',
		'ship_on'         => '2026-06-12',
		'shipped_on'      => '',
		'address'         => '99 Birch Lane, Seattle, WA 98101',
	),
	array(
		'id'              => 305,
		'subscription_id' => 1189,
		'product'         => 'Starter Bundle',
		'customer'        => 'James O\'Brien',
		'email'           => 'j.obrien@example.com',
		'status'          => 'cancelled',
		'ship_on'         => '2026-05-28',
		'shipped_on'      => '',
		'address'         => '15 Cedar Blvd, Miami, FL 33101',
	),
);

$badge_map = array(
	'waiting'    => array(
		'mod'   => 'pending',
		'label' => __( 'Waiting', 'subscription' ),
	),
	'in_process' => array(
		'mod'   => 'active',
		'label' => __( 'In Process', 'subscription' ),
	),
	'shipped'    => array(
		'mod'   => 'active',
		'label' => __( 'Shipped', 'subscription' ),
	),
	'cancelled'  => array(
		'mod'   => 'cancelled',
		'label' => __( 'Cancelled', 'subscription' ),
	),
);

$month_options = array(
	array(
		'value' => '',
		'label' => __( 'All Dates', 'subscription' ),
	),
	array(
		'value' => '2026-06',
		'label' => 'June 2026',
	),
	array(
		'value' => '2026-05',
		'label' => 'May 2026',
	),
	array(
		'value' => '2026-04',
		'label' => 'April 2026',
	),
);
?>

<div class="wp-subscription-admin-content list-page">

	<!-- Disclaimer banner -->
	<div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;">
		<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#d97706" style="flex-shrink:0;margin-top:1px;" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
		<p style="margin:0;font-size:13px;color:#92400e;line-height:1.5;">
			<strong><?php esc_html_e( 'Preview with sample data.', 'subscription' ); ?></strong>
			<?php esc_html_e( 'This page shows example data to illustrate the feature.', 'subscription' ); ?>
			<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noreferrer noopener" style="color:#b45309;font-weight:600;text-decoration:underline;"><?php esc_html_e( 'Upgrade to Pro', 'subscription' ); ?></a>
			<?php esc_html_e( 'to manage real delivery schedules.', 'subscription' ); ?>
		</p>
	</div>

	<!-- Page header -->
	<div style="margin-bottom:20px;">
		<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0 0 6px;line-height:1.2;"><?php esc_html_e( 'Delivery Schedules', 'subscription' ); ?></h1>
		<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0 0 12px;line-height:1.5;"><?php esc_html_e( 'Track and manage subscription delivery schedules.', 'subscription' ); ?></p>
		<div style="border-top:1px dashed #d0d3d7;"></div>
	</div>

	<!-- Toolbar -->
	<div class="wpsubs-toolbar" style="margin-top:0;">
		<?php
		wpsubs_render_adv_select(
			array(
				'name'        => 'subscrpt_status',
				'value'       => '',
				'placeholder' => __( 'All Statuses', 'subscription' ),
				'align'       => 'left',
				'options'     => array(
					array(
						'value' => '',
						'label' => __( 'All Statuses', 'subscription' ),
					),
					array(
						'value' => 'waiting',
						'label' => __( 'Waiting', 'subscription' ),
					),
					array(
						'value' => 'in_process',
						'label' => __( 'In Process', 'subscription' ),
					),
					array(
						'value' => 'shipped',
						'label' => __( 'Shipped', 'subscription' ),
					),
					array(
						'value' => 'cancelled',
						'label' => __( 'Cancelled', 'subscription' ),
					),
				),
			)
		);
		wpsubs_render_adv_select(
			array(
				'name'        => 'date_filter',
				'value'       => '',
				'placeholder' => __( 'All Dates', 'subscription' ),
				'align'       => 'left',
				'options'     => $month_options,
			)
		);
		?>
		<div class="wpsubs-search">
			<div class="wpsubs-input-wrap wpsubs-input-wrap--icon-l">
				<svg class="wpsubs-input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/></svg>
				<input type="search" class="wpsubs-input" placeholder="<?php esc_attr_e( 'Search by subscription ID...', 'subscription' ); ?>" disabled style="cursor:not-allowed;" />
			</div>
		</div>
		<button type="button" class="wpsubs-btn wpsubs-btn--outline" disabled style="cursor:not-allowed;opacity:0.6;"><?php esc_html_e( 'Filter', 'subscription' ); ?></button>
		<div class="wpsubs-toolbar__spacer"></div>
		<?php
		wpsubs_render_adv_select(
			array(
				'name'        => 'per_page',
				'value'       => '20',
				'placeholder' => '20 per page',
				'align'       => 'right',
				'options'     => array(
					array(
						'value' => '10',
						'label' => __( '10 per page', 'subscription' ),
					),
					array(
						'value' => '20',
						'label' => __( '20 per page', 'subscription' ),
					),
					array(
						'value' => '50',
						'label' => __( '50 per page', 'subscription' ),
					),
					array(
						'value' => '100',
						'label' => __( '100 per page', 'subscription' ),
					),
				),
			)
		);
		?>
		<button type="button" class="wpsubs-btn wpsubs-btn--outline" disabled style="cursor:not-allowed;opacity:0.6;">
			<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
			<?php esc_html_e( 'Print Addresses', 'subscription' ); ?>
		</button>
	</div>

	<div class="wpsubs-table-card">
		<table class="wpsubs-table">
			<thead>
				<tr>
					<th class="wpsubs-col--check">
						<input type="checkbox" class="wpsubs-checkbox" disabled />
					</th>
					<th><?php esc_html_e( 'Subscription', 'subscription' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'subscription' ); ?></th>
					<th><?php esc_html_e( 'Status', 'subscription' ); ?></th>
					<th class="wpsubs-col--nowrap"><?php esc_html_e( 'Shipping On', 'subscription' ); ?></th>
					<th class="wpsubs-col--nowrap"><?php esc_html_e( 'Shipped On', 'subscription' ); ?></th>
					<th><?php esc_html_e( 'Delivery Info', 'subscription' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $dummy_deliveries as $delivery ) : ?>
					<?php
					$name_parts = array_values( array_filter( explode( ' ', $delivery['customer'] ) ) );
					$initials   = '';
					foreach ( array_slice( $name_parts, 0, 2 ) as $part ) {
						$initials .= strtoupper( $part[0] );
					}
					$color_slot = ord( strtolower( $initials[0] ?? 'a' ) ) % 8;
					$badge      = $badge_map[ $delivery['status'] ] ?? array(
						'mod'   => 'pending',
						'label' => $delivery['status'],
					);
					?>
					<tr>
						<td class="wpsubs-col--check">
							<input type="checkbox" class="wpsubs-checkbox" disabled />
						</td>
						<td>
							<span class="wpsubs-cell-title" style="font-weight:600;"><?php echo esc_html( $delivery['product'] ); ?></span>
							<span class="wpsubs-cell-id">#<?php echo esc_html( $delivery['subscription_id'] ); ?></span>
						</td>
						<td>
							<div class="wpsubs-customer">
								<div class="wpsubs-avatar" data-color="<?php echo (int) $color_slot; ?>"><?php echo esc_html( $initials ); ?></div>
								<div class="wpsubs-customer__info">
									<span class="wpsubs-customer__name"><?php echo esc_html( $delivery['customer'] ); ?></span>
									<span class="wpsubs-customer__sub"><?php echo esc_html( $delivery['email'] ); ?></span>
								</div>
							</div>
						</td>
						<td>
							<span class="wpsubs-badge wpsubs-badge--<?php echo esc_attr( $badge['mod'] ); ?>"><?php echo esc_html( $badge['label'] ); ?></span>
						</td>
						<td class="wpsubs-col--nowrap">
							<span class="wpsubs-cell-title" style="font-weight:400;"><?php echo esc_html( $delivery['ship_on'] ); ?></span>
						</td>
						<td class="wpsubs-col--nowrap">
							<?php if ( $delivery['shipped_on'] ) : ?>
								<span class="wpsubs-cell-title" style="font-weight:400;"><?php echo esc_html( $delivery['shipped_on'] ); ?></span>
							<?php else : ?>
								<span style="color:var(--wpsubs-text-subtle);">&#8212;</span>
							<?php endif; ?>
						</td>
						<td>
							<span class="wpsubs-cell-id"><?php echo esc_html( $delivery['address'] ); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

</div>

<?php
$modal_title = __( 'Unlock Delivery Schedules', 'subscription' );
$modal_desc  = __( 'Delivery Schedules requires WPSubscription Pro. Unlock advanced features, priority support, and more with WPSubscription Pro.', 'subscription' );
require __DIR__ . '/pro-upgrade-modal.php';
