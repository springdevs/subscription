<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class AutoRenewal
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class AutoRenewal {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'subscrpt_subscription_expired', array( $this, 'after_subscription_expired' ) );
		add_filter(
			'subscrpt_renewal_item_meta',
			function ( $item_meta, $product ) {
				if ( 'updated' !== get_option( 'subscrpt_renewal_price', 'subscribed' ) || ! $product ) {
					return $item_meta;
				}
				if ( $product->is_type( 'variable' ) ) {
					$product = wc_get_product( $product->get_meta( '_subscrpt_convertation_default_variation_for_renewal', true ) );
					if ( ! $product ) {
						return $item_meta;
					}
				}

				$timing_per    = $product->get_meta( '_subscrpt_timing_per' );
				$timing_option = $product->get_meta( '_subscrpt_timing_option' );

				return array(
					'time'  => empty( $timing_per ) ? 1 : $timing_per,
					'type'  => $timing_option,
					'trial' => null,
				);
			},
			10,
			2
		);
		add_filter(
			'subscrpt_renewal_product_args',
			function ( $product_args, $product, $order_item ) {
				if ( 'updated' !== get_option( 'subscrpt_renewal_price', 'subscribed' ) ) {
					return $product_args;
				}

				if ( ! $product ) {
					if ( ! is_admin() ) {
						wc_add_notice( __( 'Subscription early renewal order creation failed due to product deletion !', 'sdevs_subscrpt' ), 'error' );
					}
					return false;
				}

				if ( $product->is_type( 'variable' ) ) {
					$product = wc_get_product( $product->get_meta( '_subscrpt_convertation_default_variation_for_renewal', true ) );
					if ( ! $product ) {
						if ( ! is_admin() ) {
							wc_add_notice( __( 'Subscription early renewal order creation failed due to product deletion !', 'sdevs_subscrpt' ), 'error' );
						}
						return false;
					}
				}
				$product_args = array(
					'name'     => $product->get_name(),
					'subtotal' => $product->get_price() * $order_item->get_quantity(),
					'total'    => $product->get_price() * $order_item->get_quantity(),
				);

				return $product_args;
			},
			10,
			3
		);
	}

	/**
	 * After Expired Subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function after_subscription_expired( $subscription_id ) {
		if ( subscrpt_is_auto_renew_enabled() ) {
			Helper::create_renewal_order( $subscription_id );
		}
	}
}
