<?php

require_once( AlRNDCM_PATH . '/includes/mockup-generator/editor.php');

class ALRN_Genrator {

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
        register_rest_route('alaround-generate/v1', '/save-info', array(
            'methods' => 'POST',
            'callback' => array( $this, 'save_info_callback' ),
            'permission_callback' => '__return_true'
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

        error_log( print_r( $generated_records, true ) );

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
    public function save_image_callback($request) {
        $batch = $request->get_param('batch');
    
        if (empty($batch) || !is_array($batch)) {
            return new WP_Error('invalid_batch', 'Invalid batch data', array('status' => 400));
        }
    
        $success_count = 0;
    
        foreach ($batch as $image_data) {

            // error_log( print_r( $success_count, true ) );

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

            // Output the content
            $value = '<div class="alarnd--mockup-trigger-area">';
            $value .= '<button id="ml_mockup_gen-'.$user_id.'" type="button" class="button button-primary ml_mockup_gen_trigger ml_add_loading" data-settings=\'' . wp_json_encode($user_data) . '\' data-user_id="'.$user_id.'">'.$button_text.'</button>';
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

                error_log( "product_id: $product_id" );

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