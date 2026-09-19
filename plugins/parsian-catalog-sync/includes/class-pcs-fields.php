<?php
/**
 * فیلدهای سفارشی — ستون‌هایی که افزونه‌های دیگر به فایل کاتالوگ اضافه می‌کنند.
 *
 * هستهٔ افزونه فقط فیلدهای خود ووکامرس را می‌شناسد. افزونهٔ پیش‌فروش (و هر افزونهٔ
 * دیگری) می‌تواند با فیلتر `pcs_custom_fields` ستون تازه‌ای ثبت کند؛ از آن پس آن
 * ستون هم در خروجی می‌آید، هم در پیش‌نمایش مقایسه می‌شود و هم موقع اعمال روی
 * متای محصول نوشته می‌شود — بدون آنکه لازم باشد چیزی در این افزونه تغییر کند.
 *
 * هر فیلد با این کلیدها تعریف می‌شود:
 *
 *   label    برچسب فارسی (سرستون خروجی و نام فیلد در پیش‌نمایش)
 *   aliases  نگارش‌های دیگری که هنگام خواندن فایل پذیرفته می‌شوند
 *   meta_key کلید متای محصول
 *   type     text | int | number | bool | date
 *   types    انواع محصولی که این فیلد برایشان معنا دارد (پیش‌فرض: همه)
 *   default  مقدار متای نداشته (برای مقایسه)
 *   to_store تابع تبدیل مقدار سلول به مقدار متا (اختیاری)
 *   to_file  تابع تبدیل مقدار متا به مقدار سلول (اختیاری)
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * سیاههٔ فیلدهای سفارشی.
 */
class PCS_Fields {

	/**
	 * پیشوند نام فیلد در نقشهٔ تغییرات — تا با فیلدهای ووکامرس قاطی نشود.
	 */
	const PREFIX = 'custom:';

	/**
	 * کش سیاهه در همین درخواست.
	 *
	 * @var array<string,array>|null
	 */
	protected static $cache = null;

	/**
	 * همهٔ فیلدهای ثبت‌شده.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		/**
		 * ثبت ستون‌های سفارشی کاتالوگ.
		 *
		 * @param array<string,array> $fields سیاههٔ فیلدها.
		 */
		$fields = (array) apply_filters( 'pcs_custom_fields', array() );
		$clean  = array();

		foreach ( $fields as $key => $field ) {
			$key = sanitize_key( $key );

			if ( '' === $key || empty( $field['meta_key'] ) || empty( $field['label'] ) ) {
				continue;
			}

			$clean[ $key ] = wp_parse_args(
				$field,
				array(
					'label'    => '',
					'aliases'  => array(),
					'meta_key' => '',
					'type'     => 'text',
					'types'    => array(),
					'default'  => '',
					'to_store' => null,
					'to_file'  => null,
				)
			);
		}

		self::$cache = $clean;

		return $clean;
	}

	/**
	 * دور ریختن کش — برای آزمون‌ها و پس از ثبت دیرهنگام فیلدها.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * تعریف یک فیلد بر پایهٔ نام فیلد در نقشهٔ تغییرات («custom:preorder»).
	 *
	 * @param string $field نام فیلد.
	 * @return array|null
	 */
	public static function get( $field ) {
		$key    = self::key( $field );
		$fields = self::all();

		return ( '' !== $key && isset( $fields[ $key ] ) ) ? $fields[ $key ] : null;
	}

	/**
	 * آیا این نام فیلد، یک فیلد سفارشی است؟
	 *
	 * @param string $field نام فیلد.
	 * @return bool
	 */
	public static function is_custom( $field ) {
		return 0 === strpos( (string) $field, self::PREFIX );
	}

	/**
	 * کلید فیلد از روی نام آن.
	 *
	 * @param string $field نام فیلد.
	 * @return string
	 */
	public static function key( $field ) {
		return self::is_custom( $field ) ? substr( (string) $field, strlen( self::PREFIX ) ) : '';
	}

	/**
	 * نام فیلد از روی کلید.
	 *
	 * @param string $key کلید.
	 * @return string
	 */
	public static function field( $key ) {
		return self::PREFIX . $key;
	}

	/**
	 * آیا این فیلد برای این نوع محصول معنا دارد؟
	 *
	 * @param array  $field تعریف فیلد.
	 * @param string $type  نوع محصول («» یعنی نامشخص).
	 * @return bool
	 */
	public static function applies_to( $field, $type ) {
		if ( empty( $field['types'] ) || '' === $type ) {
			return true;
		}

		return in_array( $type, (array) $field['types'], true );
	}

	/* ------------------------------ تبدیل مقدار ------------------------------ */

	/**
	 * تبدیل مقدار سلول به مقداری که در متا ذخیره می‌شود.
	 *
	 * @param array  $field تعریف فیلد.
	 * @param string $raw   مقدار خام سلول.
	 * @return string|int|float|WP_Error
	 */
	public static function to_store( $field, $raw ) {
		$raw = trim( (string) $raw );

		if ( is_callable( $field['to_store'] ) ) {
			return call_user_func( $field['to_store'], $raw, $field );
		}

		switch ( $field['type'] ) {
			case 'bool':
				$flag = PCS_Mapper::boolean( $raw );

				if ( null === $flag ) {
					return new WP_Error(
						'pcs_field_bool',
						sprintf(
							/* translators: 1: نام ستون، 2: مقدار سلول. */
							__( 'مقدار ستون «%1$s» باید بله یا خیر باشد: %2$s', 'parsian-catalog-sync' ),
							$field['label'],
							$raw
						)
					);
				}

				return $flag ? 'yes' : 'no';

			case 'int':
			case 'number':
				$number = PCS_Mapper::number( $raw );

				if ( null === $number ) {
					return new WP_Error(
						'pcs_field_number',
						sprintf(
							/* translators: 1: نام ستون، 2: مقدار سلول. */
							__( 'مقدار ستون «%1$s» عدد معتبری نیست: %2$s', 'parsian-catalog-sync' ),
							$field['label'],
							$raw
						)
					);
				}

				return 'int' === $field['type'] ? (int) $number : (float) $number;

			case 'date':
				$date = self::parse_date( $raw );

				if ( '' === $date ) {
					return new WP_Error(
						'pcs_field_date',
						sprintf(
							/* translators: 1: نام ستون، 2: مقدار سلول. */
							__( 'مقدار ستون «%1$s» تاریخ معتبری نیست (قالب درست: ۱۴۰۴/۰۷/۱۲ یا 2025-10-04): %2$s', 'parsian-catalog-sync' ),
							$field['label'],
							$raw
						)
					);
				}

				return $date;
		}

		return sanitize_text_field( $raw );
	}

	/**
	 * تبدیل مقدار متا به مقداری که در فایل خروجی نوشته می‌شود.
	 *
	 * @param array $field تعریف فیلد.
	 * @param mixed $value مقدار متا.
	 * @return string
	 */
	public static function to_file( $field, $value ) {
		if ( is_callable( $field['to_file'] ) ) {
			return (string) call_user_func( $field['to_file'], $value, $field );
		}

		if ( '' === $value || null === $value ) {
			return '';
		}

		if ( 'bool' === $field['type'] ) {
			return in_array( (string) $value, array( 'yes', '1', 'true' ), true )
				? __( 'بله', 'parsian-catalog-sync' )
				: __( 'خیر', 'parsian-catalog-sync' );
		}

		return (string) $value;
	}

	/**
	 * شکل قابل مقایسهٔ یک مقدار — دو طرف مقایسه از همین تابع می‌گذرند.
	 *
	 * @param array $field تعریف فیلد.
	 * @param mixed $value مقدار (متا یا آمادهٔ نوشتن).
	 * @return string
	 */
	public static function comparable( $field, $value ) {
		if ( null === $value ) {
			return '';
		}

		switch ( $field['type'] ) {
			case 'bool':
				if ( '' === $value ) {
					return '';
				}

				return in_array( (string) $value, array( 'yes', '1', 'true' ), true ) ? 'yes' : 'no';

			case 'int':
				return '' === $value ? '' : (string) (int) $value;

			case 'number':
				return '' === $value ? '' : (string) (float) $value;
		}

		return trim( (string) $value );
	}

	/**
	 * خواندن مقدار فعلی فیلد از روی محصول.
	 *
	 * @param WC_Product $product محصول.
	 * @param array      $field   تعریف فیلد.
	 * @return mixed
	 */
	public static function read( $product, $field ) {
		$value = $product->get_meta( $field['meta_key'], true );

		if ( '' === $value || null === $value ) {
			$value = $field['default'];
		}

		return $value;
	}

	/**
	 * خواندن یک تاریخ میلادی یا شمسی و بازگرداندن آن به قالب Y-m-d میلادی.
	 *
	 * تاریخ‌های شمسی از سال ۱۳۰۰ تا ۱۵۰۰ تشخیص داده می‌شوند؛ بقیه میلادی فرض می‌شوند.
	 *
	 * @param string $raw مقدار خام.
	 * @return string تاریخ میلادی یا رشتهٔ خالی.
	 */
	public static function parse_date( $raw ) {
		$raw = trim( PCS_Mapper::latin_digits( (string) $raw ) );

		if ( '' === $raw ) {
			return '';
		}

		$raw = str_replace( array( '.', '\\' ), array( '-', '/' ), $raw );

		if ( ! preg_match( '/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $raw, $parts ) ) {
			return '';
		}

		$year  = (int) $parts[1];
		$month = (int) $parts[2];
		$day   = (int) $parts[3];

		if ( $year >= 1300 && $year <= 1500 ) {
			list( $year, $month, $day ) = pcs_jalali_to_gregorian( $year, $month, $day );
		}

		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}
}
