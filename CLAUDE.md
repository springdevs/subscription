# CLAUDE.md

Guidance for Claude Code (claude.ai/code) when working in this repository.

## Plugin overview

**Subscriptions for WooCommerce (WPSubscription)** adds recurring payments,
renewals and subscription management to WooCommerce. This is the **free**
plugin, distributed on wordpress.org.

- **Namespace:** `SpringDevs\Subscription\` — PSR-4 root is `includes/`
- **Text domain:** `subscription`
- **Constants:** `SUBSCRPT_`
- **Hooks / functions / post types:** `subscrpt_`
- **Bootstrap class:** `Sdevs_Subscription` (singleton, in `subscription.php`)

`WPS_`, `wps_` and `WP_SUBSCRIPTION_*` are **legacy**. `includes/LegacyCompat.php`
still aliases the old names so existing installs and the Pro plugin keep
working; that is back-compat, not a pattern to copy. Never use them in new code.

### Sister plugin (pro)

**WPSubscription Pro** (`converslabs/subscription-pro`, namespace
`SpringDevs\SubscriptionPro\`, constants `SUBSCRIPT_PRO_`) extends this plugin
**exclusively through `subscrpt_*` hooks** and never edits a file here.

Roughly two thirds of Pro's listeners are attached to hooks this plugin fires.
Renaming or removing one does not error — Pro's listener simply never runs, and
the feature stops working on every install that has both, silently. Treat every
`do_action()` and `apply_filters()` in this plugin as a public API.

## Commands

```bash
yarn install-dev        # yarn + composer dependencies
yarn build              # wp-scripts build  -> build/
yarn watch              # wp-scripts start
composer lint:php       # phpcs
composer format:php     # phpcbf
yarn update-lang-files   # regenerate languages/subscription.pot
yarn release            # scripts/release.sh -> releases/subscription_v<version>.zip
yarn commit             # conventional commit via cz-customizable
yarn listen-webhook     # smee.js, for gateway webhooks against a local site
```

Husky runs phpcs, phpcbf and prettier on staged files at commit time.

## Architecture

`subscription.php` → `Sdevs_Subscription` (singleton) → `plugins_loaded` →
`init_plugin()`.

| Path                             | What lives there                                                                            |
| -------------------------------- | ------------------------------------------------------------------------------------------- |
| `includes/Illuminate/`           | Business logic — lifecycle, cron, email, renewals, gateways                                 |
| `includes/Illuminate/Action.php` | **Fires every lifecycle hook.** `Action::status()` is the single funnel for a status change |
| `includes/Illuminate/Post.php`   | Registers the `subscrpt_order` post type and its statuses                                   |
| `includes/Illuminate/Gateways/`  | Stripe and PayPal                                                                           |
| `includes/Admin/`                | Settings, product config, subscription list, order details                                  |
| `includes/Frontend/`             | Product, cart, checkout, My Account, pause/resume/cancel controller                         |
| `includes/LegacyCompat.php`      | Aliases for the retired `WP_SUBSCRIPTION_*` names                                           |
| `templates/`                     | Front-end and email templates — theme-overridable, so a change here is a public API change  |
| `src/` → `build/`                | JavaScript, via `@wordpress/scripts`                                                        |

## Things that will otherwise cost an hour

- **`vendor/` is required on the first line and is not committed.**
  `subscription.php` does `require_once __DIR__ . '/vendor/autoload.php'` before
  anything else, so a fresh clone fatals on activation with a blank screen and
  nothing saying why. `composer install` is the fix.

- **`build/` _is_ committed**, unlike most projects. A fresh clone is already
  functional. Only rebuild when you have changed something in `src/`.

- **The version in the header is a placeholder, not a number.**
  `package.json` is the source of truth; the header, `readme-template.txt` and
  the `.pot` carry `#WPSUBS_VERSION`, which `scripts/release.sh` substitutes at
  package time. Typing a real version into the header means it is never
  substituted, so the shipped header and the zip disagree — users get new PHP
  with stale cached assets, which is indistinguishable from "the fix did not
  work". Bump `package.json` only.

- **A subscription status must be one of six.** `subscrpt_order` registers
  exactly `pending`, `active`, `on_hold`, `cancelled`, `expired` and
  `pe_cancelled` (`includes/Illuminate/Post.php`). `wp_update_post()` does not
  validate `post_status`: anything else is stored without complaint, and the
  subscription then matches no query — it stays in the database and disappears
  from the admin list, its filters and My Account. Note **`on_hold` takes an
  underscore**; `on-hold` is WooCommerce's _order_ status and has caused this
  exact bug before.

- **`Action::status()` is the only way to change a status.** It writes the post,
  logs the comment, and fires `subscrpt_subscription_status_changed` plus the
  specific lifecycle hook. Calling `wp_update_post()` directly skips all of it,
  and every Pro listener with it.

- **Renewals are on wp-cron**, driven by `subscrpt_daily_cron` and
  `subscrpt_hourly_cron`. Real time moves far too slowly to watch one fire;
  run the due events by hand instead.

## The pro boundary

**This plugin must never name a symbol Pro declares.** It runs alone on nearly
all of its installs, so a call into Pro works in a development environment where
both are installed and fatals everywhere else. Testing with Pro active cannot
catch this by construction.

The only sanctioned way to ask about Pro:

```php
if ( subscrpt_pro_activated() ) { … }
if ( class_exists( 'Sdevs_Wc_Subscription_Pro' ) ) { … }
if ( defined( 'SUBSCRIPT_PRO_VERSION' ) ) { … }
```

Nothing else — not `new`, not a static call, not a type hint, not a constant
read.

The `subscrpt_` **hook prefix is shared** with Pro by design, so a prefix rule
cannot decide the boundary. The constants are what differ: `SUBSCRPT_` here,
`SUBSCRIPT_PRO_` there. A new hook must not collide with one of Pro's — grep
both codebases before adding one.

## Coding standards

`phpcs.xml` is WordPress Coding Standards against PHP 7.4. Compatibility floor
is PHP 7.4, WordPress 6.0, WooCommerce 6.0.

- Escape all output — `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`
- Sanitize all input
- AJAX and admin-post handlers verify a nonce **and** `current_user_can()`
- `$wpdb->prepare()` for every query carrying a variable
- `wp_safe_redirect()` + `exit`, never an echoed `location.href`
- Compare secrets with `hash_equals()`, never `===`
- PHPDoc on every class, method and global function, and on every `do_action`
  and `apply_filters`
- New CSS classes use the `subscrpt-` prefix. Existing `wp-subscription-`
  classes are not renamed; templates depend on them

**This plugin ships to wordpress.org**, so it is subject to Plugin Check. Do not
add a file that executes on load without an `ABSPATH` guard, and do not commit
anything into the release file set that is not needed at runtime.

## Releasing

`scripts/release.sh` builds `releases/subscription_v<version>.zip` from an
explicit list of what ships. **Adding a runtime directory means adding a line to
that list** — forgetting leaves the file out of the zip, which is the safe
direction to fail in.

The zip carries `vendor/`: wordpress.org does not run composer and the plugin
requires its autoloader on the first line.

`.github/workflows/deploy-wordpress-org.yml` builds and deploys on a pushed tag.
`scripts/svn.sh` is the manual path.

## Commits

Conventional commits, one logical change each — `fix: 🐛`, `feat: ✨`,
`docs: 📝`, `chore: 🤖`, `style: 🎨`, `test: 🧪`, `refactor: 🛠️`. If the subject
needs an "and", it is two commits.

Branch from `main` with the matching prefix: `fix/`, `feat/`, `docs/`, `chore/`.
Never commit to `main` directly.

**Never commit unless asked to.**

## Working here

- **Changing a hook, a template or a status is a cross-plugin change.** Pro
  depends on all three. Check what Pro does with it before you touch it.
- **Treat subagent and tool findings as claims, not facts.** Verify before
  acting.
