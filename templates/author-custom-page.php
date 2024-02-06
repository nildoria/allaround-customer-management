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

        $default_header_content = get_field('default_header_content', 'option');
        $profile_picture_id = get_field('profile_picture_id', "user_{$current_user_id}");
        $user_header_title = get_field('user_header_title', "user_{$current_user_id}");
        $user_header_content = get_field('user_header_content', "user_{$current_user_id}");
        $profile_picture_url = wp_get_attachment_image_url($profile_picture_id, 'medium');

       
        $tick = '<img src="'.AlRNDCM_URL.'assets/images/verified.png" class="verified_tick" loading="lazy" /> ';
        

        if (in_array('customer', $get_current_puser->roles)) {
            echo '<div class="author-header aum-container">';
            echo '<div class="welcome-column">';
            echo '<h1>היי, ' . (($user_header_title) ? esc_html($user_header_title) : esc_html($get_current_puser->display_name)) . ' '.$tick.'</h1>';

            echo '<input type="hidden" id="ml_username_hidden" value="'.$current_author.'" />';
            
            echo '<p>' . (($user_header_content) ? esc_html($user_header_content) : (!empty($default_header_content) ? esc_html($default_header_content) : ' ')) . '</p>';

            // Logout button
            echo '</div>';
            echo '<div class="profile-picture-column">';
            if (!empty($profile_picture_url)) {
                echo '<img src="' . esc_url($profile_picture_url) . '" alt="Profile Picture" loading="lazy">';
            } else {
                echo 'N/A';
            }
            echo '<a href="' . esc_url(home_url('/') . $current_author . '/about') . '" class="aboutus--page-slug"></a>';
            echo '<a href="' . esc_url(home_url('/') . $current_author . '/services') . '" class="services--page-slug"></a>';
            echo '<a href="' . esc_url(home_url('/') . $current_author . '/contact') . '" class="contact--page-slug"></a>';
            echo '<a href="' . esc_url(home_url('/') . $current_author) . '" class="load--username"></a>';
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
                        <h3 class="miniStore-promo-title">כאן בשבילך</h3>
                        <div class="miniStore-promo-text">
                            <p>לכל שאלה, התלבטות או התייעצות, נשמח לעזור בכל דבר. זמינים במגוון דרכים: טלפון, אימייל וכמובן גם בווטסאפ.</p>
                        </div>
                    </div>
                    <div class="miniStore-promo-item">
                        <div class="miniStore-promo-icon">
                            <img src="<?php echo (AlRNDCM_URL); ?>/assets/images/rocket-mini.svg" class="miniStore-promo-icon-img" loading="lazy" alt="Promo Icon" />
                        </div>
                        <h3 class="miniStore-promo-title">משלוחים מהירים</h3>
                        <div class="miniStore-promo-text">
                            <p>בין 2-5 ימי עסקים וההזמנה אצלכם! ובנוסף, משלוח חינם ע"י שליח עד הבית בכל הזמנה מעל 500 ש"ח</p>
                        </div>
                    </div>
                    <div class="miniStore-promo-item">
                        <div class="miniStore-promo-icon">
                            <img src="<?php echo (AlRNDCM_URL); ?>/assets/images/hand-mini.svg" class="miniStore-promo-icon-img" loading="lazy" alt="Promo Icon" />
                        </div>
                        <h3 class="miniStore-promo-title">פשוט ומהיר</h3>
                        <div class="miniStore-promo-text">
                            <p>יצרנו מערכת אישית בשבילך שבכמה קליקים פשוטים תוכלו לבצע הזמנה ולשדרג את העסק שלכם, ממש בכמה דקות</p>
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
            
            echo '<button class="filter_active filter_item" data-category="all">'.esc_html__("All", "hello-elementor").'</button>';
            foreach ($product_categories as $category) {
                echo '<button class="filter_item" data-filter=".category-' . $category->term_id . '" data-category="' . $category->term_id . '">' . esc_html($category->name) . '</button>';
            }

            echo '</div>';

            echo '<div class="allaround--products-filter-container">';

            echo ml_get_filter_content( $current_user_id, 'all' );
            foreach ($product_categories as $category) {
                echo ml_get_filter_content( $current_user_id, $category->term_id );
            }

            echo '</div>'; // End allaround--products-filter-container
        }
        echo '</section>'; // end .allaround--products-section section
        ?>

        <div class="cart-page alarnd--cart-wrapper-main" id="woocommerce_cart">
            <div class="alarnd--cart-wrapper-inner alarnd--full-width">
                <h2>העגלה שלך</h2>
                <?php echo do_shortcode('[woocommerce_cart]'); ?>
            </div>
        </div>

        <div class="alarnd--custom-checkout-section<?php echo WC()->cart->is_empty() ? ' ml_pay_hidden-not' : ''; ?>" id="ministore--custom-checkout-section">

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
<div id="loader">
    <div class="loader-cont">
        <div><?php esc_html__('Loading...', 'hello-elementor') ?></div>
    </div>
</div>

<?php
get_footer(); // Include footer template