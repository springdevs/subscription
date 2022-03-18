<div class="wrap">
	<?php settings_errors(); ?>
	<h1><?php _e( 'Subscription Settings', 'sdevs_subscrpt' ); ?></h1>
	<p><?php _e( 'These settings can effect Subscription', 'sdevs_subscrpt' ); ?></p>
	<form method="post" action="options.php">
		<?php settings_fields( 'subscrpt_settings' ); ?>
		<?php do_settings_sections( 'subscrpt_settings' ); ?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="subscrpt_active_role">
							<?php _e( 'Subscriber Default Role', 'sdevs_subscrpt' ); ?>
						</label>
					</th>
					<td>
						<select name="subscrpt_active_role" id="subscrpt_active_role">
							<?php wp_dropdown_roles( get_option( 'subscrpt_active_role', 'subscriber' ) ); ?>
						</select>
						<p class="description"><?php _e( 'When a subscription is activated, either manually or after a successful purchase, new users will be assigned this role.', 'sdevs_subscrpt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="subscrpt_unactive_role">
							<?php _e( 'Subscriber Unactive Role', 'sdevs_subscrpt' ); ?>
						</label>
					</th>
					<td>
						<select name="subscrpt_unactive_role" id="subscrpt_unactive_role">
							<?php wp_dropdown_roles( get_option( 'subscrpt_unactive_role', 'customer' ) ); ?>
						</select>
						<p class="description"><?php _e( "If a subscriber's subscription is manually cancelled or expires, will be assigned this role.", 'sdevs_subscrpt' ); ?></p>
					</td>
				</tr>
				<?php do_action( 'subscrpt_setting_fields' ); ?>
			</tbody>
		</table>

		<?php submit_button(); ?>

	</form>
</div>
