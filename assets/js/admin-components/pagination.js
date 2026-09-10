/**
 * WPSubsPager — paginator footer (prev / next / numbers / ellipsis) that
 * controls a slice of rows inside a card. Single source of truth so the
 * server-rendered markup from `wpsubs_render_pager()` and the client
 * re-renderings stay byte-identical.
 *
 * Usage:
 *   PHP:  wpsubs_render_pager( $args )  // emits a .wpsubs-pager[data-wpsubs-pager]
 *   JS:   WPSubsPager.init()            // auto-inits those elements
 *
 * Markup contract (data-* on .wpsubs-pager):
 *   data-current       — current page (1-indexed, int)
 *   data-total         — total pages (int)
 *   data-per-page      — items per page (int)
 *   data-link-mode     — 'url' (default) | 'cb' (callback / data-page buttons)
 *   data-info-format   — optional sprintf string for the "Showing X–Y of Z" text
 *
 * Row scope:
 *   data-wpsubs-pager-scope (on the pager OR an ancestor) — a CSS selector for
 *   the container holding the rows to paginate. Defaults to the closest <table>
 *   inside the pager's card. Rows are direct children matched by `row_selector`
 *   on the scope element (default 'tbody tr'). Pro's non-table layouts can
 *   override both with data attributes.
 *
 * Events fired on the pager root (bubbles):
 *   wpsubs:pager:change — { page, total, pages, perPage }
 */
(function () {
  "use strict";

  var MONTHS = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];

  // Same algorithm as PHP wpsubs_pager_page_range(): first, last, current ± 1,
  // gaps collapse to a single ellipsis (gap == 2 surfaces the missing page).
  function pageRange(current, total) {
    var first = 1;
    var last = total;
    current = Math.max(1, Math.min(total, current));
    var nearStart = Math.max(2, current - 1);
    var nearEnd = Math.min(last - 1, current + 1);
    var nearby = [];
    for (var i = nearStart; i <= nearEnd; i++) nearby.push(i);
    var seen = {};
    var parts = [];
    function push(n) {
      if (!seen[n]) {
        seen[n] = true;
        parts.push(n);
      }
    }
    push(first);
    nearby.forEach(push);
    if (last > first) push(last);
    var range = [];
    for (var j = 0; j < parts.length; j++) {
      var p = parts[j];
      if (j > 0) {
        var gap = p - parts[j - 1];
        if (gap === 2) range.push(parts[j - 1] + 1);
        else if (gap > 2) range.push(null);
      }
      range.push(p);
    }
    return range;
  }

  function el(tag, className, attrs) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (attrs) {
      for (var k in attrs)
        if (Object.prototype.hasOwnProperty.call(attrs, k)) {
          if (k === "html") node.innerHTML = attrs[k];
          else if (k === "text") node.textContent = attrs[k];
          else if (k === "data") {
            for (var dk in attrs.data) node.setAttribute("data-" + dk, attrs.data[dk]);
          } else node.setAttribute(k, attrs[k]);
        }
    }
    return node;
  }

  function readInt(el, name, fallback) {
    var v = el.getAttribute(name);
    if (v === null || v === "") return fallback;
    var n = parseInt(v, 10);
    return isNaN(n) ? fallback : n;
  }

  function i18nInfo(format, start, end, total) {
    if (!format) return "";
    if (typeof wp !== "undefined" && wp.i18n && typeof wp.i18n.__ === "function") {
      return wp.i18n
        .__(format, "subscription")
        .replace("%1$s", String(start))
        .replace("%2$s", String(end))
        .replace("%3$s", String(total));
    }
    return format.replace("%1$s", String(start)).replace("%2$s", String(end)).replace("%3$s", String(total));
  }

  function formatInfo(format, start, end, total) {
    if (typeof wp !== "undefined" && wp.i18n && typeof wp.i18n.sprintf === "function") {
      return wp.i18n.sprintf(format, String(start), String(end), String(total));
    }
    return format.replace("%1$s", String(start)).replace("%2$s", String(end)).replace("%3$s", String(total));
  }

  /**
   * @param {HTMLElement} root  .wpsubs-pager[data-wpsubs-pager]
   */
  function WPSubsPager(root) {
    this.root = root;
    this.scope = root.getAttribute("data-wpsubs-pager-scope")
      ? document.querySelector(root.getAttribute("data-wpsubs-pager-scope"))
      : root.closest("[data-wpsubs-pager-scope]") || this.findScope();
    this.rowSelector = root.getAttribute("data-pager-row-selector") || "tbody tr";
    this.dateCol = readInt(root, "data-date-col", -1);
    this.dateAdv = null;
    this.perPageAdv = null;
    this.linkMode = root.getAttribute("data-link-mode") || "url";
    this.infoFormat = root.getAttribute("data-info-format") || "";
    this.card = root.closest(".subscrpt-card, .wpsubs-table-card, [data-wpsubs-pager-card]") || root.parentElement;
    this.rows = this.collectRows();
    this.page = readInt(root, "data-current", 1);
    this.total = readInt(root, "data-total", 1);
    this.perPage = readInt(root, "data-per-page", 10);
    this.emptyRow = null;
    this.colSpan = 1;
    this._bind();
  }

  WPSubsPager.prototype.findScope = function () {
    // Default: closest <table> within the same card.
    var table = this.root.closest(".subscrpt-card, .wpsubs-table-card, [data-wpsubs-pager-card]");
    if (table) {
      var t = table.querySelector("table");
      if (t) return t;
    }
    return this.root.parentElement;
  };

  WPSubsPager.prototype.collectRows = function () {
    if (!this.scope) return [];
    return Array.prototype.slice.call(this.scope.querySelectorAll(this.rowSelector));
  };

  WPSubsPager.prototype._bind = function () {
    var self = this;
    if (this.card) {
      this.dateAdv = this.card.querySelector(".subscrpt-filter-date");
      this.perPageAdv = this.card.querySelector(".subscrpt-filter-perpage");
      var table = this.card.querySelector("table");
      if (table) {
        this.colSpan = table.querySelectorAll("thead th").length || 1;
      }
    }

    this.root.addEventListener("click", function (e) {
      var btn = e.target.closest(".wpsubs-pagination__btn");
      if (!btn || !self.root.contains(btn)) return;
      if (btn.classList.contains("wpsubs-pagination__btn--ellipsis")) return;
      if (btn.classList.contains("wpsubs-pagination__btn--disabled")) return;
      if (btn.classList.contains("wpsubs-pagination__btn--active")) return;
      var page = parseInt(btn.getAttribute("data-page"), 10);
      if (!isNaN(page) && page !== self.page) {
        self.page = page;
        self.render();
      }
    });

    if (this.dateAdv) {
      this.dateAdv.addEventListener("wpsubs:select", function () {
        self.page = 1;
        self.render();
      });
    }
    if (this.perPageAdv) {
      this.perPageAdv.addEventListener("wpsubs:select", function () {
        var input = self.perPageAdv.querySelector('input[type="hidden"]');
        self.perPage = parseInt(input ? input.value : self.perPage, 10) || self.perPage;
        self.page = 1;
        self.render();
      });
    }
  };

  WPSubsPager.prototype._monthKey = function (tr) {
    if (!tr || this.dateCol < 0) return "";
    var cell = tr.children[this.dateCol];
    if (!cell) return "";
    var d = new Date(cell.textContent.replace(" - ", " ").trim());
    if (isNaN(d.getTime())) return "";
    return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2);
  };

  WPSubsPager.prototype._injectMonthOptions = function (rows) {
    if (!this.dateAdv || this.dateCol < 0) return;
    var menu = this.dateAdv.querySelector(".wpsubs-adv-select__menu");
    if (!menu) return;
    // Remove previously injected month options (any non-original item).
    var injected = menu.querySelectorAll("[data-pager-month]");
    injected.forEach(function (n) {
      n.parentNode.removeChild(n);
    });
    var seen = {};
    var months = [];
    rows.forEach(function (tr) {
      var key = this._monthKey(tr);
      if (key && !seen[key]) {
        seen[key] = true;
        months.push(key);
      }
    }, this);
    months
      .sort()
      .reverse()
      .forEach(function (key) {
        var parts = key.split("-");
        var btn = document.createElement("button");
        btn.type = "button";
        btn.setAttribute("data-pager-month", "1");
        btn.className = "wpsubs-adv-select__item";
        btn.setAttribute("data-value", key);
        btn.setAttribute("role", "option");
        var span = document.createElement("span");
        span.className = "wpsubs-adv-select__item-label";
        span.textContent = MONTHS[parseInt(parts[1], 10) - 1] + " " + parts[0];
        btn.appendChild(span);
        menu.appendChild(btn);
      }, this);
  };

  WPSubsPager.prototype._advValue = function (adv) {
    if (!adv) return "";
    var input = adv.querySelector('input[type="hidden"]');
    return input ? input.value : "";
  };

  WPSubsPager.prototype._filtered = function () {
    var dm = this._advValue(this.dateAdv);
    if (!dm || this.dateCol < 0) return this.rows;
    return this.rows.filter(function (tr) {
      return this._monthKey(tr) === dm;
    }, this);
  };

  WPSubsPager.prototype._makeBtn = function (label, target, isDisabled, isActive) {
    if (isDisabled) {
      return el("span", "wpsubs-pagination__btn wpsubs-pagination__btn--disabled", {
        "aria-hidden": "true",
        html: label,
      });
    }
    if (isActive) {
      return el("span", "wpsubs-pagination__btn wpsubs-pagination__btn--active", {
        "aria-current": "page",
        text: label,
      });
    }
    if (this.linkMode === "cb") {
      return el("button", "wpsubs-pagination__btn", {
        type: "button",
        "data-page": String(target),
        text: label,
      });
    }
    return el("a", "wpsubs-pagination__btn", { "data-page": String(target), text: label });
  };

  WPSubsPager.prototype._makeEllipsis = function () {
    return el("span", "wpsubs-pagination__btn wpsubs-pagination__btn--ellipsis", {
      "aria-hidden": "true",
      text: "…",
    });
  };

  WPSubsPager.prototype._buildPager = function (pages) {
    var frag = document.createDocumentFragment();
    frag.appendChild(this._makeBtn("‹", this.page - 1, this.page <= 1, false));
    var range = pageRange(this.page, pages);
    for (var i = 0; i < range.length; i++) {
      var p = range[i];
      frag.appendChild(p === null ? this._makeEllipsis() : this._makeBtn(String(p), p, false, p === this.page));
    }
    frag.appendChild(this._makeBtn("›", this.page + 1, this.page >= pages, false));
    return frag;
  };

  WPSubsPager.prototype._updateInfo = function (start, end, total) {
    if (!this.infoFormat) return;
    var span = this.root.querySelector(".wpsubs-pagination__info");
    if (!span) return;
    span.textContent = formatInfo(this.infoFormat, total ? start + 1 : 0, Math.min(end, total), total);
  };

  WPSubsPager.prototype._showEmpty = function (show) {
    if (!this.scope) return;
    if (show) {
      if (!this.emptyRow) {
        var tr = document.createElement("tr");
        tr.className = "subscrpt-empty-row";
        var td = document.createElement("td");
        td.colSpan = this.colSpan;
        td.style.textAlign = "center";
        td.style.padding = "18px";
        td.textContent =
          typeof wp !== "undefined" && wp.i18n && typeof wp.i18n.__ === "function"
            ? wp.i18n.__("No matching records.", "subscription")
            : "No matching records.";
        tr.appendChild(td);
        if (this.scope.tagName === "TBODY" || this.scope.tagName === "TABLE") {
          this.scope.appendChild(tr);
        } else {
          this.scope.appendChild(tr);
        }
        this.emptyRow = tr;
      }
      this.emptyRow.style.display = "";
    } else if (this.emptyRow) {
      this.emptyRow.style.display = "none";
    }
  };

  WPSubsPager.prototype.render = function () {
    var list = this._filtered();
    var pages = Math.max(1, Math.ceil(list.length / this.perPage));
    if (this.page > pages) this.page = pages;
    var start = (this.page - 1) * this.perPage;
    var end = start + this.perPage;
    this.total = pages;

    this.rows.forEach(function (tr) {
      tr.style.display = "none";
    });
    list.slice(start, end).forEach(function (tr) {
      tr.style.display = "";
    });

    this._showEmpty(list.length === 0);

    // Rebuild just the nav portion, leave the root attributes alone.
    var existingNav = this.root.querySelector(".wpsubs-pagination__nav");
    if (existingNav) existingNav.parentNode.removeChild(existingNav);
    var nav = el("div", "wpsubs-pagination__nav");
    nav.appendChild(this._buildPager(pages));
    this.root.appendChild(nav);

    // Keep current/total data-* in sync so subsequent re-inits read the right state.
    this.root.setAttribute("data-current", String(this.page));
    this.root.setAttribute("data-total", String(pages));

    this._updateInfo(start, end, list.length);

    this.root.dispatchEvent(
      new CustomEvent("wpsubs:pager:change", {
        bubbles: true,
        detail: { page: this.page, total: list.length, pages: pages, perPage: this.perPage },
      }),
    );
  };

  WPSubsPager.prototype.refresh = function () {
    // External hook: re-collect rows (Pro renders new content) and re-paginate.
    this.rows = this.collectRows();
    this._injectMonthOptions(this.rows);
    this.render();
  };

  WPSubsPager.prototype.goTo = function (page) {
    page = parseInt(page, 10);
    if (isNaN(page)) return;
    var pages = Math.max(1, Math.ceil(this.rows.length / this.perPage));
    this.page = Math.max(1, Math.min(pages, page));
    this.render();
  };

  /**
   * Initialise all un-initialised .wpsubs-pager elements under root.
   * Skips pagers in `link-mode="url"` — those are server-rendered (e.g. the
   * subscriptions list page) and own all the data they need; touching them
   * from JS would re-derive totals from the current page's rows and clobber
   * the correct pagination (e.g. show only "1" of 3 pages).
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document)
      .querySelectorAll(".wpsubs-pager[data-wpsubs-pager][data-link-mode='cb']:not([data-pager-init])")
      .forEach(function (el) {
        el.setAttribute("data-pager-init", "1");
        var pager = new WPSubsPager(el);
        pager._injectMonthOptions(pager.rows);
        pager.render();
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
  window.WPSubsPager = {
    init: init,
    /**
     * Re-collect rows for every initialised cb-mode pager under root and
     * re-render. Useful when a callback injects new rows (e.g. Pro activities).
     */
    refresh: function (root) {
      (root || document).querySelectorAll(".wpsubs-pager[data-link-mode='cb'][data-pager-init]").forEach(function (el) {
        el.removeAttribute("data-pager-init");
        init(el.parentNode || document);
      });
    },
    /**
     * Force a single pager to re-collect rows (Pro hook) and re-render.
     * Skips url-mode pagers.
     */
    refreshOne: function (el) {
      if (!el) return;
      if (el.getAttribute("data-link-mode") !== "cb") return;
      el.removeAttribute("data-pager-init");
      init(el.parentNode || document);
    },
  };
})();
