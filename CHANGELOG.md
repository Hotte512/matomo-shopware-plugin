# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Real server-side tracking mode (`server`): page views, product views,
  site search, cart add/remove, checkout funnel goals, orders, customer
  registration and login are tracked from PHP via the existing async
  Symfony Messenger pipeline. Resistant to ad-blockers and missing
  JavaScript.
- Hybrid tracking mode (`hybrid`): every event is tracked server-side,
  the browser only emits Matomo `ping=1` heartbeats so that
  time-on-page / engagement metrics are attached to the existing
  server-tracked page view via a shared cookieless visitor ID. The
  full Matomo tracker JS is intentionally not loaded - this way
  adblockers cannot strip page views, products, cart actions or
  orders. Heartbeats go through the `/mtmtrpr` proxy route.
- Twig function `tinect_matomo_visitor_id()` exposing the cookieless
  visitor ID hash to templates (used by hybrid mode to share the
  visitor ID between server-side events and JavaScript heartbeats).
- New optional setting `hybridHeartbeatUrl`: when set, browser
  heartbeats in hybrid mode are sent to this URL or path directly
  (e.g. a same-origin Matomo subdirectory) instead of going through
  the built-in `/mtmtrpr` proxy and the messenger queue. Empty value
  keeps the existing proxy behavior, so existing installations are
  unaffected.
- New configuration option `trackingMode` (single-select: `client` /
  `proxy` / `hybrid` / `server`) replaces the boolean
  `activateProxyTracking`. Existing installations are migrated on plugin
  update: `activateProxyTracking=true` becomes `trackingMode=proxy`.
- New configuration options for Matomo goal IDs:
  `goalIdRegister`, `goalIdCartView`, `goalIdCheckoutConfirm`. When set,
  the matching server-side event triggers the configured Matomo goal.
- New service `Tinect\Matomo\Tracking\VisitorIdResolver` derives a
  cookieless 16-hex visitor ID from the customer ID (logged-in) or
  session ID (guest), salted with `%kernel.secret%`. No raw customer
  identifiers leave the shop.
- New service `Tinect\Matomo\Tracking\TrackingPayloadBuilder` builds
  Matomo HTTP API payloads for page views, product views, site search,
  goals, ecommerce orders and custom events.
- New service `Tinect\Matomo\Tracking\ServerSideTracker` gates dispatch
  on the configured tracking mode and forwards events asynchronously
  through the existing `TrackMessage` / `TrackHandler` pipeline.

### Fixed
- Server-side tracked URLs are now the customer-facing SEO URLs
  (e.g. `/sandkasten-paula-fichte`) instead of the internal
  `/detail/{id}` form. Shopware's `RequestTransformer` rewrites the
  request URI to the technical route before the page-loaded events
  fire; the new `StaticHelper::buildStorefrontUrl()` reads the
  preserved `RequestTransformer::ORIGINAL_REQUEST_URI` and
  `SALES_CHANNEL_ABSOLUTE_BASE_URL` request attributes (with a
  graceful fallback to `Request::getRequestUri()` /
  `getSchemeAndHttpHost()`) and is used by every subscriber and the
  `ServerSideTracker` fallback so Matomo records the human-readable
  URL.
- `MatomoAnalyticsPlugin` (the bundled storefront JS plugin) no longer
  throws `TypeError: window.mTrackCall is not a function` in `hybrid`
  and `server` tracking modes. The plugin now checks for the function
  before calling it; in modes where the inline tracker script is
  intentionally not rendered the call is skipped, in `client` / `proxy`
  modes the behavior is unchanged.

### Changed
- The Matomo `<script>` block in `base.html.twig` is only rendered for
  `client`, `proxy` and `hybrid` modes; in `server` mode no Matomo
  JavaScript is emitted.
- The `<link rel="preconnect">` to the Matomo server is only emitted in
  `client` mode (proxy, hybrid and server modes do not need it).
- README rewritten to document tracking modes, configuration fields,
  the asynchronous worker requirement, Matomo-side prerequisites,
  privacy considerations, verification steps and troubleshooting.
- README "Matomo-side setup" section expanded with step-by-step
  instructions for finding Site-ID, creating an auth token, and
  finding or creating goal IDs in the Matomo backend, including the
  exact admin paths and the consequences of misconfigured values.
- README "Google Ads (GCLID) attribution" sub-section documenting that
  the plugin forwards the GCLID as part of the page URL and the two
  ways to attribute conversions to Google Ads in Matomo: registering
  `gclid` as a campaign parameter, or adding UTM parameters to the
  Google Ads Final URL suffix.

## [6.1.0] - 2025

### Added
- Dedicated Twig block `tinect_matomo_script` for the Matomo script
  output, so other plugins / themes can override the snippet.

### Changed
- Throw an error for proxy requests without parameters.
