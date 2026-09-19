<?php
/**
 * توابع کمکی مشترک افزونهٔ پیش‌فروش.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * دسترسی لازم برای کار با پیش‌فروش‌ها.
 *
 * @return string
 */
function ppo_capability() {
	/**
	 * تغییر دسترسی موردنیاز کارتابل پیش‌فروش.
	 *
	 * @param string $capability دسترسی پیش‌فرض.
	 */
	return (string) apply_filters( 'ppo_capability', 'manage_preorders' );
}

/**
 * آیا کاربر جاری اجازهٔ کار با پیش‌فروش‌ها را دارد؟
 *
 * @return bool
 */
function ppo_user_can() {
	return current_user_can( ppo_capability() ) || current_user_can( 'manage_woocommerce' );
}

/**
 * تبدیل ارقام لاتین به فارسی.
 *
 * @param string|int $text ورودی.
 * @return string
 */
function ppo_digits( $text ) {
	return str_replace(
		array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
		array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
		(string) $text
	);
}

/**
 * تبدیل ارقام فارسی و عربی به لاتین.
 *
 * @param string $text ورودی.
 * @return string
 */
function ppo_latin_digits( $text ) {
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

	return str_replace( array_merge( $fa, $ar ), array_merge( $en, $en ), (string) $text );
}

/**
 * عدد با جداکنندهٔ هزارگان و ارقام فارسی.
 *
 * @param float|int $number عدد.
 * @return string
 */
function ppo_number( $number ) {
	return ppo_digits( number_format( (float) $number ) );
}

/**
 * یکدست‌سازی شمارهٔ موبایل ایرانی به قالب ‎+98…‎.
 *
 * @param string $phone شماره.
 * @return string
 */
function ppo_normalize_phone( $phone ) {
	$phone = preg_replace( '/[^0-9]/', '', ppo_latin_digits( $phone ) );

	if ( '' === $phone ) {
		return '';
	}

	if ( 0 === strpos( $phone, '0098' ) ) {
		$phone = substr( $phone, 4 );
	}

	if ( 0 === strpos( $phone, '0' ) ) {
		return '+98' . substr( $phone, 1 );
	}

	if ( 0 === strpos( $phone, '98' ) && 12 === strlen( $phone ) ) {
		return '+' . $phone;
	}

	if ( 0 === strpos( $phone, '9' ) && 10 === strlen( $phone ) ) {
		return '+98' . $phone;
	}

	return '+' . $phone;
}

/**
 * آیا شماره، موبایل ایرانی معتبری است؟
 *
 * @param string $phone شماره (یکدست‌شده).
 * @return bool
 */
function ppo_is_valid_phone( $phone ) {
	return (bool) preg_match( '/^\+989\d{9}$/', $phone );
}

/**
 * نمایش شمارهٔ موبایل به شکل خواناتر (۰۹۱۲…).
 *
 * @param string $phone شمارهٔ یکدست‌شده.
 * @return string
 */
function ppo_display_phone( $phone ) {
	$phone = (string) $phone;

	if ( 0 === strpos( $phone, '+98' ) ) {
		$phone = '0' . substr( $phone, 3 );
	}

	return $phone;
}

/* ------------------------------- تاریخ ------------------------------- */

/**
 * تبدیل تاریخ شمسی به میلادی.
 *
 * @param int $year  سال شمسی.
 * @param int $month ماه.
 * @param int $day   روز.
 * @return array{0:int,1:int,2:int}
 */
function ppo_jalali_to_gregorian( $year, $month, $day ) {
	$jy = (int) $year - 979;
	$jm = (int) $month - 1;
	$jd = (int) $day - 1;

	$days = 365 * $jy + intdiv( $jy, 33 ) * 8 + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd;
	// شش ماه نخست سال شمسی ۳۱ روزه‌اند (۱۸۶ روز) و بقیه ۳۰ روزه.
	$days += ( $jm < 6 ) ? $jm * 31 : ( $jm - 6 ) * 30 + 186;

	$gy    = 1600;
	$days += 79;

	$gy   += 400 * intdiv( $days, 146097 );
	$days %= 146097;

	if ( $days > 36524 ) {
		$gy   += 100 * intdiv( --$days, 36524 );
		$days %= 36524;

		if ( $days >= 365 ) {
			$days++;
		}
	}

	$gy   += 4 * intdiv( $days, 1461 );
	$days %= 1461;

	if ( $days > 365 ) {
		$gy  += intdiv( $days - 1, 365 );
		$days = ( $days - 1 ) % 365;
	}

	$gd     = $days + 1;
	$leap   = ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400;
	$months = array( 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
	$gm     = 12;

	foreach ( $months as $index => $length ) {
		if ( $gd <= $length ) {
			$gm = $index + 1;
			break;
		}

		$gd -= $length;
	}

	return array( $gy, $gm, $gd );
}

/**
 * تبدیل تاریخ میلادی به شمسی.
 *
 * @param int $year  سال میلادی.
 * @param int $month ماه.
 * @param int $day   روز.
 * @return array{0:int,1:int,2:int}
 */
function ppo_gregorian_to_jalali( $year, $month, $day ) {
	$gy     = (int) $year;
	$gm     = (int) $month;
	$gd     = (int) $day;
	$leap   = ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400;
	$months = array( 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );

	$days = 0;

	for ( $index = 0; $index < $gm - 1; $index++ ) {
		$days += $months[ $index ];
	}

	$days += $gd - 1;

	$offset = $gy - 1600;
	$total  = 365 * $offset + intdiv( $offset + 3, 4 ) - intdiv( $offset + 99, 100 ) + intdiv( $offset + 399, 400 ) + $days;
	$total -= 79;

	$jy     = 979 + 33 * intdiv( $total, 12053 );
	$total %= 12053;

	$jy    += 4 * intdiv( $total, 1461 );
	$total %= 1461;

	if ( $total >= 366 ) {
		$jy   += intdiv( $total - 1, 365 );
		$total = ( $total - 1 ) % 365;
	}

	if ( $total < 186 ) {
		$jm = 1 + intdiv( $total, 31 );
		$jd = 1 + ( $total % 31 );
	} else {
		$jm = 7 + intdiv( $total - 186, 30 );
		$jd = 1 + ( ( $total - 186 ) % 30 );
	}

	return array( $jy, $jm, $jd );
}

/**
 * خواندن یک تاریخ نوشته‌شدهٔ کاربر (شمسی یا میلادی) و بازگرداندن Y-m-d میلادی.
 *
 * @param string $raw ورودی.
 * @return string تاریخ میلادی یا رشتهٔ خالی.
 */
function ppo_parse_date( $raw ) {
	$raw = trim( ppo_latin_digits( $raw ) );

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

	// سال‌های ۱۳۰۰ تا ۱۵۰۰ شمسی فرض می‌شوند؛ بقیه میلادی.
	if ( $year >= 1300 && $year <= 1500 ) {
		list( $year, $month, $day ) = ppo_jalali_to_gregorian( $year, $month, $day );
	}

	if ( ! checkdate( $month, $day, $year ) ) {
		return '';
	}

	return sprintf( '%04d-%02d-%02d', $year, $month, $day );
}

/**
 * نمایش یک تاریخ میلادی به‌صورت شمسی.
 *
 * @param string $date  تاریخ Y-m-d (یا Y-m-d H:i:s).
 * @param bool   $clock ساعت هم نمایش داده شود؟
 * @return string
 */
function ppo_jalali_date( $date, $clock = false ) {
	$date = trim( (string) $date );

	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $date, $parts ) ) {
		return '';
	}

	list( $jy, $jm, $jd ) = ppo_gregorian_to_jalali( (int) $parts[1], (int) $parts[2], (int) $parts[3] );

	$text = sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );

	if ( $clock && isset( $parts[4] ) ) {
		$text .= sprintf( ' %02d:%02d', (int) $parts[4], (int) $parts[5] );
	}

	return ppo_digits( $text );
}

/**
 * نام ماه شمسی.
 *
 * @param int $month شمارهٔ ماه.
 * @return string
 */
function ppo_jalali_month_name( $month ) {
	$names = array(
		1  => 'فروردین',
		2  => 'اردیبهشت',
		3  => 'خرداد',
		4  => 'تیر',
		5  => 'مرداد',
		6  => 'شهریور',
		7  => 'مهر',
		8  => 'آبان',
		9  => 'آذر',
		10 => 'دی',
		11 => 'بهمن',
		12 => 'اسفند',
	);

	return isset( $names[ (int) $month ] ) ? $names[ (int) $month ] : '';
}

/**
 * فاصلهٔ یک تاریخ تا امروز، به روز.
 *
 * مثبت = آینده، منفی = گذشته.
 *
 * @param string $date تاریخ Y-m-d.
 * @return int|null
 */
function ppo_days_until( $date ) {
	$date = trim( (string) $date );

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $date ) ) {
		return null;
	}

	$target = strtotime( substr( $date, 0, 10 ) . ' 00:00:00' );
	$today  = strtotime( gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ) . ' 00:00:00' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

	if ( ! $target || ! $today ) {
		return null;
	}

	return (int) round( ( $target - $today ) / DAY_IN_SECONDS );
}

/**
 * متن «چند روز مانده / چند روز گذشته».
 *
 * @param string $date تاریخ Y-m-d.
 * @return string
 */
function ppo_relative_days( $date ) {
	$days = ppo_days_until( $date );

	if ( null === $days ) {
		return '';
	}

	if ( 0 === $days ) {
		return __( 'امروز', 'parsian-preorder' );
	}

	if ( $days > 0 ) {
		/* translators: %s: تعداد روز. */
		return sprintf( __( '%s روز مانده', 'parsian-preorder' ), ppo_digits( $days ) );
	}

	/* translators: %s: تعداد روز. */
	return sprintf( __( '%s روز گذشته', 'parsian-preorder' ), ppo_digits( abs( $days ) ) );
}

/* ------------------------------- قیمت ------------------------------- */

/**
 * نمایش قیمت با واحد فروشگاه.
 *
 * @param float $amount مقدار (به واحد دیتابیس).
 * @return string
 */
function ppo_price( $amount ) {
	if ( function_exists( 'wc_price' ) ) {
		return wp_strip_all_tags( wc_price( (float) $amount ) );
	}

	return ppo_number( $amount );
}
