/**
 * Dashboard layout.
 *
 * Full width and stacked: figures, then the one-line answer to "is anything
 * wrong", then setup if it is unfinished, then where to go next.
 */

import { __ } from "@wordpress/i18n";
import { Card, CardHeader, CardBody } from "@wordpress/components";
import StatTiles from "./StatTiles";
import HealthBanner from "./HealthBanner";
import SetupChecklist from "./SetupChecklist";
import BuildCards from "./BuildCards";
import FooterLinks from "./FooterLinks";

export default function App({ data }) {
  const { pulse = [], health, setup, build = [], footer = [] } = data;

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

      {health && <HealthBanner health={health} />}
      {setup && <SetupChecklist setup={setup} />}

      <BuildCards cards={build} />
      <FooterLinks links={footer} />
    </div>
  );
}
