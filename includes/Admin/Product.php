<?php
/**
 * Product editor integration (Subscription tab).
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Helper;

// HPOS: This file does not access WooCommerce order data directly.
// All meta access is for product or subscription data only, not WooCommerce order data.
// If you add new order data access, use WooCommerce CRUD for HPOS compatibility.

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
		add_action( 'save_post_product', array( $this, 'save_subscrpt_data' ) );
		add_filter( 'woocommerce_get_price_html', array( $this, 'change_price_html' ), 10, 2 );
	}

	/**
	 * Add trial, signup fee etc. with product price.
	 *
	 * @param string      $price Price.
	 * @param \WC_Product $product Product.
	 *
	 * @return string
	 */
	public function change_price_html( $price, $product ) {
		if ( $product->is_type( 'variable' ) || '' === $price || subscrpt_pro_activated() ) {
			return $price;
		}

		$enabled = $product->get_meta( '_subscrpt_enabled' );
		if ( $enabled ) :
			$type            = Helper::get_typos( 1, $product->get_meta( '_subscrpt_timing_option' ), true );
			$meta_trial_time = $product->get_meta( '_subscrpt_trial_timing_per' );
			$trial           = null;
			if ( ! empty( $meta_trial_time ) && $meta_trial_time > 0 ) {
				$trial_type = Helper::get_typos( $meta_trial_time, $product->get_meta( '_subscrpt_trial_timing_option' ), true );
				$trial      = '<br/> + Get ' . $meta_trial_time . ' ' . ucfirst( $trial_type ) . ' free trial!';
			}

			$price_html = $price . ' / ' . ucfirst( $type ) . $trial;
			return $price_html;
		else :
			return $price;
		endif;
	}

	/**
	 * Enqueue Assets.
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'sdevs_subscription_admin' );

		// Plan view assets on the product editor. Enqueued whether or not Pro is
		// active: with Pro off, free renders its own panel; with Pro on, the plan
		// view mounts on Pro's `subscrpt_simple_plan_panel` hook.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		wp_enqueue_style( 'subscrpt_admin_components' );
		wp_enqueue_script( 'subscrpt_admin_components' );

		// Shared plan-group + selling-plan modal logic. With Pro active the
		// Subscription tab exposes "＋ New plan group", which drives these modals.
		wp_enqueue_script( 'subscrpt_plan_forms_js' );
		wp_localize_script( 'subscrpt_plan_forms_js', 'subscrptPlanForms', \SpringDevs\Subscription\Admin\Plans::plan_forms_config() );

		wp_enqueue_script(
			'subscrpt_product_plans_js',
			SUBSCRPT_ASSETS . '/js/admin/product-plans.js',
			array( 'subscrpt_admin_components', 'subscrpt_plan_forms_js' ),
			SUBSCRPT_VERSION,
			true
		);

		wp_localize_script(
			'subscrpt_product_plans_js',
			'subscrptProductPlans',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'wpsubscription/v1/plans' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'currency' => function_exists( 'get_woocommerce_currency_symbol' )
					? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
					: '$',
				'i18n'     => array(
					'connectError'   => __( 'Could not connect the plan. Please try again.', 'subscription' ),
					'pickPlan'       => __( 'Please select a plan.', 'subscription' ),
					'confirmDetach'  => __( 'Detach this product from the plan?', 'subscription' ),
					'loading'        => __( 'Loading plans…', 'subscription' ),
					'step1'          => __( 'Step 1 of 2 · Plan', 'subscription' ),
					'step2'          => __( 'Step 2 of 2 · Plan', 'subscription' ),
					'wizardNext'     => __( 'Continue', 'subscription' ),
					'wizardBack'     => __( 'Back', 'subscription' ),
					'wizardCreate'   => __( 'Create', 'subscription' ),
					'addPlan'        => __( 'Add plan', 'subscription' ),
					'copyLinkPrompt' => __( 'Copy this direct checkout link:', 'subscription' ),
				),
			)
		);
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
			'label'    => __( 'Subscription', 'subscription' ),
			// Shown for simple and variable products. The panel content differs
			// by type (see subscription_forms): simple renders here in free;
			// variable is filled by Pro via `subscrpt_variable_subscription_panel`.
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'target'   => 'sdevs_subscription_options',
			'priority' => 11,
		);
		return $tabs;
	}

	/**
	 * Display forms on product create/edit.
	 */
	public function subscription_forms() {
		$screen           = get_current_screen();
		$subscrpt_current = ( $screen && 'edit' === $screen->parent_base ) ? wc_get_product( get_the_ID() ) : null;

		// Variable products: free renders the panel shell; the content is filled
		// by Pro (per-variation sections) via the action below. Free itself is
		// simple-only, so with Pro inactive it shows an upgrade note.
		if ( $subscrpt_current && $subscrpt_current->is_type( 'variable' ) ) {
			?>
			<div id="sdevs_subscription_options" class="panel woocommerce_options_panel option_group sdevs-form sdevs_panel show_if_variable" style="padding:10px;">
				<?php
				/**
				 * Fires inside the Subscription tab for a variable product. Pro
				 * hooks this to render its per-variation subscription sections.
				 *
				 * @param \WC_Product $subscrpt_current Variable product being edited.
				 */
				do_action( 'subscrpt_variable_subscription_panel', $subscrpt_current );

				if ( ! has_action( 'subscrpt_variable_subscription_panel' ) ) {
					?>
					<div style="text-align:center;padding:30px 20px;">
						<span style="display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:50%;background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand,#ff4d00);">
							<span class="dashicons dashicons-lock" style="font-size:24px;width:24px;height:24px;"></span>
						</span>
						<h3 style="margin:14px 0 6px;font-size:15px;color:var(--wpsubs-text,#1d2327);display:flex;align-items:center;justify-content:center;gap:8px;">
							<?php esc_html_e( 'Variable product subscriptions', 'subscription' ); ?>
							<span class="wpsubs-badge wpsubs-badge--pro"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
						</h3>
						<p style="margin:0 auto;max-width:380px;font-size:13px;line-height:1.6;color:var(--wpsubs-text-muted,#646970);">
							<?php esc_html_e( 'Sell each variation as its own subscription, with per-variation billing, trials and sign-up fees.', 'subscription' ); ?>
						</p>
						<a href="https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro" target="_blank" rel="noopener noreferrer" class="wpsubs-btn wpsubs-btn--primary" style="margin-top:16px;">
							<?php esc_html_e( 'Upgrade to Pro', 'subscription' ); ?>
						</a>
					</div>
					<?php
				}
				?>
			</div>
			<?php
			return;
		}

		if ( function_exists( 'subscrpt_pro_activated' ) ) {
			if ( subscrpt_pro_activated() ) {
				do_action( 'subscrpt_simple_pro_fields', get_the_ID() );
			} else {
				$timing_types          = array(
					'days'   => __( 'Daily', 'subscription' ),
					'weeks'  => __( 'Weekly', 'subscription' ),
					'months' => __( 'Monthly', 'subscription' ),
					'years'  => __( 'Yearly', 'subscription' ),
				);
				$trial_timing_types    = wps_subscription_get_timing_types();
				$subscrpt_timing       = null;
				$subscrpt_trial_time   = null;
				$subscrpt_trial_timing = null;
				$subscrpt_cart_txt     = 'subscribe';
				$subscrpt_user_cancell = 'yes';
				$subscrpt_limit        = 'one';
				$subscrpt_enabled      = false;

				$subscrpt_product = null;
				$screen           = get_current_screen();
				if ( 'edit' === $screen->parent_base ) {
					$subscrpt_product = wc_get_product( get_the_ID() );
					if ( $subscrpt_product ) {
						$subscrpt_enabled      = (bool) $subscrpt_product->get_meta( '_subscrpt_enabled' );
						$subscrpt_timing       = $subscrpt_product->get_meta( '_subscrpt_timing_option' );
						$subscrpt_trial_time   = $subscrpt_product->get_meta( '_subscrpt_trial_timing_per' );
						$subscrpt_trial_timing = $subscrpt_product->get_meta( '_subscrpt_trial_timing_option' );
						$subscrpt_cart_txt     = $subscrpt_product->get_meta( '_subscrpt_cart_btn_label' );
						$subscrpt_user_cancell = $subscrpt_product->get_meta( '_subscrpt_user_cancel' );
						$subscrpt_limit        = $subscrpt_product->get_meta( '_subscrpt_limit' );
					}
				}

				// Simple products: plan view (default) + hidden classic settings.
				// Variable/other products keep the classic-only panel unchanged.
				if ( $subscrpt_product && $subscrpt_product->is_type( 'simple' ) && class_exists( '\SpringDevs\Subscription\Admin\Product\Plans' ) ) {
					?>
					<div id="sdevs_subscription_options" class="panel woocommerce_options_panel option_group sdevs-form sdevs_panel show_if_simple" style="padding:10px;" data-subscrpt-product-plans data-product-id="<?php echo esc_attr( $subscrpt_product->get_id() ); ?>"<?php echo Product\Plans::should_default_classic( $subscrpt_product ) ? ' data-subscrpt-default-classic="1"' : ''; ?>>
						<?php Product\Plans::render_toolbar( $subscrpt_product ); ?>
						<div data-subscrpt-plan-view>
							<?php Product\Plans::render_plan_view( $subscrpt_product ); ?>
						</div>
						<div data-subscrpt-classic-view style="display:none;">
							<?php require __DIR__ . '/views/product-classic-fields.php'; ?>
						</div>
					</div>
					<?php
					Product\Plans::render_checkout_link_modal( $subscrpt_product );
				} else {
					include 'views/product-form.php';
				}
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

		if ( ! isset( $_POST['_subscript_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_subscript_nonce'] ) ), '_subscript_edit_product_nonce' ) ) {
			return;
		}

		remove_action( 'save_post_product', array( $this, 'save_subscrpt_data' ) );

		$subscrpt_enable       = isset( $_POST['subscrpt_enable'] );
		$subscrpt_timing       = isset( $_POST['subscrpt_timing'] ) ? sanitize_text_field( wp_unslash( $_POST['subscrpt_timing'] ) ) : '';
		$subscrpt_trial_time   = isset( $_POST['subscrpt_trial_time'] ) ? sanitize_text_field( wp_unslash( $_POST['subscrpt_trial_time'] ) ) : '';
		$subscrpt_trial_timing = isset( $_POST['subscrpt_trial_timing'] ) ? sanitize_text_field( wp_unslash( $_POST['subscrpt_trial_timing'] ) ) : '';
		$subscrpt_cart_txt     = isset( $_POST['subscrpt_cart_txt'] ) ? sanitize_text_field( wp_unslash( $_POST['subscrpt_cart_txt'] ) ) : '';
		$subscrpt_user_cancel  = isset( $_POST['subscrpt_user_cancel'] ) ? sanitize_text_field( wp_unslash( $_POST['subscrpt_user_cancel'] ) ) : '';
		$subscrpt_limit        = isset( $_POST['subscrpt_limit'] ) ? sanitize_text_field( wp_unslash( $_POST['subscrpt_limit'] ) ) : null;

		$product = wc_get_product( $product_id );
		$product->update_meta_data( '_subscrpt_enabled', $subscrpt_enable );
		$product->update_meta_data( '_subscrpt_timing_option', $subscrpt_timing );
		$product->update_meta_data( '_subscrpt_trial_timing_per', $subscrpt_trial_time );
		$product->update_meta_data( '_subscrpt_trial_timing_option', $subscrpt_trial_timing );
		$product->update_meta_data( '_subscrpt_cart_btn_label', $subscrpt_cart_txt );
		$product->update_meta_data( '_subscrpt_user_cancel', $subscrpt_user_cancel );
		$product->update_meta_data( '_subscrpt_limit', $subscrpt_limit );
		$product->save();

		add_action( 'save_post_product', array( $this, 'save_subscrpt_data' ) );
	}
}
