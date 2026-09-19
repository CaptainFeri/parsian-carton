<?php
/**
 * اتصال به افزونهٔ «همگام‌سازی کاتالوگ با اکسل».
 *
 * با ثبت چند ستون در فیلتر `pcs_custom_fields`، تنظیمات پیش‌فروش هم وارد چرخهٔ
 * فایل می‌شوند: در خروجی CSV می‌آیند، در پیش‌نمایش مقایسه می‌شوند و از فایل
 * روی محصول نوشته می‌شوند. یعنی راه‌اندازی پیش‌فروش برای ۵۰ محصول، به‌جای ۵۰
 * بار باز کردن ویرایشگر محصول، یک ستون در اکسل است.
 *
 * اگر آن افزونه نصب نباشد، این فیلتر هیچ‌وقت اجرا نمی‌شود و چیزی خراب نمی‌شود.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * ستون‌های پیش‌فروش در فایل کاتالوگ.
 */
class PPO_Catalog_Sync {

	/**
	 * ثبت قلاب‌ها.
	 */
	public static function init() {
		add_filter( 'pcs_custom_fields', array( __CLASS__, 'register_fields' ) );
	}

	/**
	 * تعریف ستون‌ها.
	 *
	 * @param array $fields ستون‌های موجود.
	 * @return array
	 */
	public static function register_fields( $fields ) {
		$fields['preorder'] = array(
			'label'    => __( 'پیش‌فروش', 'parsian-preorder' ),
			'aliases'  => array( 'پیش فروش', 'preorder', 'pre-order' ),
			'meta_key' => PPO_Product::META_ENABLED,
			'type'     => 'bool',
			'default'  => 'no',
			// واریاسیون تنظیم والدش را به ارث می‌برد و ستون جداگانه لازم ندارد.
			'types'    => array( 'simple', 'variable', '' ),
		);

		$fields['preorder_lead'] = array(
			'label'    => __( 'زمان آماده‌سازی (روز کاری)', 'parsian-preorder' ),
			'aliases'  => array( 'زمان ارسال', 'زمان ارسال (روز کاری)', 'preorder lead days' ),
			'meta_key' => PPO_Product::META_LEAD_DAYS,
			'type'     => 'int',
			'default'  => 0,
		);

		$fields['preorder_release'] = array(
			'label'    => __( 'تاریخ عرضه', 'parsian-preorder' ),
			'aliases'  => array( 'تاریخ تحویل', 'preorder release date' ),
			'meta_key' => PPO_Product::META_RELEASE,
			'type'     => 'date',
			// در فایل شمسی نوشته می‌شود؛ در دیتابیس میلادی می‌ماند.
			'to_file'  => array( __CLASS__, 'date_to_file' ),
		);

		$fields['preorder_capacity'] = array(
			'label'    => __( 'ظرفیت پیش‌فروش', 'parsian-preorder' ),
			'aliases'  => array( 'ظرفیت', 'preorder capacity' ),
			'meta_key' => PPO_Product::META_CAPACITY,
			'type'     => 'int',
			'default'  => 0,
		);

		$fields['preorder_min'] = array(
			'label'    => __( 'حداقل تیراژ', 'parsian-preorder' ),
			'aliases'  => array( 'حداقل سفارش', 'preorder minimum' ),
			'meta_key' => PPO_Product::META_MIN_QTY,
			'type'     => 'int',
			'default'  => 0,
		);

		$fields['preorder_deposit'] = array(
			'label'    => __( 'پیش‌پرداخت (درصد)', 'parsian-preorder' ),
			'aliases'  => array( 'پیش پرداخت', 'preorder deposit' ),
			'meta_key' => PPO_Product::META_DEPOSIT,
			'type'     => 'int',
			'default'  => 0,
		);

		return $fields;
	}

	/**
	 * نوشتن تاریخ در فایل به‌صورت شمسی.
	 *
	 * @param mixed $value مقدار متا (میلادی).
	 * @return string
	 */
	public static function date_to_file( $value ) {
		$value = (string) $value;

		return '' === $value ? '' : ppo_jalali_date( $value );
	}
}
