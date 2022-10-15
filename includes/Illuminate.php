<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\Checkout;
use SpringDevs\Subscription\Illuminate\Post;
use SpringDevs\Subscription\Illuminate\Helper;

class Illuminate
{
    public function __construct() {
        new Post();
        new Checkout();

        // add_action('init', function(){
        //     global $wpdb;
        //     $subscription_meta_query = "SELECT * FROM ".$wpdb->prefix."postmeta WHERE meta_key='_subscrpt_order_history'";
        //     $subscriptions_meta = $wpdb->get_results($subscription_meta_query);
            
        //     dd(unserialize($subscriptions_meta[0]->meta_value));
        //     exit;
        // });
    }
}
