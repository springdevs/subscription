/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "@woocommerce/blocks-checkout":
/*!****************************************!*\
  !*** external ["wc","blocksCheckout"] ***!
  \****************************************/
/***/ ((module) => {

module.exports = window["wc"]["blocksCheckout"];

/***/ }),

/***/ "@woocommerce/blocks-components":
/*!******************************************!*\
  !*** external ["wc","blocksComponents"] ***!
  \******************************************/
/***/ ((module) => {

module.exports = window["wc"]["blocksComponents"];

/***/ }),

/***/ "@woocommerce/price-format":
/*!*************************************!*\
  !*** external ["wc","priceFormat"] ***!
  \*************************************/
/***/ ((module) => {

module.exports = window["wc"]["priceFormat"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "@wordpress/plugins":
/*!*********************************!*\
  !*** external ["wp","plugins"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["plugins"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @woocommerce/blocks-checkout */ "@woocommerce/blocks-checkout");
/* harmony import */ var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @woocommerce/blocks-components */ "@woocommerce/blocks-components");
/* harmony import */ var _woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/plugins */ "@wordpress/plugins");
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_plugins__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _woocommerce_price_format__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @woocommerce/price-format */ "@woocommerce/price-format");
/* harmony import */ var _woocommerce_price_format__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_price_format__WEBPACK_IMPORTED_MODULE_5__);






// import { useStoreCart } from "@woocommerce/base-context/hooks";

const modifyCartItemPrice = (defaultValue, extensions, args, validation) => {
  const {
    sdevs_subscription
  } = extensions;
  const {
    cartItem
  } = args;
  const {
    totals
  } = cartItem;
  if (totals === undefined) {
    return defaultValue;
  }
  if (totals.line_total === "0") {
    return `<price/> Due Today`;
  }
  if (sdevs_subscription.type) {
    return `<price/> / ${sdevs_subscription.time ? " " + sdevs_subscription.time + "-" : ""}${sdevs_subscription.type}`;
  }
  return defaultValue;
};
const modifySubtotalPriceFormat = (defaultValue, extensions, args, validation) => {
  const {
    sdevs_subscription
  } = extensions;
  if (sdevs_subscription && sdevs_subscription.type) {
    return `<price/> every ${sdevs_subscription.time && sdevs_subscription.time === 1 ? " " + sdevs_subscription.time + "-" : ""}${sdevs_subscription.type}`;
  }
  return defaultValue;
};
(0,_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_1__.registerCheckoutFilters)("sdevs-subscription", {
  cartItemPrice: modifyCartItemPrice,
  subtotalPriceFormat: modifySubtotalPriceFormat
});
const RecurringTotals = ({
  cart,
  extensions
}) => {
  if (Object.keys(extensions).length === 0) {
    return;
  }
  const {
    cartTotals
  } = cart;
  const {
    sdevs_subscription: recurrings
  } = extensions;
  const currency = (0,_woocommerce_price_format__WEBPACK_IMPORTED_MODULE_5__.getCurrencyFromPriceResponse)(cartTotals);
  if (recurrings.length === 0) {
    return;
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_1__.TotalsItem, {
    className: "wc-block-components-totals-footer-item",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Recurring totals", "sdevs_subscrpt"),
    description: recurrings.map(recurring => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        margin: "20px 0",
        float: "right"
      }
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        fontSize: "18px"
      }
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_2__.FormattedMonetaryAmount, {
      currency: currency,
      value: parseInt(recurring.price, 10)
    }), "/", " ", recurring.time && recurring.time > 1 ? `${recurring.time + "-" + recurring.type} ` : recurring.type), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("small", null, recurring.description)))
  });
};
const render = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_1__.ExperimentalOrderMeta, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(RecurringTotals, null));
};
(0,_wordpress_plugins__WEBPACK_IMPORTED_MODULE_3__.registerPlugin)("sdevs-subscription", {
  render,
  scope: "woocommerce-checkout"
});
/******/ })()
;
//# sourceMappingURL=index.js.map