# Getting Started — DeskCaptcha

> 🌐 [desknect.com](https://desknect.com) &nbsp;|&nbsp; 📖 [Official Docs](https://dcaptcha.desknect.com/api-documentacao) &nbsp;|&nbsp; ❤️ [Donate](https://desknect.com/donate)

## Requirements

- PHP 8.0 or higher
- Extensions: `gd`, `sqlite3`, `pdo_sqlite`
- Apache or Nginx (or PHP built-in server for development)

---

## Installation

```bash
git clone https://github.com/desknect/deskcaptcha.git
cd deskcaptcha
chmod -R 775 storage/ database/
```

---

## Apache Configuration

Point `DocumentRoot` to the `public/` directory:

```apache
<VirtualHost *:80>
    ServerName deskcaptcha.local
    DocumentRoot /var/www/deskcaptcha/public
    <Directory /var/www/deskcaptcha/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable `mod_rewrite`:
```bash
a2enmod rewrite && systemctl restart apache2
```

---

## Nginx Configuration

```nginx
server {
    listen 80;
    server_name deskcaptcha.local;
    root /var/www/deskcaptcha/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(sqlite|json|log)$ { deny all; }
}
```

---

## PHP Built-in Server (Development)

```bash
cd public
php -S localhost:8080
```

Then visit: `http://localhost:8080/v1/health`

---

## Configuration

Copy and edit the config files in `config/`:

```php
// config/api.php — set your base URL
'base_url' => 'https://yourdomain.com',

// Enable API key enforcement
'require_api_key' => true,
'api_keys' => ['your-secret-key'],

// config/cors.php — restrict to your domains
'allowed_origins' => ['https://yourapp.com'],
```

Or use environment variables:
```bash
export API_BASE_URL=https://yourdomain.com
export REQUIRE_API_KEY=true
export API_KEYS=your-secret-key
export CORS_ORIGINS=https://yourapp.com
```

---

## Verify Installation

```bash
curl http://localhost:8080/v1/health
```

Expected response:
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
