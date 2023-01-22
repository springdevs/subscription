<?php

namespace SpringDevs\Subscription\Illuminate;

class Post {

	public function __construct() {
		add_action( 'init', array( $this, 'create_post_type' ) );
	}

	public function create_post_type() {
		$this->register_subscription_post_type();
		$this->register_subscription_item_post_type();
		$this->register_post_status();
	}


	/**
	 * Register ``subscrpt_order`` post type
	 */
	public function register_subscription_post_type() {
		$labels = array(
			'name'              => __( 'Subscriptions', 'sdevs_subscrpt' ),
			'singular_name'     => __( 'Subscription', 'sdevs_subscrpt' ),
			'name_admin_bar'    => __( 'Subscription\'s', 'sdevs_subscrpt' ),
			'archives'          => __( 'Item Archives', 'sdevs_subscrpt' ),
			'attributes'        => __( 'Item Attributes', 'sdevs_subscrpt' ),
			'parent_item_colon' => __( 'Parent :', 'sdevs_subscrpt' ),
			'all_items'         => __( 'Subscriptions', 'sdevs_subscrpt' ),
			'add_new_item'      => __( 'Add New Subscription', 'sdevs_subscrpt' ),
			'add_new'           => __( 'Add Subscription', 'sdevs_subscrpt' ),
			'new_item'          => __( 'New Subscription', 'sdevs_subscrpt' ),
			'edit_item'         => __( 'Edit Subscription', 'sdevs_subscrpt' ),
			'update_item'       => __( 'Update Subscription', 'sdevs_subscrpt' ),
			'view_item'         => __( 'View Subscription', 'sdevs_subscrpt' ),
			'view_items'        => __( 'View Subscription', 'sdevs_subscrpt' ),
			'search_items'      => __( 'Search Subscription', 'sdevs_subscrpt' ),
		);

		$args = array(
			'label'                 => __( 'Subscriptions', 'sdevs_subscrpt' ),
			'labels'                => $labels,
			'description'           => '',
			'public'                => false,
			'publicly_queryable'    => false,
			'show_ui'               => true,
			'delete_with_user'      => false,
			'show_in_rest'          => true,
			'rest_base'             => '',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'has_archive'           => false,
			'show_in_menu'          => false,
			'show_in_nav_menus'     => false,
			'exclude_from_search'   => false,
			'capability_type'       => 'post',
			'map_meta_cap'          => true,
			'capabilities'          => array(
				'create_posts' => false,
			),
			'hierarchical'          => false,
			'rewrite'               => array(
				'slug'       => 'subscrpt_order',
				'with_front' => true,
			),
			'query_var'             => true,
			'supports'              => false,
		);

		$args = apply_filters( 'subscrpt_order_post_args', $args );

		register_post_type( 'subscrpt_order', $args );
	}

	/**
	 * Register ``subscrpt_order_item`` post type
	 */
	public function register_subscription_item_post_type() {
		$args = array(
			'label'                 => __( 'Subscription Items', 'sdevs_subscrpt' ),
			// 'labels'                => ,
			'description'           => '',
			'public'                => false,
			'publicly_queryable'    => false,
			'show_ui'               => false,
			'delete_with_user'      => false,
			'show_in_rest'          => true,
			'rest_base'             => '',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'has_archive'           => false,
			'show_in_menu'          => false,
			'show_in_nav_menus'     => false,
			'exclude_from_search'   => false,
			'capability_type'       => 'post',
			'map_meta_cap'          => true,
			'capabilities'          => array(
				'create_posts' => false,
			),
			'hierarchical'          => false,
			'rewrite'               => array(
				'slug'       => 'subscription_item',
				'with_front' => false,
			),
			'query_var'             => true,
			'supports'              => false,
		);

		$args = apply_filters( 'subscrpt_order_item_post_args', $args );

		register_post_type( 'subscrpt_order_item', $args );
	}

	public function register_post_status() {
		register_post_status(
			'pending',
			array(
				'label'                     => _x( 'Pending', 'post status label', 'sdevs_subscrpt' ),
				'public'                    => true,
				'label_count'               => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'sdevs_subscrpt' ),
				'post_type'                 => array( 'subscrpt_order' ),
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'show_in_metabox_dropdown'  => true,
				'show_in_inline_dropdown'   => true,
				'dashicon'                  => '',
			)
		);

		register_post_status(
			'active',
			array(
				'label'                     => _x( 'Active', 'post status label', 'sdevs_subscrpt' ),
				'public'                    => true,
				'label_count'               => _n_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'sdevs_subscrpt' ),
				'post_type'                 => array( 'subscrpt_order' ),
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'show_in_metabox_dropdown'  => true,
				'show_in_inline_dropdown'   => true,
				'dashicon'                  => '',
			)
		);

		register_post_status(
			'on_hold',
			array(
				'label'                     => _x( 'On Hold', 'post status label', 'sdevs_subscrpt' ),
				'public'                    => true,
				'label_count'               => _n_noop( 'On Hold <span class="count">(%s)</span>', 'On Hold <span class="count">(%s)</span>', 'sdevs_subscrpt' ),
				'post_type'                 => array( 'subscrpt_order' ),
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'show_in_metabox_dropdown'  => true,
				'show_in_inline_dropdown'   => true,
				'dashicon'                  => '',
			)
		);

		register_post_status(
			'cancelled',
			array(
				'label'                     => _x( 'Cancelled', 'post status label', 'sdevs_subscrpt' ),
				'public'                    => true,
				'label_count'               => _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'sdevs_subscrpt' ),
				'post_type'                 => array( 'subscrpt_order' ),
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'show_in_metabox_dropdown'  => true,
				'show_in_inline_dropdown'   => true,
				'dashicon'                  => '',
			)
		);

		register_post_status(
			'expired',
			array(
				'label'                     => _x( 'Expired', 'post status label', 'sdevs_subscrpt' ),
				'public'                    => true,
				'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'sdevs_subscrpt' ),
				'post_type'                 => array( 'subscrpt_order' ),
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'show_in_metabox_dropdown'  => true,
				'show_in_inline_dropdown'   => true,
				'dashicon'                  => '',
			)
		);

		register_post_status(
			'pe_cancelled',
			array(
				'label'                     => _x( 'Pending Cancellation', 'post status label', 'sdevs_subscrpt' ),
				'public'                    => true,
				'label_count'               => _n_noop( 'Pending Cancellation <span class="count">(%s)</span>', 'Pending Cancellation <span class="count">(%s)</span>', 'sdevs_subscrpt' ),
				'post_type'                 => array( 'subscrpt_order' ),
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'show_in_metabox_dropdown'  => true,
				'show_in_inline_dropdown'   => true,
				'dashicon'                  => '',
			)
		);
	}
}
