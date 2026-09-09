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
 * Settings sidebar + tabs.
 *
 * Two levels: the sidebar picks a broad category (`?cat=`), and within a
 * category the tabs pick a group (`?tab=`). Both are real links, so without
 * this file they still work — one page load per click. This only removes the
 * reload, and keeps three things in step with what is on screen:
 *
 *   - the address bar, so a view can be linked and reloaded;
 *   - `_wp_http_referer`, which is what `options.php` redirects back to after a
 *     save, and is rendered once at page load with whatever view was open then;
 *   - `aria-selected` / `aria-current`, which is how the nav is read out.
 *
 * Every category's tab list and every panel is in the DOM at all times (a panel
 * that is not rendered is a field that does not save); switching only toggles
 * which are hidden.
 */
(function () {
  "use strict";

  const form = document.querySelector("[data-subscrpt-settings]");

  if (!form) {
    return;
  }

  const catItems = Array.prototype.slice.call(form.querySelectorAll(".wpsubs-vnav__item[data-subscrpt-cat]"));
  const tabLists = Array.prototype.slice.call(form.querySelectorAll("[data-subscrpt-tablist]"));
  const tabs = Array.prototype.slice.call(form.querySelectorAll("[data-subscrpt-tab]"));
  const panels = Array.prototype.slice.call(form.querySelectorAll("[data-subscrpt-panel]"));

  if (!catItems.length) {
    return;
  }

  // Remember the last tab opened in each category, so returning to a category
  // reopens where you left it rather than snapping back to its first tab.
  const lastTabByCat = {};
  const activeCatItem = catItems.find((el) => el.classList.contains("is-active"));
  let currentCat = activeCatItem ? activeCatItem.dataset.subscrptCat : "all";

  function tabsInCat(cat) {
    return tabs.filter(function (el) {
      const list = el.closest("[data-subscrpt-tablist]");
      return list && list.dataset.subscrptTablist === cat;
    });
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

  // Show one group's panel and mark its tab selected within its category.
  function showTab(group) {
    const tab = tabs.find((el) => el.dataset.subscrptTab === group);
    if (!tab) {
      return null;
    }

    const list = tab.closest("[data-subscrpt-tablist]");
    const cat = list ? list.dataset.subscrptTablist : currentCat;

    tabsInCat(cat).forEach((el) => {
      el.setAttribute("aria-selected", el === tab ? "true" : "false");
    });

    panels.forEach((el) => {
      el.hidden = el.dataset.subscrptPanel !== group;
    });

    lastTabByCat[cat] = group;
    return tab.href;
  }

  function activateCat(cat, push) {
    currentCat = cat;

    catItems.forEach((el) => {
      const on = el.dataset.subscrptCat === cat;
      el.classList.toggle("is-active", on);
      el.setAttribute("aria-current", on ? "page" : "false");
    });

    tabLists.forEach((el) => {
      el.hidden = cat === "all" || el.dataset.subscrptTablist !== cat;
    });

    let href = null;

    if (cat === "all") {
      // Stack every panel.
      panels.forEach((el) => {
        el.hidden = false;
      });
      const item = catItems.find((el) => el.dataset.subscrptCat === "all");
      href = item ? item.href : null;
    } else {
      const inCat = tabsInCat(cat);
      const group = lastTabByCat[cat] || (inCat[0] && inCat[0].dataset.subscrptTab);
      href = group ? showTab(group) : null;
    }

    if (push) {
      syncUrl(href);
    }
  }

  // Seed the remembered tab from whatever the server opened on.
  if (currentCat !== "all") {
    const openTab = tabsInCat(currentCat).find((el) => el.getAttribute("aria-selected") === "true");
    if (openTab) {
      lastTabByCat[currentCat] = openTab.dataset.subscrptTab;
    }
  }

  form.addEventListener("click", function (event) {
    const tab = event.target.closest("[data-subscrpt-tab]");
    if (tab && form.contains(tab)) {
      event.preventDefault();
      syncUrl(showTab(tab.dataset.subscrptTab));
      tab.focus();
      return;
    }

    const catItem = event.target.closest(".wpsubs-vnav__item[data-subscrpt-cat]");
    if (catItem && form.contains(catItem)) {
      event.preventDefault();
      activateCat(catItem.dataset.subscrptCat, true);
      catItem.focus();
    }
  });

  // Arrow-key navigation for both the vertical sidebar and the horizontal tabs.
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

    const tab = event.target.closest("[data-subscrpt-tab]");
    const catItem = event.target.closest(".wpsubs-vnav__item[data-subscrpt-cat]");

    let list;
    let current;
    let move;

    if (tab && form.contains(tab)) {
      list = tabsInCat(currentCat);
      current = tab;
      move = (next) => {
        syncUrl(showTab(next.dataset.subscrptTab));
        next.focus();
      };
    } else if (catItem && form.contains(catItem)) {
      list = catItems;
      current = catItem;
      move = (next) => {
        activateCat(next.dataset.subscrptCat, true);
        next.focus();
      };
    } else {
      return;
    }

    if (!list.length) {
      return;
    }

    const index = list.indexOf(current);
    let next = null;

    if (event.key === "ArrowDown" || event.key === "ArrowRight") {
      next = list[(index + 1) % list.length];
    } else if (event.key === "ArrowUp" || event.key === "ArrowLeft") {
      next = list[(index - 1 + list.length) % list.length];
    } else if (event.key === "Home") {
      next = list[0];
    } else if (event.key === "End") {
      next = list[list.length - 1];
    }

    if (!next || next === current) {
      return;
    }

    event.preventDefault();
    move(next);
  });
})();
