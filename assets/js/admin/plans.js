/**
 * Subscription Plans - admin interactions (free base).
 *
 * Relies on the shared components in assets/js/admin-components/:
 *   - WPSubsModal     - data-wpsubs-modal-open / -close, fires wpsubs:modal:open.
 *   - WPSubsTabs      - the detail page's Durations / Products tabs.
 *   - WPSubsAccordion - the term list + term-modal sections.
 *
 * Everything here is the list/detail chrome plus REST CRUD for plan groups and
 * durations (terms). All writes go through wpsubscription/v1. The Products
 * tab is read-only in free, so no product attach/price wiring lives here.
 */
(function () {
  "use strict";

  var cfg = window.subscrptPlans || {};
  var i18n = cfg.i18n || {};

  // REST calls, the button-busy lock and every message on this screen come
  // from the shared component (admin-components/save.js).
  var save = window.WPSubsSave.bind({ restUrl: cfg.restUrl, nonce: cfg.nonce, i18n: i18n });
  var api = save.api;
  var setLoading = save.busy;

  /* ------------------------------------------------------------------ *
   * Row-actions dropdown (kebab) + client-side list filter.
   * ------------------------------------------------------------------ */

  document.addEventListener("click", function (e) {
    var trigger = e.target.closest("[data-subscrpt-dropdown] .wpsubs-row-actions__trigger");
    if (trigger) {
      e.preventDefault();
      var wrap = trigger.closest("[data-subscrpt-dropdown]");
      var menu = wrap.querySelector(".wpsubs-dropdown");
      var opening = menu && !menu.classList.contains("wpsubs-dropdown--open");
      closeDropdowns();
      if (menu && opening) {
        menu.hidden = false;
        menu.classList.add("wpsubs-dropdown--open");
        wrap.classList.add("wpsubs-row-actions--open");
      }
      return;
    }
    // A click on a menu item (or anywhere outside the menu) closes the dropdown.
    if (!e.target.closest(".wpsubs-dropdown") || e.target.closest(".wpsubs-dropdown__item")) {
      closeDropdowns();
    }
  });

  /**
   * Close every open row-actions dropdown.
   */
  function closeDropdowns() {
    document.querySelectorAll(".wpsubs-row-actions--open").forEach(function (wrap) {
      wrap.classList.remove("wpsubs-row-actions--open");
      var menu = wrap.querySelector(".wpsubs-dropdown");
      if (menu) {
        menu.classList.remove("wpsubs-dropdown--open");
        menu.hidden = true;
      }
    });
  }

  // Whole plan-group row opens its detail page — except clicks on a link,
  // button, or the actions menu, which keep their own behaviour.
  document.addEventListener("click", function (e) {
    var row = e.target.closest(".wpsubs-plan-row[data-href]");
    if (!row || e.target.closest("a, button, input, label, .wpsubs-row-actions, .wpsubs-col--check")) {
      return;
    }
    window.location.href = row.getAttribute("data-href");
  });

  /* ------------------------------------------------------------------ *
   * Plan group: create + delete.
   * ------------------------------------------------------------------ */

  // Plan-group create + the Add / Edit Duration modal now live in the shared
  // plan-forms.js module (window.WPSubsPlanForms). It dispatches events on
  // success so this page navigates accordingly.

  // A new plan group was created: open its detail page.
  document.addEventListener("subscrpt:group-created", function (e) {
    var group = e.detail && e.detail.group;
    if (group && group.id) {
      window.location.href = cfg.listUrl + "&view=detail&plan=" + group.id;
    }
  });

  // A duration (term) was created or updated: reload to show it.
  document.addEventListener("subscrpt:term-saved", function () {
    window.location.reload();
  });

  document.addEventListener("click", function (e) {
    var link = e.target.closest("[data-subscrpt-delete-plan]");
    if (!link) {
      return;
    }
    e.preventDefault();

    if (!window.confirm(i18n.confirmPlan)) {
      return;
    }

    var id = link.getAttribute("data-subscrpt-delete-plan");
    api("DELETE", "/groups/" + id)
      .then(function () {
        var row = link.closest(".wpsubs-plan-row");
        if (row) {
          row.parentNode.removeChild(row);
        } else {
          window.location.href = cfg.listUrl;
        }
      })
      .catch(function (err) {
        save.notify(err.message || i18n.genericError, "error");
      });
  });

  /* ------------------------------------------------------------------ *
   * Tab-scoped header actions (Add Duration).
   * ------------------------------------------------------------------ */

  /**
   * Show a header action only while the tab it belongs to is selected. The
   * button sits beside the tab list rather than inside a panel, so it does not
   * hide with the panel and has to follow the selection itself.
   */
  function syncTabActions() {
    document.querySelectorAll("[data-subscrpt-tab-action]").forEach(function (action) {
      var tab = document.getElementById(action.getAttribute("data-subscrpt-tab-action"));
      action.style.display = tab && "true" === tab.getAttribute("aria-selected") ? "" : "none";
    });
  }

  document.addEventListener("wpsubs:tab:change", syncTabActions);

  // WPSubsTabs activates the initial tab on DOMContentLoaded; match it either
  // way so the action is never left showing on the wrong tab.
  if ("loading" === document.readyState) {
    document.addEventListener("DOMContentLoaded", syncTabActions);
  } else {
    syncTabActions();
  }

  /* ------------------------------------------------------------------ *
   * Plan group: inline rename of the detail page title.
   * ------------------------------------------------------------------ */

  /**
   * The rename widget's parts, or null when not on the detail page.
   *
   * @return {?Object} { wrap, display, pencil, form, input, save }.
   */
  function renameParts() {
    var wrap = document.querySelector("[data-subscrpt-rename]");
    if (!wrap) {
      return null;
    }
    return {
      wrap: wrap,
      display: wrap.querySelector("[data-subscrpt-rename-display]"),
      pencil: wrap.querySelector("[data-subscrpt-rename-open]"),
      form: wrap.querySelector("[data-subscrpt-rename-form]"),
      input: wrap.querySelector("[data-subscrpt-rename-input]"),
      save: wrap.querySelector("[data-subscrpt-rename-save]"),
    };
  }

  /**
   * Swap the title between its read and edit state.
   *
   * @param {boolean} editing Whether to show the input.
   */
  function renameToggle(editing) {
    var parts = renameParts();
    if (!parts) {
      return;
    }
    parts.display.style.display = editing ? "none" : "";
    parts.pencil.style.display = editing ? "none" : "";
    parts.form.style.display = editing ? "inline-flex" : "none";

    if (editing) {
      parts.input.value = parts.display.textContent.trim();
      parts.input.focus();
      parts.input.select();
    }
  }

  /**
   * Mirror the server-side breadcrumb label so the trail matches the new name
   * without a reload - 30 characters, ellipsis, full text as the title
   * attribute (see subscrpt_truncate_text()).
   *
   * @param {string} name New plan name.
   */
  function renameBreadcrumb(name) {
    var crumb = document.querySelector(".wp-subscription-breadcrumb-current");
    if (!crumb) {
      return;
    }
    var chars = Array.from(name);
    var label = chars.length > 30 ? chars.slice(0, 30).join("") + "\u2026" : name;

    crumb.textContent = label;
    if (label === name) {
      crumb.removeAttribute("title");
    } else {
      crumb.setAttribute("title", name);
    }
  }

  /**
   * Persist the new plan name, then update the heading and breadcrumb in place.
   */
  function renameSave() {
    var parts = renameParts();
    if (!parts) {
      return;
    }

    var host = parts.wrap.closest("[data-plan-id]");
    var name = parts.input.value.trim();

    if (!name) {
      save.notify(i18n.nameRequired, "error");
      parts.input.focus();
      return;
    }

    if (!host || name === parts.display.textContent.trim()) {
      renameToggle(false);
      return;
    }

    setLoading(parts.save, true);

    api("PUT", "/groups/" + host.getAttribute("data-plan-id"), { title: name })
      .then(function () {
        parts.display.textContent = name;
        renameBreadcrumb(name);
        renameToggle(false);
        save.notify(i18n.saved);
      })
      .catch(function (err) {
        save.notify(err.message || i18n.genericError, "error");
      })
      .then(function () {
        setLoading(parts.save, false);
      });
  }

  document.addEventListener("click", function (e) {
    if (e.target.closest("[data-subscrpt-rename-open]")) {
      e.preventDefault();
      renameToggle(true);
      return;
    }
    if (e.target.closest("[data-subscrpt-rename-cancel]")) {
      e.preventDefault();
      renameToggle(false);
      return;
    }
    if (e.target.closest("[data-subscrpt-rename-save]")) {
      e.preventDefault();
      renameSave();
    }
  });

  // Enter commits the rename, Escape abandons it.
  document.addEventListener("keydown", function (e) {
    if (!e.target.matches || !e.target.matches("[data-subscrpt-rename-input]")) {
      return;
    }
    if ("Enter" === e.key) {
      e.preventDefault();
      renameSave();
    } else if ("Escape" === e.key) {
      e.preventDefault();
      renameToggle(false);
    }
  });

  /* ------------------------------------------------------------------ *
   * Bulk actions on plan groups (select-all + per-row + Apply).
   * ------------------------------------------------------------------ */

  /**
   * The per-row checkboxes currently visible (search + pagination hide rows via
   * display:none). Bulk actions and select-all operate on the visible set only,
   * so a filtered-out row is never silently deleted.
   *
   * @return {HTMLElement[]}
   */
  function bulkRowChecks() {
    return Array.prototype.slice.call(document.querySelectorAll(".wpsubs-row-check")).filter(function (cb) {
      var tr = cb.closest("tr");
      return tr && "none" !== tr.style.display;
    });
  }

  /** @return {HTMLElement[]} The checked (and visible) per-row checkboxes. */
  function bulkChecked() {
    return bulkRowChecks().filter(function (cb) {
      return cb.checked;
    });
  }

  /** Sync the select-all checkbox (checked / indeterminate) with the visible rows. */
  function bulkSync() {
    var all = bulkRowChecks();
    var checked = bulkChecked();
    var selectAll = document.querySelector("[data-subscrpt-select-all]");
    if (selectAll) {
      selectAll.checked = all.length > 0 && checked.length === all.length;
      selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
    }
  }

  /** Reset the bulk-action adv-select back to its placeholder. */
  function bulkResetSelect() {
    var root = document.querySelector("[data-subscrpt-bulk-select]");
    if (!root) {
      return;
    }
    var input = root.querySelector('input[type="hidden"]');
    var label = root.querySelector(".wpsubs-adv-select__label");
    if (input) {
      input.value = root.dataset.defaultValue || "";
    }
    if (label) {
      label.textContent = root.dataset.placeholder || "";
    }
  }

  /**
   * Run a bulk action over the currently-checked plan groups.
   *
   * @param {string} action The chosen action value (e.g. "delete").
   */
  function bulkRun(action) {
    if (!action) {
      return;
    }
    var ids = bulkChecked().map(function (cb) {
      return cb.value;
    });
    if (!ids.length) {
      save.notify(i18n.selectPlans || i18n.genericError, "error");
      return;
    }

    if ("delete" === action) {
      var msg = (i18n.confirmBulkDelete || "").replace("%d", ids.length);
      if (!window.confirm(msg)) {
        return;
      }
      Promise.all(
        ids.map(function (id) {
          return api("DELETE", "/groups/" + id);
        }),
      )
        .then(function () {
          window.location.reload();
        })
        .catch(function (err) {
          save.notify(err.message || i18n.genericError, "error");
        });
    }
  }

  // Select-all toggles every row checkbox.
  document.addEventListener("change", function (e) {
    if (!e.target.closest("[data-subscrpt-select-all]")) {
      return;
    }
    var on = e.target.checked;
    bulkRowChecks().forEach(function (cb) {
      cb.checked = on;
    });
    bulkSync();
  });

  // A single row checkbox changed.
  document.addEventListener("change", function (e) {
    if (e.target.classList && e.target.classList.contains("wpsubs-row-check")) {
      bulkSync();
    }
  });

  // Picking an option from the bulk-action dropdown runs it immediately, then
  // resets the dropdown back to its placeholder.
  document.addEventListener("wpsubs:select", function (e) {
    if (!e.target.closest || !e.target.closest("[data-subscrpt-bulk-select]")) {
      return;
    }
    var action = e.detail && e.detail.value;
    bulkResetSelect();
    bulkRun(action);
  });

  bulkSync();

  /* ------------------------------------------------------------------ *
   * Durations (terms): create / edit / delete / toggle.
   * ------------------------------------------------------------------ */

  // Delete a term.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-delete-term]");
    if (!btn) {
      return;
    }
    if (!window.confirm(i18n.confirmTerm)) {
      return;
    }
    var id = btn.getAttribute("data-subscrpt-delete-term");
    api("DELETE", "/terms/" + id)
      .then(function () {
        var item = btn.closest("[data-term-id]");
        if (item) {
          item.parentNode.removeChild(item);
        }
      })
      .catch(function (err) {
        save.notify(err.message || i18n.genericError, "error");
      });
  });

  /**
   * Lock a term's toggle while its write is in flight. The switch keeps
   * showing the state it is moving to, dimmed, so the click reads as accepted
   * rather than ignored.
   *
   * @param {HTMLElement} label The toggle's label.
   * @param {boolean}     busy  Busy state.
   */
  function setTermBusy(label, busy) {
    label.setAttribute("aria-busy", busy ? "true" : "false");
    label.classList.toggle("wpsubs-toggle-busy", busy);
  }

  /**
   * Reflect a saved status on the row: the action the toggle now offers, its
   * tooltip, and the Draft badge beside the name.
   *
   * @param {HTMLElement} label  The toggle's label.
   * @param {boolean}     active Whether the term is now active.
   */
  function applyTermStatus(label, active) {
    label.setAttribute("data-subscrpt-set-term-status", active ? "draft" : "active");
    label.setAttribute("title", active ? i18n.setDraft : i18n.setActive);

    var card = label.closest(".wpsubs-table-card");
    var badge = card && card.querySelector("[data-subscrpt-term-badge]");

    if (active) {
      if (badge) {
        badge.parentNode.removeChild(badge);
      }
      return;
    }
    if (badge || !card) {
      return;
    }
    var row = card.querySelector("[data-subscrpt-term-name-row]");
    if (!row) {
      return;
    }
    badge = document.createElement("span");
    badge.className = "wpsubs-badge wpsubs-badge--draft";
    badge.setAttribute("data-subscrpt-term-badge", "");
    badge.textContent = i18n.draft;
    row.appendChild(badge);
  }

  // Set a term active/draft from its row toggle. The row is updated in place
  // rather than reloaded: a full reload for a one-field write left the switch
  // sitting on its old state for the whole round trip, which read as a dead
  // control.
  document.addEventListener("click", function (e) {
    var label = e.target.closest("[data-subscrpt-set-term-status]");
    if (!label) {
      return;
    }
    // The label owns the checkbox, so let it drive the switch itself.
    e.preventDefault();
    if ("true" === label.getAttribute("aria-busy")) {
      return;
    }

    var id = label.getAttribute("data-term-id");
    var status = label.getAttribute("data-subscrpt-set-term-status");
    var active = "active" === status;
    var cb = label.querySelector(".wpsubs-toggle");

    setTermBusy(label, true);
    if (cb) {
      cb.checked = active;
    }

    api("PUT", "/terms/" + id, { status: status })
      .then(function () {
        applyTermStatus(label, active);
        save.notify(active ? i18n.termActivated : i18n.termDrafted);
      })
      .catch(function (err) {
        if (cb) {
          cb.checked = !active;
        }
        save.notify(err.message || i18n.genericError, "error");
      })
      .then(function () {
        setTermBusy(label, false);
      });
  });

  /* ------------------------------------------------------------------ *
   * Products tab: refresh the panel in place.
   * ------------------------------------------------------------------ */

  /**
   * Re-render the Products panel from the server, keeping the page where it
   * was. Attaching, detaching and repricing all change server-formatted values
   * (money, badges, which products exist), so the fragment is fetched rather
   * than patched by hand — but a full reload for it threw the whole screen
   * away, which is what made every one of these actions flash.
   *
   * Which products were expanded and the scroll position are restored, so the
   * panel comes back looking like it never moved.
   *
   * @param {string|number} groupId Plan group id.
   * @return {Promise}
   */
  function refreshProducts(groupId) {
    var panel = document.getElementById("subscrpt-panel-products");
    if (!panel || !groupId) {
      return Promise.resolve();
    }

    var scrollY = window.scrollY;
    var open = [];
    panel.querySelectorAll("[data-pid]").forEach(function (item) {
      var header = item.querySelector(".wpsubs-accordion__header");
      if (header && "true" === header.getAttribute("aria-expanded")) {
        open.push(item.getAttribute("data-pid"));
      }
    });

    return api("GET", "/group-products/" + groupId).then(function (res) {
      panel.innerHTML = (res && res.html) || "";

      // Components bind on DOMContentLoaded; new markup needs a nudge.
      if (window.WPSubsAccordion && window.WPSubsAccordion.init) {
        window.WPSubsAccordion.init(panel);
      }

      open.forEach(function (pid) {
        var item = panel.querySelector('[data-pid="' + pid + '"]');
        var header = item && item.querySelector(".wpsubs-accordion__header");
        if (header && "true" !== header.getAttribute("aria-expanded")) {
          header.click();
        }
      });

      window.scrollTo(0, scrollY);
    });
  }

  /**
   * The plan group this detail page is showing.
   *
   * @return {string} Group id, or "" off the detail page.
   */
  function currentGroupId() {
    var host = document.querySelector("[data-plan-id]");
    return host ? host.getAttribute("data-plan-id") || "" : "";
  }

  /* ------------------------------------------------------------------ *
   * Products tab (Pro): bulk-add products to the plan group.
   * ------------------------------------------------------------------ */

  /**
   * Set a variable parent's checkbox from its children's state.
   *
   * @param {HTMLElement} parentCb   Parent control checkbox.
   * @param {HTMLElement[]} childBoxes Variation checkboxes.
   */
  function syncParent(parentCb, childBoxes) {
    var all = childBoxes.every(function (c) {
      return c.checked;
    });
    var none = childBoxes.every(function (c) {
      return !c.checked;
    });
    parentCb.checked = all;
    parentCb.indeterminate = !all && !none;
  }

  /**
   * Fetch the group's product relations as a Set of "oid:vid" keys, so the
   * picker can pre-check what's already attached.
   *
   * @param {number|string} groupId Plan group id.
   * @return {Promise<Set>}
   */
  function fetchAttached(groupId) {
    return api("GET", "/groups/" + groupId)
      .then(function (group) {
        var set = new Set();
        ((group && group.plans) || []).forEach(function (term) {
          (term.relations || []).forEach(function (rel) {
            if (1 === parseInt(rel.type, 10)) {
              set.add(parseInt(rel.oid, 10) + ":" + (parseInt(rel.vid, 10) || 0));
            }
          });
        });
        return set;
      })
      .catch(function () {
        return new Set();
      });
  }

  /**
   * Build one picker row: checkbox, thumbnail, name, price.
   *
   * Attachable rows (simple products, variations) carry data-oid / data-vid /
   * data-price on the checkbox. A "control" row (variable parent) has none —
   * it only toggles its children and is skipped when attaching.
   *
   * @param {Object}  p    Product/variation row from the API.
   * @param {Object}  opts { indent, control, parentId }.
   * @return {{ li: HTMLElement, cb: HTMLElement }}
   */
  function productRow(p, opts) {
    opts = opts || {};

    var li = document.createElement("li");
    li.style.cssText =
      "margin:0;display:flex;align-items:center;gap:10px;padding:8px 4px;padding-left:" +
      (opts.indent ? "36px" : "4px") +
      ";border-bottom:1px solid rgba(0,0,0,0.06);";

    var label = document.createElement("label");
    label.style.cssText = "display:flex;align-items:center;gap:10px;flex:1 1 auto;min-width:0;cursor:pointer;";

    // A product already attached to a DIFFERENT plan group can't be selected — a
    // product belongs to a single group.
    var inOtherGroup = p.plan_group_id && String(p.plan_group_id) !== String(opts.currentGroup || "");

    var cb = document.createElement("input");
    cb.type = "checkbox";
    cb.className = "wpsubs-checkbox";
    if (inOtherGroup) {
      cb.disabled = true;
    }
    if (!opts.control) {
      var oid = opts.parentId ? opts.parentId : p.id;
      var vid = opts.parentId ? p.id : 0;
      cb.setAttribute("data-oid", oid);
      cb.setAttribute("data-vid", vid);
      cb.setAttribute("data-price", p.price || "");
      // Pre-check rows already attached to this group (never one locked to another).
      if (!inOtherGroup && opts.attached && opts.attached.has(oid + ":" + vid)) {
        cb.checked = true;
      }
    }
    label.appendChild(cb);

    var thumb = document.createElement("span");
    thumb.style.cssText =
      "flex:0 0 auto;width:32px;height:32px;border-radius:4px;background:var(--wpsubs-bg-subtle,#f1f2f4);border:1px solid var(--wpsubs-border);overflow:hidden;display:flex;align-items:center;justify-content:center;";
    if (p.image) {
      var img = document.createElement("img");
      img.src = p.image;
      img.alt = "";
      img.style.cssText = "width:100%;height:100%;object-fit:cover;display:block;";
      thumb.appendChild(img);
    } else {
      thumb.innerHTML =
        '<span class="dashicons dashicons-format-image" style="font-size:16px;width:16px;height:16px;color:var(--wpsubs-text-subtle);"></span>';
    }
    label.appendChild(thumb);

    var name = document.createElement("span");
    name.textContent = p.name;
    name.style.cssText = "font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;";
    label.appendChild(name);

    if (inOtherGroup) {
      li.style.opacity = "0.55";
      label.style.cursor = "not-allowed";
      li.title = (i18n.usedInPlan || "This product is being used in '%s'").replace("%s", p.plan_group_name || "");
      var note = document.createElement("span");
      note.textContent = i18n.inOtherPlan || "Added to another plan";
      note.style.cssText = "flex:0 0 auto;font-size:11px;font-style:italic;color:var(--wpsubs-text-subtle);";
      label.appendChild(note);
    }

    var price = document.createElement("span");
    price.style.cssText = "color:var(--wpsubs-text-muted);font-size:13px;flex:0 0 auto;";
    price.textContent = p.price_html || "";

    li.appendChild(label);
    li.appendChild(price);

    return { li: li, cb: cb };
  }

  /**
   * Populate the Add Products picker with a product search.
   *
   * @param {HTMLElement} modal  The add-product modal.
   * @param {string}      search Search term.
   */
  function loadProducts(modal, search) {
    var list = modal.querySelector("[data-subscrpt-product-list]");
    if (!list) {
      return;
    }
    var attached = modal._attached || new Set();
    var currentGroup = modal.getAttribute("data-group-id") || "";
    list.innerHTML =
      '<li style="padding:10px 4px;color:var(--wpsubs-text-subtle);font-size:13px;">' +
      (i18n.loading || "Loading…") +
      "</li>";

    api("GET", "/products?search=" + encodeURIComponent(search || ""))
      .then(function (products) {
        if (!products.length) {
          list.innerHTML =
            '<li style="padding:10px 4px;color:var(--wpsubs-text-subtle);font-size:13px;">' +
            (i18n.noProducts || "No products found.") +
            "</li>";
          return;
        }
        list.innerHTML = "";
        products.forEach(function (p) {
          var variations = p.variations || [];

          if (variations.length) {
            // Variable product: parent row is a select-all control (no own
            // relation), variations are the attachable rows offset beneath it.
            var parent = productRow(p, { indent: false, control: true, currentGroup: currentGroup });
            list.appendChild(parent.li);

            var childBoxes = [];
            variations.forEach(function (v) {
              var child = productRow(v, {
                indent: true,
                parentId: p.id,
                attached: attached,
                currentGroup: currentGroup,
              });
              childBoxes.push(child.cb);
              list.appendChild(child.li);
            });

            // Reflect the pre-checked children in the parent control.
            syncParent(parent.cb, childBoxes);

            // Parent toggles every child; children keep the parent in sync.
            parent.cb.addEventListener("change", function () {
              childBoxes.forEach(function (cb) {
                cb.checked = parent.cb.checked;
              });
              parent.cb.indeterminate = false;
            });
            childBoxes.forEach(function (cb) {
              cb.addEventListener("change", function () {
                syncParent(parent.cb, childBoxes);
              });
            });
          } else {
            list.appendChild(productRow(p, { indent: false, attached: attached, currentGroup: currentGroup }).li);
          }
        });

        // No divider under the last row.
        if (list.lastElementChild) {
          list.lastElementChild.style.borderBottom = "none";
        }
        syncPickerCount(modal);
      })
      .catch(function () {
        list.innerHTML =
          '<li style="padding:10px 4px;color:var(--wpsubs-text-subtle);font-size:13px;">' +
          (i18n.genericError || "") +
          "</li>";
      });
  }

  /**
   * Update the picker's running tally. Ticking is a two-way control — it
   * attaches and detaches — so the count is what says how the plan will look
   * after saving, rather than how many were just clicked.
   *
   * @param {HTMLElement} modal The add-product modal.
   */
  function syncPickerCount(modal) {
    var out = modal && modal.querySelector("[data-subscrpt-picker-count]");
    if (!out) {
      return;
    }
    var boxes = modal.querySelectorAll("[data-subscrpt-product-list] input[data-oid]");
    var n = 0;
    Array.prototype.forEach.call(boxes, function (box) {
      if (box.checked) {
        n += 1;
      }
    });
    out.textContent = n ? (i18n.picked || "%d on this plan").replace("%d", n) : i18n.pickedNone || "";
  }

  // Any tick in the picker updates the tally.
  document.addEventListener("change", function (e) {
    var box = e.target.closest("[data-subscrpt-product-list] input[type=checkbox]");
    var modal = box && box.closest("[data-subscrpt-add-product]");
    if (modal) {
      syncPickerCount(modal);
    }
  });

  // Load the picker when the modal opens (pre-checking attached products).
  document.addEventListener("wpsubs:modal:open", function (e) {
    var modal = e.target;
    if (modal && modal.id === "subscrpt-add-product") {
      fetchAttached(modal.getAttribute("data-group-id")).then(function (set) {
        modal._attached = set;
        loadProducts(modal, "");
      });
    }
  });

  // Debounced product search.
  var addProductTimer;
  document.addEventListener("input", function (e) {
    var input = e.target.closest("[data-subscrpt-product-search]");
    if (!input) {
      return;
    }
    var modal = input.closest("[data-subscrpt-add-product]");
    window.clearTimeout(addProductTimer);
    addProductTimer = window.setTimeout(function () {
      loadProducts(modal, input.value.trim());
    }, 300);
  });

  // Attach the checked products to every duration in the group.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-add-product-submit]");
    if (!btn) {
      return;
    }
    var modal = btn.closest("[data-subscrpt-add-product]");
    var groupId = modal.getAttribute("data-group-id");
    // Only attachable rows carry data-oid (variable parents are skipped).
    var boxes = modal.querySelectorAll("[data-subscrpt-product-list] input[data-oid]");

    // Sync the group to the picker: attach checked rows, detach any previously
    // attached row that is now unchecked.
    var checkedKeys = new Set();
    var checked = [];
    Array.prototype.forEach.call(boxes, function (box) {
      if (box.checked) {
        checked.push(box);
        checkedKeys.add(box.getAttribute("data-oid") + ":" + (box.getAttribute("data-vid") || "0"));
      }
    });
    var attached = modal._attached || new Set();
    var toRemove = new Set();
    attached.forEach(function (key) {
      if (!checkedKeys.has(key)) {
        toRemove.add(key);
      }
    });

    if (!checked.length && !toRemove.size) {
      return;
    }

    setLoading(btn, true);
    api("GET", "/groups/" + groupId)
      .then(function (group) {
        var terms = (group && group.plans) || [];
        var isInstallments = group && ("installments" === group.type_key || 3 === parseInt(group.type, 10));
        var calls = [];

        // Attach checked rows to every term.
        checked.forEach(function (box) {
          var price = box.getAttribute("data-price") || "";
          var data = isInstallments
            ? { price_per_installment: price, down_payment: "" }
            : { regular_price: price, sale_price: "" };
          terms.forEach(function (term) {
            calls.push(
              api("POST", "/relations", {
                plan_id: term.id,
                oid: parseInt(box.getAttribute("data-oid"), 10),
                vid: parseInt(box.getAttribute("data-vid"), 10) || 0,
                type: 1,
                status: "active",
                data: data,
              }),
            );
          });
        });

        // Detach rows that were unchecked.
        if (toRemove.size) {
          terms.forEach(function (term) {
            (term.relations || []).forEach(function (rel) {
              if (1 !== parseInt(rel.type, 10)) {
                return;
              }
              var key = parseInt(rel.oid, 10) + ":" + (parseInt(rel.vid, 10) || 0);
              if (toRemove.has(key)) {
                calls.push(api("DELETE", "/relations/" + rel.id));
              }
            });
          });
        }

        return Promise.all(calls);
      })
      .then(function () {
        if (window.WPSubsModal) {
          window.WPSubsModal.close(modal);
        }
        return refreshProducts(groupId);
      })
      .then(function () {
        setLoading(btn, false);
        save.notify(i18n.productsUpdated);
      })
      .catch(function (err) {
        setLoading(btn, false);
        save.notify(err.message || i18n.genericError, "error");
      });
  });

  // Remove a product from the group: delete every relation for that product
  // (all durations, all variations).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-remove-product]");
    if (!btn) {
      return;
    }
    var oid = parseInt(btn.getAttribute("data-subscrpt-remove-product"), 10);
    var wrap = document.querySelector("[data-plan-id]");
    var groupId = wrap && wrap.getAttribute("data-plan-id");
    if (!groupId || !window.confirm(i18n.confirmRemoveProduct || "Remove this product from the plan?")) {
      return;
    }

    setLoading(btn, true);
    api("GET", "/groups/" + groupId)
      .then(function (group) {
        var ids = [];
        ((group && group.plans) || []).forEach(function (term) {
          (term.relations || []).forEach(function (rel) {
            if (1 === parseInt(rel.type, 10) && parseInt(rel.oid, 10) === oid) {
              ids.push(rel.id);
            }
          });
        });
        return Promise.all(
          ids.map(function (id) {
            return api("DELETE", "/relations/" + id);
          }),
        );
      })
      .then(function () {
        return refreshProducts(groupId);
      })
      .then(function () {
        setLoading(btn, false);
        save.notify(i18n.productRemoved);
      })
      .catch(function (err) {
        setLoading(btn, false);
        save.notify(err.message || i18n.genericError, "error");
      });
  });

  // Remove a single variation from the group: delete every relation for that
  // (product, variation) pair across all durations.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-remove-variation]");
    if (!btn) {
      return;
    }
    var oid = parseInt(btn.getAttribute("data-oid"), 10);
    var vid = parseInt(btn.getAttribute("data-vid"), 10);
    var wrap = document.querySelector("[data-plan-id]");
    var groupId = wrap && wrap.getAttribute("data-plan-id");
    if (!groupId || !window.confirm(i18n.confirmRemoveVariation || "Remove this variation from the plan?")) {
      return;
    }

    setLoading(btn, true);
    api("GET", "/groups/" + groupId)
      .then(function (group) {
        var ids = [];
        ((group && group.plans) || []).forEach(function (term) {
          (term.relations || []).forEach(function (rel) {
            if (1 === parseInt(rel.type, 10) && parseInt(rel.oid, 10) === oid && parseInt(rel.vid, 10) === vid) {
              ids.push(rel.id);
            }
          });
        });
        return Promise.all(
          ids.map(function (id) {
            return api("DELETE", "/relations/" + id);
          }),
        );
      })
      .then(function () {
        return refreshProducts(groupId);
      })
      .then(function () {
        setLoading(btn, false);
        save.notify(i18n.productRemoved);
      })
      .catch(function (err) {
        setLoading(btn, false);
        save.notify(err.message || i18n.genericError, "error");
      });
  });

  /* ------------------------------------------------------------------ *
   * Products tab (Pro): inline edit of a price card's rows.
   * ------------------------------------------------------------------ */

  /**
   * Toggle a price card between read and edit mode.
   *
   * @param {HTMLElement} card    The [data-subscrpt-price-card] element.
   * @param {boolean}     editing Desired mode.
   */
  function priceCardMode(card, editing) {
    card.querySelectorAll(".subscrpt-pe-view").forEach(function (el) {
      el.style.display = editing ? "none" : "";
    });
    card.querySelectorAll(".subscrpt-pe-edit").forEach(function (el) {
      el.style.display = editing ? "" : "none";
    });
    var toggle = function (sel, show) {
      var btn = card.querySelector(sel);
      if (btn) {
        btn.style.display = show ? "" : "none";
      }
    };
    toggle("[data-subscrpt-edit-prices]", !editing);
    toggle("[data-subscrpt-cancel-prices]", editing);
    toggle("[data-subscrpt-save-prices]", editing);
  }

  // Enter edit mode (snapshot current values for cancel).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-edit-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-price-card]");
    card.querySelectorAll("[data-field]").forEach(function (field) {
      field.dataset.orig = "checkbox" === field.type ? (field.checked ? "1" : "") : field.value;
    });
    priceCardMode(card, true);
  });

  // Cancel: restore snapshot, back to read mode.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-cancel-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-price-card]");
    card.querySelectorAll("[data-field]").forEach(function (field) {
      if ("checkbox" === field.type) {
        field.checked = "1" === field.dataset.orig;
      } else {
        field.value = field.dataset.orig || "";
      }
    });
    priceCardMode(card, false);
  });

  // Save: PUT each row's regular / offer price + enabled state to its relation.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-save-prices]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-price-card]");
    var rows = card.querySelectorAll("[data-subscrpt-relation]");
    // The card carries the product id, and (for a variable product) the
    // variation id — a variation row seeded from the product-level price has
    // no relation of its own yet, so Save creates one for that variation.
    var cardPid = card.getAttribute("data-product-id");
    var cardVid = parseInt(card.getAttribute("data-variation-id"), 10) || 0;

    setLoading(btn, true);
    var calls = Array.prototype.map.call(rows, function (row) {
      var id = parseInt(row.getAttribute("data-subscrpt-relation"), 10) || 0;
      var reg = row.querySelector('[data-field="regular_price"]');
      var sale = row.querySelector('[data-field="sale_price"]');
      var enabled = row.querySelector('[data-field="enabled"]');
      var data = {
        regular_price: reg ? reg.value : "",
        sale_price: sale ? sale.value : "",
        discount_value: 0,
      };
      if (id) {
        return api("PUT", "/relations/" + id, {
          exclude: enabled ? !enabled.checked : false,
          data: data,
        });
      }
      // No relation yet (seeded variation price) → create one for this variation.
      return api("POST", "/relations", {
        plan_id: parseInt(row.getAttribute("data-plan-id"), 10) || 0,
        oid: parseInt(cardPid, 10) || 0,
        vid: cardVid,
        type: 1,
        status: "active",
        exclude: enabled ? !enabled.checked : false,
        data: data,
      });
    });

    // One-time purchase row (product-specific native price) shares this Save.
    // Only persisted when its controls are rendered (Pro): the one-time price
    // stays restricted, so leave it untouched when those inputs are absent.
    var otRow = card.querySelector("[data-subscrpt-onetime-row]");
    var pid = card.getAttribute("data-product-id");
    var enable = otRow ? otRow.querySelector("[data-subscrpt-onetime-enable]") : null;
    if (otRow && pid && enable) {
      var vid = card.getAttribute("data-variation-id");
      var otPrice = otRow.querySelector('[data-ot-field="price"]');
      var otOffer = otRow.querySelector('[data-ot-field="offer"]');
      var otVals = {
        enabled: enable.checked,
        price: otPrice ? otPrice.value : "",
        offer: otOffer ? otOffer.value : "",
      };
      var otPayload = otVals;
      if (vid) {
        otPayload = { variations: {} };
        otPayload.variations[vid] = otVals;
      }
      calls.push(api("PUT", "/product-onetime/" + pid, otPayload));
    }

    Promise.all(calls)
      .then(function () {
        return refreshProducts(currentGroupId());
      })
      .then(function () {
        setLoading(btn, false);
        save.notify(i18n.pricesSaved);
      })
      .catch(function (err) {
        setLoading(btn, false);
        save.notify(err.message || i18n.genericError, "error");
      });
  });

  /* ------------------------------------------------------------------ *
   * Products tab (Pro): simple product one-time purchase card.
   * Variable products handle one-time per variation via the card Save above;
   * simple products use this standalone card + its own toggle/Save.
   * ------------------------------------------------------------------ */

  // Save the simple card's one-time (enabled flag + native price).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-subscrpt-onetime-save]");
    if (!btn) {
      return;
    }
    var card = btn.closest("[data-subscrpt-onetime-card]");
    var pid = card.getAttribute("data-product-id");
    var enable = card.querySelector("[data-subscrpt-onetime-enable]");
    var price = card.querySelector('[data-ot-field="price"]');
    var offer = card.querySelector('[data-ot-field="offer"]');

    setLoading(btn, true);
    api("PUT", "/product-onetime/" + pid, {
      enabled: enable ? enable.checked : false,
      price: price ? price.value : "",
      offer: offer ? offer.value : "",
    })
      .then(function () {
        return refreshProducts(currentGroupId());
      })
      .then(function () {
        setLoading(btn, false);
        save.notify(i18n.pricesSaved);
      })
      .catch(function (err) {
        setLoading(btn, false);
        save.notify(err.message || i18n.genericError, "error");
      });
  });

  /* ------------------------------------------------------------------ *
   * Products tab: client-side search + pagination over the product list.
   * ------------------------------------------------------------------ */

  /**
   * Windowed page numbers with ellipses (first, last, current ±1).
   *
   * @param {number} current Current page.
   * @param {number} total   Total pages.
   * @return {Array<number|string>}
   */
  function pageRange(current, total) {
    var out = [];
    for (var i = 1; i <= total; i++) {
      if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
        out.push(i);
      } else if (out[out.length - 1] !== "…") {
        out.push("…");
      }
    }
    return out;
  }

  function initBrowser(root) {
    var perPage = parseInt(root.getAttribute("data-per-page"), 10) || 10;
    var input = root.querySelector("[data-subscrpt-browse-search]");
    var items = Array.prototype.slice.call(root.querySelectorAll("[data-subscrpt-browse-item]"));
    var emptyMsg = root.querySelector("[data-subscrpt-browse-empty]");
    var pager = root.querySelector("[data-subscrpt-browse-pager]");
    var term = "";
    var page = 1;

    // Remember each item's own display so showing it restores that (e.g. cards
    // set display:flex inline) instead of clearing it to the block default.
    items.forEach(function (it) {
      it._subscrptDisplay = it.style.display || "";
    });

    function match(item) {
      if (!term) {
        return true;
      }
      var name = (item.getAttribute("data-name") || "").indexOf(term) !== -1;
      var pid = String(item.getAttribute("data-pid") || "").indexOf(term) !== -1;
      return name || pid;
    }

    function render() {
      var visible = items.filter(match);
      var total = Math.max(1, Math.ceil(visible.length / perPage));
      if (page > total) {
        page = total;
      }
      var start = (page - 1) * perPage;
      var pageItems = visible.slice(start, start + perPage);

      items.forEach(function (it) {
        it.style.display = "none";
      });
      pageItems.forEach(function (it) {
        it.style.display = it._subscrptDisplay;
      });

      if (emptyMsg) {
        emptyMsg.style.display = visible.length ? "none" : "";
      }
      renderPager(visible.length, total);
    }

    function renderPager(count, total) {
      if (total <= 1) {
        pager.innerHTML = "";
        return;
      }
      var start = count ? (page - 1) * perPage + 1 : 0;
      var end = Math.min(page * perPage, count);
      var info = (i18n.showingRange || "Showing %1-%2 of %3")
        .replace("%1", start)
        .replace("%2", end)
        .replace("%3", count);

      var nav = "";
      var chip = function (label, target, disabled, active) {
        var cls = "wpsubs-pagination__btn";
        if (disabled) {
          cls += " wpsubs-pagination__btn--disabled";
        }
        if (active) {
          cls += " wpsubs-pagination__btn--active";
        }
        if ("…" === label) {
          return '<span class="wpsubs-pagination__btn wpsubs-pagination__btn--ellipsis" aria-hidden="true">…</span>';
        }
        return '<button type="button" class="' + cls + '" data-subscrpt-page="' + target + '">' + label + "</button>";
      };

      nav += chip("‹", page - 1, page <= 1, false);
      pageRange(page, total).forEach(function (p) {
        nav += "…" === p ? chip("…") : chip(p, p, false, p === page);
      });
      nav += chip("›", page + 1, page >= total, false);

      pager.innerHTML =
        '<div class="wpsubs-pagination" role="navigation">' +
        '<span class="wpsubs-pagination__info">' +
        info +
        "</span>" +
        '<span class="wpsubs-pagination__nav">' +
        nav +
        "</span></div>";
    }

    if (input) {
      var timer;
      input.addEventListener("input", function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
          term = input.value.trim().toLowerCase();
          page = 1;
          render();
        }, 200);
      });
    }

    // Per-page adv-select (fires wpsubs:select).
    document.addEventListener("wpsubs:select", function (e) {
      var sel = e.target.closest ? e.target.closest("[data-subscrpt-browse-perpage]") : null;
      if (!sel || !root.contains(sel)) {
        return;
      }
      perPage = parseInt(e.detail && e.detail.value, 10) || perPage;
      page = 1;
      render();
    });

    pager.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-subscrpt-page]");
      if (!btn) {
        return;
      }
      page = parseInt(btn.getAttribute("data-subscrpt-page"), 10) || 1;
      render();
    });

    render();
  }

  function initBrowsers() {
    document.querySelectorAll("[data-subscrpt-browse]").forEach(initBrowser);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initBrowsers);
  } else {
    initBrowsers();
  }
})();
