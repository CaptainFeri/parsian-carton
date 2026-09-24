<?php
/**
 * کارت محصول — override ووکامرس
 *
 * @package cartonpak
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, 'WC_Product' ) ) {
	return;
}
?>
<li <?php wc_product_class( 'product-card' ); ?>>
	<a class="product-card-media" href="<?php the_permalink(); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?>
		<?php else : ?>
			<span class="product-card-ph">📦</span>
		<?php endif; ?>
		<?php if ( $product->is_on_sale() ) : ?>
			<span class="product-card-sale">حراج</span>
		<?php endif; ?>
	</a>
	<div class="product-card-body">
		<a class="product-card-title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		<div class="product-card-price">
			<?php if ( $product->is_on_sale() ) : ?>
				<del class="price-old"><?php echo wp_kses_post( wc_price( $product->get_regular_price() ) ); ?></del>
			<?php endif; ?>
			<span class="price-current"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
		</div>
		<div class="product-card-actions">
			<a href="?add-to-cart=<?php echo esc_attr( $product->get_id() ); ?>" data-quantity="1" data-product_id="<?php echo esc_attr( $product->get_id() ); ?>" class="button btn btn-small product_type_simple add_to_cart_button ajax_add_to_cart" rel="nofollow" aria-label="افزودن به سبد">افزودن به سبد</a>
			<a class="btn btn-icon" href="<?php the_permalink(); ?>" aria-label="جزئیات">👁</a>
		</div>
	</div>
</li>
