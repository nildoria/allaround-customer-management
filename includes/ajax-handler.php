<?php

class ML_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_get_item_selector', array( $this, 'get_item_selector' ) );
        add_action( 'wp_ajax_nopriv_get_item_selector', array( $this, 'get_item_selector' ) );

        add_action( 'wp_ajax_confirm_payout', array( $this, 'confirm_payout' ) );
        add_action( 'wp_ajax_nopriv_confirm_payout', array( $this, 'confirm_payout' ) );
        
        add_action( 'wp_ajax_cardform_confirm_payout', array( $this, 'cardform_confirm_payout' ) );
        add_action( 'wp_ajax_nopriv_cardform_confirm_payout', array( $this, 'cardform_confirm_payout' ) );

        add_action( 'wp_ajax_ml_add_to_cart', array( $this, 'ml_add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_ml_add_to_cart', array( $this, 'ml_add_to_cart' ) );

        add_action('wp_ajax_get_woocommerce_cart', array($this, 'get_woocommerce_cart_ajax'));
        add_action('wp_ajax_nopriv_get_woocommerce_cart', array($this, 'get_woocommerce_cart_ajax'));

        add_action('wp_ajax_add_variation_to_cart', array($this, 'add_variation_to_cart') );
        add_action('wp_ajax_nopriv_add_variation_to_cart', array($this, 'add_variation_to_cart') );
        
        add_action('wp_ajax_add_simple_to_cart', array($this, 'add_simple_to_cart') );
        add_action('wp_ajax_nopriv_add_simple_to_cart', array($this, 'add_simple_to_cart') );

        add_action('wp_ajax_ml_customer_details', array($this, 'ml_customer_details') );
        add_action('wp_ajax_nopriv_ml_customer_details', array($this, 'ml_customer_details') );

        add_action('wp_ajax_alarnd_create_order', array( $this, 'alarnd_create_order' ) );
        add_action('wp_ajax_nopriv_alarnd_create_order', array( $this, 'alarnd_create_order' ) );
        
        add_action('wp_ajax_ml_send_card', array( $this, 'ml_send_card' ) );
        add_action('wp_ajax_nopriv_ml_send_card', array( $this, 'ml_send_card' ) );
        
        add_action('wp_ajax_ml_pagination', array( $this, 'ml_pagination' ) );
        add_action('wp_ajax_nopriv_ml_pagination', array( $this, 'ml_pagination' ) );
        
        add_action('wp_ajax_load_products_by_category', array( $this, 'load_products_by_category' ) );
        add_action('wp_ajax_nopriv_load_products_by_category', array( $this, 'load_products_by_category' ) );

        add_action('wp_ajax_check_cart_status', array( $this, 'check_cart_status_callback') );
        add_action('wp_ajax_nopriv_check_cart_status', array( $this, 'check_cart_status_callback') );

    }

    function check_cart_status_callback() {
        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

        $cart_contents = WC()->cart->get_cart_contents_count();

        // Check if the cart has items
        $is_item_has = $cart_contents > 0 ? true : false;
    
        // Send back the cart status
        wp_send_json_success(array('cart_has_items' => $is_item_has));
    }

    public function ml_pagination() {
        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

         $page_num = isset( $_POST['page_num'] ) && ! empty( $_POST['page_num'] ) ? sanitize_text_field( $_POST['page_num'] ) : '';
        $filter_item = isset( $_POST['filter_item'] ) && ! empty( $_POST['filter_item'] ) ? sanitize_text_field( $_POST['filter_item'] ) : '';
        $current_user_id = isset( $_POST['user_id'] ) && ! empty( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : '';

        $filter_item = $filter_item === 'all' ? '' : $filter_item;

        $items = ml_get_user_products($current_user_id, $filter_item);

        if( 
            empty( $page_num ) ||
            empty( $current_user_id ) ||
            empty( $items ) 
        ) {
            wp_die();
        }

        $itemsPerPage = ml_products_per_page();
        $totalItems = count($items);
        $totalPages = ceil($totalItems / $itemsPerPage);
        // $currentpage = isset($_GET['list']) ? (int)$_GET['list'] : 1;
        $currentpage = $page_num+1;

        $start = ($currentpage - 1) * $itemsPerPage;
        $end = $start + $itemsPerPage;
        $itemsToDisplay = array_slice($items, $start, $itemsPerPage);
        // Check if there are more items to load
        $has_more_items = count($items) > $end;
        ob_start();

            // echo '<pre>';
            // print_r( $itemsPerPage );
            // echo '</pre>';
        

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

                echo '<li class="loadmore-loaded product-item product ';
                
                foreach ($terms as $term) {
                    echo 'category-' . $term->term_id . ' ';
                }
                echo '" data-product-id="' . esc_attr($product->get_id()) . '">';
                
                // Product Thumbnail
                echo '<div style="background-image:url(https://placeholder.pics/svg/307x200/FFFFFF-FFFFFF/636363-FFFFFF/ALLAROUND)" class="product-thumbnail">';
                echo '<img src="'.$thumbnail.'" loading="lazy" />';
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

                echo '<p>' . $product->get_price_html() . '</p>';
                
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
                                                <span class="price_step_qty">כמות: <?php echo esc_html( $qty); ?></span>
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

        $response_data = array(
            'items' => ob_get_clean(),
            'totalPages' => $totalPages,
        );

        echo json_encode($response_data);
        wp_die();

    }



    public function load_products_by_category() {
        check_ajax_referer('aum_ajax_nonce', 'nonce');

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $category_id = isset($_POST['category_id']) ? sanitize_text_field($_POST['category_id']) : 0;
        $current_user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? intval($_POST['user_id']) : '';

        if (empty($current_user_id)) {
            wp_die();
        }

        // Get selected product IDs for the user
        $selected_product_ids = ml_get_user_products($current_user_id);
        
        $filtered_product_ids = array();
        // Filter products by the selected category
        if( 'all' === $category_id ) {
            foreach ($selected_product_ids as $product) {
                $product_id = $product['value'];
                $terms = wp_get_post_terms($product_id, 'product_cat');
        
                $filtered_product_ids[] = $product_id;
            }
        }

        if( 'all' !== $category_id && ! empty( $category_id ) ) {
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

                echo '<li class="loadmore-loaded product-item product ';
                
                foreach ($terms as $term) {
                    echo 'category-' . $term->term_id . ' ';
                }
                echo '" data-product-id="' . esc_attr($product->get_id()) . '">';
                
                // Product Thumbnail
                echo '<div class="product-thumbnail">';
                echo '<img src="'.$thumbnail.'" loading="lazy" />';
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

                echo '<p>' . $product->get_price_html() . '</p>';
                
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
                                                <span class="price_step_qty">כמות: <?php echo esc_html( $qty); ?></span>
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

        $response_data = array(
            'items' => ob_get_clean(),
            'totalPages' => $totalPages,
        );

        echo json_encode($response_data);
        wp_die();
    }



    public function confirm_payout() {
        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );
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
                        <a href="#" class="alarnd--submit-btn alarnd--continue-btn"><?php esc_html_e('Try Again', 'hello-elementor'); ?></a>
                        <div class="form-message"></div>
                    </div>
                </div>

                <div class="alarnd--popup-confirmation">
                    <div class="alarnd--popup-middle">
                        <h5><?php esc_html_e( 'Thanks for adding it to your order!', "hello-elementor" ); ?></h5>
                        <div class="alarnd--popup-inline">
                            <h5><?php printf( '%s %s', esc_html__( 'Please confirm by clicking on the button below and we’ll charge your card by', 'hello-elementor' ), WC()->cart->get_total() ); ?></h5>
                        </div>
                        <span class="alrnd--create-order alarnd--submit-btn ml_add_loading button"><?php esc_html_e( 'Click To Pay ', "hello-elementor" ); ?> <?php printf( WC()->cart->get_total() ); ?></span>
                        <div class="form-message"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        wp_die();
    }

    public function cardform_confirm_payout() {
        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );
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
                        <a href="#" class="alarnd--submit-btn alarnd--continue-btn"><?php esc_html_e('Try Again', 'hello-elementor'); ?></a>
                        <div class="form-message"></div>
                    </div>
                </div>

                <div class="alarnd--popup-confirmation">
                    <div class="alarnd--popup-middle">
                        <h5><?php esc_html_e( 'Thanks for adding it to your order!', "hello-elementor" ); ?></h5>
                        <div class="alarnd--popup-inline">
                            <h5><?php printf( '%s %s', esc_html__( 'Please confirm by clicking on the button below and we’ll charge your card by', 'hello-elementor' ), WC()->cart->get_total() ); ?></h5>
                        </div>
                        <span class="alrnd--send_carddetails alarnd--submit-btn ml_add_loading button" style="width: 100%"><?php esc_html_e( 'Click To Pay ', "hello-elementor" ); ?> <?php printf( WC()->cart->get_total() ); ?></span>
                        <div class="form-message"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        wp_die();
    }
	
	public function popup_success_icon() {
        $success_icon = AlRNDCM_URL . "assets/images/tick.png";
        return $success_icon;
    }
    public function popup_failed_icon() {
        $failed_icon = AlRNDCM_URL . "assets/images/failed.png";
        return $failed_icon;
    }
    public function popup_success_markup($order_id = '') {
        $success_icon = $this->popup_success_icon();

        $thankyou_output = '';
        if( ! empty( $order_id ) ) {
            $order = wc_get_order( $order_id );
            if( $order ) {
                ob_start();
                // wc_get_template( 'order/order-details.php', array( 'order_id' => $order_id ) );
                wc_get_template( 'checkout/thankyou.php', array( 'order' => $order ) );
                $thankyou_output = ob_get_clean();
            }
        }

        $success_popup = '<div class="white-popup-block alarnd--payout-modal alarnd--thankyou-modal mfp-hide alarnd--info-modal">
            <div class="popup_product_details">
                <div class="alarnd--success-wrap">
                    <div class="woocommerce alarn--popup-thankyou">';

                        if( ! empty( $thankyou_output ) ) {
                            $success_popup .= $thankyou_output;
                        } else {
                            $success_popup .='<img src="'.$success_icon.'" alt="">
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
    public function popup_failed_markup() {
        $failed_icon = $this->popup_failed_icon();
        $failed_popup = '<div class="white-popup-block alarnd--payout-modal mfp-hide alarnd--info-modal">
            <div class="popup_product_details">
                <div class="alarnd--failed-wrap">
                    <div class="alarn--popup-thankyou">
                        <img src="'.$failed_icon.'" alt="">
                        <h2>'. esc_html__("Order Didn\"t go through", "hello-elementor") . '</h2>
                        <h3>לצערנו העסקה לא אושרה.</h3>
                        <p>נטפל בבעיה וניצור איתך קשר בהקדם :)</p>
                        <a href="#" class="alarnd--submit-btn alarnd--continue-btn">'.esc_html__("Try Again", "hello-elementor").'</a>
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
    public function alarnd_create_order() {
        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

        if ( WC()->cart->get_cart_contents_count() == 0 ) {
            wp_send_json_error( array(
                "message_type" => 'reqular',
                "message" => esc_html__( "Cart is empty.", "hello-elementor" )
            ) );
            wp_die();
        }
        
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array(
                "message_type" => 'reqular',
                "message" => esc_html__( "User need to logged in.", "hello-elementor" )
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
                "message_type" => 'reqular',
                "message" => esc_html__("Token value empty.", "hello-elementor")
            ) );
            wp_die();
        }

        $customerDetails = isset( $_POST['customerDetails'] ) && ! empty( $_POST['customerDetails'] ) ? $_POST['customerDetails'] : [];
        $userName = isset( $customerDetails['userName'] ) && ! empty( $customerDetails['userName'] ) ? sanitize_text_field( $customerDetails['userName'] ) : '';
        $userPhone = isset( $customerDetails['userPhone'] ) && ! empty( $customerDetails['userPhone'] ) ? sanitize_text_field( $customerDetails['userPhone'] ) : '';
        $userAdress = isset( $customerDetails['userAdress'] ) && ! empty( $customerDetails['userAdress'] ) ? sanitize_text_field( $customerDetails['userAdress'] ) : '';
        $userEmail = isset( $customerDetails['userEmail'] ) && ! empty( $customerDetails['userEmail'] ) ? sanitize_text_field( $customerDetails['userEmail'] ) : '';
        $cardholderCity = isset( $customerDetails['userCity'] ) && ! empty( $customerDetails['userCity'] ) ? sanitize_text_field( $customerDetails['userCity'] ) : '';
        $cardholderInvoiceName = isset( $customerDetails['userInvoiceName'] ) && ! empty( $customerDetails['userInvoiceName'] ) ? sanitize_text_field( $customerDetails['userInvoiceName'] ) : '';
        $countryCode = ml_get_country_code();

        if( 
            empty( $customerDetails ) || 
            empty( $userName ) ||
            empty( $userPhone ) ||
            empty( $userAdress ) ||
            empty( $cardholderCity ) ||
            empty( $userEmail ) 
        ) {
            wp_send_json_error( array(
                "message_type" => 'reqular',
                "message" => esc_html__("Required field are empty. Please fill all the field.", "hello-elementor")
            ) );
            wp_die();
        }

        $userPhone = $countryCode . $userPhone;
        
        if( 
            ! is_email( $userEmail )
        ) {
            wp_send_json_error( array(
                "message_type" => 'reqular',
                "message" => esc_html__("Please enter a valid email address.", "hello-elementor")
            ) );
            wp_die();
        }

        $cart_filter_data = [];
        $product_list = [];
        WC()->cart->calculate_totals();
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $_product = wc_get_product( $cart_item['product_id'] );

            $the_product = $cart_item['data'];

            // Get the price of the product
            $product_price = $the_product->get_price();
            error_log( $cart_item['data']->get_price() );

            $cart_filter_data[$cart_item_key]["title"] = $_product->get_title();
            $cart_filter_data[$cart_item_key]["price"] = $cart_item['data']->get_price();
            
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

            $single_product_item = array(
                "product_id" => $cart_item['product_id'],
                "quantity" => $cart_item['quantity']
            );

            $single_product_item["price"] = $cart_item['data']->get_price();

            if( isset( $cart_item['alarnd_size'] ) && ! empty( $cart_item['alarnd_size'] ) ) {
                $single_product_item["size"] = $cart_item['alarnd_size'];
            }
            if( isset( $cart_item['alarnd_color'] ) && ! empty( $cart_item['alarnd_color'] ) ) {
                $single_product_item["color"] = $cart_item['alarnd_color'];
            }
            if( isset( $cart_item['alarnd_color_key'] ) ) {
                $single_product_item['alarnd_color_key'] = $cart_item['alarnd_color_key'];
            }
            if( isset( $cart_item['alarnd_custom_color'] ) ) {
                $single_product_item['alarnd_custom_color'] = $cart_item['alarnd_custom_color'];
            }
            if( isset( $cart_item['alarnd_step_key'] ) ) {
                $single_product_item['alarnd_step_key'] = $cart_item['alarnd_step_key'];
            }

            $product_list[] = $single_product_item;
        }
        WC()->cart->calculate_totals();
        
        $extraMeta = [];
        $extraMeta['invoice'] = $cardholderInvoiceName;
        $extraMeta['city'] = $cardholderCity;

        // send request to api
        $api_url  = apply_filters( 'allaround_order_api_url', '' );

        $body = array(
            'username' => $userName,
            'email' => $userEmail,
            'phone' => $userPhone,
            'address' => $userAdress,
            'invoice' => $cardholderInvoiceName,
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
            'body'        => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
        $args = apply_filters( 'allaround_order_api_args', $args, $current_user_id );

        $request = wp_remote_post( esc_url( $api_url ), $args );

        // error_log( print_r( $request, true ) );

        // retrieve reponse body
        $message = wp_remote_retrieve_body( $request );

        // decode response into array
        $response_obj = ml_response($message);

        // error_log( print_r( $response_obj, true ) );
        
        // order data
        $first_name = empty( $current_user->first_name ) && empty( $current_user->last_name ) ? $userName : $current_user->first_name;
        $last_name = empty( $current_user->first_name ) && empty( $current_user->last_name ) ? '' : $current_user->last_name;
        $company = get_user_meta( $current_user_id, 'billing_company', true );
        $city = get_user_meta( $current_user_id, 'billing_city', true );
        $city = ! empty( $cardholderCity ) ? $cardholderCity : $city;
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
            "extraMeta" => $extraMeta,
            "update" => true,
			"user_id" => $current_user_id
        );

        // error_log( print_r( $order_data, true ) );

        $failed_popup = $this->popup_failed_markup();
        
        if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) == 200 && $message !== "Accepted" ) {
            
            // first create order
            $order_id = ml_create_order($order_data);

            $success_popup = $this->popup_success_markup($order_id);

            // Clear the cart
            WC()->cart->empty_cart();

            wp_send_json_success( array(
                "message_type" => 'api',
                "result_popup" => $success_popup,
                "response_obj" => $response_obj,
                "message_server" => $message,
                "message" => "Successfully products added to order #$order_id"
            ) );

            wp_die();
        }

        $error_message = "Something went wrong";
        if( is_wp_error( $request ) ) {
            $error_message = $request->get_error_message();
        }

        if( "Accepted" === $message ) {
            $error_message = "Unable to reach the api server";
        }

        if( isset( $response_obj['returnMessage'] ) && ! empty( $response_obj['returnMessage'] ) ) {
            $error_message = $response_obj['returnMessage'];
        }

        // error_log( print_r( $error_message, true ) );
        wp_send_json_error( array(
            "body" => $body,
            "message_type" => 'api',
            "result_popup" => $failed_popup,
            "message" => $error_message,
            "server_message" => $message,
            "server_body_obj" => $response_obj
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
                "message_type" => 'reqular',
                "message" => "Cart is empty."
            ) );
            wp_die();
        }
        
        $user_id = isset( $_POST['user_id'] ) && ! empty( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : '';
        $cardholderName = isset( $_POST['userName'] ) && ! empty( $_POST['userName'] ) ? sanitize_text_field( $_POST['userName'] ) : '';
        $cardholderPhone = isset( $_POST['userPhone'] ) && ! empty( $_POST['userPhone'] ) ? sanitize_text_field( $_POST['userPhone'] ) : '';
        $cardholderAdress = isset( $_POST['userAdress'] ) && ! empty( $_POST['userAdress'] ) ? sanitize_text_field( $_POST['userAdress'] ) : '';
        $cardholderEmail = isset( $_POST['userEmail'] ) && ! empty( $_POST['userEmail'] ) ? sanitize_text_field( $_POST['userEmail'] ) : '';
        $cardholderCity = isset( $_POST['userCity'] ) && ! empty( $_POST['userCity'] ) ? sanitize_text_field( $_POST['userCity'] ) : '';
        $cardholderInvoiceName = isset( $_POST['userInvoiceName'] ) && ! empty( $_POST['userInvoiceName'] ) ? sanitize_text_field( $_POST['userInvoiceName'] ) : '';

        $cardNumber = isset( $_POST['cardNumber'] ) && ! empty( $_POST['cardNumber'] ) ? sanitize_text_field( $_POST['cardNumber'] ) : '';
        $expirationDate = isset( $_POST['expirationDate'] ) && ! empty( $_POST['expirationDate'] ) ? sanitize_text_field( $_POST['expirationDate'] ) : '';
        $cvvCode = isset( $_POST['cvvCode'] ) && ! empty( $_POST['cvvCode'] ) ? sanitize_text_field( $_POST['cvvCode'] ) : '';
        $countryCode = ml_get_country_code();
        
        $cardNumber = str_replace(' ', '', $cardNumber);

        $current_user_id = $user_id;
        $current_user = get_userdata( $current_user_id );

        if( 
            empty( $cardholderName ) ||
            empty( $cardholderPhone ) ||
            empty( $cardholderAdress ) ||
            empty( $cardholderEmail ) ||
            empty( $cardholderCity ) ||
            empty( $cardNumber ) ||
            empty( $expirationDate ) ||
            empty( $cvvCode )
        ) {
            wp_send_json_error( array(
                "message_type" => 'reqular',
                "message" => esc_html__("Required field are empty. Please fill all the field.", "hello-elementor")
            ) );
            wp_die();
        }

        $cardholderPhone = $countryCode . $cardholderPhone;
        
        if( 
            ! is_email( $cardholderEmail )
        ) {
            wp_send_json_error( array(
                "message_type" => 'reqular',
                "message" => esc_html__("Please enter a valid email address.", "hello-elementor")
            ) );
            wp_die();
        }

        $expirationDate = str_replace("/", '', $expirationDate);

        $cart_filter_data = [];
        $product_list = [];
        WC()->cart->calculate_totals();
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

            $single_product_item = array(
                "product_id" => $cart_item['product_id'],
                "quantity" => $cart_item['quantity']
            );

            $single_product_item["price"] = $cart_item['data']->get_price();

            if( isset( $cart_item['alarnd_size'] ) && ! empty( $cart_item['alarnd_size'] ) ) {
                $single_product_item["size"] = $cart_item['alarnd_size'];
            }
            if( isset( $cart_item['alarnd_color'] ) && ! empty( $cart_item['alarnd_color'] ) ) {
                $single_product_item["color"] = $cart_item['alarnd_color'];
            }
            if( isset( $cart_item['alarnd_color_key'] ) ) {
                $single_product_item['alarnd_color_key'] = $cart_item['alarnd_color_key'];
            }
            if( isset( $cart_item['alarnd_custom_color'] ) ) {
                $single_product_item['alarnd_custom_color'] = $cart_item['alarnd_custom_color'];
            }
            if( isset( $cart_item['alarnd_step_key'] ) ) {
                $single_product_item['alarnd_step_key'] = $cart_item['alarnd_step_key'];
            }

            $product_list[] = $single_product_item;
        }
        WC()->cart->calculate_totals();

        $extraMeta = [];
        $extraMeta['invoice'] = $cardholderInvoiceName;
        $extraMeta['city'] = $cardholderCity;

        // send request to api
        $api_url  = apply_filters( 'allaround_card_url', 'https://hook.eu1.make.com/80wvx4qyzxkegv4n1y2ys736dz92t6u6' );

        $body = array(
            'cardholderName' => $cardholderName,
            'cardholderPhone' => $cardholderPhone,
            'cardholderAdress' => $cardholderAdress,
            'cardholderCity' => $cardholderCity,
            'cardholderEmail' => $cardholderEmail,
            'cardholderInvoiceName' => $cardholderInvoiceName,
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
            'body'        => json_encode($body, JSON_UNESCAPED_UNICODE),
        );
        $args = apply_filters( 'allaround_card_api_args', $args, $current_user_id );

        // send request to make.com
        $request = wp_remote_post( esc_url( $api_url ), $args );
        
        // error_log( print_r( $request, true ) );

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
        $city = ! empty( $cardholderCity ) ? $cardholderCity : $city;
        $postcode = get_user_meta( $current_user_id, 'billing_postcode', true );
        $state = get_user_meta( $current_user_id, 'billing_state', true );
        $country = get_user_meta( $current_user_id, 'billing_country', true );
        $country = empty( $country ) ? "IL" : $country;
        // Get the user's ACF lock_profile field value
        $lock_profile = get_field('lock_profile', 'user_' . $current_user_id);

        $update_order = true; // Default value

        if ($lock_profile === true) {
            $update_order = false;
        }

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
            "extraMeta" => $extraMeta,
            "update" => $update_order,
			"user_id" => $current_user_id
        );

        $failed_popup = $this->popup_failed_markup();

        if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) == 200 && $message !== "Accepted" ) {
		//if ( ! is_wp_error( $request ) && $message !== "Accepted" ) {
            
            // first create order
            $order_id = ml_create_order($order_data);   

            $success_popup = $this->popup_success_markup($order_id);
            
            // Clear the cart
            WC()->cart->empty_cart();

            wp_send_json_success( json_encode( array(
                "message_type" => 'api',
                "result_popup" => $success_popup,
                "message" => "Successfully products added to order #$order_id"
            ) ) );

            wp_die();
        }
        
        $error_message = "Something went wrong";
        if( is_wp_error( $request ) ) {
            $error_message = $request->get_error_message();
        }

        if( "Accepted" === $message ) {
            $error_message = "Unable to reach the api server";
        }

        if( ! empty( $response_obj ) && isset( $response_obj['returnMessage'] ) && ! empty( $response_obj['returnMessage'] ) ) {
            $error_message = $response_obj['returnMessage'];
        }

        // error_log( print_r( $response_obj, true ) );

        // error_log( print_r( $error_message, true ) );
        wp_send_json_error( json_encode( array(
            "body" => $body,
            "message_type" => 'api',
            "message" => $error_message,
            "server_message" => $message,
            "result_popup" => $failed_popup,
            "server_body_obj" => $response_obj
        ) ) );

        wp_die();
    }
	
	public function ml_customer_details() {
        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

        $user_id = isset( $_POST['user_id'] ) && ! empty( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : '';
        $userName = isset( $_POST['userName'] ) && ! empty( $_POST['userName'] ) ? sanitize_text_field( $_POST['userName'] ) : '';
        $userPhone = isset( $_POST['userPhone'] ) && ! empty( $_POST['userPhone'] ) ? sanitize_text_field( $_POST['userPhone'] ) : '';
        $userAdress = isset( $_POST['userAdress'] ) && ! empty( $_POST['userAdress'] ) ? sanitize_text_field( $_POST['userAdress'] ) : '';
        $userEmail = isset( $_POST['userEmail'] ) && ! empty( $_POST['userEmail'] ) ? sanitize_text_field( $_POST['userEmail'] ) : '';
        $userCity = isset( $_POST['userCity'] ) && ! empty( $_POST['userCity'] ) ? sanitize_text_field( $_POST['userCity'] ) : '';
        $userInvoiceName = isset( $_POST['userInvoiceName'] ) && ! empty( $_POST['userInvoiceName'] ) ? sanitize_text_field( $_POST['userInvoiceName'] ) : '';

        $current_user_id = $user_id;
        $current_email = get_userdata($current_user_id)->user_email;

        $gonna_update = false;

        // if user then only allow update
        if( is_user_logged_in() ) {
           $gonna_update = true;
        }
        
        $invalid_inputs = [];

        if( empty( $userName ) ) {
            $invalid_inputs['userName'] = esc_html__("Please enter your full name.", "hello-elementor");
        }
        if( empty( $userPhone ) ) {
            $invalid_inputs['userPhone'] = esc_html__("Please enter a valid phone number.", "hello-elementor");
        }
        if( empty( $userAdress ) ) {
            $invalid_inputs['userAdress'] = esc_html__("Please provide your complete address.", "hello-elementor");
        }
        if( empty( $userCity ) ) {
            $invalid_inputs['userCity'] = esc_html__("Please provide your city.", "hello-elementor");
        }
        // if( empty( $userInvoiceName ) ) {
        //     $invalid_inputs['userInvoiceName'] = esc_html__("Please enter the invoice number.", "hello-elementor");
        // }
        if( 
            empty( $userEmail ) ||
            ! is_email( $userEmail )
        ) {
            $invalid_inputs['userEmail'] = esc_html__("Please provide a valid email address.", "hello-elementor");
        } 
        
        if( 
            $gonna_update === true &&
            $current_email != $userEmail &&
            email_exists( $userEmail )
        ) {
            $invalid_inputs['userEmail'] = esc_html__("This email address is already in use.", "hello-elementor");
        }
        
        if( 
            ! empty( $invalid_inputs )
        ) {
            wp_send_json_error( $invalid_inputs );
            wp_die();
        }

        if( $gonna_update === true ) {
            $phoneNumber = ml_get_phone_no( $userPhone );
            $countryCode = ml_get_country_code();

            // update phone
            update_user_meta_if_different($current_user_id, 'xoo_ml_phone_code', $countryCode);
            update_user_meta_if_different($current_user_id, 'xoo_ml_phone_no', $phoneNumber);

            update_acf_anyway($current_user_id, 'invoice', $userInvoiceName);

            // WcooCommerce user field update
            update_user_meta_if_different($current_user_id, 'billing_address_1', $userAdress);
            update_user_meta_if_different($current_user_id, 'billing_phone', $userPhone);
            update_user_meta_if_different($current_user_id, 'billing_city', $userCity);

            // Email address
            update_user_email_if_different($current_user_id, $userEmail);
            
            // Display Name
            update_user_name_if_different($current_user_id, $userName);
        }

        if( empty( $userInvoiceName ) ) {
            // $current_value = get_field('invoice', "user_{$current_user_id}");
            // if ( ! empty($current_value) ) {
            //     $userInvoiceName = $current_value;
            // }
             $userInvoiceName = $userName;
        }

        ?>
        <div class="alarnd--payout-col alarnd--details-previewer">
            <h3><?php esc_html_e( 'כתובת למשלוח', 'hello-elementor' ); ?></h3>
            <div class="tokenized_inv_name_cont"><?php esc_html_e( 'חשבונית על שם', 'hello-elementor' ); ?>:<p class="tokenized_user_name"><?php echo $userInvoiceName ?></p></div>

            <div class="alarnd--user-address">
                <div class="alarnd--user-address-wrap">
                    <?php echo ! empty( $userName ) ? '<p>'. esc_html( $userName ) .'</p>' : ''; ?>
                    <?php echo ! empty( $userPhone ) ? '<p>'. esc_html( $userPhone ) .'</p>' : ''; ?>
                    <?php echo ! empty( $userEmail ) ? '<p>'. esc_html( $userEmail ) .'</p>' : ''; ?>
                    <p>
                    <?php echo ! empty( $userAdress ) ? '<span>'. esc_html( $userAdress ) .', </span>' : ''; ?>
                    <?php echo ! empty( $userCity ) ? '<span>'. esc_html( $userCity ) .'</span>' : ''; ?>
                    </p>
                </div>
                <span class="alarnd--user_address_edit"><?php esc_html_e( 'שינוי', 'hello-elementor' ); ?></span>
            </div>
        </div>
        <?php
        wp_die();
    }
    

    function add_variation_to_cart() {

        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

        $product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['product_id'] ) );
        $quantity          = empty( $_POST['quantity'] ) ? 1 : wc_stock_amount( $_POST['quantity'] );
        $user_id = isset( $_POST['user_id'] ) && ! empty( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : '';
        $cart_item_data = [];
        if( ! is_user_logged_in() && ! empty( $user_id ) ) {
            $cart_item_data['user_id'] = $user_id;
        }

        $variation_id      = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : '';
        $variations         = ! empty( $_POST['variation'] ) ? (array) $_POST['variation'] : '';

        $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );

        if ( $passed_validation && WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations, $cart_item_data ) ) {

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
    
    function add_simple_to_cart() {

        check_ajax_referer( 'aum_ajax_nonce', 'nonce' );

        $product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['product_id'] ) );
        $quantity          = empty( $_POST['quantity'] ) ? 1 : wc_stock_amount( $_POST['quantity'] );
        $user_id = isset( $_POST['user_id'] ) && ! empty( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : '';
        $cart_item_data = [];
        if( ! is_user_logged_in() && ! empty( $user_id ) ) {
            $cart_item_data['user_id'] = $user_id;
        }

        if ( WC()->cart->add_to_cart( $product_id, $quantity, '', '', $cart_item_data ) ) {

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
        $user_id = isset( $_POST['user_id'] ) && ! empty( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : '';

        $cart_item_data = [];
        if( ! is_user_logged_in() && ! empty( $user_id ) ) {
            $cart_item_data['user_id'] = $user_id;
        }

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

            // Check if product is already in the cart
            $cart_item_key = '';
            $cart_item_qty = '';

            // Check if product is already in the cart
            foreach ( WC()->cart->get_cart() as $key => $cart_item) {
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
                if ( $passed_validation && WC()->cart->add_to_cart( $product_id, $quantity, '', '', $cart_item_data ) ) {
                    do_action( 'woocommerce_ajax_added_to_cart', $product_id );
    
                    // Return fragments
                    WC_AJAX::get_refreshed_fragments();
                } {
                    wp_send_json( array(
                        "success" => true,
                        "message" => "Something wen't wrong when trying to add product #$product_id"
                    ) );
                }
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

        $user_id = isset( $_POST['user_id'] ) && ! empty( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : '';

        $cart_item_data = [];
        if( ! is_user_logged_in() && ! empty( $user_id ) ) {
            $cart_item_data['user_id'] = $user_id;
        }

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
                    $skip_item = false;
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
                            $skip_item = true;
                            continue;
                       }
                    }
                    
                    if( true !== $skip_item ) {
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
                        WC()->cart->add_to_cart( $product->get_id(), (int) $i_qty, '', '', $cart_item_meta );
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
                                            <input style="box-shadow: 0px 0px 0px 1px <?php echo $color['color_hex_code']; ?>;" type="text" class="three-digit-input" placeholder="" pattern="^[0-9]*$" inputmode="numeric" autocomplete="off" name="alarnd__color_qty[<?php echo $key; ?>][<?php echo $size; ?>]" <?php echo $disabled; ?>>
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
                        <h6><?php printf( '%1$s <span class="ml_next_target"></span> %2$s %3$s %4$s', __( "Add", "hello-elementor" ), __( "more items to reduce your cost to", "hello-elementor" ), wc_price(0, array('decimals' => 0)), __( "per item", "hello-elementor" ) ); ?></h6>
                    </div>
                    
                    <div class="alarnd--limit-message">
                        <h6><?php esc_html_e("Can't order more than 999", "hello-elementor"); ?></h6>
                    </div>

                    <div class="alarnd--price-show-wrap">
                        <div class="alarnd--single-cart-row alarnd--single-cart-price">
                            
                        <?php
                        echo '<a href="#" class="alarnd_view_pricing_cb_button" data-product_id="'. $product->get_id() .'">לפרטים על המוצר</a>';
                        ?>
                            <div class="alarnd--price-by-shirt">
                                <p class="alarnd--group-price"><?php echo wc_price($product->get_regular_price(), array('decimals' => 0)); ?> / <?php echo $first_line_keyword; ?></p>
                                <p><?php echo esc_html( $second_line_keyword ); ?>: <span class="alarnd__total_qty"><?php esc_html_e( '0', "hello-elementor" ); ?></span></p>
                                <span class="alarnd--total-price">סה"כ: <?php echo wc_price($product->get_regular_price(), array('decimals' => 0)); ?></span>
                            </div>
                            <button type="submit" name="add-to-cart"value="<?php echo esc_attr( $product->get_id() ); ?>" disabled="disabled" class="single_add_to_cart_button button alt ml_add_loading ml_add_to_cart_trigger"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>
                        </div>
                        <div class="alanrd--product-added-message"><?php esc_html_e( 'Added to Cart', "hello-elementor" ); ?></div>
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
        $the_color_title = ! empty( $colors_title ) ? $colors_title : esc_html__('Select a Color', 'hello-elementor');
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
                        <span><?php esc_html_e('Select a Quantity', 'hello-elementor'); ?></span>
                        <?php $the_price = isset( $steps[$last_step]['amount'] ) ? $steps[$last_step]['amount'] : $product->get_regular_price(); ?>
                        <div class="alarnd--custom-qtys-wrap alarnd--single-custom-qty alarnd--single-var-labelonly">
                            <div class="alarnd--single-variable alarnd--hide-price" data-min="<?php echo esc_attr( $steps[0]['quantity'] ); ?>" data-price="<?php echo esc_attr( $the_price ); ?>">
                                <span class="alarnd--single-var-info">
                                    <input type="radio" name="cutom_quantity" id="cutom_quantity_special-custom" value="<?php echo esc_attr( $the_price ); ?>" checked="checked">
                                    <input type="text" name="attribute_quantity" autocomplete="off" pattern="[0-9]*" class="alarnd_custom_input" inputmode="numeric" placeholder="<?php esc_html_e( 'הקלידו כמות…', 'hello-elementor' ); ?>" id="attribute_quanity_custom_val">
                                    <!-- <label for="cutom_quantity_special-custom"><//?//php esc_html_e( 'Custom Quantity', "hello-elementor" ); ?></label> -->
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
                                    <label for="cutom_quantity-<?php echo $key; ?>">
                                        <?php echo esc_html( $step['quantity'] ); ?>
                                    </label>
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
            <div class="alanrd--product-added-message"><?php esc_html_e( 'Added to Cart', "hello-elementor" ); ?></div>
            </form>
           </div>
        </div>
        <?php
        endif;
    }
}

new ML_Ajax();