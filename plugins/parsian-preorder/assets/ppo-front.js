/**
 * فرم پیش‌فروش — ثبت بدون بارگذاری دوبارهٔ صفحه و برآورد زندهٔ مبلغ.
 */
( function () {
	'use strict';

	var config = window.ppoFront || {};

	function money( amount ) {
		return new Intl.NumberFormat( 'fa-IR' ).format( Math.round( amount ) ) + ' ' + ( config.currency || '' );
	}

	function bindEstimate( form ) {
		var quantity = form.querySelector( 'input[name="quantity"]' );
		var estimate = form.querySelector( '.ppo-estimate' );
		var price = parseFloat( form.dataset.unitPrice || '0' );

		if ( ! quantity || ! estimate || ! price ) {
			return;
		}

		quantity.addEventListener( 'input', function () {
			var count = parseInt( quantity.value, 10 );

			if ( ! count || count < 1 ) {
				return;
			}

			estimate.textContent = ( estimate.dataset.template || '%s' ).replace( '%s', money( price * count ) );
		} );
	}

	function submit( form, event ) {
		event.preventDefault();

		var button = form.querySelector( '.ppo-submit' );
		var box = form.querySelector( '.ppo-message' );
		var data = new FormData( form );

		data.append( 'action', 'ppo_submit' );
		data.append( 'nonce', config.nonce );
		data.append( 'product_id', form.dataset.productId );

		button.disabled = true;
		box.className = 'ppo-message';
		box.textContent = config.sending || '';

		fetch( config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( result && result.success ) {
					box.className = 'ppo-message is-success';
					box.textContent = result.data.message;
					form.reset();
					return;
				}

				box.className = 'ppo-message is-error';
				box.textContent = ( result && result.data && result.data.message ) || config.error;
				button.disabled = false;
			} )
			.catch( function () {
				box.className = 'ppo-message is-error';
				box.textContent = config.error || '';
				button.disabled = false;
			} );
	}

	document.querySelectorAll( '.ppo-form' ).forEach( function ( form ) {
		bindEstimate( form );

		form.addEventListener( 'submit', function ( event ) {
			submit( form, event );
		} );
	} );
}() );
