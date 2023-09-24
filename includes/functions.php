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
			return home_url();
		}
	} else {
		return $redirect_to;
	}
}

add_filter( 'login_redirect', 'ml_login_redirect', 10, 3 );


// Redirect customers to the homepage after login
function custom_login_redirect($redirect, $user) {

    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
		//check for admins
		if ( in_array( 'administrator', $user->roles ) ) {
			// redirect them to the default place
			return admin_url();
		} else {
			return home_url();
		}
	} else {
		return $redirect;
	}
    
    return $redirect; // For other user roles, return the default redirect
}
add_filter('woocommerce_login_redirect', 'custom_login_redirect', 10, 2);


function ml_logout_shortcode() {
    return '<a href="' . wp_logout_url(home_url()) . '">' . esc_html__( 'Logout', 'hello-elementor' ) . '</a>';
}
add_shortcode('ml_logout', 'ml_logout_shortcode');

class ML_Custom_Menu_Walker extends Walker_Nav_Menu {
    function start_el(&$output, $item, $depth = 0, $args = NULL, $id = 0) {
        if ($item->title == 'Logout') {
            $logout_url = home_url('my-account');
            $title = esc_html__("Login", "hello-elementor");
            if( is_user_logged_in() ) {
                $logout_url =  esc_url(add_query_arg('custom_logout', 'logout', home_url('/custom-logout/')));;
                $title = $item->title;
            }

            $output .= '<li><a href="' . esc_url($logout_url) . '">' . esc_html($title) . '</a></li>';
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




function ml_custom_menu_args($args) {
    $args['walker'] = new ML_Custom_Menu_Walker();
    return $args;
}
add_filter('wp_nav_menu_args', 'ml_custom_menu_args');


function ml_custom_logout_redirect() {
    wp_redirect(home_url('my-account')); // Redirect to the homepage
    exit();
}
add_action('wp_logout', 'ml_custom_logout_redirect');


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
    
    if( ! is_user_logged_in() && ( is_home() || is_front_page() ) ) {
        wp_redirect( home_url('my-account') );
        exit;
    }

    if( is_author() && ! is_user_logged_in() ) {
        $current_author = get_query_var('author_name');
        $current_user = get_user_by('login', $current_author); 
        if( $current_user && isset( $current_user->ID ) ) {
            $current_user_id = $current_user->ID;
            $token = get_field('token', "user_{$current_user_id}");
            if( ! empty( $token ) ) {
                wp_redirect( home_url('my-account') );
                exit;
            }
        }    
    }
}

add_action('template_redirect', 'ml_redirect_to_home');



// Ensure cart contents update when products are added to the cart via AJAX (place the following in functions.php)
add_filter( 'woocommerce_add_to_cart_fragments', 'woocommerce_header_add_to_cart_fragment' );

function woocommerce_header_add_to_cart_fragment( $fragments ) {
	ob_start();

    echo '<div class="alarnd--cart-wrapper-inner alarnd--full-width">';
        echo '<h2>העגלה שלך</h2>';
         echo do_shortcode('[woocommerce_cart]');
    echo '</div>';

	$fragments['div.alarnd--cart-wrapper-inner'] = ob_get_clean();
	
	return $fragments;
}

add_filter( 'woocommerce_add_to_cart_fragments', 'woocommerce_header_add_to_cart_fragment_checkout' );

function woocommerce_header_add_to_cart_fragment_checkout( $fragments ) {
	ob_start();

    echo '<div class="alarnd-checkout-wrap-inner">';
         echo do_shortcode('[woocommerce_checkout]');
    echo '</div>';
	
	$fragments['div.alarnd-checkout-wrap-inner'] = ob_get_clean();
	
	return $fragments;
}

add_filter( 'woocommerce_add_to_cart_fragments', 'woocommerce_add_to_cart_fragment_token' );

function woocommerce_add_to_cart_fragment_token( $fragments ) {
    $current_user = wp_get_current_user();
    $current_user_id = $current_user->ID;
	ob_start();

    echo alarnd_single_checkout($current_user_id);
	
	$fragments['div.alarnd--payout-main'] = ob_get_clean();
	
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
add_filter( 'acf/load_field/name=default_products', 'acf_select_products_choices_cb' );

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

function alarnd_single_checkout($user_id = false) {

    $current_user = wp_get_current_user();
    $current_user_id = $current_user->ID;

    if( ! empty( $user_id ) ) {
        $current_user_id = $user_id;
    }
    
    $token = get_field('token', "user_{$current_user_id}");
    $phone = get_field('phone', "user_{$current_user_id}");

    $user_billing_info = get_field('user_billing_info', "user_{$current_user_id}");
    $card_info = get_field('card_info', "user_{$current_user_id}");

    $four_digit = isset( $card_info['last_4_digit'] ) && ! empty( $card_info['last_4_digit'] ) ? $card_info['last_4_digit'] : '';
    $card_logo = isset( $card_info['card_type'] ) && ! empty( $card_info['card_type'] ) ? strtolower($card_info['card_type']) : 'mastercard';
    $card_logo_path = AlRNDCM_URL . "assets/images/$card_logo.png";
    ?>
    <div class="alarnd--payout-main">
        <?php if( ! WC()->cart->is_empty() ) : ?>
        <div class="alanrd--single-payout-wrap">
            <div class="alarnd--payout-col">
                <h2>פרטי משלוח</h2>
                <h3><?php echo $current_user->display_name; ?></h3>

                <div class="alarnd--user-address">
                    <div class="alarnd--user-address-wrap">
                        <?php echo allround_get_meta( $user_billing_info ); ?>
                    </div>
                    <textarea name="user_billing_info" class="user_billing_info_edit" cols="30" rows="5"><?php echo ( $user_billing_info ); ?></textarea>
                    <span class="alarnd--user_address_edit">שינוי</span>
                </div>
            </div>

            <div class="alarnd--payout-col">
                <h2>פרטי תשלום</h2>

                <div class="alarnd--payout-options">
                    <div class="alarnd-payout-choose">
                        <div class="alarnd--single-payout">
                            <label>
                                <input type="radio" name="alarnd_payout" value="tokenizer" checked="checked">
                                <img src="<?php echo esc_url($card_logo_path); ?>" alt="">
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
        </div>

        <div class="alarnd--single-payout-submit">
            <button type="button" class="alarnd--regular-button alarnd--payout-trigger ml_add_loading button">התקדם לנקודת הביקורת</button>
        </div>
        <?php endif; ?>
    </div>
    <?php
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
    $gen_thumbnail = $upload_dir['baseurl'] . '/'. AlRNDCM_UPLOAD_FOLDER . '/' . $user_id . '/resized_' . $product_id . '.'.$ext;
    if( is_image_url_exists( $gen_thumbnail ) ) {
        return $gen_thumbnail;
    }
    $full_gen_thumbnail = $upload_dir['baseurl'] . '/'. AlRNDCM_UPLOAD_FOLDER . '/' . $user_id . '/' . $product_id . '.'.$ext;
    if( is_image_url_exists( $full_gen_thumbnail ) ) {
        return $full_gen_thumbnail;
    }

    return $thumbnail[0];
}

function ml_get_gallery_thumbnail($key, $attachment_id, $user_id, $product_id, $full = false) {

    $thumbnail = wp_get_attachment_image_src($attachment_id, 'alarnd_main_thumbnail');
    $filetype = wp_check_filetype($thumbnail[0]);
    $ext = $filetype['ext'];

    $upload_dir = wp_upload_dir();
    $gen_thumbnail = $upload_dir['baseurl'] . '/'. AlRNDCM_UPLOAD_FOLDER . '/' . $user_id . '/resized_' . $product_id . '-' . $key . '-' . $attachment_id . '.'.$ext;
    if( false === $full && is_image_url_exists( $gen_thumbnail ) ) {
        // error_log( "gen_thumbnail $gen_thumbnail" );
        return $gen_thumbnail;
    }
    $full_gen_thumbnail = $upload_dir['baseurl'] . '/'. AlRNDCM_UPLOAD_FOLDER . '/' . $user_id . '/' . $product_id . '-' . $key . '-' . $attachment_id . '.'.$ext;
    if( true === $full && is_image_url_exists( $full_gen_thumbnail ) ) {
        // error_log( "full_gen_thumbnail $full_gen_thumbnail" );
        return $full_gen_thumbnail;
    }
    // error_log( "thumbnail $thumbnail[0], userId $user_id, productId $product_id, key => $key" );
    // error_log( "gen_thumbnail url $gen_thumbnail" );
    return $thumbnail[0];
}

function ml_gallery_carousels( $product_id, $user_id ) {
    // error_log( $product_id );
    $galleries = get_gallery_thumbs( $product_id );
    // echo '<pre>';
    // print_r( $galleries );
    // echo '</pre>';
    if( ! empty( $galleries ) ) {
        echo ' <div class="woocommerce-product-gallery alarn--pricing-column">';
            echo '<div class="allaround--slick-carousel">';
                foreach ($galleries as $key => $gallery) {
                    $thumbnail = ml_get_gallery_thumbnail($key, $gallery['attachment_id'], $user_id, $product_id);
                    $full_thumbnail = ml_get_gallery_thumbnail($key, $gallery['attachment_id'], $user_id, $product_id, true);
                    // $gallery_thumb = 
                    echo '<div><img src="' . esc_url( $thumbnail ) . '" class="gallery-item" data-mfp-src="' . esc_url( $full_thumbnail ) . '"/></div>';
                }
            echo '</div>';
            echo '<ol class="mlCustomDots">';
            foreach ($galleries as $key => $gallery) {
                echo '<li><a href="#" data-slide="'.$key.'" style="background-color: '.$gallery['color'].'"></a></li>';
            }
            echo '</ol>';
            echo '<div class="mlHiddenGallery">';
            foreach ($galleries as $key => $gallery) {
                $full_thumbnail = ml_get_gallery_thumbnail($key, $gallery['attachment_id'], $user_id, $product_id, true);
                echo '<a class="mlGallerySingle" href="'.$full_thumbnail.'" data-title="'.$gallery['title'].'"></a>';
            }
            echo '</div>';
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

            update_field('order_token', $z_order, 'user_' . $customer_id);
            update_acf_usermeta( $user_id, 'zc_payment_token', $zc_response );
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





function ml_get_user_products( $user_id ) {
    $default_products = get_field('default_products', 'option');
    $selected_product_ids = get_field('selected_products', "user_{$user_id}");

    if( empty( $default_products ) && empty( $selected_product_ids ) )
        return [];

    // Initialize an associative array to store unique values
    $uniqueValues = [];

    // Merge default_products into the results array while skipping duplicates
    $results = [];
    if( ! empty( $default_products ) ) {
        foreach ($default_products as $item) {
            if (!isset($uniqueValues[$item['value']]) && hasValidThumbnail($item['value'])) {
                $results[] = $item;
                $uniqueValues[$item['value']] = true;
            }
        }
    }

    // Merge selected_product_ids into the results array while skipping duplicates
    if( ! empty( $selected_product_ids ) ) {
        foreach ($selected_product_ids as $item) {
            if (!isset($uniqueValues[$item['value']]) && hasValidThumbnail($item['value'])) {
                $results[] = $item;
                $uniqueValues[$item['value']] = true;
            }
        }
    }
    
    return $results;
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
    add_image_size('alarnd_main_thumbnail', 1000, 1000, false);
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
                $galleries[] = array(
                    'type' => isColorLightOrDark($color['color_hex_code']),
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
        return 'light';
    } else {
        return 'dark';
    }
}


function ml_debug_test() {
   

    $galleries = get_color_thumbnails( 3321 );

    echo '<pre>';
    print_r( $galleries );
    echo '</pre>';
}
// add_action( 'init', 'ml_debug_test' );


// Redirect Admin to Dashboard if Already Logged in
function redirect_admin_to_dashboard() {
    if (is_front_page() && is_user_logged_in() && current_user_can('administrator')) {
        wp_redirect(admin_url());
        exit;
    }
}

add_action('template_redirect', 'redirect_admin_to_dashboard');

