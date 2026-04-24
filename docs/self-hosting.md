# Self-Hosting Guide — DeskCaptcha

> 🌐 [desknect.com](https://desknect.com) &nbsp;|&nbsp; 📖 [Official Docs](https://dcaptcha.desknect.com/api-documentacao) &nbsp;|&nbsp; ❤️ [Donate](https://desknect.com/donate)

---

## Shared Hosting (cPanel/Plesk)

1. Upload all files to your hosting account
2. Set the domain document root to the `public/` folder
3. Ensure PHP 8.0+ is active and GD/SQLite extensions are enabled
4. Make `storage/` and `database/` writable: `chmod 775`
5. Edit `config/api.php` and set `base_url` to your domain

---

## VPS / Dedicated Server (Apache)

```bash
git clone https://github.com/desknect/deskcaptcha.git /var/www/deskcaptcha
chmod -R 775 /var/www/deskcaptcha/storage /var/www/deskcaptcha/database
chown -R www-data:www-data /var/www/deskcaptcha
```

Apache vhost:
```apache
<VirtualHost *:80>
    ServerName api.yourdomain.com
    DocumentRoot /var/www/deskcaptcha/public
    <Directory /var/www/deskcaptcha/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## Docker

```dockerfile
FROM php:8.2-apache
RUN apt-get update && apt-get install -y libgd-dev libsqlite3-dev \
    && docker-php-ext-configure gd \
    && docker-php-ext-install gd pdo pdo_sqlite
COPY . /var/www/html/
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' \
    /etc/apache2/sites-available/000-default.conf \
    && a2enmod rewrite
RUN chmod -R 775 /var/www/html/storage /var/www/html/database
EXPOSE 80
```

```bash
docker build -t deskcaptcha .
docker run -p 8080:80 \
  -e API_BASE_URL=http://localhost:8080 \
  -e LOCAL_MODE=false \
  deskcaptcha
```

---

## Internal Network (LAN)

For corporate intranets or internal tools, enable local mode to disable CORS:

```php
// config/api.php
'local_mode' => true,
'base_url'   => 'http://192.168.1.100:8080',
```

Or via env: `LOCAL_MODE=true`

---

## Environment Variables Reference

| Variable | Default | Description |
|----------|---------|-------------|
| `API_BASE_URL` | `http://localhost/deskcaptcha/public` | Public base URL |
| `REQUIRE_API_KEY` | `false` | Enforce API key |
| `API_KEYS` | _(empty)_ | Comma-separated keys |
| `LOCAL_MODE` | `false` | Disable CORS |
| `CORS_ORIGINS` | `*` | Allowed origins |
| `CAPTCHA_TTL` | `600` | Seconds before expiry |
| `POOL_SIZE` | `50` | Target pool size |
| `GLOBAL_LIMIT_MINUTE` | `30` | Global req/min |
| `GLOBAL_LIMIT_HOUR` | `3000` | Global req/hour |
| `GLOBAL_LIMIT_DAY` | `10000` | Global req/day |
| `USER_LIMIT_SECOND` | `1` | User req/sec |
| `USER_LIMIT_MINUTE` | `10` | User req/min |
| `USER_LIMIT_HOUR` | `60` | User req/hour |
| `USER_LIMIT_DAY` | `120` | User req/day |

---

## Security Checklist

- [ ] Set `base_url` to your actual domain (HTTPS in production)
- [ ] Restrict CORS origins in `config/cors.php`
- [ ] Enable API key enforcement for production (`require_api_key = true`)
- [ ] Ensure `database/`, `storage/`, `config/` are not web-accessible (`.htaccess` does this automatically)
- [ ] Use HTTPS (SSL certificate via Let's Encrypt)
- [ ] Keep PHP updated to latest 8.x patch
