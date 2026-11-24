# FreightIQ Marine — Setup Guide (XAMPP)

## 1. Prerequisites
- XAMPP with Apache + MySQL (PHP 8.0+)

## 2. Install
1. Extract this project so the folder `FreightIQ-Marine` sits **directly inside `htdocs`**:
   - Windows: `C:\xampp\htdocs\FreightIQ-Marine`
   - macOS: `/Applications/XAMPP/htdocs/FreightIQ-Marine`
   - Linux: `/opt/lampp/htdocs/FreightIQ-Marine`
   Double-check there isn't an extra nested folder (a common zip-extraction mistake) —
   `htdocs\FreightIQ-Marine\frontend\login.php` must be a real, reachable path.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, click **Import**, and import in this order:
   1. `database/schema.sql`
   2. `database/seed.sql`
   3. `database/seed_freight_rates.sql` (this one is larger — historical freight time series)
4. Check `backend/config/db.php` — defaults match a stock XAMPP install
   (`root` / empty password). Edit only if yours differs, or configure the
   `FIQ_DB_*` environment variables described in `backend/config/env.example.php`.
5. Visit: `http://localhost/FreightIQ-Marine/frontend/login.php`

## 3. Demo Logins
All demo accounts use password **Admin@123**.

| Role | Email |
|---|---|
| Admin | admin@sail.co.in |
| Chartering Manager | chartering@sail.co.in |
| Analyst | analyst@sail.co.in |
| Viewer | viewer@sail.co.in |
| Shipper | shipper@globalbulk.com |

Change these passwords before any real deployment.

## 4. Recommended First Look
1. **Command Dashboard** — confirms DB connectivity and shows live-style KPIs.
2. **Freight Forecast** — pick a route + vessel class, run a forecast, then try
   the What-If simulator underneath it.
3. **Vessel Optimizer** — enter a cargo quantity and destination port to see
   ranked, explained vessel recommendations.
4. **Fleet & GPS Tracking** — interactive Leaflet map with simulated vessel
   positions, ports, and your own browser GPS location (on request).
5. **Shipper Workspace** — log in as `shipper@globalbulk.com` to submit a
   cargo requirement end-to-end.

## 5. Troubleshooting
- **404 on login.php**: the project folder isn't directly inside `htdocs`, or
  Apache's `DocumentRoot` doesn't point at `htdocs`. See `docs/setup.md`
  troubleshooting notes below.
- **500 / "Database connection error"**: MySQL isn't running, or the database
  name/credentials in `backend/config/db.php` don't match your install.
- **Map tiles don't load**: confirm your network can reach
  `tile.openstreetmap.org` (used only for map tiles, no data leaves your
  machine otherwise).
- **Blank charts**: make sure all three SQL files were imported, especially
  `seed_freight_rates.sql` — the forecasting engine needs at least 10 historical
  data points per route/vessel-class pair.

## 6. Directory Reachability Check
If a page 404s, visit `http://localhost/FreightIQ-Marine/frontend/` (no
filename) to confirm the folder itself is reachable before debugging further.
