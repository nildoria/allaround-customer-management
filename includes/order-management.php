<?php
/**
 * For Order Management Site.
 *
 * 
 */
function custom_order_number_prefix($order_id)
{
    $prefix = 'MiniOrder-';
    return $prefix . $order_id;
}
add_filter('woocommerce_order_number', 'custom_order_number_prefix');

/**
 * Order Management Send Order Details.
 */
function send_order_to_other_domain($order_id)
{
    // Retrieve the order object
    $order = wc_get_order($order_id);

    // Check if the order is valid
    if (!$order) {
        error_log('Order not found: ' . $order_id);
        return;
    }

    // Get the order items
    $items = $order->get_items();
    $orderItems = array();

    if (empty($items)) {
        error_log('No items found in order: ' . $order_id);
        return; // Return early if there are no items in the order
    } else {
        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            if ($product) {
                $orderItems[] = array(
                    'product_id' => $product->get_id(),
                    'product_name' => $product->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total(),
                    // Add other item data here
                );
            }
        }
    }

    // Get the order data
    $orderData = array(
        'order_number' => $order->get_order_number(),
        'order_id' => $order->get_id(),
        'order_status' => $order->get_status(),
        'shipping_method' => $order->get_shipping_method(),
        'items' => $orderItems,
        'billing' => $order->get_address('billing'),
        'shipping' => $order->get_address('shipping'),
        'payment_method' => $order->get_payment_method(),
        'payment_method_title' => $order->get_payment_method_title(),
        // Add other order data here
    );

    error_log(print_r($orderData, true));

    // Send the order data to the other domain
    $response = wp_remote_post(
        'http://ordermanage.test/wp-json/manage-order/v1/create',
        array(
            'method' => 'POST',
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body' => json_encode($orderData),
            'sslverify' => false
        )
    );

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("Something went wrong: $error_message");
    } else {
        error_log('Response: ' . print_r(json_decode(wp_remote_retrieve_body($response), true), true));
    }
}



/**
 * Add to Order API.
 */
add_action('rest_api_init', function () {
    register_rest_route(
        'update-order/v1',
        '/add-item-to-order',
        array(
            'methods' => 'POST',
            'callback' => 'add_item_to_order',
            'permission_callback' => '__return_true',
        )
    );
});

function add_item_to_order(WP_REST_Request $request)
{
    $order_id = $request->get_param('order_id');
    $product_id = $request->get_param('product_id');
    $quantity = $request->get_param('quantity');
    $color = $request->get_param('alarnd_color');
    $size = $request->get_param('alarnd_size');
    $art_pos = $request->get_param('allaround_art_pos');
    $instruction_note = $request->get_param('allaround_instruction_note');

    if (!$order_id || !$product_id || !$quantity) {
        return new WP_Error('missing_data', 'Missing parameters', array('status' => 400));
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return new WP_Error('invalid_order', 'Invalid order ID', array('status' => 400));
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return new WP_Error('invalid_product', 'Invalid product ID', array('status' => 400));
    }

    $enable_custom_quantity = get_post_meta($product_id, 'enable_custom_quantity', true);

    // $discount_steps = get_field('discount_steps', $product_id);
    // $quantity_steps = get_field('quantity_steps', $product_id);

    if ($enable_custom_quantity) {
        $steps = get_field('quantity_steps', $product_id);
        $is_quantity_steps = true;
    } else {
        $steps = get_field('discount_steps', $product_id);
        $is_quantity_steps = false;
    }

    // Calculate the dynamic price
    $dynamic_price = calculate_dynamic_price($steps, $quantity, $product, $is_quantity_steps);


    $item = new WC_Order_Item_Product();
    $item->set_product_id($product_id);
    $item->set_quantity($quantity);
    $item->set_name($product->get_name());
    $item->set_subtotal($dynamic_price * $quantity);
    $item->set_total($dynamic_price * $quantity);

    if (!empty($color)) {
        $item->add_meta_data(__('Color', 'hello-elementor'), $color);
    }
    if (!empty($size)) {
        $item->add_meta_data(__('Size', 'hello-elementor'), $size);
    }
    if (!empty($art_pos)) {
        $item->add_meta_data(__('Art Position', 'hello-elementor'), $art_pos);
    }
    if (!empty($instruction_note)) {
        $item->add_meta_data(__('Instruction Note', 'hello-elementor'), $instruction_note);
    }

    $order->add_item($item);
    $order->calculate_totals();
    $order->save();

    return new WP_REST_Response('Item added successfully', 200);
}

function calculate_dynamic_price($discount_steps, $quantity, $product, $is_quantity_steps = false)
{
    $dynamic_price = $product->get_regular_price();
    if ($is_quantity_steps) {
        // Handle the quantity_steps structure
        foreach ($discount_steps as $step) {
            error_log("Checking step: " . print_r($step, true));
            if ($quantity >= $step['quantity']) {
                $dynamic_price = $step['amount'];
                error_log("Matched Step: " . print_r($step, true));
                error_log("Updated Dynamic Price: " . $dynamic_price);
            } else {
                break;
            }
        }
    } else {
        foreach ($discount_steps as $step) {
            if ($quantity <= $step['quantity']) {
                $dynamic_price = $step['amount'];
                break;
            }
        }
    }

    return $dynamic_price;
}


/**
 * Product List API.
 */
add_action('rest_api_init', function () {
    register_rest_route(
        'alarnd-main/v1',
        '/products',
        array(
            'methods' => 'GET',
            'callback' => 'fetch_products',
            'permission_callback' => '__return_true',
        )
    );
});

function fetch_products()
{
    $args = array(
        'status' => 'publish',
        'limit' => -1,
    );

    $products = wc_get_products($args);
    $product_list = array();

    foreach ($products as $product) {

        $product_list[] = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'meta_data' => $meta_data,
        );
    }

    return new WP_REST_Response($product_list, 200);
}
/**
 * Order Management Functions END.
 */