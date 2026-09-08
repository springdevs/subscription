/**
 * Things worth doing something about.
 *
 * Renders nothing but a single reassurance when the list is empty — an empty
 * "Needs attention" card reads as broken rather than as good news.
 */

import { __ } from "@wordpress/i18n";
import { Card, CardHeader, CardBody, Notice, Button } from "@wordpress/components";

export default function Attention({ items }) {
  return (
    <Card className="subscrpt-dash__attention">
      <CardHeader>
        <h2 className="subscrpt-dash__title">{__("Needs attention", "subscription")}</h2>
      </CardHeader>
      <CardBody>
        {items.length === 0 ? (
          <p className="subscrpt-dash__muted">
            {__("Nothing needs attention. Your store is set up and running.", "subscription")}
          </p>
        ) : (
          items.map((item) => (
            <Notice key={item.id} status={item.status} isDismissible={false} className="subscrpt-dash__notice">
              <span className="subscrpt-dash__notice-text">{item.text}</span>
              <Button variant="link" href={item.url}>
                {item.label}
              </Button>
            </Notice>
          ))
        )}
      </CardBody>
    </Card>
  );
}
