/**
 * Setup checklist.
 *
 * Renders nothing once every step is done — a finished checklist is clutter,
 * and the banner above already says the store is ready.
 */

import { __, sprintf } from "@wordpress/i18n";
// TODO(refactor): replace @wordpress/components with our `wpsubs-*` admin
// components (wpsubs-table-card, wpsubs-btn, …) — see src/dashboard/index.js.
import { Card, CardHeader, CardBody, Button } from "@wordpress/components";
import Icon from "./Icon";

export default function SetupChecklist({ setup }) {
  if (setup.complete) {
    return null;
  }

  const percent = Math.round((setup.done / setup.total) * 100);

  return (
    <Card className="subscrpt-setup">
      <CardHeader>
        <div>
          <h2 className="subscrpt-heading">{__("Get set up", "subscription")}</h2>
          <p className="subscrpt-sub">{__("A few steps before your store can sell subscriptions.", "subscription")}</p>
        </div>
        <span className="subscrpt-setup__count">
          {sprintf(
            /* translators: 1: steps done, 2: steps total. */
            __("%1$d of %2$d done", "subscription"),
            setup.done,
            setup.total,
          )}
        </span>
      </CardHeader>

      <CardBody>
        <div
          className="subscrpt-progress"
          role="progressbar"
          aria-valuenow={setup.done}
          aria-valuemin={0}
          aria-valuemax={setup.total}
        >
          <span className="subscrpt-progress__bar" style={{ width: `${percent}%` }} />
        </div>

        <ul className="subscrpt-steps">
          {setup.items.map((item) => (
            <li key={item.id} className={`subscrpt-step${item.done ? " is-done" : ""}`}>
              <span className="subscrpt-step__mark">{item.done ? <Icon name="check" size={13} /> : null}</span>
              <span className="subscrpt-step__label">{item.label}</span>
              {item.done ? (
                <span className="subscrpt-step__state">{__("Done", "subscription")}</span>
              ) : (
                <Button variant="secondary" size="small" href={item.action.url}>
                  {item.action.label}
                </Button>
              )}
            </li>
          ))}
        </ul>
      </CardBody>
    </Card>
  );
}
