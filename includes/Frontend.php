<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\ActionController;
use SpringDevs\Subscription\Frontend\Downloadable;
use SpringDevs\Subscription\Frontend\MyAccount;
use SpringDevs\Subscription\Frontend\Product;
use SpringDevs\Subscription\Frontend\Thankyou;
use SpringDevs\Subscription\Illuminate\AutoRenewal;
use SpringDevs\Subscription\Illuminate\Cron;
use SpringDevs\Subscription\Illuminate\Email;
use SpringDevs\Subscription\Illuminate\Order;
use SpringDevs\Subscription\Illuminate\RegisterPostStatus;
use SpringDevs\Subscription\Illuminate\Subscriptions;

/**
 * Frontend handler class
 */
class Frontend
{
    /**
     * Frontend constructor.
     */
    public function __construct()
    {
        new Subscriptions;
        new Cron;
        new RegisterPostStatus;
        new Product;
        new Thankyou;
        new ActionController;
        new MyAccount;
        new Downloadable;
        new Order;
        new Email;
        new AutoRenewal;
    }
}
