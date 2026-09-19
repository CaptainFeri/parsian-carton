<?php
/**
 * آزمون برون‌ریزی CSV.
 *
 * مهم‌ترین ادعا، همان ادعای آزمون خروجی ووکامرس است، این بار دربارهٔ فایلی که
 * خود این افزونه می‌سازد: **خروجی بگیر، بدون ویرایش برگردان، هیچ تغییری نباید
 * گزارش شود.** اگر این برقرار نباشد هر همگام‌سازی داده‌ها را بی‌دلیل بازنویسی
 * می‌کند و پیش‌نمایش بی‌فایده می‌شود.
 */

require __DIR__ . '/wp-stubs.php';

$base = __DIR__ . '/../plugins/parsian-catalog-sync/includes/';
foreach ( array( 'helpers', 'class-pcs-fields', 'class-pcs-content', 'class-pcs-spreadsheet', 'class-pcs-mapper', 'class-pcs-media', 'class-pcs-settings', 'class-pcs-sync', 'class-pcs-exporter' ) as $class ) {
	require $base . $class . '.php';
}

// قالب فعال: قیمت‌ها در دیتابیس ریالی‌اند و فایل تومانی نوشته می‌شود.
eval( 'function cartonpak_rial_to_toman( $p ) { return $p / 10; }' );

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

/* ------------------------- فروشگاه شبیه‌سازی‌شده ------------------------- */

pcs_test_add_attachment( 900001, 'https://parsiancarton.com/wp-content/uploads/postal-1.jpg' );
pcs_test_add_attachment( 900002, 'https://parsiancarton.com/wp-content/uploads/postal-1-b.jpg' );

// دستهٔ تودرتو: «کارتن اسباب‌کشی > ۵ لایه».
pcs_test_add_term( 'product_cat', 'کارتن پستی' );
pcs_test_add_term( 'product_cat', 'کارتن اسباب‌کشی' );
pcs_test_add_term( 'product_cat', '۵ لایه', 'کارتن اسباب‌کشی' );

pcs_test_add_product(
	11,
	array(
		'name'              => 'کارتن پستی کد ۱',
		'sku'               => 'postal-1',
		'regular_price'     => '250000',
		'sale_price'        => '225000',
		'stock_quantity'    => 480,
		'stock_status'      => 'instock',
		'status'            => 'publish',
		'short_description' => '<p>کارتن پستی سه‌لایه.</p>',
		'description'       => "<p>ابعاد ۱۵×۱۰×۱۰ سانتی‌متر.</p>\n<p>مقاوم و سبک.</p>",
		'image_id'          => 900001,
		'gallery'           => array( 900002 ),
		'weight'            => '0.12',
	),
	array( 'product_cat' => array( 'کارتن پستی' ) )
);

pcs_test_add_product(
	12,
	array(
		'name'           => 'کارتن اسباب‌کشی بزرگ',
		'sku'            => 'moving-large',
		'regular_price'  => '1200000',
		'stock_quantity' => null,
		'stock_status'   => 'outofstock',
		'status'         => 'draft',
		'description'    => '',
	),
	array( 'product_cat' => array( '۵ لایه' ) )
);

// محصولی با کاما، نقل‌قول و خط جدید در نام — سخت‌ترین حالت نوشتن CSV.
pcs_test_add_product(
	13,
	array(
		'name'          => 'کارتن "ویژه"، دولایه' . "\n" . 'سفارشی',
		'sku'           => 'special-2',
		'regular_price' => '90000',
		'stock_status'  => 'instock',
		'status'        => 'publish',
	)
);

/* ---------------------------- سرستون‌ها ---------------------------- */

$csv = PCS_Exporter::to_csv( array( 'extras' => 1 ) );

$path = tempnam( sys_get_temp_dir(), 'pcs' ) . '.csv';
file_put_contents( $path, $csv );

$read = PCS_Spreadsheet::read( $path );
check( 'فایل خروجی خوانده می‌شود', is_wp_error( $read ), false );

$mapping = PCS_Mapper::build( $read['headers'] );
check( 'هیچ سرستونی ناشناخته نیست', $mapping['unknown'], array() );
check( 'ستون کلید در خروجی هست', in_array( 'sku', $mapping['fields'], true ), true );
check( 'ستون شناسه در خروجی هست', in_array( 'id', $mapping['fields'], true ), true );
check( 'تعداد سطرها', count( $read['rows'] ), 3 );

/* ------------------------ رفت‌وبرگشت بدون تغییر ------------------------ */

$plan = PCS_Sync::plan( $path );
check( 'نقشه ساخته شد', is_wp_error( $plan ), false );

$errors = array();
foreach ( $plan['rows'] as $row ) {
	if ( 'unchanged' !== $row['action'] ) {
		$errors[ $row['sku'] ] = array( $row['action'], $row['changes'], $row['errors'] );
	}
}

check( 'همهٔ سطرها بدون تغییر', $errors, array() );
check( 'هیچ محصولی غایب شمرده نمی‌شود', $plan['missing'], array() );
check( 'هیچ محصولی ساخته نمی‌شود', $plan['summary']['create'], 0 );
check( 'هیچ سطری خطا ندارد', $plan['summary']['error'], 0 );

/* --------------------------- مقدار ستون‌ها --------------------------- */

$by_sku = array();
foreach ( $read['rows'] as $record ) {
	$by_sku[ $record['کد محصول'] ] = $record;
}

check( 'قیمت به تومان نوشته می‌شود', $by_sku['postal-1']['قیمت'], '25000' );
check( 'قیمت حراج به تومان نوشته می‌شود', $by_sku['postal-1']['قیمت حراج'], '22500' );
check( 'موجودی', $by_sku['postal-1']['موجودی'], '480' );
check( 'وضعیت موجودی فارسی', $by_sku['postal-1']['وضعیت موجودی'], 'موجود' );
check( 'وضعیت انتشار فارسی', $by_sku['moving-large']['وضعیت انتشار'], 'پیش‌نویس' );
check( 'نوع فارسی', $by_sku['postal-1']['نوع'], 'ساده' );
check( 'دستهٔ تودرتو با «>» نوشته می‌شود', $by_sku['moving-large']['دسته بندی'], 'کارتن اسباب‌کشی>۵ لایه' );
check( 'موجودی مدیریت‌نشده خالی می‌ماند', $by_sku['moving-large']['موجودی'], '' );
check( 'تصویر شاخص و گالری در یک ستون', $by_sku['postal-1']['تصاویر'], 'https://parsiancarton.com/wp-content/uploads/postal-1.jpg، https://parsiancarton.com/wp-content/uploads/postal-1-b.jpg' );
check( 'نام با کاما و نقل‌قول و خط جدید سالم برمی‌گردد', $by_sku['special-2']['نام محصول'], 'کارتن "ویژه"، دولایه' . "\n" . 'سفارشی' );

/* --------------------- یکدست‌سازی یک‌بارهٔ متن ساده --------------------- */

// توضیحی که در دیتابیس متن ساده است (بدون تگ) در نخستین همگام‌سازی به HTML
// تبدیل می‌شود — این «تغییر» درست است و فقط یک بار رخ می‌دهد. بعد از آن،
// رفت‌وبرگشت پایدار است. آزمون هر دو گام را می‌سنجد تا رفتار مستند بماند.
$plain = pcs_test_add_product(
	14,
	array(
		'name'          => 'کارتن ساده',
		'sku'           => 'plain-1',
		'regular_price' => '50000',
		'description'   => 'خط اول',
		'status'        => 'publish',
	)
);

$plain_path = tempnam( sys_get_temp_dir(), 'pcs' ) . '.csv';
file_put_contents( $plain_path, PCS_Exporter::to_csv( array() ) );

$first = PCS_Sync::plan( $plain_path );
$plain_row = null;

foreach ( $first['rows'] as $row ) {
	if ( 'plain-1' === $row['sku'] ) {
		$plain_row = $row;
	}
}

check( 'متن ساده در گام اول به HTML تبدیل می‌شود', $plain_row['changes']['description']['to'], '<p>خط اول</p>' );

// گام دوم: همان تبدیل روی محصول اعمال و دوباره خروجی گرفته می‌شود.
$plain->data['description'] = '<p>خط اول</p>';
file_put_contents( $plain_path, PCS_Exporter::to_csv( array() ) );

$second      = PCS_Sync::plan( $plain_path );
$plain_again = array();

foreach ( $second['rows'] as $row ) {
	if ( 'unchanged' !== $row['action'] ) {
		$plain_again[ $row['sku'] ] = $row['changes'];
	}
}

check( 'گام دوم دیگر تغییری ندارد', $plain_again, array() );

unlink( $plain_path );

/* ---------------------------- ستون‌های دلخواه ---------------------------- */

$minimal = PCS_Exporter::to_csv(
	array(
		'descriptions' => 0,
		'images'       => 0,
		'attributes'   => 0,
		'extras'       => 0,
	)
);

check( 'بدون ستون توضیحات', false === strpos( $minimal, 'توضیحات' ), true );
check( 'بدون ستون تصاویر', false === strpos( $minimal, 'تصاویر' ), true );
check( 'ستون قیمت همیشه هست', false !== strpos( $minimal, 'قیمت' ), true );

/* -------------------------- خنثی‌سازی فرمول -------------------------- */

check( 'سلول فرمول‌دار خنثی می‌شود', PCS_Exporter::escape_cell( '=1+1' ), "'=1+1" );
check( 'سلول با @ خنثی می‌شود', PCS_Exporter::escape_cell( '@SUM(A1)' ), "'@SUM(A1)" );
check( 'عدد منفی دست‌نخورده می‌ماند', PCS_Exporter::escape_cell( '-1200' ), '-1200' );
check( 'متن معمولی دست‌نخورده می‌ماند', PCS_Exporter::escape_cell( 'کارتن پستی' ), 'کارتن پستی' );

check( 'سطر CSV با CRLF بسته می‌شود', PCS_Exporter::csv_line( array( 'a', 'b' ) ), "a,b\r\n" );
check( 'سلول کامادار داخل نقل‌قول می‌رود', PCS_Exporter::csv_line( array( 'x,y' ) ), "\"x,y\"\r\n" );
check( 'نقل‌قول دوبرابر می‌شود', PCS_Exporter::csv_line( array( 'a"b' ) ), "\"a\"\"b\"\r\n" );

/* ----------------------------- قالب خالی ----------------------------- */

$template = PCS_Exporter::build( array( 'empty' => 1 ) );
check( 'قالب خالی سطر محصول ندارد', $template['rows'], array() );
check( 'قالب خالی سرستون دارد', in_array( 'کد محصول', $template['headers'], true ), true );

unlink( $path );

echo "\n";

if ( $failures ) {
	printf( "%d آزمون شکست خورد.\n", $failures );
	exit( 1 );
}

echo "همهٔ آزمون‌ها موفق بودند.\n";
