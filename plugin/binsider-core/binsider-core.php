<?php
/**
 * Plugin Name:       Bošnjački Insajder — Core
 * Plugin URI:        https://www.binsider.me
 * Description:       Funkcionalnost portala Bošnjački Insajder: rubrike, stranice, blokovi (udarne vijesti, najčitanije, dijeljenje, logo), Google News sitemap, NewsArticle schema i sigurnosna podešavanja. Potrebna za temu „Bošnjački Insajder".
 * Version:           1.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Bošnjački Insajder
 * License:           GPL-2.0-or-later
 * Text Domain:       binsider
 */

defined( 'ABSPATH' ) || exit;

define( 'BINSIDER_VERSION', '1.1.0' );
define( 'BINSIDER_FILE', __FILE__ );
define( 'BINSIDER_DIR', plugin_dir_path( __FILE__ ) );
define( 'BINSIDER_URL', plugin_dir_url( __FILE__ ) );

require_once BINSIDER_DIR . 'includes/helpers.php';
require_once BINSIDER_DIR . 'includes/icons.php';
require_once BINSIDER_DIR . 'includes/setup.php';
require_once BINSIDER_DIR . 'includes/settings.php';
require_once BINSIDER_DIR . 'includes/breaking.php';
require_once BINSIDER_DIR . 'includes/query.php';
require_once BINSIDER_DIR . 'includes/blocks.php';
require_once BINSIDER_DIR . 'includes/chat.php';
require_once BINSIDER_DIR . 'includes/seo.php';
require_once BINSIDER_DIR . 'includes/hardening.php';

register_activation_hook( __FILE__, 'binsider_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
