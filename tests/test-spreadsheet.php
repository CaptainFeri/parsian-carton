<?php
/**
 * آزمون خوانندهٔ صفحه‌گسترده — بدون نیاز به وردپرس.
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_message() { return $this->message; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function __( $text, $domain = '' ) { return $text; }

require __DIR__ . '/../plugins/parsian-catalog-sync/includes/class-pcs-spreadsheet.php';

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

$xlsx = __DIR__ . '/fixtures-products.xlsx';

/* ---------- نام برگه‌ها ---------- */
check( 'نام برگه‌ها', PCS_Spreadsheet::sheet_names( $xlsx ), array( 'محصولات', 'راهنما' ) );

/* ---------- خواندن برگهٔ پیش‌فرض ---------- */
$result = PCS_Spreadsheet::read( $xlsx );

if ( is_wp_error( $result ) ) {
	echo 'FAIL خواندن فایل: ' . $result->get_error_message() . "\n";
	exit( 1 );
}

check(
	'سرستون‌ها',
	$result['headers'],
	array( 'کد محصول', 'نام محصول', 'دسته بندی', 'قیمت', 'قیمت حراج', 'موجودی', 'تصویر', 'ویژگی: سایز', 'توضیح کوتاه' )
);

check( 'تعداد سطرهای داده (سطر خالی حذف شده)', count( $result['rows'] ), 4 );

check( 'سطر اول — کد', $result['rows'][0]['کد محصول'], 'PC-01' );
check( 'سطر اول — نام', $result['rows'][0]['نام محصول'], 'کارتن پستی سایز ۱' );
check( 'سطر اول — قیمت عددی', $result['rows'][0]['قیمت'], '8500' );
check( 'سطر اول — قیمت حراج خالی', $result['rows'][0]['قیمت حراج'], '' );

check( 'سطر دوم — موجودی صفر', $result['rows'][1]['موجودی'], '0' );
check( 'سطر دوم — سلول خالی میانی (تصویر)', $result['rows'][1]['تصویر'], '' );
check( 'سطر دوم — سلول خالی انتهایی (توضیح)', $result['rows'][1]['توضیح کوتاه'], '' );
check( 'سطر دوم — قیمت حراج', $result['rows'][1]['قیمت حراج'], '8900' );

check( 'سطر سوم — قیمت با ارقام فارسی', $result['rows'][2]['قیمت'], '۴۵٬۰۰۰' );
check( 'سطر سوم — دسته‌بندی سلسله‌مراتبی', $result['rows'][2]['دسته بندی'], 'کارتن اسباب‌کشی>۵ لایه' );
check( 'سطر سوم — تصویر با آدرس کامل', $result['rows'][2]['تصویر'], 'https://parsiancarton.com/img/moving-l.jpg' );

check( 'سطر چهارم — پس از سطر خالی', $result['rows'][3]['کد محصول'], 'PC-04' );

/* ---------- انتخاب برگهٔ دوم ---------- */
$second = PCS_Spreadsheet::read( $xlsx, 'راهنما' );
check( 'برگهٔ دوم — سرستون‌ها', $second['headers'], array( 'ستون الف', 'ستون ب' ) );

/* ---------- CSV ---------- */
$csv = __DIR__ . '/fixtures-products.csv';
file_put_contents(
	$csv,
	"\xEF\xBB\xBF" . "کد محصول,نام محصول,قیمت\n" .
	"PC-09,\"کارتن میوه, درجه یک\",12000\n" .
	",,\n" .
	"PC-10,پاکت حبابدار,3200\n"
);

$csv_result = PCS_Spreadsheet::read( $csv );
check( 'CSV — سرستون‌ها بدون BOM', $csv_result['headers'], array( 'کد محصول', 'نام محصول', 'قیمت' ) );
check( 'CSV — سطر خالی حذف شد', count( $csv_result['rows'] ), 2 );
check( 'CSV — کامای داخل گیومه', $csv_result['rows'][0]['نام محصول'], 'کارتن میوه, درجه یک' );

/* ---------- یکدست‌سازی سرستون ---------- */
check( 'normalize_header — نیم‌فاصله', PCS_Spreadsheet::normalize_header( "قیمت\xE2\x80\x8Cحراج" ), 'قیمت حراج' );
check( 'normalize_header — ی و ک عربی', PCS_Spreadsheet::normalize_header( 'كد محصول' ), 'کد محصول' );
check( 'normalize_header — فاصله‌های تکراری', PCS_Spreadsheet::normalize_header( '  نام   محصول  ' ), 'نام محصول' );
// نیم‌فاصله به فاصلهٔ ساده تبدیل می‌شود تا «دسته‌بندی» و «دسته بندی» یکسان دیده شوند.
check(
	'normalize_header — دو نگارش دسته‌بندی یکی می‌شوند',
	PCS_Spreadsheet::normalize_header( 'دسته‌بندی' ),
	PCS_Spreadsheet::normalize_header( 'دسته بندی' )
);

/* ---------- خطاها ---------- */
$missing = PCS_Spreadsheet::read( __DIR__ . '/nope.xlsx' );
check( 'فایل ناموجود → WP_Error', is_wp_error( $missing ), true );

file_put_contents( __DIR__ . '/fixtures-bad.pdf', 'x' );
$bad = PCS_Spreadsheet::read( __DIR__ . '/fixtures-bad.pdf' );
check( 'پسوند پشتیبانی‌نشده → WP_Error', is_wp_error( $bad ), true );
unlink( __DIR__ . '/fixtures-bad.pdf' );

echo $failures ? "\n{$failures} آزمون شکست خورد.\n" : "\nهمهٔ آزمون‌ها موفق بودند.\n";
exit( $failures ? 1 : 0 );
