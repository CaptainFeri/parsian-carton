<?php
/**
 * سبد خرید خالی — قالب اختصاصی کارتن‌پک
 *
 * @package cartonpak
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="cart-empty-wrap">
	<div class="cart-empty-icon">🛒</div>
	<h2 class="cart-empty-title">سبد خرید شما خالی است</h2>
	<p class="cart-empty-desc">هنوز محصولی به سبد خرید اضافه نکرده‌اید. از فروشگاه ما کارتن و جعبه بسته‌بندی موردنظرتان را انتخاب کنید.</p>

	<div class="cart-empty-actions">
		<a class="btn btn-primary" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">مشاهده محصولات فروشگاه</a>
		<a class="btn btn-ghost" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">مشاوره و ثبت سفارش تلفنی</a>
	</div>

	<div class="cart-empty-cats">
		<?php
		$cats = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => 0,
			'number'     => 6,
		) );
		if ( ! is_wp_error( $cats ) && $cats ) :
			foreach ( $cats as $cat ) :
				?>
				<a class="cart-empty-cat" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
