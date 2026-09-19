/**
 * کارتابل پیش‌فروش — تأییدهای پیش از عملیات برگشت‌ناپذیر.
 */
( function () {
	'use strict';

	var strings = window.ppoAdmin || {};

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.ppo-confirm-order' );

		if ( button && strings.confirmOrder && ! window.confirm( strings.confirmOrder ) ) {
			event.preventDefault();
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;

		if ( ! form.querySelector || ! strings.confirmDelete ) {
			return;
		}

		var selects = form.querySelectorAll( 'select[name="action"], select[name="action2"]' );
		var deleting = false;

		selects.forEach( function ( select ) {
			if ( 'delete' === select.value ) {
				deleting = true;
			}
		} );

		if ( deleting && ! window.confirm( strings.confirmDelete ) ) {
			event.preventDefault();
		}
	} );
}() );
