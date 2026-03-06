<?php
// modules/noise-guard.php  –  suppress known console-warning noise from third-party injectors

add_action( 'init', 'cpcs_noise_guard_disable_wp_emoji' );
function cpcs_noise_guard_disable_wp_emoji() {
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_footer_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_footer_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_detection_script' );

    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );

    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

    add_filter( 'emoji_svg_url', '__return_false' );
    add_filter( 'option_use_smilies', '__return_zero' );
}

add_filter( 'script_loader_src', 'cpcs_noise_guard_force_local_jquery_src', 9999, 2 );
function cpcs_noise_guard_force_local_jquery_src( $src, $handle ) {
    if ( ! is_string( $src ) || $src === '' ) return $src;

    if ( ! in_array( $handle, [ 'jquery', 'jquery-core', 'jquery-migrate' ], true ) ) {
        return $src;
    }

    $map = [
        'jquery'         => includes_url( '/js/jquery/jquery.min.js' ),
        'jquery-core'    => includes_url( '/js/jquery/jquery.min.js' ),
        'jquery-migrate' => includes_url( '/js/jquery/jquery-migrate.min.js' ),
    ];

    $parsed = wp_parse_url( $src );
    if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
        return $src;
    }

    $host      = strtolower( (string) $parsed['host'] );
    $site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

    if ( $site_host !== '' && $host === $site_host ) {
        return $src;
    }

    if ( isset( $map[ $handle ] ) ) {
        return $map[ $handle ];
    }

    return $src;
}

add_action( 'wp_enqueue_scripts', 'cpcs_noise_guard_dequeue_assets', 9999 );
function cpcs_noise_guard_dequeue_assets() {
    if ( cpcs_should_bypass_frontend_optimizations() ) return;

    $scripts = wp_scripts();
    if ( $scripts && ! empty( $scripts->registered ) ) {
        foreach ( $scripts->registered as $handle => $obj ) {
            $src = (string) ( $obj->src ?? '' );
            $hay = strtolower( $handle . ' ' . $src );
            if ( cpcs_noise_guard_is_webpushr_asset( $hay ) ) {
                wp_dequeue_script( $handle );
                wp_deregister_script( $handle );
            }
        }
    }
}

add_filter( 'script_loader_tag', 'cpcs_noise_guard_script_tag', 9999, 3 );
function cpcs_noise_guard_script_tag( $tag, $handle, $src ) {
    if ( cpcs_should_bypass_frontend_optimizations() ) return $tag;

    $hay = strtolower( (string) $handle . ' ' . (string) $src . ' ' . (string) $tag );
    if ( cpcs_noise_guard_is_webpushr_asset( $hay ) ) {
        return '';
    }

    return $tag;
}

add_action( 'template_redirect', 'cpcs_noise_guard_start_buffer', 0 );
function cpcs_noise_guard_start_buffer() {
    if ( cpcs_should_bypass_frontend_optimizations() ) return;

    ob_start( 'cpcs_noise_guard_filter_html' );
}

add_action( 'wp_head', 'cpcs_noise_guard_contrast_fix', 0 );
function cpcs_noise_guard_contrast_fix() {
    if ( cpcs_should_bypass_frontend_optimizations() ) return;
    ?>
<style id="cpcs-contrast-fix">
.pn-wrapper {
    color: #111 !important;
    background: #fff !important;
}

.pn-wrapper .btn,
#pn-activate-permission_link_nothanks.btn {
    color: #fff !important;
    background: #1f2937 !important;
    border-color: #1f2937 !important;
}

.pn-wrapper a,
.pn-wrapper .btn:hover,
#pn-activate-permission_link_nothanks.btn:hover {
    color: #fff !important;
}
</style>
    <?php
}

add_action( 'wp_head', 'cpcs_noise_guard_runtime_blocker', 0 );
function cpcs_noise_guard_runtime_blocker() {
    if ( cpcs_should_bypass_frontend_optimizations() ) return;
    ?>
<!-- CPCS Noise Guard Active -->
<script>
(function(){
    try {
        var _origWarn = console.warn;
        console.warn = function(){
            try {
                var first = arguments.length ? String(arguments[0]) : '';
                if (first.indexOf('WEBPUSHR: Notifications are denied by the user') !== -1) return;
            } catch(e) {}
            return _origWarn.apply(console, arguments);
        };

        var _origLog = console.log;
        console.log = function(){
            try {
                var first = arguments.length ? String(arguments[0]) : '';
                if (first.indexOf('WEBPUSHR: Notifications are denied by the user') !== -1) return;
            } catch(e) {}
            return _origLog.apply(console, arguments);
        };
    } catch(e) {}

    var shouldBlock = function(url){
        if (!url) return false;
        var u = String(url).toLowerCase();
        return u.indexOf('webpushr') !== -1 || u.indexOf('cdn.webpushr.com') !== -1;
    };

    var shouldDropPreload = function(url){
        if (!url) return false;
        var u = String(url).toLowerCase();
        return (
            u.indexOf('fonts.googleapis.com') !== -1 ||
            u.indexOf('fonts.gstatic.com/') !== -1 ||
            u.indexOf('/wp-content/plugins/contact-form-7/includes/css/styles.css') !== -1 ||
            u.indexOf('challenges.cloudflare.com/cdn-cgi/challenge-platform') !== -1
        );
    };

    var originalCreate = document.createElement;
    document.createElement = function(tagName){
        var el = originalCreate.call(document, tagName);
        if ((tagName || '').toLowerCase() === 'script') {
            try {
                var srcDescriptor = Object.getOwnPropertyDescriptor(HTMLScriptElement.prototype, 'src');
                if (srcDescriptor && srcDescriptor.set) {
                    Object.defineProperty(el, 'src', {
                        configurable: true,
                        get: function(){ return srcDescriptor.get.call(this); },
                        set: function(v){
                            if (shouldBlock(v)) {
                                this.type = 'javascript/blocked';
                                return v;
                            }
                            return srcDescriptor.set.call(this, v);
                        }
                    });
                }
            } catch(e) {}
        }
        return el;
    };

    var originalAppend = Node.prototype.appendChild;
    Node.prototype.appendChild = function(node){
        try {
            if (node && node.tagName === 'SCRIPT' && shouldBlock(node.src || node.getAttribute('src'))) {
                return node;
            }
            if (node && node.tagName === 'LINK' && String(node.rel || '').toLowerCase() === 'preload' && shouldDropPreload(node.href || node.getAttribute('href'))) {
                return node;
            }
        } catch(e) {}
        return originalAppend.call(this, node);
    };

    var originalInsertBefore = Node.prototype.insertBefore;
    Node.prototype.insertBefore = function(node, ref){
        try {
            if (node && node.tagName === 'SCRIPT' && shouldBlock(node.src || node.getAttribute('src'))) {
                return node;
            }
            if (node && node.tagName === 'LINK' && String(node.rel || '').toLowerCase() === 'preload' && shouldDropPreload(node.href || node.getAttribute('href'))) {
                return node;
            }
        } catch(e) {}
        return originalInsertBefore.call(this, node, ref);
    };

    var cleanup = function(root){
        try {
            var scripts = (root || document).querySelectorAll('script[src]');
            scripts.forEach(function(s){
                if (shouldBlock(s.getAttribute('src') || s.src)) s.remove();
            });

            var preloads = (root || document).querySelectorAll('link[rel="preload"][href]');
            preloads.forEach(function(l){
                if (shouldDropPreload(l.getAttribute('href') || l.href)) l.remove();
            });
        } catch(e) {}
    };

    cleanup(document);

    var observer = new MutationObserver(function(mutations){
        mutations.forEach(function(m){
            m.addedNodes.forEach(function(node){
                if (!node || node.nodeType !== 1) return;
                cleanup(node);
            });
        });
    });
    observer.observe(document.documentElement, { childList:true, subtree:true });

    try {
        window.webpushr = function(){};
    } catch(e) {}
})();
</script>
    <?php
}

function cpcs_noise_guard_filter_html( $html ) {
    if ( ! is_string( $html ) || $html === '' ) return $html;

    // Strip WordPress emoji loader/settings blocks to avoid blob worker warnings
    // on hosts where CSP cannot be adjusted directly.
    $html = preg_replace(
        '~<script\b[^>]*id=("|\')wp-emoji-settings\1[^>]*>[\s\S]*?</script>~i',
        '',
        $html
    );

    $html = preg_replace(
        '~<script\b[^>]*\bsrc=("|\')[^"\']*wp-emoji-release\.min\.js[^"\']*\1[^>]*>\s*</script>~i',
        '',
        $html
    );

    $html = preg_replace(
        '~<script\b[^>]*\bsrc=("|\')[^"\']*wp-emoji-loader\.min\.js[^"\']*\1[^>]*>\s*</script>~i',
        '',
        $html
    );

    $html = preg_replace(
        '~<script\b[^>]*type=("|\')module\1[^>]*>[\s\S]*?wp-emoji-settings[\s\S]*?</script>~i',
        '',
        $html
    );

    // Remove any direct Webpushr script include.
    $html = preg_replace(
        '~<script\b[^>]*\bsrc=("|\')[^"\']*(?:cdn\.webpushr\.com|webpushr)[^"\']*\1[^>]*>\s*</script>~i',
        '',
        $html
    );

    // Remove inline Webpushr bootstrap code blocks.
    $html = preg_replace(
        '~<script\b[^>]*>[\s\S]*?(?:WEBPUSHR|_webpushr|window\.webpushr)[\s\S]*?</script>~i',
        '',
        $html
    );

    // Remove known noisy preloads that trigger "preloaded but not used" warnings.
    $html = preg_replace_callback(
        '~<link\b[^>]*\brel=("|\')preload\1[^>]*>~i',
        function ( $m ) {
            $tag = $m[0];
            if ( ! preg_match( '~\bhref=("|\')([^"\']+)\1~i', $tag, $href_match ) ) {
                return $tag;
            }

            $href       = (string) $href_match[2];
            $href_lower = strtolower( $href );

            if (
                cpcs_str_contains( $href_lower, 'fonts.googleapis.com' ) ||
                cpcs_str_contains( $href_lower, 'fonts.gstatic.com/' ) ||
                cpcs_str_contains( $href_lower, '/wp-content/plugins/contact-form-7/includes/css/styles.css' ) ||
                cpcs_str_contains( $href_lower, 'challenges.cloudflare.com/cdn-cgi/challenge-platform' )
            ) {
                return '';
            }

            return $tag;
        },
        $html
    );

    return $html;
}

function cpcs_noise_guard_is_webpushr_asset( $haystack ) {
    return cpcs_str_contains( $haystack, 'webpushr' ) || cpcs_str_contains( $haystack, 'cdn.webpushr.com' );
}
