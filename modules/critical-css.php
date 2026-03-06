<?php
// modules/critical-css.php  –  Inline critical CSS + defer full stylesheets

global $cpcs_has_critical, $cpcs_deferred_tags;
$cpcs_has_critical  = false;
$cpcs_deferred_tags = [];

// ── Inline critical CSS in <head> ─────────────────────────────────────────────
add_action( 'wp_head', 'cpcs_inline_critical_css', 1 );
function cpcs_inline_critical_css() {
    if ( cpcs_should_bypass_frontend_optimizations() || cpcs_is_excluded_url() ) return;

    global $wpdb;
    $table = $wpdb->prefix . CPCS_TABLE;
    $url   = cpcs_current_url();

    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT critical_css FROM {$table} WHERE page_url IN (%s,%s) LIMIT 1",
        trailingslashit( $url ), untrailingslashit( $url )
    ) );
    if ( ! $row ) return;

    echo "\n<!-- Critical Path CSS v2 -->\n";
    echo '<style id="cpcs-critical">' . $row->critical_css . "</style>\n";

    if ( cpcs_get_setting( 'defer_stylesheets', true ) ) {
        global $cpcs_has_critical;
        $cpcs_has_critical = true;
    }
}

// ── Strip stylesheets from <head> when critical CSS is active ─────────────────
add_filter( 'style_loader_tag', 'cpcs_maybe_defer_stylesheet', 10, 4 );
function cpcs_maybe_defer_stylesheet( $tag, $handle, $href, $media ) {
    global $cpcs_has_critical;
    if ( ! $cpcs_has_critical || cpcs_should_bypass_frontend_optimizations() ) return $tag;
    if ( in_array( $handle, [ 'admin-bar', 'dashicons' ], true ) ) return $tag;

    global $cpcs_deferred_tags;
    $cpcs_deferred_tags[] = $tag;
    return '';
}

// ── Print deferred stylesheets in footer ──────────────────────────────────────
add_action( 'wp_footer', 'cpcs_print_deferred_stylesheets', 999 );
function cpcs_print_deferred_stylesheets() {
    if ( cpcs_should_bypass_frontend_optimizations() ) return;

    global $cpcs_deferred_tags;
    if ( empty( $cpcs_deferred_tags ) ) return;

    echo "\n<!-- Deferred stylesheets (Critical Path CSS) -->\n";
    foreach ( $cpcs_deferred_tags as $tag ) echo $tag;

    // Noscript fallback
    echo '<noscript>';
    foreach ( $cpcs_deferred_tags as $tag ) echo $tag;
    echo "</noscript>\n";
}

// ── AJAX: generate critical CSS ───────────────────────────────────────────────
add_action( 'wp_ajax_cpcs_generate',  'cpcs_ajax_generate' );
add_action( 'wp_ajax_cpcs_fetch_css', 'cpcs_ajax_fetch_css' );
add_action( 'wp_ajax_cpcs_save',      'cpcs_ajax_save' );
add_action( 'wp_ajax_cpcs_delete',    'cpcs_ajax_delete' );

function cpcs_ajax_generate() {
    check_ajax_referer( 'cpcs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );

    $url      = esc_url_raw( wp_unslash( $_POST['page_url'] ?? '' ) );
    $full_css = wp_unslash( $_POST['full_css'] ?? '' );
    if ( ! $url ) wp_send_json_error( 'Please provide a page URL.' );

    if ( ! $full_css ) {
        $full_css = cpcs_fetch_all_css_from_url( $url );
        if ( is_wp_error( $full_css ) ) wp_send_json_error( $full_css->get_error_message() );
    }

    $critical = cpcs_extract_critical_css( $full_css, $url, (int) cpcs_get_setting( 'viewport_height', 900 ) );
    if ( cpcs_get_setting( 'minify_output', true ) ) $critical = cpcs_minify_css( $critical );

    wp_send_json_success( [ 'critical_css' => $critical ] );
}

function cpcs_ajax_fetch_css() {
    check_ajax_referer( 'cpcs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );

    $url = esc_url_raw( wp_unslash( $_POST['page_url'] ?? '' ) );
    if ( ! $url ) wp_send_json_error( 'No URL' );

    $css = cpcs_fetch_all_css_from_url( $url );
    if ( is_wp_error( $css ) ) wp_send_json_error( $css->get_error_message() );
    wp_send_json_success( [ 'css' => $css ] );
}

function cpcs_ajax_save() {
    check_ajax_referer( 'cpcs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );

    $url = esc_url_raw( wp_unslash( $_POST['page_url'] ?? '' ) );
    $css = wp_strip_all_tags( wp_unslash( $_POST['critical_css'] ?? '' ) );
    $id  = absint( $_POST['entry_id'] ?? 0 );
    if ( ! $url || ! $css ) wp_send_json_error( 'URL and CSS required.' );

    global $wpdb;
    $table = $wpdb->prefix . CPCS_TABLE;
    $data  = [ 'page_url' => $url, 'critical_css' => $css, 'generated_at' => current_time( 'mysql', true ) ];

    if ( $id ) {
        $wpdb->update( $table, $data, [ 'id' => $id ] );
    } else {
        $wpdb->replace( $table, $data );
        $id = $wpdb->insert_id;
    }
    wp_send_json_success( [ 'message' => 'Saved!', 'id' => $id ] );
}

function cpcs_ajax_delete() {
    check_ajax_referer( 'cpcs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );
    $id = absint( $_POST['entry_id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( 'Invalid ID' );
    global $wpdb;
    $wpdb->delete( $wpdb->prefix . CPCS_TABLE, [ 'id' => $id ] );
    wp_send_json_success();
}

// ── CSS fetcher & extractor (unchanged from v1) ───────────────────────────────
function cpcs_fetch_all_css_from_url( $page_url ) {
    $response = wp_remote_get( $page_url, [ 'timeout' => 30, 'user-agent' => 'Mozilla/5.0 WordPress/CPCS' ] );
    if ( is_wp_error( $response ) ) return $response;
    if ( wp_remote_retrieve_response_code( $response ) !== 200 )
        return new WP_Error( 'fetch_fail', 'HTTP ' . wp_remote_retrieve_response_code( $response ) );

    $html     = wp_remote_retrieve_body( $response );
    $combined = '';
    $base     = trailingslashit( get_option( 'siteurl' ) );

    preg_match_all( '/<link[^>]+rel=["\']stylesheet["\'][^>]*>/i', $html, $link_tags );
    foreach ( $link_tags[0] as $tag ) {
        preg_match( '/href=["\']([^"\']+)["\']/', $tag, $m );
        if ( empty( $m[1] ) ) continue;
        $href = $m[1];
        if ( cpcs_str_starts_with( $href, '//' ) ) $href = 'https:' . $href;
        if ( ! cpcs_str_starts_with( $href, 'http' ) ) $href = rtrim( $base, '/' ) . '/' . ltrim( $href, '/' );
        $r = wp_remote_get( $href, [ 'timeout' => 15 ] );
        if ( ! is_wp_error( $r ) && wp_remote_retrieve_response_code( $r ) === 200 )
            $combined .= "\n/* {$href} */\n" . wp_remote_retrieve_body( $r );
    }

    preg_match_all( '/<style[^>]*>(.*?)<\/style>/si', $html, $blocks );
    foreach ( $blocks[1] as $b ) $combined .= "\n/* inline */\n" . $b;

    return $combined ?: new WP_Error( 'no_css', 'No stylesheets found.' );
}

function cpcs_extract_critical_css( $full_css, $page_url, $viewport_height = 900 ) {
    $response  = wp_remote_get( $page_url, [ 'timeout' => 30 ] );
    $html      = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );
    $used_cls  = $used_ids = [];

    if ( $html ) {
        preg_match_all( '/class=["\']([^"\']+)["\']/', $html, $cm );
        foreach ( $cm[1] as $s ) foreach ( preg_split( '/\s+/', trim( $s ) ) as $c ) if ( $c ) $used_cls[$c] = true;
        preg_match_all( '/id=["\']([^"\']+)["\']/', $html, $im );
        foreach ( $im[1] as $id ) if ( $id ) $used_ids[$id] = true;
    }

    $atf = [ 'body','html',':root','header','nav','hero','banner','jumbotron','masthead','top-bar',
              'h1','h2','h3','h4','h5','h6','p','a','ul','ol','li','img','figure',
              'container','wrapper','main','content','page','btn','button','cta',
              'logo','brand','site-title','menu','navbar','navigation','flex','grid','col','row' ];

    $css    = preg_replace( '/^\xEF\xBB\xBF/', '', $full_css );
    $blocks = cpcs_split_css_blocks( $css );
    $out    = [];

    foreach ( $blocks as $block ) {
        $block = trim( $block );
        if ( ! $block ) continue;
        if ( preg_match( '/^@font-face/i', $block ) ) { $out[] = $block; continue; }
        if ( preg_match( '/^@keyframes/i', $block ) )  continue;
        if ( preg_match( '/^@(media|supports)\b([^{]*)\{(.*)\}$/si', $block, $mm ) ) {
            $inner = cpcs_filter_rules( $mm[3], $used_cls, $used_ids, $atf );
            if ( trim( $inner ) ) $out[] = '@' . $mm[1] . $mm[2] . " {\n{$inner}\n}";
            continue;
        }
        if ( preg_match( '/^([^{]+)\{([^}]*)\}$/s', $block, $rm ) ) {
            if ( cpcs_selector_is_critical( trim( $rm[1] ), $used_cls, $used_ids, $atf ) )
                $out[] = trim( $rm[1] ) . ' { ' . trim( $rm[2] ) . ' }';
        }
    }
    return implode( "\n", $out );
}

function cpcs_split_css_blocks( $css ) {
    $css = preg_replace( '/\/\*.*?\*\//s', '', $css );
    $blocks = []; $depth = 0; $start = 0; $len = strlen( $css );
    for ( $i = 0; $i < $len; $i++ ) {
        if ( $css[$i] === '{' ) $depth++;
        elseif ( $css[$i] === '}' ) { $depth--; if ( $depth === 0 ) { $blocks[] = substr( $css, $start, $i - $start + 1 ); $start = $i + 1; } }
    }
    return $blocks;
}

function cpcs_filter_rules( $css, $used_cls, $used_ids, $atf ) {
    $out = [];
    foreach ( cpcs_split_css_blocks( $css ) as $block ) {
        $block = trim( $block );
        if ( preg_match( '/^([^{]+)\{([^}]*)\}$/s', $block, $rm ) && cpcs_selector_is_critical( trim( $rm[1] ), $used_cls, $used_ids, $atf ) )
            $out[] = '  ' . trim( $rm[1] ) . ' { ' . trim( $rm[2] ) . ' }';
    }
    return implode( "\n", $out );
}

function cpcs_selector_is_critical( $sel, $used_cls, $used_ids, $atf ) {
    $sl = strtolower( $sel );
    foreach ( $atf as $kw ) if ( strpos( $sl, $kw ) !== false ) return true;
    foreach ( array_keys( $used_cls ) as $c ) if ( strpos( $sel, '.' . $c ) !== false ) return true;
    foreach ( array_keys( $used_ids ) as $id ) if ( strpos( $sel, '#' . $id ) !== false ) return true;
    return false;
}
