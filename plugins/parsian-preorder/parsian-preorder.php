<?php
/**
 * Plugin Name:       پارسیان کارتن — مدیریت پیش‌فروش
 * Plugin URI:        https://parsiancarton.com/
 * Description:       سامانهٔ کامل پیش‌فروش: تنظیمات پیش‌فروش روی هر محصول (تاریخ عرضه، ظرفیت، حداقل تیراژ، پیش‌پرداخت)، فرم ثبت درخواست، کارتابل پیگیری با خط لولهٔ وضعیت، یادداشت و تاریخچهٔ هر درخواست، تبدیل یک‌کلیکی به سفارش ووکامرس، پیامک و ایمیل خودکار، داشبورد آمار و خروجی CSV.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Parsian Carton
 * Text Domain:       parsian-preorder
 * Domain Path:       /languages
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

define( 'PPO_VERSION', '2.0.0' );
define( 'PPO_FILE', __FILE__ );
define( 'PPO_PATH', plugin_dir_path( __FILE__ ) );
define( 'PPO_URL', plugin_dir_url( __FILE__ ) );

require_once PPO_PATH . 'includes/helpers.php';
require_once PPO_PATH . 'includes/class-ppo-status.php';
require_once PPO_PATH . 'includes/class-ppo-settings.php';
require_once PPO_PATH . 'includes/class-ppo-post-type.php';
require_once PPO_PATH . 'includes/class-ppo-log.php';
require_once PPO_PATH . 'includes/class-ppo-request.php';
require_once PPO_PATH . 'includes/class-ppo-product.php';
require_once PPO_PATH . 'includes/class-ppo-notifier.php';
require_once PPO_PATH . 'includes/class-ppo-orders.php';
require_once PPO_PATH . 'includes/class-ppo-metrics.php';
require_once PPO_PATH . 'includes/class-ppo-export.php';
require_once PPO_PATH . 'includes/class-ppo-catalog-sync.php';
require_once PPO_PATH . 'includes/class-ppo-frontend.php';
require_once PPO_PATH . 'includes/class-ppo-install.php';

if ( is_admin() ) {
	require_once PPO_PATH . 'includes/class-ppo-admin.php';
}

/**
 * راه‌اندازی افزونه.
 *
 * ترتیب مهم است: نوع نوشته و وضعیت‌ها باید پیش از هر چیز روی قلاب init ثبت شوند
 * تا بقیهٔ بخش‌ها بتوانند به آن‌ها تکیه کنند.
 */
function ppo_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ppo_missing_wc_notice' );
		return;
	}

	PPO_Post_Type::init();
	PPO_Product::init();
	PPO_Notifier::init();
	PPO_Orders::init();
	PPO_Catalog_Sync::init();
	PPO_Frontend::init();
	PPO_Install::maybe_upgrade();

	if ( is_admin() ) {
		PPO_Admin::instance();
	}
}
add_action( 'plugins_loaded', 'ppo_bootstrap' );

/**
 * هشدار پیشخوان در نبود ووکامرس.
 */
function ppo_missing_wc_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'افزونهٔ «مدیریت پیش‌فروش» به ووکامرس نیاز دارد؛ لطفاً ابتدا ووکامرس را فعال کنید.', 'parsian-preorder' );
	echo '</p></div>';
}

register_activation_hook( __FILE__, array( 'PPO_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PPO_Install', 'deactivate' ) );
