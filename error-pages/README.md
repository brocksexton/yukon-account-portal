# Club Penguin Styled Error & Utility Pages

This bundle provides a set of drop-in, Club Penguin themed pages styled to match the existing site look.

## Included pages

- `400.php` – Bad Request
- `401.php` – Unauthorized (login required)
- `403.php` – Forbidden
- `404.php` – Not Found
- `408.php` – Request Timeout
- `410.php` – Gone
- `429.php` – Too Many Requests
- `500.php` – Internal Server Error
- `502.php` – Bad Gateway
- `504.php` – Gateway Timeout
- `maintenance.php` – Lore-friendly maintenance page (uses HTTP 503)
- `not_ready.php` – “This area isn’t ready yet” page (uses HTTP 501)

All pages share the same layout and styling via:
- `partials/ep_layout.php`
- `css/base.css`
- `assets/` (reuses existing Parent Center assets)

## Suggested Apache configuration

If you want Apache to automatically serve these pages, place this folder somewhere web-accessible (e.g. `/errors/`) and add:

```
ErrorDocument 400 /errors/400.php
ErrorDocument 401 /errors/401.php
ErrorDocument 403 /errors/403.php
ErrorDocument 404 /errors/404.php
ErrorDocument 408 /errors/408.php
ErrorDocument 410 /errors/410.php
ErrorDocument 429 /errors/429.php
ErrorDocument 500 /errors/500.php
ErrorDocument 502 /errors/502.php
ErrorDocument 504 /errors/504.php
```

For planned downtime, you can point traffic to `maintenance.php` (503).

