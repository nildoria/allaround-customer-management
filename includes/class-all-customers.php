<?php


// All Customers Page Content
function all_customers_page() {
    ?>
    <div class="wrap">
        <h2 class="title">All Customers</h2>
        <a href="<?php echo admin_url('admin.php?page=add-new-customer'); ?>" class="page-title-action">Add New</a>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Author Page URL</th>
                    <th>Profile Picture</th>
                    <th>Edit</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $customers = get_users(['role' => 'customer']);
                foreach ($customers as $customer) {
                    $profile_picture_id = get_user_meta($customer->ID, 'profile_picture', true);
                    $profile_picture_url = wp_get_attachment_image_url($profile_picture_id, 'thumbnail');
                    $author_page_url_slug = get_user_meta($customer->ID, 'author_page_url', true);
                    ?>
                    <tr>
                        <td><?php echo esc_html($customer->display_name); ?></td>
                        <td><?php echo esc_html($customer->user_email); ?></td>
                        <td><?php echo esc_html($customer->user_login); ?></td>
                        <td>
                            <?php 
                            if (!empty($author_page_url_slug)) {
                                $author_page_url = home_url($author_page_url_slug);
                                echo '<a href="' . esc_url($author_page_url) . '">' . esc_html($author_page_url) . '</a>';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if (!empty($profile_picture_url)) : ?>
                                <img src="<?php echo esc_url($profile_picture_url); ?>" alt="Profile Picture" style="max-width: 50px;">
                            <?php else : ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo get_edit_user_link($customer->ID); ?>">Edit</a>
                        </td>
                        <td>
                            <a href="<?php echo add_query_arg('action', 'delete_customer', get_admin_url() . 'admin.php?page=user-management&user_id=' . $customer->ID); ?>" class="delete-link">Delete</a>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}