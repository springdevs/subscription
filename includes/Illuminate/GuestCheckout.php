<?php
/**
 * Guest Checkout File
 *
 * @package SpringDevs\Subscription\Illuminate
 */

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class GuestCheckout
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class GuestCheckout {
	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Add guest checkout settings
		add_filter( 'subscrpt_settings_fields', [ $this, 'add_guest_checkout_settings_fields' ] );
		add_action( 'subscrpt_register_settings', [ $this, 'register_settings' ] );

		// Show warning if guest checkout is disabled in WooCommerce settings.
		add_action( 'admin_notices', [ $this, 'check_woocommerce_checkout_settings' ] );

		// Guest checkout validation.
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_guest_checkout' ] );
		add_action( 'woocommerce_store_api_cart_errors', [ $this, 'validate_guest_checkout_storeapi' ] );

		// Enforce Login/Registration in checkout
		add_action( 'woocommerce_checkout_process', [ $this, 'require_account_creation' ] );
		add_filter( 'woocommerce_store_api_checkout_update_order_from_request', [ $this,'require_account_creation_store_api' ], 10, 2 );

		// Guest account creation.
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', [ $this, 'maybe_create_user_from_customer' ], 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ $this, 'maybe_assign_user_to_order' ], 5, 1 );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'maybe_assign_user_to_order' ], 10, 1 );
	}

	/**
	 * Add Guest Checkout Settings Fields
	 *
	 * @param array $settings_fields Settings fields.
	 * @return array
	 */
	public function add_guest_checkout_settings_fields( $settings_fields ) {
		$guest_checkout_fields = [
			[
				'type'       => 'heading',
				'group'      => 'guest_checkout',
				'priority'   => 2,
				'field_data' => [
					'title'       => __( 'Guest Checkout', 'subscription' ),
					'description' => __( 'Manage guest checkout settings for subscriptions.', 'subscription' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'guest_checkout',
				'priority'   => 1,
				'field_data' => [
					'id'          => 'wp_subscription_allow_guest_checkout',
					'title'       => __( 'Allow Guest Checkout', 'subscription' ),
					'description' => __( 'Allow customers to checkout without logging in.', 'subscription' ) . '<br/><sub>' . __( 'Note: You will need to enable <strong>Guest checkout</strong> and <strong>Allow customers to create an account during checkout</strong> options in WooCommerce settings for this to work properly.', 'subscription' ) . '</sub>',
					'value'       => '1',
					'checked'     => '1' === get_option( 'wp_subscription_allow_guest_checkout', '0' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'guest_checkout',
				'priority'   => 2,
				'field_data' => [
					'id'          => 'wp_subscription_enforce_login',
					'title'       => __( 'Enforce Login', 'subscription' ),
					'description' => __( 'Force customers to login or check the "Create account" checkbox before checking out.', 'subscription' ),
					'value'       => '1',
					'checked'     => '1' === get_option( 'wp_subscription_enforce_login', '1' ),
				],
			],
		];

		return array_merge( $settings_fields, $guest_checkout_fields );
	}

	/**
	 * Register settings option.
	 */
	public function register_settings() {
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_allow_guest_checkout',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'wp_subscription_settings',
			'wp_subscription_enforce_login',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
	}

	/**
	 * Is guest checkout allowed
	 */
	public static function is_guest_checkout_allowed() {
		return in_array( get_option( 'wp_subscription_allow_guest_checkout', '0' ), [ 1, '1' ], true );
	}

	/**
	 * Is guest login enforced
	 */
	public static function is_guest_login_enforced() {
		return in_array( get_option( 'wp_subscription_enforce_login', '1' ), [ 1, '1' ], true );
	}

	/**
	 * Check if cart/order has subscription and guest checkout are allowed.
	 */
	public function is_subs_and_guest_checkout_allowed() {
		$is_user_logged_in         = is_user_logged_in();
		$is_guest_checkout_allowed = self::is_guest_checkout_allowed();
		$cart_have_subscription    = false;

		// Check in cart.
		if ( function_exists( 'WC' ) ) {
			$cart_items             = WC()->cart->get_cart();
			$recurrs                = Helper::get_recurrs_from_cart( $cart_items );
			$cart_have_subscription = count( $recurrs ) > 0;
		}

		if ( $cart_have_subscription ) {
			return $is_user_logged_in || $is_guest_checkout_allowed;
		} else {
			return true;
		}
	}

	/**
	 * Check WooCommerce checkout settings and show admin notice if guest checkout is disabled.
	 */
	public function check_woocommerce_checkout_settings() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$subs_guest_checkout_allowed = self::is_guest_checkout_allowed();
		if ( ! $subs_guest_checkout_allowed ) {
			return;
		}

		$guest_checkout_enabled  = in_array( get_option( 'woocommerce_enable_guest_checkout' ), [ 1, '1', 'yes', 'on' ], true );
		$account_during_checkout = in_array( get_option( 'woocommerce_enable_signup_and_login_from_checkout' ), [ 1, '1', 'yes', 'on' ], true );
		$account_after_checkout  = in_array( get_option( 'woocommerce_enable_delayed_account_creation' ), [ 1, '1', 'yes', 'on' ], true );

		$issues = [];
		if ( ! $guest_checkout_enabled ) {
			$issues[] = 'Guest checkout.';
		}
		if ( ! $account_during_checkout ) {
			$issues[] = 'Account creation during checkout.';
		}

		if ( ! empty( $issues ) ) {
			$settings_url = admin_url( 'admin.php?page=wc-settings&tab=account' );

			$list_html = '';
			foreach ( $issues as $issue ) {
				$list_html .= '<li><span class="dashicons dashicons-arrow-right"></span> <strong>' . $issue . '</strong></li>';
			}

			$requirement_html = '<div class="notice notice-error is-dismissible">' .
				'<p>To ensure Subscriptions guest checkout functions correctly, please enable the following settings in WooCommerce. ' .
				'Click <a href="' . $settings_url . '">here</a> to go to the settings.</p>' .
				'<ul>' . $list_html . '</ul></div>';

			echo wp_kses_post( $requirement_html );
		}

		if ( $account_after_checkout ) {
			$settings_url = admin_url( 'admin.php?page=wc-settings&tab=account' );

			$requirement_html = '<div class="notice notice-warning is-dismissible">' .
				'<p>Enabling <strong>Account creation after checkout</strong> in WooCommerce settings may lead to issues with subscription orders for guest users.</p>' .
				'<p>It\'s recommended to disable this option for optimal functionality with Subscriptions. Click <a href="' . $settings_url . '">here</a> to go to the settings.</p></div>';

			echo wp_kses_post( $requirement_html );
		}
	}

	/**
	 * Validate guest checkout.
	 */
	public function validate_guest_checkout() {
		if ( ! $this->is_subs_and_guest_checkout_allowed() ) {
			wc_add_notice( __( 'You are trying to buy a subscription. You must be logged in to continue.', 'subscription' ), 'error' );
			return;
		}
	}

	/**
	 * Validate guest checkout on storeAPI.
	 *
	 * @param \WP_Error $errors Errors object.
	 * @return \WP_Error
	 */
	public function validate_guest_checkout_storeapi( $errors ) {
		if ( ! $this->is_subs_and_guest_checkout_allowed() ) {
			$errors->add( 'wp_subscription_login_required', __( 'You are trying to buy a subscription. You must be logged in to continue.', 'subscription' ) );
			return $errors;
		}
	}

	/**
	 * Enforce account creation in checkout.
	 */
	public function require_account_creation() {
		if ( is_user_logged_in() || ! self::is_guest_checkout_allowed() ) {
			return;
		}

		// Check cart for subscriptions.
		$cart_have_subscription = false;
		if ( function_exists( 'WC' ) ) {
			$cart_items             = WC()->cart->get_cart();
			$recurrs                = Helper::get_recurrs_from_cart( $cart_items );
			$cart_have_subscription = count( $recurrs ) > 0;
		}

		if ( ! $cart_have_subscription ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$is_create_account = isset( $_POST['createaccount'] ) ? (bool) sanitize_text_field( wp_unslash( $_POST['createaccount'] ) ) : false;
		$is_login_enforced = self::is_guest_login_enforced();

		if ( $is_login_enforced && ! $is_create_account ) {
			wc_add_notice(
				wp_kses_post(
					__( 'You are ordering a subscription product. You must be either <strong>logged in</strong> or check the "<strong>Create an account</strong>" option to continue the checkout.', 'subscription' )
				),
				'error'
			);
		}
	}

	/**
	 * Enforce account creation in Store API checkout.
	 *
	 * @param WC_Order        $order Order object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @throws \WC_Data_Exception If account creation is required but not selected.
	 */
	public function require_account_creation_store_api( $order, $request ) {
		if ( is_user_logged_in() || ! self::is_guest_checkout_allowed() ) {
			return;
		}

		$is_subscription_order = Helper::order_has_subscription_item( $order );
		if ( ! $is_subscription_order ) {
			return;
		}

		$request_body      = $request->get_json_params();
		$is_create_account = $request_body['create_account'] ?? false;
		$is_login_enforced = self::is_guest_login_enforced();

		if ( $is_login_enforced && ! $is_create_account ) {
			throw new \WC_Data_Exception(
				'wp_subscription_account_required',
				wp_kses_post(
					__( 'You are ordering a subscription product. You must be either <strong>logged in</strong> or check the "<strong>Create an account</strong>" option to continue the checkout.', 'subscription' )
				),
				400
			);
		}
	}

	/**
	 * Build user info from order or customer object.
	 *
	 * @param \WC_Order|\WC_Customer $order_or_customer Order or Customer object.
	 */
	public function build_user_info( $order_or_customer ): array {
		$user_info = [];

		// Billing info.
		$user_info['billing_first_name'] = $order_or_customer->get_billing_first_name();
		$user_info['billing_last_name']  = $order_or_customer->get_billing_last_name();
		$user_info['billing_company']    = $order_or_customer->get_billing_company();
		$user_info['billing_address_1']  = $order_or_customer->get_billing_address_1();
		$user_info['billing_address_2']  = $order_or_customer->get_billing_address_2();
		$user_info['billing_city']       = $order_or_customer->get_billing_city();
		$user_info['billing_postcode']   = $order_or_customer->get_billing_postcode();
		$user_info['billing_country']    = $order_or_customer->get_billing_country();
		$user_info['billing_state']      = $order_or_customer->get_billing_state();
		$user_info['billing_email']      = $order_or_customer->get_billing_email();
		$user_info['billing_phone']      = $order_or_customer->get_billing_phone();

		// Shipping info.
		$user_info['shipping_first_name'] = ! empty( $order_or_customer->get_shipping_first_name() ) ? $order_or_customer->get_shipping_first_name() : $user_info['billing_first_name'];
		$user_info['shipping_last_name']  = ! empty( $order_or_customer->get_shipping_last_name() ) ? $order_or_customer->get_shipping_last_name() : $user_info['billing_last_name'];
		$user_info['shipping_company']    = ! empty( $order_or_customer->get_shipping_company() ) ? $order_or_customer->get_shipping_company() : $user_info['billing_company'];
		$user_info['shipping_address_1']  = ! empty( $order_or_customer->get_shipping_address_1() ) ? $order_or_customer->get_shipping_address_1() : $user_info['billing_address_1'];
		$user_info['shipping_address_2']  = ! empty( $order_or_customer->get_shipping_address_2() ) ? $order_or_customer->get_shipping_address_2() : $user_info['billing_address_2'];
		$user_info['shipping_city']       = ! empty( $order_or_customer->get_shipping_city() ) ? $order_or_customer->get_shipping_city() : $user_info['billing_city'];
		$user_info['shipping_postcode']   = ! empty( $order_or_customer->get_shipping_postcode() ) ? $order_or_customer->get_shipping_postcode() : $user_info['billing_postcode'];
		$user_info['shipping_country']    = ! empty( $order_or_customer->get_shipping_country() ) ? $order_or_customer->get_shipping_country() : $user_info['billing_country'];
		$user_info['shipping_state']      = ! empty( $order_or_customer->get_shipping_state() ) ? $order_or_customer->get_shipping_state() : $user_info['billing_state'];
		$user_info['shipping_phone']      = ! empty( $order_or_customer->get_shipping_phone() ) ? $order_or_customer->get_shipping_phone() : $user_info['billing_phone'];

		return $user_info;
	}

	/**
	 * Maybe create user from user info.
	 *
	 * @param array $user_info User info array.
	 */
	public function maybe_create_user( $user_info ): ?int {
		// Don't proceed if guest checkout is not allowed.
		$is_guest_checkout_allowed = in_array( get_option( 'wp_subscription_allow_guest_checkout', '0' ), [ 1, '1', 'yes', 'on' ], true );
		if ( ! $is_guest_checkout_allowed ) {
			return null;
		}

		$is_new_customer = false;

		// Check if user exists with email.
		$user    = get_user_by( 'email', $user_info['billing_email'] );
		$user_id = $user ? $user->ID : 0;

		if ( ! $user_id ) {
			$is_new_customer = true;

			// Not wp_insert_user(): without a `user_pass` it stores wp_hash_password( '' ).
			$user_id = wc_create_new_customer(
				$user_info['billing_email'],
				'',
				'',
				[
					'first_name' => $user_info['billing_first_name'],
					'last_name'  => $user_info['billing_last_name'],
					'source'     => 'subscrpt-guest-checkout',
				]
			);

			if ( is_wp_error( $user_id ) ) {
				subscrpt_write_log( 'Failed to auto-create user during checkout. Error: ' . $user_id->get_error_message() );
				return null;
			}

			// Set billing info.
			update_user_meta( $user_id, 'billing_first_name', $user_info['billing_first_name'] );
			update_user_meta( $user_id, 'billing_last_name', $user_info['billing_last_name'] );
			update_user_meta( $user_id, 'billing_company', $user_info['billing_company'] );
			update_user_meta( $user_id, 'billing_address_1', $user_info['billing_address_1'] );
			update_user_meta( $user_id, 'billing_address_2', $user_info['billing_address_2'] );
			update_user_meta( $user_id, 'billing_city', $user_info['billing_city'] );
			update_user_meta( $user_id, 'billing_postcode', $user_info['billing_postcode'] );
			update_user_meta( $user_id, 'billing_country', $user_info['billing_country'] );
			update_user_meta( $user_id, 'billing_state', $user_info['billing_state'] );
			update_user_meta( $user_id, 'billing_email', $user_info['billing_email'] );
			update_user_meta( $user_id, 'billing_phone', $user_info['billing_phone'] );

			// Set shipping info.
			update_user_meta( $user_id, 'shipping_first_name', $user_info['shipping_first_name'] );
			update_user_meta( $user_id, 'shipping_last_name', $user_info['shipping_last_name'] );
			update_user_meta( $user_id, 'shipping_company', $user_info['shipping_company'] );
			update_user_meta( $user_id, 'shipping_address_1', $user_info['shipping_address_1'] );
			update_user_meta( $user_id, 'shipping_address_2', $user_info['shipping_address_2'] );
			update_user_meta( $user_id, 'shipping_city', $user_info['shipping_city'] );
			update_user_meta( $user_id, 'shipping_postcode', $user_info['shipping_postcode'] );
			update_user_meta( $user_id, 'shipping_country', $user_info['shipping_country'] );
			update_user_meta( $user_id, 'shipping_state', $user_info['shipping_state'] );
			update_user_meta( $user_id, 'shipping_phone', $user_info['shipping_phone'] );
		}

		// Auto-login the newly created account so the subscription can be associated with the user.
		// This ONLY runs when $is_new_customer is true — i.e. when wp_insert_user() succeeded
		// just above in this same request. It never fires for returning/existing users.
		// wc_set_customer_auth_cookie() is the WooCommerce-sanctioned way to log a customer in
		// during checkout; wp_set_auth_cookie() is the WP fallback if WC is not available.
		if ( ! is_user_logged_in() && $is_new_customer && $user_id ) {
			wp_set_current_user( $user_id );
			if ( function_exists( 'wc_set_customer_auth_cookie' ) ) {
				wc_set_customer_auth_cookie( $user_id );
			} else {
				wp_set_auth_cookie( $user_id );
			}

			subscrpt_write_debug_log( 'Auto-logged in newly created user ID: ' . $user_id . ' after checkout.' );
		}

		return $user_id;
	}

	/**
	 * Auto-create a guest account only while login is not enforced.
	 *
	 * @return bool
	 */
	protected function should_auto_create_account() {
		return self::is_guest_checkout_allowed() && ! self::is_guest_login_enforced();
	}

	/**
	 * Does the current cart contain a subscription product.
	 *
	 * @return bool
	 */
	protected function cart_has_subscription() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		return count( Helper::get_recurrs_from_cart( WC()->cart->get_cart() ) ) > 0;
	}

	/**
	 * Create the guest account before the order exists, for gateway customer binding.
	 *
	 * @param \WC_Customer $customer Customer object.
	 *
	 * @return void
	 */
	public function maybe_create_user_from_customer( $customer ) {
		if ( is_user_logged_in() || ! $this->should_auto_create_account() ) {
			return;
		}

		// Already backed by a real user.
		if ( $customer->get_id() ) {
			return;
		}

		if ( ! $this->cart_has_subscription() ) {
			return;
		}

		$billing_email = $customer->get_billing_email();

		// Cannot create an account without an email.
		if ( empty( $billing_email ) ) {
			return;
		}

		// Never $customer->set_id() + save() here: it blanks user_email.
		$this->maybe_create_user( $this->build_user_info( $customer ) );
	}

	/**
	 * Assign a user to a guest subscription order, creating one if needed.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return void
	 */
	public function maybe_assign_user_to_order( $order ) {
		if ( ! $this->should_auto_create_account() ) {
			return;
		}

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		// Don't proceed if the order already has a user.
		if ( $order->get_customer_id() ) {
			return;
		}

		if ( ! $this->cart_has_subscription() ) {
			return;
		}

		// Reuse the user created earlier in this request when there is one.
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			$user_info = $this->build_user_info( $order );

			if ( empty( $user_info['billing_email'] ) ) {
				return;
			}

			$user_id = $this->maybe_create_user( $user_info );
		}

		if ( ! $user_id ) {
			return;
		}

		$order->set_customer_id( $user_id );

		// The classic checkout hands over an unsaved order and persists it itself.
		if ( $order->get_id() ) {
			$order->save();
		}
	}
}
