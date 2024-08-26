<?php

namespace SpringDevs\Subscription;

/**
 * API Class
 */
class API {


	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_api' ) );
	}

	/**
	 * Register the API
	 *
	 * @return void
	 */
	public function register_api() {
	}
}
