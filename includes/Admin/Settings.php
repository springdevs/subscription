<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Class Settings
 *
 * @package SpringDevs\Subscription\Admin
 */
class Settings {
	/**
	 * Settings fields.
	 *
	 * @var array
	 */
	public $settings_fields = [];

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		// Load the settings helper.
		SettingsHelper::get_instance();

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wc_admin_styles' ) );
	}

	/**
	 * Register submenu on `Subscriptions` menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		// Initialize & process settings fields.
		$this->initiate_settings_fields();

		// Add submenu page.
		$parent_slug = 'wp-subscription';
		add_submenu_page(
			$parent_slug,
			__( 'Settings', 'subscription' ),
			__( 'Settings', 'subscription' ),
			'manage_options',
			'wp-subscription-settings',
			[ $this, 'settings_content' ]
		);
	}

	/**
	 * Initialize settings fields.
	 */
	public function initiate_settings_fields() {
		global $wp_roles;
		$roles = [];
		foreach ( ( $wp_roles->roles ?? [] ) as $role_key => $role ) {
			$roles[ $role_key ] = $role['name'];
		}

		// Setting fields.
		$settings_fields = [
			[
				'type'       => 'heading',
				'group'      => 'renewals',
				'priority'   => 0,
				'field_data' => [
					'title' => __( 'Renewals', 'subscription' ),
				],
			],
			[
				'type'       => 'select',
				'group'      => 'renewals',
				'priority'   => 1,
				'field_data' => [
					'id'          => 'wp_subscription_renewal_process',
					'title'       => __( 'Renewal Process', 'subscription' ),
					'description' => __( 'How renewal process will be done after Subscription Expired.', 'subscription' ),
					'options'     => [
						'auto'   => __( 'Automatic', 'subscription' ),
						'manual' => __( 'Manual', 'subscription' ),
					],
					'selected'    => esc_attr( subscrpt_get_renewal_process() ),
				],
			],
			[
				'type'       => 'input',
				'group'      => 'renewals',
				'priority'   => 2,
				'field_data' => [
					'id'          => 'wp_subscription_manual_renew_cart_notice',
					'title'       => __( 'Renewal Cart Notice', 'subscription' ),
					'description' => __( 'Display Notice when Renewal Subscription product add to cart. Only available for Manual Renewal Process.', 'subscription' ),
					'value'       => esc_attr( subscrpt_get_manual_renew_cart_notice() ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'renewals',
				'priority'   => 3,
				'field_data' => [
					'id'          => 'wp_subscription_stripe_auto_renew',
					'title'       => __( 'Stripe Auto Renewal', 'subscription' ),
					'label'       => __( 'Accept Stripe Auto Renewals', 'subscription' ),
					'description' => sprintf(
						/* translators: HTML tags */
						__( '%1$s WooCommerce Stripe Payment Gateway %2$s plugin is required!', 'subscription' ),
						'<a href="https://wordpress.org/plugins/woocommerce-gateway-stripe/" target="_blank">',
						'</a>'
					),
					'value'       => '1',
					'checked'     => '1' === get_option( 'wp_subscription_stripe_auto_renew', '1' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'renewals',
				'priority'   => 4,
				'field_data' => [
					'id'          => 'wp_subscription_auto_renewal_toggle',
					'title'       => __( 'Auto Renewal Toggle', 'subscription' ),
					'label'       => __( 'Display the auto renewal toggle', 'subscription' ),
					'description' => __( 'Allow customers to turn on and off automatic renewals from their Subscription details page', 'subscription' ),
					'value'       => '1',
					'checked'     => '1' === get_option( 'wp_subscription_auto_renewal_toggle', '1' ),
				],
			],
			[
				'type'       => 'select',
				'group'      => 'role_based_settings',
				'priority'   => 2,
				'field_data' => [
					'id'          => 'wp_subscription_active_role',
					'title'       => __( 'Subscriber Default Role', 'subscription' ),
					'description' => __( 'When a subscription is activated, either manually or after a successful purchase, new users will be assigned this role.', 'subscription' ),
					'options'     => $roles,
					'selected'    => esc_attr( get_option( 'wp_subscription_active_role', 'subscriber' ) ),
				],
			],
			[
				'type'       => 'select',
				'group'      => 'role_based_settings',
				'priority'   => 3,
				'field_data' => [
					'id'          => 'wp_subscription_unactive_role',
					'title'       => __( 'Subscriber Inactive Role', 'subscription' ),
					'description' => __( "If a subscriber's subscription is manually cancelled or expires, they will be assigned this role.", 'subscription' ),
					'options'     => $roles,
					'selected'    => esc_attr( get_option( 'wp_subscription_unactive_role', 'customer' ) ),
				],
			],
		];

		// Allow other modules to add/modify settings fields.
		$settings_fields = apply_filters( 'subscrpt_settings_fields', $settings_fields );

		// Process settings fields (group & sort).
		$settings_fields = apply_filters( 'process_subscrpt_settings_fields', $settings_fields );

		// Set the settings fields.
		$this->settings_fields = $settings_fields;
	}

	/**
	 * Register settings options.
	 **/
	public function register_settings() {
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_renewal_process',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_manual_renew_cart_notice',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_active_role',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_unactive_role',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_stripe_auto_renew',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_auto_renewal_toggle',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		do_action( 'subscrpt_register_settings', 'subscrpt_settings' );
	}

	/**
	 * Settings HTML.
	 */
	public function settings_content() {
		$settings_fields = $this->settings_fields;
		$category_groups = SettingsHelper::category_groups( $settings_fields );
		$active_cat      = $this->get_active_category( $category_groups );

		// Header.
		$menu = new Menu();
		$menu->render_admin_header( __( 'Settings', 'subscription' ) );

		include 'views/settings.php';

		// Footer.
		$menu->render_admin_footer();
	}

	/**
	 * Which settings section the page opens on.
	 *
	 * Read from `?cat=`, and it has to be a query argument rather than a
	 * fragment: the form posts to `options.php`, which redirects back to
	 * `_wp_http_referer`, and a fragment never reaches the server. Keeping the
	 * section in the query string is what returns you to the panel you saved
	 * from.
	 *
	 * Validated against the sections that actually hold groups, so an unknown
	 * or hostile value — or one naming a section only Pro fills — opens the
	 * first real section rather than an empty panel.
	 *
	 * @param array<string,string[]> $category_groups Section key => group keys.
	 * @return string Section key, or an empty string when there are no groups.
	 */
	private function get_active_category( array $category_groups ) {
		$cats = array_keys( $category_groups );

		if ( empty( $cats ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only section selection, validated against the list above.
		$requested = isset( $_GET['cat'] ) ? sanitize_key( wp_unslash( $_GET['cat'] ) ) : '';

		if ( in_array( $requested, $cats, true ) ) {
			return $requested;
		}

		return $cats[0];
	}

	/**
	 * Enqueue WooCommerce admin styles for settings page.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_wc_admin_styles( $hook ) {
		// Only load on our settings page
		if ( isset( $_GET['post_type'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['post_type'] ) ), 'subscrpt_order' ) !== false ) {
			// WooCommerce admin styles
			wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), SUBSCRPT_VERSION );
			// Optional: WooCommerce enhanced select2
			wp_enqueue_style( 'woocommerce_admin_select2', WC()->plugin_url() . '/assets/css/select2.css', array(), SUBSCRPT_VERSION );
			wp_enqueue_script( 'select2' );
		}
	}
}
