/**
 * Quick links, in the pulse card's footer.
 *
 * The external-link mark is an inline SVG rather than @wordpress/icons: that
 * package is bundled, not provided by WordPress at runtime, and pulling it in
 * would add a build dependency to the free plugin for one arrow.
 */

import { Button } from "@wordpress/components";

const ExternalMark = () => (
  <svg
    width="12"
    height="12"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6" />
    <polyline points="15 3 21 3 21 9" />
    <line x1="10" y1="14" x2="21" y2="3" />
  </svg>
);

export default function QuickLinks({ links }) {
  return (
    <div className="subscrpt-dash__links">
      {links.map((link) => (
        <Button
          key={link.url}
          variant="tertiary"
          href={link.url}
          target={link.external ? "_blank" : undefined}
          rel={link.external ? "noreferrer noopener" : undefined}
        >
          {link.label}
          {link.external && <ExternalMark />}
        </Button>
      ))}
    </div>
  );
}
