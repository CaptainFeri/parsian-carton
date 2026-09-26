<?php
/**
 * آیکن‌های خطی قالب (شبکهٔ ۲۴، ضخامت خط ۲، گوشهٔ گرد) — مسیرها از فایل طرح.
 *
 * @package cartonpak
 */

defined( 'ABSPATH' ) || exit;

/**
 * مسیرهای SVG هر آیکن.
 *
 * @return array<string, string>
 */
function cartonpak_icon_paths() {
	return array(
		'logo'       => '<path d="M12 3 L21 7.5 L21 16.5 L12 21 L3 16.5 L3 7.5 Z"/><path d="M3 7.5 L12 12 L21 7.5"/><path d="M12 12 L12 21"/><path d="M7.5 5.25 L16.5 9.75"/>',
		'box'        => '<path d="M12 3 L21 7.5 L21 16.5 L12 21 L3 16.5 L3 7.5 Z"/><path d="M3 7.5 L12 12 L21 7.5"/><path d="M12 12 L12 21"/>',
		'box-layers' => '<path d="M12 3 L21 7.5 L21 16.5 L12 21 L3 16.5 L3 7.5 Z"/><path d="M3 7.5 L12 12 L21 7.5"/><path d="M12 12 L12 21"/><path d="M3 11 L12 15.5 L21 11"/>',
		'diecut'     => '<path d="M3 9h18v11H3z"/><path d="M3 9l3-5h12l3 5"/><path d="M9 13h6"/>',
		'print'      => '<path d="M4 20h16M6 16l9-9 3 3-9 9H6z"/>',
		'resize'     => '<path d="M3 21 L21 3"/><path d="M3 21v-5M3 21h5M21 3v5M21 3h-5"/>',
		'truck'      => '<rect x="2" y="7" width="12" height="9"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>',
		'tag'        => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
		'chat'       => '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12z"/>',
		'search'     => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
		'user'       => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
		'cart'       => '<path d="M3 4h2l2.4 11h11l2-8H6.2"/><circle cx="9" cy="19.5" r="1.5"/><circle cx="17" cy="19.5" r="1.5"/>',
		'arrow'      => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		'plus'       => '<path d="M12 5v14M5 12h14"/>',
		'minus'      => '<path d="M5 12h14"/>',
		'menu'       => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'      => '<path d="M6 6l12 12M18 6L6 18"/>',
		'phone'      => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
		'pin'        => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'cup'        => '<path d="M6 8h12l-1.5 12h-9z"/><path d="M5 5h14v3H5z"/>',
		'zoom'       => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4M11 8v6M8 11h6"/>',
	);
}

/**
 * چاپ یا بازگرداندن یک آیکن.
 *
 * آیکن‌ها تزئینی‌اند (aria-hidden)؛ متن معنادار باید کنار آن‌ها یا در
 * aria-label عنصر والد باشد.
 *
 * @param string $name  نام آیکن.
 * @param int    $size  اندازه به پیکسل.
 * @param string $class کلاس اضافه.
 * @return string
 */
function cartonpak_icon( $name, $size = 24, $class = '' ) {
	$paths = cartonpak_icon_paths();
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="ic%s" viewBox="0 0 24 24" width="%d" height="%d" aria-hidden="true" focusable="false">%s</svg>',
		$class ? ' ' . esc_attr( $class ) : '',
		(int) $size,
		(int) $size,
		$paths[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت‌های درون همین فایل.
	);
}

/**
 * تصویر جای‌نگهدار کارتن (تا وقتی عکس واقعی محصول نیست).
 *
 * @param int $size اندازه.
 * @return string
 */
function cartonpak_carton_art( $size = 150 ) {
	return sprintf(
		'<svg class="carton-art" viewBox="0 0 200 200" width="%1$d" height="%1$d" aria-hidden="true" focusable="false"><path d="M100 30 L170 65 L100 100 L30 65 Z" fill="#D9B48C"/><path d="M30 65 L100 100 L100 175 L30 140 Z" fill="#B98A5E"/><path d="M170 65 L100 100 L100 175 L170 140 Z" fill="#9C6F45"/><polygon points="59,50.5 71,44.5 141,79.5 129,85.5" fill="#F3E6D3"/><polygon points="129,85.5 141,79.5 141,154.5 129,160.5" fill="#E6D2B5"/></svg>',
		(int) $size
	);
}
