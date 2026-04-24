# DeskCaptcha — Architectural Decisions

This document records all design decisions made during the initial architecture phase.

---

## Authentication
- **API Key is optional by default.** Public mode allows unauthenticated access. For production deployments, the instance owner may enable API key enforcement via `config/api.php`. The key is passed via the `X-API-Key` header.
- **`/v1/captcha/validate` is public.** No key required to validate a captcha answer, ensuring easy client-side integration.

## Image Pool
- **Global shared pool.** All users share a single pool of ~50 pre-generated captcha images. This is memory-efficient and sufficient for the concurrency level supported.
- **`used = true` is set when `/validate` is called.** The captcha is considered "used" only after the user submits their answer, not when the image is served.
- **Captchas expire after 10 minutes** if not validated. This is configurable via `config/api.php` (`CAPTCHA_TTL_SECONDS`). Expired captchas are deleted from disk and marked `deleted = 1` in the database.

## Scale & Proportions
- **All coordinates scale with the factor.** When `scale=2` or `scale=3`, every dimension (canvas, fonts, component positions, shape sizes) is multiplied by the scale factor. This ensures the captcha looks identical at all sizes.
- **`scale=3` + `chars=8` is allowed.** Maximum output: 1200x300px. The API documents this combination and warns about file size in the response meta.

## Database
- **Historical banks are not queryable via API.** Only the current month's SQLite bank is active. Old banks are archived files only.
- **Old banks are deleted automatically after 90 days.** A cleanup check runs on every request (throttled to once per hour via a flag file).

## CORS
- **Whitelist-based CORS, configurable in `config/cors.php`.** The default ships with `*` (open) for ease of evaluation. Production deployments should restrict to their own domains.
- **`LOCAL_MODE=true` in `config/api.php` disables CORS enforcement entirely**, allowing unrestricted use on local/internal networks.

## Rate Limiting
- **Both global and per-user limits are enforced simultaneously.** If either is exceeded, the request is blocked. The more restrictive limit provides the `Retry-After` value in the response.
- **When the global daily limit (10,000) is reached, HTTP 503 is returned for all requests**, regardless of API key status. The `Retry-After` header indicates when the next day's window opens.

## Monthly Rotation
- **New banks start empty (zeroed).** No user data is migrated from the previous month. User fingerprints are re-identified organically. This keeps each month's data clean and independent.
- **Rotation trigger:** checked on every request when `date('j') >= 20`. If the next month's bank does not yet exist, it is created immediately.

---

## Rate Limit Summary

| Scope  | Window   | Limit  | HTTP Code |
|--------|----------|--------|-----------|
| Global | 1 minute | 30     | 429       |
| Global | 1 hour   | 3,000  | 429       |
| Global | 1 day    | 10,000 | 503       |
| User   | 1 second | 1      | 429       |
| User   | 1 minute | 10     | 429       |
| User   | 1 hour   | 60     | 429       |
| User   | 1 day    | 120    | 429       |

User identity = `sha256(IP + User-Agent + Accept-Language + Accept-Encoding)`
