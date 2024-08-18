<div id="sdevs_subscription_options" class="panel woocommerce_options_panel option_group sdevs-form sdevs_panel show_if_simple" style="padding: 10px;">
<div class="show_if_subscription">
	<input name="_subscript_nonce" type="hidden" value="<?php echo esc_attr( wp_create_nonce( '_subscript_edit_product_nonce' ) ); ?>" />
	<strong style="margin: 10px;"><?php esc_html_e( 'Subscription Settings', 'sdevs_subscrpt' ); ?></strong>
	<?php

	woocommerce_wp_select(
		array(
			'id'          => 'subscrpt_timing',
			'label'       => __( 'Users will pay', 'sdevs_subscrpt' ),
			'value'       => $subscrpt_timing,
			'options'     => $timing_types,
			'description' => __( 'Set the length of each recurring subscription period to daily, weekly, monthly or annually.', 'sdevs_subscrpt' ),
			'desc_tip'    => true,
		)
	);
	?>
<p class="form-field subscrpt_field">
	<label for="subscrpt_trial_time"><?php esc_html_e( 'Offer a free trial of', 'sdevs_subscrpt' ); ?></label>
	<input type="number" class="short" name="subscrpt_trial_time" id="subscrpt_trial_time" value="<?php echo esc_attr( $subscrpt_trial_time ); ?>" />
	<select name="subscrpt_trial_timing" id="subscrpt_trial_timing">
<?php foreach ( $trial_timing_types as $timing_type ) : ?>
			<option value="<?php echo esc_attr( $timing_type['value'] ); ?>" 
										<?php
										if ( $subscrpt_trial_timing === $timing_type['value'] ) {
											echo 'selected';
										}
										?>
			><?php echo esc_html( $timing_type['label'] ); ?></option>
<?php endforeach; ?>
	</select>
	<small class="description"><?php esc_html_e( 'You can offer a free trial of this subscription. In this way the user can purchase the subscription and will pay when the trial period expires.', 'sdevs_subscrpt' ); ?></small>
</p>

	<?php

	woocommerce_wp_text_input(
		array(
			'id'          => 'subscrpt_cart_txt',
			'label'       => __( 'Add to Cart Text', 'sdevs_subscrpt' ),
			'type'        => 'text',
			'value'       => $subscrpt_cart_txt,
			'description' => __( 'change Add to Cart Text default is "subscribe"', 'sdevs_subscrpt' ),
			'desc_tip'    => true,
		)
	);

	woocommerce_wp_select(
		array(
			'id'          => 'subscrpt_user_cancel',
			'label'       => __( 'Can User Cancel', 'sdevs_subscrpt' ),
			'value'       => $subscrpt_user_cancell,
			'options'     => array(
				'yes' => __( 'Yes', 'sdevs_subscrpt' ),
				'no'  => __( 'No', 'sdevs_subscrpt' ),
			),
			'description' => __( 'if "Yes",then user can be cancelled."No" means cannot do this !!', 'sdevs_subscrpt' ),
			'desc_tip'    => true,
		)
	);

	woocommerce_wp_select(
		array(
			'id'          => 'subscrpt_limit',
			'label'       => __( 'Limit subscription', 'sdevs_subscrpt' ),
			'options'     => array(
				'unlimited' => __( 'Do not limit', 'sdevs_subscrpt' ),
				'one'       => __( 'allow only one active subscription', 'sdevs_subscrpt' ),
				'only_one'  => __( 'allow only one subscription of any status', 'sdevs_subscrpt' ),
			),
			'value'       => $subscrpt_limit,
			'description' => __( 'Set optional limits for this product subscription.', 'sdevs_subscrpt' ),
			'desc_tip'    => true,
		)
	);
	?>
</div>
</div>
