/**
 * WPSubsTabs — accessible tabbed panels.
 *
 * Markup:
 *   <div class="wpsubs-tabs">
 *     <div class="wpsubs-tabs__list" role="tablist">
 *       <button class="wpsubs-tabs__tab" role="tab" id="t1" aria-controls="p1" aria-selected="true">One</button>
 *       <button class="wpsubs-tabs__tab" role="tab" id="t2" aria-controls="p2">Two</button>
 *     </div>
 *     <div class="wpsubs-tab-panel" role="tabpanel" id="p1" aria-labelledby="t1">…</div>
 *     <div class="wpsubs-tab-panel" role="tabpanel" id="p2" aria-labelledby="t2" hidden>…</div>
 *   </div>
 *
 * The tab with aria-selected="true" (or the first tab) is active on load.
 *
 * Persistence (opt-in): add data-tabs-query="<param>" to the root and the
 * active tab's key is written to that URL query param (via replaceState, no
 * reload). On load the tab matching the param is restored, else the first tab.
 * Each tab's key comes from data-tab-key, falling back to its id.
 *
 * Events fired on the root element (bubbles):
 *   wpsubs:tab:change — { id }  the newly selected tab's id
 */
(function () {
  "use strict";

  /**
   * @param {HTMLElement} el  Root .wpsubs-tabs element.
   */
  function WPSubsTabs(el) {
    this.el = el;
    this.tabs = Array.prototype.slice.call(el.querySelectorAll(".wpsubs-tabs__tab"));
    this.queryParam = el.getAttribute("data-tabs-query") || "";
    this._bind();

    var self = this;

    // Restore from the URL query param (opt-in), else the pre-selected tab,
    // else the first tab.
    var fromQuery = null;
    if (this.queryParam) {
      var wanted = new URLSearchParams(window.location.search).get(this.queryParam);
      if (wanted) {
        fromQuery = this.tabs.filter(function (t) {
          return self._key(t) === wanted;
        })[0];
      }
    }
    var selected = this.tabs.filter(function (t) {
      return t.getAttribute("aria-selected") === "true";
    })[0];
    this.activate(fromQuery || selected || this.tabs[0], false);
  }

  /** Stable key for a tab: data-tab-key, else its id. */
  WPSubsTabs.prototype._key = function (tab) {
    return tab.getAttribute("data-tab-key") || tab.id || "";
  };

  /** Panel controlled by a tab (via aria-controls). */
  WPSubsTabs.prototype._panel = function (tab) {
    if (!tab) return null;
    var id = tab.getAttribute("aria-controls");
    return id ? this.el.querySelector("#" + id) : null;
  };

  /**
   * Show one tab's panel, hide the rest.
   *
   * @param {HTMLElement} tab         Tab to activate.
   * @param {boolean}     [focus]     Move focus to the tab (keyboard nav).
   * @param {boolean}     [updateUrl] Write the tab key to the URL query param.
   */
  WPSubsTabs.prototype.activate = function (tab, focus, updateUrl) {
    if (!tab) return;
    var self = this;

    this.tabs.forEach(function (t) {
      var active = t === tab;
      t.setAttribute("aria-selected", active ? "true" : "false");
      t.setAttribute("tabindex", active ? "0" : "-1");
      var panel = self._panel(t);
      if (panel) panel.hidden = !active;
    });

    if (focus) tab.focus();

    if (updateUrl && this.queryParam) {
      var url = new URL(window.location.href);
      url.searchParams.set(this.queryParam, this._key(tab));
      window.history.replaceState(null, "", url);
    }

    this.el.dispatchEvent(
      new CustomEvent("wpsubs:tab:change", {
        bubbles: true,
        detail: { id: tab.id || "" },
      }),
    );
  };

  WPSubsTabs.prototype._bind = function () {
    var self = this;

    this.tabs.forEach(function (tab, index) {
      tab.addEventListener("click", function (e) {
        e.preventDefault();
        self.activate(tab, false, true);
      });

      tab.addEventListener("keydown", function (e) {
        var dir = e.key === "ArrowRight" ? 1 : e.key === "ArrowLeft" ? -1 : 0;
        if (!dir) return;
        e.preventDefault();
        var next = (index + dir + self.tabs.length) % self.tabs.length;
        self.activate(self.tabs[next], true, true);
      });
    });
  };

  /**
   * Initialise all un-initialised .wpsubs-tabs elements under root.
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document).querySelectorAll(".wpsubs-tabs:not([data-tabs-init])").forEach(function (el) {
      el.setAttribute("data-tabs-init", "1");
      new WPSubsTabs(el);
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
  window.WPSubsTabs = { init: init };
})();
