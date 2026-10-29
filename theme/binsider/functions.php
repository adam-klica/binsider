<?php
/**
 * Bošnjački Insajder theme.
 * Design only — functionality (blocks, setup, SEO) lives in the "binsider-core" plugin.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/theme.css' );
		add_theme_support( 'responsive-embeds' );
		remove_theme_support( 'core-block-patterns' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$ver = wp_get_theme()->get( 'Version' );
		wp_enqueue_style( 'binsider', get_theme_file_uri( 'assets/css/theme.css' ), array(), $ver );
		wp_enqueue_script( 'binsider', get_theme_file_uri( 'assets/js/theme.js' ), array(), $ver, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
);

// Preload the two Latin font files used above the fold; flag JS early so reveal styles apply without a flash.
add_action(
	'wp_head',
	static function () {
		echo "<script>document.documentElement.classList.add('bi-js');</script>\n";
		foreach ( array( 'newsreader-latin-opsz-normal.woff2', 'schibsted-grotesk-latin-wght-normal.woff2' ) as $font ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( get_theme_file_uri( "assets/fonts/{$font}" ) ) );
		}
		echo '<meta name="theme-color" content="#0E3B2C">' . "\n";
	},
	1
);

// The theme's custom blocks come from the plugin.
add_action(
	'admin_notices',
	static function () {
		if ( ! defined( 'BINSIDER_VERSION' ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p><strong>Tema „Bošnjački Insajder"</strong> zahtijeva dodatak <strong>„Bošnjački Insajder — Core"</strong>. Instalirajte ga i aktivirajte (Dodaci → Dodaj novi → Otpremi dodatak).</p></div>';
		}
	}
);

// Excerpt "…" instead of " [&hellip;]".
add_filter( 'excerpt_more', static fn() => '…' );
