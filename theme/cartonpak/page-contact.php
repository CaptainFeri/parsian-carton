<?php
/**
 * قالب صفحه تماس با ما — دارای فرم ثبت پیام
 *
 * Template Name: تماس با ما
 *
 * @package cartonpak
 */

get_header();
?>

<div class="container page-wrap">
	<article <?php post_class( 'page-content' ); ?>>
		<div class="page-heading">
			<h1 class="page-title"><?php the_title(); ?></h1>
			<p class="page-subtitle">کارشناسان ما همه‌روزه از ۹ صبح تا ۹ شب پاسخگوی شما هستند</p>
		</div>

		<div class="contact-grid">
			<div class="contact-info">
				<div class="contact-card">
					<span class="contact-card-icon">☎</span>
					<div>
						<strong>شماره تماس شرکت</strong>
						<a href="tel:<?php echo esc_attr( cartonpak_option( 'cartonpak_phone' ) ); ?>" dir="ltr"><?php echo esc_html( cartonpak_option( 'cartonpak_phone' ) ); ?></a>
						<a href="tel:<?php echo esc_attr( cartonpak_option( 'cartonpak_mobile' ) ); ?>" dir="ltr"><?php echo esc_html( cartonpak_option( 'cartonpak_mobile' ) ); ?></a>
					</div>
				</div>
				<div class="contact-card">
					<span class="contact-card-icon">📦</span>
					<div>
						<strong>پیگیری و پشتیبانی سفارشات</strong>
						<a href="tel:<?php echo esc_attr( cartonpak_option( 'cartonpak_support' ) ); ?>" dir="ltr"><?php echo esc_html( cartonpak_option( 'cartonpak_support' ) ); ?></a>
					</div>
				</div>
				<div class="contact-card">
					<span class="contact-card-icon">📍</span>
					<div>
						<strong>آدرس کارخانه</strong>
						<span><?php echo esc_html( cartonpak_option( 'cartonpak_address' ) ); ?></span>
					</div>
				</div>
				<div class="contact-card">
					<span class="contact-card-icon">💬</span>
					<div>
						<strong>پیام‌رسان‌ها</strong>
						<span>روبیکا، بله و ایتا — <?php echo esc_html( cartonpak_option( 'cartonpak_phone' ) ); ?></span>
					</div>
				</div>
				<div class="contact-social">
					<a href="<?php echo esc_url( cartonpak_option( 'cartonpak_instagram' ) ); ?>" target="_blank" rel="noopener">📸 اینستاگرام</a>
					<a href="<?php echo esc_url( cartonpak_option( 'cartonpak_whatsapp' ) ); ?>" target="_blank" rel="noopener">💬 روبیکا، بله و ایتا</a>
				</div>
			</div>

			<div class="contact-form-wrap" id="contact-form">
				<?php if ( isset( $_COOKIE['cartonpak_sent'] ) ) : ?>
					<div class="form-notice form-notice-ok">✓ پیام شما با موفقیت ثبت شد. کارشناسان ما به‌زودی با شما تماس می‌گیرند.</div>
				<?php endif; ?>

				<form class="contact-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cartonpak_contact">
					<?php wp_nonce_field( 'cartonpak_contact', 'cartonpak_nonce' ); ?>

					<div class="form-row">
						<div class="form-group">
							<label for="cf-name">نام و نام خانوادگی *</label>
							<input type="text" id="cf-name" name="name" required>
						</div>
						<div class="form-group">
							<label for="cf-phone">شماره تماس *</label>
							<input type="text" id="cf-phone" name="phone" required>
						</div>
					</div>
					<div class="form-group">
						<label for="cf-topic">موضوع</label>
						<select id="cf-topic" name="topic">
							<option value="">انتخاب کنید...</option>
							<option>استعلام قیمت کارتن پستی</option>
							<option>سفارش چاپ اختصاصی</option>
							<option>خرید عمده</option>
							<option>پیگیری سفارش</option>
							<option>سایر</option>
						</select>
					</div>
					<div class="form-group">
						<label for="cf-message">متن پیام *</label>
						<textarea id="cf-message" name="message" rows="5" required></textarea>
					</div>
					<button type="submit" class="btn btn-primary btn-block">ارسال پیام</button>
				</form>
			</div>
		</div>
	</article>
</div>

<?php
get_footer();
