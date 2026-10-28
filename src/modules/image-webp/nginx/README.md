# Nginx integration — not enabled

No ready-to-paste Nginx `location` directive is included in the pilot because introducing a `^~` rule can bypass existing PHP-upload denial rules, while regular-expression location order and `try_files` negotiation require careful validation. Review the full live server configuration before adding any substitution.

Intended behavior: on `/upload/iblock/*.jpg|jpeg|png` and `/upload/resize_cache/*.jpg|jpeg|png` requests, serve `original.ext.webp` only when the browser sends `Accept: image/webp` and the sidecar exists. Otherwise serve original; maintain `Vary: Accept`, appropriate `Content-Type`, all access restrictions and any CDN/cache key policy. Check `nginx -t` and HTTP responses before reload.
