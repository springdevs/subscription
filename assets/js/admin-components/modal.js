/**
 * WPSubsModal — reusable centered dialog over a dimmed backdrop.
 *
 * Behaviour only; the visual comes from the .wpsubs-modal CSS. A modal is a
 * `.wpsubs-modal[hidden]` element with an id. Any control opens it via
 * `data-wpsubs-modal-open="<modal-id>"`; any control inside closes it via
 * `data-wpsubs-modal-close` (the backdrop and the header/footer buttons use it).
 * Escape also closes. No per-page wiring needed.
 *
 * A modal with `data-wpsubs-modal-autoopen` opens automatically on load (e.g. the
 * Pro-upgrade preview modals). Opening locks body scroll; it's restored when the
 * last open modal closes.
 *
 * Usage:
 *   PHP: wpsubs_render_modal( $args )  — renders the markup
 *   JS:  auto-inits; WPSubsModal.open(id) / .close(id) available programmatically
 *
 * Events fired on the modal element (bubbles):
 *   wpsubs:modal:open / wpsubs:modal:close
 */
(function () {
  "use strict";

  /**
   * @param {string|HTMLElement} target Modal id or element.
   * @return {HTMLElement|null}
   */
  function resolve(target) {
    if (target instanceof HTMLElement) return target;
    return document.getElementById(String(target));
  }

  function open(target) {
    var modal = resolve(target);
    if (!modal) return;
    modal.hidden = false;
    document.body.style.overflow = "hidden";
    modal.dispatchEvent(new CustomEvent("wpsubs:modal:open", { bubbles: true }));
    var focusable = modal.querySelector("input, textarea, select, button:not([data-wpsubs-modal-close])");
    if (focusable) focusable.focus();
  }

  function close(target) {
    var modal = resolve(target);
    if (!modal) return;
    modal.hidden = true;
    // Restore body scroll only when no modal remains open.
    if (!document.querySelector(".wpsubs-modal:not([hidden])")) {
      document.body.style.overflow = "";
    }
    modal.dispatchEvent(new CustomEvent("wpsubs:modal:close", { bubbles: true }));
  }

  function closeAll() {
    document.querySelectorAll(".wpsubs-modal:not([hidden])").forEach(function (m) {
      close(m);
    });
  }

  // Delegated open/close — works for markup added after load too.
  document.addEventListener("click", function (e) {
    var opener = e.target.closest("[data-wpsubs-modal-open]");
    if (opener) {
      e.preventDefault();
      open(opener.getAttribute("data-wpsubs-modal-open"));
      return;
    }
    var closer = e.target.closest("[data-wpsubs-modal-close]");
    if (closer) {
      e.preventDefault();
      var modal = closer.closest(".wpsubs-modal");
      if (modal) close(modal);
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeAll();
  });

  /**
   * Open any modal flagged to auto-open on load.
   *
   * @param {Document|HTMLElement} [root]
   */
  function init(root) {
    (root || document).querySelectorAll(".wpsubs-modal[data-wpsubs-modal-autoopen]").forEach(function (modal) {
      open(modal);
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
  window.WPSubsModal = { open: open, close: close, init: init };
})();
