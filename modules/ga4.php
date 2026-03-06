<?php
// modules/ga4.php  –  Google Analytics 4 loader (optional lazy-load)

if ( ! cpcs_get_setting( 'ga4_enabled', false ) ) return;
if ( cpcs_get_setting( 'gtm_enabled', false ) ) return;

$ga4_id = trim( cpcs_get_setting( 'ga4_id', '' ) );
if ( ! preg_match( '/^G-[A-Z0-9]+$/i', $ga4_id ) ) return;

if ( cpcs_get_setting( 'ga4_lazy', true ) ) {
    add_action( 'wp_head', 'cpcs_ga4_lazy_loader', 2 );

    function cpcs_ga4_lazy_loader() {
        $ga4_id   = esc_js( trim( cpcs_get_setting( 'ga4_id', '' ) ) );
        $delay_ms = (int) cpcs_get_setting( 'ga4_delay_ms', 5000 );
        $delay_ms = min( 30000, max( 500, $delay_ms ) );
        ?>
<!-- GA4 lazy-load (Critical Path CSS plugin) -->
<script>
(function(){
    var ga4Loaded = false;
    var GA4_ID    = '<?php echo $ga4_id; ?>';

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

    if (!GA4_ID || privacyBlocked()) return;

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function(){ dataLayer.push(arguments); };

    function loadGA4(){
        if (ga4Loaded) return;
        ga4Loaded = true;

        var s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA4_ID);
        document.head.appendChild(s);

        gtag('js', new Date());
        gtag('config', GA4_ID);
    }

    var events = ['scroll','click','keydown','mousemove','touchstart'];
    function onInteraction(){
        loadGA4();
        events.forEach(function(e){ window.removeEventListener(e, onInteraction, {passive:true}); });
    }

    events.forEach(function(e){ window.addEventListener(e, onInteraction, {passive:true}); });
    setTimeout(loadGA4, <?php echo (int) $delay_ms; ?>);
})();
</script>
        <?php
    }
} else {
    add_action( 'wp_head', 'cpcs_ga4_standard_loader', 2 );

    function cpcs_ga4_standard_loader() {
        $ga4_id = esc_attr( trim( cpcs_get_setting( 'ga4_id', '' ) ) );
        ?>
<!-- Google Analytics 4 -->
<script>
(function(){
    var GA4_ID = '<?php echo esc_js( $ga4_id ); ?>';

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

    if (!GA4_ID || privacyBlocked()) return;

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function(){ dataLayer.push(arguments); };

    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA4_ID);
    document.head.appendChild(s);

    gtag('js', new Date());
    gtag('config', GA4_ID);
})();
</script>
<!-- End Google Analytics 4 -->
        <?php
    }
}
