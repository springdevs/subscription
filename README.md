# Subscription

Allow your customers to order once and get their products and services every month/week.

Install dependencies

```bash
composer install
```

Minimize files for Production

```bash
bash clean.sh
```

## changelog

### v1.2.1

- **Fix**: pagination bug on `subscriptions` template.
- **Fix**: Variable product exists on cart when pro plugin is deactivated!
- **Fix**: Display '1' inside cart-price after recurring type.
- **Update**: Improve order's **Related Subscriptions** description and status.

### v1.2

- **New**: Block pages support added.
- **Update**: Rebuild the plugin for better long term support.

### v1.1.4

- **Update:** Subscription status will be `pending` when order status is `processing`.
- **New**: `subscript_order_status_to_post_status` hook added to filter post status during order status changed event.

### v1.1.3

- **New**: Subscription storeAPI checkout support added.

### v1.1.2

- **Fix:** Handle order deletion.
- **Update:** WP timezone setting support added.
- **New:** Compatible with pro version.

### v1.1.1

- **Fix:** Displaying `/1{type}` inside product details.

### v1.1

- We rebuild our plugin from scratch to provide better & long terms supports
- Severals UI & compatibility issues solved

### v1.0.4

- Display color based subscription status
- Add required plugin installer
- Fix subscription customer box overflow issue
- Plugin action links added

### v1.0.3

- Code clean-up
- Fix some minor issues

### v1.0.2

- Update plugin name
- Did some Code refactoring
- AutoFix WPCodingStandard related issues using `phpcbf`

### v1.0.1

- Fix "total" amount not display in "My Subscription's"

### v1.0.0

- Initial release
