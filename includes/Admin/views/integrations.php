<?php
/**
 * Integrations Admin Page
 *
 * Displays payment gateways and third-party integrations as cards.
 *
 * @package SpringDevs\Subscription\Admin
 *
 * @var array $integrations Filtered integrations list.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Split by type.
$payment_gateways = array_filter(
	$integrations,
	function ( $i ) {
		return ( $i['type'] ?? 'payment_gateway' ) === 'payment_gateway';
	}
);
$third_party      = array_filter(
	$integrations,
	function ( $i ) {
		return ( $i['type'] ?? '' ) === 'third_party';
	}
);

// Category config. The `section` key is the full sub-section heading; `label` is the short card badge.
$category_config = [
	'lms'        => [
		'label'   => __( 'LMS', 'subscription' ),
		'section' => __( 'Learning Management System', 'subscription' ),
		'bg'      => '#ede9fe',
		'color'   => '#6d28d9',
	],
	'crm'        => [
		'label'   => __( 'CRM', 'subscription' ),
		'section' => __( 'Customer Relationship Management', 'subscription' ),
		'bg'      => '#fce7f3',
		'color'   => '#9d174d',
	],
	'automation' => [
		'label'   => __( 'Automation', 'subscription' ),
		'section' => __( 'Automation', 'subscription' ),
		'bg'      => '#e0f2fe',
		'color'   => '#0369a1',
	],
	'email'      => [
		'label'   => __( 'Email', 'subscription' ),
		'section' => __( 'Email Marketing', 'subscription' ),
		'bg'      => '#dcfce7',
		'color'   => '#166534',
	],
	'license'    => [
		'label'   => __( 'License', 'subscription' ),
		'section' => __( 'License Management', 'subscription' ),
		'bg'      => '#fef3c7',
		'color'   => '#92400e',
	],
];

// Group third-party integrations by category, preserving the config order above
// and appending any uncategorised ones under "Other".
$third_party_grouped = [];
foreach ( array_keys( $category_config ) as $cat_key ) {
	$third_party_grouped[ $cat_key ] = [];
}
foreach ( $third_party as $integration ) {
	$cat_key                           = $integration['category'] ?? '';
	$cat_key                           = isset( $category_config[ $cat_key ] ) ? $cat_key : 'other';
	$third_party_grouped[ $cat_key ][] = $integration;
}
$third_party_grouped = array_filter( $third_party_grouped );

/**
 * Facet counts for the filter bar.
 *
 * Derived from the same array the cards render from, so a count can never
 * disagree with what is on screen — including when filter_integration_actions()
 * has removed something, or when a future integration is added and nobody
 * remembers to update a hardcoded number.
 */
$subscrpt_status_of = static function ( array $integration ): string {
	if ( ! empty( $integration['is_active'] ) ) {
		return 'active';
	}
	return ! empty( $integration['is_installed'] ) ? 'inactive' : 'not-installed';
};

$subscrpt_facets = [
	'category' => [],
	'status'   => [
		'active'        => 0,
		'inactive'      => 0,
		'not-installed' => 0,
	],
	// No `recurring` facet: every integration that supports automatic
	// recurring is a payment gateway, so the chip selected exactly the same
	// six cards as the Payment Gateways category. The card badge still shows
	// it, where it says something about that one integration.
	'tag'      => [
		'pro'  => 0,
		'beta' => 0,
	],
];

foreach ( $integrations as $integration ) {
	$cat_key = ( ( $integration['type'] ?? '' ) === 'payment_gateway' )
		? 'payment_gateway'
		: ( isset( $category_config[ $integration['category'] ?? '' ] ) ? $integration['category'] : 'other' );

	$subscrpt_facets['category'][ $cat_key ] = ( $subscrpt_facets['category'][ $cat_key ] ?? 0 ) + 1;
	++$subscrpt_facets['status'][ $subscrpt_status_of( $integration ) ];

	if ( ! empty( $integration['is_pro'] ) ) {
		++$subscrpt_facets['tag']['pro'];
	}
	if ( ! empty( $integration['is_beta'] ) ) {
		++$subscrpt_facets['tag']['beta'];
	}
}

$subscrpt_total = count( $integrations );

// Labels for the category chips. Payment gateways are a `type`, not a
// `category`, but on this page they read as one more group.
//
// The short `label` rather than the full `section`: in a 232px rail
// "Customer Relationship Management" wraps to two lines and the group of
// chips ends up taller than the viewport. The section headings above the
// cards still spell it out in full.
$subscrpt_category_labels = [ 'payment_gateway' => __( 'Payment Gateways', 'subscription' ) ];
foreach ( $category_config as $key => $cfg ) {
	$subscrpt_category_labels[ $key ] = $cfg['label'] ?? $cfg['section'];
}
$subscrpt_category_labels['other'] = __( 'Other', 'subscription' );

$subscrpt_status_labels = [
	'active'        => __( 'Active', 'subscription' ),
	'inactive'      => __( 'Inactive', 'subscription' ),
	'not-installed' => __( 'Not installed', 'subscription' ),
];

$subscrpt_tag_labels = [
	'pro'  => __( 'Pro', 'subscription' ),
	'beta' => __( 'Beta', 'subscription' ),
];
?>

<div class="wp-subscription-admin-content list-page">

	<!-- Page header -->
	<div style="margin-bottom:20px;">
		<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0 0 6px;line-height:1.2;"><?php esc_html_e( 'Integrations', 'subscription' ); ?></h1>
		<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0 0 12px;line-height:1.5;"><?php esc_html_e( 'Connect your subscriptions with payment gateways and third-party plugins.', 'subscription' ); ?></p>
		<div style="border-top:1px dashed #d0d3d7;"></div>
	</div>

	<?php
	/**
	 * Filter bar.
	 *
	 * Filtering is done in the browser against cards that are all already in the
	 * DOM — there are fifteen of them, so a round trip per keystroke would be
	 * slower and would lose the page's scroll position for nothing.
	 *
	 * Every control degrades to "everything visible" without JavaScript, which
	 * is the state the page is in today.
	 */
	?>
	<?php
	/*
	 * Two columns: filters in a sticky rail, results beside them. There are
	 * three facet groups and a dozen chips — as a horizontal bar above the
	 * grid that pushed the first card most of the way down the viewport,
	 * and scrolled out of reach exactly when a long list needed narrowing.
	 */
	?>
	<div class="subscrpt-int-layout">

		<aside class="subscrpt-int-sidebar">
		<div class="subscrpt-int-filters" data-subscrpt-integration-filters hidden>

			<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-int-reset hidden>
				<?php esc_html_e( 'Reset', 'subscription' ); ?>
			</button>

			<?php
			$subscrpt_chip_groups = [
				[
					'facet'  => 'category',
					'legend' => __( 'Category', 'subscription' ),
					'counts' => $subscrpt_facets['category'],
					'labels' => $subscrpt_category_labels,
				],
				[
					'facet'  => 'status',
					'legend' => __( 'Status', 'subscription' ),
					'counts' => $subscrpt_facets['status'],
					'labels' => $subscrpt_status_labels,
				],
				[
					'facet'  => 'tag',
					'legend' => __( 'Tags', 'subscription' ),
					'counts' => $subscrpt_facets['tag'],
					'labels' => $subscrpt_tag_labels,
				],
			];

			foreach ( $subscrpt_chip_groups as $subscrpt_group ) :
				// A facet nothing has is noise, not information.
				$subscrpt_shown = array_filter( $subscrpt_group['counts'] );
				if ( empty( $subscrpt_shown ) ) {
					continue;
				}
				$subscrpt_is_single = in_array( $subscrpt_group['facet'], array( 'category', 'status' ), true );
				?>
				<nav class="wpsubs-vnav" aria-label="<?php echo esc_attr( $subscrpt_group['legend'] ); ?>">
					<p class="wpsubs-vnav__title"><?php echo esc_html( $subscrpt_group['legend'] ); ?></p>
					<?php
					/*
					 * Category and status are single-select — a card has exactly
					 * one of each, so picking two could only ever return nothing.
					 * Both therefore need an explicit "All" to come back to.
					 */
					if ( $subscrpt_is_single ) :
						?>
						<button type="button" class="wpsubs-vnav__item is-active" data-subscrpt-int-chip data-facet="<?php echo esc_attr( $subscrpt_group['facet'] ); ?>" data-value="">
							<span class="wpsubs-vnav__label"><?php esc_html_e( 'All', 'subscription' ); ?></span>
							<span class="subscrpt-int-chip__count"><?php echo esc_html( number_format_i18n( $subscrpt_total ) ); ?></span>
						</button>
						<?php
					endif;
					?>
					<?php foreach ( $subscrpt_shown as $subscrpt_key => $subscrpt_count ) : ?>
						<button
							type="button"
							class="wpsubs-vnav__item"
							data-subscrpt-int-chip
							data-facet="<?php echo esc_attr( $subscrpt_group['facet'] ); ?>"
							data-value="<?php echo esc_attr( $subscrpt_key ); ?>"
							aria-pressed="false"
						>
							<span class="wpsubs-vnav__label"><?php echo esc_html( $subscrpt_group['labels'][ $subscrpt_key ] ?? $subscrpt_key ); ?></span>
							<span class="subscrpt-int-chip__count"><?php echo esc_html( number_format_i18n( $subscrpt_count ) ); ?></span>
						</button>
					<?php endforeach; ?>
				</nav>
				<?php
			endforeach;
			?>
		</div>
		</aside>

		<div class="subscrpt-int-results">
		<p class="subscrpt-int-empty" data-subscrpt-int-empty hidden>
			<?php esc_html_e( 'No integrations match these filters.', 'subscription' ); ?>
		</p>

		<?php if ( ! empty( $_GET['subscrpt_installed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible" style="margin:0 0 16px;"><p><?php esc_html_e( 'Plugin installed and activated successfully.', 'subscription' ); ?></p></div>
		<?php endif; ?>

		<!-- Payment Gateways -->
		<div style="margin-bottom:32px;" data-subscrpt-int-section>
			<div style="margin-bottom:12px;">
				<h2 style="font-size:12px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.06em;margin:0;line-height:1.4;margin-left:1px;"><?php esc_html_e( 'Payment Gateways', 'subscription' ); ?></h2>
			</div>
			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
				<?php foreach ( $payment_gateways as $integration ) : ?>
					<?php
					$is_installed = ! empty( $integration['is_installed'] );
					$is_active    = ! empty( $integration['is_active'] );
					$is_beta      = ! empty( $integration['is_beta'] );
					$is_pro       = ! empty( $integration['is_pro'] );
					$icon_url     = $integration['icon_url'] ?? '';
					$icon_initial = $integration['icon_initial'] ?? strtoupper( substr( $integration['title'], 0, 2 ) );
					$icon_color   = $integration['icon_color'] ?? '#64748b';

					if ( $is_active ) {
						$status_dot  = '#16a34a';
						$status_text = __( 'Active', 'subscription' );
					} elseif ( $is_installed ) {
						$status_dot  = '#d97706';
						$status_text = __( 'Inactive', 'subscription' );
					} else {
						$status_dot  = '#9ca3af';
						$status_text = __( 'Not Installed', 'subscription' );
					}

					// Filtering reads these rather than scraping the rendered markup,
					// which would break the moment a label is translated.
					$subscrpt_card_status = $subscrpt_status_of( $integration );
					$subscrpt_card_tags   = array_keys(
						array_filter(
							[
								'pro'       => $is_pro,
								'beta'      => $is_beta,
								'recurring' => ! empty( $integration['supports_recurring'] ),
							]
						)
					);
					?>
					<div
						class="wpsubs-table-card subscrpt-int-card"
						style="padding:16px;display:flex;flex-direction:column;gap:12px;"
						data-subscrpt-int-card
						data-name="<?php echo esc_attr( $integration['title'] ); ?>"
						data-search="<?php echo esc_attr( strtolower( $integration['title'] . ' ' . ( $integration['description'] ?? '' ) ) ); ?>"
						data-category="payment_gateway"
						data-status="<?php echo esc_attr( $subscrpt_card_status ); ?>"
						data-tags="<?php echo esc_attr( implode( ' ', $subscrpt_card_tags ) ); ?>"
					>

						<!-- Header: icon + name + status -->
						<div style="display:flex;gap:10px;align-items:flex-start;">
							<div style="width:40px;height:40px;flex-shrink:0;border-radius:8px;overflow:hidden;border:1px solid var(--wpsubs-border);background:var(--wpsubs-surface-muted);display:flex;align-items:center;justify-content:center;">
								<?php if ( $icon_url ) : ?>
									<img src="<?php echo esc_url( $icon_url ); ?>" style="width:100%;height:100%;object-fit:contain;" alt="" />
								<?php else : ?>
									<span style="font-size:11px;font-weight:700;color:#fff;background:<?php echo esc_attr( $icon_color ); ?>;width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><?php echo esc_html( $icon_initial ); ?></span>
								<?php endif; ?>
							</div>
							<div style="flex:1;min-width:0;">
								<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:6px;margin-bottom:5px;">
									<span style="font-size:13px;font-weight:600;color:var(--wpsubs-text);line-height:1.3;"><?php echo esc_html( $integration['title'] ); ?></span>
									<span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;color:<?php echo esc_attr( $status_dot ); ?>;white-space:nowrap;flex-shrink:0;">
										<span style="width:7px;height:7px;border-radius:50%;background:<?php echo esc_attr( $status_dot ); ?>;flex-shrink:0;"></span>
										<?php echo esc_html( $status_text ); ?>
									</span>
								</div>
								<div style="display:flex;flex-wrap:wrap;gap:4px;">
									<?php if ( $is_pro ) : ?>
										<span style="display:inline-flex;align-items:center;font-size:10px;font-weight:600;padding:2px 7px;border-radius:10px;background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand-dark,#d93f00);line-height:1.6;"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
									<?php endif; ?>
									<?php if ( $is_beta ) : ?>
										<span style="display:inline-flex;align-items:center;font-size:10px;font-weight:500;padding:2px 7px;border-radius:10px;background:#fff7ed;color:#c2410c;line-height:1.6;"><?php esc_html_e( 'Beta', 'subscription' ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $integration['supports_recurring'] ) ) : ?>
										<span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:500;padding:2px 7px;border-radius:10px;background:#dcfce7;color:#166534;line-height:1.6;">
											<svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="#166534"/></svg>
											<?php esc_html_e( 'Automatic recurring', 'subscription' ); ?>
										</span>
									<?php else : ?>
										<span style="display:inline-flex;align-items:center;font-size:10px;font-weight:500;padding:2px 7px;border-radius:10px;background:#fff3e0;color:#c2410c;line-height:1.6;"><?php esc_html_e( 'Manual renewals only', 'subscription' ); ?></span>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<!-- Description -->
						<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.5;"><?php echo esc_html( $integration['description'] ?? '' ); ?></p>

						<!-- Separator -->
						<div style="border-top:1px solid var(--wpsubs-border);margin:auto -16px 0;"></div>

						<!-- Actions -->
						<div style="display:flex;gap:6px;flex-wrap:wrap;">
							<?php if ( $is_pro && ! defined( 'SUBSCRIPT_PRO_VERSION' ) ) : ?>
								<div style="width:100%;display:flex;align-items:center;gap:6px;background:var(--wpsubs-brand-light,#fff1eb);border-radius:6px;padding:7px 10px;font-size:12px;font-weight:500;color:var(--wpsubs-brand-dark,#d93f00);line-height:1.4;">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
									<?php esc_html_e( 'WPSubscription Pro required', 'subscription' ); ?>
								</div>
							<?php else : ?>
								<?php
								foreach ( $integration['actions'] as $action ) :
									$is_primary = ( 'function' === $action['type'] );
									$btn_class  = $is_primary ? 'wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm' : 'wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm';
									?>
									<?php if ( 'link' === $action['type'] ) : ?>
										<a href="<?php echo esc_url( $action['url'] ); ?>" class="<?php echo esc_attr( $btn_class ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
									<?php elseif ( 'external_link' === $action['type'] ) : ?>
										<a href="<?php echo esc_url( $action['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr( $btn_class ); ?>">
											<?php echo esc_html( $action['label'] ); ?>
											<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
										</a>
									<?php elseif ( 'function' === $action['type'] ) : ?>
										<button class="<?php echo esc_attr( $btn_class ); ?>" onclick="<?php echo esc_attr( $action['function'] ); ?>"><?php echo esc_html( $action['label'] ); ?></button>
									<?php endif; ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>

					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- 3rd Party Integrations -->
		<?php foreach ( $third_party_grouped as $cat_key => $cat_integrations ) : ?>
		<div style="margin-bottom:32px;" data-subscrpt-int-section>
			<div style="margin-bottom:12px;">
				<h2 style="font-size:12px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.06em;margin:0;line-height:1.4;margin-left:1px;"><?php echo esc_html( $category_config[ $cat_key ]['section'] ?? __( 'Other', 'subscription' ) ); ?></h2>
			</div>
			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
				<?php foreach ( $cat_integrations as $integration ) : ?>
					<?php
					$is_active    = ! empty( $integration['is_active'] );
					$is_pro       = ! empty( $integration['is_pro'] );
					$icon_url     = $integration['icon_url'] ?? '';
					$icon_initial = $integration['icon_initial'] ?? strtoupper( substr( $integration['title'], 0, 2 ) );
					$icon_color   = $integration['icon_color'] ?? '#64748b';
					$category     = $integration['category'] ?? '';
					$cat          = $category_config[ $category ] ?? [
						'label' => $category,
						'bg'    => '#f1f5f9',
						'color' => '#475569',
					];

					$status_dot  = $is_active ? '#16a34a' : '#9ca3af';
					$status_text = $is_active ? __( 'Active', 'subscription' ) : __( 'Not Installed', 'subscription' );

					$subscrpt_card_status = $subscrpt_status_of( $integration );
					$subscrpt_card_tags   = array_keys(
						array_filter(
							[
								'pro'       => $is_pro,
								'beta'      => ! empty( $integration['is_beta'] ),
								'recurring' => ! empty( $integration['supports_recurring'] ),
							]
						)
					);
					?>
					<div
						class="wpsubs-table-card subscrpt-int-card"
						style="padding:16px;display:flex;flex-direction:column;gap:12px;"
						data-subscrpt-int-card
						data-name="<?php echo esc_attr( $integration['title'] ); ?>"
						data-search="<?php echo esc_attr( strtolower( $integration['title'] . ' ' . ( $integration['description'] ?? '' ) ) ); ?>"
						data-category="<?php echo esc_attr( isset( $category_config[ $category ] ) ? $category : 'other' ); ?>"
						data-status="<?php echo esc_attr( $subscrpt_card_status ); ?>"
						data-tags="<?php echo esc_attr( implode( ' ', $subscrpt_card_tags ) ); ?>"
					>

						<!-- Header: icon + name + status -->
						<div style="display:flex;gap:10px;align-items:flex-start;">
							<div style="width:40px;height:40px;flex-shrink:0;border-radius:8px;overflow:hidden;border:1px solid var(--wpsubs-border);background:var(--wpsubs-surface-muted);display:flex;align-items:center;justify-content:center;">
								<?php if ( $icon_url ) : ?>
									<img src="<?php echo esc_url( $icon_url ); ?>" style="width:100%;height:100%;object-fit:contain;" alt="" />
								<?php else : ?>
									<span style="font-size:11px;font-weight:700;color:#fff;background:<?php echo esc_attr( $icon_color ); ?>;width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><?php echo esc_html( $icon_initial ); ?></span>
								<?php endif; ?>
							</div>
							<div style="flex:1;min-width:0;">
								<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:6px;margin-bottom:5px;">
									<span style="font-size:13px;font-weight:600;color:var(--wpsubs-text);line-height:1.3;"><?php echo esc_html( $integration['title'] ); ?></span>
									<span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;color:<?php echo esc_attr( $status_dot ); ?>;white-space:nowrap;flex-shrink:0;">
										<span style="width:7px;height:7px;border-radius:50%;background:<?php echo esc_attr( $status_dot ); ?>;flex-shrink:0;"></span>
										<?php echo esc_html( $status_text ); ?>
									</span>
								</div>
								<?php if ( $cat['label'] || $is_pro ) : ?>
									<div style="display:flex;flex-wrap:wrap;gap:4px;">
										<?php if ( $is_pro ) : ?>
											<span style="display:inline-flex;align-items:center;font-size:10px;font-weight:600;padding:2px 7px;border-radius:10px;background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand-dark,#d93f00);line-height:1.6;"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
										<?php endif; ?>
										<?php if ( $cat['label'] ) : ?>
											<span style="display:inline-flex;align-items:center;font-size:10px;font-weight:500;padding:2px 7px;border-radius:10px;background:<?php echo esc_attr( $cat['bg'] ); ?>;color:<?php echo esc_attr( $cat['color'] ); ?>;line-height:1.6;"><?php echo esc_html( $cat['label'] ); ?></span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>

						<!-- Description -->
						<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.5;"><?php echo esc_html( $integration['description'] ?? '' ); ?></p>

						<!-- Separator -->
						<div style="border-top:1px solid var(--wpsubs-border);margin:auto -16px 0;"></div>

						<!-- Actions -->
						<div style="display:flex;gap:6px;flex-wrap:wrap;">
							<?php if ( $is_pro && ! defined( 'SUBSCRIPT_PRO_VERSION' ) ) : ?>
								<div style="width:100%;display:flex;align-items:center;gap:6px;background:var(--wpsubs-brand-light,#fff1eb);border-radius:6px;padding:7px 10px;font-size:12px;font-weight:500;color:var(--wpsubs-brand-dark,#d93f00);line-height:1.4;">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
									<?php esc_html_e( 'WPSubscription Pro required', 'subscription' ); ?>
								</div>
							<?php else : ?>
								<?php
								foreach ( $integration['actions'] as $action ) :
									$is_primary = ( 'function' === $action['type'] );
									$btn_class  = $is_primary ? 'wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm' : 'wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm';
									?>
									<?php if ( 'link' === $action['type'] ) : ?>
										<a href="<?php echo esc_url( $action['url'] ); ?>" class="<?php echo esc_attr( $btn_class ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
									<?php elseif ( 'external_link' === $action['type'] ) : ?>
										<a href="<?php echo esc_url( $action['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr( $btn_class ); ?>">
											<?php echo esc_html( $action['label'] ); ?>
											<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
										</a>
									<?php elseif ( 'function' === $action['type'] ) : ?>
										<button class="<?php echo esc_attr( $btn_class ); ?>" onclick="<?php echo esc_attr( $action['function'] ); ?>"><?php echo esc_html( $action['label'] ); ?></button>
									<?php endif; ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>

					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>

		</div>

	</div>
</div>
