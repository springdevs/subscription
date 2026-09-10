<?php
/**
 * Frontend bootstrap.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\ActionController;
use SpringDevs\Subscription\Frontend\Cart;
use SpringDevs\Subscription\Frontend\Downloadable;
use SpringDevs\Subscription\Frontend\MyAccount;
use SpringDevs\Subscription\Frontend\Order as FrontendOrder;
use SpringDevs\Subscription\Frontend\Plans;
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
		// Pro ships a superset storefront plan UI (multi-plan selector, per-variation
		// swap) on the same hooks, so free's single-line display runs only when Pro is
		// absent — otherwise the two would double-render.
		if ( ! subscrpt_pro_activated() ) {
			new Plans();
		}
		new Cart();
		new FrontendOrder();
		new ActionController();
		new MyAccount();
		new Downloadable();
	}
}
