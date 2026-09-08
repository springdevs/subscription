/**
 * One restrained pro card. Rendered only when pro is absent.
 */

import { __ } from "@wordpress/i18n";
import { Card, CardBody, Button } from "@wordpress/components";

export default function ProCard({ url }) {
  return (
    <Card className="subscrpt-dash__pro">
      <CardBody>
        <h2 className="subscrpt-dash__title">{__("WPSubscription Pro", "subscription")}</h2>
        <p className="subscrpt-dash__muted">
          {__(
            "Automatic payment retries, a health queue for subscriptions that need rescuing, and revenue reporting.",
            "subscription",
          )}
        </p>
        <Button variant="secondary" href={url} target="_blank" rel="noreferrer noopener">
          {__("See what Pro adds", "subscription")}
        </Button>
      </CardBody>
    </Card>
  );
}
