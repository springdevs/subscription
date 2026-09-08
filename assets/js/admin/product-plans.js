/**
 * Product editor — plan view (free, simple products).
 *
 * Toggles between the plan view and the classic settings, and connects /
 * detaches the product to a Recurring plan group via the wpsubscription/v1
 * REST API. Relies on the shared admin components (adv-select) being enqueued.
 */
(function () {
  "use strict";

  var cfg = window.subscrptProductPlans || {};
  var i18n = cfg.i18n || {};

  // Step 1 group details captured while the wizard is open. The group is not
  // created until step 2 (the plan) is submitted, so abandoning either step
  // creates nothing.
  var pendingGroup = null;

  // Set when adding a plan to an already-existing (empty) group, so the term
  // modal creates a plan against it instead of running the new-group wizard.
  var pendingExistingGroupId = "";

  /**
   * Call a plan REST endpoint.
   *
   * @param {string} method HTTP verb.
   * @param {string} path   Path under the /plans base.
   * @param {Object} [body] JSON body.
   * @return {Promise<Object>}
   */
  function api(method, path, body) {
    return fetch(cfg.restUrl + path, {
      method: method,
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": cfg.nonce,
      },
      credentials: "same-origin",
      body: body ? JSON.stringify(body) : undefined,
    }).then(function (res) {
      if (!res.ok) {
        return res.json().then(function (data) {
          throw new Error((data && data.message) || "");
        });
      }
      return res.status === 204 ? {} : res.json();
    });
  }

  function root() {
    return document.querySelector("[data-subscrpt-product-plans]");
  }

  /**
   * The classic-settings pane for a plan-view wrapper. Free renders its own
   * ([data-subscrpt-classic-view]); with Pro active the classic pane is Pro's
   * `.subscrpt-classic-fields` sibling inside the panel.
   *
   * @param {HTMLElement} el The [data-subscrpt-product-plans] wrapper.
   * @return {HTMLElement|null}
   */
  function classicPane(el) {
    var panel = el.closest("#sdevs_subscription_options") || el;
    return panel.querySelector("[data-subscrpt-classic-view]") || panel.querySelector(".subscrpt-classic-fields");
  }

  /**
   * Show either the plan view or the classic settings.
   *
   * @param {HTMLElement} el          The wrapper.
   * @param {boolean}     showClassic Whether classic settings are shown.
   */
  function setView(el, showClassic) {
    var planView = el.querySelector("[data-subscrpt-plan-view]");
    var classic = classicPane(el);
    if (planView) {
      planView.style.display = showClassic ? "none" : "";
    }
    if (classic) {
      classic.style.display = showClassic ? "" : "none";
    }
    var showClassicBtn = el.querySelector("[data-subscrpt-show-classic]");
    var showPlanBtn = el.querySelector("[data-subscrpt-show-plan]");
    if (showClassicBtn) {
      showClassicBtn.style.display = showClassic ? "none" : "";
    }
    if (showPlanBtn) {
      showPlanBtn.style.display = showClassic ? "" : "none";
    }
  }

  // View toggle.
  document.addEventListener("click", function (e) {
    var toClassic = e.target.closest("[data-subscrpt-show-classic]");
    var toPlan = e.target.closest("[data-subscrpt-show-plan]");
    if (!toClassic && !toPlan) {
      return;
    }
    var el = root();
    if (el) {
      setView(el, !!toClassic);
    }
  });

  /**
   * Wire the "Generate Checkout Link" modal: read the adv-select choices (link
   * type / variation / plan), live-preview the composed link, and copy it. Plan
   * options are one adv-select per variation; only the active one is shown.
   */
  function setupCheckoutLink() {
    var modal = document.getElementById("subscrpt-checkout-link");
    if (!modal) {
      return;
    }
    var data;
    try {
      data = JSON.parse(modal.getAttribute("data-subscrpt-checkout-data") || "{}");
    } catch (err) {
      return;
    }
    if (!data.contexts || !data.contexts.length) {
      return;
    }

    // Ensure adv-selects inside the (relocated) modal are initialised.
    if (window.WPSubsAdvSelect) {
      window.WPSubsAdvSelect.init(modal);
    }

    var typeInput = modal.querySelector("[data-subscrpt-checkout-type] input[type=hidden]");
    var varAdv = modal.querySelector("[data-subscrpt-checkout-variation]");
    var varInput = varAdv ? varAdv.querySelector("input[type=hidden]") : null;
    var planWraps = modal.querySelectorAll("[data-subscrpt-checkout-plan-wrap]");
    var preview = modal.querySelector("[data-subscrpt-checkout-preview]");
    var warning = modal.querySelector("[data-subscrpt-checkout-warning]");
    var refCart = modal.querySelector('[data-subscrpt-checkout-ref="cart"]');
    var refCheckout = modal.querySelector('[data-subscrpt-checkout-ref="checkout"]');
    var copyBtns = modal.querySelectorAll("[data-subscrpt-checkout-copy]");

    function activeVid() {
      if (varInput && varInput.value !== "") {
        return varInput.value;
      }
      return String(data.contexts[0].vid);
    }

    function contextByVid(vid) {
      for (var i = 0; i < data.contexts.length; i++) {
        if (String(data.contexts[i].vid) === String(vid)) {
          return data.contexts[i];
        }
      }
      return data.contexts[0];
    }

    function activePlanValue() {
      var vid = activeVid();
      var value = "";
      planWraps.forEach(function (w) {
        if (String(w.getAttribute("data-vid")) === String(vid)) {
          var input = w.querySelector("input[type=hidden]");
          value = input ? input.value : "";
        }
      });
      return value;
    }

    function showActivePlanWrap() {
      var vid = activeVid();
      planWraps.forEach(function (w) {
        w.hidden = String(w.getAttribute("data-vid")) !== String(vid);
      });
    }

    function append(base, params) {
      return base + (base.indexOf("?") === -1 ? "?" : "&") + params.join("&");
    }

    function buildLink() {
      var ctx = contextByVid(activeVid());
      var plan = activePlanValue();
      var planParam = plan !== "" && plan !== "onetime" ? "subscrpt_plan_id=" + encodeURIComponent(plan) : "";

      if (typeInput && typeInput.value === "checkout") {
        // Native WooCommerce checkout-link: /checkout-link/?products=ID:QTY .
        // For variations the target is the variation id itself.
        var target = data.type === "variable" ? ctx.vid : data.productId;
        var checkoutParams = ["products=" + encodeURIComponent(target + ":1")];
        if (planParam) {
          checkoutParams.push(planParam);
        }
        return append(data.checkoutLinkBase, checkoutParams);
      }

      // Classic add-to-cart link on the site root.
      var params = ["add-to-cart=" + encodeURIComponent(data.productId)];
      if (data.type === "variable") {
        params.push("variation_id=" + encodeURIComponent(ctx.vid));
        var attrs = ctx.attrs || {};
        Object.keys(attrs).forEach(function (k) {
          params.push(encodeURIComponent(k) + "=" + encodeURIComponent(attrs[k]));
        });
      }
      if (planParam) {
        params.push(planParam);
      }
      return append(data.cartBase, params);
    }

    function refresh() {
      var ctx = contextByVid(activeVid());
      var isCheckout = typeInput && typeInput.value === "checkout";
      if (warning) {
        warning.style.display = isCheckout && ctx && ctx.hasAny ? "flex" : "none";
      }
      if (refCart) {
        refCart.hidden = isCheckout;
      }
      if (refCheckout) {
        refCheckout.hidden = !isCheckout;
      }
      if (preview) {
        preview.value = buildLink();
      }
    }

    showActivePlanWrap();
    refresh();

    // adv-selects fire a bubbling "wpsubs:select" — recompute on any choice, and
    // re-toggle the plan adv-selects when the variation changes.
    modal.addEventListener("wpsubs:select", function (e) {
      if (varAdv && varAdv.contains(e.target)) {
        showActivePlanWrap();
      }
      refresh();
    });

    copyBtns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var link = preview ? preview.value : "";
        if (!link) {
          return;
        }
        var done = function () {
          var label = btn.querySelector(".dashicons");
          if (label) {
            var prev = label.className;
            label.className = "dashicons dashicons-yes";
            setTimeout(function () {
              label.className = prev;
            }, 1500);
          }
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(link).then(done, function () {
            if (preview) {
              preview.select();
            }
            window.prompt(i18n.copyLinkPrompt || "Copy this link:", link);
          });
        } else {
          if (preview) {
            preview.select();
          }
          window.prompt(i18n.copyLinkPrompt || "Copy this link:", link);
        }
      });
    });
  }

  // On load, default to the plan view — unless the product carries legacy
  // classic settings (data-subscrpt-default-classic), then open classic mode.
  function init() {
    var el = root();
    if (el) {
      setView(el, el.hasAttribute("data-subscrpt-default-classic"));
    }
    detachModals();
    setupCheckoutLink();
    setupWizard();
  }

  /**
   * Move the plan modals out of the WooCommerce product panel to <body>.
   *
   * Rendered in place, they inherit `.woocommerce_options_panel` descendant
   * styles (floated labels, fixed input widths) that break their layout. On
   * the Plans page the same modals sit at top level; relocating here matches
   * that so they render identically. IDs and delegated open/close still work.
   */
  function detachModals() {
    ["subscrpt-create-plan", "subscrpt-term-modal", "subscrpt-checkout-link"].forEach(function (id) {
      var modal = document.getElementById(id);
      if (modal && modal.parentNode !== document.body) {
        document.body.appendChild(modal);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  // Picking a plan group reveals its price table (one block per group).
  // The block's "Connect" button reuses the shared save handler below.
  document.addEventListener("wpsubs:select", function (e) {
    var sel = e.target.closest ? e.target.closest("[data-subscrpt-connect-group]") : null;
    if (!sel) {
      return;
    }
    var card = sel.closest("[data-subscrpt-connect-card]");
    var groupId = String(parseInt(e.detail && e.detail.value, 10) || 0);

    // Prefill the plan's regular prices from WooCommerce's regular price field
    // (product edit page only — that field does not exist elsewhere). Only fills
    // inputs left empty, so a saved or manually-entered price is never overwritten.
    var regularField = document.getElementById("_regular_price");
    var regularVal = regularField ? String(regularField.value).trim() : "";

    card.querySelectorAll("[data-connect-block]").forEach(function (block) {
      var on = block.getAttribute("data-group-id") === groupId;
      block.style.display = on ? "block" : "none";
      if (on && regularVal !== "") {
        block.querySelectorAll('[data-field="regular_price"]').forEach(function (input) {
          if (!String(input.value).trim()) {
            input.value = regularVal;
          }
        });
      }
    });
    var divider = card.querySelector("[data-subscrpt-connect-divider]");
    if (divider) {
      divider.style.display = "0" === groupId ? "none" : "";
    }
    // Reveal the one-time purchase card alongside the picked group's prices
    // (simple products: it sits above the connect card while not yet connected).
    var wrap = document.querySelector("[data-subscrpt-onetime-wrap]");
    if (wrap) {
      wrap.style.display = "0" === groupId ? "none" : "";
    }

    // Picking a plan means this product is a subscription — turn the Enable
    // subscription toggle on if it is still off (the :checked sibling styles the
    // switch; no change event, so the classic-settings pane is left as-is).
    if ("0" !== groupId) {
      var enableToggle = document.getElementById("subscrpt_enable");
      if (enableToggle && !enableToggle.checked) {
        enableToggle.checked = true;
      }
    }
  });

  /* ------------------------------------------------------------------ *
   * Two-step wizard: create a plan group + its first plan without leaving the
   * editor (Pro). "＋ New" opens step 1 (group), which advances to step 2
   * (plan). Neither is written until step 2 is submitted; then both are created
   * atomically and the plan view refreshes in place (unsaved product edits
   * survive). plan-forms.js owns the field markup + collection; the wizard
   * chrome (steps, Back, deferral) is layered on here.
   * ------------------------------------------------------------------ */

  /**
   * Turn the shared modals into a two-step wizard: mark them deferred so
   * plan-forms.js hands values back instead of writing, add a step badge, and
   * relabel the primary buttons (plus a Back control on step 2).
   */
  function setupWizard() {
    var create = document.getElementById("subscrpt-create-plan");
    var term = document.getElementById("subscrpt-term-modal");

    if (create && !create.hasAttribute("data-subscrpt-defer")) {
      create.setAttribute("data-subscrpt-defer", "1");
      injectStep(create, i18n.step1 || "Step 1 of 2");
      var cbtn = create.querySelector("[data-subscrpt-create-plan]");
      if (cbtn) {
        cbtn.textContent = i18n.wizardNext || "Continue";
      }
    }

    if (term && !term.hasAttribute("data-subscrpt-defer")) {
      term.setAttribute("data-subscrpt-defer", "1");
      injectStep(term, i18n.step2 || "Step 2 of 2");
      var sbtn = term.querySelector("[data-subscrpt-term-submit]");
      if (sbtn) {
        sbtn.textContent = i18n.wizardCreate || "Create";
        if (!term.querySelector("[data-subscrpt-wizard-back]")) {
          var back = document.createElement("button");
          back.type = "button";
          back.className = "wpsubs-btn wpsubs-btn--outline";
          back.setAttribute("data-subscrpt-wizard-back", "1");
          back.textContent = i18n.wizardBack || "Back";
          sbtn.parentNode.insertBefore(back, sbtn);
        }
      }
    }
  }

  /**
   * Prepend a "Step N of 2" badge above a modal's title.
   *
   * @param {HTMLElement} modal The modal element.
   * @param {string}      text  Badge text.
   */
  function injectStep(modal, text) {
    var title = modal.querySelector(".wpsubs-modal__title");
    if (!title || (title.parentNode && title.parentNode.querySelector(".subscrpt-wizard-step"))) {
      return;
    }
    var badge = document.createElement("div");
    badge.className = "subscrpt-wizard-step";
    badge.textContent = text;
    badge.style.cssText =
      "font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--wpsubs-brand,#ff4d00);margin:0 0 6px;";
    var parent = title.parentNode;
    if (parent && parent.classList.contains("wpsubs-modal__head")) {
      // Term modal: the title sits directly in the head (a flex row). Wrap it so
      // the badge can sit on its own line above the title.
      var col = document.createElement("div");
      parent.insertBefore(col, title);
      col.appendChild(badge);
      col.appendChild(title);
    } else {
      // Create modal: the title is already inside a text column.
      parent.insertBefore(badge, parent.firstChild);
    }
  }

  // Starting fresh via "＋ New": clear any stashed step-1 details and reset the
  // create modal (Back uses a different control, so it keeps its values).
  document.addEventListener("click", function (e) {
    if (!e.target.closest('[data-wpsubs-modal-open="subscrpt-create-plan"]')) {
      return;
    }
    pendingGroup = null;
    pendingExistingGroupId = "";
    var nameInput = document.getElementById("subscrpt-create-name");
    if (nameInput) {
      nameInput.value = "";
    }
    var recurring = document.querySelector('.subscrpt-type-card[data-subscrpt-type="recurring"]');
    if (recurring && !recurring.classList.contains("is-selected")) {
      recurring.click();
    }
  });

  /**
   * Show/hide the term modal's wizard chrome. In "wizard" mode it is step 2 of
   * new-group creation (step badge + Back). In "single" mode it just adds a
   * plan to an existing group (no step badge, no Back).
   *
   * @param {string} mode "wizard" | "single".
   */
  function setTermModalMode(mode) {
    var term = document.getElementById("subscrpt-term-modal");
    if (!term) {
      return;
    }
    var wizard = mode === "wizard";
    var step = term.querySelector(".subscrpt-wizard-step");
    if (step) {
      step.style.display = wizard ? "" : "none";
    }
    var back = term.querySelector("[data-subscrpt-wizard-back]");
    if (back) {
      back.style.display = wizard ? "" : "none";
    }
    var submit = term.querySelector("[data-subscrpt-term-submit]");
    if (submit) {
      submit.textContent = wizard ? i18n.wizardCreate || "Create" : i18n.addPlan || "Add plan";
    }
  }

  // "Create plan" on an empty group: add a plan directly to that group.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-create-plan-for]");
    if (!btn) {
      return;
    }
    pendingGroup = null;
    pendingExistingGroupId = btn.getAttribute("data-subscrpt-create-plan-for");
    setTermModalMode("single");
    if (window.WPSubsPlanForms && window.WPSubsPlanForms.openTermModalForGroup) {
      window.WPSubsPlanForms.openTermModalForGroup(pendingExistingGroupId, "recurring");
    }
  });

  // Step 1 submitted: stash the group details and advance to step 2 (the plan).
  document.addEventListener("subscrpt:group-step", function (e) {
    var d = e.detail || {};
    pendingGroup = { title: d.title, type: d.type || "recurring" };
    pendingExistingGroupId = "";
    setTermModalMode("wizard");
    if (window.WPSubsPlanForms && window.WPSubsPlanForms.openTermModalForGroup) {
      // Group id 0: it does not exist yet; created on step-2 submit below.
      window.WPSubsPlanForms.openTermModalForGroup(0, pendingGroup.type);
    }
  });

  // Back on step 2: return to step 1 with the entered group details restored.
  document.addEventListener("click", function (e) {
    if (!e.target.closest("[data-subscrpt-wizard-back]")) {
      return;
    }
    if (window.WPSubsPlanForms) {
      window.WPSubsPlanForms.closeModal("subscrpt-term-modal");
      window.WPSubsPlanForms.openModal("subscrpt-create-plan");
    }
    if (pendingGroup) {
      var nameInput = document.getElementById("subscrpt-create-name");
      if (nameInput) {
        nameInput.value = pendingGroup.title || "";
      }
      var card = document.querySelector('.subscrpt-type-card[data-subscrpt-type="' + pendingGroup.type + '"]');
      if (card && !card.classList.contains("is-selected")) {
        card.click();
      }
    }
  });

  // Step 2 submitted. New-group wizard: create the group, then its first plan,
  // atomically. Existing empty group: just create the plan against it.
  document.addEventListener("subscrpt:term-step", function (e) {
    var payload = e.detail && e.detail.payload;
    if (!payload) {
      return;
    }

    if (pendingGroup) {
      var group = pendingGroup;
      api("POST", "/groups", {
        title: group.title,
        type: group.type,
        product_type: 1,
        status: "active",
      })
        .then(function (created) {
          pendingGroup = null;
          var termBody = Object.assign({}, payload, {
            plan_group_id: created.id,
            type: group.type,
          });
          return api("POST", "/terms", termBody).then(function () {
            refreshPlanView(String(created.id));
          });
        })
        .catch(function (err) {
          window.alert((err && err.message) || i18n.connectError);
        });
      return;
    }

    if (pendingExistingGroupId) {
      var gid = pendingExistingGroupId;
      pendingExistingGroupId = "";
      var body = Object.assign({}, payload, { plan_group_id: parseInt(gid, 10) });
      api("POST", "/terms", body)
        .then(function () {
          refreshPlanView(String(gid));
        })
        .catch(function (err) {
          window.alert((err && err.message) || i18n.connectError);
        });
    }
  });

  /**
   * Re-fetch the server-rendered plan view and swap it in place, then re-init
   * the injected adv-selects and (optionally) select a connect group.
   *
   * @param {string} [selectGroupId] Group to auto-select after refresh.
   */
  function refreshPlanView(selectGroupId) {
    var wrap = root();
    if (!wrap) {
      return;
    }
    var view = wrap.querySelector("[data-subscrpt-plan-view]");
    var productId = parseInt(wrap.getAttribute("data-product-id"), 10);
    if (!view || !productId) {
      return;
    }
    api("GET", "/product-view/" + productId)
      .then(function (res) {
        view.innerHTML = res.html || "";
        if (window.WPSubsAdvSelect && window.WPSubsAdvSelect.init) {
          window.WPSubsAdvSelect.init(view);
        }
        if (selectGroupId) {
          selectConnectGroup(view, selectGroupId);
        }
      })
      .catch(function (err) {
        window.alert((err && err.message) || i18n.connectError);
      });
  }

  /**
   * Select a connect group in the refreshed view and reveal its price table.
   *
   * @param {HTMLElement} scope   The plan-view container.
   * @param {string}      groupId Group id to select.
   */
  function selectConnectGroup(scope, groupId) {
    var card = scope.querySelector("[data-subscrpt-connect-card]");
    if (!card) {
      return;
    }
    var sel = card.querySelector("[data-subscrpt-connect-group]");
    if (sel) {
      var hidden = sel.querySelector('input[type="hidden"]');
      if (hidden) {
        hidden.value = groupId;
      }
      var label = sel.querySelector(".wpsubs-adv-select__label");
      var item = sel.querySelector('.wpsubs-adv-select__item[data-value="' + groupId + '"]');
      if (label && item) {
        label.textContent = item.textContent.trim();
      }
    }
    card.querySelectorAll("[data-connect-block]").forEach(function (block) {
      block.style.display = block.getAttribute("data-group-id") === String(groupId) ? "block" : "none";
    });
    var divider = card.querySelector("[data-subscrpt-connect-divider]");
    if (divider) {
      divider.style.display = "";
    }
  }

  // Detach the product from a plan group (delete all its relations).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-detach]");
    if (!btn) {
      return;
    }
    if (!window.confirm(i18n.confirmDetach || "Detach this product from the plan?")) {
      return;
    }
    var ids = (btn.getAttribute("data-relation-ids") || "")
      .split(",")
      .map(function (s) {
        return s.trim();
      })
      .filter(Boolean);

    btn.disabled = true;
    Promise.all(
      ids.map(function (id) {
        return api("DELETE", "/relations/" + id);
      }),
    )
      .then(function () {
        refreshPlanView();
      })
      .catch(function (err) {
        btn.disabled = false;
        window.alert(err.message || i18n.connectError);
      });
  });

  /* ------------------------------------------------------------------ *
   * Edit the connected plans of a group: add / remove individual plans
   * (terms) and set each one's per-product price, inline in the card.
   * ------------------------------------------------------------------ */

  /**
   * Toggle a plan card between read and edit mode.
   *
   * @param {HTMLElement} card    The [data-subscrpt-plan-card] element.
   * @param {boolean}     editing Desired mode.
   */
  function cardMode(card, editing) {
    // A simple product has one view/edit pair; a variable product has one edit
    // table per variation (all toggled together), and no chip view.
    card.querySelectorAll(".subscrpt-pe-view").forEach(function (view) {
      view.style.display = editing ? "none" : "flex";
    });
    card.querySelectorAll(".subscrpt-pe-edit").forEach(function (edit) {
      edit.style.display = editing ? "block" : "none";
    });
    card.querySelectorAll(".subscrpt-edit-only").forEach(function (el) {
      el.style.display = editing ? "inline-flex" : "none";
    });
    var toggle = function (sel, show) {
      var el = card.querySelector(sel);
      if (el) {
        el.style.display = show ? "" : "none";
      }
    };
    toggle("[data-subscrpt-edit-prices]", !editing);
    toggle("[data-subscrpt-detach]", !editing);
    toggle("[data-subscrpt-cancel-prices]", editing);
    toggle("[data-subscrpt-save-prices]", editing);
  }

  /** The product-level one-time purchase card (single, product-specific). */
  function oneTimeCard() {
    var wrap = root();
    return wrap ? wrap.querySelector("[data-subscrpt-onetime-card]") : null;
  }

  /**
   * Unlock (snapshot) or re-lock + revert the one-time card's inputs. Only acts
   * when the card is edit-gated (connected + Pro), mirroring the plan card's
   * edit mode; in the connect flow the inputs are already editable.
   *
   * While connected the whole card is hidden and only revealed here on "Edit
   * prices", so the read view shows just the plan chips.
   *
   * @param {boolean} editing Desired mode.
   */
  function setOneTimeEditing(editing) {
    var ot = oneTimeCard();
    if (!ot) {
      return;
    }
    // Reveal / hide the whole card with edit mode (Pro editable, non-Pro upsell).
    var wrap = ot.closest("[data-subscrpt-onetime-wrap]");
    if (wrap) {
      wrap.style.display = editing ? "" : "none";
    }
    // Only the connected + Pro card unlocks its inputs; connect-flow inputs are
    // already editable, non-Pro stays a locked upsell.
    if (!ot.hasAttribute("data-subscrpt-ot-editgated")) {
      return;
    }
    ot.querySelectorAll("[data-subscrpt-onetime-enable], [data-ot-field]").forEach(function (el) {
      if (editing) {
        el.dataset.orig = "checkbox" === el.type ? (el.checked ? "1" : "") : el.value;
        el.disabled = false;
      } else {
        if ("checkbox" === el.type) {
          el.checked = "1" === el.dataset.orig;
        } else {
          el.value = el.dataset.orig || "";
        }
        el.disabled = true;
      }
    });
  }

  /** Enabled/dim a term row's price inputs from its offer toggle. */
  function syncTermRow(row) {
    var on = row.querySelector("[data-subscrpt-term-toggle]");
    var enabled = on ? on.checked : true;
    row.querySelectorAll("[data-field]").forEach(function (input) {
      input.disabled = !enabled;
      input.style.opacity = enabled ? "" : "0.5";
    });
  }

  // Enter edit mode (snapshot for cancel).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-edit-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-plan-card]");
    card.querySelectorAll("[data-subscrpt-term-row]").forEach(function (row) {
      row.querySelectorAll("[data-field]").forEach(function (input) {
        input.dataset.orig = input.value;
      });
      var on = row.querySelector("[data-subscrpt-term-toggle]");
      if (on) {
        on.dataset.orig = on.checked ? "1" : "";
      }
      syncTermRow(row);
    });
    var ven = card.querySelector("[data-subscrpt-var-enable]");
    if (ven) {
      ven.dataset.orig = ven.checked ? "1" : "";
      ven.disabled = false;
    }
    setOneTimeEditing(true);
    cardMode(card, true);
  });

  // A term's offer toggle enables its price inputs.
  document.addEventListener("change", function (e) {
    var toggle = e.target.closest("[data-subscrpt-term-toggle]");
    if (!toggle) {
      return;
    }
    var row = toggle.closest("[data-subscrpt-term-row]");
    if (row) {
      syncTermRow(row);
    }
  });

  // The "Allow one-time purchase" toggle shows / hides its price inputs.
  document.addEventListener("change", function (e) {
    var toggle = e.target.closest("[data-subscrpt-onetime-enable]");
    if (!toggle) {
      return;
    }
    var card = toggle.closest("[data-subscrpt-onetime-card]");
    var body = card && card.querySelector("[data-subscrpt-onetime-body]");
    if (body) {
      body.style.display = toggle.checked ? "" : "none";
    }
  });

  // Cancel: restore snapshot and leave edit mode.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-cancel-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-plan-card]");
    card.querySelectorAll("[data-subscrpt-term-row]").forEach(function (row) {
      row.querySelectorAll("[data-field]").forEach(function (input) {
        input.value = input.dataset.orig || "";
      });
      var on = row.querySelector("[data-subscrpt-term-toggle]");
      if (on) {
        on.checked = "1" === on.dataset.orig;
      }
    });
    var ven = card.querySelector("[data-subscrpt-var-enable]");
    if (ven) {
      ven.checked = "1" === ven.dataset.orig;
      ven.disabled = true;
    }
    setOneTimeEditing(false);
    cardMode(card, false);
  });

  /**
   * Collect the REST calls that persist one plan card's edits (relations +
   * one-time). Shared by the card's own Save button and the product
   * Update/Publish flow so plan edits save through both.
   *
   * @param {HTMLElement} card The [data-subscrpt-plan-card] element.
   * @return {Array<Promise>}
   */
  function collectCardCalls(card) {
    var wrap = root();
    var productId = wrap ? parseInt(wrap.getAttribute("data-product-id"), 10) : 0;

    var fieldVal = function (row, field) {
      var el = row.querySelector('[data-field="' + field + '"]');
      return el ? el.value : "";
    };

    var calls = [];
    card.querySelectorAll("[data-subscrpt-term-row]").forEach(function (row) {
      var planId = parseInt(row.getAttribute("data-plan-id"), 10);
      var vid = parseInt(row.getAttribute("data-vid"), 10) || 0;
      var relId = row.getAttribute("data-relation-id");
      var on = row.querySelector("[data-subscrpt-term-toggle]").checked;

      // "Enable subscription" is per variation: read the owning variation
      // card's toggle so the relation save can flip _subscrpt_enabled for it.
      var vcard = row.closest("[data-subscrpt-variation-card]");
      var venEl = vcard ? vcard.querySelector("[data-subscrpt-var-enable]") : null;
      var enabled = venEl ? venEl.checked : true;

      var data = {
        regular_price: fieldVal(row, "regular_price"),
        sale_price: fieldVal(row, "sale_price"),
        discount_value: 0,
      };

      if (relId) {
        // Existing relation: enable/disable via exclude (never delete here, so
        // it stays consistent with the Plans page). Detach removes everything.
        calls.push(
          api("PUT", "/relations/" + relId, {
            exclude: !on,
            data: data,
            enabled: enabled,
          }),
        );
      } else if (on) {
        // Not connected yet and enabled: create the relation.
        calls.push(
          api("POST", "/relations", {
            plan_id: planId,
            oid: productId,
            vid: vid,
            type: 1,
            status: "active",
            exclude: false,
            data: data,
            enabled: enabled,
          }),
        );
      }
    });

    // One-time purchase saves with the plan Save/Connect — no separate button.
    // It is a row in the price table (data-vid on the row): the simple product's
    // row is vid 0 (saved with the product-level payload), and each variable
    // product variation's row is its vid (collected into a variations map).
    var otRows = card.querySelectorAll("[data-subscrpt-onetime-row]");
    if (otRows.length) {
      var variations = {};
      var simpleOt = null;
      otRows.forEach(function (row) {
        var otVid = parseInt(row.getAttribute("data-vid"), 10) || 0;
        var en = row.querySelector("[data-subscrpt-onetime-enable]");
        var pr = row.querySelector('[data-ot-field="price"]');
        var of = row.querySelector('[data-ot-field="offer"]');
        var vals = {
          enabled: en ? en.checked : false,
          price: pr ? pr.value : "",
          offer: of ? of.value : "",
        };
        if (otVid) {
          variations[otVid] = vals;
        } else {
          simpleOt = vals;
        }
      });
      if (Object.keys(variations).length) {
        calls.push(api("PUT", "/product-onetime/" + productId, { variations: variations }));
      }
      if (simpleOt) {
        calls.push(api("PUT", "/product-onetime/" + productId, simpleOt));
      }
    }

    return calls;
  }

  // Save: persist this card's plan prices, then refresh the plan view in place
  // (no full page reload).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-save-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-plan-card]");

    btn.disabled = true;
    Promise.all(collectCardCalls(card))
      .then(function () {
        refreshPlanView();
      })
      .catch(function (err) {
        btn.disabled = false;
        window.alert(err.message || i18n.connectError);
      });
  });

  /**
   * When the plan view is the active view, copy each variation's plan-view
   * enable toggle into its classic `subscrpt_var[<vid>][enabled]` checkbox so the
   * product's native save persists the plan-mode enable. No-op when the classic
   * view is active (its own checkboxes are authoritative) or for simple products.
   *
   * @param {HTMLElement} wrap The [data-subscrpt-product-plans] wrapper.
   */
  function mirrorPlanEnables(wrap) {
    var planView = wrap.querySelector("[data-subscrpt-plan-view]");
    // Plan view hidden → classic (simple) mode is active; leave classic fields.
    if (!planView || "none" === planView.style.display) {
      return;
    }
    planView.querySelectorAll("[data-subscrpt-variation-card][data-variation-id]").forEach(function (card) {
      var vid = card.getAttribute("data-variation-id");
      var toggle = card.querySelector("[data-subscrpt-var-enable]");
      if (!vid || !toggle) {
        return;
      }
      var classicBox = document.querySelector('input[name="subscrpt_var[' + vid + '][enabled]"]');
      if (classicBox) {
        classicBox.checked = toggle.checked;
      }
    });
  }

  /**
   * When the plan view is active, mirror each one-time-purchase row's price /
   * offer into WooCommerce's native price inputs (General tab `#_regular_price`
   * / `#_sale_price` for a simple product; `variable_regular_price[i]` /
   * `variable_sale_price[i]` for a variation). The one-time price IS the native
   * price, so without this the product's own Update re-saves the stale native
   * fields and reverts the freshly-saved one-time edit.
   *
   * @param {HTMLElement} wrap The [data-subscrpt-product-plans] wrapper.
   */
  function mirrorOnetimePrices(wrap) {
    var planView = wrap.querySelector("[data-subscrpt-plan-view]");
    if (!planView || "none" === planView.style.display) {
      return;
    }
    planView.querySelectorAll("[data-subscrpt-onetime-row]").forEach(function (row) {
      var vid = parseInt(row.getAttribute("data-vid"), 10) || 0;
      var priceEl = row.querySelector('[data-ot-field="price"]');
      var offerEl = row.querySelector('[data-ot-field="offer"]');
      if (!priceEl) {
        return;
      }
      var regular = priceEl.value;
      var sale = offerEl ? offerEl.value : "";

      if (!vid) {
        // Simple product → General tab native price inputs.
        var r = document.getElementById("_regular_price");
        var s = document.getElementById("_sale_price");
        if (r) {
          r.value = regular;
        }
        if (s) {
          s.value = sale;
        }
        return;
      }

      // Variation → find its row index among the variation price inputs (only
      // present when the Variations tab has been loaded; otherwise WooCommerce
      // posts no variation prices to overwrite, so skipping is safe).
      var postId = document.querySelector('input[name^="variable_post_id["][value="' + vid + '"]');
      var match = postId ? postId.getAttribute("name").match(/\[(\d+)\]/) : null;
      if (!match) {
        return;
      }
      var i = match[1];
      var vr = document.querySelector('input[name="variable_regular_price[' + i + ']"]');
      var vs = document.querySelector('input[name="variable_sale_price[' + i + ']"]');
      if (vr) {
        vr.value = regular;
      }
      if (vs) {
        vs.value = sale;
      }
    });
  }

  /* ------------------------------------------------------------------ *
   * Save plan edits with the product's Update/Publish button too, so the
   * merchant does not have to use the plan card's own Save. Any card with an
   * active Save control (a connected card in edit mode, or a picked connect
   * group) is persisted via REST before the product form submits.
   * ------------------------------------------------------------------ */
  (function () {
    var postForm = document.getElementById("post");
    if (!postForm) {
      return;
    }

    var lastSubmitBtn = null;
    var bypass = false;

    // Remember which submit button was used so we can replay it after the async
    // plan save (a programmatic form.submit() would drop the button's name/value).
    document.addEventListener(
      "click",
      function (e) {
        var b = e.target.closest("#publish, #save-post");
        if (b) {
          lastSubmitBtn = b;
        }
      },
      true,
    );

    postForm.addEventListener("submit", function (e) {
      if (bypass) {
        return;
      }
      var wrap = root();
      if (!wrap) {
        return;
      }

      // Enable is mode-aware: when the plan view is the ACTIVE view, mirror each
      // variation's plan-view enable toggle into its classic
      // subscrpt_var[<vid>][enabled] checkbox, so the product's own save persists
      // the enable the admin set in plan view. When the classic (simple) view is
      // active we leave the classic checkboxes untouched — they win. Either way
      // per-variation enable is respected without the two sources clobbering.
      mirrorPlanEnables(wrap);

      // One-time price is the native product price; mirror it into WooCommerce's
      // own price inputs so the product's Update doesn't revert the edit.
      mirrorOnetimePrices(wrap);

      // Cards with a currently-visible Save control have pending plan edits.
      var cards = Array.prototype.filter.call(wrap.querySelectorAll("[data-subscrpt-plan-card]"), function (card) {
        var save = card.querySelector("[data-subscrpt-save-prices]");
        return save && null !== save.offsetParent;
      });
      if (!cards.length) {
        return;
      }

      e.preventDefault();

      var calls = [];
      cards.forEach(function (card) {
        calls = calls.concat(collectCardCalls(card));
      });

      Promise.all(calls)
        .then(function () {
          bypass = true;
          if (lastSubmitBtn) {
            lastSubmitBtn.click();
          } else {
            postForm.submit();
          }
        })
        .catch(function (err) {
          window.alert(err.message || i18n.connectError);
        });
    });
  })();
})();
