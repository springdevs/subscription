<?php
/**
 * Stripe integration helpers for subscription auto-renewals.
 *
 * Ensures payment methods are saved with mandates (SEPA, etc.) so that
 * off-session renewals can be charged automatically by Stripe.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Illuminate\Gateways\Stripe;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Class Stripe
 *
 * @package SpringDevs\SubscriptionPro\Illuminate
 */
class Stripe extends \WC_Stripe_Payment_Gateway {

	/**
	 * Subscriptions supported Stripe payment methods.
	 */
	public const WPSUBS_SUPPORTED_METHODS = [ 'stripe', 'stripe_ideal', 'stripe_sepa', 'sepa_debit', 'stripe_bancontact' ];

	/**
	 * Mandate needed methods.
	 */
	public const WPSUBS_MANDATE_NEEDED_METHODS = [ 'stripe_ideal', 'stripe_sepa', 'sepa_debit', 'stripe_bancontact' ];

	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Hook into WPSubscription renewal events
		add_action( 'subscrpt_after_create_renew_order', array( $this, 'after_create_renew_order' ), 10, 3 );
		add_filter( 'subscrpt_before_saving_renewal_order', array( $this, 'copy_stripe_metadata' ), 10, 3 );

		add_filter( 'wc_stripe_payment_metadata', array( $this, 'add_payment_metadata' ), 10, 2 );

		// Ensure a reusable payment method is stored for subscription checkouts (needed for iDEAL/SEPA auto-renewals).
		add_filter( 'wc_stripe_force_save_payment_method', array( $this, 'force_save_payment_method_for_subscriptions' ), 10, 2 );

		// Modify create intent request to add setup_future_usage and customer when needed.
		add_filter( 'wc_stripe_generate_create_intent_request', [ $this, 'modify_create_intent_request_for_subscriptions' ], 20, 3 );

		// Keep subscription carts off Stripe's Checkout Session (Optimized Checkout) path.
		add_filter( 'wc_stripe_is_adaptive_pricing_supported', [ $this, 'disable_adaptive_pricing_for_subscriptions' ], 10, 1 );

		// Last-resort guard: any Checkout Session that still gets created must carry a customer.
		add_filter( 'wc_stripe_request_body', [ $this, 'ensure_customer_on_checkout_session' ], 10, 2 );

		// SetupIntents ($0 trial orders) reject setup_future_usage.
		add_filter( 'wc_stripe_request_body', [ $this, 'strip_setup_future_usage_from_setup_intents' ], 10, 2 );

		// Persist whatever Stripe ended up using, so renewals can charge it.
		add_action( 'woocommerce_payment_complete', [ $this, 'backfill_stripe_meta_for_subscription_order' ], 20, 1 );
	}

	/**
	 * Process stripe auto renewal process.
	 *
	 * @param \WC_Order $new_order       New Order.
	 * @param \WC_Order $old_order       Old Order.
	 * @param int       $subscription_id Subscription ID.
	 */
	public function after_create_renew_order( $new_order, $old_order, $subscription_id ) {
		$is_auto_renew = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		$is_auto_renew = in_array( $is_auto_renew, [ 1,'1' ], true );

		$is_global_auto_renew = get_option( 'wp_subscription_stripe_auto_renew', '1' );
		$is_global_auto_renew = in_array( $is_global_auto_renew, [ 1,'1' ], true );

		$stripe_supported_methods = self::WPSUBS_SUPPORTED_METHODS;
		$old_method               = $old_order->get_payment_method();
		$is_stripe_pm             = ! empty( $old_method ) && in_array( $old_method, $stripe_supported_methods, true );

		$has_stripe_meta = ! empty( $old_order->get_meta( '_stripe_customer_id' ) ) || ! empty( $old_order->get_meta( '_stripe_source_id' ) );

		// Old order is not a stripe order, skip auto renewal processing.
		if ( ! $is_stripe_pm && ! $has_stripe_meta ) {
			return;
		}

		$stripe_enabled = $is_auto_renew && $is_global_auto_renew && subscrpt_is_auto_renew_enabled();

		if ( ! $stripe_enabled ) {
			$log_message = "Stripe auto renewal not enabled. [ Subscription: {$subscription_id}, Order #{$new_order->get_id()} ]";
			subscrpt_write_log( $log_message );
			return;
		}

		$this->pay_renew_order( $new_order );
	}

	/**
	 * Copy Stripe metadata from old order to renewal order
	 *
	 * @param \WC_Order $new_order Renewal order.
	 * @param \WC_Order $old_order Parent order.
	 * @param int       $subscription_id Subscription ID.
	 */
	public function copy_stripe_metadata( $new_order, $old_order, $subscription_id ) {
		$stripe_supported_methods = self::WPSUBS_SUPPORTED_METHODS;
		$old_method               = $old_order->get_payment_method();
		$is_stripe_pm             = ! empty( $old_method ) && in_array( $old_method, $stripe_supported_methods, true );

		if ( ! $is_stripe_pm ) {
			return $new_order;
		}

		Helper::clone_stripe_metadata_for_renewal( $subscription_id, $old_order, $new_order );

		// Store Stripe subscription ID if available
		$stripe_subscription_id = $old_order->get_meta( '_stripe_subscription_id' );
		if ( $stripe_subscription_id ) {
			$new_order->update_meta_data( '_stripe_subscription_id', $stripe_subscription_id );
		}

		// Bancontact is single-use. After the first charge, Stripe creates a sepa_debit
		// PaymentMethod on the customer. Swap the source so renewals use that SEPA PM.
		if ( 'stripe_bancontact' === $old_method ) {
			$customer_id = $old_order->get_meta( '_stripe_customer_id' );
			if ( $customer_id ) {
				$sepa_pm_id = $this->resolve_sepa_pm_for_bancontact( $customer_id );
				if ( $sepa_pm_id ) {
					$new_order->update_meta_data( '_stripe_source_id', $sepa_pm_id );
					$new_order->set_payment_method( 'stripe_sepa' );
					$new_order->set_payment_method_title( __( 'SEPA Direct Debit', 'subscription' ) );
					subscrpt_write_log( "Stripe: Bancontact → SEPA resolved PM {$sepa_pm_id} for renewal order #{$new_order->get_id()} (Subscription: #{$subscription_id})" );
				} else {
					subscrpt_write_log( "Stripe: Could not find SEPA PM for Bancontact customer {$customer_id} — renewal order #{$new_order->get_id()} (Subscription: #{$subscription_id}) may fail" );
				}
			}
		}

		return $new_order;
	}

	/**
	 * Resolve a SEPA Direct Debit PaymentMethod for a customer that originally paid with Bancontact.
	 *
	 * Bancontact is single-use. After the initial charge Stripe automatically creates a sepa_debit
	 * PaymentMethod on the same customer for future off-session use.
	 *
	 * @param string $customer_id Stripe customer ID.
	 * @return string|null sepa_debit PaymentMethod ID, or null if none found.
	 */
	private function resolve_sepa_pm_for_bancontact( $customer_id ) {
		$response = \WC_Stripe_API::retrieve(
			'customers/' . rawurlencode( $customer_id ) . '/payment_methods?type=sepa_debit&limit=10'
		);

		if ( ! empty( $response->error ) || empty( $response->data ) ) {
			return null;
		}

		foreach ( $response->data as $pm ) {
			if ( 'sepa_debit' === $pm->type ) {
				return $pm->id;
			}
		}

		return null;
	}

	/**
	 * Pay renewal Order
	 *
	 * @param \WC_Order $renewal_order Renewal order.
	 * @throws \WC_Stripe_Exception $e exception.
	 */
	public function pay_renew_order( $renewal_order ) {
		subscrpt_write_log( "Processing renewal order #{$renewal_order->get_id()} for payment." );
		subscrpt_write_debug_log( "Processing renewal order #{$renewal_order->get_id()} for payment." );

		$stripe_order_helper = new \WC_Stripe_Order_Helper();
		$order_locked        = false;

		try {
			$stripe_order_helper->validate_minimum_order_amount( $renewal_order );

			$amount   = $renewal_order->get_total();
			$order_id = $renewal_order->get_id();

			// Get source from order.
			$prepared_source = $this->prepare_order_source( $renewal_order );
			if ( ! $prepared_source->customer ) {
				subscrpt_write_log( "Customer not found for renewal order #{$renewal_order->get_id()}. Skipping payment." );
				$this->trigger_renewal_payment_failed( $renewal_order );
				return new \WP_Error( 'stripe_error', __( 'Customer not found', 'subscription' ) );
			}

			\WC_Stripe_Logger::info( "Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );

			// Create AND confirm the PaymentIntent off-session in a single request so Stripe
			// actually charges the saved payment method (using the stored mandate / MIT
			// exemption). The previous flow created an unconfirmed intent and only confirmed
			// when status was requires_confirmation — cards needing authentication were left
			// at requires_action with no charge, producing a null charge that then crashed
			// process_response() (Attempt to read property "id" on null) and silently aborted
			// the renewal without flagging it as failed.
			$stripe_order_helper->lock_order_payment( $renewal_order );
			$order_locked = true;

			$intent = $this->create_and_confirm_intent_for_off_session( $renewal_order, $prepared_source, $amount );

			if ( ! empty( $intent->error ) ) {
				$this->maybe_remove_non_existent_customer( $intent->error, $renewal_order );
				$this->throw_localized_message( $intent, $renewal_order );
			}

			// An off-session intent that did not succeed (e.g. requires_action) yields no
			// charge. Treat that as a failed renewal instead of dereferencing a null charge.
			$response = $this->get_latest_charge_from_intent( $intent );
			if ( empty( $response ) ) {
				$status = isset( $intent->status ) ? $intent->status : 'unknown';
				throw new \WC_Stripe_Exception(
					"No charge on renewal intent for order #{$renewal_order->get_id()} (status: {$status})",
					__( 'The subscription renewal payment could not be completed automatically. Customer authentication may be required.', 'subscription' )
				);
			}

			$this->process_response( $response, $renewal_order );

			$stripe_order_helper->unlock_order_payment( $renewal_order );
			$order_locked = false;

		} catch ( \WC_Stripe_Exception $e ) {
			\WC_Stripe_Logger::error( 'Error: ' . $e->getMessage() );

			$log_message = "Error processing renewal order #{$renewal_order->get_id()}: " . $e->getMessage();
			subscrpt_write_log( $log_message );
			subscrpt_write_debug_log( $log_message );

			if ( $order_locked ) {
				$stripe_order_helper->unlock_order_payment( $renewal_order );
			}

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $renewal_order );

			// Trigger failed actions.
			$this->trigger_renewal_payment_failed( $renewal_order );
		}
	}

	/**
	 * Fire the subscription payment failure action for a renewal order.
	 *
	 * @param \WC_Order $renewal_order Renewal order whose subscription should be flagged.
	 * @return void
	 */
	private function trigger_renewal_payment_failed( $renewal_order ) {
		$subscription    = Helper::get_subscriptions_from_order( $renewal_order->get_id() ?? 0 );
		$subscription    = reset( $subscription );
		$subscription_id = $subscription->subscription_id ?? 0;

		do_action( 'subscrpt_subscription_payment_failed', $subscription_id );
	}

	/**
	 * Confirms an intent if it is the `requires_confirmation` state with SEPA mandate support.
	 *
	 * @param object    $intent The intent to confirm.
	 * @param \WC_Order $order The order that the intent is associated with.
	 * @param object    $prepared_source The source that is being charged.
	 * @return object Either an error or the updated intent.
	 */
	public function confirm_intent( $intent, $order, $prepared_source ) {
		if ( \WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION !== $intent->status ) {
			return $intent;
		}

		// Build confirm request and include SEPA mandate_data when needed.
		$confirm_request = \WC_Stripe_Helper::add_payment_method_to_request_array( $prepared_source->source, array() );

		$payment_method_types = array();
		if ( isset( $intent->payment_method_types ) && is_array( $intent->payment_method_types ) ) {
			$payment_method_types = $intent->payment_method_types;
		} elseif ( isset( $prepared_source->source_object->type ) ) {
			$payment_method_types = array( $prepared_source->source_object->type );
		}

		if ( in_array( 'sepa_debit', $payment_method_types, true ) ) {
			$confirm_request['mandate_data'] = array(
				'customer_acceptance' => array(
					'type' => 'offline',
				),
			);
		}

		$level3_data      = $this->get_level3_data_from_order( $order );
		$confirmed_intent = \WC_Stripe_API::request_with_level3_data(
			$confirm_request,
			"payment_intents/$intent->id/confirm",
			$level3_data,
			$order
		);

		if ( ! empty( $confirmed_intent->error ) ) {
			return $confirmed_intent;
		}

		// Save a note about the status of the intent.
		$order_id = $order->get_id();
		if ( \WC_Stripe_Intent_Status::SUCCEEDED === $confirmed_intent->status ) {
			\WC_Stripe_Logger::info( "Stripe PaymentIntent $intent->id succeeded for order $order_id" );
		} elseif ( \WC_Stripe_Intent_Status::REQUIRES_ACTION === $confirmed_intent->status ) {
			\WC_Stripe_Logger::info( "Stripe PaymentIntent $intent->id requires authentication for order $order_id" );
		}

		return $confirmed_intent;
	}

	/**
	 * Generates the request when creating a new payment intent.
	 *
	 * @param \WC_Order $order           The order that is being paid for.
	 * @param object    $prepared_source The source that is used for the payment.
	 * @return array                    The arguments for the request.
	 */
	public function generate_create_intent_request( $order, $prepared_source ) {
		// The request for a charge contains metadata for the intent.
		$full_request = $this->generate_payment_request( $order, $prepared_source );

		$payment_method_types = array( 'card' );
		if ( isset( $prepared_source->source_object->type ) ) {
			$payment_method_types = array( $prepared_source->source_object->type );
		}

		// Determine capture method safely; default to 'automatic'.
		$requires_automatic_capture = in_array( 'sepa_debit', $payment_method_types, true );
		$capture_method             = 'automatic';
		if ( ! $requires_automatic_capture && isset( $full_request['capture'] ) ) {
			$capture_method = ( 'true' === $full_request['capture'] ) ? 'automatic' : 'manual';
		}

		$currency = strtolower( $order->get_currency() );

		$request = array(
			'amount'               => \WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency ),
			'currency'             => $currency,
			'description'          => $full_request['description'],
			'metadata'             => $full_request['metadata'],
			'capture_method'       => $capture_method,
			'payment_method_types' => $payment_method_types,
		);

		$request = \WC_Stripe_Helper::add_payment_method_to_request_array( $prepared_source->source, $request );

		$force_save_source = apply_filters( 'wc_stripe_force_save_payment_method', false, $order->get_id() );

		// Only ask Stripe to set up future usage when we actually have a Stripe customer
		// (logged-in user or a customer created for this order). For guest + iDEAL, this can
		// leave orders pending if webhooks are not completing the flow.
		$has_stripe_customer = ! empty( $prepared_source->customer );

		if ( $has_stripe_customer && ( $this->save_payment_method_requested() || $this->has_subscription( $order->get_id() ) || $force_save_source ) ) {
			$request['setup_future_usage']              = 'off_session';
			$request['metadata']['save_payment_method'] = 'true';
		}

		// For renewal orders, do not set setup_future_usage to avoid mandate_data requirement on confirmation.
		if ( $this->is_subscription_renewal_order( $order->get_id() ) && isset( $request['setup_future_usage'] ) ) {
			unset( $request['setup_future_usage'] );
		}

		if ( $prepared_source->customer ) {
			$request['customer'] = $prepared_source->customer;
		}

		if ( isset( $full_request['statement_descriptor_suffix'] ) ) {
			$request['statement_descriptor_suffix'] = $full_request['statement_descriptor_suffix'];
		}

		if ( isset( $full_request['shipping'] ) ) {
			$request['shipping'] = $full_request['shipping'];
		}

		if ( isset( $full_request['receipt_email'] ) ) {
			$request['receipt_email'] = $full_request['receipt_email'];
		}

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_create_intent_request.
		 *
		 * @since 3.1.0
		 * @param array $request
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters( 'wc_stripe_generate_create_intent_request', $request, $order, $prepared_source );
	}

	/**
	 * Add metadata to stripe payment.
	 *
	 * @param mixed          $metadata Metadata.
	 * @param \WC_Order|null $order Order, null when no order exists yet.
	 *
	 * @return mixed
	 */
	public function add_payment_metadata( $metadata, $order = null ) {
		// Note: Stripe's Checkout Session AJAX handler applies this filter before an order exists, passing null. Bail out in that case.
		if ( ! is_array( $metadata ) || ! $order instanceof \WC_Order ) {
			return $metadata;
		}

		if ( ! subscrpt_is_auto_renew_enabled() ) {
			return $metadata;
		}

		global $wpdb;
		$recurring     = false;
		$renewal_limit = null;
		foreach ( $order->get_items() as $order_item ) {
			$table_name = $wpdb->prefix . 'subscrpt_order_relation';
			// @phpcs:ignore
			$relation = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id=%d AND order_item_id=%d', array( $table_name, $order->get_id(), $order_item->get_id() ) ) );

			if ( 0 < count( $relation ) ) {
				$relation      = $relation[0];
				$is_auto_renew = get_post_meta( (int) $relation->subscription_id, '_subscrpt_auto_renew', true );

				// Get renewal limit from product meta (handles variations)
				$max_payments  = subscrpt_get_max_payments( (int) $relation->subscription_id );
				$renewal_limit = $max_payments ? $max_payments : 0;

				if ( in_array( $is_auto_renew, array( 1, '1' ), true ) && in_array( $relation->type, array( 'early-renew', 'renew' ), true ) ) {
					$recurring = true;
					break;
				}
			}
		}

		if ( $recurring ) {
			$metadata += array(
				'payment_type' => 'recurring',
			);
			if ( null !== $renewal_limit ) {
				$metadata['renewal_limit'] = $renewal_limit;
			}
		}

		return $metadata;
	}

	/**
	 * Mirror of the above for gateways using wc_stripe_force_save_payment_method filter.
	 *
	 * @param bool $force    Whether to force save the payment method.
	 * @param int  $order_id Order ID if available during confirmation.
	 * @return bool
	 */
	public function force_save_payment_method_for_subscriptions( $force, $order_id = 0 ) {
		if ( $this->cart_has_subscription_items() ) {
			return true;
		}
		if ( $order_id && $this->order_has_subscription_relation( (int) $order_id ) ) {
			return true;
		}
		return $force;
	}

	/**
	 * Check if current cart contains subscription items added by this plugin.
	 *
	 * @return bool
	 */
	private function cart_has_subscription_items(): bool {
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart_items = WC()->cart->get_cart_contents() ?? [];
			$recurs     = Helper::get_recurrs_from_cart( $cart_items );

			if ( ! empty( $recurs ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Determine if a given order has subscription relation rows in our mapping table.
	 *
	 * @param int $order_id The WooCommerce order ID to check.
	 * @return bool
	 */
	private function order_has_subscription_relation( int $order_id ): bool {
		$histories = Helper::get_subscriptions_from_order( $order_id );
		return ! empty( $histories );
	}

	/**
	 * Detect if given order id is a renewal order created by this plugin.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	private function is_subscription_renewal_order( $order_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		// @phpcs:ignore
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT type FROM %i WHERE order_id=%d ORDER BY id DESC', array( $table_name, $order_id ) ) );
		return ( $row && isset( $row->type ) && 'renew' === $row->type );
	}

	/**
	 * Modify create intent request to add setup_future_usage and customer when needed.
	 *
	 * @param array     $request         The arguments for the request.
	 * @param \WC_Order $order           The order that is being paid for.
	 * @param object    $prepared_source The source that is used for the payment.
	 */
	public function modify_create_intent_request_for_subscriptions( $request, $order, $prepared_source ) {
		if ( ! $order instanceof \WC_Order ) {
			return $request;
		}

		$is_subscription_order = $this->order_has_subscription_relation( $order->get_id() );
		$is_renewal_order      = $this->is_subscription_renewal_order( $order->get_id() );

		// Don't add setup_future_usage for renewal orders (payment method already saved)
		if ( ! $is_subscription_order || $is_renewal_order ) {
			return $request;
		}

		$request['setup_future_usage']              = 'off_session';
		$request['metadata']['save_payment_method'] = 'true';

		// Ensure we have a customer for future payments
		if ( ! empty( $prepared_source->customer ) ) {
			$request['customer'] = $prepared_source->customer;
		}

		if ( isset( $request['confirm'] ) && true === $request['confirm'] ) {
			if ( in_array( $order->get_payment_method(), self::WPSUBS_MANDATE_NEEDED_METHODS, true ) ) {
				$request['mandate_data'] = [
					'customer_acceptance' => [
						'type' => 'offline',
					],
				];
			}
		}

		return $request;
	}

	/**
	 * Opt subscription carts out of Stripe's Checkout Session (Optimized Checkout) path.
	 *
	 * A Checkout Session is created on page load and only carries a customer when the
	 * shopper is already logged in, which never holds for guest checkout.
	 *
	 * @param bool $supported Whether adaptive pricing is supported for the current cart.
	 *
	 * @return bool
	 */
	public function disable_adaptive_pricing_for_subscriptions( $supported ) {
		return $this->cart_has_subscription_items() ? false : $supported;
	}

	/**
	 * Force a customer and off-session reuse onto a Checkout Session for a subscription cart.
	 *
	 * Backstop for {@see disable_adaptive_pricing_for_subscriptions()}, applied at the API
	 * boundary so it holds for any code path and either Stripe mode.
	 *
	 * @param array  $request Request body sent to the Stripe API.
	 * @param string $api     Stripe API endpoint.
	 *
	 * @return array
	 */
	public function ensure_customer_on_checkout_session( $request, $api ) {
		if ( 'checkout/sessions' !== $api || ! is_array( $request ) ) {
			return $request;
		}

		if ( ! $this->cart_has_subscription_items() ) {
			return $request;
		}

		if ( empty( $request['customer'] ) ) {
			$customer_id = $this->resolve_stripe_customer_id();

			if ( empty( $customer_id ) ) {
				subscrpt_write_log( 'Could not attach a Stripe customer to a subscription Checkout Session. Auto renewal may fail.' );
				return $request;
			}

			$request['customer'] = $customer_id;
		}

		// `saved_payment_method_options` is deliberately not sent: Stripe rejects it
		// alongside `setup_future_usage`.
		if ( ! isset( $request['payment_intent_data'] ) || ! is_array( $request['payment_intent_data'] ) ) {
			$request['payment_intent_data'] = [];
		}

		$request['payment_intent_data']['setup_future_usage'] = 'off_session';

		return $request;
	}

	/**
	 * Strip the PaymentIntent-only `setup_future_usage` from SetupIntent requests
	 * ($0 trial orders), which Stripe otherwise rejects as an unknown parameter.
	 *
	 * @param array  $request Stripe API request body.
	 * @param string $api     Stripe API endpoint.
	 * @return array
	 */
	public function strip_setup_future_usage_from_setup_intents( $request, $api ) {
		if ( is_array( $request ) && is_string( $api ) && 0 === strpos( $api, 'setup_intents' ) ) {
			unset( $request['setup_future_usage'] );
		}

		return $request;
	}

	/**
	 * Resolve — creating when needed — the Stripe customer for the current shopper.
	 *
	 * @return string Stripe customer ID, or an empty string when one cannot be resolved.
	 */
	private function resolve_stripe_customer_id() {
		if ( ! class_exists( '\WC_Stripe_Customer' ) ) {
			return '';
		}

		try {
			$customer = new \WC_Stripe_Customer( get_current_user_id() );

			// A minimal-billing-details context, so this is valid before the shopper types anything.
			return (string) $customer->maybe_create_customer( \WC_Stripe_Customer::CUSTOMER_CONTEXT_CHECKOUT_SESSION );
		} catch ( \Exception $e ) {
			subscrpt_write_log( 'Stripe customer creation failed for Checkout Session: ' . $e->getMessage() );
			return '';
		}
	}

	/**
	 * Persist the Stripe customer / payment-method ids a subscription order needs for renewals.
	 *
	 * The gateway only writes them when it saved the payment method, which it never does for
	 * a shopper who was logged out during `process_payment()`. Read them off the
	 * PaymentIntent instead.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public function backfill_stripe_meta_for_subscription_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order || ! class_exists( '\WC_Stripe_API' ) || ! class_exists( '\WC_Stripe_Order_Helper' ) ) {
			return;
		}

		if ( ! in_array( $order->get_payment_method(), self::WPSUBS_SUPPORTED_METHODS, true ) ) {
			return;
		}

		if ( ! $this->order_has_subscription_relation( (int) $order_id ) ) {
			return;
		}

		$order_helper = \WC_Stripe_Order_Helper::get_instance();

		// Healthy order — skip the API round trip.
		if (
			! empty( $order_helper->get_stripe_customer_id( $order ) )
			&& ! empty( $order_helper->get_stripe_source_id( $order ) )
			&& ( ! $order->get_customer_id() || get_user_option( '_stripe_customer_id', $order->get_customer_id() ) )
		) {
			return;
		}

		$intent_id = $order_helper->get_stripe_intent_id( $order );

		if ( empty( $intent_id ) || 0 !== strpos( $intent_id, 'pi_' ) ) {
			return;
		}

		$intent = \WC_Stripe_API::retrieve( 'payment_intents/' . $intent_id );

		if ( empty( $intent ) || is_wp_error( $intent ) || ! empty( $intent->error ) ) {
			subscrpt_write_log( "Could not read Stripe intent {$intent_id} for order #{$order_id}." );
			return;
		}

		$customer_id = isset( $intent->customer ) ? ( is_object( $intent->customer ) ? $intent->customer->id : (string) $intent->customer ) : '';
		$source_id   = isset( $intent->payment_method ) ? ( is_object( $intent->payment_method ) ? $intent->payment_method->id : (string) $intent->payment_method ) : '';

		if ( empty( $customer_id ) ) {
			$order->add_order_note( __( 'Stripe did not attach a customer to this payment, so no reusable payment method was stored. Automatic renewal will fail until the customer saves a payment method.', 'subscription' ) );
			$order->save();

			subscrpt_write_log( "No Stripe customer on intent {$intent_id} for order #{$order_id}. Auto renewal will fail." );

			/**
			 * Fires when a paid subscription order has no reusable Stripe payment method.
			 *
			 * @param int    $order_id  Order ID.
			 * @param string $intent_id Stripe PaymentIntent ID.
			 */
			do_action( 'subscrpt_stripe_reusable_pm_missing', (int) $order_id, $intent_id );
			return;
		}

		$updated = false;

		if ( empty( $order_helper->get_stripe_customer_id( $order ) ) ) {
			$order_helper->update_stripe_customer_id( $order, $customer_id );
			$updated = true;
		}

		if ( ! empty( $source_id ) && empty( $order_helper->get_stripe_source_id( $order ) ) ) {
			$order_helper->update_stripe_source_id( $order, $source_id );
			$updated = true;
		}

		if ( $updated ) {
			$order->save();
			subscrpt_write_debug_log( "Backfilled Stripe customer {$customer_id} on order #{$order_id} from intent {$intent_id}." );
		}

		$this->maybe_attach_stripe_customer_to_user( $order, $customer_id );
	}

	/**
	 * Bind a Stripe customer created during guest checkout to the account behind the order.
	 *
	 * Otherwise it keeps the gateway's "Guest" description and every later order orphans
	 * another customer.
	 *
	 * @param \WC_Order $order       Order the customer paid for.
	 * @param string    $customer_id Stripe customer ID.
	 *
	 * @return void
	 */
	private function maybe_attach_stripe_customer_to_user( $order, $customer_id ) {
		$user_id = $order->get_customer_id();

		if ( ! $user_id || get_user_option( '_stripe_customer_id', $user_id ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		update_user_option( $user_id, '_stripe_customer_id', $customer_id, false );

		$first_name = $order->get_billing_first_name();
		$last_name  = $order->get_billing_last_name();

		\WC_Stripe_API::request(
			[
				'email'       => $order->get_billing_email(),
				'name'        => trim( $first_name . ' ' . $last_name ),
				// translators: %1$s first name, %2$s last name, %3$s username.
				'description' => sprintf( __( 'Name: %1$s %2$s, Username: %3$s', 'subscription' ), $first_name, $last_name, $user->user_login ),
				'metadata'    => [ 'user_id' => (string) $user_id ],
			],
			'customers/' . $customer_id
		);

		subscrpt_write_debug_log( "Linked Stripe customer {$customer_id} to user {$user_id} after guest checkout." );
	}
}
