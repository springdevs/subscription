<?php
/**
 * Pro Settings Fields — the single source of the Pro settings UI.
 *
 * Defines every Pro settings field (the UI only — no options are registered or
 * saved here; the real settings are registered and processed by the Pro plugin).
 * The fields are always added to the settings page via the
 * `subscrpt_settings_fields` filter. When Pro is **not** active they render
 * locked (disabled, with a "Pro" badge); when Pro is active they become
 * interactive and the Pro plugin saves their values.
 *
 * The lock state is derived from `subscrpt_pro_activated()` and injected onto
 * every field as `pro_locked`, which SettingsHelper uses for the badge and the
 * non-interactive styling.
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class ProSettingsFields
 *
 * @package SpringDevs\Subscription\Admin
 */
class ProSettingsFields {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_filter( 'subscrpt_settings_fields', [ $this, 'add_pro_preview_fields' ] );
	}

	/**
	 * Merge the Pro settings fields into the settings fields list.
	 *
	 * Fields are locked (disabled + Pro badge) only while Pro is inactive.
	 *
	 * @param array $settings_fields Existing settings fields.
	 * @return array
	 */
	public function add_pro_preview_fields( $settings_fields ) {
		$pro_fields = array_merge(
			$this->pro_core_fields(),
			$this->grace_period_fields(),
			$this->payment_failure_fields(),
			$this->api_fields(),
			$this->health_queue_fields(),
			$this->order_fields(),
			$this->switch_fields(),
			$this->role_management_fields(),
			$this->live_qr_fields(),
			$this->payment_gateway_fields()
		);

		// Lock the fields only when Pro is not active.
		$locked = ! subscrpt_pro_activated();
		foreach ( $pro_fields as &$field ) {
			$field['field_data']['pro_locked'] = $locked;
			if ( $locked ) {
				$field['field_data']['disabled'] = true;
			}
		}
		unset( $field );

		return array_merge( $settings_fields, $pro_fields );
	}

	/**
	 * Module: Pro Core (Admin/Settings.php) — general settings additions.
	 *
	 * @return array
	 */
	private function pro_core_fields() {
		return [
			[
				'type'       => 'select',
				'group'      => 'main',
				'priority'   => 7,
				'field_data' => [
					'id'          => 'subscrpt_renewal_price',
					'title'       => __( 'Renewal Price', 'subscription' ),
					'description' => __( 'Choose a price that will be used for subscription renewal.', 'subscription' ),
					'options'     => [
						'subscribed' => __( 'Subscribed Price', 'subscription' ),
						'updated'    => __( 'New/Updated Price', 'subscription' ),
					],
					'selected'    => esc_attr( get_option( 'subscrpt_renewal_price', 'subscribed' ) ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'main',
				'priority'   => 8,
				'field_data' => [
					'id'          => 'subscrpt_early_renew',
					'title'       => __( 'Early Renewal', 'subscription' ),
					'label'       => __( 'Accept Early Renewal Payments', 'subscription' ),
					'description' => __( 'With early renewals enabled, customers can renew their subscriptions before the next payment date.', 'subscription' ),
					'value'       => '1',
					'checked'     => '1' === get_option( 'subscrpt_early_renew', '1' ),
				],
			],
			[
				'type'       => 'select',
				'group'      => 'main',
				'priority'   => 9,
				'field_data' => [
					'id'          => 'subscrpt_cancellation_delay',
					'title'       => __( 'Cancellation Timing', 'subscription' ),
					'description' => __( 'When a subscription is cancelled, choose when it actually ends.', 'subscription' ),
					'options'     => [
						'24h'     => __( 'After 24 hours', 'subscription' ),
						'instant' => __( 'Immediately', 'subscription' ),
						'period'  => __( 'At end of billing period (before next renewal)', 'subscription' ),
					],
					'selected'    => esc_attr( \SpringDevs\Subscription\Illuminate\Cancellation::get_settings( 'subscrpt_cancellation_delay' ) ),
				],
			],
			[
				'type'       => 'editlist',
				'group'      => 'main',
				'priority'   => 9.6,
				'field_data' => [
					'id'              => 'subscrpt_cancellation_reasons',
					'title'           => __( 'Cancellation Reasons', 'subscription' ),
					'description'     => __( 'Reasons offered in the cancellation survey form. Shown when Cancellation Survey is enabled.', 'subscription' ),
					'value'           => \SpringDevs\Subscription\Illuminate\Cancellation::get_reasons(),
					'modal'           => true,
					'button_label'    => __( 'Manage reasons', 'subscription' ),
					'modal_title'     => __( 'Cancellation Reasons', 'subscription' ),
					'add_placeholder' => __( 'Add a reason…', 'subscription' ),
					'add_label'       => __( 'Add reason', 'subscription' ),
					'empty_text'      => __( 'No reasons yet. Add one below.', 'subscription' ),
				],
			],
		];
	}

	/**
	 * Module: Grace Period (Admin/Settings.php).
	 *
	 * @return array
	 */
	private function grace_period_fields() {
		return [
			[
				'type'       => 'heading',
				'group'      => 'grace_period',
				'priority'   => 3,
				'field_data' => [
					'title' => __( 'Grace Period Settings', 'subscription' ),
				],
			],
			[
				'type'       => 'input',
				'group'      => 'grace_period',
				'priority'   => 1,
				'field_data' => [
					'id'          => 'subscrpt_default_payment_grace_period',
					'title'       => __( 'Grace Period (Days)', 'subscription' ),
					'description' => __( 'Days to maintain access after subscriptions expires. (0 = No grace period. Max 30 days)', 'subscription' ),
					'value'       => esc_attr( get_option( 'subscrpt_default_payment_grace_period', '7' ) ),
					'type'        => 'number',
					'attributes'  => [
						'min' => 0,
						'max' => 30,
					],
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'grace_period',
				'priority'   => 2,
				'field_data' => [
					'id'          => 'subscrpt_enable_grace_period_notifications',
					'title'       => __( 'Grace Period Notifications', 'subscription' ),
					'label'       => __( 'Send notifications during grace period', 'subscription' ),
					'description' => __( 'Customers will receive warnings before their access is suspended.', 'subscription' ),
					'value'       => '1',
					'checked'     => '1' === get_option( 'subscrpt_enable_grace_period_notifications', '1' ),
				],
			],
			[
				'type'       => 'input',
				'group'      => 'grace_period',
				'priority'   => 3,
				'field_data' => [
					'id'          => 'subscrpt_grace_period_warning_days',
					'title'       => __( 'Grace Period Warning (Days Before)', 'subscription' ),
					'description' => __( 'Send warning emails this many days before grace period expires. (1-7 days)', 'subscription' ),
					'value'       => esc_attr( get_option( 'subscrpt_grace_period_warning_days', '2' ) ),
					'type'        => 'number',
					'attributes'  => [
						'min' => 1,
						'max' => 7,
					],
				],
			],
		];
	}

	/**
	 * Module: Payment Failure Handling (Admin/Settings.php).
	 *
	 * @return array
	 */
	private function payment_failure_fields() {
		return [
			[
				'type'       => 'heading',
				'group'      => 'payment_failure',
				'priority'   => 4,
				'field_data' => [
					'title' => __( 'Payment Failure Handling', 'subscription' ),
				],
			],
			[
				'type'       => 'input',
				'group'      => 'payment_failure',
				'priority'   => 1,
				'field_data' => [
					'id'          => 'subscrpt_default_max_payment_retries',
					'title'       => __( 'Default Max Payment Retries', 'subscription' ),
					'description' => __( 'Default number of automatic retry attempts for failed payments. (0 = No retries. Max 10 retries)', 'subscription' ),
					'value'       => esc_attr( get_option( 'subscrpt_default_max_payment_retries', '3' ) ),
					'type'        => 'number',
					'attributes'  => [
						'min' => 0,
						'max' => 10,
					],
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'payment_failure',
				'priority'   => 2,
				'field_data' => [
					'id'          => 'subscrpt_enable_payment_failure_emails',
					'title'       => __( 'Enable Payment Failure Emails', 'subscription' ),
					'label'       => __( 'Send email notifications when payments fail', 'subscription' ),
					'description' => __( 'Customers will receive emails about failed payments and retry attempts.', 'subscription' ),
					'value'       => '1',
					'checked'     => '1' === get_option( 'subscrpt_enable_payment_failure_emails', '1' ),
				],
			],
			[
				'type'       => 'input',
				'group'      => 'payment_failure',
				'priority'   => 3,
				'field_data' => [
					'id'          => 'subscrpt_payment_failure_email_delay',
					'title'       => __( 'Payment Failure Email Delay (Hours)', 'subscription' ),
					'description' => __( 'Delay before sending payment failure emails to avoid spam during temporary issues. (0-168 hours)', 'subscription' ),
					'value'       => esc_attr( get_option( 'subscrpt_payment_failure_email_delay', '24' ) ),
					'type'        => 'number',
					'attributes'  => [
						'min' => 0,
						'max' => 168,
					],
				],
			],
		];
	}

	/**
	 * Module: API Settings (Admin/Settings.php).
	 *
	 * @return array
	 */
	private function api_fields() {
		return [
			[
				'type'       => 'heading',
				'group'      => 'api_settings',
				'priority'   => 99,
				'field_data' => [
					'title' => __( 'API Settings', 'subscription' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'api_settings',
				'priority'   => 1,
				'field_data' => [
					'id'          => 'wpsubscription_api_enabled',
					'title'       => __( 'Enable API', 'subscription' ),
					'description' => __( 'Enable REST API endpoints for subscription actions', 'subscription' ),
					'value'       => 'on',
					'checked'     => 'on' === get_option( 'wpsubscription_api_enabled', 'on' ),
				],
			],
			[
				'type'       => 'join',
				'group'      => 'api_settings',
				'priority'   => 2,
				'field_data' => [
					'title'       => __( 'API Key', 'subscription' ),
					'description' => __( 'API key for external integrations. Keep this secure.', 'subscription' ),
					'elements'    => [
						SettingsHelper::inp_element(
							[
								'id'         => 'wpsubscription_api_key',
								'value'      => esc_attr( get_option( 'wpsubscription_api_key', '' ) ),
								'type'       => 'text',
								'style'      => 'min-width:366px;',
								'attributes' => [
									'readonly' => true,
								],
							],
							true
						),
						'<span class="wpsubs-tooltip" data-tip="' . esc_attr__( 'Regenerate API Key', 'subscription' ) . '">'
						. '<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--icon" id="regenerate_api_key" aria-label="' . esc_attr__( 'Regenerate API Key', 'subscription' ) . '">'
						. '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>'
						. '</button>'
						. '</span>',
					],
				],
			],
			[
				'type'       => 'input',
				'group'      => 'api_settings',
				'priority'   => 3,
				'field_data' => [
					'id'          => 'subscrpt_api_endpoint_url',
					'title'       => __( 'REST API Endpoint URL', 'subscription' ),
					'description' => __( 'Copy and use this endpoint for all API actions.', 'subscription' ),
					'value'       => esc_url( home_url( '/wp-json/wpsubscription/v1/action' ) ),
					'type'        => 'text',
					'attributes'  => [
						'readonly' => true,
					],
				],
			],
		];
	}

	/**
	 * Module: Subscription Health (Admin/Settings.php).
	 *
	 * @return array
	 */
	private function health_queue_fields() {
		return [
			[
				'type'       => 'heading',
				'group'      => 'health_queue',
				'priority'   => 8,
				'field_data' => [
					'title' => __( 'Subscription Health', 'subscription' ),
				],
			],
			[
				'type'       => 'select',
				'group'      => 'health_queue',
				'priority'   => 2,
				'field_data' => [
					'id'          => 'subscrpt_health_queue_email_frequency',
					'title'       => __( 'Health Queue Email Frequency', 'subscription' ),
					'description' => __( 'How often to send the admin health queue reminder email. The email is only sent when subscriptions need attention.', 'subscription' ),
					'options'     => [
						'daily'            => __( 'Daily', 'subscription' ),
						'subscrpt_weekly'  => __( 'Weekly', 'subscription' ),
						'subscrpt_monthly' => __( 'Monthly', 'subscription' ),
						'never'            => __( 'Never', 'subscription' ),
					],
					'selected'    => esc_attr( get_option( 'subscrpt_health_queue_email_frequency', 'subscrpt_weekly' ) ),
				],
			],
		];
	}

	/**
	 * Module: Order (Illuminate/Order.php) — auto complete orders.
	 *
	 * @return array
	 */
	private function order_fields() {
		return [
			[
				'type'       => 'toggle',
				'group'      => 'payment_gateways',
				'priority'   => 1,
				'field_data' => [
					'id'          => 'wp_subscription_auto_complete_order',
					'title'       => __( 'Auto Complete Orders', 'subscription' ),
					'description' => __( 'Automatically change status of processing orders to completed.', 'subscription' ),
					'value'       => '1',
					'checked'     => '1' === get_option( 'wp_subscription_auto_complete_order', '1' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'payment_gateways',
				'priority'   => 2,
				'field_data' => [
					'id'          => 'subscrpt_require_payment_on_trial',
					'title'       => __( 'Require Payment for Free Trials', 'subscription' ),
					'label'       => __( 'Collect payment details at checkout for trial subscriptions', 'subscription' ),
					'description' => __( 'Collect payment details up front on free trials so renewals charge automatically.', 'subscription' ),
					'value'       => '1',
					'checked'     => '1' === get_option( 'subscrpt_require_payment_on_trial', '1' ),
				],
			],
		];
	}

	/**
	 * Module: Switch Subscription (Illuminate/SubscriptionSwitch.php).
	 *
	 * @return array
	 */
	private function switch_fields() {
		return [
			[
				'type'       => 'toggle',
				'group'      => 'general',
				'priority'   => 9,
				'field_data' => [
					'id'          => 'subscrpt_switch_enabled',
					'title'       => __( 'Switch Subscription', 'subscription' ),
					'label'       => __( 'Allow users to upgrade/downgrade their subscriptions.', 'subscription' ),
					'description' => '',
					'value'       => '1',
					'checked'     => '1' === get_option( 'subscrpt_switch_enabled', '0' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'general',
				'priority'   => 10,
				'field_data' => [
					'id'          => 'subscrpt_downgrade_allowed',
					'title'       => __( 'Downgrade Subscription', 'subscription' ),
					'label'       => __( 'Allow users to downgrade their subscriptions.', 'subscription' ),
					'description' => '',
					'value'       => '1',
					'checked'     => '1' === get_option( 'subscrpt_downgrade_allowed', '1' ),
				],
			],
			[
				'type'       => 'join',
				'group'      => 'general',
				'priority'   => 11,
				'field_data' => [
					'title'       => __( 'Switch Fee', 'subscription' ),
					'description' => __( 'Optional fee charged when a customer switches plans. Use a flat amount or a percentage of the new plan price. (0 = no fee)', 'subscription' ),
					'elements'    => [
						SettingsHelper::inp_element(
							[
								'id'         => 'subscrpt_switch_fee_amount',
								'value'      => esc_attr( get_option( 'subscrpt_switch_fee_amount', '0' ) ),
								'type'       => 'number',
								'style'      => 'min-width:170px;width:170px;',
								'attributes' => [
									'min'  => 0,
									'step' => '0.01',
								],
							],
							true
						),
						SettingsHelper::select_element(
							[
								'id'       => 'subscrpt_switch_fee_type',
								'class'    => 'subscrpt-switch-fee-type',
								'options'  => [
									'flat'    => sprintf(
										/* translators: %s: store currency symbol. */
										__( 'Flat (%s)', 'subscription' ),
										get_woocommerce_currency_symbol()
									),
									'percent' => __( 'Percentage (%)', 'subscription' ),
								],
								'selected' => esc_attr( get_option( 'subscrpt_switch_fee_type', 'flat' ) ),
							],
							true
						),
					],
				],
			],
			[
				'type'       => 'select',
				'group'      => 'general',
				'priority'   => 12,
				'field_data' => [
					'id'          => 'subscrpt_switch_fee_apply_to',
					'title'       => __( 'Apply Fee To', 'subscription' ),
					'description' => __( 'Choose which switch directions the fee applies to.', 'subscription' ),
					'options'     => [
						'both'      => __( 'Both upgrade and downgrade', 'subscription' ),
						'upgrade'   => __( 'Upgrade only', 'subscription' ),
						'downgrade' => __( 'Downgrade only', 'subscription' ),
					],
					'selected'    => esc_attr( get_option( 'subscrpt_switch_fee_apply_to', 'both' ) ),
				],
			],
		];
	}

	/**
	 * Module: Role Management (Illuminate/RoleManagement.php).
	 *
	 * @return array
	 */
	private function role_management_fields() {
		return [
			[
				'type'       => 'heading',
				'group'      => 'role_based_settings',
				'priority'   => 6,
				'field_data' => [
					'title' => __( 'Role-Based Settings', 'subscription' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'role_based_settings',
				'priority'   => 1,
				'field_data' => [
					'id'          => 'subscrpt_role_based_access',
					'title'       => __( 'Enable Role Based Access', 'subscription' ),
					'description' => __( 'Show/Hide products based on user roles.', 'subscription' ),
					'value'       => '1',
					'checked'     => '1' === get_option( 'subscrpt_role_based_access', '1' ),
				],
			],
		];
	}

	/**
	 * Module: Live QR (Illuminate/LiveQR/LiveQR.php) — quick details QR.
	 *
	 * @return array
	 */
	private function live_qr_fields() {
		return [
			[
				'type'       => 'heading',
				'group'      => 'live_qr_settings',
				'priority'   => 5,
				'field_data' => [
					'title' => __( 'Quick Details QR Settings', 'subscription' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'live_qr_settings',
				'priority'   => 1,
				'field_data' => [
					'id'      => 'subscrpt_live_qr_active',
					'title'   => __( 'Enable Quick Details QR', 'subscription' ),
					'label'   => '',
					'value'   => '1',
					'checked' => '1' === get_option( 'subscrpt_live_qr_active', '1' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'live_qr_settings',
				'priority'   => 2,
				'field_data' => [
					'id'      => 'subscrpt_live_qr_show_product',
					'title'   => __( 'Product Details', 'subscription' ),
					'label'   => __( 'Show product details in the subscription quick view', 'subscription' ),
					'value'   => '1',
					'checked' => '1' === get_option( 'subscrpt_live_qr_show_product', '1' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'live_qr_settings',
				'priority'   => 3,
				'field_data' => [
					'id'      => 'subscrpt_live_qr_show_billing',
					'title'   => __( 'Billing Details', 'subscription' ),
					'label'   => __( 'Show billing details in the subscription quick view', 'subscription' ),
					'value'   => '1',
					'checked' => '1' === get_option( 'subscrpt_live_qr_show_billing', '0' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'live_qr_settings',
				'priority'   => 4,
				'field_data' => [
					'id'      => 'subscrpt_live_qr_show_timeline',
					'title'   => __( 'Timeline', 'subscription' ),
					'label'   => __( 'Show subscription timeline in the subscription quick view', 'subscription' ),
					'value'   => '1',
					'checked' => '1' === get_option( 'subscrpt_live_qr_show_timeline', '0' ),
				],
			],
		];
	}

	/**
	 * Module: Payment Gateways (Illuminate/Gateways/PaymentGateways.php).
	 *
	 * @return array
	 */
	private function payment_gateway_fields() {
		$gateway_options = [];
		if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
			foreach ( WC()->payment_gateways->get_available_payment_gateways() as $gateway_id => $gateway ) {
				$gateway_options[ $gateway_id ] = $gateway->get_title();
			}
		}

		return [
			[
				'type'       => 'heading',
				'group'      => 'payment_gateways',
				'priority'   => 0.1,
				'field_data' => [
					'title' => __( 'Payment Gateway Settings', 'subscription' ),
				],
			],
			[
				'type'       => 'multi_select',
				'group'      => 'payment_gateways',
				'priority'   => 2,
				'field_data' => [
					'id'          => 'hidden_payment_gateways',
					'title'       => __( 'Hide Payment Gateways', 'subscription' ),
					'description' => __( 'Select payment gateways to hide for subscription products. (keep empty for not hiding any)', 'subscription' ),
					'options'     => $gateway_options,
					'selected'    => get_option( 'hidden_payment_gateways', [] ),
				],
			],
		];
	}
}
