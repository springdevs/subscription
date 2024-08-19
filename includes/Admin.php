<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Admin\Required;
use SpringDevs\Subscription\Admin\Links;
use SpringDevs\Subscription\Admin\Menu;
use SpringDevs\Subscription\Admin\Order as AdminOrder;
use SpringDevs\Subscription\Admin\Product;
use SpringDevs\Subscription\Admin\Settings;
use SpringDevs\Subscription\Admin\Subscriptions;
use SpringDevs\Subscription\Illuminate\Comments;

/**
 * The admin class
 */
class Admin {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		$this->dispatch_actions();
		new Required();
		new Menu();
		new Product();
		new Subscriptions();
		new AdminOrder();
		new Comments();
		new Settings();
		new Links();
	}

	/**
	 * Dispatch and bind actions
	 *
	 * @return void
	 */
	public function dispatch_actions() {
	}
}
