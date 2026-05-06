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
- Hybrid tracking mode (`hybrid`): keeps the JavaScript tracker for full
  engagement metrics (heartbeat / time-on-page, link tracking) and adds
  redundant server-side tracking for orders (deduplicated by Matomo via
  `ec_id`), customer registration and customer login. Intended setup
  for shops that want JavaScript-grade engagement data and ad-blocker-
  resistant business events at the same time.
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

### Changed
- The Matomo `<script>` block in `base.html.twig` is only rendered for
  `client`, `proxy` and `hybrid` modes; in `server` mode no Matomo
  JavaScript is emitted.
- The `<link rel="preconnect">` to the Matomo server is only emitted in
  `client` mode (proxy, hybrid and server modes do not need it).

## [6.1.0] - 2025

### Added
- Dedicated Twig block `tinect_matomo_script` for the Matomo script
  output, so other plugins / themes can override the snippet.

### Changed
- Throw an error for proxy requests without parameters.
