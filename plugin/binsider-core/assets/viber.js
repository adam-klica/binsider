// Viber links (viber://) do nothing on a device without the Viber app — no error, no fallback.
// If the page is still in front 1.5 s after the click, show the number and a WhatsApp link instead.
( function () {
	var timer;

	function cancel() {
		clearTimeout( timer );
	}
	window.addEventListener( 'blur', cancel );
	window.addEventListener( 'pagehide', cancel );
	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			cancel();
		}
	} );

	function el( tag, cls, text ) {
		var node = document.createElement( tag );
		if ( cls ) {
			node.className = cls;
		}
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	function notice( link ) {
		var old = document.querySelector( '.bi-viber-note' );
		if ( old ) {
			old.remove();
		}
		var box = el( 'div', 'bi-viber-note' );
		box.setAttribute( 'role', 'status' );

		var close = el( 'button', 'bi-viber-note__close', '×' );
		close.type = 'button';
		close.setAttribute( 'aria-label', 'Zatvori' );
		close.addEventListener( 'click', function () {
			box.remove();
		} );

		var title = el( 'strong', '', 'Viber se nije otvorio' );
		var text = el( 'p', '', 'Izgleda da Viber nije instaliran na ovom uređaju. Pišite nam na Viberu sa telefona na broj ' + link.dataset.phone + ' ili nam se javite na WhatsApp.' );
		var actions = el( 'p', 'bi-viber-note__actions' );
		var wa = el( 'a', 'bi-viber-note__btn', 'Otvori WhatsApp' );
		wa.href = link.dataset.whatsapp;
		wa.target = '_blank';
		wa.rel = 'noopener';
		actions.appendChild( wa );

		box.append( close, title, text, actions );
		document.body.appendChild( box );
		wa.focus();
	}

	// The links are rendered for the Viber phone apps (viber://contact?number=%2B382…, the form that works on iPhone).
	// Viber Desktop opens a chat with viber://chat?number=+382… instead.
	var ua = navigator.userAgent;
	var mobile = /Android|iPhone|iPad|iPod|Mobile/i.test( ua ) || ( /Macintosh/.test( ua ) && navigator.maxTouchPoints > 1 );
	if ( ! mobile ) {
		document.querySelectorAll( 'a[href^="viber://contact?number=%2B"]' ).forEach( function ( a ) {
			a.href = a.getAttribute( 'href' ).replace( 'viber://contact?number=%2B', 'viber://chat?number=+' );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest && e.target.closest( 'a[href^="viber:"]' );
		if ( ! link || ! link.dataset.phone ) {
			return;
		}
		cancel();
		timer = setTimeout( function () {
			if ( ! document.hidden && document.hasFocus() ) {
				notice( link );
			}
		}, 1500 );
	} );
}() );
