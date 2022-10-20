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
use SpringDevs\Subscription\Illuminate\Cron;
use SpringDevs\Subscription\Illuminate\Email;
use SpringDevs\Subscription\Illuminate\Order;

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
		new Illuminate();
		new Cron();
		new Menu();
		new Product();
		new Subscriptions();
		new Order();
		new AdminOrder();
		new Comments();
		new Email();
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
