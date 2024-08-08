import {
  registerCheckoutFilters,
  ExperimentalOrderMeta,
  TotalsItem,
} from "@woocommerce/blocks-checkout";
import { FormattedMonetaryAmount } from "@woocommerce/blocks-components";
import { registerPlugin } from "@wordpress/plugins";
import { __ } from "@wordpress/i18n";

import { getCurrencyFromPriceResponse } from "@woocommerce/price-format";
// import { useStoreCart } from "@woocommerce/base-context/hooks";

const modifyCartItemPrice = (defaultValue, extensions, args, validation) => {
  const { sdevs_subscription } = extensions;
  const { cartItem } = args;
  const { totals } = cartItem;
  if (totals === undefined) {
    return defaultValue;
  }
  if (totals.line_total === "0") {
    return `<price/> Due Today`;
  }
  if (sdevs_subscription.type) {
    return `<price/> / ${
      sdevs_subscription.time ? " " + sdevs_subscription.time + "-" : ""
    }${sdevs_subscription.type}`;
  }
  return defaultValue;
};

const modifySubtotalPriceFormat = (
  defaultValue,
  extensions,
  args,
  validation
) => {
  const { sdevs_subscription } = extensions;
  if (sdevs_subscription && sdevs_subscription.type) {
    return `<price/> every ${
      sdevs_subscription.time && sdevs_subscription.time === 1
        ? " " + sdevs_subscription.time + "-"
        : ""
    }${sdevs_subscription.type}`;
  }
  return defaultValue;
};

registerCheckoutFilters("sdevs-subscription", {
  cartItemPrice: modifyCartItemPrice,
  subtotalPriceFormat: modifySubtotalPriceFormat,
});

const RecurringTotals = ({ cart, extensions }) => {
  if (Object.keys(extensions).length === 0) {
    return;
  }
  const { cartTotals } = cart;
  const { sdevs_subscription: recurrings } = extensions;
  const currency = getCurrencyFromPriceResponse(cartTotals);
  if (recurrings.length === 0) {
    return;
  }
  return (
    <TotalsItem
      className="wc-block-components-totals-footer-item"
      label={__("Recurring totals", "sdevs_subscrpt")}
      description={recurrings.map((recurring) => (
        <div style={{ margin: "20px 0", float: "right" }}>
          <div style={{ fontSize: "18px" }}>
            <FormattedMonetaryAmount
              currency={currency}
              value={parseInt(recurring.price, 10)}
            />
            /{" "}
            {recurring.time && recurring.time > 1
              ? `${recurring.time + "-" + recurring.type} `
              : recurring.type}
          </div>
          <small>{recurring.description}</small>
        </div>
      ))}
    ></TotalsItem>
  );
};

const render = () => {
  return (
    <ExperimentalOrderMeta>
      <RecurringTotals />
    </ExperimentalOrderMeta>
  );
};

registerPlugin("sdevs-subscription", {
  render,
  scope: "woocommerce-checkout",
});
