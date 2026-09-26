/* انتخاب تصویر ویژهٔ موبایل از کتابخانهٔ رسانه. */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame = null;
		var input = $( '#pbn-mobile-image' );
		var preview = $( '.pbn-mobile-preview' );

		$( '.pbn-pick-image' ).on( 'click', function ( event ) {
			event.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: ( window.pbnAdmin && window.pbnAdmin.title ) || '',
				button: { text: ( window.pbnAdmin && window.pbnAdmin.button ) || '' },
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var image = frame.state().get( 'selection' ).first().toJSON();
				var url = ( image.sizes && image.sizes.medium ) ? image.sizes.medium.url : image.url;

				input.val( image.id );
				preview.html( $( '<img>' ).attr( 'src', url ).attr( 'alt', '' ) );
			} );

			frame.open();
		} );

		$( '.pbn-clear-image' ).on( 'click', function ( event ) {
			event.preventDefault();
			input.val( '' );
			preview.empty();
		} );
	} );
}( jQuery ) );
