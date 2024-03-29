<div class="wrap">
	<?php settings_errors(); ?>
	<h1><?php esc_html_e( 'Subscription Settings', 'sdevs_subscrpt' ); ?></h1>
	<p><?php esc_html_e( 'These settings can effect Subscription', 'sdevs_subscrpt' ); ?></p>
	<form method="post" action="options.php">
		<?php settings_fields( 'subscrpt_settings' ); ?>
		<?php do_settings_sections( 'subscrpt_settings' ); ?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="subscrpt_renewal_process">
							<?php esc_html_e( 'Renewal Process', 'sdevs_subscrpt' ); ?>
						</label>
					</th>
					<td>
						<select name="subscrpt_renewal_process" id="subscrpt_renewal_process" 
						<?php
						if ( ! subscrpt_pro_activated() ) {
							echo esc_attr( 'disabled' );}
						?>
						>
							<option value="auto">Automatic</option>
							<option value="manual" 
							<?php
							if ( ! subscrpt_pro_activated() || 'manual' === get_option( 'subscrpt_renewal_process', 'auto' ) ) {
								echo esc_attr( 'selected' );
							}
							?>
							>Manual</option>
						</select>
						<?php if ( ! subscrpt_pro_activated() ) : ?>
						<p style="color: red;" class="description"><?php echo wp_kses_post( '<b>Automatic</b> renewal is available for Pro version only !!' ); ?></p>
						<?php else : ?>
						<p class="description"><?php esc_html_e( 'How renewal process will be done after Subscription Expired !!', 'sdevs_subscrpt' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr id="sdevs_renewal_cart_tr">
					<th scope="row">
						<label for="subscrpt_manual_renew_cart_notice">
							<?php esc_html_e( 'Renewal Cart Notice', 'sdevs_subscrpt' ); ?>
						</label>
					</th>
					<td>
						<input id="subscrpt_manual_renew_cart_notice" name="subscrpt_manual_renew_cart_notice" class="large-text" value="<?php echo wp_kses_post( get_option( 'subscrpt_manual_renew_cart_notice' ) ); ?>" type="text"/>
						<p class="description"><?php echo wp_kses_post( 'Display Notice when Renewal Subscription product add to cart !! It\'s only available for <b>Manual Renewal Process</b>.', 'sdevs_subscrpt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="subscrpt_active_role">
							<?php esc_html_e( 'Subscriber Default Role', 'sdevs_subscrpt' ); ?>
						</label>
					</th>
					<td>
						<select name="subscrpt_active_role" id="subscrpt_active_role">
							<?php wp_dropdown_roles( get_option( 'subscrpt_active_role', 'subscriber' ) ); ?>
						</select>
						<p class="description"><?php esc_html_e( 'When a subscription is activated, either manually or after a successful purchase, new users will be assigned this role.', 'sdevs_subscrpt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="subscrpt_unactive_role">
							<?php esc_html_e( 'Subscriber Unactive Role', 'sdevs_subscrpt' ); ?>
						</label>
					</th>
					<td>
						<select name="subscrpt_unactive_role" id="subscrpt_unactive_role">
							<?php wp_dropdown_roles( get_option( 'subscrpt_unactive_role', 'customer' ) ); ?>
						</select>
						<p class="description"><?php esc_html_e( "If a subscriber's subscription is manually cancelled or expires, will be assigned this role.", 'sdevs_subscrpt' ); ?></p>
					</td>
				</tr>
				<?php do_action( 'subscrpt_setting_fields' ); ?>
			</tbody>
		</table>

		<?php submit_button(); ?>

	</form>
</div>
