<?php
/**
 * Subscription product edit form view (classic-only panel).
 *
 * Used for the classic Subscription tab where no plan view is mounted. The
 * simple-product plan view (Admin/Product/Plans) renders its own panel and
 * reuses the shared classic fields partial for its hidden "classic settings".
 *
 * @package SpringDevs\Subscription\Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="sdevs_subscription_options"
	class="panel woocommerce_options_panel option_group sdevs-form sdevs_panel show_if_simple" style="padding: 10px;">
	<div class="show_if_subscription">
		<?php require __DIR__ . '/product-classic-fields.php'; ?>
	</div>
</div>
