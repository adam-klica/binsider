<?php
/**
 * Shared helpers.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Site sections in menu order: slug => [name, short menu label, description].
 */
function binsider_sections(): array {
	return array(
		'crna-gora'             => array( 'Crna Gora', 'Crna Gora', 'Vijesti iz Crne Gore.' ),
		'sandzak'               => array( 'Sandžak', 'Sandžak', 'Vijesti iz Sandžaka.' ),
		'bosna-i-hercegovina'   => array( 'Bosna i Hercegovina', 'BiH', 'Vijesti iz Bosne i Hercegovine.' ),
		'dijaspora'             => array( 'Dijaspora', 'Dijaspora', 'Vijesti iz dijaspore.' ),
		'svijet'                => array( 'Svijet', 'Svijet', 'Vijesti iz svijeta.' ),
		'politika-i-drustvo'    => array( 'Politika i društvo', 'Politika', 'Politika i društvo.' ),
		'ekonomija-i-biznis'    => array( 'Ekonomija i biznis', 'Ekonomija', 'Ekonomija i biznis.' ),
		'kultura-i-tradicija'   => array( 'Kultura i tradicija', 'Kultura', 'Kultura i tradicija.' ),
		'vjera-i-zajednica'     => array( 'Vjera i zajednica', 'Vjera', 'Vjera i zajednica.' ),
		'nauka-i-obrazovanje'   => array( 'Nauka i obrazovanje', 'Nauka', 'Nauka i obrazovanje.' ),
		'sport-i-zanimljivosti' => array( 'Sport i zanimljivosti', 'Sport', 'Sport i zanimljivosti.' ),
		'intervjui-i-stavovi'   => array( 'Intervjui i stavovi', 'Intervjui', 'Intervjui i aktuelni stavovi.' ),
		'video'                 => array( 'Video', 'Video', 'Video prilozi, intervjui i reportaže.' ),
		'foto-i-dokumenti'      => array( 'Foto i dokumenti', 'Foto', 'Foto galerije i dokumenti.' ),
	);
}

/**
 * Category term ID for a section slug (0 if missing).
 */
function binsider_cat_id( string $slug ): int {
	$term = get_term_by( 'slug', $slug, 'category' );
	return $term ? (int) $term->term_id : 0;
}

/**
 * Primary category of a post: Yoast primary if set, otherwise the first non-default category.
 */
function binsider_primary_category( int $post_id ): ?WP_Term {
	$primary = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
	if ( $primary ) {
		$term = get_term( $primary, 'category' );
		if ( $term instanceof WP_Term ) {
			return $term;
		}
	}
	$terms = get_the_category( $post_id );
	if ( ! $terms ) {
		return null;
	}
	$default = (int) get_option( 'default_category' );
	foreach ( $terms as $term ) {
		if ( $term->term_id !== $default ) {
			return $term;
		}
	}
	return $terms[0];
}

/**
 * Reading time in minutes (200 words per minute, minimum 1).
 */
function binsider_reading_time( int $post_id ): int {
	$text  = wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ) );
	$words = count( preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY ) );
	return max( 1, (int) ceil( $words / 200 ) );
}

/**
 * Option with defaults.
 */
function binsider_option( string $key ) {
	$defaults = array(
		'facebook'       => '',
		'instagram'      => '',
		'youtube'        => '',
		'x'              => '',
		'tiktok'         => '',
		'telegram'       => '',
		'breaking_hours' => 24,
		'news_name'      => 'Bošnjački Insajder',
		'chat_phone'     => '38269644087',
		'chat_fab'       => 1,
	);
	$opts = (array) get_option( 'binsider_options', array() );
	return $opts[ $key ] ?? ( $defaults[ $key ] ?? null );
}

/**
 * Configured social profiles: key => [label, url].
 */
function binsider_social_profiles(): array {
	$labels = array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'youtube'   => 'YouTube',
		'x'         => 'X',
		'tiktok'    => 'TikTok',
		'telegram'  => 'Telegram',
	);
	$out = array();
	foreach ( $labels as $key => $label ) {
		$url = (string) binsider_option( $key );
		if ( $url ) {
			$out[ $key ] = array( $label, $url );
		}
	}
	return $out;
}

/**
 * wp_date() with lowercase month names ("26. oktobra 2026.") — Bosnian writes months lowercase,
 * but the bs_BA translation capitalises them.
 */
function binsider_date( string $format, ?int $timestamp = null ): string {
	global $wp_locale;
	$out    = (string) wp_date( $format, $timestamp );
	$months = array_merge( array_values( (array) $wp_locale->month ), array_values( (array) ( $wp_locale->month_genitive ?? array() ) ) );
	foreach ( array_unique( $months ) as $month ) {
		$out = str_replace( $month, mb_strtolower( $month ), $out );
	}
	return $out;
}

/**
 * Inline brand icon (Simple Icons path), decorative.
 */
function binsider_icon( string $name, int $size = 18 ): string {
	$paths = binsider_icon_paths();
	if ( empty( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf( '<svg class="bi-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="%2$s"/></svg>', $size, esc_attr( $paths[ $name ] ) );
}

/**
 * Viber / WhatsApp chat links for the configured phone (digits only, international format without +).
 * Returns key => [label, url], or an empty array if no phone is set.
 */
function binsider_chat_links(): array {
	$phone = preg_replace( '/\D+/', '', (string) binsider_option( 'chat_phone' ) );
	if ( ! $phone ) {
		return array();
	}
	return array(
		'viber'    => array( 'Viber', 'viber://chat?number=%2B' . $phone ),
		'whatsapp' => array( 'WhatsApp', 'https://wa.me/' . $phone ),
	);
}
