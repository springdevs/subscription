/**
 * Shared plan-group + selling-plan (term) form logic.
 *
 * Owns the Create-Plan-Group modal and the Add / Edit Duration modal:
 * plan-type selection, field collection, the /groups and /terms REST writes,
 * and prefilling. Used by BOTH the Plans admin screen
 * (assets/js/admin/plans.js) and the product-editor Subscription tab
 * (assets/js/admin/product-plans.js), so the term payload contract lives in a
 * single place.
 *
 * On success it does NOT navigate. It closes the modal and dispatches a DOM
 * event so each host page decides what happens next:
 *   - "subscrpt:group-created" detail: { group }
 *   - "subscrpt:term-saved"    detail: { groupId, termId, editing }
 *
 * Config is read from window.subscrptPlanForms (restUrl, nonce, i18n),
 * localized on every page that renders these modals.
 */
(function () {
  "use strict";

  var cfg = window.subscrptPlanForms || {};
  var i18n = cfg.i18n || {};

  // day/week/month/year ⇄ billing_interval integer.
  var INTERVAL_TO_INT = { day: 1, week: 2, month: 3, year: 4 };
  var INT_TO_INTERVAL = { 1: "day", 2: "week", 3: "month", 4: "year" };

  /**
   * Call a plan REST endpoint.
   *
   * @param {string} method HTTP verb.
   * @param {string} path   Path under the /plans base, e.g. "/groups".
   * @param {Object} [body] JSON body for write requests.
   * @return {Promise<Object>} Parsed JSON (rejects on non-2xx).
   */
  function api(method, path, body) {
    return fetch(cfg.restUrl + path, {
      method: method,
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": cfg.nonce || "",
      },
      body: body ? JSON.stringify(body) : undefined,
    }).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok) {
          throw new Error((data && data.message) || i18n.genericError);
        }
        return data;
      });
    });
  }

  /**
   * Mark a button busy while its request is in flight, and lock the controls
   * beside it so the same write cannot be fired twice or abandoned midway.
   * The `is-loading` class draws the spinner (admin-components/buttons.css).
   *
   * @param {HTMLElement} btn     Button.
   * @param {boolean}     loading Loading state.
   */
  function setLoading(btn, loading) {
    if (!btn) {
      return;
    }
    btn.disabled = loading;
    btn.classList.toggle("is-loading", loading);

    // The row the button sits in: a modal footer, or the inline edit form.
    var row = btn.closest(".wpsubs-modal__footer") || btn.parentNode;
    if (row && row.querySelectorAll) {
      row.querySelectorAll("button, input, select, textarea").forEach(function (el) {
        if (el !== btn) {
          el.disabled = loading;
        }
      });
    }

    // Inside a modal, the dismiss affordances go with it. Escape is left
    // working on purpose, as the way out of a request that never returns.
    var modal = btn.closest(".wpsubs-modal");
    if (modal) {
      var close = modal.querySelector(".wpsubs-modal__close");
      if (close) {
        close.disabled = loading;
      }
      var backdrop = modal.querySelector(".wpsubs-modal__backdrop");
      if (backdrop) {
        backdrop.style.pointerEvents = loading ? "none" : "";
      }
    }
  }

  /**
   * Open a modal by id via the shared helper.
   *
   * @param {string} id Modal id.
   */
  function openModal(id) {
    if (window.WPSubsModal && typeof window.WPSubsModal.open === "function") {
      window.WPSubsModal.open(id);
    }
  }

  /**
   * Close a modal by id via the shared helper.
   *
   * @param {string} id Modal id.
   */
  function closeModal(id) {
    if (window.WPSubsModal && typeof window.WPSubsModal.close === "function") {
      window.WPSubsModal.close(id);
    }
  }

  /**
   * Read the current value of an advanced-select (its hidden input).
   *
   * @param {HTMLElement} root .wpsubs-adv-select root.
   * @return {string}
   */
  function advValue(root) {
    var hidden = root.querySelector('input[type="hidden"]');
    return hidden ? hidden.value : "";
  }

  /**
   * Programmatically set an advanced-select's value + visible label.
   *
   * @param {HTMLElement} root  .wpsubs-adv-select root.
   * @param {string}      value Option value to select.
   */
  function setAdvSelect(root, value) {
    value = value == null ? "" : String(value);
    var hidden = root.querySelector('input[type="hidden"]');
    if (hidden) {
      hidden.value = value;
    }
    var label = root.querySelector(".wpsubs-adv-select__label");
    var item = root.querySelector('.wpsubs-adv-select__item[data-value="' + value + '"]');
    if (label) {
      label.textContent = item ? item.textContent.trim() : root.getAttribute("data-placeholder") || "";
    }
  }

  /**
   * Collect all [data-subscrpt-field] values within a scope into a flat map.
   *
   * @param {HTMLElement} scope Container (the term modal).
   * @return {Object}
   */
  function collectFields(scope) {
    var out = {};
    scope.querySelectorAll("[data-subscrpt-field]").forEach(function (el) {
      var key = el.getAttribute("data-subscrpt-field");
      if (el.classList.contains("wpsubs-adv-select")) {
        out[key] = advValue(el);
      } else if (el.type === "checkbox") {
        out[key] = el.checked;
      } else if (el.type === "radio") {
        if (el.checked) {
          out[key] = el.value;
        }
      } else {
        out[key] = el.value;
      }
    });
    return out;
  }

  /**
   * Map the term modal's flat fields to a /terms payload.
   *
   * @param {Object} f         Flat field map.
   * @param {string} groupId   Plan group id.
   * @param {string} groupType Plan group type key.
   * @return {Object} REST payload.
   */
  function termPayload(f, groupId, groupType) {
    var data = {
      free_trial_interval: f.free_trial_interval || "day",
    };

    // Recurring Delivery: separate delivery schedule + sync toggle.
    if (typeof f.delivery_sync !== "undefined") {
      data.delivery_sync = !!f.delivery_sync;
      if (data.delivery_sync && typeof f.delivery_day !== "undefined") {
        data.delivery_day = f.delivery_day;
      }
    }
    if (typeof f.delivery_frequency !== "undefined") {
      var deliveryFreq = parseInt(f.delivery_frequency, 10);
      if (!String(f.delivery_frequency).trim() || isNaN(deliveryFreq) || deliveryFreq < 1) {
        // Empty delivery schedule -> mirror the billing schedule.
        data.delivery_frequency = parseInt(f.billing_frequency, 10) || 1;
        data.delivery_interval = f.billing_interval || "month";
      } else {
        data.delivery_frequency = deliveryFreq;
        data.delivery_interval = f.delivery_interval || "month";
      }
    }

    // Split Payment: number of payments + access-ends timing.
    if (typeof f.installment_count !== "undefined") {
      data.installment_count = Math.max(2, parseInt(f.installment_count, 10) || 2);
    }
    if (typeof f.access_ends !== "undefined") {
      data.access_ends = f.access_ends || "lifetime";
      if ("custom" === data.access_ends) {
        data.access_custom_value = parseInt(f.access_custom_value, 10) || 1;
        data.access_custom_interval = f.access_custom_interval || "month";
      }
    }

    return {
      plan_group_id: parseInt(groupId, 10),
      type: groupType || "recurring",
      title: f.title || "",
      billing_frequency: parseInt(f.billing_frequency, 10) || 1,
      billing_interval: INTERVAL_TO_INT[f.billing_interval] || 3,
      billing_length: parseInt(f.billing_length, 10) || 0,
      free_trial: f.free_trial || "",
      signup_fee: { amount: f.signup_fee_amount || "" },
      status: "active",
      data: data,
    };
  }

  /**
   * Prefill the term modal from a fetched term, or clear it for "add".
   *
   * @param {HTMLElement} modal Term modal.
   * @param {Object|null} term  Term row, or null to reset.
   */
  function fillTermModal(modal, term) {
    modal.querySelectorAll("[data-subscrpt-field]").forEach(function (el) {
      var key = el.getAttribute("data-subscrpt-field");
      var adv = el.classList.contains("wpsubs-adv-select");

      // Reset to defaults for "add".
      if (!term) {
        if (adv) {
          setAdvSelect(el, el.getAttribute("data-default-value") || "");
        } else if (el.type === "checkbox") {
          el.checked = false;
        } else if (el.type !== "radio") {
          el.value = key === "billing_frequency" || key === "billing_length" ? el.defaultValue : "";
        }
        return;
      }

      var data = term.data || {};
      switch (key) {
        case "title":
          el.value = term.title || "";
          break;
        case "billing_frequency":
          el.value = term.billing_frequency || 1;
          break;
        case "billing_interval":
          setAdvSelect(el, INT_TO_INTERVAL[term.billing_interval] || "month");
          break;
        case "billing_length":
          el.value = term.billing_length || 0;
          break;
        case "free_trial":
          el.value = term.free_trial || "";
          break;
        case "free_trial_interval":
          setAdvSelect(el, data.free_trial_interval || "day");
          break;
        case "signup_fee_amount":
          el.value = term.signup_fee && term.signup_fee.amount ? term.signup_fee.amount : "";
          break;
        case "delivery_sync":
          el.checked = !!data.delivery_sync;
          break;
        case "delivery_day":
          setAdvSelect(el, typeof data.delivery_day !== "undefined" ? data.delivery_day : "1");
          break;
        case "delivery_frequency":
          el.value = data.delivery_frequency || 1;
          break;
        case "delivery_interval":
          setAdvSelect(el, data.delivery_interval || "month");
          break;
        case "installment_count":
          el.value = data.installment_count || 2;
          break;
        case "access_ends":
          setAdvSelect(el, data.access_ends || "lifetime");
          break;
        case "access_custom_value":
          el.value = data.access_custom_value || 1;
          break;
        case "access_custom_interval":
          setAdvSelect(el, data.access_custom_interval || "month");
          break;
        default:
          break;
      }
    });
    modal.setAttribute("data-editing", term ? term.id : "");
    toggleAccessCustom(modal);
    toggleDeliveryDay(modal);
    var title = modal.querySelector("[data-subscrpt-term-title]");
    if (title) {
      title.textContent = term ? i18n.editTerm || "Edit Duration" : i18n.addTerm || "Add Duration";
    }
  }

  /**
   * Show the custom access-length inputs only when "Custom" is selected.
   *
   * @param {HTMLElement} modal Term modal (or any ancestor of the controls).
   */
  function toggleAccessCustom(modal) {
    if (!modal) {
      return;
    }
    var sel = modal.querySelector('[data-subscrpt-field="access_ends"]');
    var custom = modal.querySelector("[data-subscrpt-access-custom]");
    if (!sel || !custom) {
      return;
    }
    var isCustom = "custom" === advValue(sel);
    custom.style.display = isCustom ? "" : "none";

    // Access ends spans full width unless the custom-duration column is shown.
    var grid = modal.querySelector("[data-subscrpt-access-grid]");
    if (grid) {
      grid.style.gridTemplateColumns = isCustom ? "1fr 2fr" : "1fr";
    }
  }

  /**
   * Enable the delivery-day picker only when Synchronize schedule is on
   * (it stays visible but disabled otherwise).
   *
   * @param {HTMLElement} modal Term modal.
   */
  function toggleDeliveryDay(modal) {
    if (!modal) {
      return;
    }
    var box = modal.querySelector('[data-subscrpt-field="delivery_sync"]');
    var day = modal.querySelector("[data-subscrpt-delivery-day]");
    if (!box || !day) {
      return;
    }
    var on = box.checked;
    day.style.opacity = on ? "" : "0.55";
    day.style.pointerEvents = on ? "" : "none";
  }

  /**
   * Reset the term modal for a specific group and open it (used by callers
   * that chain "create group → add first plan", e.g. the product editor).
   *
   * @param {number|string} groupId   Plan group id.
   * @param {string}        groupType Plan group type key.
   */
  function openTermModalForGroup(groupId, groupType) {
    var modal = document.querySelector("[data-subscrpt-term-modal]");
    if (!modal) {
      return;
    }
    modal.setAttribute("data-group-id", groupId);
    modal.setAttribute("data-group-type", groupType || "recurring");
    fillTermModal(modal, null);
    openModal("subscrpt-term-modal");
  }

  /* ------------------------------------------------------------------ *
   * Handlers (delegated on document; each host page renders the modals).
   * ------------------------------------------------------------------ */

  // Plan-type cards: click to select (skips locked / Pro cards). Selection is a
  // border + soft brand background - no radio input.
  document.addEventListener("click", function (e) {
    var card = e.target.closest(".subscrpt-type-card");
    if (!card || card.hasAttribute("data-locked")) {
      return;
    }
    var list = card.closest("[data-subscrpt-type-list]");
    if (!list) {
      return;
    }
    var typeLabels = [];
    list.querySelectorAll(".subscrpt-type-card").forEach(function (c) {
      var on = c === card;
      c.classList.toggle("is-selected", on);
      c.style.borderColor = on ? "var(--wpsubs-brand)" : "var(--wpsubs-border)";
      c.style.background = on ? "var(--wpsubs-brand-light)" : "";
      var icon = c.querySelector(".dashicons");
      if (icon) {
        icon.style.color = on ? "var(--wpsubs-brand)" : "var(--wpsubs-text-subtle)";
      }
      var lbl = c.getAttribute("data-subscrpt-type-label");
      if (lbl) {
        typeLabels.push(lbl);
      }
    });

    // Auto-fill the name from the selected type — but only while it is empty or
    // still holds an auto-generated type label, so a name the user typed (or a
    // restored one) is never overwritten.
    var modal = card.closest(".wpsubs-modal");
    var nameInput = modal ? modal.querySelector("#subscrpt-create-name") : null;
    var selectedLabel = card.getAttribute("data-subscrpt-type-label");
    if (nameInput && selectedLabel) {
      var current = nameInput.value.trim();
      if (current === "" || typeLabels.indexOf(current) !== -1) {
        nameInput.value = selectedLabel;
      }
    }
  });

  // Create a plan group. On success, close the modal and let the host decide
  // where to go next (Plans page redirects to the detail; the product editor
  // chains into the Add-Selling-Plan modal).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-create-plan]");
    if (!btn) {
      return;
    }
    e.preventDefault();

    var modal = btn.closest(".wpsubs-modal");
    var nameInput = modal.querySelector("#subscrpt-create-name");
    var name = nameInput ? nameInput.value.trim() : "";

    if (!name) {
      window.alert(i18n.nameRequired);
      return;
    }

    // Selected type card (defaults to recurring). The REST controller rejects
    // non-recurring types unless Pro is active.
    var selectedCard = modal.querySelector(".subscrpt-type-card.is-selected");
    var planType = selectedCard ? selectedCard.getAttribute("data-subscrpt-type") : "recurring";

    // Deferred mode (product-editor wizard): don't create yet. Hand the group
    // details to step 2, which creates the group + first plan atomically on
    // submit, so nothing is created until both steps are filled.
    if (modal.hasAttribute("data-subscrpt-defer")) {
      closeModal("subscrpt-create-plan");
      document.dispatchEvent(
        new CustomEvent("subscrpt:group-step", {
          detail: { title: name, type: planType || "recurring" },
        }),
      );
      return;
    }

    setLoading(btn, true);
    api("POST", "/groups", {
      title: name,
      type: planType || "recurring",
      product_type: 1,
      status: "active",
    })
      .then(function (group) {
        setLoading(btn, false);
        if (nameInput) {
          nameInput.value = "";
        }
        closeModal("subscrpt-create-plan");
        document.dispatchEvent(new CustomEvent("subscrpt:group-created", { detail: { group: group } }));
      })
      .catch(function (err) {
        setLoading(btn, false);
        window.alert(err.message || i18n.genericError);
      });
  });

  // Re-evaluate the custom access row when the selection changes.
  document.addEventListener("wpsubs:select", function (e) {
    var root = e.target.closest('[data-subscrpt-field="access_ends"]');
    if (root) {
      toggleAccessCustom(root.closest("[data-subscrpt-term-modal]"));
    }
  });

  // Toggle the delivery-day picker as the sync checkbox changes.
  document.addEventListener("change", function (e) {
    var box = e.target.closest('[data-subscrpt-field="delivery_sync"]');
    if (box) {
      toggleDeliveryDay(box.closest("[data-subscrpt-term-modal]"));
    }
  });

  // Open the term modal in add or edit mode.
  document.addEventListener("click", function (e) {
    var add = e.target.closest("[data-subscrpt-add-term]");
    var edit = e.target.closest("[data-subscrpt-edit-term]");
    if (!add && !edit) {
      return;
    }
    var modal = document.querySelector("[data-subscrpt-term-modal]");
    if (!modal) {
      return;
    }
    if (add) {
      fillTermModal(modal, null);
      // The trigger already carries data-wpsubs-modal-open, so the shared
      // WPSubsModal opens it.
      return;
    }
    e.preventDefault();
    var termId = edit.getAttribute("data-subscrpt-edit-term");
    api("GET", "/terms/" + termId)
      .then(function (term) {
        fillTermModal(modal, term);
        openModal("subscrpt-term-modal");
      })
      .catch(function (err) {
        window.alert(err.message || i18n.genericError);
      });
  });

  // Save the term (create or update). On success, close the modal and let the
  // host decide (Plans page reloads; the product editor refreshes in place).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-term-submit]");
    if (!btn) {
      return;
    }
    var modal = btn.closest("[data-subscrpt-term-modal]");
    var groupId = modal.getAttribute("data-group-id");
    var groupType = modal.getAttribute("data-group-type");
    var editing = modal.getAttribute("data-editing");
    var payload = termPayload(collectFields(modal), groupId, groupType);

    if (!payload.title) {
      window.alert(i18n.nameRequired);
      return;
    }

    // Deferred mode (product-editor wizard): hand the plan payload to the host,
    // which creates the group + this plan atomically. Nothing is written here.
    if (modal.hasAttribute("data-subscrpt-defer")) {
      closeModal("subscrpt-term-modal");
      document.dispatchEvent(new CustomEvent("subscrpt:term-step", { detail: { payload: payload } }));
      return;
    }

    setLoading(btn, true);
    var req = editing ? api("PUT", "/terms/" + editing, payload) : api("POST", "/terms", payload);
    req
      .then(function (term) {
        setLoading(btn, false);
        closeModal("subscrpt-term-modal");
        document.dispatchEvent(
          new CustomEvent("subscrpt:term-saved", {
            detail: { groupId: groupId, termId: term && term.id, editing: editing },
          }),
        );
      })
      .catch(function (err) {
        setLoading(btn, false);
        window.alert(err.message || i18n.genericError);
      });
  });

  window.WPSubsPlanForms = {
    api: api,
    fillTermModal: fillTermModal,
    openModal: openModal,
    closeModal: closeModal,
    openTermModalForGroup: openTermModalForGroup,
  };
})();
