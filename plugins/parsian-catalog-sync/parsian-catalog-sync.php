<?php
/**
 * Plugin Name:       پارسیان کارتن — همگام‌سازی کاتالوگ با اکسل
 * Plugin URI:        https://parsiancarton.com/
 * Description:       چرخهٔ کامل کاتالوگ با یک فایل: گرفتن خروجی CSV/اکسل از محصولات فروشگاه، ویرایش آن، و برگرداندنش با پیش‌نمایش تغییرات پیش از اعمال. همگام‌سازی خودکار از یک نشانی (مثلاً گوگل‌شیت) هم دارد.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Parsian Carton
 * Text Domain:       parsian-catalog-sync
 * Domain Path:       /languages
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

define( 'PCS_VERSION', '1.1.0' );
define( 'PCS_FILE', __FILE__ );
define( 'PCS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PCS_URL', plugin_dir_url( __FILE__ ) );

require_once PCS_PATH . 'includes/helpers.php';
require_once PCS_PATH . 'includes/class-pcs-fields.php';
require_once PCS_PATH . 'includes/class-pcs-spreadsheet.php';
require_once PCS_PATH . 'includes/class-pcs-mapper.php';
require_once PCS_PATH . 'includes/class-pcs-content.php';
require_once PCS_PATH . 'includes/class-pcs-media.php';
require_once PCS_PATH . 'includes/class-pcs-settings.php';
require_once PCS_PATH . 'includes/class-pcs-sync.php';
require_once PCS_PATH . 'includes/class-pcs-exporter.php';
require_once PCS_PATH . 'includes/class-pcs-scheduler.php';
require_once PCS_PATH . 'includes/class-pcs-attribute-migrator.php';
require_once PCS_PATH . 'includes/class-pcs-admin.php';
require_once PCS_PATH . 'includes/class-pcs-attribute-admin.php';

/**
 * راه‌اندازی افزونه.
 */
function pcs_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'pcs_missing_wc_notice' );
		return;
	}

	PCS_Scheduler::init();

	if ( is_admin() ) {
		PCS_Admin::instance();
		PCS_Attribute_Admin::instance();
	}
}
add_action( 'plugins_loaded', 'pcs_bootstrap' );

/**
 * هشدار پیشخوان در نبود ووکامرس.
 */
function pcs_missing_wc_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'افزونهٔ «همگام‌سازی کاتالوگ با اکسل» به ووکامرس نیاز دارد؛ لطفاً ابتدا ووکامرس را فعال کنید.', 'parsian-catalog-sync' );
	echo '</p></div>';
}

/**
 * پاک‌سازی زمان‌بندی هنگام غیرفعال شدن افزونه.
 */
function pcs_deactivate() {
	PCS_Scheduler::clear();
}
register_deactivation_hook( __FILE__, 'pcs_deactivate' );
