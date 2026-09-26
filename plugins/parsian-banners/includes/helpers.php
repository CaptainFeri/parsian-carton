<?php
/**
 * توابع کمکی افزونهٔ بنرها.
 *
 * @package parsian-banners
 */

defined( 'ABSPATH' ) || exit;

/**
 * تبدیل ارقام لاتین به فارسی.
 *
 * @param string|int $text ورودی.
 * @return string
 */
function pbn_digits( $text ) {
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
function pbn_latin_digits( $text ) {
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

	return str_replace( array_merge( $fa, $ar ), array_merge( $en, $en ), (string) $text );
}

/**
 * تبدیل تاریخ شمسی به میلادی.
 *
 * @param int $year  سال شمسی.
 * @param int $month ماه.
 * @param int $day   روز.
 * @return array{0:int,1:int,2:int}
 */
function pbn_jalali_to_gregorian( $year, $month, $day ) {
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
function pbn_gregorian_to_jalali( $year, $month, $day ) {
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
 * خواندن تاریخ نوشته‌شدهٔ کاربر (شمسی یا میلادی) و بازگرداندن Y-m-d میلادی.
 *
 * @param string $raw ورودی.
 * @return string
 */
function pbn_parse_date( $raw ) {
	$raw = trim( pbn_latin_digits( $raw ) );

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
		list( $year, $month, $day ) = pbn_jalali_to_gregorian( $year, $month, $day );
	}

	if ( ! checkdate( $month, $day, $year ) ) {
		return '';
	}

	return sprintf( '%04d-%02d-%02d', $year, $month, $day );
}

/**
 * نمایش یک تاریخ میلادی به‌صورت شمسی.
 *
 * @param string $date تاریخ Y-m-d.
 * @return string
 */
function pbn_jalali_date( $date ) {
	$date = trim( (string) $date );

	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $date, $parts ) ) {
		return '';
	}

	list( $jy, $jm, $jd ) = pbn_gregorian_to_jalali( (int) $parts[1], (int) $parts[2], (int) $parts[3] );

	return pbn_digits( sprintf( '%04d/%02d/%02d', $jy, $jm, $jd ) );
}
