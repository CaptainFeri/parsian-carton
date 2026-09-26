/*
 * اسلایدر بنرها — پارسیان کارتن
 *
 * خودکفاست و به جاوااسکریپت قالب کاری ندارد. نسخهٔ اول به اسلایدر قالب تکیه
 * می‌کرد؛ با بازنویسی قالب آن اسلایدر حذف شد و بنرها روی هم ریختند.
 *
 * چیزهایی که رعایت می‌شوند: چرخش خودکار با امکان خاموشی، توقف روی هاور و هنگام
 * پنهان بودن تب، کشیدن با انگشت، کلیدهای جهت‌دار، و احترام به
 * prefers-reduced-motion.
 */
( function () {
	'use strict';

	var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function setup( root ) {
		var slides = Array.prototype.slice.call( root.querySelectorAll( '.pbn-slide' ) );

		if ( slides.length < 2 ) {
			return;
		}

		var dots = Array.prototype.slice.call( root.querySelectorAll( '[data-pbn-dot]' ) );
		var interval = parseInt( root.getAttribute( 'data-pbn-interval' ), 10 );
		var current = Math.max( 0, slides.findIndex( function ( s ) { return s.classList.contains( 'is-active' ); } ) );
		var timer = null;

		function show( index ) {
			var next = ( index + slides.length ) % slides.length;

			if ( next === current ) {
				return;
			}

			slides[ current ].classList.remove( 'is-active' );
			slides[ current ].setAttribute( 'aria-hidden', 'true' );

			current = next;

			slides[ current ].classList.add( 'is-active' );
			slides[ current ].removeAttribute( 'aria-hidden' );

			dots.forEach( function ( dot, i ) {
				dot.classList.toggle( 'is-active', i === current );
				dot.setAttribute( 'aria-selected', i === current ? 'true' : 'false' );
			} );
		}

		function start() {
			if ( timer || reduced || ! interval || interval < 1 ) {
				return;
			}

			timer = window.setInterval( function () { show( current + 1 ); }, interval * 1000 );
		}

		function stop() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}
		}

		function restart() {
			stop();
			start();
		}

		var prev = root.querySelector( '[data-pbn-prev]' );
		var next = root.querySelector( '[data-pbn-next]' );

		if ( prev ) {
			prev.addEventListener( 'click', function () { show( current - 1 ); restart(); } );
		}

		if ( next ) {
			next.addEventListener( 'click', function () { show( current + 1 ); restart(); } );
		}

		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () { show( i ); restart(); } );
		} );

		root.addEventListener( 'mouseenter', stop );
		root.addEventListener( 'mouseleave', start );

		// چرخیدن بنر در تبِ پنهان فقط باتری مصرف می‌کند.
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) { stop(); } else { start(); }
		} );

		root.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowLeft' === event.key ) { show( current + 1 ); restart(); }
			if ( 'ArrowRight' === event.key ) { show( current - 1 ); restart(); }
		} );

		/* ---------- کشیدن با انگشت ---------- */

		var startX = null;
		var startY = null;

		root.addEventListener( 'touchstart', function ( event ) {
			var touch = event.changedTouches[ 0 ];
			startX = touch.clientX;
			startY = touch.clientY;
			stop();
		}, { passive: true } );

		root.addEventListener( 'touchend', function ( event ) {
			if ( null === startX ) {
				start();
				return;
			}

			var touch = event.changedTouches[ 0 ];
			var dx = touch.clientX - startX;
			var dy = touch.clientY - startY;

			startX = null;
			startY = null;

			// حرکت عمودی یعنی کاربر دارد صفحه را اسکرول می‌کند، نه بنر را عوض.
			if ( Math.abs( dx ) >= 45 && Math.abs( dx ) > Math.abs( dy ) ) {
				var rtl = 'rtl' === ( document.documentElement.getAttribute( 'dir' ) || '' ).toLowerCase();
				show( current + ( ( rtl ? dx > 0 : dx < 0 ) ? 1 : -1 ) );
			}

			start();
		}, { passive: true } );

		start();
	}

	document.querySelectorAll( '.pbn-banners' ).forEach( setup );
}() );
