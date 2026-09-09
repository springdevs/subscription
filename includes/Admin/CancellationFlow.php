<?php
/**
 * Cancellation Flow - admin page.
 *
 * The home of everything that happens when a customer cancels: the reason list
 * customers pick from, and the flow-wide options beside it.
 *
 * These settings used to live on Settings -> Customers -> Cancellation. The
 * move keeps every option key byte-identical, so nothing that reads them had to
 * change - see Illuminate\Cancellation::get_settings() and ::get_reasons(),
 * which remain the only readers.
 *
 * What did have to change is the settings GROUP. wp-admin/options.php writes
 * `null` to every option registered in the submitted group that is absent from
 * the POST, so leaving these four in `wp_subscription_settings` would mean
 * saving the Settings page wiped them - and saving this page wiped everything
 * else. They are registered in their own group instead (see OPTION_GROUP), and
 * Pro registers its two into the same group.
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Cancellation;

/**
 * Cancellation Flow - admin page.
 */
class CancellationFlow {

	/**
	 * Admin page slug.
	 */
	const SLUG = 'wp-subscription-cancellation';

	/**
	 * Settings group for every option on this page.
	 *
	 * Deliberately separate from `wp_subscription_settings`: options.php nulls
	 * anything registered in a group but missing from the posted form, so two
	 * pages must never share one group.
	 */
	const OPTION_GROUP = 'wp_subscription_cancellation_settings';

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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the Cancellation Flow submenu under the WPSubscription top menu.
	 *
	 * @return void
	 */
	public function register_page() {
		$this->hook = (string) add_submenu_page(
			'wp-subscription',
			__( 'Cancellation Flow', 'subscription' ),
			__( 'Cancellation Flow', 'subscription' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Position the item within the WPSubscription submenu order.
	 *
	 * @param array $order Ordered slug => position map.
	 * @return array
	 */
	public function position_submenu( $order ) {
		if ( is_array( $order ) ) {
			$order[ self::SLUG ] = 35;
		}
		return $order;
	}

	/**
	 * Register the two free options this page owns.
	 *
	 * Same option keys as before the move, new group. Pro registers
	 * `subscrpt_cancellation_delay` and `subscrpt_cancellation_reasons` into
	 * this same group from its own Cancellation class.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			'subscrpt_cancellation_feedback_enabled',
			array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'subscrpt_cancellation_feedback_comment',
			array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		/**
		 * Fires so add-ons can register options into the Cancellation Flow group.
		 *
		 * Anything registered here is saved by this page and by no other, which
		 * is the whole point of the separate group.
		 *
		 * @param string $group The settings group name.
		 */
		do_action( 'subscrpt_register_cancellation_settings', self::OPTION_GROUP );
	}

	/**
	 * Enqueue assets only on this page.
	 *
	 * Reuses the shared component bundle plus the settings stylesheet, which
	 * already styles every field type rendered here.
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

		wp_enqueue_style(
			'subscrpt_admin_settings_css',
			SUBSCRPT_ASSETS . '/css/admin-settings.css',
			array( 'subscrpt_admin_components' ),
			SUBSCRPT_VERSION
		);
	}

	/**
	 * The tabs this page shows.
	 *
	 * One for now. Kept as a list so adding the next one is a data change.
	 *
	 * @return array<string,array{label:string}>
	 */
	public static function tabs() {
		return array(
			'reasons' => array( 'label' => __( 'Reasons', 'subscription' ) ),
		);
	}

	/**
	 * The Reasons tab field: the editable reason list.
	 *
	 * Pro-locked exactly as it was on the settings page - Pro registers and
	 * sanitizes the option, and Cancellation::get_reasons() only reads it when
	 * Pro is active.
	 *
	 * @return array
	 */
	public static function reasons_field() {
		return array(
			'id'              => 'subscrpt_cancellation_reasons',
			'title'           => __( 'Cancellation Reasons', 'subscription' ),
			'description'     => __( 'Reasons offered in the cancellation survey form. Shown when Cancellation Survey is enabled.', 'subscription' ),
			'value'           => Cancellation::get_reasons(),
			'modal'           => true,
			'button_label'    => __( 'Manage reasons', 'subscription' ),
			'modal_title'     => __( 'Cancellation Reasons', 'subscription' ),
			'add_placeholder' => __( 'Add a reason…', 'subscription' ),
			'add_label'       => __( 'Add reason', 'subscription' ),
			'empty_text'      => __( 'No reasons yet. Add one below.', 'subscription' ),
			'pro_locked'      => ! subscrpt_pro_activated(),
		);
	}

	/**
	 * The flow-wide options shown in the sidebar, in render order.
	 *
	 * @return array<int,array{type:string,field_data:array}>
	 */
	public static function sidebar_fields() {
		$pro_locked = ! subscrpt_pro_activated();

		return array(
			array(
				'type'       => 'select',
				'field_data' => array(
					'id'          => 'subscrpt_cancellation_delay',
					'title'       => __( 'Cancellation Timing', 'subscription' ),
					'description' => __( 'When a subscription is cancelled, choose when it actually ends.', 'subscription' ),
					'options'     => array(
						'24h'     => __( 'After 24 hours', 'subscription' ),
						'instant' => __( 'Immediately', 'subscription' ),
						'period'  => __( 'At end of billing period (before next renewal)', 'subscription' ),
					),
					'selected'    => esc_attr( Cancellation::get_settings( 'subscrpt_cancellation_delay' ) ),
					'pro_locked'  => $pro_locked,
				),
			),
			array(
				'type'       => 'toggle',
				'field_data' => array(
					'id'          => 'subscrpt_cancellation_feedback_enabled',
					'title'       => __( 'Cancellation Survey', 'subscription' ),
					'label'       => __( 'Ask customers why they are cancelling', 'subscription' ),
					'description' => __( 'Show a short cancellation survey when a customer cancels a subscription, and record the reason for churn tracking.', 'subscription' ),
					'value'       => '1',
					'checked'     => Cancellation::is_feedback_enabled(),
				),
			),
			array(
				'type'       => 'toggle',
				'field_data' => array(
					'id'          => 'subscrpt_cancellation_feedback_comment',
					'title'       => __( 'Survey Comment Box', 'subscription' ),
					'label'       => __( 'Allow an additional comment', 'subscription' ),
					'description' => __( 'Show an optional free-text comment field in the cancellation survey.', 'subscription' ),
					'value'       => '1',
					'checked'     => Cancellation::is_feedback_comment_enabled(),
				),
			),
		);
	}

	/**
	 * Render the page: shared header + the flow view.
	 *
	 * @return void
	 */
	public function render_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing, no state change.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'reasons';

		$tabs = self::tabs();
		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'reasons';
		}

		$menu = new Menu();
		if ( method_exists( $menu, 'render_admin_header' ) ) {
			$menu->render_admin_header(
				'',
				'',
				array( array( 'label' => __( 'Cancellation Flow', 'subscription' ) ) )
			);
		}

		$page_url = admin_url( 'admin.php?page=' . self::SLUG );

		include __DIR__ . '/views/cancellation-flow.php';
	}
}
