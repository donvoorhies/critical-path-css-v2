
<?php
/**
 * Plugin Name: Critical Path CSS Generator
 * Plugin URI:  https://github.com/donvoorhies/critical-path-css-v2
 * Description: Complete page-speed toolkit: critical-path CSS inlining, stylesheet deferral, Google Fonts optimisation, script deferral/async, preload hints, and GTM lazy-loading.
 * Version:     2.0.2
 * Author:      Don Voorhies
 * License:     GPL-2.0+
 * Text Domain: critical-path-css
 *
 * Main plugin bootstrap file. Defines constants, utility functions, and loads all feature modules.
 */


// Exit if accessed directly outside of WordPress context
if ( ! defined( 'ABSPATH' ) ) exit;


// Plugin constants for versioning, paths, and DB table
define( 'CPCS_VERSION',    '2.0.2' );
define( 'CPCS_DIR',        plugin_dir_path( __FILE__ ) );
define( 'CPCS_URL',        plugin_dir_url( __FILE__ ) );
define( 'CPCS_TABLE',      'cpcs_critical_css' );


// Polyfill: Check if a string contains a substring (for PHP < 8)
if ( ! function_exists( 'cpcs_str_contains' ) ) {
	function cpcs_str_contains( $haystack, $needle ) {
		return $needle === '' || strpos( (string) $haystack, (string) $needle ) !== false;
	}
}


// Polyfill: Check if a string starts with a substring (for PHP < 8)
if ( ! function_exists( 'cpcs_str_starts_with' ) ) {
	function cpcs_str_starts_with( $haystack, $needle ) {
		$haystack = (string) $haystack;
		$needle   = (string) $needle;
		if ( $needle === '' ) return true;
		return substr( $haystack, 0, strlen( $needle ) ) === $needle;
	}
}


// ── Load all plugin modules (feature separation for maintainability) ──
require_once CPCS_DIR . 'modules/db.php';         // Database setup and helpers
require_once CPCS_DIR . 'modules/admin.php';      // Admin UI and settings
require_once CPCS_DIR . 'modules/critical-css.php'; // Critical CSS inlining logic
require_once CPCS_DIR . 'modules/fonts.php';      // Google Fonts optimization
require_once CPCS_DIR . 'modules/scripts.php';    // Script deferral/async
require_once CPCS_DIR . 'modules/preload.php';    // Preload hints for resources
require_once CPCS_DIR . 'modules/gtm.php';        // Google Tag Manager lazy-loading
require_once CPCS_DIR . 'modules/ga4.php';        // Google Analytics 4 integration
require_once CPCS_DIR . 'modules/noise-guard.php';// Miscellaneous performance tweaks
