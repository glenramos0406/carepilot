( function () {
	document.querySelectorAll( '[data-wpcare-confirm]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			var message = form.getAttribute( 'data-wpcare-confirm' );
			if ( message && ! window.confirm( message ) ) {
				event.preventDefault();
			}
		} );
	} );
}() );
