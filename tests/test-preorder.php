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
foreach ( array( 'helpers', 'class-ppo-status', 'class-ppo-settings', 'class-ppo-post-type', 'class-ppo-log', 'class-ppo-request', 'class-ppo-product', 'class-ppo-notifier', 'class-ppo-orders', 'class-ppo-export', 'class-ppo-catalog-sync' ) as $class ) {
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
check( 'ذخیرهٔ جزئی، تیک‌ها را خاموش نمی‌کند', $settings->get( 'sms_enabled' ), 1 );

// ولی ارسال کامل فرم، تیکِ نیامده را خاموش می‌کند (رفتار چک‌باکس در HTML).
$settings->save( array( 'ppo_form' => 1, 'sms_enabled' => 1 ) );
check( 'ارسال فرم، تیک نیامده را خاموش می‌کند', $settings->get( 'email_enabled' ), 0 );
check( 'ارسال فرم، تیک آمده را روشن نگه می‌دارد', $settings->get( 'sms_enabled' ), 1 );

$settings->save( array( 'ppo_form' => 1, 'sms_enabled' => 1, 'email_enabled' => 1 ) );

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

/* ------------------------ چرخهٔ یک درخواست ------------------------ */

// پیامک و ایمیل هم وصل می‌شوند تا مطمئن شویم نبودِ شبکه، ثبت درخواست را نمی‌شکند.
PPO_Notifier::init();

$product = pcs_test_add_product(
	77,
	array(
		'name'          => 'کارتن پستی کد ۳',
		'sku'           => 'postal-3',
		'regular_price' => '180000',
		'status'        => 'publish',
	)
);

$product->meta[ PPO_Product::META_ENABLED ]   = 'yes';
$product->meta[ PPO_Product::META_LEAD_DAYS ] = 7;
$product->meta[ PPO_Product::META_MIN_QTY ]   = 50;
$product->meta[ PPO_Product::META_DEPOSIT ]   = 40;
$product->meta[ PPO_Product::META_PRICE ]     = '150000';

check( 'محصول پیش‌فروش شناخته می‌شود', PPO_Product::is_preorder( 77 ), true );
check( 'زمان آماده‌سازی', PPO_Product::lead_days( 77 ), 7 );
check( 'حداقل تیراژ', PPO_Product::min_quantity( 77 ), 50 );
check( 'درصد پیش‌پرداخت', PPO_Product::deposit_percent( 77 ), 40 );
check( 'قیمت ویژه بر قیمت عادی مقدم است', PPO_Product::effective_price( 77 ), 150000.0 );

$product->meta[ PPO_Product::META_AUTO_END ] = 'yes';
$product->meta[ PPO_Product::META_RELEASE ]  = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
check( 'با گذشت تاریخ عرضه، پیش‌فروش خودکار بسته می‌شود', PPO_Product::is_preorder( 77 ), false );

$product->meta[ PPO_Product::META_AUTO_END ] = 'no';
$product->meta[ PPO_Product::META_RELEASE ]  = gmdate( 'Y-m-d', time() + 20 * DAY_IN_SECONDS );
check( 'بدون پایان خودکار، باز می‌ماند', PPO_Product::is_preorder( 77 ), true );

$request = PPO_Request::create(
	array(
		'product_id' => 77,
		'quantity'   => '۱۰۰',
		'phone'      => '۰۹۱۲۳۴۵۶۷۸۹',
		'name'       => 'علی رضایی',
		'company'    => 'بسته‌بندی سپهر',
	)
);

check( 'درخواست ساخته شد', is_wp_error( $request ), false );
check( 'تعداد با ارقام فارسی خوانده شد', $request->get( 'quantity' ), 100 );
check( 'شماره یکدست ذخیره شد', $request->get( 'phone' ), '+989123456789' );
check( 'قیمت واحد از محصول برداشته شد', (float) $request->get( 'unit_price' ), 150000.0 );
check( 'ارزش درخواست', $request->get_value(), 15000000.0 );
check( 'زمان آماده‌سازی در درخواست ثبت شد', $request->get( 'lead_days' ), 7 );
check( 'وضعیت اولیه', $request->get_status(), 'ppo-contacted' );

$bad = PPO_Request::create( array( 'product_id' => 77, 'phone' => '12345' ) );
check( 'شمارهٔ نامعتبر رد می‌شود', is_wp_error( $bad ), true );

$missing = PPO_Request::create( array( 'product_id' => 0, 'phone' => '09123456789' ) );
check( 'محصول ناموجود رد می‌شود', is_wp_error( $missing ), true );

// تغییر وضعیت باید در تاریخچه بنشیند.
$changed = $request->set_status( 'ppo-quoted', 'قیمت ۱۵۰ هزار تومان توافق شد.' );
check( 'وضعیت عوض شد', $changed, true );
check( 'وضعیت تازه', $request->get_status(), 'ppo-quoted' );
check( 'تغییر به همان وضعیت، تغییر شمرده نمی‌شود', $request->set_status( 'ppo-quoted' ), false );
check( 'وضعیت ساختگی پذیرفته نمی‌شود', $request->set_status( 'ppo-nope' ), false );

$history = PPO_Log::get( $request->get_id() );
$kinds   = wp_list_pluck( $history, 'kind' );

check( 'رویداد ثبت درخواست در تاریخچه هست', in_array( 'system', $kinds, true ), true );
check( 'تغییر وضعیت در تاریخچه هست', in_array( 'status', $kinds, true ), true );
check( 'یادداشت تغییر در تاریخچه هست', in_array( 'note', $kinds, true ), true );
check( 'نتیجهٔ پیامک در تاریخچه هست', in_array( 'sms', $kinds, true ), true );

// بدون کلید API نباید پیامکی برود، ولی درخواست باید سالم ثبت شده باشد.
check( 'وضعیت پیامک بدون کلید API', $request->get( 'sms_status' ), 'skipped' );

// سفارش‌سازی وقتی ووکامرس در دسترس نیست باید خطای روشن بدهد، نه خطای PHP.
$order = PPO_Orders::create_order( $request );
check( 'بدون ووکامرس، خطای روشن', is_wp_error( $order ), true );

/* ------------------------ سازگاری با نسخهٔ قدیمی ------------------------ */

// قالب سایت ممکن است هنوز این نام‌ها را صدا بزند؛ نبودشان یعنی خطای مرگبار.
require_once PPO_PATH . 'includes/class-ppo-frontend.php';

check( 'تابع قدیمی تشخیص پیش‌فروش', function_exists( 'parsian_preorder_is_product' ), true );
check( 'تابع قدیمی زمان ارسال', parsian_preorder_lead_days( 77 ), 7 );
check( 'تابع قدیمی نشان کارت', false !== strpos( parsian_preorder_render_card_badge( $product ), 'ppo-card-badge' ), true );
check( 'تابع قدیمی ارقام فارسی', parsian_preorder_fa_digits( '2025' ), '۲۰۲۵' );

/* ------------------------------ خروجی CSV ------------------------------ */

check( 'سطر با CRLF بسته می‌شود', PPO_Export::line( array( 'a', 'b' ) ), "a,b\r\n" );
check( 'سلول کامادار نقل‌قول می‌گیرد', PPO_Export::line( array( 'x,y' ) ), "\"x,y\"\r\n" );
check( 'نقل‌قول دوبرابر می‌شود', PPO_Export::line( array( 'a"b' ) ), "\"a\"\"b\"\r\n" );
check( 'فرمول خنثی می‌شود', PPO_Export::escape_cell( '=cmd|calc' ), "'=cmd|calc" );
check( 'عدد منفی دست‌نخورده', PPO_Export::escape_cell( '-500' ), '-500' );
check( 'تعداد سرستون‌ها', count( PPO_Export::headers() ), 18 );

$row = PPO_Export::row( $request );
check( 'ستون‌های سطر با سرستون‌ها می‌خوانند', count( $row ), count( PPO_Export::headers() ) );
check( 'وضعیت در سطر', $row[2], 'پیش‌فاکتور صادر شد' );
check( 'کد محصول در سطر', $row[4], 'postal-3' );
check( 'تلفن با صفر در سطر', $row[10], '09123456789' );

echo "\n";

if ( $failures ) {
	printf( "%d آزمون شکست خورد.\n", $failures );
	exit( 1 );
}

echo "همهٔ آزمون‌ها موفق بودند.\n";
