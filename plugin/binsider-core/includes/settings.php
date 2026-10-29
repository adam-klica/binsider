<?php
/**
 * Settings → Bošnjački Insajder: social profiles, breaking duration, setup status.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_options_page( 'Bošnjački Insajder', 'Bošnjački Insajder', 'manage_options', 'binsider', 'binsider_settings_page' );
	}
);

add_action(
	'admin_init',
	static function () {
		register_setting(
			'binsider',
			'binsider_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'binsider_sanitize_options',
				'default'           => array(),
			)
		);
	}
);

function binsider_sanitize_options( $input ): array {
	$input = (array) $input;
	$out   = array();
	foreach ( array( 'facebook', 'instagram', 'youtube', 'x', 'tiktok', 'telegram' ) as $key ) {
		$out[ $key ] = esc_url_raw( trim( (string) ( $input[ $key ] ?? '' ) ) );
	}
	$out['breaking_hours'] = max( 1, min( 168, (int) ( $input['breaking_hours'] ?? 24 ) ) );
	$out['news_name']      = sanitize_text_field( (string) ( $input['news_name'] ?? 'Bošnjački Insajder' ) );
	$out['chat_phone']     = preg_replace( '/\D+/', '', (string) ( $input['chat_phone'] ?? '' ) );
	$out['chat_fab']       = empty( $input['chat_fab'] ) ? 0 : 1;
	return $out;
}

add_action(
	'admin_post_binsider_setup',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemate dozvolu.' );
		}
		check_admin_referer( 'binsider_setup' );
		binsider_run_setup();
		flush_rewrite_rules();
		wp_safe_redirect( admin_url( 'options-general.php?page=binsider&setup=1' ) );
		exit;
	}
);

/**
 * Status rows: label => [ok, hint].
 */
function binsider_status(): array {
	$theme  = wp_get_theme();
	$active = static fn( string $file ): bool => in_array( $file, (array) get_option( 'active_plugins', array() ), true )
		|| array_key_exists( $file, (array) get_site_option( 'active_sitewide_plugins', array() ) );
	return array(
		'Tema „Bošnjački Insajder" aktivna' => array( 'binsider' === $theme->get_stylesheet(), 'Izgled → Teme' ),
		'Yoast SEO aktivan'                 => array( $active( 'wordpress-seo/wp-seo.php' ), 'Dodaci → Dodaj novi → „Yoast SEO"' ),
		'Post Views Counter aktivan'        => array( $active( 'post-views-counter/post-views-counter.php' ), 'Dodaci → Dodaj novi → „Post Views Counter" (za Najčitanije)' ),
		'Contact Form 7 aktivan'            => array( $active( 'contact-form-7/wp-contact-form-7.php' ), 'Dodaci → Dodaj novi → „Contact Form 7" (za forme)' ),
		'Limit Login Attempts aktivan'      => array( $active( 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' ), 'Dodaci → Dodaj novi → „Limit Login Attempts Reloaded"' ),
		'Trajne veze /rubrika/naslov/'      => array( '/%category%/%postname%/' === get_option( 'permalink_structure' ), 'Podešavanja → Trajne veze → Prilagođena struktura: /%category%/%postname%/' ),
		'Naslovna i „Najnovije" podešene'   => array( 'page' === get_option( 'show_on_front' ) && get_option( 'page_for_posts' ), 'Pokrenite postavljanje ispod (ili Podešavanja → Čitanje)' ),
		'Jezik sajta bosanski'              => array( 'bs_BA' === get_locale(), 'Podešavanja → Opšte → Jezik sajta: Bosanski' ),
		'Vremenska zona Podgorica'          => array( 'Europe/Podgorica' === get_option( 'timezone_string' ), 'Podešavanja → Opšte → Vremenska zona' ),
	);
}

function binsider_settings_page(): void {
	$opts = (array) get_option( 'binsider_options', array() );
	$val  = static fn( $k ) => esc_attr( (string) ( $opts[ $k ] ?? binsider_option( $k ) ) );
	$log  = (array) get_option( 'binsider_setup_log', array() );
	?>
	<div class="wrap">
		<h1>Bošnjački Insajder</h1>
		<?php if ( isset( $_GET['setup'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success"><p>Postavljanje je završeno.</p></div>
		<?php endif; ?>

		<h2>Stanje sajta</h2>
		<table class="widefat striped" style="max-width:900px">
			<tbody>
			<?php foreach ( binsider_status() as $label => [ $ok, $hint ] ) : ?>
				<tr>
					<td style="width:28px"><?php echo $ok ? '✅' : '⚠️'; ?></td>
					<td><strong><?php echo esc_html( $label ); ?></strong></td>
					<td><?php echo $ok ? '' : esc_html( $hint ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:16px 0 32px">
			<?php wp_nonce_field( 'binsider_setup' ); ?>
			<input type="hidden" name="action" value="binsider_setup">
			<?php submit_button( 'Pokreni postavljanje (rubrike, stranice, forme)', 'secondary', 'submit', false ); ?>
			<p class="description">Kreira samo ono što nedostaje — postojeći sadržaj se ne mijenja.
				<?php if ( ! empty( $log['time'] ) ) : ?>
					Posljednje pokretanje: <?php echo esc_html( binsider_date( 'j. F Y. H:i', (int) $log['time'] ) ); ?>.
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $log['log'] ) ) : ?>
				<ul style="list-style:disc;margin-left:20px">
					<?php foreach ( $log['log'] as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</form>

		<form method="post" action="options.php">
			<?php settings_fields( 'binsider' ); ?>
			<h2>Društvene mreže</h2>
			<p>Linkovi se prikazuju u zaglavlju i podnožju sajta. Prazno polje = mreža se ne prikazuje.</p>
			<table class="form-table" role="presentation">
				<?php
				foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'x' => 'X (Twitter)', 'tiktok' => 'TikTok', 'telegram' => 'Telegram' ) as $key => $label ) :
					?>
					<tr>
						<th scope="row"><label for="binsider-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input type="url" class="regular-text" id="binsider-<?php echo esc_attr( $key ); ?>" name="binsider_options[<?php echo esc_attr( $key ); ?>]" value="<?php echo $val( $key ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" placeholder="https://"></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<h2>Udarne vijesti</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="binsider-hours">Trajanje (sati)</label></th>
					<td><input type="number" min="1" max="168" id="binsider-hours" name="binsider_options[breaking_hours]" value="<?php echo $val( 'breaking_hours' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" class="small-text">
						<p class="description">Koliko dugo se članak označen kao „Udarna vijest" prikazuje u crvenoj traci.</p></td>
				</tr>
			</table>

			<h2>Viber i WhatsApp</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="binsider-chat-phone">Broj telefona</label></th>
					<td><input type="text" class="regular-text" id="binsider-chat-phone" name="binsider_options[chat_phone]" value="<?php echo $val( 'chat_phone' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" placeholder="38269644087">
						<p class="description">Međunarodni format, samo cifre (npr. 38269644087). Koristi se za dugmad „Viber" i „WhatsApp".</p></td>
				</tr>
				<tr>
					<th scope="row">Plutajuća dugmad</th>
					<td><label><input type="checkbox" name="binsider_options[chat_fab]" value="1" <?php checked( (int) binsider_option( 'chat_fab' ), 1 ); ?>> Prikaži Viber i WhatsApp dugmad u donjem desnom uglu</label></td>
				</tr>
			</table>

			<h2>Google News</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="binsider-news">Naziv publikacije</label></th>
					<td><input type="text" class="regular-text" id="binsider-news" name="binsider_options[news_name]" value="<?php echo $val( 'news_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>">
						<p class="description">News sitemap: <a href="<?php echo esc_url( home_url( '/news-sitemap.xml' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/news-sitemap.xml' ) ); ?></a> — prijavite ga u Google Search Console.</p></td>
				</tr>
			</table>
			<?php submit_button( 'Sačuvaj podešavanja' ); ?>
		</form>
	</div>
	<?php
}

// Theme needs this plugin; nag if Yoast/PVC/CF7 missing.
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'settings_page_binsider' === $screen->id ) {
			return;
		}
		$missing = array_filter( binsider_status(), static fn( $row ) => ! $row[0] );
		if ( $missing ) {
			printf(
				'<div class="notice notice-warning"><p><strong>Bošnjački Insajder:</strong> %d stavki čeka podešavanje. <a href="%s">Pogledaj stanje sajta →</a></p></div>',
				count( $missing ),
				esc_url( admin_url( 'options-general.php?page=binsider' ) )
			);
		}
	}
);
