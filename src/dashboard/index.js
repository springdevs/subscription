/**
 * WPSubscription dashboard.
 *
 * A small React screen built on @wordpress/components, so it looks like
 * WordPress rather than like a second design system bolted into wp-admin.
 *
 * Everything shown is preloaded on `window.subscrptDashboard` by
 * Admin\Dashboard::enqueue() — none of these figures change while the page is
 * open, so there is nothing to fetch.
 *
 * The stylesheet lives in src/css/ because webpack.config.js prepends
 * `@import "colors"` to every .scss outside that directory, which fails to
 * resolve for anything that does not want the colour partial.
 *
 * TODO(refactor): migrate this dashboard off @wordpress/components and onto our
 * own admin components (the `wpsubs-*` system in assets/css/admin-components/ —
 * Card/CardHeader/CardBody -> `wpsubs-table-card`, Button -> `wpsubs-btn`, etc.)
 * so the whole admin uses one consistent design system instead of two. Applies
 * to every @wordpress/components import in this folder (App, HealthBanner,
 * SetupChecklist).
 */

import { createRoot } from "@wordpress/element";
import App from "./App";
import "../css/dashboard.scss";

const node = document.getElementById("subscrpt-dashboard");

if (node && window.subscrptDashboard) {
  createRoot(node).render(<App data={window.subscrptDashboard} />);
}
