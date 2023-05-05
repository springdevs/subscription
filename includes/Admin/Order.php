<?php

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Order class
 *
 * @package SpringDevs\Subscription\Admin
 */
class Order {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Related Subscriptions meta box on Orders.
	 */
	public function add_meta_boxes() {
		$order_id  = get_the_ID();
		$histories = Helper::get_subscriptions_from_order( $order_id );
		if ( is_array( $histories ) ) {
			$order = wc_get_order( $order_id );
			add_meta_box(
				'subscrpt_order_related',
				__( 'Related Subscriptions', 'sdevs_subscrpt' ),
				array( $this, 'subscrpt_order_related' ),
				'shop_order',
				'normal',
				'default',
				array(
					'histories' => $histories,
					'order'     => $order,
				)
			);
		}
	}

	/**
	 * Display content related subscriptions.
	 *
	 * @param mixed $order_post Current Order.
	 * @param mixed $info Meta box Info.
	 */
	public function subscrpt_order_related( $order_post, $info ) {
		$histories = $info['args']['histories'];
		$order     = $info['args']['order'];

		include 'views/related-subscriptions.php';
	}
}
