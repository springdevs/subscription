/**
 * Cancellation feedback modal.
 *
 * Intercepts the Cancel action link on the single-subscription page, shows a
 * feedback modal, records the customer's reason + comment via AJAX, then follows
 * the original secure cancel URL. Recording is best-effort: if the request fails,
 * the cancellation still proceeds.
 *
 * Backing out is reported too. Dismissing the modal - Keep subscription, the X,
 * the overlay or Escape - is a retention event the store owner wants to hear
 * about, so it posts a save before hiding. Also fire-and-forget: a failed report
 * must never trap the customer in a modal they asked to close.
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var modal = document.getElementById("subscrpt-feedback-modal");
    if (!modal) {
      return;
    }

    var cancelLink = document.querySelector(".subscrpt_action_buttons a.cancel");
    if (!cancelLink) {
      return;
    }

    var cancelUrl = cancelLink.getAttribute("href");
    var confirmBtn = document.getElementById("subscrpt-feedback-confirm");
    var reported = false;

    /**
     * Open the modal.
     *
     * @param {Event} e Click event.
     */
    function openModal(e) {
      e.preventDefault();
      modal.hidden = false;
      document.body.style.overflow = "hidden";
      var firstRadio = modal.querySelector('input[name="subscrpt_feedback_reason"]');
      if (firstRadio) firstRadio.focus();
    }

    /**
     * Post the modal's current state to a wp-ajax action.
     *
     * @param {string} action  The wp_ajax action name.
     * @param {Object} [extra] Extra key/value pairs to send.
     * @return {Promise} Resolves when the request settles.
     */
    function post(action, extra) {
      var checked = modal.querySelector('input[name="subscrpt_feedback_reason"]:checked');
      var commentEl = document.getElementById("subscrpt-feedback-comment");

      var params = new URLSearchParams();
      params.append("action", action);
      params.append("nonce", subscrptCancellationFeedback.nonce);
      params.append("subscription_id", modal.getAttribute("data-subscription") || "");
      params.append("reason_key", checked ? checked.value : "");
      params.append("comment", commentEl ? commentEl.value : "");

      if (extra) {
        Object.keys(extra).forEach(function (key) {
          params.append(key, extra[key]);
        });
      }

      return fetch(subscrptCancellationFeedback.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: params.toString(),
      });
    }

    /**
     * Close the modal without cancelling, reporting the save on the way out.
     *
     * Reported once per page view: a customer who opens and closes the modal
     * repeatedly is one save, not several.
     */
    function closeModal() {
      if (!reported) {
        reported = true;
        post("subscrpt_record_cancellation_save").catch(function () {});
      }
      modal.hidden = true;
      document.body.style.overflow = "";
    }

    /**
     * Navigate to the original cancel URL.
     */
    function proceed() {
      window.location.href = cancelUrl;
    }

    /**
     * Swap the offer step for the reasons step.
     */
    function showReasons() {
      modal.querySelectorAll("[data-subscrpt-offer-step]").forEach(function (el) {
        el.hidden = true;
      });
      modal.querySelectorAll("[data-subscrpt-reason-step]").forEach(function (el) {
        el.hidden = false;
      });
    }

    var claimBtn = modal.querySelector("[data-subscrpt-offer-claim]");
    var declineBtn = modal.querySelector("[data-subscrpt-offer-decline]");
    var offerResult = modal.querySelector("[data-subscrpt-offer-result]");

    if (declineBtn) {
      declineBtn.addEventListener("click", showReasons);
    }

    if (claimBtn) {
      claimBtn.addEventListener("click", function () {
        claimBtn.disabled = true;

        // A claimed offer is a save, reported by the endpoint itself.
        reported = true;

        post("subscrpt_claim_cancellation_offer")
          .then(function (res) {
            return res.json();
          })
          .then(function (data) {
            if (!data || !data.success || !data.data || !data.data.code) {
              throw new Error("no offer");
            }
            if (offerResult) {
              offerResult.textContent = data.data.code;
              offerResult.hidden = false;
            }
            claimBtn.hidden = true;
            if (declineBtn) {
              declineBtn.textContent = subscrptCancellationFeedback.doneLabel || "Done";
              declineBtn.removeEventListener("click", showReasons);
              declineBtn.addEventListener("click", function () {
                window.location.reload();
              });
            }
          })
          .catch(function () {
            // No offer could be issued - never strand the customer on a step
            // that cannot complete.
            claimBtn.disabled = false;
            reported = false;
            showReasons();
          });
      });
    }

    cancelLink.addEventListener("click", openModal);

    var dismissEls = modal.querySelectorAll("[data-subscrpt-feedback-dismiss]");
    for (var i = 0; i < dismissEls.length; i++) {
      dismissEls[i].addEventListener("click", closeModal);
    }

    // Close on Escape, but only while this modal is open.
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.hidden) {
        closeModal();
      }
    });

    if (confirmBtn) {
      confirmBtn.addEventListener("click", function () {
        confirmBtn.disabled = true;

        // Cancelling is not a save: stop closeModal reporting one if the page
        // tears down mid-navigation.
        reported = true;

        post("subscrpt_record_cancellation_feedback").then(proceed).catch(proceed);
      });
    }
  });
})();
