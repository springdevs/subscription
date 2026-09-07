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
		<div style="margin-bottom:4px;">
			<div style="display:flex;align-items:center;gap:10px;margin:0 0 8px;">
				<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0;line-height:1.2;"><?php echo esc_html( $plan['name'] ); ?></h1>
			</div>
			<div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.5;">
				<?php echo wp_kses_post( implode( $sep, $meta ) ); ?>
			</div>
		</div>

		<div class="wpsubs-tabs" data-tabs-query="tab">
			<div class="wpsubs-tabs__list" role="tablist" style="align-items:end;border-bottom:none;margin-top:-24px;margin-bottom:8px;">
				<span aria-hidden="true" style="flex:1 1 auto;border-top:1px dashed #d0d3d7;margin-right:8px;"></span>
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
