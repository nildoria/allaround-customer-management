<?php

function add_new_customer_page() {

    ?>
    <div class="aum-add-customer-form wrap">
        <h2>Add New Customer</h2>
        <form method="post" action="">
            <label for="first_name">First Name:</label>
            <input type="text" name="first_name" required><br>
            
            <label for="last_name">Last Name:</label>
            <input type="text" name="last_name" required><br>
            
            <label for="email">Email:</label>
            <input type="email" name="email" required><br>
            
            <label for="username">Username:</label>
            <input type="text" name="username" required><br>
            
            <label for="token">Token:</label>
            <input type="text" name="token" required><br>
            
            <label for="author_page_url">Author Page URL:</label>
            <input type="text" name="author_page_url"><br>

            <label for="profile_picture">Profile Picture:</label>
            <input type="hidden" name="profile_picture_id" id="profile_picture_id" value="">
            <button type="button" class="upload-button button">Upload Profile Picture</button>
            <div id="profile_picture_preview"></div>
            
            <div class="aum-product-selection-section">
            <h2>Select Products</h2>
            <?php
                $products = wc_get_products(array('status' => 'publish', 'limit' => 1000,));

                foreach ($products as $product) {
                    echo '<label>';
                    echo '<input type="checkbox" name="selected_products[]" value="' . $product->get_id() . '"> ' . esc_html($product->get_name());
                    echo '</label><br>';
                }
            ?>
            </div>
            <input type="submit" name="add_customer" class="button button-primary" value="Add Customer">
        </form>
    </div>

    <script>
        jQuery(document).ready(function($) {
            var custom_uploader;
            $('.upload-button').click(function(e) {
                e.preventDefault();
                
                if (custom_uploader) {
                    custom_uploader.open();
                    return;
                }
                
                custom_uploader = wp.media({
                    title: 'Choose Profile Picture',
                    button: {
                        text: 'Choose Picture'
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: false
                });
                
                custom_uploader.on('select', function() {
                    var attachment = custom_uploader.state().get('selection').first().toJSON();
                    $('#profile_picture_id').val(attachment.id);
                    $('#profile_picture_preview').html('<img src="' + attachment.url + '" alt="Profile Picture" style="max-width: 100px;">');
                });
                
                custom_uploader.open();
            });
        });
    </script>
    <?php
}