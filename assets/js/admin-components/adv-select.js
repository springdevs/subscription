/**
 * WPSubscription admin UI components.
 *
 * WPSubsAdvSelect — styled dropdown replacing native <select>.
 *
 * Usage:
 *   PHP: wpsubs_render_adv_select( $args )  — renders the HTML
 *   JS:  WPSubsAdvSelect.init()             — auto-inits all .wpsubs-adv-select elements
 *
 * Events fired on the root element (bubbles):
 *   wpsubs:select  — { value, label }  when user picks an item
 */
(function () {
  "use strict";

  var instances = [];

  /**
   * @param {HTMLElement} el  Root .wpsubs-adv-select element.
   */
  function WPSubsAdvSelect(el) {
    this.el = el;
    this.trigger = el.querySelector(".wpsubs-adv-select__trigger");
    this.label = el.querySelector(".wpsubs-adv-select__label");
    this.menu = el.querySelector(".wpsubs-adv-select__menu");
    this.input = el.querySelector('input[type="hidden"]');
    this._bind();
    instances.push(this);
  }

  WPSubsAdvSelect.prototype.open = function () {
    closeAll(this);
    this.el.classList.add("wpsubs-adv-select--open");
    if (this.trigger) this.trigger.setAttribute("aria-expanded", "true");
  };

  WPSubsAdvSelect.prototype.close = function () {
    this.el.classList.remove("wpsubs-adv-select--open");
    if (this.trigger) this.trigger.setAttribute("aria-expanded", "false");
  };

  WPSubsAdvSelect.prototype.isOpen = function () {
    return this.el.classList.contains("wpsubs-adv-select--open");
  };

  /**
   * Programmatically select a value.
   *
   * @param {string} value
   * @param {string} label  Display text shown in trigger. Defaults to value.
   */
  WPSubsAdvSelect.prototype.select = function (value, label) {
    if (this.input) this.input.value = value;
    if (this.label) this.label.textContent = label || value;
    this.el.dispatchEvent(
      new CustomEvent("wpsubs:select", {
        bubbles: true,
        detail: { value: value, label: label || value },
      }),
    );
  };

  /** Reset trigger label back to placeholder. */
  WPSubsAdvSelect.prototype.reset = function () {
    var placeholder = this.el.dataset.placeholder || "";
    if (this.input) this.input.value = this.el.dataset.defaultValue || "";
    if (this.label && placeholder) this.label.textContent = placeholder;
  };

  WPSubsAdvSelect.prototype._bind = function () {
    var self = this;

    if (self.trigger) {
      self.trigger.addEventListener("click", function (e) {
        e.stopPropagation();
        self.isOpen() ? self.close() : self.open();
      });
    }

    if (self.menu) {
      self.menu.addEventListener("click", function (e) {
        var item = e.target.closest(".wpsubs-adv-select__item");
        if (!item || item.hasAttribute("data-disabled")) return;

        var value = item.dataset.value !== undefined ? item.dataset.value : "";
        var labelEl = item.querySelector(".wpsubs-adv-select__item-label");
        var label = labelEl ? labelEl.textContent.trim() : item.textContent.trim();
        var confirmMsg = item.dataset.confirm || "";

        if (confirmMsg && !window.confirm(confirmMsg)) return;

        self.close();
        self.select(value, label);
      });
    }
  };

  function closeAll(except) {
    instances.forEach(function (inst) {
      if (inst !== except) inst.close();
    });
  }

  /**
   * Initialise all un-initialised .wpsubs-adv-select elements under root.
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document).querySelectorAll(".wpsubs-adv-select:not([data-adv-init])").forEach(function (el) {
      el.setAttribute("data-adv-init", "1");
      new WPSubsAdvSelect(el);
    });
  }

  // Global: outside click and Escape close all
  document.addEventListener("click", function () {
    closeAll();
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeAll();
  });

  // Auto-init on DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      init();
    });
  } else {
    init();
  }

  // Public API
  window.WPSubsAdvSelect = { init: init };
})();
