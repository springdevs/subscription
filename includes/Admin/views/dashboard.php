<?php
/**
 * Dashboard container.
 *
 * The screen is rendered by src/dashboard/ into this node. Everything it needs
 * is preloaded on `window.subscrptDashboard` by Admin\Dashboard::enqueue().
 *
 * The fallback below is what a visitor sees if the bundle fails to load — a
 * blank page with no explanation is the worst outcome for a landing screen.
 *
 * @package SpringDevs\Subscription\Admin
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wp-subscription-admin-content list-page">
	<div id="subscrpt-dashboard" class="subscrpt-dashboard">
		<noscript>
			<p><?php esc_html_e( 'The dashboard needs JavaScript. Use the menu on the left to reach your subscriptions.', 'subscription' ); ?></p>
		</noscript>
	</div>
</div>
