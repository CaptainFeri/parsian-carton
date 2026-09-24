<?php
/**
 * چیدمان صفحهٔ محصول و فرم خرید طبق طرح.
 *
 * @package cartonpak
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------- ستون اطلاعات محصول ---------------------------------- */

remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );

add_action( 'woocommerce_single_product_summary', 'cartonpak_single_head_open', 2 );
add_action( 'woocommerce_single_product_summary', 'cartonpak_single_status', 3 );
add_action( 'woocommerce_single_product_summary', 'cartonpak_single_dimensions', 6 );
add_action( 'woocommerce_single_product_summary', 'cartonpak_single_head_close', 7 );
add_action( 'woocommerce_single_product_summary', 'cartonpak_single_price', 10 );
add_action( 'woocommerce_single_product_summary', 'cartonpak_single_features', 35 );
add_action( 'woocommerce_after_single_product_summary', 'cartonpak_single_specs', 5 );

/**
 * شروع گروه عنوان.
 */
function cartonpak_single_head_open() {
	echo '<div class="pc-head">';
}

/**
 * پایان گروه عنوان.
 */
function cartonpak_single_head_close() {
	echo '</div>';
}

/**
 * وضعیت موجودی با نقطهٔ رنگی.
 */
function cartonpak_single_status() {
	global $product;

	if ( ! $product->is_in_stock() ) {
		$state = 'is-out';
		$label = 'ناموجود';
	} elseif ( $product->is_on_backorder() ) {
		$state = 'is-backorder';
		$label = 'پیش‌سفارش';
	} else {
		$state = 'is-in';
		$label = 'موجود در انبار';
	}

	printf( '<span class="pc-stock %s"><span class="pc-stock-dot" aria-hidden="true"></span>%s</span>', esc_attr( $state ), esc_html( $label ) );
}

/**
 * خط ابعاد زیر عنوان.
 */
function cartonpak_single_dimensions() {
	global $product;

	$dims = cartonpak_product_dimensions( $product );
	if ( $dims ) {
		echo '<span class="pc-dims">ابعاد: ' . esc_html( $dims ) . '</span>';
	}
}

/**
 * قیمت هر عدد.
 */
function cartonpak_single_price() {
	global $product;

	$html = $product->is_type( 'variable' ) ? cartonpak_card_price_html( $product ) : $product->get_price_html();
	if ( ! $html ) {
		return;
	}

	echo '<div class="pc-price"><span class="pc-price-amount price" data-pc-default="' . esc_attr( $html ) . '">' . wp_kses_post( $html ) . '</span><span class="pc-price-unit">/ هر عدد</span></div>';
}

/**
 * ویژگی‌های ارسال زیر دکمه‌ها.
 */
function cartonpak_single_features() {
	$features = apply_filters(
		'cartonpak_single_features',
		array(
			array( 'truck', 'ارسال با پست و باربری به سراسر ایران' ),
			array( 'resize', 'امکان سفارش همین مدل در ابعاد دلخواه' ),
		)
	);
	if ( ! $features ) {
		return;
	}

	echo '<ul class="pc-features">';
	foreach ( $features as $feature ) {
		echo '<li>' . cartonpak_icon( $feature[0], 22 ) . '<span>' . esc_html( $feature[1] ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</ul>';
}

/**
 * ردیف‌های جدول مشخصات فنی.
 *
 * @param WC_Product $product محصول.
 * @return array[] هر ردیف: label, value, attr (نام فیلد واریاسیون یا خالی).
 */
function cartonpak_product_specs( $product ) {
	$rows = array();

	$dims = cartonpak_product_dimensions( $product );
	if ( $dims ) {
		$rows[] = array(
			'label' => 'ابعاد',
			'value' => $dims,
			'attr'  => '',
		);
	}

	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute->get_visible() && ! $attribute->get_variation() ) {
			continue;
		}
		$value = $product->get_attribute( $attribute->get_name() );
		if ( '' === $value ) {
			continue;
		}
		$rows[] = array(
			'label' => wc_attribute_label( $attribute->get_name(), $product ),
			'value' => str_replace( array( ' | ', ', ' ), '، ', $value ),
			'attr'  => $attribute->get_variation() ? 'attribute_' . sanitize_title( $attribute->get_name() ) : '',
		);
	}

	if ( $product->has_weight() ) {
		$rows[] = array(
			'label' => 'وزن هر عدد',
			'value' => cartonpak_digits( wc_format_weight( $product->get_weight() ) ),
			'attr'  => '',
		);
	}

	if ( $product->get_sku() ) {
		$rows[] = array(
			'label' => 'کد محصول',
			'value' => $product->get_sku(),
			'attr'  => '',
		);
	}

	return apply_filters( 'cartonpak_product_specs', $rows, $product );
}

/**
 * جدول مشخصات فنی.
 */
function cartonpak_single_specs() {
	global $product;

	$rows = cartonpak_product_specs( $product );
	if ( ! $rows ) {
		return;
	}
	?>
	<section class="pc-specs" aria-labelledby="pc-specs-title">
		<h2 id="pc-specs-title">مشخصات فنی</h2>
		<dl class="pc-specs-grid">
			<?php foreach ( $rows as $row ) : ?>
				<div class="pc-spec">
					<dt><?php echo esc_html( $row['label'] ); ?></dt>
					<dd<?php echo $row['attr'] ? ' data-pc-attr="' . esc_attr( $row['attr'] ) . '" data-pc-default="' . esc_attr( $row['value'] ) . '"' : ''; ?>><?php echo esc_html( $row['value'] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</section>
	<?php
}

/* جدول مشخصات جای برگهٔ «اطلاعات تکمیلی» را می‌گیرد. */
add_filter(
	'woocommerce_product_tabs',
	function ( $tabs ) {
		unset( $tabs['additional_information'] );
		return $tabs;
	},
	20
);

/* وضعیت موجودی بالای ستون نمایش داده می‌شود؛ تکرارش در فرم خرید حذف می‌شود
   (پیام موجودی واریاسیون‌ها سر جایش می‌ماند). */
add_filter(
	'woocommerce_get_stock_html',
	function ( $html, $product ) {
		if ( is_product() && $product && $product->get_id() === get_queried_object_id() ) {
			return '';
		}
		return $html;
	},
	10,
	2
);

/* قیمت واریاسیون انتخاب‌شده جای قیمت بالای ستون می‌نشیند؛ ووکامرس باید آن را همیشه بفرستد. */
add_filter( 'woocommerce_show_variation_price', '__return_true' );

add_filter(
	'woocommerce_product_single_add_to_cart_text',
	function () {
		return 'افزودن به سبد خرید';
	}
);

/* ---------------------------------- شمارندهٔ تعداد ---------------------------------- */

add_action( 'woocommerce_before_add_to_cart_quantity', 'cartonpak_qty_open' );
add_action( 'woocommerce_before_quantity_input_field', 'cartonpak_qty_plus' );
add_action( 'woocommerce_after_quantity_input_field', 'cartonpak_qty_minus' );
add_action( 'woocommerce_after_add_to_cart_quantity', 'cartonpak_qty_close' );
add_action( 'woocommerce_after_add_to_cart_button', 'cartonpak_buy_close', 20 );

/**
 * وضعیت درون‌درخواستی شمارنده: آیا الان داخل فیلد تعدادِ فرم اصلی هستیم؟
 *
 * @param bool|null $set مقدار جدید.
 * @return bool
 */
function cartonpak_qty_state( $set = null ) {
	static $state = false;
	if ( null !== $set ) {
		$state = $set;
	}
	return $state;
}

/**
 * وضعیت درون‌درخواستی بستهٔ دکمه‌ها.
 *
 * @param bool|null $set مقدار جدید.
 * @return bool
 */
function cartonpak_buy_state( $set = null ) {
	static $state = false;
	if ( null !== $set ) {
		$state = $set;
	}
	return $state;
}

/**
 * گام دکمه‌های + و −.
 *
 * @return int
 */
function cartonpak_qty_step() {
	return function_exists( 'pw_quantity_settings' ) ? (int) pw_quantity_settings()['step'] : 1;
}

/**
 * شروع بلوک تعداد.
 */
function cartonpak_qty_open() {
	global $product;

	$enabled = is_product() && $product instanceof WC_Product && ! $product->is_sold_individually();
	cartonpak_qty_state( $enabled );
	if ( ! $enabled ) {
		return;
	}

	echo '<div class="pc-qty-block" data-pc-step="' . esc_attr( cartonpak_qty_step() ) . '"><span class="pc-label" aria-hidden="true">تعداد</span><div class="pc-qty-row">';
}

/**
 * دکمهٔ افزایش (سمت راست فیلد).
 */
function cartonpak_qty_plus() {
	if ( cartonpak_qty_state() ) {
		echo '<button type="button" class="pc-step" data-pc-dir="1" aria-label="افزایش تعداد">' . cartonpak_icon( 'plus', 20 ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * دکمهٔ کاهش (سمت چپ فیلد).
 */
function cartonpak_qty_minus() {
	if ( cartonpak_qty_state() ) {
		echo '<button type="button" class="pc-step" data-pc-dir="-1" aria-label="کاهش تعداد">' . cartonpak_icon( 'minus', 20 ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * میان‌برهای تعداد و پایان بلوک تعداد؛ شروع ردیف دکمه‌ها.
 */
function cartonpak_qty_close() {
	if ( ! cartonpak_qty_state() ) {
		return;
	}
	cartonpak_qty_state( false );

	$presets = function_exists( 'pw_quantity_settings' ) ? pw_quantity_settings()['presets'] : array();
	if ( $presets ) {
		echo '<div class="pc-presets" role="group" aria-label="تعداد پیشنهادی">';
		foreach ( $presets as $preset ) {
			printf(
				'<button type="button" class="pc-preset" data-pc-qty="%1$d" aria-pressed="false">%2$s عدد</button>',
				(int) $preset,
				esc_html( cartonpak_digits( number_format( $preset, 0, '', '٬' ) ) )
			);
		}
		echo '</div>';
	}

	echo '</div><span class="screen-reader-text" aria-live="polite" data-pc-qty-live></span></div><div class="pc-buy">';
	cartonpak_buy_state( true );
}

/**
 * دکمهٔ استعلام قیمت کنار «افزودن به سبد» و پایان ردیف دکمه‌ها.
 */
function cartonpak_buy_close() {
	global $product;

	if ( ! cartonpak_buy_state() ) {
		return;
	}
	cartonpak_buy_state( false );

	echo '<a class="btn btn-secondary pc-quote-link" href="' . esc_url( cartonpak_quote_url( $product ) ) . '">استعلام قیمت عمده</a></div>';
}

/* ---------------------------------- مسیر صفحه ---------------------------------- */

/**
 * مسیر صفحه (Breadcrumb) برای صفحهٔ محصول و بایگانی دسته‌ها.
 */
function cartonpak_breadcrumb() {
	if ( ! function_exists( 'woocommerce_breadcrumb' ) || ! ( is_product() || is_product_taxonomy() ) ) {
		return;
	}

	woocommerce_breadcrumb(
		array(
			'delimiter'   => '<span class="pc-crumb-sep" aria-hidden="true">/</span>',
			'wrap_before' => '<nav class="pc-breadcrumb" aria-label="مسیر صفحه">',
			'wrap_after'  => '</nav>',
			'home'        => 'خانه',
		)
	);
}
