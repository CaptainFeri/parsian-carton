<?php
/**
 * عیب‌یابی: چرا بنر ساخته‌شده روی سایت دیده نمی‌شود؟
 *
 * نمایش بنر به دو چیز بند است: بنرِ منتشرشده‌ای در بازهٔ زمانی‌اش، و جایی در
 * قالب که آن را صدا بزند. دومی روی سرورها معمولاً همان چیزی است که جا می‌ماند —
 * افزونه‌ها آپلود می‌شوند ولی قالب نه، یا قالبِ فعال اصلاً پوشهٔ دیگری است.
 *
 * @package parsian-banners
 */

defined( 'ABSPATH' ) || exit;

/**
 * بررسی سلامت نمایش بنرها.
 */
class PBN_Status {

	/**
	 * نشانهٔ نسخهٔ به‌روز قالب — در main.css همین مخزن هست.
	 */
	const THEME_MARKER = 'بازنگری موبایل';

	/**
	 * اجرای همهٔ بررسی‌ها.
	 *
	 * @return array[]
	 */
	public static function checks() {
		return array_values(
			array_filter(
				array(
					self::check_banners(),
					self::check_theme_hook(),
					self::check_theme_version(),
				)
			)
		);
	}

	/**
	 * بنر منتشرشده‌ای هست؟
	 *
	 * @return array
	 */
	protected static function check_banners() {
		$all    = PBN_Banner::all();
		$active = PBN_Banner::active( 'home' );

		if ( ! $all ) {
			return array(
				'label' => __( 'بنرها', 'parsian-banners' ),
				'state' => 'warn',
				'text'  => __( 'هنوز بنری نساخته‌اید.', 'parsian-banners' ),
			);
		}

		if ( ! $active ) {
			return array(
				'label' => __( 'بنرها', 'parsian-banners' ),
				'state' => 'fail',
				'text'  => __( 'بنر ساخته‌اید ولی هیچ‌کدام برای صفحهٔ اصلی فعال نیست. سه چیز را بررسی کنید: وضعیت باید «منتشرشده» باشد (نه پیش‌نویس)، جایگاه باید «صفحهٔ اصلی» باشد، و تاریخ‌های نمایش باید شامل امروز باشند.', 'parsian-banners' ),
			);
		}

		return array(
			'label' => __( 'بنرها', 'parsian-banners' ),
			'state' => 'ok',
			/* translators: 1: تعداد فعال، 2: تعداد کل. */
			'text'  => sprintf(
				__( '%1$s بنر از %2$s بنر، آمادهٔ نمایش روی صفحهٔ اصلی است.', 'parsian-banners' ),
				pbn_digits( count( $active ) ),
				pbn_digits( count( $all ) )
			),
		);
	}

	/**
	 * قالب فعال، بنرها را صدا می‌زند؟
	 *
	 * @return array
	 */
	protected static function check_theme_hook() {
		$theme = wp_get_theme();
		$calls = self::theme_calls_hook();
		$auto  = (bool) PBN_Settings::get( 'auto_inject' );

		if ( $calls ) {
			return array(
				'label' => __( 'قالب فعال', 'parsian-banners' ),
				'state' => 'ok',
				/* translators: 1: نام قالب، 2: نام پوشه. */
				'text'  => sprintf(
					__( '«%1$s» (پوشهٔ %2$s) بنرها را مستقیم صدا می‌زند.', 'parsian-banners' ),
					$theme->get( 'Name' ),
					$theme->get_stylesheet()
				),
			);
		}

		return array(
			'label' => __( 'قالب فعال', 'parsian-banners' ),
			'state' => $auto ? 'warn' : 'fail',
			/* translators: 1: نام قالب، 2: نام پوشه، 3: توضیح وضعیت. */
			'text'  => sprintf(
				__( '«%1$s» (پوشهٔ %2$s) بنرها را صدا نمی‌زند. %3$s', 'parsian-banners' ),
				$theme->get( 'Name' ),
				$theme->get_stylesheet(),
				$auto
					? __( 'افزونه خودش بنر را بالای صفحهٔ اصلی تزریق می‌کند، پس نمایش داده می‌شود؛ ولی اگر قالب را به‌روز کنید نتیجه تمیزتر است.', 'parsian-banners' )
					: __( 'و «تزریق خودکار» هم خاموش است، پس هیچ بنری دیده نمی‌شود. یا قالب را به‌روز کنید، یا تزریق خودکار را روشن کنید، یا شورت‌کد را دستی بگذارید.', 'parsian-banners' )
			),
		);
	}

	/**
	 * قالب فعال، همان نسخهٔ به‌روز است؟
	 *
	 * پاسخ این یک سؤال، معمای «افزونه‌ها را آپلود کردم ولی هیچ چیز عوض نشد» را
	 * حل می‌کند: قالب جا مانده یا قالبِ فعال پوشهٔ دیگری است.
	 *
	 * @return array
	 */
	protected static function check_theme_version() {
		$theme = wp_get_theme();
		$css   = trailingslashit( $theme->get_stylesheet_directory() ) . 'assets/css/main.css';

		if ( ! is_readable( $css ) ) {
			return array(
				'label' => __( 'نسخهٔ قالب', 'parsian-banners' ),
				'state' => 'warn',
				'text'  => __( 'فایل assets/css/main.css در قالب فعال پیدا نشد؛ یعنی قالب فعال، قالب پارسیان کارتن نیست.', 'parsian-banners' ),
			);
		}

		// فقط ابتدای فایل خوانده می‌شود؛ نشانه ته فایل است، پس کل را می‌خوانیم
		// ولی حجمش کوچک است (حدود ۸۰ کیلوبایت).
		$contents = (string) file_get_contents( $css ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$updated  = false !== strpos( $contents, self::THEME_MARKER );

		return array(
			'label' => __( 'نسخهٔ قالب', 'parsian-banners' ),
			'state' => $updated ? 'ok' : 'fail',
			'text'  => $updated
				? __( 'قالب فعال شامل بازنگری موبایل است.', 'parsian-banners' )
				: __( 'قالب فعال هنوز نسخهٔ قدیمی است — بازنگری موبایل در آن نیست. یعنی فایل‌های قالب روی سرور به‌روز نشده‌اند، یا قالبِ فعال پوشهٔ دیگری است و به‌روزرسانی روی پوشهٔ اشتباهی نشسته است.', 'parsian-banners' ),
		);
	}

	/* ------------------------------ کمک‌کننده‌ها ------------------------------ */

	/**
	 * آیا قالب فعال (یا مادرش) تابع نمایش بنرها را صدا می‌زند؟
	 *
	 * نتیجه کش می‌شود تا با هر بار بارگذاری صفحه فایل خوانده نشود.
	 *
	 * @return bool
	 */
	public static function theme_calls_hook() {
		$theme = wp_get_theme();
		$key   = 'pbn_theme_hook_' . md5( $theme->get_stylesheet() . $theme->get( 'Version' ) );
		$known = get_transient( $key );

		if ( false !== $known ) {
			return '1' === $known;
		}

		$found = false;
		$dirs  = array_unique( array( $theme->get_stylesheet_directory(), $theme->get_template_directory() ) );

		foreach ( $dirs as $dir ) {
			foreach ( array( 'front-page.php', 'home.php', 'index.php', 'header.php' ) as $file ) {
				$path = trailingslashit( $dir ) . $file;

				if ( is_readable( $path ) && false !== strpos( (string) file_get_contents( $path ), 'parsian_banners_render' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					$found = true;
					break 2;
				}
			}
		}

		set_transient( $key, $found ? '1' : '0', DAY_IN_SECONDS );

		return $found;
	}

	/**
	 * دور ریختن کش تشخیص قالب.
	 */
	public static function flush() {
		$theme = wp_get_theme();

		delete_transient( 'pbn_theme_hook_' . md5( $theme->get_stylesheet() . $theme->get( 'Version' ) ) );
	}

	/* ------------------------------- نمایش ------------------------------- */

	/**
	 * جدول وضعیت.
	 */
	public static function render() {
		$icons = array(
			'ok'   => '✅',
			'warn' => '⚠️',
			'fail' => '⛔',
		);
		?>
		<div class="pbn-card">
			<h2><?php esc_html_e( 'وضعیت — چرا بنر دیده می‌شود یا نمی‌شود؟', 'parsian-banners' ); ?></h2>

			<table class="widefat striped">
				<tbody>
					<?php foreach ( self::checks() as $check ) : ?>
						<tr>
							<td style="width:30px;font-size:16px;">
								<?php echo esc_html( isset( $icons[ $check['state'] ] ) ? $icons[ $check['state'] ] : '' ); ?>
							</td>
							<th style="width:130px;"><?php echo esc_html( $check['label'] ); ?></th>
							<td><?php echo esc_html( $check['text'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
