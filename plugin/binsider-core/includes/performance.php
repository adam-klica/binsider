<?php
/**
 * Front-end weight: load third-party assets only where they are used, and give card images a `sizes`
 * attribute that matches how big they are really shown, so phones don't download 768 px files for 112 px thumbnails.
 */

defined( 'ABSPATH' ) || exit;

// Contact Form 7 loads its JS (plus wp-hooks and wp-i18n) and CSS on every page. Forms only appear through the
// binsider/form block, which enqueues them itself (binsider_render_form).
add_filter( 'wpcf7_load_js', '__return_false' );
add_filter( 'wpcf7_load_css', '__return_false' );

/**
 * `sizes` per card size hint. The templates add one of these classes to core/post-featured-image
 * (tools/build-templates.mjs → card({ sizes })); breakpoints match theme.css (700 / 1100 / 1320 px wide layout).
 */
function binsider_card_sizes(): array {
	return array(
		'bi-sz-lead'  => '(max-width: 700px) 100vw, (max-width: 1100px) calc(100vw - 48px), 820px',
		'bi-sz-thumb' => '128px',
		'bi-sz-list'  => '(max-width: 700px) 112px, 260px',
		'bi-sz-half'  => '(max-width: 700px) 100vw, (max-width: 1100px) 50vw, 640px',
		'bi-sz-col'   => '(max-width: 700px) 100vw, (max-width: 1100px) 50vw, 300px',
	);
}

add_filter(
	'render_block_core/post-featured-image',
	static function ( string $html, array $block ): string {
		$class = (string) ( $block['attrs']['className'] ?? '' );
		foreach ( binsider_card_sizes() as $hint => $sizes ) {
			if ( str_contains( $class, $hint ) ) {
				return (string) preg_replace( '/\ssizes="[^"]*"/', ' sizes="' . esc_attr( $sizes ) . '"', $html, 1 );
			}
		}
		return $html;
	},
	10,
	2
);
