# Changelog

All notable changes to DeskCaptcha will be documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
This project adheres to [Semantic Versioning](https://semver.org/).

---

## [1.0.0] - 2026-04-23

### Added
- Initial public release by Desknect.com
- REST API with endpoints: `/v1/captcha/generate`, `/v1/captcha/validate`, `/v1/captcha/{filename}`, `/v1/status`, `/v1/health`
- Configurable scale (1×, 2×, 3×) and character count (4, 6, 8)
- 30 fonts, 30 colors, configurable shapes (circle, triangle, text overlays)
- Global rate limiting: 30/min, 3000/hour, 10000/day
- Per-user rate limiting via IP + fingerprint: 1/sec, 10/min, 60/hour, 120/day
- Monthly SQLite database rotation with automatic pre-creation on day 20
- Image pool management (~50 images), auto-cleanup of expired and used captchas
- CORS middleware with configurable origin whitelist
- Optional API key enforcement
- LOCAL_MODE for internal network deployments
- Full documentation: getting-started, API reference, web/desktop integration, self-hosting
- MIT License
