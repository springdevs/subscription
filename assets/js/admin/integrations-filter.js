/**
 * Live filtering for the Integrations page.
 *
 * Every card is already in the DOM — there are around fifteen — so filtering
 * happens here rather than as a round trip per click, which would be slower and
 * would throw away the scroll position for nothing.
 *
 * The filter rail ships with a `hidden` attribute and is revealed here, so a
 * browser with no JavaScript shows the full, unfiltered list rather than
 * controls that do nothing.
 */
(function () {
  "use strict";

  var root = document.querySelector("[data-subscrpt-integration-filters]");

  if (!root) {
    return;
  }

  var cards = Array.prototype.slice.call(document.querySelectorAll("[data-subscrpt-int-card]"));

  if (!cards.length) {
    return;
  }

  var sections = Array.prototype.slice.call(document.querySelectorAll("[data-subscrpt-int-section]"));
  var summary = root.querySelector("[data-subscrpt-int-summary]");
  var reset = root.querySelector("[data-subscrpt-int-reset]");
  var empty = document.querySelector("[data-subscrpt-int-empty]");
  var chips = Array.prototype.slice.call(root.querySelectorAll("[data-subscrpt-int-chip]"));

  var total = cards.length;

  // Category is single-select (a card has exactly one). Status and tags are
  // multi-select, because "show me Pro and Beta" is a reasonable thing to ask.
  var state = { category: "", status: [], tag: [] };

  function matches(card) {
    if (state.category && card.dataset.category !== state.category) {
      return false;
    }

    if (state.status.length && state.status.indexOf(card.dataset.status) === -1) {
      return false;
    }

    if (state.tag.length) {
      var tags = (card.dataset.tags || "").split(" ");
      var hasAll = state.tag.every(function (t) {
        return tags.indexOf(t) !== -1;
      });
      if (!hasAll) {
        return false;
      }
    }

    return true;
  }

  function apply() {
    var visible = 0;

    cards.forEach(function (card) {
      var show = matches(card);
      card.hidden = !show;
      if (show) {
        visible += 1;
      }
    });

    // A heading over nothing reads as a bug, so hide a section whose cards have
    // all been filtered away.
    sections.forEach(function (section) {
      section.hidden = !section.querySelector("[data-subscrpt-int-card]:not([hidden])");
    });

    var filtering = !!state.category || !!state.status.length || !!state.tag.length;

    if (summary) {
      summary.textContent = filtering
        ? summary.dataset.filtered.replace("%1$s", visible).replace("%2$s", total)
        : summary.dataset.all.replace("%s", total);
    }

    if (reset) {
      reset.hidden = !filtering;
    }

    if (empty) {
      empty.hidden = visible !== 0;
    }
  }

  chips.forEach(function (chip) {
    chip.addEventListener("click", function () {
      var facet = chip.dataset.facet;
      var value = chip.dataset.value;

      if (facet === "category") {
        state.category = state.category === value ? "" : value;
        chips
          .filter(function (c) {
            return c.dataset.facet === "category";
          })
          .forEach(function (c) {
            var on = c.dataset.value === state.category;
            c.classList.toggle("is-active", on);
            if (c.hasAttribute("aria-pressed")) {
              c.setAttribute("aria-pressed", on ? "true" : "false");
            }
          });
      } else {
        var list = state[facet];
        var at = list.indexOf(value);
        if (at === -1) {
          list.push(value);
        } else {
          list.splice(at, 1);
        }
        var on = list.indexOf(value) !== -1;
        chip.classList.toggle("is-active", on);
        chip.setAttribute("aria-pressed", on ? "true" : "false");
      }

      apply();
    });
  });

  if (reset) {
    reset.addEventListener("click", function () {
      state = { category: "", status: [], tag: [] };
      chips.forEach(function (c) {
        var isAll = c.dataset.facet === "category" && c.dataset.value === "";
        c.classList.toggle("is-active", isAll);
        if (c.hasAttribute("aria-pressed")) {
          c.setAttribute("aria-pressed", "false");
        }
      });
      apply();
    });
  }

  root.hidden = false;
  apply();
})();
