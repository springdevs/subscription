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
		add_action( 'woocommerce_account_view-subscription_endpoint', array( $this, 'view_subscrpt_content' ) );
		add_action( 'woocommerce_account_subscriptions_endpoint', array( $this, 'subscrpt_endpoint_content' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	public function custom_query_vars( $query_vars ) {
		$query_vars['view-subscription'] = 'view-subscription';
		return $query_vars;
	}

	public function view_subscrpt_content( $id ) {
		$post_meta = get_post_meta( $id, '_order_subscrpt_meta', true );
		$order     = wc_get_order( $post_meta['order_id'] );
		$order_item = $order->get_item( $post_meta['order_item_id'] );
		$status    = get_post_status( $id );
		$user_cancell = get_post_meta( $id, '_subscrpt_user_cancell', true );

		wc_get_template( 
			'myaccount/single.php', 
			 array( 
				'id' => $id,
				'post_meta' => $post_meta,
				'order' => $order,
				'order_item' => $order_item,
				'status' => $status,
				'user_cancell' => $user_cancell
			 ), 
			'subscription', 
			SUBSCRPT_TEMPLATES 
		);
	}

	/**
	 * Re-write flush
	 */
	public function flush_rewrite_rules() {
		add_rewrite_endpoint( 'subscriptions', EP_ROOT | EP_PAGES );
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
		$is_endpoint = isset( $wp_query->query_vars['subscriptions'] );
		$is_single   = isset( $wp_query->query_vars['view-subscription'] );
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
		$items['subscriptions'] = __( 'Subscriptions', 'sdevs_subscrpt' );
		$items['customer-logout']   = $logout;
		return $items;
	}

	/**
	 * Bookable EndPoint Content
	 */
	public function subscrpt_endpoint_content() {
		wc_get_template( 'myaccount/subscriptions.php', array(), 'subscription', SUBSCRPT_TEMPLATES );
	}

	public function enqueue_styles()
	{
		wp_enqueue_style( 'subscrpt_status_css' );
	}
}
