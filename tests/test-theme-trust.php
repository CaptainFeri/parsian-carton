<?php
/**
 * آزمون نشان‌های اعتماد فوتر قالب.
 *
 * دو جای باریک: بیرون کشیدن پارامتر Code از نشانی اینماد (که سامانه با حرف
 * بزرگ می‌نویسد و ممکن است هر جای نشانی باشد)، و تزریق loading=lazy به کدی که
 * مدیر از پنل سامانه کپی کرده — بدون خراب کردن بقیهٔ کد.
 */

require __DIR__ . '/wp-stubs.php';

$failures = 0;

function check( $label, $actual, $expected ) {
	global $failures;
	if ( $actual === $expected ) {
		printf( "PASS %s\n", $label );
		return;
	}
	$failures++;
	printf( "FAIL %s\n   expected: %s\n   actual:   %s\n", $label, var_export( $expected, true ), var_export( $actual, true ) );
}

/* ---------- تنها همان دو تابع را از قالب برمی‌داریم ---------- */

// کل functions.php قالب به وردپرس و ووکامرس وصل است و اینجا بار نمی‌شود. این دو
// تابع خالص‌اند، پس از روی فایل برداشته و همان‌جا ارزیابی می‌شوند؛ اگر امضایشان
// در قالب عوض شود، آزمون با خطای «پیدا نشد» می‌افتد — که خودش هشدار است.
$source = file_get_contents( __DIR__ . '/../theme/cartonpak/functions.php' );

foreach ( array( 'cartonpak_enamad_code', 'cartonpak_lazy_images' ) as $name ) {
	if ( ! preg_match( '/\nfunction ' . $name . '\(.*?\n\}/s', $source, $m ) ) {
		printf( "FAIL تابع %s در قالب پیدا نشد\n", $name );
		exit( 1 );
	}
	eval( $m[0] );
}

/* ---------- بیرون کشیدن Code ---------- */

$real = 'https://trustseal.enamad.ir/?id=7916201&Code=724T9byrQckUo9nPrvs8okifRlCaoDB0';

check( 'کد از نشانی واقعی اینماد', cartonpak_enamad_code( $real ), '724T9byrQckUo9nPrvs8okifRlCaoDB0' );
check( 'حرف کوچک code هم شناخته می‌شود', cartonpak_enamad_code( 'https://x.ir/?id=1&code=ABC' ), 'ABC' );
check( 'ترتیب پارامترها مهم نیست', cartonpak_enamad_code( 'https://x.ir/?Code=ABC&id=1' ), 'ABC' );
check( 'نشانی بدون Code خالی برمی‌گرداند', cartonpak_enamad_code( 'https://x.ir/?id=1' ), '' );
check( 'نشانی بدون پارامتر خالی برمی‌گرداند', cartonpak_enamad_code( 'https://x.ir/' ), '' );
check( 'رشتهٔ خالی خالی برمی‌گرداند', cartonpak_enamad_code( '' ), '' );

/* ---------- تزریق loading=lazy ---------- */

$snippet = "<a referrerpolicy='origin' target='_blank' href='https://trustseal.enamad.ir/?id=7916201&Code=724T9byrQckUo9nPrvs8okifRlCaoDB0'><img referrerpolicy='origin' src='https://trustseal.enamad.ir/logo.aspx?id=7916201&Code=724T9byrQckUo9nPrvs8okifRlCaoDB0' alt='' style='cursor:pointer' code='724T9byrQckUo9nPrvs8okifRlCaoDB0'></a>";

$lazy = cartonpak_lazy_images( $snippet );

check( 'loading=lazy اضافه شد', substr_count( $lazy, 'loading="lazy"' ), 1 );
check( 'decoding=async اضافه شد', substr_count( $lazy, 'decoding="async"' ), 1 );
check( 'صفت code اینماد دست‌نخورده ماند', false !== strpos( $lazy, "code='724T9byrQckUo9nPrvs8okifRlCaoDB0'" ), true );
check( 'نشانی تصویر دست‌نخورده ماند', false !== strpos( $lazy, 'logo.aspx?id=7916201' ), true );
check( 'لنگر دست‌نخورده ماند', false !== strpos( $lazy, "target='_blank'" ), true );

// دوبار اجرا نباید دو تا loading بگذارد — ذخیرهٔ دوبارهٔ تنظیمات عادی است.
check( 'اجرای دوباره صفت تکراری نمی‌سازد', substr_count( cartonpak_lazy_images( $lazy ), 'loading=' ), 1 );

// کدی که خودش loading دارد نباید دست بخورد.
$already = '<img loading="eager" src="a.png">';
check( 'loading موجود عوض نمی‌شود', cartonpak_lazy_images( $already ), $already );

// کد ساماندهی با onclick و چند تصویر.
$multi = '<a href="#" onclick="window.open(\'https://logo.samandehi.ir/Verify.aspx?id=1\')"><img src="a.png"><img src="b.png"></a>';
check( 'چند تصویر، هر دو lazy می‌شوند', substr_count( cartonpak_lazy_images( $multi ), 'loading="lazy"' ), 2 );
check( 'onclick ساماندهی دست‌نخورده ماند', false !== strpos( cartonpak_lazy_images( $multi ), 'Verify.aspx?id=1' ), true );

// کدی بدون تصویر (مثلاً فقط اسکریپت) نباید تغییر کند.
$script = '<script src="https://logo.samandehi.ir/logo.js"></script>';
check( 'کد بدون تصویر تغییر نمی‌کند', cartonpak_lazy_images( $script ), $script );
check( 'رشتهٔ خالی خالی می‌ماند', cartonpak_lazy_images( '   ' ), '' );

echo $failures ? "\n{$failures} آزمون شکست خورد.\n" : "\nهمهٔ آزمون‌ها موفق بودند.\n";
exit( $failures ? 1 : 0 );
