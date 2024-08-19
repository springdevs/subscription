<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\ActionController;
use SpringDevs\Subscription\Frontend\Cart;
use SpringDevs\Subscription\Frontend\Downloadable;
use SpringDevs\Subscription\Frontend\MyAccount;
use SpringDevs\Subscription\Frontend\Order as FrontendOrder;
use SpringDevs\Subscription\Frontend\Product;

/**
 * Frontend handler class
 */
class Frontend {

	/**
	 * Frontend constructor.
	 */
	public function __construct() {
		new Product();
		new Cart();
		new FrontendOrder();
		new ActionController();
		new MyAccount();
		new Downloadable();
	}
}
