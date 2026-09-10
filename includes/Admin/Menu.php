<?php
/**
 * Admin menu + shared admin header/breadcrumb renderer.
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Menu class
 *
 * @package SpringDevs\Subscription\Admin
 */
class Menu {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'reorder_submenu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_subscrpt_bulk_action', array( $this, 'handle_bulk_action_ajax' ) );
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_style(
			'wp-subscription-admin',
			SUBSCRPT_ASSETS . '/css/admin.css',
			array(),
			SUBSCRPT_VERSION
		);

		// Enqueue admin JavaScript for subscription list functionality
		wp_enqueue_script(
			'sdevs_subscription_admin',
			SUBSCRPT_ASSETS . '/js/admin.js',
			array( 'jquery' ),
			SUBSCRPT_VERSION,
			true
		);

		// Enqueue onboarding wizard styles.
		wp_enqueue_style(
			'subscrpt-onboarding-wizard',
			SUBSCRPT_ASSETS . '/css/admin/onboarding-wizard.css',
			array(),
			SUBSCRPT_VERSION
		);

		// Enqueue onboarding wizard JS (loaded on wizard page). Depends on the
		// admin components so the cadence picker (adv-select) is ready.
		wp_enqueue_script(
			'subscrpt-onboarding-wizard',
			SUBSCRPT_ASSETS . '/js/admin/onboarding-wizard.js',
			array( 'jquery', 'subscrpt_admin_components' ),
			SUBSCRPT_VERSION,
			true
		);
		$subscrpt_wizard_has_products = (bool) wc_get_products(
			array(
				'status' => array( 'publish', 'draft', 'pending', 'private' ),
				'limit'  => 1,
				'return' => 'ids',
			)
		);

		wp_localize_script(
			'subscrpt-onboarding-wizard',
			'subscrpt_wizard',
			array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'subscriptions_url' => admin_url( 'admin.php?page=wp-subscription' ),
				'products_url'      => admin_url( 'edit.php?post_type=product' ),
				'plans_url'         => admin_url( 'admin.php?page=wp-subscription-plans' ),
				'rest_url'          => rest_url( 'wpsubscription/v1/plans' ),
				'rest_nonce'        => wp_create_nonce( 'wp_rest' ),
				'currency_symbol'   => get_woocommerce_currency_symbol(),
				'is_pro'            => subscrpt_pro_activated(),
				'has_products'      => $subscrpt_wizard_has_products,
			)
		);

		// Localize script for AJAX
		wp_localize_script(
			'sdevs_subscription_admin',
			'wp_subscription_ajax',
			array(
				'nonce'   => wp_create_nonce( 'subscrpt_bulk_action_nonce' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Create Subscriptions Menu.
	 */
	public function create_admin_menu() {
		$parent_slug = 'wp-subscription';
		// Determine if the menu is active
		$is_active = isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'wp-subscription' ) === 0;
		$icon_url  = $is_active
			? SUBSCRPT_ASSETS . '/images/icons/subscription-20.png'
			: SUBSCRPT_ASSETS . '/images/icons/subscription-20-gray.png';

		$pro_text  = __( 'WPSubscription Pro required', 'subscription' );
		$pro_badge = subscrpt_pro_activated() ? '' : ' <span title="' . $pro_text . '"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" style="fill:var(--wpsubs-brand);vertical-align:middle;margin-bottom:2.2px;flex-shrink:0;" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19 19h-14c-.5 0 -.9 -.3 -1 -.8l-2 -10c0 -.4 .1 -.8 .5 -1.1c.4 -.2 .8 -.2 1.1 0l4.1 3.3l3.4 -5.1c.4 -.6 1.3 -.6 1.7 0l3.4 5.1l4.1 -3.3c.3 -.3 .8 -.3 1.1 0c.4 .2 .5 .6 .5 1.1l-2 10c0 .5 -.5 .8 -1 .8z"/></svg></span>';

		// Main menu
		add_menu_page(
			__( 'WPSubscription', 'subscription' ),
			__( 'WPSubscription', 'subscription' ),
			'manage_options',
			$parent_slug,
			array( $this, 'render_dashboard_page' ),
			$icon_url,
			40
		);

		// Onboarding Wizard (hidden from menu with CSS. can be accessed via direct URL: admin.php?page=wp-subscription-onboarding)
		// CSS Record: admin.css -> `.wp-submenu a[href*="page=wp-subscription-onboarding"]`
		add_submenu_page(
			$parent_slug,
			__( 'Setup Wizard', 'subscription' ),
			__( 'Setup Wizard', 'subscription' ),
			'manage_options',
			'wp-subscription-onboarding',
			array( $this, 'render_onboarding_wizard' )
		);

		// Dashboard. WordPress makes the first submenu entry share the parent
		// slug, so this is the page the top-level item opens.
		add_submenu_page(
			$parent_slug,
			__( 'Dashboard', 'subscription' ),
			__( 'Dashboard', 'subscription' ),
			'manage_options',
			$parent_slug,
			array( $this, 'render_dashboard_page' )
		);

		// Subscriptions List. Moved off the parent slug when the dashboard took
		// it; render_dashboard_page() redirects here when the request carries
		// list-only query arguments, so old bookmarks still work.
		add_submenu_page(
			$parent_slug,
			__( 'Subscriptions', 'subscription' ),
			__( 'Subscriptions', 'subscription' ),
			'manage_options',
			'wp-subscription-list',
			array( $this, 'render_subscriptions_page' )
		);

		// Subscription Details (hidden from menu with CSS — accessed via admin.php?page=wp-subscription-details&id=ID).
		// CSS Record: admin.css -> `.wp-submenu a[href*="page=wp-subscription-details"]`
		add_submenu_page(
			$parent_slug,
			__( 'Subscription Details', 'subscription' ),
			__( 'Subscription Details', 'subscription' ),
			'manage_options',
			'wp-subscription-details',
			array( $this, 'render_subscription_details_page' )
		);

		// Stats Overview
		add_submenu_page(
			$parent_slug,
			__( 'Reports', 'subscription' ),
			__( 'Reports', 'subscription' ) . $pro_badge,
			'manage_options',
			'wp-subscription-stats',
			array( $this, 'render_stats_page' )
		);

		// Subscription Health
		add_submenu_page(
			$parent_slug,
			__( 'Health', 'subscription' ),
			__( 'Health', 'subscription' ) . $pro_badge,
			'manage_options',
			'wp-subscription-health',
			array( $this, 'render_health_page' )
		);

		// Delivery Schedules
		add_submenu_page(
			$parent_slug,
			__( 'Delivery', 'subscription' ),
			__( 'Delivery', 'subscription' ) . $pro_badge,
			'manage_options',
			'wp-subscription-delivery',
			array( $this, 'render_delivery_page' )
		);

		// Help & Resources
		add_submenu_page(
			$parent_slug,
			__( 'Help', 'subscription' ),
			__( 'Help', 'subscription' ),
			'manage_options',
			'wp-subscription-support',
			array( $this, 'render_support_page' )
		);

		/*
		 * WPSubscription link under the WooCommerce menu.
		 *
		 * The callback has to match the one the parent menu registers. WordPress
		 * derives this entry's hookname from the *slug*, and because
		 * `wp-subscription` is itself a registered top-level menu that resolves
		 * to `toplevel_page_wp-subscription` — the same hook the parent uses.
		 * Two identical callbacks on one hook are deduplicated; two different
		 * ones both run, which rendered the dashboard and the subscriptions list
		 * stacked on the same screen.
		 */
		add_submenu_page(
			'woocommerce',
			__( 'WPSubscription', 'subscription' ),
			__( 'WPSubscription', 'subscription' ),
			'manage_options',
			'wp-subscription',
			array( $this, 'render_dashboard_page' )
		);
	}

	/**
	 * Reorder the WPSubscription submenu after all items are registered.
	 *
	 * Runs at admin_menu priority 999 so every plugin has already inserted
	 * its items. A filter lets the Pro plugin (or any extension) adjust the
	 * slug order before sorting is applied.
	 *
	 * @do_action subscrpt_submenu_order {string[]} $order Ordered list of submenu page slugs.
	 */
	public function reorder_submenu() {
		$parent = 'wp-subscription';

		global $submenu;

		if ( empty( $submenu[ $parent ] ) ) {
			return;
		}

		// slug => position. Use gaps of 10 so extensions can insert between items.
		$default_order = [
			'wp-subscription'              => 5,  // Dashboard
			'wp-subscription-list'         => 10, // Subscriptions
			'wp-subscription-stats'        => 20, // Reports
			'wp-subscription-delivery'     => 30, // Delivery (pro)
			'wp-subscription-health'       => 50, // Health
			'wp-subscription-integrations' => 60, // Integrations
			'wp-subscription-support'      => 70, // Help & Resources
			'wp-subscription-settings'     => 998, // Settings
			'wp-subscription-license'      => 999, // License (pro)
		];

		// Place pro pages at the bottom if pro is not active.
		if ( ! subscrpt_pro_activated() ) {
			$default_order['wp-subscription-stats']    = 200;
			$default_order['wp-subscription-delivery'] = 210;
			$default_order['wp-subscription-health']   = 220;
		}

		/**
		 * Filter the WPSubscription submenu slug order.
		 *
		 * Each entry is a slug => integer position pair. Lower positions appear
		 * first. Use gaps of 10 between built-in positions so extensions can
		 * insert their own slugs between existing items without renumbering.
		 *
		 * Example (pro plugin adding Delivery at position 35):
		 *   add_filter( 'subscrpt_submenu_order', function( $order ) {
		 *       $order['wp-subscription-delivery'] = 35;
		 *       return $order;
		 *   } );
		 *
		 * @param array<string,int> $order Map of slug => position.
		 */
		$order = apply_filters( 'subscrpt_submenu_order', $default_order );

		// Sort by position value, preserving slug keys.
		asort( $order );

		// Index current items by slug for fast lookup.
		$indexed = [];
		foreach ( $submenu[ $parent ] as $item ) {
			$indexed[ $item[2] ] = $item;
		}

		// Build sorted list from the ordered slugs.
		$sorted = [];
		foreach ( $order as $slug => $position ) {
			if ( isset( $indexed[ $slug ] ) ) {
				$sorted[] = $indexed[ $slug ];
				unset( $indexed[ $slug ] );
			}
		}

		// Append any remaining items not covered by the order list.
		foreach ( $indexed as $item ) {
			$sorted[] = $item;
		}

		$submenu[ $parent ] = $sorted; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional reorder of submenu items.
	}

	/**
	 * Render the admin header
	 */
	public function render_admin_footer() {
		?>
		<div style="text-align:center;margin:38px 0 0 0;font-size:14px;color:#888;">
			Made with <span style="color:#e25555;font-size:1.1em;">♥</span> by the WPSubscription Team
			<div style="margin-top:6px;">
				<a href="https://wpsubscription.co/contact?utm_source=plugin&utm_medium=admin&utm_campaign=support" target="_blank" style="color:#2563eb;text-decoration:none;">Support</a>
				&nbsp;/&nbsp;
				<a href="https://docs.wpsubscription.co/en?utm_source=plugin&utm_medium=admin&utm_campaign=docs" target="_blank" style="color:#2563eb;text-decoration:none;">Docs</a>
			</div>
		</div>
		<?php
	}
	/**
	 * Render the admin header.
	 *
	 * The breadcrumb after the home icon is built from $breadcrumbs when given —
	 * an ordered trail supporting any number of levels — otherwise it falls back
	 * to the single $title segment (backward compatible with existing callers).
	 *
	 * Each breadcrumb item is an array: `[ 'label' => string, 'url' => string ]`.
	 * Items with a non-empty `url` render as links, except the last item, which is
	 * always the current (non-linked) page.
	 *
	 * @param string $title       Page title shown as the current segment when no
	 *                            $breadcrumbs trail is supplied.
	 * @param string $subtitle    Optional subtitle (reserved; not rendered).
	 * @param array  $breadcrumbs Ordered trail of `[ 'label', 'url' ]` items.
	 */
	public function render_admin_header( string $title = '', string $subtitle = '', array $breadcrumbs = [] ) {
		$current = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'wp-subscription';

		// Kept for backward compatibility — extensions may hook here for side-effects.
		$menu_items = apply_filters( 'subscrpt_admin_header_menu_items', [], $current );
		unset( $menu_items ); // Nav no longer rendered in header; navigation is in WP sidebar.

		// Normalize to a single trail. Explicit breadcrumbs win; otherwise the
		// legacy single $title segment is used.
		// A long breadcrumb label is truncated for display; the full text is kept
		// so the renderer can add it as a title attribute.
		$make_crumb = static function ( $label, $url ) {
			$label = (string) $label;
			return [
				'label' => subscrpt_truncate_text( $label ),
				'full'  => $label,
				'url'   => (string) $url,
			];
		};

		$trail = [];
		if ( ! empty( $breadcrumbs ) ) {
			foreach ( $breadcrumbs as $crumb ) {
				if ( is_array( $crumb ) && '' !== ( $crumb['label'] ?? '' ) ) {
					$trail[] = $make_crumb( $crumb['label'], $crumb['url'] ?? '' );
				}
			}
		} elseif ( '' !== $title ) {
			$trail[] = $make_crumb( $title, '' );
		}

		$last_index = count( $trail ) - 1;
		?>
		<div class="wp-subscription-admin-header">
			<div class="wp-subscription-admin-header-inner">
			<div class="wp-subscription-admin-header-left">
				<nav class="wp-subscription-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'subscription' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription' ) ); ?>" class="wp-subscription-breadcrumb-home" aria-label="<?php esc_attr_e( 'WPSubscription Home', 'subscription' ); ?>">
						<span class="dashicons dashicons-admin-home"></span>
					</a>
					<?php foreach ( $trail as $index => $crumb ) : ?>
						<span class="wp-subscription-breadcrumb-sep" aria-hidden="true">/</span>
						<?php $crumb_title = $crumb['full'] !== $crumb['label'] ? ' title="' . esc_attr( $crumb['full'] ) . '"' : ''; ?>
						<?php if ( '' !== $crumb['url'] && $index < $last_index ) : ?>
							<a href="<?php echo esc_url( $crumb['url'] ); ?>" class="wp-subscription-breadcrumb-link"<?php echo $crumb_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute pre-escaped. ?>><?php echo esc_html( $crumb['label'] ); ?></a>
						<?php else : ?>
							<span class="wp-subscription-breadcrumb-current"<?php echo $crumb_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute pre-escaped. ?>><?php echo esc_html( $crumb['label'] ); ?></span>
						<?php endif; ?>
					<?php endforeach; ?>
				</nav>
			</div>
			<div class="wp-subscription-admin-header-right">
				<?php
				/**
				 * Filters the pro licence state shown in the admin header.
				 *
				 * This plugin cannot ask the pro plugin directly — it runs alone
				 * on nearly every install, so naming a symbol pro declares would
				 * fatal there. Pro answers this filter when it is present; when
				 * it is not, the value stays null and no badge is rendered.
				 *
				 * @since 1.11.3
				 *
				 * @param array|null $license {
				 *     Licence state, or null when pro is not installed.
				 *
				 *     @type bool   $active Whether the licence is valid.
				 *     @type string $url    Admin URL of the licence page.
				 * }
				 */
				$license = apply_filters( 'subscrpt_admin_header_license', null );

				// Only the "Activate license" badge is shown; the "License active"
				// badge is intentionally hidden from the header.
				if ( is_array( $license ) && isset( $license['active'] ) && ! $license['active'] ) :
					$license_url = isset( $license['url'] ) ? (string) $license['url'] : '';
					?>
					<a href="<?php echo esc_url( $license_url ); ?>" class="wpsubs-badge wpsubs-badge--warning wp-subscription-license-badge">
						<span class="wpsubs-badge__dot"></span>
						<?php esc_html_e( 'Activate license', 'subscription' ); ?>
					</a>
					<?php
				endif;
				?>

				<?php if ( ! class_exists( 'Sdevs_Wc_Subscription_Pro' ) ) : ?>
					<a target="_blank" href="https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" rel="noreferrer noopener">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="flex-shrink:0;"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19 19h-14c-.5 0 -.9 -.3 -1 -.8l-2 -10c0 -.4 .1 -.8 .5 -1.1c.4 -.2 .8 -.2 1.1 0l4.1 3.3l3.4 -5.1c.4 -.6 1.3 -.6 1.7 0l3.4 5.1l4.1 -3.3c.3 -.3 .8 -.3 1.1 0c.4 .2 .5 .6 .5 1.1l-2 10c0 .5 -.5 .8 -1 .8z"/></svg>
						<?php esc_html_e( 'Upgrade to Pro', 'subscription' ); ?>
					</a>
				<?php endif; ?>
<img src="<?php echo esc_url( SUBSCRPT_ASSETS . '/images/logo-title.svg' ); ?>" alt="WPSubscription" class="wp-subscription-logo">
			</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the dashboard, or hand off to the list.
	 *
	 * The subscriptions list used to live on this slug. Anything still linking
	 * here with a list-only argument — a saved filter, a bookmarked search, a
	 * pagination link — means the list, so it is sent there with its arguments
	 * intact rather than landing on a dashboard that ignores them.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$list_args = array( 'post_status', 's', 'paged', 'filter_action', 'orderby', 'order', 'subscrpt_status' );

		foreach ( $list_args as $arg ) {
			if ( isset( $_GET[ $arg ] ) && '' !== $_GET[ $arg ] ) {
				$query         = wp_unslash( $_GET );
				$query['page'] = 'wp-subscription-list';

				wp_safe_redirect( add_query_arg( array_map( 'sanitize_text_field', $query ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		( new Dashboard() )->render();
	}

	/**
	 * Render Subscriptions page
	 */
	public function render_subscriptions_page() {
		$this->render_admin_header( __( 'Subscriptions', 'subscription' ), __( 'Manage your subscriptions', 'subscription' ) );

		// Handle filters
		$status      = isset( $_GET['subscrpt_status'] ) ? sanitize_text_field( wp_unslash( $_GET['subscrpt_status'] ) ) : '';
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$date_filter = isset( $_GET['date_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['date_filter'] ) ) : '';
		$per_page    = isset( $_GET['per_page'] ) ? max( 1, intval( $_GET['per_page'] ) ) : 20;
		$paged       = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

		// Handle form submissions (both filters and bulk actions)
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $request_method ) {
			// Verify nonce before processing any POST data.
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'subscrpt_list_action' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'subscription' ) );
			}
			// Handle bulk actions
			if ( isset( $_POST['bulk_action'] ) || isset( $_POST['bulk_action2'] ) ) {
				$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : sanitize_text_field( wp_unslash( $_POST['bulk_action2'] ?? '' ) );
				$action      = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : sanitize_text_field( wp_unslash( $_POST['action2'] ?? '' ) );

				if ( $bulk_action && $action && $action !== '-1' && isset( $_POST['subscription_ids'] ) && is_array( $_POST['subscription_ids'] ) ) {
					$subscription_ids = array_map( 'intval', $_POST['subscription_ids'] );

					if ( $action === 'trash' ) {
						foreach ( $subscription_ids as $sub_id ) {
							wp_trash_post( $sub_id );
						}
					} elseif ( $action === 'restore' ) {
						foreach ( $subscription_ids as $sub_id ) {
							wp_untrash_post( $sub_id );
						}
					} elseif ( $action === 'delete' ) {
						foreach ( $subscription_ids as $sub_id ) {
							wp_delete_post( $sub_id, true );
						}
					}

					wp_safe_redirect( admin_url( 'admin.php?page=wp-subscription' ) );
					exit;
				}
			}

			// Handle filter form submission
			if ( isset( $_POST['filter_action'] ) ) {
				$filter_params = array();

				if ( ! empty( $_POST['subscrpt_status'] ) ) {
					$filter_params['subscrpt_status'] = sanitize_text_field( wp_unslash( $_POST['subscrpt_status'] ) );
				}
				if ( ! empty( $_POST['date_filter'] ) ) {
					$filter_params['date_filter'] = sanitize_text_field( wp_unslash( $_POST['date_filter'] ) );
				}
				if ( ! empty( $_POST['s'] ) ) {
					$filter_params['s'] = sanitize_text_field( wp_unslash( $_POST['s'] ) );
				}
				if ( ! empty( $_POST['per_page'] ) ) {
					$filter_params['per_page'] = intval( $_POST['per_page'] );
				}

				$redirect_url = add_query_arg( $filter_params, admin_url( 'admin.php?page=wp-subscription' ) );
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}

		// Handle individual actions
		if ( isset( $_GET['action'] ) && ! empty( $_GET['sub_id'] ) ) {
			$sub_id = intval( $_GET['sub_id'] );
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
			$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

			// Clean trash action.
			if ( $action === 'clean_trash' ) {
				// Verify nonce for security.
				$nonce_action = 'wpsubs_action_clean_trash';
				if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please try again.', 'subscription' ) . '</p></div>';
					wp_die();
				}

				// Clean all trash items.
				$trash_posts = get_posts(
					[
						'post_type'   => 'subscrpt_order',
						'post_status' => 'trash',
						'numberposts' => -1,
						'fields'      => 'ids',
					]
				);

				foreach ( $trash_posts as $trash_id ) {
					wp_delete_post( $trash_id, true );
				}

				wp_safe_redirect( admin_url( 'admin.php?page=wp-subscription&subscrpt_status=trash' ) );
				exit;
			} else {
				// For other actions, verify nonce with subscription ID.
				$nonce_action = 'wpsubs_action_' . $sub_id;
				if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please try again.', 'subscription' ) . '</p></div>';
					wp_die();
				}

				$redirect_url = admin_url( 'admin.php?page=wp-subscription' );

				switch ( $action ) {
					case 'duplicate':
						$post = get_post( $sub_id );
						if ( $post && $post->post_type === 'subscrpt_order' ) {
							$new_post = [
								'post_title'   => $post->post_title . ' (Copy)',
								'post_content' => $post->post_content,
								'post_status'  => 'draft',
								'post_type'    => 'subscrpt_order',
							];
							$new_id   = wp_insert_post( $new_post );
							if ( $new_id ) {
								$meta = get_post_meta( $sub_id );
								foreach ( $meta as $key => $values ) {
									foreach ( $values as $value ) {
										add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
									}
								}
							}
						}
						break;
					case 'trash':
						wp_trash_post( $sub_id );
						break;
					case 'restore':
						wp_untrash_post( $sub_id );
						break;
					case 'delete':
						wp_delete_post( $sub_id, true );
						$redirect_url = admin_url( 'admin.php?page=wp-subscription&subscrpt_status=trash' );
						break;
				}

				wp_safe_redirect( $redirect_url );
				exit;
			}
		}

		$args = [
			'post_type'      => 'subscrpt_order',
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $status ) {
			$args['post_status'] = $status;
		}
		// Search only by subscription ID
		if ( $search !== '' ) {
			if ( is_numeric( $search ) ) {
				$args['p'] = intval( $search );
			} else {
				// If not numeric, return no results
				$args['post__in'] = array( 0 );
			}
		}
		// Dynamic date filter (YYYY-MM)
		if ( $date_filter && preg_match( '/^\d{4}-\d{2}$/', $date_filter ) ) {
			$year                 = substr( $date_filter, 0, 4 );
			$month                = substr( $date_filter, 5, 2 );
			$args['date_query'][] = [
				'year'  => intval( $year ),
				'month' => intval( $month ),
			];
		}

		$query         = new \WP_Query( $args );
		$subscriptions = $query->posts;
		$total         = $query->found_posts;
		$max_num_pages = $query->max_num_pages;

		// Get all possible statuses for filter dropdown
		$all_statuses = get_post_stati( [ 'show_in_admin_all_list' => true ], 'objects' );

		include __DIR__ . '/views/subscription-list.php';
		?>
		<div style="text-align:center;margin:38px 0 0 0;font-size:14px;color:#888;">
			Made with <span style="color:#e25555;font-size:1.1em;">♥</span> by the WPSubscription Team
			<div style="margin-top:6px;">
				<a href="https://wpsubscription.co/contact?utm_source=plugin&utm_medium=admin&utm_campaign=support" target="_blank" style="color:#2563eb;text-decoration:none;">Support</a>
				&nbsp;/&nbsp;
				<a href="https://docs.wpsubscription.co/en?utm_source=plugin&utm_medium=admin&utm_campaign=docs" target="_blank" style="color:#2563eb;text-decoration:none;">Docs</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the standalone Subscription Details page.
	 *
	 * Replaces the legacy metabox edit screen. Mirrors the list-page design
	 * shell (header + wpsubs components) and handles the status-change form.
	 *
	 * @return void
	 */
	public function render_subscription_details_page() {
		$subscription_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

		if ( ! $subscription_id || 'subscrpt_order' !== get_post_type( $subscription_id ) ) {
			wp_die( esc_html__( 'Invalid subscription.', 'subscription' ) );
		}

		if ( ! current_user_can( 'edit_post', $subscription_id ) ) {
			wp_die( esc_html__( 'You do not have permission to view this subscription.', 'subscription' ) );
		}

		$list_url    = admin_url( 'admin.php?page=wp-subscription' );
		$form_action = admin_url( 'admin.php?page=wp-subscription-details&id=' . $subscription_id );

		// Handle the status-change submission.
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $request_method && isset( $_POST['subscrpt_order_action'] ) ) {
			$nonce = isset( $_POST['subscrpt_order_action_nonce_field'] ) ? sanitize_text_field( wp_unslash( $_POST['subscrpt_order_action_nonce_field'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'subscrpt_order_action_nonce' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'subscription' ) );
			}

			$action = sanitize_text_field( wp_unslash( $_POST['subscrpt_order_action'] ) );
			if ( '' !== $action ) {
				Subscriptions::process_status_change( $subscription_id, $action );
			}

			wp_safe_redirect( $form_action );
			exit;
		}

		// Allowed actions for the current status (mirrors Subscriptions::subscrpt_order_save_post()).
		$actions_data = array(
			'active'       => array(
				'label' => __( 'Activate Subscription', 'subscription' ),
				'value' => 'active',
			),
			'pending'      => array(
				'label' => __( 'Pending Subscription', 'subscription' ),
				'value' => 'pending',
			),
			'expire'       => array(
				'label' => __( 'Expire Subscription', 'subscription' ),
				'value' => 'expired',
			),
			'pe_cancelled' => array(
				'label' => __( 'Pending Cancel Subscription', 'subscription' ),
				'value' => 'pe_cancelled',
			),
			'cancelled'    => array(
				'label' => __( 'Cancel Subscription', 'subscription' ),
				'value' => 'cancelled',
			),
		);

		$map_actions = array(
			'pending'      => array( 'active', 'cancelled' ),
			'active'       => array( 'pe_cancelled', 'cancelled' ),
			'pe_cancelled' => array( 'active', 'cancelled' ),
			'cancelled'    => array( 'active' ),
			'expired'      => array( 'active', 'cancelled' ),
			'completed'    => array( 'cancelled' ),
		);

		$status  = get_post_status( $subscription_id );
		$actions = $map_actions[ $status ] ?? array();

		// Gather subscription data for the view.
		$subscription_data = Helper::get_subscription_data( $subscription_id );

		$order_id      = $subscription_data['order']['order_id'] ?? get_post_meta( $subscription_id, '_subscrpt_order_id', true );
		$order         = $order_id ? wc_get_order( $order_id ) : null;
		$order_item_id = $subscription_data['order']['order_item_id'] ?? get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
		$order_item    = ( $order && $order_item_id ) ? $order->get_item( $order_item_id ) : null;

		$rows = Subscriptions::get_info_rows( $subscription_id );

		// Related orders.
		$order_histories = Helper::get_related_orders( $subscription_id );

		$this->render_admin_header(
			'',
			'',
			array(
				array(
					'label' => __( 'Subscriptions', 'subscription' ),
					'url'   => $list_url,
				),
				array(
					'label' => '#' . $subscription_id,
					'url'   => '',
				),
			)
		);

		include __DIR__ . '/views/subscription-details.php';

		$this->render_admin_footer();
	}

	/**
	 * Render Stats page
	 */
	public function render_stats_page() {
		$this->render_admin_header( __( 'Reports', 'subscription' ), __( 'View your subscription analytics', 'subscription' ) );

		if ( ! subscrpt_pro_activated() ) {
			include 'views/reports-preview.php';
		} else {
			// Allow pro plugin to override the entire stats page content.
			do_action( 'subscrpt_render_stats_page' );
		}

		$this->render_admin_footer();
	}

	/**
	 * Render Health page
	 */
	public function render_health_page() {
		$this->render_admin_header( __( 'Health', 'subscription' ), __( 'Monitor your subscription health', 'subscription' ) );

		if ( ! subscrpt_pro_activated() ) {
			include 'views/health-preview.php';
		} else {
			// Allow pro plugin to render the full health page content.
			do_action( 'subscrpt_render_health_page' );
		}

		$this->render_admin_footer();
	}

	/**
	 * Render Delivery Schedules page.
	 * When pro is active, fires subscrpt_render_delivery_page for pro to handle.
	 */
	public function render_delivery_page() {
		$this->render_admin_header( __( 'Delivery Schedules', 'subscription' ), __( 'Track and manage subscription delivery schedules.', 'subscription' ) );

		if ( ! subscrpt_pro_activated() ) {
			include 'views/delivery-preview.php';
		} else {
			// Allow pro plugin to render the full delivery page content.
			do_action( 'subscrpt_render_delivery_page' );
		}

		$this->render_admin_footer();
	}

	/**
	 * Render Support page
	 */
	public function render_support_page() {
		$this->render_admin_header( __( 'Help & Resources', 'subscription' ), __( 'Documentation, community links, and ways to get help with WPSubscription.', 'subscription' ) );
		include 'views/support.php';
		$this->render_admin_footer();
	}

	/**
	 * Render Onboarding Wizard page
	 * SPA-style: all sections rendered at once, JS controls visibility
	 * Initial load always shows page 1 (JS handles transitions from there)
	 */
	public function render_onboarding_wizard() {
		// Start session if not already started (used by the wizard reset handler).
		if ( ! session_id() && ! headers_sent() ) {
			session_start();
		}

		$this->render_admin_header( __( 'Setup Wizard', 'subscription' ), __( 'Create your first subscription plan', 'subscription' ) );
		include __DIR__ . '/views/onboarding-wizard.php';
		$this->render_admin_footer();
	}

	/**
	 * Render the legacy subscriptions list page (WP_List_Table based)
	 */
	public function render_legacy_subscriptions_page() {
		// No longer needed, as the menu now links directly to the post type list.
	}

	/**
	 * Handle bulk action AJAX
	 */
	public function handle_bulk_action_ajax() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'subscrpt_bulk_action_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'subscription' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'subscription' ) ) );
		}

		// Get action and subscription IDs
		$bulk_action      = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$subscription_ids = isset( $_POST['subscription_ids'] ) ? array_map( 'intval', $_POST['subscription_ids'] ) : array();

		if ( empty( $subscription_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No subscriptions selected.', 'subscription' ) ) );
		}

		$processed_count = 0;
		$errors          = array();

		foreach ( $subscription_ids as $subscription_id ) {
			$post = get_post( $subscription_id );

			if ( ! $post || $post->post_type !== 'subscrpt_order' ) {
				// translators: Subscription ID.
				$errors[] = sprintf( __( 'Subscription #%d not found.', 'subscription' ), $subscription_id );
				continue;
			}

			try {
				switch ( $bulk_action ) {
					case 'trash':
						if ( wp_trash_post( $subscription_id ) ) {
							++$processed_count;
						} else {
							$errors[] = sprintf(
								// translators: Subscription ID.
								__( 'Failed to move subscription #%d to trash.', 'subscription' ),
								$subscription_id
							);
						}
						break;

					case 'restore':
						if ( wp_untrash_post( $subscription_id ) ) {
							++$processed_count;
						} else {
							$errors[] = sprintf(
								// translators: Subscription ID.
								__( 'Failed to restore subscription #%d.', 'subscription' ),
								$subscription_id
							);
						}
						break;

					case 'delete':
						if ( wp_delete_post( $subscription_id, true ) ) {
							++$processed_count;
						} else {
							$errors[] = sprintf(
								// translators: Subscription ID.
								__( 'Failed to delete subscription #%d.', 'subscription' ),
								$subscription_id
							);
						}
						break;

					default:
						$errors[] = sprintf(
							// translators: Bulk action.
							__( 'Unknown action: %s', 'subscription' ),
							$bulk_action
						);
						break;
				}
			} catch ( Exception $e ) {
				$errors[] = sprintf(
					// translators: Subscription ID, Error message.
					__( 'Error processing subscription #%1$d: %2$s', 'subscription' ),
					$subscription_id,
					$e->getMessage()
				);
			}
		}

		// Prepare response message
		$message = '';
		if ( $processed_count > 0 ) {
			switch ( $bulk_action ) {
				case 'trash':
					$message = sprintf(
						// translators: Number of subscriptions.
						_n( '%d subscription moved to trash.', '%d subscriptions moved to trash.', $processed_count, 'subscription' ),
						$processed_count
					);
					break;
				case 'restore':
					$message = sprintf(
						// translators: Number of subscriptions.
						_n( '%d subscription restored.', '%d subscriptions restored.', $processed_count, 'subscription' ),
						$processed_count
					);
					break;
				case 'delete':
					$message = sprintf(
						// translators: Number of subscriptions.
						_n( '%d subscription permanently deleted.', '%d subscriptions permanently deleted.', $processed_count, 'subscription' ),
						$processed_count
					);
					break;
			}
		}

		if ( ! empty( $errors ) ) {
			$message .= ' ' . __( 'Some errors occurred:', 'subscription' ) . ' ' . implode( ', ', $errors );
		}

		if ( $processed_count > 0 ) {
			wp_send_json_success( array( 'message' => $message ) );
		} else {
			wp_send_json_error( array( 'message' => $message ? $message : __( 'No subscriptions were processed.', 'subscription' ) ) );
		}
	}
}
