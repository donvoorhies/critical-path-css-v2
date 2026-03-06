# Critical Path CSS Generator — User Guide

This guide explains how to install, configure, and safely use the Critical Path CSS Generator plugin.

## 1) What the plugin does

Critical Path CSS Generator is a performance toolkit for WordPress. It helps improve Core Web Vitals by:

- Inlining critical CSS for faster first paint
- Deferring non-critical stylesheets
- Optimizing Google Fonts (including optional self-hosting)
- Deferring/async-loading scripts
- Adding preload hints for LCP assets and fonts
- Lazy-loading GTM/GA4 to reduce initial JS cost

## 2) Requirements

- WordPress 5.9+
- PHP 8.0+
- Administrator access (manage_options)

## 3) Installation

1. Upload the plugin folder to: /wp-content/plugins/critical-path-css-v2/
2. In WordPress Admin, go to Plugins.
3. Activate Critical Path CSS Generator.
4. Open Critical CSS in the admin menu.

## 4) Quick start (recommended)

1. Go to Critical CSS → Settings.
2. In Critical CSS tab, keep Defer Full Stylesheets enabled and Minify Output enabled.
3. In Fonts tab, enable Font Optimiser and set font-display to swap (or optional for maximum speed).
4. In Scripts tab, start with Script Deferral enabled but Defer All Scripts disabled.
5. In Preload tab, enable preload and add your LCP hero image URL.
6. In GTM tab, choose ONE analytics source (GTM or GA4) to avoid double tracking.
7. Save settings.
8. Go to Critical CSS main page and generate CSS for key pages.

## 5) Main page: Critical CSS workflow

Path: Critical CSS

### Generate Critical CSS

1. Enter a full page URL.
2. Optional: paste full CSS in the Full CSS box.
3. Optional: click Auto-Fetch CSS to pull styles automatically.
4. Click Generate Critical CSS.
5. Review output.
6. Click Save to Database.

### Manual add/edit

- Use Manually Add / Edit Critical CSS to paste critical CSS from another source.
- Save it with the page URL.

### Saved entries

- Edit: update URL/CSS in modal
- Delete: remove individual entry
- Status includes size and generation time

## 6) Settings tabs explained

Path: Critical CSS → Settings

### A) Critical CSS

- Defer Full Stylesheets: moves full stylesheets near end of body on pages with saved critical CSS
- Minify Output: strips comments/extra whitespace before saving
- Viewport Width/Height: controls generation viewport used for critical CSS extraction
- Exclude URLs: one URL per line where critical CSS should not be inlined

### B) Fonts

- Enable Font Optimiser: rewrites Google Fonts URLs with font-display
- font-display Strategy:
  - swap (recommended balance)
  - optional (best performance, strict loading behavior)
  - fallback
- Self-Host Google Fonts: stores fonts in /wp-content/uploads/cpcs-fonts/
- Purge Font Cache: clears downloaded font cache
- Preload woff2 Files: one URL per line; prefer local URLs when self-hosting is enabled

### C) Scripts

- Enable Script Deferral: activates script optimization rules
- Defer All Scripts: aggressive mode; test thoroughly before production use
- Exclude from Deferral: scripts never modified
- Async List: scripts loaded with async
- Defer List: scripts loaded with defer
- Remove List: scripts removed completely (use with caution)

### D) Preload

- Enable Preload: outputs preload links in head
- LCP Image URL: your hero image URL for faster LCP
- Preload Fonts: woff2 URLs (shared with Fonts tab)
- Save + Clean Preload List: removes incompatible Google-hosted font URLs when self-hosting is enabled

### E) GTM

- Enable GTM via Plugin + GTM Container ID (example: GTM-XXXXXXX)
- Lazy Load GTM: waits for user interaction, with fallback load
- Enable GA4 via Plugin + GA4 Measurement ID (example: G-XXXXXXXXXX)
- Lazy Load GA4 + Fallback Delay

Important: Use either GTM via plugin or GA4 via plugin, not both. The plugin includes a fail-safe to avoid duplicate tracking.

### F) Danger Zone

- Purge All Saved Critical CSS: removes all stored critical CSS entries
- Reset Settings to Defaults: restores plugin defaults

Both actions are irreversible.

## 7) Recommended rollout process

1. Apply settings on staging first.
2. Generate and save critical CSS for:
   - Homepage
   - Main landing pages
   - Top blog or product templates
3. Test visual stability and functionality on desktop/mobile.
4. Run PageSpeed Insights before/after.
5. Deploy to production and monitor analytics/events.

## 8) Troubleshooting

### Layout flashes or broken styles

- Regenerate critical CSS for affected URLs.
- Temporarily disable Defer Full Stylesheets to isolate issue.
- Increase viewport dimensions and regenerate.

### Missing fonts or 404 on preloaded fonts

- If self-hosting is enabled, replace fonts.gstatic.com preload URLs with local uploads URLs.
- Use Save + Clean Preload List.

### JavaScript features break

- Disable Defer All Scripts.
- Add problematic scripts to Exclude from Deferral.
- Move scripts from async to defer or remove rules one by one.

### Duplicate analytics events

- Ensure only one injection path is active:
  - GTM via plugin, or
  - GA4 via plugin, or
  - external theme/plugin setup

## 9) Best practices

- Start conservative, then optimize incrementally.
- Keep a small exclude list for scripts that must stay blocking.
- Re-test critical CSS after theme/layout changes.
- Revisit LCP image and font preload lists when design assets change.

## 10) Version reference

This guide matches plugin version 2.0.2.