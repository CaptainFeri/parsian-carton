<?php
/**
 * آزمون توابع کمکی افزونهٔ فیلتر — بدون نیاز به وردپرس (توابع لازم شبیه‌سازی می‌شوند).
 */

define( 'ABSPATH', __DIR__ );

function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function sanitize_title( $v ) { return strtolower( trim( preg_replace( '/[^\p{L}\p{N}_-]+/u', '-', (string) $v ), '-' ) ); }
function apply_filters( $tag, $value ) { return $value; }
function is_shop() { return true; }
function is_product_taxonomy() { return false; }

require __DIR__ . '/../plugins/parsian-shop-filters/includes/helpers.php';

$failures = 0;

function check( $label, $actual, $expected ) {
	global $failures;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		$failures++;
	}
	printf(
		"%s %s\n   expected: %s\n   actual:   %s\n",
		$ok ? 'PASS' : 'FAIL',
		$label,
		var_export( $expected, true ),
		var_export( $actual, true )
	);
}

check( 'psf_price_divisor بدون قالب cartonpak', psf_price_divisor(), 1 );
check( 'psf_normalize_digits ارقام فارسی', psf_normalize_digits( '۱۲۳۴۵' ), '12345' );
check( 'psf_normalize_digits ارقام عربی', psf_normalize_digits( '٤٥٦' ), '456' );
check( 'psf_digits لاتین به فارسی', psf_digits( '2024' ), '۲۰۲۴' );
check( 'psf_format_price جداکننده', psf_format_price( 1250000 ), '۱٬۲۵۰٬۰۰۰' );
check( 'psf_attribute_param', psf_attribute_param( 'pa_size' ), 'psf_attr_size' );

$_GET['psf_cat'] = array( 'کارتن-پستی', 'Moving Cartons', '' );
check( 'psf_get_array_param آرایه', psf_get_array_param( 'psf_cat' ), array( 'کارتن-پستی', 'moving-cartons' ) );

$_GET['psf_cat'] = 'a,b,a';
check( 'psf_get_array_param کاما و تکراری', psf_get_array_param( 'psf_cat' ), array( 'a', 'b' ) );

$_GET['psf_min'] = '۱٬۲۵۰٬۰۰۰';
check( 'psf_get_number_param با جداکنندهٔ فارسی', psf_get_number_param( 'psf_min' ), 1250000.0 );

$_GET['psf_max'] = '';
check( 'psf_get_number_param خالی', psf_get_number_param( 'psf_max' ), null );

check( 'psf_get_number_param غایب', psf_get_number_param( 'nope' ), null );

// با فعال بودن قالب، قیمت‌ها به تومان تبدیل می‌شوند.
// تعریف با eval انجام می‌شود تا PHP آن را به ابتدای فایل منتقل نکند.
eval( 'function cartonpak_rial_to_toman( $p ) { return $p / 10; }' );
check( 'psf_price_divisor با قالب cartonpak', psf_price_divisor(), 10 );
check( 'psf_to_display_price ریال به تومان', psf_to_display_price( 850000 ), 85000.0 );
check( 'psf_to_raw_price تومان به ریال', psf_to_raw_price( 85000 ), 850000.0 );

echo $failures ? "\n{$failures} آزمون شکست خورد.\n" : "\nهمهٔ آزمون‌ها موفق بودند.\n";
exit( $failures ? 1 : 0 );
