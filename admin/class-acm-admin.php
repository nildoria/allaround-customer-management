<?php

// Enqueue scripts
function enqueue_admin_scripts() {
    // Enqueue custom CSS for admin side
    wp_enqueue_style('aum-css-admin', plugin_dir_url(__FILE__) . '/css/aum-css-admin.css');

    wp_enqueue_script('jquery');
    wp_enqueue_media(); // Enqueue media scripts
}
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts');