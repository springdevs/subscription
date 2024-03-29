<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\ActionController;
use SpringDevs\Subscription\Frontend\Downloadable;
use SpringDevs\Subscription\Frontend\MyAccount;
use SpringDevs\Subscription\Frontend\Order as FrontendOrder;
use SpringDevs\Subscription\Frontend\Product;
use SpringDevs\Subscription\Illuminate\Cron;
use SpringDevs\Subscription\Illuminate\Email;
use SpringDevs\Subscription\Illuminate\Order;

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
		new Illuminate();
		new Product();
		new FrontendOrder();
		new ActionController();
		new MyAccount();
		new Downloadable();
		new Order();
		new Email();
	}
}
