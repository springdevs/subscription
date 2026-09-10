<?php
/**
 * Add / Edit Duration modal.
 *
 * Common rows: name, billing every, free trial, signup fee (Pro).
 * Recurring Delivery adds: delivery schedule + synchronize toggle (all Pro).
 * Split Payment adds: number of payments + access-ends timing (all Pro).
 *
 * @var array $plan Plan (provides id + type).
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

$access_options = array(
	array(
		'value' => 'lifetime',
		'label' => __( 'Never', 'subscription' ),
	),
	array(
		'value' => 'full_duration',
		'label' => __( 'After full duration', 'subscription' ),
	),
	array(
		'value' => 'custom',
		'label' => __( 'Custom', 'subscription' ),
	),
);

global $wp_locale;
$weekday_options = array();
for ( $subscrpt_wd = 0; $subscrpt_wd < 7; $subscrpt_wd++ ) {
	$weekday_options[] = array(
		'value' => (string) $subscrpt_wd,
		'label' => $wp_locale ? $wp_locale->get_weekday( $subscrpt_wd ) : (string) $subscrpt_wd,
	);
}

// Match the onboarding "Subscription details" form-row styling.
$label_style = 'display:block;font-size:13px;font-weight:500;color:var(--wpsubs-text);margin:0 0 7px;';
$row_style   = 'margin-bottom:22px;';
$pair_style  = 'display:flex;gap:8px;align-items:center;';

/**
 * Render a field description as a hover tooltip beside the label, so the
 * descriptions no longer clutter the form as paragraphs. Uses the shared
 * wpsubs-tooltip component (via wpsubs_render_hint()).
 *
 * @param string $text Description text.
 * @return string Hint markup (already escaped).
 */
$hint = function ( $text ) {
	return wpsubs_render_hint( $text );
};

$pro_active = function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated();
$currency   = function_exists( 'get_woocommerce_currency_symbol' )
	? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
	: '$';

$group_type      = isset( $plan['type'] ) ? $plan['type'] : 'recurring';
$is_delivery     = 'subscribe_save' === $group_type;
$is_installments = 'installments' === $group_type;

// The delivery + split-payment fields are Pro. When Pro is inactive (e.g. a
// Pro-typed group opened after Pro was deactivated), lock them: a Pro badge on
// the label, disabled native inputs, and a non-interactive look on adv-selects.
$pro_locked = ! $pro_active;
$pro_badge  = $pro_locked
	? ' <span class="wpsubs-badge wpsubs-badge--pro" style="margin-left:6px;" title="' . esc_attr__( 'WPSubscription Pro required', 'subscription' ) . '">' . esc_html__( 'Pro', 'subscription' ) . '</span>'
	: '';
$adv_lock   = $pro_locked ? 'opacity:0.55;pointer-events:none;' : '';
?>
<div class="wpsubs-modal" id="subscrpt-term-modal" hidden data-subscrpt-term-modal data-group-id="<?php echo esc_attr( $plan['id'] ); ?>" data-group-type="<?php echo esc_attr( $group_type ); ?>">
	<div class="wpsubs-modal__backdrop" data-wpsubs-modal-close></div>
	<div class="wpsubs-modal__dialog" style="width:min(460px, calc(100vw - 40px));">
		<div class="wpsubs-modal__head">
			<h2 class="wpsubs-modal__title" data-subscrpt-term-title><?php esc_html_e( 'Add Duration', 'subscription' ); ?></h2>
			<button type="button" class="wpsubs-modal__close" data-wpsubs-modal-close aria-label="<?php esc_attr_e( 'Close', 'subscription' ); ?>">&times;</button>
		</div>
		<div class="wpsubs-modal__body" style="overflow:visible;">

			<!-- Duration name -->
			<div style="<?php echo esc_attr( $row_style ); ?>">
				<label style="<?php echo esc_attr( $label_style ); ?>" for="subscrpt-term-name"><?php esc_html_e( 'Duration Name', 'subscription' ); ?><?php echo wp_kses_post( $hint( __( 'Shown to customers when they pick this plan.', 'subscription' ) ) ); ?></label>
				<input type="text" id="subscrpt-term-name" class="wpsubs-input" value="" placeholder="<?php esc_attr_e( 'e.g. Monthly', 'subscription' ); ?>" data-subscrpt-field="title" />
			</div>

			<?php
			// Billing every + interval; for Split Payment it shares a row with the
			// number of payments.
			ob_start();
			?>
			<label style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Billing every', 'subscription' ); ?><?php echo wp_kses_post( $hint( __( 'How often the customer is charged, for example every 1 month.', 'subscription' ) ) ); ?></label>
			<div style="<?php echo esc_attr( $pair_style ); ?>">
				<input type="number" class="wpsubs-input" value="1" min="1" style="flex:1 1 auto;min-width:0;" data-subscrpt-field="billing_frequency" aria-label="<?php esc_attr_e( 'Frequency', 'subscription' ); ?>" />
				<?php
				wpsubs_render_adv_select(
					array(
						'name'    => 'subscrpt_billing_interval',
						'value'   => 'month',
						'options' => $interval_options,
						'attrs'   => array(
							'data-subscrpt-field' => 'billing_interval',
							'style'               => 'flex:0 0 auto;',
						),
					)
				);
				?>
			</div>
			<?php
			$billing_every_html = ob_get_clean();
			?>

			<?php if ( $is_installments ) : ?>
				<!-- Billing every (billing group) | Number of payments -->
				<div style="display:grid;grid-template-columns:2fr 1fr;gap:8px;align-items:start;<?php echo esc_attr( $row_style ); ?>">
					<div><?php echo $billing_every_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped markup (esc_* + wpsubs_render_adv_select()). ?></div>
					<?php // Label text is too long for this column; keep only the hint and an "×" prefix so it reads as a count: "1 Month(s) × 5". ?>
					<div>
						<label style="<?php echo esc_attr( $label_style ); ?>text-align:right;" for="subscrpt-term-installments"><?php echo wp_kses_post( $hint( __( 'Total payments to collect (minimum 2).', 'subscription' ) ) ); ?></label>
						<div style="display:flex;align-items:center;gap:8px;">
							<span style="font-size:15px;color:var(--wpsubs-text-muted);line-height:1;" aria-hidden="true">&times;</span>
							<input type="number" id="subscrpt-term-installments" class="wpsubs-input" value="2" min="2" placeholder="<?php esc_attr_e( 'times', 'subscription' ); ?>" style="flex:1 1 auto;min-width:0;" data-subscrpt-field="installment_count" aria-label="<?php esc_attr_e( 'Number of payments', 'subscription' ); ?>" <?php disabled( $pro_locked ); ?> />
						</div>
					</div>
				</div>
			<?php else : ?>
				<!-- Billing every -->
				<div style="<?php echo esc_attr( $row_style ); ?>"><?php echo $billing_every_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped markup (esc_* + wpsubs_render_adv_select()). ?></div>
			<?php endif; ?>

			<!-- Free trial | Signup fee -->
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;<?php echo ( $is_delivery || $is_installments ) ? esc_attr( $row_style ) : 'margin-bottom:0;'; ?>">
				<div>
					<label style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Free trial', 'subscription' ); ?> <span style="color:var(--wpsubs-text-subtle);font-weight:400;">(<?php esc_html_e( 'optional', 'subscription' ); ?>)</span><?php echo wp_kses_post( $hint( __( 'Charge nothing until the trial ends.', 'subscription' ) ) ); ?></label>
					<div style="<?php echo esc_attr( $pair_style ); ?>">
						<input type="number" class="wpsubs-input" value="" min="0" placeholder="0" style="flex:1 1 auto;min-width:0;" data-subscrpt-field="free_trial" aria-label="<?php esc_attr_e( 'Trial length', 'subscription' ); ?>" />
						<?php
						wpsubs_render_adv_select(
							array(
								'name'    => 'subscrpt_free_trial_interval',
								'value'   => 'day',
								'options' => $interval_options,
								'attrs'   => array(
									'data-subscrpt-field' => 'free_trial_interval',
									'style'               => 'flex:0 0 auto;',
								),
							)
						);
						?>
					</div>
				</div>

				<div>
					<label style="<?php echo esc_attr( $label_style ); ?>" for="subscrpt-term-signup-fee">
						<?php esc_html_e( 'Signup fee', 'subscription' ); ?> <span style="color:var(--wpsubs-text-subtle);font-weight:400;">(<?php esc_html_e( 'optional', 'subscription' ); ?>)</span>
						<?php if ( ! $pro_active ) : ?>
							<span class="wpsubs-badge wpsubs-badge--pro" style="margin-left:6px;" title="<?php esc_attr_e( 'WPSubscription Pro required', 'subscription' ); ?>"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
						<?php endif; ?>
						<?php echo wp_kses_post( $hint( __( 'One-time fee on the first payment.', 'subscription' ) ) ); ?>
					</label>
					<div style="position:relative;">
						<span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:13px;color:var(--wpsubs-text-muted);pointer-events:none;z-index:1;"><?php echo esc_html( $currency ); ?></span>
						<input type="number" id="subscrpt-term-signup-fee" class="wpsubs-input" value="" min="0" step="0.01" placeholder="0.00" style="padding-left:26px!important;" data-subscrpt-field="signup_fee_amount" <?php disabled( ! $pro_active ); ?> />
					</div>
				</div>
			</div>

			<?php if ( $is_delivery ) : ?>
				<!-- Recurring Delivery extras -->
				<div style="<?php echo esc_attr( $row_style ); ?>">
					<label style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Delivery schedule', 'subscription' ); ?><?php echo wp_kses_post( $pro_badge ); ?><?php echo wp_kses_post( $hint( __( 'How often the product ships. Leave empty to match the billing schedule.', 'subscription' ) ) ); ?></label>
					<div style="<?php echo esc_attr( $pair_style ); ?>">
						<input type="number" class="wpsubs-input" value="" min="1" placeholder="<?php esc_attr_e( 'Same as billing', 'subscription' ); ?>" style="flex:1 1 auto;min-width:0;" data-subscrpt-field="delivery_frequency" aria-label="<?php esc_attr_e( 'Delivery frequency', 'subscription' ); ?>" <?php disabled( $pro_locked ); ?> />
						<?php
						wpsubs_render_adv_select(
							array(
								'name'    => 'subscrpt_delivery_interval',
								'value'   => 'month',
								'options' => $interval_options,
								'attrs'   => array(
									'data-subscrpt-field' => 'delivery_interval',
									'style'               => 'flex:0 0 auto;' . $adv_lock,
								),
							)
						);
						?>
					</div>
				</div>

				<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;margin-bottom:0;">
					<div>
						<label style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Synchronize schedule', 'subscription' ); ?><?php echo wp_kses_post( $pro_badge ); ?><?php echo wp_kses_post( $hint( __( 'Deliver everyone on the same weekday.', 'subscription' ) ) ); ?></label>
						<label class="wpsubs-settings-toggle-label" style="display:inline-flex;align-items:center;gap:8px;height:36px;<?php echo esc_attr( $adv_lock ); ?>">
							<input type="checkbox" class="wpsubs-toggle" data-subscrpt-field="delivery_sync" <?php disabled( $pro_locked ); ?> />
							<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
						</label>
					</div>
					<div data-subscrpt-delivery-day style="opacity:0.55;pointer-events:none;">
						<label style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Delivery day', 'subscription' ); ?><?php echo wp_kses_post( $pro_badge ); ?></label>
						<?php
						wpsubs_render_adv_select(
							array(
								'name'    => 'subscrpt_delivery_day',
								'value'   => '1',
								'options' => $weekday_options,
								'attrs'   => array(
									'data-subscrpt-field' => 'delivery_day',
									'style'               => 'width:100%;',
								),
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $is_installments ) : ?>
				<!-- Split Payment: Access ends (full width) → Access ends | Custom duration when "Custom" is picked (JS toggles the grid). -->
				<div data-subscrpt-access-grid style="display:grid;grid-template-columns:1fr;gap:16px;align-items:start;margin-bottom:0;">
					<div>
						<label style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Access ends', 'subscription' ); ?><?php echo wp_kses_post( $pro_badge ); ?><?php echo wp_kses_post( $hint( __( 'When the customer loses access after the payments finish.', 'subscription' ) ) ); ?></label>
						<?php
						wpsubs_render_adv_select(
							array(
								'name'    => 'subscrpt_access_ends',
								'value'   => 'lifetime',
								'options' => $access_options,
								'attrs'   => array(
									'data-subscrpt-field' => 'access_ends',
									'style'               => 'width:100%;' . $adv_lock,
								),
							)
						);
						?>
					</div>

					<div data-subscrpt-access-custom style="display:none;">
						<label style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Custom access duration', 'subscription' ); ?><?php echo wp_kses_post( $pro_badge ); ?><?php echo wp_kses_post( $hint( __( 'How long access should continue after the last payment is completed.', 'subscription' ) ) ); ?></label>
						<div style="<?php echo esc_attr( $pair_style ); ?>">
							<input type="number" class="wpsubs-input" value="1" min="1" style="flex:1 1 auto;min-width:0;" data-subscrpt-field="access_custom_value" aria-label="<?php esc_attr_e( 'Access length', 'subscription' ); ?>" <?php disabled( $pro_locked ); ?> />
							<?php
							wpsubs_render_adv_select(
								array(
									'name'    => 'subscrpt_access_custom_interval',
									'value'   => 'month',
									'options' => $interval_options,
									'attrs'   => array(
										'data-subscrpt-field' => 'access_custom_interval',
										'style' => 'flex:0 0 auto;' . $adv_lock,
									),
								)
							);
							?>
						</div>
					</div>
				</div>
			<?php endif; ?>

		</div>
		<div class="wpsubs-modal__footer">
			<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-close><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-term-submit><?php esc_html_e( 'Save Duration', 'subscription' ); ?></button>
		</div>
	</div>
</div>
