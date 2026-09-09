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
 * @var string $active_cat      Broad sidebar category currently open.
 * @var string $active_tab      Group key of the panel to open on ('' under 'all').
 * @var array  $category_groups Category key => ordered group keys.
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
				<nav class="wpsubs-vnav" aria-label="<?php esc_attr_e( 'Settings categories', 'subscription' ); ?>">
					<?php foreach ( SettingsHelper::categories() as $subscrpt_cat_id => $subscrpt_cat_label ) : ?>
						<?php $subscrpt_cat_active = $subscrpt_cat_id === $active_cat; ?>
						<a
							class="wpsubs-vnav__item<?php echo $subscrpt_cat_active ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( add_query_arg( 'cat', $subscrpt_cat_id, remove_query_arg( 'tab', $subscrpt_settings_base ) ) ); ?>"
							data-subscrpt-cat="<?php echo esc_attr( $subscrpt_cat_id ); ?>"
							aria-current="<?php echo $subscrpt_cat_active ? 'page' : 'false'; ?>"
						>
							<span class="wpsubs-vnav__label"><?php echo esc_html( $subscrpt_cat_label ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>
			</aside>

			<div class="subscrpt-settings__content">

				<?php
				// Every non-`all` category's tab list is rendered and all but the
				// active one hidden, so the sidebar can switch categories client-side
				// without a page load. `all` shows no list and stacks every panel.
				// The save button shares this row with the tabs, pinned to the right.
				?>
				<div class="subscrpt-settings__toolbar">
					<div class="subscrpt-settings__toolbar-tabs">
					<?php foreach ( $category_groups as $subscrpt_cat_id => $subscrpt_cat_group_ids ) : ?>
						<?php if ( 0 === count( $subscrpt_cat_group_ids ) ) : ?>
							<?php continue; ?>
					<?php endif; ?>
						<?php $subscrpt_list_open = 'all' !== $active_cat && $subscrpt_cat_id === $active_cat; ?>
					<div
						class="wpsubs-tabs__list"
						role="tablist"
						aria-label="<?php esc_attr_e( 'Settings sections', 'subscription' ); ?>"
						data-subscrpt-tablist="<?php echo esc_attr( $subscrpt_cat_id ); ?>"
						<?php echo $subscrpt_list_open ? '' : 'hidden'; ?>
					>
						<?php foreach ( $subscrpt_cat_group_ids as $subscrpt_tab_idx => $subscrpt_group_id ) : ?>
							<?php
							$subscrpt_group = $settings_fields[ $subscrpt_group_id ];
							$subscrpt_label = SettingsHelper::group_label( $subscrpt_group_id, $subscrpt_group );
							// Active tab when this is the open category, otherwise pre-select
							// the first so a JS reveal always has a selected tab.
							$subscrpt_tab_open = ( $subscrpt_cat_id === $active_cat )
								? ( $subscrpt_group_id === $active_tab )
								: ( 0 === $subscrpt_tab_idx );
							$subscrpt_tab_url  = add_query_arg(
								array(
									'cat' => $subscrpt_cat_id,
									'tab' => $subscrpt_group_id,
								),
								$subscrpt_settings_base
							);
							?>
							<a
								class="wpsubs-tabs__tab"
								id="subscrpt-tab-<?php echo esc_attr( $subscrpt_group_id ); ?>"
								href="<?php echo esc_url( $subscrpt_tab_url ); ?>"
								role="tab"
								aria-selected="<?php echo $subscrpt_tab_open ? 'true' : 'false'; ?>"
								aria-controls="subscrpt-panel-<?php echo esc_attr( $subscrpt_group_id ); ?>"
								data-subscrpt-tab="<?php echo esc_attr( $subscrpt_group_id ); ?>"
							>
								<?php echo esc_html( $subscrpt_label ); ?>
								<?php if ( SettingsHelper::group_is_pro_locked( $subscrpt_group ) ) : ?>
									<?php echo wp_kses_post( SettingsHelper::pro_badge_html() ); ?>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
					</div>
					<button type="submit" class="wpsubs-btn wpsubs-btn--primary subscrpt-settings__save">
						<?php esc_html_e( 'Save changes', 'subscription' ); ?>
					</button>
				</div>

				<div class="subscrpt-settings__panels">
					<?php foreach ( $settings_fields as $subscrpt_group_id => $subscrpt_group ) : ?>
						<?php
						$subscrpt_panel_open = ( 'all' === $active_cat ) || ( $subscrpt_group_id === $active_tab );
						$subscrpt_fields     = array_values( $subscrpt_group['fields'] ?? array() );
						$subscrpt_count      = count( $subscrpt_fields );
						?>
						<section
							class="subscrpt-settings__panel wpsubs-table-card"
							id="subscrpt-panel-<?php echo esc_attr( $subscrpt_group_id ); ?>"
							role="tabpanel"
							aria-labelledby="subscrpt-tab-<?php echo esc_attr( $subscrpt_group_id ); ?>"
							data-subscrpt-panel="<?php echo esc_attr( $subscrpt_group_id ); ?>"
							data-subscrpt-cat="<?php echo esc_attr( SettingsHelper::group_category( $subscrpt_group_id ) ); ?>"
							<?php echo $subscrpt_panel_open ? '' : 'hidden'; ?>
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

		</div>
	</form>

</div>
