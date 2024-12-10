<?php

class ML_Ajax
{

    public function __construct()
    {
        add_action('wp_ajax_get_item_selector', array($this, 'get_item_selector'));
        add_action('wp_ajax_nopriv_get_item_selector', array($this, 'get_item_selector'));

        add_action('wp_ajax_confirm_payout', array($this, 'confirm_payout'));
        add_action('wp_ajax_nopriv_confirm_payout', array($this, 'confirm_payout'));

        add_action('wp_ajax_cardform_confirm_payout', array($this, 'cardform_confirm_payout'));
        add_action('wp_ajax_nopriv_cardform_confirm_payout', array($this, 'cardform_confirm_payout'));

        add_action('wp_ajax_ml_add_to_cart', array($this, 'ml_add_to_cart'));
        add_action('wp_ajax_nopriv_ml_add_to_cart', array($this, 'ml_add_to_cart'));

        add_action('wp_ajax_get_woocommerce_cart', array($this, 'get_woocommerce_cart_ajax'));
        add_action('wp_ajax_nopriv_get_woocommerce_cart', array($this, 'get_woocommerce_cart_ajax'));

        add_action('wp_ajax_add_variation_to_cart', array($this, 'add_variation_to_cart'));
        add_action('wp_ajax_nopriv_add_variation_to_cart', array($this, 'add_variation_to_cart'));

        add_action('wp_ajax_add_simple_to_cart', array($this, 'add_simple_to_cart'));
        add_action('wp_ajax_nopriv_add_simple_to_cart', array($this, 'add_simple_to_cart'));

        add_action('wp_ajax_ml_customer_details', array($this, 'ml_customer_details'));
        add_action('wp_ajax_nopriv_ml_customer_details', array($this, 'ml_customer_details'));

        add_action('wp_ajax_alarnd_create_order', array($this, 'alarnd_create_order'));
        add_action('wp_ajax_nopriv_alarnd_create_order', array($this, 'alarnd_create_order'));

        // zCredit direct payment
        add_action('wp_ajax_ml_send_card', array($this, 'ml_send_card'));
        add_action('wp_ajax_nopriv_ml_send_card', array($this, 'ml_send_card'));

        // zCredit direct payment
        add_action('wp_ajax_nopriv_zcredit_callback', array($this, 'zcredit_callback_handler'));
        add_action('wp_ajax_zcredit_callback', array($this, 'zcredit_callback_handler'));

        add_action('wp_ajax_ml_pagination', array($this, 'ml_pagination'));
        add_action('wp_ajax_nopriv_ml_pagination', array($this, 'ml_pagination'));

        add_action('wp_ajax_ml_get_cart_data', array($this, 'ml_get_cart_data_callback'));
        add_action('wp_ajax_nopriv_ml_get_cart_data', array($this, 'ml_get_cart_data_callback'));

        add_action('wp_ajax_load_products_by_category', array($this, 'load_products_by_category'));
        add_action('wp_ajax_nopriv_load_products_by_category', array($this, 'load_products_by_category'));

        add_action('wp_ajax_check_cart_status', array($this, 'check_cart_status_callback'));
        add_action('wp_ajax_nopriv_check_cart_status', array($this, 'check_cart_status_callback'));

    }

    function ml_get_cart_data_callback()
    {

        $begin_checkout = ml_get_cart_data();

        // Return cart data as JSON
        wp_send_json($begin_checkout);
    }



    function check_cart_status_callback()
    {
        check_ajax_referer('aum_ajax_nonce', 'nonce');

        $cart_contents = WC()->cart->get_cart_contents_count();

        // Check if the cart has items
        $is_item_has = $cart_contents > 0 ? true : false;

        // Send back the cart status
        wp_send_json_success(array('cart_has_items' => $is_item_has));
    }

    public function ml_pagination()
    {
        check_ajax_referer('aum_ajax_nonce', 'nonce');

        $page_num = isset($_POST['page_num']) && !empty($_POST['page_num']) ? sanitize_text_field($_POST['page_num']) : '';
        $filter_item = isset($_POST['filter_item']) && !empty($_POST['filter_item']) ? sanitize_text_field($_POST['filter_item']) : '';
        $current_user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? intval($_POST['user_id']) : '';

        $filter_item = $filter_item === 'all' ? '' : $filter_item;

        $all_products = ml_get_user_products($current_user_id, $filter_item);

        $disable_product = get_field('disable_product', "user_{$current_user_id}");
        if (!is_array($disable_product)) {
            $disable_product = array();
        }
        $items = array_filter($all_products, function ($product) use ($disable_product) {
            return !in_array($product['value'], $disable_product);
        });

        if (
            empty($page_num) ||
            empty($current_user_id) ||
            empty($items)
        ) {
            wp_die();
        }

        $bump_price = get_user_meta($current_user_id, 'bump_price', true);

        $itemsPerPage = ml_products_per_page();
        $totalItems = count($items);
        $totalPages = ceil($totalItems / $itemsPerPage);
        // $currentpage = isset($_GET['list']) ? (int)$_GET['list'] : 1;
        $currentpage = $page_num + 1;

        $start = ($currentpage - 1) * $itemsPerPage;
        $end = $start + $itemsPerPage;
        $itemsToDisplay = array_slice($items, $start, $itemsPerPage);
        // Check if there are more items to load
        $has_more_items = count($items) > $end;
        ob_start();

        // echo '<pre>';
        // print_r( $disabled_product_ids );
        // echo '</pre>';


        foreach ($itemsToDisplay as $prod_object) {
            if (!isset($prod_object['value']) || empty($prod_object['value']))
                continue;

            $product_id = $prod_object['value'];

            // check if post has thumbnail otherwise skip
            $product = wc_get_product($product_id);

            $group_enable = get_field('group_enable', $product->get_id());
            $colors = get_field('color', $product->get_id());
            $custom_quanity = get_field('enable_custom_quantity', $product->get_id());
            $sizes = get_field('size', $product->get_id());
            $pricing_description = get_field('pricing_description', $product->get_id());
            $discount_steps = get_field('discount_steps', $product->get_id());
            $discount_steps = ml_filter_disount_steps($discount_steps);

            $customQuantity_steps = get_field('quantity_steps', $product->get_id());
            $customQuantity_steps = ml_filter_disount_steps($customQuantity_steps);

            if (!empty($bump_price)) {
                $discount_steps = apply_percentage_increase($discount_steps, floatval($bump_price));
                $customQuantity_steps = apply_percentage_increase($customQuantity_steps, floatval($bump_price));
            }

            $thumbnail = wp_get_attachment_image_src($product->get_image_id(), 'alarnd_main_thumbnail');
            if (!$thumbnail)
                continue;

            $thumbnail = ml_get_thumbnail($thumbnail, $current_user_id, $product_id);

            if ($product) {
                $terms = wp_get_post_terms($product_id, 'product_cat');

                echo '<li class="loadmore-loaded product-item product ';

                foreach ($terms as $term) {
                    echo 'category-' . $term->term_id . ' ';
                }
                echo '" data-product-id="' . esc_attr($product->get_id()) . '">';

                // Product Thumbnail
                echo '<div style="background-image:url(https://placeholder.pics/svg/307x200/FFFFFF-FFFFFF/636363-FFFFFF/ALLAROUND)" class="product-thumbnail">';
                echo '<img src="' . $thumbnail . '" loading="lazy" />';
                echo '</div>';

                echo '<div class="product-item-details">';
                // Product Title
                if (!empty($discount_steps) || !empty($pricing_description)) {
                    echo '<h3 class="product-title">' . esc_html($product->get_name()) . '</h3>';
                } else {
                    echo '<h3 class="product-title">' . esc_html($product->get_name()) . '</h3>';
                }

                if (!empty($colors) && !empty($group_enable) && empty($custom_quanity)): ?>
                    <div class="alarnd--colors-wrapper">
                        <div class="alarnd--colors-wrap">
                            <?php foreach ($colors as $key => $color): ?>
                                <input type="radio" name="alarnd__color" id="alarnd__color_<?php echo esc_html($color['title']); ?>"
                                    value="<?php echo esc_html($color['title']); ?>">
                                <label for="alarnd__color_<?php echo esc_html($color['title']); ?>" class="alarnd--single-color"
                                    data-key="<?php $key; ?>" data-name="<?php echo esc_html($color['title']); ?>"
                                    style="background-color: <?php echo $color['color_hex_code']; ?>">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no_color_text">
                        <span>זמין בצבע אחד כבתמונה</span>
                    </div>
                    <?php
                endif;

                // Price

                echo '<p class="mini_productCard_price">' . $product->get_price_html() . '</p>';

                // Buttons
                echo '<div class="product-buttons">';
                if (!empty($discount_steps) || !empty($pricing_description) || !empty($customQuantity_steps)) {
                    echo '<a href="#alarnd__pricing_info-' . $product->get_id() . '" class="view-details-button alarnd_view_pricing_cb" data-product_id="' . $product->get_id() . '">לפרטים על המוצר</a>';
                } else {
                    echo '<span class="view_details_not_available"></span>';
                }

                $viewItemsAtts = ml_get_gtm_item($product);
                $viewItemsAtts = wc_implode_html_attributes($viewItemsAtts);

                echo '<button class="quick-view-button ml_add_loading ml_trigger_details button" ' . $viewItemsAtts . ' data-product-id="' . esc_attr($product->get_id()) . '">' . esc_html($product->single_add_to_cart_text()) . '</button>';
                echo '</div>';
                echo '</div>';

                if (!empty($discount_steps) || !empty($pricing_description) || !empty($customQuantity_steps)): ?>
                    <div id="alarnd__pricing_info-<?php echo $product->get_id(); ?>" data-product_id="<?php echo $product->get_id(); ?>"
                        class="mfp-hide white-popup-block alarnd--info-modal">
                        <div class="alarnd--modal-inner alarnd--modal-chart-info">
                            <h2><?php echo get_the_title($product->get_id()); ?></h2>

                            <div class="alarnd--pricing-wrapper-new">

                                <?php echo ml_gallery_carousels($product->get_id(), $current_user_id); ?>

                                <div class="pricingDescSteps">
                                    <?php if (!empty($pricing_description)): ?>
                                        <div class="alarn--pricing-column alarn--pricing-column-desc">
                                            <?php echo allround_get_meta($pricing_description); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($discount_steps) && !empty($group_enable)): ?>
                                        <div class="alarn--pricing-column alarn--pricing-column-chart">
                                            <div class="alarn--price-chart">
                                                <h5>תמחור כמות</h5>
                                                <div
                                                    class="alarnd--price-chart-price <?php echo count($discount_steps) > 4 ? 'alarnd--plus4item-box' : ''; ?>">
                                                    <?php
                                                    $index = 0;
                                                    foreach ($discount_steps as $step):
                                                        $prev = ($index == 0) ? false : $discount_steps[$index - 1];
                                                        $qty = ml_get_price_range($step['quantity'], $step['amount'], $prev);

                                                        ?>
                                                        <div class="alarnd--price-chart-item">
                                                            <span
                                                                class="price_step_price"><?php echo $step['amount'] == 0 ? wc_price($product->get_regular_price(), array('decimals' => 0)) : wc_price($step['amount'], array('decimals' => 0)); ?></span>
                                                            <span class="price_step_qty">כמות: <?php echo esc_html($qty); ?></span>
                                                        </div>
                                                        <?php $index++; endforeach; ?>
                                                </div>

                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($customQuantity_steps) && !empty($custom_quanity)): ?>
                                        <div class="alarn--pricing-column alarn--pricing-column-chart">
                                            <div class="alarn--price-chart">
                                                <h5>תמחור כמות</h5>
                                                <div
                                                    class="alarnd--price-chart-price <?php echo count($customQuantity_steps) > 4 ? 'alarnd--plus4item-box' : ''; ?>">
                                                    <?php
                                                    foreach ($customQuantity_steps as $key => $step):

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
                                                            <span class="price_step_qty">כמות:
                                                                <span><?php echo esc_html($range_title); ?></span></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="modal-bottom-btn">
                                        <button type="button" class="alarnd_trigger_details_modal ml_add_loading"
                                            data-product_id="<?php echo $product->get_id(); ?>"><?php esc_html_e('הוסיפו לעגלה', 'hello-elementor'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif;

                echo '</li>'; // End product-item
            }
        }

        $response_data = array(
            'items' => ob_get_clean(),
            'totalPages' => $totalPages,
        );

        echo json_encode($response_data);
        wp_die();

    }



    public function load_products_by_category()
    {
        check_ajax_referer('aum_ajax_nonce', 'nonce');

        $page = isset($_POST['page']) ? intval($_POST['page']) : 0;
        $category_id = isset($_POST['category_id']) ? sanitize_text_field($_POST['category_id']) : 0;
        $current_user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? intval($_POST['user_id']) : '';

        if (empty($current_user_id)) {
            wp_die();
        }

        $bump_price = get_user_meta($current_user_id, 'bump_price', true);

        // Get selected product IDs for the user
        $all_products = ml_get_user_products($current_user_id);

        $disable_product = get_field('disable_product', "user_{$current_user_id}");
        if (!is_array($disable_product)) {
            $disable_product = array();
        }
        $selected_product_ids = array_filter($all_products, function ($product) use ($disable_product) {
            return !in_array($product['value'], $disable_product);
        });

        $filtered_product_ids = array();
        // Filter products by the selected category
        if ('all' === $category_id) {
            foreach ($selected_product_ids as $product) {
                $product_id = $product['value'];
                $terms = wp_get_post_terms($product_id, 'product_cat');

                $filtered_product_ids[] = $product_id;
            }
        }

        if ('all' !== $category_id && !empty($category_id)) {
            foreach ($selected_product_ids as $product) {
                $product_id = $product['value'];
                $terms = wp_get_post_terms($product_id, 'product_cat');

                foreach ($terms as $term) {
                    if ($term->term_id == $category_id) {
                        $filtered_product_ids[] = $product_id;
                        break;
                    }
                }
            }
        }

        $itemsPerPage = ml_products_per_page();
        $totalItems = count($filtered_product_ids);
        $totalPages = ceil($totalItems / $itemsPerPage);
        $currentpage = $page;

        $start = ($currentpage - 1) * $itemsPerPage;
        $end = $start + $itemsPerPage;

        $offset = ($page - 1) * $itemsPerPage;

        // Get a subset of filtered product IDs for the current page
        $current_page_product_ids = array_slice($filtered_product_ids, $offset, $itemsPerPage);

        ob_start();

        foreach ($current_page_product_ids as $product_id) {

            $product = wc_get_product($product_id);

            $group_enable = get_field('group_enable', $product->get_id());
            $colors = get_field('color', $product->get_id());
            $custom_quanity = get_field('enable_custom_quantity', $product->get_id());
            $sizes = get_field('size', $product->get_id());
            $pricing_description = get_field('pricing_description', $product->get_id());
            $discount_steps = get_field('discount_steps', $product->get_id());
            $discount_steps = ml_filter_disount_steps($discount_steps);

            $customQuantity_steps = get_field('quantity_steps', $product->get_id());
            $customQuantity_steps = ml_filter_disount_steps($customQuantity_steps);

            if (!empty($bump_price)) {
                $discount_steps = apply_percentage_increase($discount_steps, floatval($bump_price));
                $customQuantity_steps = apply_percentage_increase($customQuantity_steps, floatval($bump_price));
            }

            $thumbnail = wp_get_attachment_image_src($product->get_image_id(), 'alarnd_main_thumbnail');
            if (!$thumbnail)
                continue;

            $thumbnail = ml_get_thumbnail($thumbnail, $current_user_id, $product_id);

            if ($product) {
                $terms = wp_get_post_terms($product_id, 'product_cat');

                echo '<li class="loadmore-loaded product-item product ';

                foreach ($terms as $term) {
                    echo 'category-' . $term->term_id . ' ';
                }
                echo '" data-product-id="' . esc_attr($product->get_id()) . '">';

                // Product Thumbnail
                echo '<div class="product-thumbnail">';
                echo '<img src="' . $thumbnail . '" loading="lazy" />';
                echo '</div>';

                echo '<div class="product-item-details">';
                // Product Title
                if (!empty($discount_steps) || !empty($pricing_description)) {
                    echo '<h3 class="product-title">' . esc_html($product->get_name()) . '</h3>';
                } else {
                    echo '<h3 class="product-title">' . esc_html($product->get_name()) . '</h3>';
                }

                if (!empty($colors) && !empty($group_enable) && empty($custom_quanity)): ?>
                    <div class="alarnd--colors-wrapper">
                        <div class="alarnd--colors-wrap">
                            <?php foreach ($colors as $key => $color): ?>
                                <input type="radio" name="alarnd__color" id="alarnd__color_<?php echo esc_html($color['title']); ?>"
                                    value="<?php echo esc_html($color['title']); ?>">
                                <label for="alarnd__color_<?php echo esc_html($color['title']); ?>" class="alarnd--single-color"
                                    data-key="<?php $key; ?>" data-name="<?php echo esc_html($color['title']); ?>"
                                    style="background-color: <?php echo $color['color_hex_code']; ?>">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no_color_text">
                        <span>זמין בצבע אחד כבתמונה</span>
                    </div>
                    <?php
                endif;

                // Price

                echo '<p class="mini_productCard_price">' . $product->get_price_html() . '</p>';

                // Buttons
                echo '<div class="product-buttons">';
                if (!empty($discount_steps) || !empty($pricing_description) || !empty($customQuantity_steps)) {
                    echo '<a href="#alarnd__pricing_info-' . $product->get_id() . '" class="view-details-button alarnd_view_pricing_cb" data-product_id="' . $product->get_id() . '">לפרטים על המוצר</a>';
                } else {
                    echo '<span class="view_details_not_available"></span>';
                }

                $viewItemsAtts = ml_get_gtm_item($product);
                $viewItemsAtts = wc_implode_html_attributes($viewItemsAtts);

                echo '<button class="quick-view-button ml_add_loading ml_trigger_details button" ' . $viewItemsAtts . ' data-product-id="' . esc_attr($product->get_id()) . '">' . esc_html($product->single_add_to_cart_text()) . '</button>';
                echo '</div>';
                echo '</div>';

                if (!empty($discount_steps) || !empty($pricing_description) || !empty($customQuantity_steps)): ?>
                    <div id="alarnd__pricing_info-<?php echo $product->get_id(); ?>" data-product_id="<?php echo $product->get_id(); ?>"
                        class="mfp-hide white-popup-block alarnd--info-modal">
                        <div class="alarnd--modal-inner alarnd--modal-chart-info">
                            <h2><?php echo get_the_title($product->get_id()); ?></h2>

                            <div class="alarnd--pricing-wrapper-new">

                                <?php echo ml_gallery_carousels($product->get_id(), $current_user_id); ?>

                                <div class="pricingDescSteps">
                                    <?php if (!empty($pricing_description)): ?>
                                        <div class="alarn--pricing-column alarn--pricing-column-desc">
                                            <?php echo allround_get_meta($pricing_description); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($discount_steps) && !empty($group_enable)): ?>
                                        <div class="alarn--pricing-column alarn--pricing-column-chart">
                                            <div class="alarn--price-chart">
                                                <h5>תמחור כמות</h5>
                                                <div
                                                    class="alarnd--price-chart-price <?php echo count($discount_steps) > 4 ? 'alarnd--plus4item-box' : ''; ?>">
                                                    <?php
                                                    $index = 0;
                                                    foreach ($discount_steps as $step):
                                                        $prev = ($index == 0) ? false : $discount_steps[$index - 1];
                                                        $qty = ml_get_price_range($step['quantity'], $step['amount'], $prev);

                                                        ?>
                                                        <div class="alarnd--price-chart-item">
                                                            <span
                                                                class="price_step_price"><?php echo $step['amount'] == 0 ? wc_price($product->get_regular_price(), array('decimals' => 0)) : wc_price($step['amount'], array('decimals' => 0)); ?></span>
                                                            <span class="price_step_qty">כמות: <?php echo esc_html($qty); ?></span>
                                                        </div>
                                                        <?php $index++; endforeach; ?>
                                                </div>

                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($customQuantity_steps) && !empty($custom_quanity)): ?>
                                        <div class="alarn--pricing-column alarn--pricing-column-chart">
                                            <div class="alarn--price-chart">
                                                <h5>תמחור כמות</h5>
                                                <div
                                                    class="alarnd--price-chart-price <?php echo count($customQuantity_steps) > 4 ? 'alarnd--plus4item-box' : ''; ?>">
                                                    <?php
                                                    foreach ($customQuantity_steps as $key => $step):

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
                                                            <span class="price_step_qty">כמות:
                                                                <span><?php echo esc_html($range_title); ?></span></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="modal-bottom-btn">
                                        <button type="button" class="alarnd_trigger_details_modal ml_add_loading"
                                            data-product_id="<?php echo $product->get_id(); ?>"><?php esc_html_e('הוסיפו לעגלה', 'hello-elementor'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif;

                echo '</li>'; // End product-item
            }
        }

        $response_data = array(
            'items' => ob_get_clean(),
            'totalPages' => $totalPages,
        );

        echo json_encode($response_data);
        wp_die();
    }



    public function confirm_payout()
    {
        check_ajax_referer('aum_ajax_nonce', 'nonce');
        ?>
        <div class="white-popup-block alarnd--payout-modal mfp-hide alarnd--info-modal">
            <div class="popup_product_details">
                <div class="alarnd--success-wrap">
                    <div class="alarn--popup-thankyou">
                        <img src="<?php echo AlRNDCM_URL; ?>assets/images/tick.png" alt="">
                        <h2>תודה שהוספת את "<?php the_title(); ?>" להזמנה שלך!</h2>
                        <h3>דגם יישלח עם שאר המוצרים המותאמים אישית שהזמנת.</h3>
                        <p>אתה עדיין יכול להוסיף את שאר <br>המוצרים בעמוד זה וליהנות ממבצעים מעולים :)</p>
                        <a href="#" class="alarnd--submit-btn alarnd--continue-btn">המשך בקניות</a>
                    </div>
                </div>

                <div class="alarnd--failed-wrap">
                    <div class="alarn--popup-thankyou">
                        <img src="<?php echo AlRNDCM_URL; ?>assets/images/failed.png" alt="">
                        <h2><?php esc_html_e("Order Didn't go through", "hello-elementor"); ?></h2>
                        <h3>לצערנו העסקה לא אושרה.</h3>
                        <p>נטפל בבעיה וניצור איתך קשר בהקדם :)</p>
                        <a href="#"
                            class="alarnd--submit-btn alarnd--continue-btn"><?php esc_html_e('Try Again', 'hello-elementor'); ?></a>
                        <div class="form-message"></div>
                    </div>
                </div>

                <div class="alarnd--popup-confirmation">
                    <div class="alarnd--popup-middle">
                        <h5><?php esc_html_e('Thanks for adding it to your order!', "hello-elementor"); ?></h5>
                        <div class="alarnd--popup-inline">
                            <h5><?php printf('%s %s', esc_html__('Please confirm by clicking on the button below and we’ll charge your card by', 'hello-elementor'), WC()->cart->get_total()); ?>
                            </h5>
                        </div>
                        <span
                            class="alrnd--create-order alarnd--submit-btn ml_add_loading button"><?php esc_html_e('Click To Pay ', "hello-elementor"); ?>
                            <?php printf(WC()->cart->get_total()); ?></span>
                        <div class="form-message"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        wp_die();
    }

    public function cardform_confirm_payout()
    {
        check_ajax_referer('aum_ajax_nonce', 'nonce');
        ?>
        <div class="white-popup-block alarnd--payout-modal mfp-hide alarnd--info-modal">
            <div class="popup_product_details">
                <div class="alarnd--success-wrap">
                    <div class="alarn--popup-thankyou">
                        <img src="<?php echo AlRNDCM_URL; ?>assets/images/tick.png" alt="">
                        <h2>תודה שהוספת את "<?php the_title(); ?>" להזמנה שלך!</h2>
                        <h3>דגם יישלח עם שאר המוצרים המותאמים אישית שהזמנת.</h3>
                        <p>אתה עדיין יכול להוסיף את שאר <br>המוצרים בעמוד זה וליהנות ממבצעים מעולים :)</p>
                        <a href="#" class="alarnd--submit-btn alarnd--continue-btn">המשך בקניות</a>
                    </div>
                </div>

                <div class="alarnd--failed-wrap">
                    <div class="alarn--popup-thankyou">
                        <img src="<?php echo AlRNDCM_URL; ?>assets/images/failed.png" alt="">
                        <h2><?php esc_html_e("Order Didn't go through", "hello-elementor"); ?></h2>
                        <h3>לצערנו העסקה לא אושרה.</h3>
                        <p>נטפל בבעיה וניצור איתך קשר בהקדם :)</p>
                        <a href="#"
                            class="alarnd--submit-btn alarnd--continue-btn"><?php esc_html_e('Try Again', 'hello-elementor'); ?></a>
                        <div class="form-message"></div>
                    </div>
                </div>

                <div class="alarnd--popup-confirmation">
                    <div class="alarnd--popup-middle">
                        <h5><?php esc_html_e('Thanks for adding it to your order!', "hello-elementor"); ?></h5>
                        <div class="alarnd--popup-inline">
                            <h5><?php printf('%s %s', esc_html__('Please confirm by clicking on the button below and we’ll charge your card by', 'hello-elementor'), WC()->cart->get_total()); ?>
                            </h5>
                        </div>
                        <span class="alrnd--send_carddetails alarnd--submit-btn ml_add_loading button"
                            style="width: 100%"><?php esc_html_e('Click To Pay ', "hello-elementor"); ?>
                            <?php printf(WC()->cart->get_total()); ?></span>
                        <div class="form-message"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        wp_die();
    }

    public function popup_success_icon()
    {
        $success_icon = AlRNDCM_URL . "assets/images/tick.png";
        return $success_icon;
    }
    public function popup_failed_icon()
    {
        $failed_icon = AlRNDCM_URL . "assets/images/failed.png";
        return $failed_icon;
    }
    public function popup_success_markup($order_id = '')
    {
        $success_icon = $this->popup_success_icon();

        $thankyou_output = '';
        if (!empty($order_id)) {
            $order = wc_get_order($order_id);
            if ($order) {
                ob_start();
                // wc_get_template( 'order/order-details.php', array( 'order_id' => $order_id ) );
                wc_get_template('checkout/thankyou.php', array('order' => $order));
                $thankyou_output = ob_get_clean();
            }
        }

        $success_popup = '<div class="white-popup-block alarnd--payout-modal alarnd--thankyou-modal mfp-hide alarnd--info-modal">
            <div class="popup_product_details">
                <div class="alarnd--success-wrap">
                    <div class="woocommerce alarn--popup-thankyou">';

        if (!empty($thankyou_output)) {
            $success_popup .= $thankyou_output;
        } else {
            $success_popup .= '<img src="' . $success_icon . '" alt="">
                            <h2>תודה שהוספת את להזמנה שלך!</h2>
                            <h3>דגם יישלח עם שאר המוצרים המותאמים אישית שהזמנת.</h3>
                            <p>אתה עדיין יכול להוסיף את שאר <br>המוצרים בעמוד זה וליהנות ממבצעים מעולים :)</p>
                            <a href="#" class="alarnd--submit-btn alarnd--continue-btn">המשך בקניות</a>';
        }

        $success_popup .= '</div>
                </div>
            </div>
        </div>';
        return $success_popup;
    }
    public function popup_failed_markup()
    {
        $failed_icon = $this->popup_failed_icon();
        $failed_popup = '<div class="white-popup-block alarnd--payout-modal mfp-hide alarnd--info-modal">
            <div class="popup_product_details">
                <div class="alarnd--failed-wrap">
                    <div class="alarn--popup-thankyou">
                        <img src="' . $failed_icon . '" alt="">
                        <h2>' . esc_html__("Order Didn\"t go through", "hello-elementor") . '</h2>
                        <h3>לצערנו העסקה לא אושרה.</h3>
                        <p>נטפל בבעיה וניצור איתך קשר בהקדם :)</p>
                        <a href="#" class="alarnd--submit-btn alarnd--continue-btn">' . esc_html__("Try Again", "hello-elementor") . '</a>
                        <div class="form-message"></div>
                    </div>
                </div>
            </div>
        </div>';
        return $failed_popup;
    }

    /**
     * Add item to order from thankyou page
     *
     * @return void
     */
    /**
     * Add item to order from thankyou page
     *
     * @return void
     */
    public function alarnd_create_order()
    {
        check_ajax_referer('aum_ajax_nonce', 'nonce');

        if (WC()->cart->get_cart_contents_count() == 0) {
            wp_send_json_error(
                array(
                    "message_type" => 'reqular',
                    "message" => esc_html__("Cart is empty.", "hello-elementor")
                )
            );
            wp_die();
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(
                array(
                    "message_type" => 'reqular',
                    "message" => esc_html__("User need to logged in.", "hello-elementor")
                )
            );
            wp_die();
        }

        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;

        $token = get_field('token', "user_{$current_user_id}");

        $card_info = get_field('card_info', "user_{$current_user_id}");
        $four_digit = isset($card_info['last_4_digit']) && !empty($card_info['last_4_digit']) ? $card_info['last_4_digit'] : '';

        if (empty($token)) {
            wp_send_json_error(
                array(
                    "message_type" => 'reqular',
                    "message" => esc_html__("Token value empty.", "hello-elementor")
                )
            );
            wp_die();
        }

        $customerDetails = isset($_POST['customerDetails']) && !empty($_POST['customerDetails']) ? $_POST['customerDetails'] : [];
        $note = isset($_POST['note']) && !empty($_POST['note']) ? sanitize_text_field($_POST['note']) : '';
        $userName = isset($customerDetails['userName']) && !empty($customerDetails['userName']) ? sanitize_text_field($customerDetails['userName']) : '';
        $userPhone = isset($customerDetails['userPhone']) && !empty($customerDetails['userPhone']) ? sanitize_text_field($customerDetails['userPhone']) : '';
        $userAdress = isset($customerDetails['userAdress']) && !empty($customerDetails['userAdress']) ? sanitize_text_field($customerDetails['userAdress']) : '';
        $userPostcode = isset($customerDetails['userPostcode']) && !empty($customerDetails['userPostcode']) ? sanitize_text_field($customerDetails['userPostcode']) : '';
        $userEmail = isset($customerDetails['userEmail']) && !empty($customerDetails['userEmail']) ? sanitize_text_field($customerDetails['userEmail']) : '';
        $cardholderCity = isset($customerDetails['userCity']) && !empty($customerDetails['userCity']) ? sanitize_text_field($customerDetails['userCity']) : '';
        $cardholderInvoiceName = isset($customerDetails['userInvoiceName']) && !empty($customerDetails['userInvoiceName']) ? sanitize_text_field($customerDetails['userInvoiceName']) : '';
        $countryCode = ml_get_country_code();

        if (
            empty($customerDetails) ||
            empty($userName) ||
            empty($userPhone) ||
            empty($userAdress) ||
            empty($userPostcode) ||
            empty($cardholderCity) ||
            empty($userEmail)
        ) {
            wp_send_json_error(
                array(
                    "message_type" => 'reqular',
                    "message" => esc_html__("Required field are empty. Please fill all the field.", "hello-elementor")
                )
            );
            wp_die();
        }

        // $userPhone = $countryCode . $userPhone;

        if (
            !is_email($userEmail)
        ) {
            wp_send_json_error(
                array(
                    "message_type" => 'reqular',
                    "message" => esc_html__("Please enter a valid email address.", "hello-elementor")
                )
            );
            wp_die();
        }

        $cart_filter_data = [];
        $product_list = [];
        WC()->cart->calculate_totals();
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = wc_get_product($cart_item['product_id']);

            $the_product = $cart_item['data'];

            // Get the price of the product
            $product_price = $the_product->get_price();
            error_log($cart_item['data']->get_price());

            $cart_filter_data[$cart_item_key]["title"] = $_product->get_title();
            $cart_filter_data[$cart_item_key]["price"] = $cart_item['data']->get_price();

            if (isset($cart_item['alarnd_size']) && !empty($cart_item['alarnd_size'])) {
                $cart_filter_data[$cart_item_key]["size"] = $cart_item['alarnd_size'];
            }
            if (isset($cart_item['alarnd_color']) && !empty($cart_item['alarnd_color'])) {
                $cart_filter_data[$cart_item_key]["color"] = $cart_item['alarnd_color'];
            }
            if (isset($cart_item['quantity']) && !empty($cart_item['quantity'])) {
                $cart_filter_data[$cart_item_key]["quantity"] = $cart_item['quantity'];
                $cart_filter_data[$cart_item_key]["total_price"] = (int) $cart_item['quantity'] * (int) $cart_item['data']->get_price();
            }

            if ($_product->is_type('variable')) {

            }

            $single_product_item = array(
                "product_id" => $cart_item['product_id'],
                "quantity" => $cart_item['quantity']
            );

            $single_product_item["price"] = $cart_item['data']->get_price();

            if (isset($cart_item['alarnd_size']) && !empty($cart_item['alarnd_size'])) {
                $single_product_item["size"] = $cart_item['alarnd_size'];
            }
            if (isset($cart_item['alarnd_color']) && !empty($cart_item['alarnd_color'])) {
                $single_product_item["color"] = $cart_item['alarnd_color'];
            }
            if (isset($cart_item['alarnd_color_key'])) {
                $single_product_item['alarnd_color_key'] = $cart_item['alarnd_color_key'];
            }
            if (isset($cart_item['alarnd_custom_color'])) {
                $single_product_item['alarnd_custom_color'] = $cart_item['alarnd_custom_color'];
            }
            if (isset($cart_item['alarnd_step_key'])) {
                $single_product_item['alarnd_step_key'] = $cart_item['alarnd_step_key'];
            }

            $product_list[] = $single_product_item;
        }
        WC()->cart->calculate_totals();

        $extraMeta = [];
        $extraMeta['invoice'] = $cardholderInvoiceName;
        $extraMeta['city'] = $cardholderCity;

        // send request to api
        $api_url = apply_filters('allaround_order_api_url', '');

        $body = array(
            'username' => $userName,
            'email' => $userEmail,
            'phone' => $userPhone,
            'address_2' => $userAdress,
            'postcode' => $userPostcode,
            'invoice' => $cardholderInvoiceName,
            'token' => $token,
            'cardNum' => $four_digit,
            'price' => WC()->cart->total,
            'items' => $cart_filter_data
        );

        $body = apply_filters('allaround_order_api_body', $body, $current_user_id);

        $args = array(
            'method' => 'POST',
            'timeout' => 15,
            'sslverify' => false,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
        $args = apply_filters('allaround_order_api_args', $args, $current_user_id);

        $request = wp_remote_post(esc_url($api_url), $args);

        // error_log( print_r( $request, true ) );

        // retrieve reponse body
        $message = wp_remote_retrieve_body($request);

        // decode response into array
        $response_obj = ml_response($message);

        // error_log( print_r( $response_obj, true ) );

        // order data
        $first_name = empty($current_user->first_name) && empty($current_user->last_name) ? $userName : $current_user->first_name;
        $last_name = empty($current_user->first_name) && empty($current_user->last_name) ? '' : $current_user->last_name;
        $company = get_user_meta($current_user_id, 'billing_company', true);
        $city = get_user_meta($current_user_id, 'billing_city', true);
        $city = !empty($cardholderCity) ? $cardholderCity : $city;
        $postcode = get_user_meta($current_user_id, 'billing_address_2', true);
        $state = get_user_meta($current_user_id, 'billing_state', true);
        $country = get_user_meta($current_user_id, 'billing_country', true);
        $country = empty($country) ? "IL" : $country;

        $customerInfo = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'name' => $userName,
            'company' => $company,
            'email' => $userEmail,
            'phone' => $userPhone,
            'address_1' => $userAdress,
            'city' => $city,
            'state' => $state,
            'address_2' => $postcode,
            'country' => $country
        );

        $order_data = array(
            "products" => $product_list,
            "customerInfo" => $customerInfo,
            "cardNumber" => '',
            "response" => $response_obj,
            "extraMeta" => $extraMeta,
            "update" => true,
            "note" => $note,
            "user_id" => $current_user_id
        );

        // error_log( print_r( $order_data, true ) );

        $failed_popup = $this->popup_failed_markup();

        $is_test_mode = get_option("ml_add_test_mode_for_api");

        $is_valid_condition = !is_wp_error($request) && wp_remote_retrieve_response_code($request) == 200 && $message !== "Accepted";
        if ($is_test_mode === 'on') {
            $is_valid_condition = !is_wp_error($request) && $message !== "Accepted";
            $order_data['response']['referenceID'] = '56555545411';
            $order_data['response']['token'] = 'skdjfdsfsdf41exesdf';
        }

        if ($is_valid_condition) {
            // first create order
            $order_obj = ml_create_order($order_data);
            $order_id = $order_obj['order_id'];
            $order_info = $order_obj['order_info'];

            $success_popup = $this->popup_success_markup($order_id);

            // Clear the cart
            WC()->cart->empty_cart();

            wp_send_json_success(
                array(
                    "message_type" => 'api',
                    "result_popup" => $success_popup,
                    "response_obj" => $response_obj,
                    "order_info" => $order_info,
                    "message_server" => $message,
                    "message" => "Successfully products added to order #$order_id"
                )
            );

            wp_die();
        }

        $error_message = "Something went wrong";
        if (is_wp_error($request)) {
            $error_message = $request->get_error_message();
        }

        if ("Accepted" === $message) {
            $error_message = "Unable to reach the api server";
        }

        if (isset($response_obj['returnMessage']) && !empty($response_obj['returnMessage'])) {
            $error_message = $response_obj['returnMessage'];
        }

        // error_log( print_r( $error_message, true ) );
        wp_send_json_error(
            array(
                "body" => $body,
                "message_type" => 'api',
                "result_popup" => $failed_popup,
                "message" => $error_message,
                "server_message" => $message,
                "server_body_obj" => $response_obj
            )
        );

        wp_die();
    }

    /**
     * Send Card Details and Cart Detais
     *
     * @return void
     */
    public function zcred_request($card_details)
    {
        $zcredit_gateway_id = 'zcredit_checkout_payment';
        $zcredit_settings = get_option("woocommerce_{$zcredit_gateway_id}_settings");

        $jdata = array(
            "TerminalNumber" => isset($zcredit_settings['terminal_number']) ? $zcredit_settings['terminal_number'] : '',
            "Password" => isset($zcredit_settings['password']) ? $zcredit_settings['password'] : '',
            "CardNumber" => $card_details['cardNumber'],
            "CVV" => $card_details['cvvCode'],
            "ExpDate_MMYY" => $card_details['expirationDate'],
            "TransactionSum" => $card_details['price'],
            "J" => "2"
        );

        /*******************************
        // WP HTTP API CALL
        ********************************/
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8'
            ),
            'body' => json_encode($jdata),
            'sslverify' => true // Enforce SSL verification
        );

        $full_response = wp_remote_post("https://pci.zcredit.co.il/ZCreditWS/api/Transaction/CommitFullTransaction", $args);
        $response = wp_remote_retrieve_body($full_response);

        $response_json = json_decode($response, true);
        if (isset($response_json['HasError']) && true === $response_json['HasError']) {
            error_log("zcredit response_json error ");
            error_log(print_r($response_json, true));
            return false;
        }

        if (isset($response_json['Token']) && !empty($response_json['Token'])) {
            return array(
                "token" => $response_json['Token'],
                "referenceID" => $response_json['ReferenceNumber'],
                "CardNumber" => $response_json['CardNumber'],
                "CardBrandCode" => $response_json['CardBrandCode'],
                "ReturnCode" => $response_json['ReturnCode'],
                "ReturnMessage" => $response_json['ReturnMessage']
            );
        }

        $is_test_mode = get_option("ml_add_test_mode_for_api");

        if ($is_test_mode === 'on') {
            return array(
                "token" => 'sometoken',
                "referenceID" => 'somereference24154',
                "CardNumber" => '424242424242',
                "CardBrandCode" => 'Visa',
                "ReturnCode" => '245',
                "ReturnMessage" => 'somemessage'
            );
        }

        error_log("zcredit request failed");
        return false;
    }

    /**
     * Send Card Details and Cart Detais
     *
     * @return void
     */
    // public function ml_send_card()
    // {
    //     check_ajax_referer('aum_ajax_nonce', 'nonce');

    //     if (WC()->cart->get_cart_contents_count() == 0) {
    //         wp_send_json_error(
    //             array(
    //                 "message_type" => 'reqular',
    //                 "message" => "Cart is empty."
    //             )
    //         );
    //         wp_die();
    //     }

    //     $user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? intval($_POST['user_id']) : '';
    //     $cardholderName = isset($_POST['userName']) && !empty($_POST['userName']) ? sanitize_text_field($_POST['userName']) : '';
    //     $cardholderPhone = isset($_POST['userPhone']) && !empty($_POST['userPhone']) ? sanitize_text_field($_POST['userPhone']) : '';
    //     $cardholderAdress = isset($_POST['userAdress']) && !empty($_POST['userAdress']) ? sanitize_text_field($_POST['userAdress']) : '';
    //     $cardholderPostcode = isset($_POST['userPostcode']) && !empty($_POST['userPostcode']) ? sanitize_text_field($_POST['userPostcode']) : '';
    //     $cardholderEmail = isset($_POST['userEmail']) && !empty($_POST['userEmail']) ? sanitize_text_field($_POST['userEmail']) : '';
    //     $cardholderCity = isset($_POST['userCity']) && !empty($_POST['userCity']) ? sanitize_text_field($_POST['userCity']) : '';
    //     $note = isset($_POST['note']) && !empty($_POST['note']) ? sanitize_text_field($_POST['note']) : '';
    //     $cardholderInvoiceName = isset($_POST['userInvoiceName']) && !empty($_POST['userInvoiceName']) ? sanitize_text_field($_POST['userInvoiceName']) : '';

    //     $cardNumber = isset($_POST['cardNumber']) && !empty($_POST['cardNumber']) ? sanitize_text_field($_POST['cardNumber']) : '';
    //     $expirationDate = isset($_POST['expirationDate']) && !empty($_POST['expirationDate']) ? sanitize_text_field($_POST['expirationDate']) : '';
    //     $cvvCode = isset($_POST['cvvCode']) && !empty($_POST['cvvCode']) ? sanitize_text_field($_POST['cvvCode']) : '';
    //     $countryCode = ml_get_country_code();

    //     $cardNumber = str_replace(' ', '', $cardNumber);

    //     $current_user_id = $user_id;
    //     $current_user = get_userdata($current_user_id);

    //     if (
    //         empty($cardholderName) ||
    //         empty($cardholderPhone) ||
    //         empty($cardholderAdress) ||
    //         empty($cardholderPostcode) ||
    //         empty($cardholderEmail) ||
    //         empty($cardholderCity) ||
    //         empty($cardNumber) ||
    //         empty($expirationDate) ||
    //         empty($cvvCode)
    //     ) {
    //         wp_send_json_error(
    //             array(
    //                 "message_type" => 'reqular',
    //                 "message" => esc_html__("Required field are empty. Please fill all the field.", "hello-elementor")
    //             )
    //         );
    //         wp_die();
    //     }

    //     // $cardholderPhone = $countryCode . $cardholderPhone;

    //     if (
    //         !is_email($cardholderEmail)
    //     ) {
    //         wp_send_json_error(
    //             array(
    //                 "message_type" => 'reqular',
    //                 "message" => esc_html__("Please enter a valid email address.", "hello-elementor")
    //             )
    //         );
    //         wp_die();
    //     }

    //     $expirationDate = str_replace("/", '', $expirationDate);

    //     $cart_filter_data = [];
    //     $product_list = [];
    //     WC()->cart->calculate_totals();
    //     foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
    //         $_product = wc_get_product($cart_item['product_id']);

    //         $cart_filter_data[$cart_item_key]["title"] = $_product->get_title();
    //         $cart_filter_data[$cart_item_key]["price"] = (int) $cart_item['data']->get_price();
    //         if (isset($cart_item['alarnd_size']) && !empty($cart_item['alarnd_size'])) {
    //             $cart_filter_data[$cart_item_key]["size"] = $cart_item['alarnd_size'];
    //         }
    //         if (isset($cart_item['alarnd_color']) && !empty($cart_item['alarnd_color'])) {
    //             $cart_filter_data[$cart_item_key]["color"] = $cart_item['alarnd_color'];
    //         }
    //         if (isset($cart_item['quantity']) && !empty($cart_item['quantity'])) {
    //             $cart_filter_data[$cart_item_key]["quantity"] = $cart_item['quantity'];
    //             $cart_filter_data[$cart_item_key]["total_price"] = (int) $cart_item['quantity'] * (int) $cart_item['data']->get_price();
    //         }

    //         if ($_product->is_type('variable')) {

    //         }

    //         $single_product_item = array(
    //             "product_id" => $cart_item['product_id'],
    //             "quantity" => $cart_item['quantity']
    //         );

    //         $single_product_item["price"] = $cart_item['data']->get_price();

    //         if (isset($cart_item['alarnd_size']) && !empty($cart_item['alarnd_size'])) {
    //             $single_product_item["size"] = $cart_item['alarnd_size'];
    //         }
    //         if (isset($cart_item['alarnd_color']) && !empty($cart_item['alarnd_color'])) {
    //             $single_product_item["color"] = $cart_item['alarnd_color'];
    //         }
    //         if (isset($cart_item['alarnd_color_key'])) {
    //             $single_product_item['alarnd_color_key'] = $cart_item['alarnd_color_key'];
    //         }
    //         if (isset($cart_item['alarnd_custom_color'])) {
    //             $single_product_item['alarnd_custom_color'] = $cart_item['alarnd_custom_color'];
    //         }
    //         if (isset($cart_item['alarnd_step_key'])) {
    //             $single_product_item['alarnd_step_key'] = $cart_item['alarnd_step_key'];
    //         }

    //         $product_list[] = $single_product_item;
    //     }
    //     WC()->cart->calculate_totals();

    //     $extraMeta = [];
    //     $extraMeta['invoice'] = $cardholderInvoiceName;
    //     $extraMeta['city'] = $cardholderCity;

    //     // Send Username
    //     $siteUsername = $current_user->user_login;

    //     // send request to api
    //     $api_url = apply_filters('allaround_card_url', 'https://hook.eu1.make.com/80wvx4qyzxkegv4n1y2ys736dz92t6u6');

    //     $get_token = $this->zcred_request(
    //         array(
    //             'cardNumber' => $cardNumber,
    //             'expirationDate' => $expirationDate,
    //             'cvvCode' => $cvvCode,
    //             'price' => WC()->cart->total,
    //         )
    //     );

    //     // show error popup if token not generated
    //     if (false === $get_token || !isset($get_token['token']) || empty($get_token['token'])) {
    //         $failed_popup = $this->popup_failed_markup();

    //         $returnMessage = isset($get_token['ReturnMessage']) ? $get_token['ReturnMessage'] : '';
    //         wp_send_json_error(
    //             json_encode(
    //                 array(
    //                     "message_type" => 'api',
    //                     "message" => $returnMessage,
    //                     "result_popup" => $failed_popup,
    //                 )
    //             )
    //         );

    //         wp_die();
    //     }

    //     $body = array(
    //         'cardholderName' => $cardholderName,
    //         'cardholderPhone' => $cardholderPhone,
    //         'cardholderAdress' => $cardholderAdress,
    //         'cardholderAdress2' => $cardholderPostcode,
    //         'cardholderCity' => $cardholderCity,
    //         'cardholderEmail' => $cardholderEmail,
    //         'cardholderInvoiceName' => $cardholderInvoiceName,
    //         'siteUsername' => $siteUsername,
    //         'note' => $note,
    //         'price' => WC()->cart->total,
    //         'items' => $cart_filter_data
    //     );

    //     $order_response_data = [];

    //     if (false !== $get_token) {
    //         $body['token'] = $get_token['token'];
    //         $order_response_data['token'] = $get_token['token'];
    //         $order_response_data['referenceID'] = $get_token['referenceID'];
    //     }

    //     // error_log( print_r( $body, true ) );

    //     // Get user profile picture URLs
    //     $profile_picture_id = get_field('profile_picture_id', 'user_' . $current_user_id);
    //     $profile_picture_id_second = get_field('profile_picture_id_second', 'user_' . $current_user_id);
    //     $custom_logo_lighter = get_field('custom_logo_lighter', 'user_' . $current_user_id);
    //     $custom_logo_darker = get_field('custom_logo_darker', 'user_' . $current_user_id);

    //     // Check if profile pictures are not empty and add them to the body
    //     if (!empty($profile_picture_id)) {
    //         $profile_picture_url = wp_get_attachment_url($profile_picture_id);
    //         $body['defaultLogoLighter'] = $profile_picture_url;
    //     }

    //     if (!empty($profile_picture_id_second)) {
    //         $profile_picture_url_second = wp_get_attachment_url($profile_picture_id_second);
    //         $body['defaultLogoDarker'] = $profile_picture_url_second;
    //     }

    //     if (!empty($custom_logo_lighter)) {
    //         $custom_logo_lighter = wp_get_attachment_url($custom_logo_lighter);
    //         $body['customLogoLighter'] = $custom_logo_lighter;
    //     }

    //     if (!empty($custom_logo_darker)) {
    //         $custom_logo_darker = wp_get_attachment_url($custom_logo_darker);
    //         $body['customLogoDarker'] = $custom_logo_darker;
    //     }

    //     // error_log( print_r( $body, true ) );

    //     $body = apply_filters('allaround_card_api_body', $body, $current_user_id);

    //     $args = array(
    //         'method' => 'POST',
    //         'timeout' => 15,
    //         'sslverify' => false,
    //         'headers' => array(
    //             'Content-Type' => 'application/json',
    //         ),
    //         'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
    //     );
    //     $args = apply_filters('allaround_card_api_args', $args, $current_user_id);

    //     // send request to make.com
    //     $request = wp_remote_post(esc_url($api_url), $args);

    //     // error_log( print_r( $request, true ) );

    //     // retrieve reponse body
    //     $message = wp_remote_retrieve_body($request);

    //     // decode response into array
    //     $response_obj = ml_response($message);

    //     // error_log( print_r( $response_obj, true ) );

    //     // order data
    //     $first_name = empty($current_user->first_name) && empty($current_user->last_name) ? $cardholderName : $current_user->first_name;
    //     $last_name = empty($current_user->first_name) && empty($current_user->last_name) ? '' : $current_user->last_name;
    //     $company = get_user_meta($current_user_id, 'billing_company', true);
    //     $company = !empty($cardholderInvoiceName) ? $cardholderInvoiceName : $company;
    //     $city = get_user_meta($current_user_id, 'billing_city', true);
    //     $city = !empty($cardholderCity) ? $cardholderCity : $city;
    //     $postcode = get_user_meta($current_user_id, 'billing_address_2', true);
    //     $postcode = !empty($cardholderPostcode) ? $cardholderPostcode : $postcode;
    //     $state = get_user_meta($current_user_id, 'billing_state', true);
    //     $country = get_user_meta($current_user_id, 'billing_country', true);
    //     $country = empty($country) ? "IL" : $country;
    //     // Get the user's ACF lock_profile field value
    //     $lock_profile = get_field('lock_profile', 'user_' . $current_user_id);

    //     $update_order = true; // Default value

    //     if ($lock_profile === true) {
    //         $update_order = false;
    //     }

    //     $display_name = $current_user->display_name;
    //     if ($display_name != $cardholderName) {
    //         $first_name = $this->ml_split_name($cardholderName, 'first');
    //         $last_name = $this->ml_split_name($cardholderName, 'last');
    //     }

    //     $customerInfo = array(
    //         'first_name' => $first_name,
    //         'last_name' => $last_name,
    //         'name' => $cardholderName,
    //         'company' => $company,
    //         'email' => $cardholderEmail,
    //         'phone' => $cardholderPhone,
    //         'address_1' => $cardholderAdress,
    //         'city' => $city,
    //         'state' => $state,
    //         'address_2' => $postcode,
    //         'country' => $country
    //     );

    //     $order_data = array(
    //         "products" => $product_list,
    //         "customerInfo" => $customerInfo,
    //         "cardNumber" => $cardNumber,
    //         "response" => $order_response_data,
    //         "extraMeta" => $extraMeta,
    //         "update" => $update_order,
    //         "note" => $note,
    //         "user_id" => $current_user_id
    //     );

    //     // error_log( print_r( $body, true ) );
    //     // error_log( print_r( $order_data, true ) );

    //     $failed_popup = $this->popup_failed_markup();

    //     $is_test_mode = get_option("ml_add_test_mode_for_api");

    //     $is_valid_condition = !is_wp_error($request) && wp_remote_retrieve_response_code($request) == 200 && $message !== "Accepted";
    //     if ($is_test_mode === 'on') {
    //         $is_valid_condition = !is_wp_error($request) && $message !== "Accepted";
    //         $order_data['response']['referenceID'] = '56555545411';
    //         $order_data['response']['token'] = 'skdjfdsfsdf41exesdf';
    //     }

    //     if ($is_valid_condition) {

    //         // first create order
    //         $order_obj = ml_create_order($order_data);
    //         // error_log( print_r( $order_obj, true ) );
    //         $order_id = $order_obj['order_id'];
    //         $order_info = $order_obj['order_info'];

    //         $success_popup = $this->popup_success_markup($order_id);
	// 		//TODO: Enable this when OM is Live
	// 		$this->send_order_to_other_domain($order_id, $current_user_id);

    //         // Clear the cart
    //         WC()->cart->empty_cart();

    //         wp_send_json_success(
    //             json_encode(
    //                 array(
    //                     "message_type" => 'api',
    //                     "result_popup" => $success_popup,
    //                     "order_info" => $order_info,
    //                     "message" => "Successfully products added to order #$order_id"
    //                 )
    //             )
    //         );

    //         wp_die();
    //     }

    //     $error_message = "Something went wrong";
    //     if (is_wp_error($request)) {
    //         $error_message = $request->get_error_message();
    //     }

    //     if ("Accepted" === $message) {
    //         $error_message = "Unable to reach the api server";
    //     }

    //     if (!empty($response_obj) && isset($response_obj['returnMessage']) && !empty($response_obj['returnMessage'])) {
    //         $error_message = $response_obj['returnMessage'];
    //     }

    //     // error_log( print_r( $response_obj, true ) );

    //     // error_log( print_r( $error_message, true ) );
    //     wp_send_json_error(
    //         json_encode(
    //             array(
    //                 "body" => $body,
    //                 "message_type" => 'api',
    //                 "message" => $error_message,
    //                 "server_message" => $message,
    //                 "result_popup" => $failed_popup,
    //                 "server_body_obj" => $response_obj
    //             )
    //         )
    //     );

    //     wp_die();
    // }


    /**
     * zCredit direct payment
     * AJAX handler for submitting the checkout form and creating a WebCheckout session with ZCredit API.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function ml_send_card()
    {
        check_ajax_referer('aum_ajax_nonce', 'nonce');

        if (WC()->cart->get_cart_contents_count() == 0) {
            wp_send_json_error(
                array(
                    "message_type" => 'reqular',
                    "message" => "Cart is empty."
                )
            );
            wp_die();
        }

        $proof_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? intval($_POST['user_id']) : '';
        $first_name = isset($_POST['first_name']) && !empty($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) && !empty($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $cardholderName = isset($_POST['userName']) && !empty($_POST['userName']) ? sanitize_text_field($_POST['userName']) : '';
        $cardholderPhone = isset($_POST['userPhone']) && !empty($_POST['userPhone']) ? sanitize_text_field($_POST['userPhone']) : '';
        $cardholderAdress = isset($_POST['userAdress']) && !empty($_POST['userAdress']) ? sanitize_text_field($_POST['userAdress']) : '';
        $cardholderAdressNumber = isset($_POST['userAdressNumber']) && !empty($_POST['userAdressNumber']) ? sanitize_text_field($_POST['userAdressNumber']) : '';
        $cardholderEmail = isset($_POST['userEmail']) && !empty($_POST['userEmail']) ? sanitize_text_field($_POST['userEmail']) : '';
        $cardholderCity = isset($_POST['userCity']) && !empty($_POST['userCity']) ? sanitize_text_field($_POST['userCity']) : '';
        $note = isset($_POST['note']) && !empty($_POST['note']) ? sanitize_text_field($_POST['note']) : '';
        $cardholderInvoiceName = isset($_POST['userInvoiceName']) && !empty($_POST['userInvoiceName']) ? sanitize_text_field($_POST['userInvoiceName']) : '';

        $current_user_id = $proof_id;

        if (
            empty($cardholderName) ||
            empty($cardholderPhone) ||
            empty($cardholderAdress) ||
            empty($cardholderEmail) ||
            empty($cardholderCity)
        ) {
            wp_send_json_error(
                array(
                    "message_type" => 'reqular',
                    "message" => esc_html__("Required fields are empty. Please fill all the fields.", "hello-elementor")
                )
            );
            wp_die();
        }

        if (!is_email($cardholderEmail)) {
            wp_send_json_error(
                array(
                    "message_type" => 'reqular',
                    "message" => esc_html__("Please enter a valid email address.", "hello-elementor")
                )
            );
            wp_die();
        }

        // Prepare product details and cart items

        $cart_filter_data = [];
        $product_list = [];
        WC()->cart->calculate_totals();
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = wc_get_product($cart_item['product_id']);

            $cart_filter_data[$cart_item_key]["title"] = $_product->get_title();
            $cart_filter_data[$cart_item_key]["price"] = (int) $cart_item['data']->get_price();
            if (isset($cart_item['alarnd_size']) && !empty($cart_item['alarnd_size'])) {
                $cart_filter_data[$cart_item_key]["size"] = $cart_item['alarnd_size'];
            }
            if (isset($cart_item['alarnd_color']) && !empty($cart_item['alarnd_color'])) {
                $cart_filter_data[$cart_item_key]["color"] = $cart_item['alarnd_color'];
            }
            if (isset($cart_item['quantity']) && !empty($cart_item['quantity'])) {
                $cart_filter_data[$cart_item_key]["quantity"] = $cart_item['quantity'];
                $cart_filter_data[$cart_item_key]["total_price"] = (int) $cart_item['quantity'] * (int) $cart_item['data']->get_price();
            }

            if (isset($cart_item['art_item_title'])) {
                $cart_filter_data[$cart_item_key]['artwork_position'] = $cart_item['art_item_title'];
            }
            if (isset($cart_item['artwork_logos'])) {
                $cart_filter_data[$cart_item_key]['artwork_logos'] = $cart_item['artwork_logos'];
            }

            if ($_product->is_type('variable')) {

            }

            $single_product_item = array(
                "product_id" => $cart_item['product_id'],
                "quantity" => $cart_item['quantity']
            );

            $single_product_item["price"] = $cart_item['data']->get_price();

            if (isset($cart_item['alarnd_size']) && !empty($cart_item['alarnd_size'])) {
                $single_product_item["size"] = $cart_item['alarnd_size'];
            }
            if (isset($cart_item['alarnd_color']) && !empty($cart_item['alarnd_color'])) {
                $single_product_item["color"] = $cart_item['alarnd_color'];
            }
            if (isset($cart_item['alarnd_color_key'])) {
                $single_product_item['alarnd_color_key'] = $cart_item['alarnd_color_key'];
            }
            if (isset($cart_item['alarnd_custom_color'])) {
                $single_product_item['alarnd_custom_color'] = $cart_item['alarnd_custom_color'];
            }
            if (isset($cart_item['art_item_title'])) {
                $single_product_item['art_item_title'] = $cart_item['art_item_title'];
            }

            if (isset($cart_item['default_dark_logo'])) {
                $single_product_item['default_dark_logo'] = $cart_item['default_dark_logo'];
            }
            if (isset($cart_item['alarnd_artwork_id'])) {
                $single_product_item['alarnd_artwork_id'] = $cart_item['alarnd_artwork_id'];
            }
            if (isset($cart_item['alarnd_artwork_id2'])) {
                $single_product_item['alarnd_artwork_id2'] = $cart_item['alarnd_artwork_id2'];
            }
            if (isset($cart_item['alarnd_step_key'])) {
                $single_product_item['alarnd_step_key'] = $cart_item['alarnd_step_key'];
            }
            if (isset($cart_item['artwork_logos'])) {
                $single_product_item['artwork_logos'] = $cart_item['artwork_logos'];
            }

            $product_list[] = $single_product_item;
        }
        WC()->cart->calculate_totals();

        $extraMeta = [];
        $extraMeta['invoice'] = $cardholderInvoiceName;
        $extraMeta['city'] = $cardholderCity;

        if (!empty($cardholderName)) {
            $first_name = $this->ml_split_name($cardholderName, 'first');
            $last_name = $this->ml_split_name($cardholderName, 'last');
        }

        $customerInfo = array(
            'customer_name' => $cardholderName,
            'invoice_name' => $cardholderInvoiceName,
            'customer_email' => $cardholderEmail,
            'customer_phone' => $cardholderPhone,
            'customer_address' => $cardholderAdress,
            'customer_address_number' => $cardholderAdressNumber,
            'customer_city' => $cardholderCity
        );

        $shippingInfo = array(
            'company' => $cardholderInvoiceName,
            'email' => $cardholderEmail,
            'phone' => $cardholderPhone,
            'address_1' => $cardholderAdress,
            'address_2' => $cardholderAdressNumber,
            'city' => $cardholderCity,
            'first_name' => $first_name,
            'last_name' => $last_name
        );
		
		$applied_coupons = WC()->cart->get_applied_coupons();

        $chosen_shipping_method = ml_get_shipping_data('method');
		$shipping_method_info = array();

        // Set shipping method
        if (!empty($chosen_shipping_method)) {
            $shipping_cost = ml_get_shipping_data('cost');
            $shipping_title = ml_get_shipping_data();

            $shipping_method_info = array(
                'id' => $chosen_shipping_method,
                'cost' => $shipping_cost,
                'title' => $shipping_title
            );
        }

        $order_data = array(
            "products" => $product_list,
            "customerInfo" => $customerInfo,
            "extraMeta" => $extraMeta,
            "shippingInfo" => $shippingInfo,
			'applied_coupons' => $applied_coupons,
            'shipping_method_info' => $shipping_method_info,
            "update" => true,
            "note" => $note,
            "user_id" => $current_user_id
        );

        session_start();
        $_SESSION['order_data'] = ['order_data' => $order_data];
        
        // Step 1: Create WebCheckout Session with ZCredit API
        $zcredit_data = $this->create_zcredit_webcheckout_session(WC()->cart->total, $cart_filter_data, [
            'email' => $cardholderEmail,
            'name' => $cardholderName,
            'phone' => $cardholderPhone
        ], $shipping_method_info['cost']);

        $webcheckout_url = isset( $zcredit_data['SessionUrl'] ) && !empty( $zcredit_data['SessionUrl'] ) ? $zcredit_data['SessionUrl'] : '';
        $session_id = isset( $zcredit_data['SessionId'] ) && !empty( $zcredit_data['SessionId'] ) ? $zcredit_data['SessionId'] : '';

        if ($webcheckout_url) {

            // Generate a unique key (like session ID or user ID)
            $transient_unique_key = 'order_data_' . $session_id;
            
            // Store order data in transient
            set_transient($transient_unique_key, $order_data, 60 * 60); // 1 hour expiration

            // Step 2: Send JSON response with success and redirect URL
            wp_send_json_success(
                array(
                    "message_type" => 'api',
                    "payment_url" => $webcheckout_url,
                    "message" => "Redirecting to payment gateway..."
                )
            );
            wp_die();
        }

        // If creating the session fails, return error
        wp_send_json_error(
            array(
                "message_type" => 'api',
                "message" => "Failed to create payment session. Please try again."
            )
        );
        wp_die();
    }

    /**
     * zCredit direct payment
     * Create a ZCredit WebCheckout session for a given order and cart.
     * 
     * @param float $total_amount The total amount of the order.
     * @param array $cart_data The cart items and their details.
     * @param array $customer_data The customer's details.
     * 
     * @return array|false If successful, returns an array with the SessionUrl and SessionId.
     *                    If failed, returns false.
     */
    private function create_zcredit_webcheckout_session($total_amount, $cart_data, $customer_data, $shipping_cost = 0) {
        // ZCredit WebCheckout API URL
        $url = "https://pci.zcredit.co.il/webcheckout/api/WebCheckout/CreateSession";
        
        // Your ZCredit credentials
        $credentials = [
            'terminalId' => '2669593010',
            'username' => '516185543',
            'password' => '514951dc7de'
        ];
    
        // Prepare customer data
        $customer = [
            'Email' => $customer_data['email'],
            'Name' => $customer_data['name'],
            'PhoneNumber' => $customer_data['phone'],
            'Attributes' => [
                'HolderId' => 'none',
                'Name' => 'required',
                'PhoneNumber' => 'required',
                'Email' => 'optional'
            ]
        ];
    
        // Prepare cart items
        $cart_items = [];
        foreach ($cart_data as $item) {
            $cart_items[] = [
                'Amount' => number_format($item['price'], 2, '.', ''), // Format amount as string
                'Currency' => 'ILS',
                'Name' => $item['title'],
                'Description' => 'Item description', // Customize as needed
                'Quantity' => $item['quantity'],
                'Image' => '', // Optionally include an image URL
                'IsTaxFree' => 'false',
                'AdjustAmount' => 'false'
            ];
        }

        // Add shipping as an additional item in CartItems
        if ($shipping_cost > 0) {
            $cart_items[] = [
                'Amount' => number_format($shipping_cost, 2, '.', ''),
                'Currency' => 'ILS',
                'Name' => 'Shipping',
                'Description' => 'Shipping Charge',
                'Quantity' => 1,
                'IsTaxFree' => 'true',
                'AdjustAmount' => 'false'
            ];
        }

        $session_id = '';
    
        $home_url = esc_url( get_home_url() );
        $success_url = "$home_url/success";
    
        // Prepare the body based on the structure you provided
        $postData = [
            'Key' => 'd36980eacea1b5a33527c8ed551a8686146775de06726146800e50ec928d7cee',  // Can be left empty if not needed
            'Local' => 'He',  // Hebrew language (or 'En' for English)
            'UniqueId' => uniqid(),  // Unique transaction ID
            'SuccessUrl' => $success_url,
            'CancelUrl' => "$home_url/failure",
            'CallbackUrl' => "$home_url/wp-json/flash-sale/v1/get-payout-status",
            'PaymentType' => 'regular',
            'CreateInvoice' => 'false',
            'AdditionalText' => '',
            'ShowCart' => 'false',
            'ThemeColor' => '005ebb',
            'BitButtonEnabled' => 'true',
            'ApplePayButtonEnabled' => 'true',
            'GooglePayButtonEnabled' => 'true',
            'Customer' => $customer,
            'CartItems' => $cart_items,
            'FocusType' => 'None',
            'CardsIcons' => [
                'ShowVisaIcon' => 'true',
                'ShowMastercardIcon' => 'true',
                'ShowDinersIcon' => 'true',
                'ShowAmericanExpressIcon' => 'true',
                'ShowIsracardIcon' => 'true'
            ],
            'IssuerWhiteList' => [1, 2, 3, 4, 5, 6],
            'BrandWhiteList' => [1, 2, 3, 4, 5, 6],
            'UseLightMode' => 'false',
            'UseCustomCSS' => 'false',
            'BackgroundColor' => 'FFFFFF',
            'ShowTotalSumInPayButton' => 'true',
            'ForceCaptcha' => 'false',
            'CustomCSS' => '',
            'Bypass3DS' => 'false'
        ];
    
        // Use WP HTTP API to send request
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($credentials['username'] . ':' . $credentials['password']),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($postData),
            'method' => 'POST',
            'data_format' => 'body',
            'timeout' => 15,
        ));
    
        if (is_wp_error($response)) {
            return false; // Handle error
        }
    
        $response_body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($response_body, true);
        
        error_log( "zcredit_decoded_body" );
        error_log(print_r($decoded_body, true));
    
        if (isset($decoded_body['Data']['SessionUrl'])) {
            // Reassign the session ID from the decoded body to the variable
            $session_id = $decoded_body['Data']['SessionId'];

            // Reassign the SuccessUrl to include the SessionId
            $decoded_body['Data']['SuccessUrl'] = "$home_url/success?SessionId=$session_id";
            
            // Return the URL for the payment iframe
            return array(
                'SessionUrl' => $decoded_body['Data']['SessionUrl'],
                'SessionId' => $decoded_body['Data']['SessionId']
            );
        } else {
            // Handle error in response
            return false;
        }
    }


    /**
     * zCredit direct payment
     * Handles the ZCredit callback and creates a WooCommerce order based on the session data.
     *
     * @return void
     */
    public function zcredit_callback_handler()
    {
        // Get the SessionId from the AJAX request
        $session_id = isset($_POST['SessionId']) ? sanitize_text_field($_POST['SessionId']) : '';

        if (empty($session_id)) {
            wp_send_json_error(['message' => 'Invalid SessionId.']);
            return; // Stop execution
        }

        // Normally, you would check the transaction status here, but it's assumed that ZCredit already sent you the transaction status in the redirect or callback.
        // Assuming the transaction is successful if you reach here.

        // Extract necessary customer and order data stored in the session, cookies, or database (this should have been set during the ml_send_card process).
        $order_data = $_SESSION['order_data'];

        // Extract customer and shipping info
        $customerInfo = $order_data['customerInfo'];
        $shippingInfo = $order_data['shippingInfo'];

        // Create order data array
        $order_data = array(
            "products" => $order_data['products'],
            "customerInfo" => $customerInfo,
            "response" => [
                'SessionId' => $session_id
            ],
            "extraMeta" => $order_data['extraMeta'],
            "shippingInfo" => $shippingInfo,
            "update" => true,
            "note" => $order_data['note'],
            "user_id" => $order_data['user_id']
        );

        // First, create the WooCommerce order
        $order_obj = ml_create_order($order_data);

        if ($order_obj) {
            $order_id = $order_obj['order_id'];
            $order_info = $order_obj['order_info'];

            // Success popup and webhook notification
            $success_popup = $this->popup_success_markup($order_id);
            $this->send_order_to_other_domain($order_id, $order_data['user_id']);

            // Clear the WooCommerce cart
            WC()->cart->empty_cart();

            wp_send_json_success([
                "result_popup" => $success_popup,
                "message" => "Order successfully created.",
                "order_id" => $order_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Order creation failed.']);
        }

        wp_die();
    }

    /**
     * Order Management Send Order Details.
     */
    public function send_order_to_other_domain($order_id, $user_id)
    {
        // Check if user_id is provided
        if (!$user_id) {
            error_log('User ID not provided for order: ' . $order_id);
            return;
        }

        // Ensure the order object is retrieved
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('Order not found: ' . $order_id);
            return;
        }

        // Check if the order has already been sent
        $order_already_sent = get_post_meta($order_id, '_order_sent_to_management', true);

        if ($order_already_sent) {
            // If the order has already been sent, don't send it again
            error_log('Order ' . $order_id . ' has already been sent. Skipping...');
            return;
        }


        // Process order data
        $this->process_order_data($order, $user_id);

        // Mark the order as sent
        update_post_meta($order_id, '_order_sent_to_management', true);
    }

    public function process_order_data($order, $user_id)
    {
        // Get the order items
        $items = $order->get_items();
        $orderItems = array();

        if (empty($items)) {
            error_log('No items found in order: ' . $order->get_id());
            return; // Return early if there are no items in the order
        } else {
            foreach ($items as $item_id => $item) {
                $product = $item->get_product();
                if ($product) {
                    $mockup_thumbnail = '';
                    $attachment_meta = $item->get_meta('קובץ מצורף') ?: $item->get_meta('Attachment');

                    // Check if the meta exists and extract the URL
                    if ($attachment_meta) {
                        preg_match('/<img[^>]+src="([^">]+)"/', $attachment_meta, $matches);
                        if (!empty($matches[1])) {
                            $mockup_thumbnail = $matches[1];
                        }
                    }
					
                    $orderItems[] = array(
                        'id' => $item->get_id(),
                        'item_id' => $item->get_id(),
                        'product_id' => $product->get_id(),
                        'product_name' => $product->get_name(),
                        'quantity' => $item->get_quantity(),
                        'total' => $item->get_total(),
                        'mockup_thumbnail' => $mockup_thumbnail,
                    	'printing_note' => "",
                        // Add other item data here
                    );
                }
            }
        }

        // Extract shipping lines
        $shipping_lines = array();
        foreach ($order->get_items('shipping') as $shipping_item_id => $shipping_item) {
            $shipping_lines[] = array(
                'method_id' => $shipping_item->get_method_id(),
                'method_title' => $shipping_item->get_name(),
                'total' => $shipping_item->get_total(),
            );
        }

        // Get the order totoal
        $order_total = $order->get_total();

        // get date created
        $date_created = $order->get_date_created();

        // Get user profile picture URLs
        $profile_picture_id = get_field('profile_picture_id', 'user_' . $user_id);
        $profile_picture_id_second = get_field('profile_picture_id_second', 'user_' . $user_id);

        if (!empty($profile_picture_id)) {
            $profile_picture_url = wp_get_attachment_url($profile_picture_id);
        }

        if (!empty($profile_picture_id_second)) {
            $profile_picture_url_second = wp_get_attachment_url($profile_picture_id_second);
        }

        // Get the order data
        $orderData = array(
            'order_number' => $order->get_order_number(),
            'order_id' => $order->get_id(),
            'minisite_id' => $user_id,
            'order_status' => $order->get_status(),
            'date_created' => $date_created->date('Y-m-d H:i:s'),
            'shipping_lines' => $shipping_lines,
            'items' => $orderItems,
            'billing' => $order->get_address('billing'),
            'shipping' => $order->get_address('shipping'),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'total' => $order_total,
            'customer_note' => $order->get_customer_note(),
            'site_url' => get_site_url(),
            'order_source' => 'miniSite_order',
            'lighter_logo' => $profile_picture_url,
            'dark_logo' => $profile_picture_url_second,
            // Add other order data here
        );

        // error_log(print_r($orderData, true));

        // Username and Password for Basic Authentication
        $username = 'OmAdmin';

        $api_url = '';
        // Determine if the request is from localhost or live site
        $is_localhost = strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;
        if ($is_localhost) {
            $password = 'Qj0p rsPu eU2i Fzco pwpX eCPD';
            $api_url = 'https://ordermanage.test/wp-json/manage-order/v1/create';
        } else {
            // TODO: For Staging
            // $password = 'vZmm GYw4 LKDg 4ry5 BMYC 4TMw';
            // $api_url = 'https://om.lukpaluk.xyz/wp-json/manage-order/v1/create';
            // For Live
            $password = 'Vlh4 F7Sw Zu26 ShUG 6AYu DuRI';
            $api_url = 'https://om.allaround.co.il/wp-json/manage-order/v1/create';
        }

        $auth_header = $this->get_basic_auth_header($username, $password);

        // Send the order data to the other domain
        $response = wp_remote_post(
            $api_url,
            array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Authorization' => $auth_header
                ),
                'body' => json_encode($orderData),
                'sslverify' => false
            )
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("Something went wrong: $error_message");
        } else {
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            error_log('Response: ' . print_r($response_body, true));
        }
    }

    private function get_basic_auth_header($username, $password)
    {
        $auth = base64_encode("$username:$password");
        return 'Basic ' . $auth;
    }



    public function ml_customer_details()
    {
        check_ajax_referer('aum_ajax_nonce', 'nonce');

        $user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? absint($_POST['user_id']) : '';
        $userName = isset($_POST['userName']) && !empty($_POST['userName']) ? sanitize_text_field($_POST['userName']) : '';
        $userPhone = isset($_POST['userPhone']) && !empty($_POST['userPhone']) ? sanitize_text_field($_POST['userPhone']) : '';
        $userAdress = isset($_POST['userAdress']) && !empty($_POST['userAdress']) ? sanitize_text_field($_POST['userAdress']) : '';
        $userPostcode = isset($_POST['userPostcode']) && !empty($_POST['userPostcode']) ? sanitize_text_field($_POST['userPostcode']) : '';
        $userEmail = isset($_POST['userEmail']) && !empty($_POST['userEmail']) ? sanitize_text_field($_POST['userEmail']) : '';
        $userCity = isset($_POST['userCity']) && !empty($_POST['userCity']) ? sanitize_text_field($_POST['userCity']) : '';
        $userInvoiceName = isset($_POST['userInvoiceName']) && !empty($_POST['userInvoiceName']) ? sanitize_text_field($_POST['userInvoiceName']) : '';

        $current_user_id = $user_id;
        $current_email = get_userdata($current_user_id)->user_email;

        $gonna_update = false;

        // if user then only allow update
        if (is_user_logged_in()) {
            $gonna_update = true;
        }

        $invalid_inputs = [];

        if (empty($userName)) {
            $invalid_inputs['userName'] = esc_html__("Please enter your full name.", "hello-elementor");
        }
        if (empty($userPhone)) {
            $invalid_inputs['userPhone'] = esc_html__("Please enter a valid phone number.", "hello-elementor");
        }
        if (empty($userAdress)) {
            $invalid_inputs['userAdress'] = esc_html__("Please provide your street address.", "hello-elementor");
        }
        if (empty($userPostcode)) {
            $invalid_inputs['userPostcode'] = esc_html__("Please provide your address number.", "hello-elementor");
        }
        if (empty($userCity)) {
            $invalid_inputs['userCity'] = esc_html__("Please provide your city.", "hello-elementor");
        }
        // if( empty( $userInvoiceName ) ) {
        //     $invalid_inputs['userInvoiceName'] = esc_html__("Please enter the invoice number.", "hello-elementor");
        // }
        if (
            empty($userEmail) ||
            !is_email($userEmail)
        ) {
            $invalid_inputs['userEmail'] = esc_html__("Please provide a valid email address.", "hello-elementor");
        }

        if (
            $gonna_update === true &&
            $current_email != $userEmail &&
            email_exists($userEmail)
        ) {
            $invalid_inputs['userEmail'] = esc_html__("This email address is already in use.", "hello-elementor");
        }

        if (
            !empty($invalid_inputs)
        ) {
            wp_send_json_error($invalid_inputs);
            wp_die();
        }

        if ($gonna_update === true) {
            $phoneNumber = ml_get_phone_no($userPhone);
            $countryCode = ml_get_country_code();

            // update phone
            update_user_meta_if_different($current_user_id, 'xoo_ml_phone_code', $countryCode);
            update_user_meta_if_different($current_user_id, 'xoo_ml_phone_no', $phoneNumber);

            update_acf_anyway($current_user_id, 'invoice', $userInvoiceName);

            // WcooCommerce user field update
            update_user_meta_if_different($current_user_id, 'billing_address_1', $userAdress);
            update_user_meta_if_different($current_user_id, 'billing_address_2', $userPostcode);
            update_user_meta_if_different($current_user_id, 'billing_phone', $userPhone);
            update_user_meta_if_different($current_user_id, 'billing_city', $userCity);

            // Email address
            update_user_email_if_different($current_user_id, $userEmail);

            // Display Name
            update_user_name_if_different($current_user_id, $userName);
        }

        if (empty($userInvoiceName)) {
            // $current_value = get_field('invoice', "user_{$current_user_id}");
            // if ( ! empty($current_value) ) {
            //     $userInvoiceName = $current_value;
            // }
            $userInvoiceName = $userName;
        }

        ?>
        <div class="alarnd--payout-col alarnd--details-previewer">
            <h3><?php esc_html_e('כתובת למשלוח', 'hello-elementor'); ?></h3>
            <div class="tokenized_inv_name_cont"><?php esc_html_e('חשבונית על שם', 'hello-elementor'); ?>:<p
                    class="tokenized_user_name"><?php echo $userInvoiceName ?></p>
            </div>

            <div class="alarnd--user-address">
                <div class="alarnd--user-address-wrap">
                    <?php echo !empty($userName) ? '<p>' . esc_html($userName) . '</p>' : ''; ?>
                    <?php echo !empty($userPhone) ? '<p>' . esc_html($userPhone) . '</p>' : ''; ?>
                    <?php echo !empty($userEmail) ? '<p>' . esc_html($userEmail) . '</p>' : ''; ?>
                    <p>
                        <?php echo !empty($userAdress) ? '<span>' . esc_html($userAdress) . ', </span>' : ''; ?>
                        <?php echo !empty($userPostcode) ? '<span>' . esc_html($userPostcode) . ', </span>' : ''; ?>
                        <?php echo !empty($userCity) ? '<span>' . esc_html($userCity) . '</span>' : ''; ?>
                    </p>
                </div>
                <span class="alarnd--user_address_edit"><?php esc_html_e('שינוי', 'hello-elementor'); ?></span>
            </div>
        </div>
        <?php
        wp_die();
    }


    function add_variation_to_cart()
    {

        check_ajax_referer('aum_ajax_nonce', 'nonce');

        $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($_POST['product_id']));
        $quantity = empty($_POST['quantity']) ? 1 : wc_stock_amount($_POST['quantity']);
        $user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? intval($_POST['user_id']) : '';
        $cart_item_data = [];
        if (!is_user_logged_in() && !empty($user_id)) {
            $cart_item_data['user_id'] = $user_id;
        }

        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : '';
        $variations = !empty($_POST['variation']) ? (array) $_POST['variation'] : '';

        $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations);

        if ($passed_validation && WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations, $cart_item_data)) {

            do_action('woocommerce_ajax_added_to_cart', $product_id);

            // Return fragments
            WC_AJAX::get_refreshed_fragments();

        } else {

            // If there was an error adding to the cart, redirect to the product page to show any errors
            $data = array(
                'error' => true,
                'product_url' => apply_filters('woocommerce_cart_redirect_after_error', get_permalink($product_id), $product_id)
            );

            wp_send_json($data);

        }

        die();
    }

    function add_simple_to_cart()
    {

        check_ajax_referer('aum_ajax_nonce', 'nonce');

        $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($_POST['product_id']));
        $quantity = empty($_POST['quantity']) ? 1 : wc_stock_amount($_POST['quantity']);
        $user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? intval($_POST['user_id']) : '';
        $cart_item_data = [];
        if (!is_user_logged_in() && !empty($user_id)) {
            $cart_item_data['user_id'] = $user_id;
        }

        if (WC()->cart->add_to_cart($product_id, $quantity, '', '', $cart_item_data)) {

            do_action('woocommerce_ajax_added_to_cart', $product_id);

            // Return fragments
            WC_AJAX::get_refreshed_fragments();

        } else {

            // If there was an error adding to the cart, redirect to the product page to show any errors
            $data = array(
                'error' => true,
                'product_url' => apply_filters('woocommerce_cart_redirect_after_error', get_permalink($product_id), $product_id)
            );

            wp_send_json($data);

        }

        die();
    }

    function get_woocommerce_cart_ajax()
    {
        // Get the updated cart content using the [cart] shortcode
        echo do_shortcode('[woocommerce_cart]');
        die();
    }

    public function ml_add_to_cart()
    {
        check_ajax_referer('aum_ajax_nonce', 'nonce');

        $product_id = isset($_POST['product_id']) && !empty($_POST['product_id']) ? intval($_POST['product_id']) : '';
        $user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? intval($_POST['user_id']) : '';

        $cart_item_data = [];
        if (!is_user_logged_in() && !empty($user_id)) {
            $cart_item_data['user_id'] = $user_id;
        }

        $product = wc_get_product($product_id);

        $ml_type = isset($_POST['ml_type']) && !empty($_POST['ml_type']) ? sanitize_text_field($_POST['ml_type']) : '';
        $group_enable = get_field('group_enable', $product->get_id());
        $custom_quanity = get_field('enable_custom_quantity', $product->get_id());

        $colors = get_field('color', $product->get_id());

        $alarnd__color = (isset($_POST['alarnd__color']) && !empty($_POST['alarnd__color'])) ? sanitize_text_field($_POST['alarnd__color']) : '';
        $alarnd__sizes = (isset($_POST['alarnd__size']) && !empty($_POST['alarnd__size'])) ? $_POST['alarnd__size'] : '';
        $alarnd__color_qty = (isset($_POST['alarnd__color_qty']) && !empty($_POST['alarnd__color_qty'])) ? $_POST['alarnd__color_qty'] : '';
        $get_total_qtys = ml_get_total_qty($alarnd__color_qty);


        $data = $_POST;

        if (
            $ml_type === 'quantity' &&
            "simple" === $product->get_type()
        ) {

            $quantity = isset($_POST['quantity']) && !empty($_POST['quantity']) ? intval($_POST['quantity']) : '';

            $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);

            // Check if product is already in the cart
            $cart_item_key = '';
            $cart_item_qty = '';

            // Check if product is already in the cart
            foreach (WC()->cart->get_cart() as $key => $cart_item) {
                if ($cart_item['product_id'] == $product_id) {
                    $cart_item_key = $key;
                    $cart_item_qty = $cart_item['quantity'];
                    break;
                }
            }

            // error_log( print_r($_POST, true) );

            if ($cart_item_key) {
                // Product already exists in the cart, increase quantity
                WC()->cart->set_quantity($cart_item_key, (int) $cart_item_qty + (int) $quantity);
                WC_AJAX::get_refreshed_fragments();
            } else {
                if ($passed_validation && WC()->cart->add_to_cart($product_id, $quantity, '', '', $cart_item_data)) {
                    do_action('woocommerce_ajax_added_to_cart', $product_id);

                    // Return fragments
                    WC_AJAX::get_refreshed_fragments();
                } {
                    wp_send_json(
                        array(
                            "success" => true,
                            "message" => "Something wen't wrong when trying to add product #$product_id"
                        )
                    );
                }
            }


        } elseif (
            $ml_type === 'group' &&
            'simple' === $product->get_type()
        ) {

            $this->group_add_to_cart($product, $data);

            do_action('woocommerce_ajax_added_to_cart', $product_id);

            // Return fragments
            WC_AJAX::get_refreshed_fragments();
        }

        wp_die();

        die();
    }

    public function group_add_to_cart($product, $data)
    {

        $colors = get_field('color', $product->get_id());
        $product_id = $product->get_id();

        $alarnd__color = (isset($_POST['alarnd__color']) && !empty($_POST['alarnd__color'])) ? sanitize_text_field($_POST['alarnd__color']) : '';
        $alarnd__sizes = (isset($_POST['alarnd__size']) && !empty($_POST['alarnd__size'])) ? $_POST['alarnd__size'] : '';
        $alarnd__color_qty = (isset($_POST['alarnd__color_qty']) && !empty($_POST['alarnd__color_qty'])) ? $_POST['alarnd__color_qty'] : '';
        $get_total_qtys = ml_get_total_qty($alarnd__color_qty);

        $user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? intval($_POST['user_id']) : '';

        $cart_item_data = [];
        if (!is_user_logged_in() && !empty($user_id)) {
            $cart_item_data['user_id'] = $user_id;
        }

        $alarnd__group_id = (isset($_POST['alarnd__group_id']) && !empty($_POST['alarnd__group_id'])) ? $_POST['alarnd__group_id'] : '';

        $group_enable = get_field('group_enable', $product->get_id());
        $custom_quanity = get_field('enable_custom_quantity', $product->get_id());
        $colors = get_field('color', $product->get_id());
        $sizes = get_field('size', $product->get_id());

        $cart_content = WC()->cart->cart_contents;

        $old_qty_total = 0;

        if ($old_qty_total > 0) {
            $get_total_qtys = $get_total_qtys + $old_qty_total;
        }

        if (
            'simple' === $product->get_type() &&
            !empty($group_enable) &&
            !empty($alarnd__color_qty)
        ) {

            $same_color_exists = [];
            foreach ($alarnd__color_qty as $color_key => $item) {
                foreach ((array) $item as $i_size => $i_qty) {
                    if (empty($i_qty)) {
                        continue;
                    }

                    // check if product_id already exists in cart with color_hex_code & size
                    // then do not add to the cart but add quantity that already cart item
                    $skip_item = false;
                    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                        if (
                            $cart_item['product_id'] === $product_id &&
                            isset($cart_item['alarnd_color_hex']) &&
                            isset($cart_item['alarnd_size']) &&
                            $cart_item['alarnd_color_hex'] === $colors[$color_key]['color_hex_code'] &&
                            $cart_item['alarnd_size'] === $i_size
                        ) {
                            WC()->cart->cart_contents[$cart_item_key]['quantity'] += $i_qty;
                            WC()->cart->cart_contents[$cart_item_key]['alarnd_quantity'] += $i_qty;
                            $skip_item = true;
                            continue;
                        }
                    }

                    if (true !== $skip_item) {
                        $cart_item_meta = array();
                        $cart_item_meta['alarnd_color'] = $colors[$color_key]['title'];
                        $cart_item_meta['alarnd_color_hex'] = $colors[$color_key]['color_hex_code'];
                        $cart_item_meta['alarnd_color_key'] = $color_key;
                        $cart_item_meta['alarnd_size'] = $i_size;
                        $cart_item_meta['alarnd_group_qty'] = $get_total_qtys;
                        $cart_item_meta['alarnd_quantity'] = $i_qty;
                        $cart_item_meta['user_id'] = $user_id;
                        $cart_item_meta['alarnd_group_id'] = $alarnd__group_id;

                        // error_log( print_r( $cart_item_meta, true ) );
                        WC()->cart->add_to_cart($product->get_id(), (int) $i_qty, '', '', $cart_item_meta);
                    }
                }
            }

        }


    }

    /**
     * Get product select options
     *
     * @return void
     */
    public function get_item_selector()
    {

        check_ajax_referer('aum_ajax_nonce', 'nonce');

        $product_id = isset($_POST['product_id']) && !empty($_POST['product_id']) ? intval($_POST['product_id']) : '';

        if (empty($product_id)) {
            wp_die('Product_id is empty');
        }

        $product = wc_get_product($product_id);

        $group_enable = get_field('group_enable', $product->get_id());
        $custom_quanity = get_field('enable_custom_quantity', $product->get_id());

        if ("simple" === $product->get_type() && !empty($custom_quanity)) {
            $this->get_quantity_product_meta($product);
        } elseif ("simple" === $product->get_type() && !empty($group_enable)) {
            $this->get_group_product_meta($product);
        } else {
            $this->get_other_product_cart($product);
        }

        wp_die();
    }

    public function get_other_product_cart($product)
    {
        ?>
        <div id="ml--product_id-<?php echo $product->get_id(); ?>"
            class="white-popup-block alarnd--variable-modal mfp-hide alarnd--info-modal">
            <div class="alarnd--modal-inner alarnd--modal-chart-info">
                <h2><?php echo get_the_title($product->get_id()); ?></h2>

                <?php
                wp('p=' . $product->get_id() . '&post_type=product');
                wc_get_template('quick-view.php', array(), '', AlRNDCM_PATH . 'templates/'); ?>
            </div>
        </div>
        <?php
    }

    public function get_group_product_meta($product)
    {

        $current_user_id = ml_manage_user_session();
        $bump_price = get_user_meta($current_user_id, 'bump_price', true);
        $group_enable = get_field('group_enable', $product->get_id());

        $colors = get_field('color', $product->get_id());

        $discount_steps = get_field('discount_steps', $product->get_id());
        $adult_sizes = get_field('adult_sizes', 'option', false);
        $adult_sizes = ml_filter_string_to_array($adult_sizes);
        $child_sizes = get_field('child_sizes', 'option', false);
        $child_sizes = ml_filter_string_to_array($child_sizes);
        $first_line_keyword = get_field('first_line_keyword', $product->get_id());
        $second_line_keyword = get_field('second_line_keyword', $product->get_id());

        $all_sizes = array_merge($child_sizes, $adult_sizes);

        $selected_omit_sizes = get_field('omit_sizes_from_chart', $product->get_id());

        $discount_steps = ml_filter_disount_steps($discount_steps);
        $regular_price = $product->get_regular_price();

        // if bump price is not empty then apply percentage increase to discount steps
        if (!empty($bump_price)) {
            $discount_steps = apply_percentage_increase($discount_steps, $bump_price);
            $regular_price = round($regular_price + ($regular_price * $bump_price / 100));
        }
        error_log(print_r("Bump price get_group_product_meta: $bump_price", true));

        $json_data = array(
            "regular_price" => $regular_price,
            "data" => $discount_steps
        );

        $product_cart_id = WC()->cart->generate_cart_id($product->get_id());
        $in_cart = WC()->cart->find_product_in_cart($product_cart_id);

        $in_cart = '';
        if (in_array($product->get_id(), array_column(WC()->cart->get_cart(), 'product_id'))) {
            $in_cart = ' is_already_in_cart';
        }

        $uniqid = uniqid('alrnd');

        ?>

        <?php if (!empty($group_enable)): ?>
            <div id="ml--product_id-<?php echo $product->get_id(); ?>" data-product_id="<?php echo $product->get_id(); ?>"
                class="white-popup-block alarnd--slect-opt-modal mfp-hide alarnd--info-modal<?php echo $in_cart; ?>">
                <div class="alarnd--modal-inner alarnd--modal-chart-info">
                    <h2><?php echo get_the_title($product->get_id()); ?></h2>

                    <form class="modal-cart" action="" data-settings='<?php echo wp_json_encode($json_data); ?>'
                        enctype='multipart/form-data'>

                        <div class="alarnd--select-options-cart-wrap">
                            <div class="alarnd--select-options">

                                <div class="alarnd--select-opt-wrapper">
                                    <div class="alarnd--select-opt-header">
                                        <?php foreach ($all_sizes as $size): ?>
                                            <?php if (ml_is_omit($size, $selected_omit_sizes)): ?>
                                                <span><?php echo esc_html($size); ?></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="alarnd--select-qty-body">
                                        <?php foreach ($colors as $key => $color): ?>
                                            <div class="alarn--opt-single-row">
                                                <?php foreach ($all_sizes as $size):
                                                    $disabled = '';
                                                    if (!empty($color['omit_sizes']) && !ml_is_omit($size, $color['omit_sizes'])) {
                                                        $disabled = 'disabled="disabled"';
                                                    } ?>
                                                    <?php if (ml_is_omit($size, $selected_omit_sizes)):
                                                        $field_extra_atts = array(
                                                            "data-ml_gtm_color" => esc_html($color['color_hex_code']),
                                                            "data-ml_gtm_size" => esc_html($size),
                                                            "data-ml_gtm_item_variant" => esc_html($color['title']),
                                                            "data-ml_gtm_index" => esc_html($key)
                                                        );
                                                        $field_atts = ml_get_gtm_item($product, $field_extra_atts);
                                                        $field_atts = wc_implode_html_attributes($field_atts);
                                                        ?>
                                                        <div class="tshirt-qty-input-field mlimon">
                                                            <input style="box-shadow: 0px 0px 0px 1px <?php echo $color['color_hex_code']; ?>;"
                                                                type="text" class="three-digit-input" placeholder="" pattern="^[0-9]*$"
                                                                inputmode="numeric" autocomplete="off"
                                                                name="alarnd__color_qty[<?php echo $key; ?>][<?php echo $size; ?>]" <?php echo $field_atts; ?>                         <?php echo $disabled; ?>>
                                                            <span class="alarnd--limit-tooltip">Can't order more than 999</span>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <div class="alarnd--opt-color">
                                                    <span
                                                        style="background-color: <?php echo $color['color_hex_code']; ?>"><?php echo $color['title']; ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alarnd--next-target-message">
                            <h6>
                            <?php printf(
                                '%1$s <span class="ml_next_target"></span> %2$s %3$s %4$s %5$s<span class="stripe-through-text">%6$s <span class="ml_current_price_target"></span>%7$s</span>%8$s',
                                __( "הוסיפו", "hello-elementor" ),
                                __( "פריטים נוספים להורדת המחיר ל-", "hello-elementor" ),
                                wc_price(0, array('decimals' => 0)),
                                __( "ליחידה", "hello-elementor" ),
                                __( "(", "hello-elementor" ),
                                __( "כרגע", "hello-elementor" ),
                                __( "₪", "hello-elementor" ),
                                __( ")", "hello-elementor" )
                            ); ?>
                            </h6>
                        </div>

                        <div class="alarnd--limit-message">
                            <h6><?php esc_html_e("Can't order more than 999", "hello-elementor"); ?></h6>
                        </div>

                        <div class="alarnd--price-show-wrap">
                            <div class="alarnd--single-cart-row alarnd--single-cart-price">

                                <?php
                                echo '<a href="#" class="alarnd_view_pricing_cb_button" data-product_id="' . $product->get_id() . '">לפרטים על המוצר</a>';
                                ?>
                                <div class="alarnd--price-by-shirt">
                                    <p class="alarnd--group-price">
                                        <?php echo wc_price($regular_price, array('decimals' => 0)); ?> /
                                        <?php echo $first_line_keyword; ?>
                                    </p>
                                    <p><?php echo esc_html($second_line_keyword); ?>: <span
                                            class="alarnd__total_qty"><?php esc_html_e('0', "hello-elementor"); ?></span></p>
                                    <span class="alarnd--total-price">סה"כ:
                                        <?php echo wc_price($regular_price, array('decimals' => 0)); ?></span>
                                </div>
                                <?php
                                $addToCartAtts = ml_get_gtm_item($product);
                                $addToCartAtts = wc_implode_html_attributes($addToCartAtts);
                                ?>
                                <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>"
                                    disabled="disabled"
                                    class="single_add_to_cart_button button alt ml_add_loading ml_add_to_cart_trigger" <?php echo $addToCartAtts; ?>><?php echo esc_html($product->single_add_to_cart_text()); ?></button>
                            </div>
                            <div class="alanrd--product-added-message"><?php esc_html_e('Added to Cart', "hello-elementor"); ?>
                            </div>
                            <input type="hidden" name="ml_type" value="group">
                            <input type="hidden" name="alarnd__group_id" value="<?php echo $uniqid; ?>">
                        </div>

                    </form>
                </div>
            </div>
        <?php endif;
    }

    public function get_quantity_product_meta($product)
    {

        $current_user_id = ml_manage_user_session();
        $bump_price = get_user_meta($current_user_id, 'bump_price', true);
        $group_enable = get_field('group_enable', $product->get_id());
        $saving_info = get_field('saving_info', $product->get_id());
        $colors = get_field('colors', $product->get_id());
        $colors_title = get_field('title_for_colors', $product->get_id());
        $the_color_title = !empty($colors_title) ? $colors_title : esc_html__('Select a Color', 'hello-elementor');
        $custom_quanity = get_field('enable_custom_quantity', $product->get_id());

        $steps = get_field('quantity_steps', $product->get_id());

        // if bump price is not empty then apply percentage increase to discount steps
        if (!empty($bump_price)) {
            $steps = apply_percentage_increase($steps, $bump_price);
            // $regular_price = $regular_price + ($regular_price * $bump_price / 100);
        }
        error_log(print_r("Bump price get_quantity_product_meta: $bump_price", true));

        $last_step = array_key_last($steps);
        if (!empty($steps) && !empty($custom_quanity)):

            $qty = $product->get_min_purchase_quantity();

            if (!empty($custom_quanity) && !empty($steps) && isset($steps[0]['quantity'])) {
                $qty = $steps[0]['quantity'];
            }

            ?>

            <div id="ml--product_id-<?php echo $product->get_id(); ?>"
                class="white-popup-block alarnd--quantity-modal mfp-hide alarnd--info-modal">
                <div class="alarnd--modal-inner alarnd--modal-chart-info">
                    <h2><?php echo get_the_title($product->get_id()); ?></h2>

                    <form class="modal-cart" action="" enctype='multipart/form-data'>

                        <div class="alarnd--cart-inner">
                            <?php
                            if (!empty($colors)): ?>
                                <div class="alarnd--single-cart-row">
                                    <span><?php echo esc_html($the_color_title); ?></span>
                                    <?php
                                    foreach ($colors as $key => $item): ?>
                                        <div class="alarnd--custom-qtys-wrap">
                                            <div class="alarnd--single-variable">
                                                <span class="alarnd--single-var-info">
                                                    <input type="radio" id="custom_color-<?php echo $key; ?>" name="custom_color"
                                                        value="<?php echo esc_attr($item['color']); ?>" <?php echo 0 === $key ? 'checked="checked"' : ''; ?>>
                                                    <label
                                                        for="custom_color-<?php echo $key; ?>"><?php echo esc_html($item['color']); ?></label>
                                                </span>
                                                <span class="woocommerce-Price-amount amount"></span>
                                                <span class="alarnd--single-saving"></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="alarnd--single-cart-row" data-reqular-price="<?php echo $product->get_regular_price(); ?>">
                                <span><?php esc_html_e('Select a Quantity', 'hello-elementor'); ?></span>
                                <?php $the_price = isset($steps[$last_step]['amount']) ? $steps[$last_step]['amount'] : $product->get_regular_price(); ?>
                                <div class="alarnd--custom-qtys-wrap alarnd--single-custom-qty alarnd--single-var-labelonly">
                                    <div class="alarnd--single-variable alarnd--hide-price"
                                        data-min="<?php echo esc_attr($steps[0]['quantity']); ?>"
                                        data-price="<?php echo esc_attr($the_price); ?>">
                                        <span class="alarnd--single-var-info">
                                            <input type="radio" name="cutom_quantity" id="cutom_quantity_special-custom"
                                                value="<?php echo esc_attr($the_price); ?>" checked="checked">
                                            <input type="text" name="attribute_quantity" autocomplete="off" pattern="[0-9]*"
                                                class="alarnd_custom_input" inputmode="numeric"
                                                placeholder="<?php esc_html_e('הקלידו כמות…', 'hello-elementor'); ?>"
                                                id="attribute_quanity_custom_val">
                                            <!-- <label for="cutom_quantity_special-custom"><//?//php esc_html_e( 'Custom Quantity', "hello-elementor" ); ?></label> -->
                                        </span>
                                        <?php echo wc_price(0, array('decimals' => 0)); ?>
                                        <span class="alarnd--single-saving"><span
                                                class="alarnd__cqty_amount"><?php echo esc_html($steps[$last_step]['amount']); ?></span>
                                            <?php echo esc_html($saving_info); ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($steps)):
                                    foreach ($steps as $key => $step):
                                        $item_price = !empty($step['amount']) ? $step['amount'] : $product->get_regular_price();
                                        $price = (int) $step['quantity'] * floatval($item_price);
                                        $hide = isset($step['hide']) && !empty($step['hide']) ? true : false;
                                        ?>
                                        <div class="alarnd--custom-qtys-wrap<?php echo true === $hide ? ' alarnd--hide-qty' : ''; ?>"
                                            data-qty="<?php echo esc_attr($step['quantity']); ?>"
                                            data-price="<?php echo esc_attr($item_price); ?>">
                                            <div class="alarnd--single-variable">
                                                <span class="alarnd--single-var-info">
                                                    <input type="radio" id="cutom_quantity-<?php echo $key; ?>" name="cutom_quantity"
                                                        value="<?php echo $key; ?>" <?php echo 0 === $key ? 'checked="checked"' : ''; ?>>
                                                    <label
                                                        for="cutom_quantity-<?php echo $key; ?>"><?php echo esc_html($step['quantity']); ?></label>
                                                </span>
                                                <?php echo wc_price((int) $price, array('decimals' => 0)); ?>
                                                <span class="alarnd--single-saving"><span
                                                        class="alarnd--var-amount"><?php echo esc_html($item_price); ?></span>
                                                    <?php echo esc_html($saving_info); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; endif;
                                ?>
                            </div>
                        </div>


                        <?php
                        woocommerce_quantity_input(
                            array(
                                'min_value' => apply_filters('woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product),
                                'max_value' => apply_filters('woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product),
                                'input_value' => $qty, // WPCS: CSRF ok, input var ok.
                            ),
                            $product
                        );

                        $addToCartAtts = ml_get_gtm_item($product);
                        $addToCartAtts = wc_implode_html_attributes($addToCartAtts);
                        ?>
                        <div class="alarnd--single-button-wrap">
                            <input type="hidden" name="ml_item_price" value="">
                            <input type="hidden" name="ml_total_price" value="">
                            <input type="hidden" name="ml_quantity" value="">
                            <input type="hidden" name="ml_type" value="quantity">
                            <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>"
                                class="single_add_to_cart_button button alt ml_add_loading ml_quantity_product_addtocart" <?php echo $addToCartAtts; ?>><?php echo esc_html($product->single_add_to_cart_text()); ?></button>
                        </div>
                        <div class="alanrd--product-added-message"><?php esc_html_e('Added to Cart', "hello-elementor"); ?></div>
                    </form>
                </div>
            </div>
            <?php
        endif;
    }

    function ml_split_name($full_name, $part)
    {
        $name_parts = explode(' ', $full_name);
        $first_name = array_shift($name_parts); // First part
        $last_name = implode(' ', $name_parts); // Remaining parts

        if ($part === 'last') {
            return $last_name;
        }

        return $first_name;
    }
}

new ML_Ajax();