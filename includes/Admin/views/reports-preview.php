<?php
/**
 * Reports page — interactive preview with sample data (shown when Pro is not active).
 *
 * @package SpringDevs\Subscription\Admin
 */

defined( 'ABSPATH' ) || exit;

// Enqueue Chart.js
\SpringDevs\Subscription\Assets::enqueue_chart_js();

// ---------------------------------------------------------------------------------------------------------- //

$upgrade_url = 'https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro';
$currency    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

// --- Dummy data ---
$dummy_metrics         = array(
	'active_subscriptions'       => 142,
	'cancelled_subscriptions'    => 28,
	'trial_subscriptions'        => 19,
	'total_products'             => 8,
	'churn_rate'                 => 8.4,
	'trial_conversion_rate'      => 64.7,
	'average_subscription_value' => 24.99,
);
$dummy_revenue         = array(
	'total_revenue' => 18420.00,
	'mrr'           => 2847.00,
	'arr'           => 34164.00,
	'net_revenue'   => 17090.00,
);
$dummy_revenue_at_risk = 580.00;
$dummy_trials          = array(
	'total_trials'     => 47,
	'converted_trials' => 31,
	'active_trials'    => 19,
	'trial_revenue'    => 2240.00,
);
$dummy_popular         = array(
	array(
		'product_name'       => 'Monthly Wellness Box',
		'subscription_count' => 56,
		'total_revenue'      => 6720.00,
		'average_revenue'    => 120.00,
	),
	array(
		'product_name'       => 'Annual Premium Plan',
		'subscription_count' => 38,
		'total_revenue'      => 7220.00,
		'average_revenue'    => 190.00,
	),
	array(
		'product_name'       => 'Basic Weekly Kit',
		'subscription_count' => 29,
		'total_revenue'      => 2320.00,
		'average_revenue'    => 80.00,
	),
	array(
		'product_name'       => 'Pro Content Access',
		'subscription_count' => 19,
		'total_revenue'      => 1140.00,
		'average_revenue'    => 60.00,
	),
	array(
		'product_name'       => 'Starter Bundle',
		'subscription_count' => 12,
		'total_revenue'      => 480.00,
		'average_revenue'    => 40.00,
	),
);
$dummy_trends          = array(
	array(
		'label'       => 'Jan',
		'revenue'     => 2200,
		'active'      => 118,
		'total'       => 130,
		'cancelled'   => 5,
		'trials'      => 12,
		'conversions' => 8,
	),
	array(
		'label'       => 'Feb',
		'revenue'     => 2380,
		'active'      => 122,
		'total'       => 135,
		'cancelled'   => 4,
		'trials'      => 14,
		'conversions' => 9,
	),
	array(
		'label'       => 'Mar',
		'revenue'     => 2510,
		'active'      => 126,
		'total'       => 140,
		'cancelled'   => 6,
		'trials'      => 16,
		'conversions' => 11,
	),
	array(
		'label'       => 'Apr',
		'revenue'     => 2650,
		'active'      => 130,
		'total'       => 144,
		'cancelled'   => 3,
		'trials'      => 18,
		'conversions' => 12,
	),
	array(
		'label'       => 'May',
		'revenue'     => 2730,
		'active'      => 134,
		'total'       => 148,
		'cancelled'   => 5,
		'trials'      => 17,
		'conversions' => 14,
	),
	array(
		'label'       => 'Jun',
		'revenue'     => 2847,
		'active'      => 142,
		'total'       => 155,
		'cancelled'   => 4,
		'trials'      => 19,
		'conversions' => 13,
	),
);

if ( ! function_exists( 'subscrpt_preview_format_price' ) ) {
	/**
	 * Format a dummy price for display in the preview.
	 *
	 * @param float $amount Amount to format.
	 * @return string Formatted price HTML.
	 */
	function subscrpt_preview_format_price( float $amount ): string {
		return function_exists( 'wc_price' ) ? wc_price( $amount ) : esc_html( '$' . number_format( $amount, 2 ) );
	}
}

// Sample cancellation reasons, shaped exactly like Pro's real query result so
// the preview and the real report render from the same structure.
$dummy_cancellation_reasons = array(
	'reasons' => array(
		array(
			'reason'  => __( 'Too expensive', 'subscription' ),
			'count'   => 14,
			'percent' => 38.9,
		),
		array(
			'reason'  => __( 'Missing features I need', 'subscription' ),
			'count'   => 9,
			'percent' => 25.0,
		),
		array(
			'reason'  => __( 'Found a better alternative', 'subscription' ),
			'count'   => 7,
			'percent' => 19.4,
		),
		array(
			'reason'  => __( 'No longer needed', 'subscription' ),
			'count'   => 4,
			'percent' => 11.1,
		),
		array(
			'reason'  => __( 'Technical issues', 'subscription' ),
			'count'   => 2,
			'percent' => 5.6,
		),
	),
	'total'   => 36,
);
?>

<!-- Page content -->
<div class="wp-subscription-admin-content list-page">

	<!-- Disclaimer banner -->
	<div style="max-width:1240px;margin:20px auto 0;padding:0;">
		<div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:12px 16px;display:flex;align-items:flex-start;gap:10px;">
			<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#d97706" style="flex-shrink:0;margin-top:1px;" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
			<p style="margin:0;font-size:13px;color:#92400e;line-height:1.5;">
				<strong><?php esc_html_e( 'Preview with sample data.', 'subscription' ); ?></strong>
				<?php esc_html_e( 'This page shows example data to illustrate the feature.', 'subscription' ); ?>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noreferrer noopener" style="color:#b45309;font-weight:600;text-decoration:underline;"><?php esc_html_e( 'Upgrade to Pro', 'subscription' ); ?></a>
				<?php esc_html_e( 'to unlock real subscription analytics.', 'subscription' ); ?>
			</p>
		</div>
	</div>

	<!-- Page header -->
	<div style="max-width:1240px;margin:20px auto 0;padding:0 0 20px;">
		<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:12px;">
			<div>
				<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0 0 6px;line-height:1.2;"><?php esc_html_e( 'Subscription Reports', 'subscription' ); ?></h1>
				<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.5;"><?php esc_html_e( 'Data-driven insights for your subscription business.', 'subscription' ); ?></p>
			</div>
			<div style="display:flex;align-items:center;gap:8px;flex-shrink:0;padding-top:4px;">
				<button type="button" class="wpsubs-btn wpsubs-btn--outline" style="height:28px;padding:0 10px;font-size:12px;cursor:not-allowed;opacity:0.6;" disabled>
					<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
					<?php esc_html_e( 'Refresh', 'subscription' ); ?>
				</button>
			</div>
		</div>
		<div style="border-top:1px dashed #d0d3d7;"></div>
	</div>

	<div class="wpsubs-tabs" data-tabs-query="report" style="max-width:1240px;margin:0 auto;">
		<div class="wpsubs-tabs__list" role="tablist">
			<button class="wpsubs-tabs__tab" role="tab" id="subscrpt-report-tab-overview" data-tab-key="overview" aria-controls="subscrpt-report-panel-overview" aria-selected="true">
				<?php esc_html_e( 'Overview', 'subscription' ); ?>
			</button>
			<button class="wpsubs-tabs__tab" role="tab" id="subscrpt-report-tab-cancellation" data-tab-key="cancellation" aria-controls="subscrpt-report-panel-cancellation" aria-selected="false">
				<?php esc_html_e( 'Cancellation', 'subscription' ); ?>
			</button>
		</div>

	<div class="wpsubs-tab-panel" role="tabpanel" id="subscrpt-report-panel-overview" aria-labelledby="subscrpt-report-tab-overview">

	<!-- KPI stat cards -->
	<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:16px;">

		<div style="border:1px solid var(--wpsubs-border);border-radius:12px;padding:20px;background:var(--wpsubs-surface);">
			<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:8px;">
				<div style="font-size:1.875rem;font-weight:700;color:#111827;line-height:1;"><?php echo esc_html( number_format_i18n( $dummy_metrics['active_subscriptions'] ) ); ?></div>
				<span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#eef2ff;flex-shrink:0;color:#6366f1;">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
				</span>
			</div>
			<div style="font-size:14px;font-weight:500;color:var(--wpsubs-text);"><?php esc_html_e( 'Active Subscriptions', 'subscription' ); ?></div>
			<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Recurring revenue base', 'subscription' ); ?></div>
		</div>

		<div style="border:1px solid var(--wpsubs-border);border-radius:12px;padding:20px;background:var(--wpsubs-surface);">
			<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:8px;">
				<div style="font-size:1.875rem;font-weight:700;color:#111827;line-height:1;"><?php echo wp_kses_post( subscrpt_preview_format_price( $dummy_revenue['mrr'] ) ); ?></div>
				<span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#f0fdf4;flex-shrink:0;color:#16a34a;">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
				</span>
			</div>
			<div style="font-size:14px;font-weight:500;color:var(--wpsubs-text);"><?php esc_html_e( 'Monthly Recurring Revenue', 'subscription' ); ?></div>
			<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Predictable monthly income', 'subscription' ); ?></div>
		</div>

		<div style="border:1px solid var(--wpsubs-border);border-radius:12px;padding:20px;background:var(--wpsubs-surface);">
			<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:8px;">
				<div style="font-size:1.875rem;font-weight:700;color:#ef4444;line-height:1;"><?php echo wp_kses_post( subscrpt_preview_format_price( $dummy_revenue_at_risk ) ); ?></div>
				<span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#fef2f2;flex-shrink:0;color:#ef4444;">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /></svg>
				</span>
			</div>
			<div style="font-size:14px;font-weight:500;color:var(--wpsubs-text);"><?php esc_html_e( 'Revenue at Risk', 'subscription' ); ?></div>
			<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Needs attention', 'subscription' ); ?></div>
		</div>

		<div style="border:1px solid var(--wpsubs-border);border-radius:12px;padding:20px;background:var(--wpsubs-surface);">
			<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:8px;">
				<div style="font-size:1.875rem;font-weight:700;color:#111827;line-height:1;"><?php echo esc_html( number_format_i18n( $dummy_metrics['churn_rate'], 1 ) ); ?>%</div>
				<span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#fff7ed;flex-shrink:0;color:#f97316;">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" /></svg>
				</span>
			</div>
			<div style="font-size:14px;font-weight:500;color:var(--wpsubs-text);"><?php esc_html_e( 'Churn Rate', 'subscription' ); ?></div>
			<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Customer retention indicator', 'subscription' ); ?></div>
		</div>

		<div style="border:1px solid var(--wpsubs-border);border-radius:12px;padding:20px;background:var(--wpsubs-surface);">
			<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:8px;">
				<div style="font-size:1.875rem;font-weight:700;color:#111827;line-height:1;"><?php echo esc_html( number_format_i18n( $dummy_metrics['trial_conversion_rate'], 1 ) ); ?>%</div>
				<span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#faf5ff;flex-shrink:0;color:#8b5cf6;">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
				</span>
			</div>
			<div style="font-size:14px;font-weight:500;color:var(--wpsubs-text);"><?php esc_html_e( 'Trial Conversion', 'subscription' ); ?></div>
			<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Trial to paid success', 'subscription' ); ?></div>
		</div>

	</div>

	<!-- Revenue & Trends chart -->
	<div class="wpsubs-table-card" style="margin-bottom:16px;">
		<div style="padding:16px 20px;border-bottom:1px solid var(--wpsubs-border);">
			<h2 style="font-size:13px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.07em;margin:0;"><?php esc_html_e( 'Revenue & Subscription Trends', 'subscription' ); ?></h2>
		</div>
		<div style="padding:20px;display:grid;grid-template-columns:1fr 260px;gap:24px;align-items:start;">
			<div>
				<canvas id="subscrpt-preview-trends-chart" style="width:100%;height:280px;"></canvas>
			</div>
			<div>
				<p style="font-size:12px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.07em;margin:0 0 10px;"><?php esc_html_e( 'Quick Insights', 'subscription' ); ?></p>
				<div style="display:flex;flex-direction:column;gap:10px;">
					<div style="padding:12px;border-radius:8px;border:1px solid #86efac;background:#f0fdf4;">
						<div style="font-size:12px;font-weight:600;color:#166534;margin-bottom:4px;"><?php esc_html_e( 'Revenue Growth', 'subscription' ); ?></div>
						<div style="font-size:12px;color:#15803d;"><?php esc_html_e( 'MRR is growing steadily — great foundation!', 'subscription' ); ?></div>
					</div>
					<div style="padding:12px;border-radius:8px;border:1px solid var(--wpsubs-border);background:var(--wpsubs-surface-muted);">
						<div style="font-size:12px;font-weight:600;color:var(--wpsubs-text);margin-bottom:4px;"><?php esc_html_e( 'Pro Tip', 'subscription' ); ?></div>
						<div style="font-size:12px;color:var(--wpsubs-text-muted);"><?php esc_html_e( 'Focus on top-performing products and optimise trial conversions.', 'subscription' ); ?></div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Performance metrics: 3 cards -->
	<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">

		<div class="wpsubs-table-card">
			<div style="padding:16px 20px;border-bottom:1px solid var(--wpsubs-border);">
				<h2 style="font-size:13px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.07em;margin:0;"><?php esc_html_e( 'Revenue Performance', 'subscription' ); ?></h2>
			</div>
			<div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo wp_kses_post( subscrpt_preview_format_price( $dummy_revenue['total_revenue'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Total Revenue', 'subscription' ); ?></div>
				</div>
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo wp_kses_post( subscrpt_preview_format_price( $dummy_revenue['arr'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Annual Recurring', 'subscription' ); ?></div>
				</div>
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo wp_kses_post( subscrpt_preview_format_price( $dummy_revenue['net_revenue'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Net Revenue', 'subscription' ); ?></div>
				</div>
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo wp_kses_post( subscrpt_preview_format_price( $dummy_metrics['average_subscription_value'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Avg. Value', 'subscription' ); ?></div>
				</div>
			</div>
		</div>

		<div class="wpsubs-table-card">
			<div style="padding:16px 20px;border-bottom:1px solid var(--wpsubs-border);">
				<h2 style="font-size:13px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.07em;margin:0;"><?php esc_html_e( 'Trial Performance', 'subscription' ); ?></h2>
			</div>
			<div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo esc_html( number_format_i18n( $dummy_trials['total_trials'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Total Trials', 'subscription' ); ?></div>
				</div>
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo esc_html( number_format_i18n( $dummy_trials['converted_trials'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Converted', 'subscription' ); ?></div>
				</div>
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo esc_html( number_format_i18n( $dummy_trials['active_trials'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Active Trials', 'subscription' ); ?></div>
				</div>
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo wp_kses_post( subscrpt_preview_format_price( $dummy_trials['trial_revenue'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Trial Revenue', 'subscription' ); ?></div>
				</div>
			</div>
		</div>

		<div class="wpsubs-table-card">
			<div style="padding:16px 20px;border-bottom:1px solid var(--wpsubs-border);">
				<h2 style="font-size:13px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.07em;margin:0;"><?php esc_html_e( 'Subscription Breakdown', 'subscription' ); ?></h2>
			</div>
			<div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo esc_html( number_format_i18n( $dummy_metrics['active_subscriptions'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Active', 'subscription' ); ?></div>
				</div>
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo esc_html( number_format_i18n( $dummy_metrics['cancelled_subscriptions'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Cancelled', 'subscription' ); ?></div>
				</div>
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo esc_html( number_format_i18n( $dummy_metrics['trial_subscriptions'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Trials', 'subscription' ); ?></div>
				</div>
				<div>
					<div style="font-size:1.125rem;font-weight:700;color:#111827;"><?php echo esc_html( number_format_i18n( $dummy_metrics['total_products'] ) ); ?></div>
					<div style="font-size:12px;color:var(--wpsubs-text-muted);margin-top:2px;"><?php esc_html_e( 'Products', 'subscription' ); ?></div>
				</div>
			</div>
		</div>

	</div>

	<!-- Top Products + Action Items -->
	<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

		<div class="wpsubs-table-card">
			<div style="padding:16px 20px;border-bottom:1px solid var(--wpsubs-border);">
				<h2 style="font-size:13px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.07em;margin:0;"><?php esc_html_e( 'Top Performing Products', 'subscription' ); ?></h2>
			</div>
			<?php foreach ( array_slice( $dummy_popular, 0, 5 ) as $index => $sub_product ) : ?>
				<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 20px;border-bottom:1px solid var(--wpsubs-border);">
					<div style="display:flex;align-items:center;gap:12px;min-width:0;">
						<span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:<?php echo $index < 3 ? '#eef2ff' : 'var(--wpsubs-surface-muted)'; ?>;color:<?php echo $index < 3 ? '#6366f1' : 'var(--wpsubs-text-muted)'; ?>;font-size:11px;font-weight:700;flex-shrink:0;">
							<?php echo esc_html( $index + 1 ); ?>
						</span>
						<div style="min-width:0;">
							<div class="wpsubs-cell-title" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $sub_product['product_name'] ); ?></div>
							<div class="wpsubs-cell-id">
								<?php
								printf(
									/* translators: %s: number of subscriptions */
									esc_html__( '%s subscriptions', 'subscription' ),
									esc_html( number_format_i18n( $sub_product['subscription_count'] ) )
								);
								?>
							</div>
						</div>
					</div>
					<div style="text-align:right;flex-shrink:0;">
						<div class="wpsubs-cell-title"><?php echo wp_kses_post( subscrpt_preview_format_price( $sub_product['total_revenue'] ) ); ?></div>
						<div class="wpsubs-cell-id">
							<?php
							printf(
								/* translators: %s: average revenue */
								esc_html__( '%s avg', 'subscription' ),
								wp_kses_post( subscrpt_preview_format_price( $sub_product['average_revenue'] ) )
							);
							?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="wpsubs-table-card">
			<div style="padding:16px 20px;border-bottom:1px solid var(--wpsubs-border);">
				<h2 style="font-size:13px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.07em;margin:0;"><?php esc_html_e( 'Action Items', 'subscription' ); ?></h2>
			</div>
			<div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px;">
				<div style="padding:14px;border-radius:8px;border:1px solid #86efac;background:#f0fdf4;">
					<div style="font-size:13px;font-weight:600;color:#166534;margin-bottom:6px;"><?php esc_html_e( 'Best Performers', 'subscription' ); ?></div>
					<ul style="margin:0;padding-left:16px;font-size:12px;color:#15803d;line-height:1.8;">
						<li><?php echo esc_html( $dummy_popular[0]['product_name'] ); ?> — <?php echo wp_kses_post( subscrpt_preview_format_price( $dummy_popular[0]['total_revenue'] ) ); ?></li>
						<li><?php echo esc_html( $dummy_popular[1]['product_name'] ); ?> — <?php echo wp_kses_post( subscrpt_preview_format_price( $dummy_popular[1]['total_revenue'] ) ); ?></li>
					</ul>
				</div>
				<div style="padding:14px;border-radius:8px;border:1px solid var(--wpsubs-border);background:var(--wpsubs-surface-muted);">
					<div style="font-size:13px;font-weight:600;color:var(--wpsubs-text);margin-bottom:6px;"><?php esc_html_e( 'Recommended Next Steps', 'subscription' ); ?></div>
					<ul style="margin:0;padding-left:16px;font-size:12px;color:var(--wpsubs-text-muted);line-height:1.8;">
						<li><?php esc_html_e( 'Analyse customer feedback', 'subscription' ); ?></li>
						<li><?php esc_html_e( 'Optimise pricing strategy', 'subscription' ); ?></li>
						<li><?php esc_html_e( 'Expand product offerings', 'subscription' ); ?></li>
					</ul>
				</div>
			</div>
		</div>

	</div>

	<!-- Detailed Analytics chart -->
	<div class="wpsubs-table-card">
		<div style="padding:16px 20px;border-bottom:1px solid var(--wpsubs-border);">
			<h2 style="font-size:13px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.07em;margin:0;"><?php esc_html_e( 'Detailed Analytics', 'subscription' ); ?></h2>
		</div>
		<div style="padding:20px;">
			<canvas id="subscrpt-preview-detailed-chart" style="width:100%;height:360px;"></canvas>
		</div>
	</div>

	</div><!-- /overview panel -->

	<div class="wpsubs-tab-panel" role="tabpanel" id="subscrpt-report-panel-cancellation" aria-labelledby="subscrpt-report-tab-cancellation" hidden>

		<div class="wpsubs-table-card">
			<div style="padding:16px 20px;border-bottom:1px solid var(--wpsubs-border);">
				<h2 style="font-size:13px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.07em;margin:0;"><?php esc_html_e( 'Cancellation Reasons', 'subscription' ); ?></h2>
				<p style="font-size:12px;color:var(--wpsubs-text-muted);margin:4px 0 0;"><?php esc_html_e( 'Why customers cancelled, based on submitted feedback.', 'subscription' ); ?></p>
			</div>
			<div style="padding:10px 20px 14px;">
				<?php
				foreach ( $dummy_cancellation_reasons['reasons'] as $subscrpt_reason_row ) :
					$subscrpt_reason_fill = max( 2, min( 100, (float) $subscrpt_reason_row['percent'] ) );
					?>
					<div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;padding:11px 14px;margin-bottom:6px;border-radius:8px;border:1px solid var(--wpsubs-border);background:linear-gradient(to left, var(--wpsubs-brand-light) 0, var(--wpsubs-brand-light) calc(<?php echo esc_attr( $subscrpt_reason_fill ); ?>% - 2px), var(--wpsubs-brand) calc(<?php echo esc_attr( $subscrpt_reason_fill ); ?>% - 2px), var(--wpsubs-brand) <?php echo esc_attr( $subscrpt_reason_fill ); ?>%, transparent <?php echo esc_attr( $subscrpt_reason_fill ); ?>%);">
						<span style="font-size:13px;font-weight:600;color:var(--wpsubs-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;"><?php echo esc_html( $subscrpt_reason_row['reason'] ); ?></span>
						<span style="flex-shrink:0;">
							<span style="font-size:14px;font-weight:700;color:var(--wpsubs-text);"><?php echo esc_html( $subscrpt_reason_row['percent'] ); ?>%</span>
							<span style="font-size:12px;color:var(--wpsubs-text-muted);margin-left:4px;">
								<?php
								printf(
									/* translators: %s: number of subscriptions. */
									esc_html__( '%s subs', 'subscription' ),
									esc_html( number_format_i18n( $subscrpt_reason_row['count'] ) )
								);
								?>
							</span>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
			<div style="padding:12px 20px;border-top:1px solid var(--wpsubs-border);font-size:12px;color:var(--wpsubs-text-muted);">
				<?php
				printf(
					/* translators: %s: total number of cancellations with feedback. */
					esc_html__( 'Total cancellations with feedback: %s', 'subscription' ),
					esc_html( number_format_i18n( $dummy_cancellation_reasons['total'] ) )
				);
				?>
			</div>
		</div>

		<p style="margin:14px 0 0;font-size:13px;color:var(--wpsubs-text-muted);text-align:center;">
			<?php esc_html_e( 'Sample data. Pro reports the reasons your own customers gave.', 'subscription' ); ?>
		</p>

	</div><!-- /cancellation panel -->
	</div><!-- /tabs -->

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var months   = <?php echo wp_json_encode( array_column( $dummy_trends, 'label' ) ); ?>;
	var revenue  = <?php echo wp_json_encode( array_column( $dummy_trends, 'revenue' ) ); ?>;
	var actives  = <?php echo wp_json_encode( array_column( $dummy_trends, 'active' ) ); ?>;
	var totals   = <?php echo wp_json_encode( array_column( $dummy_trends, 'total' ) ); ?>;
	var cancelled = <?php echo wp_json_encode( array_column( $dummy_trends, 'cancelled' ) ); ?>;
	var trials   = <?php echo wp_json_encode( array_column( $dummy_trends, 'trials' ) ); ?>;
	var conversions = <?php echo wp_json_encode( array_column( $dummy_trends, 'conversions' ) ); ?>;

	var trendsCtx = document.getElementById('subscrpt-preview-trends-chart');
	if (trendsCtx) {
		new Chart(trendsCtx.getContext('2d'), {
			type: 'bar',
			data: {
				labels: months,
				datasets: [
					{
						label: '<?php echo esc_js( __( 'Revenue', 'subscription' ) ); ?>',
						data: revenue,
						type: 'bar',
						backgroundColor: 'rgba(99,102,241,0.8)',
						borderColor: '#6366f1',
						borderWidth: 2,
						borderRadius: 6,
						borderSkipped: false,
						yAxisID: 'y1'
					},
					{
						label: '<?php echo esc_js( __( 'Active Subscriptions', 'subscription' ) ); ?>',
						data: actives,
						type: 'line',
						borderColor: '#22c55e',
						backgroundColor: 'rgba(34,197,94,0.1)',
						borderWidth: 3,
						fill: true,
						tension: 0.4,
						pointRadius: 6,
						pointHoverRadius: 9,
						pointBackgroundColor: '#22c55e',
						pointBorderColor: '#fff',
						pointBorderWidth: 2,
						yAxisID: 'y'
					}
				]
			},
			options: {
				responsive: true,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { position: 'top', labels: { usePointStyle: true, padding: 20, font: { size: 12 } } },
					title: { display: false }
				},
				scales: {
					x: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 }, color: '#94a3b8' } },
					y: { type: 'linear', display: true, position: 'left', beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 }, color: '#94a3b8' } },
					y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { font: { size: 11 }, color: '#94a3b8', callback: function(v) { return '$' + v.toLocaleString(); } } }
				}
			}
		});
	}

	var detailedCtx = document.getElementById('subscrpt-preview-detailed-chart');
	if (detailedCtx) {
		var palette = ['#6366f1','#22c55e','#ef4444','#f59e0b','#8b5cf6','#06b6d4'];
		var bubbleData = months.map(function(month, i) {
			return { x: i, y: totals[i], r: Math.max(5, (actives[i] / Math.max.apply(null, actives)) * 20), month: month, active: actives[i], cancelled: cancelled[i], trial: trials[i], conversion: conversions[i] };
		});
		new Chart(detailedCtx.getContext('2d'), {
			type: 'bubble',
			data: {
				datasets: [{
					label: '<?php echo esc_js( __( 'Subscription Performance', 'subscription' ) ); ?>',
					data: bubbleData,
					backgroundColor: bubbleData.map(function(_, i) { return palette[i % palette.length]; }),
					borderColor: bubbleData.map(function(_, i) { return palette[i % palette.length]; }),
					borderWidth: 2
				}]
			},
			options: {
				responsive: true,
				plugins: {
					legend: { display: false },
					tooltip: {
						backgroundColor: 'rgba(15,23,42,0.92)', titleColor: '#fff', bodyColor: '#cbd5e1', cornerRadius: 8, displayColors: false, padding: 12,
						callbacks: {
							title: function(ctx) { return ctx[0].raw.month; },
							label: function(ctx) { var d = ctx.raw; return ['<?php echo esc_js( __( 'Total', 'subscription' ) ); ?>: ' + d.y, '<?php echo esc_js( __( 'Active', 'subscription' ) ); ?>: ' + d.active, '<?php echo esc_js( __( 'Cancelled', 'subscription' ) ); ?>: ' + d.cancelled]; }
						}
					}
				},
				scales: {
					x: { type: 'linear', ticks: { stepSize: 1, callback: function(v) { return (v >= 0 && v < months.length) ? months[v] : ''; }, font: { size: 11 }, color: '#94a3b8' }, grid: { color: 'rgba(0,0,0,0.04)' } },
					y: { ticks: { font: { size: 11 }, color: '#94a3b8' }, grid: { color: 'rgba(0,0,0,0.04)' } }
				}
			}
		});
	}
});
</script>

<?php
$modal_title = __( 'Unlock Reports', 'subscription' );
$modal_desc  = __( 'Reports requires WPSubscription Pro. Unlock advanced features, priority support, and more with WPSubscription Pro.', 'subscription' );
require __DIR__ . '/pro-upgrade-modal.php';
