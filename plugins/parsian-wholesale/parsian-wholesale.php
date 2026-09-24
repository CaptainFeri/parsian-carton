<?php
/**
 * Plugin Name:       پارسیان کارتن — فروش عمده
 * Plugin URI:        https://parsiancarton.com/
 * Description:       قیمت پلکانی بر اساس تعداد (سراسری یا اختصاصی هر محصول)، گزینهٔ «چاپ لوگوی اختصاصی» در صفحهٔ محصول و فرم «کارتن با ابعاد دلخواه / استعلام قیمت عمده» که درخواست‌ها را در پیشخوان ذخیره و ایمیل می‌کند.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Parsian Carton
 * Text Domain:       parsian-wholesale
 * Domain Path:       /languages
 *
 * @package parsian-wholesale
 */

defined( 'ABSPATH' ) || exit;

define( 'PW_VERSION', '1.0.0' );
define( 'PW_FILE', __FILE__ );
define( 'PW_PATH', plugin_dir_path( __FILE__ ) );
define( 'PW_URL', plugin_dir_url( __FILE__ ) );

require_once PW_PATH . 'includes/class-pw-tiers.php';
require_once PW_PATH . 'includes/class-pw-settings.php';
require_once PW_PATH . 'includes/helpers.php';
require_once PW_PATH . 'includes/class-pw-pricing.php';
require_once PW_PATH . 'includes/class-pw-logo-print.php';
require_once PW_PATH . 'includes/class-pw-product.php';
require_once PW_PATH . 'includes/class-pw-quote.php';

/**
 * راه‌اندازی افزونه — فقط وقتی ووکامرس فعال است.
 */
function pw_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'pw_missing_wc_notice' );
		return;
	}

	PW_Settings::instance();
	PW_Pricing::instance();
	PW_Logo_Print::instance();
	PW_Quote::instance();

	if ( is_admin() ) {
		PW_Product::instance();
	}

	add_action( 'wp_enqueue_scripts', 'pw_enqueue_styles' );
}
add_action( 'plugins_loaded', 'pw_bootstrap' );

/**
 * استایل پایهٔ اجزای افزونه. رنگ‌ها از متغیرهای قالب خوانده می‌شوند و
 * در نبودشان رنگ‌های طرح اصلی به کار می‌روند.
 */
function pw_enqueue_styles() {
	wp_enqueue_style( 'pw-wholesale', PW_URL . 'assets/pw.css', array(), PW_VERSION );
}

/**
 * هشدار پیشخوان در نبود ووکامرس.
 */
function pw_missing_wc_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'افزونهٔ «فروش عمده» به ووکامرس نیاز دارد؛ لطفاً ابتدا ووکامرس را فعال کنید.', 'parsian-wholesale' );
	echo '</p></div>';
}
