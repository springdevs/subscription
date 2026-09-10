<?php
/**
 * Cart handling for subscription products.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\ExtendRestApi;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;

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

		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'add_to_cart_validation' ), 10, 4 );
		add_action( 'woocommerce_store_api_validate_add_to_cart', array( $this, 'add_to_cart_validation_store_api' ), 10, 1 );
	}

	/**
	 * Add to cart validation.
	 *
	 * @param bool $passed Passed ?.
	 * @param int  $product_id Product Id.
	 * @param int  $quantity Quantity.
	 * @param int  $variation_id Variation Id.
	 *
	 * @return bool
	 */
	public function add_to_cart_validation( bool $passed, int $product_id, int $quantity, int $variation_id = 0 ): bool {
		$product_id = $variation_id > 0 ? $variation_id : $product_id;
		$validation = $this->validate_cart_items( $product_id );

		if ( $validation['failed'] ) {
			$error_notice = empty( $validation['error_notice'] ) ? __( 'This product cannot be added to the cart.', 'subscription' ) : $validation['error_notice'];
			wc_add_notice( $error_notice, 'error' );
			return false;
		}

		// Validation passed.
		return $passed;
	}

	/**
	 * Add to cart validation.
	 *
	 * @param \WC_Product $product Product.
	 * @throws \Exception If validation fails.
	 */
	public function add_to_cart_validation_store_api( $product ) {
		$product_id = $product->get_id();
		$validation = $this->validate_cart_items( $product_id );

		if ( $validation['failed'] ) {
			$error_notice = empty( $validation['error_notice'] ) ? __( 'This product cannot be added to the cart.', 'subscription' ) : $validation['error_notice'];
			throw new \Exception( esc_html( $error_notice ) );
		}
	}

	/**
	 * Validate cart items.
	 *
	 * @param int $product_id Product Id.
	 * @return array
	 */
	public function validate_cart_items( $product_id ) {
		$cart_items = WC()->cart->cart_contents;

		$product = Subscription::get_subs_product( $product_id );

		$error_notice = null;
		$failed       = false;
		// A tied plan counts as a subscription even without classic `_subscrpt_enabled`.
		$enabled = $product->is_enabled() || subscrpt_plan_offered( $product_id );

		foreach ( $cart_items as $key => $cart_item ) {
			if ( isset( $cart_item['subscription'] ) ) {
				if ( $enabled ) {
					$error_notice = __( 'You cannot purchase multiple subscriptions at the same time.', 'subscription' );
				} else {
					$error_notice = __( 'You cannot purchase a subscription and a non-subscription product at the same time.', 'subscription' );
				}
				$failed = true;
			} elseif ( $enabled ) {
				$error_notice = __( 'You cannot purchase a subscription along with other products. Please remove other products from your cart first.', 'subscription' );
				$failed       = true;
			}
		}

		return [
			'failed'       => (bool) $failed,
			'error_notice' => $error_notice,
		];
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
		$product = Subscription::get_subs_product( $product );
		if ( $product->is_enabled() && $product->is_type( 'simple' ) ) {
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
					'key'    => __( 'Free Trial', 'subscription' ),
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
				// Plan items were validated against the plan by the resolver at
				// add-to-cart; their `subscription` snapshot intentionally differs
				// from the product's classic meta, so skip the classic re-check
				// (which would otherwise drop them from the cart).
				if ( ! empty( $value['subscrpt_plan_id'] ) ) {
					continue;
				}

				/**
				 * Product Object.
				 *
				 * @var \WC_Product $product
				 */
				$product = $value['data'];
				$product = Subscription::get_subs_product( $product );
				if ( isset( $value['subscription'] ) ) {
					if ( $product->is_type( 'simple' ) ) {
						if ( Helper::get_typos( 1, $product->get_meta( '_subscrpt_timing_option' ) ) !== $value['subscription']['type'] || $product->get_trial() !== $value['subscription']['trial'] ) {
							// remove the item.
							wc_add_notice( __( 'An item which is no longer available was removed from your cart.', 'subscription' ), 'error' );
							WC()->cart->remove_cart_item( $key );
						}
					} else {
						// remove the item.
						wc_add_notice( __( 'An item which is no longer available was removed from your cart.', 'subscription' ), 'error' );
						WC()->cart->remove_cart_item( $key );
					}
				} elseif ( $product->get_meta( '_subscrpt_enabled' ) ) {
					// remove the item.
					wc_add_notice( __( 'An item which is no longer available was removed from your cart.', 'subscription' ), 'error' );
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
				'description'      => __( 'List of recurring totals in cart.', 'subscription' ),
				'type'             => 'array',
				'readonly'         => true,
				'recurring_totals' => array(
					'price'                  => array(
						'description' => __( 'price of the subscription, after any discount that applies to renewals.', 'subscription' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'full_price'             => array(
						'description' => __( 'price of the subscription before any discount.', 'subscription' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'first_price'            => array(
						'description' => __( 'amount charged today, after all discounts.', 'subscription' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'has_recurring_discount' => array(
						'description' => __( 'Whether a discount applies to renewals.', 'subscription' ),
						'type'        => array( 'boolean' ),
						'readonly'    => true,
					),
					'has_one_time_discount'  => array(
						'description' => __( 'Whether a discount applies to the first payment only.', 'subscription' ),
						'type'        => array( 'boolean' ),
						'readonly'    => true,
					),
					'recurring_limit'        => array(
						'description' => __( 'Number of payments the recurring discount covers. 0 means unlimited.', 'subscription' ),
						'type'        => array( 'number' ),
						'readonly'    => true,
					),
					'time'                   => array(
						'description' => __( 'time of the subscription.', 'subscription' ),
						'type'        => array( 'number' ),
						'readonly'    => true,
					),
					'type'                   => array(
						'description' => __( 'type of the subscription.', 'subscription' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'description'            => array(
						'description' => __( 'price of the subscription description.', 'subscription' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'can_user_cancel'        => array(
						'description' => __( 'Allow User Cancellation?', 'subscription' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'max_no_payment'         => array(
						'description' => __( 'Maximum Total Payments', 'subscription' ),
						'type'        => array( 'number' ),
						'readonly'    => true,
					),
					'split_total'            => array(
						'description' => __( 'Total price for a split-payment plan (entered plan price).', 'subscription' ),
						'type'        => array( 'number', 'null' ),
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
			foreach ( $cart_items as $cart_item_key => $cart_item ) {
				if ( isset( $cart_item['subscription'] ) && $cart_item['subscription']['type'] ) {
					$cart_subscription = $cart_item['subscription'];
					$start_date        = Helper::start_date( $cart_subscription['trial'] );
					$next_date         = Helper::next_date(
						( $cart_subscription['time'] ?? 1 ) . ' ' . $cart_subscription['type'],
						$cart_subscription['trial']
					);

					// Subscription timing & type
					$time = $cart_subscription['time'];
					$type = Helper::get_typos( $time, $cart_subscription['type'], true );

					// Discount-aware totals, shared with the classic cart.
					$price_data = Helper::build_cart_recurring_price_data( $cart_item, $cart_item_key, $type );

					// Description
					$description = empty( $cart_subscription['trial'] )
								? __( 'Next billing on', 'subscription' ) . ': ' . $next_date
								: __( 'First billing on', 'subscription' ) . ': ' . $start_date;

					$recurrings[] = apply_filters(
						'subscrpt_cart_recurring_data',
						array(
							'price'                  => $price_data['total'],
							'full_price'             => $price_data['full_total'],
							'first_price'            => $price_data['first_total'],
							'has_recurring_discount' => $price_data['has_recurring_discount'],
							'has_one_time_discount'  => $price_data['has_one_time_discount'],
							'recurring_limit'        => $price_data['recurring_limit'],
							'time'                   => $time,
							'type'                   => $type,
							'description'            => $description,
							'can_user_cancel'        => $cart_item['data']->get_meta( '_subscrpt_user_cancel' ),
							'max_no_payment'         => ! empty( $cart_item['subscrpt_max_no_payment'] )
								? (int) $cart_item['subscrpt_max_no_payment']
								: $cart_item['data']->get_meta( '_subscrpt_max_no_payment' ),
							'split_total'            => isset( $cart_item['subscrpt_split_total'] ) ? (float) $cart_item['subscrpt_split_total'] : null,
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
			'time'           => array(
				'description' => __( 'time of the subscription type.', 'subscription' ),
				'type'        => array( 'number', 'null' ),
				'readonly'    => true,
			),
			'type'           => array(
				'description' => __( 'the subscription type.', 'subscription' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
			'trial'          => array(
				'description' => __( 'the subscription trial.', 'subscription' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
			'signup_fee'     => array(
				'description' => __( 'Signup Fee amount.', 'subscription' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
			'cost'           => array(
				'description' => __( 'Recurring amount.', 'subscription' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
			'max_no_payment' => array(
				'description' => __( 'Maximum Total Payments', 'subscription' ),
				'type'        => array( 'number' ),
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
			'time'           => null,
			'type'           => null,
			'trial'          => null,
			'signup_fee'     => null,
			'cost'           => null,
			'max_no_payment' => null,
		);

		if ( isset( $cart_item['subscription'] ) ) {
			$item_data = $cart_item['subscription'];
			unset( $item_data['per_cost'] );
			$item_data['cost'] = (float) $cart_item['subscription']['per_cost'] * $cart_item['quantity'];

			// Plan items don't stamp the installment count into the subscription
			// array (it rides the cart item as subscrpt_max_no_payment); classic
			// products carry it on the product meta.
			if ( ! isset( $item_data['max_no_payment'] ) ) {
				$item_data['max_no_payment'] = ! empty( $cart_item['subscrpt_max_no_payment'] )
					? (int) $cart_item['subscrpt_max_no_payment']
					: $cart_item['data']->get_meta( '_subscrpt_max_no_payment' );
			}

			// Normalise the cadence word to singular/plural by frequency for the
			// blocks (Store API) cart — plan items store the raw plural interval
			// (e.g. "months"), which the block would otherwise render as-is.
			if ( ! empty( $item_data['type'] ) ) {
				$sub_time          = max( 1, (int) ( $item_data['time'] ?? 1 ) );
				$item_data['type'] = Helper::get_typos( $sub_time, $item_data['type'] );
			}
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
	public function add_to_cart_item_data( array $cart_item_data, int $product_id ): array {
		$product = Subscription::get_subs_product( $product_id );
		if ( ! $product->is_type( 'simple' ) ) {
			return $cart_item_data;
		}
		// Plan products stamp their subscription snapshot in the plan checkout
		// (Frontend\PlanCheckout / Pro), gated on the chosen plan id — so a One-Time
		// purchase of a plan product is not wrongly tagged as a subscription here
		// (which would show a cadence + list it under "Recurring totals").
		if ( subscrpt_product_has_plan( $product_id ) ) {
			return $cart_item_data;
		}
		if ( $product->is_enabled() ) :
			$subscription_data          = array();
			$subscription_data['time']  = null;
			$subscription_data['type']  = $product->get_timing_option();
			$subscription_data['trial'] = null;
			if ( $product->has_trial() ) {
				$subscription_data['trial'] = $product->get_trial();
			}
			$subscription_data['signup_fee']                  = null;
			$subscription_data['per_cost']                    = $product->get_price();
			$cart_item_data['subscription']                   = apply_filters( 'subscrpt_block_simple_cart_item_data', $subscription_data, $product, $cart_item_data );
			$cart_item_data['subscription']['max_no_payment'] = $product->get_meta( '_subscrpt_max_no_payment' );
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
		$product = Subscription::get_subs_product( $cart_item['product_id'] );
		if ( ! $product->is_type( 'simple' ) ) {
			return $price;
		}

		// A tied plan makes it a subscription even without classic `_subscrpt_enabled`;
		// get_price_html() already resolves to the plan line (Frontend\Plans), so this
		// shows the plan cadence on the cart line without doubling.
		if ( $product->is_enabled() || subscrpt_plan_offered( $product->get_id() ) ) {
			return $product->get_price_html();
		}

		return $price;
	}

	/**
	 * Display "Recurring totals" on cart
	 *
	 * @return void
	 */
	public function add_rows_order_total() {
		$cart_items = WC()->cart->get_cart_contents();
		$recurrs    = Helper::get_recurrs_from_cart( $cart_items );
		if ( 0 === count( $recurrs ) ) {
			return;
		}
		?>
		<tr class="recurring-total">
			<th><?php esc_html_e( 'Recurring totals', 'subscription' ); ?></th>
			<td data-title="<?php esc_attr_e( 'Recurring totals', 'subscription' ); ?>">
				<?php foreach ( $recurrs as $recurr ) : ?>
					<p>
						<span><?php echo wp_kses_post( $recurr['price_html'] ); ?></span>
						<?php if ( $recurr['max_no_payment'] > 0 ) : ?>
							<span>x <?php echo esc_html( $recurr['max_no_payment'] ); ?></span>
						<?php endif; ?>
						<br />

						<small>
							<?php
								$billing_text = $recurr['trial_status']
												? __( 'First billing on', 'subscription' )
												: __( 'Next billing on', 'subscription' );

								echo esc_html( $billing_text . ': ' );
								echo esc_html( $recurr['trial_status'] ? $recurr['start_date'] : $recurr['next_date'] );
							?>
						</small>

						<?php if ( ! empty( $recurr['has_one_time_discount'] ) ) : ?>
							<br />
							<small>
								<?php
								echo wp_kses_post(
									sprintf(
										// translators: 1: amount paid today, 2: amount charged on each renewal.
										__( 'You pay %1$s today. %2$s will be charged from the next renewal.', 'subscription' ),
										wc_price( $recurr['first_total'] ?? 0 ),
										wc_price( $recurr['total'] ?? 0 )
									)
								);
								?>
							</small>
						<?php endif; ?>

						<?php if ( ! empty( $recurr['has_recurring_discount'] ) && (int) ( $recurr['recurring_limit'] ?? 0 ) > 1 ) : ?>
							<br />
							<small>
								<?php
								echo wp_kses_post(
									sprintf(
										// translators: 1: number of discounted payments, 2: full price charged afterwards.
										__( 'Discount applies to your first %1$s payments. After that, %2$s.', 'subscription' ),
										number_format_i18n( (int) $recurr['recurring_limit'] ),
										$recurr['full_price_html'] ?? ''
									)
								);
								?>
							</small>
						<?php endif; ?>

						<?php if ( 'yes' === $recurr['can_user_cancel'] && 0 === (int) $recurr['max_no_payment'] ) : ?>
							<br />
							<small><?php esc_html_e( 'You can cancel subscription at any time!', 'subscription' ); ?></small>
						<?php endif; ?>

						<!-- add how many times will be build if _subscrpt_renewal_limit is not 0 -->
						<?php if ( (int) $recurr['max_no_payment'] > 0 ) : ?>
							<br>
							<small>
								<?php
								echo wp_kses_post(
									sprintf(
										// translators: 1: number of installments, 2: total amount.
										__( 'This subscription will be billed in %1$s installments, for a total of %2$s.', 'subscription' ),
										esc_html( $recurr['max_no_payment'] ),
										wc_price( isset( $recurr['split_total'] ) && null !== $recurr['split_total'] ? $recurr['split_total'] : $recurr['price'] * (int) $recurr['max_no_payment'] )
									)
								);
								?>
							</small>
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
			// Check if maximum payment limit has been reached
			if ( subscrpt_is_max_payments_reached( $expired ) ) {
				wc_add_notice( __( 'This subscription has reached its maximum payment limit and cannot be renewed further.', 'subscription' ), 'error' );
				return $cart_item_data; // Don't add renew status
			}

			$cart_item_data['renew_subscrpt'] = true;
		}

		return $cart_item_data;
	}
}
