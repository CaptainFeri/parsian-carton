<?php
/**
 * Plugin Name:       پارسیان کارتن — مدیریت بنرها
 * Plugin URI:        https://parsiancarton.com/
 * Description:       بنرهای صفحهٔ اصلی را از پیشخوان مدیریت می‌کند: تصویر، عنوان، توضیح، دکمه‌ها، ترتیب، بازهٔ نمایش و اینکه روی موبایل دیده شود یا دسکتاپ. اسلایدر خروجی از همان ساختار قالب استفاده می‌کند، پس ظاهر سایت عوض نمی‌شود — فقط محتوایش قابل ویرایش می‌شود.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Parsian Carton
 * Text Domain:       parsian-banners
 * Domain Path:       /languages
 *
 * @package parsian-banners
 */

defined( 'ABSPATH' ) || exit;

define( 'PBN_VERSION', '1.0.0' );
define( 'PBN_FILE', __FILE__ );
define( 'PBN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PBN_URL', plugin_dir_url( __FILE__ ) );

require_once PBN_PATH . 'includes/helpers.php';
require_once PBN_PATH . 'includes/class-pbn-settings.php';
require_once PBN_PATH . 'includes/class-pbn-banner.php';
require_once PBN_PATH . 'includes/class-pbn-post-type.php';
require_once PBN_PATH . 'includes/class-pbn-render.php';

if ( is_admin() ) {
	require_once PBN_PATH . 'includes/class-pbn-meta.php';
	require_once PBN_PATH . 'includes/class-pbn-admin.php';
}

/**
 * راه‌اندازی افزونه.
 */
function pbn_bootstrap() {
	PBN_Post_Type::init();
	PBN_Render::init();

	if ( is_admin() ) {
		PBN_Meta::init();
		PBN_Admin::init();
	}
}
add_action( 'plugins_loaded', 'pbn_bootstrap' );

/**
 * نمایش بنرهای صفحهٔ اصلی.
 *
 * قالب این تابع را صدا می‌زند. اگر بنری تعریف نشده باشد `false` برمی‌گرداند تا
 * قالب اسلایدر ثابت خودش را نشان دهد — یعنی نه سایت بدون افزونه می‌شکند، نه
 * با نصب افزونه صفحهٔ اصلی خالی می‌ماند.
 *
 * @param string $location جایگاه (پیش‌فرض: home).
 * @return bool آیا چیزی چاپ شد؟
 */
function parsian_banners_render( $location = 'home' ) {
	if ( ! class_exists( 'PBN_Render' ) ) {
		return false;
	}

	return PBN_Render::output( $location );
}

register_activation_hook(
	__FILE__,
	static function () {
		PBN_Post_Type::register();
		flush_rewrite_rules();
	}
);
