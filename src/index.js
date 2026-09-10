import {
  ExperimentalOrderMeta,
  registerCheckoutFilters,
  TotalsItem,
  TotalsWrapper,
} from "@woocommerce/blocks-checkout";
import { FormattedMonetaryAmount } from "@woocommerce/blocks-components";
import { __, sprintf } from "@wordpress/i18n";
import { registerPlugin } from "@wordpress/plugins";

import { formatPrice, getCurrencyFromPriceResponse } from "@woocommerce/price-format";
// import { useStoreCart } from "@woocommerce/base-context/hooks";

const modifyCartItemPrice = (defaultValue, extensions, args, validation) => {
  const { cartItem } = args;
  const { totals } = cartItem;

  if (totals === undefined) {
    return defaultValue;
  }
  if (totals.line_total === "0") {
    return `<price/> ${__("Due Today", "subscription")}`;
  }
  // The per-unit price shows no cadence — the recurring cadence appears on the
  // line total (subtotalPriceFormat) and the "Recurring totals" summary.
  return defaultValue;
};

const modifySubtotalPriceFormat = (defaultValue, extensions, args, validation) => {
  const { sdevs_subscription } = extensions;
  const { sdevs_subscription: recurrings } = extensions;

  if (sdevs_subscription && sdevs_subscription.type) {
    // Capitalize the first letter to match product page display
    const capitalizedType = sdevs_subscription.type.charAt(0).toUpperCase() + sdevs_subscription.type.slice(1);

    // Check max_no_payment - handle string, number, null, undefined
    const maxPayments = parseInt(sdevs_subscription.max_no_payment, 10);
    const paymentInfo = !isNaN(maxPayments) && maxPayments > 0 ? ` x ${maxPayments}` : "";

    const timePart = sdevs_subscription.time && sdevs_subscription.time > 1 ? sdevs_subscription.time + "-" : "";
    const priceText = "<price/>\u00A0" + __("Every", "subscription") + " " + timePart + capitalizedType + paymentInfo;

    return priceText;
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
  if (recurrings.length === 0) {
    return;
  }

  const currency = getCurrencyFromPriceResponse(cartTotals);
  const multiplier = Math.pow(10, currency.minorUnit);

  return (
    <TotalsWrapper>
      <TotalsItem
        className="wc-block-components-totals-footer-item"
        label={__("Recurring totals", "subscription")}
        description={
          <div style={{ display: "grid" }}>
            {recurrings.map((recurring) => {
              // Capitalize the first letter to match product page display
              const capitalizedType = recurring.type.charAt(0).toUpperCase() + recurring.type.slice(1);

              const priceFormatArgs = {
                currency: currency.code,
                currency_symbol: currency.symbol,
                decimal_separator: currency.decimalSeparator,
                thousand_separator: currency.thousandSeparator,
                precision: currency.minorUnit,
                price_format: currency.priceFormat,
              };

              /**
               * Format an amount the same way the surrounding totals are formatted.
               *
               * @param {number} amount Amount in major units.
               * @return {string} Formatted price.
               */
              const format = (amount) => formatPrice(Math.round(amount * multiplier), priceFormatArgs);

              const recurringLimit = parseInt(recurring.recurring_limit, 10) || 0;

              // Billing period as plain text, so notes can quote a price the same way the row does.
              const periodLabel =
                recurring.time && recurring.time > 1 ? `${recurring.time}-${capitalizedType}` : capitalizedType;

              return (
                <div style={{ margin: "8px 0px 0px", float: "right" }}>
                  <div style={{ fontSize: "18px" }}>
                    {recurring.has_recurring_discount && (
                      <del aria-hidden="true" style={{ marginRight: "6px", opacity: 0.6 }}>
                        {format(recurring.full_price)}
                      </del>
                    )}
                    <FormattedMonetaryAmount currency={currency} value={Math.round(recurring.price * multiplier)} />
                    <span class="wpsubs-subscription-timing">
                      &nbsp;/&nbsp;
                      {recurring.time && recurring.time > 1
                        ? `${recurring.time + "-" + capitalizedType} `
                        : capitalizedType}
                    </span>
                  </div>
                  <small>{recurring.description}</small>
                  {recurring.has_one_time_discount && (
                    <>
                      <br />
                      <small>
                        {sprintf(
                          // translators: 1: amount paid today, 2: amount charged on each renewal.
                          __("You pay %1$s today. %2$s will be charged from the next renewal.", "subscription"),
                          format(recurring.first_price),
                          format(recurring.price),
                        )}
                      </small>
                    </>
                  )}
                  {recurring.has_recurring_discount && recurringLimit > 1 && (
                    <>
                      <br />
                      <small>
                        {sprintf(
                          // translators: 1: number of discounted payments, 2: full price charged afterwards.
                          __("Discount applies to your first %1$s payments. After that, %2$s.", "subscription"),
                          recurringLimit,
                          `${format(recurring.full_price)} / ${periodLabel}`,
                        )}
                      </small>
                    </>
                  )}
                  {recurring.can_user_cancel === "yes" &&
                    (recurring.max_no_payment === "" || parseInt(recurring.max_no_payment) === 0) && (
                      <>
                        <br />
                        <small>{__("You can cancel subscription at any time!", "subscription")} </small>
                      </>
                    )}
                  {parseInt(recurring.max_no_payment) > 0 && (
                    <>
                      <br />
                      <small>
                        {sprintf(
                          // translators: 1: number of installments, 2: total amount.
                          __(
                            "This subscription will be billed in %1$s installments, for a total of %2$s.",
                            "subscription",
                          ),
                          recurring.max_no_payment,
                          format(
                            recurring.split_total != null
                              ? recurring.split_total
                              : recurring.price * parseInt(recurring.max_no_payment),
                          ),
                        )}
                      </small>
                    </>
                  )}
                </div>
              );
            })}
          </div>
        }
      ></TotalsItem>
    </TotalsWrapper>
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
