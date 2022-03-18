<div class="option_group sdevs-form sdevs_panel show_if_subscription hide" style="padding: 10px;">
	<strong style="margin: 10px;"><?php _e( 'Subscription Settings', 'sdevs_subscrpt' ); ?></strong>
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
			'id'          => 'subscrpt_user_cancell',
			'label'       => __( 'Can User Cancell', 'sdevs_subscrpt' ),
			'value'       => $subscrpt_user_cancell,
			'options'     => array(
				'yes' => __( 'Yes', 'sdevs_subscrpt' ),
				'no'  => __( 'No', 'sdevs_subscrpt' ),
			),
			'description' => __( 'if "Yes",then user can be cancelled."No" means cannot do this !!', 'sdevs_subscrpt' ),
			'desc_tip'    => true,
		)
	);
	?>
</div>
