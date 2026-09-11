<?php
/**
 * آزمون یکپارچه: قالب اکسلِ تحویل‌داده‌شده باید با خوانندهٔ افزونه کار کند
 * و همهٔ ستون‌هایش شناخته شوند.
 */

require __DIR__ . '/wp-stubs.php';

$base = __DIR__ . '/../plugins/parsian-catalog-sync/includes/';
require $base . 'class-pcs-spreadsheet.php';
require $base . 'class-pcs-mapper.php';
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

$template = __DIR__ . '/../docs/قالب-محصولات.xlsx';

check( 'قالب — برگه‌ها', PCS_Spreadsheet::sheet_names( $template ), array( 'محصولات', 'راهنما' ) );

$data = PCS_Spreadsheet::read( $template );

if ( is_wp_error( $data ) ) {
	echo 'FAIL خواندن قالب: ' . $data->get_error_message() . "\n";
	exit( 1 );
}

check( 'قالب — تعداد سطر نمونه', count( $data['rows'] ), 3 );

$mapping = PCS_Mapper::build( $data['headers'] );

// هیچ ستونی از قالب رسمی نباید ناشناخته بماند.
check( 'قالب — ستون ناشناخته ندارد', $mapping['unknown'], array() );
check( 'قالب — ستون کلید', in_array( 'sku', $mapping['fields'], true ), true );
check( 'قالب — سه ستون ویژگی', count( $mapping['attributes'] ), 3 );
check(
	'قالب — برچسب ویژگی‌ها',
	array_values( $mapping['attributes'] ),
	array( 'سایز', 'تعداد لایه', 'نوع چاپ' )
);

// نقشهٔ تغییرات روی فروشگاه خالی: هر سه سطر باید «محصول جدید» باشند.
$plan = PCS_Sync::plan( $template );

if ( is_wp_error( $plan ) ) {
	echo 'FAIL ساخت نقشه از قالب: ' . $plan->get_error_message() . "\n";
	exit( 1 );
}

check( 'قالب — همه جدید', $plan['summary']['create'], 3 );
check( 'قالب — بدون خطا', $plan['summary']['error'], 0 );

$first = $plan['rows'][0];
check( 'قالب — کد سطر اول', $first['sku'], 'PC-01' );
check( 'قالب — نام سطر اول', $first['name'], 'کارتن پستی سایز ۱' );
// ۸۵۰۰ تومان → بدون قالب cartonpak، واحد دیتابیس هم تومان است، پس بدون تبدیل.
check( 'قالب — قیمت سطر اول', $first['values']['regular_price'], '8500' );
check( 'قالب — دستهٔ سطر اول', $first['values']['categories'], array( 'کارتن پستی' ) );
check( 'قالب — دستهٔ سلسله‌مراتبی سطر سوم', $plan['rows'][2]['values']['categories'], array( 'کارتن اسباب‌کشی>۵ لایه' ) );
check( 'قالب — ویژگی سطر سوم', $plan['rows'][2]['attributes']['تعداد لایه'], array( '۵ لایه' ) );
check( 'قالب — وضعیت موجودی سطر اول', $first['values']['stock_status'], 'instock' );
check( 'قالب — وضعیت انتشار سطر اول', $first['values']['status'], 'publish' );
check( 'قالب — موجودی سطر اول', $first['values']['stock_quantity'], 120 );
check( 'قالب — وزن سطر اول', $first['values']['weight'], '0.12' );

echo $failures ? "\n{$failures} آزمون شکست خورد.\n" : "\nهمهٔ آزمون‌ها موفق بودند.\n";
exit( $failures ? 1 : 0 );
