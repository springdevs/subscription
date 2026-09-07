<?php
/**
 * Create Plan Group modal. Free is Recurring-only; the other two types are
 * locked unless Subscription Pro is active.
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pro_active = function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated();

$types = array(
	'recurring'      => array(
		'label' => __( 'Recurring Payment', 'subscription' ),
		'badge' => __( 'Digital products', 'subscription' ),
		'icon'  => 'dashicons-update',
		'desc'  => __( 'Automatically charge recurring payments (best for virtual & downloadable products).', 'subscription' ),
		'free'  => true,
	),
	'subscribe_save' => array(
		'label' => __( 'Recurring Delivery', 'subscription' ),
		'badge' => __( 'Physical products', 'subscription' ),
		'icon'  => 'dashicons-cart',
		'desc'  => __( 'Charge and deliver physical products on a schedule.', 'subscription' ),
		'free'  => false,
	),
	'installments'   => array(
		'label' => __( 'Split Payment', 'subscription' ),
		'badge' => __( 'Installments', 'subscription' ),
		'icon'  => 'dashicons-money-alt',
		'desc'  => __( 'Split a price into a fixed number of payments.', 'subscription' ),
		'free'  => false,
	),
);
?>
<div class="wpsubs-modal" id="subscrpt-create-plan" hidden>
	<div class="wpsubs-modal__backdrop" data-wpsubs-modal-close></div>
	<div class="wpsubs-modal__dialog">
		<div class="wpsubs-modal__head" style="align-items:flex-start;">
			<div>
				<h2 class="wpsubs-modal__title" style="font-size:16px;line-height:1.3;"><?php esc_html_e( 'Create Plan', 'subscription' ); ?></h2>
				<p style="margin:5px 0 0;color:var(--wpsubs-text-muted);font-size:13px;line-height:1.5;font-weight:400;">
					<?php esc_html_e( 'A plan bundles the billing terms you attach to products. Name it, pick a type, then add billing terms.', 'subscription' ); ?>
				</p>
			</div>
			<button type="button" class="wpsubs-modal__close" data-wpsubs-modal-close aria-label="<?php esc_attr_e( 'Close', 'subscription' ); ?>">&times;</button>
		</div>
		<div class="wpsubs-modal__body">

			<label for="subscrpt-create-name" style="display:block;font-weight:600;margin:0 0 6px;"><?php esc_html_e( 'Name', 'subscription' ); ?></label>
			<input type="text" id="subscrpt-create-name" class="wpsubs-input" placeholder="<?php esc_attr_e( 'e.g. Premium Membership', 'subscription' ); ?>" autocomplete="off" />

			<p style="font-weight:600;margin:16px 0 8px;"><?php esc_html_e( 'Plan Type', 'subscription' ); ?></p>

			<div data-subscrpt-type-list style="display:flex;flex-direction:column;gap:10px;">
				<?php
				foreach ( $types as $key => $type_def ) :
					$locked   = ! $type_def['free'] && ! $pro_active;
					$selected = 'recurring' === $key;

					$style  = 'display:flex;gap:12px;align-items:flex-start;box-sizing:border-box;width:100%;padding:12px 14px;border:1px solid var(--wpsubs-border);border-radius:var(--wpsubs-radius);';
					$style .= $selected ? 'border-color:var(--wpsubs-brand);background:var(--wpsubs-brand-light);' : '';
					$style .= $locked ? 'opacity:0.6;cursor:not-allowed;' : 'cursor:pointer;';
					?>
					<div
						class="subscrpt-type-card<?php echo $selected ? ' is-selected' : ''; ?>"
						data-subscrpt-type="<?php echo esc_attr( $key ); ?>"
						data-subscrpt-type-label="<?php echo esc_attr( $type_def['label'] ); ?>"
						<?php echo $locked ? 'data-locked="1"' : 'role="button" tabindex="0"'; ?>
						style="<?php echo esc_attr( $style ); ?>"
					>
						<span class="dashicons <?php echo esc_attr( $type_def['icon'] ); ?>" style="flex:0 0 auto;color:<?php echo $selected ? 'var(--wpsubs-brand)' : 'var(--wpsubs-text-subtle)'; ?>;"></span>
						<span style="flex:1 1 auto;min-width:0;">
							<span style="display:block;font-weight:600;color:var(--wpsubs-text);">
								<?php echo esc_html( $type_def['label'] ); ?>
								<?php if ( ! empty( $type_def['badge'] ) ) : ?>
									<span class="wpsubs-badge wpsubs-badge--brand" style="margin-left:2px;"><?php echo esc_html( $type_def['badge'] ); ?></span>
								<?php endif; ?>
								<?php if ( $locked ) : ?>
									<span class="wpsubs-badge wpsubs-badge--pro"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
								<?php endif; ?>
							</span>
							<span style="display:block;color:var(--wpsubs-text-muted);font-size:13px;word-break:break-word;"><?php echo esc_html( $type_def['desc'] ); ?></span>
						</span>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( ! $pro_active ) : ?>
				<p style="color:var(--wpsubs-text-muted);font-size:12px;margin:12px 0 0;">
					<?php esc_html_e( 'Recurring Delivery and Split Payment plans are available in Subscription Pro.', 'subscription' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<div class="wpsubs-modal__footer">
			<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-close><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-create-plan><?php esc_html_e( 'Create Plan', 'subscription' ); ?></button>
		</div>
	</div>
</div>
