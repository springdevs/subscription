<?php
/**
 * Plan detail - Durations tab. Each term is its own card: icon + name and
 * a muted meta line (breakdown, trial, signup fee, expiry) + toggle, edit and delete actions.
 *
 * @var array $plan Plan (PlanPresenter shape).
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div style="padding-top:8px;">

	<?php if ( empty( $plan['terms'] ) ) : ?>
		<div class="wpsubs-empty">
			<div class="wpsubs-empty__icon">🗓️</div>
			<h3 class="wpsubs-empty__title"><?php esc_html_e( 'No durations yet', 'subscription' ); ?></h3>
			<p class="wpsubs-empty__desc"><?php esc_html_e( 'A duration sets how often the customer is charged, like every month or every year. Add at least one so this plan group can be sold.', 'subscription' ); ?></p>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" style="margin-top:20px;" data-wpsubs-modal-open="subscrpt-term-modal" data-subscrpt-add-term>
				<?php esc_html_e( 'Add your first duration', 'subscription' ); ?>
			</button>
		</div>
	<?php else : ?>
		<div data-subscrpt-browse data-per-page="10">
			<div style="display:flex;flex-direction:column;gap:10px;">
			<?php foreach ( $plan['terms'] as $selling_term ) : ?>
				<div class="wpsubs-table-card" data-term-id="<?php echo esc_attr( $selling_term['id'] ); ?>" data-subscrpt-browse-item data-name="<?php echo esc_attr( strtolower( $selling_term['name'] ) ); ?>" style="display:flex;align-items:center;gap:14px;padding:14px 16px;">
					<span style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:var(--wpsubs-radius);background:var(--wpsubs-brand-light);color:var(--wpsubs-brand);">
						<span class="dashicons dashicons-calendar-alt"></span>
					</span>
					<div style="flex:1 1 auto;min-width:0;">
						<span style="font-weight:600;font-size:13.5px;"><?php echo esc_html( $selling_term['name'] ); ?></span>
						<?php if ( 'draft' === $selling_term['status'] ) : ?>
							<span class="wpsubs-badge wpsubs-badge--draft" style="margin-left:6px;"><?php esc_html_e( 'Draft', 'subscription' ); ?></span>
						<?php endif; ?>
						<?php $subscrpt_meta_parts = array_merge( array( $selling_term['breakdown'] ), $selling_term['chips'] ); ?>
						<div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:4px;font-size:12px;color:var(--wpsubs-text-muted);">
							<span title="<?php esc_attr_e( 'Plan ID', 'subscription' ); ?>">#<?php echo esc_html( $selling_term['id'] ); ?></span>
							<span style="color:var(--wpsubs-text-subtle);">&middot;</span>
							<?php foreach ( $subscrpt_meta_parts as $subscrpt_i => $subscrpt_part ) : ?>
								<?php
								if ( $subscrpt_i > 0 ) :
									?>
									<span style="color:var(--wpsubs-text-subtle);">&middot;</span><?php endif; ?>
								<span><?php echo esc_html( $subscrpt_part ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
					<?php $subscrpt_is_active = 'draft' !== $selling_term['status']; ?>
					<div style="flex:0 0 auto;display:inline-flex;align-items:center;gap:10px;">
						<label
							title="<?php echo esc_attr( $subscrpt_is_active ? __( 'Set as Draft', 'subscription' ) : __( 'Set as Active', 'subscription' ) ); ?>"
							data-subscrpt-set-term-status="<?php echo $subscrpt_is_active ? 'draft' : 'active'; ?>"
							data-term-id="<?php echo esc_attr( $selling_term['id'] ); ?>"
							style="display:inline-flex;align-items:center;cursor:pointer;"
						>
							<input type="checkbox" class="wpsubs-toggle" <?php checked( $subscrpt_is_active ); ?> aria-label="<?php esc_attr_e( 'Toggle active status', 'subscription' ); ?>" />
							<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
						</label>
						<span style="display:inline-flex;align-items:center;gap:2px;">
							<button type="button" class="wpsubs-icon-action" data-subscrpt-edit-term="<?php echo esc_attr( $selling_term['id'] ); ?>" title="<?php esc_attr_e( 'Edit', 'subscription' ); ?>" aria-label="<?php esc_attr_e( 'Edit', 'subscription' ); ?>">
								<span class="dashicons dashicons-edit"></span>
							</button>
							<button type="button" class="wpsubs-icon-action wpsubs-icon-action--danger" data-subscrpt-delete-term="<?php echo esc_attr( $selling_term['id'] ); ?>" title="<?php esc_attr_e( 'Delete', 'subscription' ); ?>" aria-label="<?php esc_attr_e( 'Delete', 'subscription' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</span>
					</div>
				</div>
			<?php endforeach; ?>
			</div>

			<div data-subscrpt-browse-pager style="margin-top:14px;"></div>
		</div>
	<?php endif; ?>

</div>
