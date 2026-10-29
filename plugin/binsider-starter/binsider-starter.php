<?php
/**
 * Plugin Name:       Bošnjački Insajder — Početni članci
 * Plugin URI:        https://www.binsider.me
 * Description:       Jednokratni uvoz 25 početnih članaka sa fotografijama (Alati → Početni članci). Nakon uvoza dodatak se može deaktivirati i obrisati — članci ostaju.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Requires Plugins:  binsider-core
 * Author:            Bošnjački Insajder
 * License:           GPL-2.0-or-later
 * Text Domain:       binsider
 */

defined( 'ABSPATH' ) || exit;

define( 'BINSIDER_STARTER_DIR', plugin_dir_path( __FILE__ ) . 'content' );

/**
 * All starter articles from content/articles-*.json.
 */
function binsider_starter_articles(): array {
	$articles = array();
	foreach ( glob( BINSIDER_STARTER_DIR . '/articles-*.json' ) as $file ) {
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( is_array( $data ) ) {
			$articles = array_merge( $articles, $data );
		}
	}
	return $articles;
}

/**
 * Post ID of an already imported starter article (0 if not imported).
 */
function binsider_starter_post_id( string $slug ): int {
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_binsider_starter', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Sideload a bundled image into the media library. Caption = photo credit, alt = description.
 */
function binsider_starter_image( string $rel, string $alt, string $credit, string $source, string $slug ): int {
	$path = BINSIDER_STARTER_DIR . '/' . $rel;
	if ( ! $rel || ! is_file( $path ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$tmp = wp_tempnam( basename( $path ) );
	copy( $path, $tmp );
	$id = media_handle_sideload( array( 'name' => basename( $path ), 'tmp_name' => $tmp ), 0, $alt );
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	wp_update_post( array( 'ID' => $id, 'post_excerpt' => $credit, 'post_content' => esc_url_raw( $source ) ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $id, '_binsider_starter', $slug );
	return (int) $id;
}

/**
 * Block markup for one article.
 */
function binsider_starter_content( array $a ): string {
	$inline = static fn( string $html ): string => wp_kses( $html, array( 'strong' => array(), 'em' => array(), 'a' => array( 'href' => true ) ) );
	$slug   = (string) $a['slug'];
	$c      = '';
	foreach ( (array) ( $a['body'] ?? array() ) as $i => $block ) {
		$text = $inline( (string) ( $block['text'] ?? '' ) );
		switch ( $block['type'] ?? 'p' ) {
			case 'h2':
				$c .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( wp_strip_all_tags( $text ) ) . "</h2>\n<!-- /wp:heading -->\n\n";
				break;
			case 'quote':
				$cite = ! empty( $block['cite'] ) ? '<cite>' . esc_html( $block['cite'] ) . '</cite>' : '';
				$c   .= "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->{$cite}</blockquote>\n<!-- /wp:quote -->\n\n";
				break;
			default:
				$c .= "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->\n\n";
		}
		// Video goes after the first paragraph.
		if ( 0 === $i && ! empty( $a['video'] ) ) {
			$url = esc_url( $a['video'] );
			$cap = ! empty( $a['video_title'] ) ? '<figcaption class="wp-element-caption">Video: ' . esc_html( $a['video_title'] ) . '</figcaption>' : '';
			$c  .= "<!-- wp:embed {\"url\":\"{$url}\",\"type\":\"video\",\"providerNameSlug\":\"youtube\",\"responsive\":true,\"className\":\"wp-embed-aspect-16-9 wp-has-aspect-ratio\"} -->\n<figure class=\"wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio\"><div class=\"wp-block-embed__wrapper\">\n{$url}\n</div>{$cap}</figure>\n<!-- /wp:embed -->\n\n";
		}
	}

	if ( ! empty( $a['gallery'] ) ) {
		$imgs    = '';
		$credits = array();
		foreach ( $a['gallery'] as $g ) {
			$gid = binsider_starter_image( (string) $g['image'], (string) ( $g['caption'] ?? '' ), (string) ( $g['credit'] ?? '' ), (string) ( $g['source'] ?? '' ), $slug );
			if ( ! $gid ) {
				continue;
			}
			$url   = esc_url( wp_get_attachment_image_url( $gid, 'large' ) );
			$alt   = esc_attr( $g['caption'] ?? '' );
			$cap   = esc_html( (string) ( $g['caption'] ?? '' ) );
			$imgs .= "<!-- wp:image {\"id\":{$gid},\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"{$url}\" alt=\"{$alt}\" class=\"wp-image-{$gid}\"/><figcaption class=\"wp-element-caption\">{$cap}</figcaption></figure>\n<!-- /wp:image -->\n";
			if ( ! empty( $g['credit'] ) ) {
				$credits[] = preg_replace( '/^Foto:\s*/u', '', (string) $g['credit'] );
			}
		}
		if ( $imgs ) {
			$gcap = $credits ? '<figcaption class="blocks-gallery-caption wp-element-caption">Fotografije: ' . esc_html( implode( '; ', array_unique( $credits ) ) ) . '</figcaption>' : '';
			$c   .= "<!-- wp:gallery {\"linkTo\":\"none\"} -->\n<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\">{$imgs}{$gcap}</figure>\n<!-- /wp:gallery -->\n\n";
		}
	}

	if ( ! empty( $a['sources'] ) ) {
		$links = array();
		foreach ( $a['sources'] as $src ) {
			$host    = preg_replace( '/^www\./', '', (string) wp_parse_url( $src, PHP_URL_HOST ) );
			$links[] = sprintf( '<a href="%s" rel="nofollow noopener">%s</a>', esc_url( $src ), esc_html( $host ) );
		}
		$c .= "<!-- wp:paragraph {\"className\":\"bi-sources\"} -->\n<p class=\"bi-sources\"><em>Izvori: " . implode( ', ', $links ) . "</em></p>\n<!-- /wp:paragraph -->\n";
	}
	return $c;
}

/**
 * Imports one article (skips it if already imported). Returns the post ID, 0 on failure.
 */
function binsider_starter_import_article( array $a, int $author ): int {
	$slug = sanitize_title( (string) ( $a['slug'] ?? $a['title'] ) );
	$a['slug'] = $slug;
	$existing  = binsider_starter_post_id( $slug );
	if ( $existing ) {
		return $existing;
	}

	$thumb = binsider_starter_image( (string) ( $a['image'] ?? '' ), (string) ( $a['image_caption'] ?? $a['title'] ), (string) ( $a['image_credit'] ?? '' ), (string) ( $a['image_source'] ?? '' ), $slug );
	$cats  = function_exists( 'binsider_cat_id' ) ? array_values( array_filter( array_map( 'binsider_cat_id', (array) ( $a['categories'] ?? array() ) ) ) ) : array();
	$tz    = wp_timezone();
	$when  = date_create_immutable( (string) ( $a['date'] ?? 'now' ), $tz ) ?: date_create_immutable( 'now', $tz );

	$id = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_name'     => $slug,
			'post_title'    => (string) $a['title'],
			'post_excerpt'  => (string) ( $a['excerpt'] ?? '' ),
			'post_content'  => binsider_starter_content( $a ),
			'post_category' => $cats,
			'post_author'   => $author,
			'post_date'     => $when->format( 'Y-m-d H:i:s' ),
			'post_date_gmt' => $when->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'tags_input'    => (array) ( $a['tags'] ?? array() ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	update_post_meta( $id, '_binsider_starter', $slug );
	if ( $cats ) {
		update_post_meta( $id, '_yoast_wpseo_primary_category', $cats[0] );
	}
	if ( $thumb ) {
		set_post_thumbnail( $id, $thumb );
		wp_update_post( array( 'ID' => $thumb, 'post_parent' => $id ) );
	}
	if ( ! empty( $a['flags']['breaking'] ) ) {
		update_post_meta( $id, '_binsider_breaking', 1 );
	}
	if ( ! empty( $a['flags']['sticky'] ) && ! get_option( 'sticky_posts' ) ) {
		stick_post( $id ); // Only when the site has no lead story chosen yet.
	}
	return (int) $id;
}

/**
 * Author for imported articles: the "redakcija" user if it exists, otherwise the current user.
 */
function binsider_starter_author(): int {
	$user = get_user_by( 'login', 'redakcija' );
	return $user ? (int) $user->ID : max( 1, get_current_user_id() );
}

/**
 * Imports up to $limit articles that are not imported yet. Returns [imported now, remaining].
 */
function binsider_starter_import_batch( int $limit = 0 ): array {
	$todo = array_values( array_filter( binsider_starter_articles(), static fn( $a ) => ! binsider_starter_post_id( sanitize_title( (string) $a['slug'] ) ) ) );
	$done = 0;
	foreach ( $todo as $a ) {
		if ( $limit && $done >= $limit ) {
			break;
		}
		if ( binsider_starter_import_article( $a, binsider_starter_author() ) ) {
			++$done;
		}
	}
	return array( $done, count( $todo ) - $done );
}

/* ---------- admin: Alati → Početni članci ---------- */

add_action(
	'admin_menu',
	static function () {
		add_management_page( 'Početni članci', 'Početni članci', 'manage_options', 'binsider-starter', 'binsider_starter_page' );
	}
);

function binsider_starter_page(): void {
	$all      = binsider_starter_articles();
	$imported = count( array_filter( $all, static fn( $a ) => binsider_starter_post_id( sanitize_title( (string) $a['slug'] ) ) ) );
	$left     = count( $all ) - $imported;
	?>
	<div class="wrap">
		<h1>Početni članci</h1>
		<?php if ( isset( $_GET['done'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success"><p>Uvoz je završen.</p></div>
		<?php endif; ?>
		<p>Paket sadrži <strong><?php echo (int) count( $all ); ?> članaka</strong> (vijesti, kultura, vjera, nauka, foto galerije i video prilozi) sa fotografijama sa Wikimedia Commons. Potpis i licenca fotografije su ispod svake slike, a izvori na dnu svakog članka.</p>
		<p>Uvezeno: <strong><?php echo (int) $imported; ?></strong> · Preostalo: <strong><?php echo (int) $left; ?></strong></p>
		<?php if ( ! defined( 'BINSIDER_VERSION' ) ) : ?>
			<div class="notice notice-error inline"><p>Prvo aktivirajte dodatak „Bošnjački Insajder — Core" i pokrenite postavljanje (rubrike), pa onda uvoz.</p></div>
		<?php elseif ( $left ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'binsider_starter_import' ); ?>
				<input type="hidden" name="action" value="binsider_starter_import">
				<?php submit_button( 'Uvezi članke', 'primary', 'submit', false ); ?>
				<p class="description">Uvoz traje oko minut (fotografije se obrađuju u serijama). Već uvezeni članci se preskaču, postojeći sadržaj se ne mijenja.</p>
			</form>
		<?php else : ?>
			<p>Svi članci su uvezeni. Ovaj dodatak sada možete deaktivirati i obrisati — članci i fotografije ostaju.</p>
		<?php endif; ?>
	</div>
	<?php
}

// Imports in small batches and redirects to itself, so shared hosting never hits the PHP time limit.
add_action(
	'admin_post_binsider_starter_import',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemate dozvolu.' );
		}
		check_admin_referer( 'binsider_starter_import' );
		if ( ! defined( 'BINSIDER_VERSION' ) ) {
			wp_die( 'Aktivirajte dodatak „Bošnjački Insajder — Core".' );
		}
		[ , $left ] = binsider_starter_import_batch( 3 );
		$next       = $left
			? add_query_arg( array( 'action' => 'binsider_starter_import', '_wpnonce' => wp_create_nonce( 'binsider_starter_import' ) ), admin_url( 'admin-post.php' ) )
			: admin_url( 'tools.php?page=binsider-starter&done=1' );
		wp_safe_redirect( $next );
		exit;
	}
);
