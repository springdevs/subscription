<?php
/**
 * Plan detail view. Uses the shared WPSubsTabs component (client-side tabs -
 * no page reload): Durations | Products (read-only).
 *
 * @var array  $plan     Plan (PlanPresenter shape).
 * @var string $list_url Base list URL.
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SpringDevs\Subscription\Admin\Plans;
?>
<div class="wp-subscription-admin-content list-page">
	<div data-plan-type="<?php echo esc_attr( $plan['type'] ); ?>" data-plan-id="<?php echo esc_attr( $plan['id'] ); ?>">

		<!-- Page header (mirrors the subscription details head) -->
		<?php
		$term_count    = count( $plan['terms'] );
		$product_count = count( $plan['products'] );
		$sep           = '<span style="color:var(--wpsubs-text-subtle);">&middot;</span>';

		$meta   = array();
		$meta[] = '<span class="wpsubs-badge wpsubs-badge--neutral">' . esc_html( Plans::type_label( $plan['type'] ) ) . '</span>';
		if ( 'draft' === $plan['status'] ) {
			$meta[] = '<span class="wpsubs-badge wpsubs-badge--draft">' . esc_html__( 'Draft', 'subscription' ) . '</span>';
		}
		/* translators: %d: number of durations. */
		$meta[] = esc_html( sprintf( _n( '%d duration', '%d durations', $term_count, 'subscription' ), $term_count ) );
		/* translators: %d: number of connected products. */
		$meta[] = esc_html( sprintf( _n( '%d product', '%d products', $product_count, 'subscription' ), $product_count ) );
		?>
		<div data-subscrpt-plan-notice aria-live="polite"></div>

		<?php
		/*
		 * Two columns: the title block on the left, the tab-scoped actions on
		 * the right. The actions belong on the title's line rather than on the
		 * tab rule, so the page has a single header row to read across.
		 */
		?>
		<div style="display:flex;align-items:flex-start;gap:16px;margin-bottom:4px;">
			<div style="flex:1 1 auto;min-width:0;">
				<div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:0 0 8px;" data-subscrpt-rename>
					<h1 data-subscrpt-rename-display style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0;line-height:1.2;"><?php echo esc_html( $plan['name'] ); ?></h1>
					<button type="button" class="wpsubs-icon-action" data-subscrpt-rename-open title="<?php esc_attr_e( 'Rename plan', 'subscription' ); ?>" aria-label="<?php esc_attr_e( 'Rename plan', 'subscription' ); ?>">
						<span class="dashicons dashicons-edit"></span>
					</button>
					<?php // Buttons match the input's 36px height — no --sm here. ?>
					<span data-subscrpt-rename-form style="display:none;align-items:center;gap:8px;">
						<input type="text" class="wpsubs-input" data-subscrpt-rename-input value="<?php echo esc_attr( $plan['name'] ); ?>" aria-label="<?php esc_attr_e( 'Plan name', 'subscription' ); ?>" style="min-width:260px;" />
						<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-rename-save><?php esc_html_e( 'Save', 'subscription' ); ?></button>
						<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-subscrpt-rename-cancel><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
					</span>
				</div>
				<div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.5;">
					<?php echo wp_kses_post( implode( $sep, $meta ) ); ?>
				</div>
			</div>

			<?php // One slot, one button: each tab's primary action, swapped by plans.js. ?>
			<div style="flex:0 0 auto;display:flex;align-items:center;gap:8px;">
				<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-wpsubs-modal-open="subscrpt-term-modal" data-subscrpt-add-term data-subscrpt-tab-action="subscrpt-tab-selling">
					<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:1;"></span>
					<?php esc_html_e( 'Add Duration', 'subscription' ); ?>
				</button>
				<?php // Nothing to manage until a product is attached — the empty state invites the first one. ?>
				<?php if ( ! empty( $plan['terms'] ) && ! empty( $plan['products'] ) ) : ?>
					<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-wpsubs-modal-open="subscrpt-add-product" data-subscrpt-tab-action="subscrpt-tab-products">
						<span class="dashicons dashicons-edit" style="font-size:16px;width:16px;height:16px;line-height:1;"></span>
						<?php esc_html_e( 'Manage Products', 'subscription' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<div class="wpsubs-tabs" data-tabs-query="tab">
			<div class="wpsubs-tabs__list" role="tablist">
				<button class="wpsubs-tabs__tab" role="tab" id="subscrpt-tab-selling" data-tab-key="plans" aria-controls="subscrpt-panel-selling" aria-selected="true">
					<?php esc_html_e( 'Durations', 'subscription' ); ?>
				</button>
				<button class="wpsubs-tabs__tab" role="tab" id="subscrpt-tab-products" data-tab-key="products" aria-controls="subscrpt-panel-products" aria-selected="false">
					<?php esc_html_e( 'Products', 'subscription' ); ?>
				</button>
			</div>

			<div class="wpsubs-tab-panel" role="tabpanel" id="subscrpt-panel-selling" aria-labelledby="subscrpt-tab-selling">
				<?php require __DIR__ . '/tab-selling-plans.php'; ?>
			</div>

			<div class="wpsubs-tab-panel" role="tabpanel" id="subscrpt-panel-products" aria-labelledby="subscrpt-tab-products" hidden>
				<?php require __DIR__ . '/tab-products.php'; ?>
			</div>
		</div>

	</div>
</div>

<?php require __DIR__ . '/modal-term.php'; ?>
