<?php
// modules/preload.php  –  <link rel="preload"> hints for LCP image + fonts

$cpcs_preload_enabled = cpcs_get_setting( 'preload_enabled', true );
if ( $cpcs_preload_enabled ) {
    add_action( 'wp_head', 'cpcs_output_preload_hints', 1 );
}
function cpcs_output_preload_hints() {
    if ( cpcs_should_bypass_frontend_optimizations() ) return;

    // ── LCP image preload ─────────────────────────────────────────────────────
    $lcp_image = trim( cpcs_get_setting( 'preload_lcp_image', '' ) );
    if ( $lcp_image && filter_var( $lcp_image, FILTER_VALIDATE_URL ) ) {
        if ( ! cpcs_preload_local_upload_url_exists( $lcp_image ) ) {
            cpcs_preload_debug_log( 'skip_lcp_preload_missing_file', [ 'url' => $lcp_image ] );
        } else {
        $ext  = strtolower( pathinfo( parse_url( $lcp_image, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
        if ( $ext === 'jpg' || $ext === 'jpeg' ) {
            $type = 'image/jpeg';
        } elseif ( $ext === 'png' ) {
            $type = 'image/png';
        } elseif ( $ext === 'webp' ) {
            $type = 'image/webp';
        } elseif ( $ext === 'avif' ) {
            $type = 'image/avif';
        } elseif ( $ext === 'gif' ) {
            $type = 'image/gif';
        } elseif ( $ext === 'svg' ) {
            $type = 'image/svg+xml';
        } else {
            $type = 'image/' . $ext;
        }
        echo '<link rel="preload" fetchpriority="high" as="image" href="' . esc_url( $lcp_image ) . '" type="' . esc_attr( $type ) . '">' . "\n";
            cpcs_preload_debug_log( 'emit_lcp_preload', [ 'url' => $lcp_image, 'type' => $type ] );
        }
    }

    // ── Woff2 font preloads (from settings list) ──────────────────────────────
    $font_list = array_filter( array_map( 'trim', explode( "\n", cpcs_get_setting( 'preload_fonts', '' ) ) ) );
    foreach ( $font_list as $url ) {
        $reason = '';
        if ( cpcs_should_output_font_preload( $url, $reason ) ) {
            echo '<link rel="preload" href="' . esc_url( $url ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
            cpcs_preload_debug_log( 'emit_font_preload', [ 'url' => $url, 'reason' => $reason ] );
        } else {
            cpcs_preload_debug_log( 'skip_font_preload', [ 'url' => $url, 'reason' => $reason ] );
        }
    }
}

function cpcs_should_output_font_preload( $url, &$reason = '' ) {
    if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        $reason = 'invalid_url';
        return false;
    }

    $host = (string) parse_url( $url, PHP_URL_HOST );
    if ( $host === '' ) {
        $reason = 'missing_host';
        return false;
    }

    // Local assets are assumed reachable and should not trigger network checks.
    $site_host = (string) parse_url( home_url(), PHP_URL_HOST );
    if ( $site_host !== '' && strtolower( $site_host ) === strtolower( $host ) ) {
        $reason = 'local_host';
        return true;
    }

    // When self-hosting is enabled, only local font URLs should be preloaded.
    // This prevents stale fonts.gstatic.com preloads that won't be used.
    if ( cpcs_get_setting( 'fonts_self_host', false ) ) {
        $reason = 'self_host_enabled_remote_blocked';
        return false;
    }

    $cache_key = 'cpcs_preload_ok_' . md5( $url );
    $cached    = get_transient( $cache_key );
    if ( $cached === '1' ) {
        $reason = 'cached_ok';
        return true;
    }
    if ( $cached === '0' ) {
        $reason = 'cached_fail';
        return false;
    }

    $args = [
        'timeout'    => 6,
        'redirection'=> 3,
        'user-agent' => 'Mozilla/5.0 (compatible; CPCS/2.0; +https://wordpress.org)',
    ];

    $ok   = false;
    $head = wp_remote_head( $url, $args );
    if ( ! is_wp_error( $head ) ) {
        $code = (int) wp_remote_retrieve_response_code( $head );
        $ok   = ( $code >= 200 && $code < 400 );
    }

    // Some CDNs reject HEAD; retry with a tiny GET before marking as broken.
    if ( ! $ok ) {
        $get_args                        = $args;
        $get_args['limit_response_size'] = 1;
        $get                             = wp_remote_get( $url, $get_args );
        if ( ! is_wp_error( $get ) ) {
            $code = (int) wp_remote_retrieve_response_code( $get );
            $ok   = ( $code >= 200 && $code < 400 );
        }
    }

    set_transient( $cache_key, $ok ? '1' : '0', DAY_IN_SECONDS );
    $reason = $ok ? 'remote_check_ok' : 'remote_check_fail';
    return $ok;
}

function cpcs_preload_debug_enabled() {
    if ( defined( 'CPCS_DEBUG_PRELOAD' ) && CPCS_DEBUG_PRELOAD ) return true;
    if ( isset( $_GET['cpcs_debug_preload'] ) && $_GET['cpcs_debug_preload'] === '1' && current_user_can( 'manage_options' ) ) return true;
    return false;
}

function cpcs_preload_debug_log( $event, $context = [] ) {
    if ( ! cpcs_preload_debug_enabled() ) return;

    $payload = [
        'event'   => (string) $event,
        'context' => is_array( $context ) ? $context : [],
        'uri'     => isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '',
    ];

    error_log( 'CPCS preload debug: ' . wp_json_encode( $payload ) );
    cpcs_preload_debug_emit_html_comment( $payload );
}

function cpcs_preload_debug_emit_html_comment( $payload ) {
    if ( is_admin() || wp_doing_ajax() ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;

    $json = wp_json_encode( $payload );
    if ( ! is_string( $json ) || $json === '' ) return;

    // Prevent invalid HTML comments if payload contains double dashes.
    $json = str_replace( '--', '- -', $json );
    echo "\n<!-- CPCS preload debug: {$json} -->\n";
}

function cpcs_preload_local_upload_url_exists( $url ) {
    if ( ! is_string( $url ) || $url === '' ) return false;

    $uploads = wp_upload_dir();
    $baseurl = rtrim( (string) ( $uploads['baseurl'] ?? '' ), '/' );
    $basedir = (string) ( $uploads['basedir'] ?? '' );

    if ( $baseurl === '' || $basedir === '' ) {
        return true;
    }

    if ( ! cpcs_str_starts_with( $url, $baseurl . '/' ) ) {
        return true;
    }

    $relative = ltrim( substr( $url, strlen( $baseurl ) ), '/' );
    if ( $relative === '' ) {
        return false;
    }

    return file_exists( trailingslashit( $basedir ) . $relative );
}

// ── Auto-detect LCP image from featured image of current post ─────────────────
if ( $cpcs_preload_enabled ) {
    add_action( 'wp_head', 'cpcs_auto_preload_featured_image', 1 );
}
function cpcs_auto_preload_featured_image() {
    if ( cpcs_should_bypass_frontend_optimizations() ) return;

    if ( ! is_singular() ) return;
    if ( trim( cpcs_get_setting( 'preload_lcp_image', '' ) ) ) return; // manual override in effect

    $post_id = get_queried_object_id();
    if ( ! has_post_thumbnail( $post_id ) ) return;

    $thumb_id  = get_post_thumbnail_id( $post_id );
    $thumb_url = wp_get_attachment_image_url( $thumb_id, 'large' );
    if ( ! $thumb_url ) return;
    if ( ! cpcs_preload_local_upload_url_exists( $thumb_url ) ) return;

    $ext  = strtolower( pathinfo( parse_url( $thumb_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
    if ( $ext === 'jpg' || $ext === 'jpeg' ) {
        $type = 'image/jpeg';
    } elseif ( $ext === 'png' ) {
        $type = 'image/png';
    } elseif ( $ext === 'webp' ) {
        $type = 'image/webp';
    } elseif ( $ext === 'avif' ) {
        $type = 'image/avif';
    } else {
        $type = 'image/' . $ext;
    }

    echo '<link rel="preload" fetchpriority="high" as="image" href="' . esc_url( $thumb_url ) . '" type="' . esc_attr( $type ) . '">' . "\n";
}

add_action( 'template_redirect', 'cpcs_preload_warning_filter_bootstrap', 0 );
function cpcs_preload_warning_filter_bootstrap() {
    if ( cpcs_should_bypass_frontend_optimizations() ) return;

    ob_start( 'cpcs_filter_problematic_preload_hints' );
}

function cpcs_filter_problematic_preload_hints( $html ) {
    if ( ! is_string( $html ) || $html === '' ) return $html;

    $seen_preloads = [];

    return preg_replace_callback(
        '~<link\b[^>]*\brel=("|\')preload\1[^>]*>~i',
        function ( $m ) use ( &$seen_preloads ) {
            $tag = $m[0];

            if ( ! preg_match( '~\bhref=("|\')([^"\']+)\1~i', $tag, $href_match ) ) {
                return $tag;
            }

            $href       = $href_match[2];
            $href_lower = strtolower( $href );

            // Remove duplicate preload tags for identical URLs.
            if ( isset( $seen_preloads[ $href_lower ] ) ) {
                return '';
            }
            $seen_preloads[ $href_lower ] = true;

            // Cloudflare challenge preloads are often unused at load and noisy.
            if ( cpcs_str_contains( $href_lower, 'challenges.cloudflare.com/cdn-cgi/challenge-platform' ) ) {
                return '';
            }

            // Convert CSS preloads to normal stylesheets to avoid "preloaded but not used".
            if (
                cpcs_str_contains( $href_lower, 'fonts.googleapis.com' ) ||
                cpcs_str_contains( $href_lower, '/wp-content/plugins/contact-form-7/includes/css/styles.css' )
            ) {
                return '<link rel="stylesheet" href="' . esc_url( $href ) . '">';
            }

            // Remove remote font preloads that are frequently stale/unused.
            if ( cpcs_str_contains( $href_lower, 'fonts.gstatic.com/' ) ) {
                return '';
            }

            return $tag;
        },
        $html
    );
}
