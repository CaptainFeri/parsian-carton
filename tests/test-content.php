<?php
/**
 * آزمون ترمیم و آماده‌سازی متن توضیحات.
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../plugins/parsian-catalog-sync/includes/class-pcs-content.php';

$failures = 0;

function check( $label, $actual, $expected ) {
	global $failures;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		$failures++;
		printf( "FAIL %s\n   expected: %s\n   actual:   %s\n", $label, var_export( $expected, true ), var_export( $actual, true ) );
	} else {
		printf( "PASS %s\n", $label );
	}
}

/* ---------- ترمیم: همان خرابی واقعی فروشگاه ---------- */

// در دیتابیس، هر خط جدید واقعی یک رشتهٔ تحت‌اللفظی «\n» هم کنارش دارد.
$broken = "<strong>کارتن پستی سایز ۹</strong> — ابعاد ۵۵×۴۵×۳۵ سانتی‌متر، ۵ لایه.\n\\n\n\\nجنس: کرافت، فلوت، ۲ ایرانی، تست\n\\n<ul>\n\\n \t<li>قیمت خرد: 208,000 تومان (هر عدد)</li>\n\\n \t<li>قیمت عمده: 2,080,000 تومان (هر بسته)</li>\n\\n</ul>\n\\nاین محصول مستقیماً از خط تولید کارخانه «به نگار زرین پارسیان» عرضه می‌شود.";

$fixed = PCS_Content::prepare( $broken );

check( 'هیچ «\\n» تحت‌اللفظی باقی نمی‌ماند', false !== strpos( $fixed, '\n' ), false );
check( 'تگ‌های HTML دست‌نخورده می‌مانند', false !== strpos( $fixed, '<ul>' ), true );
check( 'عنوان پررنگ حفظ می‌شود', false !== strpos( $fixed, '<strong>کارتن پستی سایز ۹</strong>' ), true );
check( 'قیمت خرد حفظ می‌شود', false !== strpos( $fixed, '208,000 تومان' ), true );
check( 'جملهٔ پایانی حفظ می‌شود', false !== strpos( $fixed, 'به نگار زرین پارسیان' ), true );
check( 'آیتم‌های فهرست سالم‌اند', substr_count( $fixed, '<li>' ), 2 );

// خط خالی داخل فهرست، در ویرایشگر وردپرس پاراگراف خالی می‌سازد.
check( 'خط خالی داخل فهرست پاک می‌شود', (bool) preg_match( '/<ul>\s*\n\s*\n/', $fixed ), false );

check( 'ترمیم ایدم‌پوتنت است', PCS_Content::prepare( $fixed ), $fixed );

/* ---------- متن ساده به HTML ---------- */

$plain = "کارتن پنج لایه مقاوم.\n\nمناسب اسباب‌کشی و بسته‌بندی سنگین.";
check(
	'دو پاراگراف ساده',
	PCS_Content::prepare( $plain ),
	"<p>کارتن پنج لایه مقاوم.</p>\n\n<p>مناسب اسباب‌کشی و بسته‌بندی سنگین.</p>"
);

$single_break = "خط اول\nخط دوم";
check(
	'خط جدید تکی به <br> تبدیل می‌شود',
	PCS_Content::prepare( $single_break ),
	"<p>خط اول<br>\nخط دوم</p>"
);

$bullets = "ویژگی‌ها:\n\n- سه لایه\n- چاپ اداره پست\n- بسته ۲۰ عددی";
$result  = PCS_Content::prepare( $bullets );
check( 'فهرست نقطه‌ای ساخته می‌شود', false !== strpos( $result, '<ul>' ), true );
check( 'سه آیتم فهرست', substr_count( $result, '<li>' ), 3 );
check( 'متن پیش از فهرست پاراگراف می‌شود', false !== strpos( $result, '<p>ویژگی‌ها:</p>' ), true );

$numbered = "1. مرحلهٔ اول\n2. مرحلهٔ دوم";
check( 'فهرست شماره‌دار', false !== strpos( PCS_Content::prepare( $numbered ), '<ol>' ), true );

$fa_numbered = "۱. مرحلهٔ اول\n۲. مرحلهٔ دوم";
check( 'فهرست شماره‌دار با ارقام فارسی', false !== strpos( PCS_Content::prepare( $fa_numbered ), '<ol>' ), true );

// خطی که نشانهٔ فهرست ندارد، بلوک را از فهرست بودن خارج می‌کند.
$mixed = "- یک\nدو";
check( 'بلوک نیمه‌فهرست، پاراگراف می‌شود', false !== strpos( PCS_Content::prepare( $mixed ), '<ul>' ), false );

/* ---------- موارد مرزی ---------- */

check( 'متن خالی', PCS_Content::prepare( '' ), '' );
check( 'فقط فاصله', PCS_Content::prepare( "  \n  " ), '' );
check( 'HTML موجود دوباره پاراگراف‌بندی نمی‌شود', PCS_Content::prepare( '<p>سلام</p>' ), '<p>سلام</p>' );
check( 'نویسه‌های خاص در متن ساده فرار داده می‌شوند', PCS_Content::prepare( 'قیمت < ۱۰۰۰' ), '<p>قیمت &lt; ۱۰۰۰</p>' );
check( 'خطوط خالی زیاد جمع می‌شوند', PCS_Content::repair( "الف\n\n\n\n\nب" ), "الف\n\nب" );

echo $failures ? "\n{$failures} آزمون شکست خورد.\n" : "\nهمهٔ آزمون‌ها موفق بودند.\n";
exit( $failures ? 1 : 0 );
