<?php
/**
 * توابع کمکی افزونهٔ همگام‌سازی.
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * تبدیل ارقام لاتین به فارسی برای نمایش در پیشخوان.
 *
 * @param string|int $text ورودی.
 * @return string
 */
function pcs_digits( $text ) {
	return str_replace(
		array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
		array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
		(string) $text
	);
}

/**
 * تبدیل تاریخ شمسی به میلادی.
 *
 * پیاده‌سازی بر پایهٔ شمارش روز از مبدأ — بدون وابستگی به intl یا کتابخانهٔ بیرونی.
 *
 * @param int $year  سال شمسی.
 * @param int $month ماه شمسی (۱ تا ۱۲).
 * @param int $day   روز شمسی.
 * @return array{0:int,1:int,2:int} سال، ماه و روز میلادی.
 */
function pcs_jalali_to_gregorian( $year, $month, $day ) {
	$year  = (int) $year;
	$month = (int) $month;
	$day   = (int) $day;

	$jy = $year - 979;
	$jm = $month - 1;
	$jd = $day - 1;

	$days = 365 * $jy + intdiv( $jy, 33 ) * 8 + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd;
	// $jm صفرپایه است: شش ماه نخست ۳۱ روزه‌اند (۱۸۶ روز) و بقیه ۳۰ روزه.
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
		$gy   += intdiv( $days - 1, 365 );
		$days  = ( $days - 1 ) % 365;
	}

	$gd     = $days + 1;
	$leap   = ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400;
	$months = array( 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
	$gm     = 0;

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
 * @param int $month ماه میلادی.
 * @param int $day   روز میلادی.
 * @return array{0:int,1:int,2:int} سال، ماه و روز شمسی.
 */
function pcs_gregorian_to_jalali( $year, $month, $day ) {
	$gy = (int) $year;
	$gm = (int) $month;
	$gd = (int) $day;

	$leap   = ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400;
	$months = array( 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );

	$days = 0;

	for ( $index = 0; $index < $gm - 1; $index++ ) {
		$days += $months[ $index ];
	}

	$days += $gd - 1;

	$gy2   = $gy - 1600;
	$total = 365 * $gy2 + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $days;

	$total -= 79;

	$jy     = 979 + 33 * intdiv( $total, 12053 );
	$total %= 12053;

	$jy    += 4 * intdiv( $total, 1461 );
	$total %= 1461;

	if ( $total >= 366 ) {
		$jy    += intdiv( $total - 1, 365 );
		$total  = ( $total - 1 ) % 365;
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
 * نمایش یک تاریخ میلادی (Y-m-d) به‌صورت شمسی با ارقام فارسی.
 *
 * @param string $date تاریخ میلادی.
 * @return string
 */
function pcs_jalali_date( $date ) {
	$date = trim( (string) $date );

	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $date, $parts ) ) {
		return '';
	}

	list( $jy, $jm, $jd ) = pcs_gregorian_to_jalali( (int) $parts[1], (int) $parts[2], (int) $parts[3] );

	return pcs_digits( sprintf( '%04d/%02d/%02d', $jy, $jm, $jd ) );
}
