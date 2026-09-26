<?php
/**
 * توابع کمکی نمایش — کارت محصول، ابعاد، دسته‌بندی‌ها، پیوندها.
 *
 * @package cartonpak
 */

defined( 'ABSPATH' ) || exit;

/**
 * آیا افزونهٔ فروش عمده فعال است و قیمت پلکانی تعریف شده؟
 *
 * @param WC_Product|null $product محصول؛ null یعنی پله‌های سراسری.
 * @return array پله‌ها یا آرایهٔ خالی.
 */
function cartonpak_tiers( $product = null ) {
	return function_exists( 'pw_get_tiers' ) ? pw_get_tiers( $product ) : array();
}

/**
 * نشانی فرم استعلام قیمت.
 *
 * @param WC_Product|null $product محصول.
 * @return string
 */
function cartonpak_quote_url( $product = null ) {
	if ( function_exists( 'pw_quote_url' ) ) {
		return pw_quote_url( $product );
	}

	return cartonpak_contact_url();
}

/**
 * نشانی صفحهٔ تماس.
 *
 * @return string
 */
function cartonpak_contact_url() {
	$page = get_page_by_path( 'contact-us' );

	return $page ? get_permalink( $page ) : home_url( '/#contact' );
}

/**
 * نشانی فروشگاه.
 *
 * @return string
 */
function cartonpak_shop_url() {
	return class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
}

/**
 * نشانی تماس تلفنی از روی شماره (ارقام فارسی را هم می‌فهمد).
 *
 * @param string $number شماره.
 * @return string
 */
function cartonpak_tel( $number ) {
	$latin = strtr( (string) $number, array_combine( array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), range( 0, 9 ) ) );

	return 'tel:' . preg_replace( '/[^\d+]/', '', $latin );
}

/**
 * ابعاد محصول به شکل «۳۰ × ۲۰ × ۲۰ سانتی‌متر».
 *
 * منبع‌ها به ترتیب: ابعاد حمل‌ونقل ووکامرس، ویژگی «ابعاد/سایز»، و در
 * نهایت اولین «عدد × عدد × عدد» در نام یا توضیحات محصول (کاتالوگ فعلی
 * ابعاد را فقط در متن توضیحات دارد).
 *
 * @param WC_Product $product محصول.
 * @return string
 */
function cartonpak_product_dimensions( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$cache_key = 'dims_' . $product->get_id();
	$cached    = wp_cache_get( $cache_key, 'cartonpak' );
	if ( false !== $cached ) {
		return $cached;
	}

	$units = array(
		'cm' => 'سانتی‌متر',
		'mm' => 'میلی‌متر',
		'm'  => 'متر',
		'in' => 'اینچ',
	);
	$unit  = $units[ get_option( 'woocommerce_dimension_unit', 'cm' ) ] ?? '';
	$parts = array();

	if ( $product->has_dimensions() ) {
		$parts = array_filter( array( $product->get_length(), $product->get_width(), $product->get_height() ), 'strlen' );
	}

	if ( count( $parts ) < 2 ) {
		$sources = array();
		foreach ( $product->get_attributes() as $attribute ) {
			$label = wc_attribute_label( $attribute->get_name(), $product );
			if ( preg_match( '/ابعاد|سایز|اندازه|size|dimension/iu', $label ) ) {
				$sources[] = $product->get_attribute( $attribute->get_name() );
			}
		}
		$sources[] = $product->get_name();
		$sources[] = wp_strip_all_tags( $product->get_short_description() );
		$sources[] = wp_strip_all_tags( $product->get_description() );

		$digit = '[0-9۰-۹٠-٩]+(?:[.٫][0-9۰-۹٠-٩]+)?';
		foreach ( $sources as $text ) {
			if ( preg_match( "/({$digit})\s*[×xX*✕]\s*({$digit})\s*[×xX*✕]\s*({$digit})/u", (string) $text, $m ) ) {
				$parts = array( $m[1], $m[2], $m[3] );
				$unit  = 'سانتی‌متر';
				break;
			}
		}
	}

	$result = count( $parts ) >= 2 ? cartonpak_digits( implode( ' × ', $parts ) ) . ( $unit ? ' ' . $unit : '' ) : '';
	$result = apply_filters( 'cartonpak_product_dimensions', $result, $product );
	wp_cache_set( $cache_key, $result, 'cartonpak' );

	return $result;
}

/**
 * کمترین قیمت نمایشی یک محصول متغیر.
 *
 * از قیمت نمایشی تک‌تک واریاسیون‌ها حساب می‌شود تا تبدیل ریال به تومان
 * قالب روی آن هم اعمال شود.
 *
 * @param WC_Product_Variable $product محصول.
 * @return float|null
 */
function cartonpak_variable_min_price( $product ) {
	$min = null;
	foreach ( $product->get_visible_children() as $child_id ) {
		$child = wc_get_product( $child_id );
		if ( ! $child || '' === $child->get_price() ) {
			continue;
		}
		$price = (float) wc_get_price_to_display( $child );
		$min   = null === $min ? $price : min( $min, $price );
	}

	return $min;
}

/**
 * قیمت کارت محصول: «از ۱۲٬۰۰۰ تومان» برای متغیرها، قیمت عادی برای بقیه.
 *
 * @param WC_Product $product محصول.
 * @return string HTML
 */
function cartonpak_card_price_html( $product ) {
	if ( $product->is_type( 'variable' ) ) {
		$min = cartonpak_variable_min_price( $product );

		return null === $min ? '' : '<span class="pc-from">از</span> ' . wc_price( $min );
	}

	return $product->get_price_html();
}

/**
 * نشان کارت محصول («حراج»، یا نشانی که بخش صفحه تعیین کرده، مثل «پرفروش»).
 *
 * @param WC_Product $product محصول.
 * @return string
 */
function cartonpak_card_badge( $product ) {
	if ( $product->is_on_sale() ) {
		return 'حراج';
	}

	$badges = function_exists( 'wc_get_loop_prop' ) ? (array) wc_get_loop_prop( 'cartonpak_badges', array() ) : array();

	return isset( $badges[ $product->get_id() ] ) ? (string) $badges[ $product->get_id() ] : '';
}

/**
 * آیکن و توضیح هر دستهٔ محصول در صفحهٔ اصلی.
 *
 * @param WP_Term $term دسته.
 * @return array{icon: string, desc: string}
 */
function cartonpak_category_meta( $term ) {
	$known = array(
		'postal-cartons'      => array( 'box', 'استاندارد اداره پست، سایز ۱ تا ۹' ),
		'moving-cartons'      => array( 'box-layers', 'کارتن مقاوم سه‌لایه و پنج‌لایه برای جابه‌جایی' ),
		'catering-restaurant' => array( 'diecut', 'سینی و جعبه‌های کترینگ' ),
		'fast-food'           => array( 'diecut', 'جعبه پیتزا، برگر و سوخاری' ),
		'juice-restaurant'    => array( 'cup', 'جا لیوانی و سینی' ),
		'packaging-supplies'  => array( 'box', 'چسب، سلفون و نایلون حبابدار' ),
		'agri-export'         => array( 'box-layers', 'کارتن میوه، پسته و صادراتی' ),
		'printing'            => array( 'print', 'چاپ لوگو و اطلاعات برند روی کارتن' ),
	);
	$slug  = urldecode( $term->slug );
	$icon  = 'box';
	$desc  = '';

	if ( isset( $known[ $slug ] ) ) {
		list( $icon, $desc ) = $known[ $slug ];
	} else {
		$name = $term->name;
		if ( preg_match( '/چاپ/u', $name ) ) {
			$icon = 'print';
		} elseif ( preg_match( '/پنج|۵|اسباب/u', $name ) ) {
			$icon = 'box-layers';
		} elseif ( preg_match( '/دایکات|پیتزا|فست|کترینگ|رستوران/u', $name ) ) {
			$icon = 'diecut';
		} elseif ( preg_match( '/لیوان|آبمیوه|بستنی/u', $name ) ) {
			$icon = 'cup';
		}
	}

	$term_desc = trim( wp_strip_all_tags( term_description( $term ) ) );
	if ( $term_desc ) {
		$desc = wp_trim_words( $term_desc, 14, '…' );
	}
	if ( ! $desc ) {
		/* translators: %s: تعداد محصول */
		$desc = sprintf( '%s محصول', cartonpak_digits( (string) $term->count ) );
	}

	return array(
		'icon' => $icon,
		'desc' => $desc,
	);
}

/**
 * دسته‌های اصلی صفحهٔ اول (پرمحصول‌ترین دسته‌های سطح اول).
 *
 * @param int $limit تعداد.
 * @return WP_Term[]
 */
function cartonpak_home_categories( $limit = 4 ) {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => 0,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => $limit + 1,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		)
	);

	return is_wp_error( $terms ) ? array() : array_slice( $terms, 0, $limit );
}

/**
 * چاپ فهرست پیوندهای منو.
 *
 * @param array  $items [برچسب، نشانی، فعال؟].
 * @param string $class کلاس فهرست.
 */
function cartonpak_render_links( $items, $class ) {
	echo '<ul class="' . esc_attr( $class ) . '">';
	foreach ( $items as $item ) {
		$current = ! empty( $item[2] );
		printf(
			'<li%s><a href="%s"%s>%s</a></li>',
			$current ? ' class="current-menu-item"' : '',
			esc_url( $item[1] ),
			$current ? ' aria-current="page"' : '',
			esc_html( $item[0] )
		);
	}
	echo '</ul>';
}

/**
 * منوی اصلی پیش‌فرض (تا وقتی منویی به «منوی اصلی» اختصاص داده نشده).
 */
function cartonpak_default_menu() {
	$items = array( array( 'خانه', home_url( '/' ), is_front_page() ) );

	foreach ( cartonpak_home_categories( 3 ) as $term ) {
		$items[] = array( $term->name, get_term_link( $term ), is_tax( 'product_cat', $term->term_id ) );
	}

	$items[] = array( 'سفارش ابعاد دلخواه', cartonpak_quote_url(), false );
	$items[] = array( 'تماس با ما', cartonpak_contact_url(), is_page( 'contact-us' ) );

	cartonpak_render_links( $items, 'main-menu' );
}

/**
 * منوی «دسترسی سریع» پیش‌فرض فوتر.
 */
function cartonpak_default_footer_menu() {
	$items = array( array( 'همهٔ محصولات', cartonpak_shop_url() ) );
	$items[] = array( 'سفارش ابعاد دلخواه', cartonpak_quote_url() );

	$about = get_page_by_path( 'about-us' );
	if ( $about ) {
		$items[] = array( 'درباره ما', get_permalink( $about ) );
	}
	$items[] = array( 'تماس با ما', cartonpak_contact_url() );
	if ( class_exists( 'WooCommerce' ) ) {
		$items[] = array( 'پیگیری سفارش و حساب کاربری', wc_get_page_permalink( 'myaccount' ) );
	}

	cartonpak_render_links( $items, 'footer-menu' );
}
