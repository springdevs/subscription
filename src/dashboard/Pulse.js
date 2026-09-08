/**
 * The five figures.
 *
 * Each is a link rather than a number: a count you cannot act on is trivia, so
 * every figure opens the list it describes.
 */

import { __ } from "@wordpress/i18n";

export default function Pulse({ items }) {
  if (!items.length) {
    return <p className="subscrpt-dash__muted">{__("No data yet.", "subscription")}</p>;
  }

  return (
    <ul className="subscrpt-pulse">
      {items.map((item) => (
        <li key={item.key} className="subscrpt-pulse__item">
          <a className="subscrpt-pulse__link" href={item.url}>
            <span className={`subscrpt-pulse__value${item.tone ? ` is-${item.tone}` : ""}`}>
              {new Intl.NumberFormat().format(item.value)}
            </span>
            <span className="subscrpt-pulse__label">{item.label}</span>
          </a>
        </li>
      ))}
    </ul>
  );
}
