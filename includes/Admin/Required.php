<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Install & Activate required plugins
 *
 * Class Required
 */
class Required {

	private $plugin_file = true;

	/**
	 * Names of the plugins this one needs but does not have active.
	 *
	 * @var string[]
	 */
	private $required_plugins = array();

	public function __construct() {
		add_action( 'init', array( $this, 'check_plugins' ) );
	}

	public function check_plugins() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';

		if ( ! file_exists( $plugin_file ) ) {
			$this->plugin_file = false;
		}

		// Only wire the notice, its assets, and the plugins-list row when a
		// dependency is actually missing — the same gating the pro plugin uses,
		// so the red row never lingers once WooCommerce is active.
		if ( ! file_exists( $plugin_file ) || ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$this->required_plugins[] = 'WooCommerce';

			add_action( 'admin_notices', array( $this, 'install_plugin_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'after_plugin_row_' . plugin_basename( SUBSCRPT_FILE ), array( $this, 'required_plugins_row' ), 10, 2 );
		}
	}

	/**
	 * Render a warning row below this plugin's row on the plugins list page.
	 *
	 * Mirrors the pro plugin's row: flags that the plugin is not fully active
	 * and lists what it still needs.
	 *
	 * @return void
	 */
	public function required_plugins_row() {
		if ( empty( $this->required_plugins ) ) {
			return;
		}

		$show_auto_updates = function_exists( 'wp_is_auto_update_enabled_for_type' ) && wp_is_auto_update_enabled_for_type( 'plugin' );
		?>
		<tr class="active subscrpt-plugin-warning-row">
			<th scope="row" class="check-column">
				<span class="dashicons dashicons-warning"></span>
			</th>
			<td class="column-primary">
				<p><?php esc_html_e( 'This plugin is not fully active', 'subscription' ); ?></p>
			</td>
			<td class="column-description desc">
				<div class="plugin-description">
					<strong><?php esc_html_e( 'Required Plugins', 'subscription' ); ?></strong>
				</div>
				<ul>
					<?php foreach ( $this->required_plugins as $plugin_name ) : ?>
						<li><?php echo esc_html( $plugin_name ); ?></li>
					<?php endforeach; ?>
				</ul>
			</td>
			<?php if ( $show_auto_updates ) : ?>
				<td class="column-auto-updates">
					<span class="label"></span>
					<div class="notice notice-error notice-alt inline hidden"><p></p></div>
				</td>
			<?php endif; ?>
		</tr>
		<?php
	}

	public function enqueue_assets() {
		// Only load the notice styles/script — including the red plugins-list row
		// tint — when a dependency is actually missing. Otherwise the row would
		// stay tinted even after WooCommerce is active.
		if ( empty( $this->required_plugins ) ) {
			return;
		}

		// Version the installer stylesheet by file mtime so CSS edits bust the
		// browser cache: the plugin version is a build-time placeholder and does
		// not change in place, so it cannot do that on its own.
		$installer_css = SUBSCRPT_PATH . '/assets/css/installer.css';
		if ( file_exists( $installer_css ) && wp_style_is( 'sdevs_installer', 'registered' ) ) {
			wp_styles()->registered['sdevs_installer']->ver = (string) filemtime( $installer_css );
		}

		if ( ! wp_style_is( 'sdevs_installer' ) ) {
			wp_enqueue_style( 'sdevs_installer' );
		}

		if ( ! wp_script_is( 'sdevs_installer' ) ) {
			wp_enqueue_script( 'sdevs_installer' );
			wp_localize_script(
				'sdevs_installer',
				'sdevs_installer_helper_obj',
				array( 'ajax_url' => admin_url( 'admin-ajax.php' ) )
			);
		}
	}

	public function install_plugin_notice() {
		if ( $this->plugin_file ) {
			$id    = 'sdevs-activate-plugin';
			$label = __( 'Activate Woocommerce', 'subscription' );
		} else {
			$id    = 'sdevs-install-plugin';
			$label = __( 'Install Woocommerce', 'subscription' );
		}

		include 'views/required-notice.php';
	}
}
