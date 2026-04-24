# API Reference — DeskCaptcha v1.0.0

> 🌐 [desknect.com](https://desknect.com) &nbsp;|&nbsp; 📖 [Official Docs](https://dcaptcha.desknect.com/api-documentacao) &nbsp;|&nbsp; ❤️ [Donate](https://desknect.com/donate)

Base URL: `http://yourserver/v1`

---

## Authentication

By default, the API is public. To enable API key enforcement, set `require_api_key = true` in `config/api.php` and pass the key as a header:

```http
X-API-Key: your-secret-key
```

---

## Endpoints

### GET /v1/captcha/generate

Generates a new CAPTCHA image and returns a token for later validation.

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `scale` | int | `1` | Image scale factor. Allowed: `1`, `2`, `3` |
| `chars` | int | `4` | Number of characters. Allowed: `4`, `6`, `8` (always letter+number pattern) |

**Dimensions by scale:**

| Scale | Width | Height |
|-------|-------|--------|
| 1 | 400px | 100px |
| 2 | 800px | 200px |
| 3 | 1200px | 300px |

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "token": "a1b2c3d4e5f6a7b8c9d0e1f2",
    "image_url": "http://yourserver/v1/captcha/xYz12345.png",
    "expires_in": 600,
    "expires_at": "2026-04-23 10:10:00",
    "scale": 1,
    "chars": 4,
    "dimensions": { "width": 400, "height": 100 }
  },
  "meta": {
    "version": "1.0.0",
    "api": "DeskCaptcha",
    "remaining": { "minute": 27, "hour": 2984, "day": 9990 }
  }
}
```

**Error Responses:**

| Code | Reason |
|------|--------|
| 422 | Invalid `scale` or `chars` value |
| 429 | Rate limit exceeded |
| 503 | Global daily limit reached |

---

### GET /v1/captcha/{filename}

Serves the CAPTCHA PNG image.

**Path Parameters:**

| Parameter | Description |
|-----------|-------------|
| `filename` | The filename returned by `/generate` |

**Success Response (200):** PNG image binary

**Error Responses:**

| Code | Reason |
|------|--------|
| 404 | Image not found or already deleted |

---

### POST /v1/captcha/validate

Validates the user's answer against the stored captcha.

**Request Body (JSON):**
```json
{
  "token": "a1b2c3d4e5f6a7b8c9d0e1f2",
  "answer": "A3B7"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": { "valid": true, "message": "Captcha validated successfully." }
}
```

**Wrong Answer (422):**
```json
{
  "success": true,
  "data": { "valid": false, "message": "Incorrect answer." }
}
```

**Error Responses:**

| Code | Reason |
|------|--------|
| 404 | Token not found |
| 409 | Captcha already used |
| 410 | Captcha expired or deleted |
| 422 | Missing token or answer |

---

### GET /v1/status

Returns current API status, pool information, and rate limit counters.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "status": "operational",
    "pool": { "active": 48, "target": 50 },
    "rate_limits": {
      "global": {
        "remaining": { "minute": 27, "hour": 2950, "day": 9800 },
        "limits":    { "per_minute": 30, "per_hour": 3000, "per_day": 10000 }
      }
    },
    "database": "deskcaptcha_2026_04.sqlite"
  }
}
```

---

### GET /v1/health

Lightweight health check for load balancers and monitoring systems.

**Response (200 = healthy, 503 = degraded):**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "database": "ok",
    "time": "2026-04-23 10:00:00",
    "version": "1.0.0"
  }
}
```

---

## Response Headers

Every API response includes:

| Header | Description |
|--------|-------------|
| `X-RateLimit-Remaining` | Requests remaining in current window |
| `X-RateLimit-Reset` | Unix timestamp when window resets |
| `Retry-After` | Seconds to wait (only on 429/503) |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |

---

## Error Format

All errors follow the same structure:

```json
{
  "success": false,
  "error": {
    "code": 429,
    "message": "Too many requests. Please wait before retrying."
  },
  "meta": {
    "rate_limit": {
      "window": "minute",
      "retry_after": 45,
      "limit": 30
    }
  }
}
```
