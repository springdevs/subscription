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
 * Settings rail.
 *
 * One level: the rail picks a section (`?cat=`) and its panel is shown. The
 * items are real links, so without this file they still work — one page load
 * per click. This only removes the reload, and keeps three things in step with
 * what is on screen:
 *
 *   - the address bar, so a section can be linked and reloaded;
 *   - `_wp_http_referer`, which is what `options.php` redirects back to after a
 *     save, and is rendered once at page load with whatever section was open then;
 *   - `aria-current`, which is how the rail is read out.
 *
 * Every panel is in the DOM at all times (a panel that is not rendered is a
 * field that does not save); switching only toggles which are hidden.
 */
(function () {
  "use strict";

  const form = document.querySelector("[data-subscrpt-settings]");

  if (!form) {
    return;
  }

  const catItems = Array.prototype.slice.call(form.querySelectorAll(".wpsubs-vnav__item[data-subscrpt-cat]"));
  const panels = Array.prototype.slice.call(form.querySelectorAll("[data-subscrpt-panel]"));

  if (!catItems.length) {
    return;
  }

  function syncUrl(href) {
    if (!href || !window.history || !window.history.replaceState) {
      return;
    }
    const url = new URL(href, window.location.origin);
    window.history.replaceState({}, "", url.toString());

    const referer = form.querySelector('input[name="_wp_http_referer"]');
    if (referer) {
      referer.value = url.pathname + url.search;
    }
  }

  function activateCat(cat, push) {
    let href = null;

    catItems.forEach((el) => {
      const on = el.dataset.subscrptCat === cat;
      el.classList.toggle("is-active", on);
      el.setAttribute("aria-current", on ? "page" : "false");
      if (on) {
        href = el.href;
      }
    });

    panels.forEach((el) => {
      el.hidden = el.dataset.subscrptPanel !== cat;
    });

    if (push) {
      syncUrl(href);
    }
  }

  form.addEventListener("click", function (event) {
    const catItem = event.target.closest(".wpsubs-vnav__item[data-subscrpt-cat]");
    if (catItem && form.contains(catItem)) {
      event.preventDefault();
      activateCat(catItem.dataset.subscrptCat, true);
      catItem.focus();
    }
  });

  // Arrow-key navigation for the rail.
  form.addEventListener("keydown", function (event) {
    const isNav =
      event.key === "ArrowDown" ||
      event.key === "ArrowUp" ||
      event.key === "ArrowLeft" ||
      event.key === "ArrowRight" ||
      event.key === "Home" ||
      event.key === "End";
    if (!isNav) {
      return;
    }

    const catItem = event.target.closest(".wpsubs-vnav__item[data-subscrpt-cat]");
    if (!catItem || !form.contains(catItem)) {
      return;
    }

    const index = catItems.indexOf(catItem);
    let next = null;

    if (event.key === "ArrowDown" || event.key === "ArrowRight") {
      next = catItems[(index + 1) % catItems.length];
    } else if (event.key === "ArrowUp" || event.key === "ArrowLeft") {
      next = catItems[(index - 1 + catItems.length) % catItems.length];
    } else if (event.key === "Home") {
      next = catItems[0];
    } else if (event.key === "End") {
      next = catItems[catItems.length - 1];
    }

    if (!next || next === catItem) {
      return;
    }

    event.preventDefault();
    activateCat(next.dataset.subscrptCat, true);
    next.focus();
  });
})();
