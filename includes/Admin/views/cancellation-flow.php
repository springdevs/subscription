<?php
/**
 * Cancellation Flow view.
 *
 * Intro card, tab strip, then a two-column body: the active tab on the left and
 * the flow-wide options on the right.
 *
 * The form posts to options.php under the page's own settings group, so a save
 * here touches these options and nothing else - see CancellationFlow::OPTION_GROUP.
 *
 * @var string $active_tab Active tab key.
 * @var array  $tabs       Tab definitions.
 * @var string $page_url   Base page URL.
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SpringDevs\Subscription\Admin\CancellationFlow;
use SpringDevs\Subscription\Admin\SettingsHelper;

$subscrpt_pro_active = subscrpt_pro_activated();
?>
<div class="wp-subscription-admin-content list-page">

	<div style="display:flex;align-items:center;gap:10px;margin:0 0 12px;">
		<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0;line-height:1.2;">
			<?php esc_html_e( 'Cancellation Flow', 'subscription' ); ?>
		</h1>
		<?php if ( ! $subscrpt_pro_active ) : ?>
			<?php echo wp_kses_post( SettingsHelper::pro_badge_html() ); ?>
		<?php endif; ?>
	</div>

	<div class="wpsubs-table-card" style="padding:18px 20px;margin-bottom:18px;background:var(--wpsubs-surface-muted,#f9fafb);">
		<ul style="margin:0 0 14px;padding-left:18px;color:var(--wpsubs-text-muted);font-size:13px;line-height:1.9;">
			<li><?php esc_html_e( 'Ask customers why they are leaving, and record it for churn tracking', 'subscription' ); ?></li>
			<li><?php esc_html_e( 'Choose the reasons they pick from, and when a cancellation takes effect', 'subscription' ); ?></li>
		</ul>
		<div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
			<a href="https://wpsubscription.co/docs/" target="_blank" rel="noopener" class="wpsubs-btn wpsubs-btn--muted wpsubs-btn--sm">
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
				<?php esc_html_e( 'View All Documentation', 'subscription' ); ?>
			</a>
			<a href="https://wpsubscription.co/support/" target="_blank" rel="noopener" class="wpsubs-btn wpsubs-btn--muted wpsubs-btn--sm">
				<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
				<?php esc_html_e( 'Get Support', 'subscription' ); ?>
			</a>
		</div>
	</div>

	<div class="wpsubs-tabs__list" role="tablist" style="margin-bottom:16px;">
		<?php foreach ( $tabs as $subscrpt_tab_key => $subscrpt_tab ) : ?>
			<a
				class="wpsubs-tabs__tab"
				role="tab"
				href="<?php echo esc_url( add_query_arg( 'tab', $subscrpt_tab_key, $page_url ) ); ?>"
				aria-selected="<?php echo $subscrpt_tab_key === $active_tab ? 'true' : 'false'; ?>"
			><?php echo esc_html( $subscrpt_tab['label'] ); ?></a>
		<?php endforeach; ?>
	</div>

	<form method="post" action="options.php" class="subscrpt-settings">
		<?php settings_fields( CancellationFlow::OPTION_GROUP ); ?>

		<div class="subscrpt-flow__layout">

			<div>
				<?php if ( 'reasons' === $active_tab ) : ?>
					<div class="wpsubs-table-card subscrpt-flow__panel subscrpt-flow__panel--reasons">
						<?php
						// The saved list, in the order customers see it. Editing happens in
						// the modal; this reflects what is stored, so it catches up on save
						// rather than while the modal is open.
						$subscrpt_reasons = \SpringDevs\Subscription\Illuminate\Cancellation::get_reasons();
						?>
						<?php if ( empty( $subscrpt_reasons ) ) : ?>
							<p class="subscrpt-flow__empty">
								<?php esc_html_e( 'No reasons yet. Add one with Manage reasons.', 'subscription' ); ?>
							</p>
						<?php else : ?>
							<ol class="subscrpt-flow__reasons">
								<?php foreach ( $subscrpt_reasons as $subscrpt_reason ) : ?>
									<li><?php echo esc_html( $subscrpt_reason['label'] ?? '' ); ?></li>
								<?php endforeach; ?>
							</ol>
						<?php endif; ?>
						<?php
						$subscrpt_reasons_field          = CancellationFlow::reasons_field();
						$subscrpt_reasons_field['title'] = '';
						SettingsHelper::render_settings_field( 'editlist', $subscrpt_reasons_field );
						?>
					</div>
				<?php endif; ?>

				<?php if ( 'offers' === $active_tab ) : ?>
					<div class="wpsubs-table-card subscrpt-flow__panel">
						<?php
						foreach ( CancellationFlow::offer_fields() as $subscrpt_offer_field ) {
							SettingsHelper::render_settings_field( $subscrpt_offer_field['type'], $subscrpt_offer_field['field_data'] );
						}
						?>
						<p class="subscrpt-flow__note">
							<?php esc_html_e( 'Accepting the offer issues a single-use WooCommerce coupon locked to that customer, and keeps the subscription.', 'subscription' ); ?>
						</p>
					</div>
				<?php endif; ?>
			</div>

			<aside>
				<div class="wpsubs-table-card subscrpt-flow__panel subscrpt-flow__options">
					<?php
					foreach ( CancellationFlow::sidebar_fields() as $subscrpt_field ) {
						SettingsHelper::render_settings_field( $subscrpt_field['type'], $subscrpt_field['field_data'] );
					}
					?>
				</div>
			</aside>

		</div>

		<div style="margin-top:18px;">
			<button type="submit" class="wpsubs-btn wpsubs-btn--primary">
				<?php esc_html_e( 'Save Changes', 'subscription' ); ?>
			</button>
		</div>
	</form>

</div>
