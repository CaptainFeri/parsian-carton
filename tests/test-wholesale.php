<?php
/**
 * آزمون منطق قیمت پلکانی و اعتبارسنجی فرم استعلام — بدون نیاز به وردپرس.
 */

define( 'ABSPATH', __DIR__ );

function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $tag, $value ) { return $value; }
function wc_get_product( $id ) { return null; }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function add_shortcode( ...$args ) {}

require __DIR__ . '/../plugins/parsian-wholesale/includes/class-pw-tiers.php';
require __DIR__ . '/../plugins/parsian-wholesale/includes/class-pw-quote.php';

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

/* ---------- یکدست‌سازی و خواندن پله‌ها ---------- */

check(
	'normalize: پلهٔ پایه اضافه و مرتب می‌شود',
	PW_Tiers::normalize( array( array( 500, 10 ), array( 100, '5' ) ) ),
	array( array( 1, 0.0 ), array( 100, 5.0 ), array( 500, 10.0 ) )
);

check(
	'normalize: ردیف نامعتبر حذف، تکراری با آخری جایگزین، تخفیف محدود',
	PW_Tiers::normalize( array( array( 0, 5 ), array( 'abc', 5 ), array( 100, 5 ), array( 100, 150 ) ) ),
	array( array( 1, 0.0 ), array( 100, 90.0 ) )
);

check(
	'normalize: کلیدهای min/discount',
	PW_Tiers::normalize( array( array( 'min' => '۱', 'discount' => '۲' ), array( 'min' => '۲۰۰', 'discount' => '' ) ) ),
	array( array( 1, 2.0 ), array( 200, 0.0 ) )
);

check(
	'parse: ارقام فارسی، ٪ و ویرگول فارسی',
	PW_Tiers::parse( '۱۰۰:۵٪، ۵۰۰ : ۱۲.۵' ),
	array( array( 1, 0.0 ), array( 100, 5.0 ), array( 500, 12.5 ) )
);

check( 'parse: متن خالی فقط پلهٔ پایه', PW_Tiers::parse( '' ), array( array( 1, 0.0 ) ) );
check( 'to_text: پلهٔ پایه نوشته نمی‌شود', PW_Tiers::to_text( PW_Tiers::parse( '500:10, 100:5' ) ), '100:5, 500:10' );

/* ---------- انتخاب پله ---------- */

$tiers = PW_Tiers::parse( '100:5, 500:10' );

check( 'is_active: با تخفیف', PW_Tiers::is_active( $tiers ), true );
check( 'is_active: همهٔ درصدها صفر', PW_Tiers::is_active( PW_Tiers::parse( '100:0, 500:0' ) ), false );
check( 'find: ۱ عدد ← پلهٔ اول', PW_Tiers::find( $tiers, 1 ), 0 );
check( 'find: ۹۹ عدد ← پلهٔ اول', PW_Tiers::find( $tiers, 99 ), 0 );
check( 'find: ۱۰۰ عدد ← پلهٔ دوم', PW_Tiers::find( $tiers, 100 ), 1 );
check( 'find: ۴۹۹ عدد ← پلهٔ دوم', PW_Tiers::find( $tiers, 499 ), 1 );
check( 'find: ۵۰۰۰ عدد ← پلهٔ سوم', PW_Tiers::find( $tiers, 5000 ), 2 );
check( 'discount_for: ۲۵۰ عدد', PW_Tiers::discount_for( $tiers, 250 ), 5.0 );
check( 'discount_for: بدون پله', PW_Tiers::discount_for( array(), 250 ), 0.0 );

check(
	'ranges: بازه‌های طرح',
	PW_Tiers::ranges( $tiers ),
	array(
		array( 'min' => 1, 'max' => 99, 'discount' => 0.0 ),
		array( 'min' => 100, 'max' => 499, 'discount' => 5.0 ),
		array( 'min' => 500, 'max' => null, 'discount' => 10.0 ),
	)
);

/* ---------- اعمال تخفیف ---------- */

check( 'apply: ۵٪ از ۳۱۵۰۰', PW_Tiers::apply( 31500, 5 ), 29925.0 );
check( 'apply: گرد به مضرب ۱۰ (ریال ← تومان)', PW_Tiers::apply( 7038, 5, 10 ), 6690.0 );
check( 'apply: بدون تخفیف دست‌نخورده', PW_Tiers::apply( 7038, 0, 10 ), 7038.0 );
check( 'apply: قیمت صفر', PW_Tiers::apply( 0, 10 ), 0.0 );

/* تخفیف تکراری نباید روی هم انباشته شود: اعمال روی پایه، نه روی نتیجهٔ قبلی. */
$base = 31500.0;
$once = PW_Tiers::apply( $base, 10 );
check( 'apply: دو بار روی پایه = یک نتیجه', PW_Tiers::apply( $base, 10 ), $once );

check( 'to_number: جداکنندهٔ فارسی', PW_Tiers::to_number( '۱٬۲۵۰' ), 1250.0 );
check( 'to_number: ممیز فارسی', PW_Tiers::to_number( '۱۲٫۵' ), 12.5 );
check( 'to_number: نامعتبر', PW_Tiers::to_number( 'abc' ), null );
check( 'format_number', PW_Tiers::format_number( 12.50 ), '12.5' );

/* ---------- اعتبارسنجی فرم استعلام ---------- */

$ok = PW_Quote::validate(
	array(
		'pw_length' => '۳۰',
		'pw_width'  => '۲۰',
		'pw_height' => '20',
		'pw_qty'    => '۵۰۰',
		'pw_type'   => 'پنج‌لایه',
		'pw_name'   => ' <b>علی</b> ',
		'pw_phone'  => '۰۹۱۲ ۰۸۱ ۰۷۶۹',
	)
);
check( 'validate: فرم کامل بدون خطا', $ok['errors'], array() );
check( 'validate: ارقام و فاصلهٔ شماره تماس', $ok['data']['phone'], '09120810769' );
check( 'validate: پاک‌سازی نام', $ok['data']['name'], 'علی' );
check( 'validate: ابعاد عددی', array( $ok['data']['length'], $ok['data']['width'], $ok['data']['height'] ), array( 30.0, 20.0, 20.0 ) );
check( 'summary', PW_Quote::summary( $ok['data'] ), '30×20×20 سانتی‌متر — پنج‌لایه — 500 عدد' );

$bad = PW_Quote::validate(
	array(
		'pw_length' => '30',
		'pw_qty'    => '0',
		'pw_type'   => 'چیز دیگر',
		'pw_phone'  => '123',
	)
);
check( 'validate: خطاهای تعداد، ابعاد و شماره', $bad['errors'], array( 'qty', 'size', 'phone' ) );
check( 'validate: نوع ناشناخته پذیرفته نمی‌شود', $bad['data']['type'], '' );

echo "\n";
if ( $failures ) {
	echo "❌ {$failures} آزمون شکست خورد.\n";
	exit( 1 );
}
echo "✅ همهٔ آزمون‌های فروش عمده موفق بودند.\n";
