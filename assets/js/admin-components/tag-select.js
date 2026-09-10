/**
 * WPSubsTagSelect — pill/tag input with inline filter and filterable dropdown.
 *
 * Usage:
 *   PHP: wpsubs_render_tag_select( $args )  — renders the HTML
 *   JS:  WPSubsTagSelect.init()             — auto-inits all .wpsubs-tag-select elements
 *
 * Events fired on the root element (bubbles):
 *   wpsubs:select  — { value, label, selected }  when a pill is added or removed
 */
(function () {
  "use strict";

  var instances = [];

  /**
   * @param {HTMLElement} el  Root .wpsubs-tag-select element.
   */
  function WPSubsTagSelect(el) {
    this.el = el;
    this.multiple = !!el.dataset.multiple;
    this.fieldName = el.dataset.name || "";
    this.field = el.querySelector(".wpsubs-tag-select__field");
    this.input = el.querySelector(".wpsubs-tag-select__input");
    this.dropdown = el.querySelector(".wpsubs-tag-select__dropdown");
    this.list = el.querySelector(".wpsubs-tag-select__list");
    this.emptyEl = el.querySelector(".wpsubs-tag-select__empty");
    this._bind();
    instances.push(this);
  }

  WPSubsTagSelect.prototype.open = function () {
    closeAll(this);
    this.el.classList.add("wpsubs-tag-select--open");
    this._filterItems("");
    if (this.input) this.input.focus();
  };

  WPSubsTagSelect.prototype.close = function () {
    this.el.classList.remove("wpsubs-tag-select--open");
    if (this.input) {
      this.input.value = "";
      this._filterItems("");
    }
  };

  WPSubsTagSelect.prototype.isOpen = function () {
    return this.el.classList.contains("wpsubs-tag-select--open");
  };

  /** Show/hide dropdown items based on query, always hiding selected ones. */
  WPSubsTagSelect.prototype._filterItems = function (query) {
    var q = query.trim().toLowerCase();
    var items = this.list ? this.list.querySelectorAll(".wpsubs-tag-select__item") : [];
    var visible = 0;
    items.forEach(function (item) {
      if (item.hasAttribute("data-selected")) {
        item.style.display = "none";
        return;
      }
      var text = item.textContent.toLowerCase();
      var match = !q || text.indexOf(q) !== -1;
      item.style.display = match ? "" : "none";
      if (match) visible++;
    });
    if (this.emptyEl) {
      this.emptyEl.style.display = visible === 0 ? "" : "none";
    }
  };

  /** Add a pill for the given dropdown item. */
  WPSubsTagSelect.prototype._addPill = function (item) {
    var value = item.dataset.value !== undefined ? item.dataset.value : "";
    var label = item.textContent.trim();

    // For single-select, remove the existing pill first.
    if (!this.multiple) {
      var existing = this.el.querySelectorAll(".wpsubs-tag-select__pill");
      var self = this;
      existing.forEach(function (p) {
        self._removePillEl(p, false);
      });
    }

    // Build the pill.
    var pill = document.createElement("span");
    pill.className = "wpsubs-tag-select__pill";
    pill.dataset.value = value;

    var pillLabel = document.createElement("span");
    pillLabel.className = "wpsubs-tag-select__pill-label";
    pillLabel.textContent = label;

    var removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "wpsubs-tag-select__pill-remove";
    removeBtn.setAttribute("aria-label", "Remove " + label);
    removeBtn.innerHTML = "&#x2715;"; // ×

    pill.appendChild(pillLabel);
    pill.appendChild(removeBtn);

    // Insert the pill before the text input.
    if (this.input) {
      this.field.insertBefore(pill, this.input);
    } else {
      this.field.appendChild(pill);
    }

    // Mark dropdown item as selected so it stays hidden.
    item.setAttribute("data-selected", "");
    item.style.display = "none";

    this._syncHiddenInputs();
    this._updateInputPlaceholder();

    if (this.input) this.input.value = "";
    this._filterItems("");

    this.el.dispatchEvent(
      new CustomEvent("wpsubs:select", {
        bubbles: true,
        detail: { value: value, label: label, selected: true },
      }),
    );
  };

  /** Remove a pill element. Pass sync=true to update hidden inputs (default). */
  WPSubsTagSelect.prototype._removePillEl = function (pill, sync) {
    var value = pill.dataset.value !== undefined ? pill.dataset.value : "";
    var label = pill.querySelector(".wpsubs-tag-select__pill-label");
    var labelText = label ? label.textContent.trim() : value;

    pill.parentNode.removeChild(pill);

    // Un-mark the corresponding dropdown item.
    var item = this.list
      ? this.list.querySelector('.wpsubs-tag-select__item[data-value="' + CSS.escape(value) + '"]')
      : null;
    if (item) {
      item.removeAttribute("data-selected");
    }

    if (sync !== false) {
      this._syncHiddenInputs();
      this._updateInputPlaceholder();
      this._filterItems(this.input ? this.input.value : "");

      this.el.dispatchEvent(
        new CustomEvent("wpsubs:select", {
          bubbles: true,
          detail: { value: value, label: labelText, selected: false },
        }),
      );
    }
  };

  /** Rebuild hidden inputs to match current pills. */
  WPSubsTagSelect.prototype._syncHiddenInputs = function () {
    var existing = this.el.querySelectorAll("input[data-ts-val]");
    var fieldName = existing.length > 0 ? existing[0].name : this.fieldName + (this.multiple ? "[]" : "");

    existing.forEach(function (inp) {
      inp.parentNode.removeChild(inp);
    });

    var pills = this.el.querySelectorAll(".wpsubs-tag-select__pill");
    var self = this;

    if (this.multiple) {
      if (pills.length === 0) {
        // Empty sentinel so the form field is always present on submit.
        var sentinel = document.createElement("input");
        sentinel.type = "hidden";
        sentinel.name = fieldName;
        sentinel.value = "";
        sentinel.setAttribute("data-ts-val", "");
        self.el.appendChild(sentinel);
      } else {
        pills.forEach(function (pill) {
          var inp = document.createElement("input");
          inp.type = "hidden";
          inp.name = fieldName;
          inp.value = pill.dataset.value !== undefined ? pill.dataset.value : "";
          inp.setAttribute("data-ts-val", "");
          self.el.appendChild(inp);
        });
      }
    } else {
      var inp = document.createElement("input");
      inp.type = "hidden";
      inp.name = fieldName;
      inp.value = pills.length > 0 && pills[0].dataset.value !== undefined ? pills[0].dataset.value : "";
      inp.setAttribute("data-ts-val", "");
      self.el.appendChild(inp);
    }
  };

  /** Show placeholder only when there are no pills. */
  WPSubsTagSelect.prototype._updateInputPlaceholder = function () {
    if (!this.input) return;
    var pills = this.el.querySelectorAll(".wpsubs-tag-select__pill");
    this.input.placeholder = pills.length === 0 ? this.el.dataset.placeholder || "" : "";
  };

  WPSubsTagSelect.prototype._bind = function () {
    var self = this;

    if (self.field) {
      self.field.addEventListener("click", function (e) {
        e.stopPropagation();
        var removeBtn = e.target.closest(".wpsubs-tag-select__pill-remove");
        if (removeBtn) {
          var pill = removeBtn.closest(".wpsubs-tag-select__pill");
          if (pill) self._removePillEl(pill);
          return;
        }
        self.open();
      });
    }

    if (self.input) {
      self.input.addEventListener("input", function () {
        if (!self.isOpen()) self.open();
        self._filterItems(self.input.value);
      });
    }

    if (self.dropdown) {
      self.dropdown.addEventListener("click", function (e) {
        e.stopPropagation();
        var item = e.target.closest(".wpsubs-tag-select__item");
        if (!item || item.hasAttribute("data-disabled")) return;
        self._addPill(item);
        if (!self.multiple) self.close();
      });
    }
  };

  function closeAll(except) {
    instances.forEach(function (inst) {
      if (inst !== except) inst.close();
    });
  }

  /**
   * Initialise all un-initialised .wpsubs-tag-select elements under root.
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document).querySelectorAll(".wpsubs-tag-select:not([data-ts-init])").forEach(function (el) {
      el.setAttribute("data-ts-init", "1");
      new WPSubsTagSelect(el);
    });
  }

  document.addEventListener("click", function () {
    closeAll();
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeAll();
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      init();
    });
  } else {
    init();
  }

  // Public API
  window.WPSubsTagSelect = { init: init };
})();
