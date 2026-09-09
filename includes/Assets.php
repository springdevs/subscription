<?php
/**
 * Registers and enqueues admin/frontend scripts and styles.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription;

/**
 * Scripts and Styles Class
 */
class Assets {

	/**
	 * Assets constructor.
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'register' ), 5 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_components' ), 6 );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'register' ), 5 );
		}
	}

	/**
	 * Register our app scripts and styles
	 *
	 * @return void
	 */
	public function register() {
		$this->register_scripts( $this->get_scripts() );
		$this->register_styles( $this->get_styles() );
	}

	/**
	 * Register scripts
	 *
	 * @param array $scripts scripts.
	 *
	 * @return void
	 */
	private function register_scripts( $scripts ) {
		foreach ( $scripts as $handle => $script ) {
			$deps      = isset( $script['deps'] ) ? $script['deps'] : false;
			$in_footer = isset( $script['in_footer'] ) ? $script['in_footer'] : false;
			$version   = isset( $script['version'] ) ? $script['version'] : SUBSCRPT_VERSION;

			wp_register_script( $handle, $script['src'], $deps, $version, $in_footer );

			// Register script translations for scripts that use wp-i18n (e.g., block assets).
			if ( function_exists( 'wp_set_script_translations' ) && 'sdevs_subscrpt_cart_block' === $handle ) {
				wp_set_script_translations( $handle, 'subscription', SUBSCRPT_PATH . '/languages' );
			}
		}
	}

	/**
	 * Register styles
	 *
	 * @param array $styles styles.
	 *
	 * @return void
	 */
	public function register_styles( $styles ) {
		foreach ( $styles as $handle => $style ) {
			$deps = isset( $style['deps'] ) ? $style['deps'] : false;

			wp_register_style( $handle, $style['src'], $deps, SUBSCRPT_VERSION );
		}
	}

	/**
	 * Get all registered scripts
	 *
	 * @return array
	 */
	public function get_scripts() {
		$plugin_js_assets_path = SUBSCRPT_ASSETS . '/js/';

		$block_script_asset_path = SUBSCRPT_PATH . '/build/index.asset.php';
		$block_script_asset      = file_exists( $block_script_asset_path )
			? require $block_script_asset_path
			: array(
				'dependencies' => false,
				'version'      => SUBSCRPT_VERSION,
			);

		// Admin UI components, one file each for readability (assets/js/admin-components/).
		// Each attaches its API to `window` and auto-inits; they are registered as
		// individual scripts and bundled behind the `subscrpt_admin_components` handle.
		$components      = array();
		$component_files = array( 'adv-select', 'tag-select', 'editlist', 'modal', 'tabs', 'accordion', 'pagination' );
		foreach ( $component_files as $component_file ) {
			$handle                = 'subscrpt_component_' . str_replace( '-', '_', $component_file );
			$components[ $handle ] = array(
				'src'       => $plugin_js_assets_path . 'admin-components/' . $component_file . '.js',
				'deps'      => array(),
				'in_footer' => true,
			);
		}

		$scripts = array(
			'sdevs_subscription_admin'  => array(
				'src'       => $plugin_js_assets_path . 'admin.js',
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
			),
			'subscrpt_admin_components' => array(
				'src'       => false,
				'deps'      => array_keys( $components ),
				'in_footer' => true,
			),
			'subscrpt_plan_forms_js'    => array(
				// Shared plan-group + selling-plan modal logic, used by both the
				// Plans admin screen and the product-editor Subscription tab.
				'src'       => $plugin_js_assets_path . 'admin/plan-forms.js',
				'deps'      => array( 'subscrpt_admin_components' ),
				'in_footer' => true,
			),
			'sdevs_installer'           => array(
				'src'       => $plugin_js_assets_path . 'installer.js',
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
			),
			'sdevs_subscrpt_cart_block' => array(
				'src'       => SUBSCRPT_URL . '/build/index.js',
				'deps'      => $block_script_asset['dependencies'],
				'version'   => $block_script_asset['version'],
				'in_footer' => true,
			),
		);

		return array_merge( $components, $scripts );
	}

	/**
	 * Get registered styles
	 *
	 * @return array
	 */
	public function get_styles() {
		$plugin_css_assets_path = SUBSCRPT_ASSETS . '/css/';

		// Admin UI component styles, split by section for readability
		// (assets/css/admin-components/). Loaded in this order behind the
		// `subscrpt_admin_components` handle; `tokens` must stay first.
		$component_styles = array();
		$component_files  = array(
			'tokens',
			'layout',
			'forms',
			'buttons',
			'select',
			'table',
			'badges',
			'dropdown',
			'pagination',
			'empty',
			'toggle',
			'settings',
			'tag-select',
			'modal',
			'editlist',
			'tabs',
			'vnav',
			'accordion',
			'tooltip',
		);
		foreach ( $component_files as $component_file ) {
			$handle                      = 'subscrpt_style_' . str_replace( '-', '_', $component_file );
			$component_styles[ $handle ] = array(
				'src' => $plugin_css_assets_path . 'admin-components/' . $component_file . '.css',
			);
		}

		$styles = array(
			'subscrpt_admin_css'        => array(
				'src' => $plugin_css_assets_path . 'admin.css',
			),
			'subscrpt_admin_components' => array(
				'src'  => false,
				'deps' => array_keys( $component_styles ),
			),
			'subscrpt_status_css'       => array(
				'src' => $plugin_css_assets_path . 'status.css',
			),
			'sdevs_installer'           => array(
				'src' => $plugin_css_assets_path . 'installer.css',
			),
		);

		return array_merge( $component_styles, $styles );
	}

	/**
	 * Enqueue admin component styles on all WPSubscription admin pages.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_components( $hook ) {
		$is_main_page = 'toplevel_page_wp-subscription' === $hook;
		$is_subs_page = str_starts_with( $hook, 'wpsubscription_page' ) || str_starts_with( $hook, 'wp-subscription_page' );

		if ( $is_main_page || $is_subs_page ) {
			wp_enqueue_style( 'subscrpt_admin_components' );
			wp_enqueue_script( 'subscrpt_admin_components' );
		}
	}

	/**
	 * Enqueue chart.js where this method is called.
	 *
	 * Source: https://cdn.jsdelivr.net/npm/chart.js
	 */
	public static function enqueue_chart_js() {
		wp_enqueue_script( 'subscrpt-ext-lib-chartjs', SUBSCRPT_ASSETS . '/js/chart.js', [], SUBSCRPT_VERSION, true );
	}
}
