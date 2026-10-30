<?php
/**
 * Security hardening and front-end trimming.
 */

defined( 'ABSPATH' ) || exit;

// XML-RPC is not used (no Jetpack / mobile app) and is a brute-force target.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter(
	'wp_headers',
	static function ( array $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);

// Hide WordPress version.
remove_action( 'wp_head', 'wp_generator' );

// Head links nobody uses: RSD (points to the disabled XML-RPC), shortlinks, and the comments feed (comments are off).
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
add_filter( 'feed_links_show_comments_feed', '__return_false' );

// Block username discovery: /?author=N and the public users REST endpoint.
add_action(
	'template_redirect',
	static function () {
		if ( ! is_user_logged_in() && isset( $_GET['author'] ) && is_numeric( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);
add_filter(
	'rest_endpoints',
	static function ( array $endpoints ) {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}
);

// No emoji script/styles (system emoji render fine everywhere).
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
