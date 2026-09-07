<?php
/**
 * Manage Products modal (Pro). Syncs the plan group's products to the picker:
 * search and multi-select; on save, checked products/variations are attached to
 * every duration and any previously-attached one left unchecked is removed.
 *
 * @var array $plan Plan (provides id).
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpsubs-modal" id="subscrpt-add-product" hidden data-subscrpt-add-product data-group-id="<?php echo esc_attr( $plan['id'] ); ?>">
	<div class="wpsubs-modal__backdrop" data-wpsubs-modal-close></div>
	<div class="wpsubs-modal__dialog" style="width:min(720px, calc(100vw - 40px));">
		<div class="wpsubs-modal__head">
			<h2 class="wpsubs-modal__title"><?php esc_html_e( 'Manage Products', 'subscription' ); ?></h2>
			<button type="button" class="wpsubs-modal__close" data-wpsubs-modal-close aria-label="<?php esc_attr_e( 'Close', 'subscription' ); ?>">&times;</button>
		</div>
		<div class="wpsubs-modal__body">
			<div class="wpsubs-input-wrap wpsubs-input-wrap--icon-l" style="margin-bottom:12px;">
				<span class="wpsubs-input-icon dashicons dashicons-search"></span>
				<input type="search" class="wpsubs-input" placeholder="<?php esc_attr_e( 'Search products…', 'subscription' ); ?>" data-subscrpt-product-search />
			</div>
			<ul data-subscrpt-product-list style="list-style:none;margin:0;padding:0;max-height:min(560px, 60vh);overflow-y:auto;display:flex;flex-direction:column;gap:2px;">
				<li style="padding:10px 4px;color:var(--wpsubs-text-subtle);font-size:13px;"><?php esc_html_e( 'Loading…', 'subscription' ); ?></li>
			</ul>
		</div>
		<div class="wpsubs-modal__footer">
			<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-close><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-add-product-submit><?php esc_html_e( 'Save Changes', 'subscription' ); ?></button>
		</div>
	</div>
</div>
