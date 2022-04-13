<?php


namespace SpringDevs\Subscription\Frontend;

/**
 * Class MyAccount
 *
 * @package SpringDevs\Subscription\Frontend
 */
class MyAccount {

	public function __construct() {
		add_action( 'init', array( $this, 'flush_rewrite_rules' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'custom_my_account_menu_items' ) );
		add_filter( 'the_title', array( $this, 'change_endpoint_title' ), 10, 2 );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'custom_query_vars' ) );
		add_action( 'woocommerce_account_view-subscrpt_endpoint', array( $this, 'view_subscrpt_content' ) );
		add_action( 'woocommerce_account_subscrpt-endpoint_endpoint', array( $this, 'subscrpt_endpoint_content' ) );
	}

	public function custom_query_vars( $query_vars ) {
		$query_vars['view-subscrpt'] = 'view-subscrpt';
		return $query_vars;
	}

	public function view_subscrpt_content( $value ) {
		wc_get_template( 'myaccount/single-subscrpt.php', array( 'id' => $value ), 'simple-subscription', SUBSCRPT_TEMPLATES );
	}

	/**
	 * Re-write flush
	 */
	public function flush_rewrite_rules() {
		add_rewrite_endpoint( 'subscrpt-endpoint', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

    /**
     * @param $title
     * @param $endpoint
     *
     * @return string|void
     */
	public function change_endpoint_title( $title, $endpoint ) {
		global $wp_query;
		$is_endpoint = isset( $wp_query->query_vars['subscrpt-endpoint'] );
		$is_single   = isset( $wp_query->query_vars['view-subscrpt'] );
		if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
			$title = __( 'My Subscriptions', 'sdevs_subscrpt' );
			remove_filter( 'the_title', array( $this, 'change_endpoint_title' ) );
		} elseif ( $is_single && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
			$title = __( 'Subscription #' . get_query_var( 'view-subscrpt' ), 'sdevs_subscrpt' );
			remove_filter( 'the_title', array( $this, 'change_endpoint_title' ) );
		}
		return $title;
	}

	/**
	 * @param $items
	 * @return mixed
	 */
	public function custom_my_account_menu_items( $items ) {
		$logout = $items['customer-logout'];
		unset( $items['customer-logout'] );
		$items['subscrpt-endpoint'] = __( 'Subscriptions', 'sdevs_subscrpt' );
		$items['customer-logout']   = $logout;
		return $items;
	}

	/**
	 * Bookable EndPoint Content
	 */
	public function subscrpt_endpoint_content() {
		wc_get_template( 'myaccount/subscriptions.php', array(), 'simple-subscription', SUBSCRPT_TEMPLATES );
	}
}
