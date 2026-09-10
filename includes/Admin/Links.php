<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Plugin action links
 *
 * Class Links
 *
 * @package SpringDevs\Subscription\Admin
 */
class Links {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_filter( 'plugin_action_links_' . plugin_basename( SUBSCRPT_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add plugin action links
	 *
	 * @param array $links Plugin Links.
	 */
	public function plugin_action_links( $links ) {
		$getting_started_url = admin_url( 'admin.php?page=wp-subscription-onboarding' );
		array_unshift( $links, '<a href="' . esc_url( $getting_started_url ) . '">' . __( 'Getting Started', 'subscription' ) . '</a>' );
		if ( ! subscrpt_pro_activated() ) {
			$links[] = '<a href="https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro" target="_blank" style="color:#3db634;">' . __( 'Upgrade to premium', 'subscription' ) . '</a>';
		}
		$links[] = '<a href="https://wordpress.org/support/plugin/subscription" target="_blank">' . __( 'Support', 'subscription' ) . '</a>';
		$links[] = '<a href="https://wordpress.org/support/plugin/subscription/reviews/" target="_blank">' . __( 'Review', 'subscription' ) . '</a>';
		return $links;
	}
}
