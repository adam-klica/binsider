<?php
/**
 * Local reset of the starter content. Never shipped.
 * Run through: ./scripts/wp.sh demo   (wp eval-file /demo/demo.php)
 * Deletes previously imported starter/demo posts and images, re-imports them with the
 * "Bošnjački Insajder — Početni članci" plugin, then adds random view counts so "Najčitanije" has data.
 */

if ( ! function_exists( 'binsider_starter_import_batch' ) ) {
	WP_CLI::error( 'Activate the binsider-starter plugin first.' );
}

foreach ( array( '_binsider_demo', '_binsider_starter' ) as $key ) {
	foreach ( get_posts( array( 'post_type' => array( 'post', 'attachment' ), 'post_status' => 'any', 'posts_per_page' => -1, 'meta_key' => $key, 'fields' => 'ids' ) ) as $old ) {
		wp_delete_post( $old, true );
	}
}
update_option( 'sticky_posts', array() );

[ $count ] = binsider_starter_import_batch();

// Post Views Counter: local fake totals only.
global $wpdb;
$table = $wpdb->prefix . 'post_views';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
	foreach ( get_posts( array( 'posts_per_page' => -1, 'meta_key' => '_binsider_starter', 'fields' => 'ids' ) ) as $id ) {
		$wpdb->replace( $table, array( 'id' => $id, 'type' => 4, 'period' => 'total', 'count' => wp_rand( 300, 9000 ) ) );
	}
}
WP_CLI::success( "{$count} articles" );
