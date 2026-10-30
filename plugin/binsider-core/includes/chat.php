<?php
/**
 * Floating contact button (bottom right, every front-end page): one round button that
 * expands into Viber and WhatsApp. Phone number and on/off switch: Settings → Bošnjački Insajder.
 * Styles live in the theme; the toggle is assets/fab.js.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_footer',
	static function () {
		if ( ! (int) binsider_option( 'chat_fab' ) ) {
			return;
		}
		$links = binsider_chat_links();
		if ( ! $links ) {
			return;
		}
		$items = '';
		foreach ( $links as $key => [ $label, $href ] ) {
			$items .= sprintf(
				'<a class="bi-fab__btn bi-fab__btn--%1$s" href="%2$s" rel="noopener"%3$s aria-label="%4$s"><span class="bi-fab__tip">%5$s</span>%6$s</a>',
				esc_attr( $key ),
				esc_url( $href, array( 'https', 'viber' ) ),
				'whatsapp' === $key ? ' target="_blank"' : binsider_viber_attrs(),
				esc_attr( "Pišite nam na {$label}" ),
				esc_html( $label ),
				binsider_icon( $key, 26 )
			);
		}
		$chat  = '<svg class="bi-fab__open" width="28" height="28" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 3C6.48 3 2 6.92 2 11.75c0 2.47 1.17 4.7 3.06 6.29L4.3 21.4a.6.6 0 0 0 .85.66l3.9-1.86c.95.25 1.94.38 2.95.38 5.52 0 10-3.92 10-8.83S17.52 3 12 3Zm-4.5 9.9a1.15 1.15 0 1 1 0-2.3 1.15 1.15 0 0 1 0 2.3Zm4.5 0a1.15 1.15 0 1 1 0-2.3 1.15 1.15 0 0 1 0 2.3Zm4.5 0a1.15 1.15 0 1 1 0-2.3 1.15 1.15 0 0 1 0 2.3Z"/></svg>';
		$close = '<svg class="bi-fab__close" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>';
		wp_enqueue_script( 'binsider-fab' );
		printf(
			'<div class="bi-fab"><div class="bi-fab__menu" id="bi-fab-menu">%1$s</div><button type="button" class="bi-fab__toggle" aria-expanded="false" aria-controls="bi-fab-menu" aria-label="Kontaktirajte redakciju (Viber, WhatsApp)">%2$s%3$s</button></div>' . "\n",
			$items, // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
			$chat,  // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
			$close  // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
		);
	},
	5
);

add_action(
	'init',
	static function () {
		wp_register_script( 'binsider-fab', BINSIDER_URL . 'assets/fab.js', array(), BINSIDER_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_register_script( 'binsider-viber', BINSIDER_URL . 'assets/viber.js', array(), BINSIDER_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
);
