/**
 * WPSubsAccordion — expand/collapse sections.
 *
 * Markup:
 *   <div class="wpsubs-accordion" data-multi="1">
 *     <div class="wpsubs-accordion__item">
 *       <button class="wpsubs-accordion__header" aria-controls="a1" aria-expanded="false">Title</button>
 *       <div class="wpsubs-accordion__panel" id="a1" hidden>…</div>
 *     </div>
 *   </div>
 *
 * data-multi (truthy) allows multiple panels open at once; default is
 * single-open (opening one collapses the others). A header with
 * aria-expanded="true" starts open.
 *
 * Events fired on the root element (bubbles):
 *   wpsubs:accordion:toggle — { id, open }
 */
(function () {
  "use strict";

  /**
   * @param {HTMLElement} el  Root .wpsubs-accordion element.
   */
  function WPSubsAccordion(el) {
    this.el = el;
    this.multi = !!el.dataset.multi;
    this.headers = Array.prototype.slice.call(el.querySelectorAll(".wpsubs-accordion__header"));
    this._bind();

    // Sync initial panel visibility to each header's aria-expanded.
    var self = this;
    this.headers.forEach(function (header) {
      var panel = self._panel(header);
      if (panel) panel.hidden = header.getAttribute("aria-expanded") !== "true";
    });
  }

  WPSubsAccordion.prototype._panel = function (header) {
    if (!header) return null;
    var id = header.getAttribute("aria-controls");
    return id ? this.el.querySelector("#" + id) : null;
  };

  /**
   * Open or close one section.
   *
   * @param {HTMLElement} header Section header button.
   * @param {boolean}     open   Desired state.
   */
  WPSubsAccordion.prototype.set = function (header, open) {
    var panel = this._panel(header);
    header.setAttribute("aria-expanded", open ? "true" : "false");
    if (panel) panel.hidden = !open;

    // Single-open mode: collapse every other section when opening this one.
    if (open && !this.multi) {
      var self = this;
      this.headers.forEach(function (other) {
        if (other !== header) self.set(other, false);
      });
    }

    this.el.dispatchEvent(
      new CustomEvent("wpsubs:accordion:toggle", {
        bubbles: true,
        detail: { id: header.getAttribute("aria-controls") || "", open: open },
      }),
    );
  };

  WPSubsAccordion.prototype._bind = function () {
    var self = this;
    this.headers.forEach(function (header) {
      header.addEventListener("click", function (e) {
        e.preventDefault();
        self.set(header, header.getAttribute("aria-expanded") !== "true");
      });
    });
  };

  /**
   * Initialise all un-initialised .wpsubs-accordion elements under root.
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document).querySelectorAll(".wpsubs-accordion:not([data-accordion-init])").forEach(function (el) {
      el.setAttribute("data-accordion-init", "1");
      new WPSubsAccordion(el);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      init();
    });
  } else {
    init();
  }

  // Public API
  window.WPSubsAccordion = { init: init };
})();
