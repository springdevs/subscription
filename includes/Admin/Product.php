<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Product class
 *
 * @package SpringDevs\Subscription\Admin
 */
class Product {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'product_type_options', array( $this, 'add_product_type_options' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'subscription_forms' ) );
		add_action( 'save_post_product', array( $this, 'save_subscrpt_data' ) );
	}

	/**
	 * Enqueue Assets.
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'sdevs_subscription_admin' );
	}

	/**
	 * Display Enable Subscription Checkbox on product data tab.
	 *
	 * @param array $product_type_options Product Type options on Data tab.
	 *
	 * @return array
	 */
	public function add_product_type_options( $product_type_options ) {
		$screen = get_current_screen();
		$value  = 'no';
		if ( 'edit' === $screen->parent_base ) {
			$post_meta = get_post_meta( get_the_ID(), '_subscrpt_meta', true );
			$value     = ! empty( $post_meta ) && $post_meta['enable'] ? 'yes' : 'no';
		}

		$wrapper_class                           = apply_filters( 'subscrpt_simple_enable_checkbox_classes', 'show_if_simple' );
		$product_type_options['subscrpt_enable'] = array(
			'id'            => 'subscrpt_enable',
			'wrapper_class' => $wrapper_class,
			'label'         => __( 'Subscription', 'sdevs_subscrpt' ),
			'description'   => __( 'Enable Subscriptions', 'sdevs_subscrpt' ),
			'default'       => $value,
		);

		return $product_type_options;
	}

	/**
	 * Display forms on product create/edit.
	 */
	public function subscription_forms() {
		if ( function_exists( 'subscrpt_pro_activated' ) ) {
			if ( subscrpt_pro_activated() ) {
				do_action( 'subscrpt_simple_pro_fields', get_the_ID() );
			} else {
				$timing_types          = array(
					'days'   => __( 'Daily', 'sdevs_subscrpt' ),
					'weeks'  => __( 'Weekly', 'sdevs_subscrpt' ),
					'months' => __( 'Monthly', 'sdevs_subscrpt' ),
					'years'  => __( 'Yearly', 'sdevs_subscrpt' ),
				);
				$subscrpt_timing       = null;
				$subscrpt_cart_txt     = 'subscribe';
				$subscrpt_user_cancell = 'yes';

				$screen = get_current_screen();
				if ( $screen->parent_base == 'edit' ) {
					$post_meta = get_post_meta( get_the_ID(), '_subscrpt_meta', true );
					if ( ! empty( $post_meta ) && is_array( $post_meta ) ) {
						$subscrpt_timing       = $post_meta['type'];
						$subscrpt_cart_txt     = $post_meta['cart_txt'];
						$subscrpt_user_cancell = $post_meta['user_cancell'];
					}
				}
				include 'views/product-form.php';
			}
		}
	}

	public function save_subscrpt_data( $post_id ) {
		if ( ! isset( $_POST['subscrpt_enable'] ) ) {
			return;
		}
		if ( function_exists( 'subscrpt_pro_activated' ) ) {
			if ( subscrpt_pro_activated() ) {
				return;
			}
		}
		$subscrpt_enable       = (bool) $_POST['subscrpt_enable'];
		$subscrpt_time         = 1;
		$subscrpt_timing       = sanitize_text_field( $_POST['subscrpt_timing'] );
		$subscrpt_trial_time   = null;
		$subscrpt_trial_timing = null;
		$subscrpt_cart_txt     = sanitize_text_field( $_POST['subscrpt_cart_txt'] );
		$subscrpt_user_cancell = sanitize_text_field( $_POST['subscrpt_user_cancell'] );
		$data                  = array(
			'enable'       => $subscrpt_enable,
			'time'         => $subscrpt_time,
			'type'         => $subscrpt_timing,
			'trial_time'   => $subscrpt_trial_time,
			'trial_type'   => $subscrpt_trial_timing,
			'cart_txt'     => $subscrpt_cart_txt,
			'user_cancell' => $subscrpt_user_cancell,
		);

		update_post_meta( $post_id, '_subscrpt_meta', $data );
	}
}
