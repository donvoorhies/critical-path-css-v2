<?php
// modules/fonts.php  –  Google Fonts optimiser

if ( ! cpcs_get_setting( 'fonts_enabled', true ) ) return;

// ── Rewrite Google Fonts URLs to add font-display ─────────────────────────────
add_filter( 'style_loader_src', 'cpcs_rewrite_google_fonts_url', 10, 2 );
function cpcs_rewrite_google_fonts_url( $src, $handle ) {
    if ( ! cpcs_is_google_fonts_url( $src ) ) return $src;

    // If self-hosting is on, swap the URL for a local one
    if ( cpcs_get_setting( 'fonts_self_host', false ) ) {
        $local = cpcs_get_or_download_font( $src, $handle );
        if ( $local ) return $local;
    }

    // Otherwise just add display param
    $display = cpcs_get_setting( 'fonts_display', 'swap' );
    if ( strpos( $src, 'display=' ) === false ) {
        $src = add_query_arg( 'display', $display, $src );
    } else {
        $src = preg_replace( '/display=[^&]+/', 'display=' . $display, $src );
    }
    return $src;
}

// ── Preconnect hints ──────────────────────────────────────────────────────────
add_action( 'wp_head', 'cpcs_fonts_preconnect', 2 );
function cpcs_fonts_preconnect() {
    if ( cpcs_get_setting( 'fonts_self_host', false ) ) return; // not needed when self-hosting
    if ( ! cpcs_page_uses_google_fonts() ) return;
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}

// ── Self-host: download Google Fonts locally ──────────────────────────────────

function cpcs_get_or_download_font( $remote_url, $handle ) {
    $upload   = wp_upload_dir();
    $font_dir = $upload['basedir'] . '/cpcs-fonts';

    // Build URL from site_url to avoid servers where wp_upload_dir()['baseurl']
    // incorrectly embeds the filesystem path inside the URL.
    $upload_rel = ltrim( str_replace( ABSPATH, '', $upload['basedir'] ), '/' );
    $font_url   = rtrim( site_url(), '/' ) . '/' . $upload_rel . '/cpcs-fonts';
    $cache_key  = 'cpcs_font_' . md5( $remote_url );

    // Check transient cache first
    $cached_url = get_transient( $cache_key );
    if ( $cached_url ) {
        // Verify the CSS file actually exists
        // Derive the local filesystem path from the cached URL
        $upload_rel2 = ltrim( str_replace( ABSPATH, '', $upload['basedir'] ), '/' );
        $font_url2   = rtrim( site_url(), '/' ) . '/' . $upload_rel2 . '/cpcs-fonts';
        $css_path = str_replace( $font_url2, $font_dir, $cached_url );
        if ( file_exists( $css_path ) ) return $cached_url;
        // Cache is stale — delete and re-download
        delete_transient( $cache_key );
    }

    // Ensure directory exists and is writable
    if ( ! wp_mkdir_p( $font_dir ) ) return false;
    if ( ! is_writable( $font_dir ) ) return false;

    // Fetch the Google Fonts CSS with a modern browser UA to get woff2 responses
    $css_response = wp_remote_get( $remote_url, [
        'timeout'    => 20,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ] );

    if ( is_wp_error( $css_response ) || wp_remote_retrieve_response_code( $css_response ) !== 200 ) {
        return false;
    }

    $css     = wp_remote_retrieve_body( $css_response );
    $display = cpcs_get_setting( 'fonts_display', 'swap' );

    // Download every font file referenced in the CSS
    // Pattern matches: url(https://fonts.gstatic.com/...)
    preg_match_all( '/url\((https:\/\/fonts\.gstatic\.com\/[^)]+)\)/', $css, $matches );

    foreach ( $matches[1] as $font_src_url ) {
        $font_src_url = trim( $font_src_url, " '\"\t" );

        // Build a safe local filename from a hash of the URL
        $ext        = pathinfo( parse_url( $font_src_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
        $ext        = $ext ?: 'woff2'; // default to woff2
        $local_name = md5( $font_src_url ) . '.' . $ext;
        $local_path = $font_dir . '/' . $local_name;
        $local_src  = $font_url . '/' . $local_name;

        // Only download if not already present
        if ( ! file_exists( $local_path ) ) {
            $font_response = wp_remote_get( $font_src_url, [
                'timeout'    => 30,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ] );

            if ( is_wp_error( $font_response ) || wp_remote_retrieve_response_code( $font_response ) !== 200 ) {
                // If any font file fails, abort entirely and fall back to CDN
                return false;
            }

            $bytes = file_put_contents( $local_path, wp_remote_retrieve_body( $font_response ) );
            if ( $bytes === false || $bytes === 0 ) return false;
        }

        // Replace CDN URL with local URL in CSS
        $css = str_replace( $font_src_url, $local_src, $css );
    }

    // Inject font-display into every @font-face block
    $css = preg_replace_callback(
        '/@font-face\s*\{([^}]*)\}/s',
        function( $m ) use ( $display ) {
            $block = $m[1];
            // Add font-display if not already present
            if ( strpos( $block, 'font-display' ) === false ) {
                $block .= "\n  font-display: {$display};";
            }
            return "@font-face {{$block}}";
        },
        $css
    );

    // Write the rewritten CSS file
    $safe_handle = preg_replace( '/[^a-z0-9\-]/', '-', strtolower( $handle ) );
    $css_filename = 'fonts-' . $safe_handle . '-' . md5( $remote_url ) . '.css';
    $css_path     = $font_dir . '/' . $css_filename;

    if ( file_put_contents( $css_path, $css ) === false ) return false;

    $local_css_url = $font_url . '/' . $css_filename;
    set_transient( $cache_key, $local_css_url, WEEK_IN_SECONDS );

    return $local_css_url;
}

// ── Helper: does the current page use Google Fonts? ───────────────────────────
function cpcs_page_uses_google_fonts() {
    global $wp_styles;
    if ( empty( $wp_styles->queue ) ) return false;
    foreach ( $wp_styles->queue as $h ) {
        $src = $wp_styles->registered[ $h ]->src ?? '';
        if ( cpcs_is_google_fonts_url( $src ) ) return true;
    }
    return false;
}

function cpcs_is_google_fonts_url( $url ) {
    return strpos( $url, 'fonts.googleapis.com' ) !== false;
}

// ── AJAX: purge font cache ────────────────────────────────────────────────────
add_action( 'wp_ajax_cpcs_purge_fonts', 'cpcs_ajax_purge_fonts' );
function cpcs_ajax_purge_fonts() {
    check_ajax_referer( 'cpcs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

    $upload   = wp_upload_dir();
    $font_dir = $upload['basedir'] . '/cpcs-fonts'; // basedir is always a real filesystem path — safe to use here

    if ( is_dir( $font_dir ) ) {
        foreach ( glob( $font_dir . '/*' ) ?: [] as $f ) {
            if ( is_file( $f ) ) unlink( $f );
        }
    }

    // Clear all font transients
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cpcs_font_%' OR option_name LIKE '_transient_timeout_cpcs_font_%'" );

    wp_send_json_success( 'Font cache cleared.' );
}
