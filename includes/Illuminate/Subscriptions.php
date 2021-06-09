<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Subscriptions
 * @package SpringDevs\Subscription\Illuminate
 */
class Subscriptions
{
    public function __construct()
    {
        add_action("init", [$this, "create_post_type"]);
    }

    /**
     *  Create Custom Post Type : subscrpt_order
     */
    public function create_post_type()
    {
        $labels = array(
            "name" => __("Subscriptions", "sdevs_subscrpt"),
            "singular_name" => __("Subscription", "sdevs_subscrpt"),
            'name_admin_bar'        => __('Subscription\'s', 'sdevs_subscrpt'),
            'archives'              => __('Item Archives', 'sdevs_subscrpt'),
            'attributes'            => __('Item Attributes', 'sdevs_subscrpt'),
            'parent_item_colon'     => __('Parent :', 'sdevs_subscrpt'),
            'all_items'             => __('Subscriptions', 'sdevs_subscrpt'),
            'add_new_item'          => __('Add New Subscription', 'sdevs_subscrpt'),
            'add_new'               => __('Add Subscription', 'sdevs_subscrpt'),
            'new_item'              => __('New Subscription', 'sdevs_subscrpt'),
            'edit_item'             => __('Edit Subscription', 'sdevs_subscrpt'),
            'update_item'           => __('Update Subscription', 'sdevs_subscrpt'),
            'view_item'             => __('View Subscription', 'sdevs_subscrpt'),
            'view_items'            => __('View Subscription', 'sdevs_subscrpt'),
            'search_items'          => __('Search Subscription', 'sdevs_subscrpt'),
        );

        $args = array(
            "label" => __("Subscriptions", "sdevs_subscrpt"),
            "labels" => $labels,
            "description" => "",
            "public" => false,
            "publicly_queryable" => true,
            "show_ui" => true,
            "delete_with_user" => false,
            "show_in_rest" => true,
            "rest_base" => "",
            "rest_controller_class" => "WP_REST_Posts_Controller",
            "has_archive" => false,
            "show_in_menu" => false,
            "show_in_nav_menus" => false,
            "exclude_from_search" => false,
            "capability_type" => "post",
            "map_meta_cap" => true,
            'capabilities' => array(
                'create_posts' => false
            ),
            "hierarchical" => false,
            "rewrite" => array("slug" => "subscrpt_order", "with_front" => true),
            "query_var" => true,
            "supports" => array("title"),
        );

        register_post_type("subscrpt_order", $args);
    }
}
