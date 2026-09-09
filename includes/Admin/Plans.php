<?php
/**
 * Plans - admin class.
 *
 * Registers the Subscription Plans admin screens (list + detail) and enqueues
 * their assets. Data is read live from PlanRepository via PlanPresenter; all
 * writes go through the wpsubscription/v1 REST API (admin-only). The UI is
 * built entirely on the shared `wpsubs-*` component system (admin-components).
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

/**
 * Plans - admin class.
 */
class Plans {

	/**
	 * Admin page slug.
	 */
	const SLUG = 'wp-subscription-plans';

	/**
	 * The page hook returned by add_submenu_page (for the enqueue gate).
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ), 11 );
		add_filter( 'subscrpt_submenu_order', array( $this, 'position_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the Plans submenu under the WPSubscription top menu.
	 *
	 * @return void
	 */
	public function register_page() {
		$this->hook = (string) add_submenu_page(
			'wp-subscription',
			__( 'Plans', 'subscription' ),
			__( 'Plans', 'subscription' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Position the Plans item within the WPSubscription submenu order.
	 *
	 * @param array $order Ordered slug => position map.
	 * @return array
	 */
	public function position_submenu( $order ) {
		if ( is_array( $order ) ) {
			$order[ self::SLUG ] = 10;
		}
		return $order;
	}

	/**
	 * Enqueue Plans assets only on the Plans page.
	 *
	 * Reuses the shared `subscrpt_admin_components` bundle (registered by
	 * Assets) for all styling; adds only the Plans interaction script on top.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( ! $this->hook || $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'subscrpt_admin_components' );
		wp_enqueue_script( 'subscrpt_admin_components' );

		// Shared plan-group + selling-plan modal logic.
		wp_enqueue_script( 'subscrpt_plan_forms_js' );
		wp_localize_script( 'subscrpt_plan_forms_js', 'subscrptPlanForms', self::plan_forms_config() );

		wp_enqueue_script(
			'subscrpt_admin_plans_js',
			SUBSCRPT_ASSETS . '/js/admin/plans.js',
			array( 'subscrpt_admin_components', 'subscrpt_plan_forms_js' ),
			SUBSCRPT_VERSION,
			true
		);

		wp_localize_script(
			'subscrpt_admin_plans_js',
			'subscrptPlans',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'wpsubscription/v1/plans' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'listUrl'  => admin_url( 'admin.php?page=' . self::SLUG ),
				'currency' => function_exists( 'get_woocommerce_currency_symbol' )
					? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
					: '$',
				'i18n'     => array(
					'saved'                  => __( 'Saved.', 'subscription' ),
					'deleted'                => __( 'Deleted.', 'subscription' ),
					'confirmPlan'            => __( 'Delete this plan and all its durations? This cannot be undone.', 'subscription' ),
					/* translators: %d: number of selected plans. */
					'confirmBulkDelete'      => __( 'Delete %d selected plan(s) and all their durations? This cannot be undone.', 'subscription' ),
					'selectPlans'            => __( 'Please select at least one plan.', 'subscription' ),
					'confirmTerm'            => __( 'Delete this duration?', 'subscription' ),
					'confirmRemoveProduct'   => __( 'Remove this product from the plan? It will be detached from every duration.', 'subscription' ),
					'confirmRemoveVariation' => __( 'Remove this variation from the plan? It will be detached from every duration.', 'subscription' ),
					'genericError'           => __( 'Something went wrong. Please try again.', 'subscription' ),
					'nameRequired'           => __( 'Please enter a name.', 'subscription' ),
					'addTerm'                => __( 'Add Duration', 'subscription' ),
					'editTerm'               => __( 'Edit Duration', 'subscription' ),
					/* translators: %1$s: first item number, %2$s: last item number, %3$s: total. */
					'showingRange'           => __( 'Showing %1-%2 of %3', 'subscription' ),
				),
			)
		);
	}

	/**
	 * Config for the shared plan-forms.js module (create group + selling-plan
	 * modals). Shared by the Plans screen and the product-editor Subscription
	 * tab so the term payload contract is localized identically on both.
	 *
	 * @return array
	 */
	public static function plan_forms_config() {
		return array(
			'restUrl' => esc_url_raw( rest_url( 'wpsubscription/v1/plans' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'genericError' => __( 'Something went wrong. Please try again.', 'subscription' ),
				'nameRequired' => __( 'Please enter a name.', 'subscription' ),
				'addTerm'      => __( 'Add Duration', 'subscription' ),
				'editTerm'     => __( 'Edit Duration', 'subscription' ),
			),
		);
	}

	/**
	 * Render the page: header + routed view (list or detail).
	 *
	 * @return void
	 */
	public function render_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view routing, no state change.
		$view    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'list';
		$plan_id = isset( $_GET['plan'] ) ? absint( wp_unslash( $_GET['plan'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$plans    = PlanPresenter::all();
		$list_url = admin_url( 'admin.php?page=' . self::SLUG );

		if ( 'detail' === $view && isset( $plans[ $plan_id ] ) ) {
			$plan = $plans[ $plan_id ];

			$this->render_header(
				array(
					array(
						'label' => __( 'Plans', 'subscription' ),
						'url'   => $list_url,
					),
					array( 'label' => $plan['name'] ),
				)
			);

			include __DIR__ . '/views/plans/detail.php';
			return;
		}

		$this->render_header( array( array( 'label' => __( 'Plans', 'subscription' ) ) ) );
		include __DIR__ . '/views/plans/list.php';
	}

	/**
	 * Render the shared sticky admin header via the Menu class.
	 *
	 * @param array $breadcrumbs Ordered breadcrumb segments.
	 * @return void
	 */
	private function render_header( $breadcrumbs ) {
		$menu = new Menu();
		if ( method_exists( $menu, 'render_admin_header' ) ) {
			$menu->render_admin_header( __( 'Plans', 'subscription' ), '', $breadcrumbs );
		}
	}

	/**
	 * Human label for a plan type.
	 *
	 * @param string $type Plan type key.
	 * @return string
	 */
	public static function type_label( $type ) {
		$labels = array(
			'recurring'      => __( 'Recurring Payment', 'subscription' ),
			'subscribe_save' => __( 'Recurring Delivery', 'subscription' ),
			'installments'   => __( 'Split Payment', 'subscription' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( $type );
	}

	/**
	 * Dashicon class for a plan type.
	 *
	 * @param string $type Plan type key.
	 * @return string
	 */
	public static function type_icon( $type ) {
		$icons = array(
			'recurring'      => 'dashicons-update',
			'subscribe_save' => 'dashicons-cart',
			'installments'   => 'dashicons-money-alt',
		);
		return isset( $icons[ $type ] ) ? $icons[ $type ] : 'dashicons-screenoptions';
	}
}
