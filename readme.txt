=== Subscription for WooCommerce ===
Contributors: naminbd, ok9xnirab
Donate link:
Tags: woocommerce, subscription, woocommerce subscription
Requires at least: 4.0
Tested up to: 6.6
Stable tag: trunk
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allow your customers to order once and get their products and services every month/week.

== Description ==

A powerfull plugin that allow to enable subscription on woocommerce products.

## Features

### Free

1. Simple product support
2. Free trial
3. BIlling cycle ( daily, weekly, monthly, yearly)
4. Customer Emails
5. admin subscription management area
6. Subscription list and subscription management tools for customers

### Premium

1. Simple & Variable Product Subscriptions
2. Stripe Auto-renewal support
3. advanced subscriptional product customizations
4. Support sign-up fees

== Installation ==

    = Installation from within WordPress =

        1. Visit 'Plugins > 'Add New'.
        2. Search for 'WooCommerce Subscription'.
        3. Install and activate the 'WooCommerce Subscription' plugin.

    = Manual installation =

        1. Upload the entire `WooCommerce Subscription` folder to the `/wp-content/plugins/` directory.
        2. Visit 'Plugins'.
        3. Activate the `WooCommerce Subscription` plugin.

== Frequently Asked Questions ==
=How to overwrite the frontend templates ?=
Just copy `myaccount` directory which is located in the `templates` folder & paste it to `yourtheme/subscription/`.
=Is it compatible with all WordPress themes ?=
Compatibility with all themes is impossible, because there are too many, but generally if themes are developed according to WordPress and WooCommerce guidelines, **Subscription for WooCommerce** is compatible with them.
Sometimes, especially when new versions are released, it might only require some time for them to be all updated, but you can be sure that they will be tested and will be working in a few days.
=Can I create subscriptions from the backend ?=
**No**, Currently this feature is not available.
=Is it possible to set a minimum subscription time ?=
Customer can set status to **Pending cancellation**, which subscription will be cancelled automatically when the period end. This feature only work when subscribed product's user-cancell option is set to **yes**.


== Screenshots ==

1. Create product with subscription
2. Subscription product view
3. Cart page
4. Cart page [ block ]
5. Mini Cart [ block ]
6. Checkout Page
7. Checkout Page [ block ]
8. Thank you Page
9. Manage wooCommerce order from user
10. My Account subscription lists
11. Manage subscription from user
12. Subscription lists from wp-admin
13. Manage subscription from wp-admin
14. Subscription settings
15. Manage wooCommerce order from admin


== Changelog ==

= 1.3 =
- **New**: Stripe renewal added.
- **New**: Trial feature added.
- **New**: Subscription limit added.
- **Fix**: Cancel by customer.
- **Update**: Improve user experience and bug fixing!

= 1.2.1 =
- **Fix**: pagination bug on `subscriptions` template.
- **Fix**: Variable product exists on cart when pro plugin is deactivated!
- **Fix**: Display '1' inside cart-price after recurring type.
- **Update**: Improve order's **Related Subscriptions** description and status.

= 1.2 =
- **New**: Block pages support added.
- **Update**: Rebuild the plugin for better long term support.

= 1.1.4 =
- **Update:** Subscription status will be `pending` when order status is `processing`.
- **New**: `subscript_order_status_to_post_status` hook added to filter post status during order status changed event.

= 1.1.3 =
- **New**: Subscription storeAPI checkout support added.

= 1.1.2 = 
- **Fix:** Handle order deletion.
- **Update:** WP timezone setting support added.
- **New:** Compatible with pro version.

= 1.1.1 =
- **Fix:** Displaying `/1{type}` inside product details.

= 1.1 =
- **Update:** We rebuild our plugin from scratch to provide better & long terms supports
- **Fix:** Severals UI & compatibility issues

= 1.0.4 =
- **New:** Display color based subscription status
- **New:** Add required plugin installer
- **Fix:** Subscription customer box overflow issue
- Plugin action links added

= 1.0.3 =
- **Update:** Code clean-up
- **Fix:** Some minor issues

= 1.0.2 =
- **Update:** Change plugin name
- **Update:** Did some Code refactoring
- **Fix:** WPCodingStandard related issues using `phpcbf`

= 1.0.1 =
- **Fix:** "total" amount not display in "My Subscription's"

= 1.0.0 =
- **New:** Initial release
