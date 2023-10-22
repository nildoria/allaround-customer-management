<?php

class ML_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_get_item_selector', array( $this, 'get_item_selector' ) );
        add_action( 'wp_ajax_nopriv_get_item_selector', array( $this, 'get_item_selector' ) );

        add_action( 'wp_ajax_confirm_payout', array( $this, 'confirm_payout' ) );
        add_action( 'wp_ajax_nopriv_confirm_payout', array( $this, 'confirm_payout' ) );

        add_action( 'wp_ajax_ml_add_to_cart', array( $this, 'ml_add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_ml_add_to_cart', array( $this, 'ml_add_to_cart' ) );

        add_action('wp_ajax_get_woocommerce_cart', array($this, 'get_woocommerce_cart_ajax'));
        add_action('wp_ajax_nopriv_get_woocommerce_cart', array($this, 'get_woocommerce_cart_ajax'));

        add_action('wp_ajax_add_variation_to_cart', array($this, 'add_variation_to_cart') );
        add_action('wp_ajax_nopriv_add_variation_to_cart', array($this, 'add_variation_to_cart') );

        add_action('wp_ajax_address_update', array($this, 'address_update') );
        add_action('wp_ajax_nopriv_address_update', array($this, 'address_update') );

        add_action('wp_ajax_alarnd_create_order', array( $this, 'alarnd_create_order' ) );
        add_action('wp_ajax_nopriv_alarnd_create_order', array( $this, 'alarnd_create_order' ) );
        
        add_action('wp_ajax_ml_send_card', array( $this, 'ml_send_card' ) );
        add_action('wp_ajax_nopriv_ml_send_card', array( $this, 'ml_send_card' ) );
    }

    public function confirm_payout() {
        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );
        
        try {
            $customerDetails = isset( $_POST['customerDetails'] ) && ! empty( $_POST['customerDetails'] ) ? $_POST['customerDetails'] : '';
            // error_log( print_r( $customerDetails, true ) );
            if (empty($customerDetails)) {
                throw new Exception('Customer Details field is empty.');
            } elseif( ! isset($customerDetails['userName']) || empty($customerDetails['userName']) ) {
                throw new Exception('Customer Name field is empty.');
            } elseif( ! isset($customerDetails['userPhone']) || empty($customerDetails['userPhone']) ) {
                throw new Exception('Customer Phone field is empty.');
            } elseif( ! isset($customerDetails['userEmail']) || empty($customerDetails['userEmail']) ) {
                throw new Exception('Customer Email field is empty.');
            } elseif( ! isset($customerDetails['userAdress']) || empty($customerDetails['userAdress']) ) {
                throw new Exception('Customer Adress field is empty.');
            }
    
            ?>
            <div class="white-popup-block alarnd--payout-modal mfp-hide alarnd--info-modal">
                <div class="popup_product_details">
                    <div class="alarnd--success-wrap">
                        <div class="alarn--popup-thankyou">
                            <img src="<?php echo AlRNDCM_URL; ?>assets/images/tick.png" alt="">
                            <h2>תודה שהוספת את "<?php the_title(); ?>" להזמנה שלך!</h2>
                            <h3>דגם יישלח עם שאר המוצרים המותאמים אישית שהזמנת.</h3>
                            <p>אתה עדיין יכול להוסיף את שאר <br>המוצרים בעמוד זה וליהנות ממבצעים מעולים :)</p>
                            <a href="<?php echo esc_url( home_url('/') ); ?>" class="alarnd--submit-btn alarnd--continue-btn">המשך בקניות</a>
                        </div>
                    </div>

                    <div class="alarnd--failed-wrap">
                        <div class="alarn--popup-thankyou">
                            <img src="<?php echo AlRNDCM_URL; ?>assets/images/failed.png" alt="">
                            <h2><?php esc_html_e("Order Didn't go through", "allaroundminilng"); ?></h2>
                            <h3>לצערנו העסקה לא אושרה.</h3>
                            <p>נטפל בבעיה וניצור איתך קשר בהקדם :)</p>
                            <a href="<?php echo esc_url( home_url('/') ); ?>" class="alarnd--submit-btn alarnd--continue-btn"><?php esc_html_e('Continue Shopping', 'allaroundminilng'); ?></a>
                        </div>
                    </div>

                    <div class="alarnd--popup-confirmation">
                        <div class="alarnd--popup-middle">
                            <h5><?php esc_html_e( 'Thanks for adding it to your order!', 'allaroundminilng' ); ?></h5>
                            <div class="alarnd--popup-inline">
                                <h5><?php printf( '%s %s', esc_html__( 'Please confirm by clicking on the button below and we’ll charge your card by', 'allaroundminilng' ), WC()->cart->get_total() ); ?></h5>
                            </div>
                            <span class="alrnd--create-order alarnd--submit-btn ml_add_loading button"><?php esc_html_e( 'Confirm', 'allaroundminilng' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        } catch (Exception $e) {
            // Catch the error and send an error response
            echo '<p>' .$e->getMessage() . '</p>';
        }
        ?>
        
        <?php
        wp_die();
    }

    /**
     * Add item to order from thankyou page
     *
     * @return void
     */
    public function alarnd_create_order() {
        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

        if ( WC()->cart->get_cart_contents_count() == 0 ) {
            wp_send_json_error( array(
                "message" => "Cart is empty."
            ) );
            wp_die();
        }
        
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array(
                "message" => "User need to logged in."
            ) );
            wp_die();
        }

        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;

        $token = get_field('token', "user_{$current_user_id}");

        $card_info = get_field('card_info', "user_{$current_user_id}");
        $four_digit = isset( $card_info['last_4_digit'] ) && ! empty( $card_info['last_4_digit'] ) ? $card_info['last_4_digit'] : '';

        if( empty( $token ) ) {
            wp_send_json_error( array(
                "message" => "Token value empty."
            ) );
            wp_die();
        }

        $customerDetails = isset( $_POST['customerDetails'] ) && ! empty( $_POST['customerDetails'] ) ? $_POST['customerDetails'] : [];

        $userName = isset( $customerDetails['userName'] ) && ! empty( $customerDetails['userName'] ) ? sanitize_text_field( $customerDetails['userName'] ) : '';
        $userPhone = isset( $customerDetails['userPhone'] ) && ! empty( $customerDetails['userPhone'] ) ? sanitize_text_field( $customerDetails['userPhone'] ) : '';
        $userAdress = isset( $customerDetails['userAdress'] ) && ! empty( $customerDetails['userAdress'] ) ? sanitize_text_field( $customerDetails['userAdress'] ) : '';
        $userEmail = isset( $customerDetails['userEmail'] ) && ! empty( $customerDetails['userEmail'] ) ? sanitize_text_field( $customerDetails['userEmail'] ) : '';

        if( 
            empty( $userName ) ||
            empty( $userPhone ) ||
            empty( $userAdress ) ||
            empty( $userEmail ) 
        ) {
            wp_send_json_error( array(
                "message" => esc_html__("Required field are empty. Please fill all the field.", "allaroundminilng")
            ) );
            wp_die();
        }
        
        if( 
            ! is_email( $userEmail )
        ) {
            wp_send_json_error( array(
                "message" => esc_html__("Please enter a valid email address.", "allaroundminilng")
            ) );
            wp_die();
        }

        $cart_filter_data = [];
        $product_list = [];
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $_product = wc_get_product( $cart_item['product_id'] );

            $cart_filter_data[$cart_item_key]["title"] = $_product->get_title();
            $cart_filter_data[$cart_item_key]["price"] = (int) $cart_item['data']->get_price();
            if( isset( $cart_item['alarnd_size'] ) && ! empty( $cart_item['alarnd_size'] ) ) {
                $cart_filter_data[$cart_item_key]["size"] = $cart_item['alarnd_size'];
            }
            if( isset( $cart_item['alarnd_color'] ) && ! empty( $cart_item['alarnd_color'] ) ) {
                $cart_filter_data[$cart_item_key]["color"] = $cart_item['alarnd_color'];
            }
            if( isset( $cart_item['quantity'] ) && ! empty( $cart_item['quantity'] ) ) {
                $cart_filter_data[$cart_item_key]["quantity"] = $cart_item['quantity'];
                $cart_filter_data[$cart_item_key]["total_price"] = (int) $cart_item['quantity'] * (int) $cart_item['data']->get_price();
            }

            
            if( $_product->is_type( 'variable' ) ) {

            }

            $product_list[] = array(
                "product_id" => $cart_item['product_id'],
                "quantity" => $cart_item['quantity']
            );
        }

        // send request to api
        $api_url  = apply_filters( 'allaround_order_api_url', '' );

        $body = array(
            'username' => $userName,
            'email' => $userEmail,
            'phone' => $userPhone,
            'address' => $userAdress,
            'token' => $token,
            'cardNum' => $four_digit,
            'price' => (int) WC()->cart->get_cart_contents_total(),
            'items' => $cart_filter_data
        );
        $body = apply_filters( 'allaround_order_api_body', $body, $current_user_id );

        $args = array(
            'method'      => 'POST',
            'timeout'     => 15,
            'sslverify'   => false,
            'headers'     => array(
                'Content-Type'  => 'application/json',
            ),
            'body'        => json_encode($body),
        );
        $args = apply_filters( 'allaround_order_api_args', $args, $current_user_id );

        $request = wp_remote_post( esc_url( $api_url ), $args );

        error_log( print_r( $request, true ) );

        // retrieve reponse body
        $message = wp_remote_retrieve_body( $request );

        // decode response into array
        $response_obj = ml_response($message);

        error_log( print_r( $response_obj, true ) );
        
        // order data
        $first_name = empty( $current_user->first_name ) && empty( $current_user->last_name ) ? $userName : $current_user->first_name;
        $last_name = empty( $current_user->first_name ) && empty( $current_user->last_name ) ? '' : $current_user->last_name;
        $company = get_user_meta( $current_user_id, 'billing_company', true );
        $city = get_user_meta( $current_user_id, 'billing_city', true );
        $postcode = get_user_meta( $current_user_id, 'billing_postcode', true );
        $state = get_user_meta( $current_user_id, 'billing_state', true );
        $country = get_user_meta( $current_user_id, 'billing_country', true );
        $country = empty( $country ) ? "IL" : $country;

        $customerInfo = array(
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'name'       => $userName,
            'company'    => $company,
            'email'      => $userEmail,
            'phone'      => $userPhone,
            'address_1'  => $userAdress,
            'city'       => $city,
            'state'      => $state,
            'postcode'   => $postcode,
            'country'    => $country
        );

        $order_data = array(
            "products" => $product_list,
            "customerInfo" => $customerInfo,
            "cardNumber" => '',
            "response" => $response_obj,
            "update" => true
        );
        
        if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) == 200 && $message !== "Accepted" ) {	
            
            // Clear the cart
            WC()->cart->empty_cart();
            // Return fragments
            WC_AJAX::get_refreshed_fragments();

            $order_id = ml_create_order($order_data);

            wp_send_json_success( array(
                "message" => "Successfully products added to order #$order_id"
            ) );

            wp_die();
        }

        $error_message = "Something went wrong";
        if( is_wp_error( $request ) ) {
            $error_message = $request->get_error_message();
        }

        // error_log( print_r( $error_message, true ) );
        wp_send_json_error( array(
            "body" => $body,
            "message" => $error_message
        ) );

        wp_die();
    }
    
    /**
     * Send Card Details and Cart Detais
     *
     * @return void
     */
    public function ml_send_card() {
        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

        if ( WC()->cart->get_cart_contents_count() == 0 ) {
            wp_send_json_error( array(
                "message" => "Cart is empty."
            ) );
            wp_die();
        }
        
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array(
                "message" => "User need to logged in."
            ) );
            wp_die();
        }

        $cardholderName = isset( $_POST['cardholderName'] ) && ! empty( $_POST['cardholderName'] ) ? sanitize_text_field( $_POST['cardholderName'] ) : '';
        $cardholderPhone = isset( $_POST['cardholderPhone'] ) && ! empty( $_POST['cardholderPhone'] ) ? sanitize_text_field( $_POST['cardholderPhone'] ) : '';
        $cardholderAdress = isset( $_POST['cardholderAdress'] ) && ! empty( $_POST['cardholderAdress'] ) ? sanitize_text_field( $_POST['cardholderAdress'] ) : '';
        $cardholderEmail = isset( $_POST['cardholderEmail'] ) && ! empty( $_POST['cardholderEmail'] ) ? sanitize_text_field( $_POST['cardholderEmail'] ) : '';
        $cardNumber = isset( $_POST['cardNumber'] ) && ! empty( $_POST['cardNumber'] ) ? sanitize_text_field( $_POST['cardNumber'] ) : '';
        $expirationDate = isset( $_POST['expirationDate'] ) && ! empty( $_POST['expirationDate'] ) ? sanitize_text_field( $_POST['expirationDate'] ) : '';
        $cvvCode = isset( $_POST['cvvCode'] ) && ! empty( $_POST['cvvCode'] ) ? sanitize_text_field( $_POST['cvvCode'] ) : '';
        
        $cardNumber = str_replace(' ', '', $cardNumber);

        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;

        if( 
            empty( $cardholderName ) ||
            empty( $cardholderPhone ) ||
            empty( $cardholderAdress ) ||
            empty( $cardholderEmail ) ||
            empty( $cardNumber ) ||
            empty( $expirationDate ) ||
            empty( $cvvCode )
        ) {
            wp_send_json_error( array(
                "message" => esc_html__("Required field are empty. Please fill all the field.", "allaroundminilng")
            ) );
            wp_die();
        }
        
        if( 
            ! is_email( $cardholderEmail )
        ) {
            wp_send_json_error( array(
                "message" => esc_html__("Please enter a valid email address.", "allaroundminilng")
            ) );
            wp_die();
        }

        $expirationDate = str_replace("/", '', $expirationDate);

        $cart_filter_data = [];
        $product_list = [];
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $_product = wc_get_product( $cart_item['product_id'] );

            $cart_filter_data[$cart_item_key]["title"] = $_product->get_title();
            $cart_filter_data[$cart_item_key]["price"] = (int) $cart_item['data']->get_price();
            if( isset( $cart_item['alarnd_size'] ) && ! empty( $cart_item['alarnd_size'] ) ) {
                $cart_filter_data[$cart_item_key]["size"] = $cart_item['alarnd_size'];
            }
            if( isset( $cart_item['alarnd_color'] ) && ! empty( $cart_item['alarnd_color'] ) ) {
                $cart_filter_data[$cart_item_key]["color"] = $cart_item['alarnd_color'];
            }
            if( isset( $cart_item['quantity'] ) && ! empty( $cart_item['quantity'] ) ) {
                $cart_filter_data[$cart_item_key]["quantity"] = $cart_item['quantity'];
                $cart_filter_data[$cart_item_key]["total_price"] = (int) $cart_item['quantity'] * (int) $cart_item['data']->get_price();
            }

            if( $_product->is_type( 'variable' ) ) {

            }

            $product_list[] = array(
                "product_id" => $cart_item['product_id'],
                "quantity" => $cart_item['quantity']
            );
        }

        // send request to api
        $api_url  = apply_filters( 'allaround_card_url', 'https://hook.eu1.make.com/80wvx4qyzxkegv4n1y2ys736dz92t6u6' );

        $body = array(
            'cardholderName' => $cardholderName,
            'cardholderPhone' => $cardholderPhone,
            'cardholderAdress' => $cardholderAdress,
            'cardholderEmail' => $cardholderEmail,
            'cardNumber' => $cardNumber,
            'expirationDate' => $expirationDate,
            'cvvCode' => $cvvCode,
            'price' => (int) WC()->cart->get_cart_contents_total(),
            'items' => $cart_filter_data
        );
        $body = apply_filters( 'allaround_card_api_body', $body, $current_user_id );

        $args = array(
            'method'      => 'POST',
            'timeout'     => 15,
            'sslverify'   => false,
            'headers'     => array(
                'Content-Type'  => 'application/json',
            ),
            'body'        => json_encode($body),
        );
        $args = apply_filters( 'allaround_card_api_args', $args, $current_user_id );

        // send request to make.com
        $request = wp_remote_post( esc_url( $api_url ), $args );
        
        error_log( print_r( $request, true ) );

        // retrieve reponse body
        $message = wp_remote_retrieve_body( $request );

        // decode response into array
        $response_obj = ml_response($message);
        
        error_log( print_r( $response_obj, true ) );
        
        // order data
        $first_name = empty( $current_user->first_name ) && empty( $current_user->last_name ) ? $cardholderName : $current_user->first_name;
        $last_name = empty( $current_user->first_name ) && empty( $current_user->last_name ) ? '' : $current_user->last_name;
        $company = get_user_meta( $current_user_id, 'billing_company', true );
        $city = get_user_meta( $current_user_id, 'billing_city', true );
        $postcode = get_user_meta( $current_user_id, 'billing_postcode', true );
        $state = get_user_meta( $current_user_id, 'billing_state', true );
        $country = get_user_meta( $current_user_id, 'billing_country', true );
        $country = empty( $country ) ? "IL" : $country;

        $customerInfo = array(
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'name'       => $cardholderName,
            'company'    => $company,
            'email'      => $cardholderEmail,
            'phone'      => $cardholderPhone,
            'address_1'  => $cardholderAdress,
            'city'       => $city,
            'state'      => $state,
            'postcode'   => $postcode,
            'country'    => $country
        );

        $order_data = array(
            "products" => $product_list,
            "customerInfo" => $customerInfo,
            "cardNumber" => $cardNumber,
            "response" => $response_obj,
            "update" => true
        );

        if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) == 200 && $message !== "Accepted" ) {	
            
            // Clear the cart
            WC()->cart->empty_cart();
            // Return fragments
            WC_AJAX::get_refreshed_fragments();

            $order_id = ml_create_order($order_data);

            wp_send_json_success( array(
                "message" => "Successfully products added to order #$order_id"
            ) );

            wp_die();
        }
        
        $order_id = ml_create_order($order_data);

        if( "Accepted" === $message ) {
            $message = "Unable to reach the api server, order #$order_id";
        }

        $error_message = "Something went wrong";
        if( is_wp_error( $request ) ) {
            $error_message = $request->get_error_message();
        }

        // error_log( print_r( $error_message, true ) );
        wp_send_json_error( array(
            "body" => $body,
            "message" => $message,
            "response_obj" => $response_obj,
            "error_message" => $error_message
        ) );

        wp_die();
    }

    public function address_update() {
        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

        $address = isset( $_POST['address'] ) && ! empty( $_POST['address'] ) ? sanitize_textarea_field( $_POST['address'] ) : '';

        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;

        update_field('user_billing_info', $address, "user_{$current_user_id}");

        echo allround_get_meta( $address );

        wp_die();
    }
    

    function add_variation_to_cart() {

        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

        $product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['product_id'] ) );
        $quantity          = empty( $_POST['quantity'] ) ? 1 : wc_stock_amount( $_POST['quantity'] );

        $variation_id      = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : '';
        $variations         = ! empty( $_POST['variation'] ) ? (array) $_POST['variation'] : '';

        $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );

        if ( $passed_validation && WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations ) ) {

            do_action( 'woocommerce_ajax_added_to_cart', $product_id );

            // Return fragments
            WC_AJAX::get_refreshed_fragments();

        } else {

            // If there was an error adding to the cart, redirect to the product page to show any errors
            $data = array(
                'error' => true,
                'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id )
            );

            wp_send_json( $data );

        }

        die();
    }

    function get_woocommerce_cart_ajax() {
        // Get the updated cart content using the [cart] shortcode
        echo do_shortcode('[woocommerce_cart]');
        die();
    }

    public function ml_add_to_cart() {
        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) && ! empty( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : '';

        $product = wc_get_product( $product_id );

        $ml_type = isset( $_POST['ml_type'] ) && ! empty( $_POST['ml_type'] ) ? sanitize_text_field( $_POST['ml_type'] ) : '';
        $group_enable = get_field( 'group_enable', $product->get_id() );
        $custom_quanity = get_field( 'enable_custom_quantity', $product->get_id() );

        $colors = get_field( 'color', $product->get_id() );

        $alarnd__color = (isset( $_POST['alarnd__color'] ) && ! empty( $_POST['alarnd__color'] )) ? sanitize_text_field( $_POST['alarnd__color'] ) : '';
        $alarnd__sizes = (isset( $_POST['alarnd__size'] ) && ! empty( $_POST['alarnd__size'] )) ? $_POST['alarnd__size'] : '';
        $alarnd__color_qty = (isset( $_POST['alarnd__color_qty'] ) && ! empty( $_POST['alarnd__color_qty'] )) ? $_POST['alarnd__color_qty'] : '';
        $get_total_qtys = ml_get_total_qty($alarnd__color_qty);


        $data = $_POST;

        if( 
            $ml_type === 'quantity' &&
            "simple" === $product->get_type()
        ) {

            $quantity = isset( $_POST['quantity'] ) && ! empty( $_POST['quantity'] ) ? intval( $_POST['quantity'] ) : '';

            $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );


            if ( $passed_validation && WC()->cart->add_to_cart( $product_id, $quantity ) ) {
                do_action( 'woocommerce_ajax_added_to_cart', $product_id );

                // Return fragments
                WC_AJAX::get_refreshed_fragments();
            } {
                wp_send_json( array(
                    "success" => true,
                    "message" => "Something wen't wrong when trying to add product #$product_id"
                ) );
            }

        } elseif( 
            $ml_type === 'group' &&
            'simple' === $product->get_type()
         ) {

            $this->group_add_to_cart($product, $data);

            do_action( 'woocommerce_ajax_added_to_cart', $product_id );

            // Return fragments
            WC_AJAX::get_refreshed_fragments();
        }

        wp_die();

        die();
    }

    public function group_add_to_cart( $product, $data ) {
        
        $colors = get_field( 'color', $product->get_id() );
        $product_id = $product->get_id();

        $alarnd__color = (isset( $_POST['alarnd__color'] ) && ! empty( $_POST['alarnd__color'] )) ? sanitize_text_field( $_POST['alarnd__color'] ) : '';
        $alarnd__sizes = (isset( $_POST['alarnd__size'] ) && ! empty( $_POST['alarnd__size'] )) ? $_POST['alarnd__size'] : '';
        $alarnd__color_qty = (isset( $_POST['alarnd__color_qty'] ) && ! empty( $_POST['alarnd__color_qty'] )) ? $_POST['alarnd__color_qty'] : '';
        $get_total_qtys = ml_get_total_qty($alarnd__color_qty);

        // error_log( "get_total_qtys & alarnd__color_qty" );
        // error_log( print_r($get_total_qtys, true) );
        // error_log( print_r($alarnd__color_qty, true) );

        $alarnd__group_id = (isset( $_POST['alarnd__group_id'] ) && ! empty( $_POST['alarnd__group_id'] )) ? $_POST['alarnd__group_id'] : '';

        $group_enable = get_field( 'group_enable', $product->get_id() );
        $custom_quanity = get_field( 'enable_custom_quantity', $product->get_id() );
        $colors = get_field( 'color', $product->get_id() );
        $sizes = get_field( 'size', $product->get_id() );

        $cart_content = WC()->cart->cart_contents;
        
        $old_qty_total = 0;

        if( $old_qty_total > 0 ) {
            $get_total_qtys = $get_total_qtys + $old_qty_total;
        }

        if( 
            'simple' === $product->get_type() && 
            ! empty( $group_enable ) && 
            ! empty( $alarnd__color_qty )
        ) {

            $same_color_exists = [];
            foreach( $alarnd__color_qty as $color_key => $item ) {
                foreach( (array) $item as $i_size => $i_qty ) {
                    if( empty( $i_qty ) ) {
                        continue;
                    }

                    // check if product_id already exists in cart with color_hex_code & size
                    // then do not add to the cart but add quantity that already cart item
                    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                        if ( 
                            $cart_item['product_id'] === $product_id &&
                            isset( $cart_item['alarnd_color_hex'] ) &&
                            isset( $cart_item['alarnd_size'] ) &&
                            $cart_item['alarnd_color_hex'] === $colors[$color_key]['color_hex_code'] &&
                            $cart_item['alarnd_size'] === $i_size
                        ) {
                            WC()->cart->cart_contents[$cart_item_key]['quantity'] += $i_qty;
                            WC()->cart->cart_contents[$cart_item_key]['alarnd_quantity'] += $i_qty;
                            continue;
                       }
                    }
                
                    $cart_item_meta = array();
                    $cart_item_meta['alarnd_color'] = $colors[$color_key]['title'];
                    $cart_item_meta['alarnd_color_hex'] = $colors[$color_key]['color_hex_code'];
                    $cart_item_meta['alarnd_size'] = $i_size;
                    $cart_item_meta['alarnd_group_qty'] = $get_total_qtys;
                    $cart_item_meta['alarnd_quantity'] = $i_qty;
                    $cart_item_meta['alarnd_group_id'] = $alarnd__group_id;

                    // error_log( print_r( $cart_item_meta, true ) );
                    WC()->cart->add_to_cart( $product->get_id(), (int) $i_qty, '', '', $cart_item_meta );
                }
            }
            
        }


    }
    
    /**
     * Get product select options
     *
     * @return void
     */
    public function get_item_selector() {

        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) && ! empty( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : '';

        if( empty( $product_id ) ) {
            wp_die('Product_id is empty');
        }

        $product = wc_get_product( $product_id );

        $group_enable = get_field( 'group_enable', $product->get_id() );
        $custom_quanity = get_field( 'enable_custom_quantity', $product->get_id() );

        if ( "simple" === $product->get_type() && ! empty( $custom_quanity ) ) {
            $this->get_quantity_product_meta( $product );
        } elseif( "simple" === $product->get_type() && ! empty( $group_enable ) ) {
            $this->get_group_product_meta( $product );
        } else {
            $this->get_other_product_cart( $product );
        }

        wp_die();
    }

    public function get_other_product_cart($product) {
        ?>
        <div id="ml--product_id-<?php echo $product->get_id(); ?>" class="white-popup-block alarnd--variable-modal mfp-hide alarnd--info-modal">
            <div class="alarnd--modal-inner alarnd--modal-chart-info">
                <h2><?php echo get_the_title( $product->get_id() ); ?></h2>

                <?php  
                wp( 'p=' . $product->get_id() . '&post_type=product' );
                wc_get_template( 'quick-view.php', array(), '', AlRNDCM_PATH . 'templates/' ); ?>
            </div>
        </div>
        <?php
    }

    public function get_group_product_meta( $product ) {
    
        $group_enable = get_field( 'group_enable', $product->get_id() );

        $colors = get_field( 'color', $product->get_id() );

        $discount_steps = get_field( 'discount_steps', $product->get_id() );
        $adult_sizes = get_field('adult_sizes', 'option', false);
        $adult_sizes = ml_filter_string_to_array( $adult_sizes );
        $child_sizes = get_field('child_sizes', 'option', false);
        $child_sizes = ml_filter_string_to_array( $child_sizes );
        $first_line_keyword = get_field( 'first_line_keyword', $product->get_id() );
        $second_line_keyword = get_field( 'second_line_keyword', $product->get_id() );

        $all_sizes = array_merge( $child_sizes, $adult_sizes );

        $selected_omit_sizes = get_field( 'omit_sizes_from_chart', $product->get_id() );

        $discount_steps = ml_filter_disount_steps($discount_steps);

        $json_data = array(
            "regular_price" => $product->get_regular_price(),
            "data" => $discount_steps
        );

        $product_cart_id = WC()->cart->generate_cart_id( $product->get_id() );
        $in_cart = WC()->cart->find_product_in_cart( $product_cart_id );

        $in_cart = '';
        if( in_array( $product->get_id(), array_column( WC()->cart->get_cart(), 'product_id' ) ) ) {
            $in_cart = ' is_already_in_cart';
        }

        $uniqid = uniqid('alrnd');

        ?>

        <?php if( ! empty( $group_enable ) ) : ?>
        <div id="ml--product_id-<?php echo $product->get_id(); ?>" data-product_id="<?php echo $product->get_id(); ?>" class="white-popup-block alarnd--slect-opt-modal mfp-hide alarnd--info-modal<?php echo $in_cart; ?>">
            <div class="alarnd--modal-inner alarnd--modal-chart-info">
                <h2><?php echo get_the_title( $product->get_id() ); ?></h2>
                    
                <form class="modal-cart" action="" data-settings='<?php echo wp_json_encode( $json_data ); ?>' enctype='multipart/form-data'>

                    <div class="alarnd--select-options-cart-wrap">
                        <div class="alarnd--select-options">
                            
                            <div class="alarnd--select-opt-wrapper">
                                <div class="alarnd--select-opt-header">
                                    <?php foreach( $all_sizes as $size ) : ?>
                                        <?php if (!ml_is_omit($size, $selected_omit_sizes)) : ?>
                                        <span><?php echo esc_html( $size ); ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>

                                <div class="alarnd--select-qty-body">
                                <?php foreach( $colors as $key => $color ) : ?>
                                    <div class="alarn--opt-single-row">
                                        <?php foreach( $all_sizes as $size ) :
                                        $disabled = '';
                                        if( ! empty( $color['omit_sizes'] ) && ml_is_omit($size, $color['omit_sizes'] ) ) {
                                                $disabled = 'disabled="disabled"'; 
                                        } ?>
                                        <?php if (!ml_is_omit($size, $selected_omit_sizes)) : ?>
                                        <div class="tshirt-qty-input-field">
                                            <input style="box-shadow: 0px 0px 0px 1px <?php echo $color['color_hex_code']; ?>;" type="text" class="three-digit-input" placeholder="" pattern="^[0-9]*$" autocomplete="off" name="alarnd__color_qty[<?php echo $key; ?>][<?php echo $size; ?>]" <?php echo $disabled; ?>>
                                            <span class="alarnd--limit-tooltip">Can't order more than 999</span>
                                        </div>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                        <div class="alarnd--opt-color">
                                            <span style="background-color: <?php echo $color['color_hex_code']; ?>"><?php echo $color['title']; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alarnd--next-target-message">
                        <h6><?php printf( '%1$s <span class="ml_next_target"></span> %2$s %3$s %4$s', __( "Add", "allaroundminilng" ), __( "more items to reduce your cost to", "allaroundminilng" ), wc_price(0, array('decimals' => 0)), __( "per item", "allaroundminilng" ) ); ?></h6>
                    </div>
                    
                    <div class="alarnd--limit-message">
                        <h6><?php esc_html_e("Can't order more than 999", "allaroundminilng"); ?></h6>
                    </div>

                    <div class="alarnd--price-show-wrap">
                        <div class="alarnd--single-cart-row alarnd--single-cart-price">
                            
                        <?php
                        echo '<a href="#" class="alarnd_view_pricing_cb_button" data-product_id="'. $product->get_id() .'">כמות, מחיר ומבחר</a>';
                        ?>
                            <div class="alarnd--price-by-shirt">
                                <p class="alarnd--group-price"><?php echo wc_price($product->get_regular_price(), array('decimals' => 0)); ?> / <?php echo $first_line_keyword; ?></p>
                                <p><?php echo esc_html( $second_line_keyword ); ?>: <span class="alarnd__total_qty"><?php esc_html_e( '0', 'allaroundminilng' ); ?></span></p>
                                <span class="alarnd--total-price">סה"כ: <?php echo wc_price($product->get_regular_price(), array('decimals' => 0)); ?></span>
                            </div>
                            <button type="submit" name="add-to-cart"value="<?php echo esc_attr( $product->get_id() ); ?>" disabled="disabled" class="single_add_to_cart_button button alt ml_add_loading ml_add_to_cart_trigger"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>
                        </div>
                        <div class="alanrd--product-added-message"><?php esc_html_e( 'Added to Cart', 'allaroundminilng' ); ?></div>
                        <input type="hidden" name="ml_type" value="group">
                        <input type="hidden" name="alarnd__group_id" value="<?php echo $uniqid; ?>">
                    </div>

                </form>
            </div>
        </div>
        <?php endif;
    }

    public function get_quantity_product_meta( $product ) {

        $group_enable = get_field( 'group_enable', $product->get_id() );
        $saving_info = get_field( 'saving_info', $product->get_id() );
        $colors = get_field( 'colors', $product->get_id() );
        $colors_title = get_field( 'title_for_colors', $product->get_id() );
        $the_color_title = ! empty( $colors_title ) ? $colors_title : esc_html__('Select a Color', 'allaroundminilng');
        $custom_quanity = get_field( 'enable_custom_quantity', $product->get_id() );

        $steps = get_field( 'quantity_steps', $product->get_id() );
        $last_step = array_key_last( $steps );
        if( ! empty( $steps ) && ! empty( $custom_quanity ) ) :

        $qty = $product->get_min_purchase_quantity();

        if( ! empty( $custom_quanity ) && ! empty( $steps ) && isset( $steps[0]['quantity'] ) ) {
            $qty = $steps[0]['quantity'];
        }

        ?>

        <div id="ml--product_id-<?php echo $product->get_id(); ?>" class="white-popup-block alarnd--quantity-modal mfp-hide alarnd--info-modal">
            <div class="alarnd--modal-inner alarnd--modal-chart-info">
                <h2><?php echo get_the_title( $product->get_id() ); ?></h2>
                    
                <form class="modal-cart" action="" enctype='multipart/form-data'>
            
                <div class="alarnd--cart-inner">
                    <?php
                    if( ! empty( $colors ) ) : ?>
                    <div class="alarnd--single-cart-row">
                        <span><?php echo esc_html( $the_color_title ); ?></span>
                        <?php
                        foreach( $colors as $key => $item ) : ?>
                        <div class="alarnd--custom-qtys-wrap">
                            <div class="alarnd--single-variable">
                                <span class="alarnd--single-var-info">
                                    <input type="radio" id="custom_color-<?php echo $key; ?>" name="custom_color" value="<?php echo esc_attr( $item['color'] ); ?>" <?php echo 0 === $key ? 'checked="checked"' : ''; ?>>
                                    <label for="custom_color-<?php echo $key; ?>"><?php echo esc_html( $item['color'] ); ?></label>
                                </span>
                                <span class="woocommerce-Price-amount amount"></span>
                                <span class="alarnd--single-saving"></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="alarnd--single-cart-row" data-reqular-price="<?php echo $product->get_regular_price(); ?>">
                        <span><?php esc_html_e('Select a Quantity', 'allaroundminilng'); ?></span>
                        <?php $the_price = isset( $steps[$last_step]['amount'] ) ? $steps[$last_step]['amount'] : $product->get_regular_price(); ?>
                        <div class="alarnd--custom-qtys-wrap alarnd--single-custom-qty alarnd--single-var-labelonly">
                            <div class="alarnd--single-variable alarnd--hide-price" data-min="<?php echo esc_attr( $steps[0]['quantity'] ); ?>" data-price="<?php echo esc_attr( $the_price ); ?>">
                                <span class="alarnd--single-var-info">
                                    <input type="radio" name="cutom_quantity" id="cutom_quantity_special-custom" value="<?php echo esc_attr( $the_price ); ?>">
                                    <label for="cutom_quantity_special-custom"><?php esc_html_e( 'Custom Quantity', 'allaroundminilng' ); ?></label>
                                </span>
                                <?php echo wc_price( 0, array('decimals' => 0)); ?>
                                <span class="alarnd--single-saving"><span class="alarnd__cqty_amount"><?php echo esc_html( $steps[$last_step]['amount'] ); ?></span> <?php echo esc_html( $saving_info ); ?></span>
                            </div>
                        </div>
                        <?php if( ! empty( $steps ) ) :
                        foreach( $steps as $key => $step ) :
                        $item_price = ! empty( $step['amount'] ) ? $step['amount'] : $product->get_regular_price();
                        $price = (int) $step['quantity'] * floatval( $item_price );
                        $hide = isset( $step['hide'] ) && ! empty( $step['hide'] ) ? true : false;
                        ?>
                        <div class="alarnd--custom-qtys-wrap<?php echo true === $hide ? ' alarnd--hide-qty' : ''; ?>" data-qty="<?php echo esc_attr( $step['quantity'] ); ?>" data-price="<?php echo esc_attr( $item_price ); ?>">
                            <div class="alarnd--single-variable">
                                <span class="alarnd--single-var-info">
                                    <input type="radio" id="cutom_quantity-<?php echo $key; ?>" name="cutom_quantity" value="<?php echo $key; ?>" <?php echo 0 === $key ? 'checked="checked"' : ''; ?>>
                                    <label for="cutom_quantity-<?php echo $key; ?>"><?php echo esc_html( $step['quantity'] ); ?></label>
                                </span>
                                <?php echo wc_price( (int) $price, array('decimals' => 0)); ?>
                                <span class="alarnd--single-saving"><?php echo esc_html( $item_price ); ?> <?php echo esc_html( $saving_info ); ?></span>
                            </div>
                        </div>
                        <?php endforeach; endif; 
                        ?>
                    </div>
                </div>

            
            <?php
            woocommerce_quantity_input(
                array(
                    'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
                    'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
                    'input_value' => $qty, // WPCS: CSRF ok, input var ok.
                )
            );
            ?>
            <div class="alarnd--single-button-wrap">
                <input type="hidden" name="ml_type" value="quantity">
                <button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt ml_add_loading ml_quantity_product_addtocart"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>
            </div>
            <div class="alanrd--product-added-message"><?php esc_html_e( 'Added to Cart', 'allaroundminilng' ); ?></div>
            </form>
           </div>
        </div>
        <?php
        endif;
    }
}

new ML_Ajax();