<?php

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;

// HPOS: This file is compatible with WooCommerce High-Performance Order Storage (HPOS).
// All WooCommerce order data is accessed via WooCommerce CRUD methods (wc_get_order, wc_get_order_item_meta, etc.).
// All direct post meta access is for subscription data only, not WooCommerce order data.
// If you add new order data access, use WooCommerce CRUD for HPOS compatibility.

/**
 * Class MyAccount
 *
 * @package SpringDevs\Subscription\Frontend
 */
class MyAccount {
	/**
	 * Subscriptions endpoint slug.
	 *
	 * @var string
	 */
	private $subscriptions_endpoint = 'subscriptions';

	/**
	 * View Subscriptions endpoint slug.
	 *
	 * @var string
	 */
	private $view_subscriptions_endpoint = 'view-subscription';


	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Assign endpoint slug from settings.
		$this->subscriptions_endpoint      = Subscription::get_user_endpoint( 'subs_list' );
		$this->view_subscriptions_endpoint = Subscription::get_user_endpoint( 'view_subs' );

		// Flush rewrite rules on init.
		add_action( 'init', array( $this, 'flush_rewrite_rules' ) );

		// Add My Subscriptions menu item.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'custom_my_account_menu_items' ), 200 );

		// Add subscription url endpoints to query vars.
		add_filter( 'woocommerce_get_query_vars', array( $this, 'custom_query_vars' ) );

		// Subscription EndPoint Content.
		add_action( "woocommerce_account_{$this->subscriptions_endpoint}_endpoint", array( $this, 'subscrpt_endpoint_content' ) );
		add_action( "woocommerce_account_{$this->view_subscriptions_endpoint}_endpoint", array( $this, 'view_subscrpt_content' ) );

		// Subscription page titles
		add_filter( "woocommerce_endpoint_{$this->subscriptions_endpoint}_title", array( $this, 'change_subscriptions_title' ) );
		add_filter( "woocommerce_endpoint_{$this->view_subscriptions_endpoint}_title", array( $this, 'change_single_subscription_title' ) );

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
		$query_vars[ $this->subscriptions_endpoint ]      = $this->subscriptions_endpoint;
		$query_vars[ $this->view_subscriptions_endpoint ] = $this->view_subscriptions_endpoint;

		return $query_vars;
	}

	/**
	 * Display Subscription Content.
	 *
	 * @param int $id Post ID.
	 */
	public function view_subscrpt_content( $id ) {
		$id = absint( $id ); // typecast to int.

		$subs_post       = get_post( $id );
		$author_id       = $subs_post ? (int) $subs_post->post_author : 0;
		$current_user_id = get_current_user_id();
		$user_is_admin   = current_user_can( 'manage_options' );

		if ( ! $user_is_admin && (int) $author_id !== (int) $current_user_id ) {
			return wp_safe_redirect( '/404' );
		}

		$subscription_id   = $id;
		$subscription_data = Helper::get_subscription_data( $subscription_id );
		$related_orders    = Helper::get_related_orders( $subscription_id );

		$status         = $subscription_data['status'] ?? '';
		$verbose_status = Helper::get_verbose_status( $status );

		$order_id      = $subscription_data['order']['order_id'] ?? 0;
		$order_item_id = $subscription_data['order']['order_item_id'] ?? 0;

		$order      = wc_get_order( $order_id );
		$order_item = $order ? $order->get_item( $order_item_id ) : null;

		if ( ! $order || ! $order_item ) {
			return wp_safe_redirect( '/404' );
		}

		$user_cancel = $subscription_data['can_user_cancel'] ?? false;

		$start_date = $subscription_data['start_date'] ?? '';
		$start_date = ! empty( $start_date ) ? wp_date( 'F j, Y', strtotime( $start_date ) ) : '-';

		$next_date = $subscription_data['next_date'] ?? '';
		$next_date = ! empty( $next_date ) ? wp_date( 'F j, Y', strtotime( $next_date ) ) : '-';

		$trial      = get_post_meta( $id, '_subscrpt_trial', true );
		$trial_mode = get_post_meta( $id, '_subscrpt_trial_mode', true );

		// Undiscounted subtotal, the discount that recurs, tax on the discounted amount, and the renewal total.
		$display_totals = Helper::get_subscription_display_totals( $id, $order_item );

		$price          = $display_totals['total'];
		$price_excl_tax = $display_totals['full_excl'];
		$tax_amount     = $display_totals['tax'];
		$discount       = $display_totals['discount'];

		$is_grace_period = isset( $subscription_data['grace_period'] );
		$grace_remaining = $subscription_data['grace_period']['remaining_days'] ?? 0;
		$grace_end_date  = $subscription_data['grace_period']['end_date'] ?? '';
		$grace_end_date  = ! empty( $grace_end_date ) ? wp_date( 'F j, Y', strtotime( $grace_end_date ) ) : '';

		$subscrpt_nonce = wp_create_nonce( 'subscrpt_nonce' );
		$action_buttons = array();

		if ( 'cancelled' !== $status ) {
			if ( in_array( $status, array( 'pending', 'active', 'on_hold' ), true ) && $user_cancel ) {
				$label = __( 'Cancel', 'subscription' );
				$label = apply_filters( 'subscrpt_split_payment_button_text', $label, 'cancel', $id, $status );

				$action_buttons['cancel'] = array(
					'url'   => subscrpt_get_action_url( 'cancelled', $subscrpt_nonce, $id ),
					'label' => $label,
					'class' => 'cancel',
				);
			} elseif ( trim( $status ) === trim( 'pe_cancelled' ) ) {
				$label = __( 'Reactivate', 'subscription' );
				$label = apply_filters( 'subscrpt_split_payment_button_text', $label, 'reactivate', $id, $status );

				$action_buttons['reactivate'] = array(
					'url'   => subscrpt_get_action_url( 'reactivate', $subscrpt_nonce, $id ),
					'label' => $label,
				);
			} elseif ( 'expired' === $status && 'pending' !== $order->get_status() ) {
				// Check if maximum payments reached before showing renew button
				if ( ! subscrpt_is_max_payments_reached( $id ) ) {
					$label = __( 'Renew', 'subscription' );
					$label = apply_filters( 'subscrpt_split_payment_button_text', $label, 'renew', $id, $status );

					$action_buttons['renew'] = array(
						'url'   => subscrpt_get_action_url( 'renew', $subscrpt_nonce, $id ),
						'label' => $label,
					);
				}
			}

			if ( 'pending' === $order->get_status() ) {
				$label = __( 'Pay now', 'subscription' );
				$label = apply_filters( 'subscrpt_split_payment_button_text', $label, 'pay_now', $id, $status );

				$action_buttons['pay_now'] = array(
					'url'   => $order->get_checkout_payment_url(),
					'label' => $label,
				);
			}
		}

		$is_auto_renew   = $subscription_data['is_auto_renew'];
		$renewal_setting = in_array( get_option( 'wp_subscription_auto_renewal_toggle', '1' ), [ 1, '1', 'true', 'yes' ], true );

		$saved_methods = wc_get_customer_saved_methods_list( get_current_user_id() );
		$has_methods   = isset( $saved_methods['cc'] );
		if ( $has_methods && $renewal_setting && class_exists( 'WC_Stripe' ) && $order && 'stripe' === $order->get_payment_method() ) {
			// Check maximum payment limit for auto-renewal buttons too
			if ( ! subscrpt_is_max_payments_reached( $id ) ) {
				if ( ! $is_auto_renew ) {
					$label = __( 'Turn on Auto Renewal', 'subscription' );
					$label = apply_filters( 'subscrpt_split_payment_button_text', $label, 'auto-renew-on', $id, $status );

					$action_buttons['auto-renew-on'] = array(
						'url'   => subscrpt_get_action_url( 'renew-on', $subscrpt_nonce, $id ),
						'label' => $label,
					);
				} else {
					$label = __( 'Turn off Auto Renewal', 'subscription' );
					$label = apply_filters( 'subscrpt_split_payment_button_text', $label, 'auto-renew-off', $id, $status );

					$action_buttons['auto-renew-off'] = array(
						'url'   => subscrpt_get_action_url( 'renew-off', $subscrpt_nonce, $id ),
						'label' => $label,
					);
				}
			}
		}

		// Allow programmatically disabling cancel button
		$disable_cancel = apply_filters( 'subscrpt_split_payment_disable_cancel', false, $id, $status );
		if ( $disable_cancel && isset( $action_buttons['cancel'] ) ) {
			unset( $action_buttons['cancel'] );
		}

		$action_buttons = apply_filters( 'subscrpt_single_action_buttons', $action_buttons, $id, $subscrpt_nonce, $status );

		wc_get_template(
			'myaccount/single.php',
			array(
				'id'              => $id,
				'status'          => $status,
				'verbose_status'  => $verbose_status,
				'start_date'      => $start_date,
				'next_date'       => $next_date,
				'is_grace_period' => $is_grace_period,
				'grace_remaining' => $grace_remaining,
				'grace_end_date'  => $grace_end_date,
				'trial'           => $trial,
				'trial_mode'      => empty( $trial_mode ) ? 'off' : $trial_mode,
				'order'           => $order,
				'order_item'      => $order_item,
				'related_orders'  => $related_orders,
				'price'           => $price,
				'price_excl_tax'  => $price_excl_tax,
				'tax'             => $tax_amount,
				'discount'        => $discount,
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
		add_rewrite_endpoint( $this->subscriptions_endpoint, EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	/**
	 * Change All Subscriptions Title
	 *
	 * @param string $title Title.
	 *
	 * @return string
	 */
	public function change_subscriptions_title( string $title ): string {
		$title = __( 'My Subscriptions', 'subscription' );
		return $title;
	}

	/**
	 * Change View Subscription Title
	 *
	 * @param string $title Title.
	 *
	 * @return string
	 */
	public function change_single_subscription_title( string $title ): string {
		$subs_id = get_query_var( $this->view_subscriptions_endpoint, 0 );

		/* translators: %s: Subscription ID */
		$title = sprintf( __( 'Subscription #%s', 'subscription' ), $subs_id );
		return $title;
	}

	/**
	 * Filter menu items.
	 *
	 * @param array $items MyAccount menu items.
	 * @return array
	 */
	public function custom_my_account_menu_items( array $items ): array {
		// Check if subscriptions menu item already exists to prevent duplicates
		if ( ! isset( $items[ $this->subscriptions_endpoint ] ) ) {
			$logout = $items['customer-logout'];
			unset( $items['customer-logout'] );

			$items[ $this->subscriptions_endpoint ] = __( 'Subscriptions', 'subscription' );
			$items['customer-logout']               = $logout;
		}
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
			'post_status'    => array( 'pending', 'active', 'on_hold', 'cancelled', 'expired', 'pe_cancelled', 'completed' ),
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
