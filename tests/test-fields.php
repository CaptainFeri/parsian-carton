<?php
/**
 * آزمون ستون‌های سفارشی — نقطهٔ اتصال افزونه‌های دیگر (مثل پیش‌فروش) به فایل کاتالوگ.
 *
 * ادعا: یک افزونهٔ بیرونی با یک فیلتر، ستون تازه‌ای به کاتالوگ اضافه می‌کند و از
 * آن پس آن ستون هم در خروجی می‌آید، هم در پیش‌نمایش مقایسه می‌شود و هم موقع
 * اعمال روی متای محصول نوشته می‌شود.
 */

require __DIR__ . '/wp-stubs.php';

$base = __DIR__ . '/../plugins/parsian-catalog-sync/includes/';
foreach ( array( 'helpers', 'class-pcs-fields', 'class-pcs-content', 'class-pcs-spreadsheet', 'class-pcs-mapper', 'class-pcs-media', 'class-pcs-settings', 'class-pcs-sync', 'class-pcs-exporter' ) as $class ) {
	require $base . $class . '.php';
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

/* ---------------------------- تبدیل تاریخ ---------------------------- */

check( 'شمسی → میلادی', pcs_jalali_to_gregorian( 1404, 6, 28 ), array( 2025, 9, 19 ) );
check( 'میلادی → شمسی', pcs_gregorian_to_jalali( 2025, 9, 19 ), array( 1404, 6, 28 ) );
check( 'اول فروردین ۱۴۰۳', pcs_jalali_to_gregorian( 1403, 1, 1 ), array( 2024, 3, 20 ) );
check( 'سال کبیسه ۱۳۹۹/۱۲/۳۰', pcs_jalali_to_gregorian( 1399, 12, 30 ), array( 2021, 3, 20 ) );
check( 'نمایش شمسی', pcs_jalali_date( '2025-09-19' ), '۱۴۰۴/۰۶/۲۸' );

$round_trip_failures = 0;

for ( $year = 1395; $year <= 1410; $year++ ) {
	for ( $month = 1; $month <= 12; $month++ ) {
		$last = $month < 7 ? 31 : ( $month < 12 ? 30 : 29 );

		for ( $day = 1; $day <= $last; $day++ ) {
			list( $gy, $gm, $gd ) = pcs_jalali_to_gregorian( $year, $month, $day );

			if ( pcs_gregorian_to_jalali( $gy, $gm, $gd ) !== array( $year, $month, $day ) ) {
				$round_trip_failures++;
			}
		}
	}
}

check( 'رفت‌وبرگشت تاریخ در ۱۶ سال', $round_trip_failures, 0 );

/* ------------------------- ثبت ستون‌های تازه ------------------------- */

add_filter(
	'pcs_custom_fields',
	static function ( $fields ) {
		$fields['preorder'] = array(
			'label'    => 'پیش‌فروش',
			'aliases'  => array( 'پیش فروش', 'preorder' ),
			'meta_key' => '_cartonpak_preorder',
			'type'     => 'bool',
			'default'  => 'no',
		);

		$fields['lead_days'] = array(
			'label'    => 'زمان ارسال (روز کاری)',
			'meta_key' => '_cartonpak_lead_days',
			'type'     => 'int',
			'default'  => 0,
		);

		$fields['release'] = array(
			'label'    => 'تاریخ عرضه',
			'meta_key' => '_cartonpak_release_date',
			'type'     => 'date',
		);

		return $fields;
	}
);

PCS_Fields::flush();

check( 'سه ستون ثبت شد', array_keys( PCS_Fields::all() ), array( 'preorder', 'lead_days', 'release' ) );

$mapping = PCS_Mapper::build( array( 'کد محصول', 'قیمت', 'پیش‌فروش', 'زمان ارسال (روز کاری)', 'تاریخ عرضه' ) );

check( 'ستون پیش‌فروش شناخته شد', $mapping['fields']['پیش‌فروش'], 'custom:preorder' );
check( 'ستون زمان ارسال شناخته شد', $mapping['fields']['زمان ارسال (روز کاری)'], 'custom:lead_days' );
check( 'ستون تاریخ شناخته شد', $mapping['fields']['تاریخ عرضه'], 'custom:release' );
check( 'هیچ ستونی ناشناخته نماند', $mapping['unknown'], array() );

// نگارش دیگر همان ستون هم باید شناخته شود.
$alias_mapping = PCS_Mapper::build( array( 'کد محصول', 'preorder' ) );
check( 'نگارش انگلیسی هم شناخته می‌شود', $alias_mapping['fields']['preorder'], 'custom:preorder' );

/* ---------------------------- خواندن مقدار ---------------------------- */

$field = PCS_Fields::get( 'custom:preorder' );
check( 'بله → yes', PCS_Fields::to_store( $field, 'بله' ), 'yes' );
check( 'خیر → no', PCS_Fields::to_store( $field, 'خیر' ), 'no' );
check( 'مقدار نامعتبر خطا می‌دهد', is_wp_error( PCS_Fields::to_store( $field, 'شاید' ) ), true );

$days = PCS_Fields::get( 'custom:lead_days' );
check( 'ارقام فارسی خوانده می‌شود', PCS_Fields::to_store( $days, '۱۲' ), 12 );

$release = PCS_Fields::get( 'custom:release' );
check( 'تاریخ شمسی خوانده می‌شود', PCS_Fields::to_store( $release, '۱۴۰۴/۰۷/۰۱' ), '2025-09-23' );
check( 'تاریخ میلادی خوانده می‌شود', PCS_Fields::to_store( $release, '2025-10-04' ), '2025-10-04' );
check( 'تاریخ نامعتبر خطا می‌دهد', is_wp_error( PCS_Fields::to_store( $release, 'فردا' ) ), true );

/* --------------------- پیش‌نمایش و اعمال روی محصول --------------------- */

$product = pcs_test_add_product(
	21,
	array(
		'name'          => 'کارتن پستی کد ۵',
		'sku'           => 'postal-5',
		'regular_price' => '300000',
		'status'        => 'publish',
	)
);

$csv = "کد محصول,نام محصول,پیش‌فروش,زمان ارسال (روز کاری),تاریخ عرضه\r\n";
$csv .= "postal-5,کارتن پستی کد ۵,بله,۱۰,۱۴۰۴/۰۸/۰۱\r\n";

$path = tempnam( sys_get_temp_dir(), 'pcs' ) . '.csv';
file_put_contents( $path, $csv );

$plan = PCS_Sync::plan( $path );
check( 'سطر، به‌روزرسانی است', $plan['rows'][0]['action'], 'update' );
check( 'پیش‌فروش در تغییرات دیده می‌شود', $plan['rows'][0]['changes']['custom:preorder'], array( 'from' => 'no', 'to' => 'yes' ) );
check( 'برچسب فارسی فیلد', PCS_Sync::field_label( 'custom:preorder' ), 'پیش‌فروش' );
check( 'تاریخ به میلادی ذخیره می‌شود', $plan['rows'][0]['values']['custom:release'], '2025-10-23' );

$report = PCS_Sync::apply( $plan );
check( 'یک محصول به‌روز شد', $report['updated'], 1 );
check( 'متای پیش‌فروش نوشته شد', $product->get_meta( '_cartonpak_preorder' ), 'yes' );
check( 'متای زمان ارسال نوشته شد', $product->get_meta( '_cartonpak_lead_days' ), 10 );
check( 'متای تاریخ نوشته شد', $product->get_meta( '_cartonpak_release_date' ), '2025-10-23' );

// اجرای دوباره نباید چیزی را تغییر دهد.
$again = PCS_Sync::plan( $path );
check( 'اجرای دوباره بدون تغییر', $again['rows'][0]['action'], 'unchanged' );

/* ------------------------- ستون‌ها در خروجی ------------------------- */

$export = PCS_Exporter::build( array() );
check( 'ستون پیش‌فروش در خروجی هست', in_array( 'پیش‌فروش', $export['headers'], true ), true );

$column = array_search( 'پیش‌فروش', $export['headers'], true );
$row    = $export['rows'][0];
check( 'مقدار پیش‌فروش در خروجی فارسی است', $row[ $column ], 'بله' );

$without = PCS_Exporter::build( array( 'custom' => 0 ) );
check( 'با خاموش بودن گزینه، ستون نمی‌آید', in_array( 'پیش‌فروش', $without['headers'], true ), false );

unlink( $path );

echo "\n";

if ( $failures ) {
	printf( "%d آزمون شکست خورد.\n", $failures );
	exit( 1 );
}

echo "همهٔ آزمون‌ها موفق بودند.\n";
