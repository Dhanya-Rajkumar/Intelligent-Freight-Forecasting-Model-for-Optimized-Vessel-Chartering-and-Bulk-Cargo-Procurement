# FreightIQ Marine — Security

## Implemented Controls

| Control | Implementation |
|---|---|
| Password hashing | `password_hash()` (bcrypt, cost 12) / `password_verify()` — never plaintext |
| Brute-force protection | Account lockout after 5 failed attempts (15 min), tracked per-user in `users.failed_attempts` / `locked_until` |
| Generic login errors | Login always returns "Invalid email or password" regardless of whether the email exists |
| Rate limiting | Session-scoped rate limiter (`rate_limit()` in `security.php`) applied to login and registration endpoints |
| SQL injection | 100% PDO prepared statements; `PDO::ATTR_EMULATE_PREPARES` disabled; zero string-concatenated SQL |
| XSS | All dynamic output escaped via `htmlspecialchars()` (`clean()` helper); API responses are JSON, not HTML |
| CSRF | Per-session token required on every state-changing (POST) request, checked with `hash_equals()` |
| Session hardening | `HttpOnly`, `SameSite=Strict` cookies; `secure` flag auto-enabled under HTTPS; periodic `session_regenerate_id()` |
| RBAC | Server-side permission checks (`require_permission()`) on **every** protected API endpoint — the frontend nav hides links a role can't use, but that's a UX convenience, not the security boundary |
| Audit logging | Every login, logout, forecast run, optimization run, cargo request, contract, and alert-resolution is written to `audit_logs` with user id, action, entity, IP, and timestamp |
| Security headers | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, and a `Content-Security-Policy` restricting script/style/img origins |
| Least-privilege config | DB credentials read from environment variables first (`FIQ_DB_*`), falling back to XAMPP defaults only for local dev — see `backend/config/env.example.php` |
| No secrets in frontend | No API keys, DB credentials, or tokens are ever emitted into HTML/JS; only a per-session CSRF token is exposed, which is not a secret credential |
| Directory protection | `backend/config/`, `backend/middleware/`, `backend/services/`, `database/`, and `docs/` are denied direct HTTP access via `.htaccess` + a `403` `index.php` stub |
| Error handling | Uncaught DB errors are logged server-side (`error_log`) and return a generic JSON error to the client — no stack traces or credentials are ever echoed |
| Transactions | Multi-statement writes (contract + voyages, cargo request creation) run inside `PDO` transactions with rollback on failure |
| Soft delete | `users.deleted_at` and `cargo_requests.deleted_at` support soft-delete rather than destructive `DELETE` |

## RBAC Model

Five roles (`roles` table) map to a `role_permissions` join against a
granular `permissions` table (e.g. `forecast.view`, `contracts.create`,
`admin.manage_users`). `backend/middleware/security.php`'s
`build_session_user()` loads a user's permission list once at login and
stores it in `$_SESSION['user']['permissions']`; every API endpoint calls
`require_permission('some.permission')`, which:

1. Confirms the session exists (`require_login`).
2. Confirms the permission is present in that session's permission list.
3. Returns HTTP 401/403 with a JSON error otherwise.

**The frontend never makes an authorization decision on its own** — sidebar
links and action buttons are hidden based on `has_permission()` purely so the
UI doesn't show controls a user can't use, but every corresponding API call
is re-checked server-side regardless of what the browser sent.

## What a Production Deployment Should Add

This is a local XAMPP demo build. Before deploying for real:

- **HTTPS everywhere** — the `secure` cookie flag activates automatically
  under HTTPS (`isset($_SERVER['HTTPS'])`), but you must terminate TLS in
  front of the app.
- **Real environment-variable injection** — set `FIQ_DB_*` via your
  webserver/OS rather than editing `db.php` defaults.
- **Persistent rate limiting** — the current limiter is session-scoped;
  replace with a shared store (Redis/DB) for multi-instance deployments and
  to survive session resets.
- **CAPTCHA or similar** on registration if it's exposed publicly.
- **Least-privilege DB user** — create a MySQL user scoped to
  `freightiq_marine` only, rather than using `root`.
- **Dependency pinning / CDN integrity** — pin Chart.js/Leaflet versions with
  Subresource Integrity (SRI) hashes if serving to the public internet.
- **Centralized logging/alerting** on the `audit_logs` table (e.g. alert on
  repeated `LOGIN_FAILED` from one IP).
