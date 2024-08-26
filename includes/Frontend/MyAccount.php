<?php

namespace SpringDevs\Subscription\Frontend;

/**
 * Class MyAccount
 *
 * @package SpringDevs\Subscription\Frontend
 */
class MyAccount {


	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'flush_rewrite_rules' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'custom_my_account_menu_items' ) );
		add_filter( 'woocommerce_endpoint_view-subscription_title', array( $this, 'change_single_title' ) );
		add_filter( 'the_title', array( $this, 'change_lists_title' ), 10 );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'custom_query_vars' ) );
		add_action( 'woocommerce_account_view-subscription_endpoint', array( $this, 'view_subscrpt_content' ) );
		add_action( 'woocommerce_account_subscriptions_endpoint', array( $this, 'subscrpt_endpoint_content' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Add custom url on MyAccount.
	 *
	 * @param array $query_vars query_vars.
	 *
	 * @return array
	 */
	public function custom_query_vars( array $query_vars ): array {
		$query_vars['view-subscription'] = 'view-subscription';
		return $query_vars;
	}

	/**
	 * Display Subscription Content.
	 *
	 * @param Int $id Post ID.
	 */
	public function view_subscrpt_content( int $id ) {
		$order_id      = get_post_meta( $id, '_subscrpt_order_id', true );
		$order_item_id = get_post_meta( $id, '_subscrpt_order_item_id', true );
		if ( ! $order_id || ! $order_item_id ) {
			return wp_safe_redirect( '/404' );
		}
		$order       = wc_get_order( $order_id );
		$order_item  = $order->get_item( $order_item_id );
		$status      = get_post_status( $id );
		$user_cancel = get_post_meta( $id, '_subscrpt_user_cancel', true );
		$start_date  = get_post_meta( $id, '_subscrpt_start_date', true );
		$next_date   = get_post_meta( $id, '_subscrpt_next_date', true );
		$trial       = get_post_meta( $id, '_subscrpt_trial', true );
		$trial_mode  = get_post_meta( $id, '_subscrpt_trial_mode', true );

		$subscrpt_nonce = wp_create_nonce( 'subscrpt_nonce' );
		$action_buttons = array();

		if ( 'cancelled' !== $status ) {
			if ( in_array( $status, array( 'pending', 'active', 'on_hold' ), true ) && 'yes' === $user_cancel ) {
				$action_buttons['cancel'] = array(
					'url'   => subscrpt_get_action_url( 'cancelled', $subscrpt_nonce, $id ),
					'label' => __( 'Cancel', 'sdevs_subscrpt' ),
					'class' => 'cancel',
				);
			} elseif ( trim( $status ) === trim( 'pe_cancelled' ) ) {
				$action_buttons['reactive'] = array(
					'url'   => subscrpt_get_action_url( 'reactive', $subscrpt_nonce, $id ),
					'label' => __( 'Reactive', 'sdevs_subscrpt' ),
				);
			} elseif ( 'expired' === $status && 'pending' !== $order->get_status() ) {
				$action_buttons['renew'] = array(
					'url'   => subscrpt_get_action_url( 'renew', $subscrpt_nonce, $id ),
					'label' => __( 'Renew', 'sdevs_subscrpt' ),
				);
			}

			if ( 'pending' === $order->get_status() ) {
				$action_buttons['pay_now'] = array(
					'url'   => $order->get_checkout_payment_url(),
					'label' => __( 'Pay now', 'sdevs_subscrpt' ),
				);
			}
		}

		$is_auto_renew   = get_post_meta( $id, '_subscrpt_auto_renew', true );
		$renewal_setting = get_option( 'subscrpt_auto_renewal_toggle', '1' );
		if ( '' === $is_auto_renew && '1' === $renewal_setting ) {
			update_post_meta( $id, '_subscrpt_auto_renew', 1 );
		}
		$saved_methods = wc_get_customer_saved_methods_list( get_current_user_id() );
		$has_methods   = isset( $saved_methods['cc'] );
		if ( $has_methods && '1' === $renewal_setting && class_exists( 'WC_Stripe' ) && $order && 'stripe' === $order->get_payment_method() ) {
			if ( '0' === $is_auto_renew ) {
				$action_buttons['auto-renew-on'] = array(
					'url'   => subscrpt_get_action_url( 'renew-on', $subscrpt_nonce, $id ),
					'label' => __( 'Turn on Auto Renewal', 'sdevs_subscrpt' ),
				);
			} else {
				$action_buttons['auto-renew-off'] = array(
					'url'   => subscrpt_get_action_url( 'renew-off', $subscrpt_nonce, $id ),
					'label' => __( 'Turn off Auto Renewal', 'sdevs_subscrpt' ),
				);
			}
		}

		$post_status_object = get_post_status_object( $status );
		$action_buttons     = apply_filters( 'subscrpt_single_action_buttons', $action_buttons, $id, $subscrpt_nonce, $status );

		wc_get_template(
			'myaccount/single.php',
			array(
				'id'              => $id,
				'start_date'      => $start_date,
				'next_date'       => $next_date,
				'trial'           => $trial,
				'trial_mode'      => empty( $trial_mode ) ? 'off' : $trial_mode,
				'order'           => $order,
				'order_item'      => $order_item,
				'status'          => $post_status_object,
				'user_cancel'     => $user_cancel,
				'action_buttons'  => $action_buttons,
				'wp_button_class' => wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '',
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
	 * Change View Subscription Title
	 *
	 * @param string $title Title.
	 *
	 * @return string
	 */
	public function change_single_title( string $title ): string {
		/* translators: %s: Subscription ID */
		return sprintf( __( 'Subscription #%s', 'sdevs_subscrpt' ), get_query_var( 'view-subscription' ) );
	}

	/**
	 * Change Subscription Lists Title
	 *
	 * @param string $title Title.
	 *
	 * @return string
	 */
	public function change_lists_title( string $title ): string {
		global $wp_query;
		$is_endpoint = isset( $wp_query->query_vars['subscriptions'] );
		if ( $is_endpoint && ! is_admin() && is_account_page() ) {
			$title = __( 'My Subscriptions', 'sdevs_subscrpt' );
		}
		return $title;
	}

	/**
	 * Filter menu items.
	 *
	 * @param array $items MyAccount menu items.
	 * @return array
	 */
	public function custom_my_account_menu_items( array $items ): array {
		$logout = $items['customer-logout'];
		unset( $items['customer-logout'] );
		$items['subscriptions']   = __( 'Subscriptions', 'sdevs_subscrpt' );
		$items['customer-logout'] = $logout;
		return $items;
	}

	/**
	 * Subscription Single EndPoint Content.
	 *
	 * @param int $current_page Current Page.
	 */
	public function subscrpt_endpoint_content( $current_page ) {
		$current_page = empty( $current_page ) ? 1 : absint( $current_page );
		$args         = array(
			'author'         => get_current_user_id(),
			'posts_per_page' => 10,
			'paged'          => $current_page,
			'post_type'      => 'subscrpt_order',
			'post_status'    => array( 'pending', 'active', 'on_hold', 'cancelled', 'expired', 'pe_cancelled' ),
		);

		$postslist = new \WP_Query( $args );
		wc_get_template(
			'myaccount/subscriptions.php',
			array(
				'wp_button_class' => wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '',
				'postslist'       => $postslist,
				'current_page'    => $current_page,
			),
			'subscription',
			SUBSCRPT_TEMPLATES
		);
	}

	/**
	 * Enqueue assets
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'subscrpt_status_css' );
	}
}
