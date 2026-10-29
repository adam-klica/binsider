<?php
/**
 * Dynamic (server-rendered) blocks used by the Bošnjački Insajder theme.
 * Editor side: assets/editor.js registers them with a live preview or placeholder.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block definitions: name => [title, description, attributes, uses post context, render callback].
 */
function binsider_block_definitions(): array {
	return array(
		'logo'          => array( 'BI · Logo', 'Logo portala.', array(
			'variant' => array( 'type' => 'string', 'default' => 'horizontal', 'enum' => array( 'horizontal', 'stacked', 'mark' ) ),
			'color'   => array( 'type' => 'string', 'default' => 'dark', 'enum' => array( 'dark', 'light' ) ),
		), false, 'binsider_render_logo' ),
		'date'          => array( 'BI · Današnji datum', 'Današnji datum na bosanskom.', array(), false, 'binsider_render_date' ),
		'social'        => array( 'BI · Društvene mreže', 'Linkovi ka profilima (Podešavanja → Bošnjački Insajder).', array(), false, 'binsider_render_social' ),
		'sections-nav'  => array( 'BI · Rubrike (meni)', 'Meni sa svim rubrikama portala.', array(
			'variant' => array( 'type' => 'string', 'default' => 'main', 'enum' => array( 'main', 'list' ) ),
		), false, 'binsider_render_sections_nav' ),
		'chat-links'    => array( 'BI · Viber i WhatsApp', 'Dugmad za kontakt preko Vibera i WhatsAppa (broj: Podešavanja → Bošnjački Insajder).', array(), false, 'binsider_render_chat_links' ),
		'pages-nav'     => array( 'BI · Stranice (meni)', 'O nama, Impresum, Kontakt…', array(), false, 'binsider_render_pages_nav' ),
		'ticker'        => array( 'BI · Udarne / najnovije vijesti', 'Traka sa udarnim vijestima (ili najnovijim, ako udarnih nema).', array(
			'count' => array( 'type' => 'number', 'default' => 6 ),
		), false, 'binsider_render_ticker' ),
		'most-read'     => array( 'BI · Najčitanije', 'Najčitaniji članci (Post Views Counter).', array(
			'title' => array( 'type' => 'string', 'default' => 'Najčitanije' ),
			'count' => array( 'type' => 'number', 'default' => 5 ),
			'days'  => array( 'type' => 'number', 'default' => 7 ),
		), false, 'binsider_render_most_read' ),
		'section-title' => array( 'BI · Naslov rubrike', 'Naslov sekcije sa linkom ka rubrici.', array(
			'slug'  => array( 'type' => 'string', 'default' => 'crna-gora' ),
			'label' => array( 'type' => 'string', 'default' => '' ),
		), false, 'binsider_render_section_title' ),
		'form'          => array( 'BI · Forma', 'Kontakt forma ili forma „Pošalji vijest" (Contact Form 7).', array(
			'form' => array( 'type' => 'string', 'default' => 'kontakt', 'enum' => array( 'kontakt', 'vijest' ) ),
		), false, 'binsider_render_form' ),
		'post-label'    => array( 'BI · Rubrika članka', 'Glavna rubrika članka (+ oznaka UDARNO).', array(), true, 'binsider_render_post_label' ),
		'post-byline'   => array( 'BI · Autor i datum', 'Autor, datum i vrijeme čitanja.', array(
			'mode' => array( 'type' => 'string', 'default' => 'short', 'enum' => array( 'short', 'full' ) ),
		), true, 'binsider_render_post_byline' ),
		'post-lead'     => array( 'BI · Uvod (lead)', 'Uvodni pasus članka (polje „Sažetak").', array(), true, 'binsider_render_post_lead' ),
		'featured'      => array( 'BI · Naslovna fotografija', 'Istaknuta slika sa potpisom.', array(), true, 'binsider_render_featured' ),
		'share'         => array( 'BI · Podijeli', 'Dugmad za dijeljenje članka.', array(), true, 'binsider_render_share' ),
		'author-box'    => array( 'BI · O autoru', 'Kratka biografija autora.', array(), true, 'binsider_render_author_box' ),
	);
}

add_action(
	'init',
	static function () {
		$asset = include BINSIDER_DIR . 'assets/editor.asset.php';
		wp_register_script( 'binsider-editor', BINSIDER_URL . 'assets/editor.js', $asset['dependencies'], $asset['version'], true );
		wp_register_script( 'binsider-share', BINSIDER_URL . 'assets/share.js', array(), BINSIDER_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

		$meta = array();
		foreach ( binsider_block_definitions() as $name => [ $title, $description, $attributes, $post_context, $render ] ) {
			register_block_type(
				"binsider/{$name}",
				array(
					'api_version'          => 3,
					'title'                => $title,
					'description'          => $description,
					'category'             => 'theme',
					'icon'                 => 'layout',
					'attributes'           => $attributes,
					'uses_context'         => $post_context ? array( 'postId', 'postType' ) : array(),
					'supports'             => array( 'html' => false, 'className' => true ),
					'render_callback'      => $render,
					'editor_script_handles' => array( 'binsider-editor' ),
				)
			);
			$meta[ "binsider/{$name}" ] = array( 'postContext' => $post_context, 'attributes' => (object) $attributes );
		}
		wp_add_inline_script( 'binsider-editor', 'window.binsiderBlocks = ' . wp_json_encode( $meta ) . ';', 'before' );
	}
);

// Editor sidebar panel (Udarna vijest) also needs the script in the post editor.
add_action(
	'enqueue_block_editor_assets',
	static function () {
		wp_enqueue_script( 'binsider-editor' );
	}
);

/* ---------- helpers ---------- */

function binsider_wrapper( string $class, string $inner, string $tag = 'div', array $extra = array() ): string {
	$attrs = get_block_wrapper_attributes( array_merge( array( 'class' => $class ), $extra ) );
	return "<{$tag} {$attrs}>{$inner}</{$tag}>";
}

function binsider_block_post_id( WP_Block $block ): int {
	return (int) ( $block->context['postId'] ?? get_the_ID() );
}

/* ---------- site blocks ---------- */

function binsider_render_logo( array $attrs ): string {
	$variant = in_array( $attrs['variant'] ?? '', array( 'horizontal', 'stacked', 'mark' ), true ) ? $attrs['variant'] : 'horizontal';
	$file    = 'mark' === $variant ? 'logo-mark.svg' : "logo-{$variant}" . ( 'light' === ( $attrs['color'] ?? '' ) ? '-white' : '' ) . '.svg';
	// An <img> instead of inline SVG: the logo is ~13 KB of outlined text, cached once instead of sent in every page.
	$path = BINSIDER_DIR . 'assets/logo/' . $file;
	$head = (string) file_get_contents( $path, false, null, 0, 300 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	preg_match( '/viewBox="0 0 ([\d.]+) ([\d.]+)"/', $head, $m );
	$img  = sprintf(
		'<img src="%s" width="%d" height="%d" alt="%s" decoding="async"%s>',
		esc_url( BINSIDER_URL . 'assets/logo/' . $file . '?v=' . filemtime( $path ) ),
		(int) ( $m[1] ?? 100 ),
		(int) ( $m[2] ?? 100 ),
		esc_attr( get_bloginfo( 'name' ) ),
		'mark' === $variant ? ' loading="lazy"' : ''
	);
	$link = sprintf( '<a href="%s" rel="home">%s</a>', esc_url( home_url( '/' ) ), $img );
	$tag  = ( is_front_page() && ! is_paged() && 'mark' !== $variant ) ? 'h1' : 'div';
	return binsider_wrapper( "bi-logo bi-logo--{$variant}", $link, $tag );
}

function binsider_render_date(): string {
	$date = binsider_date( 'l, j. F Y.' );
	return binsider_wrapper( 'bi-date', esc_html( $date ), 'p' );
}

function binsider_render_social(): string {
	$profiles = binsider_social_profiles();
	if ( ! $profiles ) {
		return '';
	}
	$items = '';
	foreach ( $profiles as $key => [ $label, $url ] ) {
		$items .= sprintf( '<li><a href="%s" rel="noopener me" target="_blank" class="bi-social__%s" title="%s">%s<span class="bi-social__label">%s</span></a></li>', esc_url( $url ), esc_attr( $key ), esc_attr( $label ), binsider_icon( $key ), esc_html( $label ) );
	}
	return binsider_wrapper( 'bi-social', "<ul>{$items}</ul>" );
}

function binsider_render_sections_nav( array $attrs ): string {
	$variant = 'list' === ( $attrs['variant'] ?? '' ) ? 'list' : 'main';
	$current = is_category() ? get_queried_object_id() : ( is_single() && get_the_ID() ? ( binsider_primary_category( (int) get_the_ID() )?->term_id ?? 0 ) : 0 );
	$items   = '';
	if ( 'main' === $variant ) {
		$items .= sprintf( '<li><a href="%s"%s>Početna</a></li>', esc_url( home_url( '/' ) ), is_front_page() ? ' aria-current="page"' : '' );
	}
	foreach ( binsider_sections() as $slug => [ $name, $short ] ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( ! $term ) {
			continue;
		}
		$is_current = (int) $term->term_id === (int) $current;
		$items     .= sprintf(
			'<li><a href="%s"%s>%s</a></li>',
			esc_url( get_category_link( $term ) ),
			$is_current ? ' aria-current="page"' : '',
			esc_html( 'main' === $variant ? $short : $name )
		);
	}
	return binsider_wrapper( "bi-sections bi-sections--{$variant}", "<ul>{$items}</ul>", 'nav', array( 'aria-label' => 'Rubrike' ) );
}

function binsider_render_chat_links(): string {
	$items = '';
	foreach ( binsider_chat_links() as $key => [ $label, $href ] ) {
		$items .= sprintf( '<a class="bi-chat bi-chat--%s" href="%s" rel="noopener">%s<span>%s</span></a>', esc_attr( $key ), esc_url( $href, array( 'https', 'viber' ) ), binsider_icon( $key, 20 ), esc_html( $label ) );
	}
	return $items ? binsider_wrapper( 'bi-chat-links', $items ) : '';
}

function binsider_render_pages_nav(): string {
	$items = '';
	foreach ( array( 'o-nama', 'impresum', 'kontakt', 'posalji-vijest', 'politika-privatnosti', 'uslovi-koristenja' ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page && 'publish' === $page->post_status ) {
			$items .= sprintf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $page ) ), esc_html( get_the_title( $page ) ) );
		}
	}
	return $items ? binsider_wrapper( 'bi-pages', "<ul>{$items}</ul>", 'nav', array( 'aria-label' => 'Informacije' ) ) : '';
}

function binsider_render_ticker( array $attrs ): string {
	$count    = max( 1, (int) ( $attrs['count'] ?? 6 ) );
	$posts    = binsider_breaking_posts( $count );
	$breaking = (bool) $posts;
	if ( ! $posts ) {
		$posts = get_posts( array( 'posts_per_page' => $count, 'ignore_sticky_posts' => true ) );
	}
	if ( ! $posts ) {
		return '';
	}
	$list = '';
	foreach ( $posts as $post ) {
		$time  = esc_html( get_the_time( 'H:i', $post ) );
		$list .= sprintf( '<li><time>%s</time> <a href="%s">%s</a></li>', $time, esc_url( get_permalink( $post ) ), esc_html( get_the_title( $post ) ) );
	}
	$label = $breaking ? 'Udarno' : 'Najnovije';
	$inner = sprintf(
		'<p class="bi-ticker__label">%s</p><div class="bi-ticker__viewport"><ul class="bi-ticker__track">%s</ul><ul class="bi-ticker__track" aria-hidden="true">%s</ul></div>',
		esc_html( $label ),
		$list,
		str_replace( '<a ', '<a tabindex="-1" ', $list )
	);
	return binsider_wrapper( 'bi-ticker' . ( $breaking ? ' is-breaking' : '' ), $inner, 'div', array( 'aria-label' => $label ) );
}

function binsider_render_most_read( array $attrs ): string {
	$count = max( 1, (int) ( $attrs['count'] ?? 5 ) );
	$days  = max( 1, (int) ( $attrs['days'] ?? 7 ) );
	$args  = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( function_exists( 'pvc_get_post_views' ) ) {
		$args['orderby'] = 'post_views';
		$args['order']   = 'DESC';
	}
	$query = new WP_Query( $args + array( 'date_query' => array( array( 'after' => "{$days} days ago" ) ) ) );
	if ( $query->post_count < $count ) {
		$query = new WP_Query( $args );
	}
	if ( ! $query->have_posts() ) {
		return '';
	}
	$items = '';
	foreach ( $query->posts as $i => $post ) {
		$items .= sprintf(
			'<li><span class="bi-most-read__n" aria-hidden="true">%d</span><a href="%s">%s</a></li>',
			$i + 1,
			esc_url( get_permalink( $post ) ),
			esc_html( get_the_title( $post ) )
		);
	}
	$title = esc_html( $attrs['title'] ?? 'Najčitanije' );
	return binsider_wrapper( 'bi-most-read', "<h2 class=\"bi-most-read__title\">{$title}</h2><ol>{$items}</ol>", 'section' );
}

function binsider_render_section_title( array $attrs ): string {
	$term = get_term_by( 'slug', (string) ( $attrs['slug'] ?? '' ), 'category' );
	if ( ! $term ) {
		return '';
	}
	$url   = esc_url( get_category_link( $term ) );
	$label = esc_html( ( $attrs['label'] ?? '' ) ? $attrs['label'] : $term->name );
	$inner = "<h2 class=\"bi-section-title__h\"><a href=\"{$url}\">{$label}</a></h2><a class=\"bi-section-title__more\" href=\"{$url}\" aria-label=\"Sve vijesti: {$label}\">Pogledaj sve</a>";
	return binsider_wrapper( 'bi-section-title', $inner );
}

function binsider_render_form( array $attrs ): string {
	$id = binsider_form_id( (string) ( $attrs['form'] ?? 'kontakt' ) );
	if ( ! $id ) {
		return current_user_can( 'manage_options' ) ? binsider_wrapper( 'bi-notice', 'Forma nije dostupna — aktivirajte dodatak „Contact Form 7".', 'p' ) : '';
	}
	// CF7's scripts and styles are off site-wide (see seo.php); load them only where a form is rendered.
	if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
		wpcf7_enqueue_scripts();
		wpcf7_enqueue_styles();
	}
	return binsider_wrapper( 'bi-form', do_shortcode( sprintf( '[contact-form-7 id="%d"]', $id ) ) );
}

/* ---------- post blocks ---------- */

function binsider_render_post_label( array $attrs, string $content, WP_Block $block ): string {
	$post_id = binsider_block_post_id( $block );
	$out     = '';
	if ( binsider_is_breaking( $post_id ) ) {
		$out .= '<span class="bi-label bi-label--breaking">Udarno</span>';
	}
	$term = binsider_primary_category( $post_id );
	if ( $term ) {
		$out .= sprintf( '<a class="bi-label" href="%s">%s</a>', esc_url( get_category_link( $term ) ), esc_html( $term->name ) );
	}
	return $out ? binsider_wrapper( 'bi-post-label', $out ) : '';
}

function binsider_render_post_byline( array $attrs, string $content, WP_Block $block ): string {
	$post_id = binsider_block_post_id( $block );
	$post    = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	$ts   = (int) get_post_time( 'U', true, $post );
	$iso  = esc_attr( get_post_time( 'c', true, $post ) );
	$diff = time() - $ts;
	if ( 'full' !== ( $attrs['mode'] ?? 'short' ) ) {
		$when = $diff >= 0 && $diff < DAY_IN_SECONDS ? 'prije ' . human_time_diff( $ts ) : binsider_date( 'j. F Y.', $ts );
		return binsider_wrapper( 'bi-byline bi-byline--short', "<time datetime=\"{$iso}\">" . esc_html( $when ) . '</time>', 'p' );
	}
	$author   = sprintf( '<a class="bi-byline__author" href="%s">%s</a>', esc_url( get_author_posts_url( (int) $post->post_author ) ), esc_html( get_the_author_meta( 'display_name', (int) $post->post_author ) ) );
	$date     = sprintf( '<time datetime="%s">%s</time>', $iso, esc_html( binsider_date( 'j. F Y. \u H:i', $ts ) ) );
	$modified = (int) get_post_modified_time( 'U', true, $post );
	$updated  = $modified - $ts > HOUR_IN_SECONDS
		? sprintf( '<span>Ažurirano: <time datetime="%s">%s</time></span>', esc_attr( get_post_modified_time( 'c', true, $post ) ), esc_html( binsider_date( 'j. F Y. \u H:i', $modified ) ) )
		: '';
	$read     = sprintf( '<span>%d min čitanja</span>', binsider_reading_time( $post_id ) );
	return binsider_wrapper( 'bi-byline bi-byline--full', "<span>Piše: {$author}</span>{$date}{$updated}{$read}", 'p' );
}

function binsider_render_post_lead( array $attrs, string $content, WP_Block $block ): string {
	$post_id = binsider_block_post_id( $block );
	if ( ! has_excerpt( $post_id ) ) {
		return '';
	}
	return binsider_wrapper( 'bi-lead', esc_html( get_the_excerpt( $post_id ) ), 'p' );
}

function binsider_render_featured( array $attrs, string $content, WP_Block $block ): string {
	$post_id  = binsider_block_post_id( $block );
	$thumb_id = (int) get_post_thumbnail_id( $post_id );
	if ( ! $thumb_id ) {
		return '';
	}
	$img     = wp_get_attachment_image(
		$thumb_id,
		'full',
		false,
		array(
			'fetchpriority' => 'high',
			'loading'       => 'eager',
			'decoding'      => 'async',
			'sizes'         => '(max-width: 1100px) 100vw, 1100px',
		)
	);
	$caption = wp_get_attachment_caption( $thumb_id );
	$cap     = $caption ? '<figcaption>' . wp_kses_post( $caption ) . '</figcaption>' : '';
	return binsider_wrapper( 'bi-featured', $img . $cap, 'figure' );
}

function binsider_render_share( array $attrs, string $content, WP_Block $block ): string {
	$post_id = binsider_block_post_id( $block );
	$url     = rawurlencode( get_permalink( $post_id ) );
	$title   = rawurlencode( html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) );
	$links   = array(
		'facebook' => array( 'Facebook', "https://www.facebook.com/sharer/sharer.php?u={$url}" ),
		'whatsapp' => array( 'WhatsApp', "https://wa.me/?text={$title}%20{$url}" ),
		'viber'    => array( 'Viber', "viber://forward?text={$title}%20{$url}" ),
		'x'        => array( 'X', "https://x.com/intent/post?url={$url}&text={$title}" ),
		'telegram' => array( 'Telegram', "https://t.me/share/url?url={$url}&text={$title}" ),
	);
	$items   = '';
	foreach ( $links as $key => [ $label, $href ] ) {
		$items .= sprintf( '<li><a class="bi-share__%s" href="%s" target="_blank" rel="noopener nofollow">%s<span>%s</span></a></li>', esc_attr( $key ), esc_url( $href, array( 'https', 'viber' ) ), binsider_icon( $key ), esc_html( $label ) );
	}
	$items .= sprintf( '<li><button type="button" class="bi-share__copy" data-url="%s" data-done="Kopirano ✓">Kopiraj link</button></li>', esc_attr( get_permalink( $post_id ) ) );
	wp_enqueue_script( 'binsider-share' );
	return binsider_wrapper( 'bi-share', "<p class=\"bi-share__title\">Podijeli</p><ul>{$items}</ul>" );
}

function binsider_render_author_box( array $attrs, string $content, WP_Block $block ): string {
	$post = get_post( binsider_block_post_id( $block ) );
	if ( ! $post ) {
		return '';
	}
	$id   = (int) $post->post_author;
	$name = esc_html( get_the_author_meta( 'display_name', $id ) );
	$bio  = get_the_author_meta( 'description', $id );
	$url  = esc_url( get_author_posts_url( $id ) );
	$bio  = $bio ? '<p>' . esc_html( $bio ) . '</p>' : '';
	return binsider_wrapper( 'bi-author', "<p class=\"bi-author__label\">Autor</p><p class=\"bi-author__name\"><a href=\"{$url}\">{$name}</a></p>{$bio}<a class=\"bi-author__more\" href=\"{$url}\">Svi tekstovi autora →</a>", 'aside' );
}
