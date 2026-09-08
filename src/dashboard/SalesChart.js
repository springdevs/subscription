/**
 * Monthly subscription revenue.
 *
 * Drawn as inline SVG rather than with a charting library. Six bars need no
 * Chart.js, and pulling one in would be roughly thirty times the weight of
 * this entire screen.
 *
 * Amounts arrive already formatted by WooCommerce — the store's currency,
 * separator and symbol position live in its settings, and reimplementing
 * wc_price() here would get them wrong for every locale but the developer's.
 */

import { __, sprintf } from "@wordpress/i18n";

const HEIGHT = 132;
const MIN_BAR = 2;

export default function SalesChart({ chart }) {
  const months = chart.months || [];
  const peak = Math.max(...months.map((m) => m.total), 0);

  return (
    <div className="subscrpt-chart">
      <div className="subscrpt-chart__total">
        <span className="subscrpt-chart__amount">{chart.display}</span>
        <span className="subscrpt-chart__caption">
          {sprintf(
            /* translators: %d: number of months. */
            __("across the last %d months", "subscription"),
            months.length,
          )}
        </span>
      </div>

      {chart.empty ? (
        <p className="subscrpt-chart__empty">
          {__("No subscription revenue yet. Bars appear once orders come in.", "subscription")}
        </p>
      ) : (
        <div
          className="subscrpt-chart__plot"
          role="img"
          aria-label={months.map((m) => `${m.label}: ${m.display}`).join(", ")}
        >
          {months.map((month) => {
            // A month with sales always gets a visible sliver, so a small
            // month reads as small rather than as no month at all.
            const ratio = peak > 0 ? month.total / peak : 0;
            const height = month.total > 0 ? Math.max(MIN_BAR, ratio * HEIGHT) : 0;

            return (
              <div className="subscrpt-chart__col" key={month.month}>
                <span className="subscrpt-chart__bar-wrap">
                  <span
                    className={`subscrpt-chart__bar${month.total > 0 ? "" : " is-empty"}`}
                    style={{ height: `${height}px` }}
                  />
                </span>
                {/* A row of repeated zeroes is noise; the month label alone
                    already says the month had nothing. */}
                <span className="subscrpt-chart__value">{month.total > 0 ? month.display : "\u2014"}</span>
                <span className="subscrpt-chart__month">{month.label}</span>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
