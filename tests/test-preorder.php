<?php
/**
 * آزمون افزونهٔ مدیریت پیش‌فروش.
 *
 * بخش‌هایی که به دیتابیس وردپرس وابسته نیستند اینجا سنجیده می‌شوند: شمارهٔ
 * موبایل، تاریخ شمسی، خط لولهٔ وضعیت، پاک‌سازی تنظیمات، نوشتن CSV و — مهم‌تر
 * از همه — اینکه ستون‌های پیش‌فروش واقعاً در فایل کاتالوگ شناخته می‌شوند.
 */

require __DIR__ . '/wp-stubs.php';

define( 'PPO_VERSION', 'test' );
define( 'PPO_PATH', __DIR__ . '/../plugins/parsian-preorder/' );
define( 'PPO_URL', 'https://example.test/ppo/' );

$ppo = __DIR__ . '/../plugins/parsian-preorder/includes/';
foreach ( array( 'helpers', 'class-ppo-status', 'class-ppo-settings', 'class-ppo-post-type', 'class-ppo-product', 'class-ppo-export', 'class-ppo-catalog-sync' ) as $class ) {
	require $ppo . $class . '.php';
}

$pcs = __DIR__ . '/../plugins/parsian-catalog-sync/includes/';
foreach ( array( 'helpers', 'class-pcs-fields', 'class-pcs-spreadsheet', 'class-pcs-mapper' ) as $class ) {
	require $pcs . $class . '.php';
}

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

/* ---------------------------- شمارهٔ موبایل ---------------------------- */

check( 'صفر ابتدایی', ppo_normalize_phone( '09123456789' ), '+989123456789' );
check( 'ارقام فارسی', ppo_normalize_phone( '۰۹۱۲۳۴۵۶۷۸۹' ), '+989123456789' );
check( 'با خط تیره و فاصله', ppo_normalize_phone( '0912-345 6789' ), '+989123456789' );
check( 'با پیش‌شمارهٔ کشور', ppo_normalize_phone( '989123456789' ), '+989123456789' );
check( 'با ۰۰۹۸', ppo_normalize_phone( '00989123456789' ), '+989123456789' );
check( 'بدون صفر', ppo_normalize_phone( '9123456789' ), '+989123456789' );
check( 'خالی', ppo_normalize_phone( '' ), '' );

check( 'شمارهٔ درست پذیرفته می‌شود', ppo_is_valid_phone( '+989123456789' ), true );
check( 'شمارهٔ ثابت رد می‌شود', ppo_is_valid_phone( ppo_normalize_phone( '02133445566' ) ), false );
check( 'شمارهٔ ناقص رد می‌شود', ppo_is_valid_phone( ppo_normalize_phone( '0912345' ) ), false );
check( 'نمایش با صفر', ppo_display_phone( '+989123456789' ), '09123456789' );

/* ------------------------------- تاریخ ------------------------------- */

check( 'شمسی → میلادی', ppo_jalali_to_gregorian( 1404, 6, 28 ), array( 2025, 9, 19 ) );
check( 'میلادی → شمسی', ppo_gregorian_to_jalali( 2025, 9, 19 ), array( 1404, 6, 28 ) );
check( 'آخر اسفند کبیسه', ppo_jalali_to_gregorian( 1399, 12, 30 ), array( 2021, 3, 20 ) );

$round_trip = 0;

for ( $year = 1395; $year <= 1412; $year++ ) {
	for ( $month = 1; $month <= 12; $month++ ) {
		$last = $month < 7 ? 31 : ( $month < 12 ? 30 : 29 );

		for ( $day = 1; $day <= $last; $day++ ) {
			list( $gy, $gm, $gd ) = ppo_jalali_to_gregorian( $year, $month, $day );

			if ( ppo_gregorian_to_jalali( $gy, $gm, $gd ) !== array( $year, $month, $day ) ) {
				$round_trip++;
			}
		}
	}
}

check( 'رفت‌وبرگشت تاریخ در ۱۸ سال', $round_trip, 0 );

check( 'خواندن تاریخ شمسی', ppo_parse_date( '۱۴۰۴/۰۶/۲۸' ), '2025-09-19' );
check( 'خواندن تاریخ شمسی با خط تیره', ppo_parse_date( '1404-06-28' ), '2025-09-19' );
check( 'خواندن تاریخ میلادی', ppo_parse_date( '2025-09-19' ), '2025-09-19' );
check( 'تاریخ نامعتبر', ppo_parse_date( 'فردا' ), '' );
check( 'روز ناموجود', ppo_parse_date( '2025-02-31' ), '' );
check( 'نمایش شمسی', ppo_jalali_date( '2025-09-19 14:30:00' ), '۱۴۰۴/۰۶/۲۸' );
check( 'نمایش شمسی با ساعت', ppo_jalali_date( '2025-09-19 14:30:00', true ), '۱۴۰۴/۰۶/۲۸ ۱۴:۳۰' );
check( 'نام ماه', ppo_jalali_month_name( 8 ), 'آبان' );

/* ------------------------------ وضعیت‌ها ------------------------------ */

check( 'وضعیت جدید وجود دارد', PPO_Status::exists( 'ppo-new' ), true );
check( 'وضعیت ساختگی وجود ندارد', PPO_Status::exists( 'ppo-nope' ), false );
check( 'تبدیل‌شده، باز نیست', PPO_Status::is_open( PPO_Status::CONVERTED ), false );
check( 'لغوشده، باز نیست', PPO_Status::is_open( PPO_Status::CANCELLED ), false );
check( 'جدید، باز است', PPO_Status::is_open( PPO_Status::NEW_REQUEST ), true );
check( 'تعداد وضعیت‌های باز', count( PPO_Status::open_keys() ), 6 );
check( 'برچسب فارسی', PPO_Status::label( 'ppo-production' ), 'در حال تولید' );

// هیچ کلید وضعیتی نباید بلندتر از سقف وردپرس (۲۰ نویسه) باشد.
$too_long = array();

foreach ( PPO_Status::keys() as $key ) {
	if ( strlen( $key ) > 20 ) {
		$too_long[] = $key;
	}
}

check( 'کلید وضعیت‌ها کوتاه‌تر از سقف وردپرس', $too_long, array() );

/* ------------------------------ تنظیمات ------------------------------ */

$settings = PPO_Settings::instance();

$settings->save(
	array(
		'duplicate_window' => '۱۲',
		'stale_days'       => '-4',
		'ask_company'      => '1',
		'ask_city'         => '',
		'badge_color'      => 'قرمز',
		'admin_phone'      => '۰۹۱۲۳۴۵۶۷۸۹',
		'email_to'         => 'a@example.com، b@example.com, نامعتبر',
		'initial_status'   => 'ppo-contacted',
		'status_templates' => array(
			'ppo-ready' => 'readytpl',
			'ppo-fake'  => 'nope',
		),
	)
);

check( 'ارقام فارسی در عدد', $settings->get( 'duplicate_window' ), 12 );
check( 'عدد منفی به صفر می‌رسد', $settings->get( 'stale_days' ), 0 );
check( 'تیک روشن', $settings->get( 'ask_company' ), 1 );
check( 'تیک خاموش', $settings->get( 'ask_city' ), 0 );
check( 'رنگ نامعتبر به پیش‌فرض برمی‌گردد', $settings->get( 'badge_color' ), '#d97706' );
check( 'تلفن یکدست می‌شود', $settings->get( 'admin_phone' ), '+989123456789' );
check( 'ایمیل نامعتبر کنار گذاشته می‌شود', $settings->get( 'email_to' ), 'a@example.com, b@example.com' );
check( 'وضعیت اولیه', $settings->get( 'initial_status' ), 'ppo-contacted' );
check( 'قالب پیامک وضعیت ساختگی رد می‌شود', $settings->get( 'status_templates' ), array( 'ppo-ready' => 'readytpl' ) );

// مقداری که فرستاده نشده باشد نباید پاک شود.
$settings->save( array( 'duplicate_window' => 3 ) );
check( 'مقدار فرستاده‌نشده دست‌نخورده می‌ماند', $settings->get( 'initial_status' ), 'ppo-contacted' );

/* -------------------- ستون‌های پیش‌فروش در فایل کاتالوگ -------------------- */

add_filter( 'pcs_custom_fields', array( 'PPO_Catalog_Sync', 'register_fields' ) );
PCS_Fields::flush();

$fields = PCS_Fields::all();
check( 'شش ستون پیش‌فروش ثبت شد', count( $fields ), 6 );

$mapping = PCS_Mapper::build(
	array(
		'کد محصول',
		'قیمت',
		'پیش‌فروش',
		'زمان آماده‌سازی (روز کاری)',
		'تاریخ عرضه',
		'ظرفیت پیش‌فروش',
		'حداقل تیراژ',
		'پیش‌پرداخت (درصد)',
	)
);

check( 'هیچ ستون پیش‌فروشی ناشناخته نماند', $mapping['unknown'], array() );
check( 'ستون پیش‌فروش', $mapping['fields']['پیش‌فروش'], 'custom:preorder' );
check( 'ستون تاریخ عرضه', $mapping['fields']['تاریخ عرضه'], 'custom:preorder_release' );
check( 'ستون ظرفیت', $mapping['fields']['ظرفیت پیش‌فروش'], 'custom:preorder_capacity' );

// کلید متا باید همان کلید افزونهٔ قدیمی بماند تا محصولات فعلی از دست نروند.
check( 'کلید متای پیش‌فروش', $fields['preorder']['meta_key'], '_cartonpak_preorder' );
check( 'کلید متای زمان آماده‌سازی', $fields['preorder_lead']['meta_key'], '_cartonpak_lead_days' );

// خواندن مقدار از فایل و نوشتن مقدار در فایل.
check( 'بله → yes', PCS_Fields::to_store( $fields['preorder'], 'بله' ), 'yes' );
check( 'yes → بله', PCS_Fields::to_file( $fields['preorder'], 'yes' ), 'بله' );
check( 'تاریخ شمسی فایل → میلادی متا', PCS_Fields::to_store( $fields['preorder_release'], '۱۴۰۴/۰۸/۰۱' ), '2025-10-23' );
check( 'تاریخ میلادی متا → شمسی فایل', PCS_Fields::to_file( $fields['preorder_release'], '2025-10-23' ), '۱۴۰۴/۰۸/۰۱' );
check( 'ظرفیت با ارقام فارسی', PCS_Fields::to_store( $fields['preorder_capacity'], '۲٬۵۰۰' ), 2500 );

/* ------------------------------ خروجی CSV ------------------------------ */

check( 'سطر با CRLF بسته می‌شود', PPO_Export::line( array( 'a', 'b' ) ), "a,b\r\n" );
check( 'سلول کامادار نقل‌قول می‌گیرد', PPO_Export::line( array( 'x,y' ) ), "\"x,y\"\r\n" );
check( 'نقل‌قول دوبرابر می‌شود', PPO_Export::line( array( 'a"b' ) ), "\"a\"\"b\"\r\n" );
check( 'فرمول خنثی می‌شود', PPO_Export::escape_cell( '=cmd|calc' ), "'=cmd|calc" );
check( 'عدد منفی دست‌نخورده', PPO_Export::escape_cell( '-500' ), '-500' );
check( 'تعداد سرستون‌ها', count( PPO_Export::headers() ), 18 );

echo "\n";

if ( $failures ) {
	printf( "%d آزمون شکست خورد.\n", $failures );
	exit( 1 );
}

echo "همهٔ آزمون‌ها موفق بودند.\n";
