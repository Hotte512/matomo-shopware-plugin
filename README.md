# Matomo Shopware 6 Plugin

This plugin for Shopware 6 connects your shop to the Open Source and self-hostable Matomo platform.
The plugin is based as fork from [tinect/matomo-shopware-plugin](https://github.com/tinect/matomo-shopware-plugin),
wich is based as fork from [Jinya-CMS/matomo-shopware-plugin](https://github.com/Jinya-CMS/matomo-shopware-plugin).

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
| Hybrid heartbeat URL | optional | Only used in `hybrid` mode. Empty (default) = browser pings go through the built-in `/mtmtrpr` proxy. Set to a same-origin path or full URL to send pings directly to Matomo and skip the proxy / messenger queue. See [Tracking modes](#tracking-modes). |
| Goal IDs (register / cart view / checkout confirm) | optional | Numeric Matomo goal IDs that should fire on the matching server-side event |
| Enable Matomo debug logger | optional | Writes detailed tracking requests / responses to `var/log/tinect_matomo*.log` |

## Tracking modes

| Mode | Browser JS | Server tracking | Recommended for |
| --- | --- | --- | --- |
| `client` | full Matomo tracker, direct to Matomo | no | Default install, no adblocker concerns |
| `proxy` | full Matomo tracker, via `/mtmtrpr` | no | Adblocker mitigation, Matomo on a different domain |
| `hybrid` | heartbeat-only ping, via `/mtmtrpr` | everything | Server-grade reliability **plus** time-on-page / engagement (recommended) |
| `server` | none | everything | No JavaScript, strict privacy setups |

In `hybrid` mode every event (page views, product views, site search,
cart, checkout funnel, orders, customer registration and login) is
tracked server-side, exactly like in `server` mode. The browser only
emits Matomo `ping=1` heartbeats - no `trackPageView`, no Matomo JS
library is loaded. The pings carry the same cookieless visitor ID hash
as the server-side events, so Matomo attributes the engagement time to
the existing page view. This way adblockers cannot strip page views,
products, cart actions or orders, and time-on-page / engagement still
gets reported.

By default the heartbeats go through the built-in `/mtmtrpr` proxy
route - same-origin, survives standard adblock lists, but every ping
is dispatched as a Symfony Messenger message which adds queue load
(roughly four messages per minute per active visitor). If your Matomo
is reachable from the browser directly, you can point heartbeats
straight at it via the **Hybrid heartbeat URL** setting and skip the
proxy entirely:

| Setup | Recommended Hybrid heartbeat URL |
| --- | --- |
| Matomo on the same domain in a subdirectory (e.g. `https://shop.example.com/your-matomo-dir/`) | `/your-matomo-dir/matomo.php` |
| Same as above with an adblocker-bypass rewrite rule (e.g. `track.php` → `matomo.php`) | `/your-matomo-dir/track.php` |
| Matomo on a subdomain with CORS allowed for the shop origin | `https://matomo.example.com/matomo.php` |
| Anything else / unsure | leave empty (= use proxy) |

Server-side tracking is unaffected by this setting: the shop talks to
Matomo server-to-server via `matomoserver` + `phpTrackingPath` regardless.

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

## Matomo-side setup

The plugin configuration references several values from your Matomo
instance. Here is where to find each one and what the Matomo side
needs to fulfill for tracking to work end-to-end.

### Site-ID

Each tracked website has a numeric ID assigned by Matomo. Find it
under **Administration → Websites → Manage** - the **ID** column
shows the value. Alternatively, the `idSite=X` query parameter in any
reporting URL is the site ID of the currently selected site. Put this
number into the plugin's **Matomo Site-ID** field.

### Auth-Token

The plugin sends this token as `token_auth` with every server-to-server
tracking request. Matomo only accepts the real visitor IP (`cip`) and
the original event time (`cdt`) when the request is authenticated -
without an authenticated request every visitor would appear under your
shop server's IP and the live-view timestamp would shift to whenever
the queue worker happened to process the event.

Required for the `proxy`, `hybrid` and `server` tracking modes; not
used in `client` mode.

How to create one:

1. Log into Matomo as a user with **at least admin rights on the
   site** you track. A dedicated user (e.g. `shopware-tracking`) is
   recommended so you can rotate the token without affecting other
   logins.
2. Click your **username (top right) → Personal → Security**
   (URL path: `/index.php?module=UsersManager&action=userSecurity`).
3. In the **Auth tokens** section choose **Create new token**, give
   it a description, confirm with your Matomo password.
4. Matomo shows the 32-character token **once**. Copy it immediately
   into the plugin's **Matomo Auth-Token** field.

Rotation: delete the old token in the same view, create a new one,
update the plugin field afterwards - tracking pauses until the new
value is saved.

If the token belongs to a user with insufficient rights, Matomo
silently drops `cip` and `cdt`. Symptom: every visitor shows the shop
server's IP and the live-view timestamps lag behind the real action.
Switch to an admin-level token to fix it. With the plugin's debug
logger enabled, HTTP 4xx errors from Matomo are written to
`var/log/tinect_matomo*.log`.

### Goal IDs

Goal IDs map server-side events (customer registration, cart view,
checkout confirm) to Matomo goals so they appear in the conversion
reports. They are optional - leaving a goal-ID field empty just
skips the goal conversion for that event; the underlying server-side
event (page view / custom event) is still tracked.

To find or create them:

1. In Matomo switch to the site whose ID you configured in the plugin.
2. **Administration → Websites → Goals**
   (URL path: `/index.php?module=Goals&action=manage&idSite=X`, with
   `X` = your Site-ID).
3. The list shows each goal with its **ID** in the first column. Put
   that number into the corresponding plugin field
   (`goalIdRegister` / `goalIdCartView` / `goalIdCheckoutConfirm`).

If the goals do not exist yet, create them in the same view via
**Add a new goal**. Choose **manually** as the trigger - the plugin
fires the goal explicitly through the tracking API, no Matomo-side URL
matching is needed. Pick a meaningful name (e.g. *Customer
registration*, *Cart viewed*, *Checkout reached*) and, if relevant, a
default revenue. Save, then copy the new goal ID back into the plugin
configuration.

### E-commerce

For order tracking (`ec_id`, `revenue`, `ec_items`) to be stored,
**enable e-commerce** on the site:
**Administration → Websites → Manage → (your site) → e-commerce**.
Without this Matomo accepts the request but discards the e-commerce
fields, leaving you with a page view but no order in the conversion
reports.

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
