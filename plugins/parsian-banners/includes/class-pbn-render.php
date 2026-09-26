<?php
/**
 * ساخت اسلایدر بنرها.
 *
 * عمداً همان نام‌های کلاسِ اسلایدر قالب استفاده می‌شود (`hero`, `hero-slide`,
 * `hero-title` و…). نتیجه این است که استایل و جاوااسکریپت موجود قالب بدون یک
 * خط کد تازه روی بنرهای مدیریت‌شده هم کار می‌کنند و ظاهر سایت عوض نمی‌شود —
 * فقط محتوایش از پیشخوان می‌آید.
 *
 * @package parsian-banners
 */

defined( 'ABSPATH' ) || exit;

/**
 * نمایش بنرها.
 */
class PBN_Render {

	/**
	 * HTML بنر، ساخته‌شده پیش از باز شدن بافر خروجی.
	 *
	 * @var string
	 */
	protected static $pending = '';

	/**
	 * ثبت قلاب‌ها.
	 */
	public static function init() {
		add_shortcode( 'parsian_banners', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		// تکیه کردن به یک خط در front-page.php قالب شکننده است: اگر فایل‌های
		// قالب روی سرور به‌روز نشوند، بنرِ ساخته‌شده هیچ‌وقت دیده نمی‌شود و هیچ
		// خطایی هم جایی ظاهر نمی‌شود. این قلاب همان حالت را می‌پوشاند.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_auto_inject' ) );
	}

	/**
	 * جایگاه‌های قابل انتخاب.
	 *
	 * @return array<string,string>
	 */
	public static function locations() {
		/**
		 * تغییر جایگاه‌های بنر.
		 *
		 * @param array<string,string> $locations جایگاه‌ها.
		 */
		return (array) apply_filters(
			'pbn_locations',
			array(
				'home' => __( 'صفحهٔ اصلی (بالای صفحه)', 'parsian-banners' ),
				'shop' => __( 'صفحهٔ فروشگاه', 'parsian-banners' ),
				'free' => __( 'فقط با شورت‌کد', 'parsian-banners' ),
			)
		);
	}

	/**
	 * بارگذاری استایل.
	 */
	public static function enqueue() {
		wp_register_style( 'pbn-banners', PBN_URL . 'assets/pbn.css', array(), PBN_VERSION );
		wp_register_script( 'pbn-banners', PBN_URL . 'assets/pbn.js', array(), PBN_VERSION, true );

		// در مسیر تزریق خودکار، html() داخل بافر خروجی اجرا می‌شود — یعنی بعد از
		// چاپ wp_head. پس استایل باید همین‌جا، زودتر، در صف بنشیند.
		if ( is_front_page() && PBN_Banner::active( 'home' ) ) {
			wp_enqueue_style( 'pbn-banners' );
			wp_enqueue_script( 'pbn-banners' );
		}
	}

	/**
	 * تزریق خودکار بنر بالای صفحهٔ اصلی، وقتی قالب خودش صدایش نمی‌زند.
	 */
	public static function maybe_auto_inject() {
		if ( is_admin() || ! is_front_page() ) {
			return;
		}

		if ( ! PBN_Settings::get( 'auto_inject' ) || PBN_Status::theme_calls_hook() ) {
			return;
		}

		if ( ! PBN_Banner::active( 'home' ) ) {
			return;
		}

		// HTML همین‌جا ساخته می‌شود، نه داخل callback بافر: html() خودش از
		// ob_start() استفاده می‌کند و PHP باز کردن بافر تازه را داخل یک
		// «output buffering display handler» ممنوع کرده است. اگر آنجا صدایش
		// بزنیم، بی‌صدا شکست می‌خورد و صفحه بدون بنر برمی‌گردد.
		self::$pending = self::html( 'home' );

		if ( '' === self::$pending ) {
			return;
		}

		ob_start( array( __CLASS__, 'inject' ) );
	}

	/**
	 * جا دادن بنر در خروجی صفحه.
	 *
	 * بعد از باز شدن <main> می‌نشیند — یعنی زیر هدر و بالای محتوا. اگر قالب
	 * <main> نداشت، بعد از بسته شدن هدر. اگر هیچ‌کدام نبود، صفحه دست‌نخورده
	 * برمی‌گردد؛ بنر دیده نمی‌شود ولی چیزی هم خراب نمی‌شود.
	 *
	 * @param string $html خروجی صفحه.
	 * @return string
	 */
	public static function inject( $html ) {
		$banner = self::$pending;

		if ( '' === $banner ) {
			return $html;
		}

		foreach ( array( '/<main\b[^>]*>/i', '/<\/header>/i' ) as $pattern ) {
			if ( preg_match( $pattern, $html, $match, PREG_OFFSET_CAPTURE ) ) {
				$at = $match[0][1] + strlen( $match[0][0] );

				return substr( $html, 0, $at ) . $banner . substr( $html, $at );
			}
		}

		return $html;
	}

	/**
	 * شورت‌کد `[parsian_banners]`.
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'location' => 'free' ), (array) $atts, 'parsian_banners' );

		return self::html( sanitize_key( $atts['location'] ) );
	}

	/**
	 * چاپ اسلایدر یک جایگاه.
	 *
	 * @param string $location جایگاه.
	 * @return bool آیا چیزی چاپ شد؟
	 */
	public static function output( $location = 'home' ) {
		$html = self::html( $location );

		if ( '' === $html ) {
			// بدون بنر، معمولاً باید اسلایدر ثابت قالب سر جایش بماند. ولی اگر
			// مدیر سایت آن را خاموش کرده باشد، `true` برمی‌گردانیم تا قالب هم
			// چیزی چاپ نکند و بالای صفحه خالی بماند — همان چیزی که خواسته است.
			return ! PBN_Settings::get( 'fallback' );
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- در html() پاک‌سازی شده است.

		return true;
	}

	/**
	 * ساخت HTML اسلایدر.
	 *
	 * @param string $location جایگاه.
	 * @return string رشتهٔ خالی وقتی بنری نیست.
	 */
	public static function html( $location = 'home' ) {
		$banners = PBN_Banner::active( $location );

		if ( ! $banners ) {
			return '';
		}

		wp_enqueue_style( 'pbn-banners' );
		wp_enqueue_script( 'pbn-banners' );

		$interval = (int) PBN_Settings::get( 'interval' );

		ob_start();
		?>
		<section class="hero pbn-hero"
			style="--pbn-height:<?php echo esc_attr( (int) PBN_Settings::get( 'height' ) ); ?>px;--pbn-height-sm:<?php echo esc_attr( (int) PBN_Settings::get( 'height_sm' ) ); ?>px;"
			data-pbn-interval="<?php echo esc_attr( $interval ); ?>">
			<div class="container hero-slider" id="heroSlider">
				<?php foreach ( $banners as $index => $banner ) : ?>
					<?php self::slide( $banner, 0 === $index ); ?>
				<?php endforeach; ?>

				<?php if ( count( $banners ) > 1 ) : ?>
					<button class="hero-arrow hero-arrow-next" id="heroNext" aria-label="<?php esc_attr_e( 'بنر بعدی', 'parsian-banners' ); ?>">❮</button>
					<button class="hero-arrow hero-arrow-prev" id="heroPrev" aria-label="<?php esc_attr_e( 'بنر قبلی', 'parsian-banners' ); ?>">❯</button>
				<?php endif; ?>

				<div class="hero-dots" id="heroDots"></div>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * یک اسلاید.
	 *
	 * @param PBN_Banner $banner بنر.
	 * @param bool       $active اسلاید نخست؟
	 */
	protected static function slide( $banner, $active ) {
		$image    = $banner->get_image_url();
		$mobile   = $banner->get_image_url( true );
		$overlay  = $banner->get_overlay();
		$features = $banner->get_features();

		$classes = array( 'hero-slide', 'pbn-slide', 'pbn-scheme-' . $banner->get_scheme() );

		if ( $active ) {
			$classes[] = 'is-active';
		}

		// نمایش وابسته به دستگاه با CSS انجام می‌شود، نه با تشخیص سمت سرور —
		// چون صفحه ممکن است کش شده باشد و تشخیص سرور آن‌وقت اشتباه می‌شود.
		$device = $banner->get_device();

		if ( 'all' !== $device ) {
			$classes[] = 'pbn-only-' . $device;
		}

		$style = array( '--pbn-overlay:' . ( $overlay / 100 ) );

		if ( $image ) {
			$style[] = "--pbn-image:url('" . esc_url( $image ) . "')";
		}

		if ( $mobile && $mobile !== $image ) {
			$style[] = "--pbn-image-sm:url('" . esc_url( $mobile ) . "')";
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( implode( ';', $style ) ); ?>">
			<?php if ( $image ) : ?>
				<span class="pbn-slide-bg" aria-hidden="true"></span>
			<?php endif; ?>

			<div class="hero-content">
				<?php if ( $banner->get( 'eyebrow' ) ) : ?>
					<span class="hero-eyebrow"><?php echo esc_html( $banner->get( 'eyebrow' ) ); ?></span>
				<?php endif; ?>

				<h2 class="hero-title">
					<?php
					// فقط <em> و <br> برای تأکید مجازند؛ بقیه حذف می‌شود.
					echo wp_kses( $banner->get_title(), array( 'em' => array(), 'strong' => array(), 'br' => array() ) );
					?>
				</h2>

				<?php if ( $banner->get( 'description' ) ) : ?>
					<p class="hero-desc"><?php echo esc_html( $banner->get( 'description' ) ); ?></p>
				<?php endif; ?>

				<?php if ( $banner->get( 'button_text' ) || $banner->get( 'button2_text' ) ) : ?>
					<div class="hero-cta">
						<?php if ( $banner->get( 'button_text' ) ) : ?>
							<a class="btn btn-primary" href="<?php echo esc_url( $banner->get( 'button_url', '#' ) ); ?>">
								<?php echo esc_html( $banner->get( 'button_text' ) ); ?>
							</a>
						<?php endif; ?>

						<?php if ( $banner->get( 'button2_text' ) ) : ?>
							<a class="btn btn-ghost" href="<?php echo esc_url( $banner->get( 'button2_url', '#' ) ); ?>">
								<?php echo esc_html( $banner->get( 'button2_text' ) ); ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $features ) : ?>
					<ul class="hero-features">
						<?php foreach ( $features as $feature ) : ?>
							<li><?php echo esc_html( $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
