// Bošnjački Insajder — small front-end behaviours (no dependencies):
// sticky-bar state, section reveal on scroll, reading progress on articles.
( function () {
	var body = document.body;
	var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	// Section bar shows the mini logo once the masthead has scrolled away.
	var masthead = document.querySelector( '.bi-masthead' );
	var progress = null;
	var article = document.querySelector( '.bi-article .bi-content' );
	if ( article ) {
		progress = document.createElement( 'div' );
		progress.className = 'bi-progress';
		progress.setAttribute( 'aria-hidden', 'true' );
		body.appendChild( progress );
	}

	// Phones: the masthead search field is folded behind a magnifier button.
	var toggle = document.querySelector( '.bi-search-toggle' );
	if ( toggle && masthead ) {
		toggle.addEventListener( 'click', function () {
			var open = masthead.classList.toggle( 'is-search-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			var input = masthead.querySelector( '.bi-search input[type="search"]' );
			if ( open && input ) {
				input.focus();
			}
		} );
	}

	var ticking = false;
	function update() {
		ticking = false;
		var y = window.scrollY;
		var limit = masthead ? masthead.offsetTop + masthead.offsetHeight : 120;
		body.classList.toggle( 'is-scrolled', y > limit );
		if ( progress ) {
			var start = article.getBoundingClientRect().top + y - window.innerHeight * 0.3;
			var span = Math.max( 1, article.offsetHeight - window.innerHeight * 0.4 );
			var p = Math.min( 1, Math.max( 0, ( y - start ) / span ) );
			progress.style.transform = 'scaleX(' + p + ')';
		}
	}
	window.addEventListener( 'scroll', function () {
		if ( ! ticking ) {
			ticking = true;
			window.requestAnimationFrame( update );
		}
	}, { passive: true } );
	update();

	// Reveal sections as they enter the viewport (CSS hides them only when .bi-js is set).
	var sections = document.querySelectorAll( '.bi-reveal' );
	if ( reduce || ! ( 'IntersectionObserver' in window ) ) {
		sections.forEach( function ( el ) { el.classList.add( 'is-in' ); } );
		return;
	}
	var io = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( entry ) {
			if ( entry.isIntersecting ) {
				entry.target.classList.add( 'is-in' );
				io.unobserve( entry.target );
			}
		} );
	}, { rootMargin: '0px 0px -12% 0px', threshold: 0.05 } );
	sections.forEach( function ( el ) { io.observe( el ); } );
}() );
