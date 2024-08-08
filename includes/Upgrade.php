<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Installer;

class Upgrade {

	public function run() {
		if ( version_compare( '1.1.0', get_option( 'subscrpt_version' ), '>' ) ) {

			// create histories table
			$installer = new Installer();
			$installer->create_tables();

			// move product meta
			$this->move_product_meta();
			// update subscription meta
			$this->update_subscription_meta();
			$this->update_comment_meta();
		}
	}

	public function move_product_meta() {
		global $wpdb;
		$product_meta_query = 'SELECT * FROM ' . $wpdb->prefix . "postmeta WHERE meta_key='subscrpt_general'";
		$products_meta      = $wpdb->get_results( $product_meta_query );
		foreach ( $products_meta as $product_meta ) {
			update_post_meta( $product_meta->post_id, '_subscrpt_meta', unserialize( $product_meta->meta_value ) );
			update_post_meta(
				$product_meta->post_id,
				'_subscrpt_user_cancel',
				get_post_meta(
					$product_meta->post_id,
					'_subscrpt_user_cancell',
					true
				)
			);
			delete_post_meta( $product_meta->post_id, '_subscrpt_user_cancell' );
			delete_post_meta( $product_meta->post_id, 'subscrpt_general' );
		}
	}

	public function update_subscription_meta() {
		global $wpdb;

		$subscription_meta_query = 'SELECT * FROM ' . $wpdb->prefix . "postmeta WHERE meta_key='_subscrpt_order_general'";
		$subscriptions_meta      = $wpdb->get_results( $subscription_meta_query );

		$subscription_history_query = 'SELECT * FROM ' . $wpdb->prefix . "postmeta WHERE meta_key='_subscrpt_order_history'";
		$histories                  = $wpdb->get_results( $subscription_history_query );

		foreach ( $subscriptions_meta as $subscription_meta ) {
			$subscription_meta_value = unserialize( $subscription_meta->meta_value );
			$order_item_id           = 0;
			$order                   = wc_get_order( $subscription_meta_value['order_id'] );

			if ( $order ) {
				foreach ( $order->get_items() as $order_item ) {
					if ( $order_item->get_product_id() == $subscription_meta_value['product_id'] ) {
						$order_item_id = $order_item->get_id();
					}
				}
			}

			update_post_meta(
				$subscription_meta->post_id,
				'_order_subscrpt_meta',
				array(
					'order_id'      => $subscription_meta_value['order_id'],
					'order_item_id' => $order_item_id,
					'trial'         => $subscription_meta_value['trial'],
					'start_date'    => $subscription_meta_value['start_date'],
					'next_date'     => $subscription_meta_value['next_date'],
				)
			);

			delete_post_meta( $subscription_meta->post_id, '_subscrpt_order_general' );
		}

		foreach ( $histories as $history ) {
			$histories_meta = unserialize( $history->meta_value );

			foreach ( $histories_meta as $history_meta ) {
				$order        = wc_get_order( $history_meta['order_id'] );
				$product_meta = get_post_meta( $history_meta['product_id'], '_subscrpt_meta', true );
				$order        = wc_get_order( $history_meta['order_id'] );

				if ( $order ) {
					$order_item_id = 0;
					foreach ( $order->get_items() as $order_item ) {
						if ( $order_item->get_product_id() == $history_meta['product_id'] ) {
							$order_item_id = $order_item->get_id();
							wc_update_order_item_meta(
								$order_item->get_id(),
								'_subscrpt_meta',
								array(
									'time'       => $product_meta['time'],
									'type'       => $product_meta['type'],
									'trial'      => $history_meta['trial'],
									'start_date' => $history_meta['start_date'],
									'next_date'  => $history_meta['next_date'],
								)
							);
						}
					}

					$history_table = $wpdb->prefix . 'subscrpt_order_relation';
					$wpdb->insert(
						$history_table,
						array(
							'subscription_id' => $history_meta['post_id'],
							'order_id'        => $history_meta['order_id'],
							'order_item_id'   => $order_item_id,
							'type'            => 'Parent Order' === $history_meta['stats'] ? 'new' : 'renew',
						)
					);
				}
			}

			delete_post_meta( $history->post_id, '_subscrpt_order_history' );
		}
	}

	public function update_comment_meta() {
		global $wpdb;
		$query         = 'SELECT * FROM ' . $wpdb->prefix . "commentmeta WHERE meta_key='subscrpt_activity'";
		$comments_meta = $wpdb->get_results( $query );
		foreach ( $comments_meta as $comment_meta ) {
			update_comment_meta( $comment_meta->comment_id, '_subscrpt_activity', $comment_meta->meta_value );
			delete_comment_meta( $comment_meta->comment_id, 'subscrpt_activity' );
		}
	}
}
