/*
 * بنرها — فقط چیزی که اسلایدر قالب ندارد: کشیدن با انگشت.
 *
 * چرخش، دکمه‌ها و نقطه‌ها را همان اسلایدر قالب اداره می‌کند؛ اینجا دوباره‌کاری
 * نمی‌شود. اگر روزی قالب عوض شد و اسلایدری نبود، این فایل بی‌صدا کاری نمی‌کند.
 */
( function () {
	'use strict';

	var slider = document.getElementById( 'heroSlider' );

	if ( ! slider || ! slider.closest( '.pbn-hero' ) ) {
		return;
	}

	var startX = null;
	var startY = null;

	slider.addEventListener( 'touchstart', function ( event ) {
		var touch = event.changedTouches[ 0 ];
		startX = touch.clientX;
		startY = touch.clientY;
	}, { passive: true } );

	slider.addEventListener( 'touchend', function ( event ) {
		if ( null === startX ) {
			return;
		}

		var touch = event.changedTouches[ 0 ];
		var dx = touch.clientX - startX;
		var dy = touch.clientY - startY;

		startX = null;
		startY = null;

		// حرکت عمودی یعنی کاربر دارد صفحه را اسکرول می‌کند، نه بنر را عوض.
		if ( Math.abs( dx ) < 45 || Math.abs( dx ) < Math.abs( dy ) ) {
			return;
		}

		// در چیدمان راست‌چین، کشیدن به چپ یعنی «بعدی».
		var rtl = 'rtl' === ( document.documentElement.getAttribute( 'dir' ) || '' ).toLowerCase();
		var next = rtl ? dx > 0 : dx < 0;
		var button = document.getElementById( next ? 'heroNext' : 'heroPrev' );

		if ( button ) {
			button.click();
		}
	}, { passive: true } );
}() );
