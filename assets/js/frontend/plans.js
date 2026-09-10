/**
 * Storefront plan selector (free) — simple products.
 *
 * Pick a plan group (radio card), then a term (buttons). The chosen plan-term id
 * is written to a hidden field that posts with add-to-cart. Simple products only;
 * Pro adds variable-product support + extra plan types on top. Pure DOM, no API.
 */
(function () {
  "use strict";

  var box = document.querySelector("[data-subscrpt-buybox]");
  if (!box) {
    return;
  }

  /**
   * Write the chosen plan-term id into the hidden field that posts with
   * add-to-cart.
   */
  function syncPlanId() {
    var hidden = box.querySelector("[data-subscrpt-plan-id]");
    if (!hidden) {
      return;
    }
    var checked = box.querySelector('input[name="subscrpt_plan_group"]:checked');
    var card = checked ? checked.closest("[data-subscrpt-card]") : null;
    var planId = "";
    if (card) {
      var activeBtn = card.querySelector("[data-subscrpt-term-btn].is-active");
      if (activeBtn) {
        planId = activeBtn.getAttribute("data-term-id");
      } else if (card.hasAttribute("data-subscrpt-single-term")) {
        planId = card.getAttribute("data-subscrpt-single-term");
      }
    }
    hidden.value = planId || "";
  }

  // Selecting a card (radio) marks it and syncs the posted plan id.
  box.addEventListener("change", function (e) {
    if (e.target.name !== "subscrpt_plan_group") {
      return;
    }
    box.querySelectorAll("[data-subscrpt-card]").forEach(function (card) {
      card.classList.remove("is-selected");
    });
    var card = e.target.closest("[data-subscrpt-card]");
    if (card) {
      card.classList.add("is-selected");
    }
    syncPlanId();
  });

  // Choosing a term button updates that card's note and selects the card.
  box.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-term-btn]");
    if (!btn) {
      return;
    }
    e.preventDefault();
    var wrap = btn.closest("[data-subscrpt-card]");
    if (!wrap) {
      return;
    }

    wrap.querySelectorAll("[data-subscrpt-term-btn]").forEach(function (b) {
      b.classList.remove("is-active");
    });
    btn.classList.add("is-active");

    var noteEl = wrap.querySelector("[data-subscrpt-note]");
    if (noteEl) {
      // Note is server-built HTML (may include a struck-through regular price).
      noteEl.innerHTML = btn.getAttribute("data-note") || "";
    }

    var radio = wrap.querySelector('input[name="subscrpt_plan_group"]');
    if (radio && !radio.checked) {
      radio.checked = true;
      radio.dispatchEvent(new Event("change", { bubbles: true }));
    }
    syncPlanId();
  });

  // Initialise the hidden plan id from the pre-selected (first) card.
  syncPlanId();
})();
