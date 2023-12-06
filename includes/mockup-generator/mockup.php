<?php

require_once( AlRNDCM_PATH . '/includes/mockup-generator/editor.php');

class ALRN_Genrator {

    public function __construct() {
        add_filter('manage_users_columns', array( $this, 'users_column' ) );
        add_action('manage_users_custom_column', array( $this, 'column_content' ), 10, 3);

        add_action('admin_enqueue_scripts', array( $this, 'generator_scripts' ));
        add_action('wp_ajax_get_generate_button', array( $this, 'get_generate_button' ));

        add_action( 'rest_api_init', array($this, 'generate_endpoint') );
        add_filter('bulk_actions-users', array( $this, 'bulk_action' ));
    }

    function bulk_action($actions) {
        $actions['alaround_mockup_gen'] = __('Generate Mockup', 'allaroundminilng');
        return $actions;
    }
    
    function generate_endpoint() {
        register_rest_route('alaround-generate/v1', '/save-image', array(
            'methods' => 'POST',
            'callback' => array( $this, 'save_image_callback' ),
            'permission_callback' => '__return_true'
        ));
    }
    
    // Callback function to save the image
    function save_image_callback($request) {
        $image_data = $request->get_param('imageData'); // Retrieve image data from the request
        $filename = $request->get_param('filename'); // Retrieve image data from the request
        $is_feature_image = $request->get_param('is_feature_image'); // Retrieve image data from the request
        $user_id = $request->get_param('user_id'); // Retrieve image data from the request

        $is_feature_image = ! empty($is_feature_image) ? boolval($is_feature_image) : '';
    
        // Ensure the user_id is numeric and not empty
        if (empty($user_id)) {
            return new WP_Error('invalid_user_id', 'Invalid user_id', array('status' => 400));
        }
    
        // Sanitize the filename to prevent directory traversal
        $filename = sanitize_file_name($filename);
    
        // Create the user-specific directory if it doesn't exist
        $user_directory = AlRNDCM_UPLOAD_DIR . '/' . $user_id;
        if (!is_dir($user_directory)) {
            mkdir($user_directory, 0755); // Create the directory recursively
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
            $max_dimension = 600; // Maximum dimension for the resized image

            $resize_data = array(
                "width" => 600,
                "original_height" => $original_height,
                "original_width" => $original_width,
                "original_image" => $original_image,
                "filename" => $filename,
                "user_directory" => $user_directory,
                "name" => "resized_"
            );

            $this->create_resize_image($resize_data);

            if( true === $is_feature_image ) {
                $resize_data = array(
                    "width" => 400,
                    "height" => 300,
                    "original_height" => $original_height,
                    "original_width" => $original_width,
                    "original_image" => $original_image,
                    "filename" => $filename,
                    "user_directory" => $user_directory,
                    "name" => "wc_thumb_"
                );
    
                $this->create_resize_image($resize_data);
            }
    
            // Free up memory
            imagedestroy($original_image);
    
            return rest_ensure_response('Original and resized images saved successfully');
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
        imagejpeg($resized_image, $resized_image_path, 90); // You can adjust the quality (90 in this example)

        imagedestroy($resized_image);
    }


    // Enqueue jQuery and the JavaScript file
    function generator_scripts() {

        // Check if this is the Users List page or User Edit page
        global $pagenow;
        
        if (($pagenow === 'users.php') || ($pagenow === 'user-edit.php')) {
            wp_enqueue_style('mockup-generator', plugin_dir_url(__FILE__) . '/css/admin.css', array(), AlRNDCM_VERSION);

            wp_enqueue_script('jquery');
            wp_enqueue_script('mockup-generator', plugin_dir_url(__FILE__) . 'js/mockup.js', array('jquery'), AlRNDCM_VERSION, true);
    
            $upload_dir = wp_upload_dir();
            // Pass AJAX URL to the script

            wp_localize_script('mockup-generator', 'mockupGeneratorAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce( "mockup_gen_nonce" ),
                'generate_file' => plugin_dir_url(__FILE__) . 'js/image-generate.js',
                'image_save_endpoint' => rest_url( 'alaround-generate/v1/save-image' ),
                'upload_foler' => $upload_dir['basedir'] . "/alaround-mockup"
            ));
        }

    }

    function users_column($columns) {
        $columns['mockup_generate'] = esc_html__('Mockup Generate', 'allaroundminilng');
        return $columns;
    }

    function column_content($value, $column_name, $user_id) {
        if ($column_name === 'mockup_generate') {

            if( ! ml_user_has_role( $user_id, 'customer' ) ) {
                return $value;
            }

            $profile_picture_id = get_field('profile_picture_id', "user_{$user_id}");
            // $type = get_field('logo_type', "user_{$user_id}");
            // $type = empty( $type ) ? 'square' : esc_attr( $type );
            $profile_picture_url = wp_get_attachment_image_url($profile_picture_id, 'full');

            $profile_second_logo = get_field('profile_picture_id_second', "user_{$user_id}");
            if (! filter_var($profile_second_logo, FILTER_VALIDATE_URL)) {
                $profile_second_logo = '';
            }

            if( empty( $profile_picture_id ) || empty( $profile_picture_url ) || ! @getimagesize($profile_picture_url) )
                return $value;

            // user meta.
            $progress = get_user_meta($user_id, 'mockup_generation_status', true);

            $button_text = __("Generate", "allaroundminilng");
            // if( "completed" === $progress ) {
            //     $button_text = __("Regenerate", "allaroundminilng");
            // };

            $thumbnails = $this->get_thumbnails( $user_id );
            $logo_positions = $this->logo_positions( $user_id );

            $custom_logo_lighter = get_field('custom_logo_lighter', "user_{$user_id}");
            $custom_logo_darker = get_field('custom_logo_darker', "user_{$user_id}");
            $custom_logo_products = get_field('custom_logo_products', "user_{$user_id}");

            $type = ml_get_orientation( $profile_picture_id );

            $custom_logo_data = array(
                "lighter" => $custom_logo_lighter,
                "darker" => $custom_logo_darker,
                "allow_products" => $custom_logo_products
            );

            $user_data = array(
                'user_id' => $user_id,
                'logo' => $profile_picture_url,
                'logo_second' => $profile_second_logo,
                'logo_type' => $type,
                'images' => $thumbnails,
                'logo_positions' => $logo_positions
            );

            if( ! empty( $custom_logo_data ) ) {
                $user_data['custom_logo_data'] = $custom_logo_data;
            }

            if( isset( $_GET['dev'] ) && 'true' === $_GET['dev'] ) {
                echo '<pre>';
                echo "<h2>$user_id</h2>";
                print_r( $logo_positions );
                echo '</pre>';
                echo '<pre>';
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

    public function get_generate_button() {
        check_ajax_referer( 'mockup_gen_nonce', 'nonce' );

        // Get the user ID from the AJAX request
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if ($user_id > 0) {

            if( ! ml_user_has_role( $user_id, 'customer' ) ) {
                wp_die();
            }

            $profile_picture_id = get_field('profile_picture_id', "user_{$user_id}");
            // $type = get_field('logo_type', "user_{$user_id}");
            // $type = empty( $type ) ? 'square' : esc_attr( $type );
            $profile_picture_url = wp_get_attachment_image_url($profile_picture_id, 'full');

            $profile_second_logo = get_field('profile_picture_id_second', "user_{$user_id}");
            if (! filter_var($profile_second_logo, FILTER_VALIDATE_URL)) {
                $profile_second_logo = '';
            }

            if( empty( $profile_picture_id ) || empty( $profile_picture_url ) || ! @getimagesize($profile_picture_url) )
                wp_die();

            // user meta.
            $progress = get_user_meta($user_id, 'mockup_generation_status', true);

            $button_text = __("Generate", "allaroundminilng");
            // if( "completed" === $progress ) {
            //     $button_text = __("Regenerate", "allaroundminilng");
            // };

            $thumbnails = $this->get_thumbnails( $user_id );
            $logo_positions = $this->logo_positions( $user_id );

            $custom_logo_lighter = get_field('custom_logo_lighter', "user_{$user_id}");
            $custom_logo_darker = get_field('custom_logo_darker', "user_{$user_id}");
            $custom_logo_products = get_field('custom_logo_products', "user_{$user_id}");

            $type = ml_get_orientation( $profile_picture_id );

            $custom_logo_data = array(
                "lighter" => $custom_logo_lighter,
                "darker" => $custom_logo_darker,
                "allow_products" => $custom_logo_products
            );

            // echo '<pre>';
            // echo "<h2>$user_id</h2>";
            // print_r( $logo_positions );
            // echo '</pre>';

            $user_data = array(
                'user_id' => $user_id,
                'logo' => $profile_picture_url,
                'logo_second' => $profile_second_logo,
                'logo_type' => $type,
                'images' => $thumbnails,
                'logo_positions' => $logo_positions
            );

            if( ! empty( $custom_logo_data ) ) {
                $user_data['custom_logo_data'] = $custom_logo_data;
            }

            // Output the content
            $value = '<div class="alarnd--mockup-trigger-wrap"><div class="alarnd--mockup-trigger-area">';
            $value .= '<button id="ml_mockup_gen-'.$user_id.'" type="button" class="button button-primary ml_mockup_gen_trigger ml_add_loading" data-settings=\'' . wp_json_encode($user_data) . '\' data-user_id="'.$user_id.'">'.$button_text.'</button>';
            $value .= '</div></div>';

            echo $value;
        }

        wp_die();
    }

    /**
	 * Get all product thumbnail as array by users select products
	 *
	 * @param int $user_id
	 * @return array
	 */
	function get_thumbnails( $user_id ) {
        $selected_product_ids = ml_get_user_products($user_id);

        // Create an array to store product categories
        $thumbnails = array();
        
        if ( ! empty($selected_product_ids) ) {

            // Collect categories for filtering
            foreach ($selected_product_ids as $product) {
                if( ! isset( $product['value'] ) || empty( $product['value'] ) )
                    continue;
			
				$product_id = (int) $product['value'];
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

    function logo_positions( $user_id ) {
        $selected_product_ids = ml_get_user_products($user_id);

        // Create an array to store product categories
        $positions = array();
        
        if ( ! empty($selected_product_ids) ) {

            // Collect categories for filtering
            foreach ($selected_product_ids as $product) {
                if( ! isset( $product['value'] ) || empty( $product['value'] ) )
                    continue;
			
				$product_id = (int) $product['value'];

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