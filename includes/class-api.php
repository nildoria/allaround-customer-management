<?php

class ACM_API
{

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_endpoints'));
    }

    /**
     * Call this method to get singleton
     *
     * @return singleton instance of ACM_API
     */
    public static function instance()
    {

        static $instance = null;
        if (is_null($instance)) {
            $instance = new ACM_API();
        }

        return $instance;
    }

    public function register_endpoints()
    {
        
        register_rest_route(
            'flash-sale/v1',
            '/get-payout-status',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'get_payout_status'),
                'permission_callback' => function () {
                    return true;
                }
            )
        );

        // Register new route for checking user by email
        register_rest_route(
            'mini-sites/v1',
            '/check-user-by-email',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'check_user_by_email'),
                'permission_callback' => function () {
                    return true;
                }
            )
        );
        
    }
    
    public function get_payout_status(WP_REST_Request $request) {

        $params = $request->get_params();

        // check if $params, SessionId exists and is not empty
        if (!isset($params) || empty($params) || !isset($params['SessionId']) || empty($params['SessionId'])
        ) {
            return new WP_REST_Response('Order creation failed.', 200);
        }

        $session_id = $params['SessionId'];

        // Generate a unique key (like session ID or user ID)
        $transient_unique_key = 'order_data_' . $session_id;
            
        $order_data = get_transient($transient_unique_key);

        if( !$order_data ) {
            error_log( "Order creation failed due to order_data empty" );
            return new WP_REST_Response("Order creation failed.", 200);
        }

        $ml_ajax = new ML_Ajax();

        error_log( "order_data" );
        error_log( print_r( $order_data, true ) );

        // Extract customer and shipping info
        $customerInfo = $order_data['customerInfo'];
        $shippingInfo = $order_data['shippingInfo'];
		$applied_coupons = $order_data['applied_coupons'];
        $shipping_method_info = $order_data['shipping_method_info'];

        // Create order data array
        $order_data = array(
            "products" => $order_data['products'],
            "customerInfo" => $customerInfo,
            "response" => [
                'SessionId' => $session_id
            ],
            "extraMeta" => $order_data['extraMeta'],
            "shippingInfo" => $shippingInfo,
			'applied_coupons' => $applied_coupons,
            'shipping_method_info' => $shipping_method_info,
            "update" => true,
            "note" => $order_data['note'],
            "user_id" => $order_data['user_id']
        );
		
		// Load cart functions which are loaded only on the front-end.
		include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
		include_once WC_ABSPATH . 'includes/class-wc-cart.php';

		// wc_load_cart() does two things:
		// 1. Initialize the customer and cart objects and setup customer saving on shutdown.
		// 2. Initialize the session class.
		if ( is_null( WC()->cart ) ) {
			wc_load_cart();
		}

        // First, create the WooCommerce order
        $order_obj = ml_create_order($order_data);

        if ($order_obj) {
            $order_id = $order_obj['order_id'];
            $order_info = $order_obj['order_info'];

            $ml_ajax->send_order_to_other_domain($order_id, $order_data['user_id']);

            // destrory $transient_unique_key transient
            delete_transient($transient_unique_key); 

            // Clear the WooCommerce cart
            WC()->cart->empty_cart();

            error_log( "Order: $order_id successfully created." );
            return new WP_REST_Response([
                'status' => 'success',
                'order_id' => $order_id,
                'message' => "Order successfully created."
            ], 200);
        }

        error_log( "Order creation failed." );
        return new WP_REST_Response([
            'status' => 'error',
            'message' => "Order creation failed."
        ], 200);
    }

    public function check_user_by_email(WP_REST_Request $request)
    {
        $params = $request->get_params();

        // Check if email parameter exists and is not empty
        if (!isset($params['email']) || empty($params['email'])) {
            return new WP_REST_Response('Email parameter is missing.', 400);
        }

        $email = sanitize_email($params['email']);
        $user = get_user_by('email', $email);

        if (!$user) {
            return new WP_REST_Response(array(
                'UserID' => null,
                'UserName' => null,
                'last_generated' => null,
                'exist' => 'no'
            ), 404);
        }

        $user_id = $user->ID;
        $username = $user->user_login;
        $last_generated_time = get_user_meta($user_id, 'mockup_last_generated_time', true);

        $response = array(
            'UserID' => $user_id,
            'UserName' => $username,
            'last_generated' => $last_generated_time ? date('Y-m-d H:i:s', $last_generated_time) : null,
            'exist' => 'yes'
        );

        return new WP_REST_Response($response, 200);
    }

}

new ACM_API();