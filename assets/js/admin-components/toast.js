/**
 * WPSubsToast — transient confirmation, bottom-right.
 *
 * Usage:
 *   WPSubsToast.show("Saved");                 // green tick
 *   WPSubsToast.show("Could not save", "error"); // red cross
 *
 * For something that already happened and needs no decision. Anything the
 * merchant has to act on belongs in the page: a toast leaves on its own and
 * takes its message with it.
 *
 * Markup is built here rather than templated, so any script can raise one
 * without the page having to reserve a slot for it.
 */
(function () {
  "use strict";

  var LIFETIME = 4000;

  /**
   * The shared bottom-right stack, created on first use.
   *
   * @return {HTMLElement} Host element.
   */
  function host() {
    var el = document.querySelector(".wpsubs-toast-host");
    if (!el) {
      el = document.createElement("div");
      el.className = "wpsubs-toast-host";
      // Announced politely: a confirmation should not interrupt what a screen
      // reader is already saying.
      el.setAttribute("aria-live", "polite");
      el.setAttribute("aria-atomic", "false");
      document.body.appendChild(el);
    }
    return el;
  }

  /**
   * Tick or cross, as an inline SVG so it needs no icon font.
   *
   * @param {string} type "success" or "error".
   * @return {HTMLElement} Icon element.
   */
  function icon(type) {
    var wrap = document.createElement("span");
    wrap.className = "wpsubs-toast__icon";
    wrap.setAttribute("aria-hidden", "true");
    wrap.innerHTML =
      "error" === type
        ? '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 2l8 8M10 2l-8 8"/></svg>'
        : '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 6.5l3 3 6-6"/></svg>';
    return wrap;
  }

  /**
   * Raise a toast.
   *
   * @param {string} message Text to show.
   * @param {string} [type]  "success" (default) or "error".
   * @return {?HTMLElement} The toast, or null when there is nothing to say.
   */
  function show(message, type) {
    if (!message) {
      return null;
    }

    var toast = document.createElement("div");
    toast.className = "wpsubs-toast" + ("error" === type ? " wpsubs-toast--error" : "");

    var text = document.createElement("span");
    text.className = "wpsubs-toast__text";
    text.textContent = message;

    toast.appendChild(icon(type));
    toast.appendChild(text);
    host().appendChild(toast);

    // Nothing to trigger: the toast is visible as soon as it is in the DOM,
    // and the entry keyframe plays over that. Leaving is a class the CSS may
    // or may not animate; the removal below happens either way.
    window.setTimeout(function () {
      toast.classList.add("is-out");
      window.setTimeout(function () {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 260);
    }, LIFETIME);

    return toast;
  }

  window.WPSubsToast = { show: show };
})();
