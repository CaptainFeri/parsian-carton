<?php
/**
 * Plugin Name: پارسیان کارتن — پیش‌فروش
 * Description: ثبت درخواست پیش‌فروش محصولات: نشان «پیش‌فروش» و زمان ارسال روی محصول، فرم درخواست، پیامک اطلاع‌رسانی، مدیریت درخواست‌ها و خروجی CSV در پیشخوان.
 * Version:     1.0.1
 * Author:      Parsian Carton
 * Text Domain: parsian-preorder
 *
 * @package parsian_preorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PARSIAN_PREORDER_VER', '1.0.1' );
define( 'PARSIAN_PREORDER_DIR', plugin_dir_path( __FILE__ ) );
define( 'PARSIAN_PREORDER_URL', plugin_dir_url( __FILE__ ) );

require_once PARSIAN_PREORDER_DIR . 'includes/helpers.php';
require_once PARSIAN_PREORDER_DIR . 'includes/product.php';
require_once PARSIAN_PREORDER_DIR . 'includes/frontend.php';
require_once PARSIAN_PREORDER_DIR . 'includes/admin.php';