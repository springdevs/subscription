<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\Checkout;
use SpringDevs\Subscription\Illuminate\Cron;
use SpringDevs\Subscription\Illuminate\Post;

/**
 * Globally Load Scripts.
 */
class Illuminate
{

	/**
	 * Initialize the Class.
	 */
	public function __construct()
	{
		new Cron();
		new Post();
		new Checkout();
	}
}
