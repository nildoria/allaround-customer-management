jQuery(document).ready(function ($) {
  /**
   * Set the Same Height to all Content area.
   */
  function setProductDetailsHeight() {
    var windowWidth = $(window).width();

    if (windowWidth > 767) {
      var tallestHeight = 0;
      $(".product-item").each(function () {
        var height = $(this).height() - 210;
        if (height > tallestHeight) {
          tallestHeight = height;
        }
      });

      $(".product-item-details").css("height", tallestHeight + "px");
    } else {
      $(".product-item-details").css("height", "auto");
    }
  }

  setProductDetailsHeight();

  $(window).resize(function () {
    setProductDetailsHeight();
  });

  var isotope_initalize = function () {
    /**
     * isotope Filtering.
     */
    var $grid = $("#allaround_products_list").isotope({
      itemSelector: ".product-item",
      layoutMode: "fitRows",
    });

    $(document).on("click", ".product-filter .filter-button", function () {
      var filterValue = $(this).attr("data-filter");
      $grid.isotope({ filter: filterValue });
    });
  };

  isotope_initalize();
  $(window).resize(isotope_initalize);

  // overwrite woocommerce scroll to notices
  $.scroll_to_notices = function (scrollElement) {
    var offset = 300;
    if (scrollElement.length) {
      $("html, body").animate(
        {
          scrollTop: scrollElement.offset().top - offset,
        },
        1000
      );
    }
  };

  /* Storage Handling */
  var $supports_html5_storage = true,
    cart_hash_key = wc_cart_fragments_params.cart_hash_key;

  try {
    $supports_html5_storage =
      "sessionStorage" in window && window.sessionStorage !== null;
  } catch (err) {
    $supports_html5_storage = false;
  }

  /* Cart session creation time to base expiration on */
  function set_cart_creation_timestamp() {
    if ($supports_html5_storage) {
      sessionStorage.setItem("wc_cart_created", new Date().getTime());
    }
  }

  /** Set the cart hash in both session and local storage */
  function set_cart_hash(cart_hash) {
    if ($supports_html5_storage) {
      localStorage.setItem(cart_hash_key, cart_hash);
      sessionStorage.setItem(cart_hash_key, cart_hash);
    }
  }

  $(document).on("click", ".alarnd--payout-trigger", function (e) {
    e.preventDefault();

    var current = $(this),
      customerDetails = $("#customerDetails");

    if (!customerDetails.valid()) {
      current.prop("disabled", true);
      return false;
    }

    var cdetails = customerDetails.serializeArray();
    var customerDetails = {};

    // Convert the serialized array to a key-value object
    $.each(cdetails, function (index, item) {
      customerDetails[item.name] = item.value;
    });
    // console.log("cdetails", customerDetails);

    $.ajax({
      url: ajax_object.ajax_url,
      type: "POST",
      dataType: "html",
      beforeSend: function () {
        current.addClass("ml_loading");
      },
      data: {
        action: "confirm_payout",
        nonce: ajax_object.nonce,
        customerDetails,
      },
      success: function (response) {
        current.removeClass("ml_loading");

        $(".alarnd--payout-validation").slideUp().html("");

        if ($(response).closest(".alarnd--payout-modal").length !== 0) {
          // Open directly via API
          $.magnificPopup.open({
            items: {
              src: response,
              type: "inline",
            },
          });
        } else {
          $(".alarnd--payout-validation").html(response).slideDown();
        }
      },
      error: function (xhr, status, error) {
        console.log(error);
      },
    });

    return false;
  });

  function initilize_validate() {
    $("#customerDetails").validate({
      rules: {
        userEmail: {
          required: true,
          email: true,
        },
      },
      messages: {
        userEmail: {
          email: "Please enter a valid email address",
        },
      },
      submitHandler: function (form, event) {
        event.preventDefault();

        var getData = $(form).serializeArray(),
          messagWrap = $(form).find(".form-message"),
          button = $(form).find(".ml_save_customer_info");

        getData.push(
          {
            name: "action",
            value: "ml_customer_details",
          },
          {
            name: "nonce",
            value: ajax_object.nonce,
          }
        );

        button.addClass("ml_loading");
        messagWrap.html("").slideUp();

        // Form is valid, send data via AJAX
        // $.ajax({
        //   type: "POST",
        //   dataType: "json",
        //   url: ajax_object.ajax_url,
        //   data: getData,
        //   success: function (response) {
        //     button.removeClass("ml_loading");

        //     if (response.success === false) {
        //       messagWrap
        //         .html("<p>" + response.data.message + "</p>")
        //         .slideDown();
        //     }
        //   },
        //   error: function (xhr, status, error) {
        //     button.removeClass("ml_loading");
        //   },
        // });

        $.ajax({
          type: "POST",
          url: ajax_object.ajax_url,
          data: getData,
          success: function (response, status, xhr) {
            button.removeClass("ml_loading");

            var contentType = xhr.getResponseHeader("Content-Type");
            // console.log('contentType', contentType);

            // Check the content type to determine the dataType
            if (contentType && contentType.indexOf("application/json") !== -1) {
              if (response.success === false) {
                messagWrap
                  .html("<p>" + response.data.message + "</p>")
                  .slideDown();
              }
            } else {
              // Response is HTML or another format, set dataType to 'html'
              $("#customerDetails").slideUp();
              $("#alarnd__details_preview").html(response).slideDown();
              $(".alarnd--single-payout-submit").slideDown();
            }
          },
          error: function (xhr, status, error) {
            // Handle the error
          },
        });
      },
    });

    $("#customerDetails").on("submit", function (e) {
      e.preventDefault;
      return false;
    });

    $("#cardDetailsForm").validate({
      rules: {
        expirationDate: {
          required: true,
          dateformat: true,
        },
        cardholderEmail: {
          required: true,
          email: true,
        },
      },
      messages: {
        expirationDate: {
          dateformat: "Please enter a valid MM/YY date format",
        },
        cardholderEmail: {
          email: "Please enter a valid email address",
        },
      },
      submitHandler: function (form, event) {
        event.preventDefault();

        var getData = $(form).serializeArray(),
          messagWrap = $(form).find(".form-message"),
          user_id = $('#main').data('user_id'),
          button = $(form).find(".allaround_card_details_submit");

        getData.push(
          {
            name: "action",
            value: "ml_send_card",
          },
          {
            name: "user_id",
            value: user_id,
          },
          {
            name: "nonce",
            value: ajax_object.nonce,
          }
        );

        button.addClass("ml_loading");
        messagWrap.html("").slideUp();

        // Form is valid, send data via AJAX
        $.ajax({
          type: "POST",
          dataType: "json",
          url: ajax_object.ajax_url,
          data: getData,
          success: function (response) {
            button.removeClass("ml_loading");

            if (response.success === false) {
              messagWrap
                .html("<p>" + response.data.message + "</p>")
                .slideDown();
            }
          },
          error: function (xhr, status, error) {
            button.removeClass("ml_loading");
          },
        });
      },
    });

    $.validator.addMethod(
      "dateformat",
      function (value, element) {
        // Validate MM/YY format
        var currentYear = new Date().getFullYear() % 100; // Get the last two digits of the current year
        var parts = value.split("/");
        if (parts.length !== 2) return false; // Must have two parts (month and year)
        var month = parseInt(parts[0]);
        var year = parseInt(parts[1]);
        return month >= 1 && month <= 12 && year >= currentYear;
      },
      "Invalid expiration date"
    );
  }
  initilize_validate();

  var $customerDetails = $("#customerDetails");
  var $cardDetails = $("#cardDetailsForm");

  if ($customerDetails.length) {
    function toggleSubmitButtonCustomer() {
      // console.log($("#customerDetails").valid());
      if ($("#customerDetails").valid()) {
        $("button.alarnd--payout-trigger").prop("disabled", false);
        $("button.ml_save_customer_info").prop("disabled", false);
      } else {
        $("button.alarnd--payout-trigger").prop("disabled", true);
        $("button.ml_save_customer_info").prop("disabled", true);
      }
    }

    $(document).on(
      "input",
      "#customerDetails input",
      toggleSubmitButtonCustomer
    );
  }
  if ($cardDetails.length) {
    function toggleSubmitButtonCard() {
      // console.log($("#cardDetailsForm").valid());
      if ($("#cardDetailsForm").valid()) {
        $("button.allaround_card_details_submit").prop("disabled", false);
      } else {
        $("button.allaround_card_details_submit").prop("disabled", true);
      }
    }

    // $(document).on("input", "#cardDetailsForm input", toggleSubmitButtonCard);
  }

  // Use JavaScript to strip non-numeric characters
  $(document).on("input", "#cardNumber", function () {
    this.value = this.value.replace(/\D/g, "");
  });
  $(document).on("input", "#cardholderPhone, #userPhone", function () {
    this.value = this.value.replace(/[^0-9+]/g, "");
  });

  $(document).on("click", ".alrnd--create-order", function (e) {
    e.preventDefault();

    var $self = $(this),
      item = $self.closest(".popup_product_details"),
      customerDetails = $("#customerDetails");

    customerDetails.trigger("submit");
    if (!customerDetails.valid()) return false;

    var cdetails = customerDetails.serializeArray();
    var customerDetails = {};

    // Convert the serialized array to a key-value object
    $.each(cdetails, function (index, item) {
      customerDetails[item.name] = item.value;
    });

    if ($self.hasClass("loading")) return false;

    // create a order behind the scene first.
    $.ajax({
      type: "POST",
      dataType: "json",
      url: ajax_object.ajax_url,
      data: {
        nonce: ajax_object.nonce,
        action: "alarnd_create_order",
        customerDetails,
      },
      beforeSend: function () {
        $self.addClass("loading");
      },
      success: function (response) {
        $self.removeClass("loading");
        if (response && response.success && response.success === true) {
          item.find(".alarnd--popup-confirmation").slideUp();
          item.find(".alarnd--success-wrap").slideDown();
          // console.log( "Success" );
        } else {
          item.find(".alarnd--popup-confirmation").slideUp();
          item.find(".alarnd--failed-wrap").slideDown();
        }
      },
    }).fail(function (jqXHR, textStatus) {
      $self.removeClass("loading");
      console.log("Request failed: " + textStatus);
      console.log(jqXHR);
    });
  });

  $(window).on("load", function () {
    $('.woocommerce-cart-form :input[name="update_cart"]').prop(
      "disabled",
      true
    );
  });

  var $fragment_refresh = {
    url: wc_cart_fragments_params.wc_ajax_url
      .toString()
      .replace("%%endpoint%%", "get_refreshed_fragments"),
    type: "POST",
    data: {
      time: new Date().getTime(),
    },
    timeout: wc_cart_fragments_params.request_timeout,
    success: function (data) {
      if (data && data.fragments) {
        $.each(data.fragments, function (key, value) {
          $(key).replaceWith(value);
        });

        if ($supports_html5_storage) {
          sessionStorage.setItem(
            wc_cart_fragments_params.fragment_name,
            JSON.stringify(data.fragments)
          );
          set_cart_hash(data.cart_hash);

          if (data.cart_hash) {
            set_cart_creation_timestamp();
          }
        }

        $(document.body).trigger("wc_fragments_refreshed");
      }
    },
    error: function () {
      $(document.body).trigger("wc_fragments_ajax_error");
    },
  };

  /* Named callback for refreshing cart fragment */
  function refresh_cart_fragment() {
    $.ajax($fragment_refresh);
  }

  // Close quick view modal

  $(document).on("click", ".alarnd--user_address_edit", function () {
    var current = $(this);
    $("#alarnd__details_preview").slideUp();
    $("#customerDetails").slideDown(function () {
      $(this).find(".form-message").html("").slideUp();
    });
    // $(".alarnd--single-payout-submit").slideUp();

    return false;
  });

  $(document).on("click", ".ml_customer_info_edit_cancel", function () {
    var current = $(this);
    $("#customerDetails").slideUp(function () {
      $(this).find(".form-message").html("").slideUp();
    });
    $("#alarnd__details_preview").slideDown();
    // $(".alarnd--single-payout-submit").slideDown();

    return false;
  });

  $(document).on("change paste keyup", ".user_billing_info_edit", function () {
    $(".alarnd--user_address_edit").addClass("ready-to-save");
  });

  $(document).on("click", ".quick-view-close", function () {
    $("#product-quick-view").fadeOut().html("");
  });

  $(document).on("change", 'input[name="alarnd_payout"]', function () {
    var current = $(this);

    if ("woocommerce" === current.val()) {
    //   $(".alrnd--shipping_address_tokenized").hide();
      $(".alarnd--single-payout-submit").hide();
      $(".alarnd--card-details-wrap").show();
      $(".payment-info-display").show();
      $(".alrnd--shipping_address_tokenized").addClass("allrnd_keepSaved_userData");
    } else if ("tokenizer" === current.val()) {
      $(".alarnd--card-details-wrap").hide();
      $(".payment-info-display").hide();
    //   $(".alrnd--shipping_address_tokenized").show();
      $(".alarnd--single-payout-submit").show();
      $(".alrnd--shipping_address_tokenized").removeClass("allrnd_keepSaved_userData");
    }
    return false;
  });

  $(document).on("change", 'input[name="alarnd_payout"]', function () {
    var current = $(this);

    if ("tokenizer" === current.val()) {
      ml_show_tokenized_checkout();
    }
    return false;
  });

  //   $(document).on("click", ".alarnd__return_token_payout", function (e) {
  //     e.preventDefault();

  //     $(".alarnd--woocommerce-checkout-page")
  //       .css({
  //         visibility: "hidden",
  //         position: "absolute",
  //         opacity: "0",
  //       })
  //       .slideUp()
  //       .find(".allaround-order-details-container")
  //       .find(".alarnd__return_token_payout")
  //       .remove();

  //     $(".alrnd--shipping_address_tokenized").slideDown();
  //     $(".alarnd--single-payout-submit").slideDown();
  //     $(".alrnd--pay_details_tokenized")
  //       .find('input[name="alarnd_payout"][value="tokenizer"]')
  //       .prop("checked", true);

  //     return false;
  //   });

  function ml_show_tokenized_checkout() {
    $(".alarnd--woocommerce-checkout-page")
      .css({
        visibility: "hidden",
        position: "absolute",
        opacity: "0",
      })
      .fadeOut()
      .removeClass("allrnd_tokenized_wooCheckout_visible");

    $(".alrnd--shipping_address_tokenized").removeClass("allrnd_hidden");
    $(".alarnd--single-payout-submit").removeClass("allrnd_hidden");
    $(".alarnd--payout-main").removeClass("alrnd_woo_checkout_choice");
    $(".alrnd--pay_details_tokenized")
      .find('input[name="alarnd_payout"][value="tokenizer"]')
      .prop("checked", true);
  }

  function ml_show_woocommerce_checkout() {
    $(".alrnd--shipping_address_tokenized").addClass("allrnd_hidden");
    $(".alarnd--single-payout-submit").addClass("allrnd_hidden");
    $(".alarnd--payout-main").addClass("alrnd_woo_checkout_choice");
    $(".alarnd--woocommerce-checkout-page")
      .css({
        visibility: "visible",
        position: "relative",
        opacity: "1",
      })
      .fadeIn()
      .addClass("allrnd_tokenized_wooCheckout_visible");
  }

  var isLoading = false;

  $(document).on("submit", ".modal-cart", function (e) {
    e.preventDefault();

    if (isLoading) {
      return false;
    }

    isLoading = true;

    var $self = $(this),
      button = $self.find('button[name="add-to-cart"]'),
      productId = button.val(),
      user_id = $('#main').data('user_id'),
      getData = $self.serializeArray();

    getData.push(
      {
        name: "action",
        value: "ml_add_to_cart",
      },
      {
        name: "product_id",
        value: productId,
      },
      {
        name: "user_id",
        value: user_id,
      },
      {
        name: "nonce",
        value: ajax_object.nonce,
      }
    );

    button.addClass("ml_loading").prop("disabled", true);
    if ($self.find(".alanrd--product-added-message").length !== 0) {
      $self.find(".alanrd--product-added-message").slideUp();
    }

    $.ajax({
      type: "POST",
      dataType: "json",
      url: ajax_object.ajax_url,
      data: getData,
      beforeSend: function () {
        if (
          !$self.closest(".alarnd--info-modal").hasClass("is_already_in_cart")
        ) {
          $self.closest(".alarnd--info-modal").addClass("is_already_in_cart");
        }
      },
      success: function (data) {
        button.removeClass("ml_loading").prop("disabled", false);

        // Fetch and update the cart content using the [cart] shortcode
        refresh_cart_fragment();

        if (
          $self.find(".alarnd--select-qty-body").find("input.three-digit-input")
            .length !== 0
        ) {
          $self
            .find(".alarnd--select-qty-body")
            .find("input.three-digit-input")
            .val("");
        }

        if ($self.find(".alanrd--product-added-message").length !== 0) {
          $self.find(".alanrd--product-added-message").slideDown();

          setTimeout(function () {
            $self.find(".alanrd--product-added-message").slideUp();
          }, 3000);
        }

        $('#ministore--custom-checkout-section').removeClass('ml_pay_hidden');

        console.log(data);
      },
    }).then(function () {
      isLoading = false;
      button.removeClass("ml_loading").prop("disabled", false);
    });
  });

  $(document).on("click", ".alarnd--loadmore-trigger", function (e) {
    e.preventDefault();

    var current = $(this),
      page_num = current.data("page_num"),
      section = $(".allaround--products-section"),
      wrapper = $("#allaround_products_list"),
      user_id = wrapper.data("user_id");

    section.addClass("loading");
    current.addClass("ml_loading");

    console.log("page_num", page_num);
    console.log($(".alarnd--loadmore-trigger").data("page_num"));

    $.ajax({
      type: "POST",
      dataType: "html",
      url: ajax_object.ajax_url,
      data: {
        action: "ml_pagination",
        page_num: page_num,
        user_id: user_id,
        nonce: ajax_object.nonce,
      },
      success: function (response) {
        section.removeClass("loading");
        current.removeClass("ml_loading");

        if (response.length === 0) {
          current.slideUp();
        } else {
          wrapper.append(response);

          var $items = $(response);
          wrapper.isotope("appended", $items);
          wrapper.isotope("reloadItems");

          current.data("page_num", page_num + 1);
        }
      },
      complete: function () {
        section.removeClass("loading");
        current.removeClass("ml_loading");
        initi_prive_view_modal();
        isotope_initalize();
        setProductDetailsHeight();
      },
    });
  });

  $(document).on("submit", "form.variations_form", function (e) {
    e.preventDefault();

    var current = $(this),
      product_id = current.find('input[name="product_id"]').val(),
      quantity = current.find('input[name="quantity"]').val(),
      button = current.find(".single_add_to_cart_button"),
      user_id = $('#main').data('user_id'),
      variation_id = current.find('input[name="variation_id"]').val();

    var getvariation = {};
    current
      .find(".alarnd--single-var-info")
      .find("input:checked")
      .each(function () {
        const name = $(this).attr("name").split("attribute_")[1];
        getvariation[name] = $(this).val();
      });

    button.addClass("ml_loading");

    $.ajax({
      type: "POST",
      dataType: "json",
      url: ajax_object.ajax_url,
      data: {
        action: "add_variation_to_cart",
        product_id: product_id,
        variation_id: variation_id,
        quantity: quantity,
        user_id: user_id,
        variation: getvariation,
        nonce: ajax_object.nonce,
      },
      success: function (data) {
        button.removeClass("ml_loading");

        checkCartStatus();
        refresh_cart_fragment();

        // console.log(data);
      },
    });
  });
  
  $(document).on("submit", "form.cart", function (e) {
    e.preventDefault();

    var current = $(this),
      button = current.find(".single_add_to_cart_button"),
      product_id = button.val(),
      user_id = $('#main').data('user_id'),
      quantity = current.find('input[name="quantity"]').val();

    button.addClass("ml_loading");

    $.ajax({
      type: "POST",
      dataType: "json",
      url: ajax_object.ajax_url,
      data: {
        action: "add_simple_to_cart",
        product_id: product_id,
        quantity: quantity,
        user_id: user_id,
        nonce: ajax_object.nonce,
      },
      success: function (data) {
        button.removeClass("ml_loading");

        checkCartStatus();
        refresh_cart_fragment();

        // console.log(data);
      },
    });
  });

  // Check cart status on page load
  checkCartStatus();

  // Update cart status when an item is added or removed
  $(document).on('added_to_cart removed_from_cart', function() {
      checkCartStatus();
  });

  // Function to check cart status
  function checkCartStatus() {
      // Perform AJAX request to check the cart status
      $.ajax({
          type: 'POST',
          url: ajax_object.ajax_url,
          data: {
              action: 'check_cart_status',
              nonce: ajax_object.nonce,
          },
          success: function(response) {
              // Show or hide the element based on the response
              if (response && response.data.cart_has_items) {
                  // Cart has items, show the element
                  $('#ministore--custom-checkout-section').removeClass('ml_pay_hidden');
              } else {
                  // Cart is empty, hide the element
                  $('#ministore--custom-checkout-section').addClass('ml_pay_hidden');
              }
          },
      });
  }
  
  $(document).ajaxSend(function(event, xhr, settings) {
    // Check if the AJAX request is a cart update
    if (settings.url.indexOf('/cart') !== -1) {
        // It's a cart update AJAX request
        $('.alarnd--cart-wrapper-inner').addClass('loading');
        console.log("started update");

        // Append HTML code
        var cartLoaderHTML = '<section class="cart_loader_section"><aside class="cart_loader_aside"><div class="info__box"><div class="left_box"><p class="shinny info__text_one"></p><p class="shinny info__text_two"></p><p class="shinny info__text_three"></p></div><div class="right_box"><div class="shinny image"></div></div></div><div class="info__box"><div class="left_box"><p class="shinny info__text_one"></p><p class="shinny info__text_two"></p><p class="shinny info__text_three"></p></div><div class="right_box"><div class="shinny image"></div></div></div></aside><div class="shipping_side"><div class="left_box"><p class="shinny info__text_one"></p><p class="shinny info__text_two"></p></div><div class="left_box"><p class="shinny info__text_one"></p><p class="shinny info__text_two"></p></div><div class="left_box cartLoader__total"><p class="shinny info__text_one"></p><p class="shinny info__text_two"></p></div><div class="right_box cartLoader_button"><div class="shinny image"></div></div></div></section>';
        $('#cart_loader').append(cartLoaderHTML);
    }
  });

  $(document.body).on('updated_wc_div', function(){
      // Add your class to the element you want to target
      setTimeout(function () {
        $('.alarnd--cart-wrapper-inner').removeClass('loading');
        console.log("completed update");
      }, 1000);
  });

  // Listen for the WooCommerce AJAX complete event
  $(document).ajaxComplete(function (event, xhr, settings) {
    // Check if the AJAX request is for refreshing fragments
    if (
      settings.url ===
      wc_cart_fragments_params.wc_ajax_url
        .toString()
        .replace("%%endpoint%%", "get_refreshed_fragments")
    ) {
      initilize_validate();
      // Get the input value (replace this with your own logic)
      var hidden_field = $("#ml_username_hidden");
      if (hidden_field.length !== 0) {
        $("form.woocommerce-checkout").append(
          '<input type="hidden" name="user_profile_username" value="' +
            hidden_field.val() +
            '">'
        );
      }
    }
  });

  $(document).ajaxComplete(function (event, xhr, settings) {
    // console.log("ajaxComplete");
    // Check if the AJAX request is for refreshing fragments
    if (
      settings.url ===
      wc_cart_fragments_params.wc_ajax_url
        .toString()
        .replace("%%endpoint%%", "get_refreshed_fragments")
    ) {
      // console.log(settings.url);
      // Get the input value (replace this with your own logic)
      var hidden_field = $("#ml_username_hidden");
      if (hidden_field.length !== 0) {
        // console.log("added", hidden_field.val());
        $("form.woocommerce-checkout").append(
          '<input type="hidden" name="user_profile_username" value="' +
            hidden_field.val() +
            '">'
        );

        // Trigger a click event with a delay
        setTimeout(function () {
          $(".white-popup-block button.mfp-close").click();
          // Smooth scroll to #woocommerce_cart
          $("html, body").animate(
            {
              scrollTop: $("#woocommerce_cart").offset().top,
            },
            1000
          );
        }, 500);
      }
    }
  });

  $(window).on("load", function () {
    var hidden_field = $("#ml_username_hidden");
    if (
      $("form.woocommerce-checkout").length !== 0 &&
      hidden_field.length !== 0
    ) {
      $("form.woocommerce-checkout").append(
        '<input type="hidden" name="user_profile_username" value="' +
          hidden_field.val() +
          '">'
      );
    }
  });

  $(document.body).on(
    "click",
    ".product-remove a.remove[data-product_id]",
    function () {
      var product_id = $(this).data("product_id");

      // console.log("product_id", product_id);

      if (
        $(this)
          .closest("form.woocommerce-cart-form")
          .find('a.remove[data-product_id="' + product_id + '"]')
          .not(this).length === 0
      ) {
        // console.log("i'm innnnnnnn");
        $("#ml--product_id-" + product_id).removeClass("is_already_in_cart");
      }
    }
  );

  $(document).on("click", ".alarnd_view_pricing_cb", function () {
    // Check for the condition and add class accordingly
    $(".alarnd--pricing-wrapper-new").each(function () {
        var $pricingWrapper = $(this);
        var $pricingColumns = $pricingWrapper.find(".alarn--pricing-column");

        if ($pricingColumns.length === 2) {
            $pricingWrapper.closest(".alarnd--info-modal").addClass("mini-no-gallery");
        } else if ($pricingColumns.length === 1) {
            $pricingWrapper.closest(".alarnd--info-modal").addClass("mini-one-pricing-column");
            $pricingColumns.css({
                'flex': '0 0 100%',
            });
            $pricingWrapper.closest(".alarnd--info-modal").css({
                'max-width': '410px',
                'min-width': 'auto'
            });
        }
    });

  });


  function initi_prive_view_modal() {
    var isApplicable = null;

    $(".alarnd_view_pricing_cb").magnificPopup({
      type: "inline",
      midClick: true,
      removalDelay: 300,
      mainClass: "mfp-fade",
      callbacks: {
        open: function () {
          const currentInstance = this;

          $(".alarnd_trigger_details_modal").removeClass("ml_loading");

          var slickCarousel = this.content.find(".allaround--slick-carousel");

          if (slickCarousel.hasClass("slick-slider")) {
            slickCarousel.slick("unslick");
          }

          var customDots = this.content.find(".mlCustomDots a"); // Your custom dot links

          slickCarousel.slick({
            arrows: true,
            dots: false,
            infinite: true,
            speed: 500,
            slidesToShow: 1,
            nextArrow:
              '<button class="slick-next" aria-label="Next" type="button"></button>',
            prevArrow:
              '<button class="slick-prev" aria-label="Previous" type="button"></button>',
            customPaging: function (slider, i) {
              return '<button class="slick-dot" type="button"></button>';
            },
            adaptiveHeight: true,
          });
          slickCarousel.slick("refresh");

          // Click event handler for custom dots
          customDots.on("click", function (e) {
            e.preventDefault();
            var slideIndex = $(this).data("slide");
            slickCarousel.slick("slickGoTo", slideIndex); // Go to the selected slide
          });

          // Update custom dots when the slider changes
          slickCarousel.on(
            "beforeChange",
            function (event, slick, currentSlide, nextSlide) {
              customDots.removeClass("active");
              customDots.eq(nextSlide).addClass("active");
            }
          );

          slickCarousel.find(".gallery-item").on("click", function () {
            isApplicable = true;
            currentInstance.close();
          });
        },
        afterClose: function () {
          const product_id = this.st.el.data("product_id");
          if (
            isApplicable === true &&
            product_id !== undefined &&
            $("#alarnd__pricing_info-" + product_id).length !== 0
          ) {
            isApplicable = false;
            $("#alarnd__pricing_info-" + product_id)
              .find(".mlGallerySingle")
              .magnificPopup("open");
          }
        },
      },
    });

    $(".mlGallerySingle").magnificPopup({
      type: "image",
      gallery: {
        enabled: true,
      },
      titleSrc: function (item) {
        // Retrieve the title from the data-title attribute
        return item.el.attr("data-title");
      },
      callbacks: {
        afterClose: function () {
          const product_id = this.st.el
            .closest(".alarnd--info-modal")
            .data("product_id");
          if (
            product_id !== undefined &&
            $('.alarnd_view_pricing_cb[data-product_id="' + product_id + '"]')
              .length !== 0
          ) {
            $(
              '.alarnd_view_pricing_cb[data-product_id="' + product_id + '"]'
            ).trigger("click");
          }
        },
      },
    });
  }

  initi_prive_view_modal();

  $(document).on(
    "click",
    ".product-item-details h3.product-title, .product-thumbnail",
    function (e) {
      e.preventDefault();

      if (
        $(this).closest(".product-item").find(".view-details-button").length !==
        0
      ) {
        $(this)
          .closest(".product-item")
          .find(".view-details-button")
          .trigger("click");
      }

      return false;
    }
  );

  $(document).on("click", ".alarnd_view_pricing_cb_button", function (e) {
    e.preventDefault();

    const product_id = $(this).data("product_id");
    if (
      product_id !== undefined &&
      $('.alarnd_view_pricing_cb[data-product_id="' + product_id + '"]')
        .length !== 0
    ) {
      if ($("#alarnd__pricing_info-" + product_id).length !== 0) {
        var gallery = $("#alarnd__pricing_info-" + product_id).find(
          ".woocommerce-product-gallery"
        );

        $(
          '.alarnd_view_pricing_cb[data-product_id="' + product_id + '"]'
        ).trigger("click");
        $(".alarnd_trigger_details_modal").removeClass("ml_loading");

        if (gallery.length !== 0) {
          var slickCarousel = gallery.find(".allaround--slick-carousel"),
            customDots = gallery.find(".mlCustomDots a");

          if (slickCarousel.hasClass("slick-slider")) {
            slickCarousel.slick("unslick");
          }

          slickCarousel.slick({
            arrows: true,
            dots: false,
            infinite: true,
            speed: 500,
            slidesToShow: 1,
            nextArrow:
              '<button class="slick-next" aria-label="Next" type="button"></button>',
            prevArrow:
              '<button class="slick-prev" aria-label="Previous" type="button"></button>',
            customPaging: function (slider, i) {
              return '<button class="slick-dot" type="button"></button>';
            },
            adaptiveHeight: true,
          });

          setTimeout(function () {
            slickCarousel.slick("refresh");
          }, 1000);

          setTimeout(function () {
            slickCarousel.slick("refresh");
          }, 2000);

          // Click event handler for custom dots
          customDots.on("click", function (e) {
            e.preventDefault();
            var slideIndex = $(this).data("slide");
            slickCarousel.slick("slickGoTo", slideIndex); // Go to the selected slide
          });

          // Update custom dots when the slider changes
          slickCarousel.on(
            "beforeChange",
            function (event, slick, currentSlide, nextSlide) {
              customDots.removeClass("active");
              customDots.eq(nextSlide).addClass("active");
            }
          );

          slickCarousel.find(".gallery-item").on("click", function () {
            if (gallery.find(".mlGallerySingle").length !== 0) {
              if ($.magnificPopup.instance.isOpen) {
                // console.log("magnificPopup.instance.close");
                $.magnificPopup.instance.close();
                setTimeout(function () {
                  gallery.find(".mlGallerySingle").magnificPopup("open");
                }, 500);
              }
            }
          });
        }
      }
    }

    return false;
  });

  $(document).on("click", ".gallery-item", function () {
    // console.log("gallery item clicked - document on click");
  });

  $(document).on("click", ".alarnd_trigger_details_modal", function (e) {
    e.preventDefault();

    const product_id = $(this).data("product_id");

    if (
      product_id !== undefined &&
      $('.ml_trigger_details[data-product-id="' + product_id + '"]').length !==
        0
    ) {
      $(this).addClass("ml_loading");
      $('.ml_trigger_details[data-product-id="' + product_id + '"]').trigger(
        "click"
      );
    }

    return false;
  });

  $(document).on("click", ".ml_trigger_details", function () {
    var $self = $(this),
      productId = $self.data("product-id");

    if ($("#ml--product_id-" + productId).length !== 0) {
      $.magnificPopup.open({
        items: {
          src: "#ml--product_id-" + productId,
          type: "inline",
        },
        callbacks: {
          open: function () {
            var form_variation = this.content.find(".variations_form");
            form_variation.each(function () {
              $(this).wc_variation_form();
            });
            form_variation.trigger("check_variations");
            form_variation.trigger("reset_image");
            $(".alarnd_trigger_details_modal").removeClass("ml_loading");
          },
        },
      });
    } else {
      $.ajax({
        url: ajax_object.ajax_url,
        type: "POST",
        dataType: "html",
        beforeSend: function () {
          $self.addClass("ml_loading");
        },
        data: {
          action: "get_item_selector",
          product_id: productId,
          nonce: ajax_object.nonce,
        },
        success: function (response) {
          $self.removeClass("ml_loading");
          // Open directly via API
          $.magnificPopup.open({
            items: {
              src: response,
              type: "inline",
            },
            callbacks: {
              open: function () {
                var form_variation = this.content.find(".variations_form");
                form_variation.each(function () {
                  $(this).wc_variation_form();
                });
                form_variation.trigger("check_variations");
                form_variation.trigger("reset_image");

                // Apply color adjustment to elements with class .alarnd--opt-color span
                $(".alarnd--opt-color span").each(function () {
                    var rgb = $(this).css("backgroundColor");
                    var colors = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);

                    var r = colors[1];
                    var g = colors[2];
                    var b = colors[3];

                    var o = Math.round(
                        (parseInt(r) * 299 + parseInt(g) * 587 + parseInt(b) * 114) / 1000
                    );

                    if (o > 125) {
                        $(this).css("color", "black");
                    } else {
                        $(this).css("color", "white");
                    }
                });

                $(".alarnd_trigger_details_modal").removeClass("ml_loading");
              },
              close: function () {
                $("body").append(response);
                $("#ml--product_id-" + productId).addClass("mfp-hide");
              },
            },
          });
        },
      });
    }
  });

  $(".xoo-ml-otp-input-cont-main input:first").focus();
  $(window).on("load", function () {
    $(".xoo-ml-otp-input-cont-main input:first").focus();
  });

  $("body").on("keyup change", "input.alarnd--otp-input", function () {
    //Switch Input
    if (
      $(this).val().length === parseInt($(this).attr("maxlength")) &&
      $(this).next("input.alarnd--otp-input").length !== 0
    ) {
      $(this).next("input.alarnd--otp-input").focus();
    }

    //Backspace is pressed
    if (
      $(this).val().length === 0 &&
      event.keyCode == 8 &&
      $(this).prev("input.alarnd--otp-input").length !== 0
    ) {
      $(this).prev("input.alarnd--otp-input").focus().val("");
    }

    var otp = "";
    $("input.alarnd--otp-input").each(function () {
      otp += $(this).val();
    });

    $("input.xoo-ml-phone-input").val(otp).change();
  });

  $(document).on(
    "click",
    '.wc-proceed-to-checkout a[href^="#"]',
    function (event) {
      event.preventDefault();

      $("html, body").animate(
        {
          scrollTop: $($.attr(this, "href")).offset().top,
        },
        500
      );
    }
  );

});
