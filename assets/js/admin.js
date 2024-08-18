jQuery(document).ready(() => {
  let sdevs_enable_subscription = jQuery("input#subscrpt_enable");
  sdevs_enable_subscription.change(() => {
    if (sdevs_enable_subscription.is(":checked")) {
      jQuery(".show_if_subscription").show();
    } else {
      jQuery(".show_if_subscription").hide();
    }
  });
  if (sdevs_enable_subscription.is(":checked")) {
    jQuery(".show_if_subscription").show();
  } else {
    jQuery(".show_if_subscription").hide();
  }

  jQuery(document).on("woocommerce_variations_loaded", () => {
    let total_variations = JSON.parse(
      jQuery(".woocommerce_variations").attr("data-total")
    );
    for (let index = 0; index < total_variations; index++) {
      let element = document.getElementById("subscrpt_enable[" + index + "]");
      if (element && element.checked) {
        jQuery("div#show_if_subscription_" + index).show();
      } else {
        jQuery("div#show_if_subscription_" + index).hide();
      }
    }
  });

  let subscrpt_renewal_process = jQuery("#subscrpt_renewal_process");
  subscrpt_renewal_process.change(() => {
    if (subscrpt_renewal_process.val() === "manual") {
      jQuery("#sdevs_renewal_cart_tr").show();
      jQuery("#subscrpt_stripe_auto_renew_tr").hide();
      jQuery("#subscrpt_auto_renewal_toggle_tr").hide();
    } else {
      jQuery("#sdevs_renewal_cart_tr").hide();
      jQuery("#subscrpt_stripe_auto_renew_tr").show();
      jQuery("#subscrpt_auto_renewal_toggle_tr").show();
    }
  });
  if (subscrpt_renewal_process.val() === "manual") {
    jQuery("#sdevs_renewal_cart_tr").show();
    jQuery("#subscrpt_stripe_auto_renew_tr").hide();
    jQuery("#subscrpt_auto_renewal_toggle_tr").hide();
  } else {
    jQuery("#sdevs_renewal_cart_tr").hide();
    jQuery("#subscrpt_stripe_auto_renew_tr").show();
    jQuery("#subscrpt_auto_renewal_toggle_tr").show();
  }
});

function hellochange(index) {
  if (document.getElementById("subscrpt_enable[" + index + "]").checked) {
    jQuery("div#show_if_subscription_" + index).show();
  } else {
    jQuery("div#show_if_subscription_" + index).hide();
  }
}

let subscrpt_product_type = jQuery("#product-type");
let latest_value_of_subscrpt_product_type = subscrpt_product_type.val();

subscrpt_product_type.change(() => {
  if (
    "simple" === latest_value_of_subscrpt_product_type &&
    "simple" !== subscrpt_product_type.val() &&
    "variable" !== subscrpt_product_type.val()
  ) {
    const confirmTypeChange = confirm(
      "Are you sure to change the product type ? If product type changed then You'll lose related subscriptions beacuse of they can't be renewed !"
    );
    if (confirmTypeChange) {
      latest_value_of_subscrpt_product_type = subscrpt_product_type.val();
    } else {
      subscrpt_product_type.val("simple");
    }
  }
});
