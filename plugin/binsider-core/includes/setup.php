<?php
/**
 * One-time / idempotent site setup: sections, pages, forms, brand images, defaults.
 * Safe to run on a site that already has content — it only creates what is missing.
 */

defined( 'ABSPATH' ) || exit;

const BINSIDER_SETUP_VERSION = 1;

function binsider_activate(): void {
	binsider_run_setup();
	binsider_register_news_sitemap_rewrite();
	flush_rewrite_rules();
}

// Re-run after a plugin update that bumps BINSIDER_SETUP_VERSION.
add_action(
	'admin_init',
	static function () {
		if ( (int) get_option( 'binsider_setup_version' ) < BINSIDER_SETUP_VERSION && current_user_can( 'manage_options' ) ) {
			binsider_run_setup();
			flush_rewrite_rules();
		}
	}
);

function binsider_run_setup(): array {
	$log = array();

	// Sections (categories).
	foreach ( binsider_sections() as $slug => [ $name, , $description ] ) {
		if ( ! term_exists( $slug, 'category' ) ) {
			$res   = wp_insert_term( $name, 'category', array( 'slug' => $slug, 'description' => $description ) );
			$log[] = is_wp_error( $res ) ? "Rubrika {$name}: " . $res->get_error_message() : "Rubrika kreirana: {$name}";
		}
	}

	// Default category gets a Bosnian name.
	$default = get_term( (int) get_option( 'default_category' ), 'category' );
	if ( $default instanceof WP_Term && in_array( $default->slug, array( 'uncategorized', 'bez-kategorije' ), true ) && 'Vijesti' !== $default->name ) {
		wp_update_term( $default->term_id, 'category', array( 'name' => 'Vijesti', 'slug' => 'vijesti' ) );
		$log[] = 'Podrazumijevana rubrika preimenovana u „Vijesti".';
	}

	// Pages.
	foreach ( binsider_pages() as $slug => [ $title, $content ] ) {
		if ( ! get_page_by_path( $slug ) ) {
			wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => $title,
					'post_name'      => $slug,
					'post_content'   => $content,
					'comment_status' => 'closed',
				)
			);
			$log[] = "Stranica kreirana: {$title}";
		}
	}

	// Homepage = front-page template; "Najnovije" = paginated list of all news.
	if ( 'posts' === get_option( 'show_on_front' ) ) {
		$front = get_page_by_path( 'naslovna' );
		$posts = get_page_by_path( 'najnovije' );
		if ( $front && $posts ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $front->ID );
			update_option( 'page_for_posts', $posts->ID );
			$log[] = 'Naslovna i stranica „Najnovije" podešene.';
		}
	}

	// Default WordPress sample content → trash (reversible), only if untouched.
	$hello = get_post( 1 );
	if ( $hello && 'post' === $hello->post_type && 'publish' === $hello->post_status && $hello->post_modified === $hello->post_date && in_array( $hello->post_name, array( 'hello-world', 'zdravo-svijete' ), true ) ) {
		wp_trash_post( 1 );
		$log[] = 'Probni članak „Hello world" premješten u otpad.';
	}
	$sample = get_post( 2 );
	if ( $sample && 'page' === $sample->post_type && 'publish' === $sample->post_status && $sample->post_modified === $sample->post_date && in_array( $sample->post_name, array( 'sample-page', 'primjer-stranice' ), true ) ) {
		wp_trash_post( 2 );
		$log[] = 'Probna stranica premještena u otpad.';
	}

	// Brand images in the media library → site icon + default share image.
	$icon_id = binsider_import_brand_image( 'site-icon-512.png', 'Bošnjački Insajder — ikona' );
	if ( $icon_id && ! get_option( 'site_icon' ) ) {
		update_option( 'site_icon', $icon_id );
		$log[] = 'Ikona sajta postavljena.';
	}
	$og_id = binsider_import_brand_image( 'og-default-1200x630.jpg', 'Bošnjački Insajder — slika za dijeljenje' );
	if ( $og_id && defined( 'WPSEO_VERSION' ) ) {
		$social = (array) get_option( 'wpseo_social', array() );
		if ( empty( $social['og_default_image'] ) ) {
			$social['og_default_image']    = wp_get_attachment_url( $og_id );
			$social['og_default_image_id'] = $og_id;
			update_option( 'wpseo_social', $social );
			$log[] = 'Yoast: podrazumijevana slika za dijeljenje postavljena.';
		}
		$titles = (array) get_option( 'wpseo_titles', array() );
		if ( empty( $titles['company_name'] ) ) {
			$titles['company_or_person'] = 'company';
			$titles['company_name']      = 'Bošnjački Insajder';
			$titles['company_logo']      = wp_get_attachment_url( $icon_id );
			$titles['company_logo_id']   = $icon_id;
			update_option( 'wpseo_titles', $titles );
			$log[] = 'Yoast: podaci o organizaciji postavljeni.';
		}
	}

	// Post Views Counter: count views but don't print "Views: N" under articles (the theme shows Najčitanije).
	$pvc = get_option( 'post_views_counter_settings_display' );
	if ( is_array( $pvc ) && 'manual' !== ( $pvc['position'] ?? '' ) ) {
		$pvc['position'] = 'manual';
		update_option( 'post_views_counter_settings_display', $pvc );
		$log[] = 'Post Views Counter: brojač pregleda sakriven ispod članaka.';
	}

	// LiteSpeed Cache (Hostinger): shorter cache for a news site + safe front-end optimisations.
	// Only values still at LiteSpeed's factory default are changed; saved through LiteSpeed's own API
	// so it also rewrites its .htaccess rules (browser cache).
	if ( class_exists( '\LiteSpeed\Conf' ) && method_exists( '\LiteSpeed\Conf', 'cls' ) ) {
		$want    = array(
			'cache-ttl_pub'           => array( 604800, 3600 ),
			'cache-ttl_frontpage'     => array( 604800, 300 ),
			'cache-ttl_feed'          => array( 604800, 3600 ),
			'cache-browser'           => array( 0, 1 ),
			'purge-upgrade'           => array( 0, 1 ),
			'optm-css_min'            => array( 0, 1 ),
			'optm-js_min'             => array( 0, 1 ),
			'optm-html_min'           => array( 0, 1 ),
			'optm-emoji_rm'           => array( 0, 1 ),
			'media-add_missing_sizes' => array( 0, 1 ),
		);
		$changes = array();
		foreach ( $want as $key => [ $default, $value ] ) {
			$current = get_option( "litespeed.conf.{$key}" );
			if ( false !== $current && (int) $current === $default ) {
				$changes[ $key ] = $value;
			}
		}
		if ( $changes ) {
			\LiteSpeed\Conf::cls()->update_confs( $changes );
			$log[] = 'LiteSpeed Cache: keš 1 h (naslovna 5 min), minifikacija CSS/JS/HTML, keš u pregledaču.';
		}
	}

	// Forms (Contact Form 7, when active).
	foreach ( array( 'kontakt', 'vijest' ) as $form ) {
		if ( binsider_form_id( $form ) ) {
			$log[] = "Forma spremna: {$form}";
		}
	}

	// Sensible defaults on a fresh install only.
	if ( 'UTC' === get_option( 'timezone_string' ) || '' === get_option( 'timezone_string' ) ) {
		update_option( 'timezone_string', 'Europe/Podgorica' );
	}
	if ( ! get_option( 'binsider_setup_version' ) ) {
		update_option( 'date_format', 'j. F Y.' );
		update_option( 'time_format', 'H:i' );
		update_option( 'start_of_week', 1 );
		update_option( 'default_comment_status', 'closed' );
		update_option( 'default_ping_status', 'closed' );
		update_option( 'blogdescription', 'Nezavisni portal Bošnjaka Crne Gore, regiona i dijaspore' );
	}

	// Permalinks /<rubrika>/<naslov>/ — only on a fresh site, never break existing links.
	$want = '/%category%/%postname%/';
	if ( get_option( 'permalink_structure' ) !== $want && (int) wp_count_posts( 'post' )->publish <= 1 ) {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( $want );
		$log[] = 'Trajne veze postavljene na /rubrika/naslov/.';
	}

	update_option( 'binsider_setup_version', BINSIDER_SETUP_VERSION );
	update_option( 'binsider_setup_log', array( 'time' => time(), 'log' => $log ) );
	return $log;
}

/**
 * Copies a bundled brand image into the media library and returns its attachment ID.
 * Re-imports when the bundled file changes (new logo in a plugin update) and moves the
 * site icon / Yoast share image over to the new attachment if they still used the old one.
 */
function binsider_import_brand_image( string $file, string $title ): int {
	$ids    = (array) get_option( 'binsider_brand_media', array() );
	$src    = BINSIDER_DIR . 'assets/logo/' . $file;
	$hash   = is_readable( $src ) ? md5_file( $src ) : '';
	$old_id = ! empty( $ids[ $file ] ) && get_post( (int) $ids[ $file ] ) ? (int) $ids[ $file ] : 0;
	if ( $old_id ) {
		$stored = wp_get_original_image_path( $old_id ) ?: get_attached_file( $old_id );
		if ( ! $hash || ( $stored && is_readable( $stored ) && md5_file( $stored ) === $hash ) ) {
			return $old_id;
		}
	}
	if ( ! $hash ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$tmp = wp_tempnam( $file );
	copy( $src, $tmp );
	$id = media_handle_sideload( array( 'name' => 'binsider-' . $file, 'tmp_name' => $tmp ), 0, $title );
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	if ( $old_id ) {
		if ( (int) get_option( 'site_icon' ) === $old_id ) {
			update_option( 'site_icon', $id );
		}
		$social = (array) get_option( 'wpseo_social', array() );
		if ( (int) ( $social['og_default_image_id'] ?? 0 ) === $old_id ) {
			$social['og_default_image']    = wp_get_attachment_url( $id );
			$social['og_default_image_id'] = $id;
			update_option( 'wpseo_social', $social );
		}
		$titles = (array) get_option( 'wpseo_titles', array() );
		if ( (int) ( $titles['company_logo_id'] ?? 0 ) === $old_id ) {
			$titles['company_logo']    = wp_get_attachment_url( $id );
			$titles['company_logo_id'] = $id;
			update_option( 'wpseo_titles', $titles );
		}
	}
	update_post_meta( $id, '_wp_attachment_image_alt', 'Bošnjački Insajder' );
	$ids[ $file ] = $id;
	update_option( 'binsider_brand_media', $ids );
	return (int) $id;
}

/**
 * Contact Form 7 form ID for "kontakt" or "vijest"; creates the form on first use.
 */
function binsider_form_id( string $key ): int {
	if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
		return 0;
	}
	$ids = (array) get_option( 'binsider_forms', array() );
	if ( ! empty( $ids[ $key ] ) && get_post( (int) $ids[ $key ] ) ) {
		return (int) $ids[ $key ];
	}

	$consent = '[acceptance saglasnost] Saglasan/saglasna sam da se moji podaci koriste isključivo za odgovor na ovu poruku, u skladu sa <a href="/politika-privatnosti/">Politikom privatnosti</a>. [/acceptance]';
	$forms   = array(
		'kontakt' => array(
			'title' => 'Kontakt',
			'form'  => "<p><label>Ime i prezime *<br>[text* ime autocomplete:name]</label></p>\n<p><label>E-mail *<br>[email* email autocomplete:email]</label></p>\n<p><label>Naslov<br>[text naslov]</label></p>\n<p><label>Poruka *<br>[textarea* poruka]</label></p>\n<p>{$consent}</p>\n<p>[submit \"Pošalji poruku\"]</p>",
			'subj'  => '[_site_title] Kontakt: [naslov]',
			'body'  => "Od: [ime] <[email]>\nNaslov: [naslov]\n\n[poruka]\n\n-- \nPoslano sa kontakt forme: [_site_url]",
			'att'   => '',
		),
		'vijest'  => array(
			'title' => 'Pošalji vijest',
			'form'  => "<p><label>Ime i prezime (ili nadimak) *<br>[text* ime autocomplete:name]</label></p>\n<p><label>E-mail ili telefon *<br>[text* kontakt]</label></p>\n<p><label>Mjesto događaja<br>[text mjesto]</label></p>\n<p><label>Šta se desilo? *<br>[textarea* vijest]</label></p>\n<p><label>Fotografija ili dokument (JPG, PNG, PDF, do 10 MB)<br>[file prilog limit:10mb filetypes:jpg|jpeg|png|webp|pdf]</label></p>\n<p>[checkbox anonimno \"Želim ostati anoniman/anonimna\"]</p>\n<p>{$consent}</p>\n<p>[submit \"Pošalji vijest\"]</p>",
			'subj'  => '[_site_title] Nova dojava: [mjesto]',
			'body'  => "Pošiljalac: [ime]\nKontakt: [kontakt]\nMjesto: [mjesto]\nAnonimno: [anonimno]\n\n[vijest]\n\n-- \nPoslano sa forme „Pošalji vijest\": [_site_url]",
			'att'   => '[prilog]',
		),
	);
	if ( ! isset( $forms[ $key ] ) ) {
		return 0;
	}
	$def  = $forms[ $key ];
	$form = WPCF7_ContactForm::get_template( array( 'title' => $def['title'] ) );
	$mail = $form->prop( 'mail' );
	$mail = array_merge(
		is_array( $mail ) ? $mail : array(),
		array(
			'active'             => true,
			'subject'            => $def['subj'],
			'recipient'          => '[_site_admin_email]',
			'body'               => $def['body'],
			'additional_headers' => 'kontakt' === $key ? 'Reply-To: [email]' : '',
			'attachments'        => $def['att'],
			'use_html'           => false,
		)
	);
	$form->set_properties(
		array(
			'form'     => $def['form'],
			'mail'     => $mail,
			'mail_2'   => array( 'active' => false ),
			'messages' => array_merge(
				(array) $form->prop( 'messages' ),
				array(
					'mail_sent_ok'     => 'Hvala! Vaša poruka je poslana.',
					'mail_sent_ng'     => 'Došlo je do greške pri slanju. Pokušajte ponovo kasnije.',
					'validation_error' => 'Jedno ili više polja nije ispravno popunjeno.',
					'spam'             => 'Došlo je do greške pri slanju. Pokušajte ponovo kasnije.',
					'accept_terms'     => 'Morate prihvatiti uslove prije slanja.',
					'invalid_required' => 'Ovo polje je obavezno.',
					'invalid_email'    => 'Unesite ispravnu e-mail adresu.',
				)
			),
		)
	);
	$form->save();
	$ids[ $key ] = (int) $form->id();
	update_option( 'binsider_forms', $ids );
	return (int) $form->id();
}

/**
 * Pages created on setup: slug => [title, block content].
 */
function binsider_pages(): array {
	$p = static fn( string $text ): string => "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->\n\n";
	$h = static fn( string $text ): string => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">{$text}</h2>\n<!-- /wp:heading -->\n\n";
	$list = static function ( array $items ): string {
		$li = '';
		foreach ( $items as $item ) {
			$li .= "<!-- wp:list-item -->\n<li>{$item}</li>\n<!-- /wp:list-item -->\n";
		}
		return "<!-- wp:list -->\n<ul class=\"wp-block-list\">{$li}</ul>\n<!-- /wp:list -->\n\n";
	};

	return array(
		'naslovna'            => array( 'Naslovna', '' ),
		'najnovije'           => array( 'Najnovije', '' ),
		'o-nama'              => array(
			'O nama',
			$p( '<strong>Bošnjački Insajder</strong> je savremeni elektronski medij namijenjen informisanju bošnjačke zajednice u Crnoj Gori, regionu i dijaspori.' )
			. $p( 'Naš cilj je pravovremeno, tačno i objektivno izvještavanje — o temama od značaja za Bošnjake, ali i o širim društvenim, kulturnim, političkim i ekonomskim dešavanjima u zemlji i svijetu.' )
			. $h( 'Šta pratimo' )
			. $list( array( 'Vijesti iz Crne Gore, Sandžaka, Bosne i Hercegovine, dijaspore i svijeta', 'Politiku i društvo, ekonomiju i biznis', 'Kulturu i tradiciju, vjeru i zajednicu', 'Nauku i obrazovanje, sport i zanimljivosti', 'Intervjue i aktuelne stavove', 'Video priloge, foto galerije i dokumente' ) )
			. $h( 'Naši principi' )
			. $p( 'Radimo u skladu sa profesionalnim i etičkim standardima novinarstva i Kodeksom novinara i novinarki Crne Gore. Provjeravamo informacije, odvajamo činjenice od komentara i ispravljamo greške javno i bez odlaganja.' )
			. $p( 'Pratite nas i na društvenim mrežama, gdje svakodnevno objavljujemo video priloge, intervjue, foto galerije i kratke informativne formate.' ),
		),
		'impresum'            => array(
			'Impresum',
			$p( '<strong>Bošnjački Insajder</strong> — elektronska publikacija<br>Internet adresa: <a href="https://www.binsider.me">www.binsider.me</a>' )
			. $p( '<strong>Osnivač i izdavač:</strong> [UPISATI naziv pravnog lica]<br><strong>Adresa:</strong> [UPISATI adresu]<br><strong>PIB / registarski broj:</strong> [UPISATI]' )
			. $p( '<strong>Glavni i odgovorni urednik:</strong> [UPISATI ime i prezime]<br><strong>Redakcija:</strong> [UPISATI]' )
			. $p( '<strong>E-mail redakcije:</strong> [UPISATI]<br><strong>Telefon:</strong> [UPISATI]' )
			. $p( 'Upisan u Evidenciju medija pod rednim brojem [UPISATI].' )
			. $p( 'Sadržaj objavljen na portalu zaštićen je autorskim pravom. Preuzimanje je dozvoljeno uz navođenje izvora i aktivan link ka originalnom tekstu.' ),
		),
		'kontakt'             => array(
			'Kontakt',
			$p( 'Imate pitanje, prijedlog ili ispravku? Pišite nam putem forme ispod ili direktno na e-mail redakcije naveden u <a href="/impresum/">Impresumu</a>.' )
			. "<!-- wp:binsider/form {\"form\":\"kontakt\"} /-->\n\n",
		),
		'posalji-vijest'      => array(
			'Pošalji vijest',
			$p( 'Bili ste svjedok događaja? Imate informaciju, fotografiju ili dokument koji bi javnost trebala vidjeti? Pošaljite nam — redakcija provjerava svaku dojavu prije objave.' )
			. $p( 'Ako želite, ostaćete anonimni. Vaše podatke nikada ne objavljujemo bez vaše saglasnosti.' )
			. "<!-- wp:binsider/form {\"form\":\"vijest\"} /-->\n\n",
		),
		'politika-privatnosti' => array(
			'Politika privatnosti',
			$p( '<em>Ovaj tekst je osnovni nacrt — prije objave ga treba uskladiti sa stvarnim načinom obrade podataka i Zakonom o zaštiti podataka o ličnosti Crne Gore.</em>' )
			. $h( 'Koje podatke prikupljamo' )
			. $list( array( 'Podatke koje nam sami pošaljete putem kontakt forme ili forme „Pošalji vijest" (ime, e-mail ili telefon, poruka, prilozi).', 'Tehničke podatke potrebne za rad sajta (IP adresa u serverskim zapisima, zaštita od zloupotrebe prijave).', 'Anonimni broj pregleda članaka (za listu „Najčitanije"); za to se koristi kolačić koji sprječava višestruko brojanje istog posjeta.' ) )
			. $h( 'Kako ih koristimo' )
			. $p( 'Podatke iz formi koristimo isključivo da bismo odgovorili na vašu poruku ili provjerili dojavu. Ne prodajemo ih i ne ustupamo trećim licima, osim kada to nalaže zakon.' )
			. $h( 'Vaša prava' )
			. $p( 'Imate pravo na pristup, ispravku i brisanje svojih podataka. Za sve zahtjeve pišite nam putem stranice <a href="/kontakt/">Kontakt</a>.' ),
		),
		'uslovi-koristenja'   => array(
			'Uslovi korištenja',
			$p( 'Korištenjem portala Bošnjački Insajder prihvatate sljedeće uslove.' )
			. $h( 'Autorska prava' )
			. $p( 'Svi tekstovi, fotografije i video zapisi objavljeni na portalu vlasništvo su redakcije ili autora. Preuzimanje sadržaja dozvoljeno je samo uz jasno navođenje izvora i aktivan link ka originalnom članku.' )
			. $h( 'Odgovornost' )
			. $p( 'Redakcija ulaže maksimalne napore da objavljene informacije budu tačne i provjerene. Ukoliko uočite grešku, molimo vas da nas obavijestite putem stranice <a href="/kontakt/">Kontakt</a>.' )
			. $h( 'Linkovi ka drugim sajtovima' )
			. $p( 'Portal može sadržati linkove ka drugim sajtovima za čiji sadržaj redakcija ne odgovara.' ),
		),
	);
}
