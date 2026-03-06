<?php
/**
 * Plugin Name: Critical Path CSS Generator
 * Plugin URI:  https://github.com/your-repo/critical-path-css
 * Description: Complete page-speed toolkit: critical-path CSS inlining, stylesheet deferral, Google Fonts optimisation, script deferral/async, preload hints, and GTM lazy-loading.
 * Version:     2.0.0
 * Author:      Your Name
 * License:     GPL-2.0+
 * Text Domain: critical-path-css
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CPCS_VERSION',    '2.0.0' );
define( 'CPCS_DIR',        plugin_dir_path( __FILE__ ) );
define( 'CPCS_URL',        plugin_dir_url( __FILE__ ) );
define( 'CPCS_TABLE',      'cpcs_critical_css' );

if ( ! function_exists( 'cpcs_str_contains' ) ) {
	function cpcs_str_contains( $haystack, $needle ) {
		return $needle === '' || strpos( (string) $haystack, (string) $needle ) !== false;
	}
}

if ( ! function_exists( 'cpcs_str_starts_with' ) ) {
	function cpcs_str_starts_with( $haystack, $needle ) {
		$haystack = (string) $haystack;
		$needle   = (string) $needle;
		if ( $needle === '' ) return true;
		return substr( $haystack, 0, strlen( $needle ) ) === $needle;
	}
}

// ── Load modules ─────────────────────────────────────────────────────────────
require_once CPCS_DIR . 'modules/db.php';
require_once CPCS_DIR . 'modules/admin.php';
require_once CPCS_DIR . 'modules/critical-css.php';
require_once CPCS_DIR . 'modules/fonts.php';
require_once CPCS_DIR . 'modules/scripts.php';
require_once CPCS_DIR . 'modules/preload.php';
require_once CPCS_DIR . 'modules/gtm.php';
require_once CPCS_DIR . 'modules/ga4.php';
require_once CPCS_DIR . 'modules/noise-guard.php';
