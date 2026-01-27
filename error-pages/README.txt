Club Penguin – Error & Utility Pages

Included pages:
  400.php – Bad Request
  401.php – Not Authenticated
  403.php – Forbidden
  404.php – Not Found
  500.php – Internal Server Error
  502.php – Bad Gateway
  503.php – Maintenance (Service Unavailable)
  construction.php – lore-friendly construction notice
  not_ready.php – "this part isn't ready" page

Suggested Apache config (example):
  ErrorDocument 400 /errors/400.php
  ErrorDocument 401 /errors/401.php
  ErrorDocument 403 /errors/403.php
  ErrorDocument 404 /errors/404.php
  ErrorDocument 500 /errors/500.php
  ErrorDocument 502 /errors/502.php
  ErrorDocument 503 /errors/503.php

Place this folder at: /errors (or any path), and adjust links accordingly.
