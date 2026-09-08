// * ----- Disable Enforce Login based on Guest Checkout Setting ----- * //
jQuery(document).ready(($) => {
  const guestCheckout = $("#wp_subscription_allow_guest_checkout");
  const enforceLogin = $("#wp_subscription_enforce_login");

  function toggleEnforceLogin() {
    enforceLogin.prop("disabled", !guestCheckout.is(":checked"));
  }

  // Initial state
  toggleEnforceLogin();

  // On change
  guestCheckout.on("change", toggleEnforceLogin);
});
// * ----- Disable Enforce Login based on Guest Checkout Setting ----- * //

/**
 * Sidebar tabs.
 *
 * The tabs are real links to `?tab=`, so without this file they still work —
 * one page load per tab. This only removes the reload, and keeps three things
 * in step with the panel on screen:
 *
 *   - the address bar, so the tab can be linked and reloaded;
 *   - `_wp_http_referer`, which is what `options.php` redirects back to after a
 *     save, and is rendered once at page load with whatever tab was open then;
 *   - `aria-selected`, which is how the tablist is read out.
 */
(function () {
  "use strict";

  const form = document.querySelector("[data-subscrpt-settings]");

  if (!form) {
    return;
  }

  const tabs = Array.prototype.slice.call(form.querySelectorAll("[data-subscrpt-tab]"));
  const panels = Array.prototype.slice.call(form.querySelectorAll("[data-subscrpt-panel]"));

  if (!tabs.length) {
    return;
  }

  function activate(group, pushUrl) {
    const tab = tabs.find((el) => el.dataset.subscrptTab === group);

    if (!tab) {
      return;
    }

    tabs.forEach((el) => {
      const isActive = el.dataset.subscrptTab === group;
      el.classList.toggle("is-active", isActive);
      el.setAttribute("aria-selected", isActive ? "true" : "false");
    });

    panels.forEach((el) => {
      el.hidden = el.dataset.subscrptPanel !== group;
    });

    if (pushUrl && window.history && window.history.replaceState) {
      const url = new URL(tab.href, window.location.origin);
      window.history.replaceState({ subscrptTab: group }, "", url.toString());

      // Keep the save redirect pointing at the tab actually on screen.
      const referer = form.querySelector('input[name="_wp_http_referer"]');
      if (referer) {
        referer.value = url.pathname + url.search;
      }
    }
  }

  form.addEventListener("click", function (event) {
    const tab = event.target.closest("[data-subscrpt-tab]");

    if (!tab || !form.contains(tab)) {
      return;
    }

    event.preventDefault();
    activate(tab.dataset.subscrptTab, true);
    tab.focus();
  });

  // Arrow-key navigation, which is what a vertical tablist is expected to do.
  form.addEventListener("keydown", function (event) {
    const tab = event.target.closest("[data-subscrpt-tab]");

    if (!tab || !form.contains(tab)) {
      return;
    }

    const index = tabs.indexOf(tab);
    let next = null;

    if (event.key === "ArrowDown" || event.key === "ArrowRight") {
      next = tabs[(index + 1) % tabs.length];
    } else if (event.key === "ArrowUp" || event.key === "ArrowLeft") {
      next = tabs[(index - 1 + tabs.length) % tabs.length];
    } else if (event.key === "Home") {
      next = tabs[0];
    } else if (event.key === "End") {
      next = tabs[tabs.length - 1];
    }

    if (!next) {
      return;
    }

    event.preventDefault();
    activate(next.dataset.subscrptTab, true);
    next.focus();
  });
})();
