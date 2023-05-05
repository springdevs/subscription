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
		if ( ! subscrpt_pro_activated() ) {
			$links[] = '<a href="https://springdevs.com/plugin/subscription" target="_blank" style="color:#3db634;">' . __( 'Upgrade to premium', 'sdevs_subscrpt' ) . '</a>';
		}
		$links[] = '<a href="https://wordpress.org/support/plugin/subscription" target="_blank">' . __( 'Support', 'sdevs_subscrpt' ) . '</a>';
		$links[] = '<a href="https://wordpress.org/support/plugin/subscription/reviews/?rate=5#new-post" target="_blank">' . __( 'Review', 'sdevs_subscrpt' ) . '</a>';
		return $links;
	}
}
