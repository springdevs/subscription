/**
 * The five figures, as tiles.
 *
 * Each is a link, not just a number: a count you cannot act on is trivia, so
 * every tile opens the list it describes.
 */

import Icon from "./Icon";

export default function StatTiles({ items }) {
  return (
    <div className="subscrpt-tiles">
      {items.map((item) => (
        <a key={item.key} className={`subscrpt-tile${item.tone ? ` is-${item.tone}` : ""}`} href={item.url}>
          <span className="subscrpt-tile__icon">
            <Icon name={item.icon} />
          </span>
          <span className="subscrpt-tile__value">{new Intl.NumberFormat().format(item.value)}</span>
          <span className="subscrpt-tile__label">{item.label}</span>
        </a>
      ))}
    </div>
  );
}
