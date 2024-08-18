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
						<select name="subscrpt_renewal_process" id="subscrpt_renewal_process">
							<option value="auto">Automatic</option>
							<option value="manual" 
							<?php
							if ( 'manual' === get_option( 'subscrpt_renewal_process', 'auto' ) ) {
								echo esc_attr( 'selected' );
							}
							?>
							>Manual</option>
						</select>
						<p class="description"><?php esc_html_e( 'How renewal process will be done after Subscription Expired !!', 'sdevs_subscrpt' ); ?></p>
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
				<tr id="subscrpt_stripe_auto_renew_tr" valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html_e( 'Stripe Auto Renewal', 'sdevs_subscrpt' ); ?></th>
			<td class="forminp forminp-checkbox">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo esc_html_e( 'Accept Stripe Auto Renewals', 'sdevs_subscrpt' ); ?></span></legend>
					<label for="subscrpt_stripe_auto_renew">
						<input name="subscrpt_stripe_auto_renew" id="subscrpt_stripe_auto_renew" type="checkbox" value="1" 
		<?php
		if ( '1' === get_option( 'subscrpt_stripe_auto_renew', '1' ) ) {
			echo 'checked';
		}
		?>
		> <?php echo esc_html_e( 'Accept Stripe Auto Renewals', 'sdevs_subscrpt' ); ?> </label>
					<p class="description">
		<?php
		echo wp_kses_post(
			sprintf(
			/* translators: HTML tags */
				__(
					'%1$s WooCommerce Stripe Payment Gateway %2$s plugin is required !',
					'sdevs_subscrpt'
				),
				'<a href="https://wordpress.org/plugins/woocommerce-gateway-stripe/" target="_blank">',
				'</a>'
			)
		);
		?>
					</p>
				</fieldset>
			</td>
		</tr>
		<tr id="subscrpt_auto_renewal_toggle_tr" valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html_e( 'Auto Renewal Toggle', 'sdevs_subscrpt' ); ?></th>
			<td class="forminp forminp-checkbox">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo esc_html_e( 'Auto Renewal Toggle', 'sdevs_subscrpt' ); ?></span></legend>
					<label for="subscrpt_auto_renewal_toggle">
						<input name="subscrpt_auto_renewal_toggle" id="subscrpt_auto_renewal_toggle" type="checkbox" value="1" 
		<?php
		if ( '1' === get_option( 'subscrpt_auto_renewal_toggle', '1' ) ) {
			echo 'checked';
		}
		?>
						> <?php echo esc_html_e( 'Display the auto renewal toggle', 'sdevs_subscrpt' ); ?> </label>
					<p class="description"><?php echo esc_html_e( 'Allow customers to turn on and off automatic renewals from their  Subscription details page.', 'sdevs_subscrpt' ); ?></p>
				</fieldset>
			</td>
		</tr>
				<?php do_action( 'subscrpt_setting_fields' ); ?>
			</tbody>
		</table>

		<?php submit_button(); ?>

	</form>
</div>
