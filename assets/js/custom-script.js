jQuery(document).ready(function($) {

    /**
    * isotope Filtering.
    */
    var $grid = $('.product-list-container').isotope({
        itemSelector: '.product-item',
        layoutMode: 'fitRows'
    });

    // overwrite woocommerce scroll to notices
    $.scroll_to_notices = function( scrollElement ) {
        var offset = 300;
        if ( scrollElement.length ) {
        $( 'html, body' ).animate( {
            scrollTop: ( scrollElement.offset().top-offset )
            }, 1000 );
        }
    };

    /* Storage Handling */
	var $supports_html5_storage = true,
		cart_hash_key           = wc_cart_fragments_params.cart_hash_key;

    try {
        $supports_html5_storage = ( 'sessionStorage' in window && window.sessionStorage !== null );
    } catch( err ) {
        $supports_html5_storage = false;
    }

    /* Cart session creation time to base expiration on */
	function set_cart_creation_timestamp() {
		if ( $supports_html5_storage ) {
			sessionStorage.setItem( 'wc_cart_created', ( new Date() ).getTime() );
		}
	}

	/** Set the cart hash in both session and local storage */
	function set_cart_hash( cart_hash ) {
		if ( $supports_html5_storage ) {
			localStorage.setItem( cart_hash_key, cart_hash );
			sessionStorage.setItem( cart_hash_key, cart_hash );
		}
	}


    $( document ).on('click', '.alarnd--payout-trigger', function(e){
        e.preventDefault();

        var current = $(this);

        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            dataType: 'html',
            beforeSend: function(){
                current.addClass('ml_loading');
            },
            data: {
                action: 'confirm_payout',
                nonce: ajax_object.nonce
            },
            success: function(response) {
                current.removeClass('ml_loading');
                // Open directly via API
                $.magnificPopup.open({
                    items: {
                        src: response,
                        type: 'inline'
                    }
                });
            }
        });

        return false
    });

    $(document).on("click", ".alrnd--create-order", function (e) {
        e.preventDefault();
    
        var $self = $(this),
            item = $self.closest('.popup_product_details'),
            address = '';

        if( $('.alarnd--user_address_edit').hasClass('alarnd--address-editing') && $('.alarnd--user_address_edit').hasClass('ready-to-save') ) {
            address = $('.user_billing_info_edit').val();
        }

        if( $self.hasClass('loading') )
            return false;
        
        // create a order behind the scene first.
        $.ajax({
            type: "POST",
            dataType: "json",
            url: ajax_object.ajax_url,
            data: {
                nonce: ajax_object.nonce,
                address: address,
                action: "alarnd_create_order",
            },
            beforeSend: function () {
                $self.addClass("loading");
            },
            success: function (response) {
                $self.removeClass("loading");
                if( response && response.success && response.success ===  true ) {
                    item.find('.alarnd--popup-confirmation').slideUp();
                    item.find('.alarnd--success-wrap').slideDown();
                    // console.log( "Success" );
                } else {
                    item.find('.alarnd--popup-confirmation').slideUp();
                    item.find('.alarnd--failed-wrap').slideDown();
                }
            },
        }).fail(function(jqXHR, textStatus) {
            $self.removeClass("loading");
            console.log( "Request failed: " + textStatus );
            console.log( jqXHR );
        });
    });

    $(window).on('load', function(){
        $( '.woocommerce-cart-form :input[name="update_cart"]' ).prop( 'disabled', true );
    });

    var $fragment_refresh = {
		url: wc_cart_fragments_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'get_refreshed_fragments' ),
		type: 'POST',
		data: {
			time: new Date().getTime()
		},
		timeout: wc_cart_fragments_params.request_timeout,
		success: function( data ) {
			if ( data && data.fragments ) {

				$.each( data.fragments, function( key, value ) {
					$( key ).replaceWith( value );
				});

				if ( $supports_html5_storage ) {
					sessionStorage.setItem( wc_cart_fragments_params.fragment_name, JSON.stringify( data.fragments ) );
					set_cart_hash( data.cart_hash );

					if ( data.cart_hash ) {
						set_cart_creation_timestamp();
					}
				}

				$( document.body ).trigger( 'wc_fragments_refreshed' );
			}
		},
		error: function() {
			$( document.body ).trigger( 'wc_fragments_ajax_error' );
		}
	};

    /* Named callback for refreshing cart fragment */
	function refresh_cart_fragment() {
		$.ajax( $fragment_refresh );
	}

    $('.product-filter').on('click', '.filter-button', function() {
        var filterValue = $(this).attr('data-filter');
        $grid.isotope({ filter: filterValue });
    });

    // Close quick view modal
    $(document).on('click', '.alarnd--user_address_edit', function() {
        var current = $(this);
        if( current.hasClass('alarnd--address-editing') ) {

            $('.alarnd--user-address-wrap').slideDown();
            $('.user_billing_info_edit').slideUp();
            current.removeClass('alarnd--address-editing').text("שינוי");

        } else {
            $('.alarnd--user-address-wrap').slideUp();
            $('.user_billing_info_edit').slideDown();
            current.addClass('alarnd--address-editing').text("לַחֲזוֹר");
        }
    });

    $(document).on('change paste keyup', '.user_billing_info_edit', function(){
        $('.alarnd--user_address_edit').addClass('ready-to-save');
    })
    
    $(document).on('click', '.quick-view-close', function() {
        $('#product-quick-view').fadeOut().html('');
    });
    
    $(document).on('change', 'input[name="alarnd_payout"]', function() {
        var current = $(this);

        if( "woocommerce" === current.val() ) {
            ml_show_woocommerce_checkout();
        }
        return false;
    });
    
    $(document).on('click', '.alarnd__return_token_payout', function(e){
        e.preventDefault();

        $('.alarnd--woocommerce-checkout-page').css({
            "visibility": "hidden",
            "position": "absolute",
            "opacity": "0",
        }).slideUp().find('.allaround-order-details-container').find('.alarnd__return_token_payout').remove();

        $('.alarnd--payout-main').slideDown().find('input[name="alarnd_payout"][value="tokenizer"]').prop("checked", true);

        return false;
    })

    function ml_show_woocommerce_checkout() {
        $('.alarnd--payout-main').slideUp();
        $('.alarnd--woocommerce-checkout-page').css({
            "visibility": "visible",
            "position": "inherit",
            "opacity": "1",
        }).slideDown().find('.allaround-order-details-container').prepend('<span class="alarnd__return_token_payout">לַחֲזוֹר</span>')
    }

    var isLoading = false;

    $(document).on('submit', '.modal-cart', function(e) {
        e.preventDefault();

        if ( isLoading ) {
            return false;
        }

        isLoading = true;

        var $self = $(this),
            button = $self.find('button[name="add-to-cart"]'),
            productId = button.val(),
            getData = $self.serializeArray();
    
        getData.push({
            name: 'action',
            value: 'ml_add_to_cart'
        },
        {
            name: 'product_id',
            value: productId
        },
        {
            name: 'nonce',
            value: ajax_object.nonce
        });

        button.addClass('ml_loading').prop("disabled", true);
        if( $self.find('.alanrd--product-added-message').length !== 0 ) {
            $self.find('.alanrd--product-added-message').slideUp();
        }

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: ajax_object.ajax_url,
            data: getData,
            beforeSend: function(){
                if( ! $self.closest('.alarnd--info-modal').hasClass('is_already_in_cart') ) {
                    $self.closest('.alarnd--info-modal').addClass('is_already_in_cart');
                }
            },
            success: function(data) {
                button.removeClass('ml_loading').prop("disabled", false);

                // Fetch and update the cart content using the [cart] shortcode
                refresh_cart_fragment();

                if( $self.find('.alarnd--select-qty-body').find('input.three-digit-input').length !== 0 ) {
                    $self.find('.alarnd--select-qty-body').find('input.three-digit-input').val('');
                }

                if( $self.find('.alanrd--product-added-message').length !== 0 ) {
                    $self.find('.alanrd--product-added-message').slideDown();

                    setTimeout(function(){
                        $self.find('.alanrd--product-added-message').slideUp();
                    }, 3000);
                }
                
                console.log( data );
            }
        }).then(function () {
            isLoading = false;
            button.removeClass('ml_loading').prop("disabled", false);
        });
    
    });

    $(document).on('submit', 'form.variations_form', function(e){
        e.preventDefault();

        var current = $(this),
            product_id = current.find('input[name="product_id"]').val(),
            quantity = current.find('input[name="quantity"]').val(),
            button = current.find('.single_add_to_cart_button'),
            variation_id = current.find('input[name="variation_id"]').val();

            var getvariation = {};
            current.find('.alarnd--single-var-info').find('input:checked').each(function(){
                const name = $(this).attr('name').split('attribute_')[1];
                getvariation[name] = $(this).val();
            });

            button.addClass('ml_loading');

            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: ajax_object.ajax_url,
                data: {
                    "action" : "add_variation_to_cart",
                    "product_id" : product_id,
                    "variation_id" : variation_id,
                    "quantity" : quantity,
                    "variation" : getvariation,
                    nonce: ajax_object.nonce
                },
                success: function(data) {
                    button.removeClass('ml_loading'); 

                    refresh_cart_fragment(); 
                    
                    console.log( data );
                }
            });
    });

    // Listen for the WooCommerce AJAX complete event
    $(document).ajaxComplete(function (event, xhr, settings) {
        // Check if the AJAX request is for refreshing fragments
        if (settings.url === wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments')) {
            // Get the input value (replace this with your own logic)
            var hidden_field = $('#ml_username_hidden');
            if( hidden_field.length !== 0  ) {
                $("form.woocommerce-checkout").append('<input type="hidden" name="user_profile_username" value="' + hidden_field.val() + '">');
            }
        }
    });

    $(document).ajaxComplete(function (event, xhr, settings) {
        console.log("ajaxComplete");
        // Check if the AJAX request is for refreshing fragments
        if (settings.url === wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments')) {
            console.log(settings.url);
            // Get the input value (replace this with your own logic)
            var hidden_field = $('#ml_username_hidden');
            if( hidden_field.length !== 0  ) {
                console.log("added", hidden_field.val());
                $("form.woocommerce-checkout").append('<input type="hidden" name="user_profile_username" value="' + hidden_field.val() + '">');
            }
        }
    });

    $(window).on('load', function(){
        var hidden_field = $('#ml_username_hidden');
        if( $("form.woocommerce-checkout").length !== 0 && hidden_field.length !== 0  ) {
            $("form.woocommerce-checkout").append('<input type="hidden" name="user_profile_username" value="' + hidden_field.val() + '">');
        }
    })

    $(document.body).on('click', '.product-remove a.remove[data-product_id]', function() {
        var product_id = $(this).data('product_id');

        console.log("product_id", product_id);

        if( $(this).closest('form.woocommerce-cart-form').find('a.remove[data-product_id="'+product_id+'"]').not(this).length === 0 ) {
            console.log("i'm innnnnnnn");
            $('#ml--product_id-'+product_id).removeClass('is_already_in_cart');
        }

    });

    var isApplicable = null;

    $('.alarnd_view_pricing_cb').magnificPopup({
        type: 'inline',
        midClick: true,
        removalDelay: 300,
        mainClass: 'mfp-fade',
        callbacks: {
            open: function() {

                const currentInstance = this;

                var slickCarousel = $('.allaround--slick-carousel');
                var customDots = $('.mlCustomDots a'); // Your custom dot links

                if ( ! slickCarousel.hasClass('slick-slider') ) {
                    slickCarousel.slick({
                        arrows: true,
                        dots: false,
                        infinite: true,
                        speed: 500,
                        slidesToShow: 1,
                        nextArrow: '<button class="slick-next" aria-label="Next" type="button"></button>',
                        prevArrow: '<button class="slick-prev" aria-label="Previous" type="button"></button>',
                        customPaging: function(slider, i) {
                            return '<button class="slick-dot" type="button"></button>';
                        },
                        adaptiveHeight: true
                    });
                }
                slickCarousel.slick('refresh');

                // Click event handler for custom dots
                customDots.on('click', function(e) {
                    e.preventDefault();
                    var slideIndex = $(this).data('slide');
                    slickCarousel.slick('slickGoTo', slideIndex); // Go to the selected slide
                });

                // Update custom dots when the slider changes
                slickCarousel.on('beforeChange', function(event, slick, currentSlide, nextSlide) {
                    customDots.removeClass('active');
                    customDots.eq(nextSlide).addClass('active');
                });

                slickCarousel.find('.gallery-item').on('click', function () {
                    isApplicable = true;
                    currentInstance.close();
                });
            },
            afterClose: function() {
                const product_id = this.st.el.data('product_id');
                if( isApplicable === true && product_id !== undefined && $( '#alarnd__pricing_info-'+product_id ).length !== 0 ) {
                    isApplicable = false;
                    $( '#alarnd__pricing_info-'+product_id ).find('.mlGallerySingle').magnificPopup('open');
                }
            }
        }
    });

    $('.mlGallerySingle').magnificPopup({
        type: 'image',
        gallery:{
          enabled:true
        },
        titleSrc: function (item) {
            // Retrieve the title from the data-title attribute
            return item.el.attr('data-title');
        },
        callbacks: {
          afterClose: function () {
            const product_id = this.st.el.closest('.alarnd--info-modal').data('product_id');
            if( product_id !== undefined && $( '.alarnd_view_pricing_cb[data-product_id="'+product_id+'"]' ).length !== 0 ) {
                $( '.alarnd_view_pricing_cb[data-product_id="'+product_id+'"]' ).trigger('click');
            }
          }
        }
    });


    $(document).on('click', '.product-item-details h2.product-title', function(e){
        e.preventDefault();
        
        if( $(this).closest('.product-item').find('.ml_trigger_details').length !== 0 ) {
            $(this).closest('.product-item').find('.ml_trigger_details').trigger('click');
        }
    
        return false;
    });
    
    $(document).on('click', '.alarnd_view_pricing_cb_button', function(e){
        e.preventDefault();
        
        const product_id = $(this).data('product_id');
        if( product_id !== undefined && $( '.alarnd_view_pricing_cb[data-product_id="'+product_id+'"]' ).length !== 0 ) {
            $( '.alarnd_view_pricing_cb[data-product_id="'+product_id+'"]' ).trigger('click');
        }
    
        return false;
    });
    
    $(document).on('click', '.alarnd_trigger_details_modal', function(e){
        e.preventDefault();
        
        const product_id = $(this).data('product_id');

        if( product_id !== undefined && $( '.ml_trigger_details[data-product-id="'+product_id+'"]' ).length !== 0 ) {
            $(this).addClass('ml_loading');
            $( '.ml_trigger_details[data-product-id="'+product_id+'"]' ).trigger('click');
        }
    
        return false;
    });


    $(document).on('click', '.ml_trigger_details', function() {

        var $self = $(this),
            productId = $self.data('product-id');

        if( $('#ml--product_id-'+productId).length !== 0 ) {
            $.magnificPopup.open({
                items: {
                    src: '#ml--product_id-'+productId,
                    type: 'inline'
                },
                callbacks: {
                    open: function() {
                        var form_variation = this.content.find('.variations_form');
                        form_variation.each( function() {
                            $( this ).wc_variation_form();
                        });
                        form_variation.trigger( 'check_variations' );
                        form_variation.trigger( 'reset_image' );
                        $('.alarnd_trigger_details_modal').removeClass('ml_loading');
                    }
                }
            });
        } else {
            $.ajax({
                url: ajax_object.ajax_url,
                type: 'POST',
                dataType: 'html',
                beforeSend: function(){
                    $self.addClass('ml_loading');
                },
                data: {
                    action: 'get_item_selector',
                    product_id: productId,
                    nonce: ajax_object.nonce
                },
                success: function(response) {
                    $self.removeClass('ml_loading');
                    // Open directly via API
                    $.magnificPopup.open({
                        items: {
                            src: response,
                            type: 'inline'
                        },
                        callbacks: {
                            open: function() {
                                var form_variation = this.content.find('.variations_form');
                                form_variation.each( function() {
                                    $( this ).wc_variation_form();
                                });
                                form_variation.trigger( 'check_variations' );
                                form_variation.trigger( 'reset_image' );
                                $('.alarnd_trigger_details_modal').removeClass('ml_loading');
                            },
                            close: function() {
                                $('body').append(response);
                                $('#ml--product_id-'+productId).addClass('mfp-hide');
                            }
                        }
                    });
                }
            });
        }
    });

    
});
