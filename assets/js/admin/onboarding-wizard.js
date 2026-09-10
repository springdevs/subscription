/**
 * Onboarding Wizard — SPA-style.
 *
 * Flow (nothing is created until the final step):
 *   1. Create Plan — pick a plan type + name (name auto-fills from the type).
 *   2. Durations   — one or more billing durations.
 *   3. Connect     — choose a new or existing product to attach the plan to.
 *   4. Finish      — creates everything in order: plan group -> one term per
 *                    duration -> product (if new) -> a relation per term.
 *
 * Steps 1–3 only collect and validate input; the plan (group), durations
 * (terms) and product relations are all created together on step 4 through the
 * Plans REST API (wpsubscription/v1/plans). Creating a brand-new product uses
 * an admin-ajax handler. All PHP values arrive via `subscrpt_wizard`.
 */
(function ($) {
  "use strict";

  var INTERVAL_TO_INT = { day: 1, week: 2, month: 3, year: 4 };

  // The preview graph has 3 duration slots, so durations are capped there.
  var MAX_DURATIONS = 3;

  var Wizard = {
    cfg: {},
    MAX_DURATIONS: MAX_DURATIONS,
    autoName: "",
    // Everything below is created on the final step (page 4), not before.
    groupId: 0,
    termIds: [],
    planTitle: "",
    billingText: "",
    finalProductId: "",
    finalProductName: "",
    relationsCreated: false,
    finalizeRunning: false,
    // Durations show as placeholder ghost cards in the preview until the user
    // reaches page 2 and starts editing them.
    reachedDurations: false,

    init: function () {
      this.cfg = window.subscrpt_wizard || {};
      this.hasProducts = $("#subscrpt-has-products").val() === "1";
      this.autoName = $.trim($("#subscrpt_plan_title").val());
      this.bindEvents();
      this.initLivePreview();
      this.initFocusZoom();
      this.initDurations();
      $("#subscrpt-link-plans").attr("href", this.cfg.plans_url || "#");
      $("#subscrpt-link-products").attr("href", this.cfg.products_url || "#");
      // Start on the welcome page (normalises stepper, footer nav and preview).
      this.switchSection(0);
    },

    // ----- REST helper -----

    api: function (method, path, body) {
      return fetch(this.cfg.rest_url + path, {
        method: method,
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": this.cfg.rest_nonce || "",
        },
        body: body ? JSON.stringify(body) : undefined,
      }).then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok) {
            throw new Error((data && data.message) || "Request failed.");
          }
          return data;
        });
      });
    },

    // ----- Events -----

    bindEvents: function () {
      // Page 0 (welcome).
      $(document).on("click", "#subscrpt-btn-start", $.proxy(this.goToPage, this, 1));
      $(document).on("click", "#subscrpt-btn-skip", $.proxy(this.skip, this));

      // Page 1 (plan).
      $(document).on("click", ".wpsubs-plan-type-card", $.proxy(this.selectPlanType, this));
      $(document).on("click", "#subscrpt-btn-back-0", $.proxy(this.goToPage, this, 0));
      $(document).on("click", "#subscrpt-btn-next-1", $.proxy(this.nextFromPlan, this));

      // Page 2 (durations).
      $(document).on("click", "#subscrpt-btn-back-1", $.proxy(this.goToPage, this, 1));
      $(document).on("click", "#subscrpt-btn-next-2", $.proxy(this.nextFromDurations, this));
      $(document).on("click", "#subscrpt-btn-add-duration", $.proxy(this.addDuration, this));
      $(document).on("click", "[data-dur-toggle]", $.proxy(this.onDurToggle, this));
      $(document).on("click", "[data-dur-remove]", $.proxy(this.onDurRemove, this));
      $(document).on("input", "[data-dur-freq]", $.proxy(this.onDurBillingInput, this));
      $(document).on("wpsubs:select", "[data-dur-interval]", $.proxy(this.onDurBillingInput, this));
      $(document).on("input", "[data-dur-name]", $.proxy(this.onDurNameInput, this));

      // Page 3 (product).
      $(document).on("click", "#subscrpt-btn-back-2", $.proxy(this.goToPage, this, 2));
      $(document).on("click", ".wpsubs-connect-mode-card", $.proxy(this.selectConnectMode, this));
      $(document).on("change", "[data-connect-enabled]", $.proxy(this.onConnectToggle, this));
      $(document).on("input", "[data-connect-price]", $.proxy(this.updatePreview, this));
      $(document).on("click", "#subscrpt-btn-next-3", $.proxy(this.nextFromProduct, this));
      $(document).on("focus", "#subscrpt-product-search-input", this.openProductSearch);
      $(document).on("input", "#subscrpt-product-search-input", this.filterProducts);
      $(document).on("click", ".wpsubs-p2-product-search__item", $.proxy(this.onProductPick, this));
      $(document).on("click", "#subscrpt-btn-clear-product", $.proxy(this.clearProduct, this));
      $(document).on("click", function (e) {
        if (!$(e.target).closest(".wpsubs-p2-product-search").length) {
          $("#subscrpt-product-search-dropdown").hide();
        }
      });

      // Page 4 (finish).
      $(document).on("click", "#subscrpt-btn-retry-finalize", $.proxy(this.finalize, this));
      $(document).on("click", "#subscrpt-btn-add-another", $.proxy(this.restart, this));
    },

    // ----- Navigation -----

    goToPage: function (pageNum, e) {
      if (e && e.preventDefault) {
        e.preventDefault();
      }
      this.switchSection(pageNum);
    },

    switchSection: function (pageNum) {
      $("#subscrpt-wizard-page").val(pageNum);

      // Once the user lands on the durations step, the preview duration nodes
      // stop being placeholders and reflect the real durations.
      if (pageNum >= 2) {
        this.reachedDurations = true;
      }

      // The welcome page (0) has no stepper, no live preview and no footer nav —
      // it's a standalone intro with its own button.
      $("#subscrpt-stepper").toggle(pageNum !== 0);
      $(".wpsubs-wizard-layout").toggleClass("is-welcome", pageNum === 0);

      $(".wpsubs-wizard-stepper__step").removeClass("active done");
      $(".wpsubs-wizard-stepper__step").each(function () {
        var step = parseInt($(this).data("step"), 10);
        if (step < pageNum) {
          $(this).addClass("done");
        } else if (step === pageNum) {
          $(this).addClass("active");
        }
      });

      $(".wpsubs-wizard-section").removeClass("active");
      $("#subscrpt-section-" + pageNum).addClass("active");

      // Build the per-duration pricing rows from the durations set on step 2.
      if (pageNum === 3) {
        this.renderConnectDurations();
      }

      // Swap the footer nav to this step's buttons.
      $(".wpsubs-wizard-nav").attr("hidden", "hidden");
      $('.wpsubs-wizard-nav[data-nav="' + pageNum + '"]').removeAttr("hidden");

      // Light up the part of the preview this step fills in.
      var groups = { 0: "plan", 1: "plan", 2: "dur", 3: "prod", 4: "done" };
      $("#subscrpt-preview-graph").attr("data-active", groups[pageNum] || "plan");
      this.updatePreview();

      $("html, body").animate({ scrollTop: 0 }, 150);
    },

    skip: function (e) {
      e.preventDefault();
      window.location.href = this.cfg.subscriptions_url;
    },

    // ----- Page 1: plan type + name -----

    selectPlanType: function (e) {
      var card = $(e.currentTarget);
      $(".wpsubs-plan-type-card").removeClass("active");
      card.addClass("active");
      $("#subscrpt-plan-type").val(card.data("type"));

      // Auto-fill the name, but never clobber a name the user has edited.
      var suggested = card.data("name") ? String(card.data("name")) : "";
      var current = $.trim($("#subscrpt_plan_title").val());
      if (!current || current === this.autoName) {
        $("#subscrpt_plan_title").val(suggested);
      }
      this.autoName = suggested;

      this.updatePreview();
    },

    nextFromPlan: function (e) {
      e.preventDefault();
      if (!$.trim($("#subscrpt_plan_title").val())) {
        window.alert("Please enter a plan name.");
        return;
      }
      this.switchSection(2);
    },

    // ----- Page 2: live preview + create plan -----

    initLivePreview: function () {
      var self = this;
      var update = function () {
        self.updatePreview();
      };
      // Fields whose typing should reflect into the preview graph. Duration
      // fields update the preview through their own handlers.
      $(document).on("input", "#subscrpt_plan_title, #subscrpt_new_product_name", update);
    },

    // Zoom the matching preview card while its field is focused.
    initFocusZoom: function () {
      var graph = $("#subscrpt-preview-graph");
      var groups = [
        { sel: "#subscrpt_plan_title", group: "plan" },
        {
          sel: "#subscrpt-product-search-input, #subscrpt_new_product_name, #subscrpt-connect-durations [data-connect-price]",
          group: "prod",
        },
      ];
      groups.forEach(function (g) {
        $(document).on("focusin", g.sel, function () {
          graph.attr("data-focus", g.group);
        });
        $(document).on("focusout", g.sel, function () {
          graph.attr("data-focus", "");
        });
      });

      // Durations zoom per card: focusing one duration's field zooms only the
      // preview node that duration maps to (fill order = card order).
      var durFields = "#subscrpt-durations input, #subscrpt-durations .wpsubs-adv-select__trigger";
      $(document).on("focusin", durFields, function () {
        var idx = $("#subscrpt-durations [data-dur]").index($(this).closest("[data-dur]"));
        $("#subscrpt-preview-graph [data-preview-dur]").removeClass("is-zoom");
        $('#subscrpt-preview-graph [data-preview-dur="' + idx + '"]').addClass("is-zoom");
      });
      $(document).on("focusout", durFields, function () {
        $("#subscrpt-preview-graph [data-preview-dur]").removeClass("is-zoom");
      });
    },

    // The name shown on the preview's product node, from whichever connect mode
    // is active.
    previewProductName: function () {
      if (this.hasProducts && this.currentConnectMode() === "existing") {
        return this.selectedProductName || "";
      }
      return $.trim($("#subscrpt_new_product_name").val());
    },

    // Show the price range (min–max of the toggled-on durations' prices) on the
    // product node's subtitle; falls back to "Subscribable" when no price set.
    updateProductPriceSub: function () {
      var sym = this.cfg.currency_symbol || "$";
      var prices = [];
      $("#subscrpt-connect-durations [data-connect-row]").each(function () {
        if (!$(this).find("[data-connect-enabled]").is(":checked")) {
          return;
        }
        var raw = $.trim($(this).find("[data-connect-price]").val());
        if (raw === "") {
          return;
        }
        var n = parseFloat(raw);
        if (!isNaN(n) && n >= 0) {
          prices.push(n);
        }
      });

      var fmt = function (v) {
        return sym + v.toFixed(2);
      };
      var text = "Price";
      if (prices.length) {
        var min = Math.min.apply(null, prices);
        var max = Math.max.apply(null, prices);
        text = min === max ? fmt(min) : fmt(min) + " - " + fmt(max);
      }
      $("#subscrpt-preview-prod-sub").text(text);
    },

    // Fill the persistent preview graph from the current form state. Each step
    // updates its own node; empty fields keep the placeholder label.
    updatePreview: function () {
      $("#subscrpt-preview-plan").text($.trim($("#subscrpt_plan_title").val()) || "Your plan");
      $("#subscrpt-preview-plan-type").text($(".wpsubs-plan-type-card.active").data("label") || "Recurring");

      // Duration nodes: stay placeholder ghost cards until the user reaches
      // page 2, then each real duration lights up one node in fill order.
      var self = this;
      var durations = this.reachedDurations ? this.collectDurations() : [];
      $("#subscrpt-preview-graph [data-preview-dur]").each(function () {
        var $node = $(this);
        var idx = parseInt($node.data("preview-dur"), 10) || 0;
        var dur = durations[idx];
        if (dur) {
          $node.removeClass("wpsubs-p1-node--ghost");
          $node.find(".wpsubs-p1-node__title").text(dur.name);
          $node.find(".wpsubs-p1-node__sub").text(self.billingEvery(dur.freq, dur.interval));
        } else {
          $node.addClass("wpsubs-p1-node--ghost");
          $node.find(".wpsubs-p1-node__title").text("Duration");
          $node.find(".wpsubs-p1-node__sub").text("Add more");
        }
      });

      var prod = this.previewProductName();
      if (prod) {
        $("#subscrpt-preview-prod").text(prod);
      }
      // The product node's subtitle shows the price range from the set pricing.
      this.updateProductPriceSub();

      // Which durations are toggled on for the product (page 3). If the rows
      // aren't rendered yet, treat every duration as on.
      var enabled = {};
      $("#subscrpt-connect-durations [data-connect-row]").each(function () {
        enabled[parseInt($(this).data("connect-dur"), 10)] = $(this).find("[data-connect-enabled]").is(":checked");
      });

      // Connector lines: muted by default. A plan->duration line colours in
      // once its duration is filled; a duration->product line also needs a
      // product to be added and that duration's toggle to be on.
      var hasProduct = !!prod;
      $("#subscrpt-preview-graph [data-line-dur]").each(function () {
        var $line = $(this);
        var idx = parseInt($line.data("line-dur"), 10) || 0;
        var active = !!durations[idx];
        if ($line.attr("data-line-to") === "prod") {
          active = active && hasProduct && enabled[idx] !== false;
        }
        $line.toggleClass("is-active", active);
      });
    },

    // ----- Durations (accordion) -----

    // The "billing every" value: "1 month", "3 days".
    billingEvery: function (freq, interval) {
      var n = parseInt(freq, 10) || 1;
      return n + " " + (interval || "month") + (n > 1 ? "s" : "");
    },

    // "1 month" -> "Every Month", "3 days" -> "Every 3 Days".
    durationName: function (freq, interval) {
      var labels = { day: "Day", week: "Week", month: "Month", year: "Year" };
      var label = labels[interval] || "Month";
      var n = parseInt(freq, 10) || 1;
      return n > 1 ? "Every " + n + " " + label + "s" : "Every " + label;
    },

    initDurations: function () {
      if (!$("#subscrpt-durations [data-dur]").length) {
        this.addDuration();
      }
      this.refreshDurControls();
    },

    addDuration: function (e) {
      if (e && e.preventDefault) {
        e.preventDefault();
      }
      if ($("#subscrpt-durations [data-dur]").length >= this.MAX_DURATIONS) {
        return;
      }
      var tpl = document.getElementById("subscrpt-duration-tpl");
      if (!tpl || !tpl.content) {
        return;
      }
      $("#subscrpt-durations").append(tpl.content.cloneNode(true));
      var card = $("#subscrpt-durations [data-dur]").last();
      // Wire up the cloned cadence picker (adv-select).
      if (window.WPSubsAdvSelect) {
        window.WPSubsAdvSelect.init(card[0]);
      }
      this.syncDurName(card, true);
      this.openDuration(card);
      this.refreshDurControls();
      this.updatePreview();
    },

    openDuration: function (card) {
      $("#subscrpt-durations [data-dur]").removeClass("is-open");
      card.addClass("is-open");
    },

    onDurToggle: function (e) {
      if ($(e.target).closest("[data-dur-remove]").length) {
        return;
      }
      var card = $(e.currentTarget).closest("[data-dur]");
      if (card.hasClass("is-open")) {
        card.removeClass("is-open");
      } else {
        this.openDuration(card);
      }
    },

    onDurRemove: function (e) {
      e.preventDefault();
      e.stopPropagation();
      var cards = $("#subscrpt-durations [data-dur]");
      if (cards.length <= 1) {
        return;
      }
      var card = $(e.currentTarget).closest("[data-dur]");
      var wasOpen = card.hasClass("is-open");
      card.remove();
      if (wasOpen) {
        $("#subscrpt-durations [data-dur]").last().addClass("is-open");
      }
      this.refreshDurControls();
      this.updatePreview();
    },

    onDurBillingInput: function (e) {
      this.syncDurName($(e.target).closest("[data-dur]"), false);
      this.updatePreview();
    },

    onDurNameInput: function (e) {
      var card = $(e.target).closest("[data-dur]");
      card.attr("data-name-edited", "1");
      card.find("[data-dur-title]").text($.trim(card.find("[data-dur-name]").val()) || "Duration");
      this.updatePreview();
    },

    // The interval value ("day"/"week"/"month"/"year") from a card's cadence
    // picker — the adv-select's hidden input.
    durInterval: function (card) {
      return card.find("[data-dur-interval] input[type=hidden]").val() || "month";
    },

    // Refresh the auto name from the billing period (unless the user edited it)
    // and mirror the name into the card header.
    syncDurName: function (card, force) {
      var auto = this.durationName(card.find("[data-dur-freq]").val(), this.durInterval(card));
      if (force || card.attr("data-name-edited") !== "1") {
        card.find("[data-dur-name]").val(auto);
      }
      card.find("[data-dur-title]").text($.trim(card.find("[data-dur-name]").val()) || auto);
    },

    refreshDurControls: function () {
      var count = $("#subscrpt-durations [data-dur]").length;
      $("#subscrpt-durations").toggleClass("has-multiple", count > 1);
      // The preview has 3 duration slots — no more durations past that.
      $("#subscrpt-btn-add-duration").toggle(count < this.MAX_DURATIONS);
    },

    collectDurations: function () {
      var self = this;
      var out = [];
      $("#subscrpt-durations [data-dur]").each(function () {
        var $c = $(this);
        var freq = parseInt($c.find("[data-dur-freq]").val(), 10) || 1;
        var interval = self.durInterval($c);
        var name = $.trim($c.find("[data-dur-name]").val()) || self.durationName(freq, interval);
        out.push({ freq: freq, interval: interval, name: name });
      });
      return out;
    },

    // Nothing is created here — just validate and move on. The plan, durations
    // and product are all created together on the final step.
    nextFromDurations: function (e) {
      e.preventDefault();
      if (!$.trim($("#subscrpt_plan_title").val())) {
        window.alert("Please enter a plan name.");
        this.switchSection(1);
        return;
      }
      if (!this.collectDurations().length) {
        window.alert("Please add at least one duration.");
        return;
      }
      this.switchSection(3);
    },

    // ----- Page 3: connect to a product -----

    // Which connect mode is active. Without existing products the only option
    // is creating a new one.
    currentConnectMode: function () {
      if (!this.hasProducts) {
        return "new";
      }
      var active = $(".wpsubs-connect-mode-card.active");
      return active.length ? active.data("mode") : "existing";
    },

    selectConnectMode: function (e) {
      var card = $(e.currentTarget);
      var mode = card.data("mode");
      $(".wpsubs-connect-mode-card").removeClass("active");
      card.addClass("active");
      $("#subscrpt-connect-existing").toggle(mode === "existing");
      $("#subscrpt-connect-new").toggle(mode === "new");
      this.updatePreview();
    },

    openProductSearch: function () {
      $(".wpsubs-p2-product-search__item").show();
      $(".wpsubs-p2-product-search__empty").hide();
      $("#subscrpt-product-search-dropdown").show();
    },

    filterProducts: function (e) {
      var q = $(e.target).val().toLowerCase().trim();
      $("#subscrpt-product-search-dropdown").show();
      var visible = 0;
      $(".wpsubs-p2-product-search__item").each(function () {
        var name = $(this).data("name") ? String($(this).data("name")).toLowerCase() : "";
        var sku = $(this).data("sku") ? String($(this).data("sku")).toLowerCase() : "";
        if (!q || name.indexOf(q) >= 0 || sku.indexOf(q) >= 0) {
          $(this).show();
          visible++;
        } else {
          $(this).hide();
        }
      });
      $(".wpsubs-p2-product-search__empty").toggle(visible === 0);
    },

    onProductPick: function (e) {
      var item = $(e.currentTarget);
      // Products already attached to a plan can't be picked.
      if (item.attr("data-connected") === "1") {
        return;
      }
      var id = String(item.data("id"));
      var name = item.data("name") || "";
      var price = item.data("price") != null ? String(item.data("price")) : "";
      var type = item.data("type") || "";
      var sku = item.data("sku") ? String(item.data("sku")) : "";

      $("#subscrpt-existing-product-hidden").val(id);
      $("#subscrpt-product-search-dropdown").hide();

      var initials = name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map(function (w) {
          return w[0].toUpperCase();
        })
        .join("");
      var meta = [sku ? "SKU " + sku : null, type].filter(Boolean).join(" · ");

      $("#p3-chip-avatar").text(initials || "?");
      $("#p3-chip-name").text(name);
      $("#p3-chip-meta").text(meta);
      $("#subscrpt-product-select-wrap").hide();
      $("#subscrpt-selected-product-chip").show();

      // Prefill any empty duration price with the product's price as a starting
      // point; leave prices the user already typed alone.
      if (price) {
        $("#subscrpt-connect-durations [data-connect-price]").each(function () {
          if (!$.trim($(this).val())) {
            $(this).val(parseFloat(price).toFixed(2));
          }
        });
      }

      this.selectedProductName = name;
      this.updatePreview();
    },

    clearProduct: function () {
      $("#subscrpt-existing-product-hidden").val("");
      $("#subscrpt-product-search-input").val("");
      $(".wpsubs-p2-product-search__item").show();
      $("#subscrpt-selected-product-chip").hide();
      $("#subscrpt-product-select-wrap").show();
      this.selectedProductName = "";
    },

    // Snapshot the current price/toggle of each duration row, keyed by index,
    // so re-rendering doesn't lose what the user typed.
    readConnectState: function () {
      var state = {};
      $("#subscrpt-connect-durations [data-connect-row]").each(function () {
        var idx = parseInt($(this).data("connect-dur"), 10);
        state[idx] = {
          price: $(this).find("[data-connect-price]").val(),
          enabled: $(this).find("[data-connect-enabled]").is(":checked"),
        };
      });
      return state;
    },

    // Build one pricing row per duration (from step 2), preserving any values
    // already entered. All rows connect to the single product.
    renderConnectDurations: function () {
      var self = this;
      var tpl = document.getElementById("subscrpt-connect-dur-tpl");
      if (!tpl || !tpl.content) {
        return;
      }
      var prev = this.readConnectState();
      var durations = this.collectDurations();
      var $wrap = $("#subscrpt-connect-durations").empty();

      durations.forEach(function (dur, idx) {
        var $frag = $(tpl.content.cloneNode(true));
        var $row = $frag.find("[data-connect-row]");
        $row.attr("data-connect-dur", idx);
        $row.find("[data-connect-name]").text(dur.name);
        $row.find("[data-connect-billing]").text("Billing every " + self.billingEvery(dur.freq, dur.interval));
        if (prev[idx]) {
          $row.find("[data-connect-price]").val(prev[idx].price);
          $row.find("[data-connect-enabled]").prop("checked", prev[idx].enabled);
        }
        $row.toggleClass("is-off", !$row.find("[data-connect-enabled]").is(":checked"));
        $wrap.append($frag);
      });
    },

    onConnectToggle: function (e) {
      var $row = $(e.target).closest("[data-connect-row]");
      $row.toggleClass("is-off", !$(e.target).is(":checked"));
      this.updatePreview();
    },

    // Nothing is created here either — validate the product choice and the
    // per-duration prices, then move to the final step which does all the work.
    nextFromProduct: function (e) {
      e.preventDefault();

      if (this.currentConnectMode() === "existing") {
        if (!$("#subscrpt-existing-product-hidden").val()) {
          window.alert("Please select a product.");
          return;
        }
      } else if (!$.trim($("#subscrpt_new_product_name").val())) {
        window.alert("Please enter a product name.");
        return;
      }

      var rows = $("#subscrpt-connect-durations [data-connect-row]");
      var enabledRows = rows.filter(function () {
        return $(this).find("[data-connect-enabled]").is(":checked");
      });
      if (!enabledRows.length) {
        window.alert("Please keep at least one duration on.");
        return;
      }
      var badPrice = false;
      enabledRows.each(function () {
        var p = $.trim($(this).find("[data-connect-price]").val());
        if (p && (isNaN(parseFloat(p)) || parseFloat(p) < 0)) {
          badPrice = true;
        }
      });
      if (badPrice) {
        window.alert("Please enter valid prices.");
        return;
      }

      this.switchSection(4);
      this.finalize();
    },

    // ----- Page 4: create everything sequentially -----

    // Mark a progress step's state ("is-doing" / "is-done").
    finalizeStep: function (key, state) {
      $('[data-finalize-step="' + key + '"]')
        .removeClass("is-doing is-done")
        .addClass(state);
    },

    // Run the whole creation flow in order: plan -> durations -> product +
    // relations. Each sub-step is skipped if a retry already completed it.
    finalize: function (e) {
      if (e && e.preventDefault) {
        e.preventDefault();
      }
      if (this.finalizeRunning) {
        return;
      }
      this.finalizeRunning = true;

      var self = this;
      $("#subscrpt-finalize-error, #subscrpt-finalize-success").attr("hidden", "hidden");
      $("#subscrpt-finalize-progress").removeAttr("hidden");
      $("#subscrpt-link-plans, #subscrpt-link-products").attr("hidden", "hidden");
      $("[data-finalize-step]").removeClass("is-doing is-done");

      this.ensurePlan()
        .then(function () {
          return self.ensureProductAndConnect();
        })
        .then(function () {
          self.finalizeRunning = false;
          self.showDone(self.finalProductName);
        })
        .catch(function (err) {
          self.finalizeRunning = false;
          $("#subscrpt-finalize-progress").attr("hidden", "hidden");
          $("#subscrpt-finalize-error-msg").text(err && err.message ? err.message : "Please try again.");
          $("#subscrpt-finalize-error").removeAttr("hidden");
        });
    },

    termBody: function (dur, groupId, type) {
      return {
        plan_group_id: groupId,
        type: type,
        title: dur.name,
        billing_frequency: dur.freq,
        billing_interval: INTERVAL_TO_INT[dur.interval] || 3,
        billing_length: 0,
        free_trial: "",
        signup_fee: { amount: "" },
        status: "active",
        data: { free_trial_interval: "day" },
      };
    },

    // Create the plan group + one term per duration (reusing the auto-seeded
    // draft term for the first). No-op if a retry already created them.
    ensurePlan: function () {
      var self = this;
      if (this.groupId && this.termIds && this.termIds.length) {
        this.finalizeStep("plan", "is-done");
        this.finalizeStep("durations", "is-done");
        return Promise.resolve();
      }

      var title = $.trim($("#subscrpt_plan_title").val());
      var type = $("#subscrpt-plan-type").val() || "recurring";
      var durations = this.collectDurations();

      this.finalizeStep("plan", "is-doing");
      return this.api("POST", "/groups", { title: title, type: type, product_type: 1, status: "active" }).then(
        function (group) {
          self.groupId = group.id;
          self.planTitle = title;
          self.billingText = durations[0].name;
          self.finalizeStep("plan", "is-done");
          self.finalizeStep("durations", "is-doing");

          var seeded = group.plans && group.plans.length ? group.plans[0] : null;
          var termIds = [];
          var chain = durations.reduce(function (promise, dur, idx) {
            return promise.then(function () {
              var body = self.termBody(dur, group.id, type);
              if (idx === 0 && seeded && seeded.id) {
                return self.api("PUT", "/terms/" + seeded.id, body).then(function () {
                  termIds.push(seeded.id);
                });
              }
              return self.api("POST", "/terms", body).then(function (term) {
                termIds.push(term.id);
              });
            });
          }, Promise.resolve());

          return chain.then(function () {
            self.termIds = termIds;
            self.finalizeStep("durations", "is-done");
          });
        },
      );
    },

    // Create the product (if new) and connect the plan to it. No-op if a retry
    // already connected it.
    ensureProductAndConnect: function () {
      var self = this;
      if (this.relationsCreated) {
        this.finalizeStep("product", "is-done");
        return Promise.resolve();
      }
      this.finalizeStep("product", "is-doing");
      return this.ensureProduct()
        .then(function () {
          return self.createRelations();
        })
        .then(function () {
          self.relationsCreated = true;
          self.finalizeStep("product", "is-done");
        });
    },

    // Resolve the product id — an existing selection, or a newly created one
    // (name only; each duration carries its own price on the relation).
    ensureProduct: function () {
      var self = this;
      if (this.finalProductId) {
        return Promise.resolve();
      }

      if (this.currentConnectMode() === "existing") {
        this.finalProductId = $("#subscrpt-existing-product-hidden").val();
        this.finalProductName = this.selectedProductName || "Product";
        return Promise.resolve();
      }

      var name = $.trim($("#subscrpt_new_product_name").val());
      this.finalProductName = name;
      return new Promise(function (resolve, reject) {
        $.post(
          self.cfg.ajax_url,
          {
            action: "subscrpt_create_wizard_product",
            nonce: $("#subscrpt_wizard_nonce").val(),
            product_name: name,
          },
          function (response) {
            if (response && response.success) {
              self.finalProductId = response.data.product_id;
              resolve();
            } else {
              reject(
                new Error((response && response.data && response.data.message) || "Could not create the product."),
              );
            }
          },
        ).fail(function () {
          reject(new Error("Server error. Please try again."));
        });
      });
    },

    // Connect the product to each toggled-on duration, using that duration's
    // own price. Durations toggled off get no relation.
    createRelations: function () {
      var self = this;
      var rows = $("#subscrpt-connect-durations [data-connect-row]");
      var calls = [];
      (this.termIds || []).forEach(function (tid, idx) {
        var $row = rows.filter('[data-connect-dur="' + idx + '"]');
        var enabled = $row.length ? $row.find("[data-connect-enabled]").is(":checked") : true;
        if (!enabled) {
          return;
        }
        var price = $row.length ? $.trim($row.find("[data-connect-price]").val()) : "";
        calls.push(
          self.api("POST", "/relations", {
            plan_id: tid,
            oid: parseInt(self.finalProductId, 10),
            vid: 0,
            type: 1,
            status: "active",
            exclude: false,
            data: { regular_price: price, sale_price: "", discount_value: 0 },
          }),
        );
      });
      return Promise.all(calls);
    },

    showDone: function (productName) {
      if (productName) {
        $("#subscrpt-preview-prod").text(productName);
      }
      $("#subscrpt-finalize-progress, #subscrpt-finalize-error").attr("hidden", "hidden");
      $("#subscrpt-finalize-success").removeAttr("hidden");
      $("#subscrpt-link-plans, #subscrpt-link-products").removeAttr("hidden");
      $("#subscrpt-preview-graph").attr("data-active", "done");
      this.updatePreview();
    },

    restart: function (e) {
      e.preventDefault();
      var self = this;
      $.post(
        this.cfg.ajax_url,
        {
          action: "subscrpt_reset_wizard",
          nonce: $("#subscrpt_wizard_nonce").val(),
        },
        function () {
          self.groupId = 0;
          self.termIds = [];
          self.planTitle = "";
          self.billingText = "";
          self.finalProductId = "";
          self.finalProductName = "";
          self.relationsCreated = false;
          self.finalizeRunning = false;
          // Reset the final step back to its progress state for a fresh run.
          $("#subscrpt-finalize-success, #subscrpt-finalize-error").attr("hidden", "hidden");
          $("#subscrpt-finalize-progress").removeAttr("hidden");
          $("[data-finalize-step]").removeClass("is-doing is-done");
          $("#subscrpt-link-plans, #subscrpt-link-products").attr("hidden", "hidden");
          $("#subscrpt_new_product_name").val("");
          $("#subscrpt-connect-durations").empty();
          $("#subscrpt-durations").empty();
          self.initDurations();
          self.clearProduct();
          // Reset the preview nodes back to their placeholders.
          $("#subscrpt-preview-prod").text("Product");
          self.reachedDurations = false;
          $("#subscrpt-preview-graph [data-preview-dur]")
            .addClass("wpsubs-p1-node--ghost")
            .find(".wpsubs-p1-node__title")
            .text("Duration");
          $("#subscrpt-preview-graph [data-preview-dur] .wpsubs-p1-node__sub").text("Add more");
          self.switchSection(1);
        },
      );
    },
  };

  $(document).ready(function () {
    Wizard.init();
  });
})(jQuery);
