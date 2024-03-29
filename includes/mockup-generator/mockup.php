<?php

require_once( AlRNDCM_PATH . '/includes/mockup-generator/editor.php');

class ALRN_Genrator {

    /**
	 * @var MLBackgroundProcess
	 */
	protected $process_all;

    /**
	 * Call this method to get singleton
	 *
	 * @return singleton instance of OW_Utility
	 */
	public static function instance() {

		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new ALRN_Genrator();
		}

		return $instance;
	}

    public function __construct() {
        add_filter('manage_users_columns', array( $this, 'users_column' ) );
        add_action('manage_users_custom_column', array( $this, 'column_content' ), 10, 3);
		add_action('manage_users_sortable_columns', array( $this, 'registered_column_sortable' ) );
		
        add_action('admin_enqueue_scripts', array( $this, 'generator_scripts' ));
        // add_action('wp_ajax_get_generate_button', array( $this, 'get_generate_button' ));

        add_action( 'rest_api_init', array($this, 'generate_endpoint') );
        add_filter('bulk_actions-users', array( $this, 'bulk_action' ));
		
		add_filter('users_list_table_query_args', array( $this, 'custom_user_orderby' ));

        $this->process_all = new MLBackgroundProcess();
       

    }
	
	function custom_user_orderby($args) {
        if ( is_admin() && isset( $args['orderby'] ) && 'mockup_last_generated_time' === $args['orderby'] ) {   
            $meta_query   = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : [];
            $meta_query[] = array(
                'relation'      => 'OR',
                'has_generated_time' => array(
                    'key'  => 'mockup_last_generated_time',
                    'type' => 'NUMERIC',
                ),
                'no_generated_time'  => array(
                    'key'     => 'mockup_last_generated_time',
                    'compare' => 'NOT EXISTS',
                ),
            );
    
            $args['meta_query'] = $meta_query;
            $args['orderby']    = 'has_generated_time';
        }
    
        return $args;
    }

    function bulk_action($actions) {
        $actions['alaround_mockup_gen'] = __('Generate Mockup', 'hello-elementor');
        return $actions;
    }
    
    function generate_endpoint() {
        register_rest_route('alaround-generate/v1', '/save-image', array(
            'methods' => 'POST',
            'callback' => array( $this, 'save_image_callback' ),
            'permission_callback' => '__return_true'
        ));
        register_rest_route('alaround-generate/v1', '/user-mockups', array(
            'methods' => 'POST',
            'callback' => array( $this, 'get_user_mockups' ),
            'permission_callback' => '__return_true'
        ));
        register_rest_route('alaround-generate/v1', '/product-generate', array(
            'methods' => 'POST',
            'callback' => array( $this, 'product_generate' ),
            'permission_callback' => '__return_true'
        ));
        register_rest_route('alaround-generate/v1', '/save-info', array(
            'methods' => 'POST',
            'callback' => array( $this, 'save_info_callback' ),
            'permission_callback' => '__return_true'
        ));
    }

    function getLightnessByID($data, $productId) {
        foreach ($data as $item) {
            // Check if at least one of logo_lighter or logo_darker is not empty
            if (($item['logo_lighter'] !== '' || $item['logo_darker'] !== '') && in_array($productId, $item['select_products'])) {
                return [
                    'lighter' => $item['logo_lighter'],
                    'darker' => $item['logo_darker'],
                    'shape' => isset($item['shape']) && $item['shape'] !== '-- Select --' ? strtolower($item['shape']) : ''
                ];
            }
        }
    
        return null;
    }
    
    function getLighter($data, $logo) {
        if ($data && isset($data['lighter']) && $data['lighter'] !== false) {
            return $data['lighter'];
        }
    
        return $logo;
    }
    
    function getDarker($data, $logo) {
        if ($data && isset($data['darker']) && $data['darker'] !== false) {
            return $data['darker'];
        }
    
        return $logo;
    }

    public function product_generate( $request ) {
        $product_id = $request->get_param('product_id');

        if ( empty( $product_id ) || ! get_post_status( $product_id ) ) {
            error_log("productId can't be empty: {$product_id}");
            return new WP_Error('invalid_info', "productId can't be empty: {$product_id}", array('status' => 400));
        }

        $is_running = get_post_meta( $product_id, 'ml_mockup_generation_running', true );

        if ( $is_running ) {
            error_log("Background process is running for product ID: $product_id");
            return rest_ensure_response(array(
                "message" => "Background process is running for product ID: ${product_id}.",
            ));
        }

        error_log( "mockup generation starts for product: $product_id" );

        $featured_img_url = get_the_post_thumbnail_url( $product_id,'full');

        if( empty( $featured_img_url ) ) {
            error_log("There's no featured image for this product: {$product_id}");
            return new WP_Error('invalid_info', "There's no featured image for this product: {$product_id}", array('status' => 400));
        }

        $get_users = ml_get_users_by_product( $product_id );
        if ( empty( $get_users ) ) {
            error_log("There's no users for this product: {$product_id}");
            return new WP_Error('invalid_info', "There's no users for this product: {$product_id}", array('status' => 400));
        }

        $remap_user_lists = array();
        $get_all_items = [];
        foreach( $get_users as $user_id => $user_data ) {
            
            $product_info = isset( $user_data['images'][$product_id] ) ? $user_data['images'][$product_id] : array();
            if( empty( $product_info ) ) {
                continue;
            }

            $profile_picture_id = get_field('profile_picture_id', "user_{$user_id}");
            $profile_picture_url = ml_get_image_url('profile_picture_id', $user_id);
            
            $profile_second_logo = ml_get_image_url('profile_picture_id_second', $user_id);
            if (! filter_var($profile_second_logo, FILTER_VALIDATE_URL)) {
                $profile_second_logo = '';
            }
    
            if( empty( $profile_picture_id ) || empty( $profile_picture_url ) || ! @getimagesize($profile_picture_url) )
                continue;
    
            $thumbnails = $user_data['images'];
            $logo_positions = $user_data['logo_positions'];
    
            $custom_logo_lighter = ml_get_image_url('custom_logo_lighter', $user_id);
            $custom_logo_darker = ml_get_image_url('custom_logo_darker', $user_id);
            $custom_logo_products = get_field('custom_logo_products', "user_{$user_id}");
            $override_shape = get_field('override_shape', 'user_' . $user_id);
            $default_logo_shape = get_field('default_logo_shape', 'user_' . $user_id);
            $custom_logo_shape = get_field('custom_logo_shape', 'user_' . $user_id);
            $logo_collections = get_field('logo_collections', 'user_' . $user_id);
            $logo_collections = ml_map_logo_collections($logo_collections);
    
            $type = ml_get_orientation( $profile_picture_id );
            $custom_type = '';
    
            $override_logo_shape = $override_custom_logo_shape = false;
            if ($override_shape && in_array($default_logo_shape, array('square', 'horizontal'))) {
                $type = $default_logo_shape;
                $override_logo_shape = $default_logo_shape;
            }
    
            if ($override_shape && in_array($custom_logo_shape, array('square', 'horizontal'))) {
                $custom_type = $custom_logo_shape;
                $override_custom_logo_shape = $custom_logo_shape;
            }
            
            $custom_logo_data = array(
                "lighter" => $custom_logo_lighter,
                "darker" => $custom_logo_darker,
                "allow_products" => $custom_logo_products
            );
        
            foreach( $thumbnails as $product_id => $thumbnail ) {
                
                // if $thumbnail['thumbnail'] is empty, skip
                if( 
                    ! isset( $thumbnail['thumbnail'] ) || 
                    ! isset( $thumbnail['thumbnail'][0] ) || 
                    empty( $thumbnail['thumbnail'][0] )
                ) {
                    continue;
                }
    
                $getLogoData = isset( $logo_positions[$product_id] ) ? (array) $logo_positions[$product_id] : [];
                $logoData = array( $product_id => $getLogoData );
    
                // if $logo_positions[$product_id] is empty, skip
                if( ! isset( $logo_positions[$product_id] ) ||  empty( $logo_positions[$product_id] ) ) {
                    continue;
                }
    
                $product_thumbnail = $thumbnail['thumbnail'];
    
                $storeLogo = $profile_picture_url;
                $storeLogoType = $type;
                $storeLogoSecond = $profile_second_logo;
                
                if ( ! empty( $logo_collections ) && count($logo_collections) !== 0) {
                    $itemData = $this->getLightnessByID($logo_collections, $product_id);
                
                    $override_logo = $override_logo_shape;
                
                    // Check if $itemData is not null
                    // Although it's checked first, it's just an extra layer of security
                    if ( ! empty( $itemData ) ) {
                        $storeLogo = $this->getLighter($itemData, $profile_picture_url);
                        $storeLogoSecond = $this->getDarker($itemData, $profile_second_logo);
                
                        if ($profile_picture_url && ! empty( $profile_picture_url ) && ($override_logo === '' || $override_logo === false)) {
                            
                            // Check if $itemData['shape'] is empty or value is square or horizontal
                            if ($itemData['shape'] !== '' && ($itemData['shape'] === 'square' || $itemData['shape'] === 'horizontal')) {
                                $storeLogoType = $itemData['shape'];
                            }
                        }
                    }
                }
    
                $galleries = false;
                $product_gallery = isset( $thumbnail['galleries'] ) ? (array) $thumbnail['galleries'] : [];
                if ($product_gallery && ! empty($product_gallery) && count($product_gallery) !== 0) {
                    $galleries = $product_gallery;
                }
    
                $queue_item = array(
                    'backgroundUrl' => $product_thumbnail[0],
                    'user_id' => $user_id,
                    'product_id' => $product_id,
                    'logo' => $storeLogo,
                    'second_logo' => $storeLogoSecond,
                    'custom_logo' => $custom_logo_data,
                    'logo_positions' => $logoData,
                    'logo_type' => $storeLogoType,
                    'custom_logo_type' => $custom_type,
                    'task_id' => "product_{$product_id}",
                    'galleries' => $galleries
                );
    
                $get_all_items[] = $queue_item;
            }
        }

        if( ! empty( $get_all_items ) ) {
            // loop only five items
            // $get_all_items = array_slice($get_all_items, 0, 3);
            foreach ( $get_all_items as $item ) {
                $this->process_all->push_to_queue( $item );
            }
            $this->process_all->save()->dispatch();
    
            $total_items = count( $get_all_items );
            return rest_ensure_response(array(
                "message" => "$total_items items images generate queue set from product ${product_id}.",
            ));
            
        }

        return rest_ensure_response(array(
            "message" => "Something went wrong. Please try again.",
        ));

    }

    public function get_user_mockups( $request ) {
        $user_id = $request->get_param('user_id');

        if ( empty( $user_id ) ) {
            error_log("UserID can't be empty.");
            return new WP_Error('invalid_info', "UserID can't be empty.", array('status' => 400));
        }

        $is_running = get_user_meta( $user_id, 'ml_mockup_generation_running', true );

        if ( $is_running ) {
            error_log("Background process is running for user ID: $user_id");
            return new WP_Error('invalid_info', "Background process is running for user ID: $user_id", array('status' => 400));
        }

        error_log( "mockup generation starts for user:$user_id" );

        $user = get_userdata( $user_id );
        if ( $user === false ) {
            error_log("user {$user_id} id does not exist");
            return new WP_Error('invalid_info', "user {$user_id} id does not exist", array('status' => 400));
        }

        $profile_picture_id = get_field('profile_picture_id', "user_{$user_id}");
        $profile_picture_url = ml_get_image_url('profile_picture_id', $user_id);
        
        $profile_second_logo = ml_get_image_url('profile_picture_id_second', $user_id);
        if (! filter_var($profile_second_logo, FILTER_VALIDATE_URL)) {
            $profile_second_logo = '';
        }

        if( empty( $profile_picture_id ) || empty( $profile_picture_url ) || ! @getimagesize($profile_picture_url) )
            return $value;

        $thumbnails = $this->get_thumbnails( $user_id );
        $logo_positions = $this->logo_positions( $user_id );

        $custom_logo_lighter = ml_get_image_url('custom_logo_lighter', $user_id);
        $custom_logo_darker = ml_get_image_url('custom_logo_darker', $user_id);
        $custom_logo_products = get_field('custom_logo_products', "user_{$user_id}");
        $override_shape = get_field('override_shape', 'user_' . $user_id);
        $default_logo_shape = get_field('default_logo_shape', 'user_' . $user_id);
        $custom_logo_shape = get_field('custom_logo_shape', 'user_' . $user_id);
        $logo_collections = get_field('logo_collections', 'user_' . $user_id);
        $logo_collections = ml_map_logo_collections($logo_collections);

        $type = ml_get_orientation( $profile_picture_id );
        $custom_type = '';

        $override_logo_shape = $override_custom_logo_shape = false;
        if ($override_shape && in_array($default_logo_shape, array('square', 'horizontal'))) {
            $type = $default_logo_shape;
            $override_logo_shape = $default_logo_shape;
        }

        if ($override_shape && in_array($custom_logo_shape, array('square', 'horizontal'))) {
            $custom_type = $custom_logo_shape;
            $override_custom_logo_shape = $custom_logo_shape;
        }
        
        $custom_logo_data = array(
            "lighter" => $custom_logo_lighter,
            "darker" => $custom_logo_darker,
            "allow_products" => $custom_logo_products
        );

        $get_all_items = [];

        foreach( $thumbnails as $product_id => $thumbnail ) {
            
            // if $thumbnail['thumbnail'] is empty, skip
            if( 
                ! isset( $thumbnail['thumbnail'] ) || 
                ! isset( $thumbnail['thumbnail'][0] ) || 
                empty( $thumbnail['thumbnail'][0] )
            ) {
                continue;
            }

            $getLogoData = isset( $logo_positions[$product_id] ) ? (array) $logo_positions[$product_id] : [];
            $logoData = array( $product_id => $getLogoData );

            // if $logo_positions[$product_id] is empty, skip
            if( ! isset( $logo_positions[$product_id] ) ||  empty( $logo_positions[$product_id] ) ) {
                continue;
            }

            $product_thumbnail = $thumbnail['thumbnail'];

            $storeLogo = $profile_picture_url;
            $storeLogoType = $type;
            $storeLogoSecond = $profile_second_logo;

            // error_log( print_r( $product_id, true ) );

            if ( ! empty( $logo_collections ) && count($logo_collections) !== 0) {
                $itemData = $this->getLightnessByID($logo_collections, $product_id);
            
                $override_logo = $override_logo_shape;
            
                // Check if $itemData is not null
                // Although it's checked first, it's just an extra layer of security
                if ( ! empty( $itemData ) ) {
                    $storeLogo = $this->getLighter($itemData, $profile_picture_url);
                    $storeLogoSecond = $this->getDarker($itemData, $profile_second_logo);
            
                    if ($profile_picture_url && ! empty( $profile_picture_url ) && ($override_logo === '' || $override_logo === false)) {
                        
                        // Check if $itemData['shape'] is empty or value is square or horizontal
                        if ($itemData['shape'] !== '' && ($itemData['shape'] === 'square' || $itemData['shape'] === 'horizontal')) {
                            $storeLogoType = $itemData['shape'];
                        }
                    }
                }
            }

            $galleries = false;
            $product_gallery = isset( $thumbnail['galleries'] ) ? (array) $thumbnail['galleries'] : [];
            if ($product_gallery && ! empty($product_gallery) && count($product_gallery) !== 0) {
                $galleries = $product_gallery;
            }

            $queue_item = array(
                'backgroundUrl' => $product_thumbnail[0],
                'user_id' => $user_id,
                'product_id' => $product_id,
                'logo' => $storeLogo,
                'second_logo' => $storeLogoSecond,
                'custom_logo' => $custom_logo_data,
                'logo_positions' => $logoData,
                'logo_type' => $storeLogoType,
                'custom_logo_type' => $custom_type,
                'task_id' => "user_{$user_id}",
                'galleries' => $galleries
            );

            $get_all_items[] = $queue_item;
        }

        // error_log( print_r( $get_all_items, true ) );

        if( ! empty( $get_all_items ) ) {
            foreach ( $get_all_items as $item ) {
                $this->process_all->push_to_queue( $item );
            }
            $this->process_all->save()->dispatch();
    
            $total_items = count( $get_all_items );
            return rest_ensure_response(array(
                "message" => "$total_items items images generate queue set from user: ${user_id}.",
            ));
            
        }

        return rest_ensure_response(array(
            "message" => "Something went wrong. Please try again.",
        ));
    }

    public function save_info_callback($request) {
        $user_id = $request->get_param('user_id');
        $start_time = $request->get_param('start_time');
        $end_time = $request->get_param('end_time');
        $total_items = $request->get_param('total_items');


        if ( empty($start_time) || empty($end_time) || empty( $user_id ) ) {
            return new WP_Error('invalid_info', 'Invalid info data', array('status' => 400));
        }

        // Get existing mockup generate records
        $generated_records = get_user_meta($user_id, 'mockup_generated_records', true);

        // If no existing records, initialize an empty array
        if (empty($generated_records)) {
            $generated_records = array();
        }

        // Add the current timestamp to the records array
        $generated_records[] = array(
            'start_time' => $start_time,
            'end_time' => $end_time,
            "generated" => $total_items
        );

        // error_log( print_r( $generated_records, true ) );

        $limit = 500;
        // Limit the records to 100 by removing older records
        if (count($generated_records) > $limit) {
            $generated_records = array_slice($generated_records, -$limit, $limit, true);
        }

        // Save the updated records array in user meta
        update_user_meta($user_id, 'mockup_generated_records', $generated_records);
		update_user_meta($user_id, 'mockup_last_generated_time', $end_time);

        return rest_ensure_response("Generated records saved successfully.");

    }
    
    // Callback function to save the image
    public function save_image_callback($request, $type = '') {

        if( $type === 'array' ) {
            $batch = $request;
        } else {
            $batch = $request->get_param('batch');
        }
    
        if (empty($batch) || !is_array($batch)) {
            return new WP_Error('invalid_batch', 'Invalid batch data', array('status' => 400));
        }
    
        $success_count = 0;
    
        foreach ($batch as $image_data) {

            $filename          = $image_data['filename'];
            $is_feature_image  = $image_data['is_feature_image'];
            $user_id           = $image_data['user_id'];
            $image_data        = $image_data['dataURL'];
    
            // Ensure the user_id is numeric and not empty
            if (empty($user_id) || !is_numeric($user_id)) {
                continue; // Skip the current iteration and move to the next one
            }
    
            // Sanitize the filename to prevent directory traversal
            $filename = sanitize_file_name($filename);
    
            // Create the user-specific directory if it doesn't exist
            $user_directory = AlRNDCM_UPLOAD_DIR . '/' . $user_id;
            if (!is_dir($user_directory)) {
                mkdir($user_directory, 0755, true); // Create the directory recursively
            }
    
            // Construct the full path to save the original image
            $original_image_path = $user_directory . '/' . $filename;
    
            // Decode and save the image data to a file (original size)
            $decoded_image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image_data));
            file_put_contents($original_image_path, $decoded_image_data);

            // Load the original image using GD library
            $original_image = imagecreatefromstring($decoded_image_data);
    
            // Check if image creation was successful
            if ($original_image !== false) {
                // Calculate the new dimensions while preserving the aspect ratio
                list($original_width, $original_height) = getimagesize($original_image_path);
                $max_dimension = 1500; // Maximum dimension for the resized image
    
                $resize_data = array(
                    "width" => 500,
                    "height" => 500,
                    "original_height" => $original_height,
                    "original_width" => $original_width,
                    "original_image" => $original_image,
                    "filename" => $filename,
                    "user_directory" => $user_directory,
                    "name" => "wc_thumb_"
                );
    
                $this->create_resize_image($resize_data);
            }

            $success_count++;
        }
    
        if ($success_count > 0) {
            return rest_ensure_response("{$success_count} image(s) saved successfully");
        } else {
            return new WP_Error('image_processing_error', 'Error processing the image', array('status' => 500));
        }
    }
    
    
    function create_resize_image($data) {
        $max_dimension_width = $data['width'];
        $max_dimension_height = isset( $data['height'] ) ? $data['height'] : '';
        $original_height = $data['original_height'];
        $original_width = $data['original_width'];
        $original_image = $data['original_image'];
        $filename = $data['filename'];
        $user_directory = $data['user_directory'];
        $name = $data['name'];

        if( ! empty( $max_dimension_height ) ) {
            $aspect_ratio = $original_width / $original_height;
            $new_width = min($max_dimension_width, $max_dimension_width); // Limit the width to 400 pixels
            $new_height = $new_width / $aspect_ratio;
            
            // Check if the calculated height exceeds the maximum height
            if ($new_height > $max_dimension_height) {
                $new_height = $max_dimension_height;
                $new_width = $new_height * $aspect_ratio;
            }
        } else {
            if ($original_width > $original_height) {
                $new_width = $max_dimension_width;
                $new_height = ($original_height / $original_width) * $max_dimension_width;
            } else {
                $new_height = $max_dimension_width;
                $new_width = ($original_width / $original_height) * $max_dimension_width;
            }
        }

        // Create an empty image with the new dimensions
        $resized_image = imagecreatetruecolor($new_width, $new_height);
    
        // Resize the image while preserving the aspect ratio
        imagecopyresampled($resized_image, $original_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

        // Construct the full path to save the resized image
        $resized_image_path = $user_directory . '/' . $name . $filename;

        // Save the resized image
        imagejpeg($resized_image, $resized_image_path, 100); // You can adjust the quality (90 in this example)

        imagedestroy($resized_image);
    }


    // Enqueue jQuery and the JavaScript file
    function generator_scripts() {

        // Check if this is the Users List page or User Edit page
        $current_screen = get_current_screen();
        
        wp_register_style('mockup-generator', plugin_dir_url(__FILE__) . '/css/admin.css', array(), AlRNDCM_VERSION);

        wp_register_script('mockup-generator', plugin_dir_url(__FILE__) . 'js/mockup.js', array('jquery'), AlRNDCM_VERSION, true);

        $upload_dir = wp_upload_dir();
        // Pass AJAX URL to the script

        $background_enabled = get_field('enable_logo_background', 'option');
        $background_enabled = $background_enabled ? 'true' : 'false';

        wp_localize_script('mockup-generator', 'mockupGeneratorAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce( "mockup_gen_nonce" ),
            'generate_file' => plugin_dir_url(__FILE__) . 'js/image-generate.js',
            'image_save_endpoint' => rest_url( 'alaround-generate/v1/save-image' ),
            'info_save_endpoint' => rest_url( 'alaround-generate/v1/save-info' ),
            'user_mockup_generate' => rest_url( 'alaround-generate/v1/user-mockups' ),
            'product_mockup_generate' => rest_url( 'alaround-generate/v1/product-generate' ),
            'background_enabled' => $background_enabled,
            'upload_foler' => $upload_dir['basedir'] . "/alaround-mockup"
        ));

        // Check if it's the product post type edit screen
        if ($current_screen && $current_screen->post_type === 'product') {
            wp_enqueue_style('mockup-generator');
            wp_enqueue_script('jquery');
            wp_enqueue_script('mockup-generator');
        }

        // Check if it's the users.php page
        if ($current_screen && $current_screen->base === 'users') {
            wp_enqueue_style('mockup-generator');
            wp_enqueue_script('jquery');
            wp_enqueue_script('mockup-generator');
        }

    }
	
	function registered_column_sortable( $columns ) {
		return wp_parse_args( array( 'registration_date' => 'registered', 'last_generate_time' => 'mockup_last_generated_time' ), $columns );
	}

    function users_column($columns) {
        $columns['mockup_generate'] = esc_html__('Mockup Generate', 'hello-elementor');
		$columns['registration_date'] = esc_html__('Registered Time', 'hello-elementor');
		$columns['last_generate_time'] = esc_html__('Last Generated', 'hello-elementor');
        return $columns;
    }

    public function get_user_data( $user_id, $value = false, $filter_product_id = '' ) {
        $profile_picture_id = get_field('profile_picture_id', "user_{$user_id}");
            $profile_picture_url = ml_get_image_url('profile_picture_id', $user_id);
            
            $profile_second_logo = ml_get_image_url('profile_picture_id_second', $user_id);
            if (! filter_var($profile_second_logo, FILTER_VALIDATE_URL)) {
                $profile_second_logo = '';
            }

            if( empty( $profile_picture_url ) || ! @getimagesize($profile_picture_url) )
                return $value;

            $thumbnails = $this->get_thumbnails( $user_id, $filter_product_id );
            $logo_positions = $this->logo_positions( $user_id, $filter_product_id );

            $custom_logo_lighter = ml_get_image_url('custom_logo_lighter', $user_id);
            $custom_logo_darker = ml_get_image_url('custom_logo_darker', $user_id);
            $custom_logo_products = get_field('custom_logo_products', "user_{$user_id}");
            $override_shape = get_field('override_shape', 'user_' . $user_id);
            $default_logo_shape = get_field('default_logo_shape', 'user_' . $user_id);
            $custom_logo_shape = get_field('custom_logo_shape', 'user_' . $user_id);
            $logo_collections = get_field('logo_collections', 'user_' . $user_id);
            $logo_collections = ml_map_logo_collections($logo_collections);

            $type = ml_get_orientation( $profile_picture_id );
            $custom_type = '';

            $override_logo_shape = $override_custom_logo_shape = false;
            if ($override_shape && in_array($default_logo_shape, array('square', 'horizontal'))) {
                $type = $default_logo_shape;
                $override_logo_shape = $default_logo_shape;
            }

            if ($override_shape && in_array($custom_logo_shape, array('square', 'horizontal'))) {
                $custom_type = $custom_logo_shape;
                $override_custom_logo_shape = $custom_logo_shape;
            }
            
            $custom_logo_data = array(
                "lighter" => $custom_logo_lighter,
                "darker" => $custom_logo_darker,
                "allow_products" => $custom_logo_products
            );

             // Get existing mockup generate records
            $generated_records = get_user_meta($user_id, 'mockup_generated_records', true);
            $generated_records = ml_format_timestamps( $generated_records );

            $collections = [];
            if( ! empty( $logo_collections ) ) {
                $collections = array(
                    'collections' => $logo_collections,
                    'override_logo' => $override_logo_shape,
                    'override_custom_logo' => $override_custom_logo_shape
                );
            }

            $user_data = array(
                'user_id' => $user_id,
                'logo' => $profile_picture_url,
                'logo_second' => $profile_second_logo,
                'logo_type' => $type,
                'custom_logo_type' => $custom_type,
                'images' => $thumbnails,
                'logo_collections' => $collections,
                'logo_positions' => $logo_positions
            );

            if( ! empty( $custom_logo_data ) ) {
                $user_data['custom_logo_data'] = $custom_logo_data;
            }

            return $user_data;
    }

    function column_content($value, $column_name, $user_id) {
		if( $column_name === 'registration_date' ) {
            $registred_time = ml_display_user_registration_time($user_id);
            $value .= $registred_time;
        }
		
		if( $column_name === 'last_generate_time' ) {
            $last_generated = ml_get_last_generated_time($user_id);
            $value .= $last_generated;
        }
		
        if ($column_name === 'mockup_generate') {

            if( ! ml_user_has_role( $user_id, 'customer' ) ) {
                return $value;
            }

            $button_text = __("Generate", "hello-elementor");
            $user_data = $this->get_user_data($user_id, $value);
            $type = isset(  $user_data['logo_type'] ) ? $user_data['logo_type'] : '';

            if( $user_id === 2 && isset( $_GET['dev'] ) && 'true' === $_GET['dev'] ) {
                echo '<pre>';
                echo "<h2>$user_id</h2>";
                echo '</pre>';
                echo '<pre>';
                // print_r( $generated_records );
                print_r( $user_data );
                echo '</pre>';
            }
            
            // update_post_meta( $product_id, 'ml_mockup_generation_running', false );
            $is_running = get_user_meta( $user_id, 'ml_mockup_generation_running', true );
            $class = '';
            if( $is_running ) {
                $class = 'ml_loading';
            }

            // Output the content
            $value = '<div class="alarnd--mockup-trigger-area">';
            $value .= '<button id="ml_mockup_gen-'.$user_id.'" type="button" class="button button-primary ml_mockup_gen_trigger ml_add_loading '.$class.'" data-settings=\'' . wp_json_encode($user_data) . '\' data-user_id="'.$user_id.'">'.$button_text.'</button>';
            // if( isset( $_GET['dev'] ) && 'true' === $_GET['dev'] ) {
                $value .= '<div>'.$type.'</div>';
            // }
            $value .= '</div>';
        }
        return $value;
    }

    function add_element_outside_user_form() {
        global $pagenow;
    
        // Check if we are on the user edit page
        if ($pagenow === 'user-edit.php') {
            $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
            echo '<div>This is an element outside the user form.</div>';
        }
    }

    /**
	 * Get all product thumbnail as array by users select products
	 *
	 * @param int $user_id
     * @param int $filter_product_id 
	 * @return array
	 */
	function get_thumbnails( $user_id, $filter_product_id = '' ) {
        $selected_product_ids = ml_get_user_products($user_id);

        // Create an array to store product categories
        $thumbnails = array();
        
        if ( ! empty($selected_product_ids) ) {

            // Collect categories for filtering
            foreach ($selected_product_ids as $product) {
                if( ! isset( $product['value'] ) || empty( $product['value'] ) )
                    continue;
			
				$product_id = (int) $product['value'];

                if( ! empty( $filter_product_id ) && $product_id !== $filter_product_id ) {
                    continue;
                }

                // error_log( "product_id: $product_id" );

                $galleries = get_color_thumbnails( $product_id );

				$featured_img_url = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'alarnd_main_thumbnail');

				// skip if featured img empty somehow.
				if( empty($featured_img_url) ) {
					continue;
				}

                $positions = get_positions_by_id( $product_id );
                // skip if no position set yet.
				if( empty($positions) ) {
					continue;
				}

				$thumbnails[$product_id] = array(
                    'thumbnail' => $featured_img_url,
                    'galleries' => $galleries
                );
            }
		}

		return $thumbnails;
	}

    function logo_positions( $user_id, $filter_product_id = '' ) {
        $selected_product_ids = ml_get_user_products($user_id);

        // Create an array to store product categories
        $positions = array();
        
        if ( ! empty($selected_product_ids) ) {

            // Collect categories for filtering
            foreach ($selected_product_ids as $product) {
                if( ! isset( $product['value'] ) || empty( $product['value'] ) )
                    continue;
			
				$product_id = (int) $product['value'];

                if( ! empty( $filter_product_id ) && $product_id !== $filter_product_id ) {
                    continue;
                }

				$logo_positons = $this->get_metas($product_id);

				// skip if featured img empty somehow.
				if( empty($logo_positons) ) {
					continue;
				}

				$positions[$product_id] = $logo_positons;
            }
		}

		return $positions;
	}

    public function get_metas($product_id) {
        global $wpdb;

        $prefix = 'ml_logos_positions';

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


    

    public function create_dir($name) {

		$dir_url = AlRNDCM_UPLOAD_DIR . '/' . $name;

		// check if directory exists
		if( file_exists( $dir_url ) ) {
			// $this->deleteFiles($dir_url);
		} else {
			mkdir($dir_url, 0755);
		}

        return $dir_url;
	}

    /**
	 * delete all files from folder
	 *
	 * @param string $dir
	 * @return void
	 */
	function deleteFiles($dir)
	{
		// loop through the files one by one
		foreach(glob($dir . '/*') as $file){
			// check if is a file and not sub-directory
			if(is_file($file)){
				// delete file
				unlink($file);
			}
		}
	}


}

new ALRN_Genrator();