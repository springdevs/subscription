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
