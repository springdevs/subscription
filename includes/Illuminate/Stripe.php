<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Stripe
 *
 * @package SpringDevs\SubscriptionPro\Illuminate
 */
class Stripe extends \WC_Stripe_Payment_Gateway {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'subscrpt_after_create_renew_order', array( $this, 'after_create_renew_order' ), 10, 3 );
		add_filter( 'wc_stripe_payment_metadata', array( $this, 'add_payment_metadata' ), 10, 2 );
	}

	/**
	 * Process stripe auto renewal process.
	 *
	 * @param \WC_Order $new_order       New Order.
	 * @param \WC_Order $old_order       Old Order.
	 * @param int       $subscription_id Subscription ID.
	 */
	public function after_create_renew_order( $new_order, $old_order, $subscription_id ) {
		$is_auto_renew  = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		$stripe_enabled = ( 'stripe' === $old_order->get_payment_method() && in_array( $is_auto_renew, array( 1, '1' ), true ) && subscrpt_is_auto_renew_enabled() && '1' === get_option( 'subscrpt_stripe_auto_renew', '1' ) );

		if ( ! $stripe_enabled ) {
			return;
		}

		$this->pay_renew_order( $new_order );
	}

	/**
	 * Pay renewal Order
	 *
	 * @param \WC_Order $renewal_order Renewal order.
	 * @throws \WC_Stripe_Exception $e excepttion.
	 */
	public function pay_renew_order( $renewal_order ) {

		try {
			$this->validate_minimum_order_amount( $renewal_order );

			$amount   = $renewal_order->get_total();
			$order_id = $renewal_order->get_id();

			// Get source from order.
			$prepared_source = $this->prepare_order_source( $renewal_order );
			if ( ! $prepared_source->customer ) {
				return new \WP_Error( 'stripe_error', __( 'Customer not found', 'sdevs_subscrpt' ) );
			}

			\WC_Stripe_Logger::log( "Info: Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );

			$intent = $this->create_intent( $renewal_order, $prepared_source );

			if ( empty( $intent->error ) ) {
				$this->lock_order_payment( $renewal_order, $intent );
				$intent = $this->confirm_intent( $intent, $renewal_order, $prepared_source );
			}

			if ( ! empty( $intent->error ) ) {
				$this->maybe_remove_non_existent_customer( $intent->error, $renewal_order );

				$this->unlock_order_payment( $renewal_order );
				$this->throw_localized_message( $intent, $renewal_order );
			}

			if ( ! empty( $intent ) ) {
				// Use the last charge within the intent to proceed.
				$response = $this->get_latest_charge_from_intent( $intent );
				$this->process_response( $response, $renewal_order );
			}
			$this->unlock_order_payment( $renewal_order );
		} catch ( \WC_Stripe_Exception $e ) {
			\WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
			do_action( 'wc_gateway_stripe_process_payment_error', $e, $renewal_order );
		}
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

		$currency = strtolower( $order->get_currency() );

		$request = array(
			'amount'               => \WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency ),
			'currency'             => $currency,
			'description'          => $full_request['description'],
			'metadata'             => $full_request['metadata'],
			'capture_method'       => ( 'true' === $full_request['capture'] ) ? 'automatic' : 'manual',
			'payment_method_types' => $payment_method_types,
		);

		$request = \WC_Stripe_Helper::add_payment_method_to_request_array( $prepared_source->source, $request );

		$force_save_source = apply_filters( 'wc_stripe_force_save_source', false, $prepared_source->source );

		if ( $this->save_payment_method_requested() || $this->has_subscription( $order->get_id() ) || $force_save_source ) {
			$request['setup_future_usage']              = 'off_session';
			$request['metadata']['save_payment_method'] = 'true';
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
	 * @param array     $metadata Metadata.
	 * @param \WC_Order $order Order.
	 *
	 * @return array
	 */
	public function add_payment_metadata( array $metadata, \WC_Order $order ): array {

		if ( ! subscrpt_is_auto_renew_enabled() ) {
			return $metadata;
		}

		global $wpdb;
		$recurring = false;
		foreach ( $order->get_items() as $order_item ) {
			$table_name = $wpdb->prefix . 'subscrpt_order_relation';
			// @phpcs:ignore
			$relation = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id=%d AND order_item_id=%d', array( $table_name, $order->get_id(), $order_item->get_id() ) ) );

			if ( 0 < count( $relation ) ) {
				$relation      = $relation[0];
				$is_auto_renew = get_post_meta( (int) $relation->subscription_id, '_subscrpt_auto_renew', true );

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
		}

		return $metadata;
	}
}
