<?php
/**
 * SEO: Google News sitemap and NewsArticle schema (Yoast SEO).
 */

defined( 'ABSPATH' ) || exit;

function binsider_register_news_sitemap_rewrite(): void {
	add_rewrite_rule( '^news-sitemap\.xml$', 'index.php?binsider_news_sitemap=1', 'top' );
}
add_action( 'init', 'binsider_register_news_sitemap_rewrite' );

// Yoast prepends its "([^/]+?)-sitemap([0-9]+)?\.xml$" rule when the option is read (priority 10), which would
// route news-sitemap.xml to Yoast's 404. Put our rule in front of it.
add_filter(
	'option_rewrite_rules',
	static function ( $rules ) {
		return is_array( $rules ) ? array( '^news-sitemap\.xml$' => 'index.php?binsider_news_sitemap=1' ) + $rules : $rules;
	},
	11
);

add_filter(
	'query_vars',
	static function ( array $vars ) {
		$vars[] = 'binsider_news_sitemap';
		return $vars;
	}
);

// Runs before redirect_canonical so the .xml URL is never redirected.
add_action(
	'template_redirect',
	static function () {
		if ( ! get_query_var( 'binsider_news_sitemap' ) ) {
			return;
		}
		$posts = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 1000,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'date_query'          => array( array( 'after' => '48 hours ago', 'column' => 'post_date_gmt' ) ),
			)
		);
		$name = esc_xml( (string) binsider_option( 'news_name' ) );

		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
		foreach ( $posts as $post ) {
			printf(
				"<url><loc>%s</loc><news:news><news:publication><news:name>%s</news:name><news:language>bs</news:language></news:publication><news:publication_date>%s</news:publication_date><news:title>%s</news:title></news:news></url>\n",
				esc_xml( get_permalink( $post ) ),
				$name, // phpcs:ignore WordPress.Security.EscapeOutput
				esc_xml( get_post_time( 'c', true, $post ) ),
				esc_xml( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) )
			);
		}
		echo '</urlset>';
		exit;
	},
	0
);

add_filter(
	'robots_txt',
	static function ( string $output, bool $public ) {
		return $public ? $output . "\nSitemap: " . home_url( '/news-sitemap.xml' ) . "\n" : $output;
	},
	20,
	2
);

// Yoast leaves the homepage without a meta description unless someone types one in its settings;
// fall back to the site tagline (Settings → General) so the front page always has one.
add_filter(
	'wpseo_metadesc',
	static function ( $desc ) {
		if ( ( is_front_page() || is_home() ) && '' === trim( (string) $desc ) ) {
			return get_bloginfo( 'description' );
		}
		return $desc;
	}
);

// Yoast: articles are news articles.
add_filter(
	'wpseo_schema_article_type',
	static function ( $type, $indexable = null ) {
		if ( is_object( $indexable ) && isset( $indexable->object_sub_type ) && 'post' !== $indexable->object_sub_type ) {
			return $type;
		}
		return 'NewsArticle';
	},
	10,
	2
);
