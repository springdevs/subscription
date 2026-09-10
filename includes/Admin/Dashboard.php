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
 * The screen is a small React app (src/dashboard/). Everything it shows is
 * computed here and handed over preloaded — there is no REST round trip,
 * because none of these figures change while the page is open.
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
		$menu->render_admin_header( __( 'Overview', 'subscription' ) );
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
		$setup  = $this->get_setup( $counts );

		return array(
			'pulse'  => $this->get_pulse( $counts ),
			'chart'  => $this->get_chart(),
			'setup'  => $setup,
			'health' => $this->get_health( $counts, $setup ),
			'build'  => $this->get_build_cards(),
			'footer' => $this->get_footer_links(),
			'isPro'  => subscrpt_pro_activated(),
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
				'icon'  => 'people',
				'label' => __( 'Active subscriptions', 'subscription' ),
				'value' => (int) ( $counts['active'] ?? 0 ),
				'url'   => add_query_arg( 'post_status', 'active', $list ),
			),
			array(
				'key'   => 'on_hold',
				'icon'  => 'pause',
				'label' => __( 'On-hold subscriptions', 'subscription' ),
				'value' => $on_hold,
				'url'   => add_query_arg( 'post_status', 'on_hold', $list ),
				'tone'  => $on_hold > 0 ? 'warning' : '',
			),
			array(
				'key'   => 'due',
				'icon'  => 'money',
				'label' => __( 'Renewals due (next 7 days)', 'subscription' ),
				'value' => Stats::count_renewals_due_within( 7 ),
				'url'   => $list,
			),
			array(
				'key'   => 'failed',
				'icon'  => 'alert',
				'label' => __( 'Failed renewals (last 24h)', 'subscription' ),
				'value' => $failed,
				'url'   => admin_url( 'edit.php?post_type=shop_order&post_status=wc-failed' ),
				'tone'  => $failed > 0 ? 'error' : '',
			),
			array(
				'key'   => 'new',
				'icon'  => 'trend',
				'label' => __( 'New subscriptions (this week)', 'subscription' ),
				'value' => Stats::count_new_since( 7 ),
				'url'   => $list,
			),
		);
	}

	/**
	 * Monthly subscription revenue for the chart.
	 *
	 * Values are pre-formatted here rather than in the browser: the store's
	 * currency, decimal separator and symbol position all live in WooCommerce
	 * settings, and reimplementing wc_price() in JavaScript gets them wrong for
	 * every locale that is not the developer's.
	 *
	 * @return array<string,mixed>
	 */
	private function get_chart(): array {
		$months = Stats::get_monthly_revenue( 6 );
		$total  = 0.0;

		foreach ( $months as &$month ) {
			$total           += $month['total'];
			$month['display'] = function_exists( 'wc_price' )
				? wp_strip_all_tags( html_entity_decode( wc_price( $month['total'] ), ENT_QUOTES, 'UTF-8' ) )
				: number_format_i18n( $month['total'], 2 );
		}
		unset( $month );

		return array(
			'months'  => $months,
			'total'   => $total,
			'display' => function_exists( 'wc_price' )
				? wp_strip_all_tags( html_entity_decode( wc_price( $total ), ENT_QUOTES, 'UTF-8' ) )
				: number_format_i18n( $total, 2 ),
			'empty'   => $total <= 0,
			'url'     => admin_url( 'admin.php?page=wp-subscription-stats' ),
		);
	}

	/**
	 * The setup checklist.
	 *
	 * Always the same three items, each carrying whether it is done — a
	 * checklist that hides what you have finished gives no sense of progress,
	 * and the whole block is dropped once everything is ticked.
	 *
	 * @param array<string,int> $counts Status counts.
	 * @return array<string,mixed>
	 */
	private function get_setup( array $counts ): array {
		unset( $counts );

		$items = array(
			array(
				'id'     => 'gateway',
				'label'  => __( 'Enable a payment gateway', 'subscription' ),
				'done'   => $this->has_enabled_gateway(),
				'action' => array(
					'label' => __( 'Set up', 'subscription' ),
					'url'   => admin_url( 'admin.php?page=wp-subscription-integrations' ),
				),
			),
			array(
				'id'     => 'product',
				'label'  => __( 'Add a subscription product', 'subscription' ),
				'done'   => $this->has_subscription_product(),
				'action' => array(
					'label' => __( 'Add', 'subscription' ),
					'url'   => admin_url( 'post-new.php?post_type=product' ),
				),
			),
			array(
				'id'     => 'permalinks',
				'label'  => __( 'Turn on pretty permalinks', 'subscription' ),
				'done'   => (bool) get_option( 'permalink_structure' ),
				'action' => array(
					'label' => __( 'Change', 'subscription' ),
					'url'   => admin_url( 'options-permalink.php' ),
				),
			),
		);

		$done = count(
			array_filter(
				$items,
				static function ( $item ) {
					return ! empty( $item['done'] );
				}
			)
		);

		return array(
			'items'    => $items,
			'done'     => $done,
			'total'    => count( $items ),
			'complete' => $done === count( $items ),
		);
	}

	/**
	 * The banner across the top of the page.
	 *
	 * One sentence answering "is anything wrong". Setup gaps outrank
	 * subscription states: an unpaid-for store has nothing to be healthy about.
	 *
	 * @param array<string,int>   $counts Status counts.
	 * @param array<string,mixed> $setup  Setup checklist.
	 * @return array<string,mixed>
	 */
	private function get_health( array $counts, array $setup ): array {
		if ( empty( $setup['complete'] ) ) {
			$remaining = (int) $setup['total'] - (int) $setup['done'];

			return array(
				'state' => 'attention',
				'title' => __( 'Finish setting up', 'subscription' ),
				/* translators: %d: number of remaining setup steps. */
				'text'  => sprintf( _n( '%d step left before your store can sell subscriptions.', '%d steps left before your store can sell subscriptions.', $remaining, 'subscription' ), $remaining ),
			);
		}

		$on_hold = (int) ( $counts['on_hold'] ?? 0 );

		if ( $on_hold > 0 ) {
			return array(
				'state'  => 'attention',
				'title'  => __( 'Some subscriptions need a look', 'subscription' ),
				/* translators: %d: number of on-hold subscriptions. */
				'text'   => sprintf( _n( '%d subscription is on hold.', '%d subscriptions are on hold.', $on_hold, 'subscription' ), $on_hold ),
				'action' => array(
					'label' => __( 'Review them', 'subscription' ),
					'url'   => add_query_arg( 'post_status', 'on_hold', admin_url( 'admin.php?page=wp-subscription-list' ) ),
				),
			);
		}

		$active = (int) ( $counts['active'] ?? 0 );

		return array(
			'state' => 'clear',
			'title' => __( 'All subscriptions look healthy', 'subscription' ),
			/* translators: %d: number of active subscriptions. */
			'text'  => sprintf( _n( '%d active subscription, nothing needs attention.', '%d active subscriptions, nothing needs attention.', $active, 'subscription' ), $active ),
		);
	}

	/**
	 * The three cards along the bottom.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_build_cards(): array {
		$is_pro = subscrpt_pro_activated();

		return array(
			array(
				'tone'    => 'insight',
				'icon'    => 'chart',
				'eyebrow' => __( 'Insight', 'subscription' ),
				'title'   => __( 'Open reports', 'subscription' ),
				'text'    => __( 'Revenue, active subscriptions and growth over time.', 'subscription' ),
				'link'    => array(
					'label' => __( 'View reports', 'subscription' ),
					'url'   => admin_url( 'admin.php?page=wp-subscription-stats' ),
				),
			),
			array(
				'tone'    => 'setup',
				'icon'    => 'card',
				'eyebrow' => __( 'Setup', 'subscription' ),
				'title'   => __( 'Configure payments', 'subscription' ),
				'text'    => __( 'Connect PayPal, Stripe, Paddle and more from one screen.', 'subscription' ),
				'link'    => array(
					'label' => __( 'Open integrations', 'subscription' ),
					'url'   => admin_url( 'admin.php?page=wp-subscription-integrations' ),
				),
			),
			$is_pro
				? array(
					'tone'    => 'extend',
					'icon'    => 'shield',
					'eyebrow' => __( 'Extend', 'subscription' ),
					'title'   => __( 'Subscription health', 'subscription' ),
					'text'    => __( 'Find and recover subscriptions that need rescuing.', 'subscription' ),
					'link'    => array(
						'label' => __( 'Open health', 'subscription' ),
						'url'   => admin_url( 'admin.php?page=wp-subscription-health' ),
					),
				)
				: array(
					'tone'    => 'extend',
					'icon'    => 'shield',
					'eyebrow' => __( 'Extend', 'subscription' ),
					'title'   => __( 'WPSubscription Pro', 'subscription' ),
					'text'    => __( 'Payment retries, a health queue and revenue reporting.', 'subscription' ),
					'link'    => array(
						'label'    => __( 'See what Pro adds', 'subscription' ),
						'url'      => 'https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=dashboard',
						'external' => true,
					),
				),
		);
	}

	/**
	 * The centred link row at the very bottom.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_footer_links(): array {
		return array(
			array(
				'label' => __( 'Documentation', 'subscription' ),
				'url'   => 'https://docs.wpsubscription.co/en?utm_source=plugin&utm_medium=admin&utm_campaign=dashboard',
			),
			array(
				'label' => __( 'Get support', 'subscription' ),
				'url'   => 'https://wpsubscription.co/contact?utm_source=plugin&utm_medium=admin&utm_campaign=dashboard',
			),
			array(
				'label' => __( 'My account', 'subscription' ),
				'url'   => 'https://my.wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=dashboard',
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
