# FreightIQ Marine
### Intelligent Freight Forecasting, Vessel Chartering & Bulk Cargo Procurement Platform
*Ministry of Steel / SAIL — East Coast India bulk cargo procurement*

A maritime logistics intelligence and decision-support platform: forecasts
freight rates, recommends optimal vessels against real port constraints,
tracks fleet position on a live-style map, runs a risk center, manages a
shipper workspace, and compares Spot vs Short-Term vs Medium-Term multi-voyage
contracts — moving procurement from **Reactive → Predictive → Optimized →
Contract-driven**.

**➡ Setup: see [`docs/setup.md`](docs/setup.md) for step-by-step XAMPP install.**
**➡ Architecture: see [`docs/architecture.md`](docs/architecture.md).**
**➡ Security: see [`docs/security.md`](docs/security.md).**
**➡ API reference: see [`docs/api.md`](docs/api.md).**

---

## Verified Working

Unlike a typical "here's some code" handoff, this build was actually installed
and exercised end-to-end before packaging: a real MariaDB instance was stood
up, all three SQL files were imported clean (including a stress test with a
deliberately wrong client character set), every backend service class was
executed against the live seeded data, and every API endpoint was hit over
real HTTP with a real authenticated session — including confirming that a
Shipper-role account is correctly blocked (HTTP 403) from admin-only
endpoints. Two real bugs were found and fixed this way: an unescaped
apostrophe in seed data that silently broke the entire import after that
point, and several `CURDATE()`-relative queries that returned "insufficient
data" once the wall clock moved past the seeded date range (now fixed to use
row-based recency instead, which is robust no matter when you actually run
this).

## Quick Start

```
1. Copy the FreightIQ-Marine/ folder into your XAMPP htdocs/ directory.
2. Start Apache + MySQL from the XAMPP Control Panel.
3. In phpMyAdmin, import (in order):
      database/schema.sql
      database/seed.sql
      database/seed_freight_rates.sql
4. Visit http://localhost/FreightIQ-Marine/frontend/login.php
5. Log in with admin@sail.co.in / Admin@123
```

## What's Inside

| Module | Highlights |
|---|---|
| **Command Dashboard** | KPIs with count-up animation, vessel availability & port congestion charts, live alert feed |
| **Freight Forecast** | 7 / 14 / 30-day predictions with confidence bands, explainable trend/seasonality/volatility breakdown, Market-Entry Intelligence Engine (Buy Now / Monitor / Preferred Window / Avoid), and a Counterfactual What-If simulator |
| **Fleet & GPS Tracking** | Leaflet map with animated pulsing port/vessel markers, radar-sweep header, optional browser-GPS overlay, searchable fleet roster |
| **Port Intelligence Center** | 7 East Coast India ports with draft/LOA/beam limits, congestion, weather, handling capacity — 🟢🟡🔴 status |
| **Vessel Optimizer** | Ranked vessel-class recommendations with per-candidate explanations (why compatible, why rejected, utilisation %, idle-time estimate) |
| **Shipper Workspace** | Cargo requirement intake → instant vessel + freight recommendation → request tracking |
| **Contract Intelligence** | Spot vs Short-Term vs Medium-Term comparison with estimated (not guaranteed) savings, multi-voyage contract creation, ledger |
| **Risk Center** | Freight / Port / Vessel / Route risk scoring with early-warning detail, Idle Vessel Management with cost & alternative-revenue estimates |
| **Alerts & Notifications** | Severity-tagged alerts, resolvable by authorized roles, plus real slide-in toast notifications on page load in addition to the notification center |
| **Audit Log** | Every sensitive action traceable by user, action, entity, and IP |
| **User Management** *(Admin only)* | Change a user's role, activate/deactivate accounts, unlock accounts locked out by failed-login protection — the first real use of the `admin.manage_users` permission |
| **Reports Center** | Generate professional print/PDF-ready reports (via browser print) for Freight Forecast, Vessel Recommendation, Port Compatibility, Risk Analysis, Contract Comparison, and Shipper Cargo Requirement — each with a one-click CSV export of the underlying data |
| **CSV Export** | Fleet roster and Contract ledger tables can be exported to CSV directly from their list views |

## Origins & Ports Modeled — All India Coasts

**Origins:** Australia, United States, Mozambique, Russia, Indonesia, Brazil,
South Africa, Saudi Arabia, Chile.

**East Coast ports (8):** Paradip, Visakhapatnam, Gangavaram, Gopalpur, Dhamra,
Sagar-Sandheads, Haldia, Ennore (Chennai).
**West Coast ports (6):** Jawaharlal Nehru Port (JNPT), Mumbai, Deendayal
(Kandla), Mundra, New Mangalore, Cochin.

Every port carries draft/LOA/beam limits, handling capacity, live-style
congestion, and weather status, and can be filtered by coast on the Port
Intelligence page. 20 origin-to-India trade routes are seeded across both
coasts with 24,000 historical freight-rate data points for the forecasting
engine to learn from.

## Multilingual, Multi-currency, Multi-theme

- **17 languages**: English plus 12 Indian official languages (Hindi, Bengali,
  Telugu, Marathi, Tamil, Gujarati, Kannada, Malayalam, Punjabi, Odia,
  Assamese, Urdu) and 4 international (Spanish, French, Arabic, Chinese).
  Covers the app's shared UI chrome (navigation, buttons, common labels) via
  a `data-i18n` framework in `frontend/assets/js/i18n.js`; Urdu and Arabic
  correctly switch the page to RTL. Server-generated explanatory text
  (forecast reasoning, risk detail sentences) stays in English in this build
  — see `docs/i18n.md` for how to extend translated coverage to that content.
- **11 currencies**: USD (base), INR, EUR, GBP, JPY, CNY, AED, SGD, AUD, ZAR,
  BRL. All monetary values are computed and stored in USD and converted for
  **display only** using static indicative reference rates (clearly not a
  live FX feed) — see `frontend/assets/js/currency.js`.
- **4 themes**: Ocean (default dark navy/cyan), Light, Midnight (high
  contrast), Sunrise (warm amber). Switch instantly from the sidebar; applied
  before first paint to avoid a flash of the wrong theme.

All three switchers live in the sidebar and persist via `localStorage`.

## Ship "Digital Twin" Visuals

Every vessel has a data-driven SVG side-profile illustration — gradient-
shaded hull, portholes, bridge, cargo hatches (count scales with vessel
class), blinking port/starboard nav lights, funnel smoke when underway, a
bow-wave ripple, a gentle at-sea rocking motion, and labelled LOA/beam/draft
dimensions pulled straight from that vessel's actual database row, framed in
a scanning "LIVE SIMULATION" HUD panel. Click any vessel in **Fleet & GPS
Tracking** to open its Digital Twin modal. These are original illustrations
generated from data (not stock photos), so the whole app stays
self-contained, copyright-clean, and works fully offline on XAMPP.

## Realistic GPS Fleet Map

- Vessel markers are a real top-down hull silhouette **rotated to each
  vessel's actual heading** (not a static, direction-less emoji pin), with a
  subtle idle bob so the fleet reads as "alive".
- Underway vessels trail a short directional wake line (illustrative, derived
  from current heading — not a real historical track).
- The basemap automatically matches the active theme: a dark nautical-style
  chart (CARTO dark tiles) for Ocean/Midnight/Sunrise, standard OpenStreetMap
  for Light — and swaps live if you change theme mid-session.
- A radar-sweep HUD overlay sits in the corner of the map for the command-
  center feel.

## Theme-Aware Background Effects

The animated canvas waves, particle field, light streaks, aurora gradient
sweep, and every glow/shimmer effect read the active theme's actual colors
(via CSS custom properties) and **re-render live** when you switch theme —
nothing is hardcoded to the default Ocean palette, so Light/Midnight/Sunrise
each get their own correctly-tinted animated backdrop instead of a mismatched
teal overlay.

## Fixed: unreadable dropdown options

Native `<select>` dropdown lists are rendered by the browser/OS with their
own background (almost always solid white) regardless of a page's custom
styling. The app's dark-theme text color was being inherited into those
native option lists too, making every dropdown — language switcher, status
filters, route/vessel/port selects, currency and theme pickers, all of
them — nearly unreadable (light-gray text on white). Fixed with an explicit,
always-legible `color`/`background` pair on every `<option>` and `<optgroup>`
app-wide, independent of the active theme.

## Data Honesty

Every simulated dataset (freight rates, market indicators, vessel GPS
positions, port congestion) is explicitly labelled as such in both the
database (`data_source` columns) and the UI (data badges, footnotes). Nothing
in this build claims to be live AIS, live freight-market, or live weather
data — see `docs/architecture.md` for how to wire in real providers later.

## Tech Stack

PHP 8 (PDO) · MySQL/MariaDB · vanilla JS · Chart.js · Leaflet.js · HTML5/CSS3
with a custom ocean/radar animation layer, SVG ship digital twins, and a
Reduced Motion accessibility toggle. No build step, no external image hosting
dependency — runs directly on stock XAMPP, fully offline-capable except for
the Leaflet map tiles (OpenStreetMap) and Google Fonts.
