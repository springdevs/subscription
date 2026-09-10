/**
 * Onboarding Wizard — SPA-style.
 *
 * Flow:
 *   1. Create Plan   — pick a plan type + name (name auto-fills from the type).
 *   2. Set Frequency — billing duration (frequency, interval, trial, fee).
 *   3. Connect       — attach the plan to a new or existing product.
 *   4. Finish        — summary.
 *
 * The plan (group), duration (term) and product relation are created through
 * the Plans REST API (wpsubscription/v1/plans) once both the plan details and
 * the frequency are known (leaving page 2). Creating a brand-new product uses
 * an admin-ajax handler. All PHP values arrive via `subscrpt_wizard`.
 */
(function ($) {
  "use strict";

  var INTERVAL_TO_INT = { day: 1, week: 2, month: 3, year: 4 };

  var Wizard = {
    cfg: {},
    autoName: "",
    // Created leaving page 2, used on page 3.
    groupId: 0,
    termId: 0,
    planTitle: "",
    billingText: "",

    init: function () {
      this.cfg = window.subscrpt_wizard || {};
      this.hasProducts = $("#subscrpt-has-products").val() === "1";
      this.autoName = $.trim($("#subscrpt_plan_title").val());
      this.bindEvents();
      this.initLivePreview();
      this.initFocusZoom();
      this.updatePreview();
      $("#subscrpt-link-plans").attr("href", this.cfg.plans_url || "#");
      $("#subscrpt-link-products").attr("href", this.cfg.products_url || "#");
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
      // Page 1 (plan).
      $(document).on("click", ".wpsubs-plan-type-card", $.proxy(this.selectPlanType, this));
      $(document).on("click", "#subscrpt-btn-skip", $.proxy(this.skip, this));
      $(document).on("click", "#subscrpt-btn-next-1", $.proxy(this.nextFromPlan, this));

      // Page 2 (frequency).
      $(document).on("click", "#subscrpt-btn-back-1", $.proxy(this.goToPage, this, 1));
      $(document).on("click", "#subscrpt-btn-create-plan", $.proxy(this.createPlan, this));

      // Page 3 (product).
      $(document).on("click", "#subscrpt-btn-back-2", $.proxy(this.goToPage, this, 2));
      $(document).on("click", ".wpsubs-connect-mode-card", $.proxy(this.selectConnectMode, this));
      $(document).on("click", "#subscrpt-btn-connect", $.proxy(this.connect, this));
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

      // Swap the footer nav to this step's buttons.
      $(".wpsubs-wizard-nav").attr("hidden", "hidden");
      $('.wpsubs-wizard-nav[data-nav="' + pageNum + '"]').removeAttr("hidden");

      // Light up the part of the preview this step fills in.
      var groups = { 1: "plan", 2: "dur", 3: "prod", 4: "done" };
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
      // All the fields whose typing should reflect into the preview graph.
      $(document).on(
        "input",
        "#subscrpt_plan_title, #subscrpt_billing_frequency, #subscrpt_free_trial, #subscrpt_new_product_name",
        update,
      );
      $(document).on("wpsubs:select", "#subscrpt-billing-interval-select, #subscrpt-trial-interval-select", update);
    },

    // Zoom the matching preview card while its field is focused.
    initFocusZoom: function () {
      var graph = $("#subscrpt-preview-graph");
      var groups = [
        { sel: "#subscrpt_plan_title", group: "plan" },
        {
          sel: "#subscrpt_billing_frequency, #subscrpt_free_trial, #subscrpt_signup_fee, #subscrpt-billing-interval-select, #subscrpt-trial-interval-select",
          group: "dur",
        },
        { sel: "#subscrpt-product-search-input, #subscrpt_new_product_name, #subscrpt_connect_price", group: "prod" },
      ];
      groups.forEach(function (g) {
        $(document).on("focusin", g.sel, function () {
          graph.attr("data-focus", g.group);
        });
        $(document).on("focusout", g.sel, function () {
          graph.attr("data-focus", "");
        });
      });
    },

    unitLabel: function (unit, count) {
      var n = parseInt(count, 10) || 1;
      return n > 1 ? n + " " + unit + "s" : unit;
    },

    // The name shown on the preview's product node, from whichever connect mode
    // is active.
    previewProductName: function () {
      if (this.hasProducts && this.currentConnectMode() === "existing") {
        return this.selectedProductName || "";
      }
      return $.trim($("#subscrpt_new_product_name").val());
    },

    // Fill the persistent preview graph from the current form state. Each step
    // updates its own node; empty fields keep the placeholder label.
    updatePreview: function () {
      $("#subscrpt-preview-plan").text($.trim($("#subscrpt_plan_title").val()) || "Your plan");
      $("#subscrpt-preview-plan-type").text($(".wpsubs-plan-type-card.active").data("label") || "Recurring");

      var freq = $("#subscrpt_billing_frequency").val() || "1";
      var interval = $("input[name='subscrpt_billing_interval']").val() || "month";
      $("#subscrpt-preview-dur").text("Every " + this.unitLabel(interval, freq));

      var prod = this.previewProductName();
      if (prod) {
        $("#subscrpt-preview-prod").text(prod);
      }
    },

    createPlan: function (e) {
      e.preventDefault();

      var self = this;
      var $btn = $("#subscrpt-btn-create-plan");
      var title = $.trim($("#subscrpt_plan_title").val());
      var type = $("#subscrpt-plan-type").val() || "recurring";

      if (!title) {
        window.alert("Please enter a plan name.");
        return;
      }

      var freq = parseInt($("#subscrpt_billing_frequency").val(), 10) || 1;
      var interval = $("input[name='subscrpt_billing_interval']").val() || "month";
      var trial = $.trim($("#subscrpt_free_trial").val());
      var trialInterval = $("input[name='subscrpt_trial_interval']").val() || "day";
      var signupFee = this.cfg.is_pro ? $.trim($("#subscrpt_signup_fee").val()) : "";

      if ($btn.hasClass("is-loading")) {
        return;
      }
      $btn.addClass("is-loading").prop("disabled", true);

      var termBody = {
        type: type,
        title: title,
        billing_frequency: freq,
        billing_interval: INTERVAL_TO_INT[interval] || 3,
        billing_length: 0,
        free_trial: trial || "",
        signup_fee: { amount: signupFee || "" },
        status: "active",
        data: { free_trial_interval: trialInterval },
      };

      // 1) Create the plan group. 2) Reuse the auto-seeded draft term (or create
      // one) with the chosen duration.
      this.api("POST", "/groups", {
        title: title,
        type: type,
        product_type: 1,
        status: "active",
      })
        .then(function (group) {
          self.groupId = group.id;
          self.planTitle = title;
          self.billingText = "every " + self.unitLabel(interval, freq);
          termBody.plan_group_id = group.id;

          var seeded = group.plans && group.plans.length ? group.plans[0] : null;
          if (seeded && seeded.id) {
            return self.api("PUT", "/terms/" + seeded.id, termBody).then(function () {
              return seeded.id;
            });
          }
          return self.api("POST", "/terms", termBody).then(function (term) {
            return term.id;
          });
        })
        .then(function (termId) {
          self.termId = termId;
          $btn.removeClass("is-loading").prop("disabled", false);
          self.switchSection(3);
        })
        .catch(function (err) {
          $btn.removeClass("is-loading").prop("disabled", false);
          window.alert(err.message || "Could not create the plan. Please try again.");
        });
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
      $("#subscrpt-btn-connect").html((mode === "new" ? "Create & connect" : "Connect plan") + " ›");
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

      if (price && !$.trim($("#subscrpt_connect_price").val())) {
        $("#subscrpt_connect_price").val(parseFloat(price).toFixed(2));
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

    connect: function (e) {
      e.preventDefault();

      var self = this;
      var $btn = $("#subscrpt-btn-connect");
      var price = $.trim($("#subscrpt_connect_price").val());

      if (price && (isNaN(parseFloat(price)) || parseFloat(price) < 0)) {
        window.alert("Please enter a valid price.");
        return;
      }

      if ($btn.hasClass("is-loading")) {
        return;
      }

      if (this.currentConnectMode() === "existing") {
        var productId = $("#subscrpt-existing-product-hidden").val();
        if (!productId) {
          window.alert("Please select a product.");
          return;
        }
        $btn.addClass("is-loading").prop("disabled", true);
        this.createRelation(productId, this.selectedProductName || "Product", price, $btn);
      } else {
        var name = $.trim($("#subscrpt_new_product_name").val());
        if (!name) {
          window.alert("Please enter a product name.");
          return;
        }
        $btn.addClass("is-loading").prop("disabled", true);
        // Create the product first, then connect the plan to it.
        $.post(
          this.cfg.ajax_url,
          {
            action: "subscrpt_create_wizard_product",
            nonce: $("#subscrpt_wizard_nonce").val(),
            product_name: name,
            product_price: price,
          },
          function (response) {
            if (response && response.success) {
              self.createRelation(response.data.product_id, name, price, $btn);
            } else {
              $btn.removeClass("is-loading").prop("disabled", false);
              window.alert((response && response.data && response.data.message) || "Could not create the product.");
            }
          },
        ).fail(function () {
          $btn.removeClass("is-loading").prop("disabled", false);
          window.alert("Server error. Please try again.");
        });
      }
    },

    createRelation: function (productId, productName, price, $btn) {
      var self = this;
      this.api("POST", "/relations", {
        plan_id: this.termId,
        oid: parseInt(productId, 10),
        vid: 0,
        type: 1,
        status: "active",
        exclude: false,
        data: { regular_price: price, sale_price: "", discount_value: 0 },
      })
        .then(function () {
          $btn.removeClass("is-loading").prop("disabled", false);
          self.showDone(productName, price);
        })
        .catch(function (err) {
          $btn.removeClass("is-loading").prop("disabled", false);
          window.alert(err.message || "Could not connect the plan. Please try again.");
        });
    },

    // ----- Page 4: finish -----

    showDone: function (productName) {
      // The finished flow is shown by the persistent preview graph.
      if (productName) {
        $("#subscrpt-preview-prod").text(productName);
      }
      this.switchSection(4);
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
          self.termId = 0;
          self.planTitle = "";
          self.billingText = "";
          $("#subscrpt_free_trial, #subscrpt_signup_fee, #subscrpt_connect_price, #subscrpt_new_product_name").val("");
          $("#subscrpt_billing_frequency").val("1");
          self.clearProduct();
          // Reset the preview product node back to its placeholder.
          $("#subscrpt-preview-prod").text("Product");
          self.switchSection(1);
        },
      );
    },
  };

  $(document).ready(function () {
    Wizard.init();
  });
})(jQuery);
