<?php
/*
Template Name: Custom Logout Page
*/

// Check if it's a custom logout request
if (isset($_GET['custom_logout']) && $_GET['custom_logout'] === 'logout') {
    // Log the user out
    wp_logout();

    // Redirect to a specific page after logout
    wp_redirect(home_url('my-account')); // Redirect to the homepage
    exit;
}
?>

<!-- Your custom logout page content can go here -->
