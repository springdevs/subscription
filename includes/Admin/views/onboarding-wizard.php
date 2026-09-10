<?php
/**
 * Onboarding Wizard Template.
 *
 * SPA-style: every section is rendered at once and JS toggles visibility.
 * Flow: Create Plan (type + name) -> Set Frequency (duration) ->
 * Connect to a Product -> Finish. A persistent right-rail preview shows the
 * plan → durations → products flow and fills in live as each step is filled.
 * The plan (group), duration (term) and product relation are created through
 * the Plans REST API; only product creation uses admin-ajax.
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_pro          = subscrpt_pro_activated();
$currency_symbol = get_woocommerce_currency_symbol();

// Whether the store has any product at all — decides the connect step's shape.
$has_products = (bool) wc_get_products(
	array(
		'status' => array( 'publish', 'draft', 'pending', 'private' ),
		'limit'  => 1,
		'return' => 'ids',
	)
);

// Plan types. Only "recurring" is available on the free plugin; the others are
// shown Pro-locked so the choice is visible but not selectable without Pro.
$plan_types = array(
	array(
		'key'   => 'recurring',
		'label' => __( 'Recurring', 'subscription' ),
		'desc'  => __( 'Charge on a repeating schedule.', 'subscription' ),
		'name'  => __( 'Recurring Subscription', 'subscription' ),
		'pro'   => false,
		'icon'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>',
	),
	array(
		'key'   => 'subscribe_save',
		'label' => __( 'Subscribe & Save', 'subscription' ),
		'desc'  => __( 'Recurring with a discount.', 'subscription' ),
		'name'  => __( 'Subscribe & Save', 'subscription' ),
		'pro'   => true,
		'icon'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
	),
	array(
		'key'   => 'installments',
		'label' => __( 'Installments', 'subscription' ),
		'desc'  => __( 'Split a price into payments.', 'subscription' ),
		'name'  => __( 'Installment Plan', 'subscription' ),
		'pro'   => true,
		'icon'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
	),
);

$interval_options = array(
	array(
		'value' => 'day',
		'label' => __( 'Day(s)', 'subscription' ),
	),
	array(
		'value' => 'week',
		'label' => __( 'Week(s)', 'subscription' ),
	),
	array(
		'value' => 'month',
		'label' => __( 'Month(s)', 'subscription' ),
	),
	array(
		'value' => 'year',
		'label' => __( 'Year(s)', 'subscription' ),
	),
);

$avatar_palette = array(
	array(
		'bg' => '#fde8d8',
		'fg' => '#b85c20',
	),
	array(
		'bg' => '#dbeafe',
		'fg' => '#1d4ed8',
	),
	array(
		'bg' => '#ede9fe',
		'fg' => '#6d28d9',
	),
	array(
		'bg' => '#d1fae5',
		'fg' => '#065f46',
	),
	array(
		'bg' => '#fce7f3',
		'fg' => '#9d174d',
	),
);

// Small SVG icons reused inside the preview graph nodes.
$subscrpt_icon_cal  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
$subscrpt_icon_box  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
$subscrpt_icon_plan = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>';
?>
<div class="wpsubs-layout" id="subscrpt-onboarding-wizard">

	<!-- Step indicator -->
	<div class="wpsubs-wizard-stepper" id="subscrpt-stepper">
		<div class="wpsubs-wizard-stepper__step active" data-step="1">
			<div class="wpsubs-wizard-stepper__num">1</div>
			<div class="wpsubs-wizard-stepper__label"><?php esc_html_e( 'Plan', 'subscription' ); ?></div>
		</div>
		<div class="wpsubs-wizard-stepper__line"></div>
		<div class="wpsubs-wizard-stepper__step" data-step="2">
			<div class="wpsubs-wizard-stepper__num">2</div>
			<div class="wpsubs-wizard-stepper__label"><?php esc_html_e( 'Durations', 'subscription' ); ?></div>
		</div>
		<div class="wpsubs-wizard-stepper__line"></div>
		<div class="wpsubs-wizard-stepper__step" data-step="3">
			<div class="wpsubs-wizard-stepper__num">3</div>
			<div class="wpsubs-wizard-stepper__label"><?php esc_html_e( 'Product', 'subscription' ); ?></div>
		</div>
		<div class="wpsubs-wizard-stepper__line"></div>
		<div class="wpsubs-wizard-stepper__step" data-step="4">
			<div class="wpsubs-wizard-stepper__num">4</div>
			<div class="wpsubs-wizard-stepper__label"><?php esc_html_e( 'Finish', 'subscription' ); ?></div>
		</div>
	</div>

	<!-- Hidden state -->
	<input type="hidden" id="subscrpt-wizard-page" value="1">
	<input type="hidden" id="subscrpt-has-products" value="<?php echo $has_products ? '1' : '0'; ?>">
	<input type="hidden" id="subscrpt-plan-type" value="recurring">
	<?php wp_nonce_field( 'subscrpt_onboarding_wizard', 'subscrpt_wizard_nonce' ); ?>

	<div class="wpsubs-wizard-layout">

		<!-- ======================= Steps (left) ======================= -->
		<div class="wpsubs-wizard-steps">

			<!-- PAGE 1: Create Plan (type + name) -->
			<div class="wpsubs-wizard-section active" data-page="1" id="subscrpt-section-1">
				<div class="wpsubs-wizard-card">
					<h1 class="wpsubs-p2-page-title"><?php esc_html_e( 'Create a plan', 'subscription' ); ?></h1>
					<p class="wpsubs-p2-page-subtitle"><?php esc_html_e( 'Pick a plan type and give it a name. You can create more plans later from the Plans page.', 'subscription' ); ?></p>

					<div class="wpsubs-p2-section-block">
						<p class="wpsubs-p2-section-label"><?php esc_html_e( '1. Plan type', 'subscription' ); ?></p>
						<div class="wpsubs-p2-option-cards wpsubs-p2-option-cards--list">
							<?php foreach ( $plan_types as $subscrpt_type ) : ?>
								<?php $subscrpt_locked = $subscrpt_type['pro'] && ! $is_pro; ?>
								<button type="button"
									class="wpsubs-p2-option-card wpsubs-plan-type-card<?php echo 'recurring' === $subscrpt_type['key'] ? ' active' : ''; ?>"
									data-type="<?php echo esc_attr( $subscrpt_type['key'] ); ?>"
									data-name="<?php echo esc_attr( $subscrpt_type['name'] ); ?>"
									data-label="<?php echo esc_attr( $subscrpt_type['label'] ); ?>"
									<?php echo $subscrpt_locked ? 'disabled' : ''; ?>>
									<div class="wpsubs-p2-option-card__icon"><?php echo $subscrpt_type['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static SVG defined above. ?></div>
									<div class="wpsubs-p2-option-card__text">
										<p class="wpsubs-p2-option-card__title">
											<?php echo esc_html( $subscrpt_type['label'] ); ?>
											<?php if ( $subscrpt_locked ) : ?>
												<span class="wpsubs-p2-pro-badge" title="<?php esc_attr_e( 'WPSubscription Pro required', 'subscription' ); ?>"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
											<?php endif; ?>
										</p>
										<p class="wpsubs-p2-option-card__desc"><?php echo esc_html( $subscrpt_type['desc'] ); ?></p>
									</div>
								</button>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="wpsubs-p2-section-block" style="margin-bottom:0;">
						<p class="wpsubs-p2-section-label"><?php esc_html_e( '2. Plan name', 'subscription' ); ?></p>
						<div class="wpsubs-form-row" style="margin-bottom:0;max-width:420px;">
							<input type="text" id="subscrpt_plan_title" class="wpsubs-input" autocomplete="off" value="<?php esc_attr_e( 'Recurring Subscription', 'subscription' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Monthly Box', 'subscription' ); ?>">
						</div>
					</div>
				</div>
			</div>

			<!-- PAGE 2: Set Frequency (duration) -->
			<div class="wpsubs-wizard-section" data-page="2" id="subscrpt-section-2">
				<div class="wpsubs-wizard-card">
					<h1 class="wpsubs-p2-page-title"><?php esc_html_e( 'Add billing durations', 'subscription' ); ?></h1>
					<p class="wpsubs-p2-page-subtitle"><?php esc_html_e( 'A duration is how often a customer is charged. Add one or more — the customer picks which to subscribe on.', 'subscription' ); ?></p>

					<div id="subscrpt-durations" data-durations></div>

					<button type="button" id="subscrpt-btn-add-duration" class="wpsubs-btn wpsubs-btn--outline" style="width:100%;justify-content:center;margin-top:4px;">
						<span class="dashicons dashicons-plus-alt2" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
						<?php esc_html_e( 'Add another duration', 'subscription' ); ?>
					</button>

					<template id="subscrpt-duration-tpl">
						<div class="wpsubs-dur is-open" data-dur>
							<div class="wpsubs-dur__head" data-dur-toggle>
								<span class="wpsubs-dur__icon"><?php echo $subscrpt_icon_cal; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static SVG. ?></span>
								<span class="wpsubs-dur__title" data-dur-title></span>
								<button type="button" class="wpsubs-dur__remove" data-dur-remove title="<?php esc_attr_e( 'Remove duration', 'subscription' ); ?>" aria-label="<?php esc_attr_e( 'Remove duration', 'subscription' ); ?>">&times;</button>
								<svg class="wpsubs-dur__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
							</div>
							<div class="wpsubs-dur__body">
								<div class="wpsubs-form-row">
									<label><?php esc_html_e( 'Billing every', 'subscription' ); ?></label>
									<div class="wpsubs-p2-billing-group">
										<input type="number" class="wpsubs-input wpsubs-p2-billing-per-input" autocomplete="off" min="1" value="1" data-dur-freq>
<?php
								wpsubs_render_adv_select(
									array(
										'name'    => 'subscrpt_dur_interval',
										'value'   => 'month',
										'options' => $interval_options,
										'class'   => 'wpsubs-dur__interval',
										'attrs'   => array( 'data-dur-interval' => '1' ),
									)
								);
								?>
									</div>
								</div>
								<div class="wpsubs-form-row" style="margin-bottom:0;">
									<label><?php esc_html_e( 'Name', 'subscription' ); ?></label>
									<input type="text" class="wpsubs-input" autocomplete="off" data-dur-name placeholder="<?php esc_attr_e( 'e.g. Every Month', 'subscription' ); ?>">
								</div>
							</div>
						</div>
					</template>
				</div>
			</div>

			<!-- PAGE 3: Connect to a Product -->
			<div class="wpsubs-wizard-section" data-page="3" id="subscrpt-section-3">
				<div class="wpsubs-wizard-card">
					<h1 class="wpsubs-p2-page-title"><?php esc_html_e( 'Connect the plan to a product', 'subscription' ); ?></h1>
					<p class="wpsubs-p2-page-subtitle"><?php esc_html_e( 'Once connected, the product becomes subscribable in your shop using this plan.', 'subscription' ); ?></p>

					<div class="wpsubs-p2-section-block" style="margin-bottom:0;">
						<?php if ( $has_products ) : ?>
							<p class="wpsubs-p2-section-label"><?php esc_html_e( 'How to connect', 'subscription' ); ?></p>
							<div class="wpsubs-p2-option-cards">
								<button type="button" class="wpsubs-p2-option-card wpsubs-connect-mode-card active" data-mode="existing">
									<div class="wpsubs-p2-option-card__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></div>
									<p class="wpsubs-p2-option-card__title"><?php esc_html_e( 'Use an existing product', 'subscription' ); ?></p>
									<p class="wpsubs-p2-option-card__desc"><?php esc_html_e( 'Attach the plan to a product you already sell.', 'subscription' ); ?></p>
								</button>
								<button type="button" class="wpsubs-p2-option-card wpsubs-connect-mode-card" data-mode="new">
									<div class="wpsubs-p2-option-card__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>
									<p class="wpsubs-p2-option-card__title"><?php esc_html_e( 'Create a new product', 'subscription' ); ?></p>
									<p class="wpsubs-p2-option-card__desc"><?php esc_html_e( 'Make a fresh product for this plan.', 'subscription' ); ?></p>
								</button>
							</div>

							<div id="subscrpt-connect-existing">
								<p class="wpsubs-p2-section-desc"><?php esc_html_e( 'Pick a WooCommerce product to attach this plan to.', 'subscription' ); ?></p>

								<div id="subscrpt-product-select-wrap">
									<div class="wpsubs-p2-product-search">
										<div class="wpsubs-p2-product-search__input-wrap">
											<svg class="wpsubs-p2-product-search__icon" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
											<input type="text" class="wpsubs-p2-product-search__input wpsubs-input" id="subscrpt-product-search-input"
												placeholder="<?php esc_attr_e( 'Search products by name or SKU...', 'subscription' ); ?>" autocomplete="off">
										</div>
										<div class="wpsubs-p2-product-search__dropdown" id="subscrpt-product-search-dropdown" style="display:none;">
											<?php
											$subscrpt_products = wc_get_products(
												array(
													'status' => 'publish',
													'limit' => 100,
													'orderby' => 'title',
													'order' => 'ASC',
													'type' => $is_pro ? array( 'simple', 'variable' ) : array( 'simple' ),
												)
											);
											foreach ( $subscrpt_products as $subscrpt_i => $subscrpt_wc_p ) :
												$subscrpt_price    = $subscrpt_wc_p->get_price();
												$subscrpt_sku      = $subscrpt_wc_p->get_sku();
												$subscrpt_type     = ucfirst( $subscrpt_wc_p->get_type() ) . ' ' . __( 'product', 'subscription' );
												$subscrpt_name     = $subscrpt_wc_p->get_name();
												$subscrpt_words    = array_filter( explode( ' ', $subscrpt_name ) );
												$subscrpt_initials = implode(
													'',
													array_slice(
														array_map(
															function ( $w ) {
																return mb_strtoupper( mb_substr( $w, 0, 1 ) );
															},
															$subscrpt_words
														),
														0,
														2
													)
												);
												$subscrpt_color    = $avatar_palette[ $subscrpt_i % count( $avatar_palette ) ];
												?>
												<div class="wpsubs-p2-product-search__item"
													data-id="<?php echo esc_attr( $subscrpt_wc_p->get_id() ); ?>"
													data-price="<?php echo esc_attr( $subscrpt_price ); ?>"
													data-type="<?php echo esc_attr( $subscrpt_type ); ?>"
													data-sku="<?php echo esc_attr( $subscrpt_sku ); ?>"
													data-name="<?php echo esc_attr( $subscrpt_name ); ?>">
													<div class="wpsubs-p2-product-search__avatar" style="background:<?php echo esc_attr( $subscrpt_color['bg'] ); ?>;color:<?php echo esc_attr( $subscrpt_color['fg'] ); ?>">
														<?php echo esc_html( $subscrpt_initials ? $subscrpt_initials : '?' ); ?>
													</div>
													<div class="wpsubs-p2-product-search__info">
														<p class="wpsubs-p2-product-search__name"><?php echo esc_html( $subscrpt_name ); ?></p>
														<p class="wpsubs-p2-product-search__meta"><?php echo esc_html( ( $subscrpt_sku ? 'SKU ' . $subscrpt_sku . ' · ' : '' ) . $subscrpt_type ); ?></p>
													</div>
													<?php if ( '' !== $subscrpt_price ) : ?>
														<span class="wpsubs-p2-product-search__price"><?php echo esc_html( $currency_symbol . number_format( (float) $subscrpt_price, 2 ) ); ?></span>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
											<div class="wpsubs-p2-product-search__empty" style="display:none;"><?php esc_html_e( 'No products found.', 'subscription' ); ?></div>
										</div>
									</div>
									<input type="hidden" id="subscrpt-existing-product-hidden" value="">
								</div>

								<div id="subscrpt-selected-product-chip" class="wpsubs-p2-selected-product" style="display:none;">
									<div class="wpsubs-p2-selected-product__avatar" id="p3-chip-avatar"></div>
									<div class="wpsubs-p2-selected-product__info">
										<p class="wpsubs-p2-selected-product__name" id="p3-chip-name"></p>
										<p class="wpsubs-p2-selected-product__meta" id="p3-chip-meta"></p>
									</div>
									<button type="button" class="wpsubs-p2-selected-product__clear" id="subscrpt-btn-clear-product" aria-label="<?php esc_attr_e( 'Clear selection', 'subscription' ); ?>">×</button>
								</div>
							</div><!-- /#subscrpt-connect-existing -->
						<?php endif; ?>

						<div id="subscrpt-connect-new" <?php echo $has_products ? 'style="display:none;"' : ''; ?>>
							<?php if ( ! $has_products ) : ?>
								<p class="wpsubs-p2-section-label"><?php esc_html_e( 'Create a product', 'subscription' ); ?></p>
								<p class="wpsubs-p2-section-desc"><?php esc_html_e( "You don't have any products yet, so let's create one and connect the plan to it.", 'subscription' ); ?></p>
							<?php endif; ?>
							<div class="wpsubs-form-row">
								<label for="subscrpt_new_product_name"><?php esc_html_e( 'Product name', 'subscription' ); ?></label>
								<input type="text" id="subscrpt_new_product_name" class="wpsubs-input" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. Monthly Subscription Box', 'subscription' ); ?>">
								<p class="wpsubs-p2-field-hint"><?php esc_html_e( 'Shown on your store page and in subscription emails.', 'subscription' ); ?></p>
							</div>
						</div>

						<!-- Price for this plan on the product -->
						<div class="wpsubs-form-row" style="max-width:280px;">
							<label for="subscrpt_connect_price"><?php esc_html_e( 'Price', 'subscription' ); ?></label>
							<div class="wpsubs-p2-input-wrap">
								<span class="wpsubs-p2-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
								<input type="text" id="subscrpt_connect_price" class="wpsubs-input" autocomplete="off" style="padding-left:24px!important;" placeholder="0.00">
							</div>
							<p class="wpsubs-p2-field-hint"><?php esc_html_e( 'What the customer pays each billing cycle.', 'subscription' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<!-- PAGE 4: Finish — everything is created here, sequentially. -->
			<div class="wpsubs-wizard-section" data-page="4" id="subscrpt-section-4">
				<div class="wpsubs-wizard-card wpsubs-p3-card">

					<!-- Working state: shown while the plan, durations and product are created. -->
					<div id="subscrpt-finalize-progress" class="wpsubs-finalize-progress">
						<div class="wpsubs-finalize-spinner" aria-hidden="true"></div>
						<h1 class="wpsubs-p3-heading"><?php esc_html_e( 'Setting things up…', 'subscription' ); ?></h1>
						<p class="wpsubs-p3-subtext"><?php esc_html_e( 'Creating your plan, its durations and connecting the product. This only takes a moment.', 'subscription' ); ?></p>
						<ul class="wpsubs-finalize-steps">
							<li data-finalize-step="plan"><span class="wpsubs-finalize-steps__dot"></span><?php esc_html_e( 'Create the plan', 'subscription' ); ?></li>
							<li data-finalize-step="durations"><span class="wpsubs-finalize-steps__dot"></span><?php esc_html_e( 'Add billing durations', 'subscription' ); ?></li>
							<li data-finalize-step="product"><span class="wpsubs-finalize-steps__dot"></span><?php esc_html_e( 'Connect the product', 'subscription' ); ?></li>
						</ul>
					</div>

					<!-- Error state: shown if any step fails; the user can retry. -->
					<div id="subscrpt-finalize-error" class="wpsubs-finalize-error" hidden>
						<div class="wpsubs-finalize-error__icon" aria-hidden="true">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
						</div>
						<h1 class="wpsubs-p3-heading"><?php esc_html_e( "Something didn't go through.", 'subscription' ); ?></h1>
						<p class="wpsubs-p3-subtext" id="subscrpt-finalize-error-msg"></p>
						<button type="button" id="subscrpt-btn-retry-finalize" class="wpsubs-btn wpsubs-btn--primary"><?php esc_html_e( 'Try again', 'subscription' ); ?></button>
					</div>

					<!-- Success state: shown once everything is created. -->
					<div id="subscrpt-finalize-success" hidden>
						<div class="wpsubs-p3-success-icon">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<polyline points="20 6 9 17 4 12"></polyline>
							</svg>
						</div>

						<h1 class="wpsubs-p3-heading"><?php esc_html_e( 'Your plan is live.', 'subscription' ); ?></h1>
						<p class="wpsubs-p3-subtext"><?php esc_html_e( 'The plan is connected and the product is now subscribable. When a customer buys it, a subscription is created automatically — you\'ll see those in the Subscriptions list.', 'subscription' ); ?></p>

						<p class="wpsubs-p3-what-now-label"><?php esc_html_e( 'WHAT NOW?', 'subscription' ); ?></p>

						<div class="wpsubs-p3-action-rows">
							<button type="button" id="subscrpt-btn-add-another" class="wpsubs-p3-action-row">
								<div class="wpsubs-p3-action-row__icon">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
								</div>
								<div class="wpsubs-p3-action-row__content">
									<p class="wpsubs-p3-action-row__title"><?php esc_html_e( 'Create another plan', 'subscription' ); ?></p>
									<p class="wpsubs-p3-action-row__desc"><?php esc_html_e( 'Set up another subscription plan now.', 'subscription' ); ?></p>
								</div>
								<svg class="wpsubs-p3-action-row__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
							</button>
						</div>

						<p class="wpsubs-p3-help-text">
							<?php esc_html_e( 'Need help? Check the', 'subscription' ); ?>
							<a href="https://docs.wpsubscription.co/en?utm_source=plugin&utm_medium=admin&utm_campaign=docs" target="_blank" rel="noopener" class="wpsubs-p3-help-link"><?php esc_html_e( 'setup guide', 'subscription' ); ?></a>
							<?php esc_html_e( 'or', 'subscription' ); ?>
							<a href="https://wordpress.org/support/plugin/subscription/" target="_blank" rel="noopener" class="wpsubs-p3-help-link"><?php esc_html_e( 'contact support', 'subscription' ); ?></a>.
						</p>
					</div>
				</div>
			</div>

		</div><!-- /.wpsubs-wizard-steps -->

		<!-- ===================== Live preview (right) ===================== -->
		<aside class="wpsubs-wizard-aside">
			<div class="wpsubs-preview">
				<p class="wpsubs-preview__label"><?php esc_html_e( 'Live preview', 'subscription' ); ?></p>
				<div class="wpsubs-p1-graph" id="subscrpt-preview-graph" data-active="plan" aria-hidden="true">
					<svg class="wpsubs-p1-graph__lines" viewBox="0 0 300 420" preserveAspectRatio="none">
						<!-- Plan -> durations (left→right offsets +1.5, +3, +1: right highest,
							left slightly lower, middle lowest). data-line-dur maps each line
							to the duration it feeds, so it colours in when filled. -->
						<path class="wpsubs-p1-flow" data-line-dur="1" d="M150,50 C150,80 51,80 51,130" />
						<path class="wpsubs-p1-flow" data-line-dur="0" d="M150,50 L150,168" />
						<path class="wpsubs-p1-flow" data-line-dur="2" d="M150,50 C150,74 249,74 249,118" />
						<!-- Durations -> products (start at each card's bottom). data-line-to="prod"
							keeps these muted until a product is added, too. -->
						<path class="wpsubs-p1-flow" data-line-dur="1" data-line-to="prod" d="M51,168 C51,235 75,235 75,300" />
						<path class="wpsubs-p1-flow" data-line-dur="0" data-line-to="prod" d="M150,206 C150,255 75,255 75,300" />
						<path class="wpsubs-p1-flow" data-line-dur="0" data-line-to="prod" d="M150,206 C150,262 225,262 225,330" />
						<path class="wpsubs-p1-flow" data-line-dur="2" data-line-to="prod" d="M249,156 C249,240 225,240 225,330" />
					</svg>

					<div class="wpsubs-p1-node wpsubs-p1-node--plan" data-group="plan" style="left:21%;top:2.9%;">
						<span class="wpsubs-p1-node__icon"><?php echo $subscrpt_icon_plan; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static SVG. ?></span>
						<span class="wpsubs-p1-node__text">
							<span class="wpsubs-p1-node__title" id="subscrpt-preview-plan"><?php esc_html_e( 'Recurring Subscription', 'subscription' ); ?></span>
							<span class="wpsubs-p1-node__sub" id="subscrpt-preview-plan-type"><?php esc_html_e( 'Recurring', 'subscription' ); ?></span>
						</span>
					</div>

					<div class="wpsubs-p1-node wpsubs-p1-node--dur wpsubs-p1-node--ghost" data-group="dur" data-preview-dur="1" style="left:2%;top:31%;">
						<span class="wpsubs-p1-node__icon"><?php echo $subscrpt_icon_cal; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static SVG. ?></span>
						<span class="wpsubs-p1-node__text">
							<span class="wpsubs-p1-node__title"><?php esc_html_e( 'Duration', 'subscription' ); ?></span>
							<span class="wpsubs-p1-node__sub"><?php esc_html_e( 'Add more', 'subscription' ); ?></span>
						</span>
					</div>
					<div class="wpsubs-p1-node wpsubs-p1-node--dur wpsubs-p1-node--ghost" data-group="dur" data-preview-dur="0" style="left:35%;top:40%;">
						<span class="wpsubs-p1-node__icon"><?php echo $subscrpt_icon_cal; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static SVG. ?></span>
						<span class="wpsubs-p1-node__text">
							<span class="wpsubs-p1-node__title"><?php esc_html_e( 'Duration', 'subscription' ); ?></span>
							<span class="wpsubs-p1-node__sub"><?php esc_html_e( 'Add more', 'subscription' ); ?></span>
						</span>
					</div>
					<div class="wpsubs-p1-node wpsubs-p1-node--dur wpsubs-p1-node--ghost" data-group="dur" data-preview-dur="2" style="left:68%;top:28%;">
						<span class="wpsubs-p1-node__icon"><?php echo $subscrpt_icon_cal; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static SVG. ?></span>
						<span class="wpsubs-p1-node__text">
							<span class="wpsubs-p1-node__title"><?php esc_html_e( 'Duration', 'subscription' ); ?></span>
							<span class="wpsubs-p1-node__sub"><?php esc_html_e( 'Add more', 'subscription' ); ?></span>
						</span>
					</div>

					<div class="wpsubs-p1-node wpsubs-p1-node--prod" data-group="prod" style="left:3%;top:71.4%;">
						<span class="wpsubs-p1-node__icon"><?php echo $subscrpt_icon_box; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static SVG. ?></span>
						<span class="wpsubs-p1-node__text">
							<span class="wpsubs-p1-node__title" id="subscrpt-preview-prod"><?php esc_html_e( 'Product', 'subscription' ); ?></span>
							<span class="wpsubs-p1-node__sub"><?php esc_html_e( 'Subscribable', 'subscription' ); ?></span>
						</span>
					</div>
					<div class="wpsubs-p1-node wpsubs-p1-node--prod wpsubs-p1-node--ghost" data-group="prod" style="left:53%;top:78.6%;">
						<span class="wpsubs-p1-node__icon"><?php echo $subscrpt_icon_box; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static SVG. ?></span>
						<span class="wpsubs-p1-node__text">
							<span class="wpsubs-p1-node__title"><?php esc_html_e( 'Product', 'subscription' ); ?></span>
							<span class="wpsubs-p1-node__sub"><?php esc_html_e( 'Add more', 'subscription' ); ?></span>
						</span>
					</div>
				</div>
				<p class="wpsubs-preview__caption"><?php esc_html_e( 'A plan holds billing durations and connects to the products customers subscribe to.', 'subscription' ); ?></p>
			</div>
		</aside>

		<!-- ======================= Footer nav (under the left column) ======================= -->
		<div class="wpsubs-wizard-footer">
			<div class="wpsubs-wizard-nav" data-nav="1">
				<button type="button" id="subscrpt-btn-skip" class="wpsubs-p2-nav-back">
					<?php esc_html_e( 'Skip setup', 'subscription' ); ?>
				</button>
				<button type="button" id="subscrpt-btn-next-1" class="wpsubs-btn wpsubs-btn--primary">
					<?php esc_html_e( 'Continue', 'subscription' ); ?> &rsaquo;
				</button>
			</div>
			<div class="wpsubs-wizard-nav" data-nav="2" hidden>
				<button type="button" id="subscrpt-btn-back-1" class="wpsubs-p2-nav-back">
					&#8249; <?php esc_html_e( 'Back', 'subscription' ); ?>
				</button>
				<button type="button" id="subscrpt-btn-next-2" class="wpsubs-btn wpsubs-btn--primary">
					<?php esc_html_e( 'Continue', 'subscription' ); ?> &rsaquo;
				</button>
			</div>
			<div class="wpsubs-wizard-nav" data-nav="3" hidden>
				<button type="button" id="subscrpt-btn-back-2" class="wpsubs-p2-nav-back">
					&#8249; <?php esc_html_e( 'Back', 'subscription' ); ?>
				</button>
				<button type="button" id="subscrpt-btn-next-3" class="wpsubs-btn wpsubs-btn--primary">
					<?php esc_html_e( 'Continue', 'subscription' ); ?> &rsaquo;
				</button>
			</div>
			<div class="wpsubs-wizard-nav" data-nav="4" hidden>
				<a href="#" id="subscrpt-link-plans" class="wpsubs-btn wpsubs-btn--outline" hidden>
					<?php esc_html_e( 'Go to Plans', 'subscription' ); ?>
				</a>
				<a href="#" id="subscrpt-link-products" class="wpsubs-btn wpsubs-btn--primary" hidden>
					<?php esc_html_e( 'Go to products', 'subscription' ); ?>
				</a>
			</div>
		</div>

	</div><!-- /.wpsubs-wizard-layout -->
</div>
