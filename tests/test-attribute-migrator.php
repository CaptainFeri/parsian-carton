<?php
/**
 * آزمون تبدیل صفت محلی به سراسری.
 *
 * ساختار آزمون عمداً همان ساختار فروشگاه واقعی است: محصول متغیر با صفت محلی
 * «نوع» و دو واریاسیون که مقدارشان **برچسب** است (نه اسلاگ). پس از انتقال،
 * مقدار واریاسیون باید به **اسلاگ ترم** تبدیل شده باشد — اگر نشود، واریاسیون از
 * والدش جدا می‌افتد و محصول قابل خرید نمی‌ماند.
 */

require __DIR__ . '/wp-stubs.php';

$base = __DIR__ . '/../plugins/parsian-catalog-sync/includes/';
require $base . 'class-pcs-spreadsheet.php';
require $base . 'class-pcs-attribute-migrator.php';

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

/**
 * ساخت یک صفت محلی.
 *
 * @param string   $name    نام.
 * @param string[] $options مقادیر.
 * @return WC_Product_Attribute
 */
function local_attribute( $name, $options ) {
	$attribute = new WC_Product_Attribute();
	$attribute->set_id( 0 );
	$attribute->set_name( $name );
	$attribute->set_options( $options );
	$attribute->set_position( 0 );
	$attribute->set_visible( true );
	$attribute->set_variation( true );
	return $attribute;
}

/* ---------- فروشگاه نمونه: یک محصول متغیر + دو واریاسیون ---------- */

$options = array( 'یک رو', 'VIP دو رو' );

$parent = pcs_test_add_product( 10, array( 'sku' => 'cat-01', 'name' => 'سینی تک پرس', 'type' => 'variable', 'children' => array( 11, 12 ) ) );
$parent->attributes = array( 'نوع' => local_attribute( 'نوع', $options ) );

$v1 = pcs_test_add_product( 11, array( 'sku' => 'cat-01-1', 'name' => 'سینی تک پرس - یک رو', 'type' => 'variation' ) );
$v1->attributes = array( 'نوع' => 'یک رو' );

$v2 = pcs_test_add_product( 12, array( 'sku' => 'cat-01-2', 'name' => 'سینی تک پرس - VIP دو رو', 'type' => 'variation' ) );
$v2->attributes = array( 'نوع' => 'VIP دو رو' );

// یک محصول ساده با همان صفت — باید آن هم منتقل شود.
$simple = pcs_test_add_product( 20, array( 'sku' => 'ff-01', 'name' => 'جعبه پیتزا', 'type' => 'simple' ) );
$simple->attributes = array( 'نوع' => local_attribute( 'نوع', array( 'یک رو' ) ) );

/* ---------- اسکن ---------- */

$found = PCS_Attribute_Migrator::scan();
check( 'اسکن — یک صفت محلی پیدا شد', count( $found ), 1 );
$first = reset( $found );
check( 'اسکن — برچسب', $first['label'], 'نوع' );
check( 'اسکن — تعداد محصولات', $first['products'], 2 );
check( 'اسکن — از آن‌ها متغیر', $first['variable'], 1 );
check( 'اسکن — مقادیر', $first['options'], array( 'یک رو', 'VIP دو رو' ) );

/* ---------- پیش‌نمایش ---------- */

$plan = PCS_Attribute_Migrator::plan( 'نوع', 'type' );
check( 'نقشه — خطا ندارد', is_wp_error( $plan ), false );
check( 'نقشه — تاکسونومی مقصد', $plan['taxonomy'], 'pa_type' );
check( 'نقشه — تاکسونومی ساخته می‌شود', $plan['creates_tax'], true );
check( 'نقشه — تعداد محصولات', count( $plan['products'] ), 2 );
check( 'نقشه — تعداد واریاسیون‌ها', $plan['variations'], 2 );
check( 'نقشه — بدون ناسازگاری', $plan['conflicts'], array() );
check( 'نقشه — مقادیر', $plan['terms'], array( 'یک رو', 'VIP دو رو' ) );

/* ---------- اجرا ---------- */

$report = PCS_Attribute_Migrator::apply( $plan );
check( 'اجرا — خطا ندارد', is_wp_error( $report ), false );
check( 'اجرا — محصولات منتقل‌شده', $report['products'], 2 );
check( 'اجرا — واریاسیون‌های منتقل‌شده', $report['variations'], 2 );

/* ---------- مهم‌ترین بررسی: واریاسیون‌ها ---------- */

$after_parent = wc_get_product( 10 );
check( 'والد — صفت محلی حذف شد', isset( $after_parent->attributes['نوع'] ), false );
check( 'والد — صفت سراسری نشست', isset( $after_parent->attributes['pa_type'] ), true );
check( 'والد — صفت واریاسیونی باقی ماند', $after_parent->attributes['pa_type']->get_variation(), true );
check( 'والد — ترم‌ها به محصول وصل شدند', isset( $GLOBALS['pcs_object_terms'][10]['pa_type'] ), true );

$slug_one = get_term_by( 'name', 'یک رو', 'pa_type' )->slug;
$slug_vip = get_term_by( 'name', 'VIP دو رو', 'pa_type' )->slug;

$after_v1 = wc_get_product( 11 );
$after_v2 = wc_get_product( 12 );

check( 'واریاسیون ۱ — کلید قدیمی حذف شد', isset( $after_v1->attributes['نوع'] ), false );
check( 'واریاسیون ۱ — مقدار، اسلاگ ترم است', $after_v1->attributes['pa_type'], $slug_one );
check( 'واریاسیون ۲ — مقدار، اسلاگ ترم است', $after_v2->attributes['pa_type'], $slug_vip );
check( 'اسلاگ‌ها با هم فرق دارند', $slug_one !== $slug_vip, true );

$after_simple = wc_get_product( 20 );
check( 'محصول ساده — صفت سراسری شد', isset( $after_simple->attributes['pa_type'] ), true );

/* ---------- ناسازگاری باید جلوی اجرا را بگیرد ---------- */

$orphan = pcs_test_add_product( 30, array( 'sku' => 'cat-09', 'name' => 'محصول مشکل‌دار', 'type' => 'variable', 'children' => array( 31 ) ) );
$orphan->attributes = array( 'رنگ' => local_attribute( 'رنگ', array( 'قرمز' ) ) );

$child = pcs_test_add_product( 31, array( 'sku' => 'cat-09-1', 'name' => 'واریاسیون یتیم', 'type' => 'variation' ) );
// مقداری که در فهرست مقادیر والد نیست.
$child->attributes = array( 'رنگ' => 'آبی' );

$bad = PCS_Attribute_Migrator::plan( 'رنگ', 'color' );
check( 'ناسازگاری تشخیص داده شد', count( $bad['conflicts'] ), 1 );
check( 'ناسازگاری — مقدار یتیم', $bad['conflicts'][0]['value'], 'آبی' );

$refused = PCS_Attribute_Migrator::apply( $bad );
check( 'با وجود ناسازگاری، اجرا رد می‌شود', is_wp_error( $refused ), true );
check( 'صفت مشکل‌دار دست‌نخورده ماند', isset( wc_get_product( 30 )->attributes['رنگ'] ), true );

/* ---------- اسلاگ ---------- */

check( 'اسلاگ — نویسه‌های غیرمجاز', PCS_Attribute_Migrator::clean_slug( 'My Attr!' ), 'my-attr' );
check( 'اسلاگ — بریدن به ۲۸ نویسه', strlen( PCS_Attribute_Migrator::clean_slug( str_repeat( 'a', 40 ) ) ), 28 );
check( 'اسلاگ — خالی', PCS_Attribute_Migrator::clean_slug( '---' ), '' );

echo $failures ? "\n{$failures} آزمون شکست خورد.\n" : "\nهمهٔ آزمون‌ها موفق بودند.\n";
exit( $failures ? 1 : 0 );
