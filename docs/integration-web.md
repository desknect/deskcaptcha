# Web Integration — DeskCaptcha

> 🌐 [desknect.com](https://desknect.com) &nbsp;|&nbsp; 📖 [Official Docs](https://dcaptcha.desknect.com/api-documentacao) &nbsp;|&nbsp; ❤️ [Donate](https://desknect.com/donate)

## Live Demo

Before integrating, try the live demo at `demo/index.html` — it shows the full flow working in a login form and lets you test different scales and character counts against your own API URL.

---

## How It Works — Full Flow

```
Browser                          Your Server                    DeskCaptcha API
  │                                    │                               │
  │── page loads ──────────────────────┤                               │
  │                                    │── GET /v1/captcha/generate ──▶│
  │                                    │◀─ { token, image_url } ───────│
  │◀── show captcha image ─────────────┤                               │
  │                                    │                               │
  │── user types + submits ────────────▶                               │
  │                                    │── POST /v1/captcha/validate ─▶│
  │                                    │◀─ { valid: true/false } ──────│
  │                                    │                               │
  │◀── success or error ───────────────┤                               │
```

**Key rule:** always call `/validate` from your **server side**, never directly from the browser in production. This prevents clients from bypassing captcha verification.

---

## Step-by-Step Integration

### Step 1 — Set your API base URL

```js
const API = 'https://your-deskcaptcha-server/v1';
```

### Step 2 — Generate a captcha on page load

```js
let captchaToken = '';

async function loadCaptcha() {
  // scale: 1 (400×100), 2 (800×200), 3 (1200×300)
  // chars: 4, 6, or 8 — always letter+number pattern
  const res  = await fetch(`${API}/captcha/generate?scale=1&chars=4`);
  const json = await res.json();

  captchaToken = json.data.token;      // save for validation
  document.getElementById('captcha-img').src = json.data.image_url;
}

loadCaptcha(); // call on page load
```

### Step 3 — Display the image

```html
<img id="captcha-img" alt="captcha" style="height:100px">
<button type="button" onclick="loadCaptcha()">↺ Refresh</button>
<input type="text" id="captcha-answer" autocomplete="off" placeholder="Type the characters">
```

**Tip:** auto-uppercase the input so users don't worry about case:
```js
document.getElementById('captcha-answer').addEventListener('input', function() {
  this.value = this.value.toUpperCase();
});
```

### Step 4 — Send token + answer to your backend

```js
// On form submit, send token + answer to YOUR server (not directly to DeskCaptcha)
async function submitForm(answer) {
  const res = await fetch('/your-api/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      email: '...',
      password: '...',
      captcha_token:  captchaToken,
      captcha_answer: answer,
    })
  });
  return res.json();
}
```

### Step 5 — Validate on your backend (PHP example)

```php
<?php
// In your login handler, validate before processing credentials:

function validateCaptcha(string $token, string $answer): bool {
    $res = file_get_contents(
        'https://your-deskcaptcha-server/v1/captcha/validate',
        false,
        stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode(['token' => $token, 'answer' => $answer]),
                'ignore_errors' => true,
            ]
        ])
    );
    $data = json_decode($res, true);
    return $data['data']['valid'] ?? false;
}

// Usage:
if (!validateCaptcha($_POST['captcha_token'], $_POST['captcha_answer'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Captcha verification failed.']);
    exit;
}

// Proceed with login logic...
```

### Step 6 — Handle responses in the browser

```js
// After your server responds:
if (result.error === 'Captcha verification failed.') {
  showError('Wrong characters. Try again.');
  loadCaptcha(); // always reload after a failed attempt
} else {
  showSuccess('Signed in!');
}
```

---

## Error Handling

| HTTP Code | Meaning | What to do |
|-----------|---------|------------|
| 200 `valid: false` | Wrong answer | Show error + reload captcha |
| 404 | Token not found | Reload captcha |
| 409 | Already used | Reload captcha |
| 410 | Expired (>10 min) | Reload captcha automatically |
| 422 | Missing token or answer | Check your request body |
| 429 | Rate limit (user or global) | Show `Retry-After` countdown |
| 503 | Daily global limit | Show "service temporarily unavailable" |

```js
async function loadCaptcha() {
  try {
    const res  = await fetch(`${API}/captcha/generate?scale=1&chars=4`);
    if (res.status === 429) {
      const retryAfter = res.headers.get('Retry-After') || 60;
      showError(`Too many requests. Please wait ${retryAfter} seconds.`);
      return;
    }
    if (res.status === 503) {
      showError('Service temporarily unavailable. Try again later.');
      return;
    }
    const json = await res.json();
    captchaToken = json.data.token;
    document.getElementById('captcha-img').src = json.data.image_url;
  } catch (e) {
    showError('Could not reach captcha server. Check your connection.');
  }
}
```

---

## Best Practices

- **Always validate server-side.** Never trust a client-side "valid=true" — always call `/validate` from your backend.
- **Reload on failure.** After any failed validation, always load a fresh captcha.
- **Reload on expiry.** Captchas expire after 10 minutes. If your form is long, add an auto-refresh timer.
- **Auto-uppercase.** CAPTCHA answers are case-insensitive on the server, but auto-uppercasing the input reduces user friction.
- **Cache-bust the image URL.** Append `?t=Date.now()` to the image URL to prevent browser caching when refreshing.
- **Don't show the answer.** Never expose the token answer in your HTML/JS — the token is an opaque reference.
- **One token = one use.** Each token can only be validated once. After use, it is invalidated.

---

## React Component

```jsx
import { useState, useEffect, useRef } from 'react';

const API = 'https://your-deskcaptcha-server/v1';

export function CaptchaField({ onVerified }) {
  const [token,   setToken]   = useState('');
  const [imgUrl,  setImgUrl]  = useState('');
  const [answer,  setAnswer]  = useState('');
  const [status,  setStatus]  = useState({ msg: '', type: '' });
  const [loading, setLoading] = useState(false);

  const load = async () => {
    setLoading(true);
    setAnswer('');
    setStatus({ msg: '', type: '' });
    try {
      const res  = await fetch(`${API}/captcha/generate?scale=2&chars=4`);
      const data = await res.json();
      setToken(data.data.token);
      setImgUrl(data.data.image_url + '?t=' + Date.now());
    } catch {
      setStatus({ msg: 'Could not load captcha.', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const handleVerify = async () => {
    if (!answer.trim()) return;
    setLoading(true);
    try {
      const res  = await fetch(`${API}/captcha/validate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, answer: answer.toUpperCase() }),
      });
      const data = await res.json();
      if (data.data?.valid) {
        setStatus({ msg: '✓ Verified!', type: 'success' });
        onVerified(token); // pass token to parent for server-side re-validation
      } else {
        setStatus({ msg: '✗ Wrong. Try the new captcha.', type: 'error' });
        load();
      }
    } catch {
      setStatus({ msg: 'Validation error.', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="captcha-field">
      <div className="captcha-image-row">
        {imgUrl
          ? <img src={imgUrl} alt="captcha" height={100} />
          : <div className="captcha-skeleton">Loading...</div>
        }
        <button onClick={load} disabled={loading} title="Refresh">↺</button>
      </div>
      <input
        value={answer}
        onChange={e => setAnswer(e.target.value.toUpperCase())}
        placeholder="Type the characters"
        maxLength={8}
        disabled={loading}
      />
      <button onClick={handleVerify} disabled={loading || !answer}>Verify</button>
      {status.msg && <p className={`captcha-status ${status.type}`}>{status.msg}</p>}
    </div>
  );
}
```

---

## Plain HTML — Minimal Example

```html
<img id="captcha-img" alt="captcha" height="100">
<button onclick="loadCaptcha()">↺ Refresh</button>
<input type="text" id="answer" placeholder="Type characters" oninput="this.value=this.value.toUpperCase()">
<button onclick="submit()">Submit</button>
<p id="msg"></p>

<script>
const API = 'https://your-deskcaptcha-server/v1';
let token = '';

async function loadCaptcha() {
  const r = await fetch(`${API}/captcha/generate?scale=1&chars=4`);
  const d = await r.json();
  token = d.data.token;
  document.getElementById('captcha-img').src = d.data.image_url + '?t=' + Date.now();
}

async function submit() {
  const answer = document.getElementById('answer').value;
  const r = await fetch(`${API}/captcha/validate`, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ token, answer })
  });
  const d = await r.json();
  document.getElementById('msg').textContent = d.data.valid ? '✅ Correct!' : '❌ Wrong, try again.';
  if (!d.data.valid) loadCaptcha();
}

loadCaptcha();
</script>
```
