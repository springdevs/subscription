/**
 * WPSubsEditList — editable ordered list of text items.
 *
 * A reusable admin component: renders items in a reorderable list with per-row
 * remove/move controls, plus an inline input + add button to append new items.
 * The ordered list is serialized as JSON ([{ key, label }]) into a hidden input
 * so it submits with the surrounding form. `key` is a slug derived from the label.
 *
 * Usage:
 *   PHP: SettingsHelper::render_editlist() / any markup with the classes below
 *   JS:  WPSubsEditList.init()  — auto-inits all .wpsubs-editlist elements
 *
 * Expected structure inside .wpsubs-editlist:
 *   input[type=hidden]                      — JSON store
 *   .wpsubs-editlist__items > .wpsubs-editlist__item[data-key]
 *       .wpsubs-editlist__label
 *       [data-editlist-up] [data-editlist-down] [data-editlist-remove]
 *   .wpsubs-editlist__empty                 — shown only when empty
 *   .wpsubs-editlist__input                 — inline text input
 *   [data-editlist-add]                     — add/confirm button
 *
 * Events fired on the root element (bubbles):
 *   wpsubs:change — { items }  after any add/remove/reorder
 */
(function () {
  "use strict";

  /**
   * Derive a slug key from a label.
   *
   * @param {string} label
   * @return {string}
   */
  function slugify(label) {
    return String(label)
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "");
  }

  var SVG_UP =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>';
  var SVG_DOWN =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
  var SVG_TRASH =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';

  /**
   * @param {HTMLElement} el  Root .wpsubs-editlist element.
   */
  function WPSubsEditList(el) {
    this.el = el;
    this.hidden = el.querySelector('input[type="hidden"]');
    this.list = el.querySelector(".wpsubs-editlist__items");
    this.emptyEl = el.querySelector(".wpsubs-editlist__empty");
    this.input = el.querySelector(".wpsubs-editlist__input");
    // Optional live count badge (e.g. when the list lives inside a modal trigger).
    this.countEl = el.querySelector(".wpsubs-editlist__count");
    this._bind();
    this._serialize();
  }

  WPSubsEditList.prototype._rows = function () {
    return this.list ? this.list.querySelectorAll(".wpsubs-editlist__item") : [];
  };

  /** Serialize the current rows into the hidden input as JSON. */
  WPSubsEditList.prototype._serialize = function () {
    var items = [];
    this._rows().forEach(function (row) {
      var labelEl = row.querySelector(".wpsubs-editlist__label");
      items.push({
        key: row.getAttribute("data-key") || "",
        label: labelEl ? labelEl.textContent : "",
      });
    });
    if (this.hidden) this.hidden.value = JSON.stringify(items);
    if (this.emptyEl) this.emptyEl.hidden = items.length > 0;
    if (this.countEl) this.countEl.textContent = String(items.length);
    this.el.dispatchEvent(new CustomEvent("wpsubs:change", { bubbles: true, detail: { items: items } }));
  };

  /**
   * Append a new item row.
   *
   * @param {string} label
   */
  WPSubsEditList.prototype._addItem = function (label) {
    var text = String(label).trim();
    if (!text || !this.list) return;

    var row = document.createElement("li");
    row.className = "wpsubs-editlist__item";
    row.setAttribute("data-key", slugify(text));

    var handle = document.createElement("span");
    handle.className = "wpsubs-editlist__handle";
    handle.setAttribute("aria-hidden", "true");
    handle.innerHTML = "&#8942;&#8942;";

    var lab = document.createElement("span");
    lab.className = "wpsubs-editlist__label";
    lab.textContent = text;

    var actions = document.createElement("span");
    actions.className = "wpsubs-editlist__actions";
    actions.innerHTML =
      '<button type="button" class="wpsubs-editlist__btn" data-editlist-up>' +
      SVG_UP +
      "</button>" +
      '<button type="button" class="wpsubs-editlist__btn" data-editlist-down>' +
      SVG_DOWN +
      "</button>" +
      '<button type="button" class="wpsubs-editlist__btn wpsubs-editlist__btn--danger" data-editlist-remove>' +
      SVG_TRASH +
      "</button>";

    row.appendChild(handle);
    row.appendChild(lab);
    row.appendChild(actions);
    this.list.appendChild(row);
    this._serialize();
  };

  /** Add the item currently typed in the inline input, then reset it. */
  WPSubsEditList.prototype._commitInput = function () {
    if (!this.input) return;
    var val = this.input.value.trim();
    if (!val) return;
    this._addItem(val);
    this.input.value = "";
    this.input.focus();
  };

  WPSubsEditList.prototype._bind = function () {
    var self = this;

    this.el.addEventListener("click", function (e) {
      if (e.target.closest("[data-editlist-add]")) {
        self._commitInput();
        return;
      }
      var row = e.target.closest(".wpsubs-editlist__item");
      if (!row) return;

      if (e.target.closest("[data-editlist-remove]")) {
        row.parentNode.removeChild(row);
        self._serialize();
      } else if (e.target.closest("[data-editlist-up]")) {
        if (row.previousElementSibling) {
          row.parentNode.insertBefore(row, row.previousElementSibling);
          self._serialize();
        }
      } else if (e.target.closest("[data-editlist-down]")) {
        if (row.nextElementSibling) {
          row.parentNode.insertBefore(row.nextElementSibling, row);
          self._serialize();
        }
      }
    });

    if (this.input) {
      this.input.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          self._commitInput();
        }
      });
    }

    // Drag to reorder — only starts from the handle.
    this._dragRow = null;

    this.el.addEventListener("mousedown", function (e) {
      var handle = e.target.closest(".wpsubs-editlist__handle");
      if (!handle) return;
      var row = handle.closest(".wpsubs-editlist__item");
      if (row) row.setAttribute("draggable", "true");
    });

    this.el.addEventListener("dragstart", function (e) {
      var row = e.target.closest(".wpsubs-editlist__item");
      if (!row || row.getAttribute("draggable") !== "true") return;
      self._dragRow = row;
      row.classList.add("wpsubs-editlist__item--dragging");
      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = "move";
        try {
          e.dataTransfer.setData("text/plain", "");
        } catch (err) {
          /* IE guard */
        }
      }
    });

    this.el.addEventListener("dragover", function (e) {
      if (!self._dragRow || !self.list) return;
      e.preventDefault();
      var over = e.target.closest(".wpsubs-editlist__item");
      if (!over || over === self._dragRow) return;
      var rect = over.getBoundingClientRect();
      var after = (e.clientY - rect.top) / rect.height > 0.5;
      self.list.insertBefore(self._dragRow, after ? over.nextSibling : over);
    });

    this.el.addEventListener("drop", function (e) {
      if (self._dragRow) e.preventDefault();
    });

    this.el.addEventListener("dragend", function () {
      if (!self._dragRow) return;
      self._dragRow.classList.remove("wpsubs-editlist__item--dragging");
      self._dragRow.removeAttribute("draggable");
      self._dragRow = null;
      self._serialize();
    });
  };

  /**
   * Initialise all un-initialised .wpsubs-editlist elements under root.
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document).querySelectorAll(".wpsubs-editlist:not([data-editlist-init])").forEach(function (el) {
      el.setAttribute("data-editlist-init", "1");
      new WPSubsEditList(el);
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
  window.WPSubsEditList = { init: init };
})();
