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
foreach ( array( 'helpers', 'class-pcs-spreadsheet', 'class-pcs-mapper', 'class-pcs-media', 'class-pcs-settings', 'class-pcs-sync' ) as $class ) {
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

$attachments = array();
$next_attachment = 900000;

foreach ( $data['rows'] as $record ) {
	// فروشگاه با همان شناسه‌های فایل بازسازی می‌شود تا تطبیق بر پایهٔ شناسه هم
	// واقعی سنجیده شود — از جمله محصولاتی که هنوز کد ندارند.
	$id  = (int) $record['شناسه'];
	$sku = trim( $record['شناسه محصول'] );

	if ( ! $id ) {
		continue;
	}

	$type  = $record['نوع'];
	$price = trim( $record['قیمت عادی'] );

	$product = pcs_test_add_product(
		$id,
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
				$attachments[ $url ] = ++$next_attachment;
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
check( 'هیچ سطری خطا ندارد', $plan['summary']['error'], 0 );

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

/* ---------- نسبت دادن کد به محصولی که کد ندارد ---------- */

// سه محصول «اسباب‌کشی چاپ‌دار» روی سایت کد ندارند. حالا که در فایل برایشان کد
// گذاشته شده، باید به همان محصول موجود نسبت داده شود — نه اینکه محصول تکراری
// ساخته شود. تطبیق بر پایهٔ «شناسه» همین را ممکن می‌کند.
$without_sku = array( 98, 99, 100 );

foreach ( $without_sku as $id ) {
	$product = wc_get_product( $id );

	if ( $product ) {
		$product->data['sku'] = '';
	}
}

$assign = PCS_Sync::plan( $workbook );

check( 'نسبت دادن کد — هیچ محصول تازه‌ای ساخته نمی‌شود', $assign['summary']['create'], 0 );
check( 'نسبت دادن کد — دقیقاً سه محصول به‌روز می‌شوند', $assign['summary']['update'], 3 );
check( 'نسبت دادن کد — بدون خطا', $assign['summary']['error'], 0 );

$assigned = array();

foreach ( $assign['rows'] as $row ) {
	if ( 'update' !== $row['action'] ) {
		continue;
	}

	check(
		sprintf( 'شناسه %d — تنها فیلد تغییرکرده «کد محصول» است', $row['id'] ),
		array_keys( $row['changes'] ),
		array( 'sku' )
	);

	check( sprintf( 'شناسه %d — کد قبلی خالی بوده', $row['id'] ), $row['changes']['sku']['from'], '' );

	$assigned[ $row['id'] ] = $row['changes']['sku']['to'];
}

check(
	'کدهای نسبت‌داده‌شده',
	$assigned,
	array(
		98  => 'movingcartons-50-30-35-print',
		99  => 'movingcartons-60-40-40-print',
		100 => 'movingcartons-70-50-40-print',
	)
);

// کد تکراری باید جلوی نوشتن را بگیرد.
$clash = wc_get_product( 98 );
$clash->data['sku'] = '';
$other = wc_get_product( 95 );
$other->data['sku'] = 'movingcartons-50-30-35-print';

$conflict = PCS_Sync::plan( $workbook );
$blocked  = false;

foreach ( $conflict['rows'] as $row ) {
	if ( 98 === (int) $row['id'] && 'error' === $row['action'] ) {
		$blocked = true;
	}
}

check( 'کد تکراری با محصول دیگر، خطا می‌دهد', $blocked, true );

echo $failures ? "\n{$failures} آزمون شکست خورد.\n" : "\nهمهٔ آزمون‌ها موفق بودند.\n";
exit( $failures ? 1 : 0 );
