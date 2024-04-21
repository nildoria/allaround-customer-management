<?php

class ML_Generation_Options {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array( $this, 'scripts' ) );
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_menu() {
        add_submenu_page(
            'options-general.php', // Parent menu slug (Settings)
            'ML Generation Options', // Page title
            'ML Generation', // Menu title
            'manage_options', // Capability required to access the menu
            'ml_generation_options', // Menu slug
            array($this, 'options_page_output') // Callback function to output the page content
        );
    }

    public function scripts($hook) {
        if ($hook == 'settings_page_ml_generation_options') {
            wp_enqueue_style('ml-generation-options', AlRNDCM_URL . "includes/mockup-generator/css/options.css", array(), AlRNDCM_VERSION);
            wp_enqueue_script('ml-generation-options', AlRNDCM_URL . "includes/mockup-generator/css/options.js", array('jquery'), AlRNDCM_VERSION, true);

            wp_localize_script('ml-generation-options', 'genOptions', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce( "ml_generation_nonce" )
            ));
        }
    }

    public function options_page_output() {

        // get current tab from query string
        if (isset($_GET['tab'])) {
            $tab = $_GET['tab'];
        } else {
            $tab = 'general';
        }

        ?>
        <div class="wrap">
            <h1>ML Generation Options</h1>
            <?php settings_errors(); ?>
            <h2 class="nav-tab-wrapper">
                <a href="?page=ml_generation_options&tab=general" class="nav-tab <?php echo ($tab == 'general' || !isset($tab)) ? 'nav-tab-active' : ''; ?>">General</a>
                <a href="?page=ml_generation_options&tab=tools" class="nav-tab <?php echo ($tab == 'tools') ? 'nav-tab-active' : ''; ?>">Tools</a>
                <a href="?page=ml_generation_options&tab=logs" class="nav-tab <?php echo ($tab == 'logs') ? 'nav-tab-active' : ''; ?>">Logs</a>
            </h2>
            <div id="tab_container">
                <?php
                $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
                switch ($active_tab) {
                    case 'tools':
                        $this->tools_tab_output();
                        break;
                    case 'logs':
                        // Output for Logs tab
                        $this->logs_tab_output();
                        break;
                    default:
                        $this->general_tab_output();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function register_settings() {
        // Register setting for "Add test mode for API" checkbox
        register_setting('ml_generation_options', 'ml_add_test_mode_for_api');
        // Register setting for "Add Background Process for User" checkbox
        register_setting('ml_generation_options', 'ml_disable_background_process_for_user');
        // Register setting for "User Notification Endpoint"
        register_setting('ml_generation_options', 'ml_user_notification_endpoint');
        
        // Add section for "General" tab
        add_settings_section('ml_general_settings_section', 'General Settings', array($this, 'general_settings_section_output'), 'ml_generation_options');
        
        // Add field for "Add test mode for API" checkbox
        add_settings_field('ml_add_test_mode_for_api', 'Add test mode for payment', array($this, 'ml_add_test_mode_for_api_callback'), 'ml_generation_options', 'ml_general_settings_section');

        // Add field for "Add Background Process for User" checkbox
        add_settings_field('ml_disable_background_process_for_user', 'Disabled Background Process for User', array($this, 'ml_disable_background_process_for_user_callback'), 'ml_generation_options', 'ml_general_settings_section');

        // Add field for "User Notification Endpoint"
        add_settings_field('ml_user_notification_endpoint', 'User Notification Endpoint', array($this, 'ml_user_notification_endpoint_callback'), 'ml_generation_options', 'ml_general_settings_section');
    }

    public function general_settings_section_output() {
        // Output section description if needed
        echo '<p>General settings for ML Generation.</p>';
    }

    public function ml_user_notification_endpoint_callback() {
        // Get the current value of the option
        $ml_user_notification_endpoint = get_option('ml_user_notification_endpoint');
        ?>
        <input type="text" id="ml_user_notification_endpoint" class="regular-text ltr" name="ml_user_notification_endpoint" value="<?php echo esc_attr($ml_user_notification_endpoint); ?>" /> <br />
        <label for="ml_user_notification_endpoint">Enter make.com webhook endpoint address to send notification after mockup generation is completed.</label>
        <?php
    }

    public function ml_disable_background_process_for_user_callback() {
        // Get the current value of the option
        $ml_disable_background_process_for_user = get_option('ml_disable_background_process_for_user');
        ?>
        <input type="checkbox" id="ml_disable_background_process_for_user" name="ml_disable_background_process_for_user" <?php checked($ml_disable_background_process_for_user, 'on'); ?> />
        <label for="ml_disable_background_process_for_user">After checking, background process will be off and  the old mockup generation will be on for each user's mockup generation.</label>
        <?php
    }

    public function ml_add_test_mode_for_api_callback() {
        // Get the current value of the option
        $ml_add_test_mode_for_api = get_option('ml_add_test_mode_for_api');
        ?>
        <input type="checkbox" id="ml_add_test_mode_for_api" name="ml_add_test_mode_for_api" <?php checked($ml_add_test_mode_for_api, 'on'); ?> />
        <label for="ml_add_test_mode_for_api">After checking, test card payments will work even if make.com sends a failed response. This functionality is solely for staging or testing purposes. Therefore, do not use it on the LIVE site.</label>
        <?php
    }

    public function general_tab_output() {
        ?>
        <div class="wrap">
            <form method="post" class="ml_general_form" action="options.php">
                <?php settings_fields('ml_generation_options'); ?>
                <?php do_settings_sections('ml_generation_options'); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function tools_tab_output() {
        ?>
        <div class="wrap">
            <h2>Tools</h2>
            <form method="post" action="">
                <label for="tool_action">Select Action:</label>
                <select name="tool_action" id="tool_action">
                    <option value="">None</option>
                    <option value="remove_meta" <?php selected( isset($_POST['tool_action']) && $_POST['tool_action'] === 'remove_meta' ); ?>>Remove Meta</option>
                    <option value="dispatch" <?php selected( isset($_POST['tool_action']) && $_POST['tool_action'] === 'dispatch' ); ?>>Dispatch</option>
                </select>
                <?php wp_nonce_field( 'tool_action_nonce', '_wpnonce' ); ?>
                <input type="submit" name="submit_tool_action" class="button button-primary" value="Submit">
            </form>
        </div>
        <?php
    
        // Handle tool actions
        // if ( isset($_POST['submit_tool_action']) && isset($_POST['tool_action']) && wp_verify_nonce($_POST['_wpnonce'], 'tool_action_nonce') ) {
        //     $tool_action = sanitize_text_field($_POST['tool_action']);
    
        //     switch ($tool_action) {
        //         case 'remove_meta':
        //             $this->update_users_meta();
        //             $this->update_products_meta();
        //             break;
        //         case 'dispatch':
        //             // Perform action to dispatch
        //             break;
        //         default:
        //             // Default action
        //             break;
        //     } 
        // }
    }

    public function update_users_meta() {
        $all_customers = ml_customer_list();

        foreach ($all_customers as $customer_id) {
            if (empty($customer_id))
                continue;

            // Update mockup generation status to false
            update_user_meta($customer_id, 'ml_mockup_generation_running', false);
        }
    }

    public function update_products_meta() {
        $args = array(
            'numberposts' => 1000,
            'post_type' => 'product',
            'post_status' => 'publish'
        );
    
        $products = get_posts($args);

        foreach ($products as $product) {
            // error_log( "productID " . $product->ID . "" );
            // Update mockup generation status to false
            update_post_meta($product->ID, 'ml_mockup_generation_running', false);
        }
    }

    public function logs_tab_output() {
        // Get list of log files
        $log_files = $this->get_log_files();

        // Handle deleting all logs
        if (isset($_POST['delete_all_logs'])) {
            $log_files = $this->delete_all_logs();
            echo '<div class="notice notice-success"><p>All log files have been deleted.</p></div>';
            echo '<p>No log files found.</p>';
            return;
        }

        if( empty( $log_files ) ) {
            echo '<p>No log files found.</p>';
            return;
        }

        $delete_log = isset($_POST['delete_log']) ? true : false;
        $delete_log_file = isset($_POST['delete_log_file']) ? sanitize_text_field( $_POST['delete_log_file'] ) : '';

        // Delete log file if delete button clicked
        if ( true === $delete_log ) {
            $log_file_path = WP_CONTENT_DIR . '/ml-logs/' . $delete_log_file;
            if (file_exists($log_file_path)) {
                // unset $delete_log_file from $log_Files
                unset($log_files[array_search($delete_log_file, $log_files)]);
            }
        }

        $first_log = '';
        if( ! empty( $log_files ) ) {
            // Get the first log file
            $first_log = $this->get_first_log( $log_files );
        }
        
        $selected_log_file = isset($_POST['log_file']) ? sanitize_text_field( $_POST['log_file'] ) : $first_log;

        // echo '<pre>';
        // print_r( $log_files );
        // echo '</pre>';

        // echo '<pre>';
        // print_r( $selected_log_file );
        // echo '</pre>';


        

        // Output dropdown menu and delete button
        echo '<div class="ml_select_log_container">';
        echo '<form method="post" class="ml_select_log_form" action="">';
        echo '<label for="log_file">Select Log File:</label>';
        echo '<select name="log_file" id="log_file">';
        foreach ($log_files as $log_file) {
            $selected = ( $log_file == $selected_log_file ) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($log_file) . '" ' . $selected . '>' . esc_html($log_file) . '</option>';
        }
        echo '</select>';
        echo '<input type="submit" class="button" name="show_log" value="Show Log">';
        echo '</form>';
        echo '<form method="post" class="ml_delete_log_form" action="" onsubmit="return confirm(\'Are you sure you want to delete all log files?\');">';
        echo '<input type="submit" class="button button-primary" name="delete_all_logs" value="Delete All Logs">';
        echo '</form>';
        echo '</div>';

        // Delete log file if delete button clicked
        if ( true === $delete_log ) {
            $deleted_log_file_path = WP_CONTENT_DIR . '/ml-logs/' . $delete_log_file;
            if (file_exists($deleted_log_file_path)) {
                unlink($deleted_log_file_path);
                echo '<div class="notice notice-success"><p>Log file deleted successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Log file not found!</p></div>';
            }
        }

        // Display log info if selected
        if ( ! empty( $selected_log_file ) ) {
            $log_content = file_get_contents(WP_CONTENT_DIR . '/ml-logs/' . $selected_log_file);
            echo '<h3>' . esc_html($selected_log_file) . '</h3>';
            echo '<pre style="margin: 20px 0; padding: 10px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; font-family: Consolas, Monaco, \'Andale Mono\', \'Ubuntu Mono\', monospace; font-size: 14px; line-height: 1.5; white-space: pre-wrap;">' . esc_html($log_content) . '</pre>';
            // Display delete button
            echo '<form method="post" action="" onsubmit="return confirm(\'Are you sure you want to delete this log file?\');">';
            echo '<input type="hidden" name="delete_log_file" value="' . esc_attr($selected_log_file) . '">';
            echo '<input type="submit" class="button button-primary" name="delete_log" value="Delete Log">';
            echo '</form>';
        }
    }

    public function get_log_files() {
        $log_files = glob(WP_CONTENT_DIR . '/ml-logs/*.log');
        $log_files = array_map('basename', $log_files);
        return $log_files;
    }

    public function delete_all_logs() {
        // Get list of log files
        $log_files = $this->get_log_files();
    
        // Delete each log file
        foreach ($log_files as $log_file) {
            $log_file_path = WP_CONTENT_DIR . '/ml-logs/' . $log_file;
            if (file_exists($log_file_path)) {
                unlink($log_file_path);
            }
        }

        return $log_files;
    }

    public function get_first_log( $log_files ) {

        // Check if ml-debug.log exists
        if (in_array('ml-debug.log', $log_files)) {
            return 'ml-debug.log';
        }

        // Sort log files by numeric prefix if present
        usort($log_files, function($a, $b) {
            preg_match('/\d+/', $a, $matches_a);
            preg_match('/\d+/', $b, $matches_b);
            $num_a = isset($matches_a[0]) ? intval($matches_a[0]) : PHP_INT_MAX;
            $num_b = isset($matches_b[0]) ? intval($matches_b[0]) : PHP_INT_MAX;
            if ($num_a != $num_b) {
                return $num_a - $num_b;
            } else {
                return strcmp($a, $b);
            }
        });

        // Return the first log file
        return $log_files[0];
    }

}

// Instantiate the class
new ML_Generation_Options();
