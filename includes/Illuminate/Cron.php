<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Cron
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Cron {

	public function __construct() {
		add_action( 'subscrpt_daily_cron', array( $this, 'daily_cron_task' ) );
	}

	public function daily_cron_task() {
		$args = array(
			'post_type' => 'subscrpt_order',
			'post_status' => "active",
			'fields' => 'ids',
			'author' => get_current_user_id()
		);

		$active_subscriptions = get_posts($args);

		foreach ( $active_subscriptions as $subscription ) {
			$post_meta = get_post_meta( $subscription, '_order_subscrpt_meta', true );
			if ( time() >= $post_meta['next_date'] || ( $post_meta['trial'] != null && time() >= $post_meta['start_date'] ) ) {
				wp_update_post(
					array(
						'ID'          => $subscription,
						'post_type'   => 'subscrpt_order',
						'post_status' => 'expired',
					)
				);
				
				Action::write_comment( 'expired', $subscription );
			}
		}
	}
}
