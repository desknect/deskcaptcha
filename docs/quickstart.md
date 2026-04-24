# Quickstart — DeskCaptcha in 5 Minutes

> 🌐 [desknect.com](https://desknect.com) &nbsp;|&nbsp; 📖 [Official Docs](https://dcaptcha.desknect.com/api-documentacao) &nbsp;|&nbsp; ❤️ [Donate](https://desknect.com/donate)

This guide gets you from zero to a working captcha integration in under 5 minutes.

---

## Prerequisites

- DeskCaptcha API running and accessible (see [Getting Started](getting-started.md))
- Your API URL — e.g. `http://localhost/v1` or `https://api.yourserver.com/v1`

---

## Step 1 — Verify the API is alive (30 seconds)

```bash
curl https://your-api/v1/health
```

Expected response:
```json
{ "success": true, "data": { "status": "healthy" } }
```

If you see this, you're ready. If not, check [Getting Started](getting-started.md).

---

## Step 2 — Generate your first captcha (1 minute)

```bash
curl "https://your-api/v1/captcha/generate?scale=1&chars=4"
```

You'll get back:
```json
{
  "data": {
    "token": "abc123...",
    "image_url": "https://your-api/v1/captcha/xYz12345.png",
    "expires_in": 600
  }
}
```

Open `image_url` in your browser. You should see the captcha image.

---

## Step 3 — Validate an answer (1 minute)

```bash
curl -X POST "https://your-api/v1/captcha/validate" \
  -H "Content-Type: application/json" \
  -d '{"token": "abc123...", "answer": "A3B7"}'
```

Response if correct:
```json
{ "data": { "valid": true } }
```

Response if wrong:
```json
{ "data": { "valid": false } }
```

---

## Step 4 — Add to your form (2 minutes)

Copy this minimal snippet into your HTML page:

```html
<!-- 1. Place where you want the captcha -->
<img id="captcha-img" alt="captcha" height="100">
<button type="button" onclick="loadCaptcha()">↺ Refresh</button>
<input type="text" id="captcha-answer" placeholder="Type the characters"
       oninput="this.value=this.value.toUpperCase()" autocomplete="off">

<!-- 2. Add this script -->
<script>
// ⚠ Change this to your actual API URL
const CAPTCHA_API = 'https://your-api/v1';

let captchaToken = '';

async function loadCaptcha() {
  const res  = await fetch(`${CAPTCHA_API}/captcha/generate?scale=1&chars=4`);
  const json = await res.json();
  captchaToken = json.data.token;
  document.getElementById('captcha-img').src = json.data.image_url + '?t=' + Date.now();
}

// Call this BEFORE submitting your form
async function verifyCaptcha() {
  const answer = document.getElementById('captcha-answer').value.trim();
  const res  = await fetch(`${CAPTCHA_API}/captcha/validate`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token: captchaToken, answer })
  });
  const json = await res.json();
  return json.data?.valid === true;
}

// Load on page ready
loadCaptcha();
</script>
```

In your form submit handler:
```js
myForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const valid = await verifyCaptcha();
  if (!valid) {
    alert('Wrong captcha. Try again.');
    loadCaptcha();
    return;
  }
  // ✅ Captcha passed — proceed with your form submission
});
```

---

## Integration Checklist

Use this checklist before going to production:

- [ ] API URL is set to your production server (HTTPS)
- [ ] Captcha loads automatically on page load
- [ ] Refresh button works and loads a new image
- [ ] Wrong answer shows an error and reloads the captcha
- [ ] Correct answer allows the form to proceed
- [ ] **Validation is done server-side** (not just in the browser)
- [ ] CORS is configured in `config/cors.php` to allow only your domain
- [ ] API key is enabled (`require_api_key = true`) for production
- [ ] Captcha auto-reloads if it expires (10 min TTL)

---

## Common Errors & Fixes

| Error | Cause | Fix |
|-------|-------|-----|
| `Could not connect to API` | Wrong URL or server down | Check `CAPTCHA_API` value and run `curl /v1/health` |
| `404 Endpoint not found` | Wrong path | Make sure URL ends with `/v1` not `/v1/` |
| `CORS error in browser` | Origin not allowed | Add your domain to `config/cors.php` |
| `410 Expired` | Captcha older than 10 min | Call `loadCaptcha()` when form loads; add auto-refresh |
| `409 Already used` | Token used twice | Each token is single-use; always reload after validation |
| `429 Too many requests` | Rate limit hit | Wait for `Retry-After` seconds; reduce request frequency |
| `valid: false` always | Answer is wrong or case mismatch | Auto-uppercase the input field |
| Image not loading | CORS or wrong `image_url` | Check that `base_url` in `config/api.php` is correct |

---

## Try the Interactive Demo

Open `demo/index.html` to see the full integration working live:

```
http://localhost/deskcaptcha/demo/index.html
```

- Change the API URL to point to your server
- Switch between scale 1/2/3 and 4/6/8 chars
- Test the full generate → validate → success/error flow

---

## Next Steps

- [API Reference](api-reference.md) — full endpoint documentation
- [Web Integration](integration-web.md) — React, PHP backend, error handling
- [Desktop Integration](integration-desktop.md) — Python, C#, local server
- [Self-Hosting Guide](self-hosting.md) — Apache, Nginx, Docker
