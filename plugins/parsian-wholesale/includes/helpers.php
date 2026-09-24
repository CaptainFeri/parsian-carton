<?php
/**
 * توابع عمومی افزونه — قالب از این‌ها برای نمایش استفاده می‌کند.
 *
 * @package parsian-wholesale
 */

defined( 'ABSPATH' ) || exit;

/**
 * ضریب تبدیل قیمت دیتابیس به قیمت نمایشی.
 *
 * قالب cartonpak قیمت‌ها را ریالی ذخیره و تومانی نمایش می‌دهد؛ قیمت پلکانی
 * در سبد به مضرب همین ضریب گرد می‌شود تا مبلغ نمایشی عدد صحیح بماند.
 *
 * @return int
 */
function pw_price_divisor() {
	$divisor = (int) apply_filters( 'pw_price_divisor', function_exists( 'cartonpak_rial_to_toman' ) ? 10 : 1 );

	return $divisor > 0 ? $divisor : 1;
}

/**
 * تبدیل ارقام لاتین به فارسی.
 *
 * @param string|int|float $text ورودی.
 * @return string
 */
function pw_digits( $text ) {
	return strtr(
		(string) $text,
		array(
			'0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
			'5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫',
		)
	);
}

/**
 * شناسهٔ محصول والد (برای واریاسیون‌ها).
 *
 * @param WC_Product|int $product محصول یا شناسه.
 * @return int
 */
function pw_parent_id( $product ) {
	if ( is_numeric( $product ) ) {
		$product = wc_get_product( (int) $product );
	}
	if ( ! $product ) {
		return 0;
	}

	return $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
}

/**
 * پله‌های قیمت یک محصول (یا پله‌های سراسری).
 *
 * @param WC_Product|int|null $product محصول؛ null یعنی پله‌های سراسری.
 * @return array پله‌های یکدست‌شده، یا آرایهٔ خالی اگر قیمت پلکانی برای این محصول خاموش است.
 */
function pw_get_tiers( $product = null ) {
	$tiers = PW_Settings::instance()->tiers();

	if ( null !== $product ) {
		$parent_id = pw_parent_id( $product );
		$mode      = $parent_id ? get_post_meta( $parent_id, '_pw_tiers_mode', true ) : '';

		if ( 'off' === $mode ) {
			return array();
		}
		if ( 'custom' === $mode ) {
			$tiers = PW_Tiers::parse( (string) get_post_meta( $parent_id, '_pw_tiers', true ) );
		}
	}

	$tiers = apply_filters( 'pw_tiers', $tiers, $product );

	return PW_Tiers::is_active( $tiers ) ? PW_Tiers::normalize( $tiers ) : array();
}

/**
 * برچسب فارسی یک بازه: «۱ تا ۹۹ عدد»، «۵۰۰ عدد به بالا».
 *
 * @param array $range بازه از PW_Tiers::ranges().
 * @return string
 */
function pw_range_label( $range ) {
	if ( null === $range['max'] ) {
		/* translators: %s: حداقل تعداد */
		return sprintf( __( '%s عدد به بالا', 'parsian-wholesale' ), pw_digits( number_format( $range['min'], 0, '', '٬' ) ) );
	}
	if ( $range['min'] === $range['max'] ) {
		/* translators: %s: تعداد */
		return sprintf( __( '%s عدد', 'parsian-wholesale' ), pw_digits( number_format( $range['min'], 0, '', '٬' ) ) );
	}

	/* translators: 1: حداقل 2: حداکثر */
	return sprintf(
		__( '%1$s تا %2$s عدد', 'parsian-wholesale' ),
		pw_digits( number_format( $range['min'], 0, '', '٬' ) ),
		pw_digits( number_format( $range['max'], 0, '', '٬' ) )
	);
}

/**
 * برچسب تخفیف یک پله: «قیمت پایه» یا «۵٪ ارزان‌تر».
 *
 * @param float $discount درصد تخفیف.
 * @return string
 */
function pw_discount_label( $discount ) {
	if ( $discount <= 0 ) {
		return __( 'قیمت پایه', 'parsian-wholesale' );
	}

	/* translators: %s: درصد */
	return sprintf( __( '%s٪ ارزان‌تر', 'parsian-wholesale' ), pw_digits( PW_Tiers::format_number( $discount ) ) );
}

/**
 * تنظیمات تعداد برای قالب (پیش‌فرض، گام، میان‌برها).
 *
 * @return array
 */
function pw_quantity_settings() {
	$settings = PW_Settings::instance();

	return array(
		'default' => (int) $settings->get( 'default_qty', 0 ),
		'step'    => max( 1, (int) $settings->get( 'qty_step', 1 ) ),
		'presets' => $settings->presets(),
	);
}

/**
 * نشانی فرم استعلام قیمت، در صورت نیاز همراه با محصول.
 *
 * @param WC_Product|int|null $product محصول.
 * @return string
 */
function pw_quote_url( $product = null ) {
	$url = apply_filters( 'pw_quote_page_url', home_url( '/' ) );
	if ( $product ) {
		$url = add_query_arg( 'pw_product', pw_parent_id( $product ), $url );
	}

	return $url . '#quote';
}
