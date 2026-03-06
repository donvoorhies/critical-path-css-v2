=== Critical Path CSS Generator ===
Contributors: donvoorhies
Tags: performance, css, critical css, render-blocking, fonts, google fonts, defer, preload, gtm
Requires at least: 5.9
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 2.0.2
License: GPL-2.0+

Complete page-speed toolkit: critical CSS, stylesheet/script deferral, Google Fonts optimisation, preload hints, and lazy GTM.

== Documentation ==

For full setup and configuration instructions, see the English user guide in this repository:
https://github.com/donvoorhies/critical-path-css-v2/blob/master/USER_GUIDE.md

== Description ==

Version 2 is a full performance toolkit targeting every major PageSpeed Insights opportunity in one plugin:

= Critical CSS (FCP / LCP) =
* Generate above-the-fold CSS automatically from any URL
* Inline critical CSS in `<head>` as a `<style>` block
* Defer full stylesheets to end of `<body>`
* Manual paste-in support for CSS from criticalcss.com

= Google Fonts Optimiser (FCP / LCP) =
* Adds `font-display: swap/optional/fallback` to all Google Fonts requests
* Optional self-hosting: downloads woff2 files to your server, eliminating fonts.gstatic.com DNS lookup
* `<link rel="preconnect">` hints for fonts.googleapis.com + fonts.gstatic.com
* `<link rel="preload">` for specific woff2 files you specify

= Script Deferral (TBT / LCP) =
* Defer or async any script by handle name or URL fragment
* "Defer All" mode with granular exclusion list
* Remove scripts entirely on pages that don't need them
* Fixes the jQuery → hooks → i18n → index.js serial chain

= Preload Hints (LCP) =
* `<link rel="preload" fetchpriority="high">` for your LCP hero image
* Auto-detects featured image on single posts/pages
* woff2 font preloading

= GTM Lazy Loading (TBT / FCP) =
* Defers Google Tag Manager until first user interaction
* Eliminates ~57 KiB of unused JS on first paint
* 5-second fallback ensures tracking always fires
* noscript iframe fallback included

== Installation ==

1. Upload `critical-path-css` to `/wp-content/plugins/`
2. Activate via Plugins → Installed Plugins
3. Go to **Critical CSS → Settings** and configure each tab
4. Go to **Critical CSS** and generate/save critical CSS for your key pages

== Changelog ==

= 2.0.2 =
* Maintenance: synchronized plugin header version, internal `CPCS_VERSION`, and readme stable tag
* Docs: release history cleanup and consistency updates

= 2.0.1 =
* Maintenance: cleaned plugin metadata
* Updated: Plugin URI, Author, and Contributors fields

= 2.0.0 =
* Added: Google Fonts optimiser with font-display and self-hosting
* Added: Script deferral/async/remove module
* Added: Preload hints for LCP image and fonts
* Added: GTM lazy-loader (defer until user interaction)
* Added: Tabbed settings UI
* Added: Reset settings option
* Refactored: Modular file structure

= 1.0.0 =
* Initial release: critical CSS generation and stylesheet deferral
