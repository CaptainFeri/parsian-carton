<?php
/**
 * آزمون فایل اکسل ساخته‌شده از کاتالوگ واقعی سایت.
 *
 * همان ادعای آزمون خروجی CSV، این بار روی فایل xlsx تحویلی: اگر فایل را بدون
 * ویرایش وارد کنید، نباید هیچ محصولی تغییر کند. اگر این برقرار نباشد، فایل
 * نمونه با سایت هماهنگ نیست.
 */

require __DIR__ . '/wp-stubs.php';

$base = __DIR__ . '/../plugins/parsian-catalog-sync/includes/';
foreach ( array( 'class-pcs-spreadsheet', 'class-pcs-mapper', 'class-pcs-media', 'class-pcs-settings', 'class-pcs-sync' ) as $class ) {
	require $base . $class . '.php';
}

eval( 'function cartonpak_rial_to_toman( $p ) { return $p / 10; }' );

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

$workbook = __DIR__ . '/../docs/محصولات-سایت.xlsx';

check( 'برگه‌ها', PCS_Spreadsheet::sheet_names( $workbook ), array( 'محصولات', 'راهنما' ) );

$data = PCS_Spreadsheet::read( $workbook );
if ( is_wp_error( $data ) ) {
	echo 'FAIL خواندن فایل: ' . $data->get_error_message() . "\n";
	exit( 1 );
}

$mapping = PCS_Mapper::build( $data['headers'] );
check( 'هیچ ستون ناشناخته‌ای ندارد', $mapping['unknown'], array() );
check( 'ستون کلید شناخته شد', in_array( 'sku', $mapping['fields'], true ), true );
check( 'ستون نوع شناخته شد', in_array( 'type', $mapping['fields'], true ), true );
check( 'ستون‌های صفت جفت شدند', count( $mapping['wc_attributes'] ), 1 );

/* ---------- فروشگاه را از روی همین فایل بازسازی می‌کنیم ---------- */

$next_id     = 2000;
$attachments = array();

foreach ( $data['rows'] as $record ) {
	$sku = trim( $record['شناسه محصول'] );
	if ( '' === $sku ) {
		continue;
	}

	$type  = $record['نوع'];
	$price = trim( $record['قیمت عادی'] );

	$product = pcs_test_add_product(
		++$next_id,
		array(
			'sku'               => $sku,
			'type'              => $type,
			'name'              => trim( $record['نام'] ),
			'description'       => trim( $record['توضیحات'] ),
			'short_description' => trim( $record['توضیح کوتاه'] ),
			'status'            => '1' === $record['منتشر شده'] ? 'publish' : 'draft',
			'stock_status'      => '1' === $record['در انبار؟'] ? 'instock' : 'outofstock',
			'featured'          => '1' === $record['آیا ویژه است؟'],
			'menu_order'        => (int) $record['موقعیت'],
			'regular_price'     => ( '' !== $price && 'variable' !== $type ) ? (string) ( (float) $price * 10 ) : '',
		)
	);

	$cats = trim( $record['دسته بندی ها'] );
	if ( '' !== $cats && 'variation' !== $type ) {
		$product->terms['product_cat'] = array_map( 'trim', explode( ',', $cats ) );
	}

	$images = array_values( array_filter( array_map( 'trim', explode( ',', $record['تصاویر'] ) ) ) );
	if ( $images ) {
		$ids = array();
		foreach ( $images as $url ) {
			if ( ! isset( $attachments[ $url ] ) ) {
				$attachments[ $url ] = ++$next_id;
				pcs_test_add_attachment( $attachments[ $url ], $url );
			}
			$ids[] = $attachments[ $url ];
		}
		$product->data['image_id'] = array_shift( $ids );
		$product->data['gallery']  = $ids;
	}
}

printf( "\nفروشگاه بازسازی‌شده از فایل: %d محصول\n\n", count( $GLOBALS['pcs_products'] ) );

$plan = PCS_Sync::plan( $workbook );
if ( is_wp_error( $plan ) ) {
	echo 'FAIL ساخت نقشه: ' . $plan->get_error_message() . "\n";
	exit( 1 );
}

check( 'هیچ محصولی ساخته نمی‌شود', $plan['summary']['create'], 0 );
check( 'هیچ محصولی به‌روزرسانی نمی‌شود', $plan['summary']['update'], 0 );
check( 'همهٔ محصولات بدون تغییر', $plan['summary']['unchanged'], count( $GLOBALS['pcs_products'] ) );
check( 'هیچ محصولی غایب شمرده نمی‌شود', count( $plan['missing'] ), 0 );
check( 'فقط سطرهای بدون کد خطا دارند', $plan['summary']['error'], 3 );

/* ---------- ترکیب کاتالوگ ---------- */

$types = array();
foreach ( $plan['rows'] as $row ) {
	$key           = $row['type'] ? $row['type'] : '(بی‌نوع)';
	$types[ $key ] = ( isset( $types[ $key ] ) ? $types[ $key ] : 0 ) + 1;
}

check( 'تعداد محصولات ساده', $types['simple'], 41 );
check( 'تعداد محصولات متغیر', $types['variable'], 58 );
check( 'تعداد واریاسیون‌ها', $types['variation'], 116 );
check( 'مجموع سطرها', count( $plan['rows'] ), 215 );

echo $failures ? "\n{$failures} آزمون شکست خورد.\n" : "\nهمهٔ آزمون‌ها موفق بودند.\n";
exit( $failures ? 1 : 0 );
