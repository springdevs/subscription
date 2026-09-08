/**
 * Dashboard layout.
 */

import { __ } from "@wordpress/i18n";
import { Card, CardHeader, CardBody, CardFooter, Flex, FlexItem } from "@wordpress/components";
import Pulse from "./Pulse";
import Attention from "./Attention";
import ProCard from "./ProCard";
import QuickLinks from "./QuickLinks";

export default function App({ data }) {
  const { pulse = [], attention = [], links = [], isPro = false, proUrl = "" } = data;

  return (
    <div className="subscrpt-dash">
      <Flex className="subscrpt-dash__grid" align="flex-start" gap={4} wrap>
        <FlexItem className="subscrpt-dash__col subscrpt-dash__col--main">
          <Card>
            <CardHeader>
              <div>
                <h2 className="subscrpt-dash__title">{__("Subscription pulse", "subscription")}</h2>
                <p className="subscrpt-dash__subtitle">
                  {__("Live counts of subscriptions and renewal activity in your store.", "subscription")}
                </p>
              </div>
            </CardHeader>
            <CardBody>
              <Pulse items={pulse} />
            </CardBody>
            <CardFooter>
              <QuickLinks links={links} />
            </CardFooter>
          </Card>
        </FlexItem>

        <FlexItem className="subscrpt-dash__col subscrpt-dash__col--side">
          <Attention items={attention} />
          {!isPro && <ProCard url={proUrl} />}
        </FlexItem>
      </Flex>
    </div>
  );
}
