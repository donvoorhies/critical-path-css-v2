<?php
// modules/gtm.php  –  Google Tag Manager lazy loader
// Defers GTM until first user interaction (scroll, click, keydown, mousemove, touchstart)
// This eliminates the 57 KiB unused JS penalty on first paint.

if ( ! cpcs_get_setting( 'gtm_enabled', false ) ) return;

$gtm_id = trim( cpcs_get_setting( 'gtm_id', '' ) );
if ( ! $gtm_id ) return;

if ( cpcs_get_setting( 'gtm_lazy', true ) ) {
    // ── Lazy GTM: fires after first user interaction ───────────────────────────
    add_action( 'wp_head', 'cpcs_gtm_lazy_head', 1 );
    add_action( 'wp_body_open', 'cpcs_gtm_noscript', 1 );

    function cpcs_gtm_lazy_head() {
        $gtm_id = esc_js( trim( cpcs_get_setting( 'gtm_id', '' ) ) );
        ?>
<!-- GTM lazy-load (Critical Path CSS plugin) -->
<script>
(function(){
    var gtmLoaded = false;
    var GTM_ID    = '<?php echo $gtm_id; ?>';

    function privacyBlocked(){
        try {
            return (
                window.globalPrivacyControl === true ||
                navigator.globalPrivacyControl === true ||
                navigator.doNotTrack === '1' ||
                window.doNotTrack === '1' ||
                navigator.msDoNotTrack === '1'
            );
        } catch(e) {
            return false;
        }
    }

    if (!GTM_ID || privacyBlocked()) return;

    function loadGTM() {
        if (gtmLoaded) return;
        gtmLoaded = true;

        // Standard GTM snippet
        (function(w,d,s,l,i){
            w[l]=w[l]||[];
            w[l].push({'gtm.start': new Date().getTime(), event:'gtm.js'});
            var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),
                dl=l!='dataLayer'?'&l='+l:'';
            j.async=true;
            j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
            f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer',GTM_ID);
    }

    // Trigger on first meaningful interaction
    var events = ['scroll','click','keydown','mousemove','touchstart'];
    function onInteraction() {
        loadGTM();
        events.forEach(function(e){ window.removeEventListener(e, onInteraction, {passive:true}); });
    }
    events.forEach(function(e){ window.addEventListener(e, onInteraction, {passive:true}); });

    // Fallback: load after 5 seconds regardless
    setTimeout(loadGTM, 5000);
})();
</script>
        <?php
    }

    function cpcs_gtm_noscript() {
        $gtm_id = esc_attr( trim( cpcs_get_setting( 'gtm_id', '' ) ) );
        echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtm_id . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
    }

} else {
    // ── Standard (non-lazy) GTM ───────────────────────────────────────────────
    add_action( 'wp_head', 'cpcs_gtm_standard_head', 1 );
    add_action( 'wp_body_open', 'cpcs_gtm_noscript', 1 );

    function cpcs_gtm_standard_head() {
        $gtm_id = esc_js( trim( cpcs_get_setting( 'gtm_id', '' ) ) );
        ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){var blocked=false;try{blocked=(w.globalPrivacyControl===true||navigator.globalPrivacyControl===true||navigator.doNotTrack==='1'||w.doNotTrack==='1'||navigator.msDoNotTrack==='1');}catch(e){}if(!i||blocked)return;w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo $gtm_id; ?>');</script>
<!-- End Google Tag Manager -->
        <?php
    }

    if ( ! function_exists( 'cpcs_gtm_noscript' ) ) {
        function cpcs_gtm_noscript() {
            $gtm_id = esc_attr( trim( cpcs_get_setting( 'gtm_id', '' ) ) );
            echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtm_id . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
        }
    }
}
