<?php
/**
 * Quick view content.
 *
 */

while ( have_posts() ) :
	the_post();
	?>
    <div class="alarnd--modal-cart-wrapper woocommerce single-product">
        <div id="product-<?php the_ID(); ?>" <?php post_class( 'product' ); ?>>
            <?php do_action( 'alarnd__modal_cart' ); ?>
        </div>
    </div>
	<?php
endwhile; // end of the loop.
