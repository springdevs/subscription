<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\Checkout;
use SpringDevs\Subscription\Illuminate\Post;

class Illuminate
{
    public function __construct() {
        new Post();
        new Checkout();
    }
}
