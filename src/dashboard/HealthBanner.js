/**
 * One sentence answering "is anything wrong".
 *
 * Full width and directly under the figures, because it is the thing a store
 * owner opens this page to find out.
 */

// TODO(refactor): replace @wordpress/components with our `wpsubs-*` admin
// components (wpsubs-btn, …) — see src/dashboard/index.js.
import { Button } from "@wordpress/components";
import Icon from "./Icon";

export default function HealthBanner({ health }) {
  const clear = health.state === "clear";

  return (
    <div className={`subscrpt-banner is-${health.state}`}>
      <span className="subscrpt-banner__badge">
        <Icon name={clear ? "shield" : "alert"} size={20} />
      </span>

      <div className="subscrpt-banner__body">
        <p className="subscrpt-banner__title">{health.title}</p>
        <p className="subscrpt-banner__text">{health.text}</p>
      </div>

      {health.action && (
        <Button variant="primary" href={health.action.url}>
          {health.action.label}
        </Button>
      )}
    </div>
  );
}
