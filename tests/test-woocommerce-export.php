<?php
/**
 * آزمون یکپارچه با خروجی واقعی ووکامرس فروشگاه.
 *
 * مهم‌ترین ادعا: اگر فایل را برون‌ریزی کنید و بدون هیچ ویرایشی دوباره وارد کنید،
 * باید **هیچ تغییری** گزارش شود. اگر این برقرار نباشد، هر همگام‌سازی داده‌ها را
 * بی‌دلیل بازنویسی می‌کند و پیش‌نمایش هم بی‌فایده می‌شود.
 */

require __DIR__ . '/wp-stubs.php';

$base = __DIR__ . '/../plugins/parsian-catalog-sync/includes/';
require $base . 'class-pcs-spreadsheet.php';
require $base . 'class-pcs-mapper.php';
require $base . 'class-pcs-media.php';
require $base . 'class-pcs-settings.php';
require $base . 'class-pcs-sync.php';

// قالب فعال است: دیتابیس ریالی، فایل تومانی (چون برون‌ریز ووکامرس قیمت را با
// context=view می‌خواند و فیلتر قالب اعمال شده است).
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

$csv = __DIR__ . '/fixtures-wc-export.csv';

/* ---------- فروشگاه را از روی همان فایل بازسازی می‌کنیم ---------- */

$data = PCS_Spreadsheet::read( $csv );
if ( is_wp_error( $data ) ) {
	echo 'FAIL خواندن فایل: ' . $data->get_error_message() . "\n";
	exit( 1 );
}

$next_id     = 1000;
$attachments = array();

foreach ( $data['rows'] as $record ) {
	$sku = trim( $record['شناسه محصول'] );
	if ( '' === $sku ) {
		continue;
	}

	$type  = $record['نوع'];
	$price = trim( $record['قیمت عادی'] );

	$fields = array(
		'sku'          => $sku,
		'name'         => trim( $record['نام'] ),
		'description'  => trim( $record['توضیحات'] ),
		'short_description' => trim( $record['توضیح کوتاه'] ),
		'status'       => '1' === $record['منتشر شده'] ? 'publish' : 'draft',
		'stock_status' => '1' === $record['در انبار؟'] ? 'instock' : 'outofstock',
		'featured'     => '1' === $record['آیا ویژه است؟'],
		'menu_order'   => (int) $record['موقعیت'],
		// قیمت فایل تومان است؛ دیتابیس ریال نگه می‌دارد.
		'regular_price' => ( '' !== $price && 'variable' !== $type ) ? (string) ( (float) $price * 10 ) : '',
	);

	$id      = ++$next_id;
	$product = pcs_test_add_product( $id, $fields );

	// دسته‌بندی‌ها (واریاسیون دسته ندارد).
	$cats = trim( $record['دسته بندی ها'] );
	if ( '' !== $cats && 'variation' !== $type ) {
		$product->terms['product_cat'] = array_map( 'trim', explode( ',', $cats ) );
	}

	// ستون «تصاویر»: اولی شاخص، بقیه گالری.
	// در وردپرس واقعی هر نشانی دقیقاً یک پیوست دارد، حتی اگر چند محصول از همان
	// تصویر استفاده کنند؛ کتابخانهٔ شبیه‌سازی‌شده هم باید همین‌طور رفتار کند.
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

printf( "فروشگاه شبیه‌سازی‌شده: %d محصول\n\n", count( $GLOBALS['pcs_products'] ) );

/* ---------- ادعای اصلی: رفت‌وبرگشت بدون ویرایش = بدون تغییر ---------- */

$plan = PCS_Sync::plan( $csv );
if ( is_wp_error( $plan ) ) {
	echo 'FAIL ساخت نقشه: ' . $plan->get_error_message() . "\n";
	exit( 1 );
}

check( 'هیچ محصولی ساخته نمی‌شود', $plan['summary']['create'], 0 );
check( 'هیچ محصولی به‌روزرسانی نمی‌شود', $plan['summary']['update'], 0 );
check( 'هیچ محصولی «در فایل نیست» شمرده نمی‌شود', count( $plan['missing'] ), 0 );

// تنها خطاها باید مربوط به همان ۳ سطر بدون کد محصول باشد.
check( 'فقط سطرهای بدون کد خطا دارند', $plan['summary']['error'], 3 );
check( 'همهٔ سطرهای دارای کد، بدون تغییر', $plan['summary']['unchanged'], count( $GLOBALS['pcs_products'] ) );

$reasons = array();
foreach ( $plan['rows'] as $row ) {
	foreach ( $row['errors'] as $error ) {
		$reasons[ $error ] = true;
	}
}
check( 'تنها یک نوع خطا وجود دارد', count( $reasons ), 1 );

/* ---------- تغییر واقعی باید دیده شود ---------- */

$edited = __DIR__ . '/fixtures-wc-edited.csv';
$lines  = file( $csv );
$header = $lines[0];
$body   = implode( '', array_slice( $lines, 1 ) );

// نخستین قیمت فایل را عوض می‌کنیم تا فقط یک سطر تغییر کند.
$body = preg_replace( '/,9700,/', ',11000,', $body, 1 );
file_put_contents( $edited, $header . $body );

$plan2 = PCS_Sync::plan( $edited );
check( 'یک ویرایش قیمت → دقیقاً یک به‌روزرسانی', $plan2['summary']['update'], 1 );

foreach ( $plan2['rows'] as $row ) {
	if ( 'update' === $row['action'] ) {
		check( 'تنها فیلد تغییرکرده، قیمت است', array_keys( $row['changes'] ), array( 'regular_price' ) );
		check( 'قیمت قدیم به ریال', $row['changes']['regular_price']['from'], '97000' );
		check( 'قیمت جدید به ریال', $row['changes']['regular_price']['to'], '110000' );
		break;
	}
}
unlink( $edited );

/* ---------- قواعد ایمنی بر پایهٔ نوع محصول ---------- */

$by_type = array();
foreach ( $plan['rows'] as $row ) {
	if ( $row['type'] && ! isset( $by_type[ $row['type'] ] ) && $row['product_id'] ) {
		$by_type[ $row['type'] ] = $row;
	}
}

check( 'روی محصول متغیر، قیمت اعمال نمی‌شود', isset( $by_type['variable']['values']['regular_price'] ), false );
check( 'روی واریاسیون، قیمت اعمال می‌شود', isset( $by_type['variation']['values']['regular_price'] ), true );
check( 'روی واریاسیون، دسته‌بندی اعمال نمی‌شود', isset( $by_type['variation']['values']['categories'] ), false );
check( 'روی محصول ساده، دسته‌بندی اعمال می‌شود', isset( $by_type['simple']['values']['categories'] ), true );

echo $failures ? "\n{$failures} آزمون شکست خورد.\n" : "\nهمهٔ آزمون‌ها موفق بودند.\n";
exit( $failures ? 1 : 0 );
