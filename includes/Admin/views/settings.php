<?php
/**
 * Subscription settings admin view.
 *
 * One form, every panel rendered, one panel visible. The form posts to
 * `options.php`, which saves whatever it is given — so a panel that is not
 * rendered is a panel whose settings do not save. Hiding is therefore CSS, not
 * a conditional, and every one of the fields below submits on every save
 * whichever tab happens to be open.
 *
 * @package SpringDevs\Subscription\Admin
 *
 * @var array  $settings_fields Grouped and sorted settings fields.
 * @var string $active_tab      Group key of the panel to open on.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SpringDevs\Subscription\Admin\SettingsHelper;

wp_enqueue_style( 'wp-subscription-admin-settings', SUBSCRPT_ASSETS . '/css/admin-settings.css', [], SUBSCRPT_VERSION );
wp_enqueue_script( 'wp-subscription-admin-settings', SUBSCRPT_ASSETS . '/js/admin-settings.js', [ 'jquery' ], SUBSCRPT_VERSION, true );

$subscrpt_settings_base = admin_url( 'admin.php?page=wp-subscription-settings' );
?>
<div class="wp-subscription-admin-content list-page">

	<form method="post" action="options.php" class="subscrpt-settings" data-subscrpt-settings>
		<?php settings_fields( 'wp_subscription_settings' ); ?>
		<?php do_settings_sections( 'wp_subscription_settings' ); ?>

		<div class="subscrpt-settings__layout">

			<aside class="subscrpt-settings__sidebar">
				<nav class="subscrpt-settings__nav" role="tablist" aria-orientation="vertical" aria-label="<?php esc_attr_e( 'Settings sections', 'subscription' ); ?>">
					<?php foreach ( $settings_fields as $subscrpt_group_id => $subscrpt_group ) : ?>
						<?php
						$subscrpt_is_active = $subscrpt_group_id === $active_tab;
						$subscrpt_label     = SettingsHelper::group_label( $subscrpt_group_id, $subscrpt_group );
						?>
						<a
							class="subscrpt-settings__tab<?php echo $subscrpt_is_active ? ' is-active' : ''; ?>"
							id="subscrpt-tab-<?php echo esc_attr( $subscrpt_group_id ); ?>"
							href="<?php echo esc_url( add_query_arg( 'tab', $subscrpt_group_id, $subscrpt_settings_base ) ); ?>"
							role="tab"
							aria-selected="<?php echo $subscrpt_is_active ? 'true' : 'false'; ?>"
							aria-controls="subscrpt-panel-<?php echo esc_attr( $subscrpt_group_id ); ?>"
							data-subscrpt-tab="<?php echo esc_attr( $subscrpt_group_id ); ?>"
						>
							<span class="subscrpt-settings__tab-label"><?php echo esc_html( $subscrpt_label ); ?></span>
							<?php if ( SettingsHelper::group_is_pro_locked( $subscrpt_group ) ) : ?>
								<?php echo wp_kses_post( SettingsHelper::pro_badge_html() ); ?>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</nav>
			</aside>

			<div class="subscrpt-settings__panels">
				<?php foreach ( $settings_fields as $subscrpt_group_id => $subscrpt_group ) : ?>
					<?php
					$subscrpt_is_active = $subscrpt_group_id === $active_tab;
					$subscrpt_fields    = array_values( $subscrpt_group['fields'] ?? array() );
					$subscrpt_count     = count( $subscrpt_fields );
					?>
					<section
						class="subscrpt-settings__panel wpsubs-table-card"
						id="subscrpt-panel-<?php echo esc_attr( $subscrpt_group_id ); ?>"
						role="tabpanel"
						aria-labelledby="subscrpt-tab-<?php echo esc_attr( $subscrpt_group_id ); ?>"
						data-subscrpt-panel="<?php echo esc_attr( $subscrpt_group_id ); ?>"
						<?php echo $subscrpt_is_active ? '' : 'hidden'; ?>
					>
						<?php foreach ( $subscrpt_fields as $subscrpt_idx => $subscrpt_field ) : ?>
							<?php
							$subscrpt_type = $subscrpt_field['type'] ?? 'input';
							SettingsHelper::render_settings_field( $subscrpt_type, $subscrpt_field['field_data'] ?? array() );
							?>
							<?php if ( 'heading' !== $subscrpt_type && $subscrpt_idx + 1 < $subscrpt_count ) : ?>
								<div class="subscrpt-settings__rule"></div>
							<?php endif; ?>
						<?php endforeach; ?>
					</section>
				<?php endforeach; ?>
			</div>

		</div>

		<div class="subscrpt-settings__actions">
			<button type="submit" class="wpsubs-btn wpsubs-btn--primary">
				<?php esc_html_e( 'Save changes', 'subscription' ); ?>
			</button>
			<span class="subscrpt-settings__actions-hint">
				<?php esc_html_e( 'Saves every section, not just the one open.', 'subscription' ); ?>
			</span>
		</div>
	</form>

</div>
