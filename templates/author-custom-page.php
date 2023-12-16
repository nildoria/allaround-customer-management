<?php

get_header(); // Include header template

$logged_user = wp_get_current_user();
$logged_user_id = $logged_user->ID;

// Get the current author's username from the URL
$current_author = get_query_var('author_name');

// Get the author's user data
$get_current_puser = get_user_by('login', $current_author);        
$current_user_id = $get_current_puser->ID;
?>

<div id="primary" class="content-area aum_auth_page">
    <main id="main" class="site-main" role="main" data-user_id="<?php echo esc_attr( $current_user_id ); ?>">    

        <?php

        $token = get_field('token', "user_{$current_user_id}");
        $phone = ml_get_user_phone($current_user_id);
        $is_tokenpayout_show = false;
        if( 
            $current_author === $logged_user->user_login &&
            ! empty( $token )
        )  {
            $is_tokenpayout_show = true;
        }

        $is_login_form_show = false;
        if( is_author() && ! is_user_logged_in() ) {
            if( $current_user && isset( $current_user->ID ) ) {
                $token = get_field('token', "user_{$current_user_id}");
                $phone = ml_get_user_phone($current_user_id);
                if( ! empty( $token ) && ! empty( $phone ) ) {
                    $is_login_form_show = true;
                }
            }    
        }
        
        if( $is_login_form_show === true ) {
            echo '<div class="alarnd_login_form_main alarnd--load-overlay">';
            echo '<div class="alarnd--overlay loading"></div>';
            echo woocommerce_login_form();
            echo '</div>';
        } else {

        $profile_picture_id = get_field('profile_picture_id', "user_{$current_user_id}");
        $user_header_title = get_field('user_header_title', "user_{$current_user_id}");
        $profile_picture_url = wp_get_attachment_image_url($profile_picture_id, 'medium');

       
        $tick = '<img src="'.AlRNDCM_URL.'assets/images/verified.png" class="verified_tick" loading="lazy" /> ';
        

        if (in_array('customer', $get_current_puser->roles)) {
            echo '<div class="author-header aum-container">';
            echo '<div class="welcome-column">';
            echo '<h1>היי, ' . (($user_header_title) ? esc_html($user_header_title) : esc_html($get_current_puser->display_name)) . ' '.$tick.'</h1>';

            echo '<input type="hidden" id="ml_username_hidden" value="'.$current_author.'" />';
            
            echo '<p>עיצבנו ויצרנו חנות אישית משלך, שבה תוכל להזמין בקלות לצרכי החברה שלך.</p>';
            // Logout button
            echo '</div>';
            echo '<div class="profile-picture-column">';
            if (!empty($profile_picture_url)) {
                echo '<img src="' . esc_url($profile_picture_url) . '" alt="Profile Picture" loading="lazy">';
            } else {
                echo 'N/A';
            }
            echo '</div>';
            echo '</div>';

        } else {
            // Display login form if not logged in
            if (!is_user_logged_in()) {
                echo '<h1>Login to Access</h1>';
                wp_login_form();
            } else {
                echo '<p>Access denied.</p>';
            }
        }
        ?>
        <div class="aum-customer-elementor-widget">
        <!-- Customer page Promo Section -->
        <section class="minStore-profile-promi-section">
            <div class="miniStore-promo-container">
                <div class="miniStore-promo-item">
                    <div class="miniStore-promo-icon">
                        <img src="<?php echo (AlRNDCM_URL); ?>/assets/images/device-mini.svg" class="miniStore-promo-icon-img" loading="lazy" alt="Promo Icon" />
                    </div>
                    <h3 class="miniStore-promo-title">משלוחים מהירים</h3>
                    <div class="miniStore-promo-text">
                        <p>משלוח מהיר לכל הארץ ע"י שליח בעלות של 29 ש"ח או איסוף עצמי בתאום מראש מגבעתיים.</p>
                    </div>
                </div>
                <div class="miniStore-promo-item">
                    <div class="miniStore-promo-icon">
                        <img src="<?php echo (AlRNDCM_URL); ?>/assets/images/rocket-mini.svg" class="miniStore-promo-icon-img" loading="lazy" alt="Promo Icon" />
                    </div>
                    <h3 class="miniStore-promo-title">משלוחים מהירים</h3>
                    <div class="miniStore-promo-text">
                        <p>משלוח מהיר לכל הארץ ע"י שליח בעלות של 29 ש"ח או איסוף עצמי בתאום מראש מגבעתיים.</p>
                    </div>
                </div>
                <div class="miniStore-promo-item">
                    <div class="miniStore-promo-icon">
                        <img src="<?php echo (AlRNDCM_URL); ?>/assets/images/hand-mini.svg" class="miniStore-promo-icon-img" loading="lazy" alt="Promo Icon" />
                    </div>
                    <h3 class="miniStore-promo-title">משלוחים מהירים</h3>
                    <div class="miniStore-promo-text">
                        <p>משלוח מהיר לכל הארץ ע"י שליח בעלות של 29 ש"ח או איסוף עצמי בתאום מראש מגבעתיים.</p>
                    </div>
                </div>
            </div>
        </section>
        </div>

        <?php
        echo '<section class="allaround--products-section">';
        echo '<div class="alarnd--overlay"></div>';

        // Selected Product Ids for the User
        // $selected_product_ids = get_user_meta($get_current_puser->ID, 'selected_products', true);
        $selected_product_ids = ml_get_user_products($current_user_id);

        // Create an array to store product categories
        $product_categories = array();
        
        if (!empty($selected_product_ids)) {

            echo '<div class="product-filter">';
            // Collect categories for filtering
            foreach ($selected_product_ids as $product) {
                if( ! isset( $product['value'] ) || empty( $product['value'] ) )
                    continue;

                $product_id = $product['value'];
                $product = wc_get_product($product_id);
                if ($product) {
                    $terms = wp_get_post_terms($product_id, 'product_cat');
                    foreach ($terms as $term) {
                        $product_categories[$term->term_id] = $term;
                    }
                }
            }

            // Display category filters
            echo '<button class="filter-button" data-filter="*">'.esc_html__("All", "allaroundminilng").'</button>';
            foreach ($product_categories as $category) {
                echo '<button class="filter-button" data-filter=".category-' . $category->term_id . '">' . esc_html($category->name) . '</button>';
            }
            
            echo '</div>';

            echo '<div class="woocommerce">';

            $items = $selected_product_ids;
            $itemsPerPage = ml_products_per_page();
            $totalItems = count($items);
            $totalPages = ceil($totalItems / $itemsPerPage);
            $currentpage = isset($_GET['list']) ? (int)$_GET['list'] : 1;

            $start = ($currentpage - 1) * $itemsPerPage;
            $end = $start + $itemsPerPage;
            $itemsToDisplay = array_slice($items, $start, $itemsPerPage);
            $big = 999999999; // need an unlikely integer

            echo '<ul id="allaround_products_list" data-user_id="'.esc_attr( $current_user_id ).'" class="mini-store-product-list product-list-container products columns-3">';
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
                            <span>זמין בצבע אחד</span>
                        </div>
                    <?php 
                    endif;
                    // Price

                    echo '<p class="mini_productCard_price">' . $product->get_price_html() . '</p>';
                    
                    // Buttons
                    echo '<div class="product-buttons">';
                    if( ! empty( $discount_steps ) || ! empty( $pricing_description ) || ! empty( $customQuantity_steps ) ) {
                        echo '<a href="#alarnd__pricing_info-'. $product->get_id() .'" class="view-details-button alarnd_view_pricing_cb" data-product_id="'. $product->get_id() .'">כמות, מחיר ומבחר</a>';
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
                                        <h5>תמחור כמות</h5>
                                        <div class="alarn--price-chart">
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
                                        <h5>תמחור כמות</h5>
                                        <div class="alarn--price-chart">
                                            <div class="alarnd--price-chart-price <?php echo count($customQuantity_steps) > 4 ? 'alarnd--plus4item-box' : ''; ?>">
                                                <?php 
                                                $index = 0;
                                                foreach( $customQuantity_steps as $step ) :
                                                $prev = ($index == 0) ? false : $customQuantity_steps[$index-1];                            
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

                                    <div class="modal-bottom-btn">
                                        <button type="button" class="alarnd_trigger_details_modal ml_add_loading" data-product_id="<?php echo $product->get_id(); ?>"><?php esc_html_e( 'הוסף לעגלה שלך', 'hello-elementor' ); ?></button>
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
            ?>
            <div class="allaround--loadmore-wrap">
                <button type="button" class="alarnd--regular-button alarnd--loadmore-trigger ml_add_loading button" data-page_num="1"><?php esc_html_e("Load More", "allaroundminilng"); ?></button>
            </div>
            <?php
            echo '</div>'; // End mini-store-product-list woocommerce
        }
        echo '</section>'; // end .allaround--products-section section
        ?>

        <div class="cart-page alarnd--cart-wrapper-main" id="woocommerce_cart">
            <div class="alarnd--cart-wrapper-inner alarnd--full-width">
                <h2>העגלה שלך</h2>
                <?php echo do_shortcode('[woocommerce_cart]'); ?>
            </div>
        </div>

        <div class="alarnd--custom-checkout-section<?php echo WC()->cart->is_empty() ? ' ml_pay_hidden' : ''; ?>" id="ministore--custom-checkout-section">

            <?php if( is_user_logged_in() ) : ?>
                <?php
                if( $is_tokenpayout_show === true ) : ?>
                <?php echo alarnd_single_checkout($logged_user_id); ?>
                <?php else : ?>
                    <div class="alarnd--woocommerce-checkout-page alarnd--default-visible">
                        <div class="alarnd-checkout-wrap-inner">
                            <?php echo allaround_card_form(); ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="alarnd--woocommerce-checkout-page alarnd--default-visible">
                    <div class="alarnd-checkout-wrap-inner">
                        <?php echo allaround_card_form(); ?>
                    </div>
                </div>
            <?php endif; ?>
        
        </div>

        <div id="product-quick-view"></div>
        <?php } ?> <!-- $is_login_form_show end -->
    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer(); // Include footer template