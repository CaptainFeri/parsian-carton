<?php
/**
 * آزمون نگاشت ستون‌ها و ساخت نقشهٔ تغییرات.
 */

require __DIR__ . '/wp-stubs.php';

$base = __DIR__ . '/../plugins/parsian-catalog-sync/includes/';
require $base . 'class-pcs-spreadsheet.php';
require $base . 'class-pcs-mapper.php';
require $base . 'class-pcs-media.php';
require $base . 'class-pcs-settings.php';
require $base . 'class-pcs-sync.php';

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

/* ============================ نگاشت ستون‌ها ============================ */

$mapping = PCS_Mapper::build(
	array( 'کد محصول', 'نام محصول', 'قیمت', 'قیمت حراج', 'موجودی', 'دسته بندی', 'تصویر', 'ویژگی: سایز', 'ستون بی‌ربط' )
);

check( 'نگاشت — کد محصول', $mapping['fields']['کد محصول'], 'sku' );
check( 'نگاشت — قیمت', $mapping['fields']['قیمت'], 'regular_price' );
check( 'نگاشت — قیمت حراج', $mapping['fields']['قیمت حراج'], 'sale_price' );
check( 'نگاشت — دسته‌بندی', $mapping['fields']['دسته بندی'], 'categories' );
check( 'نگاشت — ویژگی', $mapping['attributes']['ویژگی: سایز'], 'سایز' );
check( 'نگاشت — ستون ناشناخته', $mapping['unknown'], array( 'ستون بی‌ربط' ) );

// نگارش‌های جایگزین سرستون باید همان فیلد را بدهند.
$alt = PCS_Mapper::build( array( 'SKU', 'عنوان محصول', 'Price', 'انبار' ) );
check( 'نگاشت — SKU انگلیسی', $alt['fields']['SKU'], 'sku' );
check( 'نگاشت — «عنوان محصول»', $alt['fields']['عنوان محصول'], 'name' );
check( 'نگاشت — «Price»', $alt['fields']['Price'], 'regular_price' );
check( 'نگاشت — «انبار»', $alt['fields']['انبار'], 'stock_quantity' );

/* ============================ خواندن مقادیر ============================ */

check( 'عدد — ارقام فارسی با جداکننده', PCS_Mapper::number( '۴۵٬۰۰۰' ), 45000.0 );
check( 'عدد — جداکنندهٔ لاتین', PCS_Mapper::number( '1,250,000' ), 1250000.0 );
check( 'عدد — همراه واحد', PCS_Mapper::number( '8500 تومان' ), 8500.0 );
check( 'عدد — اعشاری', PCS_Mapper::number( '2.5' ), 2.5 );
check( 'عدد — خالی', PCS_Mapper::number( '' ), null );
check( 'عدد — نامعتبر', PCS_Mapper::number( 'ندارد' ), null );
check( 'عدد — صفر', PCS_Mapper::number( '0' ), 0.0 );

check( 'بولی — بله', PCS_Mapper::boolean( 'بله' ), true );
check( 'بولی — خیر', PCS_Mapper::boolean( 'خیر' ), false );
check( 'بولی — ناشناخته', PCS_Mapper::boolean( 'شاید' ), null );

check( 'موجودی — موجود', PCS_Mapper::stock_status( 'موجود' ), 'instock' );
check( 'موجودی — ناموجود', PCS_Mapper::stock_status( 'ناموجود' ), 'outofstock' );
check( 'موجودی — ناشناخته', PCS_Mapper::stock_status( 'نامعلوم' ), null );

check( 'انتشار — منتشر', PCS_Mapper::post_status( 'منتشر' ), 'publish' );
check( 'انتشار — پیش‌نویس', PCS_Mapper::post_status( 'پیش‌نویس' ), 'draft' );

check( 'تفکیک — کامای فارسی', PCS_Mapper::split( 'کارتن پستی، اسباب‌کشی' ), array( 'کارتن پستی', 'اسباب‌کشی' ) );
check( 'تفکیک — خط عمودی', PCS_Mapper::split( 'a|b|c' ), array( 'a', 'b', 'c' ) );
check( 'تفکیک — مقدارهای خالی حذف می‌شوند', PCS_Mapper::split( 'a,,b,' ), array( 'a', 'b' ) );

/* ============================ نقشهٔ تغییرات ============================ */

// قیمت‌های دیتابیس ریالی‌اند (قالب cartonpak) و فایل تومانی است.
eval( 'function cartonpak_rial_to_toman( $p ) { return $p / 10; }' );

$settings = PCS_Settings::instance();
check( 'واحد دیتابیس تشخیص داده شد', $settings->store_unit(), 'rial' );
check( 'ضریب تبدیل تومان → ریال', $settings->price_multiplier(), 10.0 );
check( 'تبدیل قیمت', $settings->to_store_price( 8500 ), 85000.0 );

// محصول موجود: قیمت ۸۵۰۰ تومان (۸۵۰۰۰ ریال)، موجودی ۱۲۰.
pcs_test_add_product(
	101,
	array(
		'sku' => 'PC-01', 'name' => 'کارتن پستی سایز ۱',
		'regular_price' => '85000', 'sale_price' => '', 'stock_quantity' => 120,
		'stock_status' => 'instock', 'status' => 'publish',
	),
	array( 'product_cat' => array( 'کارتن پستی' ) )
);

// محصولی که مقادیرش دقیقاً با فایل یکی است — باید «بدون تغییر» بماند (ایدم‌پوتنت بودن).
pcs_test_add_product(
	103,
	array(
		'sku' => 'PC-03', 'name' => 'کارتن پستی سایز ۳',
		'regular_price' => '120000', 'sale_price' => '', 'stock_quantity' => 10,
		'stock_status' => 'instock', 'status' => 'publish',
	)
);

// محصولی که در فایل نخواهد بود.
pcs_test_add_product( 102, array( 'sku' => 'OLD-99', 'name' => 'محصول قدیمی' ) );

$plan = PCS_Sync::plan( __DIR__ . '/fixtures-plan.csv' );

if ( is_wp_error( $plan ) ) {
	echo 'FAIL ساخت نقشه: ' . $plan->get_error_message() . "\n";
	exit( 1 );
}

$rows = array();
foreach ( $plan['rows'] as $row ) {
	$rows[ $row['sku'] ] = $row;
}

check( 'خلاصه — جدید', $plan['summary']['create'], 1 );
check( 'خلاصه — به‌روزرسانی', $plan['summary']['update'], 1 );
check( 'خلاصه — بدون تغییر', $plan['summary']['unchanged'], 1 );
check( 'خلاصه — خطا', $plan['summary']['error'], 1 );

// PC-01: قیمت از ۸۵۰۰ به ۹۰۰۰ تومان → ۹۰۰۰۰ ریال.
check( 'PC-01 — عملیات', $rows['PC-01']['action'], 'update' );
check( 'PC-01 — قیمت قدیم (ریال)', $rows['PC-01']['changes']['regular_price']['from'], '85000' );
check( 'PC-01 — قیمت جدید (ریال)', $rows['PC-01']['changes']['regular_price']['to'], '90000' );
check( 'PC-01 — موجودی تغییر نکرده', isset( $rows['PC-01']['changes']['stock_quantity'] ), false );
check( 'PC-01 — شناسهٔ محصول', $rows['PC-01']['product_id'], 101 );

// PC-02: محصول تازه.
check( 'PC-02 — عملیات', $rows['PC-02']['action'], 'create' );
check( 'PC-02 — شناسهٔ محصول صفر', $rows['PC-02']['product_id'], 0 );
// صفت‌ها به‌صورت پیش‌فرض وارد نمی‌شوند؛ روی محصول متغیر، بازنویسی صفت‌ها
// پیوند واریاسیون‌ها را می‌شکند، پس این کار باید آگاهانه روشن شود.
check( 'PC-02 — صفت‌ها به‌صورت پیش‌فرض نادیده گرفته می‌شوند', $rows['PC-02']['attributes'], array() );

// PC-03: همان مقادیر فعلی → بدون تغییر (ایدم‌پوتنت بودن).
check( 'PC-03 — بدون تغییر', $rows['PC-03']['action'], 'unchanged' );
check( 'PC-03 — بدون تفاوت', $rows['PC-03']['changes'], array() );

// سطر بدون کد محصول → خطا.
$error_rows = array_values( array_filter( $plan['rows'], static function ( $row ) { return 'error' === $row['action']; } ) );
check( 'سطر بدون کد — خطا دارد', count( $error_rows[0]['errors'] ) > 0, true );

/* ---------- صفت‌ها با روشن بودن تنظیم ---------- */

$GLOBALS['pcs_options']['pcs_settings'] = array( 'import_attributes' => 1 );
PCS_Settings::instance()->flush();

$with_attrs = PCS_Sync::plan( __DIR__ . '/fixtures-plan.csv' );
$attr_rows  = array();
foreach ( $with_attrs['rows'] as $row ) {
	$attr_rows[ $row['sku'] ] = $row;
}

check( 'با روشن بودن تنظیم، صفت خوانده می‌شود', $attr_rows['PC-02']['attributes']['سایز'], array( 'سایز ۲' ) );

$GLOBALS['pcs_options']['pcs_settings'] = array();
PCS_Settings::instance()->flush();

// محصول غایب از فایل.
check( 'غایب — تعداد', count( $plan['missing'] ), 1 );
check( 'غایب — کد', $plan['missing'][0]['sku'], 'OLD-99' );

echo $failures ? "\n{$failures} آزمون شکست خورد.\n" : "\nهمهٔ آزمون‌ها موفق بودند.\n";
exit( $failures ? 1 : 0 );
