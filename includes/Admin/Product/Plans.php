<?php
/**
 * Product-editor plan view (free).
 *
 * Renders, on the product editor Subscription tab, the plan(s) this product is
 * connected to plus a connect control to attach it to a Recurring plan group at
 * a per-product price. The classic `_subscrpt_*` meta inputs stay in the DOM
 * (hidden) behind a "Switch to classic settings" toggle. Writes go through the
 * wpsubscription/v1 REST API (see assets/js/admin/product-plans.js).
 *
 * Free mounts this for simple products; Pro also reuses render_toolbar() /
 * render_plan_view() / render_modals() for variable products (product-level
 * plan connection), with the per-variation classic fields behind the toggle.
 *
 * @package SpringDevs\Subscription\Admin\Product
 */

namespace SpringDevs\Subscription\Admin\Product;

use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * Product-editor plan view.
 */
class Plans {

	/**
	 * Register the mount point used when Pro renders the Subscription panel.
	 *
	 * With Pro active, Pro's `Admin/Product/Simple` renders the panel and fires
	 * `subscrpt_simple_plan_panel` at the top; free mounts its plan view there,
	 * toggling against Pro's classic fields (`.subscrpt-classic-fields`). With
	 * Pro inactive, free renders its own panel (see Admin\Product::subscription_forms).
	 */
	public function __construct() {
		add_action( 'subscrpt_simple_plan_panel', array( $this, 'render_mount' ) );
	}

	/**
	 * Mount the plan view inside Pro's Subscription panel.
	 *
	 * @param int $product_id Product being edited.
	 *
	 * @return void
	 */
	public function render_mount( $product_id ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product || ! $product->is_type( 'simple' ) ) {
			return;
		}
		?>
		<div data-subscrpt-product-plans data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"<?php echo self::should_default_classic( $product ) ? ' data-subscrpt-default-classic="1"' : ''; ?> style="margin-bottom:16px;">
			<?php self::render_toolbar( $product ); ?>
			<div data-subscrpt-plan-view>
				<?php self::render_plan_view( $product ); ?>
			</div>
		</div>
		<?php
		self::render_checkout_link_modal( $product );
		self::render_modals();
	}

	/**
	 * Render the Create-Plan-Group + Add-Selling-Plan modals once per page, so
	 * merchants can build a new plan group + plan without leaving the product
	 * editor. Pro-only: free's product editor is attach-only. The modals sit
	 * outside the [data-subscrpt-plan-view] region so an in-place refresh never
	 * duplicates them. Driven by the shared plan-forms.js module.
	 *
	 * @return void
	 */
	public static function render_modals() {
		if ( ! function_exists( 'subscrpt_pro_activated' ) || ! subscrpt_pro_activated() ) {
			return;
		}
		// modal-term.php expects a $plan (id + type); product-plans.js rewrites
		// the modal's group id/type before opening it for a freshly made group.
		$plan = array(
			'id'   => 0,
			'type' => 'recurring',
		);
		require __DIR__ . '/../views/plans/modal-create.php';
		require __DIR__ . '/../views/plans/modal-term.php';
	}

	/**
	 * Render the "Generate Checkout Link" modal for a plan-connected product.
	 *
	 * Builds a per-context (product / per-variation) list of the plans the product
	 * offers — plus a "One-time purchase" option when enabled — and embeds it as a
	 * JSON blob the modal JS uses to compose a direct add-to-cart or checkout link.
	 * The link carries `subscrpt_plan_id` (the term id) so PlanCheckout resolves the
	 * chosen plan; the empty id (one-time) omits it and relies on the bare-link
	 * fallback. Renders nothing when the product has no plan connections.
	 *
	 * @param \WC_Product|null $product Product being edited.
	 *
	 * @return void
	 */
	public static function render_checkout_link_modal( $product ) {
		if ( ! $product || empty( PlanRepository::get_product_connections( $product->get_id() ) ) ) {
			return;
		}

		$is_variable = $product->is_type( 'variable' );
		$product_id  = $product->get_id();

		// Plans a context offers, in the same order (and with the same ids) the
		// storefront resolver / generated link will use. One-time first when on.
		// One-time uses the sentinel value "onetime" (JS omits subscrpt_plan_id for
		// it) so the adv-select still resolves a non-empty default label.
		$build_plans = static function ( $vid, $one_time_enabled ) use ( $product_id ) {
			$plans = array();
			if ( $one_time_enabled ) {
				$plans[] = array(
					'value' => 'onetime',
					'label' => __( 'One-time purchase', 'subscription' ),
				);
			}
			foreach ( PlanRepository::resolve_for_product( $product_id, $vid ) as $subscrpt_row ) {
				$plans[] = array(
					'value' => (string) (int) $subscrpt_row['plan_id'],
					'label' => $subscrpt_row['group_title'] . ' · ' . $subscrpt_row['plan_title'],
				);
			}
			return $plans;
		};

		$contexts = array();
		if ( $is_variable ) {
			foreach ( $product->get_children() as $subscrpt_child_id ) {
				$subscrpt_variation = wc_get_product( $subscrpt_child_id );
				if ( ! $subscrpt_variation ) {
					continue;
				}
				$subscrpt_vid   = (int) $subscrpt_child_id;
				$subscrpt_plans = $build_plans( $subscrpt_vid, 'yes' === $subscrpt_variation->get_meta( '_subscrpt_one_time_enabled' ) );
				if ( empty( $subscrpt_plans ) ) {
					continue;
				}
				// An "Any …" attribute stores an empty value; such a variation can't
				// be resolved by id alone, so the checkout-link endpoint can't add it.
				$subscrpt_attrs   = $subscrpt_variation->get_variation_attributes();
				$subscrpt_has_any = in_array( '', array_map( 'strval', $subscrpt_attrs ), true );
				$contexts[]       = array(
					'vid'    => $subscrpt_vid,
					'label'  => self::variation_display_name( $subscrpt_variation, $product->get_name() ),
					'attrs'  => (object) $subscrpt_attrs,
					'hasAny' => $subscrpt_has_any,
					'plans'  => $subscrpt_plans,
				);
			}
		} else {
			$subscrpt_plans = $build_plans( 0, 'yes' === $product->get_meta( '_subscrpt_one_time_enabled' ) );
			if ( ! empty( $subscrpt_plans ) ) {
				$contexts[] = array(
					'vid'   => 0,
					'label' => '',
					'attrs' => (object) array(),
					'plans' => $subscrpt_plans,
				);
			}
		}

		if ( empty( $contexts ) ) {
			return;
		}

		// JS only needs the ids/attributes to compose the link; plans are rendered
		// as adv-selects below, so keep the blob slim.
		$subscrpt_ctx_data = array();
		foreach ( $contexts as $subscrpt_ctx ) {
			$subscrpt_ctx_data[] = array(
				'vid'    => $subscrpt_ctx['vid'],
				'attrs'  => $subscrpt_ctx['attrs'],
				'hasAny' => ! empty( $subscrpt_ctx['hasAny'] ),
			);
		}

		// Checkout link: WooCommerce Blocks' native checkout-link endpoint, which
		// empties the cart, adds `products=ID:QTY`, and redirects to checkout. It
		// resolves via the pretty path `/checkout-link/` when permalinks are on,
		// or the registered `?checkout-link=true` query var when they are plain.
		$subscrpt_checkout_base = get_option( 'permalink_structure' )
			? home_url( 'checkout-link/' )
			: add_query_arg( 'checkout-link', 'true', home_url( '/' ) );

		$subscrpt_data = array(
			'productId'        => $product_id,
			'type'             => $is_variable ? 'variable' : 'simple',
			// Add-to-cart link: WooCommerce's classic `?add-to-cart=` on the site root.
			'cartBase'         => home_url( '/' ),
			'checkoutLinkBase' => $subscrpt_checkout_base,
			'contexts'         => $subscrpt_ctx_data,
		);

		$subscrpt_field       = 'display:flex;flex-direction:column;gap:6px;';
		$subscrpt_field_l     = 'font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:var(--wpsubs-text-muted);';
		$subscrpt_default_vid = (int) $contexts[0]['vid'];
		?>
		<div class="wpsubs-modal" id="subscrpt-checkout-link" hidden data-subscrpt-checkout-data="<?php echo esc_attr( wp_json_encode( $subscrpt_data ) ); ?>">
			<div class="wpsubs-modal__backdrop" data-wpsubs-modal-close></div>
			<div class="wpsubs-modal__dialog" style="max-width:520px;">
				<div class="wpsubs-modal__head" style="align-items:flex-start;">
					<div style="display:flex;flex-direction:column;gap:8px;min-width:0;">
						<h2 class="wpsubs-modal__title" style="display:flex;align-items:center;gap:8px;margin:0;">
							<span class="dashicons dashicons-admin-links" style="color:var(--wpsubs-brand,#ff4d00);font-size:18px;width:18px;height:18px;line-height:1;"></span>
							<?php esc_html_e( 'Generate Checkout Link', 'subscription' ); ?>
						</h2>
						<p style="margin:0;font-size:12.5px;line-height:1.6;color:var(--wpsubs-text-muted);">
							<?php esc_html_e( 'Share a link that drops this product into a customer’s cart with the chosen plan already selected.', 'subscription' ); ?>
						</p>
					</div>
					<button type="button" class="wpsubs-modal__close" data-wpsubs-modal-close aria-label="<?php esc_attr_e( 'Close', 'subscription' ); ?>" style="flex:0 0 auto;">&times;</button>
				</div>
				<?php // overflow:visible lets the adv-select dropdowns escape the body (short modal, no scroll needed). ?>
				<div class="wpsubs-modal__body" style="display:flex;flex-direction:column;gap:18px;overflow:visible;">
						<div style="<?php echo esc_attr( $subscrpt_field ); ?>">
							<span style="<?php echo esc_attr( $subscrpt_field_l ); ?>"><?php esc_html_e( 'Link type', 'subscription' ); ?></span>
							<?php
							wpsubs_render_adv_select(
								array(
									'name'    => 'subscrpt_checkout_type',
									'value'   => 'checkout',
									'options' => array(
										array(
											'value' => 'cart',
											'label' => __( 'Add to cart', 'subscription' ),
										),
										array(
											'value' => 'checkout',
											'label' => __( 'Checkout', 'subscription' ),
										),
									),
									'attrs'   => array(
										'data-subscrpt-checkout-type' => '1',
										'style' => 'width:100%;',
									),
								)
							);
							?>
						</div>

					<div style="display:grid;grid-template-columns:<?php echo $is_variable ? '1fr 1fr' : '1fr'; ?>;gap:14px;align-items:start;">
						<?php if ( $is_variable ) : ?>
							<div style="<?php echo esc_attr( $subscrpt_field ); ?>">
								<span style="<?php echo esc_attr( $subscrpt_field_l ); ?>"><?php esc_html_e( 'Variation', 'subscription' ); ?></span>
								<?php
								$subscrpt_var_opts = array();
								foreach ( $contexts as $subscrpt_ctx ) {
									$subscrpt_var_opts[] = array(
										'value' => (string) $subscrpt_ctx['vid'],
										'label' => $subscrpt_ctx['label'],
									);
								}
								wpsubs_render_adv_select(
									array(
										'name'    => 'subscrpt_checkout_variation',
										'value'   => (string) $subscrpt_default_vid,
										'options' => $subscrpt_var_opts,
										'attrs'   => array(
											'data-subscrpt-checkout-variation' => '1',
											'style' => 'width:100%;',
										),
									)
								);
								?>
							</div>
						<?php endif; ?>

					<div style="<?php echo esc_attr( $subscrpt_field ); ?>">
						<span style="<?php echo esc_attr( $subscrpt_field_l ); ?>"><?php esc_html_e( 'Plan', 'subscription' ); ?></span>
						<?php
						foreach ( $contexts as $subscrpt_ctx ) :
							// Separate one-time from recurring with a divider so the two
							// read as distinct sets (one-time is always listed first).
							$subscrpt_onetime   = array();
							$subscrpt_recurring = array();
							foreach ( $subscrpt_ctx['plans'] as $subscrpt_p ) {
								if ( 'onetime' === $subscrpt_p['value'] ) {
									$subscrpt_onetime[] = $subscrpt_p;
								} else {
									$subscrpt_recurring[] = $subscrpt_p;
								}
							}
							$subscrpt_plan_opts = $subscrpt_onetime;
							foreach ( $subscrpt_recurring as $subscrpt_i => $subscrpt_p ) {
								// Divider before the first recurring plan when a one-time exists.
								if ( 0 === $subscrpt_i && ! empty( $subscrpt_onetime ) ) {
									$subscrpt_p['divider'] = true;
								}
								$subscrpt_plan_opts[] = $subscrpt_p;
							}
							$subscrpt_plan_default = isset( $subscrpt_ctx['plans'][0]['value'] ) ? $subscrpt_ctx['plans'][0]['value'] : '';
							?>
							<div data-subscrpt-checkout-plan-wrap data-vid="<?php echo esc_attr( $subscrpt_ctx['vid'] ); ?>" <?php echo (int) $subscrpt_ctx['vid'] === $subscrpt_default_vid ? '' : 'hidden'; ?>>
								<?php
								wpsubs_render_adv_select(
									array(
										'name'    => 'subscrpt_checkout_plan_' . $subscrpt_ctx['vid'],
										'value'   => $subscrpt_plan_default,
										'options' => $subscrpt_plan_opts,
										'attrs'   => array(
											'data-subscrpt-checkout-plan' => '1',
											'style' => 'width:100%;',
										),
									)
								);
								?>
							</div>
						<?php endforeach; ?>
					</div>
					</div>

					<div style="<?php echo esc_attr( $subscrpt_field ); ?>">
						<span style="<?php echo esc_attr( $subscrpt_field_l ); ?>"><?php esc_html_e( 'Link preview', 'subscription' ); ?></span>
						<div style="display:flex;align-items:stretch;gap:0;border:1px solid var(--wpsubs-border,#e5e7eb);border-radius:var(--wpsubs-radius,8px);overflow:hidden;background:var(--wpsubs-surface-muted,#f6f7f7);">
							<input type="text" data-subscrpt-checkout-preview readonly style="flex:1 1 auto;min-width:0;border:0;background:transparent;padding:9px 11px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px;color:var(--wpsubs-text);outline:none;" />
							<button type="button" data-subscrpt-checkout-copy title="<?php esc_attr_e( 'Copy link', 'subscription' ); ?>" style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;border:0;border-left:1px solid var(--wpsubs-border,#e5e7eb);background:transparent;color:var(--wpsubs-text-muted);cursor:pointer;padding:0 12px;align-self:stretch;">
								<span class="dashicons dashicons-admin-page" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
							</button>
						</div>
						<?php // URL structure reference for the selected link type (JS toggles). ?>
						<span data-subscrpt-checkout-ref="cart" style="font-size:10.5px;line-height:1.5;color:var(--wpsubs-text-subtle,#8a8f98);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all;"><strong style="font-family:inherit;"><?php esc_html_e( 'Ref.', 'subscription' ); ?></strong> /?add-to-cart=PRODUCT_ID&amp;subscrpt_plan_id=PLAN_ID</span>
						<span data-subscrpt-checkout-ref="checkout" hidden style="font-size:10.5px;line-height:1.5;color:var(--wpsubs-text-subtle,#8a8f98);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all;"><strong style="font-family:inherit;"><?php esc_html_e( 'Ref.', 'subscription' ); ?></strong> /checkout-link/?products=PRODUCT_ID:QTY&amp;subscrpt_plan_id=PLAN_ID</span>
					</div>

					<div data-subscrpt-checkout-warning style="display:none;gap:8px;align-items:flex-start;padding:10px 12px;border-radius:var(--wpsubs-radius,8px);background:#fcf3d9;border:1px solid #f0d98a;font-size:12px;line-height:1.55;color:#8a6d1a;">
						<span class="dashicons dashicons-warning" style="flex:0 0 auto;font-size:16px;width:16px;height:16px;color:#b9902a;"></span>
						<span><?php esc_html_e( 'This variation uses an “Any” attribute, so the checkout link can’t pre-select it. Use the “Add to cart” link type instead.', 'subscription' ); ?></span>
					</div>
				</div>
				<div class="wpsubs-modal__footer">
					<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-close><?php esc_html_e( 'Close', 'subscription' ); ?></button>
					<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-checkout-copy>
						<span class="dashicons dashicons-admin-page" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
						<?php esc_html_e( 'Copy link', 'subscription' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Whether the editor should open in classic (simple) mode by default.
	 *
	 * A product carrying legacy classic subscription settings but not tied to any
	 * plan is an "old" product — open it in the classic view it was configured in.
	 * Fresh products (and plan-connected ones) default to the plan view.
	 *
	 * @param \WC_Product|null $product Product being edited.
	 *
	 * @return bool
	 */
	public static function should_default_classic( $product = null ) {
		if ( ! $product ) {
			return false;
		}

		// A product is "classic/legacy" only when it is enabled as a classic
		// subscription (`_subscrpt_enabled`) but tied to no plan. `_subscrpt_timing_option`
		// is written with a default on every product save, so it can't distinguish a
		// real classic subscription from a plain product — never key on it here.
		if ( $product->is_type( 'variable' ) ) {
			$has_classic = false;
			foreach ( $product->get_children() as $variation_id ) {
				if ( function_exists( 'subscrpt_product_has_plan' ) && subscrpt_product_has_plan( $product->get_id(), $variation_id ) ) {
					return false;
				}
				if ( ! $has_classic ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation && (bool) $variation->get_meta( '_subscrpt_enabled' ) ) {
						$has_classic = true;
					}
				}
			}
			return $has_classic;
		}

		if ( function_exists( 'subscrpt_product_has_plan' ) && subscrpt_product_has_plan( $product->get_id() ) ) {
			return false;
		}
		return (bool) $product->get_meta( '_subscrpt_enabled' );
	}

	/**
	 * Render the view toggle bar (plan view ⇄ classic settings).
	 *
	 * @param \WC_Product|null $product Product being edited (null when no product context).
	 *
	 * @return void
	 */
	public static function render_toolbar( $product = null ) {
		// A connected plan enables the subscription by default (until saved off).
		$subscrpt_enabled = $product ? subscrpt_is_subscription_enabled( $product->get_id() ) : false;
		// Show the checkout-link generator only when the product is tied to a plan.
		$subscrpt_has_plans = $product && ! empty( PlanRepository::get_product_connections( $product->get_id() ) );
		?>
		<div data-subscrpt-plan-toolbar style="display:flex;align-items:center;gap:12px;margin:0 0 14px;flex-wrap:wrap;">
			<strong style="margin-left:10px;font-size:13px;color:var(--wpsubs-text);"><?php esc_html_e( 'WPSubscription', 'subscription' ); ?></strong>
			<?php // Variable products enable per variation (toggle lives on each variation card); simple products enable at the product level here. ?>
			<?php if ( ! ( $product && $product->is_type( 'variable' ) ) ) : ?>
				<label class="wpsubs-settings-toggle-label" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--wpsubs-text-muted);" title="<?php esc_attr_e( 'Sell this product as a subscription', 'subscription' ); ?>">
					<input type="checkbox" class="wpsubs-toggle" id="subscrpt_enable" name="subscrpt_enable" value="yes" <?php checked( $subscrpt_enabled ); ?> />
					<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Enable subscription', 'subscription' ); ?></span>
				</label>
			<?php endif; ?>
			<span style="flex:1 1 auto;"></span>
			<?php if ( $subscrpt_has_plans ) : ?>
				<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-wpsubs-modal-open="subscrpt-checkout-link">
					<span class="dashicons dashicons-admin-links" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
					<?php esc_html_e( 'Generate Checkout Link', 'subscription' ); ?>
				</button>
			<?php endif; ?>
			<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-show-classic>
				<?php esc_html_e( 'Switch to simple mode', 'subscription' ); ?>
			</button>
			<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-show-plan style="display:none;">
				<span class="dashicons dashicons-arrow-left-alt2" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
				<?php esc_html_e( 'Back to plan mode', 'subscription' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render the plan view: connected plan(s) + connect control.
	 *
	 * @param \WC_Product $product Simple product being edited.
	 *
	 * @return void
	 */
	public static function render_plan_view( $product ) {
		$connections = PlanRepository::get_product_connections( $product->get_id() );
		$connected   = self::group_connections( $connections );
		$available   = self::available_groups( array_keys( $connected ) );
		// Pro adds "create plan group / plan" shortcuts here; free is attach-only.
		$pro_active = function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated();

		// One-time purchase (simple products): the product's native WooCommerce
		// price + an enabled flag, rendered as a row after the plan rows (matching
		// the variable-product layout), not a separate card. Null hides the row.
		$subscrpt_simple_ot = ( ! $product->is_type( 'variable' ) )
			? array(
				'enabled' => 'yes' === $product->get_meta( '_subscrpt_one_time_enabled' ),
				'regular' => (string) $product->get_regular_price(),
				'offer'   => (string) $product->get_sale_price(),
			)
			: null;
		?>
		<style>
			/* Neutralise WooCommerce's floated-label layout inside the toolbar +
				plan view (not the classic fields, which keep the native layout). */
			#sdevs_subscription_options [data-subscrpt-plan-toolbar] label,
			#sdevs_subscription_options [data-subscrpt-plan-view] label {
				float: none;
				width: auto;
				margin: 0;
			}
			/* Compact adv-select triggers on this tab only (not the modals, which
				are relocated to <body>). */
			#sdevs_subscription_options [data-subscrpt-plan-view] .wpsubs-adv-select__trigger {
				height: 30px;
				min-height: 30px;
				padding-top: 0;
				padding-bottom: 0;
			}
			/* Keep the group picker a fixed width and ellipsis a long plan name. */
			#sdevs_subscription_options [data-subscrpt-connect-group] {
				width: 240px;
				max-width: 100%;
			}
			#sdevs_subscription_options [data-subscrpt-connect-group] .wpsubs-adv-select__trigger {
				min-width: 0;
			}
			#sdevs_subscription_options [data-subscrpt-connect-group] .wpsubs-adv-select__label {
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}
		</style>

		<?php if ( ! empty( $connected ) ) : ?>
			<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:18px;">
				<?php
				foreach ( $connected as $subscrpt_gid => $group ) :
					$all_terms = PlanRepository::get_plans( $subscrpt_gid );
					?>
					<div class="wpsubs-table-card" data-subscrpt-plan-card data-group-id="<?php echo esc_attr( $subscrpt_gid ); ?>" style="padding:12px 14px;">
						<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
							<span style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:var(--wpsubs-radius,8px);background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand,#ff4d00);">
								<span class="dashicons dashicons-update"></span>
							</span>
							<div style="flex:1 1 auto;min-width:0;display:flex;align-items:center;gap:8px;">
								<strong style="font-size:13.5px;color:var(--wpsubs-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $group['title'] ); ?></strong>
								<span class="wpsubs-badge wpsubs-badge--active" style="font-weight:500;"><?php esc_html_e( 'Connected', 'subscription' ); ?></span>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription-plans&view=detail&plan=' . (int) $subscrpt_gid ) ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Manage this plan', 'subscription' ); ?>" aria-label="<?php esc_attr_e( 'Manage this plan', 'subscription' ); ?>" style="flex:0 0 auto;display:inline-flex;align-items:center;color:var(--wpsubs-text-subtle);text-decoration:none;">
									<span class="dashicons dashicons-external" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
								</a>
							</div>
							<div style="flex:0 0 auto;display:flex;align-items:center;gap:8px;">
								<?php if ( ! $product->is_type( 'variable' ) ) : ?>
									<?php // Simple: one Edit/Save for the whole card. Variable edits per variation (buttons live on each variation sub-card). ?>
									<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-edit-prices>
										<span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;line-height:1;"></span>
										<?php esc_html_e( 'Edit prices', 'subscription' ); ?>
									</button>
									<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-cancel-prices style="display:none;"><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
									<button type="button" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" data-subscrpt-save-prices style="display:none;"><?php esc_html_e( 'Save', 'subscription' ); ?></button>
								<?php endif; ?>
								<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm wpsubs-btn--danger" data-subscrpt-detach data-relation-ids="<?php echo esc_attr( implode( ',', $group['relation_ids'] ) ); ?>">
									<?php esc_html_e( 'Detach', 'subscription' ); ?>
								</button>
							</div>
						</div>

						<?php if ( $product->is_type( 'variable' ) ) : ?>
							<div style="display:flex;flex-direction:column;gap:10px;margin-top:12px;">
								<?php self::render_variation_price_cards( $product, $all_terms, $group['term_map_by_vid'], false ); ?>
							</div>
						<?php else : ?>
							<!-- Read view: offered plans + one-time as chips. -->
							<div class="subscrpt-pe-view" style="display:flex;flex-wrap:wrap;gap:6px;margin:12px -14px 0;padding:12px 14px 0;border-top:1px solid var(--wpsubs-border,#e5e7eb);">
								<?php self::render_variation_summary( $all_terms, $group['term_map'], $subscrpt_simple_ot, 0 ); ?>
							</div>

							<!-- Edit view: plan rows + the one-time row (mirrors the Plans page). -->
							<div class="subscrpt-pe-edit" style="display:none;margin-top:12px;">
								<?php self::render_price_table( $all_terms, $group['term_map'], false, 0, $subscrpt_simple_ot ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div style="display:flex;align-items:flex-start;gap:10px;padding:14px;margin-bottom:18px;border-radius:8px;background:var(--wpsubs-surface-muted,#f9fafb);border:1px solid var(--wpsubs-border,#e5e7eb);">
				<span class="dashicons dashicons-info-outline" style="flex:0 0 auto;color:var(--wpsubs-text-subtle);"></span>
				<span style="font-size:13px;color:var(--wpsubs-text-muted);line-height:1.5;">
					<?php esc_html_e( 'Not connected to any plan yet. Connect this product to a plan to sell it as a subscription.', 'subscription' ); ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( empty( $available ) && empty( $connected ) ) : ?>
			<?php if ( $pro_active ) : ?>
				<div class="wpsubs-table-card" style="padding:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
					<span style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:var(--wpsubs-radius,8px);background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand,#ff4d00);">
						<span class="dashicons dashicons-admin-links"></span>
					</span>
					<div style="flex:1 1 auto;min-width:140px;">
						<div style="font-size:13.5px;font-weight:600;color:var(--wpsubs-text);line-height:1.3;"><?php esc_html_e( 'No plans yet', 'subscription' ); ?></div>
						<div style="font-size:12px;color:var(--wpsubs-text-muted);line-height:1.4;"><?php esc_html_e( 'Create a plan and its first duration, then connect this product to it.', 'subscription' ); ?></div>
					</div>
					<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-wpsubs-modal-open="subscrpt-create-plan">
						<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:1;"></span>
						<?php esc_html_e( 'New plan', 'subscription' ); ?>
					</button>
				</div>
			<?php else : ?>
				<p style="margin:0;font-size:13px;color:var(--wpsubs-text-muted);">
					<?php esc_html_e( 'No plans available.', 'subscription' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription-plans' ) ); ?>"><?php esc_html_e( 'Create a plan', 'subscription' ); ?></a>
				</p>
			<?php endif; ?>
		<?php elseif ( ! empty( $available ) && empty( $connected ) ) : ?>
			<div class="wpsubs-table-card" data-subscrpt-connect-card style="padding:14px;">
				<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
					<span style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:var(--wpsubs-radius,8px);background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand,#ff4d00);">
						<span class="dashicons dashicons-admin-links"></span>
					</span>
					<div style="flex:1 1 auto;min-width:140px;">
						<div style="font-size:13.5px;font-weight:600;color:var(--wpsubs-text);line-height:1.3;"><?php esc_html_e( 'Connect to a plan', 'subscription' ); ?></div>
						<div style="font-size:12px;color:var(--wpsubs-text-muted);line-height:1.4;"><?php esc_html_e( 'Pick a plan, then set the prices for each of its durations.', 'subscription' ); ?></div>
					</div>
					<div style="flex:0 0 auto;display:flex;align-items:center;gap:8px;">
						<?php
						$options = array();
						foreach ( $available as $group ) {
							$options[] = array(
								'value' => (string) $group['id'],
								'label' => $group['title'],
							);
						}
						wpsubs_render_adv_select(
							array(
								'name'        => 'subscrpt_connect_group',
								'placeholder' => __( 'Select a plan…', 'subscription' ),
								'options'     => $options,
								'attrs'       => array( 'data-subscrpt-connect-group' => '1' ),
								'align'       => 'right',
							)
						);
						?>
						<?php if ( $pro_active ) : ?>
							<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-wpsubs-modal-open="subscrpt-create-plan" title="<?php esc_attr_e( 'Create a new plan', 'subscription' ); ?>">
								<span class="dashicons dashicons-plus-alt2" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
								<?php esc_html_e( 'New', 'subscription' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>

				<div data-subscrpt-connect-divider style="display:none;border-top:1px dashed var(--wpsubs-border-strong,#d1d5db);margin-top:14px;"></div>

				<!-- One price table per available group; shown when its group is picked. -->
				<?php foreach ( $available as $group ) : ?>
					<?php $subscrpt_group_terms = PlanRepository::get_plans( (int) $group['id'] ); ?>
					<div data-subscrpt-plan-card data-connect-block data-group-id="<?php echo esc_attr( $group['id'] ); ?>" style="display:none;margin-top:14px;">
						<?php if ( empty( $subscrpt_group_terms ) ) : ?>
							<div style="display:flex;flex-direction:column;align-items:center;gap:12px;padding:20px 16px;text-align:center;">
								<p style="margin:0;color:var(--wpsubs-text-subtle);font-size:13px;">
									<?php esc_html_e( 'This plan has no durations yet. Add a duration before connecting this product.', 'subscription' ); ?>
								</p>
								<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:center;">
									<?php if ( $pro_active ) : ?>
										<button type="button" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" data-subscrpt-create-plan-for="<?php echo esc_attr( $group['id'] ); ?>">
											<span class="dashicons dashicons-plus-alt2" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
											<?php esc_html_e( 'Create plan', 'subscription' ); ?>
										</button>
									<?php endif; ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription-plans&view=detail&plan=' . (int) $group['id'] ) ); ?>" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" target="_blank" rel="noopener">
										<span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;line-height:1;"></span>
										<?php esc_html_e( 'Manage plans', 'subscription' ); ?>
									</a>
								</div>
							</div>
						<?php else : ?>
							<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
								<span style="flex:1 1 auto;"></span>
								<button type="button" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" data-subscrpt-save-prices>
									<span class="dashicons dashicons-yes" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
									<?php esc_html_e( 'Save', 'subscription' ); ?>
								</button>
							</div>
							<?php if ( $product->is_type( 'variable' ) ) : ?>
								<div style="display:flex;flex-direction:column;gap:10px;">
									<?php self::render_variation_price_cards( $product, $subscrpt_group_terms, array(), true ); ?>
								</div>
							<?php else : ?>
								<div>
									<?php self::render_price_table( $subscrpt_group_terms, array(), true, 0, $subscrpt_simple_ot ); ?>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		/**
		 * Fires at the end of the product-editor plan view. Pro hooks this to add
		 * more plan-related inputs to the Subscription tab.
		 *
		 * @param \WC_Product $product The product being edited.
		 */
		do_action( 'subscrpt_product_plan_view', $product );
		?>
		<?php
	}

	/**
	 * Render the per-term price table (Regular / Offer + an enable toggle)
	 * shared by the edit and connect views. One-time purchase is product-level
	 * (its own section), not part of this per-plan table.
	 *
	 * @param array      $all_terms All terms of the group (from PlanRepository::get_plans()).
	 * @param array      $term_map  Map plan_id => relation values (empty for the connect view).
	 * @param bool       $connect   Connect view: no relation ids, every term enabled by default.
	 * @param int        $vid       Variation id these rows price (0 = the product itself).
	 * @param array|null $one_time  One-time purchase values for this variation
	 *                              (enabled, regular, offer) — appended as a row;
	 *                              null omits it (simple products use a separate card).
	 *
	 * @return void
	 */
	protected static function render_price_table( $all_terms, $term_map, $connect, $vid = 0, $one_time = null ) {
		?>
		<table class="wpsubs-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Duration', 'subscription' ); ?></th>
					<th>
							<?php esc_html_e( 'Regular Price', 'subscription' ); ?>
							<?php echo wp_kses_post( wpsubs_render_hint( __( 'The recurring price charged each billing cycle.', 'subscription' ) ) ); ?>
						</th>
					<th>
							<?php esc_html_e( 'Offer Price', 'subscription' ); ?>
							<?php echo wp_kses_post( wpsubs_render_hint( __( 'A discounted recurring price shown in place of the regular price. Leave empty for no discount.', 'subscription' ) ) ); ?>
						</th>
					<th><?php esc_html_e( 'Actions', 'subscription' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $all_terms as $subscrpt_term ) :
					$subscrpt_tid     = (int) $subscrpt_term['id'];
					$subscrpt_map     = $connect ? null : ( isset( $term_map[ $subscrpt_tid ] ) ? $term_map[ $subscrpt_tid ] : null );
					$subscrpt_relid   = $subscrpt_map ? $subscrpt_map['relation_id'] : '';
					$subscrpt_enabled = $connect ? true : ( $subscrpt_map && empty( $subscrpt_map['excluded'] ) );
					$subscrpt_vals    = $subscrpt_map ? $subscrpt_map : array(
						'regular' => '',
						'offer'   => '',
					);
					?>
					<tr data-subscrpt-term-row data-plan-id="<?php echo esc_attr( $subscrpt_tid ); ?>" data-vid="<?php echo esc_attr( $vid ); ?>" data-relation-id="<?php echo esc_attr( $subscrpt_relid ); ?>">
						<td>
							<div style="font-size:12.5px;color:var(--wpsubs-text);"><?php echo esc_html( $subscrpt_term['title'] ); ?></div>
							<?php $subscrpt_meta = \SpringDevs\Subscription\Admin\PlanPresenter::term_meta( $subscrpt_term ); ?>
							<?php if ( ! empty( $subscrpt_meta ) ) : ?>
								<div style="margin-top:3px;font-size:11px;color:var(--wpsubs-text-muted);line-height:1.5;">
									<?php echo esc_html( implode( ' · ', $subscrpt_meta ) ); ?>
								</div>
							<?php endif; ?>
						</td>
						<td><input type="number" min="0" step="0.01" class="wpsubs-input" data-field="regular_price" value="<?php echo esc_attr( $subscrpt_vals['regular'] ); ?>" placeholder="0.00" style="width:120px;max-width:100%;" /></td>
						<td><input type="number" min="0" step="0.01" class="wpsubs-input" data-field="sale_price" value="<?php echo esc_attr( $subscrpt_vals['offer'] ); ?>" placeholder="0.00" style="width:120px;max-width:100%;" /></td>
						<td>
							<div style="display:inline-flex;align-items:center;gap:8px;">
								<label class="wpsubs-settings-toggle-label" style="display:inline-flex;align-items:center;" title="<?php esc_attr_e( 'Enable this plan for the product', 'subscription' ); ?>">
									<input type="checkbox" class="wpsubs-toggle" data-subscrpt-term-toggle <?php checked( $subscrpt_enabled ); ?> />
									<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
								</label>
								<?php if ( ! $connect && '' !== trim( (string) $subscrpt_vals['regular'] ) ) : ?>
									<button type="button" class="wpsubs-icon-action" data-subscrpt-copy-checkout data-plan-id="<?php echo esc_attr( $subscrpt_tid ); ?>" data-vid="<?php echo esc_attr( $vid ); ?>" title="<?php esc_attr_e( 'Copy checkout link', 'subscription' ); ?>" aria-label="<?php esc_attr_e( 'Copy checkout link', 'subscription' ); ?>">
										<span class="dashicons dashicons-admin-links"></span>
									</button>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
					</tbody>
					</table>

					<?php
					/*
					 * One-time purchase sits below the table, not in it. It is not a duration:
					 * it has no billing cycle, and as a row it read as one more line of the
					 * plan's own pricing. Its own block says it is a separate way to buy.
					 */
					if ( is_array( $one_time ) ) :
						$subscrpt_ot_on = ! empty( $one_time['enabled'] );
						?>
					<div data-subscrpt-onetime data-vid="<?php echo esc_attr( $vid ); ?>" style="margin-top:12px;border:1px solid var(--wpsubs-border,#e5e7eb);border-radius:var(--wpsubs-radius,8px);background:var(--wpsubs-surface,#fff);">
						<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;">
							<span class="dashicons dashicons-cart" style="flex:0 0 auto;font-size:15px;width:15px;height:15px;color:var(--wpsubs-text-subtle);"></span>
							<strong style="font-size:12.5px;color:var(--wpsubs-text);"><?php esc_html_e( 'One-time purchase', 'subscription' ); ?></strong>
							<?php echo wp_kses_post( wpsubs_render_hint( __( 'A single, non-recurring purchase at the product’s regular WooCommerce price.', 'subscription' ) ) ); ?>
							<span style="flex:1 1 auto;"></span>
							<label class="wpsubs-settings-toggle-label" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--wpsubs-text-muted);" title="<?php esc_attr_e( 'Offer this product for one-time purchase', 'subscription' ); ?>">
								<input type="checkbox" class="wpsubs-toggle" data-subscrpt-onetime-enable <?php checked( $subscrpt_ot_on ); ?> />
								<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Allow one-time purchase', 'subscription' ); ?></span>
							</label>
							<?php if ( ! $connect && '' !== trim( (string) $one_time['regular'] ) ) : ?>
								<button type="button" class="wpsubs-icon-action" data-subscrpt-copy-checkout data-plan-id="onetime" data-vid="<?php echo esc_attr( $vid ); ?>" title="<?php esc_attr_e( 'Copy checkout link', 'subscription' ); ?>" aria-label="<?php esc_attr_e( 'Copy checkout link', 'subscription' ); ?>">
									<span class="dashicons dashicons-admin-links"></span>
								</button>
							<?php endif; ?>
						</div>
						<?php
						// The gate clears the inline `display` to reveal, which falls back
						// to a div's `block` — so the flex row is a child, not this element.
						?>
						<div data-subscrpt-onetime-price style="padding:12px;border-top:1px solid var(--wpsubs-border,#e5e7eb);<?php echo $subscrpt_ot_on ? '' : 'display:none;'; ?>">
							<div style="display:flex;gap:16px;">
							<label style="display:block;font-size:12px;color:var(--wpsubs-text-muted);">
								<span style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Regular Price', 'subscription' ); ?></span>
								<input type="number" min="0" step="0.01" class="wpsubs-input" data-ot-field="price" value="<?php echo esc_attr( $one_time['regular'] ); ?>" placeholder="0.00" style="width:120px;max-width:100%;" />
							</label>
							<label style="display:block;font-size:12px;color:var(--wpsubs-text-muted);">
								<span style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Offer Price', 'subscription' ); ?></span>
								<input type="number" min="0" step="0.01" class="wpsubs-input" data-ot-field="offer" value="<?php echo esc_attr( $one_time['offer'] ); ?>" placeholder="<?php esc_attr_e( 'No offer', 'subscription' ); ?>" style="width:120px;max-width:100%;" />
								</label>
							</div>
						</div>
					</div>
					<?php endif; ?>
		<?php
	}

	/**
	 * Render one price sub-card per variation for a variable product, so each
	 * variation can carry its own plan prices under a single connected group
	 * ("one group, price per variation"). A variation with no relation of its
	 * own inherits the product-level (vid 0) seed values; Save then creates that
	 * variation's own relation instead of editing the shared seed.
	 *
	 * @param \WC_Product $product    Variable product being edited.
	 * @param array       $all_terms  All terms of the group.
	 * @param array       $map_by_vid Relation values keyed [vid][plan_id].
	 * @param bool        $connect    Connect flow: tables shown immediately, no
	 *                                relation ids yet.
	 *
	 * @return void
	 */
	protected static function render_variation_price_cards( $product, $all_terms, $map_by_vid, $connect ) {
		$seed = isset( $map_by_vid[0] ) ? $map_by_vid[0] : array();

		foreach ( $product->get_children() as $subscrpt_child_id ) {
			$subscrpt_vid       = (int) $subscrpt_child_id;
			$subscrpt_variation = function_exists( 'wc_get_product' ) ? wc_get_product( $subscrpt_vid ) : null;
			if ( ! $subscrpt_variation ) {
				continue;
			}

			// Per-variation term map: the variation's own relations, falling back to
			// the product-level seed (with a blank relation id so Save creates this
			// variation's relation instead of editing the shared seed).
			$subscrpt_vmap = array();
			if ( ! $connect ) {
				$subscrpt_own = isset( $map_by_vid[ $subscrpt_vid ] ) ? $map_by_vid[ $subscrpt_vid ] : array();
				foreach ( $all_terms as $subscrpt_term ) {
					$subscrpt_pid = (int) $subscrpt_term['id'];
					if ( isset( $subscrpt_own[ $subscrpt_pid ] ) ) {
						$subscrpt_vmap[ $subscrpt_pid ] = $subscrpt_own[ $subscrpt_pid ];
					} elseif ( isset( $seed[ $subscrpt_pid ] ) ) {
						$subscrpt_seed_row                = $seed[ $subscrpt_pid ];
						$subscrpt_seed_row['relation_id'] = '';
						$subscrpt_vmap[ $subscrpt_pid ]   = $subscrpt_seed_row;
					}
				}
			}

			// One-time purchase is the variation's own native WooCommerce price
			// (regular = one-time, sale = offer) + an enabled flag, saved with this
			// variation's plan prices on Save.
			$subscrpt_one_time = array(
				'enabled' => 'yes' === $subscrpt_variation->get_meta( '_subscrpt_one_time_enabled' ),
				'regular' => (string) $subscrpt_variation->get_regular_price(),
				'offer'   => (string) $subscrpt_variation->get_sale_price(),
			);

			// "Enable subscription" is per variation. Connecting a plan group turns
			// it on by default (until saved off); the connect toggle starts checked
			// and Save persists the variation's _subscrpt_enabled meta.
			$subscrpt_var_on = $connect ? true : subscrpt_is_subscription_enabled( $product->get_id(), $subscrpt_vid );
			?>
			<div data-subscrpt-variation-card data-variation-id="<?php echo esc_attr( $subscrpt_vid ); ?>" <?php echo $connect ? '' : 'data-subscrpt-plan-card'; ?> style="border:1px solid var(--wpsubs-border,#e5e7eb);border-radius:8px;background:var(--wpsubs-surface,#fff);">
				<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;">
					<span class="dashicons dashicons-image-filter" style="flex:0 0 auto;font-size:15px;width:15px;height:15px;color:var(--wpsubs-text-subtle);"></span>
					<strong style="font-size:12.5px;color:var(--wpsubs-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( self::variation_display_name( $subscrpt_variation, $product->get_name() ) ); ?></strong>
					<label class="wpsubs-settings-toggle-label" style="flex:0 0 auto;display:inline-flex;align-items:center;gap:6px;font-size:11.5px;color:var(--wpsubs-text-muted);" title="<?php esc_attr_e( 'Sell this variation as a subscription', 'subscription' ); ?>">
						<input type="checkbox" class="wpsubs-toggle" data-subscrpt-var-enable <?php checked( $subscrpt_var_on ); ?> <?php disabled( ! $connect ); ?> />
						<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
						<span><?php esc_html_e( 'Enable subscription', 'subscription' ); ?></span>
					</label>
					<span style="flex:1 1 auto;"></span>
					<?php if ( ! $connect ) : ?>
						<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-edit-prices>
							<span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;line-height:1;"></span>
							<?php esc_html_e( 'Edit prices', 'subscription' ); ?>
						</button>
						<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-cancel-prices style="display:none;"><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
						<button type="button" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" data-subscrpt-save-prices style="display:none;"><?php esc_html_e( 'Save', 'subscription' ); ?></button>
					<?php endif; ?>
				</div>
				<?php if ( $connect ) : ?>
					<div style="padding:2px 0;border-top:1px solid var(--wpsubs-border,#e5e7eb);">
						<?php self::render_price_table( $all_terms, array(), true, $subscrpt_vid, $subscrpt_one_time ); ?>
					</div>
				<?php else : ?>
					<div class="subscrpt-pe-view" style="display:flex;flex-wrap:wrap;gap:6px;padding:10px 12px 12px;border-top:1px solid var(--wpsubs-border,#e5e7eb);">
						<?php self::render_variation_summary( $all_terms, $subscrpt_vmap, $subscrpt_one_time, $subscrpt_vid ); ?>
					</div>
					<div class="subscrpt-pe-edit" style="display:none;padding:2px 0;border-top:1px solid var(--wpsubs-border,#e5e7eb);">
						<?php self::render_price_table( $all_terms, $subscrpt_vmap, false, $subscrpt_vid, $subscrpt_one_time ); ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Render the read-mode price summary chips for one variation (each plan term
	 * with its price, plus a one-time chip when enabled), mirroring the simple
	 * product's connected-card chips.
	 *
	 * @param array      $all_terms All terms of the group.
	 * @param array      $vmap      This variation's plan_id => relation values.
	 * @param array|null $one_time  One-time values (enabled, regular, offer).
	 * @param int        $vid       Variation id (0 for simple), for the checkout link.
	 *
	 * @return void
	 */
	protected static function render_variation_summary( $all_terms, $vmap, $one_time, $vid = 0 ) {
		foreach ( $all_terms as $subscrpt_term ) :
			$subscrpt_tid   = (int) $subscrpt_term['id'];
			$subscrpt_row   = isset( $vmap[ $subscrpt_tid ] ) ? $vmap[ $subscrpt_tid ] : null;
			$subscrpt_off   = ! $subscrpt_row || ! empty( $subscrpt_row['excluded'] );
			$subscrpt_reg   = $subscrpt_row ? (string) $subscrpt_row['regular'] : '';
			$subscrpt_offer = $subscrpt_row ? (string) $subscrpt_row['offer'] : '';
			?>
			<span style="display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:20px;background:var(--wpsubs-surface-muted,#f9fafb);border:1px solid var(--wpsubs-border,#e5e7eb);font-size:11.5px;color:var(--wpsubs-text-muted);<?php echo $subscrpt_off ? 'opacity:0.55;' : ''; ?>">
				<?php echo esc_html( $subscrpt_term['title'] ); ?>
				<?php self::price_pair( $subscrpt_reg, $subscrpt_offer ); ?>
				<?php if ( $subscrpt_off ) : ?>
					<span style="font-style:italic;">(<?php esc_html_e( 'off', 'subscription' ); ?>)</span>
				<?php elseif ( '' !== trim( $subscrpt_reg ) ) : ?>
					<?php self::render_chip_copy_link( (string) $subscrpt_tid, $vid ); ?>
				<?php endif; ?>
			</span>
			<?php
		endforeach;

		if ( is_array( $one_time ) && ! empty( $one_time['enabled'] ) ) :
			?>
			<span style="display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:20px;background:var(--wpsubs-surface-muted,#f9fafb);border:1px solid var(--wpsubs-border,#e5e7eb);font-size:11.5px;color:var(--wpsubs-text-muted);">
				<span class="dashicons dashicons-cart" style="font-size:13px;width:13px;height:13px;line-height:1;color:var(--wpsubs-text-subtle);"></span>
				<?php esc_html_e( 'One-time', 'subscription' ); ?>
				<?php self::price_pair( (string) $one_time['regular'], (string) $one_time['offer'] ); ?>
				<?php if ( '' !== trim( (string) $one_time['regular'] ) ) : ?>
					<?php self::render_chip_copy_link( 'onetime', $vid ); ?>
				<?php endif; ?>
			</span>
			<?php
		endif;
	}

	/**
	 * Compact "Copy checkout link" icon button for a summary chip.
	 *
	 * @param string $plan_id Plan (term) id, or the 'onetime' sentinel.
	 * @param int    $vid     Variation id (0 for simple).
	 *
	 * @return void
	 */
	protected static function render_chip_copy_link( $plan_id, $vid ) {
		?>
		<button type="button" data-subscrpt-copy-checkout data-plan-id="<?php echo esc_attr( $plan_id ); ?>" data-vid="<?php echo esc_attr( $vid ); ?>" title="<?php esc_attr_e( 'Copy checkout link', 'subscription' ); ?>" aria-label="<?php esc_attr_e( 'Copy checkout link', 'subscription' ); ?>" style="display:inline-flex;align-items:center;justify-content:center;padding:0;border:none;background:transparent;color:var(--wpsubs-text-subtle);cursor:pointer;line-height:1;">
			<span class="dashicons dashicons-admin-links" style="font-size:13px;width:13px;height:13px;line-height:1;"></span>
		</button>
		<?php
	}

	/**
	 * Echo a price for a summary chip: the offer price when one is set (with the
	 * regular price shown struck-through beside it), otherwise the regular price.
	 *
	 * @param string $regular Regular price (raw).
	 * @param string $offer   Offer / sale price (raw, '' when none).
	 *
	 * @return void
	 */
	protected static function price_pair( $regular, $offer ) {
		$money = '\SpringDevs\Subscription\Admin\PlanPresenter';
		if ( '' !== $offer ) :
			?>
			<?php if ( '' !== $regular ) : ?>
				<span style="text-decoration:line-through;opacity:0.7;"><?php echo esc_html( $money::money( (float) $regular ) ); ?></span>
			<?php endif; ?>
			<span style="color:var(--wpsubs-text);font-weight:600;"><?php echo esc_html( $money::money( (float) $offer ) ); ?></span>
			<?php
		elseif ( '' !== $regular ) :
			?>
			<span style="color:var(--wpsubs-text);font-weight:600;"><?php echo esc_html( $money::money( (float) $regular ) ); ?></span>
			<?php
		endif;
	}

	/**
	 * Human label for a variation (its attribute values, e.g. "Large, Red"),
	 * falling back to the WC formatted name or the parent name.
	 *
	 * @param \WC_Product $variation   Variation product.
	 * @param string      $parent_name Parent product name.
	 *
	 * @return string
	 */
	protected static function variation_display_name( $variation, $parent_name ) {
		$attributes = array_filter( array_values( $variation->get_variation_attributes() ) );
		if ( $attributes ) {
			return implode( ', ', $attributes );
		}
		$name = $variation->get_name();
		return $name ? $name : $parent_name;
	}

	/**
	 * Group flat relation rows by plan group.
	 *
	 * @param array $connections Rows from PlanRepository::get_product_connections().
	 *
	 * @return array<int,array> Keyed by group id: title, terms[], relation_ids[].
	 */
	protected static function group_connections( $connections ) {
		$by_group = array();

		foreach ( $connections as $row ) {
			$gid = (int) $row['plan_group_id'];

			if ( ! isset( $by_group[ $gid ] ) ) {
				$by_group[ $gid ] = array(
					'title'           => $row['group_title'],
					'terms'           => array(),
					'relation_ids'    => array(),
					'term_map'        => array(),
					'term_map_by_vid' => array(),
				);
			}

			$vid                                = (int) $row['vid'];
			$data                               = is_array( $row['relation_data'] ) ? $row['relation_data'] : array();
			$price                              = isset( $data['regular_price'] ) ? (string) $data['regular_price'] : '';
			$excluded                           = ! empty( $row['exclude'] );
			$entry                              = array(
				'relation_id' => (int) $row['relation_id'],
				'excluded'    => $excluded,
				'regular'     => $price,
				'offer'       => isset( $data['sale_price'] ) ? (string) $data['sale_price'] : '',
			);
			$by_group[ $gid ]['relation_ids'][] = (int) $row['relation_id'];
			$by_group[ $gid ]['term_map_by_vid'][ $vid ][ (int) $row['plan_id'] ] = $entry;

			// Product-level (vid 0) rows drive the simple-product table + the read
			// summary chips; variation rows are consumed through term_map_by_vid.
			if ( 0 === $vid ) {
				$by_group[ $gid ]['terms'][]                           = array(
					'title'       => $row['plan_title'],
					'price'       => $price,
					'relation_id' => (int) $row['relation_id'],
					'excluded'    => $excluded,
				);
				$by_group[ $gid ]['term_map'][ (int) $row['plan_id'] ] = $entry;
			}
		}

		return $by_group;
	}

	/**
	 * Plan groups not already connected to this product.
	 *
	 * Free is Recurring-only, so it offers only Recurring groups; Pro unlocks
	 * Subscribe & Save and Installments, so all plan types are selectable.
	 *
	 * @param array $connected_ids Group ids already connected.
	 *
	 * @return array<int,array> Each: id, title.
	 */
	protected static function available_groups( $connected_ids ) {
		$pro_active = function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated();
		$recurring  = PlanRepository::type_to_int( 'recurring' );
		$available  = array();

		foreach ( PlanRepository::get_groups() as $group ) {
			if ( ! $pro_active && (int) $group['type'] !== $recurring ) {
				continue;
			}
			if ( 'trash' === ( $group['status'] ?? '' ) ) {
				continue;
			}
			if ( in_array( (int) $group['id'], array_map( 'intval', $connected_ids ), true ) ) {
				continue;
			}
			$available[] = array(
				'id'    => (int) $group['id'],
				'title' => $group['title'],
			);
		}

		return $available;
	}
}
