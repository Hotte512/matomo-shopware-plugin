# Matomo Shopware 6 Plugin

This plugin for Shopware 6 connects your shop to the Open Source and self-hostable Matomo platform.
The plugin is based as fork from [Jinya-CMS/matomo-shopware-plugin](https://github.com/Jinya-CMS/matomo-shopware-plugin).

## Features

* Four tracking modes: `client`, `proxy`, `hybrid`, `server` (see [Tracking modes](#tracking-modes))
* Page views, product views, site search, cart, orders, customer registration and login
* Cookieless server-side visitor identification (SHA-256 hashed customer / session ID)
* Asynchronous forwarding to Matomo via the Symfony Messenger queue
* Adblocker-resistant order tracking (hybrid and server modes)

## Installation

```bash
composer require tinect/matomo
bin/console plugin:refresh
bin/console plugin:install --activate TinectMatomo
bin/console cache:clear
```

If the plugin is already installed and you are upgrading, run:

```bash
bin/console plugin:update TinectMatomo
bin/console cache:clear
```

The update migrates the legacy `activateProxyTracking=true` setting to the
new `trackingMode=proxy` automatically. Fresh installations default to
`trackingMode=client`.

## Configuration

Open the Shopware administration and navigate to
**Extensions → Tinect Matomo → Configure**.

| Field | Required | Notes |
| --- | --- | --- |
| Matomo Server (URL) | yes | Base URL of your Matomo instance |
| Matomo Site-ID | yes | Numeric site ID from Matomo |
| Tracking mode | yes | `client` / `proxy` / `hybrid` / `server` |
| Matomo Auth-Token | for `proxy`, `hybrid`, `server` | Token of a user with at least admin rights on the site |
| Optional path to matomo.php / matomo.js | optional | Use a non-default path together with a server rewrite rule to bypass adblockers |
| Goal IDs (register / cart view / checkout confirm) | optional | Numeric Matomo goal IDs that should fire on the matching server-side event |
| Enable Matomo debug logger | optional | Writes detailed tracking requests / responses to `var/log/tinect_matomo*.log` |

## Tracking modes

| Mode | Browser JS | Server tracking | Recommended for |
| --- | --- | --- | --- |
| `client` | yes, direct to Matomo | no | Default install, no adblocker concerns |
| `proxy` | yes, via `/mtmtrpr` | no | Adblocker mitigation, Matomo on a different domain |
| `hybrid` | yes, via `/mtmtrpr` | orders + register + login | Engagement metrics **and** adblocker-resistant business events (recommended for most shops) |
| `server` | no | everything | No JavaScript, strict privacy setups |

In `hybrid` mode the JavaScript tracker keeps producing engagement
metrics (heartbeat / time-on-page, link tracking) while the server
additionally tracks orders (deduplicated by Matomo via `ec_id`),
customer registration and customer login - the events most likely to
be lost to adblockers. Page views, product views, site search, cart
and checkout funnel events stay client-only in this mode to avoid
double counting.

In `server` mode no Matomo JavaScript is rendered at all. Visitor IDs
are derived cookielessly via SHA-256 hashes of the customer ID
(logged-in) or session ID (guest), salted with `%kernel.secret%`, so
no raw customer identifier ever leaves the shop.

## Asynchronous worker (important)

`proxy`, `hybrid` and `server` modes dispatch tracking requests to the
Symfony Messenger queue (`async` transport). Without a running worker
nothing reaches Matomo:

```bash
bin/console messenger:consume async --time-limit=300
```

In production run the worker permanently via Supervisor or a systemd
unit. If tracking suddenly stops, check the queue first
(`bin/console messenger:failed:show`).

## Matomo-side requirements

* The configured **auth token** must belong to a user with at least
  *admin* rights on the site, otherwise Matomo ignores `cip` (client
  IP) and `cdt` (custom datetime).
* **Enable e-commerce** for the Matomo site (Settings → Websites →
  e-commerce) so that `ec_id`, `revenue`, `ec_items` etc. are stored.
* If you want to use the goal-ID settings, **create the goals in
  Matomo first** and copy the numeric IDs into the plugin configuration.

## Privacy and consent

* `client` / `proxy` / `hybrid`: cookie consent is handled by the
  bundled `MatomoAnalyticsPlugin.js`; the Matomo JavaScript only runs
  after the user opts in.
* `server`: no JavaScript, no Matomo cookies. The visitor ID is a
  pseudonymous hash. The real client IP (`cip`) is forwarded to Matomo
  - enable IP anonymization on the Matomo side if you do not want
  this.
* In every case, double-check with your data-protection officer
  whether server-side tracking without opt-in is acceptable for your
  jurisdiction and the data you collect.

## Verification

1. Open the storefront and view the page source. In `server` mode no
   `_paq` block must be present; in the other modes it must be there.
2. Place a test order and check Matomo's live visitor log - the order
   must appear with the correct `ec_id` and revenue.
3. With the debug logger enabled, watch `var/log/tinect_matomo*.log`
   for tracking requests and responses.
4. Repeat the order with an adblocker (e.g. uBlock Origin) enabled.
   In `hybrid` and `server` mode the order must still appear in
   Matomo.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| No tracking at all in `proxy` / `hybrid` / `server` | Messenger worker not running |
| HTTP 4xx in the Matomo log | Auth token missing or insufficient rights |
| Orders missing in `server` mode | E-commerce not enabled in Matomo |
| Goals do not fire | Goal ID setting empty or does not match Matomo |
| Storefront still loads Matomo JS in `server` mode | Cache not cleared after switching mode |

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
