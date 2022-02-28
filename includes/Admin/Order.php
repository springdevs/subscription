<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Order class
 * @package SpringDevs\Subscription\Admin
 */
class Order
{
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
    }

    public function add_meta_boxes()
    {
        $order_id = get_the_ID();
        $order_meta = get_post_meta($order_id, "_order_subscrpt_data", true);
        if (!empty($order_meta) && is_array($order_meta) && isset($order_meta['status'])) {
            add_meta_box(
                'subscrpt_order_related',
                __('Related Subscriptions', 'sdevs_subscrpt'),
                [$this, 'subscrpt_order_related'],
                'shop_order',
                'normal',
                'default'
            );
        }
    }

    public function subscrpt_order_related()
    {
        $order_id = get_the_ID();
        $order_meta = get_post_meta($order_id, "_order_subscrpt_data", true);
        if (empty($order_meta) && !is_array($order_meta) && !isset($order_meta['status'])) return;

        include 'views/related-subscriptions.php';
    }
}
