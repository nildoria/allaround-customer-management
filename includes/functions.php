<?php
/**
 * Redirect user after successful login.
 *
 * @param string $redirect_to URL to redirect to.
 * @param string $request URL the user is coming from.
 * @param object $user Logged user's data.
 * @return string
 */
function ml_login_redirect( $redirect_to, $request, $user ) {
	//is there a user to check?
	if ( isset( $user->roles ) && is_array( $user->roles ) ) {
		//check for admins
		if ( in_array( 'administrator', $user->roles ) ) {
			// redirect them to the default place
			return admin_url();
		} else {
			return home_url('/' . $user->user_login);
		}
	} else {
		return $redirect_to;
	}
}

add_filter( 'login_redirect', 'ml_login_redirect', 10, 3 );


// Redirect customers to the homepage after login
function custom_login_redirect($redirect, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        // Check for customer
        if (in_array('customer', $user->roles)) {
            // Redirect other users to home_url/username
            return home_url('/' . $user->user_login);
        }
    } else {
        return $redirect;
    }
}
add_filter('woocommerce_login_redirect', 'custom_login_redirect', 10, 2);


// function custom_woocommerce_my_account_redirect($url, $endpoint, $user) {
//     // Check if the user is an administrator
//     if (isset($user->roles) && is_array($user->roles) && in_array('administrator', $user->roles)) {
//         return admin_url();
//     } else {
//         // Redirect other users to home_url/username
//         return home_url('/' . $user->user_login);
//     }
// }
// add_filter('woocommerce_get_endpoint_url', 'custom_woocommerce_my_account_redirect', 10, 3);

/**
 * Overwrite otp login redirect url
 *
 * @param string $redirect
 * @return string
 */
if( function_exists( 'ml_xoo_ml_login_redirect' ) ) {
    function ml_xoo_ml_login_redirect( $redirect ) {
        $otp_data = Xoo_Ml_Otp_Handler::get_otp_data();
        $user = xoo_ml_get_user_by_phone( $otp_data['phone_no'], $otp_data['phone_code'] );
        if( $user ){
            return home_url('/' . $user->user_login);
        }
    
        return $redirect;
    }
    add_filter( 'xoo_ml_login_with_otp_redirect', 'ml_xoo_ml_login_redirect' );
}


function ml_logout_shortcode() {
    return '<a href="' . wp_logout_url(home_url()) . '">' . esc_html__( 'Logout', 'hello-elementor' ) . '</a>';
}
add_shortcode('ml_logout', 'ml_logout_shortcode');

class ML_Custom_Menu_Walker extends Walker_Nav_Menu {
    function start_el(&$output, $item, $depth = 0, $args = NULL, $id = 0) {
        $get_current_author = get_query_var('author_name');
        $get_current_puser = get_user_by('login', $get_current_author); 
        $current_user_id = isset( $get_current_puser->ID ) ? $get_current_puser->ID : '';
        $token_available = alarnd_get_token($current_user_id);
        $phone = ml_get_user_phone($current_user_id, 'number');
        if ($item->title == 'Logout') {
            $logout_url = home_url('/' . $get_current_author);
            $title = esc_html__("Login", "hello-elementor");
            if( is_user_logged_in() ) {
                $logout_url =  esc_url(add_query_arg('custom_logout', 'logout', home_url('/custom-logout/')));
                $title = $item->title;
            }

            if (! empty($token_available) && ! empty($phone)) {
                $output .= '<li class="login-logout-menu"><a href="' . esc_url($logout_url) . '">' . esc_html($title) . '</a></li>';
            }
        } else {
            parent::start_el($output, $item, $depth, $args, $id);
        }
    }
}

function disable_wp_login() {
    // Check if the request is for the login page and not for the WooCommerce customer logout
    if (
        strpos($_SERVER['REQUEST_URI'], '/wp-login.php') !== false &&
        !isset($_GET['customer-logout']) // Exclude WooCommerce customer logout
    ) {
        // Check if the user is an administrator
        if (current_user_can('administrator')) {
            // If the user is an administrator, allow access to wp-login.php for logging out
            return;
        } else {
            // For all other users, redirect login requests to the homepage
            wp_redirect(home_url('my-account'));
            exit;
        }
    }
}

// Hook the function to the login_init action
add_action('login_init', 'disable_wp_login');


function custom_logout_url($logout_url) {
    // Append a custom query parameter to the logout URL
    return add_query_arg('custom_logout', 'true', $logout_url);
}
add_filter('logout_url', 'custom_logout_url', 10, 2);



function get_positions_by_id( $post_id ) {
    $prefix = 'ml_logos_positions';

    $all_postmeta = get_post_meta($post_id);

    $filter_arr = [];
    if( ! empty( $all_postmeta ) ) :
    foreach ($all_postmeta as $meta_key => $meta_values) {
        if (strpos($meta_key, $prefix) === 0) {
            if (strpos($meta_key, $prefix.'_') === 0) {
                $userId = substr($meta_key, strlen('ml_logos_positions_'));
                $userId = substr($meta_key, strlen($prefix.'_')); // Extract the user_id
                if ($userId) {
                    $filter_arr['users'][$userId] = maybe_unserialize($meta_values[0]);
                }
            } else {
                $filter_arr[$meta_key] = maybe_unserialize($meta_values[0]);
            }
            // delete_post_meta($post_id, $meta_key);
        }
    }
    endif;

    return $filter_arr;
}

function delete_positions_by_id($post_id) {
    // Get all post meta for the specified post ID
    $all_meta = get_post_meta($post_id);

    if( empty( $all_meta ) )
        return false;

    // Loop through all post meta
    foreach ($all_meta as $meta_key => $meta_values) {
        // Check if the meta key starts with "ml_logos_positions"
        if (strpos($meta_key, 'ml_logos_positions') === 0) {
            // Delete the post meta with the matching key
            delete_post_meta($post_id, $meta_key);
        }
    }
    return true;
}




function ml_custom_menu_args($args) {
    $args['walker'] = new ML_Custom_Menu_Walker();
    return $args;
}
add_filter('wp_nav_menu_args', 'ml_custom_menu_args');


function ml_custom_logout_redirect( $user_id ) {
    $current_user = get_user_by('id', $user_id); 
    if ($current_user && $current_user->ID && in_array('customer', $current_user->roles) ) {
        wp_redirect( home_url($current_user->user_login) );
    } else {
        wp_redirect(home_url('my-account'));
    }
    exit();
}

add_action('wp_logout', 'ml_custom_logout_redirect');

if (!function_exists('ml_get_price_range')) {
    function ml_get_price_range($qty, $amount, $prev) {
        
        if( 0 == $amount ) {
            $range = "1-$qty";
        }
        
        $i = is_bool($prev) ? '1' : (int) $prev['quantity']+1;
        $range = $i == $qty ? "$qty+": "$i-$qty";

        return $range;
    }
}


function ml_redirect_to_home() {
    // Check if the user is not an administrator
    if ( is_user_logged_in() && ( is_home() || is_front_page() ) ) {
        $current_user = wp_get_current_user();
        if( in_array('customer', $current_user->roles) ) {
            // Redirect to the home page
            wp_redirect( home_url($current_user->user_login) );
            exit;
        }
    } 
    
    // if( ! is_user_logged_in() && ( is_home() || is_front_page() ) ) {
    //     wp_redirect( home_url('my-account') );
    //     exit;
    // }

    // if( is_author() && ! is_user_logged_in() ) {
    //     $current_author = get_query_var('author_name');
    //     $current_user = get_user_by('login', $current_author); 
    //     if( $current_user && isset( $current_user->ID ) ) {
    //         $current_user_id = $current_user->ID;
    //         $token = get_field('token', "user_{$current_user_id}");
    //         if( ! empty( $token ) ) {
    //             wp_redirect( home_url('my-account') );
    //             exit;
    //         }
    //     }    
    // }
}

add_action('template_redirect', 'ml_redirect_to_home');



// Ensure cart contents update when products are added to the cart via AJAX (place the following in functions.php)
add_filter( 'woocommerce_add_to_cart_fragments', 'woocommerce_header_add_to_cart_fragment' );

function woocommerce_header_add_to_cart_fragment( $fragments ) {
	ob_start();

    echo '<div class="alarnd--cart-wrapper-inner alarnd--full-width">';
        echo '<h2>העגלה שלך</h2>';
        echo '<div id="cart_loader">';
         echo do_shortcode('[woocommerce_cart]');
        echo '</div>';
    echo '</div>';

	$fragments['div.alarnd--cart-wrapper-inner'] = ob_get_clean();
	
	return $fragments;
}

add_filter( 'woocommerce_add_to_cart_fragments', 'update_order_total' );

function update_order_total( $fragments ) {
	ob_start();

    WC()->cart->calculate_totals();

    // Output the updated order total HTML
    ?>
    <div class="your-order-total-container"><?php esc_html_e("Total Amount", "hello-elementor" ); ?>: <?php printf(WC()->cart->get_total()); ?></div>
    <?php

	$fragments['div.your-order-total-container'] = ob_get_clean();
	
	return $fragments;
}

add_action('init', 'set_author_name_global_variable_anohter');

function set_author_name_global_variable_anohter() {
    
    if (is_author()) {
        // Get the author object
        $author = get_queried_object();

        // Set the author name as a global variable
        $GLOBALS['ml_author_name_var'] = $author->ID;
    }
}

add_filter('woocommerce_add_to_cart_fragments', 'remove_element_from_cart_fragments', 10, 1);

function remove_element_from_cart_fragments($fragments) {
    // Remove the element with the specified key from fragments
    unset($fragments['div.alarnd-checkout-wrap-inner']);
    unset($fragments['div.alarnd--payout-main']);

    return $fragments;
}


/**
 * Dynamic value for select products
 *
 * @param array $field
 * @return array
 */
function acf_select_products_choices_cb( $field ) {

    // reset choices
    $field['choices'] = array();

    $products = wc_get_products(array('status' => 'publish', 'limit' => 1000));

    $args = array(
        'numberposts' => 1000,
        'post_type'   => 'product',
        'post_status' => 'publish'
    );
      
    $products = get_posts( $args );
    
    // Loop through the array and add to field 'choices'
    if( is_array($products) && ! empty($products) ) {
        foreach( $products as $product ) {
            $field['choices'][ $product->ID ] = $product->post_title;
        }
    }

    // return the field
    return $field;

}
add_filter( 'acf/load_field/name=selected_products', 'acf_select_products_choices_cb' );
add_filter( 'acf/load_field/name=disable_product', 'acf_select_products_choices_cb' );
add_filter( 'acf/load_field/name=default_products', 'acf_select_products_choices_cb' );
add_filter( 'acf/load_field/name=custom_logo_products', 'acf_select_products_choices_cb' );

function acf_repeater_select_products_choices_cb( $field ) {

    // Loop through repeater rows
    if( isset($field['sub_fields']) && is_array($field['sub_fields']) ) {
        foreach( $field['sub_fields'] as &$sub_field ) {
            // Check if the subfield is the select field inside the repeater
            if( $sub_field['name'] === 'select_products' ) {
                // Reset choices for the select field inside the repeater
                $sub_field['choices'] = array();

                $args = array(
                    'numberposts' => 1000,
                    'post_type'   => 'product',
                    'post_status' => 'publish'
                );

                $products = get_posts( $args );

                // Loop through the array and add to field 'choices'
                if( is_array($products) && ! empty($products) ) {
                    foreach( $products as $product ) {
                        $sub_field['choices'][ $product->ID ] = $product->post_title;
                    }
                }
            }
        }
    }

    // Return the modified repeater field
    return $field;
}

// Filter to populate choices for the select field inside the repeater
add_filter( 'acf/load_field/name=logo_collections', 'acf_repeater_select_products_choices_cb' );








add_action( 'alarnd__modal_cart', 'woocommerce_template_single_add_to_cart' );






add_filter( 'woocommerce_add_to_cart_form_action', 'ml_avoid_redirect_to_single_page', 10, 1 );


function alarnd_is_quick_view() {
    return ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['action'] ) && 'get_item_selector' === $_REQUEST['action'] );
}
function ml_avoid_redirect_to_single_page( $value ) {
    if ( alarnd_is_quick_view() ) {
        return '';
    }
    return $value;
}

remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );



function alarnd_get_logo( $user_id, $type = '' ) {
    $profile_picture_id = get_field('profile_picture_id', "user_{$user_id}");
    $profile_picture_id_second = get_field('profile_picture_id_second', "user_{$user_id}");

    $profile_picture_url = '';
    if( ! empty( $profile_picture_id ) ) {
        $profile_picture_url = wp_get_attachment_image_url($profile_picture_id, 'full');
    }

    if( ! empty( $profile_picture_id_second ) && $type === 'second' ) {
        $profile_picture_url = $profile_picture_id_second;
    }

    return $profile_picture_url;

}


function alarnd_get_token( $user_id ) {
    $user_token = get_field('token', "user_{$user_id}");

    return $user_token;

}



/**
 * One click upsell payment uri.
 *
 * @return string
 */
function allaround_thankyou_page_buyout_api_uri() {
    return 'https://hook.eu1.make.com/6eus7os4m1yj4wa81sqhwzh2vky0rtmx';
}
add_filter('allaround_order_api_url', 'allaround_thankyou_page_buyout_api_uri' );


add_filter('author_rewrite_rules', 'no_author_base_rewrite_rules');
function no_author_base_rewrite_rules($author_rewrite) { 
    global $wpdb;
    $author_rewrite = array();

    $sql = "
        SELECT u.user_login AS nicename
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
        WHERE um.meta_key = '{$wpdb->prefix}capabilities'
        AND um.meta_value LIKE '%customer%'
    ";
    $authors = $wpdb->get_results($sql);
    
    foreach($authors as $author) {
        $author_rewrite["({$author->nicename})/page/?([0-9]+)/?$"] = 'index.php?author_name=$matches[1]&paged=$matches[2]';
        $author_rewrite["({$author->nicename})/?$"] = 'index.php?author_name=$matches[1]';
    }
    return $author_rewrite;
}
add_filter('author_link', 'no_author_base', 1000, 2);
function no_author_base($link, $author_id) {
    $link_base = trailingslashit(get_option('home'));
    $link = preg_replace("|^{$link_base}author/|", '', $link);
    return $link_base . $link;
}

function ml_user_has_role($user_id, $role_name)
{
    $user_meta = get_userdata($user_id);
    $user_roles = $user_meta->roles;
    return in_array($role_name, $user_roles);
}

function ml_customer_list() {
    $customer_query = new WP_User_Query(
        array(
            'fields' => 'ID',
            'role' => 'customer',
            'number' => 5000
        )
    );
    return $customer_query->get_results();
}

/**
 * Get generate thumbnail from uploads/alaround-mockup/{user_id}/{product_id}.jpg
 *
 * @param [type] $thumbnail
 * @param [type] $user_id
 * @param [type] $product_id
 * @return string
 */
function ml_get_thumbnail( $thumbnail, $user_id, $product_id ) {
    $filetype = wp_check_filetype($thumbnail[0]);
    $ext = $filetype['ext'];

    $upload_dir = wp_upload_dir();
    // $gen_thumbnail = $upload_dir['baseurl'] . DIRECTORY_SEPARATOR . AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR  . $user_id . DIRECTORY_SEPARATOR  . 'resized_' . $product_id . '.'.$ext;
    // $gen_thumbnail = str_replace('\\', '/', $gen_thumbnail);
    // $basedir_url = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR  . $user_id . DIRECTORY_SEPARATOR . 'resized_' . $product_id . '.'.$ext;
    // if( file_exists( $basedir_url ) ) {
    //     return $gen_thumbnail;
    // }
    $full_gen_thumbnail = $upload_dir['baseurl'] . DIRECTORY_SEPARATOR. AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . $product_id . '.'.$ext;
    $full_gen_thumbnail = str_replace('\\', '/', $full_gen_thumbnail);
    $full_basedir_url = $upload_dir['basedir'] . DIRECTORY_SEPARATOR. AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . $product_id . '.'.$ext;
    if( file_exists( $full_basedir_url ) ) {
        return $full_gen_thumbnail;
    }

    return $thumbnail[0];
}

function ml_get_gallery_thumbnail($key, $attachment_id, $user_id, $product_id, $full = false) {

    $thumbnail = wp_get_attachment_image_src($attachment_id, 'alarnd_main_thumbnail');
    $filetype = wp_check_filetype($thumbnail[0]);
    $ext = $filetype['ext'];

    $upload_dir = wp_upload_dir();
    $gen_thumbnail = $upload_dir['baseurl'] . DIRECTORY_SEPARATOR. AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . 'wc_thumb_' . $product_id . '-' . $key . '-' . $attachment_id . '.'.$ext;
    $gen_thumbnail = str_replace('\\', '/', $gen_thumbnail);
    $basedir_url = $upload_dir['basedir'] . DIRECTORY_SEPARATOR. AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . 'wc_thumb_' . $product_id . '-' . $key . '-' . $attachment_id . '.'.$ext;
    if( false === $full && file_exists( $basedir_url ) ) {
        // error_log( "gen_thumbnail $gen_thumbnail" );
        return $gen_thumbnail;
    }
    $full_gen_thumbnail = $upload_dir['baseurl'] . DIRECTORY_SEPARATOR. AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . $product_id . '-' . $key . '-' . $attachment_id . '.'.$ext;
    $full_gen_thumbnail = str_replace('\\', '/', $full_gen_thumbnail);
    $full_basedir_url = $upload_dir['basedir'] . DIRECTORY_SEPARATOR. AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . $product_id . '-' . $key . '-' . $attachment_id . '.'.$ext;
    if( true === $full && file_exists( $full_basedir_url ) ) {
        // error_log( "full_gen_thumbnail $full_gen_thumbnail" );
        return $full_gen_thumbnail;
    }
    return $thumbnail[0];
}

function ml_gallery_carousels( $product_id, $user_id ) {
    // error_log( $product_id );
    $galleries = get_gallery_thumbs( $product_id );

    $product = wc_get_product( $product_id );

    $thumbnail = wp_get_attachment_image_src($product->get_image_id(), 'alarnd_main_thumbnail');
    if( ! empty( $thumbnail ) ) {
        $thumbnail = ml_get_thumbnail($thumbnail, $user_id, $product_id );
    }

    if( ! empty( $galleries ) ) {
        echo ' <div class="woocommerce-product-gallery alarn--pricing-column">';
            echo '<div class="allaround--slick-carousel">';
                foreach ($galleries as $key => $gallery) {
                    //For Smaller Thumbnail
                    // $thumbnail = ml_get_gallery_thumbnail($key, $gallery['attachment_id'], $user_id, $product_id);

                    //For Original Size
                    $thumbnail = ml_get_gallery_thumbnail($key, $gallery['attachment_id'], $user_id, $product_id, true);

                    $full_thumbnail = ml_get_gallery_thumbnail($key, $gallery['attachment_id'], $user_id, $product_id, true);
                    // $gallery_thumb = 
                    echo '<div><img src="' . esc_url( $thumbnail ) . '" class="gallery-item zoom" data-mfp-src="' . esc_url( $full_thumbnail ) . '" data-magnify-src="' . esc_url( $full_thumbnail ) . '"/></div>';
                }
            echo '</div>';
            echo '<ol class="mlCustomDots">';
            foreach ($galleries as $key => $gallery) {
                echo '<li><a href="#" data-slide="'.$key.'" style="background-color: '.$gallery['color'].'"></a></li>';
            }
            echo '</ol>';
        echo '</div>';
    } elseif( ! empty( $thumbnail ) ) {
        // Product Thumbnail
        echo '<div class="product-thumbnail alarn--pricing-column">';
        echo '<img src="'.$thumbnail.'" data-magnify-src="' . esc_url( $thumbnail ) . '"  class="zoom"/>';
        echo '</div>';
    }
}

function is_image_url_exists($url) {
    // Make an HTTP GET request to the image URL
    $response = wp_remote_get($url);

    // Check if the request was successful and the response code is within the 200-299 range
    if (is_array($response) && isset($response['response']['code']) && $response['response']['code'] >= 200 && $response['response']['code'] < 300) {
        return true; // Image URL exists
    } else {
        return false; // Image URL does not exist
    }
}


function get_customer_id_by_order_id($order_id) {
    $order = wc_get_order($order_id);
    if ($order) {
        return $order->get_customer_id();
    }
    return null;
}

function ml_get_last_order($customer_id, $payment_method, $meta_key) {
    $args = array(
        'customer_id' => $customer_id,
        'limit'       => 1,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'meta_query'  => array(
            'relation' => 'AND', // Use 'AND' to check both meta queries.
            array(
                'key'     => '_payment_method',
                'value'   => $payment_method,
                'compare' => '=',
            ),
            array(
                'key'     => $meta_key,
                'compare' => 'EXISTS', // Check if the meta key exists.
            ),
            array(
                'key'     => $meta_key,
                'value'   => '',
                'compare' => '!=', // Check if the meta value is not empty.
            ),
        ),
    );

    $orders = wc_get_orders($args);

    if (!empty($orders)) {
        return reset($orders); // Get the first order (last order) in the list.
    }

    return null; // No order found with the specified payment method and non-empty meta value.
}


function get_zc_response( $order_id ) {
	$json = get_post_meta( $order_id, 'zc_response', true );
	$json = $json ? unserialize(base64_decode($json)) : "";
	
	// Converts it into a PHP object
	$zc_response = json_decode($json, true);
	//$data = $zc_response['Data'];
	
	return $zc_response;
}

// Function to create a new user
function ml_create_new_user() {
    // Your code to create a new user here

    // Flush rewrite rules after creating the user
    flush_rewrite_rules();
}

// Hook this function to a user creation event or action
add_action('user_register', 'ml_create_new_user');

function ml_update_user_profile_from_order($order) {
    // Get the order object
    $order_id = $order->get_id();

    $username = $order->get_meta('_profile_username');
    if( ! empty( $username ) && empty( $order->get_user_id() ) ) {

        $author_obj = get_user_by('login', $username);
        $user_id = $author_obj->ID;

        // Billing information
        $billing_address_1 = $order->get_billing_address_1();
        $billing_address_2 = $order->get_billing_address_2();
        $billing_city = $order->get_billing_city();
        $billing_state = $order->get_billing_state();
        $billing_postcode = $order->get_billing_postcode();
        $billing_country = $order->get_billing_country();

        update_user_meta_if_different($user_id, 'billing_address_1', $billing_address_1);
        update_user_meta_if_different($user_id, 'billing_address_2', $billing_address_2);
        update_user_meta_if_different($user_id, 'billing_city', $billing_city);
        update_user_meta_if_different($user_id, 'billing_state', $billing_state);
        update_user_meta_if_different($user_id, 'billing_postcode', $billing_postcode);
        update_user_meta_if_different($user_id, 'billing_country', $billing_country);

        // First name and last name
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();

        // Update first name and last name
        update_user_meta_if_different($user_id, 'first_name', $first_name);
        update_user_meta_if_different($user_id, 'last_name', $last_name);

        // Phone number
        $billing_phone = $order->get_billing_phone();
        update_user_meta_if_different($user_id, 'billing_phone', $billing_phone);

        // Check payment method and update 'zc_response' if it's 'zcredit_checkout_payment'
        if ($order->get_payment_method() === 'zcredit_checkout_payment') {
            $zc_response = get_user_meta($user_id, 'zc_response', true);
            $zc_payment_token = get_user_meta($user_id, 'zc_payment_token', true);
            $zc_transaction_id = get_user_meta($user_id, 'zc_transaction_id', true);
            update_user_meta_if_different($user_id, 'zc_response', $zc_response);
            update_user_meta_if_different($user_id, 'zc_payment_token', $zc_response);
            update_user_meta_if_different($user_id, 'zc_transaction_id', $zc_response);

            update_acf_usermeta( $user_id, 'token', $zc_payment_token );
        }

        // Email address
        $billing_email = $order->get_billing_email();
        update_user_email_if_different($user_id, $billing_email);
    } elseif( ! empty( $order->get_user_id() ) ) {
        $user_id = $order->get_user_id();

        // Check payment method and update 'zc_response' if it's 'zcredit_checkout_payment'
        if ($order->get_payment_method() === 'zcredit_checkout_payment') {
            $zc_response = get_user_meta($user_id, 'zc_response', true);
            $zc_payment_token = get_user_meta($user_id, 'zc_payment_token', true);
            $zc_transaction_id = get_user_meta($user_id, 'zc_transaction_id', true);
            update_user_meta_if_different($user_id, 'zc_response', $zc_response);
            update_user_meta_if_different($user_id, 'zc_payment_token', $zc_response);
            update_user_meta_if_different($user_id, 'zc_transaction_id', $zc_response);
        }
    }
}

// Custom function to update user meta if different
function update_user_meta_if_different($user_id, $meta_key, $new_value) {
    $current_value = get_user_meta($user_id, $meta_key, true);
    if ($new_value && $new_value !== $current_value) {
        update_user_meta($user_id, $meta_key, $new_value);
    }
}

function update_acf_usermeta($user_id, $meta_key, $new_value) {
    $current_value = get_field($meta_key, "user_{$user_id}");
    if ($new_value && $new_value !== $current_value) {
        update_field($meta_key, $new_value, 'user_' . $user_id);
    }
}

// Custom function to update user email if different
function update_user_email_if_different($user_id, $new_email) {
    $current_email = get_userdata($user_id)->user_email;
    if ($new_email && $new_email !== $current_email) {
        wp_update_user(array('ID' => $user_id, 'user_email' => $new_email));
        // Update the 'dummy_email' field to false
        update_field('dummy_email', false, 'user_' . $user_id);
    }
}
function update_user_name_if_different($user_id, $name) {
    $current_name = get_userdata($user_id)->display_name;
    if ($name && $name !== $current_name) {
        wp_update_user(array('ID' => $user_id, 'display_name' => $name));
    }
}

add_action('woocommerce_checkout_order_created', 'ml_update_user_profile_from_order');


// Save the custom field value as order meta data
add_action('woocommerce_checkout_create_order', 'save_custom_checkout_field');

function save_custom_checkout_field($order) {
    if (!empty($_POST['user_profile_username'])) {
        $order->update_meta_data('_profile_username', sanitize_text_field($_POST['user_profile_username']));
    }
}

// Display the custom field in the order details
add_action('woocommerce_admin_order_data_after_billing_address', 'display_custom_field_in_order_details');

function display_custom_field_in_order_details($order) {
    $custom_field = $order->get_meta('_profile_username');
    if ($custom_field) {
        echo '<p><strong>Profile Username:</strong> ' . esc_html($custom_field) . '</p>';
    }
}


/**
 * Check if array has key called "label"
 *
 * @param array $selections
 * @return boolean
 */
function ml_need_refactor($selections) {
    $labelKeyExists = false;

    // Iterate through the array and check if a "label" key exists in any of the elements
    foreach ($selections as $value) {
        if (is_array($value) && array_key_exists('label', $value)) {
            $labelKeyExists = true;
            break; // Exit the loop as soon as we find a "label" key
        }
    }

    return $labelKeyExists;
}

/**
 * Convert Selecte Product into proper array
 *
 * @param array $selections
 * @return array
 */
function ml_refactor_selects( $selections ) {
    $refactor = [];
    foreach( $selections as $select ) {
        $title = get_the_title( (int) $select );
        $refactor[] = array(
            "value" => $select,
            "label" => $title
        );
    }
    return $refactor;
}

/**
 * Get user selected products with defaults
 *
 * @param int $user_id
 * @return array
 */
function ml_get_user_products($user_id, $filter_item = '') {
    $default_products = get_field('default_products', 'option');
    $selected_product_ids = get_field('selected_products', "user_{$user_id}");
    $disable_product = get_field('disable_product', "user_{$user_id}");

    if (empty($default_products) && empty($selected_product_ids)) {
        return [];
    }

    $uniqueValues = [];

    $results = [];
    if (!empty($default_products)) {
        foreach ($default_products as $item) {
            if (!isset($uniqueValues[$item['value']]) && hasValidThumbnail($item['value'])) {
                if ($filter_item === '' || productBelongsToCategory($item['value'], $filter_item)) {
                    $results[] = $item;
                    $uniqueValues[$item['value']] = true;
                }
            }
        }
    }

    if (!empty($selected_product_ids)) {
        $is_refactor_need = ml_need_refactor($selected_product_ids);
        if (false === $is_refactor_need) {
            $selected_product_ids = ml_refactor_selects($selected_product_ids);
        }

        foreach ($selected_product_ids as $item) {
            if (!isset($uniqueValues[$item['value']]) && hasValidThumbnail($item['value'])) {
                if ($filter_item === '' || productBelongsToCategory($item['value'], $filter_item)) {
                    $results[] = $item;
                    $uniqueValues[$item['value']] = true;
                }
            }
        }
    }

    if (!empty($disable_product)) {
        $disable_product_values = array_column($disable_product, 'value');

        $filtered_array = array_filter($results, function ($item) use ($disable_product_values) {
            return !in_array($item['value'], $disable_product_values);
        });

        return $filtered_array;
    }

    return $results;
}

function productBelongsToCategory($product_id, $category_id) {
    $terms = wp_get_post_terms($product_id, 'product_cat');
    foreach ($terms as $term) {
        if ($term->term_id == $category_id) {
            return true;
        }
    }
    return false;
}


// Function to check if a post has a valid thumbnail
function hasValidThumbnail($postId) {
    $thumbnail_id = get_post_thumbnail_id($postId);
    if ($thumbnail_id) {
        $thumbnail_url = wp_get_attachment_image_src($thumbnail_id, 'full');
        if (!empty($thumbnail_url[0])) {
            return true;
        }
    }
    return false;
}


function ml_get_metas($product_id) {
    global $wpdb;

    $prefix = 'ml_canvas_logos';

    $query = $wpdb->prepare("
        SELECT
            meta_key,
            meta_value
        FROM
            {$wpdb->prefix}postmeta
        WHERE
            post_id = %d
            AND (meta_key = %s OR meta_key LIKE %s)
    ", $product_id, $prefix, $prefix . '_%');

    $results = $wpdb->get_results($query);

    $filter_results = [];
    if ($results) {
        foreach ($results as $key => $result) {
            $filter_results[$key]['meta_key'] = $result->meta_key;
            $filter_results[$key]['meta_value'] = maybe_unserialize($result->meta_value);
        }
    }

    return $filter_results;
}

function ml_register_custom_image_size() {
    // Register the custom image size
    add_image_size('alarnd_main_thumbnail', 1500, 1500, false);
    add_image_size('alarnd_main_thumbnail_resize', 400, 400, false);

    // You can register more custom image sizes as needed
}
add_action('init', 'ml_register_custom_image_size');



function get_galleries( $product_id, $thumb_size = 'alarnd_main_thumbnail' ) {
    $_product = wc_get_product( $product_id );

    $attachment_ids = $_product->get_gallery_image_ids();
    $galleries = [];
    if( ! empty( $attachment_ids ) ) {
        foreach ($attachment_ids as $attachment_id) {
            $gallery_thumb = wp_get_attachment_image_src($attachment_id, $thumb_size);
            if( ! empty( $gallery_thumb ) && isset( $gallery_thumb[0] ) ) {
                $galleries[$attachment_id] = $gallery_thumb[0];
            }
        }
    }
    return $galleries;
}

function get_color_thumbnails( $product_id, $thumb_size = 'alarnd_main_thumbnail' ) {
    $colors = get_field( 'color', $product_id );

    $galleries = [];
    if( ! empty( $colors ) ) {
        foreach ($colors as $color) {
            if( ! isset($color['color_hex_code']) || empty( $color['color_hex_code'] ) )
                continue;

            $thumbnail = isset($color['thumbnail']) && ! empty( $color['thumbnail'] ) ? $color['thumbnail'] : '';
            if( ! empty( $thumbnail ) && isset( $thumbnail['ID'] ) ) {
                $url = wp_get_attachment_image_src($thumbnail['ID'], $thumb_size);

                $get_lightdark = isColorLightOrDark($color['color_hex_code']);
                $lightdark = isset( $color['lightdark'] ) && in_array( $color['lightdark'], array('Lighter', 'Darker') ) ? $color['lightdark'] : $get_lightdark;

                $galleries[] = array(
                    'type' => $lightdark,
                    'attachment_id' => $thumbnail['ID'],
                    'color_hex' => $color['color_hex_code'],
                    'thumbnail' => $url[0]
                );
            }
        }
    }
    return $galleries;
}

function get_gallery_thumbs( $product_id, $thumb_size = 'alarnd_main_thumbnail' ) {
    $colors = get_field( 'color', $product_id );

    $galleries = [];
    if( ! empty( $colors ) ) {
        foreach ($colors as $color) {
            if( ! isset($color['color_hex_code']) || empty( $color['color_hex_code'] ) )
                continue;

            $thumbnail = isset($color['thumbnail']) && ! empty( $color['thumbnail'] ) ? $color['thumbnail'] : '';
            if( ! empty( $thumbnail ) && isset( $thumbnail['ID'] ) ) {
                $url = wp_get_attachment_image_src($thumbnail['ID'], $thumb_size);
                $galleries[] = array(
                    'attachment_id' => $thumbnail['ID'],
                    'color' => $color['color_hex_code'],
                    'title' => $color['title'],
                    'thumbnail' => $url[0]
                );
            }
        }
    }
    return $galleries;
}


function isColorLightOrDark($hexColor) {
    // Remove the # symbol if present
    $hexColor = ltrim($hexColor, '#');

    // Convert the hex color to RGB
    $r = hexdec(substr($hexColor, 0, 2));
    $g = hexdec(substr($hexColor, 2, 2));
    $b = hexdec(substr($hexColor, 4, 2));

    // Calculate the luminance (perceived brightness) of the color
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

    // Define a threshold to determine if the color is light or dark
    $threshold = 0.5;

    // Compare the luminance to the threshold
    if ($luminance > $threshold) {
        return 'dark';
    } else {
        return 'light';
    }
}


// Set the Display Name to FirstName + Last Name
add_filter('pre_user_display_name', 'alar_set_display_name_to_first_last_name');

function alar_set_display_name_to_first_last_name($display_name) {
    $first_name = isset($_POST['billing_first_name']) ? sanitize_text_field($_POST['billing_first_name']) : '';
    $last_name = isset($_POST['billing_last_name']) ? sanitize_text_field($_POST['billing_last_name']) : '';

    if (!empty($first_name) && !empty($last_name)) {
        $display_name = $first_name . ' ' . $last_name;
    } elseif (!empty($first_name)) {
        $display_name = $first_name;
    } elseif (!empty($last_name)) {
        $display_name = $last_name;
    }

    return $display_name;
}


function ml_get_user_phone( $user_id, $code_or_phone = '' ){

	$code 	= esc_attr( get_user_meta( $user_id, 'xoo_ml_phone_code', true ) );
	$number = esc_attr( get_user_meta( $user_id, 'xoo_ml_phone_no', true ) );

    if( empty( $number ) ) {
        return '';
    }

	if( $code_or_phone === 'number' ){
		return $number;
	}else if( $code_or_phone === 'code' ){
		return $code;
	}

    if( ! empty( $code ) ) {
        // $number = $code . $number;
        $number = $number;
    }

    return $number;
}

function ml_get_author_page_userid() {
    global $wp_query;

    if( is_user_logged_in() ) {
        // Get the current author's username from the URL
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;

        return $current_user_id;
    }

    if( WC()->session->get('ml_author_id') ) {
        return WC()->session->get('ml_author_id');
    }

    // Check if the query is for an author
    if (isset($wp_query->query_vars['author_name'])) {
        $username = $wp_query->query_vars['author_name'];

        $get_current_puser = get_user_by('login', $username);
        if( ! $get_current_puser ) {
            return false;
        }

        return $get_current_puser->ID;
    }

    return false;
}


function alarnd_single_checkout($user_id = false) {

    $current_user = wp_get_current_user();
    $current_user_id = $current_user->ID;
    $user_email = $current_user->user_email;
    $display_name = $current_user->display_name;

    if( ! empty( $user_id ) ) {
        $current_user_id = $user_id;
    }
    
    $token = get_field('token', "user_{$current_user_id}");
    $phone = ml_get_user_phone($current_user_id);

    $dummy_email = get_field('dummy_email', "user_{$current_user_id}");
    $card_info = get_field('card_info', "user_{$current_user_id}");
    $invoice = get_field('invoice', "user_{$current_user_id}");
    $city = get_user_meta( $current_user_id, 'billing_city', true );
    $billing_address = get_user_meta( $current_user_id, 'billing_address_1', true );

    $four_digit = isset( $card_info['last_4_digit'] ) && ! empty( $card_info['last_4_digit'] ) ? $card_info['last_4_digit'] : '';
    $card_logo = isset( $card_info['card_type'] ) && ! empty( $card_info['card_type'] ) ? strtolower($card_info['card_type']) : 'mastercard';
    $card_logo = str_replace(" ", "-", $card_logo);
    $card_logo_path = AlRNDCM_URL . "assets/images/$card_logo.png";


    $is_disabled = false;
    if(
        empty( $phone ) ||
        empty( $billing_address ) ||
        empty( $city ) ||
        empty( $display_name ) ||
        empty( $user_email )
    ) {
        $is_disabled = true;
    }


    ?>
    <div class="alarnd--payout-main">
        <div class="alanrd--single-payout-wrap">
            <div class="alarnd--payout-col alrnd_tokenized_col">
                <div class="alarnd--payout-col alrnd_wooz_col">
                    <div class="alrnd--pay_details_tokenized">
                        <h2><?php esc_html_e( 'פרטי תשלום', 'hello-elementor' ); ?></h2>
                        <div class="alarnd--payout-options">
                            <div class="alarnd-payout-choose">
                                <div class="alarnd--single-payout">
                                    <label>
                                        <input type="radio" name="alarnd_payout" value="tokenizer" checked="checked">
                                        <img src="<?php echo esc_url($card_logo_path); ?>" alt="Selected Card">
                                        <div class="alarnd--four-digit"><?php echo esc_html($four_digit); ?>&nbsp;<span>****&nbsp;****&nbsp;****</span></div>
                                    </label>
                                </div>
                                <div class="alarnd--single-payout">
                                    <label>
                                        <input type="radio" name="alarnd_payout" value="woocommerce">
                                        <div class="alarnd--four-digit">כרטיס חדש</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card Image Demo -->
                    <div class="payment-info-display">
                        <div class="payment-title">
                        </div>
                        <div class="card-img-container preload">
                            <div class="creditcard">
                                <div class="front">
                                    <div id="ccsingle"></div>
                                    <svg version="1.1" id="cardfront" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                        x="0px" y="0px" viewBox="0 0 750 471" style="enable-background:new 0 0 750 471;" xml:space="preserve">
                                        <g id="Front">
                                            <g id="CardBackground">
                                                <g id="Page-1_1_">
                                                    <g id="amex_1_">
                                                        <path id="Rectangle-1_1_" class="lightcolor grey" d="M40,0h670c22.1,0,40,17.9,40,40v391c0,22.1-17.9,40-40,40H40c-22.1,0-40-17.9-40-40V40
                                                C0,17.9,17.9,0,40,0z" />
                                                    </g>
                                                </g>
                                                <path class="darkcolor greydark" d="M750,431V193.2c-217.6-57.5-556.4-13.5-750,24.9V431c0,22.1,17.9,40,40,40h670C732.1,471,750,453.1,750,431z" />
                                            </g>
                                            <text transform="matrix(1 0 0 1 60.106 295.0121)" id="svgnumber" class="st2 st3 st4">1111 1111 1111 1111</text>
                                            <text transform="matrix(1 0 0 1 54.1064 428.1723)" id="svgname" class="st2 st5 st6">JOHN DOE</text>
                                            <text transform="matrix(1 0 0 1 54.1074 389.8793)" id="svgnameTitle" class="st7 st5 st8">cardholder name</text>
                                            <text transform="matrix(1 0 0 1 479.7754 388.8793)" class="st7 st5 st8">expiration</text>
                                            <text transform="matrix(1 0 0 1 65.1054 241.5)" class="st7 st5 st8">card number</text>
                                            <g>
                                                <text transform="matrix(1 0 0 1 574.4219 433.8095)" id="svgexpire" class="st2 st5 st9">MM/YY</text>
                                                <text transform="matrix(1 0 0 1 479.3848 417.0097)" class="st2 st10 st11">VALID</text>
                                                <text transform="matrix(1 0 0 1 479.3848 435.6762)" class="st2 st10 st11">THRU</text>
                                                <polygon class="st2" points="554.5,421 540.4,414.2 540.4,427.9 		" />
                                            </g>
                                            <g id="cchip">
                                                <g>
                                                    <path class="st2" d="M168.1,143.6H82.9c-10.2,0-18.5-8.3-18.5-18.5V74.9c0-10.2,8.3-18.5,18.5-18.5h85.3
                                            c10.2,0,18.5,8.3,18.5,18.5v50.2C186.6,135.3,178.3,143.6,168.1,143.6z" />
                                                </g>
                                                <g>
                                                    <g>
                                                        <rect x="82" y="70" class="st12" width="1.5" height="60" />
                                                    </g>
                                                    <g>
                                                        <rect x="167.4" y="70" class="st12" width="1.5" height="60" />
                                                    </g>
                                                    <g>
                                                        <path class="st12" d="M125.5,130.8c-10.2,0-18.5-8.3-18.5-18.5c0-4.6,1.7-8.9,4.7-12.3c-3-3.4-4.7-7.7-4.7-12.3
                                                c0-10.2,8.3-18.5,18.5-18.5s18.5,8.3,18.5,18.5c0,4.6-1.7,8.9-4.7,12.3c3,3.4,4.7,7.7,4.7,12.3
                                                C143.9,122.5,135.7,130.8,125.5,130.8z M125.5,70.8c-9.3,0-16.9,7.6-16.9,16.9c0,4.4,1.7,8.6,4.8,11.8l0.5,0.5l-0.5,0.5
                                                c-3.1,3.2-4.8,7.4-4.8,11.8c0,9.3,7.6,16.9,16.9,16.9s16.9-7.6,16.9-16.9c0-4.4-1.7-8.6-4.8-11.8l-0.5-0.5l0.5-0.5
                                                c3.1-3.2,4.8-7.4,4.8-11.8C142.4,78.4,134.8,70.8,125.5,70.8z" />
                                                    </g>
                                                    <g>
                                                        <rect x="82.8" y="82.1" class="st12" width="25.8" height="1.5" />
                                                    </g>
                                                    <g>
                                                        <rect x="82.8" y="117.9" class="st12" width="26.1" height="1.5" />
                                                    </g>
                                                    <g>
                                                        <rect x="142.4" y="82.1" class="st12" width="25.8" height="1.5" />
                                                    </g>
                                                    <g>
                                                        <rect x="142" y="117.9" class="st12" width="26.2" height="1.5" />
                                                    </g>
                                                </g>
                                            </g>
                                        </g>
                                        <g id="Back">
                                        </g>
                                    </svg>
                                </div>
                                <div class="back">
                                    <svg version="1.1" id="cardback" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                        x="0px" y="0px" viewBox="0 0 750 471" style="enable-background:new 0 0 750 471;" xml:space="preserve">
                                        <g id="Front">
                                            <line class="st0" x1="35.3" y1="10.4" x2="36.7" y2="11" />
                                        </g>
                                        <g id="Back">
                                            <g id="Page-1_2_">
                                                <g id="amex_2_">
                                                    <path id="Rectangle-1_2_" class="darkcolor greydark" d="M40,0h670c22.1,0,40,17.9,40,40v391c0,22.1-17.9,40-40,40H40c-22.1,0-40-17.9-40-40V40
                                            C0,17.9,17.9,0,40,0z" />
                                                </g>
                                            </g>
                                            <rect y="61.6" class="st2" width="750" height="78" />
                                            <g>
                                                <path class="st3" d="M701.1,249.1H48.9c-3.3,0-6-2.7-6-6v-52.5c0-3.3,2.7-6,6-6h652.1c3.3,0,6,2.7,6,6v52.5
                                        C707.1,246.4,704.4,249.1,701.1,249.1z" />
                                                <rect x="42.9" y="198.6" class="st4" width="664.1" height="10.5" />
                                                <rect x="42.9" y="224.5" class="st4" width="664.1" height="10.5" />
                                                <path class="st5" d="M701.1,184.6H618h-8h-10v64.5h10h8h83.1c3.3,0,6-2.7,6-6v-52.5C707.1,187.3,704.4,184.6,701.1,184.6z" />
                                            </g>
                                            <text transform="matrix(1 0 0 1 621.999 227.2734)" id="svgsecurity" class="st6 st7">985</text>
                                            <g class="st8">
                                                <text transform="matrix(1 0 0 1 518.083 280.0879)" class="st9 st6 st10">security code</text>
                                            </g>
                                            <rect x="58.1" y="378.6" class="st11" width="375.5" height="13.5" />
                                            <rect x="58.1" y="405.6" class="st11" width="421.7" height="13.5" />
                                            <text transform="matrix(1 0 0 1 59.5073 228.6099)" id="svgnameback" class="st12 st13">John Doe</text>
                                        </g>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Card Image Demo END -->
                </div>
                <div class="alrnd--shipping_address_tokenized">
                    <?php if( $is_disabled === false ) : ?>
                    <div id="alarnd__details_preview">
                        <div class="alarnd--payout-col alarnd--details-previewer">
                            <h3><?php esc_html_e("כתובת למשלוח", "hello-elementor" ); ?></h3>
                            <div class="tokenized_inv_name_cont"><?php esc_html_e( 'חשבונית על שם', 'hello-elementor' ); ?>:
                            <?php echo ! empty( $invoice ) ? '<p class="tokenized_user_name">'. esc_html( $invoice ) .'</p>' : '<p class="tokenized_user_name">'. esc_html( $display_name ) .'</p>'; ?>
                            </div>

                            <div class="alarnd--user-address">
                                <div class="alarnd--user-address-wrap">
                                    <?php echo ! empty( $display_name ) ? '<p>'. esc_html( $display_name ) .'</p>' : ''; ?>
                                    <?php echo ! empty( $phone ) ? '<p>'. esc_html( $phone ) .'</p>' : ''; ?>
                                    <?php echo ! empty( $user_email ) ? '<p>'. esc_html( $user_email ) .'</p>' : ''; ?>
                                    <p>
                                    <?php echo ! empty( $billing_address ) ? '<span>'. esc_html( $billing_address ) .', </span>' : ''; ?>
                                    <?php echo ! empty( $city ) ? '<span>'. esc_html( $city ) .'</span>' : ''; ?>
                                    </p>
                                </div>
                                <span class="alarnd--user_address_edit">שינוי</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php echo allaround_customer_form($is_disabled); ?>
                </div>

                <div class="alarnd--card-details-wrap <?php echo $is_disabled === false ? 'pix_all_user_data_filled' : ''; ?>">
                    <?php echo allaround_card_form(); ?>
                </div>
            </div>

        </div>

        <div class="your-order-total-container"><?php esc_html_e("Total Amount", "hello-elementor" ); ?>: <?php printf( WC()->cart->get_total() ); ?></div>

        <div class="alarnd--single-payout-submit">
            <button type="submit" class="alarnd--regular-button alarnd--payout-trigger ml_add_loading button" <?php echo ($is_disabled === true ? 'disabled="disabled"' : WC()->cart->is_empty()) ? 'disabled="disabled"' : ''; ?>>התקדם לנקודת הביקורת</button>
        </div>
        <div class="form-message text-center"></div>
    </div>
    <?php
}



function allaround_card_form($user_id = '') {

    $current_user_id = ml_get_author_page_userid();

    if( ! empty( $user_id ) ) {
        $current_user_id = $user_id;
    }

    error_log("he id $current_user_id");

    $name = $phone = $user_billing_info = $email = '';
    $available_card_path = AlRNDCM_URL . "assets/images/available-cards.png";
    $cvc_info_path = AlRNDCM_URL . "assets/images/question-circle.svg";

    $the_user = get_user_by( 'id', $current_user_id );

    $phone = ml_get_user_phone($current_user_id);
    $user_billing_info = get_field('user_billing_info', "user_{$current_user_id}");
    $invoice = get_field('invoice', "user_{$current_user_id}");
    $city = get_user_meta( $current_user_id, 'billing_city', true );
    $billing_address = get_user_meta( $current_user_id, 'billing_address_1', true );
    $dummy_email = get_field('dummy_email', "user_{$current_user_id}");

    $token = get_field('token', "user_{$current_user_id}");

    $name = isset( $the_user->display_name ) && ! empty( $the_user->display_name ) ? $the_user->display_name : $current_user_id;
    $email = $the_user->user_email;
    $lock_profile = get_field('lock_profile', 'user_' . $current_user_id);
    error_log("email $email");
    
    
    $is_disabled = false;
    if(
        empty( $phone ) ||
        empty( $billing_address ) ||
        empty( $city ) ||
        empty( $name ) ||
        empty( $email ) ||
        ($lock_profile === true) ||
        ($dummy_email === true)
    ) {
        $is_disabled = true;
    }

    ?>
    <?php if( !is_user_logged_in() || empty( $token ) ) : ?>
    <div class="alrnd--shipping_address_tokenized mini_non_loggedIn">
        <?php if( $is_disabled === false ) : ?>
        <div id="alarnd__details_preview">
            <div class="alarnd--payout-col alarnd--details-previewer">
                <h3><?php esc_html_e("כתובת למשלוח", "hello-elementor" ); ?></h3>
                <div class="tokenized_inv_name_cont"><?php esc_html_e( 'חשבונית על שם', 'hello-elementor' ); ?>:<?php echo ! empty( $invoice ) ? '<p class="tokenized_user_name">'. esc_html( $invoice ) .'</p>' : '<p class="tokenized_user_name">'. esc_html( $name ) .'</p>'; ?></div>

                <div class="alarnd--user-address">
                    <div class="alarnd--user-address-wrap">
                        <?php echo ! empty( $name ) ? '<p>'. esc_html( $name ) .'</p>' : ''; ?>
                        <?php echo ! empty( $phone ) ? '<p>'. esc_html( $phone ) .'</p>' : ''; ?>
                        <?php echo ! empty( $email ) && !$dummy_email ? '<p>'. esc_html( $email ) .'</p>' : ''; ?>
                        <p>
                        <?php echo ! empty( $billing_address ) ? '<span>'. esc_html( $billing_address ) .', </span>' : ''; ?>
                        <?php echo ! empty( $city ) ? '<span>'. esc_html( $city ) .'</span>' : ''; ?>
                        </p>
                    </div>
                    <span class="alarnd--user_address_edit">שינוי</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php echo allaround_customer_form($is_disabled); ?>
    </div>
    <?php endif; ?>

    <form action="" id="cardDetailsForm" class="allaround--card-form cardForm-wCard <?php echo $is_disabled === false ? 'pix_all_user_data_filled' : ''; ?>" data-user_id="<?php echo $user_id; ?>">

        <div class="allaround_carf_form-fields">
            <div class="allaround_carf_form-cardDetail">

                <?php if( !is_user_logged_in() || empty( $token ) ) : ?>
                <h2><?php esc_html_e( 'מעובד לקופה', 'hello-elementor' ); ?></h2>
                <?php endif; ?>

                <div class="ministore_available_pay_options">
                    <span><?php esc_html_e( 'יארשא סיטרכ', 'hello-elementor' ); ?></span> <img src="<?php echo esc_url($available_card_path); ?>" alt="Available Payment Options" loading="lazy">
                </div>
                <div class="form-cardDetail-fields">
                    <div class="form-row">
                        <div class="form-label"><?php esc_html_e("Card Number", "hello-elementor" ); ?></div>
                        <div class="form-input">
                            <input type="text" id="cardNumber" name="cardNumber" maxlength="19" placeholder="<?php esc_attr_e("1111 1111 1111 1111", "hello-elementor" ); ?>" dir="ltr" inputmode="numeric" required>
                            <svg id="ccicon" class="ccicon" width="750" height="471" viewBox="0 0 750 471" version="1.1" xmlns="http://www.w3.org/2000/svg"
                            xmlns:xlink="http://www.w3.org/1999/xlink"></svg>
                        </div>
                    </div>
                    <div class="form-row flex-row exp-cvc-con">
                        <div class="form-row">
                            <div class="form-label"><?php esc_html_e("Expiration Date", "hello-elementor" ); ?></div>
                            <div class="form-input">
                                <input type="text" inputmode="numeric" id="expirationDate" maxlength="5" name="expirationDate" placeholder="<?php esc_attr_e("MM/YY", "hello-elementor" ); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-label"><?php esc_html_e("CVC", "hello-elementor" ); ?></div>
                            <div class="form-input">
                                <input type="number" id="cvvCode" maxlength="3" name="cvvCode" placeholder="<?php esc_attr_e("CVC", "hello-elementor" ); ?>" inputmode="numeric" required>
                                <div class="cvc-info tooltip-left" data-tooltip="<?php esc_html_e( '3 סיטרכה בגב תורפס', 'hello-elementor' ); ?>">
                                    <img src="<?php echo esc_url($cvc_info_path); ?>" alt="CVC Info" loading="lazy">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if( !is_user_logged_in() || empty( $token ) ) : ?>
                <!-- Card Image Demo -->
                <div class="payment-info-display nonAuthorized-infoDisplay">
                    <div class="payment-title">
                    </div>
                    <div class="card-img-container preload">
                        <div class="creditcard">
                            <div class="front">
                                <div id="ccsingle"></div>
                                <svg version="1.1" id="cardfront" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                    x="0px" y="0px" viewBox="0 0 750 471" style="enable-background:new 0 0 750 471;" xml:space="preserve">
                                    <g id="Front">
                                        <g id="CardBackground">
                                            <g id="Page-1_1_">
                                                <g id="amex_1_">
                                                    <path id="Rectangle-1_1_" class="lightcolor grey" d="M40,0h670c22.1,0,40,17.9,40,40v391c0,22.1-17.9,40-40,40H40c-22.1,0-40-17.9-40-40V40
                                            C0,17.9,17.9,0,40,0z" />
                                                </g>
                                            </g>
                                            <path class="darkcolor greydark" d="M750,431V193.2c-217.6-57.5-556.4-13.5-750,24.9V431c0,22.1,17.9,40,40,40h670C732.1,471,750,453.1,750,431z" />
                                        </g>
                                        <text transform="matrix(1 0 0 1 60.106 295.0121)" id="svgnumber" class="st2 st3 st4">1111 1111 1111 1111</text>
                                        <text transform="matrix(1 0 0 1 54.1064 428.1723)" id="svgname" class="st2 st5 st6">JOHN DOE</text>
                                        <text transform="matrix(1 0 0 1 54.1074 389.8793)" class="st7 st5 st8" id="svgnameTitle">cardholder name</text>
                                        <text transform="matrix(1 0 0 1 479.7754 388.8793)" class="st7 st5 st8">expiration</text>
                                        <text transform="matrix(1 0 0 1 65.1054 241.5)" class="st7 st5 st8">card number</text>
                                        <g>
                                            <text transform="matrix(1 0 0 1 574.4219 433.8095)" id="svgexpire" class="st2 st5 st9">MM/YY</text>
                                            <text transform="matrix(1 0 0 1 479.3848 417.0097)" class="st2 st10 st11">VALID</text>
                                            <text transform="matrix(1 0 0 1 479.3848 435.6762)" class="st2 st10 st11">THRU</text>
                                            <polygon class="st2" points="554.5,421 540.4,414.2 540.4,427.9 		" />
                                        </g>
                                        <g id="cchip">
                                            <g>
                                                <path class="st2" d="M168.1,143.6H82.9c-10.2,0-18.5-8.3-18.5-18.5V74.9c0-10.2,8.3-18.5,18.5-18.5h85.3
                                        c10.2,0,18.5,8.3,18.5,18.5v50.2C186.6,135.3,178.3,143.6,168.1,143.6z" />
                                            </g>
                                            <g>
                                                <g>
                                                    <rect x="82" y="70" class="st12" width="1.5" height="60" />
                                                </g>
                                                <g>
                                                    <rect x="167.4" y="70" class="st12" width="1.5" height="60" />
                                                </g>
                                                <g>
                                                    <path class="st12" d="M125.5,130.8c-10.2,0-18.5-8.3-18.5-18.5c0-4.6,1.7-8.9,4.7-12.3c-3-3.4-4.7-7.7-4.7-12.3
                                            c0-10.2,8.3-18.5,18.5-18.5s18.5,8.3,18.5,18.5c0,4.6-1.7,8.9-4.7,12.3c3,3.4,4.7,7.7,4.7,12.3
                                            C143.9,122.5,135.7,130.8,125.5,130.8z M125.5,70.8c-9.3,0-16.9,7.6-16.9,16.9c0,4.4,1.7,8.6,4.8,11.8l0.5,0.5l-0.5,0.5
                                            c-3.1,3.2-4.8,7.4-4.8,11.8c0,9.3,7.6,16.9,16.9,16.9s16.9-7.6,16.9-16.9c0-4.4-1.7-8.6-4.8-11.8l-0.5-0.5l0.5-0.5
                                            c3.1-3.2,4.8-7.4,4.8-11.8C142.4,78.4,134.8,70.8,125.5,70.8z" />
                                                </g>
                                                <g>
                                                    <rect x="82.8" y="82.1" class="st12" width="25.8" height="1.5" />
                                                </g>
                                                <g>
                                                    <rect x="82.8" y="117.9" class="st12" width="26.1" height="1.5" />
                                                </g>
                                                <g>
                                                    <rect x="142.4" y="82.1" class="st12" width="25.8" height="1.5" />
                                                </g>
                                                <g>
                                                    <rect x="142" y="117.9" class="st12" width="26.2" height="1.5" />
                                                </g>
                                            </g>
                                        </g>
                                    </g>
                                    <g id="Back">
                                    </g>
                                </svg>
                            </div>
                            <div class="back">
                                <svg version="1.1" id="cardback" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                    x="0px" y="0px" viewBox="0 0 750 471" style="enable-background:new 0 0 750 471;" xml:space="preserve">
                                    <g id="Front">
                                        <line class="st0" x1="35.3" y1="10.4" x2="36.7" y2="11" />
                                    </g>
                                    <g id="Back">
                                        <g id="Page-1_2_">
                                            <g id="amex_2_">
                                                <path id="Rectangle-1_2_" class="darkcolor greydark" d="M40,0h670c22.1,0,40,17.9,40,40v391c0,22.1-17.9,40-40,40H40c-22.1,0-40-17.9-40-40V40
                                        C0,17.9,17.9,0,40,0z" />
                                            </g>
                                        </g>
                                        <rect y="61.6" class="st2" width="750" height="78" />
                                        <g>
                                            <path class="st3" d="M701.1,249.1H48.9c-3.3,0-6-2.7-6-6v-52.5c0-3.3,2.7-6,6-6h652.1c3.3,0,6,2.7,6,6v52.5
                                    C707.1,246.4,704.4,249.1,701.1,249.1z" />
                                            <rect x="42.9" y="198.6" class="st4" width="664.1" height="10.5" />
                                            <rect x="42.9" y="224.5" class="st4" width="664.1" height="10.5" />
                                            <path class="st5" d="M701.1,184.6H618h-8h-10v64.5h10h8h83.1c3.3,0,6-2.7,6-6v-52.5C707.1,187.3,704.4,184.6,701.1,184.6z" />
                                        </g>
                                        <text transform="matrix(1 0 0 1 621.999 227.2734)" id="svgsecurity" class="st6 st7">985</text>
                                        <g class="st8">
                                            <text transform="matrix(1 0 0 1 518.083 280.0879)" class="st9 st6 st10">security code</text>
                                        </g>
                                        <rect x="58.1" y="378.6" class="st11" width="375.5" height="13.5" />
                                        <rect x="58.1" y="405.6" class="st11" width="421.7" height="13.5" />
                                        <text transform="matrix(1 0 0 1 59.5073 228.6099)" id="svgnameback" class="st12 st13">John Doe</text>
                                    </g>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Card Image Demo END -->
                <?php endif; ?>
            </div>
                
        </div>
        <div class="form-row form-row-special">
            <div class="your-order-total-container"><?php esc_html_e("Total Amount", "hello-elementor" ); ?>: <?php printf( WC()->cart->get_total() ); ?></div>
            <div class="form-label"></div>
            <div class="form-input form-submit-container">
                <button type="submit" class="ml_add_loading button allaround_card_details_submit" <?php echo WC()->cart->is_empty() ? 'disabled' : ''; ?>><?php esc_html_e("התקדם לנקודת הביקורת", "hello-elementor" ); ?></button>
            </div>
			<div class="form-message text-center"></div>
        </div>
    </form>
    <?php
}

function allaround_customer_form($is_disabled = false) {

    $current_user_id = ml_get_author_page_userid();

    $the_user = get_user_by( 'id', $current_user_id );
    $phone = ml_get_user_phone($current_user_id);
    $dummy_email = get_field('dummy_email', 'user_' . $current_user_id);
    $user_billing_info = get_field('user_billing_info', "user_{$current_user_id}");
    $invoice = get_field('invoice', "user_{$current_user_id}");
    $city = get_user_meta( $current_user_id, 'billing_city', true );
    $billing_address = get_user_meta( $current_user_id, 'billing_address_1', true );
    $first_name = get_user_meta($current_user_id, 'billing_first_name', true);
    $last_name = get_user_meta($current_user_id, 'billing_last_name', true);
    $user_display_name = trim("{$first_name} {$last_name}");
    ?>
    <form action="" id="customerDetails" class="allaround--card-form<?php echo $is_disabled === false ? ' hidden_form' : ''; ?>">
        <h3><?php esc_html_e("כתובת למשלוח", "hello-elementor" ); ?></h3>
        <div class="form-row flex-row">
            <div class="form-row">
                <div class="form-label"><?php esc_html_e("Name", "hello-elementor" ); ?></div>
                <div class="form-input">
                    <input type="text" id="userName" name="userName" placeholder="<?php esc_attr_e("required", "hello-elementor" ); ?>" value="<?php echo $user_display_name ? esc_attr($user_display_name) : '' ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-label"><?php esc_html_e("Invoice Name", "hello-elementor" ); ?></div>
                <div class="form-input">
                    <input type="text" id="userInvoiceName" name="userInvoiceName" placeholder="<?php esc_attr_e("required", "hello-elementor" ); ?>" value="<?php echo esc_attr( $invoice ); ?>">
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-label"><?php esc_html_e("Email", "hello-elementor" ); ?></div>
            <div class="form-input">
                <input type="text" id="userEmail" name="userEmail" placeholder="<?php esc_attr_e("required", "hello-elementor" ); ?>" value="<?php echo $dummy_email ? "" : esc_attr($the_user->user_email); ?>" required>
            </div>
        </div>
        <div class="form-row flex-row">
            <div class="form-row">
                <div class="form-label"><?php esc_html_e("Phone", "hello-elementor" ); ?></div>
                <div class="form-input">
                    <input type="text" id="userPhone" name="userPhone" placeholder="<?php esc_attr_e("required", "hello-elementor" ); ?>" value="<?php echo esc_attr( $phone ); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-label"><?php esc_html_e("City", "hello-elementor" ); ?></div>
                <div class="form-input">
                    <input type="text" id="userCity" name="userCity" placeholder="<?php esc_attr_e("required", "hello-elementor" ); ?>" value="<?php echo esc_attr( $city ); ?>" required>
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-label"><?php esc_html_e("Address", "hello-elementor" ); ?></div>
            <div class="form-input">
                <input type="text" id="userAdress" name="userAdress" placeholder="<?php esc_attr_e("required", "hello-elementor" ); ?>" value="<?php echo esc_attr( $billing_address ); ?>" required>
            </div>
        </div>
        <div class="form-row form-submit-row">
            <button type="submit" class="button alarnd--regular-button alt ml_add_loading ml_save_customer_info" disabled><?php esc_html_e( "Update", "hello-elementor" ); ?></button>
            <a href="#" class="ml_customer_info_edit_cancel"><?php esc_html_e("Return", "hello-elementor"); ?></a>
        </div>
        <div class="form-message"></div>
    </form>
    <?php
}

function add_address_phone_fields_to_user_form() {
    global $pagenow;
    // Check if we are on the Add New User page
    if ($pagenow === 'user-new.php') {

        // Output the billing address fields before the "Add New User" button
        echo '<table class="form-table" id="fieldset-billing">';
        $countryCode = ml_get_country_code();
        ?>
            <tr>
                <th><label for="xoo-ml-user-reg-phone">Mobile Number (Without Country Code)</label></th>
                <td>
                    <input type="hidden" name="xoo-ml-user-reg-phone-cc" id="xoo-ml-user-reg-phone-cc" value="<?php echo $countryCode; ?>">
                    <input type="text" name="xoo-ml-user-reg-phone" id="xoo-ml-user-reg-phone" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="billing_address_1">Address</label></th>
                <td>
                    <input type="text" name="billing_address_1" id="billing_address_1" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="billing_city">City</label></th>
                <td>
                    <input type="text" name="billing_city" id="billing_city" class="regular-text">
                </td>
            </tr>
        <?php
        echo '</table>';
    }
}
add_action('user_new_form', 'add_address_phone_fields_to_user_form');


function save_address_phone_fields($user_id) {
    
    if( function_exists( 'xoo_ml_users_table' ) ) {
        xoo_ml_users_table()->save_customer_meta_fields($user_id);
    }

    if (isset($_POST['billing_address_1'])) {
        update_user_meta($user_id, 'billing_address_1', sanitize_text_field($_POST['billing_address_1']));
    }
    if (isset($_POST['billing_city'])) {
        update_user_meta($user_id, 'billing_city', sanitize_text_field($_POST['billing_city']));
    }
}

add_action('user_register', 'save_address_phone_fields', 10, 1);
add_action('profile_update', 'save_address_phone_fields', 10, 1);


function ml_response($response) {
    $parts = explode('|', $response);
    $result = array();

    if( count($parts) > 1 ) {
        foreach ($parts as $part) {
            $pair = explode(':', $part);
            $key = trim($pair[0], '"');
            $value = trim($pair[1], '"');
            $result[$key] = $value;
        }
    }

    return $result;
}

/**
 * Get shipping info by using chosen method from cart
 *
 * @param string $type
 * @return string
 */
function ml_get_shipping_data($type = '') {
    $current_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
    if( ! is_array($current_shipping_method) ) {
        return '';
    }

    if( $type === 'method' ) {
        return reset($current_shipping_method);
    }

    $packages = WC()->shipping()->get_packages();

    // Loop through each package
    foreach ($packages as $package_key => $package) {
        // Get the shipping rates for the package
        $rates = $package['rates'];

        // Loop through each shipping rate
        foreach ($rates as $rate_key => $rate) {
            // Get the shipping cost for the rate
            if ($current_shipping_method[0] == $rate->id) {
                $shipping_cost = $rate->cost;
                $shipping_label = $rate->label;

                if( $type === 'cost' ) {
                    return $shipping_cost;
                }

                return $shipping_label;
            }
        }
    }
}

/**
 * Create WooCommerce order programmatically 
 *
 * @param array $data
 * @return int
 */
function ml_create_order($data) {

    global $woocommerce;
    $cart = $woocommerce->cart;
    $cart->calculate_totals();

    $applied_coupons = WC()->cart->get_applied_coupons();

    $user_id = isset( $data['user_id'] ) && ! empty( $data['user_id'] ) ? (int) $data['user_id'] : '';
    if( ! empty( $user_id ) ) {
        $current_user = get_user_by( 'ID', $user_id );
    }

    if( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
    }

    if( empty( $current_user) ) {
        error_log( "current user not found inside ml_create_order" );
        // throw new Exception( 'Current user not found' );
        return false;
    }

    $user_id = $current_user->ID;
    $user_login = $current_user->user_login;

    $products = $data['products'];
    $customerInfo = $data['customerInfo'];
    $cardNumber = isset( $data['cardNumber'] ) && ! empty( $data['cardNumber'] ) ? $data['cardNumber'] : '';
    $extraMeta = isset( $data['extraMeta'] ) ? $data['extraMeta'] : [];
    $response = isset( $data['response'] ) ? $data['response'] : [];
    $update = isset( $data['update'] ) ? true : false;
    $token_update = isset( $data['token_update'] ) ? true : false;
    $fullname = isset( $customerInfo['name'] ) ? $customerInfo['name'] : '';

    // error_log( print_r( $data, true ) );

    // Assuming you have received payment response and details
    $order = wc_create_order();

    $gallery_thumbs = [];

    // Loop through the products and add them to the order
    foreach ($products as $product) {
        $product_id = $product['product_id'];

        $_product = wc_get_product( $product_id );
        $image_id = $_product->get_image_id();

        $quantity = $product['quantity'];

        $color = isset( $product['color'] ) ? $product['color'] : ''; // Assuming 'color' is part of your product data
        $size = isset( $product['size'] ) ? $product['size'] : '';   // Assuming 'size' is part of your product data
        $price = isset( $product['price'] ) ? $product['price'] : '';
        $alarnd_color_key = isset( $product['alarnd_color_key'] ) ? $product['alarnd_color_key'] : '';
        $alarnd_custom_color = isset( $product['alarnd_custom_color'] ) ? $product['alarnd_custom_color'] : '';
        $alarnd_step_key = isset( $product['alarnd_step_key'] ) ? $product['alarnd_step_key'] : '';

        $extra_meta = [];
        if( ! empty( $color ) ) {
            $extra_meta['color'] = $color;
        }
        if( ! empty( $size ) ) {
            $extra_meta['size'] = $size;
        }
        if( ! empty( $price ) ) {
            $extra_meta['price'] = $price;

            $extra_meta['subtotal'] = $price*$quantity;
            $extra_meta['total'] = $price*$quantity;
        }

        $item_id = $order->add_product($_product, $quantity, $extra_meta);

        // Add color and size as order item meta
        if( ! empty( $color ) ) {
            wc_add_order_item_meta($item_id, __('Color', 'hello-elementor'), $color);
        }

        if( ! empty( $alarnd_custom_color ) ) {
            wc_add_order_item_meta($item_id, __('Color', 'hello-elementor'), $alarnd_custom_color);
        }

        if( ! empty( $size ) ) {
            wc_add_order_item_meta($item_id, __('Size', 'hello-elementor'), $size);
        }
        if( ! empty( $alarnd_color_key ) ) {
            $gallery_thumbs[$item_id] = $alarnd_color_key;
        }
    }

    // Set billing and shipping addresses
    $order->set_address($customerInfo, 'billing');
    $order->set_address($customerInfo, 'shipping');

    $order->set_customer_id( $user_id );

    // Set payment method (e.g., 'zcredit_checkout_payment' for zcredit)
    $order->set_payment_method('zcredit_checkout_payment');
    $order->set_payment_method_title('Z-Credit Payment');

    // Get the chosen shipping method
    $chosen_shipping_method = ml_get_shipping_data('method');

    // Set shipping method
    if (!empty($chosen_shipping_method)) {

        $shipping_cost = ml_get_shipping_data('cost');
        $shipping_title = ml_get_shipping_data();

        // Create a new shipping item
        $shipping_item = new WC_Order_Item_Shipping();
        $shipping_item->set_method_title($shipping_title); // Replace with the shipping method title
        $shipping_item->set_method_id($chosen_shipping_method);
        $shipping_item->set_total($shipping_cost);

        // Add the shipping item to the order
        $order->add_item($shipping_item);

        // Set the applied coupons to the order
        foreach ($applied_coupons as $coupon_code) {
            $order->apply_coupon($coupon_code);
        }

        // Recalculate totals and save the order
        $order->calculate_totals();
    }

    if( true === $update ) {
        if( ! empty( $response ) && isset( $response['referenceID'] ) ) {
            $order->add_order_note( __( 'Z-Credit Payment Complete.', 'woocommerce_zcredit' ) );
            $order->add_order_note( "Refence Number: #".$response['referenceID']." for Z-Credit" );
            $order->payment_complete();   
        }
    
        if( $customerInfo && ! empty( $customerInfo ) ) {
            $phoneNumber = ml_get_phone_no( $customerInfo['phone'] );
            $countryCode = ml_get_country_code();
            
            // WcooCommerce user field update
            update_user_meta_if_different($user_id, 'billing_address_1', $customerInfo['address_1']);
            update_user_meta_if_different($user_id, 'billing_phone', $customerInfo['phone']);
        
            update_user_meta_if_different($user_id, 'xoo_ml_phone_code', $countryCode);
            update_user_meta_if_different($user_id, 'xoo_ml_phone_no', $phoneNumber);
    
            // Email address
            update_user_email_if_different($user_id, $customerInfo['email']);
            
            // Display Name
            update_user_name_if_different($user_id, $fullname);
        }
    
        if( $extraMeta && ! empty( $extraMeta ) ) {
            if( isset( $extraMeta['invoice'] ) && ! empty( $extraMeta['invoice'] ) ) {
                update_acf_usermeta($user_id, 'invoice', $extraMeta['invoice']);
            }
            if( isset( $extraMeta['city'] ) && ! empty( $extraMeta['city'] ) ) {
                update_user_meta_if_different($user_id, 'billing_city', $extraMeta['city']);
            }
        }
        
        if( true === $token_update && ! empty( $response ) && isset( $response['referenceID'] ) ) {
            //TODO - update token and customer info if new or change input.

            if( isset( $response['token'] ) && ! empty( $response['token'] ) ) {
                update_post_meta( $order->get_id(), 'zc_payment_token', $response['token'] );
                update_post_meta( $order->get_id(), 'zc_transaction_id', $response['referenceID'] );
                update_acf_usermeta( $user_id, 'token', $response['token'] );
            }
    
            if( isset( $response['token'] ) && ! empty( $response['token'] ) ) {
                // ACF field update
                update_acf_usermeta( $user_id, 'token', $response['token'] );
            }
    
            if( ! empty( $cardNumber ) ) {
                $last_four_digit = ml_get_last_four_digit($cardNumber);
                $card_type = ml_get_card_type($cardNumber);
    
                $card_info = [];
                $card_info['last_4_digit'] = $last_four_digit;
                $card_info['card_type'] = $card_type;
    
                update_acf_usermeta( $user_id, 'card_info', $card_info);
            }
        }
    }

    $woocommerce->cart->calculate_totals();

    $order->calculate_totals();

    // Mark the order as paid (change this status to match your payment method)
    $order->set_status('wc-processing');
    
    // Save the order
    $order->save();

    $order_id = $order->get_id();

    if( ! empty( $gallery_thumbs ) ) {
        // Add custom thumbnail URL as post meta for the product
        update_post_meta($order_id, '_gallery_thumbs', $gallery_thumbs);
    }


    // Trigger emails Manually
    // WC()->mailer()->get_emails()['WC_Email_Customer_Processing_Order']->trigger( $order_id );
    // WC()->mailer()->get_emails()['WC_Email_New_Order']->trigger( $order_id );

    return $order_id;
}


// Hook to replace order item thumbnail with custom thumbnail URL
add_filter('woocommerce_admin_order_item_thumbnail', 'custom_order_item_thumbnail', 10, 3);

function custom_order_item_thumbnail($thumbnail, $item_id, $item) {

    // get order id
    $order_id = $item->get_order_id();
    // get order object
    $order = wc_get_order($order_id);
    // Get the product ID from the order item
    $product_id = $item->get_product_id();

    $customer_id = $order->get_customer_id();

    $gallery_thumbs = get_post_meta($item->get_order_id(), '_gallery_thumbs', true);


    $thumb_item = array(
        'user_id' => $customer_id
    );

    if( ! empty( $gallery_thumbs ) && isset( $gallery_thumbs[$item_id] ) ) {
        $thumb_item['alarnd_color_key'] = $gallery_thumbs[$item_id];
    }

    $wc_thumb = ml_get_cart_thumb($product_id, $thumb_item);

    error_log( print_r( $wc_thumb, true ) );

    if ($wc_thumb) {
        // Wrap the thumbnail with an anchor tag
        $thumbnail = '<a target="_blank" href="' . esc_url( $wc_thumb ) . '">' . $thumbnail . '</a>';
        
        // Replace the image source with the custom thumbnail URL
        $thumbnail = preg_replace('/src="([^"]*)"/', 'src="' . esc_url($wc_thumb) . '"', $thumbnail);
    }

    return $thumbnail;
}

function ministore_empty_cart_message($message) {
    if (WC()->cart->is_empty()) {
        $message = '<div class="custom-empty-cart-message">';
        $image_url = plugins_url('../assets/images/cart-large-minimalistic-svg.svg', __FILE__);
        $message .= '<img src="' . esc_url($image_url) . '" alt="Empty Cart Icon">';
        $message .= '<h3>' . esc_html__('העגלה ריקה', 'hello-elementor') . '</h3>';
        $message .= '</div>';
    }

    echo '<div class="cart-collaterals minstr-empt-cart-collatrl">';
    echo '<span class="shipping-title">' . esc_html__('משלוח', 'hello-elementor') . '</span>';

    if (WC()->cart->is_empty()) {
        // Get available shipping zones
        $shipping_zones = WC_Shipping_Zones::get_zones();

        foreach ($shipping_zones as $shipping_zone) {
            $shipping_zone_methods = $shipping_zone['shipping_methods'];

            echo '<ul id="shipping_method" class="woocommerce-shipping-methods">';

            $count = 0; // Counter to track the number of displayed shipping methods

            foreach ($shipping_zone_methods as $index => $shipping_method) {
                $input_id = 'shipping_method_' . $index . '_' . sanitize_title($shipping_method->id);
                $input_value = esc_attr($shipping_method->id);
                $input_name = 'shipping_method[0]';

                if ($count < 2) {
                    $selected = ($count === 0) ? 'checked="checked"' : '';
                    echo '<li>';
                    echo '<input type="radio" name="' . esc_attr($input_name) . '" data-index="' . esc_attr($index) . '" id="' . esc_attr($input_id) . '" value="' . esc_attr($input_value) . '" class="shipping_method" ' . $selected . '>';
                    echo '<label for="' . esc_attr($input_id) . '">';
                    echo esc_html($shipping_method->title);
                    echo '</label>';
                    echo '</li>';

                    $count++;
                }
            }

            echo '</ul>';
        }
    }

    do_action('woocommerce_cart_collaterals');
    echo '</div>';
    return $message;
}

add_filter('wc_empty_cart_message', 'ministore_empty_cart_message');

/**
 * Replace src and srcset from cart item image
 *
 * @param string $content
 * @param array $cart_item
 * @param string $cart_item_key
 * @return string|$content
 */
function ml_cart_item_thumbnail( $content, $cart_item, $cart_item_key ) {

    // error_log( print_r( $cart_item, true ) );

    $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
    $wc_thumb = ml_get_cart_thumb($product_id, $cart_item);

    // Normal image
    $matches = array();
    preg_match_all( '/<img[\s\r\n]+.*?>/is', $content, $matches );

    $search = array();
    $replace = array();

    $i = 0;
    foreach ( $matches[0] as $imgHTML ) {

        $i++;
        // replace the src and add the data-src attribute
        $replaceHTML = $imgHTML;

        if ( ! empty( $wc_thumb ) ) {
            // Replace src attribute
            $replaceHTML = preg_replace( '/ src=(["\'])(.*?)["\']/is', ' src="' . $wc_thumb . '"', $replaceHTML );

            // Replace srcset attribute
            $replaceHTML = preg_replace( '/ srcset=(["\'])(.*?)["\']/is', ' srcset="' . $wc_thumb . '"', $replaceHTML );
        }

        array_push( $search, $imgHTML );
        array_push( $replace, $replaceHTML );
    }

    if( ! empty( $replace ) ) {
        $content = str_replace( $search, $replace, $content );
    }

    return $content;
}
add_filter( 'woocommerce_cart_item_thumbnail', 'ml_cart_item_thumbnail', 3, 250 );

/**
 * Get generate cart thumbnail
 *
 * @param int $product_id
 * @return void
 */
function ml_get_cart_thumb($product_id, $cart_item) {

    // error_log( print_r( $cart_item, true ) );

    $current_user_id = ml_get_author_page_userid();

    $class = 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail'; // Default cart thumbnail class.

    $_product = wc_get_product( $product_id );
    $image_id = $_product->get_image_id();

    if( empty( $product_id ) || empty( $image_id ) ) {
        return '';
    }

    if( isset( $cart_item['user_id'] ) && ! empty( $cart_item['user_id'] ) ) {
        $current_user_id = $cart_item['user_id'];
    }

    $alarnd_color_key = '';
    
    if( isset( $cart_item['alarnd_color_key'] ) ) {
        $alarnd_color_key = $cart_item['alarnd_color_key'];
    }
    
    $gen_thumbnail = ml_get_wc_thumbnail_url( $image_id, $product_id, $current_user_id, $alarnd_color_key );
    error_log( "cart thumb $gen_thumbnail" );
    if( empty( $gen_thumbnail ) ) {
        return '';
    }

    // Output.
    return $gen_thumbnail;
}


function ml_get_wc_thumbnail_url( $attachment_id, $product_id, $user_id, $alarnd_color_key ) {

    $fullsize_path = get_attached_file( $attachment_id ); // Full path
    if( empty( $fullsize_path ) ) {
        return '';
    }    

    $filename_only = basename( get_attached_file( $attachment_id ) ); // Just the file name
    $filetype = wp_check_filetype($filename_only);
    $ext = $filetype['ext'];

    $upload_dir = wp_upload_dir();

    if( $alarnd_color_key !== '' ) {
        $colors = get_field( 'color', $product_id );
        $attachment_id = isset( $colors[$alarnd_color_key]['thumbnail']['ID'] ) ? $colors[$alarnd_color_key]['thumbnail']['ID'] : '';
        if( ! empty( $attachment_id ) ) {
            $gen_thumbnail = $upload_dir['baseurl'] . DIRECTORY_SEPARATOR. AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . 'wc_thumb_' . $product_id . '-' . $alarnd_color_key . '-' . $attachment_id . '.'.$ext;
            $gen_thumbnail = str_replace('\\', '/', $gen_thumbnail);
            $basedir_url = $upload_dir['basedir'] . DIRECTORY_SEPARATOR. AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . 'wc_thumb_' . $product_id . '-' . $alarnd_color_key . '-' . $attachment_id . '.'.$ext;

            if( file_exists( $basedir_url ) ) {
                return $gen_thumbnail;
            }

            $full_gen_thumbnail = $upload_dir['baseurl'] . DIRECTORY_SEPARATOR. AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . $product_id . '-' . $alarnd_color_key . '-' . $attachment_id . '.'.$ext;
            $full_gen_thumbnail = str_replace('\\', '/', $full_gen_thumbnail);
            $full_basedir_url = $upload_dir['basedir'] . DIRECTORY_SEPARATOR. AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . $product_id . '-' . $alarnd_color_key . '-' . $attachment_id . '.'.$ext;
            if( file_exists( $full_basedir_url ) ) {
                return $full_gen_thumbnail;
            }
        }
    }

    
    $gen_thumbnail = $upload_dir['baseurl'] . DIRECTORY_SEPARATOR . AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR  . $user_id . DIRECTORY_SEPARATOR . 'wc_thumb_' . $product_id . '.'.$ext;
    $gen_thumbnail = str_replace('\\', '/', $gen_thumbnail);
    $basedir_url = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . AlRNDCM_UPLOAD_FOLDER . DIRECTORY_SEPARATOR  . $user_id . DIRECTORY_SEPARATOR . 'wc_thumb_' . $product_id . '.'.$ext;

    if( file_exists( $basedir_url ) ) {
        return $gen_thumbnail;
    }

    return '';
}


function ml_get_phone_no($phone) {
    if( empty( $phone ) )
        return;

    $countryCode = ml_get_country_code();

    if(strpos($phone, $countryCode) !== false){
        $number = substr($phone, strlen($countryCode));
        return $number;
    }

    return $phone;
}

function ml_get_country_code() {
    if( ! function_exists( 'xoo_ml_helper' ) ) {
        return '';
    }

    $settings = xoo_ml_helper()->get_phone_option();

    $countryCode = $settings['r-default-country-code-type'] === 'geolocation' ? Xoo_Ml_Geolocation::get_phone_code() : $settings['r-default-country-code'];

    return $countryCode;
}

function ml_get_last_four_digit( $cardnumber ) {
    $lastFourCharacters = substr($cardnumber, -4);

    return $lastFourCharacters;
}

function ml_get_card_type($cardNumber) {
    // Define regular expressions and associated card types
    $patterns = array(
        '/^4\d{12}(\d{3})?$/' => 'Visa',
        '/^5[1-5]\d{14}$/' => 'MasterCard',
        '/^3[47]\d{13}$/' => 'American Express',
        '/^6(011|5\d{2})\d{12}$/' => 'Discover',
    );

    // Check the card number against each pattern and return the card type
    foreach ($patterns as $pattern => $type) {
        if (preg_match($pattern, $cardNumber)) {
            return $type;
        }
    }

    // If no match is found, return an unknown card type
    return 'Unknown';
}



function ml_discount_obj_valid($obj) {
    if( empty( $obj ) || ! is_array( $obj ) ) {
        return false;
    }

    $valid = false;
    
    foreach ($obj as $item) {
        if ($item['amount'] > 0) {
            $valid = true;
            break; // You can break the loop as soon as you find a non-zero value
        }
    }

    return $valid;
}

// Custom function to modify the price display
function ml_modify_price_html($price, $product) {

    $discount_steps = get_field( 'discount_steps', $product->get_id() );
    $custom_quanity_enable = get_field( 'enable_custom_quantity', $product->get_id() );
    $quantity_steps = get_field( 'quantity_steps', $product->get_id() );
    $regular_price = (int) get_post_meta($product->get_id(), '_regular_price', true);
    // error_log( print_r( $discount_steps, true ) );

    if( 
        ! empty( $custom_quanity_enable ) && 
        ! empty( $quantity_steps )
     ) {

        $the_last_qty_steps = end($quantity_steps);
        if( 
            empty( $regular_price ) ||
            ! isset( $the_last_qty_steps['amount'] ) ||
            empty( $the_last_qty_steps['amount'] )
        ) {
            return $price;
        }

        $min_price = $the_last_qty_steps['amount'];
        $max_price = $quantity_steps['0']['amount'];

        if ( $min_price && $max_price ) {

            $min_html = wc_price($min_price);
            $max_html = wc_price($max_price);

            if( $min_price < 1 ) {
                $min_html = wc_price($min_price, array('decimals' => 1));
            }
            
            if( $max_price < 1 ) {
                $max_html = wc_price($max_price, array('decimals' => 1));
            }

            return $min_html . ' - ' . $max_html;
        }

        return $price;
     }

    if( 
        empty( $discount_steps ) ||
        ! isset( $discount_steps[0] ) ||
        ! isset( $discount_steps[0]['amount'] ) ||
        ! ml_discount_obj_valid($discount_steps) ||
        empty( $regular_price ) 
    ) {
        return $price;
    }
    
    $the_last_steps = end($discount_steps);
    if( ! isset( $the_last_steps['amount'] ) ) {
        return $price;
    }

    $min_price = isset( $the_last_steps['amount'] ) ? (int) $the_last_steps['amount'] : '';

    // check if last key has any amount.
    // if not then return regular price
    if( empty( $min_price ) )
        return $price;

    $max_price = isset( $discount_steps[0]['amount'] ) ? (int) $discount_steps[0]['amount'] : '';
    if( 0 == $max_price || empty( $max_price ) || $max_price > $regular_price ) {
        $max_price = $regular_price;
    }

    if ( $min_price && $max_price ) {
        $min_html = wc_price($min_price);
        $max_html = wc_price($max_price);

        if( $min_price < 1 ) {
            $min_html = wc_price($min_price, array('decimals' => 1));
        }
        
        if( $max_price < 1 ) {
            $max_html = wc_price($max_price, array('decimals' => 1));
        }

        return $min_html . ' - ' . $max_html;
    }
    
    // For other products, use the default price display
    return $price;
}

add_filter('woocommerce_get_price_html', 'ml_modify_price_html', 10, 2);

function ml_get_current_list($url) {
    // Parse the URL to get the query string
    $query = parse_url($url, PHP_URL_QUERY);

    // Parse the query string to get the parameter value
    parse_str($query, $params);

    if (isset($params['list'])) {
        return (int) $params['list'];
    } else {
        return null; // Parameter not found in the URL
    }
}

function ml_customers_arr() {
    $customer_query = new WP_User_Query(
        array(
            'fields' => 'all',
            'role' => 'customer',
            'number' => 5000
        )
    );
    return $customer_query->get_results();
}

function acf_customers_list_choices_cb( $field ) {

    // reset choices
    $field['choices'] = array();

    $customers = ml_customers_arr();

    // Loop through the array and add to field 'choices'
    if( is_array($customers) && ! empty($customers) ) {
        foreach( $customers as $customer ) {
            $field['choices'][ $customer->ID ] = $customer->display_name;
        }
    }

    // return the field
    return $field;

}
add_filter( 'acf/load_field/name=customers_list', 'acf_customers_list_choices_cb' );

function update_acf_anyway($user_id, $meta_key, $new_value) {
    update_field($meta_key, $new_value, 'user_' . $user_id);
}

function ml_products_per_page() {
    $products_per_page = get_field('products_per_page', 'option');
    if( empty( $products_per_page ) ) {
        return 3;
    }
    return (int) $products_per_page;
}


/**
 * Get the orientation of an attachment (square or horizontal).
 *
 * @param int $attachment_id The ID of the attachment.
 * @return string The orientation of the attachment ('horizontal', or 'square').
 */
function ml_get_orientation($attachment_id) {
    // Get attachment metadata
    $attachment_metadata = wp_get_attachment_metadata($attachment_id);

    if ($attachment_metadata) {

        // Calculate the threshold for height to be less than 60% of width
        $heightThreshold = 0.6 * $attachment_metadata['width'];

        // Check if width and height are equal (square)
        if ($attachment_metadata['width'] === $attachment_metadata['height']) {
            return 'square';
        } else if ($attachment_metadata['height'] < $heightThreshold) {
            return 'horizontal';
        } else {
            return 'square';
        }
    }
    return 'square';
}

// Custom Function for getting coupon code responses
function customize_coupon_error_message( $err, $err_code, $coupon )
{
  $messages = array(
    'coupon_apply_error' => __('This coupon cannot be applied.'),
    'coupon_minimum_error' => __('You need to spend more to use this coupon.'),
    // Add more custom error messages here
  );

  $message = isset( $messages[$err_code] ) ? $messages[$err_code] : $err;

  // Display the customized error message
  echo '<div class="custom-error-message woocommerce-info">' . $message . '</div>';

  // Return an empty string to exclude the default error message
  return '';
}

add_filter( 'woocommerce_coupon_error', 'customize_coupon_error_message', 10, 3 );


function custom_remove_woocommerce_shipping_details() {
    $screen = get_current_screen();

    // Check if we are on the user edit page and the user has the necessary capabilities
    if ($screen->id === 'user-edit' && current_user_can('edit_user', get_current_user_id())) {
        ?>
        <script>
            jQuery(document).ready(function($) {
                // Hide the WooCommerce shipping details fields
                $('#fieldset-shipping').hide();
                $('.user-nickname-wrap').hide();
                $('.user-display-name-wrap').hide();
                $('.user-first-name-wrap').hide();
                $('.user-last-name-wrap').hide();
                $('.user-role-wrap').hide();
                $('#billing_company').closest('tr').hide();
                $('#billing_address_2').closest('tr').hide();
                $('#billing_postcode').closest('tr').hide();
                $('#billing_country').closest('tr').hide();
                $('#billing_state').closest('tr').hide();
                $('#billing_phone').closest('tr').hide();
                $('#billing_email').closest('tr').hide();
                $('.user-description-wrap').closest('table').hide();
                $('.user-admin-color-wrap').closest('table').hide();
                $('#password').closest('table').hide();
                $('#elementor_pro_notes_enable_permissions').closest('table').hide();
                $('#e-notes').hide();
                $('.user-url-wrap').hide();
                $('#application-passwords-section').hide();
                $('h2:contains("Customer shipping address")').hide();
                $('h2:contains("About the user")').hide();
                $('h2:contains("Personal Options")').hide();
                $('h2:contains("Account Management")').hide();
                $('h2:contains("Customer billing address")').hide();
                $('label[for="billing_address_1"]').text('Address');
                $('select[name="xoo-ml-user-reg-phone-cc"]').hide();
            });
        </script>
        <?php
    }
}
add_action('admin_footer', 'custom_remove_woocommerce_shipping_details');

// Add custom JavaScript to update the cart quantity when the quantity field changes
function custom_update_cart_quantity_script() {
    ?>
    <script>
        jQuery(document).ready(function ($) {
            // Listen for changes on the quantity input field
            $('div.woocommerce').on('change', 'input.qty', function () {
                // Trigger an event to update the cart
                $(document.body).trigger('update_cart');
            });
        });
    </script>
    <?php
}

add_action('wp_footer', 'custom_update_cart_quantity_script');


add_action('admin_footer', 'modify_user_notification_checkbox');
function modify_user_notification_checkbox() {
    $currentScreen = get_current_screen();
    if ('user' === $currentScreen->id) {
        ?>
        <script type="text/javascript">
            document.getElementById("send_user_notification").checked = false;
            jQuery('#send_user_notification').closest('tr').hide();
            jQuery('#url').closest('tr').hide();
            jQuery('.user-language-wrap').hide();
        </script>
        <?php
    }
}


function ml_get_filter_content( $current_user_id, $filter = '' ) {

    $id = empty( $filter ) || 'all' === $filter ? "filter_wrap-all" : "filter_wrap-$filter";
    $active_class = empty( $filter ) || 'all' === $filter ? "filter_wrap-active" : "";

     // Get selected product IDs for the user
     $selected_product_ids = ml_get_user_products($current_user_id);
        
     $filtered_product_ids = array();
     // Filter products by the selected category
     if( 'all' === $filter || empty( $filter ) ) {
         foreach ($selected_product_ids as $product) {
             $product_id = $product['value'];
             $terms = wp_get_post_terms($product_id, 'product_cat');
     
             $filtered_product_ids[] = $product;
         }
     }

     if( 'all' !== $filter && ! empty( $filter ) ) {
        foreach ($selected_product_ids as $product) {
            $product_id = $product['value'];
            $terms = wp_get_post_terms($product_id, 'product_cat');
    
            foreach ($terms as $term) {
                if ($term->term_id == $filter) {
                    $filtered_product_ids[] = $product;
                    break;
                }
            }
        }
    }

    echo '<div id="'.$id.'" class="woocommerce filter_wrap-item '.$id.' '.$active_class.'">';

        $items = $filtered_product_ids;
        $itemsPerPage = ml_products_per_page();
        $totalItems = count($items);
        $totalPages = ceil($totalItems / $itemsPerPage);
        $currentpage = isset($_GET['list']) ? (int)$_GET['list'] : 1;

        $start = ($currentpage - 1) * $itemsPerPage;
        $end = $start + $itemsPerPage;
        $itemsToDisplay = array_slice($items, $start, $itemsPerPage);
        $big = 999999999; // need an unlikely integer

        echo '<ul id="allaround_products_list-'.$filter.'" data-user_id="'.esc_attr( $current_user_id ).'" class="mini-store-product-list product-list-container products columns-3">';
        foreach ($itemsToDisplay as $prod_object) {
            if( ! isset( $prod_object['value'] ) || empty( $prod_object['value'] ) )
                continue;

            $product_id = $prod_object['value'];

            // check if post has thumbnail otherwise skip
            $product = wc_get_product($product_id);

            $group_enable = get_field( 'group_enable', $product->get_id() );
            $colors = get_field( 'color', $product->get_id() );
            $custom_quanity = get_field( 'enable_custom_quantity', $product->get_id() );
            $sizes = get_field( 'size', $product->get_id() );
            $pricing_description = get_field( 'pricing_description', $product->get_id() );
            $discount_steps = get_field( 'discount_steps', $product->get_id() );
            $discount_steps = ml_filter_disount_steps($discount_steps);

            $customQuantity_steps = get_field( 'quantity_steps', $product->get_id() );
            $customQuantity_steps = ml_filter_disount_steps($customQuantity_steps);

            $thumbnail = wp_get_attachment_image_src($product->get_image_id(), 'alarnd_main_thumbnail');
            if( ! $thumbnail )
                continue;

            $thumbnail = ml_get_thumbnail($thumbnail, $current_user_id, $product_id );

            if ($product) {
                $terms = wp_get_post_terms($product_id, 'product_cat');

                echo '<li class="product-item product ';
                
                foreach ($terms as $term) {
                    echo 'category-' . $term->term_id . ' ';
                }
                echo '" data-product_type="'.esc_attr($product->get_type()).'" data-product-id="' . esc_attr($product->get_id()) . '">';
                
                // Product Thumbnail
                echo '<div class="product-thumbnail">';
                echo '<img src="'.$thumbnail.'" loading="lazy"/>';
                echo '</div>';
                
                echo '<div class="product-item-details">';
                // Product Title
                if( ! empty( $discount_steps ) || ! empty( $pricing_description ) ) {
                    echo '<h3 class="product-title">' . esc_html($product->get_name()) . '</h3>';
                } else {
                    echo '<h3 class="product-title">' . esc_html($product->get_name()) . '</h3>';
                }

                if( ! empty( $colors ) && ! empty( $group_enable ) && empty( $custom_quanity ) ) : ?>
                <div class="alarnd--colors-wrapper">
                    <div class="alarnd--colors-wrap">
                        <?php foreach( $colors as $key => $color ) : ?>
                            <input type="radio" name="alarnd__color" id="alarnd__color_<?php echo esc_html( $color['title'] ); ?>" value="<?php echo esc_html( $color['title'] ); ?>">
                            <label for="alarnd__color_<?php echo esc_html( $color['title'] ); ?>" class="alarnd--single-color" data-key="<?php $key; ?>" data-name="<?php echo esc_html( $color['title'] ); ?>" style="background-color: <?php echo $color['color_hex_code']; ?>">
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else :?>
                    <div class="no_color_text">
                        <span>זמין בצבע אחד כבתמונה</span>
                    </div>
                <?php 
                endif;
                // Price

                echo '<p class="mini_productCard_price">' . $product->get_price_html() . '</p>';
                
                // Buttons
                echo '<div class="product-buttons">';
                if( ! empty( $discount_steps ) || ! empty( $pricing_description ) || ! empty( $customQuantity_steps ) ) {
                    echo '<a href="#alarnd__pricing_info-'. $product->get_id() .'" class="view-details-button alarnd_view_pricing_cb" data-product_id="'. $product->get_id() .'">לפרטים על המוצר</a>';
                } else {
                    echo '<span class="view_details_not_available"></span>';
                }
                echo '<button class="quick-view-button ml_add_loading ml_trigger_details button" data-product-id="' . esc_attr($product->get_id()) . '">'.esc_html( $product->single_add_to_cart_text() ).'</button>';
                echo '</div>';
                echo '</div>';

                if( ! empty( $discount_steps ) || ! empty( $pricing_description ) || ! empty( $customQuantity_steps ) ) : ?>
                    <div id="alarnd__pricing_info-<?php echo $product->get_id(); ?>" data-product_id="<?php echo $product->get_id(); ?>" class="mfp-hide white-popup-block alarnd--info-modal">
                        <div class="alarnd--modal-inner alarnd--modal-chart-info">
                            <h2><?php echo get_the_title( $product->get_id() ); ?></h2>

                            <div class="alarnd--pricing-wrapper-new">
                                <?php echo ml_gallery_carousels($product->get_id(), $current_user_id); ?>

                                <div class="pricingDescSteps">
                                <?php if( ! empty( $pricing_description ) ) : ?>
                                <div class="alarn--pricing-column alarn--pricing-column-desc">
                                    <?php echo allround_get_meta( $pricing_description ); ?>
                                </div>
                                <?php endif; ?>
                                <?php if( ! empty( $discount_steps ) && ! empty( $group_enable ) ) : ?>
                                <div class="alarn--pricing-column alarn--pricing-column-chart">
                                    <div class="alarn--price-chart">
                                        <h5>תמחור כמות</h5>
                                        <div class="alarnd--price-chart-price <?php echo count($discount_steps) > 4 ? 'alarnd--plus4item-box' : ''; ?>">
                                            <?php 
                                            $index = 0;
                                            foreach( $discount_steps as $step ) :
                                            $prev = ($index == 0) ? false : $discount_steps[$index-1];                            
                                            $qty = ml_get_price_range($step['quantity'], $step['amount'], $prev);

                                            ?>
                                            <div class="alarnd--price-chart-item">
                                                <span class="price_step_price"><?php echo $step['amount'] == 0 ? wc_price($product->get_regular_price(), array('decimals' => 0)) : wc_price($step['amount'], array('decimals' => 0)); ?></span>
                                                <span class="price_step_qty">כמות: <span><?php echo esc_html( $qty); ?></span></span>
                                            </div>
                                            <?php $index++; endforeach; ?>
                                        </div>
                                        
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if( ! empty( $customQuantity_steps ) && ! empty( $custom_quanity ) ) : ?>
                                <div class="alarn--pricing-column alarn--pricing-column-chart">
                                    <div class="alarn--price-chart">
                                        <h5>תמחור כמות</h5>
                                        <div class="alarnd--price-chart-price <?php echo count($customQuantity_steps) > 4 ? 'alarnd--plus4item-box' : ''; ?>">
                                            <?php 
                                            foreach ($customQuantity_steps as $key => $step) :

                                            $startRange = $step['quantity'];
                                            $endRange = isset($customQuantity_steps[$key + 1]) ? $customQuantity_steps[$key + 1]['quantity'] - 1 : null;
                                            
                                            $range_title = '';
                                            if ($endRange === null) {
                                                $range_title = "$startRange+";
                                            } elseif ($startRange == $endRange) {
                                                $range_title = "$startRange";
                                            } else {
                                                $range_title = "$startRange-$endRange";
                                            }

                                            ?>
                                            <div class="alarnd--price-chart-item yyy">
                                                <span class="price_step_price">
                                                    <?php
                                                    $price = $step['amount'] == 0 ? wc_price($product->get_regular_price()) : wc_price($step['amount']);
                                                    echo preg_replace('/\.00/', '', $price); // Remove trailing .00
                                                    ?>
                                                </span>
                                                <span class="price_step_qty">כמות: <span><?php echo esc_html( $range_title); ?></span></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="modal-bottom-btn">
                                    <button type="button" class="alarnd_trigger_details_modal ml_add_loading" data-product_id="<?php echo $product->get_id(); ?>"><?php esc_html_e( 'הוסיפו לעגלה', 'hello-elementor' ); ?></button>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif;
                
                echo '</li>'; // End product-item
            }
        }
        echo '</ul>';

        if( $totalItems >= $itemsPerPage ) :
        ?>
        <div class="allaround--loadmore-wrap">
            <button type="button" class="alarnd--regular-button alarnd--loadmore-trigger ml_loading ml_add_loading button" data-page_num="1"><?php esc_html_e("Load More", "hello-elementor"); ?></button>
            <button type="button" class="alarnd--regular-button alarnd--reveal-more ml_add_loading button" ><?php esc_html_e("מוצרים נוספים...", "hello-elementor"); ?></button>
        </div>
        <?php
        endif;
        echo '</div>'; // End mini-store-product-list woocommerce
}


/**
 * Function to handle the quantity callback for Woocommerce cart items.
 *
 * @param datatype $product_quantity description
 * @param datatype $cart_item_key description
 * @param datatype $cart_item description
 * @return datatype
 */
function woocommerce_cart_item_quantity_cb( $product_quantity, $cart_item_key, $cart_item ) {
    if( ! isset( $cart_item['product_id'] ) )
        return $product_quantity;

    $product_id = $cart_item['product_id'];

    $product = wc_get_product( $product_id );

    $group_enable = get_field( 'group_enable', $product->get_id() );
    $custom_quanity = get_field( 'enable_custom_quantity', $product->get_id() );


    $steps = get_field( 'quantity_steps', $product->get_id() );

    $qty = $product->get_min_purchase_quantity();

    if( ! empty( $custom_quanity ) && ! empty( $steps ) && isset( $steps[0]['quantity'] ) ) {
        $qty = $steps[0]['quantity'];
    }

    if ( 
        "simple" === $product->get_type() && 
        ! empty( $custom_quanity ) && 
        ! empty( $steps ) )
    {
        $product_quantity = woocommerce_quantity_input(
            array(
                'input_name'   => "cart[{$cart_item_key}][qty]",
                'input_value'  => $cart_item['quantity'],
                'max_value'    => '50000',
                'min_value'    => $qty,
                'product_name' => $product->get_name(),
            ),
            $product,
            false
        );

        // error_log( print_r( $qty, true ) );
        // error_log( print_r( $product_quantity, true ) );
    }

    return $product_quantity;

}
add_filter( 'woocommerce_cart_item_quantity', 'woocommerce_cart_item_quantity_cb', 10, 3 );

/**
 * Filters the quantity input classes.
 *
 * @param array $classes The input classes.
 * @param WC_Product $product Product instance.
 * @return array The modified input classes.
 */
function woocommerce_quantity_input_classes_cb( $classes, $product ) {

    $group_enable = get_field( 'group_enable', $product->get_id() );
    $custom_quanity = get_field( 'enable_custom_quantity', $product->get_id() );

    $steps = get_field( 'quantity_steps', $product->get_id() );

    if ( 
        "simple" === $product->get_type() && 
        ! empty( $custom_quanity ) && 
        ! empty( $steps ) )
    {
        $classes[] = 'allaround_check_min_qty';
    }

    return $classes;

}
add_filter( 'woocommerce_quantity_input_classes', 'woocommerce_quantity_input_classes_cb', 10, 2 );


/**
 * Snippet Name:	WooCommerce Show Coupon Code Used In Emails
 * Snippet Author:	ecommercehints.com
 */

add_action( 'woocommerce_email_after_order_table', 'allaround_show_coupons_used_in_emails', 10, 4 );
function allaround_show_coupons_used_in_emails( $order, $sent_to_admin, $plain_text, $email ) {
    if (count( $order->get_coupons() ) > 0 ) {
        $html = '<div class="used-coupons">
        <h2>Used coupons<h2>
        <table class="td" cellspacing="0" cellpadding="6" border="1"><tr>
        <th>Coupon Code</th>
        <th>Coupon Amount</th>
        </tr>';

        foreach( $order->get_coupons() as $item ){
            $coupon_code   = $item->get_code();
            $coupon = new WC_Coupon($coupon_code);
			$discount_type = $coupon->get_discount_type();
			$coupon_amount = $coupon->get_amount();

			if ($discount_type == 'percent') {
				$output = $coupon_amount . "%";
			} else {
				$output = wc_price($coupon_amount);
			}

            $html .= '<tr>
                <td>' . strtoupper($coupon_code) . '</td>
                <td>' . $output . '</td>
            </tr>';
        }
        $html .= '</table><br></div>';

        $css = '<style>
            .used-coupons table {
				width: 100%;
				font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;
                color: #737373;
				border: 1px solid #e4e4e4;
				margin-bottom:8px;
			}
            .used-coupons table th, table.tracking-info td {
			text-align: left;
			border-top-width: 4px;
            color: #737373;
			border: 1px solid #e4e4e4;
			padding: 12px;
			}
            .used-coupons table td {
			text-align: left;
			border-top-width: 4px;
			color: #737373;
			border: 1px solid #e4e4e4;
			padding: 12px;
			}
        </style>';

        echo $css . $html;
    }
}

function ml_format_timestamps($timestamps_array) {
    $formatted_array = array();

    if( empty( $timestamps_array ) )
        return $timestamps_array;

    foreach ($timestamps_array as $key => $timestamps) {
        $start_time_formatted = date('Y-m-d H:i:s', $timestamps['start_time']);
        $end_time_formatted = date('Y-m-d H:i:s', $timestamps['end_time']);
        $generated_total = isset( $timestamps['generated'] ) ? $timestamps['generated'] : 0;

        $formatted_array[$key] = array(
            'start_time' => $start_time_formatted,
            'end_time' => $end_time_formatted,
            'generated' => $generated_total
        );
    }

    return $formatted_array;
}