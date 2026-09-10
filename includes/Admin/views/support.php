<?php
/**
 * Support Admin Page
 *
 * @package SpringDevs\Subscription\Admin
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$other_plugins = array(
	array(
		'name'     => 'Paddle for WooCommerce',
		'tagline'  => 'Payments & checkout',
		'desc'     => 'Looking to boost your sales of themes, plugins, or designs with the power of WooCommerce? Streamline your checkout process with Paddle’s WooCommerce integration.',
		'url'      => 'https://wpsmartpay.com/paddle-for-woocommerce/',
		'cta'      => __( 'Learn more', 'subscription' ),
		'icon_url' => SUBSCRPT_ASSETS . '/images/other_plugins/paddle.png',
	),
	array(
		'name'     => 'WPSmartpay',
		'tagline'  => 'Payments & checkout',
		'desc'     => 'Sell Digital Products & Accept Payment with WordPress. WPSmartPay is the perfect plugin for selling digital products, accepting one-time and recurring payments on your WordPress site without setting up a shopping cart.',
		'url'      => 'https://wpsmartpay.com/features/',
		'cta'      => __( 'Learn more', 'subscription' ),
		'icon_url' => SUBSCRPT_ASSETS . '/images/other_plugins/wpsmartpay.png',
	),
	array(
		'name'     => 'Thrivedesk',
		'tagline'  => 'Agentic Helpdesk',
		'desc'     => 'ThriveDesk unifies email, live chat, and knowledge base into one AI-powered helpdesk trained on your own data to handle repetitive tickets automatically, so your team gets back to building the business.',
		'url'      => 'https://www.thrivedesk.com/',
		'cta'      => __( 'Learn more', 'subscription' ),
		'icon_url' => SUBSCRPT_ASSETS . '/images/other_plugins/thrivedesk.png',
	),
);

?>


<div class="wp-subscription-admin-content list-page">

	<!-- Page header -->
	<div style="margin-bottom:24px;">
		<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0 0 6px;line-height:1.2;"><?php esc_html_e( 'Help & Resources', 'subscription' ); ?></h1>
		<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0 0 12px;line-height:1.5;"><?php esc_html_e( 'Documentation, community links, and ways to get help with WPSubscription.', 'subscription' ); ?></p>
		<div style="border-top:1px dashed #d0d3d7;"></div>
	</div>

	<?php if ( ! class_exists( 'Sdevs_Wc_Subscription_Pro' ) ) : ?>
	<!-- Upgrade banner -->
	<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:16px 20px;margin-bottom:24px;">
		<div>
			<div style="font-size:14px;font-weight:700;color:var(--wpsubs-text);margin-bottom:4px;"><?php esc_html_e( 'Unlock the full power of WPSubscription', 'subscription' ); ?></div>
			<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.5;"><?php esc_html_e( 'Upgrade to Pro for advanced features, more payment gateways, third-party integrations, and priority support.', 'subscription' ); ?></p>
		</div>
		<a href="https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro" target="_blank" rel="noopener noreferrer" class="wpsubs-btn wpsubs-btn--primary" style="white-space:nowrap;flex-shrink:0;"><?php esc_html_e( 'Upgrade to Pro', 'subscription' ); ?></a>
	</div>
	<?php endif; ?>

	<!-- Community -->
	<div style="margin-bottom:32px;">
		<div style="margin-bottom:12px;">
			<h2 style="font-size:12px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.06em;margin:0;line-height:1.4;margin-left:1px;"><?php esc_html_e( 'Community', 'subscription' ); ?></h2>
		</div>
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">

			<div class="wpsubs-table-card" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
				<div style="display:flex;align-items:center;gap:12px;">
					<div style="width:44px;height:44px;flex-shrink:0;border-radius:12px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><polyline points="9 9 12 9 12 13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
					</div>
					<div>
						<div style="font-size:14px;font-weight:700;color:var(--wpsubs-text);line-height:1.2;"><?php esc_html_e( 'Support Forum', 'subscription' ); ?></div>
						<div style="font-size:11px;color:#0369a1;font-weight:500;margin-top:2px;"><?php esc_html_e( 'wordpress.org/support', 'subscription' ); ?></div>
					</div>
				</div>
				<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.6;flex:1;"><?php esc_html_e( 'Browse existing threads or post a new question in the free WPSubscription support forum on WordPress.org.', 'subscription' ); ?></p>
				<div style="border-top:1px solid var(--wpsubs-border);margin:0 -20px;"></div>
				<a href="https://wordpress.org/support/plugin/subscription/" target="_blank" rel="noopener noreferrer" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" style="align-self:flex-start;">
					<?php esc_html_e( 'Visit Forum', 'subscription' ); ?>
					<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
				</a>
			</div>

			<div class="wpsubs-table-card" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
				<div style="display:flex;align-items:center;gap:12px;">
					<div style="width:44px;height:44px;flex-shrink:0;border-radius:12px;background:#fef3c7;display:flex;align-items:center;justify-content:center;">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#92400e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
					</div>
					<div>
						<div style="font-size:14px;font-weight:700;color:var(--wpsubs-text);line-height:1.2;"><?php esc_html_e( 'Request a Feature', 'subscription' ); ?></div>
						<div style="font-size:11px;color:#92400e;font-weight:500;margin-top:2px;"><?php esc_html_e( 'Share your ideas', 'subscription' ); ?></div>
					</div>
				</div>
				<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.6;flex:1;"><?php esc_html_e( 'Have a feature idea? We read every request. Your feedback directly shapes the plugin roadmap.', 'subscription' ); ?></p>
				<div style="border-top:1px solid var(--wpsubs-border);margin:0 -20px;"></div>
				<a href="https://wpsubscription.co/contact?utm_source=plugin&utm_medium=admin&utm_campaign=support" target="_blank" rel="noopener noreferrer" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" style="align-self:flex-start;">
					<?php esc_html_e( 'Send a Request', 'subscription' ); ?>
					<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
				</a>
			</div>

			<div class="wpsubs-table-card" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
				<div style="display:flex;align-items:center;gap:12px;">
					<div style="width:44px;height:44px;flex-shrink:0;border-radius:12px;background:#fce7f3;display:flex;align-items:center;justify-content:center;">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#9d174d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
					</div>
					<div>
						<div style="font-size:14px;font-weight:700;color:var(--wpsubs-text);line-height:1.2;"><?php esc_html_e( 'Leave a Review', 'subscription' ); ?></div>
						<div style="font-size:11px;color:#9d174d;font-weight:500;margin-top:2px;"><?php esc_html_e( 'wordpress.org/plugins', 'subscription' ); ?></div>
					</div>
				</div>
				<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.6;flex:1;"><?php esc_html_e( 'Enjoying WPSubscription? A short review on WordPress.org helps others discover the plugin and means a lot to our small team.', 'subscription' ); ?></p>
				<div style="border-top:1px solid var(--wpsubs-border);margin:0 -20px;"></div>
				<a href="https://wordpress.org/support/plugin/subscription/reviews/" target="_blank" rel="noopener noreferrer" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" style="align-self:flex-start;">
					<?php esc_html_e( 'Write a Review', 'subscription' ); ?>
					<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
				</a>
			</div>

			<div class="wpsubs-table-card" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
				<div style="display:flex;align-items:center;gap:12px;">
					<div style="width:44px;height:44px;flex-shrink:0;border-radius:12px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/></svg>
					</div>
					<div>
						<div style="font-size:14px;font-weight:700;color:var(--wpsubs-text);line-height:1.2;"><?php esc_html_e( 'Changelog', 'subscription' ); ?></div>
						<div style="font-size:11px;color:#475569;font-weight:500;margin-top:2px;"><?php esc_html_e( 'wordpress.org/plugins', 'subscription' ); ?></div>
					</div>
				</div>
				<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.6;flex:1;"><?php esc_html_e( 'See what\'s new in each release — bug fixes, new features, and improvements listed on the plugin page.', 'subscription' ); ?></p>
				<div style="border-top:1px solid var(--wpsubs-border);margin:0 -20px;"></div>
				<a href="https://wordpress.org/plugins/subscription/#developers" target="_blank" rel="noopener noreferrer" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" style="align-self:flex-start;">
					<?php esc_html_e( 'View Changelog', 'subscription' ); ?>
					<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
				</a>
			</div>

		</div>
	</div>

	<!-- Resources -->
	<div style="margin-bottom:32px;">
		<div style="margin-bottom:12px;">
			<h2 style="font-size:12px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.06em;margin:0;line-height:1.4;margin-left:1px;"><?php esc_html_e( 'Resources', 'subscription' ); ?></h2>
		</div>
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">

			<div class="wpsubs-table-card" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
				<div style="display:flex;align-items:center;gap:12px;">
					<div style="width:44px;height:44px;flex-shrink:0;border-radius:12px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
					</div>
					<div>
						<div style="font-size:14px;font-weight:700;color:var(--wpsubs-text);line-height:1.2;"><?php esc_html_e( 'Documentation', 'subscription' ); ?></div>
						<div style="font-size:11px;color:#0369a1;font-weight:500;margin-top:2px;"><?php esc_html_e( 'docs.wpsubscription.co', 'subscription' ); ?></div>
					</div>
				</div>
				<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.6;flex:1;"><?php esc_html_e( 'Step-by-step setup guides, configuration references, shortcode docs, and migration instructions.', 'subscription' ); ?></p>
				<div style="border-top:1px solid var(--wpsubs-border);margin:0 -20px;"></div>
				<a href="https://docs.wpsubscription.co/en?utm_source=plugin&utm_medium=admin&utm_campaign=docs" target="_blank" rel="noopener noreferrer" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" style="align-self:flex-start;">
					<?php esc_html_e( 'Browse Docs', 'subscription' ); ?>
					<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
				</a>
			</div>

			<div class="wpsubs-table-card" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
				<div style="display:flex;align-items:center;gap:12px;">
					<div style="width:44px;height:44px;flex-shrink:0;border-radius:12px;background:#ede9fe;display:flex;align-items:center;justify-content:center;">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6d28d9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
					</div>
					<div>
						<div style="font-size:14px;font-weight:700;color:var(--wpsubs-text);line-height:1.2;"><?php esc_html_e( 'Payment Gateways', 'subscription' ); ?></div>
						<div style="font-size:11px;color:#6d28d9;font-weight:500;margin-top:2px;"><?php esc_html_e( 'docs.wpsubscription.co', 'subscription' ); ?></div>
					</div>
				</div>
				<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.6;flex:1;"><?php esc_html_e( 'Setup guides for Stripe, PayPal, Mollie, Razorpay, Xendit, and other supported payment gateways.', 'subscription' ); ?></p>
				<div style="border-top:1px solid var(--wpsubs-border);margin:0 -20px;"></div>
				<a href="https://docs.wpsubscription.co/en/category/payments?utm_source=plugin&utm_medium=admin&utm_campaign=docs" target="_blank" rel="noopener noreferrer" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" style="align-self:flex-start;">
					<?php esc_html_e( 'View Guides', 'subscription' ); ?>
					<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
				</a>
			</div>

			<div class="wpsubs-table-card" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
				<div style="display:flex;align-items:center;gap:12px;">
					<div style="width:44px;height:44px;flex-shrink:0;border-radius:12px;background:#fce7f3;display:flex;align-items:center;justify-content:center;">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#9d174d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
					</div>
					<div>
						<div style="font-size:14px;font-weight:700;color:var(--wpsubs-text);line-height:1.2;"><?php esc_html_e( '3rd Party Integrations', 'subscription' ); ?></div>
						<div style="font-size:11px;color:#9d174d;font-weight:500;margin-top:2px;"><?php esc_html_e( 'docs.wpsubscription.co', 'subscription' ); ?></div>
					</div>
				</div>
				<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.6;flex:1;"><?php esc_html_e( 'Integration guides for LMS, CRM, automation, email marketing, and license management plugins.', 'subscription' ); ?></p>
				<div style="border-top:1px solid var(--wpsubs-border);margin:0 -20px;"></div>
				<a href="https://docs.wpsubscription.co/en/category/integrations?utm_source=plugin&utm_medium=admin&utm_campaign=docs" target="_blank" rel="noopener noreferrer" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" style="align-self:flex-start;">
					<?php esc_html_e( 'View Guides', 'subscription' ); ?>
					<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
				</a>
			</div>

			<div class="wpsubs-table-card" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
				<div style="display:flex;align-items:center;gap:12px;">
					<div style="width:44px;height:44px;flex-shrink:0;border-radius:12px;background:#dcfce7;display:flex;align-items:center;justify-content:center;">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
					</div>
					<div>
						<div style="font-size:14px;font-weight:700;color:var(--wpsubs-text);line-height:1.2;"><?php esc_html_e( 'Get Support', 'subscription' ); ?></div>
						<div style="font-size:11px;color:#166534;font-weight:500;margin-top:2px;"><?php esc_html_e( 'wpsubscription.co/contact', 'subscription' ); ?></div>
					</div>
				</div>
				<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.6;flex:1;"><?php esc_html_e( 'Open a support ticket and our team will respond within 48 hours. Please include your site URL and a description of the issue.', 'subscription' ); ?></p>
				<div style="border-top:1px solid var(--wpsubs-border);margin:0 -20px;"></div>
				<a href="https://wpsubscription.co/contact?utm_source=plugin&utm_medium=admin&utm_campaign=support" target="_blank" rel="noopener noreferrer" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" style="align-self:flex-start;">
					<?php esc_html_e( 'Open a Ticket', 'subscription' ); ?>
					<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
				</a>
			</div>

		</div>
	</div>

	<!-- Our Other Plugins -->
	<div style="margin-bottom:32px;">
		<div style="margin-bottom:12px;">
			<h2 style="font-size:12px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.06em;margin:0;line-height:1.4;margin-left:1px;"><?php esc_html_e( 'Our Other Products', 'subscription' ); ?></h2>
		</div>
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
			<?php foreach ( $other_plugins as $other_plugin ) : ?>
				<div class="wpsubs-table-card" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
					<div style="display:flex;align-items:center;gap:12px;">
						<img src="<?php echo esc_url( $other_plugin['icon_url'] ); ?>" alt="" width="44" height="44" style="flex-shrink:0;border-radius:12px;object-fit:cover;" />
						<div>
							<div style="font-size:14px;font-weight:700;color:var(--wpsubs-text);line-height:1.2;"><?php echo esc_html( $other_plugin['name'] ); ?></div>
							<div style="font-size:11px;color:var(--wpsubs-text-muted);font-weight:500;margin-top:2px;"><?php echo esc_html( $other_plugin['tagline'] ); ?></div>
						</div>
					</div>
					<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.6;flex:1;"><?php echo esc_html( $other_plugin['desc'] ); ?></p>
					<div style="border-top:1px solid var(--wpsubs-border);margin:0 -20px;"></div>
					<a href="<?php echo esc_url( $other_plugin['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" style="align-self:flex-start;">
						<?php echo esc_html( $other_plugin['cta'] ); ?>
						<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

</div>
