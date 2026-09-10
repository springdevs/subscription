<?php
/**
 * WooCommerce dependency notice.
 *
 * @package SpringDevs\Subscription
 */

/*
STYLE GUIDE FOR WP SUBSCRIPTION ADMIN PAGES:
- Use .wp-subscription-admin-content for main content area.
- Use .wp-subscription-admin-box for white card/box with shadow and 6-8px border-radius.
- Use compact, modern, visually unified design for all sections.
- Use Georgia, serif for titles, system sans-serif for body.
- All new UI/UX changes must follow these conventions.
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="notice notice-error sdevs-install-plugin">
	<div class="sdevs-notice-icon">
		<img src="<?php echo esc_url( SUBSCRPT_ASSETS . '/images/logo.png' ); ?>" alt="woocommerce-logo" />
	</div>
	<div class="sdevs-notice-content">
		<h2><?php esc_html_e( 'Thanks for installing WPSubscription.', 'subscription' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: WooCommerce, linked to its wordpress.org page. */
				esc_html__( '%s must be installed and activated before WPSubscription can be used.', 'subscription' ),
				'<a href="https://wordpress.org/plugins/woocommerce/" target="_blank" rel="noopener">' . esc_html__( 'WooCommerce', 'subscription' ) . '</a>'
			);
			?>
		</p>
	</div>
	<div class="sdevs-install-notice-button">
		<a class="button-primary <?php echo esc_attr( $id ); ?>" href="javascript:void(0);"><svg xmlns="http://www.w3.org/2000/svg" class="sdevs-loading-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
			</svg> <?php echo esc_html( $label ); ?></a>
	</div>
</div>
