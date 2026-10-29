<?php
/**
 * "Udarna vijest" (breaking news) flag on posts.
 * The editor sidebar panel lives in assets/editor.js.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_post_meta(
			'post',
			'_binsider_breaking',
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => static fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ),
			)
		);
	}
);

/**
 * Is this post currently breaking (flagged and inside the configured window)?
 */
function binsider_is_breaking( int $post_id ): bool {
	if ( ! get_post_meta( $post_id, '_binsider_breaking', true ) ) {
		return false;
	}
	$hours = (int) binsider_option( 'breaking_hours' );
	return ( time() - (int) get_post_time( 'U', true, $post_id ) ) < $hours * HOUR_IN_SECONDS;
}

/**
 * Currently breaking posts, newest first.
 */
function binsider_breaking_posts( int $limit = 5 ): array {
	$hours = (int) binsider_option( 'breaking_hours' );
	return get_posts(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'meta_key'            => '_binsider_breaking', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'          => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			'date_query'          => array( array( 'after' => gmdate( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS ), 'column' => 'post_date_gmt' ) ),
		)
	);
}

// Show the flag in the posts list.
add_filter(
	'display_post_states',
	static function ( array $states, WP_Post $post ) {
		if ( 'post' === $post->post_type && binsider_is_breaking( $post->ID ) ) {
			$states['binsider_breaking'] = 'UDARNO';
		}
		return $states;
	},
	10,
	2
);
