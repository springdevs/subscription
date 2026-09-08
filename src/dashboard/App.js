/**
 * Dashboard layout.
 *
 * Figures across the top, then the store's shape on the left with everything
 * that wants doing on the right, then where to go next.
 */

import { __ } from "@wordpress/i18n";
import { Card, CardHeader, CardBody, Button } from "@wordpress/components";
import StatTiles from "./StatTiles";
import SalesChart from "./SalesChart";
import HealthBanner from "./HealthBanner";
import SetupChecklist from "./SetupChecklist";
import BuildCards from "./BuildCards";
import FooterLinks from "./FooterLinks";

export default function App({ data }) {
  const { pulse = [], chart, health, setup, build = [], footer = [] } = data;

  return (
    <div className="subscrpt-dash">
      <Card className="subscrpt-dash__pulse">
        <CardHeader>
          <div>
            <h2 className="subscrpt-heading">{__("Subscription pulse", "subscription")}</h2>
            <p className="subscrpt-sub">
              {__("Live counts of subscriptions and renewal activity in your store.", "subscription")}
            </p>
          </div>
        </CardHeader>
        <CardBody>
          <StatTiles items={pulse} />
        </CardBody>
      </Card>

      <div className="subscrpt-dash__split">
        <div className="subscrpt-dash__main">
          {chart && (
            <Card>
              <CardHeader>
                <div>
                  <h2 className="subscrpt-heading">{__("Monthly sales", "subscription")}</h2>
                  <p className="subscrpt-sub">{__("Revenue from subscription orders.", "subscription")}</p>
                </div>
                <Button variant="tertiary" href={chart.url}>
                  {__("Open reports", "subscription")}
                </Button>
              </CardHeader>
              <CardBody>
                <SalesChart chart={chart} />
              </CardBody>
            </Card>
          )}
        </div>

        <aside className="subscrpt-dash__aside">
          {health && <HealthBanner health={health} />}
          {setup && <SetupChecklist setup={setup} />}
        </aside>
      </div>

      <BuildCards cards={build} />
      <FooterLinks links={footer} />
    </div>
  );
}
