<?php
/**
 * Dashboard screen.
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Stats;

/**
 * Builds the dashboard payload and renders its container.
 *
 * The screen itself is a small React app (src/dashboard/) built on
 *
 * @wordpress/components. Everything it shows is computed here and handed over
 * as preloaded data — there is no REST round trip, because none of these
 * figures change while the page is open.
 */
class Dashboard {

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'subscrpt-dashboard';

	/**
	 * Hook the screen's assets.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	/**
	 * Enqueue the bundle, but only on the dashboard screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function maybe_enqueue( $hook ) {
		if ( 'toplevel_page_wp-subscription' !== $hook ) {
			return;
		}

		$this->enqueue();
	}

	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function render() {
		$menu = new Menu();
		$menu->render_admin_header( __( 'Dashboard', 'subscription' ) );
		include __DIR__ . '/views/dashboard.php';
		$menu->render_admin_footer();
	}

	/**
	 * Enqueue the dashboard bundle.
	 *
	 * @return void
	 */
	public function enqueue() {
		$asset_file = SUBSCRPT_PATH . '/build/dashboard.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			self::HANDLE,
			SUBSCRPT_URL . '/build/dashboard.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		/*
		 * WordPress ships the stylesheet for @wordpress/components separately
		 * from the script. Without this the components render as unstyled
		 * markup — which is why plugins that skip it end up reinventing every
		 * button in their own CSS.
		 */
		wp_enqueue_style( 'wp-components' );

		wp_enqueue_style(
			self::HANDLE,
			SUBSCRPT_URL . '/build/dashboard.css',
			array( 'wp-components' ),
			$asset['version']
		);

		// The build emits dashboard-rtl.css alongside dashboard.css; this is
		// what makes WordPress pick it up for right-to-left locales.
		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );

		wp_set_script_translations( self::HANDLE, 'subscription' );

		wp_add_inline_script(
			self::HANDLE,
			'window.subscrptDashboard = ' . wp_json_encode( $this->get_data() ) . ';',
			'before'
		);
	}

	/**
	 * Everything the dashboard renders.
	 *
	 * @return array<string,mixed>
	 */
	public function get_data(): array {
		$counts = Stats::get_status_counts();

		return array(
			'pulse'     => $this->get_pulse( $counts ),
			'attention' => $this->get_attention( $counts ),
			'links'     => $this->get_links(),
			'isPro'     => subscrpt_pro_activated(),
			'proUrl'    => 'https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=dashboard',
		);
	}

	/**
	 * The five figures.
	 *
	 * @param array<string,int> $counts Status counts.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_pulse( array $counts ): array {
		$list = admin_url( 'admin.php?page=wp-subscription-list' );

		// Each of these runs a query, so call them once.
		$on_hold = (int) ( $counts['on_hold'] ?? 0 );
		$failed  = Stats::count_failed_renewals_since( 24 );

		return array(
			array(
				'key'   => 'active',
				'label' => __( 'Active subscriptions', 'subscription' ),
				'value' => $counts['active'] ?? 0,
				'url'   => add_query_arg( 'post_status', 'active', $list ),
			),
			array(
				'key'   => 'on_hold',
				'label' => __( 'On-hold subscriptions', 'subscription' ),
				'value' => $on_hold,
				'url'   => add_query_arg( 'post_status', 'on_hold', $list ),
				'tone'  => $on_hold > 0 ? 'warning' : '',
			),
			array(
				'key'   => 'due',
				'label' => __( 'Renewals due (next 7 days)', 'subscription' ),
				'value' => Stats::count_renewals_due_within( 7 ),
				'url'   => $list,
			),
			array(
				'key'   => 'failed',
				'label' => __( 'Failed renewals (last 24h)', 'subscription' ),
				'value' => $failed,
				'url'   => admin_url( 'edit.php?post_type=shop_order&post_status=wc-failed' ),
				'tone'  => $failed > 0 ? 'error' : '',
			),
			array(
				'key'   => 'new',
				'label' => __( 'New subscriptions (this week)', 'subscription' ),
				'value' => Stats::count_new_since( 7 ),
				'url'   => $list,
			),
		);
	}

	/**
	 * Things the store owner should do something about.
	 *
	 * Setup gaps come first: they are the reasons the plugin silently does
	 * nothing on a fresh install, and no amount of subscription data matters
	 * until they are cleared.
	 *
	 * @param array<string,int> $counts Status counts.
	 * @return array<int,array<string,string>>
	 */
	private function get_attention( array $counts ): array {
		$items = array();

		if ( ! $this->has_enabled_gateway() ) {
			$items[] = array(
				'id'     => 'gateway',
				'status' => 'error',
				'text'   => __( 'No payment gateway is enabled, so no subscription can be paid for.', 'subscription' ),
				'label'  => __( 'Set up a gateway', 'subscription' ),
				'url'    => admin_url( 'admin.php?page=wp-subscription-integrations' ),
			);
		}

		if ( ! $this->has_subscription_product() ) {
			$items[] = array(
				'id'     => 'product',
				'status' => 'warning',
				'text'   => __( 'No product has subscriptions enabled yet.', 'subscription' ),
				'label'  => __( 'Add a product', 'subscription' ),
				'url'    => admin_url( 'post-new.php?post_type=product' ),
			);
		}

		if ( ! get_option( 'permalink_structure' ) ) {
			$items[] = array(
				'id'     => 'permalinks',
				'status' => 'warning',
				'text'   => __( 'Plain permalinks are on. My Account subscription pages need pretty permalinks.', 'subscription' ),
				'label'  => __( 'Change permalinks', 'subscription' ),
				'url'    => admin_url( 'options-permalink.php' ),
			);
		}

		$on_hold = (int) ( $counts['on_hold'] ?? 0 );
		if ( $on_hold > 0 ) {
			$items[] = array(
				'id'     => 'on_hold',
				'status' => 'warning',
				/* translators: %d: number of on-hold subscriptions. */
				'text'   => sprintf( _n( '%d subscription is on hold.', '%d subscriptions are on hold.', $on_hold, 'subscription' ), $on_hold ),
				'label'  => __( 'Review them', 'subscription' ),
				'url'    => add_query_arg( 'post_status', 'on_hold', admin_url( 'admin.php?page=wp-subscription-list' ) ),
			);
		}

		return $items;
	}

	/**
	 * Quick links out of the dashboard.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_links(): array {
		return array(
			array(
				'label' => __( 'Subscriptions', 'subscription' ),
				'url'   => admin_url( 'admin.php?page=wp-subscription-list' ),
			),
			array(
				'label' => __( 'Reports', 'subscription' ),
				'url'   => admin_url( 'admin.php?page=wp-subscription-stats' ),
			),
			array(
				'label' => __( 'Settings', 'subscription' ),
				'url'   => admin_url( 'admin.php?page=wp-subscription-settings' ),
			),
			array(
				'label'    => __( 'Documentation', 'subscription' ),
				'url'      => 'https://docs.wpsubscription.co/en?utm_source=plugin&utm_medium=admin&utm_campaign=dashboard',
				'external' => true,
			),
		);
	}

	/**
	 * Whether any gateway this plugin supports is switched on.
	 *
	 * @return bool
	 */
	private function has_enabled_gateway(): bool {
		foreach ( array( 'wp_subscription_paypal', 'stripe', 'smartpay_paddle' ) as $gateway ) {
			if ( Integrations::is_gateway_enabled( $gateway ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether any product has subscriptions turned on.
	 *
	 * @return bool
	 */
	private function has_subscription_product(): bool {
		$found = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_subscrpt_enabled',
						'value' => 1,
					),
				),
			)
		);

		return ! empty( $found );
	}
}
