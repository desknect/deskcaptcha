# DeskCaptcha

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](CHANGELOG.md)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)
[![Company](https://img.shields.io/badge/by-Desknect.com-orange.svg)](https://desknect.com)

**DeskCaptcha** is a lightweight, self-hosted CAPTCHA generator API built with PHP and SQLite. It generates image-based CAPTCHAs with configurable scale, character count, and visual complexity — designed for easy integration into web applications, SaaS platforms, and desktop systems.

---

## Features

- 🖼️ Generates PNG CAPTCHA images with random fonts, colors, shapes and lines
- 📐 Configurable scale (1×, 2×, 3×) and character count (4, 6, or 8)
- 🔒 Per-user and global rate limiting
- 🗄️ Monthly SQLite database rotation
- 🌐 CORS-ready for web embedding
- 🖥️ Works on shared hosting, VPS, LAN, and localhost
- 📦 Zero dependencies — pure PHP + SQLite + GD

---

## Quick Start

```bash
# Clone the repository
git clone https://github.com/desknect/deskcaptcha.git
cd deskcaptcha

# Point your web server document root to: deskcaptcha/public/
# Make sure storage/ and database/ are writable:
chmod -R 775 storage/ database/
```

Point Apache/Nginx to `public/` and open:

```
http://localhost/v1/health
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/captcha/generate` | Generate a new captcha |
| GET | `/v1/captcha/{filename}` | Serve captcha image |
| POST | `/v1/captcha/validate` | Validate user answer |
| GET | `/v1/status` | API status and pool info |
| GET | `/v1/health` | Health check |

### Generate a captcha

```http
GET /v1/captcha/generate?scale=1&chars=4
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "a1b2c3d4e5f6...",
    "image_url": "http://yourserver/v1/captcha/abc123.png",
    "expires_in": 600,
    "scale": 1,
    "chars": 4,
    "dimensions": { "width": 400, "height": 100 }
  },
  "meta": {
    "version": "1.0.0",
    "remaining": { "minute": 27, "hour": 2984, "day": 9990 }
  }
}
```

### Validate an answer

```http
POST /v1/captcha/validate
Content-Type: application/json

{ "token": "a1b2c3d4...", "answer": "A3B7" }
```

---

## Integration Example (HTML + JS)

```html
<img id="captcha-img" src="" alt="captcha">
<input type="text" id="captcha-answer" placeholder="Enter the characters">
<button onclick="loadCaptcha()">Refresh</button>

<script>
let captchaToken = '';

async function loadCaptcha() {
  const res  = await fetch('http://yourserver/v1/captcha/generate?scale=1&chars=4');
  const data = await res.json();
  captchaToken = data.data.token;
  document.getElementById('captcha-img').src = data.data.image_url;
}

async function submitCaptcha() {
  const answer = document.getElementById('captcha-answer').value;
  const res = await fetch('http://yourserver/v1/captcha/validate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token: captchaToken, answer })
  });
  const data = await res.json();
  alert(data.data.valid ? 'Success!' : 'Wrong answer, try again.');
}

loadCaptcha();
</script>
```

---

## Rate Limits

| Scope | Window | Limit | HTTP Code |
|-------|--------|-------|-----------|
| Global | 1 minute | 30 | 429 |
| Global | 1 hour | 3,000 | 429 |
| Global | 1 day | 10,000 | 503 |
| Per user | 1 second | 1 | 429 |
| Per user | 1 minute | 10 | 429 |
| Per user | 1 hour | 60 | 429 |
| Per user | 1 day | 120 | 429 |

---

## Documentation

- [Getting Started](docs/getting-started.md)
- [API Reference](docs/api-reference.md)
- [Web Integration](docs/integration-web.md)
- [Desktop Integration](docs/integration-desktop.md)
- [Self-Hosting Guide](docs/self-hosting.md)
- [Architectural Decisions](DECISIONS.md)
- 🌐 [Official API Docs — dcaptcha.desknect.com](https://dcaptcha.desknect.com/api-documentacao)

---

## Support & Donate

DeskCaptcha is free and open source. If it helps your project, consider supporting its development:

- ❤️ [Donate — desknect.com/donate](https://desknect.com/donate)
- 🌐 [Desknect.com](https://desknect.com)

---

## License

MIT © [Desknect.com](https://desknect.com)