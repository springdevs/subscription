<?php

namespace SpringDevs\Subscription\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrations class
 *
 * @package SpringDevs\Subscription\Admin
 */
class Integrations {
	/**
	 * Integrations list.
	 *
	 * @var array
	 */
	protected $integrations = [];

	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Initialize.
		add_action( 'init', [ $this, 'init' ], 10 );

		// Admin menu (sidebar).
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 20 );

		// WPSubscription navbar.
		add_filter( 'subscrpt_admin_header_menu_items', [ $this, 'add_integrations_menu_item' ], 10, 2 );

		// Enqueue integrations scripts.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_integrations_scripts' ] );

		// Integrations AJAX handler.
		// add_action( 'admin_ajax_integrations_handler', [ $this,'integrations_handler_callback' ] );
		// add_action( 'admin_ajax_nopriv_integrations_handler', [ $this,'integrations_handler_callback' ] );
	}

	/**
	 * Initialize the integrations.
	 */
	public function init() {
		// Set integrations.
		$this->integrations = $this->get_integrations();
	}

	/**
	 * Register submenu under `subscriptions` menu.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		$parent_slug = 'wp-subscription';
		add_submenu_page(
			$parent_slug,
			__( 'Integrations', 'subscription' ),
			__( 'Integrations', 'subscription' ),
			'manage_options',
			'wp-subscription-integrations',
			[ $this, 'render_integrations_page' ],
		);
	}

	/**
	 * Add Integrations link to the WPSubscription admin header menu.
	 *
	 * @param array  $menu_items Array of menu items.
	 * @param string $current Current active menu item slug.
	 */
	public function add_integrations_menu_item( $menu_items, $current ) {
		$menu_items[] = [
			'slug'  => 'wp-subscription-integrations',
			'label' => __( 'Integrations', 'subscription' ),
			'url'   => admin_url( 'admin.php?page=wp-subscription-integrations' ),
		];
		return $menu_items;
	}

	/**
	 * Enqueue scripts for integrations page.
	 */
	public function enqueue_integrations_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'wp-subscription-integrations' ) ) {
			return;
		}

		wp_enqueue_script( 'subscrpt-integrations', SUBSCRPT_ASSETS . '/js/integration_settings.js', [], SUBSCRPT_VERSION, true );

		wp_localize_script(
			'subscrpt-integrations',
			'subscrptIntegrations',
			array(
				'nonce'   => wp_create_nonce( 'subscrpt_integration_install_nonce' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Handle AJAX request for integrations.
	 */
	public function integrations_handler_callback() {
		check_ajax_referer( 'wp_subs_integrations_nonce', 'nonce' );

		$action_callback = ! empty( $_POST['action_callback'] ) ? sanitize_text_field( wp_unslash( $_POST['action_callback'] ) ) : '';

		dd( '🔽 action_callback', $action_callback );
	}

	/**
	 * Check if a payment gateway is installed and active.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @param bool   $partial_match Whether to allow partial match of gateway ID.
	 * @return bool
	 */
	public static function is_gateway_installed( $gateway_id, $partial_match = false ) {
		$installed_gateways = WC()->payment_gateways()->payment_gateways();

		// Partial match check. For gateways with dynamic IDs.
		if ( $partial_match ) {
			foreach ( $installed_gateways as $key => $gateway ) {
				if ( strpos( $key, $gateway_id ) !== false ) {
					return true;
				}
			}
			return false;
		}

		return isset( $installed_gateways[ $gateway_id ] );
	}

	/**
	 * Check if a payment gateway is enabled.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @param bool   $partial_match Whether to allow partial match of gateway ID.
	 * @return bool
	 */
	public static function is_gateway_enabled( $gateway_id, $partial_match = false ) {
		$installed_gateways = WC()->payment_gateways()->payment_gateways();

		// Partial match check. For gateways with dynamic IDs.
		if ( $partial_match ) {
			foreach ( $installed_gateways as $key => $gateway ) {
				if ( strpos( $key, $gateway_id ) !== false ) {
					return $gateway->is_available();
				}
			}
			return false;
		}

		if ( ! isset( $installed_gateways[ $gateway_id ] ) ) {
			return false;
		}
		return $installed_gateways[ $gateway_id ]->is_available();
	}



	/**
	 * Check if a gateway is installed by plugin file.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return bool
	 */
	protected function is_plugin_installed( $plugin_file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		return isset( $plugins[ $plugin_file ] );
	}

	/**
	 * Check if a gateway plugin is active.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return bool
	 */
	protected function is_plugin_active( $plugin_file ) {
		return is_plugin_active( $plugin_file );
	}

	/**
	 * Check if a payment gateway is enabled.
	 *
	 * @param string $gateway_id Gateway ID.
	 */
	public function is_payment_gateway_enabled( $gateway_id ) {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		return isset( $gateways[ $gateway_id ] );
	}

	/**
	 * Get the list of integrations.
	 *
	 * @return array
	 */
	protected function get_integrations(): array {
		$integrations = [
			'paypal'   => [
				'title'              => 'PayPal',
				'description'        => 'Accept recurring subscription payments directly through PayPal.',
				'icon_url'           => SUBSCRPT_ASSETS . '/images/integrations/paypal.svg',
				'type'               => 'payment_gateway',
				'is_installed'       => 'on' === get_option( 'wp_subs_paypal_integration_enabled', 'off' ),
				'is_active'          => self::is_gateway_enabled( 'wp_subscription_paypal' ),
				'supports_recurring' => true,
				'actions'            => [
					// [
					// 'action'   => 'install',
					// 'label'    => 'Install Now',
					// 'type'     => 'function',
					// 'function' => 'wpSubsInstallPaypalIntegration()',
					// ],
					[
						'action' => 'settings',
						'label'  => 'Settings',
						'type'   => 'link',
						'url'    => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wp_subscription_paypal' ),
					],
					// [
					// 'action'   => 'uninstall',
					// 'label'    => 'Uninstall',
					// 'type'     => 'function',
					// 'function' => 'wpSubsUninstallPaypalIntegration()',
					// 'class'    => 'button button-primary wp-subs-button-danger',
					// ],
				],
			],
			'stripe'   => [
				'title'              => 'Stripe',
				'description'        => 'Process subscription payments securely with Stripe.',
				'icon_url'           => 'https://ps.w.org/woocommerce-gateway-stripe/assets/icon-256x256.png',
				'type'               => 'payment_gateway',
				'is_installed'       => class_exists( 'WC_Stripe' ),
				'is_active'          => self::is_gateway_enabled( 'stripe' ),
				'supports_recurring' => true,
				'actions'            => [
					[
						'action'   => 'install',
						'label'    => 'Install Now',
						'type'     => 'function',
						'function' => "subscrptInstallPlugin(this, 'woocommerce-gateway-stripe')",
					],
					[
						'action' => 'settings',
						'label'  => 'Settings',
						'type'   => 'link',
						'url'    => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings' ),
					],
				],
			],
			'paddle'   => [
				'title'              => 'Paddle',
				'description'        => 'Process subscription payments securely with Paddle.',
				'icon_url'           => SUBSCRPT_ASSETS . '/images/integrations/paddle.svg',
				'type'               => 'payment_gateway',
				'is_installed'       => class_exists( 'SmartPayWoo\Gateways\Paddle\SmartPay_Paddle' ),
				'is_active'          => self::is_gateway_enabled( 'smartpay_paddle' ),
				'supports_recurring' => true,
				'actions'            => [
					[
						'action' => 'install',
						'label'  => 'Get Paddle',
						'type'   => 'external_link',
						'url'    => 'https://wpsmartpay.com/paddle-for-woocommerce/',
					],
					[
						'action'     => 'enable',
						'label'      => 'Enable Gateway',
						'type'       => 'toggle_option',
						'option_key' => 'woocommerce_enable_paddle_gateway',
						'value'      => true,
					],
					[
						'action' => 'settings',
						'label'  => 'Settings',
						'type'   => 'link',
						'url'    => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=smartpay_paddle&from=WCADMIN_PAYMENT_SETTINGS' ),
					],
					[
						'label' => 'More Details',
						'type'  => 'external_link',
						'url'   => 'https://wpsmartpay.com/paddle-for-woocommerce/',
					],
				],
			],
			'mollie'   => [
				'title'              => 'Mollie',
				'description'        => 'Pay for subscriptions with Mollie Payments for WooCommerce.',
				'icon_url'           => SUBSCRPT_ASSETS . '/images/integrations/mollie.png',
				'type'               => 'payment_gateway',
				'is_pro'             => true,
				'is_installed'       => class_exists( 'Mollie\WooCommerce\Activation\ActivationModule' ),
				'is_active'          => self::is_gateway_enabled( 'mollie_wc_gateway', true ),
				'supports_recurring' => true,
				'actions'            => [
					[
						'action'   => 'install',
						'label'    => 'Install Now',
						'type'     => 'function',
						'function' => "subscrptInstallPlugin(this, 'mollie-payments-for-woocommerce')",
					],
					[
						'action' => 'settings',
						'label'  => 'Settings',
						'type'   => 'link',
						'url'    => admin_url( 'admin.php?page=wc-settings&tab=mollie_settings' ),
					],
					[
						'label' => 'More Details',
						'type'  => 'external_link',
						'url'   => 'https://docs.wpsubscription.co/en/wpsubscription-payment-with-mollie?utm_source=plugin&utm_medium=admin&utm_campaign=docs',
					],
				],
			],
			'razorpay' => [
				'title'              => 'Razorpay',
				'description'        => 'Pay for subscriptions securely with Razorpay for WooCommerce.',
				'icon_url'           => SUBSCRPT_ASSETS . '/images/integrations/razorpay.png',
				'type'               => 'payment_gateway',
				'is_pro'             => true,
				'is_beta'            => true,
				'is_installed'       => class_exists( 'WC_Razorpay' ),
				'is_active'          => self::is_gateway_enabled( 'razorpay' ),
				'supports_recurring' => true,
				'actions'            => [
					[
						'action'   => 'install',
						'label'    => 'Install Now',
						'type'     => 'function',
						'function' => "subscrptInstallPlugin(this, 'woo-razorpay')",
					],
					[
						'action' => 'settings',
						'label'  => 'Settings',
						'type'   => 'link',
						'url'    => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=razorpay' ),
					],
					[
						'label' => 'More Details',
						'type'  => 'external_link',
						'url'   => 'https://docs.wpsubscription.co/en/wpsubscription-payment-with-razorpay?utm_source=plugin&utm_medium=admin&utm_campaign=docs',
					],
				],
			],
			'xendit'   => [
				'title'              => 'Xendit',
				'description'        => 'Pay for subscriptions securely with Xendit for WooCommerce.',
				'icon_url'           => SUBSCRPT_ASSETS . '/images/integrations/xendit.png',
				'type'               => 'payment_gateway',
				'is_pro'             => true,
				'is_beta'            => true,
				'is_installed'       => class_exists( 'WC_Xendit_CC' ),
				'is_active'          => self::is_gateway_enabled( 'xendit' ),
				'supports_recurring' => true,
				'actions'            => [
					[
						'action'   => 'install',
						'label'    => 'Install Now',
						'type'     => 'function',
						'function' => "subscrptInstallPlugin(this, 'woo-xendit-virtual-accounts')",
					],
					[
						'action' => 'settings',
						'label'  => 'Settings',
						'type'   => 'link',
						'url'    => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=xendit_gateway' ),
					],
					[
						'label' => 'More Details',
						'type'  => 'external_link',
						'url'   => 'https://docs.wpsubscription.co/en/wpsubscription-payment-with-xendit?utm_source=plugin&utm_medium=admin&utm_campaign=docs',
					],
				],
			],
		];

		// Third-party integrations (requires Pro plugin to function).
		$third_party = [
			// LMS.
			'tutor_lms'   => [
				'title'        => 'Tutor LMS',
				'description'  => 'Restrict course access based on subscription status. Enroll and unenroll students automatically.',
				'icon_url'     => 'https://ps.w.org/tutor/assets/icon-256x256.gif',
				'type'         => 'third_party',
				'is_pro'       => true,
				'category'     => 'lms',
				'is_installed' => is_plugin_active( 'tutor/tutor.php' ) && class_exists( 'TUTOR\Tutor' ),
				'is_active'    => is_plugin_active( 'tutor/tutor.php' ) && class_exists( 'TUTOR\Tutor' ),
				'actions'      => [
					[
						'action'   => 'install',
						'label'    => __( 'Install Now', 'subscription' ),
						'type'     => 'function',
						'function' => "subscrptInstallPlugin(this, 'tutor')",
					],
					[
						'label' => __( 'Learn More', 'subscription' ),
						'type'  => 'external_link',
						'url'   => 'https://docs.wpsubscription.co/en/wpsubscription-tutor-lms?utm_source=plugin&utm_medium=admin&utm_campaign=docs',
					],
				],
			],
			'learnpress'  => [
				'title'        => 'LearnPress',
				'description'  => 'Connect subscriptions with LearnPress courses. Enroll users automatically when subscriptions are active.',
				'icon_url'     => 'https://ps.w.org/learnpress/assets/icon-256x256.gif',
				'type'         => 'third_party',
				'is_pro'       => true,
				'category'     => 'lms',
				'is_installed' => is_plugin_active( 'learnpress/learnpress.php' ) && class_exists( 'LearnPress' ),
				'is_active'    => is_plugin_active( 'learnpress/learnpress.php' ) && class_exists( 'LearnPress' ),
				'actions'      => [
					[
						'action'   => 'install',
						'label'    => __( 'Install Now', 'subscription' ),
						'type'     => 'function',
						'function' => "subscrptInstallPlugin(this, 'learnpress')",
					],
					[
						'label' => __( 'Learn More', 'subscription' ),
						'type'  => 'external_link',
						'url'   => 'https://docs.wpsubscription.co/en/wpsubscription-learnpress-lms?utm_source=plugin&utm_medium=admin&utm_campaign=docs',
					],
				],
			],
			'learndash'   => [
				'title'        => 'LearnDash',
				'description'  => 'Sync subscription status with LearnDash group enrollment and course access.',
				'icon_url'     => SUBSCRPT_ASSETS . '/images/integrations/learndash.jpeg',
				'type'         => 'third_party',
				'is_pro'       => true,
				'category'     => 'lms',
				'is_installed' => is_plugin_active( 'sfwd-lms/sfwd_lms.php' ) && class_exists( 'LearnDash\Core\App' ),
				'is_active'    => is_plugin_active( 'sfwd-lms/sfwd_lms.php' ) && class_exists( 'LearnDash\Core\App' ),
				'actions'      => [
					[
						'action' => 'install',
						'label'  => __( 'Get LearnDash', 'subscription' ),
						'type'   => 'external_link',
						'url'    => 'https://www.learndash.com/',
					],
					[
						'label' => __( 'Learn More', 'subscription' ),
						'type'  => 'external_link',
						'url'   => 'https://docs.wpsubscription.co/en/wpsubscription-learndash-lms?utm_source=plugin&utm_medium=admin&utm_campaign=docs',
					],
				],
			],
			// CRM.
			'fluentcrm'   => [
				'title'        => 'FluentCRM',
				'description'  => 'Trigger email sequences and manage contacts based on subscription events and status changes.',
				'icon_url'     => 'https://ps.w.org/fluent-crm/assets/icon-256x256.png',
				'type'         => 'third_party',
				'is_pro'       => true,
				'category'     => 'crm',
				'is_installed' => class_exists( 'FluentCrm\App\Services\Funnel\BaseTrigger' ),
				'is_active'    => class_exists( 'FluentCrm\App\Services\Funnel\BaseTrigger' ),
				'actions'      => [
					[
						'action'   => 'install',
						'label'    => __( 'Install Now', 'subscription' ),
						'type'     => 'function',
						'function' => "subscrptInstallPlugin(this, 'fluent-crm')",
					],
					[
						'label' => __( 'Learn More', 'subscription' ),
						'type'  => 'external_link',
						'url'   => 'https://docs.wpsubscription.co/en/wpsubscription-fluent-crm?utm_source=plugin&utm_medium=admin&utm_campaign=docs',
					],
				],
			],
			// Automation.
			'automatorwp' => [
				'title'        => 'AutomatorWP',
				'description'  => 'Build powerful automations triggered by subscription events without writing any code.',
				'icon_url'     => 'https://ps.w.org/automatorwp/assets/icon-256x256.png',
				'type'         => 'third_party',
				'is_pro'       => true,
				'category'     => 'automation',
				'is_installed' => is_plugin_active( 'automatorwp/automatorwp.php' ) && class_exists( 'AutomatorWP' ),
				'is_active'    => is_plugin_active( 'automatorwp/automatorwp.php' ) && class_exists( 'AutomatorWP' ),
				'actions'      => [
					[
						'action'   => 'install',
						'label'    => __( 'Install Now', 'subscription' ),
						'type'     => 'function',
						'function' => "subscrptInstallPlugin(this, 'automatorwp')",
					],
					// [
					// 'label' => __( 'Learn More', 'subscription' ),
					// 'type'  => 'external_link',
					// 'url'   => 'https://docs.wpsubscription.co/en/',
					// ],
				],
			],
			'wpfusion'    => [
				'title'        => 'WP Fusion',
				'description'  => 'Sync subscription data with your CRM and marketing platforms through WP Fusion.',
				'icon_url'     => 'https://ps.w.org/wp-fusion-lite/assets/icon-256x256.png',
				'type'         => 'third_party',
				'is_pro'       => true,
				'category'     => 'automation',
				'is_installed' => class_exists( 'WPF_Integrations_Base' ),
				'is_active'    => class_exists( 'WPF_Integrations_Base' ),
				'actions'      => [
					[
						'action' => 'install',
						'label'  => __( 'Get WP Fusion', 'subscription' ),
						'type'   => 'external_link',
						'url'    => 'https://wpfusion.com/',
					],
					// [
					// 'label' => __( 'Learn More', 'subscription' ),
					// 'type'  => 'external_link',
					// 'url'   => 'https://docs.wpsubscription.co/en/',
					// ],
				],
			],
			// Email Marketing.
			'mailpoet'    => [
				'title'        => 'MailPoet',
				'description'  => 'Add subscribers to MailPoet lists and trigger email automations based on subscription lifecycle events.',
				'icon_url'     => 'https://ps.w.org/mailpoet/assets/icon-256x256.png',
				'type'         => 'third_party',
				'is_pro'       => true,
				'category'     => 'email',
				'is_installed' => is_plugin_active( 'mailpoet/mailpoet.php' ) || is_plugin_active( 'mailpoet-premium/mailpoet-premium.php' ),
				'is_active'    => is_plugin_active( 'mailpoet/mailpoet.php' ) || is_plugin_active( 'mailpoet-premium/mailpoet-premium.php' ),
				'actions'      => [
					[
						'action'   => 'install',
						'label'    => __( 'Install Now', 'subscription' ),
						'type'     => 'function',
						'function' => "subscrptInstallPlugin(this, 'mailpoet')",
					],
					[
						'label' => __( 'Learn More', 'subscription' ),
						'type'  => 'external_link',
						'url'   => 'https://docs.wpsubscription.co/en/wpsubscription-mailpoet?utm_source=plugin&utm_medium=admin&utm_campaign=docs',
					],
				],
			],
			// License Management.
			'wp_soft_lic' => [
				'title'        => 'WP Software License',
				'description'  => 'Issue and validate software licenses for subscription-based digital products.',
				'icon_url'     => SUBSCRPT_ASSETS . '/images/integrations/wp-software-license.png',
				'type'         => 'third_party',
				'is_pro'       => true,
				'category'     => 'license',
				'is_installed' => is_plugin_active( 'software-license/software-license.php' ) && class_exists( 'WOO_SL' ),
				'is_active'    => is_plugin_active( 'software-license/software-license.php' ) && class_exists( 'WOO_SL' ),
				'actions'      => [
					[
						'action' => 'install',
						'label'  => __( 'Get Plugin', 'subscription' ),
						'type'   => 'external_link',
						'url'    => 'https://wpsoftwarelicense.com/',
					],
					// [
					// 'label' => __( 'Learn More', 'subscription' ),
					// 'type'  => 'external_link',
					// 'url'   => 'https://docs.wpsubscription.co/en/',
					// ],
				],
			],
			'license_mgr' => [
				'title'        => 'License Manager for WooCommerce',
				'description'  => 'Generate and manage software license keys that are automatically tied to active subscriptions.',
				'icon_url'     => 'https://ps.w.org/license-manager-for-woocommerce/assets/icon-256x256.gif',
				'type'         => 'third_party',
				'is_pro'       => true,
				'category'     => 'license',
				'is_installed' => is_plugin_active( 'license-manager-for-woocommerce/license-manager-for-woocommerce.php' ) && class_exists( 'LicenseManagerForWooCommerce\Models\Resources\License' ),
				'is_active'    => is_plugin_active( 'license-manager-for-woocommerce/license-manager-for-woocommerce.php' ) && class_exists( 'LicenseManagerForWooCommerce\Models\Resources\License' ),
				'actions'      => [
					[
						'action'   => 'install',
						'label'    => __( 'Install Now', 'subscription' ),
						'type'     => 'function',
						'function' => "subscrptInstallPlugin(this, 'license-manager-for-woocommerce')",
					],
					// [
					// 'label' => __( 'Learn More', 'subscription' ),
					// 'type'  => 'external_link',
					// 'url'   => 'https://docs.wpsubscription.co/en/',
					// ],
				],
			],
		];
		$integrations = array_merge( $integrations, $third_party );

		// Add more integrations as needed.
		$integrations = apply_filters( 'wpsubs_integrations', $integrations );

		return $integrations;
	}

	/**
	 * Filter actions.
	 *
	 * @param array $integrations Integrations array.
	 */
	protected function filter_integration_actions( array $integrations ): array {
		$cleaned_integrations = [];

		foreach ( $integrations as $integration ) {
			$is_installed = $integration['is_installed'] ?? false;
			$is_active    = $integration['is_active'] ?? false;

			$cleaned_actions = [];

			foreach ( $integration['actions'] as $integration_action ) {
				$action_tag = $integration_action['action'] ?? null;

				if ( 'install' === $action_tag ) {
					if ( ! $is_installed ) {
						$cleaned_actions[] = $integration_action;
					}
					continue;
				}
				if ( 'uninstall' === $action_tag ) {
					if ( $is_installed ) {
						$cleaned_actions[] = $integration_action;
					}
					continue;
				}
				if ( 'enable' === $action_tag ) {
					if ( $is_installed && ! $is_active ) {
						$cleaned_actions[] = $integration_action;
					}
					continue;
				}
				if ( 'settings' === $action_tag ) {
					if ( $is_installed ) {
						$cleaned_actions[] = $integration_action;
					}
					continue;
				}

				// Default.
				$cleaned_actions[] = $integration_action;
			}

			// Overwrite.
			$integration['actions'] = $cleaned_actions;
			$cleaned_integrations[] = $integration;
		}

		return $cleaned_integrations;
	}

	/**
	 * Render the Integrations admin page.
	 */
	public function render_integrations_page() {
		$integrations = $this->integrations;
		$integrations = $this->filter_integration_actions( $integrations );

		// Integrations styles.
		// wp_enqueue_style( 'wp-subs-integration-settings', SUBSCRPT_ASSETS . '/css/integration_settings.css', [], SUBSCRPT_VERSION, 'all' );

		// Filtering happens in the browser against cards already in the DOM, so
		// this is behaviour rather than rendering — see the file's header.
		wp_enqueue_script(
			'subscrpt-integrations-filter',
			SUBSCRPT_ASSETS . '/js/admin/integrations-filter.js',
			array(),
			SUBSCRPT_VERSION,
			true
		);

		$menu = new \SpringDevs\Subscription\Admin\Menu();
		$menu->render_admin_header( __( 'Integrations', 'subscription' ) );
		include 'views/integrations.php';
		$menu->render_admin_footer();
	}
}
