<?php
/**
 * کارت محصول — override ووکامرس.
 *
 * @package cartonpak
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, 'WC_Product' ) || ! $product->is_visible() ) {
	return;
}

$name  = $product->get_name();
$badge = cartonpak_card_badge( $product );
$dims  = cartonpak_product_dimensions( $product );
$price = cartonpak_card_price_html( $product );
?>
<li <?php wc_product_class( 'pc-card', $product ); ?>>
	<a class="pc-card-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $product->get_image_id() ) : ?>
			<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<?php echo cartonpak_carton_art( 140 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</a>
	<?php if ( $badge ) : ?>
		<span class="pc-badge"><?php echo esc_html( $badge ); ?></span>
	<?php endif; ?>

	<div class="pc-card-body">
		<h3 class="pc-card-title"><a href="<?php the_permalink(); ?>"><?php echo esc_html( $name ); ?></a></h3>
		<?php if ( $dims ) : ?>
			<span class="pc-card-dims"><?php echo esc_html( $dims ); ?></span>
		<?php endif; ?>

		<div class="pc-card-foot">
			<span class="pc-card-price price"><?php echo $price ? wp_kses_post( $price ) : 'تماس بگیرید'; ?></span>

			<?php if ( ! $product->is_in_stock() ) : ?>
				<span class="pc-card-out">ناموجود</span>
			<?php elseif ( $product->is_type( 'simple' ) && $product->is_purchasable() ) : ?>
				<form class="pc-card-form" method="post" action="<?php echo esc_url( $product->add_to_cart_url() ); ?>">
					<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>"
						class="pc-add add_to_cart_button ajax_add_to_cart"
						data-product_id="<?php echo esc_attr( $product->get_id() ); ?>"
						data-product_sku="<?php echo esc_attr( $product->get_sku() ); ?>"
						data-quantity="1"
						aria-label="<?php echo esc_attr( sprintf( 'افزودن %s به سبد', $name ) ); ?>">
						<?php echo cartonpak_icon( 'plus', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
				</form>
			<?php else : ?>
				<a class="pc-add" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( sprintf( 'انتخاب گزینه‌های %s', $name ) ); ?>">
					<?php echo cartonpak_icon( 'plus', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</li>
