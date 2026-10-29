// Floating contact button: opens/closes the Viber + WhatsApp menu.
( function () {
	var fab = document.querySelector( '.bi-fab' );
	if ( ! fab ) {
		return;
	}
	var toggle = fab.querySelector( '.bi-fab__toggle' );
	fab.classList.add( 'is-ready' );

	function set( open ) {
		fab.classList.toggle( 'is-open', open );
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	}
	toggle.addEventListener( 'click', function () {
		set( ! fab.classList.contains( 'is-open' ) );
	} );
	document.addEventListener( 'click', function ( e ) {
		if ( ! fab.contains( e.target ) ) {
			set( false );
		}
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && fab.classList.contains( 'is-open' ) ) {
			set( false );
			toggle.focus();
		}
	} );
}() );
