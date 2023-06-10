<?php

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Product class
 * control single product page
 */
class Product {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'change_single_add_to_cart_text' ) );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'change_single_add_to_cart_text' ) );
		add_filter( 'woocommerce_get_price_html', array( $this, 'change_price_html' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'change_price_cart_html' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'change_price_cart_html' ), 10, 3 );
		add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'add_rows_order_total' ) );
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'add_rows_order_total' ) );
		add_action( 'woocommerce_review_order_before_cart_contents', array( $this, 'change_cart_calculates' ) );
		add_action( 'woocommerce_before_cart_totals', array( $this, 'change_cart_calculates' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_cart_fee' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_to_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_is_purchasable', array( $this, 'check_if_purchasable' ), 10, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'remove_button_active_products' ), 10, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'text_if_active' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_product_meta' ), 10, 3 );
		add_filter( 'woocommerce_cart_get_total', array( $this, 'calculates_cart_total' ) );
	}

	/**
	 * Update Cart total
	 *
	 * @param mixed $total Cart total.
	 *
	 * @return mixed
	 */
	public function calculates_cart_total( $total ) {
		$cart_items = WC()->cart->cart_contents;
		foreach ( $cart_items as $cart_item ) {
			$conditional_key = apply_filters( 'subscrpt_filter_checkout_conditional_key', $cart_item['product_id'], $cart_item );
			$post_meta       = get_post_meta( $conditional_key, '_subscrpt_meta', true );
			$has_trial       = Helper::check_trial( $conditional_key );
			if ( is_array( $post_meta ) && $post_meta['enable'] ) {
				if ( ! empty( $post_meta['trial_time'] ) && $post_meta['trial_time'] > 0 && $has_trial ) {
					if ( isset( $cart_item['line_subtotal'] ) ) {
						$total = $total - $cart_item['line_subtotal'];
					}
				}
			}
		}
		return $total;
	}

	/**
	 * Add signup fee if available
	 *
	 * @param \WC_Cart $cart Cart.
	 */
	public function add_cart_fee( $cart ) {
		$cart_items = WC()->cart->cart_contents;
		$signup_fee = 0;
		foreach ( $cart_items as $cart_item ) {
			$conditional_key = apply_filters( 'subscrpt_filter_checkout_conditional_key', $cart_item['product_id'], $cart_item );
			$post_meta       = get_post_meta( $conditional_key, '_subscrpt_meta', true );
			if ( is_array( $post_meta ) && $post_meta['enable'] ) :
				$has_trial = Helper::check_trial( $conditional_key );
				if ( $has_trial && isset( $post_meta['signup_fee'] ) ) {
					$signup_fee += (int) $post_meta['signup_fee'];
				}
			endif;
		}
		if ( $signup_fee > 0 ) {
			$cart->add_fee( 'SignUp Fee', $signup_fee );
		}
	}

	/**
	 * Save renew meta
	 *
	 * @param Object $item Item.
	 * @param String $cart_item_key Cart Item Key.
	 * @param Array  $cart_item Cart Item.
	 */
	public function save_order_item_product_meta( $item, $cart_item_key, $cart_item ) {
		if ( isset( $cart_item['renew_subscrpt'] ) ) {
			$item->update_meta_data( '_renew_subscrpt', $cart_item['renew_subscrpt'] );
		}
	}

	/**
	 * Display notice if already purchased.
	 */
	public function text_if_active() {
		global $product;
		if ( $product->is_type( 'variable' ) ) {
			return;
		}
		$post_meta = get_post_meta( $product->get_id(), '_subscrpt_meta', true );
		$unexpired = Helper::subscription_exists( $product->get_id(), array( 'active', 'pending' ) );
		if ( is_array( $post_meta ) && isset( $post_meta['limit'] ) ) {
			if ( 'unlimited' === $post_meta['limit'] ) {
				return;
			}
			if ( 'one' === $post_meta['limit'] ) {
				if ( ! $unexpired ) {
					return false;
				}
			}
			if ( 'only_one' === $post_meta['limit'] ) {
				if ( ! Helper::check_trial( $product->get_id() ) ) {
					echo '<strong>' . esc_html_e( 'You Already Purchased These Product!', 'sdevs_subscrpt' ) . '</strong>';
				}
			}
		}
		if ( $unexpired ) {
			echo '<strong>' . esc_html_e( 'You Already Purchased These Product!', 'sdevs_subscrpt' ) . '</strong>';
		}
	}

	/**
	 * Remove button if product already subscribed.
	 *
	 * @param mixed       $button Button.
	 * @param \Wc_Product $product Product.
	 *
	 * @return mixed
	 */
	public function remove_button_active_products( $button, $product ) {
		if ( $product->is_type( 'variable' ) && ! subscrpt_pro_activated() ) {
			return $button;
		}
		$unexpired = Helper::subscription_exists( $product->get_id(), array( 'active', 'pending' ) );
		if ( $unexpired ) {
			return;
		}
		return $button;
	}

	/**
	 * Check if product pruchasable.
	 *
	 * @param Boolean     $is_purchasable True\False.
	 * @param \WC_Product $product Product.
	 *
	 * @return Boolean
	 */
	public function check_if_purchasable( $is_purchasable, $product ) {
		if ( $product->is_type( 'variable' ) ) {
			return $is_purchasable;
		}
		$unexpired = Helper::subscription_exists( $product->get_id(), array( 'active', 'pending' ) );
		if ( $unexpired ) {
			return false;
		}
		return $is_purchasable;
	}

	/**
	 * Add renew status.
	 *
	 * @param Array $cart_item_data cart_item_data.
	 * @param Int   $product_id Product ID.
	 *
	 * @return Array
	 */
	public function add_to_cart_item_data( $cart_item_data, $product_id ) {
		$expired = Helper::subscription_exists( $product_id, 'expired' );
		if ( $expired ) {
			$cart_item_data['renew_subscrpt'] = true;
		}
		return $cart_item_data;
	}

	/**
	 * Set cart subtotal.
	 *
	 * @param \WC_Cart $cart Cart.
	 */
	public function change_cart_calculates( $cart ) {
		$cart_items = WC()->cart->cart_contents;
		foreach ( $cart_items as $cart_item ) {
			$conditional_key = apply_filters( 'subscrpt_filter_checkout_conditional_key', $cart_item['product_id'], $cart_item );
			$post_meta       = get_post_meta( $conditional_key, '_subscrpt_meta', true );
			$has_trial       = Helper::check_trial( $conditional_key );
			if ( is_array( $post_meta ) && $post_meta['enable'] ) {
				if ( ! empty( $post_meta['trial_time'] ) && $post_meta['trial_time'] > 0 && $has_trial ) {
					$subtotal = WC()->cart->get_subtotal() - $cart_item['line_subtotal'];
					WC()->cart->set_subtotal( $subtotal );
				}
			}
		}
	}

	/**
	 * Change single product add-to-cart button text.
	 *
	 * @param String $text Add-to-cart button Text.
	 */
	public function change_single_add_to_cart_text( $text ) {
		global $product;
		if ( ! $product || $product->is_type( 'variable' ) || '' === $product->get_price() ) {
			return $text;
		}
		$post_meta = get_post_meta( $product->get_id(), '_subscrpt_meta', true );
		$expired   = Helper::subscription_exists( $product->get_id(), 'expired' );
		if ( $expired ) :
			$text = __( 'renew', 'sdevs_subscrpt' );
		elseif ( is_array( $post_meta ) && $post_meta['enable'] && '' !== $post_meta['cart_txt'] ) :
			$text = $post_meta['cart_txt'];
		endif;
		return $text;
	}

	/**
	 * Add trial, signup fee etc. with product price.
	 *
	 * @param mixed       $price Price.
	 * @param \WC_Product $product Product.
	 *
	 * @return mixed
	 */
	public function change_price_html( $price, $product ) {
		if ( $product->is_type( 'variable' ) || '' === $price ) {
			return $price;
		}

		$post_meta = get_post_meta( $product->get_id(), '_subscrpt_meta', true );
		if ( is_array( $post_meta ) && $post_meta['enable'] ) :
			$time            = '1' === $post_meta['time'] ? null : $post_meta['time'];
			$type            = Helper::get_typos( $post_meta['time'], $post_meta['type'] );
			$has_trial       = Helper::check_trial( $product->get_id() );
			$trial           = null;
			$signup_fee_html = null;
			if ( ! empty( $post_meta['trial_time'] ) && $post_meta['trial_time'] > 0 && $has_trial ) {
				$trial = '<br/> + Get ' . $post_meta['trial_time'] . ' ' . Helper::get_typos( $post_meta['trial_time'], $post_meta['trial_type'] ) . ' free trial!';
				if ( isset( $post_meta['signup_fee'] ) && '' !== $post_meta['signup_fee'] ) {
					$signup_fee_html = '<br/> + Signup fee of ' . wc_price( $post_meta['signup_fee'] );
				}
			}
			$price_html = $price . ' / ' . $time . ' ' . $type . $signup_fee_html . $trial;
			return $price_html;
		else :
			return $price;
		endif;
	}

	public function change_price_cart_html( $price, $cart_item, $cart_item_key ) {
		$product = wc_get_product( $cart_item['product_id'] );
		if ( $product->is_type( 'variable' ) ) {
			return $price;
		}
		$post_meta = get_post_meta( $cart_item['product_id'], '_subscrpt_meta', true );
		if ( is_array( $post_meta ) && $post_meta['enable'] ) :
			$time            = $post_meta['time'] == 1 ? null : $post_meta['time'];
			$price_type      = apply_filters( 'subscrpt_single_item_cart_price_type', $post_meta['type'], $cart_item );
			$type            = Helper::get_typos( $post_meta['time'], $price_type );
			$trial           = null;
			$signup_fee_html = null;
			$has_trial       = Helper::check_trial( $cart_item['product_id'] );
			if ( ! empty( $post_meta['trial_time'] ) && $post_meta['trial_time'] > 0 && $has_trial ) {
				$trial = '<br/><small> + ' . $post_meta['trial_time'] . ' ' . Helper::get_typos( $post_meta['trial_time'], $post_meta['trial_type'] ) . ' free trial!</small>';
				if ( isset( $post_meta['signup_fee'] ) ) {
					$signup_fee_html = '<br/><small> + Signup fee of ' . wc_price( $post_meta['signup_fee'] ) . '</small>';
				}
			}
			$price_html = $price . ' / ' . $time . ' ' . $type . $signup_fee_html . $trial;
			return apply_filters( 'subscrpt_single_item_cart_price_html', $price_html, $cart_item );
		else :
			return $price;
		endif;
	}

	public function add_rows_order_total() {
		$cart_items = WC()->cart->cart_contents;
		$recurrs    = array();
		foreach ( $cart_items as $cart_item ) {
			$post_meta = get_post_meta( $cart_item['product_id'], '_subscrpt_meta', true );
			$product   = wc_get_product( $cart_item['product_id'] );
			if ( ! $product->is_type( 'variable' ) && is_array( $post_meta ) && $post_meta['enable'] ) :
				$time       = $post_meta['time'] == 1 ? null : $post_meta['time'];
				$price_type = apply_filters( 'subscrpt_single_item_cart_price_type', $post_meta['type'], $cart_item );
				$type       = Helper::get_typos( $post_meta['time'], $price_type );
				$price_html = get_woocommerce_currency_symbol() . $cart_item['line_subtotal'] . ' / ' . $time . ' ' . $type;
				$trial      = null;
				$start_date = null;
				$has_trial  = Helper::check_trial( $cart_item['product_id'] );
				if ( ! empty( $post_meta['trial_time'] ) && $post_meta['trial_time'] > 0 && $has_trial ) {
					$trial      = $post_meta['trial_time'] . ' ' . Helper::get_typos( $post_meta['trial_time'], $post_meta['trial_type'] );
					$start_date = Helper::start_date(
						$post_meta['time'] . ' ' . $type,
						$trial
					);
				}
				$trial_status = $trial == null ? false : true;
				$next_date    = Helper::next_date(
					$post_meta['time'] . ' ' . $type,
					$trial
				);
				$next_date    = apply_filters( 'subscrpt_next_date_single_cart', $next_date, $cart_item, $trial );
				$recurrs[]    = array(
					'trial'      => $trial_status,
					'price_html' => $price_html,
					'start_date' => $start_date,
					'next_date'  => $next_date,
				);
			endif;
		}
		$recurrs = apply_filters( 'subscrpt_cart_recurring_items', $recurrs );
		if ( 0 === count( $recurrs ) ) {
			return;
		}
		?>
		<tr class="recurring-total">
			<th><?php esc_html_e( 'Recurring totals', 'sdevs_subscrpt' ); ?></th>
			<td data-title="<?php esc_attr_e( 'Recurring totals', 'sdevs_subscrpt' ); ?>">
				<?php foreach ( $recurrs as $recurr ) : ?>
					<?php if ( $recurr['trial'] ) : ?>
						<p>
							<span><?php echo wp_kses_post( $recurr['price_html'] ); ?></span><br />
							<small><?php echo esc_html_e( 'First billing on', 'sdevs_subscrpt' ); ?>: <?php echo esc_html( $recurr['start_date'] ?? wp_date( get_option( 'date_format' ) ) ); ?></small>
						</p>
						<?php else : ?>
						<p>
							<span><?php echo wp_kses_post( $recurr['price_html'] ); ?></span><br />
							<small><?php echo esc_html_e( 'Next billing on', 'sdevs_subscrpt' ); ?>: <?php echo wp_kses_post( $recurr['next_date'] ); ?></small>
						</p>
					<?php endif; ?>
				<?php endforeach; ?>
			</td>
		</tr>
		<?php
	}
}
