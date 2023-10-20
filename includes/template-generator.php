<?php

function my_template_lists() {
    $temps = [];

    // $temps['author-custom-page.php'] = "Author Custom Page";
    $temps['custom-logout.php'] = "Custom Logout Page";

    return $temps;
}

function ml_template_register($page_templates, $theme, $post) {
    $mytemplates = my_template_lists();

    foreach( $mytemplates as $key => $template ) {
        $page_templates[$key] = $template;
    }

    return $page_templates;
}
add_filter('theme_page_templates', 'ml_template_register', 10, 3);

function ml_template_include($template) {

    global $post, $wpdb, $wp_query;

    $custom_author = get_query_var('author_name');

    if (!empty($custom_author)) {
        $template = AlRNDCM_PATH . 'templates/author-custom-page.php';
        return $template;
    }

    if( ! isset( $post->ID ) )
        return $template;

    $page_temp_slug = get_page_template_slug( $post->ID );

    $templates = my_template_lists();

    if( isset( $templates[$page_temp_slug] ) ) {
        $template = AlRNDCM_PATH . 'templates/' . $page_temp_slug;
    }

    return $template;

}
add_filter('template_include', 'ml_template_include', 99);