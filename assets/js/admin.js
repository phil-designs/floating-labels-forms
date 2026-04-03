/* Floating Labels for Forms — Admin UI */
( function () {

	/* Keep .is-selected on style cards in sync with radio button clicks
	   (for browsers that don't support :has() yet). */
	document.querySelectorAll( '.flfg-style-card input[type="radio"]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', function () {
			document.querySelectorAll( '.flfg-style-card' ).forEach( function ( card ) {
				card.classList.remove( 'is-selected' );
			} );
			radio.closest( '.flfg-style-card' ).classList.add( 'is-selected' );
		} );
	} );

	/* Reset colour pickers to their built-in default values. */
	document.querySelectorAll( '.flfg-color-reset' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var targetId = btn.dataset.target;
			var defaultVal = btn.dataset.default;
			var input = document.getElementById( targetId );
			if ( input && defaultVal ) {
				input.value = defaultVal;
			}
		} );
	} );

} )();
