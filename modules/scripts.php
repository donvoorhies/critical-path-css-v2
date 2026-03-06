<?php
// modules/scripts.php  –  JS defer / async / remove

add_filter( 'script_loader_tag', 'cpcs_modify_script_tag', 10, 3 );
add_action( 'template_redirect', 'cpcs_script_warning_filter_bootstrap', 0 );

function cpcs_script_warning_filter_bootstrap() {
    if ( cpcs_should_bypass_frontend_optimizations() ) return;

    ob_start( 'cpcs_filter_problematic_script_hints' );
}

function cpcs_modify_script_tag( $tag, $handle, $src ) {
    if ( cpcs_should_bypass_frontend_optimizations() ) return $tag;

    // Never touch login page
    if ( $GLOBALS['pagenow'] ?? '' === 'wp-login.php' ) return $tag;

    $scripts_enabled = cpcs_get_setting( 'scripts_enabled', true );

    $defer_all    = cpcs_get_setting( 'scripts_defer_all',    false );
    $async_list   = cpcs_setting_to_array( 'scripts_async_list' );
    $defer_list   = cpcs_setting_to_array( 'scripts_defer_list' );
    $remove_list  = cpcs_setting_to_array( 'scripts_remove_list' );
    $exclude_list = cpcs_setting_to_array( 'scripts_exclude_list' );

    // Webpushr fail-safe: when notifications are denied, avoid loading app.min.js
    // to prevent repeated console warnings from the vendor script.
    if ( cpcs_is_webpushr_src( $src ) ) {
        return cpcs_wrap_webpushr_permission_guard( $src );
    }

    if ( ! $scripts_enabled ) return $tag;

    // Exclude list wins over everything
    foreach ( $exclude_list as $ex ) {
        if ( cpcs_handle_or_src_matches( $handle, $src, $ex ) ) return $tag;
    }

    // Remove entirely
    foreach ( $remove_list as $rm ) {
        if ( cpcs_handle_or_src_matches( $handle, $src, $rm ) ) return '';
    }

    // Async (takes priority over defer)
    foreach ( $async_list as $as ) {
        if ( cpcs_handle_or_src_matches( $handle, $src, $as ) ) {
            return cpcs_add_script_attr( $tag, 'async' );
        }
    }

    // Explicit defer list
    foreach ( $defer_list as $df ) {
        if ( cpcs_handle_or_src_matches( $handle, $src, $df ) ) {
            return cpcs_add_script_attr( $tag, 'defer' );
        }
    }

    // Defer all (unless already async/defer in original tag)
    if ( $defer_all && ! preg_match( '/\b(async|defer)\b/i', $tag ) ) {
        return cpcs_add_script_attr( $tag, 'defer' );
    }

    return $tag;
}

function cpcs_is_webpushr_src( $src ) {
    if ( ! is_string( $src ) || $src === '' ) return false;
    $src_l = strtolower( $src );
    return cpcs_str_contains( $src_l, 'cdn.webpushr.com' ) && cpcs_str_contains( $src_l, 'app.min.js' );
}

function cpcs_wrap_webpushr_permission_guard( $src ) {
    $safe_src = esc_js( $src );

    return '<script>(function(){try{var privacyBlocked=(window.globalPrivacyControl===true||navigator.globalPrivacyControl===true||navigator.doNotTrack==="1"||window.doNotTrack==="1"||navigator.msDoNotTrack==="1");if(privacyBlocked)return;if(!("Notification" in window)||Notification.permission!=="denied"){var s=document.createElement("script");s.src="' . $safe_src . '";s.async=true;document.head.appendChild(s);}}catch(e){}})();</script>';
}

function cpcs_add_script_attr( $tag, $attr ) {
    // Avoid duplicates
    if ( cpcs_str_contains( $tag, $attr ) ) return $tag;
    return str_replace( '<script ', "<script {$attr} ", $tag );
}

function cpcs_handle_or_src_matches( $handle, $src, $pattern ) {
    $pattern = trim( $pattern );
    if ( ! $pattern ) return false;
    return $handle === $pattern || cpcs_str_contains( $src, $pattern );
}

function cpcs_setting_to_array( $key ) {
    $raw = cpcs_get_setting( $key, '' );
    return array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
}

function cpcs_filter_problematic_script_hints( $html ) {
    if ( ! is_string( $html ) || $html === '' ) return $html;

    // Strip direct Webpushr vendor script tags (hardcoded/theme/plugin output).
    $html = preg_replace(
        '~<script\b[^>]*\bsrc=("|\')[^"\']*cdn\.webpushr\.com/app\.min\.js[^"\']*\1[^>]*>\s*</script>~i',
        '',
        $html
    );

    // Strip inline Webpushr boot code to prevent "Notifications are denied" spam.
    $html = preg_replace(
        '~<script\b[^>]*>[^<]*(?:_webpushr|window\.webpushr|cdn\.webpushr\.com)[\s\S]*?</script>~i',
        '',
        $html
    );

    return $html;
}
