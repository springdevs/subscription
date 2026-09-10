<?php
/**
 * Subscription Plans REST controller (free base).
 *
 * CRUD for plan groups, plan terms, and product relations, plus a product
 * picker for the admin Plans manager. Namespace `wpsubscription/v1`, gated by
 * `manage_woocommerce`. This is the free plugin's first REST surface; Pro adds
 * the extra routes (`/plans/detach`, `/plans/migrate`, plan-side bulk attach).
 *
 * Admin-only: the storefront and checkout never call these routes - they read
 * plan data directly via PlanRepository. A REST fault cannot break checkout.
 *
 * @package SpringDevs\Subscription\Api
 */

namespace SpringDevs\Subscription\Api;

use SpringDevs\Subscription\Admin\PlanPresenter;
use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

/**
 * Plan REST controller.
 */
class PlanController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NS = 'wpsubscription/v1';

	/**
	 * Register all plan routes.
	 *
	 * Called from within `rest_api_init` (see API::register_api), so it does not
	 * hook the action itself.
	 *
	 * @return void
	 */
	public function register_routes() {
		$perm = array( $this, 'check_permission' );

		register_rest_route(
			self::NS,
			'/plans/groups',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_groups' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_group' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/groups/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_group' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_group' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_group' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/terms',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_term' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/terms/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_term' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_term' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_term' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/relations',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_relation' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/relations/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_relation' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_relation' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_products' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/product-onetime/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_product_onetime' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/group-products/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'group_products_view' ),
					'permission_callback' => $perm,
					'args'                => array(
						'id' => array(
							'validate_callback' => function ( $value ) {
								return is_numeric( $value );
							},
						),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/product-view/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'product_plan_view' ),
					'permission_callback' => $perm,
					'args'                => array(
						'id' => array(
							'validate_callback' => function ( $value ) {
								return is_numeric( $value );
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Permission check: WooCommerce manager.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You are not allowed to manage subscription plans.', 'subscription' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Read request params, preferring a JSON body over query / form params.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return array
	 */
	protected function read_params( WP_REST_Request $request ) {
		$json = $request->get_json_params();

		return ! empty( $json ) ? $json : $request->get_params();
	}

	/* ---- Groups ---- */

	/**
	 * GET /plans/groups - list every plan group with its term count.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_groups() {
		$groups = PlanRepository::get_groups();

		foreach ( $groups as &$group ) {
			$plans               = PlanRepository::get_plans( $group['id'] );
			$group['term_count'] = count( $plans );
			$group['type_key']   = PlanRepository::type_to_string( $group['type'] );
		}
		unset( $group );

		return rest_ensure_response( $groups );
	}

	/**
	 * POST /plans/groups - create a plan group.
	 *
	 * Free is Recurring-only: a non-Recurring type is rejected unless Pro is
	 * active (Pro unlocks Subscribe & Save / Installments).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_group( WP_REST_Request $request ) {
		$params = $this->read_params( $request );

		$guard = $this->guard_recurring_only( $params );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$id = PlanRepository::insert_group( $params );

		if ( ! $id ) {
			return new WP_Error( 'subscrpt_plan_create_failed', __( 'Could not create the plan.', 'subscription' ), array( 'status' => 500 ) );
		}

		// Seed a default monthly duration (draft) so a new plan opens with a
		// starting billing term the merchant can edit and publish.
		$this->create_default_monthly_term( $id, $params['type'] ?? 'recurring' );

		return rest_ensure_response( PlanRepository::get_group_tree( $id ) );
	}

	/**
	 * Create a default monthly duration, in draft, under a freshly created group.
	 *
	 * @param int        $group_id Plan group id.
	 * @param string|int $type     Group/term type (recurring|subscribe_save|installments, or its int).
	 * @return void
	 */
	private function create_default_monthly_term( $group_id, $type ) {
		$type_int        = is_numeric( $type ) ? (int) $type : PlanRepository::type_to_int( $type );
		$is_installments = PlanRepository::TYPE_MAP['installments'] === $type_int;

		$term = array(
			'plan_group_id'     => (int) $group_id,
			'title'             => __( 'Monthly', 'subscription' ),
			'type'              => $type,
			'billing_frequency' => 1,
			'billing_interval'  => 3, // Months.
			'status'            => 'draft',
		);

		if ( $is_installments ) {
			$term['data'] = array( 'installment_count' => 3 );
		}

		PlanRepository::insert_plan( $term );
	}

	/**
	 * GET /plans/groups/{id} - full group tree.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_group( WP_REST_Request $request ) {
		$group = PlanRepository::get_group_tree( (int) $request['id'] );

		if ( ! $group ) {
			return $this->not_found();
		}

		return rest_ensure_response( $group );
	}

	/**
	 * PUT /plans/groups/{id} - update a plan group.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_group( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_group( $id ) ) {
			return $this->not_found();
		}

		$params = $this->read_params( $request );

		$guard = $this->guard_recurring_only( $params );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		PlanRepository::update_group( $id, $params );

		return rest_ensure_response( PlanRepository::get_group_tree( $id ) );
	}

	/**
	 * DELETE /plans/groups/{id} - delete group + cascade.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function delete_group( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_group( $id ) ) {
			return $this->not_found();
		}

		PlanRepository::delete_group( $id );

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/* ---- Terms ---- */

	/**
	 * POST /plans/terms - create a plan term under a group.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_term( WP_REST_Request $request ) {
		$params = $this->read_params( $request );

		if ( empty( $params['plan_group_id'] ) || ! PlanRepository::get_group( $params['plan_group_id'] ) ) {
			return new WP_Error( 'subscrpt_plan_group_missing', __( 'A valid plan_group_id is required.', 'subscription' ), array( 'status' => 400 ) );
		}

		$id = PlanRepository::insert_plan( $params );

		if ( ! $id ) {
			return new WP_Error( 'subscrpt_term_create_failed', __( 'Could not create the plan term.', 'subscription' ), array( 'status' => 500 ) );
		}

		// Link products already in the group to the new duration so it shows
		// on the Products tab for them (inheriting their existing price).
		PlanRepository::backfill_term_relations( (int) $params['plan_group_id'], $id );

		return rest_ensure_response( PlanRepository::get_plan( $id ) );
	}

	/**
	 * GET /plans/terms/{id} - single plan term (for edit prefill).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_term( WP_REST_Request $request ) {
		$term = PlanRepository::get_plan( (int) $request['id'] );

		if ( ! $term ) {
			return $this->not_found();
		}

		return rest_ensure_response( $term );
	}

	/**
	 * PUT /plans/terms/{id} - update a plan term.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_term( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_plan( $id ) ) {
			return $this->not_found();
		}

		PlanRepository::update_plan( $id, $this->read_params( $request ) );

		return rest_ensure_response( PlanRepository::get_plan( $id ) );
	}

	/**
	 * DELETE /plans/terms/{id} - delete a plan term + its relations.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function delete_term( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_plan( $id ) ) {
			return $this->not_found();
		}

		PlanRepository::delete_plan( $id );

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/* ---- Relations ---- */

	/**
	 * POST /plans/relations - attach a product to a plan term.
	 *
	 * Free is simple-product only: a variation relation (`vid` != 0) is rejected
	 * unless Pro is active (Pro unlocks per-variation attach).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_relation( WP_REST_Request $request ) {
		$params = $this->read_params( $request );

		if ( empty( $params['plan_id'] ) || ! PlanRepository::get_plan( $params['plan_id'] ) ) {
			return new WP_Error( 'subscrpt_plan_missing', __( 'A valid plan_id is required.', 'subscription' ), array( 'status' => 400 ) );
		}

		if ( empty( $params['oid'] ) ) {
			return new WP_Error( 'subscrpt_oid_missing', __( 'A product or term id (oid) is required.', 'subscription' ), array( 'status' => 400 ) );
		}

		if ( ! isset( $params['type'] ) ) {
			$params['type'] = PlanRepository::REL_PRODUCT;
		}

		$guard = $this->guard_simple_only( $params );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$id = PlanRepository::insert_relation( $params );

		if ( ! $id ) {
			return new WP_Error( 'subscrpt_relation_create_failed', __( 'Could not attach the product.', 'subscription' ), array( 'status' => 500 ) );
		}

		// Connecting a plan enables the subscription on the product / variation
		// (it stays on until a product save explicitly clears the toggle). For a
		// variation, the parent's "any variation enabled" flag is turned on too.
		$oid = (int) $params['oid'];
		if ( ! empty( $params['vid'] ) ) {
			update_post_meta( (int) $params['vid'], '_subscrpt_enabled', 'yes' );
			update_post_meta( $oid, '_subscrpt_enabled', 'yes' );
		} else {
			update_post_meta( $oid, '_subscrpt_enabled', 'yes' );
		}

		// Default the purchase limit when the product has never had one set. The
		// storefront gate (Frontend\Product::check_if_purchasable) only overrides
		// WooCommerce's empty-price rule when a limit is set, so without this a
		// plan-connected product with no base price stays un-purchasable — its
		// plan selector never renders until a product save writes this meta.
		if ( '' === get_post_meta( $oid, '_subscrpt_limit', true ) ) {
			update_post_meta( $oid, '_subscrpt_limit', 'unlimited' );
		}

		return rest_ensure_response( PlanRepository::get_relation( $id ) );
	}

	/**
	 * PUT /plans/relations/{id} - update a relation (price / exclude).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_relation( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_relation( $id ) ) {
			return $this->not_found();
		}

		PlanRepository::update_relation( $id, $this->read_params( $request ) );

		return rest_ensure_response( PlanRepository::get_relation( $id ) );
	}

	/**
	 * DELETE /plans/relations/{id} - detach a product from a plan term.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function delete_relation( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_relation( $id ) ) {
			return $this->not_found();
		}

		PlanRepository::delete_relation( $id );

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/**
	 * PUT /plans/product-onetime/{id} - save a product's one-time purchase.
	 *
	 * One-time purchase is product-specific: its price is the product's native
	 * WooCommerce price (regular = one-time price, sale = one-time offer). A
	 * simple product has a single enabled flag; a variable product enables it
	 * per variation (each variation stores its own flag + native price, and the
	 * parent flag mirrors "any variation enabled").
	 *
	 * Body (simple): { enabled: bool, price?: string, offer?: string }.
	 * Body (variable): { variations: { <vid>: { enabled: bool, price?, offer? } } }.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save_product_onetime( WP_REST_Request $request ) {
		$product_id = (int) $request['id'];
		$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		if ( ! $product ) {
			return $this->not_found();
		}

		$params = $this->read_params( $request );

		if ( $product->is_type( 'variable' ) ) {
			// Per-variation one-time: each variation carries its own enabled flag
			// + native price. Saves may be partial (one variation at a time).
			$variations = isset( $params['variations'] ) && is_array( $params['variations'] ) ? $params['variations'] : array();
			foreach ( $variations as $vid => $vals ) {
				$variation = wc_get_product( (int) $vid );
				if ( ! $variation || 'variation' !== $variation->get_type() || (int) $variation->get_parent_id() !== $product_id ) {
					continue;
				}
				$variation->update_meta_data( '_subscrpt_one_time_enabled', empty( $vals['enabled'] ) ? '' : 'yes' );
				$this->set_native_prices( $variation, is_array( $vals ) ? $vals : array() );
				$variation->save();
			}

			// Parent flag mirrors "any variation enabled" — recomputed from all
			// children so a partial save never clears it for other variations.
			$any_enabled = false;
			foreach ( $product->get_children() as $child_id ) {
				if ( 'yes' === get_post_meta( (int) $child_id, '_subscrpt_one_time_enabled', true ) ) {
					$any_enabled = true;
					break;
				}
			}
			$product->update_meta_data( '_subscrpt_one_time_enabled', $any_enabled ? 'yes' : '' );
		} else {
			$enabled = ! empty( $params['enabled'] );
			$product->update_meta_data( '_subscrpt_one_time_enabled', $enabled ? 'yes' : '' );
			$this->set_native_prices( $product, $params );
		}

		$product->save();

		return rest_ensure_response( array( 'saved' => true ) );
	}

	/**
	 * Write a product/variation's native regular + sale price from a one-time
	 * price payload ({ price, offer }). Empty values clear the price.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @param array       $prices  { price, offer } values.
	 *
	 * @return void
	 */
	protected function set_native_prices( $product, $prices ) {
		$regular = ( isset( $prices['price'] ) && '' !== $prices['price'] ) ? wc_format_decimal( $prices['price'] ) : '';
		$offer   = ( isset( $prices['offer'] ) && '' !== $prices['offer'] ) ? wc_format_decimal( $prices['offer'] ) : '';

		$product->set_regular_price( $regular );
		$product->set_sale_price( $offer );
		$product->set_price( '' !== $offer ? $offer : $regular );
	}

	/**
	 * GET /plans/product-view/{id} - re-render the product-editor plan view.
	 *
	 * Used to refresh the Subscription tab's plan view in place (no page
	 * reload) after a connect / detach or after creating a plan group + plan
	 * from the product editor, so unsaved product edits are preserved. Returns
	 * only the plan-view fragment; the modals live outside it.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function product_plan_view( WP_REST_Request $request ) {
		$product_id = (int) $request->get_param( 'id' );
		$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		// render_plan_view() supports simple products (free) and variable products
		// (Pro reuses it for the variation plan view), so both can refresh in place.
		if ( ! $product || ! ( $product->is_type( 'simple' ) || $product->is_type( 'variable' ) ) ) {
			return new WP_Error(
				'rest_invalid_product',
				__( 'Plan view is not available for this product type.', 'subscription' ),
				array( 'status' => 400 )
			);
		}

		ob_start();
		\SpringDevs\Subscription\Admin\Product\Plans::render_plan_view( $product );
		$html = ob_get_clean();

		return rest_ensure_response( array( 'html' => $html ) );
	}

	/**
	 * GET /plans/group-products/{id} - re-render a plan's Products tab.
	 *
	 * The same fragment the detail page includes, so attaching, detaching or
	 * repricing can refresh it in place instead of reloading the page. The
	 * alternative is rebuilding PlanPresenter's shape — and WooCommerce's price
	 * formatting — in JavaScript, which would drift from the template the first
	 * time either changed.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function group_products_view( WP_REST_Request $request ) {
		$plan = PlanPresenter::group( (int) $request->get_param( 'id' ) );

		if ( empty( $plan ) ) {
			return $this->not_found();
		}

		ob_start();
		require SUBSCRPT_INCLUDES . '/Admin/views/plans/tab-products.php';
		$html = ob_get_clean();

		return rest_ensure_response( array( 'html' => $html ) );
	}

	/* ---- Product picker ---- */

	/**
	 * GET /plans/products - search WC products for the connect picker.
	 *
	 * Free is simple-product only: the picker returns simple products.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function search_products( WP_REST_Request $request ) {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );

		$args = array(
			'status'  => 'publish',
			'type'    => 'simple',
			'limit'   => 20,
			'return'  => 'objects',
			's'       => $search,
			'orderby' => 'title',
			'order'   => 'ASC',
		);

		$products = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : array();
		$results  = array();

		foreach ( $products as $product ) {
			$results[] = self::product_row( $product );
		}

		/**
		 * Filter the product-picker results. Free returns simple products only;
		 * Pro hooks this to append variable products (with nested variations).
		 *
		 * @param array  $results Product rows (see product_row()).
		 * @param string $search  Current search term.
		 */
		$results = apply_filters( 'subscrpt_plan_products', $results, $search );

		// Newest first — sort parents by ID descending (variations keep their order).
		usort(
			$results,
			function ( $a, $b ) {
				return (int) $b['id'] - (int) $a['id'];
			}
		);

		return rest_ensure_response( $results );
	}

	/**
	 * Build one product-picker row.
	 *
	 * Shape: id, name, type, image (thumbnail URL or ''), price (numeric,
	 * for the relation price field), price_html (display, decoded), is_virtual,
	 * variations (array of child rows — empty for simple products).
	 *
	 * @param \WC_Product $product   Product (or variation).
	 * @param string      $name_over Optional name override (used for variations).
	 *
	 * @return array
	 */
	public static function product_row( $product, $name_over = '' ) {
		$image_id = $product->get_image_id();
		$price    = (float) wc_get_price_to_display( $product );

		// Amount only — wc_price() skips the subscription "/ period" suffix.
		// Variable parents have no single price, so they show none.
		$price_html = $product->is_type( 'variable' )
			? ''
			: html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES, 'UTF-8' );

		// The plan group this product is attached to (0 = none). A product belongs
		// to a single group; the picker disables rows already in another group.
		// Variations share their parent's connection (relations use the parent oid),
		// cached per owner so a product's variations don't each re-query.
		static $group_cache = array();

		$owner_id = $product->get_parent_id() ? (int) $product->get_parent_id() : (int) $product->get_id();
		if ( ! isset( $group_cache[ $owner_id ] ) ) {
			$conns                    = PlanRepository::get_product_connections( $owner_id );
			$group_cache[ $owner_id ] = ! empty( $conns )
				? array(
					'id'   => (int) $conns[0]['plan_group_id'],
					'name' => (string) $conns[0]['group_title'],
				)
				: array(
					'id'   => 0,
					'name' => '',
				);
		}

		return array(
			'id'              => $product->get_id(),
			'name'            => '' !== $name_over ? $name_over : $product->get_name(),
			'type'            => $product->get_type(),
			'image'           => $image_id ? wp_get_attachment_image_url( $image_id, array( 48, 48 ) ) : '',
			'price'           => $price,
			'price_html'      => $price_html,
			'is_virtual'      => $product->is_virtual(),
			'plan_group_id'   => $group_cache[ $owner_id ]['id'],
			'plan_group_name' => $group_cache[ $owner_id ]['name'],
			'variations'      => array(),
		);
	}

	/* ---- Free constraint guards ---- */

	/**
	 * Reject a non-Recurring group type on a free-only install.
	 *
	 * @param array $params Group params.
	 *
	 * @return true|WP_Error
	 */
	protected function guard_recurring_only( array $params ) {
		if ( ! isset( $params['type'] ) || subscrpt_pro_activated() ) {
			return true;
		}

		$type_int = is_numeric( $params['type'] ) ? (int) $params['type'] : PlanRepository::type_to_int( $params['type'] );

		if ( PlanRepository::TYPE_MAP['recurring'] !== $type_int ) {
			return new WP_Error(
				'subscrpt_plan_type_pro',
				__( 'Recurring Delivery and Split Payment plans require Subscription Pro.', 'subscription' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Reject a per-variation relation (vid != 0) on a free-only install.
	 *
	 * @param array $params Relation params.
	 *
	 * @return true|WP_Error
	 */
	protected function guard_simple_only( array $params ) {
		if ( subscrpt_pro_activated() ) {
			return true;
		}

		if ( ! empty( $params['vid'] ) ) {
			return new WP_Error(
				'subscrpt_variation_pro',
				__( 'Attaching plans to product variations requires Subscription Pro.', 'subscription' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Standard 404 response.
	 *
	 * @return WP_Error
	 */
	protected function not_found() {
		return new WP_Error( 'subscrpt_plan_not_found', __( 'Not found.', 'subscription' ), array( 'status' => 404 ) );
	}
}
