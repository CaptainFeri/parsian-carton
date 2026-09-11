<?php
/**
 * Plugin Name:       پارسیان کارتن — فیلتر و جستجوی فروشگاه
 * Plugin URI:        https://parsiancarton.com/
 * Description:       پنل فیلتر و ابزار جستجو برای صفحهٔ فروشگاه و بایگانی دسته‌بندی محصولات ووکامرس (دسته، قیمت، ویژگی‌ها، موجودی، حراج) به‌همراه جستجوی زنده و به‌روزرسانی آژاکسی شبکهٔ محصولات.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Parsian Carton
 * Text Domain:       parsian-shop-filters
 * Domain Path:       /languages
 *
 * @package parsian-shop-filters
 */

defined( 'ABSPATH' ) || exit;

define( 'PSF_VERSION', '1.0.0' );
define( 'PSF_FILE', __FILE__ );
define( 'PSF_PATH', plugin_dir_path( __FILE__ ) );
define( 'PSF_URL', plugin_dir_url( __FILE__ ) );

require_once PSF_PATH . 'includes/helpers.php';
require_once PSF_PATH . 'includes/class-psf-settings.php';
require_once PSF_PATH . 'includes/class-psf-query.php';
require_once PSF_PATH . 'includes/class-psf-render.php';
require_once PSF_PATH . 'includes/class-psf-ajax.php';

/**
 * راه‌اندازی افزونه — فقط وقتی ووکامرس فعال است.
 */
function psf_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'psf_missing_wc_notice' );
		return;
	}

	PSF_Settings::instance();
	PSF_Query::instance();
	PSF_Render::instance();
	PSF_Ajax::instance();
}
add_action( 'plugins_loaded', 'psf_bootstrap' );

/**
 * هشدار پیشخوان در نبود ووکامرس.
 */
function psf_missing_wc_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'افزونهٔ «فیلتر و جستجوی فروشگاه» به ووکامرس نیاز دارد؛ لطفاً ابتدا ووکامرس را فعال کنید.', 'parsian-shop-filters' );
	echo '</p></div>';
}
