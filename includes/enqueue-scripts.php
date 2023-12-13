<?php

function enqueue_aum_script() {

    // Enqueue the Magnific Popup styles
    wp_enqueue_style('magnific-popup-style', AlRNDCM_URL . 'assets/css/magnific-popup.css');

    // Enqueue custom CSS for front-end
    wp_enqueue_style('css-frontend', AlRNDCM_URL . 'assets/css/css-frontend.css', array(), AlRNDCM_VERSION );

    
    // Enqueue WooCommerce scripts
    if (class_exists('WooCommerce')) {

        if( is_author() ) {
            wp_enqueue_script('wc-cart');
            wp_enqueue_script('wc-checkout');
            wp_enqueue_script('wc-cart-fragments');
            wp_enqueue_script( 'wc-add-to-cart-variation' );
            wp_enqueue_script( 'wc-single-product' );
            wp_enqueue_script( 'wc-single-product' );

            wp_enqueue_script('slick-js', AlRNDCM_URL . 'assets/js/slick.min.js', array('jquery'), null, true);
            wp_enqueue_style('slick-css', AlRNDCM_URL . 'assets/css/slick.css');
            if( defined('XOO_ML_VERSION') ) {
                wp_deregister_style('xoo-ml-style');
                wp_enqueue_style( 'xoo-ml-style', XOO_ML_URL.'/assets/css/xoo-ml-style.css', array(), XOO_ML_VERSION );
            }
            
            // wp_enqueue_style('slick-theme-css', AlRNDCM_URL . 'assets/css/slick-theme.css');
            wp_enqueue_script('isotope-script', AlRNDCM_URL . 'assets/js/isotope.pkgd.min.js', array('jquery'), '3.0.6', true);
            wp_enqueue_script('magnific-popup', AlRNDCM_URL . 'assets/js/jquery.magnific-popup.min.js', array('jquery'), '1.0', true);

            wp_enqueue_script('imask', AlRNDCM_URL . 'assets/js/imask.min.js', array('jquery'), XOO_ML_VERSION, true);
            wp_enqueue_script('mask', AlRNDCM_URL . 'assets/js/jquery.mask.min.js', array('jquery'), XOO_ML_VERSION, true);
        }

        // wp_deregister_script( 'wc-cart' );
        // wp_deregister_script( 'wc-cart-fragments' );

        // wp_register_script( 'wc-cart',  AlRNDCM_URL . 'assets/js/woocommerce/cart.js', array( 'jquery', 'woocommerce', 'wc-country-select', 'wc-address-i18n' ), WC_VERSION, true);
        // wp_register_script( 'wc-cart-fragments',  AlRNDCM_URL . 'assets/js/woocommerce/cart-fragments.js', array( 'jquery', 'js-cookie' ), WC_VERSION, true);

        // wp_enqueue_script( 'wc-cart' );
        // wp_enqueue_script( 'wc-cart-fragments' );
        
    }

    wp_enqueue_script('validate', AlRNDCM_URL . 'assets/js/jquery.validate.min.js', array('jquery'), XOO_ML_VERSION, true);


    // Dequeue the default WooCommerce script
    // wp_dequeue_script('wc-cart'); // Replace 'wc-script' with the correct script handle
    // wp_dequeue_script('wc-cart-fragments'); // Replace 'wc-script' with the correct script handle

    wp_enqueue_script('custom-script', AlRNDCM_URL . 'assets/js/custom-script.js', array(
        'jquery',
        'wc-cart',
        'wc-checkout',
        'wc-add-to-cart-variation',
        'wc-single-product',
        'wc-cart-fragments',
        'selectWoo',
        'slick-js',
    ), AlRNDCM_VERSION, true);

    wp_localize_script( 'custom-script', 'ajax_object',
        array( 
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'admin_email' => get_bloginfo( 'admin_email' ),
            'nonce' => wp_create_nonce( "aum_ajax_nonce" )
        ) 
    );

    wp_enqueue_script('leads', AlRNDCM_URL . 'assets/js/leads.js', array(
        'jquery',
        'validate'
    ), AlRNDCM_VERSION, true);

    wp_localize_script( 'leads', 'leads_object',
        array( 
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( "leads_ajax_nonce" )
        ) 
    );
}
add_action('wp_enqueue_scripts', 'enqueue_aum_script', 99);


function enqueue_frontend_css() {
    
}
add_action('wp_enqueue_scripts', 'enqueue_frontend_css');