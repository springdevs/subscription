<?php
/**
 * Subscription Health page — interactive preview with sample data (shown when Pro is not active).
 *
 * @package SpringDevs\Subscription\Admin
 */

defined( 'ABSPATH' ) || exit;

$upgrade_url = 'https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro';
$currency    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

$dummy_subs = array(
	array(
		'id'         => 1042,
		'product'    => 'Monthly Wellness Box',
		'customer'   => 'Sarah Johnson',
		'email'      => 'sarah.j@example.com',
		'amount'     => 29.99,
		'period'     => 'month',
		'renewal'    => '2026-05-20',
		'days_since' => 18,
		'issue'      => 'failed_payment',
	),
	array(
		'id'         => 1087,
		'product'    => 'Annual Premium Plan',
		'customer'   => 'Marcus Williams',
		'email'      => 'marcus.w@example.com',
		'amount'     => 199.00,
		'period'     => 'year',
		'renewal'    => '2026-05-28',
		'days_since' => 10,
		'issue'      => 'overdue_renewal',
	),
	array(
		'id'         => 1103,
		'product'    => 'Basic Weekly Kit',
		'customer'   => 'Emma Clarke',
		'email'      => 'emma.c@example.com',
		'amount'     => 9.99,
		'period'     => 'week',
		'renewal'    => '2026-06-01',
		'days_since' => 6,
		'issue'      => 'retry_needed',
	),
	array(
		'id'         => 1124,
		'product'    => 'Pro Content Access',
		'customer'   => 'David Park',
		'email'      => 'd.park@example.com',
		'amount'     => 59.99,
		'period'     => 'month',
		'renewal'    => '2026-05-15',
		'days_since' => 23,
		'issue'      => 'stalled_renewal',
	),
	array(
		'id'         => 1155,
		'product'    => 'Monthly Wellness Box',
		'customer'   => 'Linda Zhao',
		'email'      => 'linda.z@example.com',
		'amount'     => 29.99,
		'period'     => 'month',
		'renewal'    => '2026-06-03',
		'days_since' => 4,
		'issue'      => 'failed_payment',
	),
	array(
		'id'         => 1189,
		'product'    => 'Starter Bundle',
		'customer'   => 'James O\'Brien',
		'email'      => 'j.obrien@example.com',
		'amount'     => 14.99,
		'period'     => 'month',
		'renewal'    => '2026-05-30',
		'days_since' => 8,
		'issue'      => 'overdue_renewal',
	),
);

$revenue_at_risk = 0.0;
foreach ( $dummy_subs as $s ) {
	$revenue_at_risk += $s['amount'];
}

$issue_badge_map = array(
	'failed_payment'  => array(
		'mod'     => 'expired',
		'label'   => __( 'Payment failed', 'subscription' ),
		'tooltip' => __( 'The last payment attempt was declined.', 'subscription' ),
	),
	'overdue_renewal' => array(
		'mod'     => 'pending-cancel',
		'label'   => __( 'Overdue renewal', 'subscription' ),
		'tooltip' => __( 'The renewal date passed with no order created.', 'subscription' ),
	),
	'retry_needed'    => array(
		'mod'     => 'pending',
		'label'   => __( 'Retry needed', 'subscription' ),
		'tooltip' => __( 'A payment failed and still needs a retry.', 'subscription' ),
	),
	'stalled_renewal' => array(
		'mod'     => 'cancelled',
		'label'   => __( 'Stalled renewal', 'subscription' ),
		'tooltip' => __( 'A renewal order has stayed pending too long.', 'subscription' ),
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
			<?php esc_html_e( 'to monitor and recover real subscriptions.', 'subscription' ); ?>
		</p>
	</div>

	<!-- Page header -->
	<div style="display:flex;align-items:flex-start;margin-bottom:20px;gap:24px;flex-wrap:wrap;">

		<div style="flex:1;min-width:200px;">
			<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0 0 6px;line-height:1.2;"><?php esc_html_e( 'Subscription Health', 'subscription' ); ?></h1>
			<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0 0 12px;line-height:1.5;">
				<?php esc_html_e( 'Monitor and recover subscriptions that need attention.', 'subscription' ); ?>
			</p>
			<div style="border-top:1px dashed #d0d3d7;padding-top:10px;display:flex;align-items:center;gap:8px;">
				<button type="button" class="wpsubs-btn wpsubs-btn--outline" style="height:28px;padding:0 10px;font-size:12px;cursor:not-allowed;opacity:0.6;" disabled>
					<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
					<?php esc_html_e( 'Refresh', 'subscription' ); ?>
				</button>
			</div>
		</div>

		<div style="display:flex;gap:12px;flex-shrink:0;">
			<div style="width:140px;border:1px solid var(--wpsubs-border);border-radius:12px;padding:20px;background:var(--wpsubs-surface);">
				<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:8px;">
					<div style="font-size:1.875rem;font-weight:700;color:#111827;line-height:1;"><?php echo esc_html( count( $dummy_subs ) ); ?></div>
					<span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#eef2ff;flex-shrink:0;color:#6366f1;">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
					</span>
				</div>
				<div style="font-size:14px;font-weight:500;color:var(--wpsubs-text);"><?php esc_html_e( 'Subscriptions', 'subscription' ); ?></div>
				<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'are at risk zone', 'subscription' ); ?></div>
			</div>
			<div style="width:180px;border:1px solid var(--wpsubs-border);border-radius:12px;padding:20px;background:var(--wpsubs-surface);">
				<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:8px;">
					<div style="font-size:1.875rem;font-weight:700;color:#ef4444;line-height:1;"><?php echo esc_html( $currency . number_format_i18n( $revenue_at_risk, 2 ) ); ?></div>
					<span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#fef2f2;flex-shrink:0;color:#ef4444;">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /></svg>
					</span>
				</div>
				<div style="font-size:14px;font-weight:500;color:var(--wpsubs-text);"><?php esc_html_e( 'Revenue at Risk', 'subscription' ); ?></div>
				<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Across active issues', 'subscription' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Toolbar -->
	<div class="wpsubs-toolbar" style="margin-top:16px;">
		<?php
		wpsubs_render_adv_select(
			array(
				'name'        => 'filter',
				'value'       => 'all',
				'placeholder' => __( 'All issues', 'subscription' ),
				'align'       => 'left',
				'options'     => array(
					array(
						'value' => 'all',
						'label' => __( 'All issues', 'subscription' ),
					),
					array(
						'value' => 'overdue_renewal',
						'label' => __( 'Overdue Renewal', 'subscription' ),
					),
					array(
						'value' => 'failed_payment',
						'label' => __( 'Failed Payment', 'subscription' ),
					),
					array(
						'value' => 'retry_needed',
						'label' => __( 'Retry Needed', 'subscription' ),
					),
					array(
						'value' => 'stalled_renewal',
						'label' => __( 'Stalled Renewal', 'subscription' ),
					),
					array(
						'value' => 'skipped',
						'label' => __( 'Skipped', 'subscription' ),
					),
				),
			)
		);
		?>
		<div class="wpsubs-search">
			<div class="wpsubs-input-wrap wpsubs-input-wrap--icon-l">
				<svg class="wpsubs-input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/></svg>
				<input type="search" class="wpsubs-input" placeholder="<?php esc_attr_e( 'Search ID, product, customer...', 'subscription' ); ?>" disabled style="cursor:not-allowed;" />
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
		wpsubs_render_adv_select(
			array(
				'name'        => 'bulk_action',
				'value'       => '',
				'placeholder' => __( 'Bulk actions', 'subscription' ),
				'align'       => 'right',
				'options'     => array(
					array(
						'value' => 'create_renewal',
						'label' => __( 'Create Renewal', 'subscription' ),
					),
					array(
						'value' => 'retry',
						'label' => __( 'Retry Payment', 'subscription' ),
					),
					array(
						'value' => 'send_email',
						'label' => __( 'Send Email', 'subscription' ),
					),
					array( 'divider' => true ),
					array(
						'value'  => 'skip',
						'label'  => __( 'Skip', 'subscription' ),
						'danger' => true,
					),
				),
			)
		);
		?>
		<span class="wpsubs-btn wpsubs-btn--outline" style="cursor:not-allowed;opacity:0.6;white-space:nowrap;">
			<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
			<?php esc_html_e( 'Export CSV', 'subscription' ); ?>
		</span>
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
					<th style="white-space:nowrap;"><?php esc_html_e( 'Amount', 'subscription' ); ?></th>
					<th class="wpsubs-col--nowrap"><?php esc_html_e( 'Renew Date', 'subscription' ); ?></th>
					<th><?php esc_html_e( 'Renewal Order', 'subscription' ); ?></th>
					<th><?php esc_html_e( 'Issue', 'subscription' ); ?></th>
					<th class="wpsubs-col--actions"><?php esc_html_e( 'Actions', 'subscription' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $dummy_subs as $sub ) : ?>
					<?php
					$name_parts = array_values( array_filter( explode( ' ', $sub['customer'] ) ) );
					$initials   = '';
					foreach ( array_slice( $name_parts, 0, 2 ) as $part ) {
						$initials .= strtoupper( $part[0] );
					}
					$color_slot = ord( strtolower( $initials[0] ?? 'a' ) ) % 8;
					$issue_cfg  = $issue_badge_map[ $sub['issue'] ] ?? array(
						'mod'     => 'cancelled',
						'label'   => $sub['issue'],
						'tooltip' => '',
					);
					$days       = (int) $sub['days_since'];
					$date_style = $days > 14 ? 'color:#dc2626;' : ( $days > 7 ? 'color:#f97316;' : '' );
					?>
					<tr>
						<td class="wpsubs-col--check">
							<input type="checkbox" class="wpsubs-checkbox" disabled />
						</td>
						<td>
							<span class="wpsubs-cell-title" style="font-weight:600;"><?php echo esc_html( $sub['product'] ); ?></span>
							<span class="wpsubs-cell-id">#<?php echo esc_html( $sub['id'] ); ?></span>
						</td>
						<td>
							<div class="wpsubs-customer">
								<div class="wpsubs-avatar" data-color="<?php echo (int) $color_slot; ?>"><?php echo esc_html( $initials ); ?></div>
								<div class="wpsubs-customer__info">
									<span class="wpsubs-customer__name"><?php echo esc_html( $sub['customer'] ); ?></span>
									<span class="wpsubs-customer__sub"><?php echo esc_html( $sub['email'] ); ?></span>
								</div>
							</div>
						</td>
						<td style="white-space:nowrap;">
							<span class="wpsubs-cell-title" style="font-variant-numeric:tabular-nums;"><?php echo esc_html( $currency . number_format_i18n( $sub['amount'], 2 ) ); ?></span>
							<span class="wpsubs-cell-id">/ <?php echo esc_html( $sub['period'] ); ?></span>
						</td>
						<td class="wpsubs-col--nowrap">
							<span class="wpsubs-cell-title" style="font-weight:400;<?php echo esc_attr( $date_style ); ?>"><?php echo esc_html( $sub['renewal'] ); ?></span>
							<?php if ( $days > 0 ) : ?>
								<span class="wpsubs-cell-id">
									<?php
									printf(
										/* translators: %d: days overdue */
										esc_html__( '%d days overdue', 'subscription' ),
										(int) $days
									);
									?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<span class="wpsubs-badge wpsubs-badge--cancelled"><?php esc_html_e( 'Not created', 'subscription' ); ?></span>
						</td>
						<td>
							<span class="wpsubs-badge wpsubs-badge--<?php echo esc_attr( $issue_cfg['mod'] ); ?>" title="<?php echo esc_attr( $issue_cfg['tooltip'] ); ?>">
								<?php echo esc_html( $issue_cfg['label'] ); ?>
							</span>
						</td>
						<td class="wpsubs-cell--actions">
							<div class="wpsubs-row-actions">
								<button type="button" class="wpsubs-row-actions__trigger" style="cursor:not-allowed;opacity:0.6;" disabled>···</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

</div>

<?php
$modal_title = __( 'Unlock Subscription Health', 'subscription' );
$modal_desc  = __( 'Subscription Health requires WPSubscription Pro. Unlock advanced features, priority support, and more with WPSubscription Pro.', 'subscription' );
require __DIR__ . '/pro-upgrade-modal.php';
