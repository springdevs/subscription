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
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'register_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'subscription_forms' ) );
		add_filter( 'product_type_options', array( $this, 'add_product_type_options' ) );
		add_action( 'save_post_product', array( $this, 'save_subscrpt_data' ) );
	}

	/**
	 * Enqueue Assets.
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'sdevs_subscription_admin' );
	}

	/**
	 * Register "Subscription" option tab.
	 *
	 * @param array $tabs Tabs.
	 *
	 * @return array
	 */
	public function register_tab( $tabs ) {
		$tabs['sdevs_subscription'] = array(
			'label'    => __( 'Subscription', 'sdevs_subscrpt' ),
			'class'    => array( 'show_if_simple', 'show_if_subscription' ),
			'target'   => 'sdevs_subscription_options',
			'priority' => 11,
		);
		return $tabs;
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
			$product = wc_get_product( get_the_ID() );
			if ( $product ) {
				$value = $product->get_meta( '_subscrpt_enabled' ) ? 'yes' : 'no';
			}
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
				if ( 'edit' === $screen->parent_base ) {
					$product = wc_get_product( get_the_ID() );
					if ( $product ) {
						$subscrpt_timing       = $product->get_meta( '_subscrpt_timing_option' );
						$subscrpt_cart_txt     = $product->get_meta( '_subscrpt_cart_btn_label' );
						$subscrpt_user_cancell = $product->get_meta( '_subscrpt_user_cancel' );
					}
				}
				include 'views/product-form.php';
			}
		}
	}

	/**
	 * Save subscription settings.
	 *
	 * @param int $product_id Product Id.
	 *
	 * @return void
	 */
	public function save_subscrpt_data( $product_id ) {
		if ( function_exists( 'subscrpt_pro_activated' ) ) {
			if ( subscrpt_pro_activated() ) {
				return;
			}
		}

		if ( ! isset( $_POST['_subscript_nonce'], $_POST['subscrpt_timing'], $_POST['subscrpt_cart_txt'], $_POST['subscrpt_user_cancel'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_subscript_nonce'] ) ), '_subscript_edit_product_nonce' ) ) {
			return;
		}

		remove_action( 'save_post_product', array( $this, 'save_subscrpt_data' ) );

		$subscrpt_enable      = isset( $_POST['subscrpt_enable'] );
		$subscrpt_timing      = sanitize_text_field( wp_unslash( $_POST['subscrpt_timing'] ) );
		$subscrpt_cart_txt    = sanitize_text_field( wp_unslash( $_POST['subscrpt_cart_txt'] ) );
		$subscrpt_user_cancel = sanitize_text_field( wp_unslash( $_POST['subscrpt_user_cancel'] ) );
		$product              = wc_get_product( $product_id );

		$product->update_meta_data( '_subscrpt_enabled', $subscrpt_enable );
		$product->update_meta_data( '_subscrpt_timing_option', $subscrpt_timing );
		$product->update_meta_data( '_subscrpt_cart_btn_label', $subscrpt_cart_txt );
		$product->update_meta_data( '_subscrpt_user_cancel', $subscrpt_user_cancel );
		$product->save();

		add_action( 'save_post_product', array( $this, 'save_subscrpt_data' ) );
	}
}
