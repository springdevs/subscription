<?php
/**
 * Subscription settings admin view.
 *
 * One form, every panel rendered, one panel visible. The form posts to
 * `options.php`, which saves whatever it is given — so a panel that is not
 * rendered is a panel whose settings do not save. Hiding is therefore CSS, not
 * a conditional, and every one of the fields below submits on every save
 * whichever section happens to be open.
 *
 * One level of navigation: the rail picks a section and the section's panel
 * stacks whatever groups belong to it. A section holding a single group drops
 * that group's heading, because the rail item beside it already says the same
 * word.
 *
 * @package SpringDevs\Subscription\Admin
 *
 * @var array  $settings_fields Grouped and sorted settings fields.
 * @var string $active_cat      Section currently open.
 * @var array  $category_groups Section key => ordered group keys (empty ones dropped).
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

		<?php // Page header: title, save on the right, one line of description, rule. ?>
		<div class="subscrpt-settings__head">
			<div class="subscrpt-settings__head-row">
				<h1 class="subscrpt-settings__title"><?php esc_html_e( 'Settings', 'subscription' ); ?></h1>
				<span class="wpsubs-toolbar__spacer"></span>
				<button type="submit" class="wpsubs-btn wpsubs-btn--primary subscrpt-settings__save">
					<?php esc_html_e( 'Save changes', 'subscription' ); ?>
				</button>
			</div>
			<p class="subscrpt-settings__desc"><?php esc_html_e( 'Configure how subscriptions renew, charge and behave for your customers.', 'subscription' ); ?></p>
			<div class="subscrpt-settings__head-rule"></div>
		</div>

		<div class="subscrpt-settings__layout">

			<aside class="subscrpt-settings__sidebar">
				<nav class="wpsubs-vnav" aria-label="<?php esc_attr_e( 'Settings sections', 'subscription' ); ?>">
					<?php
					$subscrpt_cat_labels = SettingsHelper::categories();
					foreach ( $category_groups as $subscrpt_cat_id => $subscrpt_cat_group_ids ) :
						$subscrpt_cat_active = $subscrpt_cat_id === $active_cat;
						?>
					<a
						class="wpsubs-vnav__item<?php echo $subscrpt_cat_active ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'cat', $subscrpt_cat_id, $subscrpt_settings_base ) ); ?>"
						data-subscrpt-cat="<?php echo esc_attr( $subscrpt_cat_id ); ?>"
						aria-current="<?php echo $subscrpt_cat_active ? 'page' : 'false'; ?>"
					>
						<span class="wpsubs-vnav__label"><?php echo esc_html( $subscrpt_cat_labels[ $subscrpt_cat_id ] ?? $subscrpt_cat_id ); ?></span>
						<?php if ( SettingsHelper::category_is_pro_locked( $subscrpt_cat_group_ids, $settings_fields ) ) : ?>
							<?php echo wp_kses_post( SettingsHelper::pro_badge_html() ); ?>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
				</nav>
			</aside>

			<div class="subscrpt-settings__content">

				<div class="subscrpt-settings__panels">
					<?php foreach ( $category_groups as $subscrpt_cat_id => $subscrpt_cat_group_ids ) : ?>
						<?php
						// A section of one group takes its name from the rail item, so
						// repeating it as a heading says nothing.
						$subscrpt_show_headings = count( $subscrpt_cat_group_ids ) > 1;

						// Flatten the section's groups into one run of fields, so the rules
						// between them are drawn once and in order.
						$subscrpt_fields = array();
						foreach ( $subscrpt_cat_group_ids as $subscrpt_group_id ) {
							foreach ( array_values( $settings_fields[ $subscrpt_group_id ]['fields'] ?? array() ) as $subscrpt_field ) {
								if ( ! $subscrpt_show_headings && 'heading' === ( $subscrpt_field['type'] ?? '' ) ) {
									continue;
								}
								$subscrpt_fields[] = $subscrpt_field;
							}
						}
						$subscrpt_count = count( $subscrpt_fields );
						?>
						<section
							class="subscrpt-settings__panel wpsubs-table-card"
							id="subscrpt-panel-<?php echo esc_attr( $subscrpt_cat_id ); ?>"
							role="region"
							aria-label="<?php echo esc_attr( $subscrpt_cat_labels[ $subscrpt_cat_id ] ?? $subscrpt_cat_id ); ?>"
							data-subscrpt-panel="<?php echo esc_attr( $subscrpt_cat_id ); ?>"
							<?php echo $subscrpt_cat_id === $active_cat ? '' : 'hidden'; ?>
						>
							<?php foreach ( $subscrpt_fields as $subscrpt_idx => $subscrpt_field ) : ?>
								<?php
								$subscrpt_type = $subscrpt_field['type'] ?? 'input';
								SettingsHelper::render_settings_field( $subscrpt_type, $subscrpt_field['field_data'] ?? array() );

								// No rule before a heading either — the heading is itself the
								// break between two groups.
								$subscrpt_next = $subscrpt_fields[ $subscrpt_idx + 1 ] ?? null;
								$subscrpt_rule = 'heading' !== $subscrpt_type
									&& $subscrpt_idx + 1 < $subscrpt_count
									&& 'heading' !== ( $subscrpt_next['type'] ?? '' );
								?>
								<?php if ( $subscrpt_rule ) : ?>
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
