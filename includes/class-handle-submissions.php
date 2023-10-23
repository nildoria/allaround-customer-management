<?php


// Handle form submission
function handle_customer_submission() {
    if (isset($_POST['add_customer'])) {
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $author_page_url = isset($_POST['author_page_url']) ? esc_url_raw($_POST['author_page_url']) : '';
        $profile_picture_id = isset($_POST['profile_picture_id']) ? intval($_POST['profile_picture_id']) : '';
        $selected_products = isset($_POST['selected_products']) ? array_map('intval', $_POST['selected_products']) : [];

        $user_id = username_exists($username);
        $email_exists = email_exists($email);
        
        if (!$user_id && !$email_exists) {
            $random_password = wp_generate_password(12, false);
            $user_id = wp_create_user($username, $random_password, $email);
            
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id);
                $user->set_role('customer');
                // Save author_page_url in user meta
                $author_page_url_slug = sanitize_text_field($_POST['author_page_url']);

                $userfields = array(
                    'first_name',
                    'last_name',
                    'token',
                    'author_page_url',
                    'profile_picture_id',
                    'selected_products'
                );

                foreach( $userfields as $field ) {
                    update_user_meta($user_id, $fild, ${$field});
                }
                
                echo '<div class="updated"><p>Customer added successfully!</p></div>';
            } else {
                echo '<div class="error"><p>Failed to add customer.</p></div>';
            }
        } else {
            echo '<div class="error"><p>';
            if ($user_id) {
                echo 'Username already exists. ';
            }
            if ($email_exists) {
                echo 'Email address is already registered.';
            }
            echo '</p></div>';
        }
    }
}
add_action('admin_init', 'handle_customer_submission');


// Handle customer deletion
function handle_customer_deletion() {
    if (isset($_GET['action']) && $_GET['action'] === 'delete_customer' && isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);

        if ($user_id > 0 && current_user_can('delete_users')) {
            wp_delete_user($user_id);
            echo '<div class="updated"><p>Customer deleted successfully!</p></div>';
        } else {
            echo '<div class="error"><p>Sorry, you do not have the permission to perform this action.</p></div>';
        }
    }
}
add_action('admin_init', 'handle_customer_deletion');