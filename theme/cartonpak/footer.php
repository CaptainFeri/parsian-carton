<?php
/**
 * فوتر قالب کارتن‌پک
 *
 * @package cartonpak
 */

function cartonpak_default_menu() {
	echo '<ul class="main-menu">';
	$items = array(
		array( 'خانه', home_url( '/' ) ),
	);
	if ( class_exists( 'WooCommerce' ) ) {
		$items[] = array( 'فروشگاه', wc_get_page_permalink( 'shop' ) );
	}
	$items[] = array( 'وبلاگ', home_url( '/blog/' ) );
	$items[] = array( 'درباره ما', home_url( '/about-us/' ) );
	$items[] = array( 'تماس با ما', home_url( '/contact-us/' ) );
	foreach ( $items as $item ) {
		echo '<li><a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a></li>';
	}
	echo '</ul>';
}
?>

	</main>

	<footer class="site-footer">
		<div class="footer-newsletter">
			<div class="container footer-newsletter-inner">
				<div class="newsletter-text">
					<strong>از تخفیف‌ها و جدیدترین‌های فروشگاه باخبر شوید</strong>
					<span>عضویت در خبرنامه کارتن‌پک و دریافت کد تخفیف ۵۰ هزار تومانی 🎁</span>
				</div>
				<form class="newsletter-form" id="newsletterForm">
					<input type="text" placeholder="شماره موبایل" aria-label="شماره موبایل">
					<button type="submit">ارسال</button>
				</form>
			</div>
		</div>

		<div class="container footer-main">
			<div class="footer-col footer-about">
				<h4 class="footer-title">درباره پارسیان کارتن</h4>
				<p>
					تولیدکننده انواع کارتن و جعبه‌های بسته‌بندی سه لایه و پنج لایه، جعبه‌های مقوایی دایکاتی چاپی،
					سینی، لایی و کارتن‌های لمینتی؛ مستقر در شهرک صنعتی شرق سمنان با ارسال به سراسر کشور.
				</p>
				<div class="footer-social">
					<a href="<?php echo esc_url( cartonpak_option( 'cartonpak_instagram' ) ); ?>" target="_blank" rel="noopener" aria-label="اینستاگرام">📸</a>
					<a href="<?php echo esc_url( cartonpak_option( 'cartonpak_whatsapp' ) ); ?>" target="_blank" rel="noopener" aria-label="پیام‌رسان‌ها">💬</a>
				</div>
			</div>

			<div class="footer-col">
				<h4 class="footer-title">دسترسی سریع</h4>
				<?php
				wp_nav_menu( array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'footer-menu',
					'fallback_cb'    => false,
				) );
				?>
			</div>

			<div class="footer-col">
				<h4 class="footer-title">اطلاعات تماس</h4>
				<ul class="footer-contact">
					<li>
						<span class="fc-icon">📍</span>
						<span><?php echo esc_html( cartonpak_option( 'cartonpak_address' ) ); ?></span>
					</li>
					<li>
						<span class="fc-icon">☎</span>
						<a href="tel:<?php echo esc_attr( cartonpak_option( 'cartonpak_phone' ) ); ?>" dir="ltr"><?php echo esc_html( cartonpak_option( 'cartonpak_phone' ) ); ?></a>
					</li>
					<li>
						<span class="fc-icon">📱</span>
						<a href="tel:<?php echo esc_attr( cartonpak_option( 'cartonpak_mobile' ) ); ?>" dir="ltr"><?php echo esc_html( cartonpak_option( 'cartonpak_mobile' ) ); ?></a>
					</li>
					<li>
						<span class="fc-icon">📦</span>
						<a href="tel:<?php echo esc_attr( cartonpak_option( 'cartonpak_support' ) ); ?>" dir="ltr">پیگیری سفارش: <?php echo esc_html( cartonpak_option( 'cartonpak_support' ) ); ?></a>
					</li>
				</ul>
				<div class="footer-trust">
					<div class="trust-badge">نماد اعتماد الکترونیکی</div>
					<div class="trust-badge trust-ssl">اتصال امن SSL</div>
				</div>
			</div>
		</div>

		<div class="footer-bottom">
			<div class="container footer-bottom-inner">
				<span>© <?php echo esc_html( date_i18n( 'Y' ) ); ?> کلیه حقوق این وب‌سایت متعلق به پارسیان کارتن است.</span>
				<span>شعار ما: کیفیت، سرعت و دقت</span>
			</div>
		</div>
	</footer>
</div>

<?php wp_footer(); ?>
</body>
</html>
