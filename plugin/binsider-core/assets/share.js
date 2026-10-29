// "Kopiraj link" button in the share bar.
document.addEventListener( 'click', function ( e ) {
	var btn = e.target.closest( '.bi-share__copy' );
	if ( ! btn || ! navigator.clipboard ) {
		return;
	}
	navigator.clipboard.writeText( btn.dataset.url ).then( function () {
		var old = btn.textContent;
		btn.textContent = btn.dataset.done;
		setTimeout( function () { btn.textContent = old; }, 2000 );
	} );
} );
