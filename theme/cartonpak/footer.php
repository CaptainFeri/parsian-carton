<?php
/**
 * فوتر قالب: درباره، دسترسی سریع، تماس و نمادهای اعتماد.
 *
 * @package cartonpak
 */

$address   = cartonpak_option( 'cartonpak_address' );
$phone     = cartonpak_option( 'cartonpak_phone' );
$mobile    = cartonpak_option( 'cartonpak_mobile' );
$hours     = cartonpak_option( 'cartonpak_hours' );
$instagram = cartonpak_option( 'cartonpak_instagram' );
$messenger = cartonpak_option( 'cartonpak_whatsapp' );
$badges    = cartonpak_trust_badges();
?>
	</main>

	<footer class="site-footer" id="contact">
		<div class="container">
			<div class="footer-grid<?php echo $badges ? ' has-trust' : ''; ?>">
				<div class="footer-col footer-about">
					<span class="footer-brand"><?php bloginfo( 'name' ); ?></span>
					<p>تولیدکنندهٔ انواع کارتن و جعبهٔ بسته‌بندی سه‌لایه و پنج‌لایه، جعبه‌های دایکاتی چاپی، سینی و لایی؛ مستقر در شهرک صنعتی شرق سمنان با ارسال به سراسر کشور.</p>
					<?php if ( $instagram || $messenger ) : ?>
						<div class="footer-social">
							<?php if ( $instagram ) : ?>
								<a href="<?php echo esc_url( $instagram ); ?>" target="_blank" rel="noopener">اینستاگرام</a>
							<?php endif; ?>
							<?php if ( $messenger ) : ?>
								<a href="<?php echo esc_url( $messenger ); ?>" target="_blank" rel="noopener">روبیکا، بله، ایتا</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<nav class="footer-col" aria-labelledby="footerLinksTitle">
					<h2 class="footer-title" id="footerLinksTitle">دسترسی سریع</h2>
					<?php
					wp_nav_menu( array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'footer-menu',
						'depth'          => 1,
						'fallback_cb'    => 'cartonpak_default_footer_menu',
					) );
					?>
				</nav>

				<div class="footer-col">
					<h2 class="footer-title">تماس با ما</h2>
					<ul class="footer-contact">
						<?php if ( $address ) : ?>
							<li><?php echo cartonpak_icon( 'pin', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $address ); ?></span></li>
						<?php endif; ?>
						<?php foreach ( array_unique( array_filter( array( $phone, $mobile ) ) ) as $number ) : ?>
							<li><?php echo cartonpak_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="<?php echo esc_url( cartonpak_tel( $number ) ); ?>" dir="ltr"><?php echo esc_html( $number ); ?></a></li>
						<?php endforeach; ?>
						<?php if ( $hours ) : ?>
							<li><?php echo cartonpak_icon( 'clock', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $hours ); ?></span></li>
						<?php endif; ?>
					</ul>
				</div>

				<?php if ( $badges ) : ?>
					<div class="footer-col footer-trust">
						<?php foreach ( $badges as $badge ) : ?>
							<div class="trust-slot"><?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- یا هنگام ذخیره پاک‌سازی شده، یا خودمان ساخته‌ایم. ?></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="footer-bottom">
				© <?php echo esc_html( cartonpak_digits( date_i18n( 'Y' ) ) ); ?> <?php bloginfo( 'name' ); ?> — تمامی حقوق محفوظ است.
			</div>
		</div>
	</footer>
</div>

<?php wp_footer(); ?>
</body>
</html>
