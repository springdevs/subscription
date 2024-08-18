<?php

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\ExtendRestApi;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;

/**
 * Cart class
 */
class Cart {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_to_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'define_custom_schema' ) );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'change_price_cart_html' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'change_price_cart_html' ), 10, 2 );
		add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'add_rows_order_total' ) );
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'add_rows_order_total' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'set_renew_status' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'check_cart_items' ) );
		add_filter( 'woocommerce_get_item_data', array( $this, 'set_line_item_meta' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'add_calculation_price_filter' ) );
		add_action( 'woocommerce_calculate_totals', array( $this, 'remove_calculation_price_filter' ) );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'remove_calculation_price_filter' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'add_to_cart_validation' ), 10, 2 );
	}

	/**
	 * Add to cart validation.
	 *
	 * @param bool $passed Passed ?.
	 * @param int  $product_id Product Id.
	 *
	 * @return bool
	 */
	public function add_to_cart_validation( $passed, $product_id ) {
		$cart_items   = WC()->cart->cart_contents;
		$error_notice = null;
		$failed       = false;
		$product      = wc_get_product( $product_id );
		$enabled      = $product->get_meta( '_subscrpt_enabled' );
		foreach ( $cart_items as $key => $cart_item ) {
			if ( isset( $cart_item['subscription'] ) ) {
				if ( $enabled ) {
					$error_notice = __( 'Currently You have an another Subscriptional product on cart !!', 'sdevs_subscrpt' );
				} else {
					$error_notice = __( 'Currently You have Subscriptional product in a cart !!', 'sdevs_subscrpt' );
				}
				$failed = true;
			} elseif ( $enabled ) {
					$failed       = true;
					$error_notice = __( 'Your cart isn\'t empty !!', 'sdevs_subscrpt' );
			}
		}

		if ( $failed ) {
			wc_add_notice( $error_notice, 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Add filter before cart calculation.
	 *
	 * @return void
	 */
	public function add_calculation_price_filter() {
		add_filter( 'woocommerce_product_get_price', array( $this, 'set_prices_for_calculation' ), 100, 2 );
	}

	/**
	 * Return 0 if product has trial.
	 *
	 * @param float       $price Price.
	 * @param \WC_Product $product Product object.
	 *
	 * @return float
	 */
	public function set_prices_for_calculation( $price, $product ) {
		if ( $product->get_meta( '_subscrpt_enabled' ) && $product->is_type( 'simple' ) ) {
			$trial_time_per = $product->get_meta( '_subscrpt_trial_timing_per' );
			if ( ! empty( $trial_time_per ) && $trial_time_per > 0 && Helper::check_trial( $product->get_id() ) ) {
				return 0;
			}
		}
		return $price;
	}

	/**
	 * Remove filter after calculate calculation.
	 *
	 * @return void
	 */
	public function remove_calculation_price_filter() {
		remove_filter( 'woocommerce_product_get_price', array( $this, 'set_prices_for_calculation' ), 100 );
	}

	/**
	 * Set line item for display meta details.
	 *
	 * @param array $cart_item_data Cart Item Data.
	 * @param array $cart_item Cart Item.
	 *
	 * @return array
	 */
	public function set_line_item_meta( $cart_item_data, $cart_item ) {
		if ( isset( $cart_item['subscription'] ) ) {
			if ( $cart_item['subscription']['trial'] ) {
				$cart_item_data[] = array(
					'key'    => __( 'Free Trial', 'sdevs_subscrpt' ),
					'value'  => $cart_item['subscription']['trial'],
					'hidden' => true,
					'__experimental_woocommerce_blocks_hidden' => false,
				);
			}
		}

		return $cart_item_data;
	}

	/**
	 * Check cart items if it's valid or not?
	 *
	 * @return void
	 */
	public function check_cart_items() {
		if ( subscrpt_pro_activated() ) {
			return;
		}
		$cart_items = WC()->cart->cart_contents;
		if ( is_array( $cart_items ) ) {
			foreach ( $cart_items as $key => $value ) {
				/**
				 * Product Object.
				 *
				 * @var \WC_Product $product
				 */
				$product = $value['data'];
				if ( isset( $value['subscription'] ) ) {
					if ( $product->is_type( 'simple' ) ) {
						$product_trial_per = $product->get_meta( '_subscrpt_trial_timing_per' );
						$trial             = null;
						if ( Helper::check_trial( $product->get_id() ) ) {
							$trial = ( $product_trial_per ? $product_trial_per . ' ' . Helper::get_typos( $product_trial_per ?? 1, $product->get_meta( '_subscrpt_trial_timing_option' ) )
							: null );
						}
						if ( Helper::get_typos( 1, $product->get_meta( '_subscrpt_timing_option' ) ) !== $value['subscription']['type'] || $trial !== $value['subscription']['trial'] ) {
							// remove the item.
							wc_add_notice( __( 'An item which is no longer available was removed from your cart.', 'sdevs_subscrpt' ), 'error' );
							WC()->cart->remove_cart_item( $key );
						}
					} else {
						// remove the item.
						wc_add_notice( __( 'An item which is no longer available was removed from your cart.', 'sdevs_subscrpt' ), 'error' );
						WC()->cart->remove_cart_item( $key );
					}
				} elseif ( $product->get_meta( '_subscrpt_enabled' ) ) {
					// remove the item.
					wc_add_notice( __( 'An item which is no longer available was removed from your cart.', 'sdevs_subscrpt' ), 'error' );
					WC()->cart->remove_cart_item( $key );
				}
			}
		}
	}

	/**
	 * Define custom schema.
	 *
	 * @return void
	 */
	public function define_custom_schema() {
		$this->register_endpoint_data(
			array(
				'endpoint'        => CartItemSchema::IDENTIFIER,
				'namespace'       => 'sdevs_subscription',
				'data_callback'   => array( $this, 'extend_cart_item_data' ),
				'schema_callback' => array( $this, 'extend_cart_item_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
		$this->register_endpoint_data(
			array(
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => 'sdevs_subscription',
				'data_callback'   => array( $this, 'extend_cart_data' ),
				'schema_callback' => array( $this, 'extend_cart_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Register subscription product schema into cart/items endpoint.
	 *
	 * @return array Registered schema.
	 */
	public function extend_cart_schema() {
		return array(
			'recurring_totals' => array(
				'description'      => __( 'List of recurring totals in cart.', 'sdevs_subscrpt' ),
				'type'             => 'array',
				'readonly'         => true,
				'recurring_totals' => array(
					'price'           => array(
						'description' => __( 'price of the subscription.', 'sdevs_subscrpt' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'time'            => array(
						'description' => __( 'time of the subscription.', 'sdevs_subscrpt' ),
						'type'        => array( 'number' ),
						'readonly'    => true,
					),
					'type'            => array(
						'description' => __( 'type of the subscription.', 'sdevs_subscrpt' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'description'     => array(
						'description' => __( 'price of the subscription description.', 'sdevs_subscrpt' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'can_user_cancel' => array(
						'description' => __( 'Can User Cancel?', 'sdevs_subscrpt' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
				),
			),
		);
	}

	/**
	 * Register subscription product data into cart/items endpoint.
	 *
	 * @return array $item_data Registered data or empty array if condition is not satisfied.
	 */
	public function extend_cart_data() {
		$cart_items = WC()->cart->cart_contents;
		$recurrings = array();
		if ( $cart_items ) {
			foreach ( $cart_items as $cart_item ) {
				if ( isset( $cart_item['subscription'] ) && $cart_item['subscription']['type'] ) {
					$cart_subscription = $cart_item['subscription'];
					$start_date        = Helper::start_date( $cart_subscription['trial'] );
					$next_date         = Helper::next_date(
						( $cart_subscription['time'] ?? 1 ) . ' ' . $cart_subscription['type'],
						$cart_subscription['trial']
					);

					$recurrings[] = apply_filters(
						'subscrpt_cart_recurring_data',
						array(
							'price'           => ( $cart_item['subscription']['per_cost'] * $cart_item['quantity'] ) * 100,
							'time'            => $cart_subscription['time'],
							'type'            => $cart_subscription['type'],
							'description'     => empty( $cart_subscription['trial'] ) ? 'Next billing on: ' . $next_date : 'First billing on: ' . $start_date,
							'can_user_cancel' => $cart_item['data']->get_meta( '_subscrpt_user_cancel' ),
						),
						$cart_item
					);
				}
			}
		}
		return $recurrings;
	}

	/**
	 * Register subscription product schema into cart/items endpoint.
	 *
	 * @return array Registered schema.
	 */
	public function extend_cart_item_schema() {
		return array(
			'time'       => array(
				'description' => __( 'time of the subscription type.', 'sdevs_subscrpt' ),
				'type'        => array( 'number', 'null' ),
				'readonly'    => true,
			),
			'type'       => array(
				'description' => __( 'the subscription type.', 'sdevs_subscrpt' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
			'trial'      => array(
				'description' => __( 'the subscription trial.', 'sdevs_subscrpt' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
			'signup_fee' => array(
				'description' => __( 'Signup Fee amount.', 'sdevs_subscrpt' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
			'cost'       => array(
				'description' => __( 'Recurring amount.', 'sdevs_subscrpt' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
		);
	}

	/**
	 * Register subscription product data into cart/items endpoint.
	 *
	 * @param array $cart_item Current cart item data.
	 *
	 * @return array $item_data Registered data or empty array if condition is not satisfied.
	 */
	public function extend_cart_item_data( $cart_item ) {
		$item_data = array(
			'time'       => null,
			'type'       => null,
			'trial'      => null,
			'signup_fee' => null,
			'cost'       => null,
		);
		if ( isset( $cart_item['subscription'] ) ) {
			$item_data = $cart_item['subscription'];
			unset( $item_data['per_cost'] );
			$item_data['cost'] = $cart_item['subscription']['per_cost'] * $cart_item['quantity'];
		}
		if ( ! subscrpt_pro_activated() ) {
			$item_data['time']       = null;
			$item_data['signup_fee'] = null;
		}

		return $item_data;
	}

	/**
	 * Add product meta on cart item.
	 *
	 * @param array $cart_item_data cart_item_data.
	 * @param int   $product_id Product ID.
	 *
	 * @return array
	 */
	public function add_to_cart_item_data( $cart_item_data, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product->is_type( 'simple' ) ) {
			return $cart_item_data;
		}
		$enabled = $product->get_meta( '_subscrpt_enabled' );
		if ( $enabled ) :
			$price_type                 = $product->get_meta( '_subscrpt_timing_option' );
			$type                       = Helper::get_typos( 1, $price_type );
			$subscription_data          = array();
			$subscription_data['time']  = null;
			$subscription_data['type']  = $type;
			$subscription_data['trial'] = null;
			$trial_timing_per           = $product->get_meta( '_subscrpt_trial_timing_per' );
			if ( $trial_timing_per && Helper::check_trial( $product->get_id() ) ) {
				$subscription_data['trial'] = $trial_timing_per . ' ' . Helper::get_typos( $trial_timing_per, $product->get_meta( '_subscrpt_trial_timing_option' ) );
			}
			$subscription_data['signup_fee'] = null;
			$subscription_data['per_cost']   = $product->get_price();
			$cart_item_data['subscription']  = apply_filters( 'subscrpt_block_simple_cart_item_data', $subscription_data, $product, $cart_item_data );
		endif;
		return $cart_item_data;
	}

	/**
	 * Register endpoint data with the API.
	 *
	 * @param array $args Endpoint data to register.
	 */
	protected function register_endpoint_data( $args ) {
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			woocommerce_store_api_register_endpoint_data( $args );
		} else {
			Package::container()->get( ExtendRestApi::class )->register_endpoint_data( $args );
		}
	}

	/**
	 * Display formatted price on cart.
	 *
	 * @param string $price price.
	 * @param array  $cart_item cart item.
	 *
	 * @return string
	 */
	public function change_price_cart_html( $price, $cart_item ) {
		$product = wc_get_product( $cart_item['product_id'] );
		if ( ! $product->is_type( 'simple' ) ) {
			return $price;
		}

		$enabled = $product->get_meta( '_subscrpt_enabled' );
		if ( $enabled ) :
			$has_trial       = Helper::check_trial( $product->get_id() );
			$trial           = null;
			$meta_trial_time = $product->get_meta( '_subscrpt_trial_timing_per' );
			if ( ! empty( $meta_trial_time ) && $meta_trial_time > 0 && $has_trial ) {
				$trial = '<br/><small> + Get ' . $meta_trial_time . ' ' . Helper::get_typos( $meta_trial_time, $product->get_meta( '_subscrpt_trial_timing_option' ) ) . ' free trial!</small>';
			}
			$timing_option = $product->get_meta( '_subscrpt_timing_option' );
			$type          = Helper::get_typos( 1, $timing_option );

			return apply_filters( 'subscrpt_simple_price_html', ( $price . '/ ' . $type . $trial ), $product, $price, $timing_option, $trial );
		else :
			return $price;
		endif;
	}

	/**
	 * Display "Recurring totals" on cart
	 *
	 * @return void
	 */
	public function add_rows_order_total() {
		$cart_items = WC()->cart->cart_contents;
		$recurrs    = Helper::get_recurrs_for_cart( $cart_items );
		if ( 0 === count( $recurrs ) ) {
			return;
		}
		?>
		<tr class="recurring-total">
			<th><?php esc_html_e( 'Recurring totals', 'sdevs_subscrpt' ); ?></th>
			<td data-title="<?php esc_attr_e( 'Recurring totals', 'sdevs_subscrpt' ); ?>">
				<?php foreach ( $recurrs as $recurr ) : ?>
						<p>
							<span><?php echo wp_kses_post( $recurr['price_html'] ); ?></span><br />
							<small><?php echo esc_html_e( ( $recurr['trial_status'] ? 'First billing on' : 'Next billing on' ), 'sdevs_subscrpt' ); ?>: <?php echo esc_html( $recurr['trial_status'] ? $recurr['start_date'] : $recurr['next_date'] ); ?></small>
							<?php if ( 'yes' === $recurr['can_user_cancel'] ) : ?>
								<br>
								<small><?php echo esc_html_e( 'You can cancel subscription at any time!', 'sdevs_subscrpt' ); ?></small>
							<?php endif; ?>
						</p>
				<?php endforeach; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Add renew status.
	 *
	 * @param array $cart_item_data cart_item_data.
	 * @param int   $product_id Product ID.
	 *
	 * @return array
	 */
	public function set_renew_status( $cart_item_data, $product_id ) {
		$expired = Helper::subscription_exists( $product_id, 'expired' );
		if ( $expired ) {
			$cart_item_data['renew_subscrpt'] = true;
		}
		return $cart_item_data;
	}
}
