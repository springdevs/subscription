<?php

namespace SpringDevs\Subscription\Traits;

use Exception;
use SpringDevs\Subscription\Illuminate\Helper;

trait Email {
	/**
	 * Template list with emails.
	 *
	 * @var array[]
	 */
	private $templates = array(
		'subscrpt_status_changed_admin_email'   => array(
			'html'  => 'emails/status-changed-admin-html.php',
			'plain' => 'emails/plains/status-changed-admin-plain.php',
		),
		'subscrpt_subscription_expired_email'   => array(
			'html'  => 'emails/subscription-expired-html.php',
			'plain' => 'emails/plains/subscription-expired-plain.php',
		),
		'subscrpt_subscription_cancelled_email' => array(
			'html'  => 'emails/subscription-cancelled-html.php',
			'plain' => 'emails/plains/subscription-cancelled-plain.php',
		),
	);
	/**
	 * Template path.
	 *
	 * @var string
	 */
	public $template_base;

	/**
	 * Plain text template path.
	 *
	 * @var string
	 */
	public $template_plain;

	/**
	 * HTML template path.
	 *
	 * @var string
	 */
	public $template_html;

	/**
	 * Subscription id.
	 *
	 * @var int
	 */
	public $subscription_id;

	/**
	 * Product Name ( Order Item ).
	 *
	 * @var string
	 */
	public $product_name;

	/**
	 * Subscription's quantity.
	 *
	 * @var int
	 */
	public $qty;

	/**
	 * Formatted recurring amount.
	 *
	 * @var string
	 */
	public $amount;

	/**
	 * Contain extra data to pass template.
	 *
	 * @var array
	 */
	public $extra = array();

	/**
	 * Get email heading.
	 *
	 * @return string
	 * @since  3.1.0
	 */
	public function get_default_heading(): string {
		return __( 'Subscription: #{subscription_id}', 'sdevs_subscrpt' );
	}

	/**
	 * Get the email content in HTML format.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return $this->render_template( $this->template_html );
	}

	/**
	 * Get the email content in Plain Format.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return $this->render_template( $this->template_plain );
	}

	/**
	 * Render template for email.
	 *
	 * @param string $path Template Path.
	 *
	 * @return string
	 */
	public function render_template( string $path ): string {
		return wc_get_template_html(
			$path,
			array_merge(
				array(
					'id'            => $this->subscription_id,
					'email_heading' => $this->get_heading(),
					'product_name'  => $this->product_name,
					'qty'           => $this->qty,
					'amount'        => $this->amount,
				),
				$this->extra
			),
			'subscription',
			$this->template_base
		);
	}

	/**
	 * Set template for mail.
	 *
	 * @param string $id id of email.
	 * @return void
	 */
	public function set_template( string $id ) {
		$this->template_base = SUBSCRPT_TEMPLATES;
		if ( isset( $this->templates[ $id ] ) ) {
			$this->template_html  = $this->templates[ $id ]['html'];
			$this->template_plain = $this->templates[ $id ]['plain'];
			add_filter( 'woocommerce_template_directory', array( $this, 'filter_woocommerce_template_directory' ), 10, 2 );
		}
	}

	/**
	 * Set data for subscription table.
	 *
	 * @return void
	 */
	public function set_table_data() {
		$order_item_id = get_post_meta( $this->subscription_id, '_subscrpt_order_item_id', true );
		try {
			$order_id = wc_get_order_id_by_order_item_id( $order_item_id );
		} catch ( Exception $e ) {
			return;
		}
		$order      = wc_get_order( $order_id );
		$order_item = $order->get_item( $order_item_id );

		$this->product_name = $order_item->get_name();
		$this->qty          = $order_item->get_quantity();
		$this->amount       = Helper::format_price_with_order_item( get_post_meta( $this->subscription_id, '_subscrpt_price', true ), $order_item->get_id() );
	}

	/**
	 * Filter WooCommerce template directory.
	 *
	 * @param string $directory Directory name.
	 * @param string $template Template path.
	 * @return string
	 */
	public function filter_woocommerce_template_directory( string $directory, string $template ): string {
		$email_templates = array( $this->template_plain, $this->template_html );
		if ( in_array( $template, $email_templates, true ) ) {
			return 'subscription';
		}

		return $directory;
	}
}
