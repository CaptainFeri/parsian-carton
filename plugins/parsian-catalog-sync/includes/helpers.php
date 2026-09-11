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
