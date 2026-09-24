<?php
/**
 * منطق خالص قیمت پلکانی — بدون وابستگی به وردپرس تا آزمون‌پذیر بماند.
 *
 * هر پله یک جفت [حداقل تعداد، درصد تخفیف] است. پلهٔ اول همیشه از ۱ شروع
 * می‌شود؛ اگر کاربر آن را ننوشته باشد، پلهٔ «۱ عدد با قیمت پایه» اضافه می‌شود.
 *
 * @package parsian-wholesale
 */

defined( 'ABSPATH' ) || exit;

/**
 * پله‌های قیمت.
 */
class PW_Tiers {

	/**
	 * بیشینهٔ درصد تخفیف مجاز یک پله.
	 */
	const MAX_DISCOUNT = 90;

	/**
	 * یکدست کردن فهرست پله‌ها.
	 *
	 * @param array $rows ردیف‌های [حداقل، تخفیف] یا ['min' => , 'discount' => ].
	 * @return array فهرست مرتب [[min, discount], ...] که همیشه با min=1 شروع می‌شود.
	 */
	public static function normalize( $rows ) {
		$by_min = array();

		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$min      = isset( $row['min'] ) ? $row['min'] : ( isset( $row[0] ) ? $row[0] : '' );
			$discount = isset( $row['discount'] ) ? $row['discount'] : ( isset( $row[1] ) ? $row[1] : 0 );
			$min      = self::to_number( $min );
			$discount = self::to_number( $discount );

			if ( null === $min || $min < 1 ) {
				continue;
			}

			$min      = (int) floor( $min );
			$discount = null === $discount ? 0.0 : round( max( 0.0, min( (float) self::MAX_DISCOUNT, $discount ) ), 2 );

			$by_min[ $min ] = $discount;
		}

		if ( ! isset( $by_min[1] ) ) {
			$by_min[1] = 0.0;
		}

		ksort( $by_min, SORT_NUMERIC );

		$tiers = array();
		foreach ( $by_min as $min => $discount ) {
			$tiers[] = array( (int) $min, (float) $discount );
		}

		return $tiers;
	}

	/**
	 * خواندن پله‌ها از متن «۱۰۰:۵، ۵۰۰:۱۰».
	 *
	 * جداکننده‌ها: کاما، ویرگول فارسی، نقطه‌ویرگول یا خط جدید. علامت ٪ و ارقام
	 * فارسی/عربی پذیرفته می‌شوند.
	 *
	 * @param string $text متن ورودی.
	 * @return array پله‌های یکدست‌شده.
	 */
	public static function parse( $text ) {
		$text  = self::latin_digits( (string) $text );
		$parts = preg_split( '/[,،;؛\r\n]+/u', $text );
		$rows  = array();

		foreach ( $parts as $part ) {
			$part = trim( str_replace( array( '%', '٪' ), '', $part ) );
			if ( '' === $part ) {
				continue;
			}
			$pair = preg_split( '/\s*[:=]\s*/u', $part );
			if ( count( $pair ) !== 2 ) {
				continue;
			}
			$rows[] = array( $pair[0], $pair[1] );
		}

		return self::normalize( $rows );
	}

	/**
	 * تبدیل پله‌ها به متن قابل ویرایش.
	 *
	 * پلهٔ پایه (۱ عدد بدون تخفیف) نوشته نمی‌شود چون همیشه ضمنی است.
	 *
	 * @param array $tiers پله‌ها.
	 * @return string
	 */
	public static function to_text( $tiers ) {
		$out = array();
		foreach ( self::normalize( $tiers ) as $tier ) {
			if ( 1 === $tier[0] && 0.0 === $tier[1] ) {
				continue;
			}
			$out[] = $tier[0] . ':' . self::format_number( $tier[1] );
		}

		return implode( ', ', $out );
	}

	/**
	 * آیا دست‌کم یک پله تخفیف دارد؟
	 *
	 * @param array $tiers پله‌ها.
	 * @return bool
	 */
	public static function is_active( $tiers ) {
		foreach ( (array) $tiers as $tier ) {
			if ( isset( $tier[1] ) && (float) $tier[1] > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * اندیس پله‌ای که تعداد در آن می‌افتد.
	 *
	 * @param array     $tiers پله‌ها (یکدست‌شده).
	 * @param int|float $qty   تعداد.
	 * @return int
	 */
	public static function find( $tiers, $qty ) {
		$index = 0;
		foreach ( array_values( (array) $tiers ) as $i => $tier ) {
			if ( $qty >= $tier[0] ) {
				$index = $i;
			}
		}

		return $index;
	}

	/**
	 * درصد تخفیف برای یک تعداد.
	 *
	 * @param array     $tiers پله‌ها.
	 * @param int|float $qty   تعداد.
	 * @return float
	 */
	public static function discount_for( $tiers, $qty ) {
		$tiers = array_values( (array) $tiers );
		if ( ! $tiers ) {
			return 0.0;
		}

		return (float) $tiers[ self::find( $tiers, $qty ) ][1];
	}

	/**
	 * اعمال تخفیف روی قیمت.
	 *
	 * @param float $price    قیمت پایه.
	 * @param float $discount درصد تخفیف.
	 * @param int   $step     گرد کردن به مضرب این عدد (مثلاً ۱۰ برای ریالی که تومانی نمایش داده می‌شود).
	 * @return float
	 */
	public static function apply( $price, $discount, $step = 1 ) {
		$price = (float) $price;
		if ( $discount <= 0 || $price <= 0 ) {
			return $price;
		}

		$step = max( 1, (int) $step );

		return (float) ( round( $price * ( 100 - (float) $discount ) / 100 / $step ) * $step );
	}

	/**
	 * بازه‌های قابل نمایش: هر پله با حداقل و حداکثرش.
	 *
	 * @param array $tiers پله‌ها.
	 * @return array [['min' => 1, 'max' => 99, 'discount' => 0.0], ..., ['min' => 500, 'max' => null, ...]]
	 */
	public static function ranges( $tiers ) {
		$tiers  = array_values( self::normalize( $tiers ) );
		$ranges = array();
		$count  = count( $tiers );

		foreach ( $tiers as $i => $tier ) {
			$ranges[] = array(
				'min'      => $tier[0],
				'max'      => $i + 1 < $count ? $tiers[ $i + 1 ][0] - 1 : null,
				'discount' => $tier[1],
			);
		}

		return $ranges;
	}

	/**
	 * نمایش عدد بدون صفرهای اعشاری اضافه (۵.۵۰ ← ۵.۵).
	 *
	 * @param float $number عدد.
	 * @return string
	 */
	public static function format_number( $number ) {
		$text = number_format( (float) $number, 2, '.', '' );

		return rtrim( rtrim( $text, '0' ), '.' );
	}

	/**
	 * تبدیل ورودی به عدد؛ ارقام فارسی و جداکنندهٔ هزارگان را می‌فهمد.
	 *
	 * @param mixed $value ورودی.
	 * @return float|null
	 */
	public static function to_number( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}

		$value = self::latin_digits( trim( (string) $value ) );
		$value = str_replace( array( ',', '٬', ' ', '%', '٪' ), '', $value );
		$value = str_replace( '٫', '.', $value );

		return is_numeric( $value ) ? (float) $value : null;
	}

	/**
	 * تبدیل ارقام فارسی و عربی به لاتین.
	 *
	 * @param string $text متن.
	 * @return string
	 */
	public static function latin_digits( $text ) {
		return strtr(
			(string) $text,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);
	}
}
