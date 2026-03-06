<?php
// modules/admin.php  –  Admin menu, pages, assets

// ── Menu ─────────────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'cpcs_admin_menu' );
function cpcs_admin_menu() {
    add_menu_page(
        'Critical Path CSS', 'Critical CSS', 'manage_options',
        'critical-path-css', 'cpcs_admin_page',
        'dashicons-performance', 80
    );
    add_submenu_page(
        'critical-path-css', 'Settings', 'Settings', 'manage_options',
        'critical-path-css-settings', 'cpcs_settings_page'
    );
}

// ── Assets ───────────────────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'cpcs_admin_assets' );
function cpcs_admin_assets( $hook ) {
    if ( strpos( $hook, 'critical-path-css' ) === false ) return;
    wp_enqueue_style(  'cpcs-admin', CPCS_URL . 'assets/admin.css', [], CPCS_VERSION );
    wp_enqueue_script( 'cpcs-admin', CPCS_URL . 'assets/admin.js', ['jquery'], CPCS_VERSION, true );
    wp_localize_script( 'cpcs-admin', 'CPCS', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'cpcs_nonce' ),
        'strings' => [
            'generating'     => 'Generating…',
            'success'        => 'Saved!',
            'error'          => 'Error — check console.',
            'confirm_delete' => 'Delete this entry?',
            'fetching'       => 'Fetching CSS…',
            'purging'        => 'Purging…',
            'purged'         => 'Font cache cleared!',
        ],
    ] );
}

function cpcs_has_stale_gstatic_preloads( $settings ) {
    if ( empty( $settings['fonts_self_host'] ) ) return false;

    $list = array_filter( array_map( 'trim', explode( "\n", (string) ( $settings['preload_fonts'] ?? '' ) ) ) );
    foreach ( $list as $url ) {
        $host = (string) parse_url( $url, PHP_URL_HOST );
        if ( strtolower( $host ) === 'fonts.gstatic.com' ) {
            return true;
        }
    }

    return false;
}

function cpcs_normalize_preload_fonts( $raw, $self_host_enabled, &$removed_gstatic = 0 ) {
    $removed_gstatic = 0;
    $lines           = array_filter( array_map( 'trim', explode( "\n", (string) $raw ) ) );
    $clean           = [];
    $seen            = [];

    foreach ( $lines as $line ) {
        if ( ! filter_var( $line, FILTER_VALIDATE_URL ) ) {
            continue;
        }

        $host = strtolower( (string) parse_url( $line, PHP_URL_HOST ) );
        if ( $self_host_enabled && ( $host === 'fonts.gstatic.com' || $host === 'fonts.googleapis.com' ) ) {
            $removed_gstatic++;
            continue;
        }

        if ( isset( $seen[ $line ] ) ) {
            continue;
        }

        $seen[ $line ] = true;
        $clean[]       = $line;
    }

    return implode( "\n", $clean );
}

// ── Main page: Critical CSS tab ───────────────────────────────────────────────
function cpcs_admin_page() {
    global $wpdb;
    $entries = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cpcs_critical_css ORDER BY generated_at DESC" );
    ?>
    <div class="wrap cpcs-wrap">
        <h1><span class="dashicons dashicons-performance"></span> Critical Path CSS Generator <span class="cpcs-version">v<?php echo CPCS_VERSION; ?></span></h1>

        <div class="cpcs-grid">
            <div class="cpcs-card">
                <h2>Generate Critical CSS</h2>
                <table class="form-table cpcs-form-table">
                    <tr>
                        <th><label for="cpcs-page-url">Page URL</label></th>
                        <td>
                            <input type="url" id="cpcs-page-url" class="regular-text" placeholder="https://example.com/page/" />
                            <p class="description">The URL to generate critical CSS for.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cpcs-full-css">Full CSS</label></th>
                        <td>
                            <textarea id="cpcs-full-css" rows="8" class="large-text code" placeholder="Paste CSS here, or leave blank to auto-fetch…"></textarea>
                            <p class="description">
                                Leave blank to auto-fetch all stylesheets.
                                <button type="button" id="cpcs-fetch-css" class="button button-small">Auto-Fetch CSS</button>
                            </p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" id="cpcs-generate" class="button button-primary button-hero">Generate Critical CSS</button>
                    <span id="cpcs-spinner" class="spinner cpcs-spinner" style="display:none;"></span>
                </p>
                <div id="cpcs-result-wrap" style="display:none;">
                    <h3>Critical CSS Output</h3>
                    <div class="cpcs-toolbar">
                        <button type="button" id="cpcs-copy-btn" class="button button-small">Copy</button>
                        <button type="button" id="cpcs-save-btn" class="button button-primary button-small">Save to Database</button>
                    </div>
                    <textarea id="cpcs-output" rows="12" class="large-text code" readonly></textarea>
                    <p id="cpcs-status-msg" class="cpcs-msg" style="display:none;"></p>
                </div>
            </div>

            <div class="cpcs-card">
                <h2>Manually Add / Edit Critical CSS</h2>
                <p class="description">Paste in CSS from <a href="https://criticalcss.com" target="_blank">criticalcss.com</a> or any other source.</p>
                <table class="form-table cpcs-form-table">
                    <tr>
                        <th><label for="cpcs-manual-url">Page URL</label></th>
                        <td><input type="url" id="cpcs-manual-url" class="regular-text" placeholder="https://example.com/page/" /></td>
                    </tr>
                    <tr>
                        <th><label for="cpcs-manual-css">Critical CSS</label></th>
                        <td><textarea id="cpcs-manual-css" rows="8" class="large-text code"></textarea></td>
                    </tr>
                </table>
                <p>
                    <button type="button" id="cpcs-manual-save" class="button button-primary">Save Critical CSS</button>
                    <span id="cpcs-manual-spinner" class="spinner cpcs-spinner" style="display:none;"></span>
                </p>
                <p id="cpcs-manual-msg" class="cpcs-msg" style="display:none;"></p>
            </div>
        </div>

        <div class="cpcs-card cpcs-full-width">
            <h2>Saved Entries</h2>
            <?php if ( empty( $entries ) ) : ?>
                <p>No entries yet. Generate critical CSS for a page above.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th style="width:40%">Page URL</th>
                    <th style="width:20%">Size</th>
                    <th style="width:20%">Generated</th>
                    <th style="width:20%">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $entries as $e ) : ?>
                <tr id="cpcs-row-<?php echo (int)$e->id; ?>">
                    <td><a href="<?php echo esc_url($e->page_url); ?>" target="_blank"><?php echo esc_html($e->page_url); ?></a></td>
                    <td><span class="cpcs-badge cpcs-badge-active">&#10003; Active</span> <?php echo number_format(strlen($e->critical_css)); ?> B</td>
                    <td><?php echo esc_html( get_date_from_gmt($e->generated_at, get_option('date_format').' '.get_option('time_format')) ); ?></td>
                    <td class="cpcs-actions">
                        <button class="button button-small cpcs-view-btn"
                            data-id="<?php echo (int)$e->id; ?>"
                            data-url="<?php echo esc_attr($e->page_url); ?>"
                            data-css="<?php echo esc_attr($e->critical_css); ?>">Edit</button>
                        <button class="button button-small button-link-delete cpcs-delete-btn" data-id="<?php echo (int)$e->id; ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Edit modal -->
        <div id="cpcs-modal-overlay" style="display:none;">
            <div id="cpcs-modal">
                <button id="cpcs-modal-close" class="cpcs-modal-close">&times;</button>
                <h2>Edit Critical CSS</h2>
                <input type="hidden" id="cpcs-modal-id" />
                <label>Page URL<input type="url" id="cpcs-modal-url" class="large-text" /></label>
                <label style="margin-top:12px;display:block;">Critical CSS
                    <textarea id="cpcs-modal-css" rows="14" class="large-text code"></textarea>
                </label>
                <p style="margin-top:12px;">
                    <button type="button" id="cpcs-modal-save" class="button button-primary">Save Changes</button>
                    <span id="cpcs-modal-spinner" class="spinner cpcs-spinner" style="display:none;"></span>
                    <span id="cpcs-modal-msg" class="cpcs-msg" style="display:none;"></span>
                </p>
            </div>
        </div>
    </div>
    <?php
}

// ── Settings page: tabbed ─────────────────────────────────────────────────────
function cpcs_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $removed_preload_urls = 0;
    $clean_preloads_requested = false;
    $ga4_auto_disabled = false;

    if ( ( isset( $_POST['cpcs_save_settings'] ) || isset( $_POST['cpcs_clean_preloads'] ) ) && check_admin_referer( 'cpcs_save_settings' ) ) {
        $clean_preloads_requested = ! empty( $_POST['cpcs_clean_preloads'] );
        // Load current saved settings so tabs we're NOT on don't lose their values.
        $current = wp_parse_args( get_option( 'cpcs_settings', [] ), cpcs_defaults() );

        // Only update the fields that belong to the current tab.
        // Checkboxes NOT in POST = unchecked = false, so we handle them explicitly.
        $tab = sanitize_key( $_POST['cpcs_current_tab'] ?? 'critical' );

        if ( $tab === 'critical' ) {
            $current['defer_stylesheets'] = ! empty( $_POST['defer_stylesheets'] );
            $current['minify_output']     = ! empty( $_POST['minify_output'] );
            $current['exclude_urls']      = sanitize_textarea_field( wp_unslash( $_POST['exclude_urls'] ?? '' ) );
            $current['viewport_width']    = absint( $_POST['viewport_width']  ?? 1300 );
            $current['viewport_height']   = absint( $_POST['viewport_height'] ?? 900  );
        } elseif ( $tab === 'fonts' ) {
            $current['fonts_enabled']   = ! empty( $_POST['fonts_enabled'] );
            $current['fonts_display']   = sanitize_text_field( $_POST['fonts_display'] ?? 'swap' );
            $current['fonts_self_host'] = ! empty( $_POST['fonts_self_host'] );
            $current['preload_fonts']   = cpcs_normalize_preload_fonts(
                sanitize_textarea_field( wp_unslash( $_POST['preload_fonts'] ?? '' ) ),
                $current['fonts_self_host'],
                $removed_preload_urls
            );
        } elseif ( $tab === 'scripts' ) {
            $current['scripts_enabled']      = ! empty( $_POST['scripts_enabled'] );
            $current['scripts_defer_all']    = ! empty( $_POST['scripts_defer_all'] );
            $current['scripts_async_list']   = sanitize_textarea_field( wp_unslash( $_POST['scripts_async_list']   ?? '' ) );
            $current['scripts_defer_list']   = sanitize_textarea_field( wp_unslash( $_POST['scripts_defer_list']   ?? '' ) );
            $current['scripts_remove_list']  = sanitize_textarea_field( wp_unslash( $_POST['scripts_remove_list']  ?? '' ) );
            $current['scripts_exclude_list'] = sanitize_textarea_field( wp_unslash( $_POST['scripts_exclude_list'] ?? '' ) );
        } elseif ( $tab === 'preload' ) {
            $current['preload_enabled']   = ! empty( $_POST['preload_enabled'] );
            $current['preload_lcp_image'] = esc_url_raw( wp_unslash( $_POST['preload_lcp_image'] ?? '' ) );
            $current['preload_fonts']     = cpcs_normalize_preload_fonts(
                sanitize_textarea_field( wp_unslash( $_POST['preload_fonts'] ?? '' ) ),
                ! empty( $current['fonts_self_host'] ),
                $removed_preload_urls
            );
        } elseif ( $tab === 'gtm' ) {
            $current['gtm_enabled'] = ! empty( $_POST['gtm_enabled'] );
            $current['gtm_id']      = sanitize_text_field( $_POST['gtm_id'] ?? '' );
            $current['gtm_lazy']    = ! empty( $_POST['gtm_lazy'] );
            $current['ga4_enabled'] = ! empty( $_POST['ga4_enabled'] );
            $current['ga4_id']      = sanitize_text_field( $_POST['ga4_id'] ?? '' );
            $current['ga4_lazy']    = ! empty( $_POST['ga4_lazy'] );
            $current['ga4_delay_ms']= min( 30000, max( 500, absint( $_POST['ga4_delay_ms'] ?? 5000 ) ) );

            // Fail-safe: avoid duplicate analytics events from plugin-side GTM + GA4.
            if ( $current['gtm_enabled'] && $current['ga4_enabled'] ) {
                $current['ga4_enabled'] = false;
                $ga4_auto_disabled = true;
            }
        }

        update_option( 'cpcs_settings', $current );
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        if ( $clean_preloads_requested && in_array( $tab, [ 'fonts', 'preload' ], true ) ) {
            if ( $removed_preload_urls > 0 ) {
                echo '<div class="notice notice-warning is-dismissible"><p>Preload list cleaned. Removed ' . (int) $removed_preload_urls . ' external Google Fonts URL(s) because self-hosting is enabled.</p></div>';
            } else {
                echo '<div class="notice notice-info is-dismissible"><p>Preload list cleaned. No external Google Fonts URLs were found.</p></div>';
            }
        } elseif ( $removed_preload_urls > 0 ) {
            echo '<div class="notice notice-warning is-dismissible"><p>Removed ' . (int) $removed_preload_urls . ' external Google Fonts preload URL(s) because self-hosting is enabled.</p></div>';
        }

        if ( $ga4_auto_disabled ) {
            echo '<div class="notice notice-warning is-dismissible"><p>Fail-safe applied: GA4 via plugin was disabled because GTM via plugin is enabled. Use one source to avoid double tracking.</p></div>';
        }
    }

    $s = get_option( 'cpcs_settings', [] );
    $tab = sanitize_key( $_GET['tab'] ?? 'critical' );
    $tabs = [ 'critical' => 'Critical CSS', 'fonts' => 'Fonts', 'scripts' => 'Scripts', 'preload' => 'Preload', 'gtm' => 'GTM', 'danger' => 'Danger Zone' ];
    $show_font_preload_warning = cpcs_has_stale_gstatic_preloads( $s );
    ?>
    <div class="wrap cpcs-wrap">
        <h1><span class="dashicons dashicons-performance"></span> Critical Path CSS — Settings</h1>

        <nav class="nav-tab-wrapper cpcs-tabs">
            <?php foreach ( $tabs as $key => $label ) : ?>
            <a href="?page=critical-path-css-settings&tab=<?php echo $key; ?>"
               class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <form method="post" action="">
            <?php wp_nonce_field( 'cpcs_save_settings' ); ?>
            <input type="hidden" name="cpcs_current_tab" value="<?php echo esc_attr( $tab ); ?>" />

            <?php if ( $tab === 'critical' ) : ?>
            <div class="cpcs-card" style="margin-top:20px;">
                <h2>Critical CSS Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>Defer Full Stylesheets</th>
                        <td><label><input type="checkbox" name="defer_stylesheets" value="1" <?php checked( $s['defer_stylesheets'] ?? true ); ?> />
                            Move stylesheets to <code>&lt;/body&gt;</code> on pages with saved critical CSS</label></td>
                    </tr>
                    <tr>
                        <th>Minify Output</th>
                        <td><label><input type="checkbox" name="minify_output" value="1" <?php checked( $s['minify_output'] ?? true ); ?> />
                            Strip comments and whitespace before saving</label></td>
                    </tr>
                    <tr>
                        <th>Viewport Width (px)</th>
                        <td><input type="number" name="viewport_width" value="<?php echo (int)($s['viewport_width'] ?? 1300); ?>" class="small-text" min="320" max="3840" /></td>
                    </tr>
                    <tr>
                        <th>Viewport Height (px)</th>
                        <td><input type="number" name="viewport_height" value="<?php echo (int)($s['viewport_height'] ?? 900); ?>" class="small-text" min="320" max="3840" /></td>
                    </tr>
                    <tr>
                        <th>Exclude URLs</th>
                        <td>
                            <textarea name="exclude_urls" rows="4" class="large-text"><?php echo esc_textarea( $s['exclude_urls'] ?? '' ); ?></textarea>
                            <p class="description">One URL per line. Critical CSS will not be inlined on these pages.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php elseif ( $tab === 'fonts' ) : ?>
            <div class="cpcs-card" style="margin-top:20px;">
                <h2>Google Fonts Optimiser</h2>
                <p class="description">Fixes the <strong>font-display</strong> and <strong>duplicate Google Fonts requests</strong> issues flagged by PageSpeed Insights.</p>
                <?php if ( $show_font_preload_warning ) : ?>
                <div class="notice notice-warning inline"><p><strong>Warning:</strong> Self-hosting is enabled, but <code>Preload woff2 Files</code> still contains <code>fonts.gstatic.com</code> URLs. Remove or replace them with local <code>/wp-content/uploads/cpcs-fonts/...</code> URLs to avoid 404 and unused preload warnings.</p></div>
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th>Enable Font Optimiser</th>
                        <td><label><input type="checkbox" name="fonts_enabled" value="1" <?php checked( $s['fonts_enabled'] ?? true ); ?> />
                            Rewrite Google Fonts URLs to include <code>font-display</code></label></td>
                    </tr>
                    <tr>
                        <th>font-display Strategy</th>
                        <td>
                            <select name="fonts_display">
                                <?php foreach ( ['swap'=>'swap (recommended)','optional'=>'optional (fastest)','fallback'=>'fallback'] as $v => $l ) : ?>
                                <option value="<?php echo $v; ?>" <?php selected( $s['fonts_display'] ?? 'swap', $v ); ?>><?php echo esc_html($l); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><strong>swap</strong> shows fallback font immediately, then swaps when loaded. <strong>optional</strong> only uses the web font if it loads very quickly — best LCP score.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Self-Host Google Fonts</th>
                        <td><label><input type="checkbox" name="fonts_self_host" value="1" <?php checked( $s['fonts_self_host'] ?? false ); ?> />
                            Download fonts to <code>/wp-content/uploads/cpcs-fonts/</code> and serve locally</label>
                            <p class="description">Eliminates the external <code>fonts.gstatic.com</code> DNS lookup. Fonts are cached for one week.</p>
                            <button type="button" id="cpcs-purge-fonts" class="button button-small" style="margin-top:6px;">Purge Font Cache</button>
                            <span id="cpcs-purge-fonts-msg" class="cpcs-msg" style="display:none;margin-left:8px;"></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Preload woff2 Files</th>
                        <td>
                            <textarea name="preload_fonts" rows="4" class="large-text" placeholder="https://example.com/wp-content/uploads/cpcs-fonts/font.woff2"><?php echo esc_textarea( $s['preload_fonts'] ?? '' ); ?></textarea>
                            <p class="description">One woff2 URL per line. These will be output as <code>&lt;link rel="preload" as="font"&gt;</code> in <code>&lt;head&gt;</code>. If <strong>Self-Host Google Fonts</strong> is enabled, only local font URLs should be listed here.</p>
                            <p><button type="submit" name="cpcs_clean_preloads" value="1" class="button button-secondary">Save + Clean Preload List</button></p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php elseif ( $tab === 'scripts' ) : ?>
            <div class="cpcs-card" style="margin-top:20px;">
                <h2>Script Deferral</h2>
                <p class="description">Fixes the <strong>render-blocking JS chain</strong> (jQuery → hooks → i18n → index.js) and reduces main-thread blocking time.</p>
                <table class="form-table">
                    <tr>
                        <th>Enable Script Deferral</th>
                        <td><label><input type="checkbox" name="scripts_enabled" value="1" <?php checked( $s['scripts_enabled'] ?? true ); ?> />
                            Apply defer/async rules below</label></td>
                    </tr>
                    <tr>
                        <th>Defer All Scripts</th>
                        <td><label><input type="checkbox" name="scripts_defer_all" value="1" <?php checked( $s['scripts_defer_all'] ?? false ); ?> />
                            Add <code>defer</code> to every <code>&lt;script&gt;</code> tag (except excluded)</label>
                            <p class="description">⚠ Test carefully. Scripts that depend on DOM-ready order may break.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Exclude from Deferral</th>
                        <td>
                            <textarea name="scripts_exclude_list" rows="3" class="large-text" placeholder="jquery"><?php echo esc_textarea( $s['scripts_exclude_list'] ?? 'jquery' ); ?></textarea>
                            <p class="description">Handle names or URL fragments — one per line. These will never be touched.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Async List</th>
                        <td>
                            <textarea name="scripts_async_list" rows="4" class="large-text" placeholder="google-tag-manager&#10;webpushr"><?php echo esc_textarea( $s['scripts_async_list'] ?? '' ); ?></textarea>
                            <p class="description">Scripts to load with <code>async</code> (analytics, chat widgets, etc). One handle or URL fragment per line.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Defer List</th>
                        <td>
                            <textarea name="scripts_defer_list" rows="4" class="large-text" placeholder="wp-block-library&#10;wc-cart-fragments"><?php echo esc_textarea( $s['scripts_defer_list'] ?? '' ); ?></textarea>
                            <p class="description">Scripts to load with <code>defer</code>. One handle or URL fragment per line.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Remove List</th>
                        <td>
                            <textarea name="scripts_remove_list" rows="3" class="large-text"><?php echo esc_textarea( $s['scripts_remove_list'] ?? '' ); ?></textarea>
                            <p class="description">Scripts to remove entirely from this page. Use with caution.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php elseif ( $tab === 'preload' ) : ?>
            <div class="cpcs-card" style="margin-top:20px;">
                <h2>Preload Hints</h2>
                <p class="description">Outputs <code>&lt;link rel="preload"&gt;</code> hints in <code>&lt;head&gt;</code> to tell the browser to fetch critical assets immediately.</p>
                <?php if ( $show_font_preload_warning ) : ?>
                <div class="notice notice-warning inline"><p><strong>Warning:</strong> Self-hosting is enabled, but <code>Preload Fonts</code> still contains <code>fonts.gstatic.com</code> URLs. Use local font URLs to avoid stale preloads.</p></div>
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th>Enable Preload</th>
                        <td><label><input type="checkbox" name="preload_enabled" value="1" <?php checked( $s['preload_enabled'] ?? true ); ?> />
                            Output preload tags in <code>&lt;head&gt;</code></label></td>
                    </tr>
                    <tr>
                        <th>LCP Image URL</th>
                        <td>
                            <input type="url" name="preload_lcp_image" value="<?php echo esc_attr( $s['preload_lcp_image'] ?? '' ); ?>" class="large-text" placeholder="https://example.com/wp-content/uploads/hero.webp" />
                            <p class="description">The above-the-fold hero/banner image that is your Largest Contentful Paint element. Find it in the PageSpeed LCP breakdown. Leave blank to auto-detect from featured image on single posts/pages.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Preload Fonts</th>
                        <td>
                            <textarea name="preload_fonts" rows="4" class="large-text" placeholder="https://example.com/wp-content/uploads/cpcs-fonts/font.woff2"><?php echo esc_textarea( $s['preload_fonts'] ?? '' ); ?></textarea>
                            <p class="description">woff2 URLs to preload (same list as the Fonts tab). If Google Fonts are self-hosted, keep this list local (avoid <code>fonts.gstatic.com</code> URLs).</p>
                            <p><button type="submit" name="cpcs_clean_preloads" value="1" class="button button-secondary">Save + Clean Preload List</button></p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php elseif ( $tab === 'gtm' ) : ?>
            <div class="cpcs-card" style="margin-top:20px;">
                <h2>Google Tag Manager</h2>
                <p class="description">Lazy-loading GTM until first user interaction eliminates <strong>~57 KiB of unused JavaScript</strong> on initial page load.</p>
                <table class="form-table">
                    <tr>
                        <th>Enable GTM via Plugin</th>
                        <td>
                            <label><input type="checkbox" name="gtm_enabled" value="1" <?php checked( $s['gtm_enabled'] ?? false ); ?> />
                            Let this plugin inject GTM</label>
                            <p class="description">⚠ Remove your existing GTM snippet from your theme/other plugins first to avoid double-firing.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>GTM Container ID</th>
                        <td>
                            <input type="text" name="gtm_id" value="<?php echo esc_attr( $s['gtm_id'] ?? '' ); ?>" class="regular-text" placeholder="GTM-XXXXXXX" />
                        </td>
                    </tr>
                    <tr>
                        <th>Lazy Load GTM</th>
                        <td>
                            <label><input type="checkbox" name="gtm_lazy" value="1" <?php checked( $s['gtm_lazy'] ?? true ); ?> />
                            Defer GTM until first user interaction (scroll, click, keydown…)</label>
                            <p class="description">Recommended. GTM loads after 5 seconds regardless as a fallback. Disable to use standard (immediate) GTM loading.</p>
                        </td>
                    </tr>
                    <tr><th colspan="2"><hr style="border:0;border-top:1px solid #dcdcde;margin:8px 0;"></th></tr>
                    <tr>
                        <th>Enable GA4 via Plugin</th>
                        <td>
                            <label><input type="checkbox" name="ga4_enabled" value="1" <?php checked( $s['ga4_enabled'] ?? false ); ?> />
                            Let this plugin inject Google Analytics 4 (<code>gtag.js</code>)</label>
                            <p class="description">Use this only if you are <strong>not</strong> loading GA4 from theme, Site Kit, or GTM. Avoid double tracking.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>GA4 Measurement ID</th>
                        <td>
                            <input type="text" name="ga4_id" value="<?php echo esc_attr( $s['ga4_id'] ?? '' ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX" />
                        </td>
                    </tr>
                    <tr>
                        <th>Lazy Load GA4</th>
                        <td>
                            <label><input type="checkbox" name="ga4_lazy" value="1" <?php checked( $s['ga4_lazy'] ?? true ); ?> />
                            Defer GA4 until first user interaction</label>
                        </td>
                    </tr>
                    <tr>
                        <th>GA4 Fallback Delay (ms)</th>
                        <td>
                            <input type="number" name="ga4_delay_ms" value="<?php echo (int) ( $s['ga4_delay_ms'] ?? 5000 ); ?>" class="small-text" min="500" max="30000" step="100" />
                            <p class="description">Fallback timer to load GA4 even without interaction. Recommended: <strong>5000</strong> ms.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php elseif ( $tab === 'danger' ) : ?>
            <div class="cpcs-card cpcs-danger-card" style="margin-top:20px;">
                <h2>⚠ Danger Zone</h2>
                <p>These actions are irreversible.</p>
                <table class="form-table">
                    <tr>
                        <th>Purge All Critical CSS</th>
                        <td>
                            <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=cpcs_purge_all'), 'cpcs_purge_all' ); ?>"
                               class="button button-secondary"
                               onclick="return confirm('Delete ALL saved critical CSS entries?')">
                               Purge All Saved Critical CSS
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th>Reset All Settings</th>
                        <td>
                            <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=cpcs_reset_settings'), 'cpcs_reset_settings' ); ?>"
                               class="button button-secondary"
                               onclick="return confirm('Reset all plugin settings to defaults?')">
                               Reset Settings to Defaults
                            </a>
                        </td>
                    </tr>
                </table>
                <!-- No submit button needed on danger tab -->
                <?php echo '<style>.cpcs-settings-submit{ display:none }</style>'; ?>
            </div>
            <?php endif; ?>

            <p class="submit cpcs-settings-submit">
                <input type="submit" name="cpcs_save_settings" class="button button-primary" value="Save Settings" />
            </p>
        </form>
    </div>
    <?php
}

// ── Admin-post handlers ───────────────────────────────────────────────────────
add_action( 'admin_post_cpcs_purge_all', function() {
    if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'cpcs_purge_all' ) ) wp_die();
    global $wpdb; $wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'cpcs_critical_css' );
    wp_redirect( admin_url( 'admin.php?page=critical-path-css-settings&tab=danger&purged=1' ) ); exit;
} );

add_action( 'admin_post_cpcs_reset_settings', function() {
    if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'cpcs_reset_settings' ) ) wp_die();
    delete_option( 'cpcs_settings' ); cpcs_activate();
    wp_redirect( admin_url( 'admin.php?page=critical-path-css-settings&tab=danger&reset=1' ) ); exit;
} );
