# FreightIQ Marine — API Reference

Base URL (default XAMPP install): `http://localhost/FreightIQ-Marine/backend/api/`

All endpoints return JSON. All protected endpoints require an active session
(login via `auth_login.php` first) and enforce **server-side** RBAC via
`require_permission()` — see `docs/security.md`. State-changing (POST)
requests require a `csrf_token` field matching the session's token (obtained
from `auth_login.php` / `auth_me.php` responses).

> Note on URL shape: endpoints are flat query-string files rather than
> `/api/vessels/{id}` path segments, to avoid depending on `mod_rewrite`
> configuration on a stock XAMPP install. See `docs/architecture.md` for the
> rationale and how to add pretty routing later.

## Auth
| Endpoint | Method | Description |
|---|---|---|
| `auth_login.php` | POST | `{email, password, csrf_token}` → session + user + new csrf_token |
| `auth_logout.php` | POST | Destroys session |
| `auth_me.php` | GET | Current session user + csrf_token |
| `auth_register.php` | POST | Self-service Shipper signup only |

## Dashboard
| Endpoint | Method | Permission | Description |
|---|---|---|---|
| `dashboard.php` | GET | `dashboard.view` | KPIs, vessel availability, port congestion, recent alerts |

## Forecasting
| Endpoint | Method | Permission | Description |
|---|---|---|---|
| `forecast.php?route_id=&vessel_class_id=` | GET | `forecast.view` | 7/14/30-day forecast bundle + market-entry recommendation |
| `counterfactual.php` | POST | `forecast.view` | `{route_id, vessel_class_id, horizon, demand_change_pct, congestion_change_pct, bunker_change_pct}` |

## Vessels & Ports
| Endpoint | Method | Permission | Description |
|---|---|---|---|
| `vessels.php` | GET | `vessels.view` | List (filter: `class_id`, `status`, `search`, pagination) or `?id=` for detail |
| `vessels_optimize.php?cargo_volume=&port_id=&route_id=` | GET | `vessels.optimize` | Ranked, explained vessel-class recommendations |
| `ports.php` | GET | `ports.view` | List all ports (East & West coast, each tagged `coast` and `state`) with live congestion, or `?id=` for detail + constraints |
| `gps.php` | GET | `gps.view` | Latest vessel positions, ports, origins for the Leaflet map |

## Cargo (Shipper Workspace)
| Endpoint | Method | Permission | Description |
|---|---|---|---|
| `cargo.php` | GET | `cargo.view` | List cargo requests (own, unless `cargo.manage_all`) |
| `cargo.php` | POST | `cargo.create` | Create a cargo request; auto-runs vessel optimization + forecast |

## Contracts
| Endpoint | Method | Permission | Description |
|---|---|---|---|
| `contracts.php` | GET | `contracts.view` | List contracts |
| `contracts.php?action=compare&route_id=&vessel_class_id=&volume_per_voyage=` | GET | `contracts.view` | Spot vs Short-Term vs Medium-Term comparison |
| `contracts.php` | POST | `contracts.create` | Create a contract + its voyages |

## Risk & Alerts
| Endpoint | Method | Permission | Description |
|---|---|---|---|
| `risk.php` | GET | `risk.view` | Freight/Port/Vessel/Route risk snapshot + idle scenarios |
| `alerts.php` | GET | `alerts.view` | List alerts |
| `alerts.php` | POST | `alerts.resolve` | `{id}` → mark resolved |
| `notifications.php` | GET/POST | (any logged-in user) | List / mark-read notifications |

## Reference Data & Audit
| Endpoint | Method | Permission | Description |
|---|---|---|---|
| `reference_data.php` | GET | (any logged-in user) | Routes, vessel classes, commodities, origins, ports — for populating dropdowns |
| `audit.php` | GET | `audit.view` | Paginated audit log |

## Admin
| Endpoint | Method | Permission | Description |
|---|---|---|---|
| `users.php` | GET | `admin.manage_users` | List all users with role, status, lockout state |
| `users.php` | POST | `admin.manage_users` | `{action, id, role_id?}` — `action` is one of `change_role`, `activate`, `deactivate`, `unlock`. An admin cannot deactivate or change their own role. |

## Error Shape
All errors return `{"error": "human-readable message"}` with an appropriate
HTTP status code (400 bad request, 401 unauthenticated, 403 forbidden, 404
not found, 423 locked, 429 rate-limited, 500 server error). Stack traces are
never exposed to the client.
