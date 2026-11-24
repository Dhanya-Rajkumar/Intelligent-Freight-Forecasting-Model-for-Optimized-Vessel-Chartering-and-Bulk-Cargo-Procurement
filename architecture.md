# FreightIQ Marine — Architecture

## Logical Architecture

```
                 ┌───────────────────────┐
                 │      Web Frontend      │
                 │ Dashboard / Shipper /  │
                 │ Fleet / Risk / etc.    │
                 └───────────┬────────────┘
                             │  fetch() JSON
                             ▼
                 ┌───────────────────────┐
                 │  Secure PHP REST API   │
                 │ (backend/api/*.php)   │
                 └───────────┬────────────┘
                             │
          ┌──────────────────┼──────────────────┐
          ▼                  ▼                  ▼
 ┌────────────────┐ ┌────────────────┐ ┌────────────────┐
 │ Forecast Engine │ │ Vessel Optimizer│ │  Risk Engine   │
 │ + Market Entry  │ │ + Port Checks  │ │  + Idle Engine │
 └────────────────┘ └────────────────┘ └────────────────┘
          │                  │                  │
          └──────────────────┼──────────────────┘
                             ▼
                  ┌─────────────────────┐
                  │   MySQL / MariaDB   │
                  └─────────────────────┘
```

## Why flat query-string API endpoints, not `/api/vessels/{id}` path routing

Stock XAMPP has no application router configured out of the box, and adding
one means either enabling and hand-rolling `mod_rewrite` config (which varies
across XAMPP versions and OSes) or a front controller. To keep "clone and
run" reliable, endpoints are flat PHP files with query-string parameters
(`vessels.php?id=5`, `ports.php?id=3`) rather than pretty path segments. Each
endpoint still maps 1:1 to the REST resource named in the original spec, and
the same file handles GET (read) vs POST (write) per HTTP method, so the
resource model is unchanged — only the URL shape is simplified. Introducing
`.htaccess` rewrite rules to get `/api/vessels/{id}` is a natural follow-up
once the app is deployed behind a controlled Apache config.

## Forecasting Model Architecture

`backend/services/ForecastEngine.php` implements a transparent, explainable
statistical baseline (`model_key: weighted_regression_v1`):
recency-weighted linear regression + seasonal harmonic component +
volatility-derived confidence bands + market-indicator (demand/supply)
adjustment.

The `forecast_models` table already lists ARIMA, SARIMA, Prophet, XGBoost,
and LSTM as **planned** (`is_active = 0`) model keys. To integrate a real
model:

1. Stand up a small Python service (FastAPI/Flask) that trains/serves the
   chosen model against `freight_rates` + `market_indicators`.
2. Add a thin PHP client in `backend/services/` that calls that service over
   HTTP and returns the same array shape as `ForecastEngine::predictHorizon()`
   (`predicted_rate`, `lower_bound`, `upper_bound`, `confidence_pct`,
   `market_direction`, `volatility_pct`, …).
3. Flip `is_active` in `forecast_models` and swap the class used by
   `backend/api/forecast.php`.

No frontend or API-contract changes are required.

## GPS / AIS Architecture

`backend/api/gps.php` reads from `vessel_positions`, a table shaped like a
real AIS feed (lat/lon, speed, heading, timestamp) but currently populated
with simulated single-snapshot data (`data_source` column says so explicitly,
and the frontend map surfaces that label too — see `pages/fleet.php`).

To connect a real AIS provider (e.g. MarineTraffic, Spire, ExactEarth):
- Write a scheduled PHP/cron job or webhook receiver that inserts rows into
  `vessel_positions` on the same schema.
- Update `data_source` per row to the real provider name.
- No frontend change is needed — the Leaflet map already redraws from
  whatever `gps.php` returns.

## Security Architecture

See `docs/security.md`.

## Data Quality & Provider Interfaces

Every seeded table that stands in for a live feed (`freight_rates`,
`market_indicators`, `vessel_positions`, `port_congestion`) carries a
`data_source` / equivalent column defaulting to a string that includes
"Simulated (demo)". The frontend surfaces this via `data-badge` elements and
inline "Data source" labels — nowhere does the UI claim simulated data is
live. This is intentional so the platform can be demonstrated to
stakeholders honestly, and so real providers can be wired in later by (a)
changing the value written to `data_source` and (b) pointing the relevant
ingestion job at a real API instead of the seed script.

## Frontend Presentation Layer (i18n / currency / theme / ship twins)

These are pure frontend concerns — no backend/API/database changes were
needed to add them, which keeps them easy to extend or remove independently:

- `frontend/assets/js/i18n.js` — translation dictionary + `data-i18n` DOM scanner. See `docs/i18n.md`.
- `frontend/assets/js/currency.js` — static-rate USD→display-currency conversion (`fiqMoney()`), used everywhere a dollar amount is rendered. The database and all backend calculations remain USD-only; conversion is display-only.
- `frontend/assets/js/theme.js` — swaps a `data-theme` attribute on `<html>`; all theming is CSS custom properties in `style.css`, so no component needed separate light/dark variants.
- `frontend/assets/js/ship_svg.js` — generates each vessel's "digital twin" SVG from its actual DB row (class, LOA, beam, draft, status) rather than using a static image per class, so the illustration is genuinely data-driven. Also exports `fiqShipTopIcon()`, a compact top-down hull silhouette used as a heading-rotated Leaflet marker on the Fleet & GPS Tracking map (see `fleet.js`'s `vesselIcon()`/`drawWake()`).

All four are plain `<script>` includes with no build step, consistent with the rest of the project.

### Theme-aware canvas effects
`ocean.js`'s animated background (waves, particles, light streaks) reads
color from CSS custom properties (`--teal-rgb`, `--cyan-rgb`, etc. — see
`style.css`) at draw time rather than hardcoding a palette, and
`theme.js` dispatches a `fiq-theme-changed` window event whenever the theme
switches so the canvas (which CSS alone cannot repaint) re-reads those
colors immediately. `fleet.js` listens for the same event to hot-swap its
Leaflet basemap between a dark nautical chart and standard OpenStreetMap.

### Native `<select>` contrast
Browsers render an open `<select>`'s option list with their own
(near-always white) background, ignoring most of a page's custom styling.
`style.css` sets an explicit, theme-independent `color`/`background` on
every `<option>`/`<optgroup>` globally so this can't regress per-theme or
per-dropdown again.

## Folder Structure

```
FreightIQ-Marine/
├── frontend/           # PHP-rendered pages + static assets
│   ├── login.php, register.php, index.php
│   ├── pages/           # forecast, fleet, ports, vessels, shipper, contracts, risk, alerts, audit
│   ├── includes/        # nav.php, footer.php
│   └── assets/css|js
├── backend/
│   ├── api/             # one flat PHP file per REST resource
│   ├── config/          # db.php (env-based), env.example.php
│   ├── middleware/       # security.php (session, CSRF, RBAC, audit, rate limit)
│   └── services/        # ForecastEngine, MarketEntryEngine, VesselOptimizer, RiskEngine, IdleEngine
├── database/            # schema.sql, seed.sql, seed_freight_rates.sql
└── docs/                # this file, security.md, api.md, setup.md
```
