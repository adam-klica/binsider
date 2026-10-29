<?php
/**
 * Homepage logic for Query Loop blocks.
 *
 * A Query Loop block can carry "binsider" inside its "query" attribute:
 *   "lead"      → the main story: newest sticky post, otherwise newest post (1 post)
 *   "secondary" → newest posts excluding the main story
 *   "related"   → same primary category as the current article, excluding it
 * Category sections use the normal category filter, which is resolved by slug at render time
 * ("binsiderCat": "<slug>") so templates work on any site regardless of term IDs.
 */

defined( 'ABSPATH' ) || exit;

/**
 * ID of the lead (main) story, cached per request.
 */
function binsider_lead_post_id(): int {
	static $id = null;
	if ( null !== $id ) {
		return $id;
	}
	$sticky = array_map( 'intval', (array) get_option( 'sticky_posts', array() ) );
	$args   = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 1,
		'ignore_sticky_posts' => true,
		'fields'              => 'ids',
		'no_found_rows'       => true,
	);
	$ids    = $sticky ? get_posts( $args + array( 'post__in' => $sticky ) ) : array();
	if ( ! $ids ) {
		$ids = get_posts( $args );
	}
	$id = $ids ? (int) $ids[0] : 0;
	return $id;
}

add_filter(
	'query_loop_block_query_vars',
	static function ( array $vars, WP_Block $block ) {
		$q    = (array) ( $block->context['query'] ?? array() );
		$mode = (string) ( $q['binsider'] ?? '' );

		if ( ! empty( $q['binsiderCat'] ) ) {
			$cat = binsider_cat_id( (string) $q['binsiderCat'] );
			// Missing category → return nothing rather than every post.
			$vars['cat']      = $cat ? $cat : -1;
			$vars['post__in'] = $cat ? ( $vars['post__in'] ?? array() ) : array( 0 );
			unset( $vars['tax_query'] );
		}

		switch ( $mode ) {
			case 'lead':
				$lead               = binsider_lead_post_id();
				$vars['post__in']   = array( $lead ? $lead : 0 );
				$vars['orderby']    = 'post__in';
				$vars['ignore_sticky_posts'] = true;
				break;
			case 'secondary':
				$vars['post__not_in']        = array_merge( (array) ( $vars['post__not_in'] ?? array() ), array( binsider_lead_post_id() ) );
				$vars['ignore_sticky_posts'] = true;
				break;
			case 'related':
				$post_id = (int) get_queried_object_id();
				$primary = $post_id ? binsider_primary_category( $post_id ) : null;
				$vars['post__not_in'] = array( $post_id );
				if ( $primary ) {
					$vars['cat'] = $primary->term_id;
				}
				unset( $vars['tax_query'] );
				break;
		}
		$vars['no_found_rows'] = empty( $q['inherit'] );
		return $vars;
	},
	10,
	2
);
