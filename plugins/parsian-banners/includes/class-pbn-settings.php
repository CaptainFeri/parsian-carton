<?php
/**
 * تنظیمات افزونهٔ بنرها.
 *
 * @package parsian-banners
 */

defined( 'ABSPATH' ) || exit;

/**
 * گزینه‌ها.
 */
class PBN_Settings {

	const OPTION = 'pbn_settings';

	/**
	 * کش گزینه‌ها.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * مقادیر پیش‌فرض.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// فاصلهٔ چرخش اسلایدها (ثانیه؛ ۰ = بدون چرخش خودکار).
			'interval'    => 6,
			// کمینهٔ ارتفاع بنر روی دسکتاپ و موبایل (پیکسل).
			'height'      => 420,
			'height_sm'   => 300,
			// وقتی بنری تعریف نشده باشد، اسلایدر ثابت قالب نشان داده شود؟
			'fallback'    => 1,
			// اگر قالب بنرها را صدا نزند، افزونه خودش بالای صفحهٔ اصلی تزریقشان کند؟
			'auto_inject' => 1,
		);
	}

	/**
	 * خواندن یک گزینه.
	 *
	 * @param string $key     کلید.
	 * @param mixed  $default مقدار جایگزین.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}

		return array_key_exists( $key, self::$cache ) ? self::$cache[ $key ] : $default;
	}

	/**
	 * ذخیرهٔ گزینه‌ها.
	 *
	 * @param array $values مقادیر خام.
	 */
	public static function save( $values ) {
		$clean = array();

		foreach ( self::defaults() as $key => $default ) {
			$clean[ $key ] = self::get( $key, $default );
		}

		if ( isset( $values['interval'] ) ) {
			$clean['interval'] = min( 60, max( 0, (int) pbn_latin_digits( $values['interval'] ) ) );
		}

		if ( isset( $values['height'] ) ) {
			$clean['height'] = min( 900, max( 180, (int) pbn_latin_digits( $values['height'] ) ) );
		}

		if ( isset( $values['height_sm'] ) ) {
			$clean['height_sm'] = min( 700, max( 140, (int) pbn_latin_digits( $values['height_sm'] ) ) );
		}

		$clean['fallback'] = empty( $values['fallback'] ) ? 0 : 1;

		// چک‌باکس خاموش در POST نمی‌آید؛ فیلد پنهان pbn_form این دو حالت را جدا
		// می‌کند تا ذخیرهٔ برنامه‌ای تنظیم را خاموش نکند.
		if ( ! empty( $values['pbn_form'] ) || array_key_exists( 'auto_inject', $values ) ) {
			$clean['auto_inject'] = empty( $values['auto_inject'] ) ? 0 : 1;
		}

		update_option( self::OPTION, $clean, true );
		self::$cache = null;
	}

	/**
	 * دور ریختن کش.
	 */
	public static function flush() {
		self::$cache = null;
	}
}
