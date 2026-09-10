/**
 * WPSubsSave — one way to write to the REST API and one way to say what
 * happened.
 *
 * Every admin screen that writes had grown its own copy of the same three
 * things: a `fetch` wrapper, a button-busy lock, and some way of reporting the
 * result. The wrappers had already drifted (one handled 204, the others did
 * not) and the reporting had not settled at all — a banner here, a blocking
 * `window.alert` there. This is that trio, once.
 *
 * Usage:
 *   var save = WPSubsSave.bind({ restUrl: cfg.restUrl, nonce: cfg.nonce, i18n: i18n });
 *
 *   save.api("PUT", "/terms/12", { status: "active" });   // → Promise
 *
 *   save.run(btn, function () {                            // busy + toast
 *     return save.api("PUT", "/relations/3", body);
 *   }, { success: i18n.saved });
 *
 * `window.confirm` is deliberately left alone: a toast reports, it does not
 * ask, and a destructive action still needs a real answer.
 */
(function () {
  "use strict";

  /**
   * Say something happened. Falls back to nothing rather than throwing when
   * the toast component is not on the page.
   *
   * @param {string} message Text.
   * @param {string} [type]  "success" (default) or "error".
   */
  function notify(message, type) {
    if (window.WPSubsToast && message) {
      window.WPSubsToast.show(message, type);
    }
  }

  /**
   * Mark a button busy while its request is in flight, and lock the controls
   * beside it so the same write cannot be fired twice or abandoned midway.
   * The `is-loading` class draws the spinner (admin-components/buttons.css).
   *
   * @param {HTMLElement} btn     Button.
   * @param {boolean}     loading Loading state.
   */
  function busy(btn, loading) {
    if (!btn) {
      return;
    }
    btn.disabled = loading;
    btn.classList.toggle("is-loading", loading);

    // The row the button sits in: a modal footer, or an inline edit form.
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
   * Bind the helpers to one screen's REST base, nonce and strings.
   *
   * @param {Object} cfg { restUrl, nonce, i18n }.
   * @return {Object} { api, run, busy, notify }.
   */
  function bind(cfg) {
    cfg = cfg || {};
    var i18n = cfg.i18n || {};

    /**
     * Call a REST endpoint under this screen's base.
     *
     * Rejects with the server's own message when it sends one, so a caller
     * can report the real reason rather than a generic failure.
     *
     * @param {string} method HTTP verb.
     * @param {string} path   Path under the base, e.g. "/groups".
     * @param {Object} [body] JSON body for writes.
     * @return {Promise<Object>} Parsed JSON ({} for an empty body).
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
        // 204 and an empty body are both success with nothing to parse;
        // res.json() would throw on either.
        return res.text().then(function (text) {
          var data = {};
          if (text) {
            try {
              data = JSON.parse(text);
            } catch (e) {
              data = {};
            }
          }
          if (!res.ok) {
            throw new Error((data && data.message) || i18n.genericError || "");
          }
          return data;
        });
      });
    }

    /**
     * The whole write: lock the button, do the work, report the outcome,
     * unlock either way.
     *
     * @param {HTMLElement} btn  Button that triggered it (may be null).
     * @param {Function}    work Returns a Promise for the write.
     * @param {Object}     [opts] { success, error } toast messages; pass
     *                            success: false to stay silent.
     * @return {Promise} Resolves after the work and any follow-up.
     */
    function run(btn, work, opts) {
      opts = opts || {};
      busy(btn, true);

      return Promise.resolve()
        .then(work)
        .then(function (result) {
          busy(btn, false);
          if (false !== opts.success) {
            notify(opts.success || i18n.saved);
          }
          return result;
        })
        .catch(function (err) {
          busy(btn, false);
          notify(opts.error || (err && err.message) || i18n.genericError, "error");
          throw err;
        });
    }

    return { api: api, run: run, busy: busy, notify: notify };
  }

  window.WPSubsSave = { bind: bind, busy: busy, notify: notify };
})();
