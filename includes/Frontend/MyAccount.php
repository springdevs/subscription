<?php

namespace SpringDevs\Subscription\Frontend;

/**
 * Class MyAccount
 *
 * @package SpringDevs\Subscription\Frontend
 */
class MyAccount
{

	/**
	 * Initialize the class
	 */
	public function __construct()
	{
		add_action('init', array($this, 'flush_rewrite_rules'));
		add_filter('woocommerce_account_menu_items', array($this, 'custom_my_account_menu_items'));
		add_filter('woocommerce_endpoint_view-subscription_title', array($this, 'change_single_title'));
		add_filter('the_title', array($this, 'change_lists_title'), 10);
		add_filter('woocommerce_get_query_vars', array($this, 'custom_query_vars'));
		add_action('woocommerce_account_view-subscription_endpoint', array($this, 'view_subscrpt_content'));
		add_action('woocommerce_account_subscriptions_endpoint', array($this, 'subscrpt_endpoint_content'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
	}

	/**
	 * Add custom url on MyAccount.
	 *
	 * @param array $query_vars query_vars.
	 *
	 * @return array
	 */
	public function custom_query_vars(array $query_vars): array
	{
		$query_vars['view-subscription'] = 'view-subscription';
		return $query_vars;
	}

	/**
	 * Display Subscription Content.
	 *
	 * @param Int $id Post ID.
	 */
	public function view_subscrpt_content(int $id)
	{
		$post_meta   = get_post_meta($id, '_order_subscrpt_meta', true);
		$order       = wc_get_order($post_meta['order_id']);
		$order_item  = $order->get_item($post_meta['order_item_id']);
		$status      = get_post_status($id);
		$user_cancel = get_post_meta($id, '_subscrpt_user_cancel', true);

		$subscrpt_nonce = wp_create_nonce('subscrpt_nonce');
		$action_buttons = array();

		if ('cancelled' !== $status) {
			if (in_array($status, array('pending', 'active', 'on_hold'), true) && 'yes' === $user_cancel) {
				$action_buttons['cancel'] = array(
					'url'   => subscrpt_get_action_url('cancelled', $subscrpt_nonce, $id),
					'label' => __('Cancel', 'sdevs_subscrpt'),
					'class' => 'cancel',
				);
			} elseif (trim($status) === trim('pe_cancelled')) {
				$action_buttons['reactive'] = array(
					'url'   => subscrpt_get_action_url('reactive', $subscrpt_nonce, $id),
					'label' => __('Reactive', 'sdevs_subscrpt'),
				);
			} elseif ('expired' === $status && 'pending' !== $order->get_status()) {
				$action_buttons['renew'] = array(
					'url'   => subscrpt_get_action_url('renew', $subscrpt_nonce, $id),
					'label' => __('Renew', 'sdevs_subscrpt'),
				);
			}

			if ('pending' === $order->get_status()) {
				$action_buttons['pay_now'] = array(
					'url'   => $order->get_checkout_payment_url(),
					'label' => __('Pay now', 'sdevs_subscrpt'),
				);
			}
		}

		$post_status_object = get_post_status_object($status);
		$action_buttons     = apply_filters('subscrpt_single_action_buttons', $action_buttons, $id, $subscrpt_nonce, $status);

		wc_get_template(
			'myaccount/single.php',
			array(
				'id'              => $id,
				'post_meta'       => $post_meta,
				'order'           => $order,
				'order_item'      => $order_item,
				'status'          => $post_status_object,
				'user_cancel'     => $user_cancel,
				'action_buttons'  => $action_buttons,
				'wp_button_class' => wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : '',
			),
			'subscription',
			SUBSCRPT_TEMPLATES
		);
	}

	/**
	 * Re-write flush
	 */
	public function flush_rewrite_rules()
	{
		add_rewrite_endpoint('subscriptions', EP_ROOT | EP_PAGES);
		flush_rewrite_rules();
	}

	/**
	 * Change View Subscription Title
	 *
	 * @param String $title Title.
	 *
	 * @return String
	 */
	public function change_single_title(string $title): string
	{
		/* translators: %s: Subscription ID */
		return sprintf(__('Subscription #%s', 'sdevs_subscrpt'), get_query_var('view-subscription'));
	}

	/**
	 * Change Subscription Lists Title
	 *
	 * @param String $title Title.
	 *
	 * @return String
	 */
	public function change_lists_title(string $title): string
	{
		global $wp_query;
		$is_endpoint = isset($wp_query->query_vars['subscriptions']);
		if ($is_endpoint && !is_admin() && is_account_page()) {
			$title = __('My Subscriptions', 'sdevs_subscrpt');
		}
		return $title;
	}

	/**
	 * Filter menu items.
	 *
	 * @param array $items MyAccount menu items.
	 * @return array
	 */
	public function custom_my_account_menu_items(array $items): array
	{
		$logout = $items['customer-logout'];
		unset($items['customer-logout']);
		$items['subscriptions']   = __('Subscriptions', 'sdevs_subscrpt');
		$items['customer-logout'] = $logout;
		return $items;
	}

	/**
	 * Subscription Single EndPoint Content
	 */
	public function subscrpt_endpoint_content()
	{
		wc_get_template(
			'myaccount/subscriptions.php',
			array(
				'wp_button_class' => wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : '',
			),
			'subscription',
			SUBSCRPT_TEMPLATES
		);
	}

	/**
	 * Enqueue assets
	 */
	public function enqueue_styles()
	{
		wp_enqueue_style('subscrpt_status_css');
	}
}
