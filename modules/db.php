<?php
// modules/db.php  –  Database setup & helpers

// ── Defaults ──────────────────────────────────────────────────────────────────
function cpcs_defaults() {
    return [
        // Critical CSS
        'defer_stylesheets'    => true,
        'minify_output'        => true,
        'exclude_urls'         => '',
        'viewport_width'       => 1300,
        'viewport_height'      => 900,
        // Fonts
        'fonts_enabled'        => true,
        'fonts_display'        => 'swap',
        'fonts_self_host'      => false,
        'preload_fonts'        => '',
        // Scripts
        'scripts_enabled'      => true,
        'scripts_defer_all'    => false,
        'scripts_async_list'   => '',
        'scripts_defer_list'   => '',
        'scripts_remove_list'  => '',
        'scripts_exclude_list' => 'jquery',
        // Preload
        'preload_enabled'      => true,
        'preload_lcp_image'    => '',
        // GTM
        'gtm_enabled'          => false,
        'gtm_id'               => '',
        'gtm_lazy'             => true,
        // GA4
        'ga4_enabled'          => false,
        'ga4_id'               => '',
        'ga4_lazy'             => true,
        'ga4_delay_ms'         => 5000,
    ];
}

// ── Activation ────────────────────────────────────────────────────────────────
register_activation_hook( CPCS_DIR . 'critical-path-css.php', 'cpcs_activate' );
function cpcs_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . CPCS_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql     = "CREATE TABLE IF NOT EXISTS {$table} (
        id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        page_url     VARCHAR(2083)       NOT NULL DEFAULT '',
        critical_css LONGTEXT            NOT NULL,
        generated_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY   page_url (page_url(191))
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Merge over existing so upgrades don't lose saved values
    $existing = get_option( 'cpcs_settings', [] );
    update_option( 'cpcs_settings', array_merge( cpcs_defaults(), $existing ) );
}

// Run on every load — handles sites that installed v1 without re-activating
add_action( 'plugins_loaded', 'cpcs_ensure_settings', 1 );
function cpcs_ensure_settings() {
    $defaults = cpcs_defaults();
    $existing = get_option( 'cpcs_settings', [] );

    if ( ! is_array( $existing ) ) {
        $existing = [];
    }

    $missing_keys = array_diff_key( $defaults, $existing );
    if ( empty( $existing ) || ! empty( $missing_keys ) ) {
        update_option( 'cpcs_settings', array_merge( $defaults, $existing ) );
    }
}

register_deactivation_hook( CPCS_DIR . 'critical-path-css.php', '__return_false' );

// ── Settings getter ───────────────────────────────────────────────────────────
// No static cache — get_option() is already served from WP's in-memory object
// cache after the first DB read, so this is fast with no caching risk.
function cpcs_get_setting( $key, $fallback = null ) {
    $s = get_option( 'cpcs_settings', [] );
    return array_key_exists( $key, $s ) ? $s[ $key ] : $fallback;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function cpcs_current_url() {
    $protocol = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function cpcs_is_excluded_url() {
    $current = cpcs_current_url();
    $list    = array_filter( array_map( 'trim', explode( "\n", cpcs_get_setting( 'exclude_urls', '' ) ) ) );
    foreach ( $list as $ex ) {
        if ( rtrim( $current, '/' ) === rtrim( $ex, '/' ) ) return true;
    }
    return false;
}

function cpcs_minify_css( $css ) {
    $css = preg_replace( '/\/\*.*?\*\//s', '', $css );
    $css = preg_replace( '/\s+/', ' ', $css );
    $css = preg_replace( '/\s*([{};:,>+~])\s*/', '$1', $css );
    return trim( str_replace( ';}', '}', $css ) );
}

function cpcs_should_bypass_frontend_optimizations() {
    if ( is_admin() || wp_doing_ajax() || is_feed() ) return true;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return true;
    if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) return true;
    if ( isset( $_GET['customize_changeset_uuid'] ) || isset( $_GET['customize_messenger_channel'] ) || isset( $_GET['customize_theme'] ) ) return true;
    return false;
}
