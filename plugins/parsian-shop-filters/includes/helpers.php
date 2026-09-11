<?php
/**
 * توابع کمکی مشترک.
 *
 * @package parsian-shop-filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * ضریب تبدیل قیمت ذخیره‌شده در دیتابیس به قیمت نمایشی.
 *
 * قالب cartonpak قیمت‌ها را به ریال ذخیره می‌کند و هنگام نمایش بر ۱۰ تقسیم می‌کند.
 * پنل فیلتر باید همان واحدی را به کاربر نشان دهد که در کارت محصول می‌بیند،
 * ولی پرس‌وجو باید روی مقدار خام دیتابیس (`_price`) انجام شود.
 *
 * @return int ضریب (۱ یعنی بدون تبدیل).
 */
function psf_price_divisor() {
	$divisor = function_exists( 'cartonpak_rial_to_toman' ) ? 10 : 1;

	/**
	 * تغییر ضریب تبدیل واحد قیمت.
	 *
	 * @param int $divisor ضریب پیش‌فرض.
	 */
	$divisor = (int) apply_filters( 'psf_price_divisor', $divisor );

	return $divisor > 0 ? $divisor : 1;
}

/**
 * تبدیل قیمت دیتابیس به واحد نمایشی.
 *
 * @param float $amount مقدار خام.
 * @return float
 */
function psf_to_display_price( $amount ) {
	return (float) $amount / psf_price_divisor();
}

/**
 * تبدیل قیمت واردشده توسط کاربر به واحد دیتابیس.
 *
 * @param float $amount مقدار نمایشی.
 * @return float
 */
function psf_to_raw_price( $amount ) {
	return (float) $amount * psf_price_divisor();
}

/**
 * تبدیل ارقام لاتین به فارسی.
 *
 * @param string|int $text ورودی.
 * @return string
 */
function psf_digits( $text ) {
	if ( function_exists( 'cartonpak_digits' ) ) {
		return cartonpak_digits( (string) $text );
	}

	return str_replace(
		array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
		array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
		(string) $text
	);
}

/**
 * قالب‌بندی عدد قیمت برای نمایش (جداکنندهٔ هزارگان فارسی).
 *
 * @param float $amount مقدار نمایشی.
 * @return string
 */
function psf_format_price( $amount ) {
	return psf_digits( number_format( (float) $amount, 0, '.', '٬' ) );
}

/**
 * تبدیل ارقام فارسی/عربی به لاتین — برای ورودی‌های کاربر.
 *
 * @param string $text ورودی.
 * @return string
 */
function psf_normalize_digits( $text ) {
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

	return str_replace( array_merge( $fa, $ar ), array_merge( $en, $en ), (string) $text );
}

/**
 * آیا صفحهٔ جاری یک بایگانی محصول است که باید فیلتر بگیرد؟
 *
 * @return bool
 */
function psf_is_filterable_archive() {
	if ( ! function_exists( 'is_shop' ) ) {
		return false;
	}

	$is_archive = is_shop() || is_product_taxonomy();

	/**
	 * تعیین اینکه پنل فیلتر در صفحهٔ جاری نمایش داده شود یا نه.
	 *
	 * @param bool $is_archive نتیجهٔ پیش‌فرض.
	 */
	return (bool) apply_filters( 'psf_is_filterable_archive', $is_archive );
}

/**
 * خواندن یک پارامتر GET به‌صورت آرایهٔ اسلاگ پاک‌سازی‌شده.
 *
 * اسلاگ ترم‌های فارسی در وردپرس به‌صورت درصدی ذخیره می‌شود
 * (مثلاً «کارتن پستی» ← `%da%a9%d8%a7%d8%b1%d8%aa%d9%86-%d9%be%d8%b3%d8%aa%db%8c`).
 * مقدار رسیده از مرورگر ممکن است همان شکل درصدی باشد یا شکل رمزگشایی‌شدهٔ آن؛
 * `sanitize_title` هر دو را به یک اسلاگ می‌رساند (روی مقدار درصدی خوداستوار است و
 * مقدار رمزگشایی‌شده را دوباره رمزگذاری می‌کند)، پس مقایسه با `$term->slug` درست
 * درمی‌آید.
 *
 * @param string $key نام پارامتر.
 * @return string[]
 */
function psf_get_array_param( $key ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- پارامترهای فیلتر عمومی و فقط-خواندنی هستند.
	if ( ! isset( $_GET[ $key ] ) ) {
		return array();
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$raw = wp_unslash( $_GET[ $key ] );
	$raw = is_array( $raw ) ? $raw : explode( ',', (string) $raw );

	$values = array();
	foreach ( $raw as $item ) {
		$item = sanitize_title( trim( (string) $item ) );
		if ( '' !== $item ) {
			$values[] = $item;
		}
	}

	return array_values( array_unique( $values ) );
}

/**
 * خواندن یک پارامتر GET عددی.
 *
 * @param string $key نام پارامتر.
 * @return float|null
 */
function psf_get_number_param( $key ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET[ $key ] ) || '' === $_GET[ $key ] ) {
		return null;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$value = psf_normalize_digits( wp_unslash( $_GET[ $key ] ) );
	$value = preg_replace( '/[^0-9.\-]/', '', $value );

	return is_numeric( $value ) ? (float) $value : null;
}

/**
 * نام پارامتر GET مربوط به یک تاکسونومی ویژگی.
 *
 * @param string $taxonomy نام تاکسونومی (مثلاً pa_size).
 * @return string
 */
function psf_attribute_param( $taxonomy ) {
	return 'psf_attr_' . str_replace( 'pa_', '', $taxonomy );
}
