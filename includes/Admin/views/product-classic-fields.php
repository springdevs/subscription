<?php
/**
 * Classic (legacy) per-product subscription meta fields.
 *
 * Shared by the classic-only panel (product-form.php) and the simple-product
 * plan view's hidden "classic settings" pane (Admin/Product/Plans). The fields
 * are unchanged legacy inputs kept for backward compatibility; they always
 * submit with the product post save regardless of which view is visible.
 *
 * Expects in scope: $subscrpt_enabled, $timing_types, $subscrpt_timing,
 * $trial_timing_types, $subscrpt_trial_time, $subscrpt_trial_timing,
 * $subscrpt_cart_txt, $subscrpt_user_cancell, $subscrpt_limit.
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<input name="_subscript_nonce" type="hidden" value="<?php echo esc_attr( wp_create_nonce( '_subscript_edit_product_nonce' ) ); ?>"/>
<strong style="margin: 10px;"><?php esc_html_e( 'Subscription Settings', 'subscription' ); ?></strong>
<?php
// The "Enable subscription" toggle lives in the shared toolbar above (see
// Admin\Product\Plans::render_toolbar), so it is not repeated here.
woocommerce_wp_select(
	array(
		'id'          => 'subscrpt_timing',
		'label'       => __( 'Users will pay', 'subscription' ),
		'value'       => $subscrpt_timing,
		'options'     => $timing_types,
		'description' => __( 'Set the length of each recurring subscription period to daily, weekly, monthly or annually.', 'subscription' ),
		'desc_tip'    => true,
	)
);
?>
<p class="form-field subscrpt_field">
	<label for="subscrpt_trial_time"><?php esc_html_e( 'Free Trial Duration', 'subscription' ); ?></label>
	<input type="number" class="short" name="subscrpt_trial_time" id="subscrpt_trial_time" value="<?php echo esc_attr( $subscrpt_trial_time ); ?>"/>
	<select name="subscrpt_trial_timing" id="subscrpt_trial_timing">
		<?php foreach ( $trial_timing_types as $timing_type ) : ?>
			<option value="<?php echo esc_attr( $timing_type['value'] ); ?>"
				<?php selected( $subscrpt_trial_timing, $timing_type['value'] ); ?>
			><?php echo esc_html( $timing_type['label'] ); ?></option>
		<?php endforeach; ?>
	</select>
	<small class="description"><?php esc_html_e( 'Let users try the subscription for free before the first payment is collected.', 'subscription' ); ?></small>
</p>
<?php
woocommerce_wp_text_input(
	array(
		'id'          => 'subscrpt_cart_txt',
		'label'       => __( 'Button Text (Custom)', 'subscription' ),
		'type'        => 'text',
		'value'       => $subscrpt_cart_txt,
		'description' => __( 'Customize the button label shown on the product or shop page. Default is "Subscribe"', 'subscription' ),
		'desc_tip'    => true,
	)
);

woocommerce_wp_select(
	array(
		'id'          => 'subscrpt_user_cancel',
		'label'       => __( 'Allow User Cancellation?', 'subscription' ),
		'value'       => $subscrpt_user_cancell,
		'options'     => array(
			'yes' => __( 'Yes', 'subscription' ),
			'no'  => __( 'No', 'subscription' ),
		),
		'description' => __( 'Allow subscribers to cancel their subscription manually from their account dashboard.', 'subscription' ),
		'desc_tip'    => true,
	)
);

woocommerce_wp_select(
	array(
		'id'          => 'subscrpt_limit',
		'label'       => __( 'Limit subscription', 'subscription' ),
		'options'     => array(
			'unlimited' => __( 'Do not limit', 'subscription' ),
			'one'       => __( 'allow only one active subscription', 'subscription' ),
			'only_one'  => __( 'allow only one subscription of any status', 'subscription' ),
		),
		'value'       => $subscrpt_limit,
		'description' => __( 'Set optional limits for this product subscription.', 'subscription' ),
		'desc_tip'    => true,
	)
);
