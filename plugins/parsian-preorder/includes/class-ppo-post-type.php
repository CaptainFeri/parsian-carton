<?php
/**
 * نوع نوشتهٔ «درخواست پیش‌فروش».
 *
 * رابط کاربری استاندارد وردپرس برای این نوع خاموش است؛ همهٔ کار از کارتابل
 * اختصاصی افزونه انجام می‌شود (پیشخوان ← پیش‌فروش). دلیلش این است که یک
 * درخواست پیش‌فروش «نوشته» نیست: عنوان و متن ندارد، ولی وضعیت، مسئول پیگیری،
 * تاریخ پیگیری و تاریخچه دارد.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * ثبت نوع نوشته و وضعیت‌ها.
 */
class PPO_Post_Type {

	/**
	 * نام نوع نوشته — همان نام افزونهٔ قدیمی، تا درخواست‌های ثبت‌شده از دست نروند.
	 */
	const POST_TYPE = 'preorder';

	/**
	 * ثبت قلاب‌ها.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
	}

	/**
	 * ثبت نوع نوشته و وضعیت‌های خط لوله.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'درخواست‌های پیش‌فروش', 'parsian-preorder' ),
					'singular_name' => __( 'درخواست پیش‌فروش', 'parsian-preorder' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'create_posts' => 'do_not_allow',
				),
			)
		);

		PPO_Status::register();
	}
}
