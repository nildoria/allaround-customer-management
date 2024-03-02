<?php
/**
 * Show canvas as metabox
 */
class ALRN_Metabox {

    public function __construct() {
        add_action('add_meta_boxes', array( $this, 'add_metabox' ) );
        add_action('admin_enqueue_scripts', array( $this, 'metabox_script' ) );

        add_action('wp_ajax_save_canvas', array( $this, 'save_canvas' ));
        add_action('wp_ajax_remove_data', array( $this, 'remove_data' ));
        add_action('wp_ajax_remove_all_data', array( $this, 'remove_all_data' ));
        add_action('wp_ajax_remove_default_data', array( $this, 'remove_default_data' ));
    }

    public function remove_default_data() {
        check_ajax_referer( 'canvas_ajx_nonce', 'nonce' );

        // Get the user ID from the AJAX request
		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : '';

        if( empty( $product_id ) ) {
            wp_send_json_error();
            wp_die();
        }

        $result = delete_post_meta($product_id, "ml_logos_positions");

        if ($result === true) {
            $featured_img_url = get_the_post_thumbnail_url( $product_id,'alarnd_main_thumbnail');
            $filter_arr = get_positions_by_id( $product_id );
    
            $data = array(
                "logo" => array(
                    "square" => plugin_dir_url(__FILE__) . 'images/square.png',
                    "horizontal" => plugin_dir_url(__FILE__) . 'images/horizontal.png'
                ),
                "back_logo" => array(
                    "square" => plugin_dir_url(__FILE__) . 'images/back-square.png',
                    "horizontal" => plugin_dir_url(__FILE__) . 'images/back-horizontal.png'
                ),
                'positions' => $filter_arr,
                "background" => $featured_img_url
            );
    
            echo wp_json_encode($data);
        }

        wp_die();
    }
    
    public function remove_all_data() {
        check_ajax_referer( 'canvas_ajx_nonce', 'nonce' );

        // Get the user ID from the AJAX request
		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : '';

        if( empty( $product_id ) ) {
            wp_send_json_error();
            wp_die();
        }

        $result = delete_positions_by_id($product_id);

        if ($result === true) {
            $featured_img_url = get_the_post_thumbnail_url( $product_id,'alarnd_main_thumbnail');
            $filter_arr = get_positions_by_id( $product_id );
    
            $data = array(
                "logo" => array(
                    "square" => plugin_dir_url(__FILE__) . 'images/square.png',
                    "horizontal" => plugin_dir_url(__FILE__) . 'images/horizontal.png'
                ),
                "back_logo" => array(
                    "square" => plugin_dir_url(__FILE__) . 'images/back-square.png',
                    "horizontal" => plugin_dir_url(__FILE__) . 'images/back-horizontal.png'
                ),
                'positions' => $filter_arr,
                "background" => $featured_img_url
            );
    
            echo wp_json_encode($data);
        }

        wp_die();
    }
    
    public function remove_data() {
        check_ajax_referer( 'canvas_ajx_nonce', 'nonce' );

        // Get the user ID from the AJAX request
		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : '';
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : '';

        if( empty( $product_id ) || empty( $user_id ) ) {
            wp_send_json_error();
            wp_die();
        }

        $meta_key = "ml_logos_positions_{$user_id}";
        $result = delete_post_meta($product_id, $meta_key);

        if ($result === true) {
            $featured_img_url = get_the_post_thumbnail_url( $product_id,'alarnd_main_thumbnail');
            $filter_arr = get_positions_by_id( $product_id );
    
            $data = array(
                "logo" => array(
                    "square" => plugin_dir_url(__FILE__) . 'images/square.png',
                    "horizontal" => plugin_dir_url(__FILE__) . 'images/horizontal.png'
                ),
                'positions' => $filter_arr,
                "background" => $featured_img_url
            );
    
            echo wp_json_encode($data);
        }

        wp_die();
    }

    public function save_canvas() {
        check_ajax_referer( 'canvas_ajx_nonce', 'nonce' );

        // Get the user ID from the AJAX request
		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : '';
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : '';
		$type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'square';
		$logoNumber = isset($_POST['logoNumber']) ? sanitize_text_field($_POST['logoNumber']) : 'default';
		$logos = isset($_POST['logos']) ? sanitize_text_field($_POST['logos']) : [];

        if( empty( $product_id ) || empty( $logos ) ) {
            wp_send_json_error();
            wp_die();
        }

        $name = "ml_logos_positions";
        if( ! empty( $user_id ) ) {
            $name = "ml_logos_positions_{$user_id}";
        }

        // decode json format value.
        $logos = stripslashes($logos);
        $logos = json_decode($logos, true);

        // remove image key from the array.
        $refoctory_logos = [];
        foreach( $logos as $logo ) {
            unset( $logo['image'] );
            $refoctory_logos[] = $logo;
        }

        if( ! empty( $refoctory_logos ) ) {

            // Get the current post meta value
            $current_meta_value = get_post_meta($product_id, $name, true);

            // Initialize the arrays
            $square_array = [];
            $horizontal_array = [];

            if ($current_meta_value) {
                // If the meta key exists, split the existing values
                $square_array = isset($current_meta_value['square']) ? $current_meta_value['square'] : [];
                $horizontal_array = isset($current_meta_value['horizontal']) ? $current_meta_value['horizontal'] : [];
            }

            if( $type === 'square' ) {
                $square_array = $refoctory_logos;
            } elseif( $type === 'horizontal' ) {
                $horizontal_array = $refoctory_logos;
            }

            // Combine the arrays into the updated meta value
            $updated_meta_value = array(
                'square' => $square_array,
                'horizontal' => $horizontal_array,
                'logoNumber' => $logoNumber
            );

            // Update the post meta
            update_post_meta($product_id, $name, $updated_meta_value);
        }

        $featured_img_url = get_the_post_thumbnail_url( $product_id,'alarnd_main_thumbnail');
        $filter_arr = get_positions_by_id( $product_id );

        $data = array(
            "logo" => array(
                "square" => plugin_dir_url(__FILE__) . 'images/square.png',
                "horizontal" => plugin_dir_url(__FILE__) . 'images/horizontal.png'
            ),
            "back_logo" => array(
                "square" => plugin_dir_url(__FILE__) . 'images/back-square.png',
                "horizontal" => plugin_dir_url(__FILE__) . 'images/back-horizontal.png'
            ),
            'positions' => $filter_arr,
            "background" => $featured_img_url
        );

        echo wp_json_encode($data);
        
        wp_die();
    }

    public function metabox_script() {
        // Check if we are on the product edit page
        if (isset($_GET['post']) && get_post_type($_GET['post']) === 'product') {
            wp_enqueue_style('canvas-metabox', plugin_dir_url(__FILE__) . '/css/canvas.css', array(), AlRNDCM_VERSION);
            wp_enqueue_script('canvas-metabox', plugin_dir_url(__FILE__) . 'js/canvas.js', array('jquery'), AlRNDCM_VERSION, true);

            wp_localize_script('canvas-metabox', 'canvasObj', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce( "canvas_ajx_nonce" )
            ));
        }
    }
    
    // Add a custom metabox to the product edit page
    function add_metabox() {
        add_meta_box(
            'alaround_canvas_id', // Metabox ID
            'Canvas Editor',   // Metabox Title
            array( $this, 'show_canvas' ), // Callback function to display the content
            'product', // Post type (WooCommerce products)
            'normal', // Context (normal, side, advanced)
            'high' // Priority (high, core, default, low)
        );
        
        // add_meta_box(
        //     'alaround_canvas_gen', // Metabox ID
        //     'Mockup Generate',   // Metabox Title
        //     array( $this, 'mockup_generate' ), // Callback function to display the content
        //     'product', // Post type (WooCommerce products)
        //     'side', // Context (normal, side, advanced)
        //     'high' // Priority (high, core, default, low)
        // );
    }

    public function mockup_generate( $post ) {
        $filter_arr = get_positions_by_id( $post->ID );
        if( ! empty( $filter_arr ) ) {
            ?>
            <div class="allaround--product-mockup-gen-wrap">
                <button id="alarndGenerateMockup" data-product_id="<?php echo esc_attr( $post->ID ); ?>" class="button button-primary button-large ml_add_loading">Generate</button>
            </div>
            <?php
        }
    }

    public function show_canvas( $post ) {
        // Retrieve and display your custom fields here
        $featured_img_url = get_the_post_thumbnail_url( $post->ID,'alarnd_main_thumbnail');

        if( empty( $featured_img_url ) ) {
            echo 'You need to add product thumbnail first.';
            return;
        }

        $customers = ml_customer_list();

        if( empty( $customers ) ) {
            echo 'There\'s not customer in the site.';
            return;
        }

        $customer_has_logo = [];
        foreach ( $customers as $customer_id ) {
            $profile_logo = ml_get_image_url('profile_picture_id', $customer_id);

            if( empty( $profile_logo ) ) {
                continue;
            }
            $customer_has_logo[] = $profile_logo;
        }

        if( empty( $customer_has_logo ) || ! isset( $customer_has_logo[0] ) ) {
            echo 'There\'s no customer with logo.';
            return;
        }

        $profile_picture_url = $customer_has_logo[0];

        $filter_arr = get_positions_by_id( $post->ID );

        $data = array(
            "logo" => array(
                "square" => plugin_dir_url(__FILE__) . 'images/square.png',
                "horizontal" => plugin_dir_url(__FILE__) . 'images/horizontal.png'
            ),
            "back_logo" => array(
                "square" => plugin_dir_url(__FILE__) . 'images/back-square.png',
                "horizontal" => plugin_dir_url(__FILE__) . 'images/back-horizontal.png'
            ),
            'positions' => $filter_arr,
            "background" => $featured_img_url
        );

        if( isset( $_GET['dev'] ) && 'true' === $_GET['dev'] ) {
            echo '<pre>';
            print_r( $data );
            echo '</pre>';
        }

        ?>
        <div class="alarnd--canvas-wrapper">
            <div class="alarnd--canvas-ediotr">
                <div class="alarnd--canvas-container">
                    <canvas id='mergedCanvas' data-settings='<?php echo wp_json_encode($data); ?>' width='1000' height='800'></canvas>
                </div>

                <?php
                if( ! empty( $customers ) ) :
                ?>
                <div id="alarnd--main-canvas-editor-wrap">
                    <div class="alarnd--select-logotypes">
                        <label for="logoTypes">Logo Type</label>
                        <select id="logoTypes">
                            <option value="square" selected>Square</option>
                            <option value="horizontal">Horizontal</option>
                        </select>
                    </div>
                    <div class="alarnd--select-customers">
                        <label for="logoSelector">Customer's List</label>
                        <select id="logoSelector">
                            <option value="">Select a customer.</option>
                            <?php 
                            $customer_has_logo = [];
                            foreach ( $customers as $customer_id ) : 

                            $profile_logo = ml_get_image_url('profile_picture_id', $customer_id);
                            $profile_picture_id_second = ml_get_image_url('profile_picture_id_second', $customer_id);

                            $custom_logo_lighter = ml_get_image_url('custom_logo_lighter', $customer_id);
                            $custom_logo_darker = ml_get_image_url('custom_logo_darker', $customer_id);

                            if( empty( $profile_logo ) ) {
                                continue;
                            }
                            $customer_has_logo[$customer_id] = $profile_logo;
                            $user_info = get_userdata($customer_id);
                            ?>
                            <option 
                                value="<?php echo esc_url( $profile_logo ); ?>"
                                data-second_logo="<?php echo esc_attr($profile_picture_id_second); ?>" 
                                data-custom_logo_lighter="<?php echo esc_attr($custom_logo_lighter); ?>" 
                                data-custom_logo_darker="<?php echo esc_attr($custom_logo_darker); ?>" 
                                data-user_id="<?php echo esc_attr( $customer_id ); ?>"
                            ><?php echo esc_html( $user_info->display_name ); ?> (<?php echo esc_html( $user_info->user_email ); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <select id="logoNumber">
                            <option value="default" selected>Lighter Logo</option>
                            <option value="second">Darker Logo</option>
                        </select>
                    </div>
                
                    <?php
                    endif;
                    ?>
                    <!-- <button id="defaultSelect" class="button">Select As Default</button> -->
                    <button id="addLogo" class="button">Add Logo</button>
                    <button id="addBackLogo" class="button">Add Back Logo</button>
                    <button id="removeLogo" class="button">Remove Logo</button>
                    <button id="deselectLogo" class="button">Deselect Logo</button>
                    <input type="hidden" id="ml_product_id" value="<?php echo esc_attr( $post->ID ); ?>">
                    <button id="undoResizeBtn" class="button">Undo Resize</button>
                    <button id="removeUserDataBtn" class="button" disabled="disabled">Remove User Data</button>
                    <button id="removeAllDataBtn" class="button" disabled="disabled">Remove All Data</button>
                    <button id="removeDefaultDataBtn" class="button" disabled="disabled">Remove Default Data</button>
                    <div class="rotate-controls">
                        <label for="rotationInput">Rotate:</label>
                        <input type="number" id="rotationInput" class="small-text" step="1" min="-360" max="360" value="0">
                        <button id="rotateLeft" class="button">Rotate Left</button>
                        <button id="rotateRight" class="button">Rotate Right</button>
                        <button id="showInfo" class="button">Show Info</button>
                    </div>
                    <div class="alarnd--canvas-save-wrap">
                        <button id="alarndSaveCanvas" class="button button-primary button-large ml_add_loading">Save</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

new ALRN_Metabox();