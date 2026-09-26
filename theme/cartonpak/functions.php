<?php
/**
 * کارتن‌پک — توابع قالب
 *
 * @package cartonpak
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CARTONPAK_VERSION', '2.0.0' );

require_once get_template_directory() . '/inc/icons.php';
require_once get_template_directory() . '/inc/template-tags.php';
if ( class_exists( 'WooCommerce' ) ) {
	require_once get_template_directory() . '/inc/woocommerce.php';
}

/* ---------------------------------- تنظیمات اولیه ---------------------------------- */

function cartonpak_setup() {
	load_theme_textdomain( 'cartonpak', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 80,
		'width'       => 220,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'woocommerce', array(
		'thumbnail_image_width' => 450,
		'single_image_width'    => 700,
		'product_grid'          => array( 'default_columns' => 4, 'min_columns' => 2, 'max_columns' => 4 ),
	) );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus( array(
		'primary' => __( 'منوی اصلی', 'cartonpak' ),
		'footer'  => __( 'منوی فوتر', 'cartonpak' ),
	) );
}
add_action( 'after_setup_theme', 'cartonpak_setup' );

function cartonpak_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'cartonpak_content_width', 1200 );
}
add_action( 'after_setup_theme', 'cartonpak_content_width', 0 );

/* ---------------------------------- اسکریپت‌ها و استایل‌ها ---------------------------------- */

function cartonpak_assets() {
	/* فونت Vazirmatn روی همین هاست است (assets/fonts) و در main.css با @font-face تعریف می‌شود. */
	wp_enqueue_style(
		'cartonpak-main',
		get_template_directory_uri() . '/assets/css/main.css',
		array(),
		CARTONPAK_VERSION
	);
	wp_add_inline_style( 'cartonpak-main', cartonpak_accent_css() );
	wp_enqueue_script(
		'cartonpak-main',
		get_template_directory_uri() . '/assets/js/main.js',
		array( 'jquery' ),
		CARTONPAK_VERSION,
		true
	);
	wp_localize_script( 'cartonpak-main', 'cartonpakData', array(
		'cartUrl' => class_exists( 'WooCommerce' ) ? wc_get_cart_url() : home_url( '/' ),
	) );

	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_script( 'wc-add-to-cart' );
		wp_enqueue_script( 'wc-cart-fragments' );
	}
}
add_action( 'wp_enqueue_scripts', 'cartonpak_assets' );

/**
 * پیش‌بارگذاری دو وزن پرکاربرد فونت تا متن فارسی بدون پرش نمایش داده شود.
 */
function cartonpak_preload_fonts() {
	foreach ( array( 'Regular', 'Black' ) as $weight ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( get_template_directory_uri() . '/assets/fonts/Vazirmatn-FD-' . $weight . '.woff2' )
		);
	}
}
add_action( 'wp_head', 'cartonpak_preload_fonts', 2 );

/**
 * رنگ‌های اصلی قابل انتخاب (همه با متن سفید خوانا هستند).
 *
 * @return array<string, string>
 */
function cartonpak_accent_choices() {
	return array(
		'#8A5429' => 'کرافت (پیش‌فرض)',
		'#1F4E79' => 'سرمه‌ای',
		'#2F5D3A' => 'سبز',
		'#1E1A15' => 'مشکی',
	);
}

/**
 * متغیر رنگ اصلی بر اساس انتخاب سفارشی‌سازی.
 *
 * @return string
 */
function cartonpak_accent_css() {
	$accent = get_theme_mod( 'cartonpak_accent', '#8A5429' );
	if ( ! array_key_exists( $accent, cartonpak_accent_choices() ) || '#8A5429' === $accent ) {
		return '';
	}

	return ':root{--c-accent:' . $accent . ';}';
}

function cartonpak_widgets() {
	register_sidebar( array(
		'name'          => __( 'سایدبار وبلاگ', 'cartonpak' ),
		'id'            => 'blog-sidebar',
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	) );
}
add_action( 'widgets_init', 'cartonpak_widgets' );

/* ---------------------------------- سفارشی‌سازی (تلفن، شبکه‌های اجتماعی) ---------------------------------- */

/**
 * فیلدهای متنی «اطلاعات فروشگاه» و مقدار پیش‌فرضشان.
 *
 * @return array<string, array{label: string, default: string}>
 */
function cartonpak_contact_fields() {
	return array(
		'cartonpak_phone'       => array( 'label' => 'تلفن شرکت', 'default' => '۰۹۹۱۲۷۴۸۸۴۸' ),
		'cartonpak_mobile'      => array( 'label' => 'موبایل / ثبت سفارش', 'default' => '۰۹۱۲۰۸۱۰۷۶۹' ),
		'cartonpak_support'     => array( 'label' => 'پیگیری و پشتیبانی سفارشات (نوار بالا)', 'default' => '۰۹۲۱۵۳۱۶۲۳۱' ),
		'cartonpak_instagram'   => array( 'label' => 'اینستاگرام', 'default' => 'https://instagram.com/kartonsazi_parsian' ),
		'cartonpak_telegram'    => array( 'label' => 'تلگرام', 'default' => '' ),
		'cartonpak_whatsapp'    => array( 'label' => 'پیام‌رسان‌ها (روبیکا، بله، ایتا)', 'default' => 'tel:09912748848' ),
		'cartonpak_address'     => array( 'label' => 'آدرس کارخانه', 'default' => 'سمنان، شهرک صنعتی شرق، بلوار کارفرمایان جنوبی، کارفرمایان چهارم، پلاک ۲۰۰' ),
		'cartonpak_hours'       => array( 'label' => 'ساعت کاری (خالی = نمایش داده نشود)', 'default' => '' ),
		'cartonpak_topbar_text' => array( 'label' => 'متن نوار بالا', 'default' => 'ارسال به سراسر ایران · قیمت ویژهٔ خرید عمده' ),
	);
}

function cartonpak_customize( $wp_customize ) {
	$wp_customize->add_section( 'cartonpak_contact', array(
		'title'    => __( 'اطلاعات فروشگاه', 'cartonpak' ),
		'priority' => 30,
	) );

	foreach ( cartonpak_contact_fields() as $id => $args ) {
		$wp_customize->add_setting( $id, array(
			'default'           => $args['default'],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( $id, array(
			'label'   => $args['label'],
			'section' => 'cartonpak_contact',
			'type'    => 'text',
		) );
	}

	/* کد نماد اعتماد و نشان ساماندهی — همان کدی که سامانه‌ها می‌دهند. */
	$badges = array(
		'cartonpak_trust_enamad'    => 'کد نماد اعتماد الکترونیکی (اینماد)',
		'cartonpak_trust_samandehi' => 'کد نشان ساماندهی',
	);
	foreach ( $badges as $id => $label ) {
		$wp_customize->add_setting( $id, array(
			'default'           => '',
			'sanitize_callback' => 'cartonpak_sanitize_badge_code',
		) );
		$wp_customize->add_control( $id, array(
			'label'       => $label,
			'description' => 'کد HTML را از پنل سامانه کپی کنید. تا خالی است، جایی در فوتر برایش نمایش داده نمی‌شود.',
			'section'     => 'cartonpak_contact',
			'type'        => 'textarea',
		) );
	}

	$wp_customize->add_section( 'cartonpak_design', array(
		'title'    => __( 'ظاهر فروشگاه', 'cartonpak' ),
		'priority' => 31,
	) );

	$wp_customize->add_setting( 'cartonpak_accent', array(
		'default'           => '#8A5429',
		'sanitize_callback' => function ( $value ) {
			return array_key_exists( $value, cartonpak_accent_choices() ) ? $value : '#8A5429';
		},
	) );
	$wp_customize->add_control( 'cartonpak_accent', array(
		'label'   => 'رنگ اصلی',
		'section' => 'cartonpak_design',
		'type'    => 'radio',
		'choices' => cartonpak_accent_choices(),
	) );

	$wp_customize->add_setting( 'cartonpak_hero_image', array(
		'default'           => 0,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'cartonpak_hero_image', array(
		'label'       => 'عکس بخش معرفی صفحهٔ اصلی',
		'description' => 'عکس واقعی کارتن‌ها با پس‌زمینهٔ ساده، حداقل ۱۲۰۰×۱۰۰۰ پیکسل. تا انتخاب نشود، تصویر ساده‌شدهٔ کارتن نمایش داده می‌شود.',
		'section'     => 'cartonpak_design',
		'mime_type'   => 'image',
	) ) );
}
add_action( 'customize_register', 'cartonpak_customize' );

/**
 * پاک‌سازی کد نشان‌ها: مدیرانی که اجازهٔ HTML آزاد دارند کد کامل (با onclick
 * ساماندهی) را ذخیره می‌کنند؛ بقیه فقط HTML مجاز نوشته‌ها.
 *
 * @param string $value کد.
 * @return string
 */
function cartonpak_sanitize_badge_code( $value ) {
	return current_user_can( 'unfiltered_html' ) ? trim( (string) $value ) : wp_kses_post( $value );
}

/**
 * خواندن یک تنظیم سفارشی‌سازی با پیش‌فرض ثبت‌شده‌اش.
 *
 * @param string $key      کلید.
 * @param string $fallback جایگزین.
 * @return string
 */
function cartonpak_option( $key, $fallback = '' ) {
	if ( '' === $fallback ) {
		$fields   = cartonpak_contact_fields();
		$fallback = isset( $fields[ $key ] ) ? $fields[ $key ]['default'] : '';
	}
	$value = get_theme_mod( $key, $fallback );
	return $value ? $value : $fallback;
}

/* ---------------------------------- کمکی‌ها ---------------------------------- */

function cartonpak_price( $price, $format = true ) {
	if ( '' === $price || null === $price ) {
		return '';
	}
	$price = round( (float) $price );
	$price = number_format( $price, 0, '.', '٬' );
	$price = str_replace( ',', '٬', $price );
	return $format ? $price . ' تومان' : $price;
}

function cartonpak_digits( $text ) {
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	return str_replace( $en, $fa, $text );
}

/* نمایش قیمت‌ها به تومان در ووکامرس */
function cartonpak_wc_currency_symbol( $currency_symbol, $currency ) {
	if ( 'IRR' === $currency || 'IRT' === $currency ) {
		return 'تومان';
	}
	return $currency_symbol;
}
add_filter( 'woocommerce_currency_symbol', 'cartonpak_wc_currency_symbol', 10, 2 );

function cartonpak_wc_price_args( $args ) {
	$args['decimals']           = 0;
	$args['decimal_separator']  = '.';
	$args['thousand_separator'] = '٬';
	return $args;
}
add_filter( 'wc_price_args', 'cartonpak_wc_price_args' );

/* قیمت‌های دیتابیس به ریال ذخیره شده‌اند؛ هنگام نمایش به تومان تبدیل می‌شوند (حذف یک صفر).
   فیلترها فقط در کانتکست «view» اعمال می‌شوند؛ ذخیره‌سازی در پیشخوان دست‌نخورده می‌ماند. */
function cartonpak_rial_to_toman( $price, $product = null ) {
	if ( is_numeric( $price ) && (float) $price > 0 ) {
		return round( (float) $price / 10 );
	}
	return $price;
}
add_filter( 'woocommerce_product_get_price', 'cartonpak_rial_to_toman', 20, 2 );
add_filter( 'woocommerce_product_get_regular_price', 'cartonpak_rial_to_toman', 20, 2 );
add_filter( 'woocommerce_product_get_sale_price', 'cartonpak_rial_to_toman', 20, 2 );
add_filter( 'woocommerce_product_variation_get_price', 'cartonpak_rial_to_toman', 20, 2 );
add_filter( 'woocommerce_product_variation_get_regular_price', 'cartonpak_rial_to_toman', 20, 2 );
add_filter( 'woocommerce_product_variation_get_sale_price', 'cartonpak_rial_to_toman', 20, 2 );

/* بازهٔ قیمت محصولات متغیر («۲۵٬۰۰۰ تا ۳۸٬۰۰۰») از قیمت‌های خام کش‌شده ساخته
   می‌شود و از فیلترهای بالا رد نمی‌شد؛ همین تبدیل روی آن هم اعمال می‌شود. */
add_filter( 'woocommerce_variation_prices_price', 'cartonpak_rial_to_toman', 20 );
add_filter( 'woocommerce_variation_prices_regular_price', 'cartonpak_rial_to_toman', 20 );
add_filter( 'woocommerce_variation_prices_sale_price', 'cartonpak_rial_to_toman', 20 );
add_filter( 'woocommerce_get_variation_prices_hash', function ( $hash ) {
	$hash[] = 'cartonpak-toman';
	return $hash;
} );

/* وردپرس برای تصاویر SVG ابعاد را ۱×۱ برمی‌گرداند که باعث نامرئی شدن تصویر محصول می‌شود؛
   ابعاد واقعی از فایل SVG خوانده و به متادیتای پیوست اضافه می‌شود. */
function cartonpak_svg_dimensions( $data, $attachment_id ) {
	if ( is_array( $data ) && ( (int) $data['width'] <= 1 || (int) $data['height'] <= 1 ) ) {
		$file = get_attached_file( $attachment_id );
		if ( $file && is_file( $file ) ) {
			$svg = @simplexml_load_file( $file );
			if ( $svg ) {
				$w = (float) $svg['width'];
				$h = (float) $svg['height'];
				if ( ! $w && isset( $svg['viewBox'] ) ) {
					$vb = preg_split( '/[\s,]+/', trim( (string) $svg['viewBox'] ) );
					if ( count( $vb ) === 4 ) {
						$w = (float) $vb[2];
						$h = (float) $vb[3];
					}
				}
				if ( $w && $h ) {
					$data['width']  = (int) round( $w );
					$data['height'] = (int) round( $h );
				}
			}
		}
	}
	return $data;
}
add_filter( 'wp_get_attachment_metadata', 'cartonpak_svg_dimensions', 10, 2 );

/* شمارندهٔ سبد خرید در هدر (با به‌روزرسانی آژاکسی) */
function cartonpak_cart_count_html() {
	$count = class_exists( 'WooCommerce' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	return sprintf(
		'<span class="cart-count" data-count="%1$s"><span class="screen-reader-text">، تعداد کالا: </span>%2$s</span>',
		esc_attr( $count ),
		esc_html( cartonpak_digits( $count ) )
	);
}

function cartonpak_cart_fragments( $fragments ) {
	$fragments['.cart-count'] = cartonpak_cart_count_html();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'cartonpak_cart_fragments' );

function cartonpak_loop_columns() {
	return 4;
}
add_filter( 'loop_shop_columns', 'cartonpak_loop_columns' );

function cartonpak_products_per_page() {
	return 12;
}
add_filter( 'loop_shop_per_page', 'cartonpak_products_per_page' );

remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

/* ---------------------------------- ترجمه متن‌های ووکامرس ---------------------------------- */

function cartonpak_translate_wc( $translated, $text, $domain ) {
	if ( 'woocommerce' !== $domain ) {
		return $translated;
	}
	$map = array(
		'Add to cart'                       => 'افزودن به سبد خرید',
		'View cart'                         => 'مشاهده سبد خرید',
		'Add to cart &rarr;'                => 'افزودن به سبد خرید',
		'Select options'                    => 'انتخاب گزینه‌ها',
		'Read more'                         => 'ادامه مطلب',
		'Added to cart'                     => 'به سبد خرید اضافه شد',
		'Return to shop'                    => 'بازگشت به فروشگاه',
		'Your cart is currently empty.'     => 'سبد خرید شما در حال حاضر خالی است.',
		'Continue shopping'                 => 'ادامه خرید',
		'Cart Totals'                       => 'جمع سبد خرید',
		'Cart totals'                       => 'جمع سبد خرید',
		'Proceed to checkout'               => 'ادامه و پرداخت',
		'Checkout'                          => 'پرداخت',
		'Place order'                       => 'ثبت سفارش',
		'Update cart'                       => 'به‌روزرسانی سبد خرید',
		'Update Cart'                       => 'به‌روزرسانی سبد خرید',
		'Apply coupon'                      => 'اعمال کد تخفیف',
		'Coupon code'                       => 'کد تخفیف',
		'Subtotal'                          => 'جمع جزء',
		'Total'                             => 'مبلغ کل',
		'Shipping'                          => 'حمل‌ونقل',
		'Free shipping'                     => 'ارسال رایگان',
		'Flat rate'                         => 'نرخ ثابت',
		'Local pickup'                      => 'تحویل حضوری',
		'Product'                           => 'محصول',
		'Products'                          => 'محصولات',
		'Price'                             => 'قیمت',
		'Quantity'                          => 'تعداد',
		'Remove'                            => 'حذف',
		'SKU'                               => 'شناسه محصول',
		'Category'                          => 'دسته‌بندی',
		'Categories'                        => 'دسته‌بندی‌ها',
		'Tags'                              => 'برچسب‌ها',
		'Description'                       => 'توضیحات',
		'Additional information'            => 'اطلاعات تکمیلی',
		'Reviews'                           => 'نظرات',
		'Related products'                  => 'محصولات مرتبط',
		'Billing details'                   => 'جزئیات صورت‌حساب',
		'Your order'                        => 'سفارش شما',
		'Payment'                           => 'پرداخت',
		'Cash on delivery'                  => 'پرداخت در محل',
		'Order notes'                       => 'یادداشت سفارش',
		'Notes about your order, e.g. special notes for delivery.' => 'یادداشت‌های سفارش شما، مثلاً توضیحات خاص برای تحویل.',
		'First name'                        => 'نام',
		'Last name'                         => 'نام خانوادگی',
		'Email address'                     => 'ایمیل',
		'Phone'                             => 'تلفن',
		'Street address'                    => 'نشانی',
		'Town / City'                       => 'شهر',
		'Postcode / ZIP'                    => 'کد پستی',
		'Country / Region'                  => 'کشور / منطقه',
		'Back to cart'                      => 'بازگشت به سبد خرید',
		'Showing all %d results'            => 'نمایش همهٔ %d نتیجه',
		'Showing %d result'                 => 'نمایش %d نتیجه',
		'Showing %1$d–%2$d of %3$d results' => 'نمایش %1$d تا %2$d از %3$d نتیجه',
		'Filter'                            => 'فیلتر',
		'Filter by price'                   => 'فیلتر بر اساس قیمت',
		'Sort by'                           => 'مرتب‌سازی بر اساس',
		'Default sorting'                   => 'پیش‌فرض',
		'Sort by popularity'                => 'محبوب‌ترین',
		'Sort by latest'                    => 'جدیدترین',
		'Sort by price: low to high'        => 'قیمت: از کم به زیاد',
		'Sort by price: high to low'        => 'قیمت: از زیاد به کم',
		'Sale!'                             => 'حراج!',
		'In stock'                          => 'موجود در انبار',
		'Out of stock'                      => 'ناموجود',
		'This product is currently out of stock and unavailable.' => 'این محصول در حال حاضر ناموجود و غیرقابل سفارش است.',
		'You cannot add that amount to the cart' => 'امکان افزودن این تعداد به سبد خرید وجود ندارد',
		'Your cart is empty'                => 'سبد خرید شما خالی است',
		/* ورود / ثبت‌نام / حساب کاربری */
		'Username or email address'         => 'نام کاربری یا ایمیل',
		'Username'                          => 'نام کاربری',
		'Password'                          => 'رمز عبور',
		'Remember me'                       => 'مرا به خاطر بسپار',
		'Log in'                            => 'ورود',
		'Login'                             => 'ورود',
		'Register'                          => 'ثبت‌نام',
		'Lost your password?'               => 'رمز عبور را فراموش کرده‌اید؟',
		'Register on the site'              => 'ثبت‌نام در سایت',
		'My account'                        => 'حساب کاربری',
		'My Account'                        => 'حساب کاربری',
		'Dashboard'                         => 'پیشخوان',
		'Orders'                            => 'سفارش‌ها',
		'Order'                             => 'سفارش',
		'Downloads'                         => 'دانلودها',
		'Addresses'                         => 'نشانی‌ها',
		'Account details'                   => 'جزئیات حساب',
		'Logout'                            => 'خروج',
		'Login' => 'ورود',
		'Coupon'                            => 'کد تخفیف',
		'Billing address'                   => 'نشانی صورت‌حساب',
		'Shipping address'                  => 'نشانی ارسال',
		'Company name'                      => 'نام شرکت',
		'State / County'                    => 'استان',
		'Postcode / ZIP' => 'کد پستی',
		'Country / Region' => 'کشور / منطقه',
		'Payment details'                   => 'جزئیات پرداخت',
		'Order summary'                     => 'خلاصه سفارش',
		'Your order'                        => 'سفارش شما',
		'Tax'                               => 'مالیات',
		'Coupons'                           => 'کدهای تخفیف',
		'Actions'                           => 'عملیات',
		'Date'                              => 'تاریخ',
		'Status'                            => 'وضعیت',
		'Paid'                              => 'پرداخت‌شده',
		'Pending payment'                   => 'در انتظار پرداخت',
		'Processing'                        => 'در حال پردازش',
		'On hold'                           => 'در انتظار بررسی',
		'Completed'                         => 'تکمیل‌شده',
		'Refunded'                          => 'استرداد‌شده',
		'Failed'                            => 'ناموفق',
		'Cancelled'                         => 'لغو‌شده',
		'Thank you. Your order has been received.' => 'با تشکر؛ سفارش شما ثبت شد.',
		'Order received'                    => 'سفارش دریافت شد',
		'Your order number'                 => 'شماره سفارش شما',
		'Order date'                        => 'تاریخ سفارش',
		'Order total'                       => 'جمع کل سفارش',
		'Total'                             => 'مبلغ کل',
		'Take a coupon?'                    => 'کد تخفیف دارید؟',
		'Take a coupon'                     => 'کد تخفیف دارید؟',
		'Apply coupon to you'               => 'اعمال کد تخفیف',
		'Apply coupon' => 'اعمال کد تخفیف',
		'Coupon removed successfully.'      => 'کد تخفیف با موفقیت حذف شد.',
		'Apply'                             => 'اعمال',
		'Edit'                              => 'ویرایش',
		'Add'                               => 'افزودن',
		'Save address'                      => 'ذخیره نشانی',
		'Save changes'                      => 'ذخیره تغییرات',
		'Pay for order'                     => 'پرداخت سفارش',
		'Phone number'                      => 'شماره تماس',
		'Mobile'                            => 'موبایل',
		'Email'                             => 'ایمیل',
		'City'                              => 'شهر',
		'Street address' => 'نشانی',
		'Address'                           => 'نشانی',
		'Postcode'                          => 'کد پستی',
		'Shipping'                       => 'حمل‌ونقل',
		'Flat rate'                      => 'نرخ ثابت',
		'Free shipping'                  => 'ارسال رایگان',
		'Local pickup'                   => 'تحویل حضوری',
		'Pay by card'                 => 'پرداخت با کارت',
		'Pay'                            => 'پرداخت',
		'Cash on delivery'              => 'پرداخت در محل',
		'via'                              => 'از طریق',
	);
	if ( isset( $map[ $text ] ) ) {
		return $map[ $text ];
	}
	return $translated;
}
add_filter( 'gettext', 'cartonpak_translate_wc', 10, 3 );

/* حذف پیام پیش‌فرض «افزودن به سبد» — به‌جای آن پاپ‌آپ سفارشی نشان داده می‌شود */
function cartonpak_disable_cart_notice_fragment( $fragments ) {
	unset( $fragments['.woocommerce-notices-wrapper'] );
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'cartonpak_disable_cart_notice_fragment', 30 );

add_filter( 'woocommerce_add_to_cart_message_html', '__return_empty_string' );

/* نوار ابزار بالای فروشگاه (تعداد نتیجه + مرتب‌سازی کنار هم) */
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_ordering', 30 );

function cartonpak_shop_toolbar() {
	echo '<div class="shop-toolbar">';
	if ( function_exists( 'woocommerce_result_count' ) ) {
		woocommerce_result_count();
	}
	if ( function_exists( 'woocommerce_ordering' ) ) {
		woocommerce_ordering();
	}
	echo '</div>';
}
add_action( 'woocommerce_before_shop_loop', 'cartonpak_shop_toolbar', 25 );

/* نماد «تومان» در سبد و پرداخت */
function cartonpak_cart_totals_toman( $price_html ) {
	return str_replace( 'تومان', '<span class="toman-suffix">تومان</span>', $price_html );
}
add_filter( 'woocommerce_cart_item_subtotal', 'cartonpak_cart_totals_toman' );
add_filter( 'woocommerce_cart_subtotal', 'cartonpak_cart_totals_toman' );
add_filter( 'woocommerce_cart_totals_order_total_html', 'cartonpak_cart_totals_toman' );

/* ---------------------------------- فرم تماس (پیام‌ها) ---------------------------------- */

function cartonpak_message_post_type() {
	register_post_type( 'cartonpak_message', array(
		'labels'       => array(
			'name'          => 'پیام‌های تماس',
			'singular_name' => 'پیام',
			'menu_name'     => 'پیام‌های تماس',
		),
		'public'       => false,
		'show_ui'      => true,
		'menu_icon'    => 'dashicons-email-alt',
		'supports'     => array( 'title' ),
		'capabilities' => array(
			'create_posts' => 'do_not_allow',
		),
		'map_meta_cap' => true,
	) );
}
add_action( 'init', 'cartonpak_message_post_type' );

function cartonpak_message_columns( $columns ) {
	return array(
		'cb'       => $columns['cb'],
		'title'    => 'موضوع',
		'name'     => 'نام',
		'phone'    => 'شماره تماس',
		'cdate'    => 'تاریخ',
	);
}
add_filter( 'manage_cartonpak_message_posts_columns', 'cartonpak_message_columns' );

function cartonpak_message_columns_content( $column, $post_id ) {
	$meta = get_post_meta( $post_id, '_cartonpak_message', true );
	$meta = is_array( $meta ) ? $meta : array();
	switch ( $column ) {
		case 'name':
			echo esc_html( isset( $meta['name'] ) ? $meta['name'] : '-' );
			break;
		case 'phone':
			echo esc_html( isset( $meta['phone'] ) ? $meta['phone'] : '-' );
			break;
		case 'cdate':
			echo esc_html( get_the_date( 'Y/m/d', $post_id ) );
			break;
	}
}
add_action( 'manage_cartonpak_message_posts_custom_column', 'cartonpak_message_columns_content', 10, 2 );

function cartonpak_handle_contact_form() {
	if ( ! isset( $_POST['cartonpak_nonce'] ) || ! wp_verify_nonce( $_POST['cartonpak_nonce'], 'cartonpak_contact' ) ) {
		wp_safe_redirect( wp_get_referer() . '#contact-form' );
		exit;
	}
	$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$topic = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
	$body  = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

	$post_id = wp_insert_post( array(
		'post_type'   => 'cartonpak_message',
		'post_status' => 'private',
		'post_title'  => $topic ? $topic : ( $name ? 'پیام از ' . $name : 'پیام جدید' ),
		'post_content' => $body,
	) );

	if ( $post_id ) {
		update_post_meta( $post_id, '_cartonpak_message', array(
			'name'  => $name,
			'phone' => $phone,
		) );
	}

	setcookie( 'cartonpak_sent', '1', time() + 60, COOKIEPATH, COOKIE_DOMAIN );
	wp_safe_redirect( wp_get_referer() . '#contact-form' );
	exit;
}
add_action( 'admin_post_nopriv_cartonpak_contact', 'cartonpak_handle_contact_form' );
add_action( 'admin_post_cartonpak_contact', 'cartonpak_handle_contact_form' );

/* ---------------------------------- متن‌های ووکامرس ---------------------------------- */

function cartonpak_wc_add_to_cart_text() {
	return 'افزودن به سبد خرید';
}
add_filter( 'woocommerce_product_add_to_cart_text', 'cartonpak_wc_add_to_cart_text' );

function cartonpak_wc_related_products_args( $args ) {
	$args['posts_per_page'] = 4;
	$args['columns']        = 4;
	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'cartonpak_wc_related_products_args' );

/* ---------------------------------- فرم ویرایش نشانی حساب کاربری ---------------------------------- */

remove_action( 'woocommerce_account_edit-address_endpoint', 'woocommerce_account_edit_address' );
add_action( 'woocommerce_account_edit-address_endpoint', 'cartonpak_account_edit_address', 10 );

function cartonpak_account_edit_address( $load_address = 'billing' ) {
	$load_address = wc_edit_address_i18n( sanitize_title( $load_address ), true );
	$current_user = wp_get_current_user();
	$country      = get_user_meta( get_current_user_id(), $load_address . '_country', true );

	if ( ! $country ) {
		$country = WC()->countries->get_base_country();
	}

	if ( 'billing' === $load_address ) {
		$allowed_countries = WC()->countries->get_allowed_countries();
		if ( ! array_key_exists( $country, $allowed_countries ) ) {
			$country = current( array_keys( $allowed_countries ) );
		}
	} else {
		$allowed_countries = WC()->countries->get_shipping_countries();
		if ( ! array_key_exists( $country, $allowed_countries ) ) {
			$country = current( array_keys( $allowed_countries ) );
		}
	}

	$address = WC()->countries->get_address_fields( $country, $load_address . '_' );

	wp_enqueue_script( 'wc-country-select' );
	wp_enqueue_script( 'wc-address-i18n' );

	foreach ( $address as $key => $field ) {
		$value = get_user_meta( get_current_user_id(), $key, true );
		if ( ! $value && in_array( $key, array( 'billing_email', 'shipping_email' ), true ) ) {
			$value = $current_user->user_email;
		}
		$address[ $key ]['value'] = apply_filters( 'woocommerce_my_account_edit_address_field_value', $value, $key, $load_address );
	}

	$full_width_fields = array(
		'billing_email', 'billing_company', 'billing_country', 'billing_address_1', 'billing_address_2',
		'shipping_company', 'shipping_country', 'shipping_address_1', 'shipping_address_2',
	);

	$title = ( 'billing' === $load_address ) ? 'نشانی صورت‌حساب' : 'نشانی ارسال';

	do_action( 'woocommerce_before_edit_account_address_form' );
	?>
	<form method="post" class="address-edit-form" novalidate>
		<div class="address-edit-card">
			<div class="address-edit-head">
				<div>
					<h3 class="address-edit-title"><?php echo esc_html( apply_filters( 'woocommerce_my_account_edit_address_title', $title, $load_address ) ); ?></h3>
					<p class="address-edit-sub">
						<?php echo ( 'billing' === $load_address ) ? 'این نشانی برای صدور فاکتور و تماس استفاده می‌شود.' : 'سفارش‌های شما به این نشانی ارسال می‌شوند.'; ?>
					</p>
				</div>
				<a class="address-edit-back" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address' ) ); ?>">بازگشت به نشانی‌ها</a>
			</div>

			<?php do_action( "woocommerce_before_edit_address_form_{$load_address}" ); ?>

			<div class="address-edit-fields">
				<?php
				foreach ( $address as $key => $field ) {
					$field['class'][] = in_array( $key, $full_width_fields, true ) ? 'form-row-full' : 'form-row-half';
					echo woocommerce_form_field( $key, $field, wc_get_post_data_by_key( $key, $field['value'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>

			<?php do_action( "woocommerce_after_edit_address_form_{$load_address}" ); ?>

			<p class="address-edit-actions">
				<input type="hidden" name="action" value="edit_address" />
				<input type="hidden" name="address" value="<?php echo esc_attr( $load_address ); ?>" />
				<?php wp_nonce_field( 'woocommerce-edit_address', 'woocommerce-edit-address-nonce' ); ?>
				<button type="submit" class="btn btn-primary" name="save_address" value="<?php esc_attr_e( 'Save address', 'woocommerce' ); ?>">ذخیره نشانی</button>
				<a class="btn btn-ghost" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address' ) ); ?>">انصراف</a>
			</p>
		</div>
	</form>
	<?php
	do_action( 'woocommerce_after_edit_account_address_form' );
}

/* ============================================================
   پیشخوان حساب کاربری — نسخه بهبودیافته کارتن‌پک
   ============================================================ */

function cartonpak_account_dash_buffer_start() {
	ob_start();
}
add_action( 'woocommerce_account_content', 'cartonpak_account_dash_buffer_start', 1 );

function cartonpak_account_dash_buffer_end() {
	$html = ob_get_clean();
	if ( false === $html ) {
		return;
	}
	if ( did_action( 'woocommerce_account_dashboard' ) ) {
		$html = preg_replace( '#<p>\s*سلام.*?</p>#s', '', $html, 1 );
		$html = preg_replace( '#<p>\s*از طریق پیشخوان.*?</p>#s', '', $html, 1 );
	}
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( did_action( 'woocommerce_account_dashboard' ) ) {
		echo cartonpak_account_dashboard_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'woocommerce_account_content', 'cartonpak_account_dash_buffer_end', 99 );

function cartonpak_account_dashboard_html() {
	$customer_id = get_current_user_id();
	$user        = wp_get_current_user();
	$first_name  = trim( (string) get_user_meta( $customer_id, 'first_name', true ) );
	$hello_name  = $first_name ? $first_name : 'کاربر گرامی';
	$avatar      = $first_name ? mb_substr( $first_name, 0, 1 ) : 'ک';

	$orders = array();
	if ( $customer_id ) {
		$orders = wc_get_orders( array(
			'customer' => $customer_id,
			'limit'    => -1,
			'orderby'  => 'date',
			'order'    => 'DESC',
		) );
	}

	$total_count  = count( $orders );
	$active_count = 0;
	$done_count   = 0;
	$spent        = 0;

	foreach ( $orders as $order ) {
		$status = $order->get_status();
		if ( in_array( $status, array( 'processing', 'on-hold' ), true ) ) {
			$active_count++;
		} elseif ( 'completed' === $status ) {
			$done_count++;
		}
		if ( ! in_array( $status, array( 'cancelled', 'failed', 'refunded', 'trash' ), true ) ) {
			$spent += (float) $order->get_total();
		}
	}

	$recent = array_slice( $orders, 0, 5 );
	?>
	<div class="acct-dash">

		<div class="acct-hero">
			<span class="acct-hero-avatar"><?php echo esc_html( $avatar ); ?></span>
			<div class="acct-hero-meta">
				<span class="acct-hero-greet">سلام، خوش آمدید</span>
				<h2 class="acct-hero-name"><?php echo esc_html( $hello_name ); ?></h2>
				<span class="acct-hero-sub">این پیشخوان حساب کاربری شما در پارسیان کارتن است</span>
			</div>
			<a class="acct-hero-logout" href="<?php echo esc_url( wc_logout_url() ); ?>">خروج از حساب</a>
		</div>

		<div class="acct-stats">
			<div class="acct-stat">
				<span class="acct-stat-ic">📦</span>
				<strong class="acct-stat-num"><?php echo esc_html( cartonpak_digits( (string) $total_count ) ); ?></strong>
				<span class="acct-stat-label">کل سفارش‌ها</span>
			</div>
			<div class="acct-stat">
				<span class="acct-stat-ic">⏳</span>
				<strong class="acct-stat-num"><?php echo esc_html( cartonpak_digits( (string) $active_count ) ); ?></strong>
				<span class="acct-stat-label">در حال انجام</span>
			</div>
			<div class="acct-stat">
				<span class="acct-stat-ic">✅</span>
				<strong class="acct-stat-num"><?php echo esc_html( cartonpak_digits( (string) $done_count ) ); ?></strong>
				<span class="acct-stat-label">تکمیل‌شده</span>
			</div>
			<div class="acct-stat">
				<span class="acct-stat-ic">💳</span>
				<strong class="acct-stat-num"><?php echo esc_html( cartonpak_price( $spent / 10, false ) ); ?></strong>
				<span class="acct-stat-label">مجموع خرید (تومان)</span>
			</div>
		</div>

		<div class="acct-panels">
			<div class="acct-panel acct-panel-orders">
				<div class="acct-panel-head">
					<h3>آخرین سفارش‌ها</h3>
					<a class="acct-panel-more" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">مشاهده همه</a>
				</div>
				<?php if ( $recent ) : ?>
					<table class="acct-table">
						<thead>
							<tr>
								<th>شماره</th>
								<th>تاریخ</th>
								<th>وضعیت</th>
								<th>مبلغ</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent as $order ) : ?>
								<?php $order_date = $order->get_date_created(); ?>
								<tr>
									<td>#<?php echo esc_html( cartonpak_digits( (string) $order->get_order_number() ) ); ?></td>
									<td><?php echo $order_date ? esc_html( cartonpak_digits( wc_format_datetime( $order_date, 'j F Y' ) ) ) : '—'; ?></td>
									<td><span class="acct-badge st-<?php echo esc_attr( $order->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span></td>
									<td><?php echo esc_html( cartonpak_price( (float) $order->get_total() / 10 ) ); ?></td>
									<td><a class="acct-view" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">مشاهده</a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="acct-empty">
						<p>هنوز سفارشی ثبت نکرده‌اید</p>
						<a class="btn btn-primary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">مشاهده محصولات</a>
					</div>
				<?php endif; ?>
			</div>

			<div class="acct-panel acct-panel-links">
				<div class="acct-panel-head">
					<h3>دسترسی سریع</h3>
				</div>
				<a class="acct-link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">📋 سفارش‌های من</a>
				<a class="acct-link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'downloads' ) ); ?>">⬇️ دانلودها</a>
				<a class="acct-link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>">📍 نشانی‌های من</a>
				<a class="acct-link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">👤 جزئیات حساب و رمز عبور</a>
				<a class="acct-link acct-link-logout" href="<?php echo esc_url( wc_logout_url() ); ?>">🚪 خروج از حساب</a>
			</div>
		</div>

	</div>
	<?php
}
