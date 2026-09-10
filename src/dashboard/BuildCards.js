/**
 * The three cards along the bottom.
 */

import { __ } from "@wordpress/i18n";
import Icon from "./Icon";

export default function BuildCards({ cards }) {
  return (
    <section className="subscrpt-build">
      <h2 className="subscrpt-heading">{__("Build with WPSubscription", "subscription")}</h2>
      <p className="subscrpt-sub">{__("Everything you need to grow recurring revenue.", "subscription")}</p>

      <div className="subscrpt-build__grid">
        {cards.map((card) => (
          <a
            key={card.tone}
            className={`subscrpt-build__card is-${card.tone}`}
            href={card.link.url}
            target={card.link.external ? "_blank" : undefined}
            rel={card.link.external ? "noreferrer noopener" : undefined}
          >
            <span className="subscrpt-build__icon">
              <Icon name={card.icon} size={18} />
            </span>
            <span className="subscrpt-build__eyebrow">{card.eyebrow}</span>
            <span className="subscrpt-build__title">{card.title}</span>
            <span className="subscrpt-build__text">{card.text}</span>
            <span className="subscrpt-build__link">
              {card.link.label}
              {card.link.external ? <Icon name="external" size={12} /> : <span aria-hidden="true">→</span>}
            </span>
          </a>
        ))}
      </div>
    </section>
  );
}
