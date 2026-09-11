<?php
/**
 * توابع کمکی و ارسال پیامک (کاوه‌نگار)
 *
 * @package parsian_preorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function parsian_preorder_defaults() {
	return array(
		'customer_template' => 'parsianpreorder',
		'admin_template'    => 'parsianpreorderadmin',
		'admin_phone'       => '',
	);
}

function parsian_preorder_get_option( $key ) {
	$defaults = parsian_preorder_defaults();
	$stored   = get_option( 'parsian_preorder_' . $key, null );
	if ( array_key_exists( $key, $defaults ) && null === $stored ) {
		return $defaults[ $key ];
	}
	return null !== $stored ? $stored : '';
}

/**
 * کلید API کاوه‌نگار — از افزونه «ورود با پیامک» (parsian-otp) برداشته می‌شود.
 */
function parsian_preorder_api_key() {
	$key = get_option( 'parsian_otp_api_key', '' );
	if ( '' === $key ) {
		$key = get_option( 'parsian_preorder_api_key', '' );
	}
	return $key;
}

function parsian_preorder_normalize_phone( $phone ) {
	$fa    = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$en    = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$phone = str_replace( $fa, $en, (string) $phone );
	$phone = preg_replace( '/[^0-9]/', '', $phone );
	if ( 0 === strpos( $phone, '0' ) ) {
		$phone = '+98' . substr( $phone, 1 );
	} elseif ( 0 === strpos( $phone, '98' ) && 11 === strlen( $phone ) ) {
		$phone = '+' . $phone;
	} elseif ( 0 === strpos( $phone, '9' ) && 10 === strlen( $phone ) ) {
		$phone = '+98' . $phone;
	}
	return $phone;
}

function parsian_preorder_is_valid_phone( $phone ) {
	return (bool) preg_match( '/^\+98\d{10}$/', $phone );
}

function parsian_preorder_fa_digits( $text ) {
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	return str_replace( $en, $fa, (string) $text );
}

function parsian_preorder_statuses() {
	return array(
		'new'       => 'جدید',
		'contacted' => 'تماس گرفته شد',
		'converted' => 'تبدیل به سفارش',
		'cancelled' => 'لغو شده',
	);
}

function parsian_preorder_status_label( $status ) {
	$statuses = parsian_preorder_statuses();
	return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
}

/**
 * شماره تلفن اطلاع‌رسانی به پشتیبانی — از تنظیمات افزونه یا سفارشی‌سازی قالب.
 */
function parsian_preorder_admin_phone() {
	$phone = parsian_preorder_get_option( 'admin_phone' );
	if ( '' === $phone ) {
		$phone = get_theme_mod( 'cartonpak_mobile', '' );
	}
	if ( '' === $phone ) {
		$phone = get_theme_mod( 'cartonpak_support', '' );
	}
	return parsian_preorder_normalize_phone( $phone );
}

/**
 * ارسال پیامک از طریق کاوه‌نگار (سرویس verify/lookup).
 *
 * @param string $phone    شماره گیرنده.
 * @param string $token    پارامتر اول قالب.
 * @param string $token2   پارامتر دوم قالب (اختیاری).
 * @param string $template نام قالب (پیش‌فرض: قالب مشتری).
 *
 * @return true|WP_Error|string (true = ارسال موفق، WP_Error = خطا، 'test' = بدون کلید API)
 */
function parsian_preorder_send_sms( $phone, $token, $token2 = '', $template = '' ) {
	$api_key = parsian_preorder_api_key();

	if ( '' === $api_key ) {
		return 'test'; // بدون کلید API، پیامکی ارسال نمی‌شود.
	}

	if ( '' === $template ) {
		$template = parsian_preorder_get_option( 'customer_template' );
	}

	$endpoint = sprintf(
		'https://api.kavenegar.com/v1/%s/verify/lookup.json',
		rawurlencode( $api_key )
	);

	$body = array(
		'receptor' => $phone,
		'token'    => (string) $token,
		'template' => $template,
	);
	if ( '' !== $token2 ) {
		$body['token2'] = (string) $token2;
	}

	$response = wp_remote_post(
		$endpoint,
		array(
			'timeout' => 15,
			'body'    => $body,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$json = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( isset( $json['return']['status'] ) && 200 === (int) $json['return']['status'] ) {
		return true;
	}

	$msg = isset( $json['return']['message'] ) ? $json['return']['message'] : 'خطای ناشناخته سرویس کاوه‌نگار';
	return new WP_Error( 'kavenegar', $msg );
}