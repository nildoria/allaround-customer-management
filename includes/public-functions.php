<?php

// Add menu items
function user_management_menu() {
    $user_management_page = add_menu_page(
        'All Customers',
        'All Customers',
        'manage_options',
        'user-management',
        'all_customers_page', // Set the default content to All Customers page
        'dashicons-admin-users'
    );

    add_submenu_page(
        'user-management',
        'Add New Customer',
        'Add New Customer',
        'manage_options',
        'add-new-customer',
        'add_new_customer_page'
    );

    // Enqueue scripts only on the User Management page
    add_action('load-' . $user_management_page, 'enqueue_admin_scripts');
}
add_action('admin_menu', 'user_management_menu');
