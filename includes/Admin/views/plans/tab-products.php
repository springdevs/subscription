<?php
/**
 * Plan detail - Products tab.
 *
 * Lists the products attached to this plan group. In free this is read-only:
 * products are connected from their own editor. With Pro active, an "Add
 * Products" bulk picker is available here.
 *
 * @var array $plan Plan (PlanPresenter shape).
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pro_active = function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated();
// Products can only be attached once the plan group has at least one term.
$has_terms = ! empty( $plan['terms'] );
?>
<div style="padding-top:8px;">

	<?php if ( empty( $plan['products'] ) ) : ?>
		<div class="wpsubs-empty">
			<div class="wpsubs-empty__icon">📦</div>
			<h3 class="wpsubs-empty__title"><?php esc_html_e( 'No products connected', 'subscription' ); ?></h3>
			<?php if ( $pro_active && ! $has_terms ) : ?>
				<p class="wpsubs-empty__desc"><?php esc_html_e( 'Add at least one plan on the Plans tab before attaching products.', 'subscription' ); ?></p>
			<?php elseif ( $pro_active ) : ?>
				<p class="wpsubs-empty__desc"><?php esc_html_e( 'Add products to this plan group and set their prices here, or connect a product from its own Subscription tab.', 'subscription' ); ?></p>
				<button type="button" class="wpsubs-btn wpsubs-btn--primary" style="margin-top:20px;" data-wpsubs-modal-open="subscrpt-add-product">
					<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:1;"></span>
					<?php esc_html_e( 'Add Products', 'subscription' ); ?>
				</button>
			<?php else : ?>
				<p class="wpsubs-empty__desc"><?php esc_html_e( 'Products are connected from their own editor: open a product, go to its Subscription tab, pick this plan group, and set the price. Bulk-add from here is available with WPSubscription Pro.', 'subscription' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="wpsubs-btn wpsubs-btn--primary" style="margin-top:20px;">
					<?php esc_html_e( 'Go to Products', 'subscription' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php else : ?>

		<?php
		/**
		 * Render the price table for a set of relation rows (Selling Plan /
		 * Regular / Offer / Status). Each editable cell has a read view + a
		 * hidden input revealed by the card's Edit/Save buttons (JS).
		 *
		 * When $one_time is provided, a "One-time purchase" row is appended: its
		 * price is the product's/variation's native WooCommerce price and the
		 * card's single Save persists both the plan relations and the one-time
		 * native price. Pro-gated (read-only + Pro badge when Pro is inactive).
		 *
		 * @param array      $rows     Price rows (term / regular / offer / …).
		 * @param array|null $one_time One-time data: enabled, regular, offer.
		 */
		$subscrpt_render_rows = function ( $rows, $one_time = null ) use ( $pro_active ) {
			?>
			<table class="wpsubs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Selling Plan', 'subscription' ); ?></th>
						<th>
							<?php esc_html_e( 'Regular Price', 'subscription' ); ?>
							<?php echo wp_kses_post( wpsubs_render_hint( __( 'The recurring price charged each billing cycle.', 'subscription' ) ) ); ?>
						</th>
						<th>
							<?php esc_html_e( 'Offer Price', 'subscription' ); ?>
							<?php echo wp_kses_post( wpsubs_render_hint( __( 'A discounted recurring price shown in place of the regular price. Leave empty for no discount.', 'subscription' ) ) ); ?>
						</th>
						<th><?php esc_html_e( 'Status', 'subscription' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr data-subscrpt-relation="<?php echo esc_attr( $row['relation_id'] ); ?>" data-plan-id="<?php echo esc_attr( isset( $row['plan_id'] ) ? $row['plan_id'] : '' ); ?>">

							<td><?php echo esc_html( $row['term'] ); ?></td>
							<td>
								<span class="subscrpt-pe-view"><?php echo esc_html( $row['regular'] ); ?></span>
								<?php if ( $pro_active ) : ?>
									<input type="number" min="0" step="0.01" class="wpsubs-input subscrpt-pe-edit" data-field="regular_price" value="<?php echo esc_attr( $row['regular_raw'] ); ?>" placeholder="0.00" style="display:none;max-width:110px;" />
								<?php endif; ?>
							</td>
							<td>
								<span class="subscrpt-pe-view"><?php echo ! empty( $row['has_offer'] ) ? esc_html( $row['offer'] ) : '<span style="color:var(--wpsubs-text-subtle);">&mdash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static dash markup / escaped price. ?></span>
								<?php if ( $pro_active ) : ?>
									<input type="number" min="0" step="0.01" class="wpsubs-input subscrpt-pe-edit" data-field="sale_price" value="<?php echo esc_attr( $row['offer_raw'] ); ?>" placeholder="0.00" style="display:none;max-width:110px;" />
								<?php endif; ?>
							</td>
							<td>
								<span class="subscrpt-pe-view">
									<?php if ( ! empty( $row['exclude'] ) ) : ?>
										<span class="wpsubs-badge wpsubs-badge--draft"><?php esc_html_e( 'Disabled', 'subscription' ); ?></span>
									<?php else : ?>
										<span class="wpsubs-badge wpsubs-badge--active"><?php esc_html_e( 'Enabled', 'subscription' ); ?></span>
									<?php endif; ?>
								</span>
								<?php if ( $pro_active ) : ?>
									<label class="wpsubs-settings-toggle-label subscrpt-pe-edit" style="display:none;align-items:center;" title="<?php esc_attr_e( 'Enable this plan for the product', 'subscription' ); ?>">
										<input type="checkbox" class="wpsubs-toggle" data-field="enabled" <?php checked( empty( $row['exclude'] ) ); ?> />
										<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
									</label>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>

					<?php
					if ( is_array( $one_time ) ) :
						$subscrpt_ot_on   = $pro_active && ! empty( $one_time['enabled'] );
						$subscrpt_ot_reg  = (string) $one_time['regular'];
						$subscrpt_ot_off  = (string) $one_time['offer'];
						$subscrpt_ot_rdsp = '' !== $subscrpt_ot_reg ? \SpringDevs\Subscription\Admin\PlanPresenter::money( (float) $subscrpt_ot_reg ) : '-';
						$subscrpt_ot_odsp = '' !== $subscrpt_ot_off ? \SpringDevs\Subscription\Admin\PlanPresenter::money( (float) $subscrpt_ot_off ) : '-';
						?>
						<tr data-subscrpt-onetime-row style="border-top:2px solid var(--wpsubs-border,#e5e7eb);background:var(--wpsubs-surface-muted,#f6f7f7);">
							<td>
								<span style="display:inline-flex;align-items:center;gap:6px;">
									<span class="dashicons dashicons-cart" style="flex:0 0 auto;font-size:15px;width:15px;height:15px;color:var(--wpsubs-text-subtle);"></span>
									<?php esc_html_e( 'One-time purchase', 'subscription' ); ?>
									<?php echo wp_kses_post( wpsubs_render_hint( __( 'A single, non-recurring purchase at the product’s regular WooCommerce price.', 'subscription' ) ) ); ?>
									<?php if ( ! $pro_active ) : ?>
										<span class="wpsubs-badge wpsubs-badge--pro" title="<?php esc_attr_e( 'WPSubscription Pro required', 'subscription' ); ?>"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td>
								<span class="subscrpt-pe-view"><?php echo esc_html( $subscrpt_ot_rdsp ); ?></span>
								<?php if ( $pro_active ) : ?>
									<input type="number" min="0" step="0.01" class="wpsubs-input subscrpt-pe-edit" data-ot-field="price" value="<?php echo esc_attr( $subscrpt_ot_reg ); ?>" placeholder="0.00" style="display:none;max-width:110px;" />
								<?php endif; ?>
							</td>
							<td>
								<span class="subscrpt-pe-view"><?php echo esc_html( $subscrpt_ot_odsp ); ?></span>
								<?php if ( $pro_active ) : ?>
									<input type="number" min="0" step="0.01" class="wpsubs-input subscrpt-pe-edit" data-ot-field="offer" value="<?php echo esc_attr( $subscrpt_ot_off ); ?>" placeholder="<?php esc_attr_e( 'No offer', 'subscription' ); ?>" style="display:none;max-width:110px;" />
								<?php endif; ?>
							</td>
							<td>
								<span class="subscrpt-pe-view">
									<?php if ( $subscrpt_ot_on ) : ?>
										<span class="wpsubs-badge wpsubs-badge--active"><?php esc_html_e( 'Enabled', 'subscription' ); ?></span>
									<?php else : ?>
										<span class="wpsubs-badge wpsubs-badge--draft"><?php esc_html_e( 'Disabled', 'subscription' ); ?></span>
									<?php endif; ?>
								</span>
								<?php if ( $pro_active ) : ?>
									<label class="wpsubs-settings-toggle-label subscrpt-pe-edit" style="display:none;align-items:center;" title="<?php esc_attr_e( 'Offer this product for one-time purchase', 'subscription' ); ?>">
										<input type="checkbox" class="wpsubs-toggle" data-subscrpt-onetime-enable <?php checked( $subscrpt_ot_on ); ?> />
										<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
									</label>
								<?php endif; ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<?php
		};

		/**
		 * Render the price-card header controls (Pro only): Edit / Cancel / Save.
		 * JS toggles the card into edit mode and saves the plan prices via REST.
		 */
		$subscrpt_price_actions = function () use ( $pro_active ) {
			if ( ! $pro_active ) {
				return;
			}
			?>
			<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-edit-prices>
				<span class="dashicons dashicons-edit" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
				<?php esc_html_e( 'Edit prices', 'subscription' ); ?>
			</button>
			<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-cancel-prices style="display:none;"><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" data-subscrpt-save-prices style="display:none;"><?php esc_html_e( 'Save', 'subscription' ); ?></button>
			<?php
		};

		/**
		 * Render a simple product's one-time purchase card (its own toggle + Save).
		 * One-time is product-specific: the price is the product’s native
		 * WooCommerce price (regular = one-time, sale = offer). Pro-gated: disabled
		 * with a Pro badge when Pro is inactive. Variable products use the
		 * per-variation one-time row inside render_rows().
		 *
		 * @param array $product Product entry (PlanPresenter shape).
		 */
		$subscrpt_render_onetime = function ( $product ) use ( $pro_active ) {
			$subscrpt_on        = $pro_active && ! empty( $product['one_time_on'] );
			$subscrpt_show_body = $subscrpt_on || ! $pro_active;
			?>
			<div style="width:90%;border-top:1px dashed var(--wpsubs-border-strong,#d1d5db);margin:16px auto 0;"></div>
			<div data-subscrpt-onetime-card data-product-id="<?php echo esc_attr( $product['id'] ); ?>" style="border:1px solid var(--wpsubs-border,#e5e7eb);border-radius:8px;background:var(--wpsubs-surface,#fff);margin-top:14px;">
				<div style="display:flex;align-items:center;gap:10px;padding:11px 14px;">
					<span class="dashicons dashicons-cart" style="flex:0 0 auto;font-size:16px;width:16px;height:16px;color:var(--wpsubs-text-subtle);"></span>
					<strong style="font-size:13px;color:var(--wpsubs-text);"><?php esc_html_e( 'One-time purchase', 'subscription' ); ?></strong>
					<?php echo wp_kses_post( wpsubs_render_hint( __( 'This is the product’s regular WooCommerce price, charged when a customer buys it once instead of subscribing.', 'subscription' ) ) ); ?>
					<?php if ( ! $pro_active ) : ?>
						<span class="wpsubs-badge wpsubs-badge--pro" title="<?php esc_attr_e( 'WPSubscription Pro required', 'subscription' ); ?>"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
					<?php endif; ?>
					<span class="wpsubs-toolbar__spacer"></span>
					<label class="wpsubs-settings-toggle-label" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--wpsubs-text-muted);<?php echo $pro_active ? 'cursor:pointer;' : 'opacity:0.6;'; ?>">
						<input type="checkbox" class="wpsubs-toggle" data-subscrpt-onetime-enable <?php checked( $subscrpt_on ); ?> <?php disabled( ! $pro_active ); ?> />
						<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
						<span><?php esc_html_e( 'Allow one-time purchase', 'subscription' ); ?></span>
					</label>
					<button type="button" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" data-subscrpt-onetime-save <?php disabled( ! $pro_active ); ?>><?php esc_html_e( 'Save', 'subscription' ); ?></button>
				</div>
				<div data-subscrpt-onetime-body style="padding:12px 14px;border-top:1px solid var(--wpsubs-border,#e5e7eb);<?php echo $subscrpt_show_body ? '' : 'display:none;'; ?>">
					<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;max-width:460px;">
						<label style="display:flex;flex-direction:column;gap:5px;font-size:12px;color:var(--wpsubs-text-muted);">
							<?php esc_html_e( 'Regular Price', 'subscription' ); ?>
							<input type="number" min="0" step="0.01" class="wpsubs-input" data-ot-field="price" value="<?php echo esc_attr( $product['ot_regular'] ); ?>" placeholder="0.00" style="width:100%;box-sizing:border-box;" <?php disabled( ! $pro_active ); ?> />
						</label>
						<label style="display:flex;flex-direction:column;gap:5px;font-size:12px;color:var(--wpsubs-text-muted);">
							<?php esc_html_e( 'Offer Price', 'subscription' ); ?>
							<input type="number" min="0" step="0.01" class="wpsubs-input" data-ot-field="offer" value="<?php echo esc_attr( $product['ot_offer'] ); ?>" placeholder="<?php esc_attr_e( 'No offer', 'subscription' ); ?>" style="width:100%;box-sizing:border-box;" <?php disabled( ! $pro_active ); ?> />
						</label>
					</div>
				</div>
			</div>
			<?php
		};
	?>

		<div data-subscrpt-browse data-per-page="10">
			<div class="wpsubs-toolbar" style="margin-bottom:14px;">
				<div class="wpsubs-search">
					<div class="wpsubs-input-wrap wpsubs-input-wrap--icon-l">
						<svg class="wpsubs-input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
						<input type="search" class="wpsubs-input" placeholder="<?php esc_attr_e( 'Search products...', 'subscription' ); ?>" data-subscrpt-browse-search />
					</div>
				</div>

				<?php
				wpsubs_render_per_page_select(
					array(
						'name'  => 'subscrpt_products_per_page',
						'attrs' => array( 'data-subscrpt-browse-perpage' => '1' ),
					)
				);
				?>

				<div class="wpsubs-toolbar__spacer"></div>

				<?php if ( $pro_active && $has_terms ) : ?>
					<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-wpsubs-modal-open="subscrpt-add-product">
						<span class="dashicons dashicons-edit" style="font-size:16px;width:16px;height:16px;line-height:1;"></span>
						<?php esc_html_e( 'Manage Products', 'subscription' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="wpsubs-accordion" data-multi="1" data-subscrpt-product-list>
			<?php foreach ( $plan['products'] as $product ) : ?>
				<?php $subscrpt_panel_id = 'subscrpt-prod-' . (int) $product['id']; ?>
				<div class="wpsubs-accordion__item" data-subscrpt-browse-item data-name="<?php echo esc_attr( strtolower( $product['name'] ) ); ?>" data-pid="<?php echo esc_attr( $product['id'] ); ?>">
					<div style="display:flex;align-items:stretch;background:var(--wpsubs-surface-muted,#f9fafb);">
						<button type="button" class="wpsubs-accordion__header wpsubs-accordion__header--chevron-start" style="flex:1 1 auto;min-width:0;background:transparent;" aria-controls="<?php echo esc_attr( $subscrpt_panel_id ); ?>" aria-expanded="false">
							<span style="display:flex;align-items:center;gap:10px;min-width:0;">
								<span style="flex:0 0 auto;width:44px;height:44px;border-radius:6px;background:var(--wpsubs-surface,#fff);border:1px solid var(--wpsubs-border,#e5e7eb);overflow:hidden;display:flex;align-items:center;justify-content:center;">
									<?php if ( ! empty( $product['image'] ) ) : ?>
										<img src="<?php echo esc_url( $product['image'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;" />
									<?php else : ?>
										<span class="dashicons dashicons-format-image" style="font-size:22px;width:22px;height:22px;color:var(--wpsubs-text-subtle);"></span>
									<?php endif; ?>
								</span>
								<span style="display:flex;flex-direction:column;min-width:0;line-height:1.35;">
								<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $product['name'] ); ?></span>
								<span style="font-size:12px;font-weight:400;color:var(--wpsubs-text-muted);white-space:nowrap;">
									<?php
									/* translators: %d: product ID. */
									$subscrpt_desc = sprintf( __( 'ID: %d', 'subscription' ), (int) $product['id'] );
									if ( ! empty( $product['is_variable'] ) ) {
										$subscrpt_var_count = count( $product['variations'] );
										/* translators: %d: number of variations. */
										$subscrpt_desc .= ' &middot; ' . sprintf( _n( '%d variation', '%d variations', $subscrpt_var_count, 'subscription' ), $subscrpt_var_count );
									}
									echo wp_kses_post( $subscrpt_desc );
									?>
								</span>
								</span>
							</span>
						</button>
						<div style="display:flex;align-items:center;gap:8px;padding:0 12px 0 4px;">
							<?php if ( ! empty( $product['edit_url'] ) ) : ?>
								<a href="<?php echo esc_url( $product['edit_url'] ); ?>" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" target="_blank" rel="noopener">
									<?php esc_html_e( 'Edit product', 'subscription' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $pro_active ) : ?>
								<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm wpsubs-btn--danger" data-subscrpt-remove-product="<?php echo esc_attr( $product['id'] ); ?>">
									<?php esc_html_e( 'Remove', 'subscription' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>

					<div class="wpsubs-accordion__panel" id="<?php echo esc_attr( $subscrpt_panel_id ); ?>" style="padding:14px 16px;background:var(--wpsubs-surface-muted,#f9fafb);" hidden>
						<?php if ( ! empty( $product['is_variable'] ) ) : ?>
							<div style="display:flex;flex-direction:column;gap:14px;">
								<?php foreach ( $product['variations'] as $variation ) : ?>
									<div data-subscrpt-price-card data-product-id="<?php echo esc_attr( $product['id'] ); ?>" data-variation-id="<?php echo esc_attr( $variation['vid'] ); ?>" style="border:1px solid var(--wpsubs-border,#e5e7eb);border-radius:8px;background:var(--wpsubs-surface,#fff);">
										<div style="display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid var(--wpsubs-border,#e5e7eb);">
											<span class="dashicons dashicons-image-filter" style="flex:0 0 auto;font-size:16px;width:16px;height:16px;color:var(--wpsubs-text-subtle);"></span>
											<strong style="font-size:13px;color:var(--wpsubs-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $variation['name'] ); ?></strong>
											<span class="wpsubs-toolbar__spacer"></span>
											<?php $subscrpt_price_actions(); ?>
											<?php if ( $pro_active ) : ?>
												<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm wpsubs-btn--danger" data-subscrpt-remove-variation data-oid="<?php echo esc_attr( $product['id'] ); ?>" data-vid="<?php echo esc_attr( $variation['vid'] ); ?>">
													<?php esc_html_e( 'Remove', 'subscription' ); ?>
												</button>
											<?php endif; ?>
										</div>
										<?php
										$subscrpt_render_rows(
											$variation['rows'],
											array(
												'enabled' => ! empty( $variation['one_time_on'] ),
												'regular' => $variation['ot_regular'],
												'offer'   => $variation['ot_offer'],
											)
										);
										?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div data-subscrpt-price-card data-product-id="<?php echo esc_attr( $product['id'] ); ?>" style="border:1px solid var(--wpsubs-border,#e5e7eb);border-radius:8px;background:var(--wpsubs-surface,#fff);">
								<?php if ( $pro_active ) : ?>
									<div style="display:flex;align-items:center;gap:8px;padding:11px 14px;border-bottom:1px solid var(--wpsubs-border,#e5e7eb);">
										<strong style="font-size:13px;color:var(--wpsubs-text);"><?php esc_html_e( 'Pricing', 'subscription' ); ?></strong>
										<span class="wpsubs-toolbar__spacer"></span>
										<?php $subscrpt_price_actions(); ?>
									</div>
								<?php endif; ?>
								<?php $subscrpt_render_rows( $product['rows'] ); ?>
							</div>
							<?php $subscrpt_render_onetime( $product ); ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
			</div>

			<p data-subscrpt-browse-empty style="display:none;padding:20px 4px;color:var(--wpsubs-text-subtle);font-size:13px;text-align:center;">
				<?php esc_html_e( 'No products match your search.', 'subscription' ); ?>
			</p>

			<div data-subscrpt-browse-pager style="margin-top:14px;"></div>
		</div>

	<?php endif; ?>

</div>

<?php
if ( $pro_active ) {
	require __DIR__ . '/modal-add-product.php';
}
